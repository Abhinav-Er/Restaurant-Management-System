<?php
// Sets a dish's stock quantity, or switches the dish on or off.
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../config/db.php';

function post_text(string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

// Keep the customer on the same filter tab afterwards
$filter = in_array(post_text('filter'), ['all', 'low', 'soldout', 'off'], true) ? post_text('filter') : 'all';

function back(string $filter, string $type, string $code): void {
    header('Location: ../../admin/inventory.php?filter=' . $filter . '&' . $type . '=' . $code);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../admin/inventory.php');
    exit;
}
if (empty($_SESSION['admin_id'])) {
    header('Location: ../../admin/login.php?error=login');
    exit;
}
if (!in_array($_SESSION['admin_role'], ['admin', 'manager'], true)) {
    http_response_code(403);
    exit('You do not have access to this action.');
}

$action = post_text('action');
$id     = (int) post_text('item_id');

try {
    if ($action === 'set_stock') {
        $qty = filter_var(post_text('quantity'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
        if ($qty === false || $id < 1) {
            back($filter, 'error', 'invalid');
        }
        $pdo->prepare('UPDATE menu_item SET stock_quantity = ? WHERE item_id = ?')->execute([$qty, $id]);
        back($filter, 'success', 'stock');
    }

    if ($action === 'toggle') {
        // Flips 1 to 0 and 0 to 1 in one step
        $pdo->prepare('UPDATE menu_item SET is_available = 1 - is_available WHERE item_id = ?')->execute([$id]);
        back($filter, 'success', 'toggled');
    }
} catch (PDOException $e) {
    error_log('Stock update failed: ' . $e->getMessage());
    back($filter, 'error', 'failed');
}

back($filter, 'error', 'invalid');