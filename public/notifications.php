<?php
include '../includes/auth-check.php';
$active_page = '';
$page_title  = 'Notifications | Pawan Punjabi Chinese Restaurant';

// Once login exists, protect this page by adding this line at the very top of the file:
//   include '../includes/auth-check.php';

// Placeholder notifications, newest first. Once MySQL is connected, replace with:
//   SELECT notification_id, type, message, read_status, created_at, link
//   FROM Notification WHERE customer_id = ? ORDER BY created_at DESC;
// "day" is worked out from created_at (Today / Yesterday / Earlier).
$notifications = [
    ['id' => 1, 'type' => 'status',   'unread' => true,  'day' => 'Today',     'time' => '2 min ago',   'link' => 'track-order.php',   'message' => 'Your order #1042 is now being prepared.'],
    ['id' => 2, 'type' => 'payment',  'unread' => true,  'day' => 'Today',     'time' => '10 min ago',  'link' => 'track-order.php',   'message' => 'Payment received for order #1042.'],
    ['id' => 3, 'type' => 'order',    'unread' => false, 'day' => 'Today',     'time' => '12 min ago',  'link' => 'track-order.php',   'message' => 'Your order #1042 has been placed.'],
    ['id' => 4, 'type' => 'group',    'unread' => false, 'day' => 'Today',     'time' => '1 hour ago',  'link' => 'group-order.php',   'message' => 'Riya added Veg Hakka Noodles to your group order.'],
    ['id' => 5, 'type' => 'schedule', 'unread' => false, 'day' => 'Yesterday', 'time' => '6:45 PM',     'link' => 'track-order.php',   'message' => 'Your scheduled order will start preparing at 7:30 PM.'],
    ['id' => 6, 'type' => 'stock',    'unread' => false, 'day' => 'Yesterday', 'time' => '2:10 PM',     'link' => 'cart.php',          'message' => 'Chicken Momos in your cart is now out of stock.'],
    ['id' => 7, 'type' => 'review',   'unread' => false, 'day' => 'Yesterday', 'time' => '11:30 AM',    'link' => 'order-history.php', 'message' => 'How was your last order? Rate your dishes.'],
    ['id' => 8, 'type' => 'status',   'unread' => false, 'day' => 'Earlier',   'time' => '10 Oct',      'link' => 'order-history.php', 'message' => 'Order #1031 was delivered. Enjoy your meal!'],
];

// Which icon each notification type uses (paths are simple line icons)
$type_icon = [
    'order' => 'bag', 'payment' => 'card', 'status' => 'clock', 'schedule' => 'calendar',
    'stock' => 'alert', 'group' => 'users', 'review' => 'star',
];
$icon_paths = [
    'bag'      => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'card'     => '<rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/>',
    'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'alert'    => '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
    'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
    'star'     => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
];

// Messages the backend can send back, e.g. notifications.php?success=marked
$success_messages = ['marked' => 'All notifications marked as read.'];
$success = $success_messages[$_GET['success'] ?? ''] ?? '';

$unread_total = count(array_filter($notifications, fn($n) => $n['unread']));

// Group the notifications by day, keeping their order
$by_day = [];
foreach ($notifications as $n) {
    $by_day[$n['day']][] = $n;
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
  /* ---- Notifications page specific styles ---- */
  .notif-page { max-width: 760px; margin: 0 auto; padding: 48px 24px 80px; }
  .page-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
  #unreadSummary { font-size: 0.9rem; opacity: 0.7; margin-top: 6px; }

  .tabs { display: inline-flex; background: var(--paper-dim); border-radius: 10px; padding: 4px; gap: 4px; margin: 22px 0 20px; }
  .tab { padding: 8px 18px; border-radius: 7px; font-size: 0.88rem; font-weight: 600; cursor: pointer; border: none; background: transparent; color: var(--ink); opacity: 0.6; }
  .tab.active { background: var(--card); opacity: 1; box-shadow: 0 2px 6px var(--shadow); }

  .day-group { margin-bottom: 24px; }
  .day-group[hidden] { display: none; }
  .day-head { font-size: 0.8rem; font-weight: 700; opacity: 0.55; margin: 0 0 10px; }

  .notif-list { background: var(--card); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
  .notif {
    display: grid; grid-template-columns: 40px 1fr auto; gap: 14px; align-items: center;
    padding: 15px 18px; border-bottom: 1px solid var(--line); text-decoration: none;
  }
  .notif:last-child { border-bottom: none; }
  .notif[hidden] { display: none; }
  .notif:hover { background: var(--paper-dim); }
  .notif.unread { background: color-mix(in srgb, var(--gold-soft) 35%, transparent); }
  .notif.unread:hover { background: color-mix(in srgb, var(--gold-soft) 55%, transparent); }

  .n-icon { width: 40px; height: 40px; border-radius: 50%; background: var(--paper-dim); display: flex; align-items: center; justify-content: center; color: var(--brick); }
  .n-icon svg { width: 19px; height: 19px; }
  .n-text { font-size: 0.93rem; line-height: 1.45; }
  .notif.unread .n-text { font-weight: 600; }
  .n-time { font-size: 0.78rem; opacity: 0.55; margin-top: 3px; font-weight: 400; }
  .n-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--brick); }
  .notif:not(.unread) .n-dot { visibility: hidden; }

  .empty-state { text-align: center; padding: 56px 24px; opacity: 0.7; background: var(--card); border: 1px solid var(--line); border-radius: 14px; }
  .empty-state[hidden] { display: none; }
  .empty-state h2 { margin: 0 0 6px; font-size: 1.15rem; }
  .empty-state p { margin: 0; font-size: 0.92rem; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="notif-page">
  <div class="page-head">
    <div>
      <h1 class="section-title display">Notifications</h1>
      <div id="unreadSummary"></div>
    </div>

    <!-- The action file is built in the backend stage (action/notification-action.php) -->
    <form method="post" action="../action/notification-action.php" id="markAllForm">
      <input type="hidden" name="action" value="mark_all">
      <button type="submit" class="btn-ghost" id="markAllBtn">Mark all as read</button>
    </form>
  </div>

  <?php if ($success): ?><div class="alert alert-success" style="margin-top:18px;" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <div class="tabs" role="group" aria-label="Filter notifications">
    <button class="tab active" data-filter="all">All</button>
    <button class="tab" data-filter="unread">Unread</button>
  </div>

  <?php foreach ($by_day as $day => $items): ?>
    <section class="day-group">
      <h2 class="day-head"><?php echo htmlspecialchars($day); ?></h2>
      <div class="notif-list">
        <?php foreach ($items as $n): ?>
          <!-- Backend stage: this link should go through the backend so it can mark the notification read before opening the page -->
          <a class="notif <?php echo $n['unread'] ? 'unread' : ''; ?>" href="<?php echo htmlspecialchars($n['link']); ?>" data-id="<?php echo (int) $n['id']; ?>">
            <span class="n-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $icon_paths[$type_icon[$n['type']]]; ?></svg>
            </span>
            <span class="n-text">
              <?php echo htmlspecialchars($n['message']); ?>
              <div class="n-time"><?php echo htmlspecialchars($n['time']); ?></div>
            </span>
            <span class="n-dot" aria-label="<?php echo $n['unread'] ? 'Unread' : ''; ?>"></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="empty-state" id="emptyState" <?php echo empty($notifications) ? '' : 'hidden'; ?>>
    <h2 class="display" id="emptyTitle">You're all caught up</h2>
    <p id="emptyText">New updates about your orders will show up here.</p>
  </div>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // Set to true once action/notification-action.php exists and the database is connected.
  const BACKEND_READY = false;

  const tabs       = document.querySelectorAll('.tab');
  const items      = document.querySelectorAll('.notif');
  const groups     = document.querySelectorAll('.day-group');
  const emptyState = document.getElementById('emptyState');
  const summaryEl  = document.getElementById('unreadSummary');
  const markAllBtn = document.getElementById('markAllBtn');

  let filter = 'all';

  // Keeps the page, the "mark all" button and the navbar bell in step with each other
  function refresh() {
    const unread = document.querySelectorAll('.notif.unread').length;

    summaryEl.textContent = unread === 0 ? 'No unread notifications' : unread + ' unread';
    markAllBtn.disabled = unread === 0;

    // Navbar bell badge (from includes/header.php)
    const badge = document.querySelector('#notifBtn .badge');
    if (badge) {
      badge.textContent = unread;
      badge.hidden = unread === 0;
    }

    // Apply the All / Unread filter
    let visible = 0;
    items.forEach(n => {
      const show = filter === 'all' || n.classList.contains('unread');
      n.hidden = !show;
      if (show) visible++;
    });
    groups.forEach(g => {
      g.hidden = g.querySelectorAll('.notif:not([hidden])').length === 0;
    });

    // Empty message
    if (items.length > 0) {
      emptyState.hidden = visible > 0;
      document.getElementById('emptyTitle').textContent = filter === 'unread' ? 'Nothing unread' : "You're all caught up";
      document.getElementById('emptyText').textContent  = filter === 'unread' ? 'Every notification has been read.' : 'New updates about your orders will show up here.';
    }
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      filter = tab.dataset.filter;
      refresh();
    });
  });

  // Clicking a notification marks it as read on this page before opening its link.
  // This is visual only for now; the backend will save it.
  items.forEach(n => {
    n.addEventListener('click', () => n.classList.remove('unread'));
  });

  // Mark all as read
  document.getElementById('markAllForm').addEventListener('submit', (e) => {
    if (!BACKEND_READY) {
      e.preventDefault();
      items.forEach(n => n.classList.remove('unread'));
      refresh();
      showToast('Marked as read on this page. It will be saved once the backend is connected.');
    }
  });

  refresh();
</script>

</body>
</html>