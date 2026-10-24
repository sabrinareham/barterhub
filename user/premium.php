<?php
// user/premium.php - premium membership is planned for a later version
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Premium';
$active    = '';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5" style="max-width:640px">
  <div class="card text-center"><div class="card-body p-5">
    <i class="bi bi-stars display-4 text-warning"></i>
    <h2 class="h4 mt-3">Premium membership is coming soon</h2>
    <p class="text-muted mb-4">Barter Hub is free today. In a future version, optional premium members will get extras
      such as featured items. Exchanging will always stay free of buying and selling.</p>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/user/dashboard.php">Back to my dashboard</a>
  </div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>