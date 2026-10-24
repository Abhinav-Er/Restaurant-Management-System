<?php
$allowed_roles = ['admin', 'manager', 'kitchen'];
include '../includes/admin-auth-check.php';
require_once '../config/db.php';
require_once '../includes/admin-helpers.php';
date_default_timezone_set('Asia/Kolkata');

$admin_active = 'kitchen';
$role = $_SESSION['admin_role'];
$can_cancel = in_array($role, ['admin', 'manager'], true);

// Every order the kitchen still has to deal with
$orders = $pdo->query(
    "SELECT order_id, order_type, table_no, notes, status, scheduled_time,
            TIMESTAMPDIFF(MINUTE, order_date, NOW()) AS mins_ago,
            (scheduled_time IS NOT NULL AND scheduled_time > NOW() + INTERVAL 30 MINUTE) AS is_upcoming
     FROM orders
     WHERE status IN ('paid', 'preparing', 'ready')
     ORDER BY COALESCE(scheduled_time, order_date), order_id"
)->fetchAll();

// Their dishes
$items = [];
if ($orders) {
    $ids   = array_column($orders, 'order_id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare(
        "SELECT oi.order_id, m.name, oi.quantity
         FROM order_item oi JOIN menu_item m ON m.item_id = oi.item_id
         WHERE oi.order_id IN ($marks) ORDER BY oi.order_item_id"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $items[(int) $row['order_id']][] = $row;
    }
}

// Sort them into columns. A scheduled order more than 30 minutes away waits in "Upcoming".
$columns  = ['new' => [], 'preparing' => [], 'ready' => []];
$upcoming = [];
foreach ($orders as $o) {
    if ($o['status'] === 'paid' && $o['is_upcoming']) {
        $upcoming[] = $o;
    } elseif ($o['status'] === 'paid') {
        $columns['new'][] = $o;
    } else {
        $columns[$o['status']][] = $o;
    }
}

$success = $admin_success[$_GET['success'] ?? ''] ?? '';
$error   = $admin_errors[$_GET['error'] ?? ''] ?? '';

function render_ticket(array $o, array $items, bool $can_cancel, array $next_step): void {
    $id = (int) $o['order_id'];
    ?>
    <div class="ticket">
      <div class="ticket-top">
        <strong>#<?php echo $id; ?></strong>
        <span class="sub"><?php echo htmlspecialchars(time_ago((int) $o['mins_ago'])); ?></span>
      </div>
      <div class="sub" style="margin-bottom:8px;">
        <?php echo $o['order_type'] === 'dinein' ? 'Dine-in' : 'Takeaway'; ?>
        <?php if ($o['table_no']): ?> &middot; Table <?php echo htmlspecialchars($o['table_no']); ?><?php endif; ?>
        <?php if ($o['scheduled_time']): ?> &middot; For <?php echo date('g:i A', strtotime($o['scheduled_time'])); ?><?php endif; ?>
      </div>
      <ul class="ticket-items">
        <?php foreach ($items[$id] ?? [] as $it): ?>
          <li><span class="qty"><?php echo (int) $it['quantity']; ?> &times;</span> <?php echo htmlspecialchars($it['name']); ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($o['notes']): ?><div class="ticket-note">Note: <?php echo htmlspecialchars($o['notes']); ?></div><?php endif; ?>
      <div class="ticket-actions">
        <?php if (isset($next_step[$o['status']])): [$to, $label] = $next_step[$o['status']]; ?>
          <?php echo status_form($id, $to, $label, 'kitchen.php'); ?>
        <?php endif; ?>
        <?php if ($can_cancel): ?>
          <?php echo status_form($id, 'cancelled', 'Cancel', 'kitchen.php', 'btn-sm danger', 'Cancel this order? The customer will be notified and the dishes returned to stock.'); ?>
        <?php endif; ?>
      </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="30">
<title>Kitchen | Staff area</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../public/css/style.css">
<link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin">

<?php include '../includes/admin-header.php'; ?>

  <h1 class="page-title">Kitchen</h1>
  <p class="page-sub">This screen refreshes by itself every 30 seconds.</p>

  <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="board">
    <section class="col">
      <h2>New <span class="count"><?php echo count($columns['new']); ?></span></h2>
      <?php if (!$columns['new']): ?><p class="empty">No new orders.</p><?php endif; ?>
      <?php foreach ($columns['new'] as $o) { render_ticket($o, $items, $can_cancel, $next_step); } ?>
    </section>

    <section class="col">
      <h2>Preparing <span class="count"><?php echo count($columns['preparing']); ?></span></h2>
      <?php if (!$columns['preparing']): ?><p class="empty">Nothing in the kitchen.</p><?php endif; ?>
      <?php foreach ($columns['preparing'] as $o) { render_ticket($o, $items, $can_cancel, $next_step); } ?>
    </section>

    <section class="col">
      <h2>Ready <span class="count"><?php echo count($columns['ready']); ?></span></h2>
      <?php if (!$columns['ready']): ?><p class="empty">Nothing waiting.</p><?php endif; ?>
      <?php foreach ($columns['ready'] as $o) { render_ticket($o, $items, $can_cancel, $next_step); } ?>
    </section>
  </div>

  <section class="panel" style="margin-top:22px;">
    <h2>Upcoming scheduled orders</h2>
    <?php if (!$upcoming): ?>
      <p class="empty">No scheduled orders waiting.</p>
    <?php else: foreach ($upcoming as $o): $id = (int) $o['order_id']; ?>
      <div class="list-row">
        <span>
          <strong>#<?php echo $id; ?></strong>
          for <?php echo date('j M, g:i A', strtotime($o['scheduled_time'])); ?>
          <span class="sub">&middot;
            <?php echo htmlspecialchars(implode(', ', array_map(fn($it) => $it['quantity'] . ' x ' . $it['name'], $items[$id] ?? []))); ?>
          </span>
        </span>
        <?php if ($can_cancel): ?>
          <?php echo status_form($id, 'preparing', 'Start now', 'kitchen.php', 'btn-sm ghost'); ?>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </section>

<?php include '../includes/admin-footer.php'; ?>

</body>
</html>