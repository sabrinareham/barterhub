<?php
// user/profile.php - my details, change password, rating, exchange history
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$cities = ['Lahore', 'Faisalabad', 'Multan', 'Karachi', 'Islamabad', 'Rawalpindi', 'Sahiwal'];

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors   = [];      // problems in the "details" form
$pwErrors = [];      // problems in the "password" form
$old = ['full_name' => $user['full_name'], 'phone' => $user['phone'], 'city' => $user['city']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    if ($form === 'details') {
        $old = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone'     => trim($_POST['phone'] ?? ''),
            'city'      => $_POST['city'] ?? '',
        ];
        if (mb_strlen($old['full_name']) < 3 || mb_strlen($old['full_name']) > 80) {
            $errors[] = 'Please enter your full name (3 to 80 characters).';
        }
        if (!preg_match('/^[0-9 +\-]{10,20}$/', $old['phone'])) {
            $errors[] = 'Please enter a valid phone number (digits, spaces, + or -).';
        }
        if (!in_array($old['city'], $cities, true)) {
            $errors[] = 'Please choose your city from the list.';
        }
        if (count($errors) === 0) {
            $stmt = $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, city = ? WHERE id = ?');
            $stmt->execute([$old['full_name'], $old['phone'], $old['city'], $userId]);
            $_SESSION['user_name'] = $old['full_name'];            // the navbar greeting changes too
            set_flash('success', 'Profile saved.');
            redirect(BASE_URL . '/user/profile.php');
        }

    } elseif ($form === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $pwErrors[] = 'Your current password is not correct.';
        }
        if (strlen($new) < 8) {
            $pwErrors[] = 'The new password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $pwErrors[] = 'The two new passwords do not match.';
        }
        if (count($pwErrors) === 0) {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);
            set_flash('success', 'Password changed.');
            redirect(BASE_URL . '/user/profile.php');
        }
    }
}

// ---- numbers for the left card ----
$stmt = $pdo->prepare('SELECT ROUND(AVG(stars), 1) AS avg_rating, COUNT(*) AS n FROM ratings WHERE rated_id = ?');
$stmt->execute([$userId]);
$rating = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE status = 'completed' AND (requester_id = ? OR owner_id = ?)");
$stmt->execute([$userId, $userId]);
$swaps = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE user_id = ?');
$stmt->execute([$userId]);
$itemCount = (int)$stmt->fetchColumn();

// ---- exchange history (completed) ----
$stmt = $pdo->prepare(
    "SELECT r.completed_at, r.requester_id,
            w.title AS wanted_title, o.title AS offered_title,
            ru.full_name AS requester_name, ou.full_name AS owner_name,
            rt.stars AS my_stars
       FROM exchange_requests r
       JOIN items w  ON w.id  = r.item_id
       JOIN items o  ON o.id  = r.offered_item_id
       JOIN users ru ON ru.id = r.requester_id
       JOIN users ou ON ou.id = r.owner_id
       LEFT JOIN ratings rt ON rt.request_id = r.id AND rt.rater_id = ?
      WHERE r.status = 'completed' AND (r.requester_id = ? OR r.owner_id = ?)
      ORDER BY r.completed_at DESC"
);
$stmt->execute([$userId, $userId, $userId]);
$history = $stmt->fetchAll();

// ---- ratings I received ----
$stmt = $pdo->prepare(
    'SELECT rt.stars, rt.comment, u.full_name
       FROM ratings rt JOIN users u ON u.id = rt.rater_id
      WHERE rt.rated_id = ? ORDER BY rt.id DESC LIMIT 20'
);
$stmt->execute([$userId]);
$received = $stmt->fetchAll();

$pageTitle = 'My profile';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
  <div class="row g-4">

    <div class="col-lg-4">
      <div class="card text-center"><div class="card-body p-4">
        <i class="bi bi-person-circle display-3 text-primary"></i>
        <h4 class="mt-2 mb-0"><?= e($user['full_name']) ?></h4>
        <div class="text-muted"><?= e($user['city']) ?></div>
        <div class="my-2">
          <?php if ($user['is_verified']): ?><span class="badge text-bg-success"><i class="bi bi-patch-check"></i> Verified</span>
          <?php else: ?><span class="badge text-bg-light border">Not verified</span><?php endif; ?>
        </div>
        <?php if ((int)$rating['n'] > 0): ?>
          <div class="fs-4"><i class="bi bi-star-fill text-warning"></i> <?= e($rating['avg_rating']) ?></div>
          <div class="small text-muted">from <?= (int)$rating['n'] ?> rating<?= (int)$rating['n'] === 1 ? '' : 's' ?></div>
        <?php else: ?>
          <div class="small text-muted">No ratings yet</div>
        <?php endif; ?>
        <hr>
        <div class="row"><div class="col"><strong><?= $swaps ?></strong><div class="small text-muted">Swaps</div></div>
        <div class="col"><strong><?= $itemCount ?></strong><div class="small text-muted">Items</div></div></div>
      </div></div>
    </div>

    <div class="col-lg-8">

      <div class="card mb-4">
        <div class="card-header bg-white fw-semibold">Edit profile</div>
        <div class="card-body">
          <?php if (count($errors) > 0): ?>
            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="form" value="details">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Name</label>
                <input class="form-control" name="full_name" value="<?= e($old['full_name']) ?>" required maxlength="80"></div>
              <div class="col-md-6"><label class="form-label">Email</label>
                <input class="form-control" value="<?= e($user['email']) ?>" disabled>
                <div class="form-text">Your email is your login, so it cannot be changed here.</div></div>
              <div class="col-md-6"><label class="form-label">Phone</label>
                <input class="form-control" name="phone" value="<?= e($old['phone']) ?>" required></div>
              <div class="col-md-6"><label class="form-label">City</label>
                <select class="form-select" name="city" required>
                  <?php foreach ($cities as $c): ?><option <?= $old['city'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select></div>
              <div class="col-12"><button class="btn btn-primary">Save changes</button></div>
            </div>
          </form>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header bg-white fw-semibold">Change password</div>
        <div class="card-body">
          <?php if (count($pwErrors) > 0): ?>
            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($pwErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="form" value="password">
            <div class="row g-3">
              <div class="col-12"><label class="form-label">Current password</label>
                <input type="password" class="form-control" name="current_password" required></div>
              <div class="col-md-6"><label class="form-label">New password</label>
                <input type="password" class="form-control" name="new_password" required minlength="8"></div>
              <div class="col-md-6"><label class="form-label">Confirm new password</label>
                <input type="password" class="form-control" name="confirm_password" required></div>
              <div class="col-12"><button class="btn btn-outline-primary">Change password</button></div>
            </div>
          </form>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header bg-white fw-semibold">Exchange history</div>
        <?php if (count($history) === 0): ?>
          <div class="card-body text-muted">No completed exchanges yet.</div>
        <?php else: ?>
          <div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><th>Date</th><th>I gave</th><th>I got</th><th>With</th><th>My rating</th></tr></thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <?php $iAmRequester = (int)$h['requester_id'] === $userId; ?>
                <tr>
                  <td class="small"><?= e(substr((string)$h['completed_at'], 0, 10)) ?></td>
                  <td><?= e($iAmRequester ? $h['offered_title'] : $h['wanted_title']) ?></td>
                  <td><?= e($iAmRequester ? $h['wanted_title'] : $h['offered_title']) ?></td>
                  <td><?= e($iAmRequester ? $h['owner_name'] : $h['requester_name']) ?></td>
                  <td><?= $h['my_stars'] === null ? '<span class="text-muted">not rated</span>' : str_repeat('★', (int)$h['my_stars']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-header bg-white fw-semibold">Ratings I received</div>
        <?php if (count($received) === 0): ?>
          <div class="card-body text-muted">Nobody has rated you yet.</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($received as $rv): ?>
              <li class="list-group-item"><span class="text-warning"><?= str_repeat('★', (int)$rv['stars']) ?></span>
                <strong><?= e($rv['full_name']) ?></strong><?= $rv['comment'] ? ' - "' . e($rv['comment']) . '"' : '' ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>