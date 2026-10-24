 <?php
require_once '../includes/init.php';
require_once '../config/db.php';

$active_page = 'home';
$page_title  = 'Pawan Punjabi Chinese Restaurant';

// Up to 3 dishes marked as featured, only if they can be ordered right now
$featured_dishes = $pdo->query(
    "SELECT item_id AS id, name, description AS `desc`, price, is_veg AS veg
     FROM menu_item
     WHERE is_featured = 1 AND is_available = 1 AND stock_quantity > 0
     LIMIT 3"
)->fetchAll();

// The 3 best recent reviews that have a written comment
$top_reviews = $pdo->query(
    "SELECT c.name, r.rating, m.name AS dish, r.review_text AS text
     FROM review r
     JOIN customer c  ON c.customer_id = r.customer_id
     JOIN menu_item m ON m.item_id = r.item_id
     WHERE r.is_hidden = 0 AND r.review_text <> ''
     ORDER BY r.rating DESC, r.created_at DESC
     LIMIT 3"
)->fetchAll();
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
  /* ---- Home page specific styles ---- */
  .hero {
    padding: 64px 24px 40px;
    max-width: 1120px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1.05fr 0.95fr;
    gap: 48px;
    align-items: center;
  }
  @media (max-width: 860px) { .hero { grid-template-columns: 1fr; padding-top: 40px; gap: 32px; } }

  .eyebrow { color: var(--brick); font-weight: 600; font-size: 0.95rem; margin-bottom: 14px; }
  h1.hero-title { font-size: clamp(2.2rem, 5vw, 3.4rem); line-height: 1.05; font-weight: 600; margin: 0 0 20px; max-width: 12ch; }
  .hero-title .accent { color: var(--brick); font-style: italic; font-weight: 500; }
  .hero-sub { font-size: 1.08rem; line-height: 1.6; opacity: 0.78; max-width: 46ch; margin: 0 0 28px; }
  .hero-action { display: flex; gap: 14px; flex-wrap: wrap; align-items: center; }
  .hero-meta { display: flex; gap: 26px; margin-top: 34px; padding-top: 22px; border-top: 1px solid var(--line); flex-wrap: wrap; }
  .hero-meta div { font-size: 0.85rem; opacity: 0.65; }
  .hero-meta strong { display: block; font-size: 1rem; opacity: 1; font-family: 'Fraunces', serif; font-weight: 600; }
  .plate { background: var(--card); border-radius: 28px; padding: 28px; box-shadow: 0 24px 60px -20px var(--shadow); border: 1px solid var(--line); }

  .section-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 28px; }
  .link-more { font-weight: 600; font-size: 0.92rem; color: var(--brick); text-decoration: none; }

  .dish-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  @media (max-width: 780px) { .dish-grid { grid-template-columns: 1fr; } }
  .dish-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 18px; display: flex; flex-direction: column; gap: 8px; }
  .dish-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
  .dish-name { font-weight: 600; line-height: 1.25; }
  .dish-tag { width: 15px; height: 15px; border: 1.5px solid var(--jade); border-radius: 3px; flex-shrink: 0; margin-top: 3px; display: flex; align-items: center; justify-content: center; }
  .dish-tag.nonveg { border-color: var(--brick); }
  .dish-tag i { width: 7px; height: 7px; border-radius: 50%; background: var(--jade); }
  .dish-tag.nonveg i { background: var(--brick); }
  .dish-desc { font-size: 0.85rem; opacity: 0.6; line-height: 1.4; }
  .dish-foot { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; }
  .dish-price { font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.05rem; }
  .add-btn { width: 34px; height: 34px; border-radius: 9px; border: none; background: var(--brick); color: #fff; font-size: 1.2rem; cursor: pointer; line-height: 1; }

  .steps-section { background: var(--paper-dim); }
  .steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 28px; margin-top: 28px; }
  @media (max-width: 780px) { .steps { grid-template-columns: repeat(2, 1fr); } }
  .step-num { font-family: 'Fraunces', serif; font-size: 2.4rem; font-weight: 600; color: var(--gold); line-height: 1; margin-bottom: 12px; }
  .step h3 { margin: 0 0 6px; font-size: 1.05rem; }
  .step p { margin: 0; font-size: 0.88rem; opacity: 0.65; line-height: 1.5; }

  .options-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  @media (max-width: 780px) { .options-grid { grid-template-columns: 1fr; } }
  .option-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 24px; text-decoration: none; display: block; }
  .option-card h3 { margin: 0 0 8px; font-size: 1.1rem; font-family: 'Fraunces', serif; }
  .option-card p { margin: 0 0 14px; font-size: 0.9rem; opacity: 0.65; line-height: 1.5; }
  .option-card span { font-weight: 600; font-size: 0.9rem; color: var(--brick); }

  .review-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  @media (max-width: 780px) { .review-grid { grid-template-columns: 1fr; } }
  .review-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 20px; }
  .stars { color: var(--gold); letter-spacing: 2px; font-size: 1rem; }
  .stars .off { opacity: 0.25; }
  .review-card p { margin: 10px 0 12px; font-size: 0.92rem; line-height: 1.55; }
  .review-meta { font-size: 0.8rem; opacity: 0.6; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<section class="hero">
  <div>
    <div class="eyebrow">Tarsadi, Surat</div>
    <h1 class="hero-title display">Punjabi warmth, <span class="accent">Chinese heat</span></h1>
    <p class="hero-sub">Order the Indo-Chinese and Punjabi dishes you already love from Pawan. Browse the full menu, pay online, and track your order right up to your table.</p>
    <div class="hero-action">
      <a href="menu.php" class="btn-primary">View full menu</a>
      <a href="#how" class="btn-ghost">How ordering works</a>
    </div>
    <div class="hero-meta">
      <div><strong>25&ndash;30 min</strong>Typical prep time</div>
      <div><strong>Dine-in &amp; takeaway</strong>Both supported</div>
      <div><strong>11 AM &ndash; 11 PM</strong>Open daily</div>
    </div>
  </div>
  <div>
    <div class="plate">
      <svg viewBox="0 0 400 320" width="100%" role="img" aria-label="Illustration of a plate of noodles">
        <ellipse cx="200" cy="270" rx="150" ry="18" fill="var(--paper-dim)"/>
        <circle cx="200" cy="160" r="120" fill="var(--gold-soft)"/>
        <circle cx="200" cy="160" r="92" fill="var(--card)"/>
        <path d="M120 165c10-30 40-45 80-45s70 15 80 45" stroke="var(--gold)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M128 178c14-22 44-33 72-33s58 11 72 33" stroke="var(--brick)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <path d="M136 190c16-16 40-24 64-24s48 8 64 24" stroke="var(--gold)" stroke-width="6" fill="none" stroke-linecap="round"/>
        <circle cx="150" cy="140" r="7" fill="var(--brick)"/>
        <circle cx="250" cy="135" r="7" fill="var(--jade)"/>
        <circle cx="200" cy="120" r="7" fill="var(--brick)"/>
        <path d="M160 70q-6-20 4-34" stroke="var(--gold)" stroke-width="4" fill="none" stroke-linecap="round" opacity="0.55"/>
        <path d="M198 62q-6-22 4-36" stroke="var(--gold)" stroke-width="4" fill="none" stroke-linecap="round" opacity="0.55"/>
        <path d="M236 70q-6-20 4-34" stroke="var(--gold)" stroke-width="4" fill="none" stroke-linecap="round" opacity="0.55"/>
      </svg>
    </div>
  </div>
</section>

<section class="section">
  <div class="section-head">
    <div>
      <h2 class="section-title display">Today's favourites</h2>
      <p class="section-note">A quick look at what's popular. The full menu has everything on offer.</p>
    </div>
    <a href="menu.php" class="link-more">See full menu</a>
  </div>

  <div class="dish-grid">
    <?php foreach ($featured_dishes as $dish): ?>
      <div class="dish-card">
        <div class="dish-top">
          <div class="dish-name"><?php echo htmlspecialchars($dish['name']); ?></div>
          <div class="dish-tag <?php echo $dish['veg'] ? '' : 'nonveg'; ?>" title="<?php echo $dish['veg'] ? 'Vegetarian' : 'Non-vegetarian'; ?>"><i></i></div>
        </div>
        <div class="dish-desc"><?php echo htmlspecialchars($dish['desc']); ?></div>
        <div class="dish-foot">
          <div class="dish-price">&#8377;<?php echo (int) $dish['price']; ?></div>
          <button class="add-btn"
                  aria-label="Add <?php echo htmlspecialchars($dish['name']); ?> to cart"
                  data-id="<?php echo (int) $dish['id']; ?>"
                  data-name="<?php echo htmlspecialchars($dish['name']); ?>"
                  data-price="<?php echo (int) $dish['price']; ?>">+</button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section steps-section" id="how" style="max-width:none;">
  <div style="max-width:1120px;margin:0 auto;">
    <h2 class="section-title display">How ordering works</h2>
    <div class="steps">
      <div class="step">
        <div class="step-num display">1</div>
        <h3>Browse &amp; add to cart</h3>
        <p>Pick dishes from the live menu. Availability updates as the kitchen preps.</p>
      </div>
      <div class="step">
        <div class="step-num display">2</div>
        <h3>Pay online</h3>
        <p>Check out securely and get an instant, itemised bill.</p>
      </div>
      <div class="step">
        <div class="step-num display">3</div>
        <h3>Kitchen prepares</h3>
        <p>Your order goes straight to the kitchen. No phone calls needed.</p>
      </div>
      <div class="step">
        <div class="step-num display">4</div>
        <h3>Track &amp; collect</h3>
        <p>Watch the status move from preparing to ready, in real time.</p>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="section-head">
    <div>
      <h2 class="section-title display">Order your way</h2>
      <p class="section-note">Whether it's just you, a group, or a later time slot.</p>
    </div>
  </div>
  <div class="options-grid">
    <a href="menu.php" class="option-card">
      <h3>Order now</h3>
      <p>Browse the menu, build your cart and check out in a few taps.</p>
      <span>Start ordering</span>
    </a>
    <a href="group-order.php" class="option-card">
      <h3>Group order</h3>
      <p>Create a room, share the code, and let everyone add their own dishes to one bill.</p>
      <span>Start a group</span>
    </a>
    <a href="menu.php" class="option-card">
      <h3>Schedule for later</h3>
      <p>Pick a time during opening hours and we'll have it ready when you arrive.</p>
      <span>Pick a time at checkout</span>
    </a>
  </div>
</section>
<?php if (!empty($top_reviews)): ?>
<section class="section" style="padding-top:0;">
  <div class="section-head">
    <div>
      <h2 class="section-title display">What customers say</h2>
      <p class="section-note">Reviews come from customers who have ordered the dish.</p>
    </div>
    <a href="reviews.php" class="link-more">Read all reviews</a>
  </div>
  <div class="review-grid">
    <?php foreach ($top_reviews as $r): ?>
      <div class="review-card">
        <div class="stars" aria-label="<?php echo (int) $r['rating']; ?> out of 5 stars">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="<?php echo $i <= $r['rating'] ? '' : 'off'; ?>">&#9733;</span>
          <?php endfor; ?>
        </div>
        <p><?php echo htmlspecialchars($r['text']); ?></p>
        <div class="review-meta"><?php echo htmlspecialchars($r['name']); ?> &middot; <?php echo htmlspecialchars($r['dish']); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // Add-to-cart buttons on this page (uses the shared Cart helper from main.js)
  document.querySelectorAll('.add-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      Cart.add({
        id: Number(btn.dataset.id),
        name: btn.dataset.name,
        price: Number(btn.dataset.price)
      });
    });
  });
</script>

</body>
</html>