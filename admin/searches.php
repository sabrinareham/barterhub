<?php
// admin/searches.php - what people search for (and what they cannot find)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// the most searched words
$top = $pdo->query(
    'SELECT term, COUNT(*) AS n, MAX(created_at) AS last_at FROM search_log GROUP BY term ORDER BY n DESC, last_at DESC LIMIT 15'
)->fetchAll();

// words that found NOTHING = items people want but nobody has posted yet
$missing = $pdo->query(
    'SELECT term, COUNT(*) AS n FROM search_log WHERE result_count = 0 GROUP BY term ORDER BY n DESC LIMIT 15'
)->fetchAll();

// the newest searches
$recent = $pdo->query(
    'SELECT s.term, s.result_count, s.created_at, u.full_name
       FROM search_log s LEFT JOIN users u ON u.id = s.user_id
      ORDER BY s.id DESC LIMIT 30'
)->fetchAll();

$adminTitle  = 'Searches';
$adminActive = 'searches.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-3">
  <div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Most searched</div>
      <ul class="list-group list-group-flush">
        <?php if (count($top) === 0): ?><li class="list-group-item text-muted">No searches yet.</li><?php endif; ?>
        <?php foreach ($top as $t): ?><li class="list-group-item d-flex justify-content-between"><?= e($t['term']) ?><span class="badge text-bg-primary"><?= (int)$t['n'] ?></span></li><?php endforeach; ?>
      </ul></div></div>
    <div class="col-md-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Searches with no result (people want these)</div>
      <ul class="list-group list-group-flush">
        <?php if (count($missing) === 0): ?><li class="list-group-item text-muted">Every search found something.</li><?php endif; ?>
        <?php foreach ($missing as $t): ?><li class="list-group-item d-flex justify-content-between"><?= e($t['term']) ?><span class="badge text-bg-danger"><?= (int)$t['n'] ?></span></li><?php endforeach; ?>
      </ul></div></div>
  </div>

  <div class="card"><div class="card-header bg-white fw-semibold">Latest searches</div>
    <div class="table-responsive"><table class="table mb-0">
      <thead><tr><th>Search</th><th>Results</th><th>Who</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr><td><?= e($r['term']) ?></td><td><?= (int)$r['result_count'] ?></td><td><?= e($r['full_name'] ?? 'A guest') ?></td><td class="small"><?= e((string)$r['created_at']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div></div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>