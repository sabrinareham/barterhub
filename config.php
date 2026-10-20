<?php
// config.php - settings used by every page
define('SITE_NAME', 'Barter Hub');
define('BASE_URL', '/barterhub');   // the folder name inside htdocs

define('DB_HOST', 'localhost');
define('DB_NAME', 'barterhub');
define('DB_USER', 'root');   // XAMPP default
define('DB_PASS', '');       // XAMPP default is an empty password

session_start();             // lets us remember things between page loads