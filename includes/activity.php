<?php
// includes/activity.php - one feed of "what happened", built from the tables we already have

function recent_activity($pdo, $limit = 50) {
    $limit = (int)$limit;
    $sql = "
      SELECT created_at AS happened_at, 'user' AS kind, full_name AS a, '' AS b, '' AS c FROM users
      UNION ALL
      SELECT i.created_at, 'item', u.full_name, i.title, '' FROM items i JOIN users u ON u.id = i.user_id
      UNION ALL
      SELECT r.created_at, 'request', ru.full_name, ou.full_name, w.title
        FROM exchange_requests r JOIN users ru ON ru.id = r.requester_id JOIN users ou ON ou.id = r.owner_id JOIN items w ON w.id = r.item_id
      UNION ALL
      SELECT r.responded_at, 'response', ru.full_name, ou.full_name, r.status
        FROM exchange_requests r JOIN users ru ON ru.id = r.requester_id JOIN users ou ON ou.id = r.owner_id
       WHERE r.responded_at IS NOT NULL
      UNION ALL
      SELECT r.completed_at, 'completed', ru.full_name, ou.full_name, ''
        FROM exchange_requests r JOIN users ru ON ru.id = r.requester_id JOIN users ou ON ou.id = r.owner_id
       WHERE r.completed_at IS NOT NULL
      UNION ALL
      SELECT rt.created_at, 'rating', u1.full_name, u2.full_name, rt.stars
        FROM ratings rt JOIN users u1 ON u1.id = rt.rater_id JOIN users u2 ON u2.id = rt.rated_id
      UNION ALL
      SELECT rp.created_at, 'report', u.full_name, i.title, rp.reason
        FROM reports rp JOIN users u ON u.id = rp.reporter_id JOIN items i ON i.id = rp.item_id
      ORDER BY happened_at DESC
      LIMIT $limit";
    return $pdo->query($sql)->fetchAll();
}

// turns one feed row into [icon name, safe HTML text]
function activity_line($row) {
    $a = '<strong>' . e($row['a']) . '</strong>';
    $b = e($row['b']);
    $c = e($row['c']);
    switch ($row['kind']) {
        case 'user':      return ['person-plus',   "$a joined Barter Hub"];
        case 'item':      return ['plus-circle',   "$a added the item \"$b\""];
        case 'request':   return ['send',          "$a sent a request to <strong>$b</strong> for \"$c\""];
        case 'response':  return ['arrow-repeat',  "The request from $a to <strong>$b</strong> is now <em>$c</em>"];
        case 'completed': return ['check-circle',  "$a and <strong>$b</strong> completed an exchange"];
        case 'rating':    return ['star',          "$a rated <strong>$b</strong> $c stars"];
        case 'report':    return ['flag',          "$a reported the item \"$b\" ($c)"];
    }
    return ['dot', $a];
}