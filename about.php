<?php
// about.php - what Barter Hub is
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'About';
$active    = 'about';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:820px">
  <h1 class="h2 mb-3">About <?= SITE_NAME ?></h1>
  <p class="lead">Barter Hub is a place to exchange things you no longer need for things you do. No buying, no selling, no money.</p>

  <div class="row g-3 my-4">
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
      <h2 class="h5"><i class="bi bi-1-circle text-primary"></i> List</h2>
      <p class="mb-0 text-muted">Add an item with a photo and say what you would like in return. The admin checks it before it goes public.</p>
    </div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
      <h2 class="h5"><i class="bi bi-2-circle text-primary"></i> Request</h2>
      <p class="mb-0 text-muted">Find something you want and offer one of your items. The owner can accept, reject or suggest another item.</p>
    </div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
      <h2 class="h5"><i class="bi bi-3-circle text-primary"></i> Swap</h2>
      <p class="mb-0 text-muted">Chat, share photos or files, meet, and confirm the swap. Then rate each other to build trust.</p>
    </div></div></div>
  </div>

  <h2 class="h5">How we keep it safe</h2>
  <ul class="text-muted">
    <li>Every new item is approved by an admin first.</li>
    <li>Any user can report an item that looks fake or not allowed.</li>
    <li>A swap is complete only when both people confirm it.</li>
    <li>Ratings after each swap show who is reliable.</li>
  </ul>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>