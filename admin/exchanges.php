<?php
// admin/exchanges.php - every exchange request (read only: the admin never trades or chats)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$statuses = ['all' => 'All', 'pending' => 'Pending', 'countered' => 'Countered', 'accepted' => 'Accepted',
             'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
$status = $_GET['status'] ?? 'all';
if (!isset($statuses[$status])) { $status = 'all'; }

// counts for the tabs
$counts = $pdo->query('SELECT status, COUNT(*) FROM exchange_requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$counts['all'] = array_sum($counts);

$where  = $status === 'all' ? '1 = 1' : 'r.status = ?';
$params = $status === 'all' ? [] : [$status];
$stmt = $pdo->prepare(
    "SELECT r.id, r.status, r.created_at, r.completed_at, r.requester_done, r.owner_done,
            w.title AS wanted_title, o.title AS offered_title,
            ru.full_name AS requester_name, ou.full_name AS owner_name,
            (SELECT COUNT(*) FROM messages m WHERE m.request_id = r.id) AS msg_count
       FROM exchange_requests r
       JOIN items w  ON w.id  = r.item_id
       JOIN items o  ON o.id  = r.offered_item_id
       JOIN users ru ON ru.id = r.requester_id
       JOIN users ou ON ou.id = r.owner_id
      WHERE $where
      ORDER BY r.created_at DESC LIMIT 200"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$badges = ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger',
           'cancelled' => 'secondary', 'countered' => 'info', 'completed' => 'primary'];

$adminTitle  = 'Exchanges';
$adminActive = 'exchanges.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-3">
<ul class="nav nav-pills mb-3">
  <?php foreach ($statuses as $key => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $status === $key ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/exchanges.php?status=<?= e($key) ?>">
      <?= e($label) ?> <span class="badge text-bg-light"><?= (int)($counts[$key] ?? 0) ?></span></a></li>
  <?php endforeach; ?>
</ul>

<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>#</th><th>Requester</th><th>Offers</th><th></th><th>Owner</th><th>Wants</th><th>Status</th><th>Confirmed</th><th>Chat</th><th>Date</th></tr></thead>
  <tbody>
  <?php if (count($rows) === 0): ?><tr><td colspan="10" class="text-center text-muted py-4">No exchanges here.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= e($r['requester_name']) ?></td><td><?= e($r['offered_title']) ?></td>
      <td><i class="bi bi-arrow-left-right text-primary"></i></td>
      <td><?= e($r['owner_name']) ?></td><td><?= e($r['wanted_title']) ?></td>
      <td><span class="badge text-bg-<?= $badges[$r['status']] ?? 'secondary' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
      <td class="small"><?= in_array($r['status'], ['accepted', 'completed'], true)
            ? ($r['requester_done'] ? '✔' : '…') . ' / ' . ($r['owner_done'] ? '✔' : '…') : '-' ?></td>
      <td><?= (int)$r['msg_count'] ?></td>
      <td class="small"><?= e(substr($r['created_at'], 0, 10)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>