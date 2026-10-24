<?php
// Handles the register form: checks the data, saves the customer, then redirects.
session_start();
require_once __DIR__ . '/../config/db.php';

// Only accept form submissions, not people typing the address in the browser
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/register.php');
    exit;
}

function back_with_error(string $code): void {
    header('Location: ../public/register.php?error=' . $code);
    exit;
}

// The browser already checked these, but the server must check again (never trust the browser)
$name     = trim($_POST['name'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$phone    = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';
$diet     = $_POST['preference'] ?? '';

if ($name === '' || $email === '' || $phone === '' || $password === '') {
    back_with_error('required');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[6-9]\d{9}$/', $phone) || strlen($password) < 8 || mb_strlen($name) > 100) {
    back_with_error('invalid');
}
if ($password !== $confirm) {
    back_with_error('mismatch');
}

// The form sends '' for "No preference", the database stores 'none'
if (!in_array($diet, ['veg', 'nonveg'], true)) {
    $diet = 'none';
}

// Is this email already registered?
$check = $pdo->prepare('SELECT customer_id FROM customer WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    back_with_error('exists');
}

// Save the customer. The password is stored as a hash, never as plain text.
try {
    $insert = $pdo->prepare(
        'INSERT INTO customer (name, email, phone, password_hash, diet) VALUES (?, ?, ?, ?, ?)'
    );
    $insert->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $diet]);
} catch (PDOException $e) {
    // 23000 = duplicate email (two people registering at the same moment)
    if ($e->getCode() === '23000') {
        back_with_error('exists');
    }
    error_log('Register failed: ' . $e->getMessage());
    back_with_error('invalid');
}

header('Location: ../public/login.php?success=registered');
exit;