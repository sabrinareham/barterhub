<?php
// includes/notify.php - the numbers for the red badges in the admin sidebar.
//
// Every badge works like WhatsApp: it counts what is NEW since the admin last opened that page,
// and goes back to zero as soon as the admin opens the page.
// (The 'open' kind - "keep counting until handled" - is still supported by the code below but no page uses it.)
// For 'since' badges we remember, per admin and per page, the last row number the admin has seen (table admin_seen).

// page file name => [kind, table to look at, extra condition, [singular text, plural text]]
function admin_sections() {
    return [
        'users.php'       => ['since', 'users',             "role <> 'admin'",   ['new user joined', 'new users joined']],
        'submissions.php' => ['since', 'items',             "status = 'pending'", ['new item to approve', 'new items to approve']],
        'exchanges.php'   => ['since', 'exchange_requests', '1 = 1',            ['new exchange request', 'new exchange requests']],
        'chats.php'       => ['since', 'messages',          '1 = 1',            ['new chat message', 'new chat messages']],
        'searches.php'    => ['since', 'search_log',        '1 = 1',            ['new search', 'new searches']],
        'complaints.php'  => ['since', 'reports',           '1 = 1',            ['new report', 'new reports']],
    ];
}

// the words used in the pop-up: ['users.php' => ['new user joined', 'new users joined'], ...]
function admin_notify_labels() {
    $out = [];
    foreach (admin_sections() as $page => $s) { $out[$page] = $s[3]; }
    return $out;
}

// Returns ['counts' => [page => number], 'fresh' => [page => number]]
//   counts = what the badges show
//   fresh  = for the page the admin is looking at right now: how many new things arrived (then we mark it as seen)
//
// "Seen" is remembered as a row NUMBER (the highest id the admin has already seen), not as a time.
// A row number never has clock or same-second problems.
function admin_notify_counts($pdo, $adminId, $currentPage = '') {
    $sections = admin_sections();

    $seen = $pdo->prepare('SELECT section, last_id FROM admin_seen WHERE admin_id = ?');
    $seen->execute([$adminId]);
    $lastSeen = $seen->fetchAll(PDO::FETCH_KEY_PAIR);

    $counts = [];
    $fresh  = [];
    foreach ($sections as $page => $s) {
        [$kind, $table, $where] = $s;

        // the newest row that exists right now (the table name comes from our own list above, never from the user)
        $max = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM $table")->fetchColumn();

        if ($kind === 'open') {                                   // waiting items: count what is still open
            $counts[$page] = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE $where")->fetchColumn();
            continue;
        }

        if (!isset($lastSeen[$page])) {                           // first time ever: everything that exists now counts as seen
            try {
                $pdo->prepare('INSERT INTO admin_seen (admin_id, section, last_id) VALUES (?, ?, ?)')->execute([$adminId, $page, $max]);
            } catch (PDOException $ex) { /* two requests at once: the other one inserted it, fine */ }
            $lastSeen[$page] = $max;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $where AND id > ? AND id <= ?");
        $stmt->execute([(int)$lastSeen[$page], $max]);
        $n = (int)$stmt->fetchColumn();

        if ($page === $currentPage) {                             // the admin is looking at this page right now
            $fresh[$page] = $n;
            $n = 0;
            $pdo->prepare('UPDATE admin_seen SET last_id = ? WHERE admin_id = ? AND section = ?')->execute([$max, $adminId, $page]);
        }
        $counts[$page] = $n;
    }
    return ['counts' => $counts, 'fresh' => $fresh];
}

// One short text that changes whenever ANYTHING important changes.
// Read-only admin pages (dashboard, activity ...) reload themselves when this text changes.
function admin_notify_stamp($pdo) {
    $row = $pdo->query(
        "SELECT (SELECT COUNT(*) FROM users) a,
                (SELECT COUNT(*) FROM items) b,
                (SELECT COUNT(*) FROM items WHERE status = 'approved') c,
                (SELECT COUNT(*) FROM items WHERE status = 'pending') d,
                (SELECT COUNT(*) FROM exchange_requests) e,
                (SELECT COUNT(*) FROM exchange_requests WHERE status IN ('accepted','completed')) f,
                (SELECT COUNT(*) FROM messages) g,
                (SELECT COUNT(*) FROM ratings) h,
                (SELECT COUNT(*) FROM reports) i,
                (SELECT COUNT(*) FROM reports WHERE status = 'open') j,
                (SELECT COUNT(*) FROM search_log) k"
    )->fetch(PDO::FETCH_NUM);
    return implode('-', $row);
}