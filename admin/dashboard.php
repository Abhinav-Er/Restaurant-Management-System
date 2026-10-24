<?php
$allowed_roles = ['admin', 'manager'];
include '../includes/admin-auth-check.php';
require_once '../config/db.php';
date_default_timezone_set('Asia/Kolkata');

$admin_active = 'dashboard';

// ----- Today's numbers -----
$today = $pdo->query(
    "SELECT COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN status NOT IN ('placed','cancelled') THEN total_amount END), 0) AS revenue
     FROM orders WHERE DATE(order_date) = CURDATE()"
)->fetch();

$active_orders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('paid','preparing','ready')")->fetchColumn();
$customer_count = (int) $pdo->query('SELECT COUNT(*) FROM customer')->fetchColumn();

// ----- Recent orders -----
$recent = $pdo->query(
    'SELECT o.order_id, o.order_date, o.status, o.order_type, o.total_amount, c.name AS customer
     FROM orders o JOIN customer c ON c.customer_id = o.customer_id
     ORDER BY o.order_date DESC, o.order_id DESC LIMIT 8'
)->fetchAll();

// ----- Popular dishes, last 30 days -----
$popular = $pdo->query(
    "SELECT m.name, SUM(oi.quantity) AS qty
     FROM order_item oi
     JOIN orders o     ON o.order_id = oi.order_id
     JOIN menu_item m  ON m.item_id = oi.item_id
     WHERE o.status NOT IN ('placed','cancelled') AND o.order_date >= NOW() - INTERVAL 30 DAY
     GROUP BY m.item_id, m.name
     ORDER BY qty DESC LIMIT 5"
)->fetchAll();
$popular_max = $popular ? max(array_column($popular, 'qty')) : 1;

// ----- Peak hours, last 30 days -----
$peak = $pdo->query(
    "SELECT HOUR(order_date) AS hr, COUNT(*) AS n
     FROM orders
     WHERE status NOT IN ('placed','cancelled') AND order_date >= NOW() - INTERVAL 30 DAY
     GROUP BY HOUR(order_date)
     ORDER BY n DESC LIMIT 3"
)->fetchAll();

// ----- Low stock (10 or fewer left, or switched off) -----
$low_stock = $pdo->query(
    'SELECT name, stock_quantity, is_available
     FROM menu_item
     WHERE stock_quantity <= 10 OR is_available = 0
     ORDER BY stock_quantity ASC, name LIMIT 8'
)->fetchAll();

$status_labels = [
    'placed' => 'Placed', 'paid' => 'Paid', 'preparing' => 'Preparing',
    'ready' => 'Ready', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
];

// 14 -> "2 PM"
function hour_label(int $h): string {
    return date('g A', mktime($h, 0, 0));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard | Staff area</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../public/css/style.css">
<link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin">

<?php include '../includes/admin-header.php'; ?>

  <h1 class="page-title">Dashboard</h1>
  <p class="page-sub"><?php echo date('l, j F Y'); ?></p>

  <div class="stat-grid">
    <div class="stat"><div class="label">Orders today</div><div class="value"><?php echo (int) $today['orders']; ?></div></div>
    <div class="stat"><div class="label">Revenue today</div><div class="value">&#8377;<?php echo number_format((float) $today['revenue']); ?></div></div>
    <div class="stat"><div class="label">Active orders</div><div class="value"><?php echo $active_orders; ?></div></div>
    <div class="stat"><div class="label">Customers</div><div class="value"><?php echo $customer_count; ?></div></div>
  </div>

  <div class="grid-2">
    <section class="panel">
      <h2>Recent orders</h2>
      <?php if (!$recent): ?>
        <p class="empty">No orders yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $o): ?>
                <tr>
                  <td>#<?php echo (int) $o['order_id']; ?></td>
                  <td><?php echo htmlspecialchars($o['customer']); ?></td>
                  <td><?php echo $o['order_type'] === 'dinein' ? 'Dine-in' : 'Takeaway'; ?></td>
                  <td>&#8377;<?php echo number_format((float) $o['total_amount']); ?></td>
                  <td><span class="pill <?php echo htmlspecialchars($o['status']); ?>"><?php echo htmlspecialchars($status_labels[$o['status']]); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <h2>Popular dishes (30 days)</h2>
      <?php if (!$popular): ?>
        <p class="empty">No sales yet.</p>
      <?php else: foreach ($popular as $p): ?>
        <div class="bar-row">
          <div>
            <div class="bar-name"><?php echo htmlspecialchars($p['name']); ?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?php echo round($p['qty'] / $popular_max * 100); ?>%"></div></div>
          </div>
          <div class="bar-num"><?php echo (int) $p['qty']; ?></div>
        </div>
      <?php endforeach; endif; ?>
    </section>
  </div>

  <div class="grid-2" style="grid-template-columns:1fr 1fr;">
    <section class="panel">
      <h2>Stock alerts</h2>
      <?php if (!$low_stock): ?>
        <p class="empty">All dishes are well stocked.</p>
      <?php else: foreach ($low_stock as $s): ?>
        <div class="list-row">
          <span><?php echo htmlspecialchars($s['name']); ?></span>
          <?php if ((int) $s['stock_quantity'] === 0 || !$s['is_available']): ?>
            <span class="pill low">Sold out</span>
          <?php else: ?>
            <span class="pill low"><?php echo (int) $s['stock_quantity']; ?> left</span>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </section>

    <section class="panel">
      <h2>Peak hours (30 days)</h2>
      <?php if (!$peak): ?>
        <p class="empty">Not enough orders yet.</p>
      <?php else: foreach ($peak as $p): ?>
        <div class="list-row">
          <span><?php echo hour_label((int) $p['hr']) . ' &ndash; ' . hour_label(((int) $p['hr'] + 1) % 24); ?></span>
          <span><?php echo (int) $p['n']; ?> order<?php echo (int) $p['n'] === 1 ? '' : 's'; ?></span>
        </div>
      <?php endforeach; endif; ?>
    </section>
  </div>

<?php include '../includes/admin-footer.php'; ?>

</body>
</html>