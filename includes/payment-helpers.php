<?php
// Shared payment rules.
const PAYMENT_WINDOW_MINUTES = 15;   // how long an unpaid order is held

// Cancels ONE unpaid order and puts its dishes back into stock.
// Call it inside a transaction, after locking the order row and checking its status is still 'placed'.
function release_unpaid_order(PDO $pdo, int $order_id, string $reason): void
{
    $pdo->prepare(
        'UPDATE menu_item m JOIN order_item oi ON oi.item_id = m.item_id
         SET m.stock_quantity = m.stock_quantity + oi.quantity
         WHERE oi.order_id = ?'
    )->execute([$order_id]);

    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?")->execute([$order_id]);
    $pdo->prepare("UPDATE payment SET payment_status = 'failed' WHERE order_id = ? AND payment_status = 'pending'")->execute([$order_id]);

    $who = $pdo->prepare('SELECT customer_id FROM orders WHERE order_id = ?');
    $who->execute([$order_id]);
    $customer_id = (int) $who->fetchColumn();

    $text = $reason === 'expired'
        ? "Order #$order_id was cancelled because payment was not completed in time."
        : "You cancelled order #$order_id.";
    $pdo->prepare('INSERT INTO notification (customer_id, type, message, link) VALUES (?, ?, ?, ?)')
        ->execute([$customer_id, 'order', $text, 'order-history.php']);
}

// Cancels every order that has waited too long for payment.
// Runs whenever a page that shows orders loads, so no timer or cron job is needed.
function expire_unpaid_orders(PDO $pdo): void
{
    try {
        $ids = $pdo->query(
            "SELECT order_id FROM orders
             WHERE status = 'placed' AND order_date < NOW() - INTERVAL " . PAYMENT_WINDOW_MINUTES . " MINUTE"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT status FROM orders WHERE order_id = ? FOR UPDATE');
            $lock->execute([$id]);
            if ($lock->fetchColumn() === 'placed') {   // may have been paid a moment ago
                release_unpaid_order($pdo, (int) $id, 'expired');
            }
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Expire unpaid orders failed: ' . $e->getMessage());
    }
}