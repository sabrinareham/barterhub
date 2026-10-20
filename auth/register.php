<?php
// auth/register.php - create a new user account
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {                       // already logged in: nothing to register
    redirect(BASE_URL . '/index.php');
}

$cities = ['Lahore', 'Faisalabad', 'Multan', 'Karachi', 'Islamabad', 'Rawalpindi', 'Sahiwal'];
$errors = [];
$old    = ['full_name' => '', 'email' => '', 'phone' => '', 'city' => ''];   // refill the form after an error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $city     = $_POST['city'] ?? '';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $agree    = isset($_POST['agree']);

    $old = ['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'city' => $city];

    // ---- validate every field on the SERVER (the browser checks can be bypassed) ----
    if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 80) {
        $errors[] = 'Please enter your full name (3 to 80 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!preg_match('/^[0-9 +\-]{10,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number (digits, spaces, + or -).';
    }
    if (!in_array($city, $cities, true)) {
        $errors[] = 'Please choose your city from the list.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $password2) {
        $errors[] = 'The two passwords do not match.';
    }
    if (!$agree) {
        $errors[] = 'You must agree to the terms and conditions.';
    }

    // ---- is the email already used? ----
    if (count($errors) === 0) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists. Please log in.';
        }
    }

    // ---- save ----
    if (count($errors) === 0) {
        $hash = password_hash($password, PASSWORD_DEFAULT);   // NEVER store the real password
        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, city, password_hash) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fullName, $email, $phone, $city, $hash]);

        set_flash('success', 'Account created. Please log in.');
        redirect(BASE_URL . '/auth/login.php');
    }
}

$pageTitle = 'Register';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container auth-wrap py-4">
  <div class="card auth-card shadow-sm" style="max-width:560px"><div class="card-body p-4">
    <h4 class="mb-1">Create your account</h4>
    <p class="text-muted">It is free. Start exchanging in a minute.</p>

    <?php if (count($errors) > 0): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Full name</label>
          <input class="form-control" name="full_name" value="<?= e($old['full_name']) ?>" required minlength="3" maxlength="80">
        </div>
        <div class="col-12">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" value="<?= e($old['email']) ?>" required maxlength="120">
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone</label>
          <input class="form-control" name="phone" value="<?= e($old['phone']) ?>" placeholder="03xx xxxxxxx" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">City</label>
          <select class="form-select" name="city" required>
            <option value="">Choose...</option>
            <?php foreach ($cities as $c): ?>
              <option <?= $old['city'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Password</label>
          <input type="password" class="form-control" name="password" required minlength="8">
          <div class="form-text">At least 8 characters.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm password</label>
          <input type="password" class="form-control" name="password2" required>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="agree" id="agree" required>
            <label class="form-check-label small" for="agree">I agree to the <a href="<?= BASE_URL ?>/terms.html" target="_blank">terms and conditions</a></label>
          </div>
        </div>
        <div class="col-12"><button class="btn btn-primary w-100">Register</button></div>
      </div>
    </form>
    <p class="text-center small mt-3 mb-0">Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Login</a></p>
  </div></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>