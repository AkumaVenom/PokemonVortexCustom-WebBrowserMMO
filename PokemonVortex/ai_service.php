<?php
/**
 * Persistent local AI worker. Run with CLI PHP, not Apache:
 *   php ai_service.php --continuous
 *   php ai_service.php --once
 *   php ai_service.php --duration=300
 *
 * Uses the same gameplay runtime and configured database as browser requests.
 * Startup checks are read-only: installation/upgrades remain operator actions.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const PV_AI_SERVICE_LOCK = 'pokemon_vortex_ai_service';
const PV_AI_SERVICE_MAX_SECONDS = 300;
const PV_AI_SERVICE_BATCH = 256;
const PV_AI_SERVICE_BUDGET_MS = 1000;
const PV_AI_SERVICE_PAUSE_MS = 50;

require_once __DIR__ . '/includes/ai_service_runtime.php';

function pv_ai_service_message(string $message, bool $error = false): void
{
    fwrite($error ? STDERR : STDOUT, '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message . PHP_EOL);
}

function pv_ai_service_help(): void
{
    echo "Pokemon Vortex NXT autonomous AI service\n"
        . "Usage: php ai_service.php [--continuous|--once|--status] [--duration=1..300]\n\n"
        . "--continuous    Keep running; recycle child workers and retry after database outages.\n"
        . "--once          Run one bounded world/Ranked heartbeat, then exit.\n"
        . "--duration=N    Child/cache lifetime, default 300 seconds; finishes in-flight work.\n"
        . "--status        Read database lock and heartbeat health; exit 0 healthy, 1 stopped/stale.\n"
        . "--help          Show help without a database connection.\n\n"
        . "With no arguments the service runs continuously, including with no players online.\n"
        . "An explicit --duration alone runs a bounded worker for an external supervisor.\n"
        . "Requires Setup Upgrade; uses config/app.php and never installs/seeds accounts.\n"
        . "Disabled/restricted bots and administrative maintenance remain paused.\n"
        . "World and Ranked work run independently under their shared database locks.\n"
        . "Heartbeat: storage/ai-service.json. Setup: ../docs/AI_SERVICE.md.\n"
        . "Exit codes: 0 completed/recycle/healthy, 1 failure/stale, 2 bad arguments,\n"
        . "3 another AI service owns the lock, 130 interrupted.\n";
}

/** Read-only structural checks before any gameplay mutation is allowed. */
function pv_ai_service_preflight(mysqli $db): array
{
    $required = [
        'pv_schema_meta' => 'id,version',
        'members' => 'id,username,s1,s2,s3,s4,s5,s6,total_poke,battle,wins,losses,clan_name,totalexp,uniques,averageexp,points',
        'pokemon' => 'id,pid,name,a1,a2,a3,a4,lvl,exp,exp_curve_version,t1,t2,rowner,owner,ball,gender,ot',
        'pokemon_stats' => 'id,hp_iv,attack_iv,defense_iv,spatk_iv,spdef_iv,speed_iv,nature,ability,ball,gender,ot,happiness,display_form,nickname',
        'pguide' => 'id,name,type1,type2,a1,a2,a3,a4,amount',
        'abilities' => 'name,ability1,ability2,ability3',
        'items' => 'uid',
        'clans' => 'name,wins,members,exp,points',
        'clan_members' => 'id,clan_name,clan,exp',
        'upfortrade' => 'pid',
        'trade_offer_items' => 'pokemon_id,offer_id',
        'trade_offers' => 'id,status',
        'live_battle' => 'id,uid_1,uid_2,settled_1,settled_2',
        'map_blocks' => 'mapnumber,xblock,yblock',
        'world_map_blocks' => 'world_key,area_key,xblock,yblock',
        'mapusers' => 'id,username,trainer,world_key,map,x,y,time',
        'world_map_positions' => 'user_id,world_key,area_key,x,y,updated_at',
        'bot_trainers' => 'user_id,bot_index,enabled,ranked_retry_at,trainer_sprite,world_key,map_key,x,y,next_action_at,last_action_at,last_action,last_wild_name,last_wild_level,wild_battles,wild_wins,captures,wild_encounters,wild_stats_version,updated_at',
        'trainer_rank_state' => 'user_id,rating,peak_rating,ranked_wins,ranked_losses,current_streak,best_streak,shield_until,shield_source_user_id,last_ranked_at,last_attack_at,last_defense_at,updated_at',
        'rival_battles' => 'id,attacker_id,defender_id,winner_id,loser_id,source,attacker_rating_before,defender_rating_before,rating_delta,retaliation_id,summary,created_at',
        'rival_retaliations' => 'id,battle_id,defender_id,attacker_id,status,created_at,expires_at,used_at',
        'ai_activity' => 'id,bot_user_id,category,headline,detail,related_user_id,rating_delta,created_at',
    ];
    foreach ($required as $table => $columns) {
        // Identifiers are fixed above; LIMIT 0 validates columns without reading rows.
        $projection = '`' . implode('`,`', explode(',', $columns)) . '`';
        $result = $db->query('SELECT ' . $projection . ' FROM `' . $table . '` LIMIT 0');
        if (!$result) {
            throw new RuntimeException('Required table or columns are unavailable: ' . $table
                . '. Complete Setup Upgrade manually before starting the AI service.');
        }
        $result->free();
    }
    $result = $db->query('SELECT version FROM pv_schema_meta WHERE id=1 LIMIT 1');
    if (!$result) throw new RuntimeException('Could not read the installed schema version.');
    $version = (int)($result->fetch_assoc()['version'] ?? 0);
    $result->free();
    if ($version < 31) {
        throw new RuntimeException('Schema version 31 or later is required; found ' . $version
            . '. Complete Setup Upgrade manually before starting the AI service.');
    }

    // One aggregate validates identities and their existing state, without a
    // query per bot, account repair, rank-state initialization or starter creation.
    $result = $db->query('SELECT COUNT(*) AS rows_total,COUNT(DISTINCT b.bot_index) AS indexes_total,'
        . 'MIN(b.bot_index) AS first_index,MAX(b.bot_index) AS last_index,'
        . 'COALESCE(SUM(b.enabled=1),0) AS enabled_total,COUNT(m.id) AS member_total,'
        . 'COALESCE(SUM(b.enabled=1 AND p.id IS NOT NULL),0) AS owned_leads,'
        . 'COALESCE(SUM(b.enabled=1 AND rs.user_id IS NOT NULL),0) AS rank_total '
        . 'FROM bot_trainers b LEFT JOIN members m ON m.id=b.user_id '
        . 'LEFT JOIN pokemon p ON p.id=m.s1 AND CAST(p.owner AS UNSIGNED)=m.id '
        . 'LEFT JOIN trainer_rank_state rs ON rs.user_id=b.user_id');
    if (!$result) throw new RuntimeException('Could not verify the installed AI population.');
    $population = $result->fetch_assoc() ?: [];
    $result->free();
    $target = PV_BOT_POPULATION_TARGET;
    $ready = (int)($population['first_index'] ?? 0) === 1
        && (int)($population['last_index'] ?? 0) === $target;
    foreach (['rows_total','indexes_total','member_total'] as $field) {
        $ready = $ready && (int)($population[$field] ?? 0) === $target;
    }
    foreach (['owned_leads','rank_total'] as $field) {
        $ready = $ready && (int)($population[$field] ?? 0) === (int)($population['enabled_total'] ?? 0);
    }
    if (!$ready) {
        // A damaged or deliberately deleted identity must not stop the other
        // 9,999 trainers. Runtime account locks isolate repair/skip decisions.
        pv_ai_service_message('AI population needs review: expected ' . $target
            . ' identities with owned leads/rank state; found '
            . json_encode($population, JSON_UNESCAPED_SLASHES)
            . '. Existing eligible trainers will continue. Run Setup Upgrade for population repair.', true);
    }
    return $population;
}

/** Report queue lag independently of per-child counters and browser traffic. */
function pv_ai_service_backlog(mysqli $db, int $now): array
{
    $adminReady = pv_admin_runtime_ready($db);
    $result = $db->query('SELECT COUNT(*) AS eligible_trainers,'
        . 'COALESCE(SUM(b.next_action_at<=' . $now . '),0) AS due_trainers,'
        . 'MIN(CASE WHEN b.next_action_at<=' . $now . ' THEN b.next_action_at ELSE NULL END) AS oldest_due_at '
        . 'FROM bot_trainers b JOIN members m ON m.id=b.user_id '
        . pv_admin_activity_join($adminReady)
        . ' WHERE b.enabled=1 AND ' . pv_admin_activity_where($adminReady, $now));
    if (!$result) throw new RuntimeException('Could not read the AI work queue health.');
    $row = $result->fetch_assoc() ?: [];
    $result->free();
    return ['checked_at'=>$now, 'eligible_trainers'=>(int)($row['eligible_trainers'] ?? 0),
        'due_trainers'=>(int)($row['due_trainers'] ?? 0),
        'oldest_due_seconds'=>isset($row['oldest_due_at']) ? max(0, $now - (int)$row['oldest_due_at']) : 0];
}

try { $options = pv_ai_service_options(array_slice($argv, 1)); }
catch (InvalidArgumentException $error) { pv_ai_service_message($error->getMessage(), true); exit(2); }
if ($options['help']) { pv_ai_service_help(); exit(0); }
$supervisorPath = __DIR__ . '/storage/ai-service-supervisor.lock';
if (!$options['status'] && ($options['continuous'] || getenv('PV_AI_SERVICE_SUPERVISOR_PID') === false)) {
    try { $supervisorReservation = pv_ai_service_reserve_supervisor($supervisorPath); }
    catch (Throwable $error) { pv_ai_service_message($error->getMessage(), true); exit(1); }
    if ($supervisorReservation === null) {
        pv_ai_service_message('Another local AI supervisor is already running; no work started.', true);
        exit(3);
    }
    if (!$options['continuous']) fclose($supervisorReservation);
}
if ($options['continuous']) {
    putenv('PV_AI_SERVICE_SUPERVISOR_PID=' . getmypid());
    $command = [PHP_BINARY];
    $ini = php_ini_loaded_file();
    if (is_string($ini) && $ini !== '') { $command[] = '-c'; $command[] = $ini; }
    $command = array_merge($command, ['-d', 'display_errors=0', '-d', 'display_startup_errors=0', __FILE__, '--duration=' . $options['duration']]);
    exit(pv_ai_service_supervise($command, 'pv_ai_service_message'));
}
$once = $options['once'];
$duration = $options['duration'];
$heartbeatPath = __DIR__ . '/storage/ai-service.json';

// Load the normal database/config helpers directly. Never include Setup or a
// page controller that could run an upgrade. Bootstrap itself does not migrate.
define('PV_DISABLE_OUTPUT_FILTER', true);
$db = null;
$lockHeld = false;
$exitCode = 0;
$stop = false;
$heartbeat = [];
try {
    require_once __DIR__ . '/includes/bootstrap.php';
    require_once __DIR__ . '/includes/bot_runtime.php';
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (!defined('PV_BOT_POPULATION_TARGET')) {
        throw new RuntimeException('Install the complete 10,000-trainer release before starting the AI service.');
    }
    $db = pv_db();
    if ($options['status']) {
        $result = $db->query("SELECT IS_USED_LOCK('" . PV_AI_SERVICE_LOCK . "') AS owner");
        if (!$result) throw new RuntimeException('Could not read AI service lock status.');
        $owner = $result->fetch_assoc()['owner'] ?? null;
        $result->free();
        $saved = is_file($heartbeatPath) ? json_decode((string)file_get_contents($heartbeatPath), true) : [];
        $status = pv_ai_service_status(is_array($saved) ? $saved : [], $owner === null ? null : (int)$owner, time());
        echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        $db->close();
        exit($status['healthy'] ? 0 : 1);
    }
    $result = $db->query("SELECT GET_LOCK('" . PV_AI_SERVICE_LOCK . "',0) AS acquired");
    if (!$result) throw new RuntimeException('Could not acquire the AI service advisory lock.');
    $acquired = $result->fetch_assoc()['acquired'] ?? null;
    $result->free();
    if ($acquired === null) throw new RuntimeException('The database could not provide the AI service advisory lock.');
    if ((int)$acquired !== 1) {
        pv_ai_service_message('Another AI service is already running; no work started.', true);
        $exitCode = 3;
    } else {
        $lockHeld = true;
        $population = pv_ai_service_preflight($db);
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $handler = static function (int $signal) use (&$stop): void { $stop = true; };
            pcntl_signal(SIGINT, $handler);
            pcntl_signal(SIGTERM, $handler);
        }
        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(static function (int $event) use (&$stop): void { $stop = true; });
        }
        $started = hrtime(true);
        $deadline = $started + ($duration * 1000000000);
        $nextReport = $started + 30000000000;
        $passes = 0;
        $actions = 0;
        $ranked = 0;
        $nextHeartbeat = 0;
        $nextBacklog = 0;
        $heartbeat = ['state'=>'running', 'pid'=>getmypid(), 'connection_id'=>$db->thread_id,
            'started_at'=>time(), 'heartbeat_at'=>time(), 'passes'=>0, 'world_actions'=>0, 'ranked_matches'=>0,
            'population'=>$population];
        pv_ai_service_write_heartbeat($heartbeatPath, $heartbeat);
        pv_ai_service_message('AI service started for ' . PV_BOT_POPULATION_TARGET . ' trainers; '
            . ($once ? 'one heartbeat' : $duration . '-second process') . '. Ctrl+C stops the service.');
        do {
            // A lost connection/lock must stop the worker, never silently resume
            // with stale runtime caches or compete with a replacement worker.
            $result = $db->query("SELECT IS_USED_LOCK('" . PV_AI_SERVICE_LOCK . "')=CONNECTION_ID() AS owned");
            if (!$result) throw new RuntimeException('Lost the database connection or AI service lock.');
            $owned = (int)($result->fetch_assoc()['owned'] ?? 0);
            $result->free();
            if ($owned !== 1) throw new RuntimeException('AI service lock ownership was lost.');

            // Independent heartbeats prevent a busy world lock from starving
            // Ranked. Both retain runtime locks, cooldowns and the shared quota.
            $worldCompleted = pv_bot_tick($db, PV_AI_SERVICE_BATCH, '', '', PV_AI_SERVICE_BUDGET_MS, false);
            $rankedCompleted = pv_bot_ranked_pulse($db, PV_BOT_RANKED_PULSE_MAX_OPERATIONS, 1200);
            $actions += $worldCompleted;
            $ranked += $rankedCompleted;
            $passes++;
            $now = hrtime(true);
            if ($now >= $nextBacklog) {
                $heartbeat['queue'] = pv_ai_service_backlog($db, time());
                $nextBacklog = $now + 30000000000;
            }
            if ($now >= $nextHeartbeat || $once || $stop || $now >= $deadline) {
                $heartbeat = array_replace($heartbeat, ['state'=>pv_admin_activity_server_open($db) ? 'running' : 'maintenance',
                    'heartbeat_at'=>time(), 'passes'=>$passes, 'world_actions'=>$actions, 'ranked_matches'=>$ranked]);
                pv_ai_service_write_heartbeat($heartbeatPath, $heartbeat);
                $nextHeartbeat = $now + 5000000000;
            }
            if ($once || $stop || $now >= $deadline) break;
            if ($now >= $nextReport) {
                pv_ai_service_message('Heartbeat passes=' . $passes . '; processed world actions=' . $actions . '; ranked matches=' . $ranked . '.');
                $nextReport = $now + 30000000000;
            }
            $pauseMs = $worldCompleted === 0 && $rankedCompleted === 0 ? 500 : PV_AI_SERVICE_PAUSE_MS;
            usleep((int)min($pauseMs * 1000, max(0, ($deadline - $now) / 1000)));
        } while (!$stop && hrtime(true) < $deadline);

        $reason = $stop ? 'interrupted' : ($once ? 'once complete' : 'cache recycle');
        pv_ai_service_message('AI service finished (' . $reason . '): passes=' . $passes
            . '; processed world actions=' . $actions . '; ranked matches=' . $ranked . '; elapsed_seconds='
            . number_format((hrtime(true) - $started) / 1000000000, 2, '.', '') . '.');
        if ($stop) $exitCode = 130;
    }
} catch (Throwable $error) {
    if ($options['status']) {
        echo json_encode(['healthy'=>false, 'error'=>$error->getMessage()], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
    } else pv_ai_service_message('AI service stopped: ' . $error->getMessage(), true);
    $exitCode = 1;
} finally {
    if ($db instanceof mysqli && $lockHeld) {
        if ($heartbeat !== []) {
            try { pv_ai_service_write_heartbeat($heartbeatPath, array_replace($heartbeat, ['state'=>$exitCode === 1 ? 'failed' : 'stopped', 'heartbeat_at'=>time()])); }
            catch (Throwable $ignored) { /* Original error has already been reported. */ }
        }
        try { $db->query("DO RELEASE_LOCK('" . PV_AI_SERVICE_LOCK . "')"); }
        catch (Throwable $ignored) { /* Disconnect also releases connection-owned locks. */ }
    }
    if ($db instanceof mysqli) {
        try { $db->close(); } catch (Throwable $ignored) {}
    }
}
exit($exitCode);
