<?php
// Put this first in every admin page. A page can limit who may open it by setting
// $allowed_roles before including this file, for example:
//   $allowed_roles = ['admin', 'manager'];
//   include '../includes/admin-auth-check.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php?error=login');
    exit;
}

if (isset($allowed_roles) && !in_array($_SESSION['admin_role'], $allowed_roles, true)) {
    // Send staff to the page their role is meant to use
    $home = $_SESSION['admin_role'] === 'kitchen' ? 'kitchen.php' : 'dashboard.php';
    if (basename($_SERVER['SCRIPT_NAME']) !== $home) {
        header('Location: ' . $home);
        exit;
    }
    http_response_code(403);
    exit('You do not have access to this page.');
}