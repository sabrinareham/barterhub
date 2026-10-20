<?php
// index.php - public home page
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

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
    <div class="mt-4"><a class="btn btn-light me-2" href="auth/register.php">Start swapping</a><a class="btn btn-outline-light" href="<?= BASE_URL ?>/browse.php">Browse items</a></div>
  </div>
</section>

<section class="container py-5">
  <h2 class="h4 fw-bold mb-3">Browse by category</h2>
    <div class="row g-3">
      <!-- ......................... -->
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
  <!-- ............................... -->
</section>

<section class="container pb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 fw-bold mb-0">Latest items</h2><a href="<?= BASE_URL ?>/browse.php">See all <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="row g-4" data-new-items="public">
      <div class="col-sm-6 col-lg-4 item-col" data-title="Urdu &amp; English Novels (10 books)" data-cat="Books" data-cond="Good" data-city="Lahore" data-wants="A study table or a small bookshelf">
        <div class="card item-card h-100 shadow-sm">
          <div class="item-thumb cat-books"><i class="bi bi-book"></i></div>
          <div class="card-body">
            <span class="badge text-bg-light border">Books</span> <span class="badge text-bg-light border">Good</span>
            <h6 class="card-title mt-2 mb-1">Urdu &amp; English Novels (10 books)</h6>
            <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> Lahore &nbsp;<i class="bi bi-person"></i> Ali Raza</p>
            <div class="swap-box small"><i class="bi bi-arrow-left-right"></i> Wants: <strong>A study table or a small bookshelf</strong></div>
          </div>
          <div class="card-footer bg-white border-0"><a class="btn btn-outline-primary btn-sm w-100" href="item.html?id=1">View details</a></div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4 item-col" data-title="Wooden Study Table" data-cat="Furniture" data-cond="Good" data-city="Faisalabad" data-wants="Novels or school / college textbooks">
        <div class="card item-card h-100 shadow-sm">
          <div class="item-thumb cat-furniture"><i class="bi bi-lamp"></i></div>
          <div class="card-body">
            <span class="badge text-bg-light border">Furniture</span> <span class="badge text-bg-light border">Good</span>
            <h6 class="card-title mt-2 mb-1">Wooden Study Table</h6>
            <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> Faisalabad &nbsp;<i class="bi bi-person"></i> Sara Khan</p>
            <div class="swap-box small"><i class="bi bi-arrow-left-right"></i> Wants: <strong>Novels or school / college textbooks</strong></div>
          </div>
          <div class="card-footer bg-white border-0"><a class="btn btn-outline-primary btn-sm w-100" href="item.html?id=2">View details</a></div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4 item-col" data-title="Samsung Galaxy A12 (64 GB)" data-cat="Mobiles" data-cond="Fair" data-city="Multan" data-wants="A tablet or a smart watch">
        <div class="card item-card h-100 shadow-sm">
          <div class="item-thumb cat-mobiles"><i class="bi bi-phone"></i></div>
          <div class="card-body">
            <span class="badge text-bg-light border">Mobiles</span> <span class="badge text-bg-light border">Fair</span>
            <h6 class="card-title mt-2 mb-1">Samsung Galaxy A12 (64 GB)</h6>
            <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> Multan &nbsp;<i class="bi bi-person"></i> Hamza Ali</p>
            <div class="swap-box small"><i class="bi bi-arrow-left-right"></i> Wants: <strong>A tablet or a smart watch</strong></div>
          </div>
          <div class="card-footer bg-white border-0"><a class="btn btn-outline-primary btn-sm w-100" href="item.html?id=3">View details</a></div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4 item-col" data-title="Electric Kettle 1.7 L" data-cat="Kitchen" data-cond="New" data-city="Karachi" data-wants="A blender or a toaster">
        <div class="card item-card h-100 shadow-sm">
          <div class="item-thumb cat-kitchen"><i class="bi bi-cup-hot"></i></div>
          <div class="card-body">
            <span class="badge text-bg-light border">Kitchen</span> <span class="badge text-bg-light border">New</span>
            <h6 class="card-title mt-2 mb-1">Electric Kettle 1.7 L</h6>
            <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> Karachi &nbsp;<i class="bi bi-person"></i> Ayesha Noor</p>
            <div class="swap-box small"><i class="bi bi-arrow-left-right"></i> Wants: <strong>A blender or a toaster</strong></div>
          </div>
          <div class="card-footer bg-white border-0"><a class="btn btn-outline-primary btn-sm w-100" href="item.html?id=5">View details</a></div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4 item-col" data-title="Building Blocks Set (200 pcs)" data-cat="Toys" data-cond="Good" data-city="Islamabad" data-wants="Children story books">
        <div class="card item-card h-100 shadow-sm">
          <div class="item-thumb cat-toys"><i class="bi bi-controller"></i></div>
          <div class="card-body">
            <span class="badge text-bg-light border">Toys</span> <span class="badge text-bg-light border">Good</span>
            <h6 class="card-title mt-2 mb-1">Building Blocks Set (200 pcs)</h6>
            <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> Islamabad &nbsp;<i class="bi bi-person"></i> Zara Malik</p>
            <div class="swap-box small"><i class="bi bi-arrow-left-right"></i> Wants: <strong>Children story books</strong></div>
          </div>
          <div class="card-footer bg-white border-0"><a class="btn btn-outline-primary btn-sm w-100" href="item.html?id=6">View details</a></div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4 item-col" data-title="Winter Jacket (Size M)" data-cat="Clothing" data-cond="Like New" data-city="Rawalpindi" data-wants="Sports shoes, size 9">
        <div class="card item-card h-100 shadow-sm">
          <div class="item-thumb cat-clothing"><i class="bi bi-handbag"></i></div>
          <div class="card-body">
            <span class="badge text-bg-light border">Clothing</span> <span class="badge text-bg-light border">Like New</span>
            <h6 class="card-title mt-2 mb-1">Winter Jacket (Size M)</h6>
            <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> Rawalpindi &nbsp;<i class="bi bi-person"></i> Usman Tariq</p>
            <div class="swap-box small"><i class="bi bi-arrow-left-right"></i> Wants: <strong>Sports shoes, size 9</strong></div>
          </div>
          <div class="card-footer bg-white border-0"><a class="btn btn-outline-primary btn-sm w-100" href="item.html?id=7">View details</a></div>
        </div>
      </div>
  </div>
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
