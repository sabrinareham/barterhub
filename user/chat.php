<?php
// user/chat.php - list of my chats, and one chat thread (?request=ID)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId    = (int)$_SESSION['user_id'];
$requestId = (int)($_GET['request'] ?? 0);

// ================= ONE CHAT =================
if ($requestId > 0) {
    // only the two people of an accepted (or completed) exchange may enter
    $stmt = $pdo->prepare(
        "SELECT r.*, w.title AS wanted_title, o.title AS offered_title,
                ru.full_name AS requester_name, ou.full_name AS owner_name
           FROM exchange_requests r
           JOIN items w  ON w.id  = r.item_id
           JOIN items o  ON o.id  = r.offered_item_id
           JOIN users ru ON ru.id = r.requester_id
           JOIN users ou ON ou.id = r.owner_id
          WHERE r.id = ? AND r.status IN ('accepted','completed')
            AND (r.requester_id = ? OR r.owner_id = ?)"
    );
    $stmt->execute([$requestId, $userId, $userId]);
    $chat = $stmt->fetch();

    if (!$chat) {
        set_flash('danger', 'That chat does not exist or you are not part of it.');
        redirect(BASE_URL . '/user/chat.php');
    }

    $otherName = ((int)$chat['owner_id'] === $userId) ? $chat['requester_name'] : $chat['owner_name'];

    // ---- send a message (only while the exchange is still open) ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = trim($_POST['body'] ?? '');
        if ($chat['status'] !== 'accepted') {
            set_flash('danger', 'This exchange is completed. The chat is read-only.');
        } elseif ($body === '' || mb_strlen($body) > 500) {
            set_flash('danger', 'Write a message of 1 to 500 characters.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO messages (request_id, sender_id, body) VALUES (?, ?, ?)');
            $stmt->execute([$requestId, $userId, $body]);
        }
        redirect(BASE_URL . '/user/chat.php?request=' . $requestId);
    }

    $stmt = $pdo->prepare('SELECT sender_id, body, created_at FROM messages WHERE request_id = ? ORDER BY id ASC');
    $stmt->execute([$requestId]);
    $messages = $stmt->fetchAll();

    $pageTitle = 'Chat with ' . $otherName;
    $active    = '';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="container py-4" style="max-width:760px">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="h5 mb-0"><?= e($otherName) ?></h2>
          <div class="small text-muted"><?= e($chat['offered_title']) ?> ⇄ <?= e($chat['wanted_title']) ?>
            <?php if ($chat['status'] === 'completed'): ?><span class="badge text-bg-primary">Completed</span><?php endif; ?></div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/user/requests.php?tab=<?= $chat['status'] === 'completed' ? 'completed' : ((int)$chat['owner_id'] === $userId ? 'received' : 'sent') ?>"><?= $chat['status'] === 'completed' ? 'Back to requests' : 'Go to request to complete' ?></a>
      </div>

      <div class="card"><div class="card-body bg-light" style="min-height:260px;max-height:480px;overflow:auto">
        <?php if (count($messages) === 0): ?>
          <p class="text-muted text-center my-5">No messages yet. Say hello and agree on a place to meet.</p>
        <?php endif; ?>
        <?php foreach ($messages as $m): ?>
          <?php $mine = (int)$m['sender_id'] === $userId; ?>
          <div class="d-flex <?= $mine ? 'justify-content-end' : 'justify-content-start' ?> mb-2">
            <div class="rounded px-3 py-2 <?= $mine ? 'bg-primary text-white' : 'bg-white border' ?>" style="max-width:75%">
              <div><?= nl2br(e($m['body'])) ?></div>
              <div class="small <?= $mine ? 'text-white-50' : 'text-muted' ?>"><?= $mine ? 'You' : e($otherName) ?> · <?= e($m['created_at']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div></div>

      <?php if ($chat['status'] === 'accepted'): ?>
        <form method="post" class="d-flex gap-2 mt-3">
          <input class="form-control" name="body" maxlength="500" placeholder="Type a message" autocomplete="off" required>
          <button class="btn btn-primary"><i class="bi bi-send"></i></button>
        </form>
        <div class="small text-muted mt-2">New messages appear when you reload the page.</div>
      <?php else: ?>
        <div class="alert alert-secondary mt-3 mb-0">This exchange is completed, so the chat is read-only.</div>
      <?php endif; ?>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ================= LIST OF MY CHATS =================
$stmt = $pdo->prepare(
    "SELECT r.id, r.status, w.title AS wanted_title, o.title AS offered_title,
            ru.full_name AS requester_name, ou.full_name AS owner_name, r.owner_id,
            (SELECT COUNT(*) FROM messages m WHERE m.request_id = r.id) AS msg_count
       FROM exchange_requests r
       JOIN items w  ON w.id  = r.item_id
       JOIN items o  ON o.id  = r.offered_item_id
       JOIN users ru ON ru.id = r.requester_id
       JOIN users ou ON ou.id = r.owner_id
      WHERE r.status IN ('accepted','completed') AND (r.requester_id = ? OR r.owner_id = ?)
      ORDER BY r.responded_at DESC"
);
$stmt->execute([$userId, $userId]);
$chats = $stmt->fetchAll();

$pageTitle = 'Chat';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:760px">
  <h2 class="h4 mb-3">My chats</h2>
  <?php if (count($chats) === 0): ?>
    <div class="card"><div class="card-body text-center text-muted py-5">
      A chat opens when an exchange request is accepted.
    </div></div>
  <?php endif; ?>
  <div class="list-group">
    <?php foreach ($chats as $c): ?>
      <a class="list-group-item list-group-item-action" href="<?= BASE_URL ?>/user/chat.php?request=<?= (int)$c['id'] ?>">
        <div class="d-flex justify-content-between">
          <strong><?= e((int)$c['owner_id'] === $userId ? $c['requester_name'] : $c['owner_name']) ?></strong>
          <span>
            <?php if ($c['status'] === 'completed'): ?><span class="badge text-bg-primary">Completed</span><?php endif; ?>
            <span class="badge text-bg-light border"><?= (int)$c['msg_count'] ?> msg</span>
          </span>
        </div>
        <div class="small text-muted"><?= e($c['offered_title']) ?> ⇄ <?= e($c['wanted_title']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>