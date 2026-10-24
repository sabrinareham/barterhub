<?php
// user/rate.php - rate the other person after a COMPLETED exchange (once only)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId    = (int)$_SESSION['user_id'];
$requestId = (int)($_GET['request'] ?? 0);

// only a participant of a completed exchange may rate
$stmt = $pdo->prepare(
    "SELECT r.*, ru.full_name AS requester_name, ou.full_name AS owner_name
       FROM exchange_requests r
       JOIN users ru ON ru.id = r.requester_id
       JOIN users ou ON ou.id = r.owner_id
      WHERE r.id = ? AND r.status = 'completed' AND (r.requester_id = ? OR r.owner_id = ?)"
);
$stmt->execute([$requestId, $userId, $userId]);
$r = $stmt->fetch();
if (!$r) {
    set_flash('danger', 'You can only rate after a completed exchange you took part in.');
    redirect(BASE_URL . '/user/requests.php?tab=completed');
}

$iAmOwner  = (int)$r['owner_id'] === $userId;
$ratedId   = $iAmOwner ? (int)$r['requester_id'] : (int)$r['owner_id'];     // the OTHER person
$otherName = $iAmOwner ? $r['requester_name'] : $r['owner_name'];

// did I already rate?
$stmt = $pdo->prepare('SELECT 1 FROM ratings WHERE request_id = ? AND rater_id = ?');
$stmt->execute([$requestId, $userId]);
if ($stmt->fetchColumn()) {
    set_flash('warning', 'You already rated this exchange.');
    redirect(BASE_URL . '/user/requests.php?tab=completed');
}

$errors  = [];
$stars   = 0;
$comment = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stars   = (int)($_POST['stars'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($stars < 1 || $stars > 5) { $errors[] = 'Please choose 1 to 5 stars.'; }
    if (mb_strlen($comment) > 255) { $errors[] = 'The comment is too long (255 characters at most).'; }

    if (count($errors) === 0) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ratings (request_id, rater_id, rated_id, stars, comment) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$requestId, $userId, $ratedId, $stars, $comment === '' ? null : $comment]);
            set_flash('success', 'Thank you! Your rating was saved.');
            redirect(BASE_URL . '/user/requests.php?tab=completed');
        } catch (PDOException $ex) {
            // the UNIQUE key (request_id, rater_id) also blocks a double submit
            $errors[] = 'Could not save your rating. Maybe you already rated.';
        }
    }
}

$pageTitle = 'Rate ' . $otherName;
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:520px">
  <h2 class="h4 mb-3">Rate <?= e($otherName) ?></h2>

  <?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="card p-4">
    <label class="form-label">How was the exchange?</label>
    <select class="form-select mb-3" name="stars" required>
      <option value="">Choose...</option>
      <?php foreach ([5 => 'Excellent', 4 => 'Good', 3 => 'Okay', 2 => 'Poor', 1 => 'Bad'] as $n => $label): ?>
        <option value="<?= $n ?>" <?= $stars === $n ? 'selected' : '' ?>><?= str_repeat('★', $n) ?> - <?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <label class="form-label">Comment (optional)</label>
    <input class="form-control mb-3" name="comment" maxlength="255" value="<?= e($comment) ?>" placeholder="e.g. Quick and honest">
    <div class="d-flex gap-2">
      <button class="btn btn-primary">Save rating</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/user/requests.php?tab=completed">Cancel</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>