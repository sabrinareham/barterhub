<?php
// config.php - settings used by every page
define('SITE_NAME', 'Barter Hub');
define('BASE_URL', '/barterhub');   // the folder name inside htdocs

define('DB_HOST', 'localhost');
define('DB_NAME', 'barterhub');
define('DB_USER', 'root');   // XAMPP default
define('DB_PASS', '');       // XAMPP default is an empty password

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// ---- CSRF protection: every POST must carry the secret token of this session ----
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(403);
        die('Security check failed. Go back, reload the page and try again.');
    }
}