<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Already logged in? Go straight to the right page.
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . ($_SESSION['admin_role'] === 'kitchen' ? 'kitchen.php' : 'dashboard.php'));
    exit;
}

$error_messages = [
    'invalid'  => 'Incorrect username or password.',
    'required' => 'Please enter your username and password.',
    'login'    => 'Please log in to continue.',
    'locked'   => 'Too many failed attempts. Please wait a few minutes and try again.',
];
$success_messages = ['loggedout' => 'You have been logged out.'];
$error   = $error_messages[$_GET['error'] ?? ''] ?? '';
$success = $success_messages[$_GET['success'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff login | Pawan Punjabi Chinese Restaurant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../public/css/style.css">
<style>
  .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
  .login-wrap .form-card { width: 100%; max-width: 400px; }
  .login-wrap h1 { font-family: 'Fraunces', serif; font-size: 1.7rem; margin: 0 0 6px; }
  .login-wrap p.sub { opacity: 0.65; font-size: 0.92rem; margin: 0 0 22px; }
  .back { display: block; text-align: center; margin-top: 18px; font-size: 0.88rem; color: var(--brick); font-weight: 600; text-decoration: none; }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="form-card">
    <h1>Staff login</h1>
    <p class="sub">For the admin, manager and kitchen team.</p>

    <?php if ($error): ?><div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="post" action="../action/admin/admin-login-action.php">
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-primary btn-block">Log in</button>
    </form>
    <a class="back" href="../public/index.php">Back to the website</a>
  </div>
</div>
</body>
</html>