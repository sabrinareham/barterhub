<?php
// item.php - one public item page (approved items only) + the "Request exchange" form
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exchange.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT i.*, u.full_name, u.city, c.name AS category_name, c.slug AS category_slug,
            (SELECT ROUND(AVG(stars), 1) FROM ratings WHERE rated_id = i.user_id) AS avg_rating,
            (SELECT COUNT(*)             FROM ratings WHERE rated_id = i.user_id) AS rating_count
       FROM items i
       JOIN users u      ON u.id = i.user_id
       JOIN categories c ON c.id = i.category_id
      WHERE i.id = ? AND i.status = 'approved' AND c.is_visible = 1"
);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
}

// ---- what can this visitor do with the item? ----
$isOwner     = $item && is_logged_in() && (int)$_SESSION['user_id'] === (int)$item['user_id'];
$myItems     = [];      // my approved items I could offer
$alreadySent = false;   // do I already have a pending request for this item?
$reserved    = false;   // is it inside an accepted exchange?

if ($item) {
    $reserved = item_reserved($pdo, (int)$item['id']);
}
if ($item && is_logged_in() && !$isOwner) {
    $stmt = $pdo->prepare("SELECT id, title FROM items WHERE user_id = ? AND status = 'approved' ORDER BY title");
    $stmt->execute([$_SESSION['user_id']]);
    $myItems = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT 1 FROM exchange_requests WHERE requester_id = ? AND item_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id'], $item['id']]);
    $alreadySent = (bool)$stmt->fetchColumn();
}


$pageTitle = $item ? $item['title'] : 'Item not found';
$active    = 'browse';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
<?php if (!$item): ?>
  <div class="alert alert-warning">This item does not exist or is not available. <a href="<?= BASE_URL ?>/browse.php">Back to browse</a></div>
<?php else: ?>
  <nav class="small mb-3">
    <a href="<?= BASE_URL ?>/browse.php">Browse</a> /
    <a href="<?= BASE_URL ?>/browse.php?category=<?= urlencode($item['category_slug']) ?>"><?= e($item['category_name']) ?></a> /
    <span class="text-muted"><?= e($item['title']) ?></span>
  </nav>
  <div class="row g-4">
    <div class="col-md-6">
      <?php if ($item['photo']): ?>
        <img src="<?= BASE_URL ?>/uploads/items/<?= e($item['photo']) ?>" class="img-fluid rounded" alt="">
      <?php endif; ?>
    </div>
    <div class="col-md-6">
      <h2 class="h3"><?= e($item['title']) ?></h2>
      <p class="text-muted"><?= e($item['category_name']) ?> · <?= e(str_replace('_', ' ', $item['item_condition'])) ?></p>
      <p><?= nl2br(e($item['description'])) ?></p>
      <p><strong>Wants in exchange:</strong> <?= e($item['wants']) ?></p>
      <p class="small text-muted"><i class="bi bi-person"></i> <?= e($item['full_name']) ?> · <i class="bi bi-geo-alt"></i> <?= e($item['city']) ?>
        <?php if ((int)$item['rating_count'] > 0): ?>
          · <i class="bi bi-star-fill text-warning"></i> <?= e($item['avg_rating']) ?> (<?= (int)$item['rating_count'] ?> <?= (int)$item['rating_count'] === 1 ? 'rating' : 'ratings' ?>)
        <?php else: ?>
          · <span>No ratings yet</span>
        <?php endif; ?>
      </p>

      <?php if (!is_logged_in()): ?>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/auth/login.php">Log in to request an exchange</a>

      <?php elseif ($isOwner): ?>
        <span class="badge text-bg-secondary">This is your item</span>

      <?php elseif ($reserved): ?>
        <div class="alert alert-info mb-0">This item is already promised in an accepted exchange.</div>

      <?php elseif ($alreadySent): ?>
        <div class="alert alert-success mb-0">
          You already sent a request for this item.
          <a href="<?= BASE_URL ?>/user/requests.php?tab=sent">See my requests</a>
        </div>

      <?php elseif (count($myItems) === 0): ?>
        <div class="alert alert-warning mb-0">
          To request this item you need at least one <strong>approved</strong> item to offer.
          <a href="<?= BASE_URL ?>/user/add-item.php">Add an item</a>
        </div>

      <?php else: ?>
        <form method="post" action="<?= BASE_URL ?>/user/request-send.php" class="card p-3">
          <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
          <label class="form-label">Which of your items do you offer?</label>
          <select class="form-select mb-3" name="offered_item_id" required>
            <option value="">Choose...</option>
            <?php foreach ($myItems as $mine): ?>
              <option value="<?= (int)$mine['id'] ?>"><?= e($mine['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <label class="form-label">Message (optional)</label>
          <input class="form-control mb-3" name="message" maxlength="255" placeholder="e.g. Can we meet in Johar Town?">
          <button class="btn btn-primary">Send request</button>
        </form>
      <?php endif; ?>

      <?php if (is_logged_in() && !$isOwner): ?>
        <p class="mt-3 mb-0"><a class="small text-danger" href="<?= BASE_URL ?>/user/report.php?item=<?= (int)$item['id'] ?>"><i class="bi bi-flag"></i> Report this item</a></p>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>