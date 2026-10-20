<?php
// auth/logout.php - log the user out and go home
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
set_flash('success', 'You have been logged out.');
redirect(BASE_URL . '/index.php');