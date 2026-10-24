<?php
// user/matches.php - items of other people that fit what MY items want
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/matching.php';
require_login();

$groups = find_matches($pdo, (int)$_SESSION['user_id']);

$pageTitle = 'My matches';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
  <h2 class="h4 mb-1"><i class="bi bi-stars text-warning"></i> Matches for you</h2>
  <p class="text-muted">For each of your approved items we look for other people's items that fit what you asked for.
    A <span class="badge text-bg-success">Mutual</span> match means they also want something like your item.</p>

  <?php if (count($groups) === 0): ?>
    <div class="card"><div class="card-body text-center py-5 text-muted">
      No matches yet. Matches use the <strong>"what do you want in exchange?"</strong> text of your approved items,
      so write it clearly (for example <em>table</em> or <em>bicycle</em>). <a href="<?= BASE_URL ?>/user/add-item.php">Add an item</a>
    </div></div>
  <?php endif; ?>

  <?php foreach ($groups as $g): ?>
    <h3 class="h6 mt-4">For your <strong><?= e($g['mine']['title']) ?></strong>
      <span class="text-muted fw-normal">(you want: <?= e($g['mine']['wants']) ?>)</span></h3>
    <div class="row g-3">
      <?php foreach ($g['matches'] as $m): $item = $m['item']; ?>
        <div class="col-6 col-md-4 col-lg-3">
          <?php if ($m['mutual']): ?><span class="badge text-bg-success mb-1">Mutual</span><?php else: ?><span class="badge text-bg-light border mb-1">They have it</span><?php endif; ?>
          <?php require __DIR__ . '/../includes/item_card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>