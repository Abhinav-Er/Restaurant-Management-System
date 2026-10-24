<?php
include '../includes/auth-check.php';
require_once '../config/db.php';
require_once '../includes/payment-helpers.php'; expire_unpaid_orders($pdo);
date_default_timezone_set('Asia/Kolkata');

$active_page = '';
$page_title  = 'My orders | Pawan Punjabi Chinese Restaurant';

$customer_id = (int) $_SESSION['customer_id'];

// The customer's orders, newest first (latest 50)
  $stmt = $pdo->prepare(
      'SELECT o.order_id, o.order_date, o.scheduled_time, o.status, o.order_type, o.total_amount, o.group_order_id,
              p.payment_status
       FROM orders o LEFT JOIN payment p ON p.order_id = o.order_id
       WHERE o.customer_id = ?
       ORDER BY o.order_date DESC, o.order_id DESC LIMIT 50'
  );
$stmt->execute([$customer_id]);
$rows = $stmt->fetchAll();

$orders = [];
foreach ($rows as $r) {
    $orders[(int) $r['order_id']] = [
        'id'            => (int) $r['order_id'],
        'date'          => date('j M Y, g:i A', strtotime($r['order_date'])),
        'status'        => $r['status'],
        'type'          => $r['order_type'] === 'dinein' ? 'Dine-in' : 'Takeaway',
        'scheduled_for' => $r['scheduled_time'] ? date('j M Y, g:i A', strtotime($r['scheduled_time'])) : null,
        'is_group'      => !empty($r['group_order_id']),
        'reviewed'      => false,
        'total'         => (float) $r['total_amount'],
        'items'         => [],
        'payment' => $r['payment_status'],
    ];
}

if ($orders) {
    $ids   = array_keys($orders);
    $marks = implode(',', array_fill(0, count($ids), '?'));

    // The dishes in all of those orders. "available" is used by the Reorder button.
    $items_stmt = $pdo->prepare(
        "SELECT oi.order_id, m.item_id, m.name, oi.quantity, oi.unit_price,
                (m.is_available = 1 AND m.stock_quantity > 0) AS available
         FROM order_item oi
         JOIN menu_item m ON m.item_id = oi.item_id
         WHERE oi.order_id IN ($marks)
         ORDER BY oi.order_item_id"
    );
    $items_stmt->execute($ids);
    foreach ($items_stmt->fetchAll() as $it) {
        $orders[(int) $it['order_id']]['items'][] = [
            'id'        => (int) $it['item_id'],
            'name'      => $it['name'],
            'qty'       => (int) $it['quantity'],
            'price'     => (float) $it['unit_price'],
            'available' => (bool) $it['available'],
        ];
    }

    // Orders the customer has already reviewed
    $rev_stmt = $pdo->prepare("SELECT DISTINCT order_id FROM review WHERE customer_id = ? AND order_id IN ($marks)");
    $rev_stmt->execute(array_merge([$customer_id], $ids));
    foreach ($rev_stmt->fetchAll(PDO::FETCH_COLUMN) as $rid) {
        $orders[(int) $rid]['reviewed'] = true;
    }
}
$orders = array_values($orders);

$status_labels = [
     'placed' => 'Awaiting payment', 'paid' => 'Paid', 'preparing' => 'Preparing',
    'ready' => 'Ready', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
];
// Which filter tab each status belongs to
$status_group = [
    'placed' => 'active', 'paid' => 'active', 'preparing' => 'active', 'ready' => 'active',
    'delivered' => 'completed', 'cancelled' => 'cancelled',
];

// Messages the backend can send back, e.g. order-history.php?success=reviewed
$success_messages = ['reviewed' => 'Thanks! Your review has been submitted.'];
$error_messages   = ['review' => 'Your review could not be saved. Please try again.'];
$success = $success_messages[$_GET['success'] ?? ''] ?? '';
$error   = $error_messages[$_GET['error'] ?? ''] ?? '';
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
  /* ---- Order history page specific styles ---- */
  .history-page { max-width: 900px; margin: 0 auto; padding: 48px 24px 80px; }

  .tabs { display: inline-flex; background: var(--paper-dim); border-radius: 10px; padding: 4px; gap: 4px; margin: 20px 0 22px; flex-wrap: wrap; }
  .tab { padding: 8px 16px; border-radius: 7px; font-size: 0.88rem; font-weight: 600; cursor: pointer; border: none; background: transparent; color: var(--ink); opacity: 0.6; }
  .tab.active { background: var(--card); opacity: 1; box-shadow: 0 2px 6px var(--shadow); }

  .order-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; margin-bottom: 18px; overflow: hidden; }
  .order-card[hidden] { display: none; }
  .oc-top { display: flex; justify-content: space-between; gap: 14px; flex-wrap: wrap; padding: 18px 22px; border-bottom: 1px solid var(--line); }
  .oc-title { font-weight: 700; font-size: 1.02rem; }
  .oc-date { font-size: 0.82rem; opacity: 0.65; margin-top: 3px; }
  .oc-total { font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.15rem; text-align: right; }

  .pill { display: inline-block; padding: 3px 11px; border-radius: 20px; font-size: 0.74rem; font-weight: 700; background: var(--gold-soft); margin-left: 6px; }
  .pill.s-delivered { background: var(--success-bg); color: var(--jade); }
  .pill.s-cancelled { background: var(--danger-bg); color: var(--brick); }

  .oc-items { padding: 14px 22px; font-size: 0.9rem; line-height: 1.7; }
  .oc-items span { opacity: 0.85; }
  .oc-tags { padding: 0 22px 14px; }

  .oc-action { display: flex; gap: 10px; flex-wrap: wrap; padding: 14px 22px 18px; }
  .oc-action .btn-primary, .oc-action .btn-ghost { padding: 9px 18px; font-size: 0.88rem; border-radius: 9px; }
  .reviewed-note { font-size: 0.85rem; color: var(--jade); font-weight: 600; align-self: center; }

  /* Review form */
  .review-panel { padding: 6px 22px 22px; border-top: 1px solid var(--line); background: var(--paper-dim); }
  .review-panel[hidden] { display: none; }
  .review-panel h3 { margin: 16px 0 4px; font-size: 1rem; }
  .review-panel .sub { font-size: 0.82rem; opacity: 0.65; margin: 0 0 14px; }
  .rv-dish { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; }
  .rv-name { font-weight: 600; margin-bottom: 8px; }
  .stars-input { position: relative; display: inline-flex; flex-direction: row-reverse; gap: 2px; }
  .stars-input input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .stars-input label { font-size: 1.7rem; line-height: 1; color: var(--line); cursor: pointer; padding: 0 1px; }
  .stars-input input:checked ~ label,
  .stars-input label:hover,
  .stars-input label:hover ~ label { color: var(--gold); }
  .stars-input input:focus-visible + label { outline: 2px solid var(--gold); outline-offset: 2px; border-radius: 4px; }
  .rv-dish textarea { width: 100%; margin-top: 10px; padding: 10px 12px; border-radius: 10px; border: 1.5px solid var(--line); background: var(--paper); color: var(--ink); font-family: inherit; font-size: 0.9rem; }
  .rv-error { font-size: 0.8rem; color: var(--brick); margin-top: 6px; display: none; }
  .rv-dish.invalid .rv-error { display: block; }

  .empty-state { display: none; text-align: center; padding: 48px 24px; opacity: 0.65; }
  .empty-state.show { display: block; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="history-page">
  <h1 class="section-title display">My orders</h1>
  <p class="section-note">Track current orders, reorder a favourite, or rate what you ate.</p>

  <?php if ($success): ?><div class="alert alert-success" style="margin-top:18px;" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" style="margin-top:18px;" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="tabs" role="group" aria-label="Filter orders">
    <button class="tab active" data-filter="all">All</button>
    <button class="tab" data-filter="active">Active</button>
    <button class="tab" data-filter="completed">Completed</button>
    <button class="tab" data-filter="cancelled">Cancelled</button>
  </div>

  <?php foreach ($orders as $o):
      $total = $o['total'];
      $group = $status_group[$o['status']];
      $can_review = $o['status'] === 'delivered' && !$o['reviewed'];
  ?>
    <article class="order-card" data-group="<?php echo $group; ?>">
      <div class="oc-top">
        <div>
          <div class="oc-title">
            Order #<?php echo (int) $o['id']; ?>
            <span class="pill s-<?php echo htmlspecialchars($o['status']); ?>"><?php echo htmlspecialchars($status_labels[$o['status']]); ?></span>
          </div>
          <div class="oc-date"><?php echo htmlspecialchars($o['date']); ?> &middot; <?php echo htmlspecialchars($o['type']); ?></div>
        </div>
        <div class="oc-total">&#8377;<?php echo number_format($total); ?></div>
      </div>

      <div class="oc-items">
        <?php foreach ($o['items'] as $it): ?>
          <div><span><?php echo htmlspecialchars($it['name']); ?> &times; <?php echo (int) $it['qty']; ?></span></div>
        <?php endforeach; ?>
      </div>

      <?php if ($o['scheduled_for'] || $o['is_group']): ?>
        <div class="oc-tags">
          <?php if ($o['is_group']): ?><span class="pill" style="margin-left:0;">Group order</span><?php endif; ?>
          <?php if ($o['scheduled_for']): ?><span class="pill">Scheduled for <?php echo htmlspecialchars($o['scheduled_for']); ?></span><?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="oc-action">
        <a href="track-order.php?id=<?php echo (int) $o['id']; ?>" class="btn-ghost">
          <?php echo $group === 'active' ? 'Track order' : 'View details'; ?>
        </a>
        <button type="button" class="btn-ghost reorder-btn"
                data-items="<?php echo htmlspecialchars(json_encode($o['items']), ENT_QUOTES); ?>">Reorder</button>
                  <?php if (in_array($o['payment'], ['success', 'refunded'], true)): ?>
    <a href="bill.php?id=<?php echo (int) $o['id']; ?>" class="btn-ghost">View bill</a>
  <?php elseif ($o['status'] === 'placed'): ?>
    <a href="payment.php?id=<?php echo (int) $o['id']; ?>" class="btn-primary">Complete payment</a>
  <?php endif; ?>


        <?php if ($can_review): ?>
          <button type="button" class="btn-primary rate-btn" data-target="review-<?php echo (int) $o['id']; ?>">Rate this order</button>
        <?php elseif ($o['status'] === 'delivered'): ?>
          <span class="reviewed-note">&#10003; Reviewed</span>
        <?php endif; ?>
      </div>

      <?php if ($can_review): ?>
        <!-- Review form: only customers with a delivered order can review its dishes (verified review) -->
        <div class="review-panel" id="review-<?php echo (int) $o['id']; ?>" hidden>
          <h3 class="display">How was your order?</h3>
          <p class="sub">Rate each dish from 1 to 5 stars. Comments are optional.</p>

          <!-- The action file is built in the backend stage (action/review-action.php) -->
          <form method="post" action="../action/review-action.php" class="review-form" novalidate>
            <input type="hidden" name="order_id" value="<?php echo (int) $o['id']; ?>">

            <?php foreach ($o['items'] as $it): $rid = (int) $o['id'] . '-' . (int) $it['id']; ?>
              <div class="rv-dish">
                <div class="rv-name"><?php echo htmlspecialchars($it['name']); ?></div>
                <div class="stars-input" role="radiogroup" aria-label="Rating for <?php echo htmlspecialchars($it['name']); ?>">
                  <?php for ($s = 5; $s >= 1; $s--): ?>
                    <input type="radio" id="star-<?php echo $rid; ?>-<?php echo $s; ?>" name="rating[<?php echo (int) $it['id']; ?>]" value="<?php echo $s; ?>">
                    <label for="star-<?php echo $rid; ?>-<?php echo $s; ?>" title="<?php echo $s; ?> star<?php echo $s > 1 ? 's' : ''; ?>">&#9733;</label>
                  <?php endfor; ?>
                </div>
                <textarea name="comment[<?php echo (int) $it['id']; ?>]" rows="2" maxlength="300" placeholder="Anything to add? (optional)"></textarea>
                <div class="rv-error">Please choose a star rating.</div>
              </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-primary">Submit review</button>
          </form>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>

  <div class="empty-state" id="emptyState">No orders in this section yet.</div>

  <?php if (empty($orders)): ?>
    <div style="text-align:center;padding:48px 0;">
      <p style="opacity:.65;">You haven't placed any orders yet.</p>
      <a href="menu.php" class="btn-primary">Browse menu</a>
    </div>
  <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // Set to true once action/review-action.php exists and the database is connected.
  const BACKEND_READY = true;

  // ----- Filter tabs -----
  const tabs  = document.querySelectorAll('.tab');
  const cards = document.querySelectorAll('.order-card');
  const emptyState = document.getElementById('emptyState');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const filter = tab.dataset.filter;
      let visible = 0;
      cards.forEach(card => {
        const show = filter === 'all' || card.dataset.group === filter;
        card.hidden = !show;
        if (show) visible++;
      });
      emptyState.classList.toggle('show', visible === 0 && cards.length > 0);
    });
  });

  // ----- Reorder: put the previous order's dishes into the cart, then open the cart -----
  document.querySelectorAll('.reorder-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      let items = [];
      try { items = JSON.parse(btn.dataset.items); } catch (e) { return; }

      const cart = Cart.get();
      const skipped = [];

      items.forEach(it => {
        if (!it.available) { skipped.push(it.name); return; }
        const existing = cart.find(c => c.id === it.id);
        if (existing) {
          existing.qty += it.qty;
        } else {
          cart.push({ id: it.id, name: it.name, price: Number(it.price), qty: it.qty });
        }
      });

      Cart.save(cart);

      if (skipped.length > 0) {
        showToast('Not available right now: ' + skipped.join(', '));
        setTimeout(() => { window.location.href = 'cart.php'; }, 1800);
      } else {
        window.location.href = 'cart.php';
      }
    });
  });

  // ----- Show / hide the review form -----
  document.querySelectorAll('.rate-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const panel = document.getElementById(btn.dataset.target);
      panel.hidden = !panel.hidden;
      if (!panel.hidden) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });

  // ----- Review form validation: every dish needs a star rating -----
  document.querySelectorAll('.review-form').forEach(form => {
    form.addEventListener('submit', (e) => {
      let allRated = true;
      form.querySelectorAll('.rv-dish').forEach(dish => {
        const rated = dish.querySelector('input[type="radio"]:checked') !== null;
        dish.classList.toggle('invalid', !rated);
        if (!rated) allRated = false;
      });

      if (!allRated) {
        e.preventDefault();
        return;
      }
      if (!BACKEND_READY) {
        e.preventDefault();
        showToast('Review is ready. Submitting will work once the backend is connected.');
      }
    });
  });
</script>

</body>
</html>