<?php
// Handles the login form: checks the email and password, starts the session, then redirects.
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/login.php');
    exit;
}

$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$next     = $_POST['next'] ?? 'index.php';

// Only these pages are allowed as a "go here after login" target
$allowed_next = ['cart.php', 'checkout.php', 'track-order.php', 'order-history.php', 'profile.php', 'group-order.php', 'notifications.php'];
if (!in_array($next, $allowed_next, true)) {
    $next = 'index.php';
}

if ($email === '' || $password === '') {
    header('Location: ../public/login.php?error=required');
    exit;
}

$stmt = $pdo->prepare('SELECT customer_id, name, password_hash FROM customer WHERE email = ?');
$stmt->execute([$email]);
$customer = $stmt->fetch();

// Same message for "no such email" and "wrong password", so nobody can find out which emails exist
if (!$customer || !password_verify($password, $customer['password_hash'])) {
    header('Location: ../public/login.php?error=invalid');
    exit;
}

// Success: a fresh session id stops session-fixation attacks
session_regenerate_id(true);
$_SESSION['customer_id']   = (int) $customer['customer_id'];
$_SESSION['customer_name'] = $customer['name'];

header('Location: ../public/' . $next);
exit;