<?php
/**
 * Optional local AI worker. Run with CLI PHP, not Apache:
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
const PV_AI_SERVICE_BATCH = 64;
const PV_AI_SERVICE_BUDGET_MS = 100;
const PV_AI_SERVICE_PAUSE_MS = 250;

function pv_ai_service_message(string $message, bool $error = false): void
{
    fwrite($error ? STDERR : STDOUT, '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message . PHP_EOL);
}

function pv_ai_service_help(): void
{
    echo "Pokemon Vortex NXT optional AI service\n"
        . "Usage: php ai_service.php [--once] [--duration=1..300] [--help]\n\n"
        . "--once          Run one bounded gameplay heartbeat, then exit (cron/smoke).\n"
        . "--duration=N    Run for up to N seconds, default 300; finish in-flight work.\n"
        . "--help          Show this help without opening a database connection.\n\n"
        . "Requires the upgraded schema and all AI identities; disabled bots stay disabled.\n"
        . "Uses config/app.php through pv_db(); never installs or seeds accounts.\n"
        . "Each pass uses up to 64 due bots, the existing shared Ranked quota,\n"
        . "and a 250 ms pause. Work budgets are best-effort, not throughput promises.\n"
        . "A zero-action pass can mean nothing is due or another request owns the tick lock.\n"
        . "Continuous processes recycle within 300 seconds plus in-flight work so\n"
        . "runtime caches refresh. Start a new process to continue after a normal recycle.\n"
        . "Exit codes: 0 completed/recycle, 1 startup/runtime failure, 2 bad arguments,\n"
        . "3 another AI service owns the lock, 130 interrupted.\n";
}

/** Read-only structural checks before any gameplay mutation is allowed. */
function pv_ai_service_preflight(mysqli $db): void
{
    $required = [
        'pv_schema_meta' => 'id,version',
        'members' => 'id,username,s1,s2,s3,s4,s5,s6,total_poke,battle,wins,losses,clan_name,totalexp,uniques,averageexp,points',
        'pokemon' => 'id,pid,name,a1,a2,a3,a4,lvl,exp,t1,t2,rowner,owner,ball,gender,ot',
        'pokemon_stats' => 'id,hp_iv,attack_iv,defense_iv,spatk_iv,spdef_iv,speed_iv,nature,ability,ball,gender,ot,happiness,display_form',
        'pguide' => 'id,name,type1,type2,a1,a2,a3,a4,amount',
        'abilities' => 'name,ability1,ability2,ability3',
        'items' => 'uid',
        'clans' => 'name,wins,members,exp,points',
        'clan_members' => 'id,clan_name,clan,exp',
        'map_blocks' => 'mapnumber,xblock,yblock',
        'world_map_blocks' => 'world_key,area_key,xblock,yblock',
        'mapusers' => 'id,username,trainer,world_key,map,x,y,time',
        'world_map_positions' => 'user_id,world_key,area_key,x,y,updated_at',
        'bot_trainers' => 'user_id,bot_index,enabled,trainer_sprite,world_key,map_key,x,y,next_action_at,last_action_at,last_action,last_wild_name,last_wild_level,wild_battles,wild_wins,captures,updated_at',
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
    if ($version < 28) {
        throw new RuntimeException('Schema version 28 or later is required; found ' . $version
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
        throw new RuntimeException('AI population is not ready: expected ' . $target
            . ' indexes 1..' . $target . ' with member accounts and an owned lead/rank state for every enabled bot; found '
            . json_encode($population, JSON_UNESCAPED_SLASHES)
            . '. Review the installation and complete Setup Upgrade if needed. No repair was attempted.');
    }
}

$once = false;
$duration = PV_AI_SERVICE_MAX_SECONDS;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        pv_ai_service_help();
        exit(0);
    }
    if ($argument === '--once') {
        $once = true;
        continue;
    }
    if (preg_match('/^--duration=([0-9]{1,3})$/D', $argument, $match)
        && (int)$match[1] >= 1 && (int)$match[1] <= PV_AI_SERVICE_MAX_SECONDS) {
        $duration = (int)$match[1];
        continue;
    }
    pv_ai_service_message('Invalid argument: ' . $argument . '. Use --help for usage.', true);
    exit(2);
}

// Load the normal database/config helpers directly. Never include Setup or a
// page controller that could run an upgrade. Bootstrap itself does not migrate.
define('PV_DISABLE_OUTPUT_FILTER', true);
$db = null;
$lockHeld = false;
$exitCode = 0;
$stop = false;
try {
    require_once __DIR__ . '/includes/bootstrap.php';
    require_once __DIR__ . '/includes/bot_runtime.php';
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (!defined('PV_BOT_POPULATION_TARGET')) {
        throw new RuntimeException('Install the complete 10,000-trainer release before starting the AI service.');
    }
    $db = pv_db();
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
        pv_ai_service_preflight($db);
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

            // Empty focus arguments preserve global due order. pv_bot_tick owns
            // its existing nonblocking tick lock and calls pv_bot_ranked_pulse
            // under the existing ranked lock and persisted shared minute quota.
            $actions += pv_bot_tick($db, PV_AI_SERVICE_BATCH, '', '', PV_AI_SERVICE_BUDGET_MS);
            $passes++;
            $now = hrtime(true);
            if ($once || $stop || $now >= $deadline) break;
            if ($now >= $nextReport) {
                pv_ai_service_message('Heartbeat passes=' . $passes . '; processed world actions=' . $actions . '.');
                $nextReport = $now + 30000000000;
            }
            usleep((int)min(PV_AI_SERVICE_PAUSE_MS * 1000, max(0, ($deadline - $now) / 1000)));
        } while (!$stop && hrtime(true) < $deadline);

        $reason = $stop ? 'interrupted' : ($once ? 'once complete' : 'cache recycle');
        pv_ai_service_message('AI service finished (' . $reason . '): passes=' . $passes
            . '; processed world actions=' . $actions . '; elapsed_seconds='
            . number_format((hrtime(true) - $started) / 1000000000, 2, '.', '') . '.');
        if ($stop) $exitCode = 130;
    }
} catch (Throwable $error) {
    pv_ai_service_message('AI service stopped: ' . $error->getMessage(), true);
    $exitCode = 1;
} finally {
    if ($db instanceof mysqli && $lockHeld) {
        try { $db->query("DO RELEASE_LOCK('" . PV_AI_SERVICE_LOCK . "')"); }
        catch (Throwable $ignored) { /* Disconnect also releases connection-owned locks. */ }
    }
    if ($db instanceof mysqli) {
        try { $db->close(); } catch (Throwable $ignored) {}
    }
}
exit($exitCode);
