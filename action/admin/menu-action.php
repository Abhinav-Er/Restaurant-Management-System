<?php
// Adds, updates or deletes a dish. Every value is checked here, on the server.
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../config/db.php';

function back(string $type, string $code): void {
    header('Location: ../../admin/menu-management.php?' . $type . '=' . $code);
    exit;
}
// Reads a form field as plain text (ignores anything that is not text)
function post_text(string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../admin/menu-management.php');
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

// ---------- Delete ----------
if ($action === 'delete') {
    try {
        $stmt = $pdo->prepare('DELETE FROM menu_item WHERE item_id = ?');
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // 23000: the dish is used by an order, a review or a group order, so it must stay
        if ($e->getCode() === '23000') {
            back('error', 'inuse');
        }
        error_log('Delete dish failed: ' . $e->getMessage());
        back('error', 'failed');
    }
    back($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'deleted' : 'notfound');
}

// ---------- Add or update ----------
if ($action !== 'save') {
    back('error', 'invalid');
}

$name        = post_text('name');
$description = post_text('description');
$category    = post_text('category');
$price_raw   = post_text('price');
$stock       = filter_var(post_text('stock_quantity'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);

if ($name === '' || mb_strlen($name) > 100
    || mb_strlen($description) > 255
    || $category === '' || mb_strlen($category) > 50
    || !is_numeric($price_raw) || (float) $price_raw < 0.01 || (float) $price_raw > 99999
    || $stock === false) {
    back('error', 'invalid');
}
$price = round((float) $price_raw, 2);

// Checkboxes send a value only when ticked
$is_available = isset($_POST['is_available']) ? 1 : 0;
$is_veg       = isset($_POST['is_veg']) ? 1 : 0;
$is_featured  = isset($_POST['is_featured']) ? 1 : 0;

try {
    if ($id > 0) {
        $exists = $pdo->prepare('SELECT 1 FROM menu_item WHERE item_id = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            back('error', 'notfound');
        }
        $pdo->prepare(
            'UPDATE menu_item
             SET name = ?, description = ?, category = ?, price = ?, stock_quantity = ?,
                 is_available = ?, is_veg = ?, is_featured = ?
             WHERE item_id = ?'
        )->execute([$name, $description, $category, $price, $stock, $is_available, $is_veg, $is_featured, $id]);
        back('success', 'saved');
    }

    $pdo->prepare(
        'INSERT INTO menu_item (name, description, category, price, stock_quantity, is_available, is_veg, is_featured)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$name, $description, $category, $price, $stock, $is_available, $is_veg, $is_featured]);
    back('success', 'added');
} catch (PDOException $e) {
    error_log('Save dish failed: ' . $e->getMessage());
    back('error', 'failed');
}