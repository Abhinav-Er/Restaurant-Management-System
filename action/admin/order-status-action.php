<?php
// Moves an order to its next status. All the rules are checked here, on the server.
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../config/db.php';

// Pages this action may send staff back to
$return = in_array($_POST['return'] ?? '', ['orders.php', 'kitchen.php'], true) ? $_POST['return'] : 'orders.php';

function finish(string $return, string $type, string $code): void {
    global $pdo;
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../../admin/' . $return . '?' . $type . '=' . $code);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../admin/orders.php');
    exit;
}
if (empty($_SESSION['admin_id'])) {
    header('Location: ../../admin/login.php?error=login');
    exit;
}

$role     = $_SESSION['admin_role'];
$order_id = (int) ($_POST['order_id'] ?? 0);
$new      = $_POST['status'] ?? '';

// Which statuses each role may set. Kitchen staff cannot cancel orders.
$role_can = [
    'admin'   => ['preparing', 'ready', 'delivered', 'cancelled'],
    'manager' => ['preparing', 'ready', 'delivered', 'cancelled'],
    'kitchen' => ['preparing', 'ready', 'delivered'],
];
if (!in_array($new, $role_can[$role] ?? [], true)) {
    finish($return, 'error', 'denied');
}

// Which change is allowed from each current status
$transitions = [
    'placed'    => ['cancelled'],
    'paid'      => ['preparing', 'cancelled'],
    'preparing' => ['ready', 'cancelled'],
    'ready'     => ['delivered', 'cancelled'],
];

try {
    $pdo->beginTransaction();

    // Lock the order so two people pressing a button at once cannot clash
    $stmt = $pdo->prepare(
        'SELECT customer_id, status,
                (scheduled_time IS NOT NULL AND scheduled_time > NOW() + INTERVAL 30 MINUTE) AS too_early
         FROM orders WHERE order_id = ? FOR UPDATE'
    );
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order || !in_array($new, $transitions[$order['status']] ?? [], true)) {
        finish($return, 'error', 'invalid');
    }
    // Kitchen staff cannot start a scheduled order early. Admin and manager can.
    if ($new === 'preparing' && $order['too_early'] && $role === 'kitchen') {
        finish($return, 'error', 'early');
    }

    $pdo->prepare('UPDATE orders SET status = ? WHERE order_id = ?')->execute([$new, $order_id]);

    // Cancelling puts the dishes back into stock
         // Cancelling puts the dishes back into stock and settles the payment
    $refunded = false;
    if ($new === 'cancelled') {
        $pdo->prepare(
            'UPDATE menu_item m JOIN order_item oi ON oi.item_id = m.item_id
             SET m.stock_quantity = m.stock_quantity + oi.quantity
             WHERE oi.order_id = ?'
        )->execute([$order_id]);

        // A paid order is marked refunded. An unpaid one just closes its pending payment.
        $refund = $pdo->prepare("UPDATE payment SET payment_status = 'refunded', refunded_at = NOW() WHERE order_id = ? AND payment_status = 'success'");
        $refund->execute([$order_id]);
        $refunded = $refund->rowCount() > 0;
        $pdo->prepare("UPDATE payment SET payment_status = 'failed' WHERE order_id = ? AND payment_status = 'pending'")->execute([$order_id]);
    }

    // Tell the customer
    $messages = [
        'preparing' => "Your order #$order_id is now being prepared.",
        'ready'     => "Your order #$order_id is ready. Please collect it or wait at your table.",
        'delivered' => "Order #$order_id was delivered. Enjoy your meal!",
        'cancelled' => $refunded ? "Your order #$order_id was cancelled and your payment has been refunded."
            : "Your order #$order_id was cancelled.",
    ];
    $note = $pdo->prepare('INSERT INTO notification (customer_id, type, message, link) VALUES (?, ?, ?, ?)');
    $note->execute([$order['customer_id'], 'status', $messages[$new], 'track-order.php?id=' . $order_id]);
    if ($new === 'delivered') {
        $note->execute([$order['customer_id'], 'review', "How was your order #$order_id? Rate your dishes.", 'order-history.php']);
    }

    $pdo->commit();
} catch (Throwable $e) {
    error_log('Order status change failed: ' . $e->getMessage());
    finish($return, 'error', 'failed');
}

finish($return, 'success', $new === 'cancelled' ? 'cancelled' : 'updated');