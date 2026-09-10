<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED')) { http_response_code(404); exit; }

/** Web-safe enforcement only. No console parser, dispatcher, or command handlers. */
function pv_admin_runtime_query(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('Account administration storage is unavailable.');
    if ($params) {
        $types = '';
        foreach ($params as $value) $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Account administration storage could not be updated.'); }
    return $stmt;
}

function pv_admin_runtime_ready(mysqli $db): bool
{
    static $ready = [];
    $key = spl_object_id($db);
    if (array_key_exists($key, $ready)) return $ready[$key];
    $stmt = pv_admin_runtime_query($db, "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='console_player_state'");
    $ready[$key] = (bool)$stmt->get_result()->fetch_row(); $stmt->close();
    return $ready[$key];
}

/** Shared helpers intentionally expose data operations, never a web command transport. */
function pv_admin_player_state(int $uid, bool $lock = false): array
{
    if ($uid < 1) throw new InvalidArgumentException('A valid trainer ID is required.');
    $db = pv_db();
    $stmt = pv_admin_runtime_query($db, 'INSERT IGNORE INTO console_player_state (user_id) SELECT id FROM members WHERE id=?', [$uid]); $stmt->close();
    $stmt = pv_admin_runtime_query($db, 'SELECT * FROM console_player_state WHERE user_id=?' . ($lock ? ' FOR UPDATE' : ''), [$uid]);
    $state = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$state) throw new RuntimeException('The trainer no longer exists.');
    return $state;
}

function pv_admin_state_update(int $uid, array $changes): void
{
    $fields = ['rank_name','locked','frozen','jail_until','ban_until','session_epoch','afk','invisible',
        'play_seconds','last_seen','title','pose','data_revision','location_revision','location_json','heal_revision'];
    if (!$changes) return;
    foreach (array_keys($changes) as $field) {
        if (!in_array($field, $fields, true)) throw new InvalidArgumentException('Unknown player-state field.');
    }
    pv_admin_player_state($uid);
    $sql = 'UPDATE console_player_state SET ' . implode(',', array_map(static fn($field) => '`'.$field.'`=?', array_keys($changes))) . ' WHERE user_id=?';
    $stmt = pv_admin_runtime_query(pv_db(), $sql, [...array_values($changes), $uid]); $stmt->close();
}

function pv_admin_request_lock_name(int $uid): string
{
    return 'pv-admin-user:' . substr(hash('sha256', (string)pv_config('db.name', 'pokemon_vortex')), 0, 20) . ':' . $uid;
}

/** Share a name reservation with browser signup, retaining legacy duplicate-name repair by ID. */
function pv_admin_account_name_lock(mysqli $db, string $username): string
{
    if (!preg_match('/^[A-Za-z0-9_-]{3,24}$/D', $username)) throw new InvalidArgumentException('Invalid trainer name.');
    $key = 'pv-account-name:' . substr(hash('sha256', (string)pv_config('db.name', 'pokemon_vortex') . ':' . strtolower($username)), 0, 40);
    $stmt = pv_admin_runtime_query($db, 'SELECT GET_LOCK(?,5) AS acquired', [$key]);
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ((int)($row['acquired'] ?? 0) !== 1) throw new RuntimeException('This trainer name is being registered. Please retry shortly.');
    $GLOBALS['pv_account_name_locks'][$key] = true;
    register_shutdown_function(static function () use ($db, $key): void {
        pv_admin_account_name_unlock($db, $key);
    });
    return $key;
}

function pv_admin_account_name_unlock(mysqli $db, string $key): void
{
    if (empty($GLOBALS['pv_account_name_locks'][$key])) return;
    unset($GLOBALS['pv_account_name_locks'][$key]);
    try { $stmt = pv_admin_runtime_query($db, 'SELECT RELEASE_LOCK(?)', [$key]); $stmt->close(); } catch (Throwable $e) {}
}

function pv_admin_runtime_lock(mysqli $db, int $uid): void
{
    static $held = [];
    $name = pv_admin_request_lock_name($uid);
    if (isset($held[$name])) return;
    $stmt = pv_admin_runtime_query($db, 'SELECT GET_LOCK(?,5) AS acquired', [$name]);
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ((int)($row['acquired'] ?? 0) !== 1) pv_admin_runtime_deny('Your account is being updated. Please retry in a moment.', 409);
    $held[$name] = true;
    register_shutdown_function(static function () use ($db, $name): void {
        try { $stmt = pv_admin_runtime_query($db, 'SELECT RELEASE_LOCK(?)', [$name]); $stmt->close(); } catch (Throwable $e) {}
    });
}

function pv_admin_runtime_deny(string $message, int $status = 403): never
{
    if (!headers_sent()) { http_response_code($status); header('Cache-Control: no-store'); }
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $json = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || in_array($script, ['map_move.php','map_presence.php','world_map_move.php','world_map_presence.php','live_battle_state.php','ai_service.php','trade_notifications.php'], true);
    if ($json) {
        if (!headers_sent()) header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok'=>false,'error'=>$message,'restricted'=>true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Server status · Pokemon Vortex NXT</title><style>body{font:16px/1.6 system-ui,sans-serif;background:#101c30;color:#eaf5ff;padding:clamp(20px,6vw,80px)}main{max-width:650px;margin:auto;padding:28px;border:1px solid #365371;border-radius:14px}a{color:#8ddcff}</style></head><body><main><h1>Server status</h1><p>'.pv_h($message).'</p><p><a href="'.pv_h(pv_url('dashboard.php')).'">Check again</a> · <a href="'.pv_h(pv_url('logout.php')).'">Log out</a></p></main></body></html>';
    }
    exit;
}

/** -1 means permanent; 0 means absent; positive values are UTC Unix expiry times. */
function pv_admin_expire_restrictions(mysqli $db, int $uid, array $state): array
{
    $now = time();
    if ((int)$state['ban_until'] > 0 && (int)$state['ban_until'] <= $now) {
        $stmt = pv_admin_runtime_query($db, "UPDATE members m JOIN console_player_state s ON s.user_id=m.id SET m.banned='0',s.ban_until=0 WHERE m.id=? AND s.ban_until>0 AND s.ban_until<=?", [$uid,$now]); $stmt->close();
        $state['ban_until'] = 0;
    }
    if ((int)$state['jail_until'] > 0 && (int)$state['jail_until'] <= $now) {
        $stmt = pv_admin_runtime_query($db, 'UPDATE console_player_state SET jail_until=0 WHERE user_id=? AND jail_until>0 AND jail_until<=?', [$uid,$now]); $stmt->close();
        $state['jail_until'] = 0;
    }
    return $state;
}

function pv_admin_login_state(mysqli $db, array &$member): ?array
{
    if (!pv_admin_runtime_ready($db)) return null;
    $uid = (int)$member['id'];
    pv_admin_runtime_lock($db, $uid);
    $stmt = pv_admin_runtime_query($db, 'SELECT * FROM members WHERE id=?', [$uid]);
    $freshMember = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$freshMember) pv_admin_runtime_deny('This account is no longer available.', 401);
    $member = $freshMember;
    $state = pv_admin_player_state($uid);
    $expiredBan = (int)$state['ban_until'] > 0 && (int)$state['ban_until'] <= time();
    $state = pv_admin_expire_restrictions($db, $uid, $state);
    if ($expiredBan) $member['banned'] = '0';
    return $state;
}

function pv_admin_runtime_revoke(mysqli $db, int $uid, string $reason): never
{
    $_SESSION = [];
    foreach (['online','mapusers'] as $table) {
        $stmt = pv_admin_runtime_query($db, "DELETE FROM {$table} WHERE id=?", [$uid]); $stmt->close();
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    $ajax = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    if ($ajax) pv_admin_runtime_deny($reason, 401);
    pv_redirect('login.php?expired=1');
}

function pv_admin_runtime_refresh(mysqli $db, int $uid, array $member, array $state): void
{
    // Invalidate every recovered battle/item snapshot before it can overwrite a console edit.
    foreach (array_keys($_SESSION) as $key) {
        if (preg_match('/^(?:s[1-6]|ops[1-6]|y_p|your_profile|opponent_profile|attack_short|position|items|aop1|live|wb|lvl|evid|ev|clan_battle|battle_count|nojs-check.*|pv_action_token_.*|pv_wild_.*|pv_pending_wild_encounter|pv_rival_battle|pv_audio_standard_id)$/', (string)$key)) unset($_SESSION[$key]);
    }
    $_SESSION['my_team'] = array_map(static fn($i) => (int)($member['s'.$i] ?? 0), range(1,6));
    $_SESSION['sidequest'] = (int)($member['sidequest'] ?? 1);
    $_SESSION['clan'] = (string)($member['clan_name'] ?? '');
    $_SESSION['myeb'] = (string)($member['eb'] ?? '1');
    $_SESSION['map_preferences'][0] = (int)($member['badges'] ?? 0);
    $stmt = pv_admin_runtime_query($db, 'SELECT pid FROM pokemon WHERE owner=? GROUP BY pid', [$uid]);
    $_SESSION['your_pokemon'] = array_map(static fn($r) => (int)$r['pid'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC)); $stmt->close();
    $stmt = pv_admin_runtime_query($db, 'SELECT id FROM clans WHERE owner=? LIMIT 1', [(string)$member['username']]);
    $_SESSION['clanowner'] = (int)(bool)$stmt->get_result()->fetch_row(); $stmt->close();
    $_SESSION['pv_console_revision'] = (int)$state['data_revision'];
}

/** Refresh derived session caches without discarding active battles or pending rewards. */
function pv_admin_runtime_cache_refresh(mysqli $db, int $uid, array $member): void
{
    $stmt = pv_admin_runtime_query($db, "SELECT value_json FROM console_settings WHERE setting_key='cache_revision'");
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $revision = $row ? (float)json_decode((string)$row['value_json'], true) : 0.0;
    if ($revision <= 0 || $revision > microtime(true) || $revision <= (float)($_SESSION['pv_console_cache_revision'] ?? 0)) return;
    $stmt = pv_admin_runtime_query($db, 'SELECT DISTINCT pid FROM pokemon WHERE owner=?', [$uid]);
    $_SESSION['your_pokemon'] = array_map(static fn($r) => (int)$r['pid'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC)); $stmt->close();
    $_SESSION['my_team'] = array_map(static fn($i) => (int)($member['s'.$i] ?? 0), range(1,6));
    $_SESSION['sidequest'] = (int)($member['sidequest'] ?? 1);
    $_SESSION['clan'] = (string)($member['clan_name'] ?? '');
    $_SESSION['map_preferences'][0] = (int)($member['badges'] ?? 0);
    $stmt = pv_admin_runtime_query($db, 'SELECT id FROM clans WHERE owner=? LIMIT 1', [(string)$member['username']]);
    $_SESSION['clanowner'] = (int)(bool)$stmt->get_result()->fetch_row(); $stmt->close();
    $_SESSION['pv_console_cache_revision'] = $revision;
}

function pv_admin_web_guard(mysqli $db): void
{
    if (PHP_SAPI === 'cli') return;
    static $ran = false;
    if ($ran) return;
    $ran = true;
    // Anonymous login/signup traffic also respects a persisted server maintenance gate.
    if (is_file(__DIR__.'/system_runtime.php')) require_once __DIR__.'/system_runtime.php';
    if (function_exists('pv_admin_apply_script_revision')) pv_admin_apply_script_revision($db);
    if (function_exists('pv_admin_maintenance_state')) {
        $maintenance = pv_admin_maintenance_state($db);
        if (!empty($maintenance['active']) && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'logout.php') {
            pv_admin_runtime_deny((string)($maintenance['reason'] ?? 'The server is temporarily unavailable.'), 503);
        }
    }
    $uid = (int)($_SESSION['myid'] ?? 0);
    if ($uid < 1 || !pv_admin_runtime_ready($db)) return;
    pv_admin_runtime_lock($db, $uid);
    $stmt = pv_admin_runtime_query($db, 'SELECT * FROM members WHERE id=?', [$uid]);
    $member = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$member) pv_admin_runtime_revoke($db, $uid, 'This account is no longer available.');
    $state = pv_admin_player_state($uid);
    $expiredBan = (int)$state['ban_until'] > 0 && (int)$state['ban_until'] <= time();
    $state = pv_admin_expire_restrictions($db, $uid, $state);
    if ($expiredBan) $member['banned'] = '0';
    if ((int)$state['locked'] || (string)($member['banned'] ?? '0') === '1' || (int)$state['ban_until'] !== 0) {
        pv_admin_runtime_revoke($db, $uid, 'This account is not permitted to log in.');
    }
    if ((int)($_SESSION['pv_console_epoch'] ?? 0) !== (int)$state['session_epoch']) {
        pv_admin_runtime_revoke($db, $uid, 'Your session was ended by the local server administrator. Please log in again.');
    }
    $_SESSION['pv_console_epoch'] = (int)$state['session_epoch'];
    if ((int)($_SESSION['pv_console_revision'] ?? 0) !== (int)$state['data_revision']) {
        pv_admin_runtime_refresh($db, $uid, $member, $state);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') pv_admin_runtime_deny('Your account was updated by the server administrator. Refresh this page before trying again.', 409);
    }
    $now = time();
    // Observed active request intervals only: never invent historical playtime or add offline gaps.
    $stmt = pv_admin_runtime_query($db, 'UPDATE console_player_state SET play_seconds=play_seconds+IF(last_seen>0 AND last_seen<=? AND last_seen>=?-300 AND afk=0,?-last_seen,0),last_seen=? WHERE user_id=?', [$now,$now,$now,$now,$uid]); $stmt->close();
    if ((int)$state['frozen'] || (int)$state['jail_until'] !== 0) {
        if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'logout.php') {
            $message = (int)$state['jail_until'] !== 0 ? 'You are in the administrative holding area. Movement, battles, trading and other gameplay actions are suspended.' : 'Your character is frozen by the local server administrator. Movement and gameplay actions are suspended.';
            if ((int)$state['jail_until'] > 0) $message .= ' Release time: '.gmdate('Y-m-d H:i:s', (int)$state['jail_until']).' UTC.';
            pv_admin_runtime_deny($message);
        }
    }
    pv_admin_runtime_cache_refresh($db, $uid, $member);
    if (is_file(__DIR__.'/../admin_pokemon_runtime.php')) require_once __DIR__.'/../admin_pokemon_runtime.php';
    if (function_exists('pv_console_apply_heal')) pv_console_apply_heal($db, $uid, $state);
    if (is_file(__DIR__.'/world_runtime.php')) require_once __DIR__.'/world_runtime.php';
    if (function_exists('pv_admin_world_session_sync')) pv_admin_world_session_sync($db, $uid, $state);
}
