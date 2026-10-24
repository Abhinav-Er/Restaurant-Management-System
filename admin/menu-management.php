<?php
$allowed_roles = ['admin', 'manager'];
include '../includes/admin-auth-check.php';
require_once '../config/db.php';

$admin_active = 'menu';

$dishes = $pdo->query(
    'SELECT item_id, name, description, category, price, stock_quantity, is_available, is_veg, is_featured
     FROM menu_item ORDER BY category, name'
)->fetchAll();
$categories = array_values(array_unique(array_column($dishes, 'category')));

// ?edit=5 fills the form with that dish. Otherwise the form adds a new dish.
$edit = null;
$edit_id = (int) ($_GET['edit'] ?? 0);
foreach ($dishes as $d) {
    if ($edit_id > 0 && (int) $d['item_id'] === $edit_id) { $edit = $d; }
}
$form = $edit ?? [
    'item_id' => 0, 'name' => '', 'description' => '', 'category' => '', 'price' => '',
    'stock_quantity' => 50, 'is_available' => 1, 'is_veg' => 1, 'is_featured' => 0,
];

$success_messages = ['added' => 'Dish added to the menu.', 'saved' => 'Dish updated.', 'deleted' => 'Dish deleted.'];
$error_messages = [
    'invalid'  => 'Please check the form. A name, a category, a price above zero and a stock number are needed.',
    'inuse'    => 'This dish appears in past orders or reviews, so it cannot be deleted. Switch it off in Inventory instead.',
    'notfound' => 'That dish no longer exists.',
    'failed'   => 'Something went wrong. Please try again.',
];
$success = $success_messages[$_GET['success'] ?? ''] ?? '';
$error   = $error_messages[$_GET['error'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menu | Staff area</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../public/css/style.css">
<link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin">

<?php include '../includes/admin-header.php'; ?>

  <h1 class="page-title">Menu</h1>
  <p class="page-sub">Add dishes, change prices and descriptions. Changes show on the website straight away.</p>

  <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <section class="panel" id="dishForm">
    <h2><?php echo $edit ? 'Edit dish' : 'Add a new dish'; ?></h2>

    <form method="post" action="../action/admin/menu-action.php">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="item_id" value="<?php echo (int) $form['item_id']; ?>">

      <div class="form-grid">
        <div class="field">
          <label for="name">Dish name</label>
          <input type="text" id="name" name="name" maxlength="100" required value="<?php echo htmlspecialchars($form['name']); ?>">
        </div>
        <div class="field">
          <label for="category">Category</label>
          <input type="text" id="category" name="category" maxlength="50" list="categoryList" required value="<?php echo htmlspecialchars($form['category']); ?>">
          <datalist id="categoryList">
            <?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="field">
          <label for="price">Price (&#8377;)</label>
          <input type="number" id="price" name="price" min="0.01" max="99999" step="0.01" required value="<?php echo htmlspecialchars((string) $form['price']); ?>">
        </div>
        <div class="field">
          <label for="stock_quantity">Stock quantity</label>
          <input type="number" id="stock_quantity" name="stock_quantity" min="0" max="100000" step="1" required value="<?php echo (int) $form['stock_quantity']; ?>">
        </div>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <input type="text" id="description" name="description" maxlength="255" value="<?php echo htmlspecialchars($form['description']); ?>">
      </div>

      <div class="checks">
        <label><input type="checkbox" name="is_veg" <?php echo $form['is_veg'] ? 'checked' : ''; ?>> Vegetarian</label>
        <label><input type="checkbox" name="is_available" <?php echo $form['is_available'] ? 'checked' : ''; ?>> Available to order</label>
        <label><input type="checkbox" name="is_featured" <?php echo $form['is_featured'] ? 'checked' : ''; ?>> Show on the home page</label>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="btn-primary"><?php echo $edit ? 'Save changes' : 'Add dish'; ?></button>
        <?php if ($edit): ?><a href="menu-management.php" class="btn-ghost">Cancel</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="panel" style="margin-top:20px;">
    <h2>All dishes (<?php echo count($dishes); ?>)</h2>
    <?php if (!$dishes): ?>
      <p class="empty">No dishes yet. Add the first one above.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Dish</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($dishes as $d): ?>
              <tr>
                <td class="wrap">
                  <span class="veg-dot <?php echo $d['is_veg'] ? '' : 'nonveg'; ?>" title="<?php echo $d['is_veg'] ? 'Vegetarian' : 'Non-vegetarian'; ?>"></span>
                  <strong><?php echo htmlspecialchars($d['name']); ?></strong>
                  <?php if ($d['is_featured']): ?><span class="pill">Featured</span><?php endif; ?>
                  <div class="sub"><?php echo htmlspecialchars($d['description']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($d['category']); ?></td>
                <td>&#8377;<?php echo number_format((float) $d['price'], 2); ?></td>
                <td><?php echo (int) $d['stock_quantity']; ?></td>
                <td>
                  <?php if (!$d['is_available']): ?><span class="pill low">Switched off</span>
                  <?php elseif ((int) $d['stock_quantity'] === 0): ?><span class="pill low">Sold out</span>
                  <?php else: ?><span class="pill delivered">On the menu</span><?php endif; ?>
                </td>
                <td>
                  <a class="btn-sm ghost" style="text-decoration:none;display:inline-block;" href="menu-management.php?edit=<?php echo (int) $d['item_id']; ?>#dishForm">Edit</a>
                  <form method="post" action="../action/admin/menu-action.php" style="display:inline"
                        onsubmit="return confirm('Delete this dish permanently? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item_id" value="<?php echo (int) $d['item_id']; ?>">
                    <button type="submit" class="btn-sm danger">Delete</button>
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