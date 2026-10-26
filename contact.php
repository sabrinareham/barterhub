<?php
// contact.php - how to reach us
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Contact';
$active    = 'contact';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:820px">
  <h1 class="h2 mb-3">Contact us</h1>
  <p class="text-muted">Questions, ideas or a problem with your account? Write to us and we will reply as soon as we can.</p>

  <div class="card mb-3"><div class="card-body">
    <div class="mb-2"><i class="bi bi-envelope text-primary"></i> <strong>Email:</strong> support@barterhub.example</div>
    <div class="mb-2"><i class="bi bi-geo-alt text-primary"></i> <strong>City:</strong> Lahore, Pakistan</div>
    <div><i class="bi bi-clock text-primary"></i> <strong>Hours:</strong> Monday to Saturday, 9 am to 6 pm</div>
  </div></div>

  <div class="alert alert-info mb-0">
    <i class="bi bi-flag"></i> Found an item that looks fake or not allowed? Open the item and use <strong>Report this item</strong>. The admin looks at every report.
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>