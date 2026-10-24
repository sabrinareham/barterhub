<?php
// user/report.php - report an item to the admin
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$itemId = (int)($_GET['item'] ?? 0);

// I can only report an approved item that is not mine
$stmt = $pdo->prepare("SELECT id, title, user_id FROM items WHERE id = ? AND status = 'approved'");
$stmt->execute([$itemId]);
$item = $stmt->fetch();
if (!$item || (int)$item['user_id'] === $userId) {
    set_flash('danger', 'You cannot report that item.');
    redirect(BASE_URL . '/browse.php');
}

$back = BASE_URL . '/item.php?id=' . $itemId;

// one report per person per item
$stmt = $pdo->prepare('SELECT 1 FROM reports WHERE reporter_id = ? AND item_id = ?');
$stmt->execute([$userId, $itemId]);
if ($stmt->fetchColumn()) {
    set_flash('warning', 'You already reported this item. The admin will look at it.');
    redirect($back);
}

$reasons = [
    'fake'           => 'The item is fake or not as described',
    'prohibited'     => 'Not allowed (illegal or dangerous)',
    'scam'           => 'The person looks like a scammer',
    'wrong_category' => 'Wrong category or duplicate',
    'other'          => 'Something else',
];
$errors  = [];
$reason  = '';
$details = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason  = $_POST['reason'] ?? '';
    $details = trim($_POST['details'] ?? '');

    if (!isset($reasons[$reason])) { $errors[] = 'Please choose a reason.'; }
    if (mb_strlen($details) > 500) { $errors[] = 'The details are too long (500 characters at most).'; }

    if (count($errors) === 0) {
        $stmt = $pdo->prepare('INSERT INTO reports (reporter_id, item_id, reason, details) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $itemId, $reason, $details === '' ? null : $details]);
        set_flash('success', 'Thank you. The admin will review your report.');
        redirect($back);
    }
}

$pageTitle = 'Report an item';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:560px">
  <h2 class="h4 mb-1">Report an item</h2>
  <p class="text-muted">You are reporting <strong><?= e($item['title']) ?></strong>.</p>

  <?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="card p-4">
    <label class="form-label">What is wrong?</label>
    <select class="form-select mb-3" name="reason" required>
      <option value="">Choose...</option>
      <?php foreach ($reasons as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $reason === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="form-label">Details (optional)</label>
    <textarea class="form-control mb-3" name="details" rows="3" maxlength="500"><?= e($details) ?></textarea>
    <div class="d-flex gap-2">
      <button class="btn btn-danger">Send report</button>
      <a class="btn btn-outline-secondary" href="<?= $back ?>">Cancel</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>