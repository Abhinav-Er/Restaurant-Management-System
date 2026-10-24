<?php
require_once '../includes/init.php';
$active_page = '';
$page_title  = 'Create account | Pawan Punjabi Chinese Restaurant';

// Messages the backend can send back with a redirect, e.g. register.php?error=exists
$error_messages = [
    'exists'   => 'An account with this email already exists. Try logging in instead.',
    'required' => 'Please fill in all required fields.',
    'invalid'  => 'Some details look incorrect. Please check and try again.',
    'mismatch' => 'The two passwords do not match.',
];
$error = $error_messages[$_GET['error'] ?? ''] ?? '';
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
  /* ---- Register page specific styles ---- */
  .auth-wrap { max-width: 480px; margin: 0 auto; padding: 56px 24px 80px; }
  .auth-wrap h1 { font-size: 1.9rem; margin: 0 0 6px; font-weight: 600; }
  .auth-sub { opacity: 0.65; font-size: 0.95rem; margin: 0 0 24px; }
  .auth-switch { text-align: center; font-size: 0.9rem; margin-top: 20px; opacity: 0.8; }
  .auth-switch a { color: var(--brick); font-weight: 600; text-decoration: none; }
  .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  @media (max-width: 480px) { .row-2 { grid-template-columns: 1fr; gap: 0; } }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="auth-wrap">
  <div class="form-card">
    <h1 class="display">Create your account</h1>
    <p class="auth-sub">Save your details and favourites for faster ordering.</p>

    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="alert alert-error" id="formAlert" role="alert" hidden></div>

    <!-- The action file is built in the backend stage (action/register-action.php) -->
    <form id="registerForm" method="post" action="../action/register-action.php" novalidate>

      <div class="field">
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" autocomplete="name" required>
        <div class="error-text">Enter your name.</div>
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="email" required>
        <div class="error-text">Enter a valid email address.</div>
      </div>

      <div class="field">
        <label for="phone">Mobile number</label>
        <input type="tel" id="phone" name="phone" autocomplete="tel" inputmode="numeric" maxlength="10" required>
        <div class="hint">10-digit Indian mobile number</div>
        <div class="error-text">Enter a valid 10-digit mobile number.</div>
      </div>

      <div class="row-2">
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="new-password" required>
          <div class="hint">At least 8 characters</div>
          <div class="error-text">Password must be at least 8 characters.</div>
        </div>
        <div class="field">
          <label for="confirm">Confirm password</label>
          <input type="password" id="confirm" name="confirm_password" autocomplete="new-password" required>
          <div class="error-text">Passwords do not match.</div>
        </div>
      </div>

      <div class="field">
        <label for="preference">Food preference (optional)</label>
        <select id="preference" name="preference">
          <option value="">No preference</option>
          <option value="veg">Vegetarian</option>
          <option value="nonveg">Non-vegetarian</option>
        </select>
      </div>

      <button type="submit" class="btn-primary btn-block">Create account</button>
    </form>

    <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
  </div>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // Set to true once action/register-action.php exists and the database is connected.
  const BACKEND_READY = true;
  

  const form   = document.getElementById('registerForm');
  const nameEl = document.getElementById('name');
  const emailEl = document.getElementById('email');
  const phoneEl = document.getElementById('phone');
  const passEl = document.getElementById('password');
  const confirmEl = document.getElementById('confirm');

  // Only allow digits in the phone box
  phoneEl.addEventListener('input', () => {
    phoneEl.value = phoneEl.value.replace(/\D/g, '');
  });

  form.addEventListener('submit', (e) => {
    const results = [
      Validate.setState(nameEl,    Validate.required(nameEl.value)),
      Validate.setState(emailEl,   Validate.email(emailEl.value)),
      Validate.setState(phoneEl,   Validate.phone(phoneEl.value)),
      Validate.setState(passEl,    Validate.password(passEl.value)),
      Validate.setState(confirmEl, confirmEl.value === passEl.value && confirmEl.value !== '')
    ];

    if (results.includes(false)) {
      e.preventDefault();
      return;
    }
    if (!BACKEND_READY) {
      e.preventDefault();
      showToast('Form is valid. Registration will work once the backend is connected.');
    }
  });
</script>

</body>
</html>