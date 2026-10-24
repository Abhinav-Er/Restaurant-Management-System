<?php
// Handles the checkout form: checks everything on the server, saves the order,
// its dishes and its payment, reduces stock, then opens the tracking page.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/payment-helpers.php';

date_default_timezone_set('Asia/Kolkata');   // XAMPP defaults to UTC, which would break the scheduling rules

const OPEN_MINUTES     = 11 * 60;   // 11:00
const CLOSE_MINUTES    = 23 * 60;   // 23:00
const MIN_LEAD_MINUTES = 30;
const MAX_QTY_PER_DISH = 20;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/checkout.php');
    exit;
}
if (empty($_SESSION['customer_id'])) {
    header('Location: ../public/login.php?error=login&next=checkout.php');
    exit;
}
$customer_id = (int) $_SESSION['customer_id'];

// Sends the customer back to checkout with an error code. Undoes any half-saved order first.
function fail(string $code): void {
    global $pdo;
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../public/checkout.php?error=' . $code);
    exit;
}

// ---------- 1. Read and check what the customer sent ----------
$name  = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$notes = trim($_POST['notes'] ?? '');
if ($name === '' || mb_strlen($name) > 100 || !preg_match('/^[6-9]\d{9}$/', $phone) || mb_strlen($notes) > 200) {
    fail('details');
}

$order_type = $_POST['order_type'] ?? '';
if (!in_array($order_type, ['dinein', 'takeaway'], true)) {
    fail('details');
}
$table_no = $order_type === 'dinein' ? trim($_POST['table_no'] ?? '') : '';
if (mb_strlen($table_no) > 10) {
    fail('details');
}

$payment_method = $_POST['payment_method'] ?? '';
if (!in_array($payment_method, ['upi', 'card'], true)) {
    fail('details');
}

// Scheduled time: at least 30 minutes ahead and inside opening hours
$scheduled = null;
if (($_POST['timing'] ?? 'asap') === 'later') {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['scheduled_time'] ?? '');
    if (!$dt) {
        fail('schedule');
    }
    $minutes = (int) $dt->format('G') * 60 + (int) $dt->format('i');
    if ($dt < new DateTime('+' . MIN_LEAD_MINUTES . ' minutes') || $minutes < OPEN_MINUTES || $minutes > CLOSE_MINUTES) {
        fail('schedule');
    }
    $scheduled = $dt->format('Y-m-d H:i:s');
}

// The cart: only the dish ids and quantities are used. Prices always come from the database.
$cart = json_decode($_POST['cart_json'] ?? '', true);
if (!is_array($cart) || count($cart) === 0 || count($cart) > 30) {
    fail('cart');
}
$wanted = [];   // dish id => quantity
foreach ($cart as $line) {
    $id  = (int) ($line['id'] ?? 0);
    $qty = (int) ($line['qty'] ?? 0);
    if ($id < 1 || $qty < 1 || $qty > MAX_QTY_PER_DISH) {
        fail('cart');
    }
    $wanted[$id] = ($wanted[$id] ?? 0) + $qty;
    if ($wanted[$id] > MAX_QTY_PER_DISH) {
        fail('cart');
    }
}
   expire_unpaid_orders($pdo);   // frees stock held by orders that were never paid

// ---------- 2. Save everything, all or nothing ----------
try {
    $pdo->beginTransaction();

    // Lock these dish rows so two customers can't take the last portion at the same moment
    $ids = array_keys($wanted);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT item_id, name, price, stock_quantity, is_available
         FROM menu_item WHERE item_id IN ($marks) FOR UPDATE"
    );
    $stmt->execute($ids);
    $dishes = [];
    foreach ($stmt->fetchAll() as $row) {
        $dishes[(int) $row['item_id']] = $row;
    }

    // Money is worked out in paise (whole numbers) so there are no rounding surprises
    $subtotal = 0;
    $lines = [];
    foreach ($wanted as $id => $qty) {
        if (!isset($dishes[$id])) {
            fail('cart');
        }
        $d = $dishes[$id];
        if (!$d['is_available'] || (int) $d['stock_quantity'] < $qty) {
            fail('stock');
        }
        $unit = (int) round($d['price'] * 100);
        $lines[] = ['id' => $id, 'qty' => $qty, 'unit' => $unit, 'sub' => $unit * $qty];
        $subtotal += $unit * $qty;
    }

    // Coupon: checked here, whatever the page showed
    $coupon_id = null;
    $discount  = 0;
    $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
    if ($code !== '') {
        $c = $pdo->prepare(
            'SELECT coupon_id, discount_type, discount_value, min_order_amount
             FROM coupon
             WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())'
        );
        $c->execute([$code]);
        $coupon = $c->fetch();
        if (!$coupon || $subtotal < (int) round($coupon['min_order_amount'] * 100)) {
            fail('coupon');
        }
        $coupon_id = (int) $coupon['coupon_id'];
        $discount  = $coupon['discount_type'] === 'percent'
            ? (int) round($subtotal * $coupon['discount_value'] / 100)
            : (int) round($coupon['discount_value'] * 100);
        $discount = min($discount, $subtotal);   // a discount can never make the bill negative
    }
    $total = $subtotal - $discount;

    // The order. Payment is confirmed below, so it starts as "paid".
    $ins = $pdo->prepare(
        'INSERT INTO orders (customer_id, contact_name, contact_phone, order_type, table_no, notes,
                             scheduled_time, status, coupon_id, discount_amount, total_amount)
         VALUES (?, ?, ?, ?, ?, ?, ?, "paid", ?, ?, ?)'
    );
    $ins->execute([
        $customer_id, $name, $phone, $order_type,
        $table_no !== '' ? $table_no : null,
        $notes !== '' ? $notes : null,
        $scheduled, $coupon_id,
        number_format($discount / 100, 2, '.', ''),
        number_format($total / 100, 2, '.', ''),
    ]);
    $order_id = (int) $pdo->lastInsertId();

    // The dishes, saved with the price at this moment, and stock reduced
    $line_ins = $pdo->prepare(
        'INSERT INTO order_item (order_id, item_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)'
    );
    $stock_upd = $pdo->prepare('UPDATE menu_item SET stock_quantity = stock_quantity - ? WHERE item_id = ?');
    foreach ($lines as $l) {
        $line_ins->execute([
            $order_id, $l['id'], $l['qty'],
            number_format($l['unit'] / 100, 2, '.', ''),
            number_format($l['sub'] / 100, 2, '.', ''),
        ]);
        $stock_upd->execute([$l['qty'], $l['id']]);
    }

        // Payment record. It stays "pending" until the customer pays on the payment page.
    $pay = $pdo->prepare(
        'INSERT INTO payment (order_id, amount, payment_method, payment_status)
         VALUES (?, ?, ?, "pending")'
    );
    $pay->execute([
        $order_id,
        number_format($total / 100, 2, '.', ''),
        $payment_method,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Place order failed: ' . $e->getMessage());
    fail('failed');
}

 
header('Location: ../public/payment.php?id=' . $order_id . '&placed=1');
exit;