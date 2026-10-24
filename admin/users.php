<?php
// admin/users.php - all users: search, block / unblock, verify / unverify
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();                                    // guard FIRST

$q = trim($_GET['q'] ?? '');

// ---- actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $q      = trim($_POST['q'] ?? '');

    // only these four actions exist. "role = 'user'" means an admin account can never be blocked here.
    $actions = [
        'block'    => ["UPDATE users SET status = 'blocked' WHERE id = ? AND role = 'user'", 'User blocked. They cannot log in or use the site.'],
        'unblock'  => ["UPDATE users SET status = 'active'  WHERE id = ? AND role = 'user'", 'User unblocked.'],
        'verify'   => ['UPDATE users SET is_verified = 1 WHERE id = ?', 'User verified. A Verified badge is shown.'],
        'unverify' => ['UPDATE users SET is_verified = 0 WHERE id = ?', 'Verified badge removed.'],
    ];
    if (isset($actions[$action])) {
        $stmt = $pdo->prepare($actions[$action][0]);
        $stmt->execute([$id]);
        set_flash($stmt->rowCount() ? 'success' : 'danger', $stmt->rowCount() ? $actions[$action][1] : 'Nothing was changed.');
    }
    redirect(BASE_URL . '/admin/users.php' . ($q !== '' ? '?q=' . urlencode($q) : ''));
}

// ---- list ----
$like = '%' . $q . '%';
$stmt = $pdo->prepare(
    "SELECT u.*,
            (SELECT COUNT(*) FROM items WHERE user_id = u.id) AS item_count,
            (SELECT COUNT(*) FROM exchange_requests r
              WHERE r.status = 'completed' AND (r.requester_id = u.id OR r.owner_id = u.id)) AS swaps,
            (SELECT ROUND(AVG(stars), 1) FROM ratings WHERE rated_id = u.id) AS avg_rating
       FROM users u
      WHERE (? = '' OR u.full_name LIKE ? OR u.email LIKE ? OR u.city LIKE ?)
      ORDER BY u.created_at DESC
      LIMIT 200"
);
$stmt->execute([$q, $like, $like, $like]);
$users = $stmt->fetchAll();

$flash = get_flash();

$adminTitle  = 'Users';
$adminActive = 'users.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-3">
<?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-6"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name, email or city"></div>
  <div class="col-auto"><button class="btn btn-primary">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/users.php">Clear</a><?php endif; ?></div>
</form>

<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>#</th><th>User</th><th>City</th><th>Items</th><th>Swaps</th><th>Rating</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php if (count($users) === 0): ?><tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr><?php endif; ?>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= (int)$u['id'] ?></td>
      <td><strong><?= e($u['full_name']) ?></strong>
        <?php if ($u['role'] === 'admin'): ?><span class="badge text-bg-warning">Admin</span><?php endif; ?>
        <?php if ($u['is_verified']): ?><i class="bi bi-patch-check-fill text-success" title="Verified"></i><?php endif; ?>
        <div class="small text-muted"><?= e($u['email']) ?> · <?= e($u['phone']) ?></div></td>
      <td><?= e($u['city']) ?></td>
      <td><?= (int)$u['item_count'] ?></td>
      <td><?= (int)$u['swaps'] ?></td>
      <td><?= $u['avg_rating'] !== null ? '★ ' . e($u['avg_rating']) : '-' ?></td>
      <td><span class="badge text-bg-<?= $u['status'] === 'blocked' ? 'danger' : 'success' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
      <td class="text-end text-nowrap">
        <form method="post" class="d-inline"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
          <input type="hidden" name="action" value="<?= $u['is_verified'] ? 'unverify' : 'verify' ?>">
          <button class="btn btn-sm btn-outline-secondary"><?= $u['is_verified'] ? 'Remove badge' : 'Verify' ?></button></form>
        <?php if ($u['role'] === 'user'): ?>
          <form method="post" class="d-inline" onsubmit="return confirm('<?= $u['status'] === 'blocked' ? 'Unblock' : 'Block' ?> this user?');">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
            <input type="hidden" name="action" value="<?= $u['status'] === 'blocked' ? 'unblock' : 'block' ?>">
            <button class="btn btn-sm <?= $u['status'] === 'blocked' ? 'btn-success' : 'btn-outline-danger' ?>"><?= $u['status'] === 'blocked' ? 'Unblock' : 'Block' ?></button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>