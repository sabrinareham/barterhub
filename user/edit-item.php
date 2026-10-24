<?php
// user/edit-item.php - the owner edits one of their items
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$id     = (int)($_GET['id'] ?? 0);

// ---- load the item, but ONLY if it is mine and still editable ----
$stmt = $pdo->prepare(
    "SELECT * FROM items WHERE id = ? AND user_id = ? AND status IN ('pending','approved','rejected')"
);
$stmt->execute([$id, $userId]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('danger', 'That item cannot be edited.');
    redirect(BASE_URL . '/user/my-items.php');
}

// ---- categories the user may choose (same logic as add-item.php) ----
$all = $pdo->query(
    'SELECT id, parent_id, name FROM categories WHERE is_visible = 1 ORDER BY sort_order, name'
)->fetchAll();

$tops = [];
foreach ($all as $c) {
    if ($c['parent_id'] === null) { $tops[$c['id']] = $c; }
}
$options = [];
foreach ($tops as $top) {
    $options[$top['id']] = $top['name'];
    foreach ($all as $c) {
        if ($c['parent_id'] == $top['id']) {
            $options[$c['id']] = $top['name'] . ' → ' . $c['name'];
        }
    }
}

$conditions = ['new' => 'New', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'];
$errors = [];

// the form starts filled with the saved values
$old = [
    'title'          => $item['title'],
    'description'    => $item['description'],
    'category_id'    => $item['category_id'],
    'item_condition' => $item['item_condition'],
    'wants'          => $item['wants'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'title'          => trim($_POST['title'] ?? ''),
        'description'    => trim($_POST['description'] ?? ''),
        'category_id'    => $_POST['category_id'] ?? '',
        'item_condition' => $_POST['item_condition'] ?? '',
        'wants'          => trim($_POST['wants'] ?? ''),
    ];

    if (mb_strlen($old['title']) < 3 || mb_strlen($old['title']) > 100) {
        $errors[] = 'Title must be 3 to 100 characters.';
    }
    if (mb_strlen($old['description']) < 10 || mb_strlen($old['description']) > 1000) {
        $errors[] = 'Description must be 10 to 1000 characters.';
    }
    if (!isset($options[(int)$old['category_id']])) {
        $errors[] = 'Please choose a category from the list.';
    }
    if (!isset($conditions[$old['item_condition']])) {
        $errors[] = 'Please choose the condition.';
    }
    if (mb_strlen($old['wants']) < 3 || mb_strlen($old['wants']) > 255) {
        $errors[] = 'Tell us what you want in exchange (3 to 255 characters).';
    }

    // ---- photo is OPTIONAL here: no file means "keep the old photo" ----
    $file = $_FILES['photo'] ?? null;
    $ext  = null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The photo could not be uploaded. Try a smaller file.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'The photo must be 2 MB or smaller.';
        } else {
            $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                $errors[] = 'The photo must be a JPG, PNG or WebP image.';
            } else {
                $ext = $allowed[$mime];
            }
        }
    }

    if (count($errors) === 0) {
        $photoName = $item['photo'];                 // default: keep the old photo
        $newSaved  = false;

        if ($ext !== null) {
            $dir = __DIR__ . '/../uploads/items/';
            if (!is_dir($dir)) { mkdir($dir, 0775, true); }
            $photoName = bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $photoName)) {
                $newSaved = true;
            } else {
                $errors[]  = 'Could not save the photo on the server.';
            }
        }

        if (count($errors) === 0) {
            // Status rule: an APPROVED item stays approved, a PENDING item stays pending,
            // a REJECTED item goes back to the admin (pending) because the user fixed it.
            $newStatus = ($item['status'] === 'rejected') ? 'pending' : $item['status'];

            $stmt = $pdo->prepare(
                "UPDATE items
                    SET category_id = ?, title = ?, description = ?, item_condition = ?, wants = ?,
                        photo = ?, status = ?, admin_note = NULL
                  WHERE id = ? AND user_id = ? AND status IN ('pending','approved','rejected')"
            );
            $stmt->execute([
                (int)$old['category_id'], $old['title'], $old['description'],
                $old['item_condition'], $old['wants'], $photoName, $newStatus, $id, $userId,
            ]);

            if ($newSaved && $item['photo']) {
                @unlink(__DIR__ . '/../uploads/items/' . $item['photo']);   // remove the replaced picture
            }
            set_flash('success', $newStatus === 'pending'
                ? 'Item updated. It is waiting for the admin to review it.'
                : 'Item updated.');
            redirect(BASE_URL . '/user/my-items.php');
        }
    }
}

$pageTitle = 'Edit item';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4" style="max-width:720px">
  <h2 class="h4 mb-3">Edit item</h2>

  <?php if ($item['status'] === 'rejected'): ?>
    <div class="alert alert-warning">
      The admin rejected this item<?= $item['admin_note'] ? ': ' . e($item['admin_note']) : '' ?>.
      Fix it and save, and it goes back to the admin for review.
    </div>
  <?php endif; ?>

  <?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul></div>
  <?php endif; ?>

  <!-- no action="" on purpose: the form posts back to the same address, including ?id= -->
  <form method="post" enctype="multipart/form-data" class="card p-4">
    <div class="mb-3">
      <label class="form-label">Title</label>
      <input class="form-control" name="title" value="<?= e($old['title']) ?>" required maxlength="100">
    </div>
    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea class="form-control" name="description" rows="4" required maxlength="1000"><?= e($old['description']) ?></textarea>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Category</label>
        <select class="form-select" name="category_id" required>
          <option value="">Choose...</option>
          <?php foreach ($options as $optId => $label): ?>
            <option value="<?= (int)$optId ?>" <?= (string)$old['category_id'] === (string)$optId ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Condition</label>
        <select class="form-select" name="item_condition">
          <?php foreach ($conditions as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $old['item_condition'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">What do you want in exchange?</label>
      <input class="form-control" name="wants" value="<?= e($old['wants']) ?>" required maxlength="255">
    </div>
    <div class="mb-3">
      <label class="form-label">Photo</label>
      <?php if ($item['photo']): ?>
        <div class="mb-2"><img src="<?= BASE_URL ?>/uploads/items/<?= e($item['photo']) ?>" style="height:110px;object-fit:cover" class="rounded" alt="Current photo"></div>
      <?php endif; ?>
      <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp">
      <div class="form-text">Leave empty to keep the current photo. JPG, PNG or WebP, up to 2 MB.</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary">Save changes</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/user/my-items.php">Cancel</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>