<?php
// user/my-items.php - the logged-in user's own items, with review status
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId = (int)$_SESSION['user_id'];

// ---- delete (only my own item, and only while pending or rejected) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT photo FROM items WHERE id = ? AND user_id = ? AND status IN ('pending','rejected')"
    );
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();

    if ($row) {
        $pdo->prepare('DELETE FROM items WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
        if ($row['photo']) {
            @unlink(__DIR__ . '/../uploads/items/' . $row['photo']);   // remove the picture file too
        }
        set_flash('success', 'Item deleted.');
    } else {
        set_flash('danger', 'That item cannot be deleted.');
    }
    redirect(BASE_URL . '/user/my-items.php');
}

// ---- list ----
$stmt = $pdo->prepare(
    'SELECT i.*, c.name AS category_name
       FROM items i
       JOIN categories c ON c.id = i.category_id
      WHERE i.user_id = ?
      ORDER BY i.created_at DESC'
);
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

$badges = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'exchanged' => 'secondary'];

$pageTitle = 'My items';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">My items</h2>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/user/add-item.php"><i class="bi bi-plus-lg"></i> Add item</a>
  </div>

  <?php if (count($items) === 0): ?>
    <div class="card"><div class="card-body text-center py-5 text-muted">You have not added any items yet.</div></div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($items as $item): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100">
          <?php if ($item['photo']): ?>
            <img src="<?= BASE_URL ?>/uploads/items/<?= e($item['photo']) ?>" class="card-img-top" style="height:180px;object-fit:cover" alt="">
          <?php endif; ?>
          <div class="card-body">
            <span class="badge text-bg-<?= $badges[$item['status']] ?>"><?= e(ucfirst($item['status'])) ?></span>
            <h5 class="card-title mt-2"><?= e($item['title']) ?></h5>
            <p class="small text-muted mb-1"><?= e($item['category_name']) ?> · <?= e(str_replace('_', ' ', $item['item_condition'])) ?></p>
            <p class="small mb-2"><strong>Wants:</strong> <?= e($item['wants']) ?></p>
            <?php if ($item['status'] === 'rejected' && $item['admin_note']): ?>
              <p class="small text-danger mb-2">Admin note: <?= e($item['admin_note']) ?></p>
            <?php endif; ?>
             <?php if (in_array($item['status'], ['pending', 'approved', 'rejected'], true)): ?>
              <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/user/edit-item.php?id=<?= (int)$item['id'] ?>">
                  <i class="bi bi-pencil"></i> Edit
                </a>

                <?php if (in_array($item['status'], ['pending', 'rejected'], true)): ?>
                  <form method="post" onsubmit="return confirm('Delete this item?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>