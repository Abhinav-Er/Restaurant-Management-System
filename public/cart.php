<?php
require_once '../includes/init.php';
$active_page = '';
$page_title  = 'Your cart | Pawan Punjabi Chinese Restaurant';

// The cart lives in the browser for now (see Cart in js/main.js) and is drawn by the script below.
// In the backend stage it moves to a PHP session / database, and this page will be filled
// from PHP instead.
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
  /* ---- Cart page specific styles ---- */
  .cart-page { max-width: 1120px; margin: 0 auto; padding: 48px 24px 80px; }
  .cart-layout { display: grid; grid-template-columns: 1fr 340px; gap: 28px; margin-top: 28px; align-items: start; }
  @media (max-width: 860px) { .cart-layout { grid-template-columns: 1fr; } }

  .cart-list { background: var(--card); border: 1px solid var(--line); border-radius: 16px; overflow: hidden; }
  .cart-row {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 18px;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--line);
  }
  .cart-row:last-child { border-bottom: none; }
  @media (max-width: 560px) {
    .cart-row { grid-template-columns: 1fr auto; row-gap: 10px; }
  }
  .item-name { font-weight: 600; }
  .item-unit { font-size: 0.82rem; opacity: 0.6; margin-top: 2px; }

  .qty { display: inline-flex; align-items: center; border: 1.5px solid var(--line); border-radius: 10px; overflow: hidden; }
  .qty button { width: 34px; height: 34px; border: none; background: var(--paper-dim); color: var(--ink); font-size: 1.1rem; cursor: pointer; }
  .qty span { min-width: 34px; text-align: center; font-weight: 600; font-size: 0.95rem; }

  .line-total { font-family: 'Fraunces', serif; font-weight: 600; min-width: 70px; text-align: right; }
  .remove-btn { background: none; border: none; color: var(--brick); font-weight: 600; font-size: 0.82rem; cursor: pointer; padding: 6px; }

  .list-foot { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; background: var(--paper-dim); font-size: 0.88rem; }
  .list-foot button { background: none; border: none; color: var(--brick); font-weight: 600; cursor: pointer; font-size: 0.88rem; }

  .summary { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 22px; position: sticky; top: 90px; }
  .summary h2 { margin: 0 0 16px; font-size: 1.15rem; }
  .sum-row { display: flex; justify-content: space-between; font-size: 0.93rem; padding: 6px 0; }
  .sum-row.total { border-top: 1px solid var(--line); margin-top: 8px; padding-top: 14px; font-weight: 700; font-size: 1.05rem; }
  .sum-note { font-size: 0.8rem; opacity: 0.6; margin: 12px 0 18px; line-height: 1.5; }

  .empty-cart { text-align: center; padding: 64px 24px; background: var(--card); border: 1px solid var(--line); border-radius: 16px; margin-top: 28px; }
  .empty-cart h2 { margin: 0 0 8px; }
  .empty-cart p { opacity: 0.65; margin: 0 0 22px; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="cart-page">
  <h1 class="section-title display">Your cart</h1>
  <p class="section-note" id="cartSubtitle">Review your items before checking out.</p>

  <!-- Shown when the cart has items -->
  <div class="cart-layout" id="cartLayout" hidden>
    <div class="cart-list">
      <div id="cartRows"></div>
      <div class="list-foot">
        <a href="menu.php" style="color:var(--brick);font-weight:600;text-decoration:none;">+ Add more dishes</a>
        <button type="button" id="clearBtn">Clear cart</button>
      </div>
    </div>

    <aside class="summary">
      <h2 class="display">Order summary</h2>
      <div class="sum-row"><span>Items</span><span id="sumCount">0</span></div>
      <div class="sum-row"><span>Subtotal</span><span id="sumSubtotal">&#8377;0</span></div>
      <div class="sum-row total"><span>Total</span><span id="sumTotal">&#8377;0</span></div>
      <p class="sum-note">Coupons, order type (dine-in or takeaway) and scheduling are chosen at checkout.</p>
      <a href="checkout.php" class="btn-primary btn-block">Proceed to checkout</a>
    </aside>
  </div>

  <!-- Shown when the cart is empty -->
  <div class="empty-cart" id="emptyCart" hidden>
    <h2 class="display">Your cart is empty</h2>
    <p>Add a few dishes from the menu and they'll show up here.</p>
    <a href="menu.php" class="btn-primary">Browse menu</a>
  </div>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  const layoutEl   = document.getElementById('cartLayout');
  const emptyEl    = document.getElementById('emptyCart');
  const rowsEl     = document.getElementById('cartRows');
  const subtitleEl = document.getElementById('cartSubtitle');

  function makeButton(text, label, onClick) {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = text;
    b.setAttribute('aria-label', label);
    b.addEventListener('click', onClick);
    return b;
  }

  function renderCart() {
    const items = Cart.get();

    if (items.length === 0) {
      layoutEl.hidden = true;
      emptyEl.hidden = false;
      subtitleEl.hidden = true;
      return;
    }

    layoutEl.hidden = false;
    emptyEl.hidden = true;
    subtitleEl.hidden = false;
    rowsEl.innerHTML = '';

    items.forEach(item => {
      const row = document.createElement('div');
      row.className = 'cart-row';

      // Name + unit price (textContent keeps dish names safe from HTML injection)
      const info = document.createElement('div');
      const name = document.createElement('div');
      name.className = 'item-name';
      name.textContent = item.name;
      const unit = document.createElement('div');
      unit.className = 'item-unit';
      unit.textContent = formatPrice(item.price) + ' each';
      info.append(name, unit);

      // Quantity controls
      const qty = document.createElement('div');
      qty.className = 'qty';
      const qtyNum = document.createElement('span');
      qtyNum.textContent = item.qty;
      qty.append(
        makeButton('\u2212', 'Decrease quantity of ' + item.name, () => { Cart.setQty(item.id, item.qty - 1); renderCart(); }),
        qtyNum,
        makeButton('+', 'Increase quantity of ' + item.name, () => { Cart.setQty(item.id, item.qty + 1); renderCart(); })
      );

      // Line total
      const line = document.createElement('div');
      line.className = 'line-total';
      line.textContent = formatPrice(item.price * item.qty);

      // Remove
      const remove = makeButton('Remove', 'Remove ' + item.name + ' from cart', () => { Cart.remove(item.id); renderCart(); });
      remove.className = 'remove-btn';

      row.append(info, qty, line, remove);
      rowsEl.appendChild(row);
    });

    document.getElementById('sumCount').textContent = Cart.count();
    document.getElementById('sumSubtotal').textContent = formatPrice(Cart.total());
    document.getElementById('sumTotal').textContent = formatPrice(Cart.total());
  }

  document.getElementById('clearBtn').addEventListener('click', () => {
    if (confirm('Remove all items from your cart?')) {
      Cart.clear();
      renderCart();
    }
  });

  renderCart();
</script>

</body>
</html>