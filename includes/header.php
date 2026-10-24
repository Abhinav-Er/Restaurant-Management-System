<?php
/*
 * Shared navbar. Include at the top of every customer page:
 *   $active_page = 'menu';              // home | menu | group | reviews | track
 *   include '../includes/header.php';
 *
 * Placeholder data for now. Once login and the database exist, replace:
 *   $is_logged_in    -> check $_SESSION['customer_id']
 *   $notifications   -> SELECT ... FROM Notification WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5
 */
if (!isset($active_page)) {
    $active_page = '';
}

$is_logged_in = !empty($_SESSION['customer_id']);


$notifications = [
    ['message' => 'Your order #1042 is now being prepared.', 'time' => '2 min ago', 'unread' => true,  'link' => 'track-order.php'],
    ['message' => 'Payment received for order #1042.',       'time' => '10 min ago', 'unread' => true,  'link' => 'track-order.php'],
    ['message' => 'How was your last order? Rate your dishes.', 'time' => 'Yesterday', 'unread' => false, 'link' => 'order-history.php'],
];
$unread_count = count(array_filter($notifications, fn($n) => $n['unread']));
?>
<header class="site">
  <div class="nav">
    <a href="index.php" class="brand">
      <svg class="mark" viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <circle cx="20" cy="20" r="19" stroke="#8C2F26" stroke-width="2"/>
        <path d="M12 24c0-6 4-9 8-9s8 3 8 9" stroke="#8C2F26" stroke-width="2" stroke-linecap="round"/>
        <path d="M13 24h14" stroke="#8C2F26" stroke-width="2" stroke-linecap="round"/>
        <path d="M17 12c0 2-1.5 2.5-1.5 4.5M20 11c0 2-1.5 2.8-1.5 5M23 12c0 2-1.5 2.5-1.5 4.5" stroke="#C99A2E" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      Pawan Punjabi Chinese
    </a>

    <nav class="nav-links" id="navLinks" aria-label="Main">
      <a href="index.php"       class="<?php echo $active_page === 'home'    ? 'active' : ''; ?>">Home</a>
      <a href="menu.php"        class="<?php echo $active_page === 'menu'    ? 'active' : ''; ?>">Menu</a>
      <a href="group-order.php" class="<?php echo $active_page === 'group'   ? 'active' : ''; ?>">Group Order</a>
      <a href="reviews.php"     class="<?php echo $active_page === 'reviews' ? 'active' : ''; ?>">Reviews</a>
      <a href="track-order.php" class="<?php echo $active_page === 'track'   ? 'active' : ''; ?>">Track Order</a>
    </nav>

    <div class="nav-right">
      <!-- Notifications -->
      <div class="notif-wrap">
        <button class="icon-btn" id="notifBtn" aria-label="Notifications" aria-expanded="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
          </svg>
          <span class="badge" <?php echo $unread_count === 0 ? 'hidden' : ''; ?>><?php echo (int) $unread_count; ?></span>
        </button>
        <div class="notif-menu" id="notifMenu">
          <div class="notif-head">Notifications</div>
          <?php foreach ($notifications as $n): ?>
            <a class="notif-item <?php echo $n['unread'] ? 'unread' : ''; ?>" href="<?php echo htmlspecialchars($n['link']); ?>">
              <?php echo htmlspecialchars($n['message']); ?>
              <small><?php echo htmlspecialchars($n['time']); ?></small>
            </a>
          <?php endforeach; ?>
          <a class="notif-foot" href="notifications.php">View all</a>
        </div>
      </div>

      <!-- Cart -->
      <a href="cart.php" class="icon-btn" aria-label="Cart">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
          <path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>
        </svg>
        <span class="badge" id="cartCount" hidden>0</span>
      </a>

      <?php if ($is_logged_in): ?>
        <a href="profile.php" class="nav-cta">Profile</a>
      <?php else: ?>
        <a href="login.php" class="nav-cta">Login</a>
      <?php endif; ?>

      <!-- Mobile menu button -->
      <button class="icon-btn nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <path d="M3 6h18M3 12h18M3 18h18"/>
        </svg>
      </button>
    </div>
  </div>
</header>