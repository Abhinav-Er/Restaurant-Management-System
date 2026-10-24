<?php
// Put this as the very first line of any page that needs a logged-in customer:
//   <?php include '../includes/auth-check.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['customer_id'])) {
    $page = basename($_SERVER['SCRIPT_NAME']);   // so login can send them back here afterwards
    header('Location: login.php?error=login&next=' . urlencode($page));
    exit;
}