<?php
declare(strict_types=1);
/** CLI-only lifecycle regressions; no database or game records are touched. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/ai_service_runtime.php';
$checks = 0;
$assert = static function (bool $condition, string $description) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL ' . $description);
    $checks++;
};
$directory = sys_get_temp_dir() . '/pv-ai-service-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
try {
    $assert(pv_ai_service_options(['--continuous','--duration=1'])['duration'] === 1, 'short child recycle is accepted');
    $assert(pv_ai_service_options([])['duration'] === 300, 'bounded worker retains maximum cache lifetime');
    $assert(pv_ai_service_options([])['continuous'], 'bare legacy launch now remains continuously active');
    $assert(!pv_ai_service_options(['--duration=2'])['continuous'], 'explicit duration remains bounded for systemd');
    foreach ([['--duration=0'],['--duration=301'],['--once','--continuous'],['--status','--continuous'],['--status','--once'],['--unknown']] as $arguments) {
        try { pv_ai_service_options($arguments); $rejected = false; }
        catch (InvalidArgumentException $expected) { $rejected = true; }
        $assert($rejected, 'invalid/conflicting arguments are rejected before database access');
    }

    $path = $directory . '/heartbeat.json';
    $heartbeat = ['connection_id'=>42, 'heartbeat_at'=>100, 'state'=>'running', 'world_actions'=>12];
    pv_ai_service_write_heartbeat($path, $heartbeat);
    $assert(json_decode((string)file_get_contents($path), true) === $heartbeat, 'heartbeat is complete JSON');
    $heartbeat['world_actions'] = 13;
    pv_ai_service_write_heartbeat($path, $heartbeat);
    $assert(json_decode((string)file_get_contents($path), true)['world_actions'] === 13, 'existing heartbeat replaces atomically');
    $assert(pv_ai_service_status($heartbeat, 42, 130)['healthy'], 'live owner and recent heartbeat are healthy');
    $assert(!pv_ai_service_status($heartbeat, 42, 131)['healthy'], 'stale heartbeat is unhealthy');
    $assert(!pv_ai_service_status($heartbeat, null, 101)['healthy'], 'stopped database owner is unhealthy');
    $assert(!pv_ai_service_status($heartbeat, 43, 101)['healthy'], 'previous child heartbeat cannot validate new owner');
    $assert(pv_ai_service_status(array_replace($heartbeat, ['state'=>'maintenance']), 42, 101)['healthy'], 'maintenance is healthy intentional pause');
    $assert(!pv_ai_service_status(array_replace($heartbeat, ['state'=>'failed']), 42, 101)['healthy'], 'failed state is unhealthy');

    $reservation = pv_ai_service_reserve_supervisor($directory . '/supervisor.lock');
    $assert(is_resource($reservation), 'supervisor reserves its complete lifetime');
    $assert(pv_ai_service_reserve_supervisor($directory . '/supervisor.lock') === null, 'duplicate cannot steal reservation between children');
    fclose($reservation);
    $reservation = pv_ai_service_reserve_supervisor($directory . '/supervisor.lock');
    $assert(is_resource($reservation), 'reservation releases after supervisor exits');
    fclose($reservation);

    // Real child processes prove successful exits are recycled, transient
    // failures retried, and duplicate/invalid workers never restart forever.
    $fixture = $directory . '/child.php';
    file_put_contents($fixture, <<<'PHP'
<?php
$path = $argv[1];
$count = is_file($path) ? (int)file_get_contents($path) : 0;
file_put_contents($path, (string)++$count);
if ($argv[2] === 'wait') { file_put_contents($path . '.pid', (string)getmypid()); sleep(20); exit(0); }
if ($argv[2] === 'recycle') exit($count < 3 ? 0 : 130);
if ($argv[2] === 'retry') exit($count < 2 ? 1 : 130);
exit((int)$argv[2]);
PHP);
    foreach (['recycle'=>[130,3], 'retry'=>[130,2], '3'=>[3,1], '2'=>[2,1]] as $scenario => [$expectedCode, $expectedRuns]) {
        $countPath = $directory . '/count-' . $scenario;
        $messages = [];
        $code = pv_ai_service_supervise([PHP_BINARY, $fixture, $countPath, (string)$scenario], static function (string $message, bool $error = false) use (&$messages): void { $messages[] = $message; });
        $assert($code === $expectedCode, 'supervisor preserves terminal result: ' . $scenario);
        $assert((int)file_get_contents($countPath) === $expectedRuns, 'correct real process restart count: ' . $scenario);
    }
    if (PHP_OS_FAMILY !== 'Windows' && function_exists('pcntl_signal') && function_exists('posix_kill')) {
        $harness = $directory . '/supervisor.php';
        $runtime = dirname(__DIR__) . '/includes/ai_service_runtime.php';
        file_put_contents($harness, '<?php require ' . var_export($runtime, true)
            . '; exit(pv_ai_service_supervise([PHP_BINARY,$argv[1],$argv[2],"wait"],static function(string $message,bool $error=false):void{}));');
        $countPath = $directory . '/signal-count';
        $process = proc_open([PHP_BINARY, '-c', (string)php_ini_loaded_file(), $harness, $fixture, $countPath], [0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR], $pipes);
        $assert(is_resource($process), 'signal test supervisor starts');
        $deadline = microtime(true) + 5;
        while (!is_file($countPath . '.pid') && microtime(true) < $deadline) usleep(10000);
        try {
            $assert(is_file($countPath . '.pid'), 'supervisor starts child before stop');
            $childPid = (int)file_get_contents($countPath . '.pid');
            proc_terminate($process);
            $deadline = microtime(true) + 5;
            do { $state = proc_get_status($process); if (!$state['running']) break; usleep(10000); } while (microtime(true) < $deadline);
            $assert(!$state['running'] && $state['exitcode'] === 130, 'SIGTERM stops continuous supervisor intentionally');
            $assert(!posix_kill($childPid, 0), 'stopping supervisor leaves no orphan worker');
            $assert((int)file_get_contents($countPath) === 1, 'intentional stop does not restart child');
        } finally { proc_terminate($process, 9); proc_close($process); }
    }
    echo 'PASS ' . $checks . ' AI worker lifecycle/status checks; actual child recycling and failure retry verified.' . PHP_EOL;
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) unlink($file);
    rmdir($directory);
}
