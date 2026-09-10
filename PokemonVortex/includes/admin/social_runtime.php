<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED')) { http_response_code(404); exit; }

/** Serialize pair-level social rules with transfers, without exposing any console handler. */
function pv_console_lock_social_pair(mysqli $db, int $firstId, int $secondId): void
{
    if ($firstId <= 0 || $secondId <= 0 || $firstId === $secondId) {
        throw new RuntimeException('Choose two different existing trainers.');
    }
    $low = min($firstId, $secondId);
    $high = max($firstId, $secondId);
    $stmt = $db->prepare('SELECT id FROM members WHERE id IN (?,?) ORDER BY id FOR UPDATE');
    if (!$stmt) throw new RuntimeException('Trainer permissions could not be checked.');
    $stmt->bind_param('ii', $low, $high);
    $stmt->execute();
    $count = $stmt->get_result()->num_rows;
    $stmt->close();
    if ($count !== 2) throw new RuntimeException('One of these trainer accounts no longer exists.');
}

/** Must run inside the same transaction as offer creation or acceptance. */
function pv_console_assert_trade_allowed(mysqli $db, int $firstId, int $secondId): void
{
    pv_console_lock_social_pair($db, $firstId, $secondId);
    $stmt = $db->prepare('SELECT user_id FROM console_trade_blocks WHERE (user_id=? AND target_id=?) OR (user_id=? AND target_id=?) LIMIT 1');
    if (!$stmt) throw new RuntimeException('Trade permissions are unavailable. Run the server database repair.');
    $stmt->bind_param('iiii', $firstId, $secondId, $secondId, $firstId);
    $stmt->execute();
    $blocked = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if ($blocked) throw new RuntimeException('Trading is blocked between these trainers. Pending offers can still be withdrawn or declined.');
}
