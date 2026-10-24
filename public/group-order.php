<?php
include '../includes/auth-check.php';
$active_page = 'group';
$page_title  = 'Group order | Pawan Punjabi Chinese Restaurant';

// Once login exists, protect this page by adding this line at the very top of the file:
//   include '../includes/auth-check.php';

// Placeholder room. Once MySQL is connected, load the customer's current room with:
//   SELECT g.* FROM GroupOrder g WHERE g.room_code = ? AND g.status = 'open';
//   and its items with: SELECT gi.*, c.name FROM GroupOrderItem gi
//   JOIN Customer c ON c.customer_id = gi.customer_id
//   JOIN MenuItem m ON m.item_id = gi.item_id WHERE gi.group_order_id = ?;
$room = null;

// DEMO ONLY: preview the "inside a room" view by opening group-order.php?demo=1
// (add &demo_closed=1 to preview a closed room). Delete this block when the database is connected.
if (isset($_GET['demo'])) {
    $room = [
        'code'       => 'PAW-4821',
        'status'     => isset($_GET['demo_closed']) ? 'closed' : 'open',
        'is_creator' => true,
        'participants' => [
            ['name' => 'You', 'creator' => true, 'items' => [
                ['name' => 'Paneer Manchurian', 'qty' => 1, 'price' => 210],
                ['name' => 'Butter Naan',       'qty' => 2, 'price' => 55],
            ]],
            ['name' => 'Riya', 'creator' => false, 'items' => [
                ['name' => 'Veg Hakka Noodles', 'qty' => 1, 'price' => 180],
            ]],
            ['name' => 'Kunal', 'creator' => false, 'items' => []],
        ],
    ];
}

// Dishes people can add to the room (same data as the menu page later)
$menu_items = [
    ['id' => 1, 'name' => 'Veg Hakka Noodles',  'price' => 180, 'veg' => true,  'available' => true],
    ['id' => 2, 'name' => 'Paneer Manchurian',  'price' => 210, 'veg' => true,  'available' => true],
    ['id' => 4, 'name' => 'Dal Makhani',        'price' => 190, 'veg' => true,  'available' => true],
    ['id' => 5, 'name' => 'Butter Naan',        'price' => 55,  'veg' => true,  'available' => true],
    ['id' => 6, 'name' => 'Chilli Chicken Dry', 'price' => 240, 'veg' => false, 'available' => true],
    ['id' => 8, 'name' => 'Butter Chicken',     'price' => 260, 'veg' => false, 'available' => true],
    ['id' => 9, 'name' => 'Chicken Momos',      'price' => 160, 'veg' => false, 'available' => false],
];

// Messages the backend can send back, e.g. group-order.php?error=notfound
$error_messages = [
    'notfound' => 'No open group order found with that code. Check the code and try again.',
    'closed'   => 'This group order has been closed by its creator.',
    'full'     => 'This group order is full.',
    'code'     => 'Enter a valid room code, for example PAW-4821.',
];
$success_messages = [
    'created' => 'Your group order room is ready. Share the code with your friends.',
    'joined'  => 'You joined the group order.',
    'added'   => 'Dish added to the group order.',
];
$error   = $error_messages[$_GET['error'] ?? ''] ?? '';
$success = $success_messages[$_GET['success'] ?? ''] ?? '';

// Totals (only when inside a room)
$group_total = 0;
if ($room) {
    foreach ($room['participants'] as $i => $p) {
        $sub = 0;
        foreach ($p['items'] as $it) { $sub += $it['price'] * $it['qty']; }
        $room['participants'][$i]['subtotal'] = $sub;
        $group_total += $sub;
    }
}
$room_open = $room && $room['status'] === 'open';
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
  /* ---- Group order page specific styles ---- */
  .group-page { max-width: 1040px; margin: 0 auto; padding: 48px 24px 80px; }
  .panel { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
  .panel h2 { margin: 0 0 6px; font-size: 1.1rem; }
  .panel .sub { font-size: 0.88rem; opacity: 0.65; margin: 0 0 18px; line-height: 1.5; }

  /* Lobby: create or join */
  .lobby { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 28px; }
  @media (max-width: 760px) { .lobby { grid-template-columns: 1fr; } }
  .code-input { text-transform: uppercase; letter-spacing: 2px; font-weight: 600; }

  /* Inside a room */
  .room-bar {
    display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;
    background: var(--gold-soft); border-radius: 14px; padding: 16px 22px; margin-top: 24px;
  }
  .room-code { font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700; letter-spacing: 2px; }
  .room-label { font-size: 0.8rem; opacity: 0.7; font-weight: 600; }
  .status-pill { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 0.76rem; font-weight: 700; background: var(--success-bg); color: var(--jade); margin-left: 8px; }
  .status-pill.closed { background: var(--danger-bg); color: var(--brick); }

  .room-layout { display: grid; grid-template-columns: 1fr 380px; gap: 22px; margin-top: 22px; align-items: start; }
  @media (max-width: 900px) { .room-layout { grid-template-columns: 1fr; } }

  .dish-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--line); }
  .dish-row:last-child { border-bottom: none; }
  .dish-left { display: flex; align-items: center; gap: 10px; }
  .dish-tag { width: 14px; height: 14px; border: 1.5px solid var(--jade); border-radius: 3px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .dish-tag.nonveg { border-color: var(--brick); }
  .dish-tag i { width: 6px; height: 6px; border-radius: 50%; background: var(--jade); }
  .dish-tag.nonveg i { background: var(--brick); }
  .dish-price { font-family: 'Fraunces', serif; font-weight: 600; margin-right: 10px; }
  .add-btn { width: 32px; height: 32px; border-radius: 9px; border: none; background: var(--brick); color: #fff; font-size: 1.15rem; cursor: pointer; line-height: 1; }
  .add-btn:disabled { background: var(--line); color: var(--ink); cursor: not-allowed; }
  .sold { font-size: 0.76rem; font-weight: 600; opacity: 0.65; }

  .person { padding: 12px 0; border-bottom: 1px solid var(--line); }
  .person:last-of-type { border-bottom: none; }
  .person-head { display: flex; justify-content: space-between; font-weight: 600; font-size: 0.95rem; }
  .person-head small { font-weight: 400; opacity: 0.6; margin-left: 6px; }
  .person-item { display: flex; justify-content: space-between; font-size: 0.86rem; opacity: 0.8; padding: 3px 0 0 4px; }
  .person-none { font-size: 0.84rem; opacity: 0.5; padding: 3px 0 0 4px; }
  .group-total { display: flex; justify-content: space-between; border-top: 1px solid var(--line); margin-top: 8px; padding-top: 14px; font-weight: 700; font-size: 1.08rem; }

  .room-action { display: grid; gap: 10px; margin-top: 18px; }
  .btn-danger { background: none; border: 1.5px solid var(--brick); color: var(--brick); padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 0.93rem; }
  .note { font-size: 0.82rem; opacity: 0.65; line-height: 1.5; margin: 12px 0 0; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="group-page">
  <h1 class="section-title display">Group order</h1>
  <p class="section-note">Everyone adds their own dishes to one shared order. The creator pays at checkout.</p>

  <?php if ($error): ?><div class="alert alert-error" style="margin-top:18px;" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success" style="margin-top:18px;" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<?php if (!$room): ?>

  <!-- ===== Not in a room: create or join ===== -->
  <div class="lobby">
    <section class="panel">
      <h2 class="display">Start a group order</h2>
      <p class="sub">You become the creator. We give you a room code to share with friends or family.</p>
      <!-- The action file is built in the backend stage (action/group-order-action.php) -->
      <form method="post" action="../action/group-order-action.php" class="js-group-form">
        <input type="hidden" name="action" value="create">
        <button type="submit" class="btn-primary btn-block">Create a room</button>
      </form>
    </section>

    <section class="panel">
      <h2 class="display">Join with a code</h2>
      <p class="sub">Got a code from a friend? Enter it to add your dishes.</p>
      <form method="post" action="../action/group-order-action.php" class="js-group-form" id="joinForm" novalidate>
        <input type="hidden" name="action" value="join">
        <div class="field">
          <label for="room_code">Room code</label>
          <input type="text" id="room_code" name="room_code" class="code-input" placeholder="PAW-4821" maxlength="8" autocomplete="off" required>
          <div class="error-text">Enter a valid room code, for example PAW-4821.</div>
        </div>
        <button type="submit" class="btn-primary btn-block">Join room</button>
      </form>
    </section>
  </div>

  <p class="note" style="margin-top:22px;">Preview the room view with <strong>group-order.php?demo=1</strong> while the backend is being built.</p>

<?php else: ?>

  <!-- ===== Inside a room ===== -->
  <div class="room-bar">
    <div>
      <div class="room-label">ROOM CODE <span class="status-pill <?php echo $room_open ? '' : 'closed'; ?>"><?php echo $room_open ? 'Open' : 'Closed'; ?></span></div>
      <div class="room-code" id="roomCode"><?php echo htmlspecialchars($room['code']); ?></div>
    </div>
    <button type="button" class="btn-ghost" id="copyBtn">Copy code</button>
  </div>

  <div class="room-layout">

    <!-- Menu to add from -->
    <section class="panel">
      <h2 class="display">Add your dishes</h2>
      <p class="sub"><?php echo $room_open ? 'Tap + to add a dish to the group order under your name.' : 'This room is closed, so no more dishes can be added.'; ?></p>

      <?php foreach ($menu_items as $m): ?>
        <div class="dish-row">
          <div class="dish-left">
            <span class="dish-tag <?php echo $m['veg'] ? '' : 'nonveg'; ?>"><i></i></span>
            <span><?php echo htmlspecialchars($m['name']); ?></span>
          </div>
          <div style="display:flex;align-items:center;">
            <span class="dish-price">&#8377;<?php echo (int) $m['price']; ?></span>
            <?php if ($m['available'] && $room_open): ?>
              <form method="post" action="../action/group-order-action.php" class="js-group-form">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="room_code" value="<?php echo htmlspecialchars($room['code']); ?>">
                <input type="hidden" name="item_id" value="<?php echo (int) $m['id']; ?>">
                <button type="submit" class="add-btn" aria-label="Add <?php echo htmlspecialchars($m['name']); ?> to the group order">+</button>
              </form>
            <?php elseif (!$m['available']): ?>
              <span class="sold">Sold out</span>
            <?php else: ?>
              <button class="add-btn" disabled aria-label="Room closed">+</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- Shared order -->
    <aside class="panel">
      <h2 class="display">Group order so far</h2>
      <p class="sub"><?php echo count($room['participants']); ?> people in this room</p>

      <?php foreach ($room['participants'] as $p): ?>
        <div class="person">
          <div class="person-head">
            <span><?php echo htmlspecialchars($p['name']); ?><?php if ($p['creator']): ?><small>creator</small><?php endif; ?></span>
            <span>&#8377;<?php echo number_format($p['subtotal']); ?></span>
          </div>
          <?php if (empty($p['items'])): ?>
            <div class="person-none">Nothing added yet</div>
          <?php else: foreach ($p['items'] as $it): ?>
            <div class="person-item">
              <span><?php echo htmlspecialchars($it['name']); ?> &times; <?php echo (int) $it['qty']; ?></span>
              <span>&#8377;<?php echo number_format($it['price'] * $it['qty']); ?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="group-total"><span>Group total</span><span>&#8377;<?php echo number_format($group_total); ?></span></div>

      <?php if ($room['is_creator']): ?>
        <div class="room-action">
          <!-- Checkout is the creator's step. Backend: copies the group items into the order, then opens checkout.php -->
          <form method="post" action="../action/group-order-action.php" class="js-group-form">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="room_code" value="<?php echo htmlspecialchars($room['code']); ?>">
            <button type="submit" class="btn-primary btn-block" <?php echo ($group_total === 0) ? 'disabled' : ''; ?>>Proceed to checkout</button>
          </form>
          <?php if ($room_open): ?>
            <form method="post" action="../action/group-order-action.php" class="js-group-form" onsubmit="return confirm('Close this room? Nobody can add dishes after this.');">
              <input type="hidden" name="action" value="close">
              <input type="hidden" name="room_code" value="<?php echo htmlspecialchars($room['code']); ?>">
              <button type="submit" class="btn-danger btn-block" style="width:100%;">Close room</button>
            </form>
          <?php endif; ?>
        </div>
        <p class="note">Only you, as the creator, can check out or close the room.</p>
      <?php else: ?>
        <p class="note">The room creator will check out when everyone has added their dishes.</p>
        <div class="room-action">
          <form method="post" action="../action/group-order-action.php" class="js-group-form">
            <input type="hidden" name="action" value="leave">
            <input type="hidden" name="room_code" value="<?php echo htmlspecialchars($room['code']); ?>">
            <button type="submit" class="btn-danger btn-block" style="width:100%;">Leave room</button>
          </form>
        </div>
      <?php endif; ?>
    </aside>
  </div>

<?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // Set to true once action/group-order-action.php exists and the database is connected.
  const BACKEND_READY = false;

  // Every group form (create, join, add, checkout, close, leave) goes through this check
  document.querySelectorAll('.js-group-form').forEach(form => {
    form.addEventListener('submit', (e) => {
      // Join form: check the code format first
      if (form.id === 'joinForm') {
        const codeEl = document.getElementById('room_code');
        codeEl.value = codeEl.value.trim().toUpperCase();
        const ok = /^PAW-\d{4}$/.test(codeEl.value);
        Validate.setState(codeEl, ok);
        if (!ok) { e.preventDefault(); return; }
      }
      if (!BACKEND_READY) {
        e.preventDefault();
        showToast('This will work once the backend is connected.');
      }
    });
  });

  // Copy the room code
  const copyBtn = document.getElementById('copyBtn');
  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      const code = document.getElementById('roomCode').textContent.trim();
      try {
        await navigator.clipboard.writeText(code);
        showToast('Room code copied: ' + code);
      } catch (err) {
        showToast('Copy failed. Your room code is ' + code);
      }
    });
  }
</script>

</body>
</html>