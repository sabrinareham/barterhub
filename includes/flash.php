<?php
// includes/flash.php - shows flash messages as small pop-ups ("toasts") that close by themselves after 2 seconds.
// A page calls render_flash() ONCE, right after <body> starts.
// It also creates the JavaScript function bhToast(), so other scripts (admin notifications) can show pop-ups too.
function render_flash() {
    // get_flash() (in functions.php) keeps a copy in $GLOBALS['bh_flash'], so this works
    // even when the page already called get_flash() itself.
    $flash = $GLOBALS['bh_flash'] ?? get_flash();
    $type  = 'info';
    $msg   = '';
    if ($flash) {
        $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
        $msg  = (string)$flash['message'];
    }
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;   // makes the text safe inside <script>
    ?>
<style>
  #bhToastTop    { position: fixed; top: 72px; left: 50%; transform: translateX(-50%); z-index: 2000; width: min(92vw, 460px); pointer-events: none; }
  #bhToastCorner { position: fixed; bottom: 20px; right: 20px; z-index: 2000; width: min(92vw, 360px); pointer-events: none; }
  .bh-toast      { pointer-events: auto; margin-bottom: .5rem; transition: opacity .3s ease, transform .3s ease; }
  .bh-toast.bh-out { opacity: 0; transform: translateY(-8px); }
</style>
<div id="bhToastTop"></div>
<div id="bhToastCorner"></div>
<script>
// bhToast(text, type, milliseconds, 'top' or 'corner', optionalButton)
function bhToast(text, type, ms, pos, button) {
  var host = document.getElementById(pos === 'corner' ? 'bhToastCorner' : 'bhToastTop');
  if (!host) { return; }
  var icons = { success: 'check-circle-fill', danger: 'x-octagon-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
  var box = document.createElement('div');
  box.className = 'alert alert-' + type + ' bh-toast shadow d-flex align-items-center gap-2 py-2';
  var icon = document.createElement('i');
  icon.className = 'bi bi-' + (icons[type] || icons.info);
  var span = document.createElement('span');
  span.textContent = text;                       // textContent = safe, no HTML is ever run
  box.appendChild(icon);
  box.appendChild(span);
  if (button) {
    var b = document.createElement('button');
    b.className = 'btn btn-sm btn-light ms-auto';
    b.textContent = button.label;
    b.onclick = button.run;
    box.appendChild(b);
  }
  host.appendChild(box);
  setTimeout(function () {
    box.classList.add('bh-out');
    setTimeout(function () { box.remove(); }, 300);
  }, ms);
}

document.addEventListener('DOMContentLoaded', function () {
  var msg  = <?= json_encode($msg, $flags) ?>;
  var type = <?= json_encode($type, $flags) ?>;
  if (msg === '') { return; }
  // some admin pages also print the same message inside the page: remove that copy
  document.querySelectorAll('.alert').forEach(function (a) {
    if (!a.classList.contains('bh-toast') && a.textContent.trim() === msg) { a.remove(); }
  });
  bhToast(msg, type, 2000, 'top');               // 2000 ms = 2 seconds
});
</script>
<?php
}