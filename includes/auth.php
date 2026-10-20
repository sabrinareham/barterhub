<?php
// includes/auth.php - who is logged in? (uses the session started in config.php)

// true when a user has logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Remember the user in the session after a correct password
function login_user($user) {
    session_regenerate_id(true);                 // new session id: blocks "session fixation" attacks
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
}

// Forget the user (flash messages can still be used afterwards)
function logout_user() {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);
    session_regenerate_id(true);
}

// Where should this user land? Admins and normal users have different homes.
// (those pages are still .html - we convert them in the next lessons)
function account_home_url() {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        return BASE_URL . '/admin/dashboard.html';
    }
    return BASE_URL . '/user/dashboard.html';
}