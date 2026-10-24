<?php
// admin/dashboard.php - real numbers + latest activity
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity.php';
require_admin();

// run a "SELECT COUNT(*) ..." and return the number
function dash_count($pdo, $sql) {
    return (int)$pdo->query($sql)->fetchColumn();
}

$stats = [
    ['people',          'Total users',          dash_count($pdo, "SELECT COUNT(*) FROM users")],
    ['box-seam',        'Total items',          dash_count($pdo, "SELECT COUNT(*) FROM items")],
    ['flag',            'Open reports',         dash_count($pdo, "SELECT COUNT(*) FROM reports WHERE status = 'open'")],
    ['box-arrow-in-right', 'Logins today',      dash_count($pdo, "SELECT COUNT(*) FROM users WHERE DATE(last_login_at) = CURDATE()")],
    ['arrow-left-right','Active exchanges',     dash_count($pdo, "SELECT COUNT(*) FROM exchange_requests WHERE status = 'accepted'")],
    ['check-circle',    'Completed swaps',      dash_count($pdo, "SELECT COUNT(*) FROM exchange_requests WHERE status = 'completed'")],
    ['inbox',           'New items to review',  dash_count($pdo, "SELECT COUNT(*) FROM items WHERE status = 'pending'")],
    ['cash-coin',       'Income (premium later)', null],
];
$openReports = $stats[2][2];
$toReview    = $stats[6][2];
$unverified  = dash_count($pdo, "SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active' AND is_verified = 0");

$feed = recent_activity($pdo, 8);

$adminTitle  = 'Dashboard';
$adminActive = 'dashboard.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-3">
  <div class="row g-3 mb-4">
    <?php foreach ($stats as $s): ?>
      <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-<?= e($s[0]) ?> fs-2 text-primary"></i>
        <div><div class="fs-3 fw-bold"><?= $s[2] === null ? '--' : (int)$s[2] ?></div><div class="small text-muted"><?= e($s[1]) ?></div></div>
      </div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between">
        Live activity <a class="small" href="<?= BASE_URL ?>/admin/activity.php">See all</a></div>
        <ul class="list-group list-group-flush">
          <?php if (count($feed) === 0): ?><li class="list-group-item text-muted">Nothing has happened yet.</li><?php endif; ?>
          <?php foreach ($feed as $row): [$icon, $text] = activity_line($row); ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><i class="bi bi-<?= e($icon) ?> text-primary"></i> <?= $text ?></span>
              <span class="small text-muted text-nowrap ms-2"><?= e(substr((string)$row['happened_at'], 5, 11)) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card"><div class="card-header bg-white fw-semibold">Needs your attention</div>
        <div class="list-group list-group-flush">
          <a class="list-group-item list-group-item-action <?= $openReports ? 'text-danger' : '' ?>" href="<?= BASE_URL ?>/admin/complaints.php"><i class="bi bi-flag"></i> <?= $openReports ?> open report<?= $openReports === 1 ? '' : 's' ?></a>
          <a class="list-group-item list-group-item-action" href="<?= BASE_URL ?>/admin/submissions.php"><i class="bi bi-inbox"></i> <?= $toReview ?> new item<?= $toReview === 1 ? '' : 's' ?> to review</a>
          <a class="list-group-item list-group-item-action" href="<?= BASE_URL ?>/admin/users.php"><i class="bi bi-person-check"></i> <?= $unverified ?> user<?= $unverified === 1 ? '' : 's' ?> not verified yet</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>