<?php
// browse.php - public browse page (categories + sub-categories + real approved items)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

// ---- 1. What did the visitor ask for? ----
$slug = trim($_GET['category'] ?? '');   // e.g. browse.php?category=jwellery
$q    = trim($_GET['q'] ?? '');          // e.g. browse.php?q=table

// ---- 2. Load the visible category tree ----
$all = $pdo->query(
    'SELECT id, parent_id, name, slug, icon, description
       FROM categories
      WHERE is_visible = 1
      ORDER BY sort_order, name'
)->fetchAll();

$tops     = [];   // top-level categories, keyed by id
$children = [];   // $children[parentId] = list of that parent's sub-categories
$bySlug   = [];   // quick lookup: slug -> category row

foreach ($all as $row) {
    if ($row['parent_id'] === null) {
        $tops[$row['id']]     = $row;
        $bySlug[$row['slug']] = $row;
    }
}
foreach ($all as $row) {
    if ($row['parent_id'] !== null && isset($tops[$row['parent_id']])) {
        $children[$row['parent_id']][] = $row;
        $bySlug[$row['slug']]          = $row;
    }
}

// ---- 3. Which category was chosen? ----
$current     = null;
$parent      = null;
$categoryIds = [];
$notFound    = false;

if ($slug !== '') {
    $current = $bySlug[$slug] ?? null;
    if ($current === null) {
        $notFound = true;
        http_response_code(404);
    }
}
if ($current !== null) {
    $categoryIds[] = (int)$current['id'];
    if ($current['parent_id'] !== null) {
        $parent = $tops[$current['parent_id']];
    } else {
        foreach ($children[$current['id']] ?? [] as $child) {
            $categoryIds[] = (int)$child['id'];
        }
    }
}

// ---- 4. Load the approved items that match ----
$where  = ["i.status = 'approved'", 'c.is_visible = 1'];
$params = [];

if ($current !== null) {
    $marks   = implode(',', array_fill(0, count($categoryIds), '?'));
    $where[] = "i.category_id IN ($marks)";
    $params  = array_merge($params, $categoryIds);
}
if ($q !== '') {
    $where[] = '(i.title LIKE ? OR i.wants LIKE ? OR i.description LIKE ?)';
    $like    = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$items = [];
if (!$notFound) {
    $stmt = $pdo->prepare(
        'SELECT i.id, i.title, i.wants, i.photo, i.item_condition, u.city, c.name AS category_name
           FROM items i
           JOIN users u      ON u.id = i.user_id
           JOIN categories c ON c.id = i.category_id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY i.created_at DESC'
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll();
}

// remember what people search for (the admin sees it in Searches)
if ($q !== '' && !$notFound) {
    $pdo->prepare('INSERT INTO search_log (term, user_id, result_count) VALUES (?, ?, ?)')
        ->execute([mb_substr(mb_strtolower($q), 0, 100), $_SESSION['user_id'] ?? null, count($items)]);
}

$pageTitle = $current ? $current['name'] : 'Browse items';
$active    = 'browse';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-head"><div class="container"><h1>Browse items</h1></div></div>

<div class="container py-4">
  <div class="row g-4">

    <!-- left: search + category tree -->
    <div class="col-lg-3">
      <form class="card filter-card p-3 mb-3" method="get" action="<?= BASE_URL ?>/browse.php">
        <?php if ($slug !== ''): ?>
          <input type="hidden" name="category" value="<?= e($slug) ?>">
        <?php endif; ?>
        <label class="form-label small mb-1">Search</label>
        <div class="input-group">
          <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Item or what it wants">
          <button class="btn btn-primary"><i class="bi bi-search"></i></button>
        </div>
      </form>

      <div class="card">
        <div class="card-header bg-white fw-semibold">Categories</div>
        <div class="list-group list-group-flush">
          <a class="list-group-item list-group-item-action <?= $slug === '' ? 'active' : '' ?>" href="<?= BASE_URL ?>/browse.php">All categories</a>

          <?php foreach ($tops as $top): ?>
            <a class="list-group-item list-group-item-action <?= $slug === $top['slug'] ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/browse.php?category=<?= urlencode($top['slug']) ?>">
              <i class="bi bi-<?= e($top['icon']) ?>"></i> <?= e($top['name']) ?>
            </a>
            <?php foreach ($children[$top['id']] ?? [] as $child): ?>
              <a class="list-group-item list-group-item-action ps-5 small <?= $slug === $child['slug'] ? 'active' : '' ?>"
                 href="<?= BASE_URL ?>/browse.php?category=<?= urlencode($child['slug']) ?>">
                &#8627; <i class="bi bi-<?= e($child['icon']) ?>"></i> <?= e($child['name']) ?>
              </a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- right: what the visitor asked for -->
    <div class="col-lg-9">

      <?php if ($notFound): ?>
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i> That category does not exist (or is hidden).
          <a href="<?= BASE_URL ?>/browse.php">See all categories</a>
        </div>

      <?php else: ?>

        <nav class="small mb-2">
          <a href="<?= BASE_URL ?>/browse.php">Browse</a>
          <?php if ($parent): ?>
            / <a href="<?= BASE_URL ?>/browse.php?category=<?= urlencode($parent['slug']) ?>"><?= e($parent['name']) ?></a>
          <?php endif; ?>
          <?php if ($current): ?>
            / <span class="text-muted"><?= e($current['name']) ?></span>
          <?php endif; ?>
        </nav>

        <?php if ($current): ?>
          <h2 class="h4"><i class="bi bi-<?= e($current['icon']) ?> text-primary"></i> <?= e($current['name']) ?></h2>
          <?php if ($current['description']): ?>
            <p class="text-muted"><?= e($current['description']) ?></p>
          <?php endif; ?>

          <?php if (!empty($children[$current['id']])): ?>
            <div class="mb-3">
              <span class="small text-muted me-1">Sub-categories:</span>
              <?php foreach ($children[$current['id']] as $child): ?>
                <a class="btn btn-sm btn-outline-primary me-1 mb-1" href="<?= BASE_URL ?>/browse.php?category=<?= urlencode($child['slug']) ?>">
                  <i class="bi bi-<?= e($child['icon']) ?>"></i> <?= e($child['name']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <h2 class="h4">All items</h2>
        <?php endif; ?>

        <?php if ($q !== ''): ?>
          <p class="small">Searching for: <span class="badge text-bg-light border"><?= e($q) ?></span></p>
        <?php endif; ?>

        <p class="small text-muted"><?= count($items) ?> item(s) found</p>

        <?php if (count($items) === 0): ?>
          <div class="card"><div class="card-body text-center py-5">
            <i class="bi bi-box-seam fs-1 text-muted"></i>
            <h5 class="mt-2">No items found</h5>
            <p class="text-muted mb-0">Try another category or a different search word.</p>
          </div></div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($items as $item): ?>
              <div class="col-sm-6 col-xl-4">
                <?php require __DIR__ . '/includes/item_card.php'; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>