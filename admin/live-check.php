<?php
// admin/live-check.php - the admin pages ask this file every few seconds: "anything new?"
// It answers in JSON (just numbers), never in HTML.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notify.php';
require_admin();

$adminId = (int)$_SESSION['user_id'];
session_write_close();                 // free the session so this polling never slows down the other pages

$page = basename((string)($_GET['page'] ?? ''));   // e.g. "users.php"

$data = admin_notify_counts($pdo, $adminId, $page);
$data['stamp'] = admin_notify_stamp($pdo);

header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode($data);