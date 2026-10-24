<?php
// includes/matching.php - suggests items that fit what a user wants

// "Brown wooden Study Table (new)" -> ['brown', 'wooden', 'study', 'table']
function match_words($text) {
    $stop  = ['the', 'and', 'for', 'with', 'any', 'new', 'old', 'good', 'your', 'you', 'one', 'set',
              'pcs', 'size', 'free', 'want', 'wants', 'need', 'some', 'that', 'this', 'from', 'like'];
    $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string)$text), -1, PREG_SPLIT_NO_EMPTY);
    $words = [];
    foreach ($parts as $p) {
        if (mb_strlen($p) >= 3 && !in_array($p, $stop, true)) { $words[$p] = true; }
    }
    return array_keys($words);
}

// do the two word lists share a word? ("chair" and "chairs" count as the same)
function words_overlap($a, $b) {
    foreach ($a as $x) {
        foreach ($b as $y) {
            if ($x === $y || rtrim($x, 's') === rtrim($y, 's')) { return true; }
        }
    }
    return false;
}

// Returns a list like:
//   [ ['mine' => <my item row>, 'matches' => [ ['item' => <their item row>, 'mutual' => true/false], ... ]], ... ]
// A match means: THEIR item fits what MY item wants. It is "mutual" when MY item also fits what THEY want.
function find_matches($pdo, $userId) {
    $free = "NOT EXISTS (SELECT 1 FROM exchange_requests r
                          WHERE r.status = 'accepted' AND (r.item_id = i.id OR r.offered_item_id = i.id))";

    $stmt = $pdo->prepare(
        "SELECT i.id, i.title, i.wants, c.name AS category_name
           FROM items i JOIN categories c ON c.id = i.category_id
          WHERE i.user_id = ? AND i.status = 'approved' AND $free
          ORDER BY i.created_at DESC"
    );
    $stmt->execute([$userId]);
    $mine = $stmt->fetchAll();
    if (count($mine) === 0) { return []; }

    $stmt = $pdo->prepare(
        "SELECT i.id, i.title, i.wants, i.photo, i.item_condition, u.city, c.name AS category_name
           FROM items i
           JOIN users u      ON u.id = i.user_id
           JOIN categories c ON c.id = i.category_id
          WHERE i.user_id <> ? AND i.status = 'approved' AND c.is_visible = 1 AND u.status = 'active' AND $free
          ORDER BY i.created_at DESC LIMIT 500"
    );
    $stmt->execute([$userId]);
    $others = $stmt->fetchAll();

    // split every text into words ONCE
    foreach ($others as $k => $o) {
        $others[$k]['has_words']  = match_words($o['title'] . ' ' . $o['category_name']);   // what they HAVE
        $others[$k]['want_words'] = match_words($o['wants']);                               // what they WANT
    }

    $result = [];
    foreach ($mine as $m) {
        $iWant = match_words($m['wants']);
        $iHave = match_words($m['title'] . ' ' . $m['category_name']);
        $found = [];
        foreach ($others as $o) {
            if (words_overlap($iWant, $o['has_words'])) {                    // they have what I want
                $found[] = ['item' => $o, 'mutual' => words_overlap($iHave, $o['want_words'])];
            }
        }
        usort($found, function ($a, $b) { return (int)$b['mutual'] <=> (int)$a['mutual']; });   // mutual first
        if (count($found) > 0) { $result[] = ['mine' => $m, 'matches' => $found]; }
    }
    return $result;
}