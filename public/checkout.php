<?php
include '../includes/auth-check.php';
$active_page = '';
$page_title  = 'Checkout | Pawan Punjabi Chinese Restaurant';

// Opening hours used by the scheduling rules (24-hour clock).
$open_hour  = 11;
$close_hour = 23;
$min_lead_minutes = 30;   // earliest scheduled time = now + 30 minutes

// Placeholder: set to a room code when the order comes from a group room.
// Later this comes from the session: $_SESSION['group_room_code'] ?? null
$group_room_code = null;
  $error_messages = [
      'details'  => 'Please check your name, mobile number and order details.',
      'schedule' => 'The scheduled time is not valid. Pick a time during opening hours, at least 30 minutes from now.',
      'cart'     => 'Your cart could not be read. Please go back to the cart and try again.',
      'stock'    => 'One of the dishes is sold out or has less stock than you asked for. Please update your cart.',
      'coupon'   => 'That coupon is not valid for this order. Remove it and try again.',
      'failed'   => 'Something went wrong while placing your order. You have not been charged. Please try again.',
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
  /* ---- Checkout page specific styles ---- */
  .checkout-page { max-width: 1120px; margin: 0 auto; padding: 48px 24px 80px; }
  .checkout-layout { display: grid; grid-template-columns: 1fr 360px; gap: 28px; margin-top: 28px; align-items: start; }
  @media (max-width: 900px) { .checkout-layout { grid-template-columns: 1fr; } }

  .panel { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 22px; margin-bottom: 20px; }
  .panel h2 { margin: 0 0 16px; font-size: 1.1rem; }

  .choice-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
  @media (max-width: 480px) { .choice-grid { grid-template-columns: 1fr; } }
  .choice { position: relative; }
  .choice input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
  .choice span {
    display: block; padding: 14px 16px; border: 1.5px solid var(--line);
    border-radius: 12px; font-weight: 600; font-size: 0.93rem; background: var(--paper);
  }
  .choice span small { display: block; font-weight: 400; opacity: 0.6; margin-top: 3px; font-size: 0.8rem; }
  .choice input:checked + span { border-color: var(--brick); background: color-mix(in srgb, var(--gold-soft) 40%, transparent); }
  .choice input:focus-visible + span { outline: 2px solid var(--gold); outline-offset: 2px; }

  .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  @media (max-width: 480px) { .row-2 { grid-template-columns: 1fr; gap: 0; } }

  .coupon-row { display: flex; gap: 10px; }
  .coupon-row input { flex: 1; }
  .coupon-msg { font-size: 0.82rem; margin-top: 8px; }
  .coupon-msg.ok { color: var(--jade); }
  .coupon-msg.bad { color: var(--brick); }

  .summary { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 22px; position: sticky; top: 90px; }
  .summary h2 { margin: 0 0 14px; font-size: 1.15rem; }
  .sum-item { display: flex; justify-content: space-between; gap: 12px; font-size: 0.9rem; padding: 5px 0; }
  .sum-item span:first-child { opacity: 0.85; }
  .sum-divider { border-top: 1px solid var(--line); margin: 10px 0; }
  .sum-row { display: flex; justify-content: space-between; font-size: 0.93rem; padding: 5px 0; }
  .sum-row.discount { color: var(--jade); }
  .sum-row.total { font-weight: 700; font-size: 1.08rem; padding-top: 10px; }
  .group-note { background: var(--gold-soft); border-radius: 10px; padding: 10px 14px; font-size: 0.85rem; margin-bottom: 16px; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="checkout-page">
  <h1 class="section-title display">Checkout</h1>
  <p class="section-note">Confirm your details, choose when you want it, and pay.</p>
    <?php if ($error): ?><div class="alert alert-error" style="margin-top:16px;" role="alert">
      <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <?php if ($group_room_code): ?>
    <div class="group-note" style="margin-top:16px;">Group order from room <strong><?php echo htmlspecialchars($group_room_code); ?></strong>. Items are listed by participant in the summary.</div>
  <?php endif; ?>

  <!-- The action file is built in the backend stage (action/place-order-action.php) -->
  <form id="checkoutForm" method="post" action="../action/place-order-action.php" novalidate>
    <input type="hidden" name="cart_json" id="cartJson">
    <input type="hidden" name="coupon_code" id="couponHidden">

    <div class="checkout-layout">
      <div>

        <!-- 1. Order type -->
        <section class="panel">
          <h2 class="display">1. Order type</h2>
          <div class="choice-grid">
            <label class="choice">
              <input type="radio" name="order_type" value="dinein" checked>
              <span>Dine-in<small>Eat at the restaurant</small></span>
            </label>
            <label class="choice">
              <input type="radio" name="order_type" value="takeaway">
              <span>Takeaway<small>Collect from the counter</small></span>
            </label>
          </div>
          <div class="field" id="tableField" style="margin-top:16px;margin-bottom:0;">
            <label for="table_no">Table number (optional)</label>
            <input type="text" id="table_no" name="table_no" maxlength="10">
          </div>
        </section>

        <!-- 2. Your details -->
        <section class="panel">
          <h2 class="display">2. Your details</h2>
          <div class="row-2">
            <div class="field">
              <label for="name">Name</label>
              <input type="text" id="name" name="name" autocomplete="name" required>
              <div class="error-text">Enter your name.</div>
            </div>
            <div class="field">
              <label for="phone">Mobile number</label>
              <input type="tel" id="phone" name="phone" autocomplete="tel" inputmode="numeric" maxlength="10" required>
              <div class="error-text">Enter a valid 10-digit mobile number.</div>
            </div>
          </div>
          <div class="field" style="margin-bottom:0;">
            <label for="notes">Notes for the kitchen (optional)</label>
            <textarea id="notes" name="notes" rows="2" maxlength="200" placeholder="Less spicy, no onion, etc."></textarea>
          </div>
        </section>

        <!-- 3. When -->
        <section class="panel">
          <h2 class="display">3. When do you want it?</h2>
          <div class="choice-grid">
            <label class="choice">
              <input type="radio" name="timing" value="asap" checked>
              <span>As soon as possible<small>About 25&ndash;30 minutes</small></span>
            </label>
            <label class="choice">
              <input type="radio" name="timing" value="later">
              <span>Schedule for later<small>Pick a time today or later</small></span>
            </label>
          </div>
          <div class="field" id="scheduleField" style="margin-top:16px;margin-bottom:0;" hidden>
            <label for="scheduled_time">Date and time</label>
            <input type="datetime-local" id="scheduled_time" name="scheduled_time">
            <div class="hint">Open daily <?php echo $open_hour; ?>:00 to <?php echo $close_hour; ?>:00. At least <?php echo $min_lead_minutes; ?> minutes from now.</div>
            <div class="error-text" id="scheduleError">Choose a valid time.</div>
          </div>
        </section>

        <!-- 4. Coupon -->
        <section class="panel">
          <h2 class="display">4. Coupon</h2>
          <div class="coupon-row">
            <input type="text" id="couponInput" placeholder="Enter coupon code" autocapitalize="characters" aria-label="Coupon code"
                   style="padding:12px 14px;border-radius:10px;border:1.5px solid var(--line);background:var(--paper);color:var(--ink);font-family:inherit;">
            <button type="button" class="btn-ghost" id="couponBtn">Apply</button>
          </div>
          <div class="coupon-msg" id="couponMsg" aria-live="polite"></div>
        </section>

        <!-- 5. Payment -->
        <section class="panel">
          <h2 class="display">5. Payment</h2>
          <div class="choice-grid">
            <label class="choice">
              <input type="radio" name="payment_method" value="upi" checked>
              <span>UPI<small>Pay online</small></span>
            </label>
            <label class="choice">
              <input type="radio" name="payment_method" value="card">
              <span>Card<small>Credit or debit, pay online</small></span>
            </label>
          </div>
        </section>
      </div>

      <!-- Order summary -->
      <aside class="summary">
        <h2 class="display">Order summary</h2>
        <div id="sumItems"></div>
        <div class="sum-divider"></div>
        <div class="sum-row"><span>Subtotal</span><span id="sumSubtotal">&#8377;0</span></div>
        <div class="sum-row discount" id="discountRow" hidden><span>Discount</span><span id="sumDiscount">-&#8377;0</span></div>
        <div class="sum-row total"><span>Total</span><span id="sumTotal">&#8377;0</span></div>
        <button type="submit" class="btn-primary btn-block" style="margin-top:18px;" id="placeBtn">Continue to payment</button>
        <p style="font-size:0.78rem;opacity:0.6;margin:12px 0 0;line-height:1.5;">Your bill is generated automatically after payment is confirmed.</p>
      </aside>
    </div>
  </form>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // Set to true once action/place-order-action.php exists and the database is connected.
  const BACKEND_READY = true;

  const OPEN_HOUR = <?php echo (int) $open_hour; ?>;
  const CLOSE_HOUR = <?php echo (int) $close_hour; ?>;
  const MIN_LEAD_MIN = <?php echo (int) $min_lead_minutes; ?>;

  // DEMO coupons for the frontend stage only.
  // Real validation happens on the server (coupon rules, expiry, minimum order).
  const DEMO_COUPONS = { 'WELCOME10': 10, 'PAWAN20': 20 };

  const items = Cart.get();
  if (items.length === 0) {
    window.location.href = 'cart.php';   // nothing to check out
  }

  let discountPercent = 0;

  // ----- Order summary -----
  function renderSummary() {
    const box = document.getElementById('sumItems');
    box.innerHTML = '';
    items.forEach(it => {
      const row = document.createElement('div');
      row.className = 'sum-item';
      const left = document.createElement('span');
      left.textContent = it.name + ' \u00D7 ' + it.qty;
      const right = document.createElement('span');
      right.textContent = formatPrice(it.price * it.qty);
      row.append(left, right);
      box.appendChild(row);
    });

    const subtotal = Cart.total();
    const discount = Math.round(subtotal * discountPercent / 100);
    document.getElementById('sumSubtotal').textContent = formatPrice(subtotal);
    document.getElementById('discountRow').hidden = discount === 0;
    document.getElementById('sumDiscount').textContent = '-' + formatPrice(discount);
    document.getElementById('sumTotal').textContent = formatPrice(subtotal - discount);
  }
  renderSummary();

  // ----- Order type: table number only matters for dine-in -----
  const tableField = document.getElementById('tableField');
  document.querySelectorAll('input[name="order_type"]').forEach(r => {
    r.addEventListener('change', () => {
      tableField.hidden = r.value !== 'dinein' || !r.checked;
    });
  });

  // ----- Scheduling -----
  const scheduleField = document.getElementById('scheduleField');
  const scheduleInput = document.getElementById('scheduled_time');

  function toLocalInputValue(date) {
    const pad = n => String(n).padStart(2, '0');
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) +
           'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
  }

  function earliestSlot() {
    return new Date(Date.now() + MIN_LEAD_MIN * 60000);
  }

  scheduleInput.min = toLocalInputValue(earliestSlot());

  document.querySelectorAll('input[name="timing"]').forEach(r => {
    r.addEventListener('change', () => {
      const later = document.querySelector('input[name="timing"]:checked').value === 'later';
      scheduleField.hidden = !later;
      if (later && !scheduleInput.value) {
        scheduleInput.value = toLocalInputValue(earliestSlot());
      }
    });
  });

  // Returns an error message, or '' if the chosen time is fine
  function scheduleProblem() {
    if (!scheduleInput.value) return 'Choose a date and time.';
    const chosen = new Date(scheduleInput.value);
    if (isNaN(chosen)) return 'Choose a valid time.';
    if (chosen < earliestSlot()) return 'Pick a time at least ' + MIN_LEAD_MIN + ' minutes from now.';
    const minutes = chosen.getHours() * 60 + chosen.getMinutes();
    if (minutes < OPEN_HOUR * 60 || minutes > CLOSE_HOUR * 60) {
      return 'We are open ' + OPEN_HOUR + ':00 to ' + CLOSE_HOUR + ':00. Pick a time in that window.';
    }
    return '';
  }

  // ----- Coupon (demo) -----
  const couponInput = document.getElementById('couponInput');
  const couponMsg = document.getElementById('couponMsg');
  const couponHidden = document.getElementById('couponHidden');

  document.getElementById('couponBtn').addEventListener('click', () => {
    const code = couponInput.value.trim().toUpperCase();
    couponMsg.className = 'coupon-msg';
    if (code === '') {
      discountPercent = 0;
      couponHidden.value = '';
      couponMsg.textContent = '';
    } else if (DEMO_COUPONS[code]) {
      discountPercent = DEMO_COUPONS[code];
      couponHidden.value = code;
      couponMsg.classList.add('ok');
      couponMsg.textContent = 'Coupon applied: ' + discountPercent + '% off.';
    } else {
      discountPercent = 0;
      couponHidden.value = '';
      couponMsg.classList.add('bad');
      couponMsg.textContent = 'This coupon is not valid.';
    }
    renderSummary();
  });

  // ----- Details validation + submit -----
  const phoneEl = document.getElementById('phone');
  phoneEl.addEventListener('input', () => { phoneEl.value = phoneEl.value.replace(/\D/g, ''); });

  document.getElementById('checkoutForm').addEventListener('submit', (e) => {
    const nameOk  = Validate.setState(document.getElementById('name'), Validate.required(document.getElementById('name').value));
    const phoneOk = Validate.setState(phoneEl, Validate.phone(phoneEl.value));

    let timeOk = true;
    if (document.querySelector('input[name="timing"]:checked').value === 'later') {
      const problem = scheduleProblem();
      timeOk = problem === '';
      scheduleField.classList.toggle('invalid', !timeOk);
      document.getElementById('scheduleError').textContent = problem;
    }

    if (!nameOk || !phoneOk || !timeOk) {
      e.preventDefault();
      return;
    }

    // Send the cart to the server along with the form
    document.getElementById('cartJson').value = JSON.stringify(items);
      document.getElementById('placeBtn').disabled = true;

    if (!BACKEND_READY) {
      e.preventDefault();
      showToast('Details are valid. Placing orders will work once the backend is connected.');
    }
  });
</script>

</body>
</html>