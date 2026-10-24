<?php
// admin/items.php - every item on the site: search, take down, delete
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exchange.php';
require_admin();

$statuses = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'exchanged' => 'Exchanged'];
$status = $_GET['status'] ?? 'all';
if (!isset($statuses[$status])) { $status = 'all'; }
$q = trim($_GET['q'] ?? '');

// ---- actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $status = isset($statuses[$_POST['status'] ?? '']) ? $_POST['status'] : 'all';
    $q      = trim($_POST['q'] ?? '');

    if ($action === 'takedown') {
        $note = trim($_POST['note'] ?? '');
        if (mb_strlen($note) < 3 || mb_strlen($note) > 255) {
            set_flash('danger', 'Please write a reason (3 to 255 characters).');
        } elseif (item_reserved($pdo, $id)) {
            set_flash('danger', 'This item is inside an accepted exchange, so it cannot be taken down now.');
        } else {
            $stmt = $pdo->prepare("UPDATE items SET status = 'rejected', admin_note = ? WHERE id = ? AND status IN ('pending','approved')");
            $stmt->execute([$note, $id]);
            set_flash($stmt->rowCount() ? 'success' : 'danger', $stmt->rowCount() ? 'Item taken down. The owner can see your reason.' : 'Nothing was changed.');
        }

    } elseif ($action === 'delete') {
        // an item that was ever part of a request is part of somebody's history, so it is never deleted
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM exchange_requests WHERE item_id = ? OR offered_item_id = ? OR counter_item_id = ?');
        $stmt->execute([$id, $id, $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            set_flash('danger', 'This item is part of an exchange history, so it cannot be deleted. Use "Take down" instead.');
        } else {
            $stmt = $pdo->prepare('SELECT photo FROM items WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
                if ($row['photo']) { @unlink(__DIR__ . '/../uploads/items/' . $row['photo']); }
                set_flash('success', 'Item deleted for good.');
            }
        }
    }
    redirect(BASE_URL . '/admin/items.php?status=' . $status . ($q !== '' ? '&q=' . urlencode($q) : ''));
}

// ---- list ----
$where  = ['(? = \'\' OR i.title LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)'];
$like   = '%' . $q . '%';
$params = [$q, $like, $like, $like];
if ($status !== 'all') { $where[] = 'i.status = ?'; $params[] = $status; }

$stmt = $pdo->prepare(
    'SELECT i.id, i.title, i.photo, i.status, i.created_at, i.admin_note,
            u.full_name, u.email, c.name AS category_name,
            (SELECT COUNT(*) FROM exchange_requests r WHERE r.item_id = i.id OR r.offered_item_id = i.id) AS request_count
       FROM items i
       JOIN users u      ON u.id = i.user_id
       JOIN categories c ON c.id = i.category_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY i.created_at DESC LIMIT 200'
);
$stmt->execute($params);
$items = $stmt->fetchAll();

$badges = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'exchanged' => 'secondary'];
$flash = get_flash();

$adminTitle  = 'Items';
$adminActive = 'items.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-3">
<?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<ul class="nav nav-pills mb-3">
  <?php foreach ($statuses as $key => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $status === $key ? 'active' : '' ?>"
      href="<?= BASE_URL ?>/admin/items.php?status=<?= e($key) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"><?= e($label) ?></a></li>
  <?php endforeach; ?>
</ul>
<form class="row g-2 mb-3" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="col-md-6"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search item title, owner name or email"></div>
  <div class="col-auto"><button class="btn btn-primary">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/items.php?status=<?= e($status) ?>">Clear</a><?php endif; ?></div>
</form>

<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>#</th><th>Item</th><th>Owner</th><th>Category</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php if (count($items) === 0): ?><tr><td colspan="6" class="text-center text-muted py-4">No items found.</td></tr><?php endif; ?>
  <?php foreach ($items as $it): ?>
    <tr>
      <td><?= (int)$it['id'] ?></td>
      <td><div class="d-flex align-items-center gap-2">
        <?php if ($it['photo']): ?><img src="<?= BASE_URL ?>/uploads/items/<?= e($it['photo']) ?>" style="width:46px;height:46px;object-fit:cover" class="rounded" alt=""><?php endif; ?>
        <div><strong><?= e($it['title']) ?></strong><div class="small text-muted"><?= e(substr($it['created_at'], 0, 10)) ?> · <?= (int)$it['request_count'] ?> request(s)</div></div></div></td>
      <td><?= e($it['full_name']) ?><div class="small text-muted"><?= e($it['email']) ?></div></td>
      <td><?= e($it['category_name']) ?></td>
      <td><span class="badge text-bg-<?= $badges[$it['status']] ?? 'secondary' ?>"><?= e(ucfirst($it['status'])) ?></span>
        <?php if ($it['status'] === 'rejected' && $it['admin_note']): ?><div class="small text-danger"><?= e($it['admin_note']) ?></div><?php endif; ?></td>
      <td class="text-end">
        <?php if (in_array($it['status'], ['pending', 'approved'], true)): ?>
          <form method="post" class="d-inline-flex gap-1 mb-1">
            <input type="hidden" name="action" value="takedown"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <input type="hidden" name="status" value="<?= e($status) ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
            <input class="form-control form-control-sm" name="note" placeholder="Reason" style="width:130px">
            <button class="btn btn-sm btn-outline-warning">Take down</button></form>
        <?php endif; ?>
        <?php if ((int)$it['request_count'] === 0): ?>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this item for good?');">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <input type="hidden" name="status" value="<?= e($status) ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>