<?php
// includes/header.php - top of every PUBLIC page: <head> + navbar.
// The page that includes this file must set these variables FIRST:
//   $pageTitle -> text for the browser tab
//   $active    -> key of the navbar link to highlight ('home', 'browse', 'about', 'contact')
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Welcome';
$active    = $active ?? '';

// Navbar links: [key, file name, label]
// When we convert a page from .html to .php we change its file name HERE, in one place.
$navLinks = [
    ['home',    'index.php',    'Home'],
    ['browse',  'browse.php',   'Browse items'],
    ['about',   'about.html',   'About'],
    ['contact', 'contact.html', 'Contact'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> - <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bh-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/index.php">
      <span class="brand-dot"><i class="bi bi-arrow-left-right"></i></span><?= SITE_NAME ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <?php foreach ($navLinks as $link): ?>
          <li class="nav-item">
            <a class="nav-link <?= $link[0] === $active ? 'active' : '' ?>" href="<?= BASE_URL ?>/<?= $link[1] ?>"><?= $link[2] ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex gap-2 align-items-center">
        <?php if (is_logged_in()): ?>
          <span class="small text-muted d-none d-lg-inline">Hello, <?= e($_SESSION['user_name']) ?></span>
          <a class="btn btn-outline-primary" href="<?= e(account_home_url()) ?>">My account</a>
          <a class="btn btn-primary" href="<?= BASE_URL ?>/auth/logout.php">Logout</a>
        <?php else: ?>
          <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/auth/login.php">Login</a>
          <a class="btn btn-primary" href="<?= BASE_URL ?>/auth/register.php">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
  <div class="container mt-3">
    <div class="alert alert-<?= e($flash['type']) ?> mb-0"><?= e($flash['message']) ?></div>
  </div>
<?php endif; ?>