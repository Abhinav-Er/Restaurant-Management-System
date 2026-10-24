<?php
$allowed_roles = ['admin', 'manager'];
include '../includes/admin-auth-check.php';
require_once '../config/db.php';
require_once '../includes/admin-helpers.php';
date_default_timezone_set('Asia/Kolkata');

$admin_active = 'orders';

$tabs = ['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'];
$filter = array_key_exists($_GET['status'] ?? '', $tabs) ? $_GET['status'] : 'active';

$where  = [];
$params = [];
if ($filter === 'active')    { $where[] = "o.status IN ('placed','paid','preparing','ready')"; }
if ($filter === 'completed') { $where[] = "o.status = 'delivered'"; }
if ($filter === 'cancelled') { $where[] = "o.status = 'cancelled'"; }

// Search by order number (1042 or #1042) or by customer name
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $digits = ltrim($q, '#');
    if (ctype_digit($digits)) {
        $where[]  = 'o.order_id = ?';
        $params[] = (int) $digits;
    } else {
        $where[]  = 'c.name LIKE ?';
        $params[] = '%' . $q . '%';
    }
}
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT o.order_id, o.order_date, o.scheduled_time, o.status, o.order_type, o.table_no,
            o.notes, o.total_amount, o.group_order_id,
            c.name AS customer, c.phone,
            p.payment_method, p.payment_status,
            GROUP_CONCAT(CONCAT(m.name, ' x ', oi.quantity) ORDER BY oi.order_item_id SEPARATOR ', ') AS items
     FROM orders o
     JOIN customer c   ON c.customer_id = o.customer_id
     LEFT JOIN payment p ON p.order_id = o.order_id
     JOIN order_item oi ON oi.order_id = o.order_id
     JOIN menu_item m   ON m.item_id = oi.item_id
     $sql_where
     GROUP BY o.order_id
     ORDER BY o.order_date DESC, o.order_id DESC
     LIMIT 100"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$success = $admin_success[$_GET['success'] ?? ''] ?? '';
$error   = $admin_errors[$_GET['error'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Orders | Staff area</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../public/css/style.css">
<link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin">

<?php include '../includes/admin-header.php'; ?>

  <h1 class="page-title">Orders</h1>
  <p class="page-sub">View incoming orders and move them along. Customers are notified of every change.</p>

  <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="toolbar">
    <div class="tabs">
      <?php foreach ($tabs as $key => $label): ?>
        <a href="orders.php?status=<?php echo $key; ?>" class="<?php echo $filter === $key ? 'active' : ''; ?>"><?php echo $label; ?></a>
      <?php endforeach; ?>
    </div>
    <form class="search" method="get" action="orders.php">
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
      <input type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Order number or customer" aria-label="Search orders">
      <button type="submit" class="btn-sm">Search</button>
    </form>
  </div>

  <section class="panel">
    <?php if (!$orders): ?>
      <p class="empty">No orders found here.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr><th>Order</th><th>Customer</th><th>Items</th><th>Type</th><th>Total</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td>
                  <strong>#<?php echo (int) $o['order_id']; ?></strong>
                  <div class="sub"><?php echo date('j M, g:i A', strtotime($o['order_date'])); ?></div>
                </td>
                <td>
                  <?php echo htmlspecialchars($o['customer']); ?>
                  <div class="sub"><?php echo htmlspecialchars($o['phone']); ?></div>
                </td>
                <td class="wrap">
                  <?php echo htmlspecialchars($o['items']); ?>
                  <?php if ($o['notes']): ?><div class="sub">Note: <?php echo htmlspecialchars($o['notes']); ?></div><?php endif; ?>
                </td>
                <td>
                  <?php echo $o['order_type'] === 'dinein' ? 'Dine-in' : 'Takeaway'; ?>
                  <?php if ($o['table_no']): ?><div class="sub">Table <?php echo htmlspecialchars($o['table_no']); ?></div><?php endif; ?>
                  <?php if ($o['scheduled_time']): ?><div class="sub">Scheduled <?php echo date('j M, g:i A', strtotime($o['scheduled_time'])); ?></div><?php endif; ?>
                  <?php if ($o['group_order_id']): ?><div class="sub">Group order</div><?php endif; ?>
                </td>
                <td>
                  &#8377;<?php echo number_format((float) $o['total_amount']); ?>
                  <div class="sub"><?php echo strtoupper(htmlspecialchars($o['payment_method'] ?? '')); ?> &middot; <?php echo htmlspecialchars($o['payment_status'] ?? 'no payment'); ?></div>
                </td>
                <td><span class="pill <?php echo htmlspecialchars($o['status']); ?>"><?php echo htmlspecialchars($status_labels[$o['status']]); ?></span></td>
                <td>
                  <?php if (isset($next_step[$o['status']])): [$to, $label] = $next_step[$o['status']]; ?>
                    <?php echo status_form((int) $o['order_id'], $to, $label, 'orders.php'); ?>
                  <?php endif; ?>
                  <?php if (in_array($o['status'], ['placed', 'paid', 'preparing', 'ready'], true)): ?>
                    <?php echo status_form((int) $o['order_id'], 'cancelled', 'Cancel', 'orders.php', 'btn-sm danger', 'Cancel this order? The customer will be notified and the dishes returned to stock.'); ?>
                  <?php endif; ?>
                   <?php if (in_array($o['payment_status'], ['success', 'refunded'], true)): ?>
                    <a class="btn-sm ghost" style="text-decoration:none;display:inline-block;" href="bill.php?id=<?php echo (int) $o['order_id']; ?>">Bill</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

<?php include '../includes/admin-footer.php'; ?>

</body>
</html>