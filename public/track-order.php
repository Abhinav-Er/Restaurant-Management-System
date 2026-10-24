<?php
include '../includes/auth-check.php';
require_once '../config/db.php';
require_once '../includes/payment-helpers.php';
date_default_timezone_set('Asia/Kolkata');

$active_page = 'track';
$page_title  = 'Track order | Pawan Punjabi Chinese Restaurant';

// Order steps, in order. "cancelled" is handled separately below.
$steps = [
    'placed'    => 'Placed',
    'paid'      => 'Paid',
    'preparing' => 'Preparing',
    'ready'     => 'Ready',
    'delivered' => 'Delivered',
];

$customer_id = (int) $_SESSION['customer_id'];
$order_id    = (int) ($_GET['id'] ?? 0);

// ?id=... opens that order. With no id, show the customer's most recent order.
// The customer_id check means nobody can open someone else's order by changing the number.
expire_unpaid_orders($pdo);
if ($order_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = ? AND customer_id = ?');
    $stmt->execute([$order_id, $customer_id]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY order_date DESC, order_id DESC LIMIT 1');
    $stmt->execute([$customer_id]);
}
$row = $stmt->fetch();

if (!$row) {
    header('Location: order-history.php');   // no such order (or no orders yet)
    exit;
}
  $pay = $pdo->prepare('SELECT payment_status FROM payment WHERE order_id = ?');
  $pay->execute([$row['order_id']]);
  $payment = $pay->fetch() ?: ['payment_status' => null];
// The dishes in this order, with the price they were bought at
$items_stmt = $pdo->prepare(
    'SELECT m.name, oi.quantity AS qty, oi.unit_price AS price
     FROM order_item oi
     JOIN menu_item m ON m.item_id = oi.item_id
     WHERE oi.order_id = ?
     ORDER BY oi.order_item_id'
);
$items_stmt->execute([$row['order_id']]);
$items = $items_stmt->fetchAll();

// Kitchen queue: paid or preparing orders placed before this one that the kitchen should already be on
$orders_ahead = 0;
if (in_array($row['status'], ['paid', 'preparing'], true)) {
    $q = $pdo->prepare(
        "SELECT COUNT(*) FROM orders
         WHERE status IN ('paid', 'preparing')
           AND order_id < ?
           AND (scheduled_time IS NULL OR scheduled_time <= NOW())"
    );
    $q->execute([$row['order_id']]);
    $orders_ahead = (int) $q->fetchColumn();
}

$order = [
    'id'            => (int) $row['order_id'],
    'placed_at'     => date('j M Y, g:i A', strtotime($row['order_date'])),
    'status'        => $row['status'],
    'order_type'    => $row['order_type'] === 'dinein' ? 'Dine-in' : 'Takeaway',
    'scheduled_for' => $row['scheduled_time'] ? date('j M Y, g:i A', strtotime($row['scheduled_time'])) : null,
    'is_group'      => !empty($row['group_order_id']),
    'orders_ahead'  => $orders_ahead,
    'est_minutes'   => 10 + $orders_ahead * 5,   // rough estimate: 10 minutes plus 5 per order ahead
    'items'         => $items,
    'discount'      => (float) $row['discount_amount'],
];

$is_cancelled = $order['status'] === 'cancelled';
$step_keys    = array_keys($steps);
$current_idx  = $is_cancelled ? -1 : array_search($order['status'], $step_keys, true);

$subtotal = 0;
foreach ($order['items'] as $it) {
    $subtotal += $it['price'] * $it['qty'];
}
$total = $subtotal - $order['discount'];

$status_messages = [
    'placed'    => 'We have received your order. Waiting for payment confirmation.',
    'paid'      => 'Payment received. Your order is waiting for the kitchen.',
    'preparing' => 'The kitchen is preparing your order.',
    'ready'     => 'Your order is ready. Please collect it or wait at your table.',
    'delivered' => 'Your order is complete. Enjoy your meal!',
    'cancelled' => 'This order was cancelled. If you were charged, the amount will be refunded.',
];
  if ($is_cancelled && $payment['payment_status'] === 'refunded') {
      $status_messages['cancelled'] = 'This order was cancelled and your payment has been refunded.';
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($page_title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<style>
  /* ---- Track order page specific styles ---- */
  .track-page { max-width: 900px; margin: 0 auto; padding: 48px 24px 80px; }
  .order-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
  .order-meta { font-size: 0.9rem; opacity: 0.7; margin-top: 6px; line-height: 1.6; }
  .pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; background: var(--gold-soft); margin-right: 6px; }

  .panel { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 24px; margin-top: 22px; }
  .panel h2 { margin: 0 0 18px; font-size: 1.1rem; }

  /* Timeline */
  .timeline { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0; list-style: none; padding: 0; margin: 0; }
  .tl-step { position: relative; text-align: center; font-size: 0.85rem; font-weight: 600; opacity: 0.45; }
  .tl-step .dot {
    width: 30px; height: 30px; border-radius: 50%; margin: 0 auto 10px;
    border: 2px solid var(--line); background: var(--paper);
    display: flex; align-items: center; justify-content: center; font-size: 0.8rem; position: relative; z-index: 1;
  }
  .tl-step::before {
    content: ''; position: absolute; top: 14px; left: -50%; width: 100%; height: 3px; background: var(--line); z-index: 0;
  }
  .tl-step:first-child::before { display: none; }
  .tl-step.done, .tl-step.current { opacity: 1; }
  .tl-step.done .dot { background: var(--jade); border-color: var(--jade); color: #fff; }
  .tl-step.done::before, .tl-step.current::before { background: var(--jade); }
  .tl-step.current .dot { background: var(--brick); border-color: var(--brick); color: #fff; box-shadow: 0 0 0 5px color-mix(in srgb, var(--brick) 20%, transparent); }
  @media (max-width: 560px) {
    .timeline { grid-template-columns: 1fr; gap: 18px; }
    .tl-step { display: flex; align-items: center; gap: 14px; text-align: left; }
    .tl-step .dot { margin: 0; flex-shrink: 0; }
    .tl-step::before { display: none; }
  }

  .status-box { margin-top: 24px; padding: 14px 18px; border-radius: 12px; background: var(--paper-dim); font-size: 0.93rem; line-height: 1.5; }
  .status-box.cancelled { background: var(--danger-bg); color: var(--brick); }
  .queue { display: flex; gap: 28px; margin-top: 16px; flex-wrap: wrap; }
  .queue div { font-size: 0.82rem; opacity: 0.7; }
  .queue strong { display: block; font-family: 'Fraunces', serif; font-size: 1.3rem; opacity: 1; }

  .bill-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; font-size: 0.93rem; }
  .bill-row.total { border-top: 1px solid var(--line); margin-top: 8px; padding-top: 14px; font-weight: 700; font-size: 1.05rem; }
  .bill-row.discount { color: var(--jade); }

  .action { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="track-page">
    <?php if (isset($_GET['paid'])): ?><div clert-success" role="status">Payment successful. Your order is confirmed.</div><?php endif; ?>
  <div class="order-head">ass="alert al
    <div>
      <h1 class="section-title display">Order #<?php echo (int) $order['id']; ?></h1>
      <div class="order-meta">
        <span class="pill"><?php echo htmlspecialchars($order['order_type']); ?></span>
        <?php if ($order['is_group']): ?><span class="pill">Group order</span><?php endif; ?>
        <?php if ($order['scheduled_for']): ?><span class="pill">Scheduled</span><?php endif; ?>
        <br>
        Placed on <?php echo htmlspecialchars($order['placed_at']); ?>
        <?php if ($order['scheduled_for']): ?>
          <br>Scheduled for <strong><?php echo htmlspecialchars($order['scheduled_for']); ?></strong>
        <?php endif; ?>
      </div>
    </div>
    <button type="button" class="btn-ghost" onclick="window.location.reload()">Refresh status</button>
  </div>

  <!-- Status timeline -->
  <section class="panel">
    <h2 class="display">Order status</h2>

    <?php if (!$is_cancelled): ?>
      <ol class="timeline" aria-label="Order progress">
        <?php foreach ($steps as $key => $label):
            $idx = array_search($key, $step_keys, true);
            $class = $idx < $current_idx ? 'done' : ($idx === $current_idx ? 'current' : '');
        ?>
          <li class="tl-step <?php echo $class; ?>" <?php echo $class === 'current' ? 'aria-current="step"' : ''; ?>>
            <div class="dot"><?php echo $idx < $current_idx ? '&#10003;' : ($idx + 1); ?></div>
            <?php echo htmlspecialchars($label); ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>

    <div class="status-box <?php echo $is_cancelled ? 'cancelled' : ''; ?>" role="status">
      <?php echo htmlspecialchars($status_messages[$order['status']]); ?>
    </div>

    <?php if (in_array($order['status'], ['paid', 'preparing'], true)): ?>
      <div class="queue">
        <div><strong><?php echo (int) $order['orders_ahead']; ?></strong>Orders ahead of yours</div>
        <div><strong>~<?php echo (int) $order['est_minutes']; ?> min</strong>Estimated time</div>
      </div>
    <?php endif; ?>
  </section>

  <!-- Items and bill -->
  <section class="panel">
    <h2 class="display">Items and bill</h2>
    <?php foreach ($order['items'] as $it): ?>
      <div class="bill-row">
        <span><?php echo htmlspecialchars($it['name']); ?> &times; <?php echo (int) $it['qty']; ?></span>
        <span>&#8377;<?php echo number_format($it['price'] * $it['qty']); ?></span>
      </div>
    <?php endforeach; ?>

    <div class="bill-row" style="border-top:1px solid var(--line);margin-top:8px;padding-top:12px;">
      <span>Subtotal</span><span>&#8377;<?php echo number_format($subtotal); ?></span>
    </div>
    <?php if ($order['discount'] > 0): ?>
      <div class="bill-row discount"><span>Discount</span><span>-&#8377;<?php echo number_format($order['discount']); ?></span></div>
    <?php endif; ?>
    <div class="bill-row total"><span>Total</span><span>&#8377;<?php echo number_format($total); ?></span></div>

    <div class="action">
        <?php if ($order['status'] === 'placed'): ?>
    <a href="payment.php?id=<?php echo (int) $order['id']; ?>" class="btn-primary">Complete payment</a>
  <?php endif; ?>
  <?php if (in_array($payment['payment_status'], ['success', 'refunded'], true)): ?>
    <a href="bill.php?id=<?php echo (int) $order['id']; ?>" class="btn-ghost">View bill</a>
  <?php endif; ?>
      <?php if (in_array($order['status'], ['delivered', 'cancelled'], true)): ?>
        <a href="menu.php" class="btn-primary">Order again</a>
      <?php endif; ?>
      <?php if ($order['status'] === 'delivered'): ?>
        <a href="order-history.php" class="btn-ghost">Rate your dishes</a>
      <?php endif; ?>
      <a href="order-history.php" class="btn-ghost">All my orders</a>
    </div>
  </section>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // After a successful order, empty the browser cart
  if (new URLSearchParams(location.search).get('placed') === '1') { Cart.clear(); }
</script>
</body>
</html>