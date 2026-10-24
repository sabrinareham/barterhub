<?php
// admin/chats.php - chat monitor (read only): the admin can read a chat but never write in it
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$requestId = (int)($_GET['request'] ?? 0);

// ---------- one chat ----------
if ($requestId > 0) {
    $stmt = $pdo->prepare(
        "SELECT r.id, r.status, ru.full_name AS requester_name, ou.full_name AS owner_name,
                r.requester_id, w.title AS wanted_title, o.title AS offered_title
           FROM exchange_requests r
           JOIN users ru ON ru.id = r.requester_id JOIN users ou ON ou.id = r.owner_id
           JOIN items w ON w.id = r.item_id JOIN items o ON o.id = r.offered_item_id
          WHERE r.id = ?"
    );
    $stmt->execute([$requestId]);
    $chat = $stmt->fetch();
    if (!$chat) { redirect(BASE_URL . '/admin/chats.php'); }

    $stmt = $pdo->prepare(
        'SELECT m.sender_id, m.body, m.created_at, u.full_name FROM messages m JOIN users u ON u.id = m.sender_id
          WHERE m.request_id = ? ORDER BY m.id ASC'
    );
    $stmt->execute([$requestId]);
    $messages = $stmt->fetchAll();

    $adminTitle  = 'Chat monitor';
    $adminActive = 'chats.php';
    require_once __DIR__ . '/../includes/admin_header.php';
    ?>
    <div class="p-3">
      <a class="btn btn-sm btn-outline-secondary mb-3" href="<?= BASE_URL ?>/admin/chats.php">&larr; All chats</a>
      <div class="card"><div class="card-header bg-white">
        <strong><?= e($chat['requester_name']) ?></strong> &harr; <strong><?= e($chat['owner_name']) ?></strong>
        <span class="text-muted">· <?= e($chat['offered_title']) ?> ⇄ <?= e($chat['wanted_title']) ?></span>
        <span class="badge text-bg-secondary"><?= e(ucfirst($chat['status'])) ?></span></div>
        <div class="card-body bg-light">
          <?php if (count($messages) === 0): ?><p class="text-muted mb-0">No messages in this chat.</p><?php endif; ?>
          <?php foreach ($messages as $m): ?>
            <div class="mb-2"><strong><?= e($m['full_name']) ?></strong>
              <span class="small text-muted"><?= e($m['created_at']) ?></span><br><?= nl2br(e($m['body'])) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/admin_footer.php';
    exit;
}

// ---------- list ----------
$chats = $pdo->query(
    "SELECT r.id, r.status, ru.full_name AS requester_name, ou.full_name AS owner_name,
            w.title AS wanted_title, o.title AS offered_title,
            (SELECT COUNT(*) FROM messages m WHERE m.request_id = r.id) AS msg_count,
            (SELECT MAX(m.created_at) FROM messages m WHERE m.request_id = r.id) AS last_at
       FROM exchange_requests r
       JOIN users ru ON ru.id = r.requester_id JOIN users ou ON ou.id = r.owner_id
       JOIN items w ON w.id = r.item_id JOIN items o ON o.id = r.offered_item_id
      WHERE r.status IN ('accepted','completed')
      ORDER BY last_at DESC, r.id DESC LIMIT 200"
)->fetchAll();

$adminTitle  = 'Chat monitor';
$adminActive = 'chats.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-3">
  <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>#</th><th>People</th><th>Items</th><th>Status</th><th>Messages</th><th>Last message</th><th></th></tr></thead>
    <tbody>
    <?php if (count($chats) === 0): ?><tr><td colspan="7" class="text-center text-muted py-4">No chats yet.</td></tr><?php endif; ?>
    <?php foreach ($chats as $c): ?>
      <tr>
        <td><?= (int)$c['id'] ?></td>
        <td><?= e($c['requester_name']) ?> &harr; <?= e($c['owner_name']) ?></td>
        <td class="small"><?= e($c['offered_title']) ?> ⇄ <?= e($c['wanted_title']) ?></td>
        <td><span class="badge text-bg-secondary"><?= e(ucfirst($c['status'])) ?></span></td>
        <td><?= (int)$c['msg_count'] ?></td>
        <td class="small"><?= e((string)$c['last_at']) ?></td>
        <td><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/admin/chats.php?request=<?= (int)$c['id'] ?>">Read</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div></div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>