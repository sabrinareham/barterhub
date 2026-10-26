<?php
// includes/chat_files.php - everything about files attached to chat messages.

const CHAT_MAX_BYTES = 10 * 1024 * 1024;   // 10 MB per file

// The ONLY file types we accept: extension => what the browser is told the file is.
// (We never trust the name or type the user's browser sends, and we never accept .php, .exe ...)
function chat_allowed_types() {
    return [
        // text and documents
        'txt' => 'text/plain', 'csv' => 'text/csv', 'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
        // pictures
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        // audio
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4',
        // video
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
    ];
}

// 'image', 'audio', 'video' or 'file'
function chat_file_kind($mime) {
    foreach (['image', 'audio', 'video'] as $kind) {
        if (strpos((string)$mime, $kind . '/') === 0) { return $kind; }
    }
    return 'file';
}

function chat_human_size($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) { return round($bytes / 1048576, 1) . ' MB'; }
    if ($bytes >= 1024)    { return round($bytes / 1024) . ' KB'; }
    return $bytes . ' B';
}

function chat_dir() {
    return __DIR__ . '/../uploads/chat';
}

// Check and store ONE uploaded file ($_FILES['file']).
// Returns ['error' => 'text']  or  ['path' => ..., 'name' => ..., 'type' => ..., 'size' => ...]
function chat_save_upload($f) {
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'That file is too big. The limit is 10 MB.'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'The file could not be uploaded. Please try again.'];
    }
    if ($f['size'] > CHAT_MAX_BYTES) {
        return ['error' => 'That file is too big. The limit is 10 MB.'];
    }

    $original = basename((string)$f['name']);
    $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed  = chat_allowed_types();
    if (!isset($allowed[$ext])) {
        return ['error' => 'This file type is not allowed. You can send text, PDF, Office files, zip, pictures, audio and video.'];
    }
    if (chat_file_kind($allowed[$ext]) === 'image' && @getimagesize($f['tmp_name']) === false) {
        return ['error' => 'That picture file is damaged or is not a real picture.'];
    }

    // folder + a rule that blocks direct links: files can only be opened through chat-file.php
    $dir = chat_dir();
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (!is_file($dir . '/.htaccess')) { @file_put_contents($dir . '/.htaccess', "Require all denied\n"); }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;      // random name, nobody can guess it
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) {
        return ['error' => 'The file could not be saved on the server.'];
    }

    $clean = preg_replace('/[^\p{L}\p{N}\.\-_ ()]/u', '_', $original);   // a harmless name to show on screen
    return [
        'path' => $stored,
        'name' => mb_substr($clean, 0, 100),
        'type' => $allowed[$ext],
        'size' => (int)$f['size'],
    ];
}

// HTML for the attachment of one message row ($m needs: id, file_path, file_name, file_type, file_size)
function render_attachment($m) {
    if (empty($m['file_path'])) { return ''; }
    $url  = BASE_URL . '/user/chat-file.php?id=' . (int)$m['id'];
    $name = e($m['file_name']);
    $kind = chat_file_kind($m['file_type']);
    $info = '<div class="small mt-1">' . $name . ' &middot; ' . e(chat_human_size($m['file_size']))
          . ' &middot; <a class="text-reset" href="' . $url . '&amp;download=1"><i class="bi bi-download"></i> Download</a></div>';

    if ($kind === 'image') {
        return '<a href="' . $url . '" target="_blank"><img src="' . $url . '" alt="' . $name . '" class="img-fluid rounded" style="max-height:220px"></a>' . $info;
    }
    if ($kind === 'audio') {
        return '<audio controls preload="metadata" style="max-width:100%" src="' . $url . '"></audio>' . $info;
    }
    if ($kind === 'video') {
        return '<video controls preload="metadata" style="max-width:100%;max-height:260px" src="' . $url . '"></video>' . $info;
    }
    return '<a class="text-reset text-decoration-none d-flex align-items-center gap-2" href="' . $url . '&amp;download=1">'
         . '<i class="bi bi-file-earmark-text fs-3"></i><span>' . $name . '<br><span class="small">'
         . e(chat_human_size($m['file_size'])) . ' &middot; click to download</span></span></a>';
}