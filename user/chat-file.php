<?php
// user/chat-file.php?id=MESSAGE_ID - gives out ONE chat attachment, but only to the two people of that
// exchange and to the admin. The files themselves sit in uploads/chat/ where nobody can open them directly.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat_files.php';
require_login();

$userId  = (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
session_write_close();

$stmt = $pdo->prepare(
    'SELECT m.file_path, m.file_name, m.file_type, r.requester_id, r.owner_id
       FROM messages m JOIN exchange_requests r ON r.id = m.request_id
      WHERE m.id = ? AND m.file_path IS NOT NULL'
);
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$f = $stmt->fetch();

if (!$f || (!$isAdmin && (int)$f['requester_id'] !== $userId && (int)$f['owner_id'] !== $userId)) {
    http_response_code(404);
    die('File not found.');
}

$path = chat_dir() . '/' . basename($f['file_path']);
if (!is_file($path)) {
    http_response_code(404);
    die('File not found.');
}

$size  = filesize($path);
$start = 0;
$end   = $size - 1;
$mime  = $f['file_type'];

// pictures, audio, video, pdf and plain text open in the browser; everything else downloads
$inlineTypes = ['application/pdf', 'text/plain'];
$inline = !isset($_GET['download']) && (chat_file_kind($mime) !== 'file' || in_array($mime, $inlineTypes, true));

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: ' . $mime . ($mime === 'text/plain' ? '; charset=utf-8' : ''));
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
     . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $f['file_name']) . '"; filename*=UTF-8\'\'' . rawurlencode($f['file_name']));
header('Cache-Control: private, max-age=3600');

// video and audio players ask for pieces of the file ("Range"), so they can jump around
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') { $start = (int)$m[1]; }
    if ($m[2] !== '') { $end = min((int)$m[2], $size - 1); }
    if ($m[1] === '' && $m[2] !== '') { $start = max(0, $size - (int)$m[2]); $end = $size - 1; }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . ($end - $start + 1));

$fh = fopen($path, 'rb');
fseek($fh, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fh)) {
    $chunk = fread($fh, min(8192, $left));
    echo $chunk;
    $left -= strlen($chunk);
}
fclose($fh);