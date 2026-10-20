<?php
// admin/categories.php - Categories and sub-categories: Create, Read, Update, Delete
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

// ------------------------------------------------------------------
// Reads the add/edit form, checks it, and returns [cleanData, errors].
// $currentId = 0 when adding, or the category's id when editing.
// ------------------------------------------------------------------
function read_category_form($pdo, $currentId = 0) {
    $errors = [];

    $name     = trim($_POST['name'] ?? '');
    $slugIn   = trim($_POST['slug'] ?? '');
    $icon     = trim($_POST['icon'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $sort     = (int)($_POST['sort_order'] ?? 0);
    $visible  = isset($_POST['is_visible']) ? 1 : 0;   // an unticked checkbox is not sent at all
    $parentId = (int)($_POST['parent_id'] ?? 0);        // 0 means "no parent"

    // name
    if ($name === '' || mb_strlen($name) > 40) {
        $errors[] = 'Name is required (maximum 40 characters).';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id <> ?');
        $stmt->execute([$name, $currentId]);
        if ($stmt->fetch()) {
            $errors[] = 'Another category already uses that name.';
        }
    }

    // icon
    if ($icon === '') {
        $icon = 'tag';
    }
    if (!preg_match('/^[a-z0-9-]{1,40}$/', $icon)) {
        $errors[] = 'Icon must be a Bootstrap icon name such as "book" or "cup-hot".';
    }

    // description
    if (mb_strlen($desc) > 255) {
        $errors[] = 'Description can be at most 255 characters.';
    }

    // slug: use what the admin typed, or build it from the name; then make it unique
    $slug = unique_slug($pdo, make_slug($slugIn !== '' ? $slugIn : $name), $currentId);

    // parent: we allow only TWO levels (category -> sub-category)
    if ($parentId > 0) {
        if ($parentId === $currentId) {
            $errors[] = 'A category cannot be its own parent.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND parent_id IS NULL');
            $stmt->execute([$parentId]);
            if (!$stmt->fetch()) {
                $errors[] = 'Please choose a top-level category as the parent.';
            }
        }
        if ($currentId > 0) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
            $stmt->execute([$currentId]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'This category has sub-categories, so it cannot become a sub-category itself.';
            }
        }
    }

    $data = [
        'parent_id'   => $parentId > 0 ? $parentId : null,   // NULL = top-level
        'name'        => $name,
        'slug'        => $slug,
        'icon'        => $icon,
        'description' => $desc !== '' ? $desc : null,
        'sort_order'  => $sort,
        'is_visible'  => $visible,
    ];
    return [$data, $errors];
}

$errors = [];      // validation messages to show above the form
$old    = null;    // what the admin typed, so we can refill the form after an error

// ============ PART 1: handle form submits ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- CREATE ----------
    if ($action === 'add') {
        [$data, $errors] = read_category_form($pdo, 0);
        if (count($errors) === 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO categories (parent_id, name, slug, icon, description, sort_order, is_visible)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$data['parent_id'], $data['name'], $data['slug'], $data['icon'],
                            $data['description'], $data['sort_order'], $data['is_visible']]);
            set_flash('success', 'Category "' . $data['name'] . '" added.');
            redirect('categories.php');          // success -> redirect (Post/Redirect/Get)
        }
        $old = $_POST;                           // error -> show the form again with the typed values
    }

    // ---------- UPDATE (full edit form) ----------
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        [$data, $errors] = read_category_form($pdo, $id);
        if (count($errors) === 0) {
            $stmt = $pdo->prepare(
                'UPDATE categories
                    SET parent_id = ?, name = ?, slug = ?, icon = ?, description = ?, sort_order = ?, is_visible = ?
                  WHERE id = ?'
            );
            $stmt->execute([$data['parent_id'], $data['name'], $data['slug'], $data['icon'],
                            $data['description'], $data['sort_order'], $data['is_visible'], $id]);
            set_flash('success', 'Category updated.');
            redirect('categories.php');
        }
        $old = $_POST;
    }

    // ---------- UPDATE (hide / show button) ----------
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE categories SET is_visible = 1 - is_visible WHERE id = ?');
        $stmt->execute([$id]);
        set_flash('success', 'Category visibility changed.');
        redirect('categories.php');
    }

    // ---------- DELETE ----------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            set_flash('danger', 'This category has sub-categories. Delete or move them first.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'Category deleted.');
        }
        redirect('categories.php');
    }
}

// ============ PART 2: read the data the page needs ============
$all = $pdo->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();

// Group the flat list: $tops = top-level rows, $children[parentId] = that parent's sub-categories
$tops = [];
$children = [];
foreach ($all as $row) {
    if ($row['parent_id'] === null) {
        $tops[] = $row;
    } else {
        $children[$row['parent_id']][] = $row;
    }
}

// One flat list for the table: each top-level row followed by its children
$rows = [];
foreach ($tops as $top) {
    $top['level'] = 0;
    $rows[] = $top;
    foreach ($children[$top['id']] ?? [] as $child) {
        $child['level'] = 1;
        $rows[] = $child;
    }
}

// Which category are we editing? (?edit=5 in the URL, or the id that was just submitted)
$editId = (int)($_GET['edit'] ?? 0);
if (($_POST['action'] ?? '') === 'update') {
    $editId = (int)($_POST['id'] ?? 0);
}
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}

// Values to put in the form fields
$form = [
    'name' => '', 'slug' => '', 'parent_id' => (int)($_GET['parent'] ?? 0),   // ?parent=3 pre-selects a parent
    'icon' => 'tag', 'description' => '', 'sort_order' => 0, 'is_visible' => 1,
];
if ($editing) {
    foreach (array_keys($form) as $key) {
        $form[$key] = $editing[$key] ?? $form[$key];
    }
    $form['parent_id'] = (int)($editing['parent_id'] ?? 0);
}
if ($old !== null) {   // after a validation error: show what the admin typed
    $form['name']        = $old['name'] ?? '';
    $form['slug']        = $old['slug'] ?? '';
    $form['parent_id']   = (int)($old['parent_id'] ?? 0);
    $form['icon']        = $old['icon'] ?? 'tag';
    $form['description'] = $old['description'] ?? '';
    $form['sort_order']  = (int)($old['sort_order'] ?? 0);
    $form['is_visible']  = isset($old['is_visible']) ? 1 : 0;
}

// Parent choices: top-level categories, except the one we are editing
$parentOptions = array_filter($tops, fn($t) => (int)$t['id'] !== $editId);

$iconSuggestions = ['book', 'book-half', 'laptop', 'lamp', 'handbag', 'trophy', 'cup-hot', 'controller',
                    'phone', 'smartwatch', 'gem', 'brush', 'tag', 'house', 'tools', 'bicycle', 'camera',
                    'headphones', 'palette', 'basket', 'bag', 'gift', 'tv'];

// ============ PART 3: show the page ============
$adminTitle  = 'Categories';
$adminActive = 'categories.php';
require_once __DIR__ . '/../includes/admin_header.php';

$flash = get_flash();
?>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<p class="text-muted">Users can only choose from these categories when adding an item. A category can have sub-categories (for example Electronics &rarr; Laptops).</p>

<div class="row g-4">
  <!-- left: add form OR edit form -->
  <div class="col-lg-5">
    <div class="card table-card"><div class="card-body">

      <?php if (count($errors) > 0): ?>
        <div class="alert alert-danger">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <h6><?= $editing ? 'Edit category' : 'Add a category' ?></h6>
      <form method="post">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
        <?php if ($editing): ?>
          <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
        <?php endif; ?>

        <label class="form-label small mb-1">Name *</label>
        <input class="form-control mb-2" name="name" value="<?= e($form['name']) ?>" required maxlength="40" placeholder="e.g. Garden tools">

        <label class="form-label small mb-1">Slug <span class="text-muted">(optional)</span></label>
        <input class="form-control" name="slug" value="<?= e($form['slug']) ?>" maxlength="60" placeholder="made from the name if empty">
        <div class="form-text mb-2">Used in web addresses, like <code>?category=garden-tools</code>.</div>

        <label class="form-label small mb-1">Parent category</label>
        <select class="form-select mb-2" name="parent_id">
          <option value="0">None (top-level category)</option>
          <?php foreach ($parentOptions as $opt): ?>
            <option value="<?= (int)$opt['id'] ?>" <?= (int)$opt['id'] === (int)$form['parent_id'] ? 'selected' : '' ?>>
              <?= e($opt['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label class="form-label small mb-1">Icon</label>
        <div class="input-group">
          <span class="input-group-text"><i id="iconPreview" class="bi bi-<?= e($form['icon']) ?>"></i></span>
          <input id="iconInput" class="form-control" name="icon" list="iconList" value="<?= e($form['icon']) ?>" maxlength="40">
        </div>
        <datalist id="iconList">
          <?php foreach ($iconSuggestions as $ic): ?><option value="<?= e($ic) ?>"><?php endforeach; ?>
        </datalist>
        <div class="form-text mb-2">A Bootstrap Icons name. Find more at <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">icons.getbootstrap.com</a>.</div>

        <label class="form-label small mb-1">Description</label>
        <textarea class="form-control mb-2" name="description" rows="3" maxlength="255" placeholder="Short text shown to users"><?= e($form['description']) ?></textarea>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small mb-1">Sort order</label>
            <input class="form-control" type="number" name="sort_order" value="<?= (int)$form['sort_order'] ?>">
          </div>
          <div class="col-6 d-flex align-items-end">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="is_visible" value="1" id="isVisible" <?= $form['is_visible'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="isVisible">Visible to users</label>
            </div>
          </div>
        </div>

        <button class="btn btn-primary w-100 mb-2"><?= $editing ? 'Save changes' : 'Add category' ?></button>
        <?php if ($editing): ?>
          <a class="btn btn-light w-100" href="categories.php">Cancel</a>
        <?php endif; ?>
      </form>
    </div></div>
  </div>

  <!-- right: the list (parents with their sub-categories underneath) -->
  <div class="col-lg-7">
    <div class="card table-card"><div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th>Category</th><th>Status</th><th>Order</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php if (count($rows) === 0): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No categories yet. Add the first one.</td></tr>
          <?php endif; ?>

          <?php foreach ($rows as $cat): ?>
            <tr>
              <td class="<?= $cat['level'] === 1 ? 'ps-4' : '' ?>">
                <?php if ($cat['level'] === 1): ?><span class="text-muted">&#8627;</span><?php endif; ?>
                <i class="bi bi-<?= e($cat['icon']) ?> text-primary"></i>
                <strong><?= e($cat['name']) ?></strong>
                <div class="small text-muted"><?= e($cat['slug']) ?></div>
              </td>
              <td>
                <?php if ($cat['is_visible']): ?>
                  <span class="badge text-bg-success">Visible</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Hidden</span>
                <?php endif; ?>
              </td>
              <td><?= (int)$cat['sort_order'] ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="categories.php?edit=<?= (int)$cat['id'] ?>">Edit</a>
                <?php if ($cat['level'] === 0): ?>
                  <a class="btn btn-sm btn-outline-success" href="categories.php?parent=<?= (int)$cat['id'] ?>">+ Sub</a>
                <?php endif; ?>

                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary"><?= $cat['is_visible'] ? 'Hide' : 'Show' ?></button>
                </form>

                <form method="post" class="d-inline" onsubmit="return confirm('Delete this category?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>

<script>
  // live icon preview: when the admin types an icon name, show the icon next to the box
  var iconInput = document.getElementById('iconInput');
  var iconPreview = document.getElementById('iconPreview');
  iconInput.addEventListener('input', function () {
    iconPreview.className = 'bi bi-' + (iconInput.value.trim() || 'tag');
  });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>