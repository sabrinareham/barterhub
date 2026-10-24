<?php
// user/requests.php - requests I received / sent / completed, and every action on them
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exchange.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$tab    = $_GET['tab'] ?? 'received';
if (!in_array($tab, ['received', 'sent', 'completed'], true)) { $tab = 'received'; }

// ---- actions (every one of them checks WHO I am inside the SQL) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $back   = 'received';

    if ($action === 'accept') {                                   // owner accepts
        [$ok, $msg] = accept_exchange($pdo, $id, $userId, 'owner');
        set_flash($ok ? 'success' : 'danger', $msg);

    } elseif ($action === 'accept_counter') {                     // requester accepts the counter-offer
        [$ok, $msg] = accept_exchange($pdo, $id, $userId, 'counter');
        set_flash($ok ? 'success' : 'danger', $msg);
        $back = 'sent';

    } elseif ($action === 'reject') {                             // owner rejects
        $stmt = $pdo->prepare(
            "UPDATE exchange_requests SET status = 'rejected', responded_at = CURRENT_TIMESTAMP
              WHERE id = ? AND owner_id = ? AND status = 'pending'"
        );
        $stmt->execute([$id, $userId]);
        set_flash($stmt->rowCount() ? 'success' : 'danger', $stmt->rowCount() ? 'Request rejected.' : 'This request can no longer be rejected.');

    } elseif ($action === 'decline') {                            // requester says no to the counter-offer
        $stmt = $pdo->prepare(
            "UPDATE exchange_requests SET status = 'rejected', responded_at = CURRENT_TIMESTAMP
              WHERE id = ? AND requester_id = ? AND status = 'countered'"
        );
        $stmt->execute([$id, $userId]);
        set_flash($stmt->rowCount() ? 'success' : 'danger', $stmt->rowCount() ? 'Counter-offer declined.' : 'This offer can no longer be declined.');
        $back = 'sent';

    } elseif ($action === 'cancel') {                             // requester withdraws
        $stmt = $pdo->prepare(
            "UPDATE exchange_requests SET status = 'cancelled', responded_at = CURRENT_TIMESTAMP
              WHERE id = ? AND requester_id = ? AND status IN ('pending','countered')"
        );
        $stmt->execute([$id, $userId]);
        set_flash($stmt->rowCount() ? 'success' : 'danger', $stmt->rowCount() ? 'Request cancelled.' : 'This request can no longer be cancelled.');
        $back = 'sent';

    } elseif ($action === 'counter') {                            // owner proposes a different item
        $counterId = (int)($_POST['counter_item_id'] ?? 0);
        $note      = trim($_POST['note'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM exchange_requests WHERE id = ? AND owner_id = ? AND status = 'pending'");
        $stmt->execute([$id, $userId]);
        $r = $stmt->fetch();

        // the item I ask for instead must belong to the REQUESTER and be approved
        $okItem = false;
        if ($r) {
            $stmt = $pdo->prepare("SELECT 1 FROM items WHERE id = ? AND user_id = ? AND status = 'approved'");
            $stmt->execute([$counterId, $r['requester_id']]);
            $okItem = (bool)$stmt->fetchColumn();
        }

        if (!$r) {
            set_flash('danger', 'This request can no longer be changed.');
        } elseif (!$okItem || $counterId === (int)$r['offered_item_id'] || item_reserved($pdo, $counterId)) {
            set_flash('danger', 'Please choose a different, available item from the list.');
        } elseif (mb_strlen($note) > 255) {
            set_flash('danger', 'Your note is too long (255 characters at most).');
        } else {
            $stmt = $pdo->prepare(
                "UPDATE exchange_requests
                    SET status = 'countered', counter_item_id = ?, counter_note = ?, responded_at = CURRENT_TIMESTAMP
                  WHERE id = ? AND owner_id = ? AND status = 'pending'"
            );
            $stmt->execute([$counterId, $note === '' ? null : $note, $id, $userId]);
            set_flash('success', 'Counter-offer sent. Now the other person decides.');
        }

    } elseif ($action === 'complete') {                           // either person confirms the swap happened
        [$ok, $msg] = mark_completed($pdo, $id, $userId);
        set_flash($ok ? 'success' : 'danger', $msg);
        $back = 'received';
    }
    redirect(BASE_URL . '/user/requests.php?tab=' . $back);
}

// ---- list for the open tab ----
if ($tab === 'sent') {
    $where  = "r.requester_id = ? AND r.status <> 'completed'";
    $params = [$userId];
} elseif ($tab === 'completed') {
    $where  = "(r.requester_id = ? OR r.owner_id = ?) AND r.status = 'completed'";
    $params = [$userId, $userId];
} else {
    $where  = "r.owner_id = ? AND r.status <> 'completed'";
    $params = [$userId];
}
$params = array_merge([$userId], $params);        // first "?" belongs to the ratings join below

$stmt = $pdo->prepare(
    "SELECT r.*,
            w.title AS wanted_title,  w.photo AS wanted_photo,
            o.title AS offered_title, o.photo AS offered_photo,
            c.title AS counter_title,
            ru.full_name AS requester_name, ru.city AS requester_city,
            ou.full_name AS owner_name,
            rt.stars AS my_stars
       FROM exchange_requests r
       JOIN items w  ON w.id  = r.item_id
       JOIN items o  ON o.id  = r.offered_item_id
       LEFT JOIN items c ON c.id = r.counter_item_id
       JOIN users ru ON ru.id = r.requester_id
       JOIN users ou ON ou.id = r.owner_id
       LEFT JOIN ratings rt ON rt.request_id = r.id AND rt.rater_id = ?
      WHERE $where
      ORDER BY r.created_at DESC"
);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// numbers for the tab badges: things waiting for MY answer
$stmt = $pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE owner_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$waitingReceived = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE requester_id = ? AND status = 'countered'");
$stmt->execute([$userId]);
$waitingSent = (int)$stmt->fetchColumn();

$badges = ['pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger',
           'cancelled' => 'secondary', 'countered' => 'info', 'completed' => 'primary'];

$pageTitle = 'Requests';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <h2 class="h4 mb-3">Exchange requests</h2>

  <ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'received' ? 'active' : '' ?>" href="<?= BASE_URL ?>/user/requests.php?tab=received">
      Received <?php if ($waitingReceived > 0): ?><span class="badge text-bg-danger"><?= $waitingReceived ?></span><?php endif; ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'sent' ? 'active' : '' ?>" href="<?= BASE_URL ?>/user/requests.php?tab=sent">
      Sent <?php if ($waitingSent > 0): ?><span class="badge text-bg-danger"><?= $waitingSent ?></span><?php endif; ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'completed' ? 'active' : '' ?>" href="<?= BASE_URL ?>/user/requests.php?tab=completed">Completed</a></li>
  </ul>

  <?php if (count($requests) === 0): ?>
    <div class="card"><div class="card-body text-center text-muted py-5">Nothing here yet.</div></div>
  <?php endif; ?>

  <?php foreach ($requests as $r): ?>
    <?php
      $iAmOwner  = (int)$r['owner_id'] === $userId;
      $otherName = $iAmOwner ? $r['requester_name'] : $r['owner_name'];
      $myDone    = $iAmOwner ? (int)$r['owner_done'] : (int)$r['requester_done'];
      $theirDone = $iAmOwner ? (int)$r['requester_done'] : (int)$r['owner_done'];
    ?>
    <div class="card mb-3"><div class="card-body">
      <div class="d-flex justify-content-between mb-2">
        <div class="small text-muted">
          <?= $iAmOwner ? 'From' : 'To' ?> <strong><?= e($otherName) ?></strong>
          <?php if ($iAmOwner): ?>(<?= e($r['requester_city']) ?>)<?php endif; ?>
          · <?= e($r['created_at']) ?>
        </div>
        <span class="badge text-bg-<?= $badges[$r['status']] ?? 'secondary' ?>"><?= e(ucfirst($r['status'])) ?></span>
      </div>

      <div class="row g-3 align-items-center text-center">
        <div class="col-5">
          <?php if ($r['offered_photo']): ?><img src="<?= BASE_URL ?>/uploads/items/<?= e($r['offered_photo']) ?>" class="rounded mb-1" style="height:90px;object-fit:cover;max-width:100%" alt=""><?php endif; ?>
          <div class="small text-muted"><?= $iAmOwner ? 'They offer' : 'You offer' ?></div>
          <strong class="<?= $r['status'] === 'countered' ? 'text-decoration-line-through text-muted' : '' ?>"><?= e($r['offered_title']) ?></strong>
        </div>
        <div class="col-2 fs-3 text-primary"><i class="bi bi-arrow-left-right"></i></div>
        <div class="col-5">
          <?php if ($r['wanted_photo']): ?><img src="<?= BASE_URL ?>/uploads/items/<?= e($r['wanted_photo']) ?>" class="rounded mb-1" style="height:90px;object-fit:cover;max-width:100%" alt=""><?php endif; ?>
          <div class="small text-muted"><?= $iAmOwner ? 'For your item' : 'For their item' ?></div>
          <strong><?= e($r['wanted_title']) ?></strong>
        </div>
      </div>

      <?php if ($r['message']): ?>
        <p class="small bg-light rounded p-2 mt-3 mb-0"><i class="bi bi-chat-left-text"></i> <?= e($r['message']) ?></p>
      <?php endif; ?>

      <?php if ($r['status'] === 'countered'): ?>
        <div class="alert alert-info mt-3 mb-0 small">
          <i class="bi bi-arrow-repeat"></i>
          <?= $iAmOwner ? 'You asked for' : 'The owner asks for' ?> <strong><?= e($r['counter_title']) ?></strong> instead.
          <?php if ($r['counter_note']): ?><br>Note: <?= e($r['counter_note']) ?><?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($r['status'] === 'accepted'): ?>
        <div class="bg-light rounded p-3 mt-3 small">
          <i class="bi bi-lock"></i> Both items are reserved. Chat and agree on a place to meet.
          <?php if ($myDone): ?><br><span class="text-success">You confirmed the swap.</span><?php endif; ?>
          <?php if ($theirDone): ?><br><span class="text-success"><?= e($otherName) ?> confirmed the swap.</span><?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="d-flex flex-wrap gap-2 mt-3">
        <?php if ($r['status'] === 'pending' && $iAmOwner): ?>
          <form method="post"><input type="hidden" name="action" value="accept"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Accept</button></form>
          <form method="post" onsubmit="return confirm('Reject this request?');"><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i> Reject</button></form>
          <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#counter<?= (int)$r['id'] ?>">
            <i class="bi bi-arrow-repeat"></i> Counter offer</button>

        <?php elseif ($r['status'] === 'pending' && !$iAmOwner): ?>
          <form method="post" onsubmit="return confirm('Cancel this request?');"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-outline-secondary btn-sm">Cancel request</button></form>

        <?php elseif ($r['status'] === 'countered' && !$iAmOwner): ?>
          <form method="post"><input type="hidden" name="action" value="accept_counter"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Accept this change</button></form>
          <form method="post"><input type="hidden" name="action" value="decline"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-outline-danger btn-sm">Decline</button></form>
          <form method="post" onsubmit="return confirm('Cancel the whole request?');"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-outline-secondary btn-sm">Cancel request</button></form>

        <?php elseif ($r['status'] === 'countered' && $iAmOwner): ?>
          <span class="small text-muted">Waiting for <?= e($otherName) ?> to answer your counter-offer.</span>

        <?php elseif ($r['status'] === 'accepted'): ?>
          <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/user/chat.php?request=<?= (int)$r['id'] ?>"><i class="bi bi-chat-dots"></i> Open chat</a>
          <?php if (!$myDone): ?>
            <form method="post" onsubmit="return confirm('Confirm that the swap really happened?');"><input type="hidden" name="action" value="complete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-outline-success btn-sm"><i class="bi bi-check2-circle"></i> Mark exchange as completed</button></form>
          <?php endif; ?>

        <?php elseif ($r['status'] === 'completed'): ?>
          <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/user/chat.php?request=<?= (int)$r['id'] ?>"><i class="bi bi-chat-dots"></i> View chat</a>
          <?php if ($r['my_stars'] === null): ?>
            <a class="btn btn-warning btn-sm" href="<?= BASE_URL ?>/user/rate.php?request=<?= (int)$r['id'] ?>"><i class="bi bi-star"></i> Rate <?= e($otherName) ?></a>
          <?php else: ?>
            <span class="small text-muted align-self-center">You rated <?= e($otherName) ?>: <?= str_repeat('★', (int)$r['my_stars']) ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <?php if ($r['status'] === 'pending' && $iAmOwner): ?>
        <?php
          $stmt = $pdo->prepare("SELECT id, title FROM items WHERE user_id = ? AND status = 'approved' AND id <> ? ORDER BY title");
          $stmt->execute([$r['requester_id'], $r['offered_item_id']]);
          $theirItems = $stmt->fetchAll();
        ?>
        <div class="collapse mt-3" id="counter<?= (int)$r['id'] ?>">
          <?php if (count($theirItems) === 0): ?>
            <div class="alert alert-secondary mb-0 small"><?= e($otherName) ?> has no other approved item you could ask for.</div>
          <?php else: ?>
            <form method="post" class="border rounded p-3">
              <input type="hidden" name="action" value="counter"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <label class="form-label small">I would rather have this item of <?= e($otherName) ?>:</label>
              <select class="form-select form-select-sm mb-2" name="counter_item_id" required>
                <option value="">Choose...</option>
                <?php foreach ($theirItems as $ti): ?><option value="<?= (int)$ti['id'] ?>"><?= e($ti['title']) ?></option><?php endforeach; ?>
              </select>
              <input class="form-control form-control-sm mb-2" name="note" maxlength="255" placeholder="Note (optional), e.g. I prefer this one">
              <button class="btn btn-primary btn-sm">Send counter-offer</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div></div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>