<?php
// auth/login.php - log a user in
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect(account_home_url());
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // same message for "no such email" and "wrong password": do not help attackers guess emails
        $errors[] = 'Email or password is incorrect.';
    } elseif ($user['status'] === 'blocked') {
        $errors[] = 'Your account has been blocked. Please contact the admin.';
    } else {
        login_user($user);
        redirect(account_home_url());
    }
}

$pageTitle = 'Login';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container auth-wrap py-4">
  <div class="card auth-card shadow-sm"><div class="card-body p-4">
    <h4 class="mb-1">Welcome back</h4>
    <p class="text-muted">Log in to post items and start exchanges.</p>

    <?php if (count($errors) > 0): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <label class="form-label">Email</label>
      <input type="email" class="form-control mb-3" name="email" value="<?= e($email) ?>" required>
      <label class="form-label">Password</label>
      <input type="password" class="form-control mb-3" name="password" required>
      <button class="btn btn-primary w-100">Login</button>
    </form>
    <p class="text-center small mt-3 mb-0">New here? <a href="<?= BASE_URL ?>/auth/register.php">Create an account</a></p>
  </div></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>