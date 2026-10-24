<?php
include '../includes/auth-check.php';
require_once '../config/db.php';
date_default_timezone_set('Asia/Kolkata');

$order_id         = (int) ($_GET['id'] ?? 0);
$only_customer_id = (int) $_SESSION['customer_id'];   // customers can only open their own bills
$css_href         = 'css/style.css';
$back_href        = 'track-order.php?id=' . $order_id;

include '../includes/bill-view.php';