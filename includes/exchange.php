<?php
// includes/exchange.php - helpers for exchange requests (used by several pages)

// Is this item already promised in an ACCEPTED exchange (as the wanted item OR the offered item)?
function item_reserved($pdo, $itemId) {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM exchange_requests
          WHERE status = 'accepted' AND (item_id = ? OR offered_item_id = ?)
          LIMIT 1"
    );
    $stmt->execute([$itemId, $itemId]);
    return (bool)$stmt->fetchColumn();
}

// Accept a request.
//   $mode 'owner'   : the OWNER accepts a PENDING request
//   $mode 'counter' : the REQUESTER accepts the owner's COUNTER-OFFER
// Returns [true or false, 'message for the user'].
function accept_exchange($pdo, $id, $userId, $mode) {
    if ($mode === 'counter') { $who = 'requester_id'; $needStatus = 'countered'; }
    else                     { $who = 'owner_id';     $needStatus = 'pending'; }

    // $who is chosen by us above (never from the user), so it is safe inside the SQL text
    $stmt = $pdo->prepare("SELECT * FROM exchange_requests WHERE id = ? AND $who = ? AND status = ?");
    $stmt->execute([$id, $userId, $needStatus]);
    $r = $stmt->fetch();
    if (!$r) {
        return [false, 'This request can no longer be accepted.'];
    }

    $wantedId  = (int)$r['item_id'];
    $offeredId = $mode === 'counter' ? (int)$r['counter_item_id'] : (int)$r['offered_item_id'];

    // both items must still be approved and still belong to the right people
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM items
          WHERE status = 'approved' AND ((id = ? AND user_id = ?) OR (id = ? AND user_id = ?))"
    );
    $stmt->execute([$wantedId, $r['owner_id'], $offeredId, $r['requester_id']]);
    if ((int)$stmt->fetchColumn() !== 2) {
        return [false, 'One of the items is no longer available.'];
    }
    if (item_reserved($pdo, $wantedId) || item_reserved($pdo, $offeredId)) {
        return [false, 'One of the items is already promised in another exchange.'];
    }

    $pdo->beginTransaction();
    try {
        // 1. accept this one ("AND status = ..." makes a double click harmless)
        $stmt = $pdo->prepare(
            "UPDATE exchange_requests
                SET status = 'accepted', offered_item_id = ?, responded_at = CURRENT_TIMESTAMP
              WHERE id = ? AND status = ?"
        );
        $stmt->execute([$offeredId, $id, $needStatus]);
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            return [false, 'This request was already handled.'];
        }

        // 2. every OTHER open request that touches one of these two items is rejected
        $stmt = $pdo->prepare(
            "UPDATE exchange_requests SET status = 'rejected', responded_at = CURRENT_TIMESTAMP
              WHERE status IN ('pending','countered') AND id <> ?
                AND (item_id IN (?, ?) OR offered_item_id IN (?, ?) OR counter_item_id IN (?, ?))"
        );
        $stmt->execute([$id, $wantedId, $offeredId, $wantedId, $offeredId, $wantedId, $offeredId]);

        $pdo->commit();
        return [true, 'Exchange accepted. You can now chat and agree on a place to meet.'];
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, 'Something went wrong. Please try again.'];
    }
}

// One person confirms "we did the swap". When BOTH have confirmed:
// the request becomes 'completed' and both items become 'exchanged'.
function mark_completed($pdo, $id, $userId) {
    $stmt = $pdo->prepare(
        "SELECT * FROM exchange_requests
          WHERE id = ? AND status = 'accepted' AND (requester_id = ? OR owner_id = ?)"
    );
    $stmt->execute([$id, $userId, $userId]);
    $r = $stmt->fetch();
    if (!$r) {
        return [false, 'This exchange cannot be completed.'];
    }

    $col = ((int)$r['requester_id'] === (int)$userId) ? 'requester_done' : 'owner_done';   // our own choice, safe

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE exchange_requests SET $col = 1 WHERE id = ? AND status = 'accepted'")->execute([$id]);

        $stmt = $pdo->prepare('SELECT requester_done, owner_done FROM exchange_requests WHERE id = ?');
        $stmt->execute([$id]);
        $flags = $stmt->fetch();

        if ((int)$flags['requester_done'] === 1 && (int)$flags['owner_done'] === 1) {
            $pdo->prepare("UPDATE exchange_requests SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'accepted'")
                ->execute([$id]);
            $pdo->prepare("UPDATE items SET status = 'exchanged' WHERE id IN (?, ?) AND status = 'approved'")
                ->execute([$r['item_id'], $r['offered_item_id']]);
            $pdo->commit();
            return [true, 'Exchange completed! Both items are marked as exchanged. Please rate each other.'];
        }
        $pdo->commit();
        return [true, 'Saved. Waiting for the other person to confirm too.'];
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, 'Something went wrong. Please try again.'];
    }
}