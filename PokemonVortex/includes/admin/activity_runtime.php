<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED')) { http_response_code(404); exit; }
require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/system_runtime.php';

/** One check per autonomous batch also gates CLI workers during maintenance. */
function pv_admin_activity_server_open(mysqli $db): bool
{
    return !pv_admin_runtime_ready($db) || empty(pv_admin_maintenance_state($db)['active']);
}

/** Bounded background-work gates; this file never loads the console dispatcher. */
function pv_admin_activity_join(bool $stateReady): string
{
    return $stateReady ? ' LEFT JOIN console_player_state adm ON adm.user_id=m.id ' : ' ';
}

/** Fixed internal aliases only. Expired bans remain eligible for selected-account cleanup. */
function pv_admin_activity_where(bool $stateReady, int $now, bool $visible = false): string
{
    if (!$stateReady) return "COALESCE(m.banned,'0')<>'1'";
    $now = max(0, $now);
    $ban = 'COALESCE(adm.ban_until,0)';
    $jail = 'COALESCE(adm.jail_until,0)';
    return 'COALESCE(adm.locked,0)=0 AND COALESCE(adm.frozen,0)=0 '
        . 'AND ('.$ban.'=0 OR ('.$ban.'>0 AND '.$ban.'<='.$now.')) '
        . "AND (COALESCE(m.banned,'0')<>'1' OR (".$ban.'>0 AND '.$ban.'<='.$now.')) '
        . 'AND ('.$jail.'=0 OR ('.$jail.'>0 AND '.$jail.'<='.$now.')) '
        . ($visible ? 'AND COALESCE(adm.invisible,0)=0 ' : '');
}

function pv_admin_activity_state_allowed(array $row, int $now, bool $visible = false): bool
{
    $ban = (int)($row['ban_until'] ?? 0);
    $jail = (int)($row['jail_until'] ?? 0);
    $expiredBan = $ban > 0 && $ban <= $now;
    return empty($row['locked']) && empty($row['frozen'])
        && ($ban === 0 || $expiredBan)
        && ((string)($row['banned'] ?? '0') !== '1' || $expiredBan)
        && ($jail === 0 || ($jail > 0 && $jail <= $now))
        && (!$visible || empty($row['invisible']));
}

/** Call while holding the account lock, so reset/delete/moderation cannot interleave. */
function pv_admin_activity_allowed(mysqli $db, int $uid, bool $visible = false): bool
{
    if ($uid < 1) return false;
    $ready = pv_admin_runtime_ready($db);
    $columns = $ready ? ',adm.locked,adm.frozen,adm.ban_until,adm.jail_until,adm.invisible' : '';
    $stmt = pv_admin_runtime_query($db, 'SELECT m.banned'.$columns.' FROM members m'
        .pv_admin_activity_join($ready).'WHERE m.id=?', [$uid]);
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) return false;
    $now = time();
    if ($ready) {
        $expiredBan = (int)($row['ban_until'] ?? 0) > 0 && (int)$row['ban_until'] <= $now;
        $row = pv_admin_expire_restrictions($db, $uid, $row + ['ban_until'=>0,'jail_until'=>0]);
        if ($expiredBan) $row['banned'] = '0';
    }
    return pv_admin_activity_state_allowed($row, $now, $visible);
}

/** Background work skips busy accounts instead of delaying the current web request. */
function pv_admin_activity_lock(mysqli $db, int $uid): ?string
{
    if ($uid < 1) return null;
    $name = pv_admin_request_lock_name($uid);
    $stmt = pv_admin_runtime_query($db, 'SELECT GET_LOCK(?,0) AS acquired', [$name]);
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return (int)($row['acquired'] ?? 0) === 1 ? $name : null;
}

function pv_admin_activity_unlock(mysqli $db, ?string $name): void
{
    if ($name === null) return;
    $stmt = pv_admin_runtime_query($db, 'SELECT RELEASE_LOCK(?)', [$name]); $stmt->close();
}
