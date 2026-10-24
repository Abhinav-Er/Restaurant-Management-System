<?php
$allowed_roles = ['admin', 'manager'];
include '../includes/admin-auth-check.php';
require_once '../config/db.php';
date_default_timezone_set('Asia/Kolkata');

$order_id         = (int) ($_GET['id'] ?? 0);
$only_customer_id = null;   // staff can open any bill
$css_href         = '../public/css/style.css';
$back_href        = 'orders.php';

include '../includes/bill-view.php';