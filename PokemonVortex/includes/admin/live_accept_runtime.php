<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED')) { http_response_code(404); exit; }
require_once __DIR__.'/runtime.php';

/** Called inside the existing acceptance transaction, before team reads.
 * The request already holds its own trainer lock. A zero-wait counterparty lock
 * avoids two simultaneous acceptance requests waiting on each other's lock.
 * Hold both locks until request shutdown, matching the normal web guard.
 */
function pv_admin_live_accept_guard(mysqli $db, int $actorId, int $challengerId): void
{
    if ($actorId <= 0 || $challengerId <= 0 || $actorId === $challengerId) {
        throw new RuntimeException('A live battle requires two distinct trainers.');
    }
    if (!pv_admin_runtime_ready($db)) return; // Compatible with unmigrated installs.
    pv_admin_runtime_lock($db, $actorId);
    static $held = [];
    $lock = pv_admin_request_lock_name($challengerId);
    if (!isset($held[$lock])) {
        $stmt = pv_admin_runtime_query($db, 'SELECT GET_LOCK(?,0) AS acquired', [$lock]);
        $acquired = (int)($stmt->get_result()->fetch_assoc()['acquired'] ?? 0) === 1;
        $stmt->close();
        if (!$acquired) throw new RuntimeException('The challenger is completing another action or an administrator update. Retry accepting in a moment.');
        $held[$lock] = true;
        register_shutdown_function(static function() use ($db,$lock): void {
            try { $stmt=pv_admin_runtime_query($db,'SELECT RELEASE_LOCK(?)',[$lock]); $stmt->close(); }
            catch (Throwable $e) { /* Disconnect releases the advisory lock. */ }
        });
    }
    // Locking reads deliberately avoid stale REPEATABLE READ snapshots after
    // waiting for a console mutation, including deletion and active-team edits.
    $stmt = pv_admin_runtime_query($db, 'SELECT * FROM members WHERE id IN (?,?) ORDER BY id FOR UPDATE', [$actorId,$challengerId]);
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    if (count($players) !== 2) throw new RuntimeException('A participant account is no longer available.');
    foreach ($players as $player) {
        $id=(int)$player['id']; $state=pv_admin_player_state($id,true);
        $expiredBan=(int)$state['ban_until']>0 && (int)$state['ban_until']<=time();
        $state=pv_admin_expire_restrictions($db,$id,$state);
        if ($expiredBan) $player['banned']='0';
        if ((int)$state['locked'] || (int)$state['frozen'] || (int)$state['jail_until']!==0
            || (int)$state['ban_until']!==0 || (string)($player['banned']??'0')==='1') {
            throw new RuntimeException('A participant is currently restricted and cannot begin a live battle.');
        }
    }
    $stmt=pv_admin_runtime_query($db,
        'SELECT id FROM live_battle WHERE (uid_1 IN (?,?) OR uid_2 IN (?,?)) AND (settled_1=0 OR settled_2=0) LIMIT 1 FOR UPDATE',
        [$actorId,$challengerId,$actorId,$challengerId]);
    $active=$stmt->get_result()->fetch_row(); $stmt->close();
    if ($active) throw new RuntimeException('A participant already has an unsettled live battle. Finish that match before accepting another.');
}
