<?php
// includes/csrf_script.php - adds the hidden security token to EVERY post form on the page.
// Included in <head> by header.php and admin_header.php. The server (config.php) checks it.
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrfToken = <?= json_encode($_SESSION['csrf'] ?? '') ?>;
  document.querySelectorAll('form').forEach(function (form) {
    if ((form.getAttribute('method') || '').toLowerCase() === 'post') {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'csrf_token';
      input.value = csrfToken;
      form.appendChild(input);
    }
  });
});
</script>