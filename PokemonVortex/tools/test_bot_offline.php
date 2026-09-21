<?php
declare(strict_types=1);
/**
 * Real background-worker regression, using an upgraded disposable 10,000-bot DB.
 * No browser requests are made. The worker changes the selected fixture normally.
 *
 * PV_TEST_DB_NAME=pv_test_offline php tools/test_bot_offline.php --integration
 * Optional: PV_TEST_DB_HOST, PV_TEST_DB_PORT, PV_TEST_DB_USER,
 *           PV_TEST_DB_PASSWORD (or PV_TEST_DB_PASS), --seconds=45..120.
 * Run in a test checkout: the service uses its normal storage heartbeat/logs.
 * The test does not install, reset, enable, seed, or overwrite trainer progress.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$seconds = 45;
$integration = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--integration') $integration = true;
    elseif (preg_match('/^--seconds=(\d+)$/D', $argument, $match)
        && (int)$match[1] >= 45 && (int)$match[1] <= 120) $seconds = (int)$match[1];
    elseif ($argument === '--help' || $argument === '-h') $integration = false;
    else { fwrite(STDERR, "Usage: php tools/test_bot_offline.php --integration [--seconds=45..120]\n"); exit(2); }
}
if (!$integration) {
    echo "SKIP: use --integration with an upgraded, enabled 10,000-bot PV_TEST_DB_NAME=pv_test_* fixture.\n"
        . "This runs the production background worker for 45 seconds without browser traffic.\n";
    exit;
}

$name = (string)getenv('PV_TEST_DB_NAME');
if (!preg_match('/^pv_test_[a-z0-9_]+$/D', $name)) {
    fwrite(STDERR, "Refusing integration: PV_TEST_DB_NAME must name a disposable pv_test_* database.\n");
    exit(2);
}
if (!function_exists('proc_open') || !class_exists('mysqli')) {
    fwrite(STDERR, "Integration requires CLI PHP with mysqli and proc_open.\n");
    exit(2);
}

$app = dirname(__DIR__);
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pv-offline-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Cannot create integration scratch directory.');
$log = $directory . DIRECTORY_SEPARATOR . 'worker.log';
$process = null;
$checks = 0;
$pids = [];
$db = null;
$failure = null;
$stopRequested = false;
$startedWorker = false;
if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, static function () use (&$stopRequested): void { $stopRequested = true; });
    pcntl_signal(SIGTERM, static function () use (&$stopRequested): void { $stopRequested = true; });
}

function offline_check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS ' . $label . PHP_EOL;
}

/** Reap the supervisor, which propagates the signal to its bounded worker. */
function offline_stop(&$process): void
{
    if (!is_resource($process)) return;
    $state = proc_get_status($process);
    if ($state['running']) @proc_terminate($process);
    $deadline = microtime(true) + 15;
    while ($state['running'] && microtime(true) < $deadline) {
        usleep(100000);
        $state = proc_get_status($process);
    }
    if ($state['running']) @proc_terminate($process, 9);
    proc_close($process);
    $process = null;
}
register_shutdown_function(static function () use (&$process): void { offline_stop($process); });

function offline_snapshot(mysqli $db): array
{
    $row = $db->query('SELECT SUM(wild_encounters) encounters,SUM(wild_battles) wild_battles,'
        . 'SUM(wild_wins) wild_wins,SUM(wild_battles-wild_wins) wild_losses,SUM(captures) captures '
        . 'FROM bot_trainers WHERE enabled=1')->fetch_assoc();
    // New captures have existing level EXP. They must never count as evidence
    // that training earned EXP: only specimens present before launch qualify.
    $experience = $db->query('SELECT COALESCE(SUM(GREATEST(p.exp-s.exp,0)),0) earned,'
        . 'SUM(p.exp>s.exp) trained FROM pv_offline_specimens s JOIN pokemon p ON p.id=s.id')->fetch_assoc();
    $row['existing_specimen_exp_gained'] = $experience['earned'];
    $row['existing_specimens_trained'] = $experience['trained'];
    return array_map('intval', $row);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $password = getenv('PV_TEST_DB_PASSWORD');
    if ($password === false) $password = getenv('PV_TEST_DB_PASS');
    $db = new mysqli((string)(getenv('PV_TEST_DB_HOST') ?: '127.0.0.1'),
        (string)(getenv('PV_TEST_DB_USER') ?: 'root'), $password === false ? '' : $password,
        $name, (int)(getenv('PV_TEST_DB_PORT') ?: 3306));
    $db->set_charset('utf8mb4');
    offline_check((string)$db->query('SELECT DATABASE() db')->fetch_assoc()['db'] === $name,
        'connection uses the explicit disposable database');
    $population = $db->query('SELECT COUNT(*) total,SUM(enabled=1) enabled,MIN(bot_index) first_index,'
        . 'MAX(bot_index) last_index,SUM(wild_stats_version=1) migrated FROM bot_trainers')->fetch_assoc();
    offline_check((int)$population['total'] === 10000 && (int)$population['enabled'] === 10000
        && (int)$population['first_index'] === 1 && (int)$population['last_index'] === 10000
        && (int)$population['migrated'] === 10000, 'fixture contains 10,000 enabled and upgraded trainers');
    offline_check($db->query("SELECT IS_USED_LOCK('pokemon_vortex_ai_service') owner")->fetch_assoc()['owner'] === null,
        'no other worker owns the fixture');
    $db->query('CREATE TEMPORARY TABLE pv_offline_specimens (id INT PRIMARY KEY, exp BIGINT NOT NULL)');
    $db->query('INSERT INTO pv_offline_specimens SELECT p.id,p.exp FROM pokemon p '
        . 'JOIN bot_trainers b ON b.user_id=CAST(p.owner AS UNSIGNED) WHERE b.enabled=1');
    $feedStart = (int)$db->query('SELECT COALESCE(MAX(id),0) id FROM ai_activity')->fetch_assoc()['id'];
    $rankStart = (int)$db->query('SELECT COALESCE(MAX(id),0) id FROM rival_battles')->fetch_assoc()['id'];
    $before = offline_snapshot($db);

    // Put the override in a temporary php.ini, not a -d option: the real
    // supervisor deliberately inherits its loaded ini when recycling children.
    // Credentials remain in the supplied environment, never in generated files.
    $prepend = $directory . DIRECTORY_SEPARATOR . 'prepend.php';
    $prependCode = '<?php' . PHP_EOL . 'declare(strict_types=1);' . PHP_EOL
        . '$testName=(string)getenv("PV_TEST_DB_NAME");' . PHP_EOL
        . 'if(!preg_match("/^pv_test_[a-z0-9_]+$/D",$testName))throw new RuntimeException("Unsafe offline test database.");' . PHP_EOL
        . 'require_once ' . var_export($app . '/includes/bootstrap.php', true) . ';' . PHP_EOL
        . '$testPassword=getenv("PV_TEST_DB_PASSWORD");if($testPassword===false)$testPassword=getenv("PV_TEST_DB_PASS");' . PHP_EOL
        . '$GLOBALS["pv_config"]["db"]=array_replace($GLOBALS["pv_config"]["db"],[' . PHP_EOL
        . '"name"=>$testName,"host"=>(string)(getenv("PV_TEST_DB_HOST")?:"127.0.0.1"),' . PHP_EOL
        . '"port"=>(int)(getenv("PV_TEST_DB_PORT")?:3306),"user"=>(string)(getenv("PV_TEST_DB_USER")?:"root"),' . PHP_EOL
        . '"pass"=>$testPassword===false?"":$testPassword]);' . PHP_EOL
        . 'if(session_status()===PHP_SESSION_ACTIVE)session_write_close();' . PHP_EOL;
    if (file_put_contents($prepend, $prependCode) !== strlen($prependCode)) throw new RuntimeException('Cannot write test prepend.');
    $iniSource = php_ini_loaded_file();
    $iniText = is_string($iniSource) && $iniSource !== '' ? (string)file_get_contents($iniSource) : '';
    // Quote as a PHP ini string; forward slashes also work on Windows.
    $iniPath = str_replace(['\\', '"'], ['/', '\\"'], $prepend);
    $iniText .= PHP_EOL . 'auto_prepend_file="' . $iniPath . '"' . PHP_EOL;
    $ini = $directory . DIRECTORY_SEPARATOR . 'php.ini';
    if (file_put_contents($ini, $iniText) !== strlen($iniText)) throw new RuntimeException('Cannot write test ini.');
    $command = [PHP_BINARY, '-c', $ini, $app . '/ai_service.php', '--continuous', '--duration=2'];
    $process = proc_open($command, [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, $app, null, ['bypass_shell' => true]);
    offline_check(is_resource($process), 'production continuous worker starts');
    $startedWorker = true;
    $supervisorPid = (int)proc_get_status($process)['pid'];
    $deadline = microtime(true) + $seconds;
    do {
        $state = proc_get_status($process);
        if (!$state['running']) throw new RuntimeException('Continuous supervisor exited early, code ' . $state['exitcode']);
        if ($stopRequested) throw new RuntimeException('Integration interrupted.');
        $heartbeatFile = $app . '/storage/ai-service.json';
        if (is_file($heartbeatFile)) {
            $heartbeat = json_decode((string)file_get_contents($heartbeatFile), true);
            if (($heartbeat['state'] ?? '') === 'running') $pids[(int)$heartbeat['pid']] = true;
        }
        usleep(100000);
    } while (microtime(true) < $deadline);
    offline_stop($process);
    // On platforms without signal handlers, a child still ends at its bounded
    // deadline. Wait for its lock release before making the final snapshot.
    $deadline = microtime(true) + 10;
    do {
        $owner = $db->query("SELECT IS_USED_LOCK('pokemon_vortex_ai_service') owner")->fetch_assoc()['owner'];
        if ($owner === null) break;
        usleep(100000);
    } while (microtime(true) < $deadline);
    offline_check($owner === null, 'shutdown releases the worker database lock');
    offline_check(count($pids) >= 2, 'multiple worker processes recycle and resume without browser traffic');
    if (function_exists('posix_kill')) {
        foreach (array_merge([$supervisorPid], array_keys($pids)) as $pid) {
            offline_check(!@posix_kill((int)$pid, 0), 'service process ' . $pid . ' is reaped after shutdown');
        }
    }
    $after = offline_snapshot($db);
    $delta = [];
    foreach ($after as $key => $value) $delta[$key] = $value - $before[$key];
    $delta['ranked'] = (int)$db->query("SELECT COUNT(*) n FROM rival_battles WHERE source='autonomous' AND id>" . $rankStart)->fetch_assoc()['n'];
    $feed = $db->query("SELECT SUM(category='wild' AND headline='Won a wild battle') wild_wins,"
        . "SUM(category='wild' AND headline='Lost a wild battle') wild_losses,SUM(category='capture') captures,"
        . "SUM(category='wild' AND headline='Won a wild battle' AND detail LIKE '%team EXP%') rewarded_wins "
        . 'FROM ai_activity WHERE id>' . $feedStart)->fetch_assoc();
    $feed = array_map('intval', $feed);
    echo json_encode(['seconds' => $seconds, 'worker_processes' => count($pids),
        'outcome_deltas' => $delta, 'visible_feed' => $feed], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    offline_check($delta['encounters'] >= 200, 'offline run resolves enough real encounters to assess activity balance');
    offline_check($delta['wild_wins'] > 0 && $delta['wild_losses'] > 0,
        'wild victories and losses both settle, separately from captures');
    offline_check($delta['captures'] > 0 && $delta['ranked'] > 0, 'captures and autonomous ranked matches continue alongside training');
    offline_check($delta['wild_battles'] === $delta['wild_wins'] + $delta['wild_losses']
        && $delta['encounters'] === $delta['wild_battles'] + $delta['captures'],
        'every committed encounter has exactly one battle or capture outcome');
    offline_check($delta['wild_wins'] / $delta['encounters'] >= 0.15
        && $delta['captures'] / $delta['encounters'] >= 0.15, 'neither hunting nor successful training is starved');
    offline_check($delta['existing_specimen_exp_gained'] > 0 && $delta['existing_specimens_trained'] > 0,
        'Pokemon owned before the test earn EXP, excluding every newly captured specimen');
    offline_check($feed['wild_wins'] > 0 && $feed['wild_losses'] > 0 && $feed['captures'] > 0
        && $feed['rewarded_wins'] > 0, 'visible activity reports wild victories, losses, catches and earned training EXP');
    $quota = $db->query("SELECT FLOOR(created_at/60) bucket,COUNT(*) total FROM rival_battles "
        . "WHERE source='autonomous' AND created_at>=(SELECT FLOOR(COALESCE(MIN(created_at),0)/60)*60 FROM rival_battles WHERE id>"
        . $rankStart . ") GROUP BY FLOOR(created_at/60) HAVING COUNT(*)>16");
    offline_check($quota->num_rows === 0, 'recycling preserves the shared sixteen ranked matches per minute quota');
    $output = (string)file_get_contents($log);
    offline_check(!str_contains($output, 'AI service stopped:') && !str_contains($output, 'Fatal error'),
        'worker log contains no runtime shutdown failures');
} catch (Throwable $error) {
    $failure = $error;
} finally {
    offline_stop($process);
    // If an assertion or interrupt ended the run early, a platform without
    // signal forwarding must still allow the bounded child to finish before
    // deleting the ini/prepend files or closing the fixture connection.
    if ($startedWorker && $db instanceof mysqli) {
        $deadline = microtime(true) + 10;
        try {
            do {
                $owner = $db->query("SELECT IS_USED_LOCK('pokemon_vortex_ai_service') owner")->fetch_assoc()['owner'];
                if ($owner === null) break;
                usleep(100000);
            } while (microtime(true) < $deadline);
            if ($owner !== null && $failure === null) $failure = new RuntimeException('Worker lock remained held after cleanup.');
        } catch (Throwable $cleanupError) { if ($failure === null) $failure = $cleanupError; }
    }
    if ($db instanceof mysqli) $db->close();
    if ($failure !== null && is_file($log)) fwrite(STDERR, (string)file_get_contents($log));
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $temporary) @unlink($temporary);
    @rmdir($directory);
}
if ($failure !== null) { fwrite(STDERR, $failure->getMessage() . PHP_EOL); exit(1); }
echo 'PASS ' . $checks . ' production offline worker checks' . PHP_EOL;
