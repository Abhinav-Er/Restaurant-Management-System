<?php
// Sidebar for admin pages. Set $admin_active before including, e.g. $admin_active = 'dashboard';
if (!isset($admin_active)) {
    $admin_active = '';
}

$role = $_SESSION['admin_role'] ?? '';

// Each link lists the roles that may see it. Add more links here as pages get built.
$admin_nav = [
    'dashboard' => ['Dashboard', 'dashboard.php',        ['admin', 'manager']],
    'orders'    => ['Orders',    'orders.php',           ['admin', 'manager']],
    'kitchen'   => ['Kitchen',   'kitchen.php',          ['admin', 'manager', 'kitchen']],
    'menu'      => ['Menu',      'menu-management.php',  ['admin', 'manager']],
    'inventory' => ['Inventory', 'inventory.php',        ['admin', 'manager']],
];
?>
<div class="admin-shell">
  <aside class="sidebar">
    <div class="side-brand">Pawan<small>Staff area</small></div>
    <nav class="side-nav" aria-label="Admin">
      <?php foreach ($admin_nav as $key => [$label, $href, $roles]): ?>
        <?php if (in_array($role, $roles, true)): ?>
          <a href="<?php echo $href; ?>" class="<?php echo $admin_active === $key ? 'active' : ''; ?>"><?php echo htmlspecialchars($label); ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="side-user">
      <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?></strong>
      <span><?php echo htmlspecialchars(ucfirst($role)); ?></span>
      <a href="logout.php">Log out</a>
      <a href="../public/index.php">View website</a>
    </div>
  </aside>
  <main class="admin-main">