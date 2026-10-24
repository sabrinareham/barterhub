<?php
// index.php - public home page
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

// latest approved items for the home page
$latest = $pdo->query(
    "SELECT i.id, i.title, i.wants, i.photo, i.item_condition, u.city, c.name AS category_name
       FROM items i
       JOIN users u      ON u.id = i.user_id
       JOIN categories c ON c.id = i.category_id
      WHERE i.status = 'approved' AND c.is_visible = 1
      ORDER BY i.created_at DESC
      LIMIT 8"
)->fetchAll();

// READ: only the categories the admin left visible, A to Z
$categories = $pdo->query('SELECT id, name, slug, icon, description FROM categories WHERE is_visible = 1 AND parent_id IS NULL ORDER BY sort_order, name')->fetchAll();

$pageTitle = 'Home';
$active    = 'home';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container text-center">
    <h1 class="display-5">Swap what you do not need.<br>Get what you do.</h1>
    <p class="lead mb-4">A free place to exchange unused items with other people. No buying. No selling.</p>
    <form class="search-box d-flex mx-auto" action="<?= BASE_URL ?>/browse.php" method="get">
      <input class="form-control form-control-lg" name="q" placeholder="Search items, e.g. books, table, speaker">
      <button class="btn btn-warning btn-lg px-4" type="submit"><i class="bi bi-search"></i></button>
    </form>
    <div class="mt-4"><a class="btn btn-light me-2" href="<?= BASE_URL ?>/auth/register.php">Start swapping</a><a class="btn btn-outline-light" href="<?= BASE_URL ?>/browse.php">Browse items</a></div>
  </div>
</section>

<section class="container py-5">
  <h2 class="h4 fw-bold mb-3">Browse by category</h2>
  <div class="d-flex flex-nowrap overflow-auto gap-3 pb-3" style="scrollbar-width: thin;">
    <?php if (count($categories) === 0): ?>
      <p class="text-muted">No categories yet.</p>
    <?php endif; ?>
    <?php foreach ($categories as $cat): ?>
      <div style="min-width: 160px; flex: 0 0 auto;">
        <a class="cat-tile h-100 d-flex flex-column align-items-center justify-content-center text-center p-3 border rounded text-decoration-none" title="<?= e($cat['description']) ?>" href="<?= BASE_URL ?>/browse.php?category=<?= urlencode($cat['slug']) ?>">
          <i class="bi bi-<?= e($cat['icon']) ?> fs-3"></i>
          <div class="mt-2 fw-semibold text-dark"><?= e($cat['name']) ?></div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="container pb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 fw-bold mb-0">Latest items</h2>
    <a href="<?= BASE_URL ?>/browse.php">See all <i class="bi bi-arrow-right"></i></a>
  </div>

  <?php if (count($latest) === 0): ?>
    <p class="text-muted">No items have been approved yet.</p>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($latest as $item): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <?php require __DIR__ . '/includes/item_card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="container py-5">
  <h2 class="h4 fw-bold text-center mb-4">How Barter Hub works</h2>
  <div class="row g-4 text-center">
    <div class="col-md-3"><div class="step-circle mb-2"><i class="bi bi-person-plus"></i></div><h6>1. Register and log in</h6><p class="small text-muted">Anyone can look around. Log in to post and swap.</p></div>
    <div class="col-md-3"><div class="step-circle mb-2"><i class="bi bi-plus-square"></i></div><h6>2. Add your item</h6><p class="small text-muted">Pick a category, add photos, and say what you want in return.</p></div>
    <div class="col-md-3"><div class="step-circle mb-2"><i class="bi bi-stars"></i></div><h6>3. Get matches</h6><p class="small text-muted">We suggest people who have what you want and want what you have.</p></div>
    <div class="col-md-3"><div class="step-circle mb-2"><i class="bi bi-chat-dots"></i></div><h6>4. Chat and swap</h6><p class="small text-muted">Send a request, chat, complete the swap and rate each other.</p></div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>