<?php
// user/add-item.php - a logged-in user posts an item (it starts as "pending")
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// ---- categories the user may choose (visible only; a child of a hidden parent is skipped) ----
$all = $pdo->query(
    'SELECT id, parent_id, name FROM categories WHERE is_visible = 1 ORDER BY sort_order, name'
)->fetchAll();

$tops = [];
foreach ($all as $c) {
    if ($c['parent_id'] === null) { $tops[$c['id']] = $c; }
}
$options = [];                                   // id => label shown in the dropdown
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
$old = ['title' => '', 'description' => '', 'category_id' => '', 'item_condition' => 'good', 'wants' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'title'          => trim($_POST['title'] ?? ''),
        'description'    => trim($_POST['description'] ?? ''),
        'category_id'    => $_POST['category_id'] ?? '',
        'item_condition' => $_POST['item_condition'] ?? '',
        'wants'          => trim($_POST['wants'] ?? ''),
    ];

    // ---- validate text fields ----
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

    // ---- validate the photo ----
    $file = $_FILES['photo'] ?? null;
    $ext  = null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose a photo.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'The photo could not be uploaded. Try a smaller file.';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'The photo must be 2 MB or smaller.';
    } else {
        // look at the real file contents, not the name the user gave it
        $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            $errors[] = 'The photo must be a JPG, PNG or WebP image.';
        } else {
            $ext = $allowed[$mime];
        }
    }

    // ---- save ----
    if (count($errors) === 0) {
        $dir = dirname(__DIR__) . '/uploads/items/';
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }

        $fileName = bin2hex(random_bytes(8)) . '.' . $ext;     // random name, we choose the extension
        if (!move_uploaded_file($file['tmp_name'], $dir . $fileName)) {
            $errors[] = 'Could not save the photo on the server.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO items (user_id, category_id, title, description, item_condition, wants, photo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $_SESSION['user_id'], (int)$old['category_id'], $old['title'],
                $old['description'], $old['item_condition'], $old['wants'], $fileName,
            ]);
            set_flash('success', 'Item sent to the admin for review. It will appear publicly after approval.');
            redirect(BASE_URL . '/user/my-items.php');
        }
    }
}

$pageTitle = 'Add an item';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4" style="max-width:720px">
  <h2 class="h4 mb-3">Add an item</h2>

  <?php if (count($errors) > 0): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul></div>
  <?php endif; ?>

  <!-- enctype is REQUIRED for file upload -->
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
          <?php foreach ($options as $id => $label): ?>
            <option value="<?= (int)$id ?>" <?= (string)$old['category_id'] === (string)$id ? 'selected' : '' ?>><?= e($label) ?></option>
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
      <input class="form-control" name="wants" value="<?= e($old['wants']) ?>" placeholder="e.g. a study table or a bicycle" required maxlength="255">
    </div>
    <div class="mb-3">
      <label class="form-label">Photo (JPG, PNG or WebP, up to 2 MB)</label>
      <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp" required>
    </div>
    <button class="btn btn-primary">Send for review</button>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>