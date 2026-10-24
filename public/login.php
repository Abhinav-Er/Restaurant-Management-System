<?php
require_once '../includes/init.php';
$active_page = '';
$page_title  = 'Login | Pawan Punjabi Chinese Restaurant';

// Messages the backend can send back with a redirect, e.g. login.php?error=invalid
// Only these known codes are shown, so nothing from the URL is printed directly.
$error_messages = [
    'invalid'  => 'Incorrect email or password. Please try again.',
    'required' => 'Please fill in both fields.',
    'login'    => 'Please log in to continue.',
];
$success_messages = [
    'registered' => 'Account created. You can log in now.',
    'loggedout'  => 'You have been logged out.',
];

$error   = $error_messages[$_GET['error'] ?? ''] ?? '';
$success = $success_messages[$_GET['success'] ?? ''] ?? '';

// Where to go after login (only pages from this list are allowed).
$allowed_next = ['cart.php', 'checkout.php', 'track-order.php', 'order-history.php', 'profile.php', 'group-order.php', 'notifications.php'];
$next = in_array($_GET['next'] ?? '', $allowed_next, true) ? $_GET['next'] : 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($page_title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<style>
  /* ---- Login page specific styles ---- */
  .auth-wrap { max-width: 440px; margin: 0 auto; padding: 56px 24px 80px; }
  .auth-wrap h1 { font-size: 1.9rem; margin: 0 0 6px; font-weight: 600; }
  .auth-sub { opacity: 0.65; font-size: 0.95rem; margin: 0 0 24px; }
  .auth-switch { text-align: center; font-size: 0.9rem; margin-top: 20px; opacity: 0.8; }
  .auth-switch a { color: var(--brick); font-weight: 600; text-decoration: none; }
  .pw-row { position: relative; }
  .pw-row input { padding-right: 64px; }
  .pw-toggle {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: var(--brick); font-weight: 600;
    font-size: 0.8rem; cursor: pointer; padding: 6px;
  }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="auth-wrap">
  <div class="form-card">
    <h1 class="display">Welcome back</h1>
    <p class="auth-sub">Log in to order, track and reorder your favourites.</p>

    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <div class="alert alert-error" id="formAlert" role="alert" hidden></div>

    <!-- The action file is built in the backend stage (action/login-action.php) -->
    <form id="loginForm" method="post" action="../action/login-action.php" novalidate>
      <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="email" required>
        <div class="error-text">Enter a valid email address.</div>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="pw-row">
          <input type="password" id="password" name="password" autocomplete="current-password" required>
          <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">Show</button>
        </div>
        <div class="error-text">Enter your password.</div>
      </div>

      <button type="submit" class="btn-primary btn-block">Log in</button>
    </form>

    <p class="auth-switch">New here? <a href="register.php">Create an account</a></p>
  </div>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // Set to true once action/login-action.php exists and the database is connected.
  const BACKEND_READY = true;
  

  const form = document.getElementById('loginForm');
  const emailEl = document.getElementById('email');
  const passEl = document.getElementById('password');
  const pwToggle = document.getElementById('pwToggle');

  pwToggle.addEventListener('click', () => {
    const show = passEl.type === 'password';
    passEl.type = show ? 'text' : 'password';
    pwToggle.textContent = show ? 'Hide' : 'Show';
    pwToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });

  form.addEventListener('submit', (e) => {
    const emailOk = Validate.setState(emailEl, Validate.email(emailEl.value));
    const passOk  = Validate.setState(passEl, Validate.required(passEl.value));

    if (!emailOk || !passOk) {
      e.preventDefault();
      return;
    }
    if (!BACKEND_READY) {
      e.preventDefault();
      showToast('Form is valid. Login will work once the backend is connected.');
    }
  });
</script>

</body>
</html>