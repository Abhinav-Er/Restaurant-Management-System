<?php
// Records the result of a payment attempt, or cancels the unpaid order.
// (Test mode: the result comes from the buttons. A real gateway would send a verified result instead.)
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/payment-helpers.php';

function go(string $url): void {
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    go('../public/order-history.php');
}
if (empty($_SESSION['customer_id'])) {
    go('../public/login.php?error=login&next=order-history.php');
}
$customer_id = (int) $_SESSION['customer_id'];

$order_id = (int) ($_POST['order_id'] ?? 0);
$outcome  = $_POST['outcome'] ?? '';
$method   = $_POST['method'] ?? '';
if ($order_id < 1 || !in_array($outcome, ['success', 'fail', 'cancel'], true) || !in_array($method, ['upi', 'card'], true)) {
    go('../public/order-history.php');
}

try {
    $pdo->beginTransaction();

    // Lock the order so a double click or a second tab cannot pay it twice
    $stmt = $pdo->prepare(
        'SELECT o.status, p.payment_status,
                (o.order_date < NOW() - INTERVAL ' . PAYMENT_WINDOW_MINUTES . ' MINUTE) AS expired
         FROM orders o JOIN payment p ON p.order_id = o.order_id
         WHERE o.order_id = ? AND o.customer_id = ? FOR UPDATE'
    );
    $stmt->execute([$order_id, $customer_id]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->rollBack();
        go('../public/order-history.php');
    }
    if (in_array($row['payment_status'], ['success', 'refunded'], true)) {   // already paid
        $pdo->commit();
        go('../public/track-order.php?id=' . $order_id);
    }
    if ($row['status'] !== 'placed') {                                       // cancelled earlier
        $pdo->commit();
        go('../public/payment.php?id=' . $order_id);
    }
    if ($row['expired']) {
        release_unpaid_order($pdo, $order_id, 'expired');
        $pdo->commit();
        go('../public/payment.php?id=' . $order_id . '&error=expired');
    }

    $note = $pdo->prepare('INSERT INTO notification (customer_id, type, message, link) VALUES (?, ?, ?, ?)');
    $link = 'track-order.php?id=' . $order_id;

    if ($outcome === 'cancel') {
        release_unpaid_order($pdo, $order_id, 'customer');
        $pdo->commit();
        go('../public/order-history.php?success=ordercancelled');
    }

    if ($outcome === 'fail') {
        $pdo->prepare("UPDATE payment SET payment_method = ?, payment_status = 'failed' WHERE order_id = ?")
            ->execute([$method, $order_id]);
        $note->execute([$customer_id, 'payment', "Payment failed for order #$order_id. You can try again before it expires.", 'payment.php?id=' . $order_id]);
        $pdo->commit();
        go('../public/payment.php?id=' . $order_id . '&error=declined');
    }

    // Success
    $pdo->prepare(
        "UPDATE payment SET payment_method = ?, payment_status = 'success', transaction_ref = ?, paid_at = NOW()
         WHERE order_id = ?"
    )->execute([$method, 'SIM-' . strtoupper(bin2hex(random_bytes(6))), $order_id]);
    $pdo->prepare("UPDATE orders SET status = 'paid' WHERE order_id = ?")->execute([$order_id]);

    $note->execute([$customer_id, 'order',   "Your order #$order_id has been placed.", $link]);
    $note->execute([$customer_id, 'payment', "Payment received for order #$order_id.", $link]);

    $pdo->commit();
    go('../public/track-order.php?id=' . $order_id . '&paid=1');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Payment failed: ' . $e->getMessage());
    go('../public/payment.php?id=' . $order_id . '&error=failed');
}