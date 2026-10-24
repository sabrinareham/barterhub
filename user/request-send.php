<?php
// user/request-send.php - receives the "Request exchange" form from item.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exchange.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {          // this page is only for the form
    redirect(BASE_URL . '/browse.php');
}

$userId    = (int)$_SESSION['user_id'];
$itemId    = (int)($_POST['item_id'] ?? 0);           // the item I want
$offeredId = (int)($_POST['offered_item_id'] ?? 0);   // my item that I offer
$message   = trim($_POST['message'] ?? '');
$back      = BASE_URL . '/item.php?id=' . $itemId;

// small helper: show a red message and go back to the item page
function fail($text, $to) {
    set_flash('danger', $text);
    redirect($to);
}

if (mb_strlen($message) > 255) {
    fail('Your message is too long (255 characters at most).', $back);
}

// 1. the wanted item must be public (approved) and not mine
$stmt = $pdo->prepare("SELECT id, user_id FROM items WHERE id = ? AND status = 'approved'");
$stmt->execute([$itemId]);
$wanted = $stmt->fetch();
if (!$wanted) {
    fail('That item is not available.', BASE_URL . '/browse.php');
}
if ((int)$wanted['user_id'] === $userId) {
    fail('You cannot request your own item.', $back);
}

// 2. the item I offer must be MINE and approved
$stmt = $pdo->prepare("SELECT id FROM items WHERE id = ? AND user_id = ? AND status = 'approved'");
$stmt->execute([$offeredId, $userId]);
if (!$stmt->fetch()) {
    fail('Please choose one of your own approved items to offer.', $back);
}

// 3. neither item may be inside an accepted exchange already
if (item_reserved($pdo, $itemId) || item_reserved($pdo, $offeredId)) {
    fail('One of these items is already promised in another exchange.', $back);
}

// 4. only one pending request from me for this item
$stmt = $pdo->prepare("SELECT 1 FROM exchange_requests WHERE requester_id = ? AND item_id = ? AND status = 'pending'");
$stmt->execute([$userId, $itemId]);
if ($stmt->fetchColumn()) {
    fail('You already sent a request for this item.', $back);
}

// 5. save
$stmt = $pdo->prepare(
    'INSERT INTO exchange_requests (requester_id, owner_id, item_id, offered_item_id, message)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$userId, (int)$wanted['user_id'], $itemId, $offeredId, $message === '' ? null : $message]);

set_flash('success', 'Request sent. The owner will accept or reject it.');
redirect(BASE_URL . '/user/requests.php?tab=sent');