<?php
// includes/admin_header.php - top part of every admin page.
// The page that includes this file must set these two variables FIRST:
//   $adminTitle  -> title shown at the top and in the browser tab
//   $adminActive -> file name of the sidebar link to highlight, e.g. 'categories.php'
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/notify.php';

$adminTitle  = $adminTitle ?? 'Admin';
$adminActive = $adminActive ?? '';

// Sidebar links: [file name, icon, label]
$adminLinks = [
    ['dashboard.php',   'speedometer2',     'Dashboard'],
    ['activity.php',    'activity',         'Activity log'],
    ['users.php',       'people',           'Users'],
    ['items.php',       'box-seam',         'Items'],
    ['submissions.php', 'inbox',            'Item submissions'],
    ['exchanges.php',   'arrow-left-right', 'Exchanges'],
    ['chats.php',       'chat-square-text', 'Chat monitor'],
    ['searches.php',    'search',           'Searches'],
    ['complaints.php',  'flag',             'Reports and complaints'],
    ['categories.php',  'tags',             'Categories'],
    ['payments.php',    'cash-coin',        'Payments'],
];

// numbers for the red badges (and mark the page we are on as "seen")
$notify = admin_notify_counts($pdo, (int)($_SESSION['user_id'] ?? 0), $adminActive);
$stamp  = admin_notify_stamp($pdo);
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
  <?php require __DIR__ . '/csrf_script.php'; ?>
  <style>
    @keyframes bhPulse { 0% { transform: scale(1); } 50% { transform: scale(1.35); } 100% { transform: scale(1); } }
    .bh-badge.bh-pulse { animation: bhPulse .6s ease 2; }
  </style>
</head>
<body>
<?php render_flash(); ?>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="brand"><i class="bi bi-arrow-left-right"></i> <?= SITE_NAME ?> <span class="badge text-bg-warning ms-1">Admin</span></div>
    <div class="py-2">
      <?php foreach ($adminLinks as $link): ?>
        <?php $n = (int)($notify['counts'][$link[0]] ?? 0); ?>
        <a href="<?= BASE_URL ?>/admin/<?= $link[0] ?>" class="d-flex align-items-center <?= $link[0] === $adminActive ? 'active' : '' ?>">
          <i class="bi bi-<?= $link[1] ?> me-2"></i> <?= $link[2] ?>
          <span class="badge rounded-pill text-bg-danger ms-auto bh-badge <?= $n > 0 ? '' : 'd-none' ?>" data-badge="<?= e($link[0]) ?>"><?= $n ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>
  <div class="admin-main">
    <div class="admin-top d-flex justify-content-between align-items-center">
      <h1 class="h5 mb-0"><?= e($adminTitle) ?></h1>
      <a class="btn btn-outline-danger btn-sm" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
    <div class="admin-content">
<script>
// ---- live notifications: ask admin/notify.php every 4 seconds, no page reload needed ----
(function () {
  var page     = <?= json_encode($adminActive) ?>;
  var labels   = <?= json_encode(admin_notify_labels()) ?>;
  var last     = <?= json_encode($notify['counts']) ?>;      // numbers the page was drawn with
  var stamp    = <?= json_encode($stamp) ?>;
  var baseTitle = document.title;
  // read-only pages that may refresh themselves (pages with forms never do, so you do not lose what you typed)
  var autoReload = ['dashboard.php', 'activity.php', 'searches.php', 'chats.php'];

  function showTotal() {
    var total = 0;
    document.querySelectorAll('.bh-badge').forEach(function (b) { total += parseInt(b.textContent, 10) || 0; });
    document.title = (total > 0 ? '(' + total + ') ' : '') + baseTitle;
  }

  function beep() {                     // a short, soft "ding" (some browsers block sound until you click once)
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var o = ctx.createOscillator(), g = ctx.createGain();
      o.frequency.value = 880; g.gain.value = 0.05;
      o.connect(g); g.connect(ctx.destination);
      o.start(); o.stop(ctx.currentTime + 0.15);
    } catch (e) {}
  }

  function reloadButton() { return { label: 'Reload', run: function () { location.reload(); } }; }

  function check() {
    fetch('<?= BASE_URL ?>/admin/live-check.php?page=' + encodeURIComponent(page), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var news = false;
        var here = (d.fresh && d.fresh[page]) || 0;           // new things on the page we are looking at
        Object.keys(d.counts).forEach(function (k) {
          var now = d.counts[k], before = last[k] || 0;
          var badge = document.querySelector('[data-badge="' + k + '"]');
          if (badge) {
            badge.textContent = now;
            badge.classList.toggle('d-none', now <= 0);
          }
          if (now > before && k !== page) {                 // something new on ANOTHER page
            var diff = now - before;
            badge && badge.classList.add('bh-pulse');
            setTimeout(function () { badge && badge.classList.remove('bh-pulse'); }, 1300);
            bhToast(diff + ' ' + labels[k][diff === 1 ? 0 : 1], 'info', 5000, 'corner');
            news = true;
          }
          if (now > before && k === page) { here += now - before; }
          last[k] = now;
        });
        // something new on the page we are looking at
        if (here > 0 && autoReload.indexOf(page) === -1) {
          bhToast(here + ' ' + labels[page][here === 1 ? 0 : 1] + ' - reload to see', 'info', 6000, 'corner', reloadButton());
          news = true;
        }
        if (news) { beep(); }
        showTotal();
        // read-only pages: anything changed anywhere? then refresh this page quietly
        if (autoReload.indexOf(page) !== -1 && d.stamp !== stamp) {
          stamp = d.stamp;
          bhToast('Updating...', 'info', 1200, 'corner');
          setTimeout(function () { location.reload(); }, 1300);
        }
      })
      .catch(function () { /* offline or logged out: just try again next time */ });
  }

  showTotal();
  setInterval(check, 4000);
})();
</script>