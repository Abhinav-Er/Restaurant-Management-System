<?php
// Checks the staff username and password, then opens the right page for their role.
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../admin/login.php');
    exit;
}

function back(string $code): void {
    header('Location: ../../admin/login.php?error=' . $code);
    exit;
}

// Simple brute-force guard: 5 wrong tries lock the login for 5 minutes (per browser session)
if (($_SESSION['admin_lock_until'] ?? 0) > time()) {
    back('locked');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
if ($username === '' || $password === '') {
    back('required');
}

$stmt = $pdo->prepare('SELECT admin_id, username, password_hash, role FROM admin_manager WHERE username = ?');
$stmt->execute([$username]);
$staff = $stmt->fetch();

if (!$staff || !password_verify($password, $staff['password_hash'])) {
    $_SESSION['admin_fails'] = ($_SESSION['admin_fails'] ?? 0) + 1;
    if ($_SESSION['admin_fails'] >= 5) {
        $_SESSION['admin_lock_until'] = time() + 300;
        $_SESSION['admin_fails'] = 0;
        back('locked');
    }
    back('invalid');
}

// Success
session_regenerate_id(true);
unset($_SESSION['admin_fails'], $_SESSION['admin_lock_until']);
$_SESSION['admin_id']       = (int) $staff['admin_id'];
$_SESSION['admin_username'] = $staff['username'];
$_SESSION['admin_role']     = $staff['role'];

header('Location: ../../admin/' . ($staff['role'] === 'kitchen' ? 'kitchen.php' : 'dashboard.php'));
exit;