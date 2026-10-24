<?php
// Handles the "Rate this order" form from My orders.
// A review is "verified": the order must belong to the logged-in customer, be delivered,
// and the dish must be in that order. The server checks all of this itself.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/order-history.php');
    exit;
}
if (empty($_SESSION['customer_id'])) {
    header('Location: ../public/login.php?error=login&next=order-history.php');
    exit;
}
$customer_id = (int) $_SESSION['customer_id'];

function back_to_orders(string $type, string $code): void {
    header('Location: ../public/order-history.php?' . $type . '=' . $code);
    exit;
}

$order_id = (int) ($_POST['order_id'] ?? 0);
$ratings  = $_POST['rating']  ?? [];
$comments = $_POST['comment'] ?? [];
if ($order_id < 1 || !is_array($ratings) || !is_array($comments)) {
    back_to_orders('error', 'review');
}

// The order must be this customer's and already delivered
$stmt = $pdo->prepare('SELECT status FROM orders WHERE order_id = ? AND customer_id = ?');
$stmt->execute([$order_id, $customer_id]);
$order = $stmt->fetch();
if (!$order || $order['status'] !== 'delivered') {
    back_to_orders('error', 'review');
}

// Only the dishes that were really in this order can be reviewed
$stmt = $pdo->prepare('SELECT DISTINCT item_id FROM order_item WHERE order_id = ?');
$stmt->execute([$order_id]);
$dish_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
if (!$dish_ids) {
    back_to_orders('error', 'review');
}

// Every dish needs a rating from 1 to 5, as the form asks. The comment is optional.
$to_save = [];
foreach ($dish_ids as $id) {
    $rating = (int) ($ratings[$id] ?? 0);
    if ($rating < 1 || $rating > 5) {
        back_to_orders('error', 'review');
    }
    $comment = $comments[$id] ?? '';
    $comment = is_string($comment) ? trim($comment) : '';
    $to_save[$id] = [$rating, mb_substr($comment, 0, 300)];
}

// Save all of them, or none
try {
    $pdo->beginTransaction();
    $ins = $pdo->prepare(
        'INSERT INTO review (customer_id, item_id, order_id, rating, review_text) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($to_save as $item_id => [$rating, $comment]) {
        $ins->execute([$customer_id, $item_id, $order_id, $rating, $comment]);
    }
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // 23000 = this order was already reviewed (one review per dish per order)
    if ($e->getCode() !== '23000') {
        error_log('Review failed: ' . $e->getMessage());
    }
    back_to_orders('error', 'review');
}

back_to_orders('success', 'reviewed');