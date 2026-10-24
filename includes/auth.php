<?php
// includes/auth.php - who is logged in? (uses the session started in config.php)

// true when a user has logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Remember the user in the session after a correct password
function login_user($user) {
    global $pdo;
    session_regenerate_id(true);                 // new session id: blocks "session fixation" attacks
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];

    if (isset($pdo)) {                           // remember WHEN they logged in (for the admin dashboard)
        $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int)$user['id']]);
    }
}

// Forget the user (flash messages can still be used afterwards)
function logout_user() {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);
    session_regenerate_id(true);
}

// Where should this user land? Admins and normal users have different homes.
function account_home_url() {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        return BASE_URL . '/admin/dashboard.php';
    }
    return BASE_URL . '/user/dashboard.php';
}

// GUARD 1: only logged-in users may continue.
// It also asks the DATABASE every time, so a user the admin has just blocked
// (or whose role was changed) is stopped on their very next click.
function require_login() {
    global $pdo;                                       // the database connection from db.php
    if (!isset($pdo)) {                                // a page that forgot to load it: load it here
        require_once __DIR__ . '/db.php';
    }

    if (!is_logged_in()) {
        set_flash('warning', 'Please log in first.');
        redirect(BASE_URL . '/auth/login.php');
    }

    $stmt = $pdo->prepare('SELECT role, status FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || $row['status'] === 'blocked') {       // deleted or blocked account
        logout_user();
        set_flash('danger', 'Your account is not available. Please contact the admin.');
        redirect(BASE_URL . '/auth/login.php');
    }
    $_SESSION['user_role'] = $row['role'];             // always use the role stored in the database
}

// GUARD 2: only admins may continue
function require_admin() {
    require_login();                                   // must be logged in first
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);                       // 403 = "forbidden"
        die('403 - This area is for admins only.');
    }
}