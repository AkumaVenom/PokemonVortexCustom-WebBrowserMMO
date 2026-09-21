<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Argument validation stays independent of database availability. */
function pv_ai_service_options(array $arguments): array
{
    $options = ['once'=>false, 'continuous'=>false, 'status'=>false, 'help'=>false, 'duration'=>300];
    $explicitDuration = false;
    foreach ($arguments as $argument) {
        if ($argument === '-h') $argument = '--help';
        if (in_array($argument, ['--once','--continuous','--status','--help'], true)) {
            $options[substr($argument, 2)] = true;
        } elseif (preg_match('/^--duration=([0-9]{1,3})$/D', $argument, $match)
            && (int)$match[1] >= 1 && (int)$match[1] <= 300) {
            $options['duration'] = (int)$match[1];
            $explicitDuration = true;
        } else throw new InvalidArgumentException('Invalid argument. Use --help for usage.');
    }
    if ((int)$options['once'] + (int)$options['continuous'] + (int)$options['status'] > 1) {
        throw new InvalidArgumentException('--once, --continuous and --status are mutually exclusive.');
    }
    if (!$options['once'] && !$options['status'] && !$options['help'] && !$explicitDuration) $options['continuous'] = true;
    return $options;
}

/** Keep the local service reserved across child/database lock recycle gaps. */
function pv_ai_service_reserve_supervisor(string $path)
{
    $handle = @fopen($path, 'c+');
    if ($handle === false) throw new RuntimeException('Cannot open the AI supervisor lock; check storage permissions.');
    if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); return null; }
    ftruncate($handle, 0);
    fwrite($handle, (string)getmypid() . PHP_EOL);
    fflush($handle);
    return $handle;
}

/** Fresh child processes clear request-scoped caches and reconnect after outages. */
function pv_ai_service_supervise(array $command, callable $message): int
{
    if (!function_exists('proc_open')) {
        $message('Continuous mode requires proc_open in CLI PHP. Use a systemd service to supervise bounded workers instead.', true);
        return 1;
    }
    $stop = false;
    $child = null;
    $handler = static function () use (&$stop): void { $stop = true; };
    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
    }
    if (function_exists('sapi_windows_set_ctrl_handler')) sapi_windows_set_ctrl_handler($handler);
    // On parent failure do not leave a detached child processing indefinitely.
    register_shutdown_function(static function () use (&$child): void {
        if (is_resource($child)) @proc_terminate($child);
    });
    $failures = 0;
    $message('Continuous AI supervisor started. Worker caches recycle automatically; Ctrl+C stops the service.');
    while (!$stop) {
        $started = hrtime(true);
        $child = @proc_open($command, [0=>['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1=>STDOUT, 2=>STDERR], $pipes, null, null, ['bypass_shell'=>true]);
        $code = 1;
        if (is_resource($child)) {
            $terminatedAt = null;
            do {
                $state = proc_get_status($child);
                if (!$state['running']) { $code = (int)$state['exitcode']; break; }
                if ($stop && $terminatedAt === null) { @proc_terminate($child); $terminatedAt = hrtime(true); }
                if ($terminatedAt !== null && hrtime(true) - $terminatedAt > 10000000000) @proc_terminate($child, 9);
                usleep(100000);
            } while (true);
            $closed = proc_close($child);
            $child = null;
            if ($code < 0) $code = $closed;
        }
        if ($stop || $code === 130) return 130;
        // Argument errors and another owner are deliberate terminal conditions.
        if ($code === 2 || $code === 3) return $code;
        if ($code === 0) { $failures = 0; continue; }
        if (hrtime(true) - $started >= 30000000000) $failures = 0;
        $failures = min(6, $failures + 1);
        $delay = min(30, 2 ** ($failures - 1));
        $message('AI worker exited with code ' . $code . '; retrying in ' . $delay . ' seconds.', true);
        $retryAt = hrtime(true) + $delay * 1000000000;
        while (!$stop && hrtime(true) < $retryAt) usleep(100000);
    }
    return 130;
}

/** Atomic heartbeat replacement prevents readers from observing half a JSON file. */
function pv_ai_service_write_heartbeat(string $path, array $state): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create the AI heartbeat directory.');
    }
    $temporary = tempnam($directory, '.ai-heartbeat-');
    if ($temporary === false) throw new RuntimeException('Cannot create the AI heartbeat file.');
    try {
        @chmod($temporary, 0660 & ~umask());
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json) || !@rename($temporary, $path)) {
            throw new RuntimeException('Cannot write the AI heartbeat file; check storage permissions.');
        }
    } finally { if (is_file($temporary)) @unlink($temporary); }
}

function pv_ai_service_status(array $heartbeat, ?int $owner, int $now): array
{
    $age = isset($heartbeat['heartbeat_at']) ? max(0, $now - (int)$heartbeat['heartbeat_at']) : null;
    $healthy = $owner !== null && $age !== null && $age <= 30
        && (int)($heartbeat['connection_id'] ?? 0) === $owner
        && in_array($heartbeat['state'] ?? '', ['running','maintenance'], true);
    return ['healthy'=>$healthy, 'database_lock_owner'=>$owner, 'heartbeat_age_seconds'=>$age] + $heartbeat;
}
