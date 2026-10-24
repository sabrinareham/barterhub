<?php
// admin/payments.php - premium membership is future work
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminTitle  = 'Payments';
$adminActive = 'payments.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-3">
  <div class="card"><div class="card-body text-center py-5">
    <i class="bi bi-cash-coin display-4 text-muted"></i>
    <h2 class="h5 mt-3">Premium membership is a future feature</h2>
    <p class="text-muted mb-0">Barter Hub has no payments today: exchanging is free, with no buying and no selling.
      Payments and featured listings will be added here in a later version.</p>
  </div></div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>