<?php
// admin/submissions.php - the admin reviews items users have posted
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();                                    // guard FIRST, before any action runs

$tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

$tab = $_GET['status'] ?? 'pending';
if (!isset($tabs[$tab])) { $tab = 'pending'; }

// ---- actions: approve / reject ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $backTo = $_POST['tab'] ?? 'pending';
    if (!isset($tabs[$backTo])) { $backTo = 'pending'; }

    if ($action === 'approve') {
        $stmt = $pdo->prepare(
            "UPDATE items SET status = 'approved', admin_note = NULL
              WHERE id = ? AND status IN ('pending','rejected')"
        );
        $stmt->execute([$id]);
        set_flash('success', 'Item approved. It is now public.');

    } elseif ($action === 'reject') {
        $note = trim($_POST['note'] ?? '');
        if (mb_strlen($note) < 3 || mb_strlen($note) > 255) {
            set_flash('danger', 'Please write a reason for the user (3 to 255 characters).');
        } else {
            $stmt = $pdo->prepare(
                "UPDATE items SET status = 'rejected', admin_note = ?
                  WHERE id = ? AND status IN ('pending','approved')"
            );
            $stmt->execute([$note, $id]);
            set_flash('success', 'Item rejected. The user can see your reason.');
        }
    }
    redirect(BASE_URL . '/admin/submissions.php?status=' . $backTo);
}

// ---- how many items in each tab? ----
$counts = $pdo->query('SELECT status, COUNT(*) FROM items GROUP BY status')
              ->fetchAll(PDO::FETCH_KEY_PAIR);

// ---- the items of the open tab ----
$stmt = $pdo->prepare(
    'SELECT i.*, u.full_name, u.email, u.city, c.name AS category_name
       FROM items i
       JOIN users u      ON u.id = i.user_id
       JOIN categories c ON c.id = i.category_id
      WHERE i.status = ?
      ORDER BY i.created_at ASC'
);
$stmt->execute([$tab]);
$items = $stmt->fetchAll();

$flash = get_flash();

$adminTitle  = 'Item submissions';
$adminActive = 'submissions.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="p-3">

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<ul class="nav nav-pills mb-3">
  <?php foreach ($tabs as $key => $label): ?>
    <li class="nav-item">
      <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/submissions.php?status=<?= e($key) ?>">
        <?= e($label) ?> <span class="badge text-bg-light"><?= (int)($counts[$key] ?? 0) ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if (count($items) === 0): ?>
  <div class="card"><div class="card-body text-center text-muted py-5">Nothing in this tab.</div></div>
<?php endif; ?>

<?php foreach ($items as $item): ?>
  <div class="card mb-3"><div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <?php if ($item['photo']): ?>
          <img src="<?= BASE_URL ?>/uploads/items/<?= e($item['photo']) ?>" class="img-fluid rounded" style="max-height:180px;object-fit:cover;width:100%" alt="">
        <?php endif; ?>
      </div>
      <div class="col-md-9">
        <h5 class="mb-1"><?= e($item['title']) ?></h5>
        <p class="small text-muted mb-2">
          <?= e($item['category_name']) ?> · <?= e(str_replace('_', ' ', $item['item_condition'])) ?> ·
          by <?= e($item['full_name']) ?> (<?= e($item['email']) ?>), <?= e($item['city']) ?>
        </p>
        <p class="mb-1"><?= nl2br(e($item['description'])) ?></p>
        <p class="small mb-2"><strong>Wants:</strong> <?= e($item['wants']) ?></p>
        <?php if ($item['admin_note']): ?>
          <p class="small text-danger">Reason given: <?= e($item['admin_note']) ?></p>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2 align-items-start">
          <?php if ($item['status'] !== 'approved'): ?>
            <form method="post">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="tab" value="<?= e($tab) ?>">
              <button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Approve</button>
            </form>
          <?php endif; ?>
          <?php if ($item['status'] !== 'rejected'): ?>
            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="tab" value="<?= e($tab) ?>">
              <input class="form-control form-control-sm" name="note" placeholder="Reason (required)" style="min-width:220px">
              <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i> Reject</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div></div>
<?php endforeach; ?>

</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>