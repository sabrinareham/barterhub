<?php
// includes/admin_header.php - top part of every admin page.
// The page that includes this file must set these two variables FIRST:
//   $adminTitle  -> title shown at the top and in the browser tab
//   $adminActive -> file name of the sidebar link to highlight, e.g. 'categories.html'

$adminTitle  = $adminTitle ?? 'Admin';
$adminActive = $adminActive ?? '';

// Sidebar links: [file name, icon, label]
// When we convert a page from .html to .php we change its name HERE, in one place.
$adminLinks = [
    ['dashboard.php',   'speedometer2',     'Dashboard'],
    ['activity.html',    'activity',         'Activity log'],
    ['users.php',       'people',           'Users'],
    ['items.php',       'box-seam',         'Items'],
    ['submissions.php',  'inbox',            'Item submissions'],
    ['exchanges.php',   'arrow-left-right', 'Exchanges'],
    ['chats.html',       'chat-square-text', 'Chat monitor'],
    ['searches.html',    'search',           'Searches'],
    ['complaints.html',  'flag',             'Reports and complaints'],
    ['categories.html',   'tags',             'Categories'],
    ['payments.html',    'cash-coin',        'Payments'],
    ['add-listing.html', 'star',             'Featured listing'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - <?= e($adminTitle) ?> - <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="brand"><i class="bi bi-arrow-left-right"></i> <?= SITE_NAME ?> <span class="badge text-bg-warning ms-1">Admin</span></div>
    <div class="py-2">
      <?php foreach ($adminLinks as $link): ?>
        <a href="<?= BASE_URL ?>/admin/<?= $link[0] ?>" class="<?= $link[0] === $adminActive ? 'active' : '' ?>">
          <i class="bi bi-<?= $link[1] ?>"></i> <?= $link[2] ?>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>
  <div class="admin-main">
    <div class="admin-top d-flex justify-content-between align-items-center">
      <h1 class="h5 mb-0"><?= e($adminTitle) ?></h1>
      <a class="btn btn-outline-danger btn-sm" href="<?= BASE_URL ?>/auth/login.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
    <div class="admin-content">