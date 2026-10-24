 <?php
require_once '../includes/init.php';
require_once '../config/db.php';

$active_page = 'menu';
$page_title  = 'Menu | Pawan Punjabi Chinese Restaurant';

// Set to a room code (e.g. 'PAW-4821') when the customer is inside a group order.
$group_room_code = null;

// Every dish, with its average rating from visible (not hidden) reviews.
// A dish counts as available only if it's switched on AND still has stock.
$menu_items = $pdo->query(
    "SELECT m.item_id AS id,
            m.name,
            m.category,
            m.description AS `desc`,
            m.price,
            m.is_veg AS veg,
            (m.is_available = 1 AND m.stock_quantity > 0) AS available,
            COALESCE(AVG(r.rating), 0) AS rating,
            COUNT(r.review_id) AS reviews
     FROM menu_item m
     LEFT JOIN review r ON r.item_id = m.item_id AND r.is_hidden = 0
     GROUP BY m.item_id
     ORDER BY m.category, m.name"
)->fetchAll();

$categories = array_values(array_unique(array_column($menu_items, 'category')));
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
  /* ---- Menu page specific styles ---- */
  .menu-header { padding: 48px 24px 8px; max-width: 1120px; margin: 0 auto; }

  .group-banner {
    max-width: 1120px;
    margin: 16px auto 0;
    padding: 12px 18px;
    background: var(--gold-soft);
    border-radius: 12px;
    font-size: 0.9rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .group-banner-wrap { padding: 0 24px; }

  .controls { display: flex; gap: 14px; flex-wrap: wrap; align-items: center; margin-top: 20px; }
  .search-box {
    flex: 1 1 240px;
    max-width: 360px;
    padding: 11px 14px;
    border-radius: 10px;
    border: 1.5px solid var(--line);
    background: var(--card);
    color: var(--ink);
    font-family: inherit;
    font-size: 0.93rem;
  }
  .search-box:focus { outline: 2px solid var(--gold); outline-offset: 1px; border-color: transparent; }

  .tabs { display: inline-flex; background: var(--paper-dim); border-radius: 10px; padding: 4px; gap: 4px; }
  .tab {
    padding: 8px 16px;
    border-radius: 7px;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: transparent;
    color: var(--ink);
    opacity: 0.6;
  }
  .tab.active { background: var(--card); opacity: 1; box-shadow: 0 2px 6px var(--shadow); }

  .cat-select {
    padding: 10px 12px;
    border-radius: 10px;
    border: 1.5px solid var(--line);
    background: var(--card);
    color: var(--ink);
    font-family: inherit;
    font-size: 0.9rem;
  }

  .menu-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    padding: 28px 24px 24px;
    max-width: 1120px;
    margin: 0 auto;
  }
  @media (max-width: 860px) { .menu-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 560px) { .menu-grid { grid-template-columns: 1fr; } }

  .dish { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 18px; display: flex; flex-direction: column; gap: 8px; }
  .dish[hidden] { display: none; }
  .dish-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
  .dish-name { font-weight: 600; font-size: 1rem; line-height: 1.25; }
  .dish-tag { width: 15px; height: 15px; border: 1.5px solid var(--jade); border-radius: 3px; flex-shrink: 0; margin-top: 3px; display: flex; align-items: center; justify-content: center; }
  .dish-tag.nonveg { border-color: var(--brick); }
  .dish-tag i { width: 7px; height: 7px; border-radius: 50%; background: var(--jade); }
  .dish-tag.nonveg i { background: var(--brick); }
  .dish-cat { font-size: 0.75rem; opacity: 0.55; font-weight: 600; }
  .dish-desc { font-size: 0.85rem; opacity: 0.6; line-height: 1.4; }
  .rating { font-size: 0.82rem; text-decoration: none; display: inline-flex; gap: 5px; align-items: center; }
  .rating .star { color: var(--gold); }
  .rating small { opacity: 0.6; }
  .dish-foot { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; }
  .dish-price { font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.05rem; }
  .add-btn { width: 34px; height: 34px; border-radius: 9px; border: none; background: var(--brick); color: #fff; font-size: 1.2rem; cursor: pointer; line-height: 1; }
  .dish.out-of-stock { opacity: 0.55; }
  .dish.out-of-stock .add-btn { background: var(--line); color: var(--ink); cursor: not-allowed; }
  .stock-note { font-size: 0.78rem; font-weight: 600; opacity: 0.75; }

  .empty-state { display: none; text-align: center; padding: 40px 24px 72px; opacity: 0.65; }
  .empty-state.show { display: block; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="menu-header">
  <h1 class="section-title display">Full Menu</h1>
  <p class="section-note">Prices and availability update live from the kitchen.</p>

  <div class="controls">
    <input type="search" class="search-box" id="searchBox" placeholder="Search dishes" aria-label="Search dishes">

    <div class="tabs" role="group" aria-label="Filter by type">
      <button class="tab active" data-filter="all">All</button>
      <button class="tab" data-filter="veg">Veg</button>
      <button class="tab" data-filter="nonveg">Non-veg</button>
    </div>

    <select class="cat-select" id="catSelect" aria-label="Filter by category">
      <option value="all">All categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?php if ($group_room_code): ?>
  <div class="group-banner-wrap">
    <div class="group-banner">
      <span>You're ordering in group room <strong><?php echo htmlspecialchars($group_room_code); ?></strong>. Items you add go to the shared group order.</span>
      <a href="group-order.php" class="link-more" style="font-weight:600;color:var(--brick);text-decoration:none;">View group order</a>
    </div>
  </div>
<?php endif; ?>

<div class="menu-grid" id="menuGrid">
  <?php foreach ($menu_items as $item): ?>
    <div class="dish <?php echo $item['available'] ? '' : 'out-of-stock'; ?>"
         data-type="<?php echo $item['veg'] ? 'veg' : 'nonveg'; ?>"
         data-category="<?php echo htmlspecialchars($item['category']); ?>"
         data-name="<?php echo htmlspecialchars(strtolower($item['name'])); ?>">
      <div class="dish-top">
        <div class="dish-name"><?php echo htmlspecialchars($item['name']); ?></div>
        <div class="dish-tag <?php echo $item['veg'] ? '' : 'nonveg'; ?>" title="<?php echo $item['veg'] ? 'Vegetarian' : 'Non-vegetarian'; ?>"><i></i></div>
      </div>
      <div class="dish-cat"><?php echo htmlspecialchars($item['category']); ?></div>
      <div class="dish-desc"><?php echo htmlspecialchars($item['desc']); ?></div>

      <?php if ($item['reviews'] > 0): ?>
        <a class="rating" href="reviews.php?item=<?php echo (int) $item['id']; ?>">
          <span class="star">&#9733;</span><?php echo number_format($item['rating'], 1); ?>
          <small>(<?php echo (int) $item['reviews']; ?> reviews)</small>
        </a>
      <?php else: ?>
        <span class="rating"><small>No reviews yet</small></span>
      <?php endif; ?>

      <div class="dish-foot">
        <div class="dish-price">&#8377;<?php echo (int) $item['price']; ?></div>
        <?php if ($item['available']): ?>
          <button class="add-btn"
                  aria-label="Add <?php echo htmlspecialchars($item['name']); ?> to cart"
                  data-id="<?php echo (int) $item['id']; ?>"
                  data-name="<?php echo htmlspecialchars($item['name']); ?>"
                  data-price="<?php echo (int) $item['price']; ?>">+</button>
        <?php else: ?>
          <button class="add-btn" disabled aria-label="<?php echo htmlspecialchars($item['name']); ?> is sold out">+</button>
        <?php endif; ?>
      </div>
      <?php if (!$item['available']): ?>
        <div class="stock-note">Sold out for today</div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="empty-state" id="emptyState">No dishes match your search. Try a different filter.</div>

<?php include '../includes/footer.php'; ?>

<script src="js/main.js"></script>
<script>
  // ----- Filtering: search + veg/non-veg + category work together -----
  const dishes    = document.querySelectorAll('#menuGrid .dish');
  const tabs      = document.querySelectorAll('.tab');
  const searchBox = document.getElementById('searchBox');
  const catSelect = document.getElementById('catSelect');
  const emptyState = document.getElementById('emptyState');

  let typeFilter = 'all';

  function applyFilters() {
    const query = searchBox.value.trim().toLowerCase();
    const cat = catSelect.value;
    let visible = 0;

    dishes.forEach(dish => {
      const matchType   = typeFilter === 'all' || dish.dataset.type === typeFilter;
      const matchCat    = cat === 'all' || dish.dataset.category === cat;
      const matchSearch = query === '' || dish.dataset.name.includes(query);
      const show = matchType && matchCat && matchSearch;
      dish.hidden = !show;
      if (show) visible++;
    });

    emptyState.classList.toggle('show', visible === 0);
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      typeFilter = tab.dataset.filter;
      applyFilters();
    });
  });
  searchBox.addEventListener('input', applyFilters);
  catSelect.addEventListener('change', applyFilters);

  // ----- Add to cart (uses the shared Cart helper from main.js) -----
  document.querySelectorAll('.add-btn:not([disabled])').forEach(btn => {
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