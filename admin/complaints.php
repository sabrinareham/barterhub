<?php
// admin/complaints.php - reports that users sent about items
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exchange.php';
require_admin();

$tabs = ['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];
$tab  = $_GET['status'] ?? 'open';
if (!isset($tabs[$tab])) { $tab = 'open'; }

$reasons = ['fake' => 'Fake / not as described', 'prohibited' => 'Not allowed', 'scam' => 'Scammer',
            'wrong_category' => 'Wrong category', 'other' => 'Other'];

// ---- actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['note'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ? AND status = 'open'");
    $stmt->execute([$id]);
    $report = $stmt->fetch();

    if (!$report || !in_array($action, ['resolve', 'dismiss'], true)) {
        set_flash('danger', 'This report was already handled.');
    } elseif (mb_strlen($note) > 255) {
        set_flash('danger', 'The note is too long (255 characters at most).');
    } else {
        $newStatus = $action === 'resolve' ? 'resolved' : 'dismissed';
        $pdo->prepare('UPDATE reports SET status = ?, admin_note = ?, handled_at = CURRENT_TIMESTAMP WHERE id = ? AND status = \'open\'')
            ->execute([$newStatus, $note === '' ? null : $note, $id]);

        $msg = 'Report ' . $newStatus . '.';
        if ($action === 'resolve' && isset($_POST['takedown'])) {            // the "also take the item down" checkbox
            if (item_reserved($pdo, (int)$report['item_id'])) {
                $msg .= ' The item is inside an accepted exchange, so it was NOT taken down.';
            } else {
                $pdo->prepare("UPDATE items SET status = 'rejected', admin_note = ? WHERE id = ? AND status IN ('pending','approved')")
                    ->execute(['Removed after a report' . ($note !== '' ? ': ' . $note : '.'), $report['item_id']]);
                $msg .= ' The item was taken down.';
            }
        }
        set_flash('success', $msg);
    }
    redirect(BASE_URL . '/admin/complaints.php?status=' . ($_POST['tab'] ?? 'open'));
}

$counts = $pdo->query('SELECT status, COUNT(*) FROM reports GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare(
    'SELECT rp.*, i.title AS item_title, i.status AS item_status, ru.full_name AS reporter_name,
            ou.full_name AS owner_name, ou.email AS owner_email
       FROM reports rp
       JOIN items i  ON i.id  = rp.item_id
       JOIN users ru ON ru.id = rp.reporter_id
       JOIN users ou ON ou.id = i.user_id
      WHERE rp.status = ?
      ORDER BY rp.created_at ASC LIMIT 200'
);
$stmt->execute([$tab]);
$reports = $stmt->fetchAll();

$flash = get_flash();
$adminTitle  = 'Reports and complaints';
$adminActive = 'complaints.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-3">
<?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<ul class="nav nav-pills mb-3">
  <?php foreach ($tabs as $key => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/complaints.php?status=<?= e($key) ?>">
      <?= e($label) ?> <span class="badge text-bg-light"><?= (int)($counts[$key] ?? 0) ?></span></a></li>
  <?php endforeach; ?>
</ul>

<?php if (count($reports) === 0): ?><div class="card"><div class="card-body text-center text-muted py-5">Nothing in this tab.</div></div><?php endif; ?>

<?php foreach ($reports as $r): ?>
  <div class="card mb-3"><div class="card-body">
    <div class="d-flex justify-content-between">
      <h5 class="mb-1"><i class="bi bi-flag text-danger"></i> <?= e($r['item_title']) ?></h5>
      <span class="badge text-bg-warning"><?= e($reasons[$r['reason']] ?? $r['reason']) ?></span>
    </div>
    <p class="small text-muted mb-2">Reported by <strong><?= e($r['reporter_name']) ?></strong> · item owner: <?= e($r['owner_name']) ?> (<?= e($r['owner_email']) ?>)
      · item is now <em><?= e($r['item_status']) ?></em> · <?= e((string)$r['created_at']) ?></p>
    <?php if ($r['details']): ?><p class="bg-light rounded p-2"><?= nl2br(e($r['details'])) ?></p><?php endif; ?>
    <?php if ($r['admin_note']): ?><p class="small">Admin note: <?= e($r['admin_note']) ?></p><?php endif; ?>

    <?php if ($r['status'] === 'open'): ?>
      <form method="post" class="row g-2 align-items-center">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="col-md-5"><input class="form-control form-control-sm" name="note" maxlength="255" placeholder="Note (optional)"></div>
        <div class="col-auto form-check ms-2"><input class="form-check-input" type="checkbox" name="takedown" id="td<?= (int)$r['id'] ?>">
          <label class="form-check-label small" for="td<?= (int)$r['id'] ?>">Also take the item down</label></div>
        <div class="col-auto"><button name="action" value="resolve" class="btn btn-sm btn-success">Resolve</button>
          <button name="action" value="dismiss" class="btn btn-sm btn-outline-secondary">Dismiss</button></div>
      </form>
    <?php endif; ?>
  </div></div>
<?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>