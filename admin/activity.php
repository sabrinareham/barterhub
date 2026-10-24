<?php
// admin/activity.php - the latest 100 things that happened on the site
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity.php';
require_admin();

$feed = recent_activity($pdo, 100);

$adminTitle  = 'Activity log';
$adminActive = 'activity.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-3">
  <div class="card"><ul class="list-group list-group-flush">
    <?php if (count($feed) === 0): ?><li class="list-group-item text-muted">Nothing has happened yet.</li><?php endif; ?>
    <?php foreach ($feed as $row): [$icon, $text] = activity_line($row); ?>
      <li class="list-group-item d-flex justify-content-between">
        <span><i class="bi bi-<?= e($icon) ?> text-primary"></i> <?= $text ?></span>
        <span class="small text-muted text-nowrap ms-2"><?= e((string)$row['happened_at']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul></div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>