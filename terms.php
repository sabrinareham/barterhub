<?php
// terms.php - rules of the site
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Terms';
$active    = '';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:820px">
  <h1 class="h2 mb-3">Terms of use</h1>
  <p class="text-muted">By using <?= SITE_NAME ?> you agree to these simple rules.</p>

  <ol class="lh-lg">
    <li><strong>Exchange only.</strong> Items are swapped for items. Asking for or offering money is not allowed.</li>
    <li><strong>Be honest.</strong> Describe your item and its condition truthfully and use your own photos.</li>
    <li><strong>Allowed items only.</strong> No weapons, drugs, stolen goods, fake products or anything illegal.</li>
    <li><strong>Be respectful.</strong> Treat other members politely in chat. Do not send harmful or illegal files.</li>
    <li><strong>Meet safely.</strong> Meet in a public place and check the item before you confirm the swap.</li>
    <li><strong>The admin can act.</strong> Items can be taken down and accounts blocked when these rules are broken.</li>
    <li><strong>Your risk.</strong> <?= SITE_NAME ?> connects people. Each swap is an agreement between the two members.</li>
  </ol>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>