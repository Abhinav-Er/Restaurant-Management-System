<?php
include '../includes/auth-check.php';
require_once '../config/db.php';
require_once '../includes/payment-helpers.php';
date_default_timezone_set('Asia/Kolkata');

$active_page = '';
$page_title  = 'Payment | Pawan Punjabi Chinese Restaurant';

$customer_id = (int) $_SESSION['customer_id'];
$order_id    = (int) ($_GET['id'] ?? 0);

expire_unpaid_orders($pdo);   // release orders that were never paid

$stmt = $pdo->prepare(
    'SELECT o.order_id, o.status, o.total_amount, o.discount_amount,
            p.payment_method, p.payment_status,
            GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), o.order_date + INTERVAL ' . PAYMENT_WINDOW_MINUTES . ' MINUTE)) AS seconds_left
     FROM orders o JOIN payment p ON p.order_id = o.order_id
     WHERE o.order_id = ? AND o.customer_id = ?'
);
$stmt->execute([$order_id, $customer_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: order-history.php');
    exit;
}
if (in_array($order['payment_status'], ['success', 'refunded'], true)) {
    header('Location: track-order.php?id=' . $order_id);   // already paid
    exit;
}
$is_cancelled = $order['status'] === 'cancelled';

$items_stmt = $pdo->prepare(
    'SELECT m.name, oi.quantity AS qty, oi.subtotal
     FROM order_item oi JOIN menu_item m ON m.item_id = oi.item_id
     WHERE oi.order_id = ? ORDER BY oi.order_item_id'
);
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

$total    = (float) $order['total_amount'];
$subtotal = $total + (float) $order['discount_amount'];

$error_messages = [
    'declined' => 'The payment was declined. Nothing has been charged. You can try again.',
    'expired'  => 'Time ran out, so this order was cancelled. Please place it again.',
    'failed'   => 'Something went wrong. Nothing has been charged. Please try again.',
];
$error = $error_messages[$_GET['error'] ?? ''] ?? '';
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
  .pay-page { max-width: 960px; margin: 0 auto; padding: 48px 24px 80px; }
  .pay-layout { display: grid; grid-template-columns: 1fr 340px; gap: 24px; margin-top: 26px; align-items: start; }
  @media (max-width: 860px) { .pay-layout { grid-template-columns: 1fr; } }
  .panel { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
  .panel h2 { margin: 0 0 14px; font-size: 1.1rem; }
  .test-banner { background: var(--gold-soft); border-radius: 10px; padding: 11px 14px; font-size: 0.84rem; line-height: 1.5; margin-bottom: 20px; }
  .timer { font-size: 0.9rem; margin-bottom: 18px; }
  .timer strong { font-family: 'Fraunces', serif; font-size: 1.15rem; color: var(--brick); }
  .amount { font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 600; margin: 0 0 18px; }
  .choice-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 22px; }
  .choice { position: relative; }
  .choice input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
  .choice span { display: block; padding: 14px 16px; border: 1.5px solid var(--line); border-radius: 12px; font-weight: 600; font-size: 0.93rem; background: var(--paper); }
  .choice span small { display: block; font-weight: 400; opacity: 0.6; margin-top: 3px; font-size: 0.8rem; }
  .choice input:checked + span { border-color: var(--brick); background: color-mix(in srgb, var(--gold-soft) 40%, transparent); }
  .choice input:focus-visible + span { outline: 2px solid var(--gold); outline-offset: 2px; }
  .pay-actions { display: grid; gap: 10px; }
  .link-btn { background: none; border: none; color: var(--brick); font-weight: 600; font-size: 0.88rem; cursor: pointer; padding: 10px; width: 100%; font-family: inherit; }
  .sum-item { display: flex; justify-content: space-between; gap: 12px; font-size: 0.9rem; padding: 5px 0; }
  .sum-row { display: flex; justify-content: space-between; font-size: 0.93rem; padding: 5px 0; }
  .sum-row.total { border-top: 1px solid var(--line); margin-top: 8px; padding-top: 12px; font-weight: 700; font-size: 1.05rem; }
  .sum-row.discount { color: var(--jade); }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="pay-page">
  <h1 class="section-title display">Payment</h1>
  <p class="section-note">Order #<?php echo (int) $order['order_id']; ?></p>

  <?php if ($error): ?><div class="alert alert-error" style="margin-top:18px;" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($is_cancelled): ?>

  <section class="panel" style="margin-top:22px;text-align:center;">
    <h2 class="display">This order was cancelled</h2>
    <p style="opacity:.7;">It was not paid in time, so it was cancelled and the dishes were released. You have not been charged.</p>
    <a href="menu.php" class="btn-primary">Order again</a>
  </section>

<?php else: ?>

  <div class="pay-layout">
    <section class="panel">
      <div class="test-banner">
        <strong>Test mode.</strong> No real money is charged. On a live site this panel is replaced by the payment gateway's own secure checkout, so card and UPI details never reach this website.
      </div>

      <div class="timer">Complete payment within <strong id="timer" data-seconds="<?php echo (int) $order['seconds_left']; ?>">--:--</strong>. After that the order is cancelled.</div>
      <p class="amount">&#8377;<?php echo number_format($total, 2); ?></p>

      <form method="post" action="../action/payment-action.php" id="payForm">
        <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">

        <div class="choice-grid">
          <label class="choice">
            <input type="radio" name="method" value="upi" <?php echo $order['payment_method'] !== 'card' ? 'checked' : ''; ?>>
            <span>UPI<small>Pay with any UPI app</small></span>
          </label>
          <label class="choice">
            <input type="radio" name="method" value="card" <?php echo $order['payment_method'] === 'card' ? 'checked' : ''; ?>>
            <span>Card<small>Credit or debit card</small></span>
          </label>
        </div>

        <div class="pay-actions">
          <button type="submit" name="outcome" value="success" class="btn-primary btn-block">Pay &#8377;<?php echo number_format($total, 2); ?></button>
          <button type="submit" name="outcome" value="fail" class="btn-ghost btn-block">Simulate a failed payment</button>
        </div>
      </form>

      <form method="post" action="../action/payment-action.php" style="margin-top:6px;"
            onsubmit="return confirm('Cancel this order? Nothing will be charged.');">
        <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
        <input type="hidden" name="method" value="upi">
        <button type="submit" name="outcome" value="cancel" class="link-btn">Cancel this order</button>
      </form>
    </section>

    <aside class="panel">
      <h2 class="display">Order summary</h2>
      <?php foreach ($items as $it): ?>
        <div class="sum-item"><span><?php echo htmlspecialchars($it['name']); ?> &times; <?php echo (int) $it['qty']; ?></span><span>&#8377;<?php echo number_format((float) $it['subtotal'], 2); ?></span></div>
      <?php endforeach; ?>
      <div class="sum-row" style="margin-top:10px;"><span>Subtotal</span><span>&#8377;<?php echo number_format($subtotal, 2); ?></span></div>
      <?php if ((float) $order['discount_amount'] > 0): ?>
        <div class="sum-row discount"><span>Discount</span><span>-&#8377;<?php echo number_format((float) $order['discount_amount'], 2); ?></span></div>
      <?php endif; ?>
      <div class="sum-row total"><span>Total</span><span>&#8377;<?php echo number_format($total, 2); ?></span></div>
    </aside>
  </div>

<?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // The order now exists on the server, so the browser cart is no longer needed
  if (new URLSearchParams(location.search).get('placed') === '1') { Cart.clear(); }

  // Countdown. When it reaches zero the page reloads and the server cancels the order.
  const timerEl = document.getElementById('timer');
  if (timerEl) {
    let left = parseInt(timerEl.dataset.seconds, 10);
    const draw = () => {
      const m = String(Math.floor(left / 60)).padStart(2, '0');
      const s = String(left % 60).padStart(2, '0');
      timerEl.textContent = m + ':' + s;
    };
    draw();
    const tick = setInterval(() => {
      left--;
      if (left <= 0) { clearInterval(tick); location.reload(); return; }
      draw();
    }, 1000);
  }

  // Stop double clicks. The delay lets the browser read the clicked button first.
  const form = document.getElementById('payForm');
  if (form) {
    form.addEventListener('submit', () => {
      setTimeout(() => form.querySelectorAll('button').forEach(b => { b.disabled = true; }), 0);
    });
  }
</script>

</body>
</html>