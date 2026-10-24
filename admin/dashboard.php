<?php
// user/dashboard.php - my summary (real numbers from the database)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId = (int)$_SESSION['user_id'];

// my items grouped by status: ['approved' => 3, 'pending' => 1]
$stmt = $pdo->prepare('SELECT status, COUNT(*) FROM items WHERE user_id = ? GROUP BY status');
$stmt->execute([$userId]);
$itemCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// a helper that runs "SELECT COUNT(*) ... WHERE ... = my id" and returns the number
function count_for($pdo, $sql, $userId) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userId]);
    return (int)$stmt->fetchColumn();
}
$waitingForMe = count_for($pdo,
    "SELECT COUNT(*) FROM exchange_requests
      WHERE (owner_id = ? AND status = 'pending') OR (requester_id = ? AND status = 'countered')", $userId);
$activeSwaps  = count_for($pdo,
    "SELECT COUNT(*) FROM exchange_requests WHERE status = 'accepted' AND (requester_id = ? OR owner_id = ?)", $userId);
$doneSwaps    = count_for($pdo,
    "SELECT COUNT(*) FROM exchange_requests WHERE status = 'completed' AND (requester_id = ? OR owner_id = ?)", $userId);

$stmt = $pdo->prepare('SELECT ROUND(AVG(stars), 1) AS avg_rating, COUNT(*) AS n FROM ratings WHERE rated_id = ?');
$stmt->execute([$userId]);
$rating = $stmt->fetch();

$pageTitle = 'My dashboard';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
  <h2 class="h4 mb-1">Hello, <?= e($_SESSION['user_name']) ?></h2>
  <p class="text-muted">Here is what is happening with your items and exchanges.</p>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><a class="card text-decoration-none h-100" href="<?= BASE_URL ?>/user/requests.php">
      <div class="card-body"><div class="display-6 fw-bold <?= $waitingForMe > 0 ? 'text-danger' : '' ?>"><?= $waitingForMe ?></div>
      <div class="small text-muted">Requests waiting for your answer</div></div></a></div>
    <div class="col-6 col-lg-3"><a class="card text-decoration-none h-100" href="<?= BASE_URL ?>/user/chat.php">
      <div class="card-body"><div class="display-6 fw-bold"><?= $activeSwaps ?></div>
      <div class="small text-muted">Active exchanges (chat open)</div></div></a></div>
    <div class="col-6 col-lg-3"><a class="card text-decoration-none h-100" href="<?= BASE_URL ?>/user/requests.php?tab=completed">
      <div class="card-body"><div class="display-6 fw-bold"><?= $doneSwaps ?></div>
      <div class="small text-muted">Completed swaps</div></div></a></div>
    <div class="col-6 col-lg-3"><a class="card text-decoration-none h-100" href="<?= BASE_URL ?>/user/profile.php">
      <div class="card-body"><div class="display-6 fw-bold">
        <?php if ((int)$rating['n'] > 0): ?><i class="bi bi-star-fill text-warning fs-4"></i> <?= e($rating['avg_rating']) ?><?php else: ?>-<?php endif; ?></div>
      <div class="small text-muted"><?= (int)$rating['n'] ?> rating<?= (int)$rating['n'] === 1 ? '' : 's' ?></div></div></a></div>
  </div>

  <div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between">
    My items <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/user/add-item.php">Add item</a></div>
    <div class="card-body">
      <?php foreach (['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'exchanged' => 'secondary'] as $st => $color): ?>
        <a class="btn btn-outline-<?= $color ?> me-2 mb-1" href="<?= BASE_URL ?>/user/my-items.php">
          <?= e(ucfirst($st)) ?> <span class="badge text-bg-<?= $color ?>"><?= (int)($itemCounts[$st] ?? 0) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>