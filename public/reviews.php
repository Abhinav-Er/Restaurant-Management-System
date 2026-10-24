<?php
require_once '../includes/init.php';
require_once '../config/db.php';
$active_page = 'reviews';
$page_title  = 'Reviews | Pawan Punjabi Chinese Restaurant';

// Placeholder data. Once MySQL is connected, replace with:
//   dishes:  SELECT item_id, name FROM MenuItem ORDER BY name;
//   reviews: SELECT r.review_id, r.item_id, m.name AS dish, c.name AS customer, r.rating,
//                   r.review_text, r.created_at
//            FROM Review r
//            JOIN MenuItem m ON m.item_id = r.item_id
//            JOIN Customer c ON c.customer_id = r.customer_id
//            WHERE r.is_hidden = 0            -- hidden by an admin in review moderation
//              AND (? = 0 OR r.item_id = ?);  -- optional dish filter
// Every review here is "verified": the backend only saves a review from a customer
// who has a delivered order containing that dish.
// All dishes, for the filter dropdown
$dishes = [];
foreach ($pdo->query('SELECT item_id, name FROM menu_item ORDER BY name')->fetchAll() as $d) {
    $dishes[(int) $d['item_id']] = $d['name'];
}

// All visible reviews. Reviews hidden by an admin are skipped.
$rows = $pdo->query(
    'SELECT r.review_id, r.item_id, c.name AS customer, r.rating,
            DATE(r.created_at) AS review_date, r.review_text
     FROM review r
     JOIN customer c ON c.customer_id = r.customer_id
     WHERE r.is_hidden = 0
     ORDER BY r.created_at DESC'
)->fetchAll();

$all_reviews = array_map(function ($r) {
    // Show a first name and last initial only, e.g. "Riya S.", to protect privacy
    $parts = preg_split('/\s+/', trim($r['customer']));
    $short = $parts[0];
    if (count($parts) > 1) {
        $short .= ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
    }
    return [
        'id'       => (int) $r['review_id'],
        'item_id'  => (int) $r['item_id'],
        'customer' => $short,
        'rating'   => (int) $r['rating'],
        'date'     => $r['review_date'],
        'text'     => $r['review_text'],
    ];
}, $rows);

// ----- Read the filter and sort from the address bar, allowing only known values -----
$item_filter = (int) ($_GET['item'] ?? 0);
if ($item_filter !== 0 && !isset($dishes[$item_filter])) {
    $item_filter = 0;
}
$sort_options = [
    'newest'  => 'Newest first',
    'highest' => 'Highest rated',
    'lowest'  => 'Lowest rated',
];
$sort = array_key_exists($_GET['sort'] ?? '', $sort_options) ? $_GET['sort'] : 'newest';

// ----- Filter -----
$reviews = array_values(array_filter($all_reviews, function ($r) use ($item_filter) {
    return $item_filter === 0 || $r['item_id'] === $item_filter;
}));

// ----- Sort -----
usort($reviews, function ($a, $b) use ($sort) {
    if ($sort === 'highest') return $b['rating'] <=> $a['rating'] ?: strcmp($b['date'], $a['date']);
    if ($sort === 'lowest')  return $a['rating'] <=> $b['rating'] ?: strcmp($b['date'], $a['date']);
    return strcmp($b['date'], $a['date']);
});

// ----- Summary: average and how many reviews at each star level -----
$count = count($reviews);
$average = 0;
$distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $r) {
    $average += $r['rating'];
    $distribution[$r['rating']]++;
}
$average = $count > 0 ? $average / $count : 0;

$heading = $item_filter ? $dishes[$item_filter] : 'All dishes';

// Shows filled and empty stars for a rating from 1 to 5
function render_stars(int $rating): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="' . ($i <= $rating ? '' : 'off') . '">&#9733;</span>';
    }
    return $out;
}
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
  /* ---- Reviews page specific styles ---- */
  .reviews-page { max-width: 1000px; margin: 0 auto; padding: 48px 24px 80px; }

  .summary-panel {
    display: grid; grid-template-columns: 220px 1fr; gap: 32px; align-items: center;
    background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 26px; margin-top: 24px;
  }
  @media (max-width: 640px) { .summary-panel { grid-template-columns: 1fr; gap: 20px; } }
  .big-score { font-family: 'Fraunces', serif; font-size: 3.4rem; font-weight: 600; line-height: 1; }
  .big-score small { font-size: 1.1rem; opacity: 0.5; font-weight: 400; }
  .summary-panel .stars { font-size: 1.2rem; margin: 8px 0 4px; }
  .summary-count { font-size: 0.85rem; opacity: 0.65; }

  .bars { display: grid; gap: 8px; }
  .bar-row { display: grid; grid-template-columns: 38px 1fr 34px; gap: 10px; align-items: center; font-size: 0.84rem; }
  .bar-track { height: 9px; background: var(--paper-dim); border-radius: 5px; overflow: hidden; }
  .bar-fill { height: 100%; background: var(--gold); border-radius: 5px; }
  .bar-row span:last-child { text-align: right; opacity: 0.65; }

  .stars { color: var(--gold); letter-spacing: 2px; }
  .stars .off { opacity: 0.25; }

  .filters { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; margin: 26px 0 18px; }
  .filters .field { margin-bottom: 0; min-width: 190px; }
  .filters select {
    width: 100%; padding: 10px 12px; border-radius: 10px; border: 1.5px solid var(--line);
    background: var(--card); color: var(--ink); font-family: inherit; font-size: 0.92rem;
  }

  .review-list { display: grid; gap: 14px; }
  .review-card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 20px 22px; }
  .review-top { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: baseline; }
  .review-dish { font-weight: 600; text-decoration: none; }
  .review-dish:hover { color: var(--brick); }
  .review-date { font-size: 0.8rem; opacity: 0.55; }
  .review-card .stars { margin: 6px 0 8px; font-size: 1rem; }
  .review-text { margin: 0 0 12px; font-size: 0.93rem; line-height: 1.6; }
  .review-text.none { opacity: 0.5; font-style: italic; }
  .review-meta { font-size: 0.82rem; opacity: 0.7; display: flex; gap: 10px; align-items: center; }
  .verified { color: var(--jade); font-weight: 600; }

  .write-box { margin-top: 30px; padding: 20px 24px; background: var(--paper-dim); border-radius: 14px; font-size: 0.92rem; line-height: 1.6; }
  .write-box a { color: var(--brick); font-weight: 600; }

  .empty-state { text-align: center; padding: 48px 24px; opacity: 0.7; background: var(--card); border: 1px solid var(--line); border-radius: 14px; }
  #moreBtn { display: block; margin: 22px auto 0; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="reviews-page">
  <h1 class="section-title display">Customer reviews</h1>
  <p class="section-note">Every review comes from a customer who ordered the dish.</p>

  <!-- Summary for the dish (or all dishes) currently selected -->
  <section class="summary-panel" aria-label="Rating summary">
    <div>
      <div class="big-score"><?php echo $count > 0 ? number_format($average, 1) : '&ndash;'; ?><small> / 5</small></div>
      <div class="stars" aria-hidden="true"><?php echo render_stars((int) round($average)); ?></div>
      <div class="summary-count"><?php echo $count; ?> review<?php echo $count === 1 ? '' : 's'; ?> &middot; <?php echo htmlspecialchars($heading); ?></div>
    </div>

    <div class="bars">
      <?php foreach ($distribution as $stars => $n):
          $pct = $count > 0 ? round($n / $count * 100) : 0; ?>
        <div class="bar-row">
          <span><?php echo $stars; ?> &#9733;</span>
          <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
          <span><?php echo $n; ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Filter and sort: submits through the address bar, so reviews.php?item=2 links from the menu work too -->
  <form class="filters" method="get" action="reviews.php">
    <div class="field">
      <label for="item">Dish</label>
      <select id="item" name="item" onchange="this.form.submit()">
        <option value="0">All dishes</option>
        <?php foreach ($dishes as $id => $name): ?>
          <option value="<?php echo (int) $id; ?>" <?php echo $item_filter === $id ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sort">Sort by</label>
      <select id="sort" name="sort" onchange="this.form.submit()">
        <?php foreach ($sort_options as $key => $label): ?>
          <option value="<?php echo $key; ?>" <?php echo $sort === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <noscript><button type="submit" class="btn-ghost">Apply</button></noscript>
  </form>

  <!-- Review list -->
  <?php if ($count === 0): ?>
    <div class="empty-state">
      No reviews for this dish yet. Order it and be the first to rate it.
      <div style="margin-top:16px;"><a href="menu.php" class="btn-primary">Browse menu</a></div>
    </div>
  <?php else: ?>
    <div class="review-list" id="reviewList">
      <?php foreach ($reviews as $r): ?>
        <article class="review-card">
          <div class="review-top">
            <a class="review-dish" href="reviews.php?item=<?php echo (int) $r['item_id']; ?>"><?php echo htmlspecialchars($dishes[$r['item_id']]); ?></a>
            <span class="review-date"><?php echo htmlspecialchars(date('j M Y', strtotime($r['date']))); ?></span>
          </div>
          <div class="stars" aria-label="<?php echo (int) $r['rating']; ?> out of 5 stars"><?php echo render_stars((int) $r['rating']); ?></div>
          <?php if ($r['text'] !== ''): ?>
            <p class="review-text"><?php echo htmlspecialchars($r['text']); ?></p>
          <?php else: ?>
            <p class="review-text none">Rated without a comment.</p>
          <?php endif; ?>
          <div class="review-meta">
            <span><?php echo htmlspecialchars($r['customer']); ?></span>
            <span class="verified">&#10003; Verified order</span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn-ghost" id="moreBtn" hidden>Show more reviews</button>
  <?php endif; ?>

  <!-- How to write a review (there is no separate form on this page) -->
  <div class="write-box">
    <strong>Want to leave a review?</strong><br>
    Once your order is delivered, open <a href="order-history.php">My orders</a> and choose &ldquo;Rate this order&rdquo; to rate each dish.
  </div>
</main>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // Show 5 reviews at first, and reveal 5 more each time "Show more" is pressed
  const PAGE_SIZE = 5;
  const cards = Array.from(document.querySelectorAll('#reviewList .review-card'));
  const moreBtn = document.getElementById('moreBtn');
  let shown = PAGE_SIZE;

  function updateVisible() {
    cards.forEach((card, i) => { card.hidden = i >= shown; });
    if (moreBtn) moreBtn.hidden = shown >= cards.length;
  }

  if (cards.length > PAGE_SIZE) {
    moreBtn.addEventListener('click', () => { shown += PAGE_SIZE; updateVisible(); });
  }
  if (cards.length > 0) updateVisible();
</script>

</body>
</html>