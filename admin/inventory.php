<?php
$allowed_roles = ['admin', 'manager'];
include '../includes/admin-auth-check.php';
require_once '../config/db.php';

$admin_active = 'inventory';
$LOW_LIMIT = 10;   // 10 or fewer left counts as low stock

$tabs = ['all' => 'All dishes', 'low' => 'Low stock', 'soldout' => 'Sold out', 'off' => 'Switched off'];
$filter = array_key_exists($_GET['filter'] ?? '', $tabs) ? $_GET['filter'] : 'all';

$where = [
    'all'     => '',
    'low'     => "WHERE stock_quantity BETWEEN 1 AND $LOW_LIMIT",
    'soldout' => 'WHERE stock_quantity = 0',
    'off'     => 'WHERE is_available = 0',
][$filter];

$dishes = $pdo->query(
    "SELECT item_id, name, category, stock_quantity, is_available
     FROM menu_item $where
     ORDER BY stock_quantity ASC, name"
)->fetchAll();

// Numbers for the tab badges
$counts = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(stock_quantity BETWEEN 1 AND $LOW_LIMIT) AS low,
            SUM(stock_quantity = 0) AS soldout,
            SUM(is_available = 0) AS off_count
     FROM menu_item"
)->fetch();
$tab_counts = [
    'all' => (int) $counts['total'], 'low' => (int) $counts['low'],
    'soldout' => (int) $counts['soldout'], 'off' => (int) $counts['off_count'],
];

$success_messages = ['stock' => 'Stock updated.', 'toggled' => 'Dish availability changed.'];
$error_messages   = ['invalid' => 'Enter a whole number from 0 upwards.', 'failed' => 'Something went wrong. Please try again.'];
$success = $success_messages[$_GET['success'] ?? ''] ?? '';
$error   = $error_messages[$_GET['error'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inventory | Staff area</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../public/css/style.css">
<link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin">

<?php include '../includes/admin-header.php'; ?>

  <h1 class="page-title">Inventory</h1>
  <p class="page-sub">Keep stock accurate. A dish at 0, or switched off, shows as sold out on the website. Orders reduce stock automatically.</p>

  <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="toolbar">
    <div class="tabs">
      <?php foreach ($tabs as $key => $label): ?>
        <a href="inventory.php?filter=<?php echo $key; ?>" class="<?php echo $filter === $key ? 'active' : ''; ?>">
          <?php echo $label; ?> (<?php echo $tab_counts[$key]; ?>)
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <section class="panel">
    <?php if (!$dishes): ?>
      <p class="empty">Nothing here. All good.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Dish</th><th>Category</th><th>Status</th><th>Set stock</th><th>Availability</th></tr></thead>
          <tbody>
            <?php foreach ($dishes as $d):
                $stock = (int) $d['stock_quantity'];
            ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($d['category']); ?></td>
                <td>
                  <?php if (!$d['is_available']): ?><span class="pill low">Switched off</span>
                  <?php elseif ($stock === 0): ?><span class="pill low">Sold out</span>
                  <?php elseif ($stock <= $LOW_LIMIT): ?><span class="pill low"><?php echo $stock; ?> left</span>
                  <?php else: ?><span class="pill delivered"><?php echo $stock; ?> in stock</span><?php endif; ?>
                </td>
                <td>
                  <form method="post" action="../action/admin/stock-action.php" class="inline-form">
                    <input type="hidden" name="action" value="set_stock">
                    <input type="hidden" name="item_id" value="<?php echo (int) $d['item_id']; ?>">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="number" name="quantity" value="<?php echo $stock; ?>" min="0" max="100000" step="1" class="qty-input" aria-label="Stock for <?php echo htmlspecialchars($d['name']); ?>">
                    <button type="submit" class="btn-sm">Save</button>
                  </form>
                </td>
                <td>
                  <form method="post" action="../action/admin/stock-action.php" class="inline-form">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="item_id" value="<?php echo (int) $d['item_id']; ?>">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <button type="submit" class="btn-sm ghost"><?php echo $d['is_available'] ? 'Switch off' : 'Switch on'; ?></button>
                  </form>
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