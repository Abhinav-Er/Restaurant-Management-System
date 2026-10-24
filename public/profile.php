<?php
include '../includes/auth-check.php';
$active_page = '';
$page_title  = 'My profile | Pawan Punjabi Chinese Restaurant';

// Once login exists, protect this page by adding this line at the very top of the file:
//   include '../includes/auth-check.php';

// Placeholder customer. Once MySQL is connected, load it with:
//   SELECT customer_id, name, email, phone, preferences, created_at FROM Customer WHERE customer_id = ?;
//   and favorites with: SELECT m.item_id, m.name, m.price, m.is_available
//   FROM Favorite f JOIN MenuItem m ON m.item_id = f.item_id WHERE f.customer_id = ?;
$customer = [
    'name'       => 'Abhinav Kumar',
    'email'      => 'abhinav@example.com',
    'phone'      => '9876543210',
    'since'      => 'September 2026',
    'diet'       => 'veg',          // '' | veg | nonveg
    'spice'      => 'medium',       // mild | medium | hot
    'order_type' => 'dinein',       // dinein | takeaway
];

$favorites = [
    ['id' => 2, 'name' => 'Paneer Manchurian', 'price' => 210, 'available' => true],
    ['id' => 4, 'name' => 'Dal Makhani',       'price' => 190, 'available' => true],
    ['id' => 9, 'name' => 'Chicken Momos',     'price' => 160, 'available' => false],
];

// Messages the backend can send back, e.g. profile.php?success=details
$success_messages = [
    'details'     => 'Your details have been updated.',
    'preferences' => 'Your preferences have been saved.',
    'password'    => 'Your password has been changed.',
];
$error_messages = [
    'details'     => 'Could not update your details. Please check them and try again.',
    'wrongpass'   => 'Your current password is incorrect.',
    'mismatch'    => 'The new passwords do not match.',
    'weakpass'    => 'The new password must be at least 8 characters.',
    'emailtaken'  => 'That email is already used by another account.',
];
$success = $success_messages[$_GET['success'] ?? ''] ?? '';
$error   = $error_messages[$_GET['error'] ?? ''] ?? '';

$initial = strtoupper(substr($customer['name'], 0, 1));
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
  /* ---- Profile page specific styles ---- */
  .profile-page { max-width: 1000px; margin: 0 auto; padding: 48px 24px 80px; }
  .profile-head { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-bottom: 8px; }
  .avatar {
    width: 64px; height: 64px; border-radius: 50%; background: var(--brick); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 600; flex-shrink: 0;
  }
  .profile-head h1 { margin: 0; font-size: 1.7rem; }
  .since { font-size: 0.88rem; opacity: 0.65; margin-top: 3px; }

  .quick-links { display: flex; gap: 10px; flex-wrap: wrap; margin: 18px 0 26px; }
  .quick-links a { padding: 9px 16px; border-radius: 9px; border: 1.5px solid var(--line); text-decoration: none; font-weight: 600; font-size: 0.88rem; background: var(--card); }
  .quick-links a.logout { color: var(--brick); }

  .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }
  @media (max-width: 820px) { .profile-grid { grid-template-columns: 1fr; } }

  .panel { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
  .panel.wide { grid-column: 1 / -1; }
  .panel h2 { margin: 0 0 4px; font-size: 1.1rem; }
  .panel .sub { font-size: 0.85rem; opacity: 0.65; margin: 0 0 18px; }

  .choice-row { display: flex; gap: 10px; flex-wrap: wrap; }
  .choice { position: relative; }
  .choice input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
  .choice span { display: block; padding: 10px 16px; border: 1.5px solid var(--line); border-radius: 10px; font-weight: 600; font-size: 0.88rem; background: var(--paper); }
  .choice input:checked + span { border-color: var(--brick); background: color-mix(in srgb, var(--gold-soft) 40%, transparent); }
  .choice input:focus-visible + span { outline: 2px solid var(--gold); outline-offset: 2px; }
  .field-label { display: block; font-size: 0.85rem; font-weight: 600; margin: 0 0 8px; }

  .fav-list { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
  @media (max-width: 820px) { .fav-list { grid-template-columns: 1fr; } }
  .fav { border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; background: var(--paper); }
  .fav-name { font-weight: 600; }
  .fav-price { font-family: 'Fraunces', serif; font-weight: 600; margin: 4px 0 10px; }
  .fav-action { display: flex; gap: 8px; align-items: center; }
  .fav-action .btn-primary { padding: 7px 14px; font-size: 0.82rem; border-radius: 8px; }
  .fav-remove { background: none; border: none; color: var(--brick); font-weight: 600; font-size: 0.82rem; cursor: pointer; padding: 6px; }
  .fav-sold { font-size: 0.78rem; opacity: 0.7; font-weight: 600; }
  .fav-empty { opacity: 0.65; font-size: 0.92rem; display: none; }
  .fav-empty.show { display: block; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="profile-page">
  <div class="profile-head">
    <div class="avatar" aria-hidden="true"><?php echo htmlspecialchars($initial); ?></div>
    <div>
      <h1 class="display"><?php echo htmlspecialchars($customer['name']); ?></h1>
      <div class="since">Customer since <?php echo htmlspecialchars($customer['since']); ?></div>
    </div>
  </div>

  <div class="quick-links">
    <a href="order-history.php">My orders</a>
    <a href="notifications.php">Notifications</a>
    <a href="reviews.php">Reviews</a>
    <!-- logout.php ends the session in the backend stage -->
    <a href="logout.php" class="logout">Log out</a>
  </div>

  <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="profile-grid">

    <!-- Personal details -->
    <section class="panel">
      <h2 class="display">Your details</h2>
      <p class="sub">Used to prefill checkout.</p>

      <!-- The action file is built in the backend stage (action/profile-action.php) -->
      <form id="detailsForm" method="post" action="../action/profile-action.php" novalidate>
        <input type="hidden" name="form" value="details">

        <div class="field">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" autocomplete="name" required>
          <div class="error-text">Enter your name.</div>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" autocomplete="email" required>
          <div class="error-text">Enter a valid email address.</div>
        </div>
        <div class="field">
          <label for="phone">Mobile number</label>
          <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" autocomplete="tel" inputmode="numeric" maxlength="10" required>
          <div class="error-text">Enter a valid 10-digit mobile number.</div>
        </div>
        <button type="submit" class="btn-primary">Save details</button>
      </form>
    </section>

    <!-- Saved preferences -->
    <section class="panel">
      <h2 class="display">Your preferences</h2>
      <p class="sub">Saved for faster ordering.</p>

      <form id="prefForm" method="post" action="../action/profile-action.php">
        <input type="hidden" name="form" value="preferences">

        <div class="field">
          <span class="field-label">Food preference</span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="diet" value="" <?php echo $customer['diet'] === '' ? 'checked' : ''; ?>><span>No preference</span></label>
            <label class="choice"><input type="radio" name="diet" value="veg" <?php echo $customer['diet'] === 'veg' ? 'checked' : ''; ?>><span>Vegetarian</span></label>
            <label class="choice"><input type="radio" name="diet" value="nonveg" <?php echo $customer['diet'] === 'nonveg' ? 'checked' : ''; ?>><span>Non-veg</span></label>
          </div>
        </div>

        <div class="field">
          <span class="field-label">Spice level</span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="spice" value="mild" <?php echo $customer['spice'] === 'mild' ? 'checked' : ''; ?>><span>Mild</span></label>
            <label class="choice"><input type="radio" name="spice" value="medium" <?php echo $customer['spice'] === 'medium' ? 'checked' : ''; ?>><span>Medium</span></label>
            <label class="choice"><input type="radio" name="spice" value="hot" <?php echo $customer['spice'] === 'hot' ? 'checked' : ''; ?>><span>Hot</span></label>
          </div>
        </div>

        <div class="field">
          <span class="field-label">Usual order type</span>
          <div class="choice-row">
            <label class="choice"><input type="radio" name="order_type" value="dinein" <?php echo $customer['order_type'] === 'dinein' ? 'checked' : ''; ?>><span>Dine-in</span></label>
            <label class="choice"><input type="radio" name="order_type" value="takeaway" <?php echo $customer['order_type'] === 'takeaway' ? 'checked' : ''; ?>><span>Takeaway</span></label>
          </div>
        </div>

        <button type="submit" class="btn-primary">Save preferences</button>
      </form>
    </section>

    <!-- Favorites -->
    <section class="panel wide">
      <h2 class="display">Favourite dishes</h2>
      <p class="sub">Quick access to the dishes you love. Heart a dish on the menu to add it here.</p>

      <div class="fav-list" id="favList">
        <?php foreach ($favorites as $f): ?>
          <div class="fav" data-id="<?php echo (int) $f['id']; ?>">
            <div class="fav-name"><?php echo htmlspecialchars($f['name']); ?></div>
            <div class="fav-price">&#8377;<?php echo (int) $f['price']; ?></div>
            <div class="fav-action">
              <?php if ($f['available']): ?>
                <button type="button" class="btn-primary fav-add"
                        data-id="<?php echo (int) $f['id']; ?>"
                        data-name="<?php echo htmlspecialchars($f['name']); ?>"
                        data-price="<?php echo (int) $f['price']; ?>">Add to cart</button>
              <?php else: ?>
                <span class="fav-sold">Sold out today</span>
              <?php endif; ?>
              <button type="button" class="fav-remove" aria-label="Remove <?php echo htmlspecialchars($f['name']); ?> from favourites">Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="fav-empty <?php echo empty($favorites) ? 'show' : ''; ?>" id="favEmpty">No favourites yet. <a href="menu.php" style="color:var(--brick);font-weight:600;">Browse the menu</a></p>
    </section>

    <!-- Change password -->
    <section class="panel wide">
      <h2 class="display">Change password</h2>
      <p class="sub">Use at least 8 characters.</p>

      <form id="passForm" method="post" action="../action/profile-action.php" novalidate style="max-width:420px;">
        <input type="hidden" name="form" value="password">

        <div class="field">
          <label for="current_password">Current password</label>
          <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
          <div class="error-text">Enter your current password.</div>
        </div>
        <div class="field">
          <label for="new_password">New password</label>
          <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
          <div class="error-text">Password must be at least 8 characters.</div>
        </div>
        <div class="field">
          <label for="confirm_password">Confirm new password</label>
          <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
          <div class="error-text">Passwords do not match.</div>
        </div>
        <button type="submit" class="btn-primary">Update password</button>
      </form>
    </section>

  </div>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // Set to true once action/profile-action.php exists and the database is connected.
  const BACKEND_READY = false;

  // Only digits in the phone box
  const phoneEl = document.getElementById('phone');
  phoneEl.addEventListener('input', () => { phoneEl.value = phoneEl.value.replace(/\D/g, ''); });

  // ----- Details form -----
  document.getElementById('detailsForm').addEventListener('submit', (e) => {
    const nameEl = document.getElementById('name');
    const emailEl = document.getElementById('email');
    const ok = [
      Validate.setState(nameEl,  Validate.required(nameEl.value)),
      Validate.setState(emailEl, Validate.email(emailEl.value)),
      Validate.setState(phoneEl, Validate.phone(phoneEl.value))
    ];
    if (ok.includes(false)) { e.preventDefault(); return; }
    if (!BACKEND_READY) { e.preventDefault(); showToast('Details are valid. Saving will work once the backend is connected.'); }
  });

  // ----- Preferences form (no validation needed, just the backend switch) -----
  document.getElementById('prefForm').addEventListener('submit', (e) => {
    if (!BACKEND_READY) { e.preventDefault(); showToast('Preferences will be saved once the backend is connected.'); }
  });

  // ----- Password form -----
  document.getElementById('passForm').addEventListener('submit', (e) => {
    const cur = document.getElementById('current_password');
    const next = document.getElementById('new_password');
    const conf = document.getElementById('confirm_password');
    const ok = [
      Validate.setState(cur,  Validate.required(cur.value)),
      Validate.setState(next, Validate.password(next.value)),
      Validate.setState(conf, conf.value === next.value && conf.value !== '')
    ];
    if (ok.includes(false)) { e.preventDefault(); return; }
    if (!BACKEND_READY) { e.preventDefault(); showToast('Password is valid. Changing it will work once the backend is connected.'); }
  });

  // ----- Favourites -----
  document.querySelectorAll('.fav-add').forEach(btn => {
    btn.addEventListener('click', () => {
      Cart.add({ id: Number(btn.dataset.id), name: btn.dataset.name, price: Number(btn.dataset.price) });
    });
  });

  // Placeholder: removes the card from the page only. In the backend stage this
  // will also delete the row from the Favorite table.
  document.querySelectorAll('.fav-remove').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.closest('.fav').remove();
      if (document.querySelectorAll('#favList .fav').length === 0) {
        document.getElementById('favEmpty').classList.add('show');
      }
    });
  });
</script>

</body>
</html>