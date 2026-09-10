<?php
/**
 * Local-only world server console: interactive administration plus live activity.
 * HTTP requests cannot load this entry point or its command handlers.
 * Windows input is owned by the bundled PowerShell editor, with one PHP worker
 * communicating over inherited process pipes. No socket, URL or shell command
 * is used to transport operator commands.
 */
declare(strict_types=1);
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('PV_SERVER_CONSOLE_CLI', true);

function pv_console_usage(): string
{
    return <<<'HELP'
POKEMON VORTEX NXT - LOCAL SERVER CONSOLE

Windows: double-click PokemonVortex_ServerConsole.cmd
Linux/macOS: ./PokemonVortex_ServerConsole.sh
Direct PHP: php server_console.php [options]

  --help                 Show this launcher help without a database connection.
  --command "help"       Execute one command and exit (nonzero on failure).
  --no-color             Disable coloured output.
  --gameplay-only        Hide HTTP request traffic from the activity stream.
  --apache-access        Include the detected XAMPP Apache access log.
  --tail-only            Open the original read-only live activity viewer.

Type help for the complete command list, help <command> for syntax, and exit to
close the console. Closing the console does not stop Apache or MySQL. Commands
run with the configured local operator rank; browser accounts cannot execute
console commands. There is no chat, broadcast or remote command endpoint.

Use quotes around player names or arguments containing spaces. Arrow keys edit
and recall commands in the interactive editor. History is kept only in memory;
credential, confirmation and account commands are excluded. Generated temporary
credentials appear only in their command response; keep this local window private.

Redirected input executes one command per line, then exits on EOF:
  php server_console.php --no-color < commands.txt

See docs/SERVER_COMMANDS.md for setup, command coverage and operating details.
HELP;
}

$options = ['help'=>false, 'worker'=>false, 'tail-only'=>false, 'no-color'=>false,
    'gameplay-only'=>false, 'apache-access'=>false, 'command'=>null];
for ($i = 1; $i < count($argv); $i++) {
    $arg = (string)$argv[$i];
    if ($arg === '--command' || str_starts_with($arg, '--command=')) {
        if ($options['command'] !== null) { fwrite(STDERR, "Only one --command is allowed.\n"); exit(2); }
        if ($arg === '--command') {
            if (!isset($argv[$i + 1])) { fwrite(STDERR, "--command requires a quoted command.\n"); exit(2); }
            $options['command'] = (string)$argv[++$i];
        } else $options['command'] = substr($arg, 10);
    } elseif (str_starts_with($arg, '--') && array_key_exists(substr($arg, 2), $options)) {
        $options[substr($arg, 2)] = true;
    } else { fwrite(STDERR, "Unknown option. Use --help for launcher syntax.\n"); exit(2); }
}
if ($options['help']) { echo pv_console_usage() . PHP_EOL; exit(0); }
if ($options['tail-only'] && $options['command'] !== null) {
    fwrite(STDERR, "--tail-only cannot be combined with --command.\n"); exit(2);
}

$stdinTty = function_exists('stream_isatty') && @stream_isatty(STDIN);
$stdoutTty = function_exists('stream_isatty') && @stream_isatty(STDOUT);
// Direct invocation on Windows also gets the working key editor. The .cmd file
// normally calls that editor itself. Never put operator command text in a shell.
if (PHP_OS_FAMILY === 'Windows' && $stdinTty && $stdoutTty && !$options['worker']
    && !$options['tail-only'] && $options['command'] === null) {
    $ps = (getenv('SystemRoot') ?: 'C:\\Windows') . '/System32/WindowsPowerShell/v1.0/powershell.exe';
    $helper = __DIR__ . '/PokemonVortex_ServerConsole.ps1';
    if (!is_file($ps) || !is_file($helper) || !function_exists('proc_open')) {
        fwrite(STDERR, "The Windows console requires Windows PowerShell and proc_open. Use --command or --tail-only.\n");
        exit(2);
    }
    $launch = [$ps, '-NoLogo', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $helper, '-PhpExe', PHP_BINARY];
    foreach (array_slice($argv, 1) as $a) $launch[] = $a;
    $child = proc_open($launch, [0=>STDIN, 1=>STDOUT, 2=>STDERR], $pipes, __DIR__, null, ['bypass_shell'=>true]);
    if (!is_resource($child)) { fwrite(STDERR, "Could not start the Windows input editor.\n"); exit(2); }
    exit(proc_close($child));
}

$root = __DIR__;
$configFile = $root . '/config/app.php';
$config = is_file($configFile) ? require $configFile : [];
$consoleCfg = is_array($config['server_console'] ?? null) ? $config['server_console'] : [];
$relativeLog = trim((string)($consoleCfg['log_file'] ?? 'storage/logs/worldserver.log'));
$worldLog = preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $relativeLog)
    ? $relativeLog : $root . '/' . ltrim(str_replace('\\', '/', $relativeLog), '/');
$apacheAccess = dirname(dirname($root)) . '/apache/logs/access.log';
$showApacheAccess = $options['apache-access'];
$gameplayOnly = $options['gameplay-only'];
$logDir = dirname($worldLog);
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
if (!is_file($worldLog)) @touch($worldLog);
$vtEnabled = $stdoutTty;
if (function_exists('sapi_windows_vt100_support')) $vtEnabled = @sapi_windows_vt100_support(STDOUT, true);
$GLOBALS['pv_console_colour'] = !$options['no-color'] && $stdoutTty && $vtEnabled;
$GLOBALS['pv_console_worker'] = (bool)$options['worker'];
$GLOBALS['pv_console_output'] = [];
$GLOBALS['pv_console_editor_redraw'] = null;

const C_RESET = "\033[0m";
const C_DIM = "\033[2m";
const C_RED = "\033[91m";
const C_YELLOW = "\033[93m";
const C_GREEN = "\033[92m";
const C_CYAN = "\033[96m";
const C_BLUE = "\033[94m";
const C_MAGENTA = "\033[95m";
const C_WHITE = "\033[97m";

/** Untrusted log/player text cannot inject terminal escape sequences. */
function pv_console_safe_text(string $text): string
{
    $text = preg_replace('/\x1B(?:\[[0-?]*[ -\/]*[@-~]|\][^\x07\x1B]*(?:\x07|\x1B\\\\)?)/', '', $text) ?? $text;
    return preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text) ?? $text;
}

function pv_console_write_line(string $line, string $colour = C_WHITE): void
{
    $line = pv_console_safe_text($line);
    foreach (preg_split('/\r\n|\r|\n/', $line) ?: [''] as $part) {
        if ($GLOBALS['pv_console_worker']) {
            $names = [C_RED=>'red', C_YELLOW=>'yellow', C_GREEN=>'green', C_CYAN=>'cyan', C_DIM=>'dim', C_MAGENTA=>'magenta'];
            $GLOBALS['pv_console_output'][] = ['text'=>$part, 'colour'=>$names[$colour] ?? 'white'];
        } else {
            if (is_callable($GLOBALS['pv_console_editor_redraw'])) echo "\r\033[2K";
            echo ($GLOBALS['pv_console_colour'] ? $colour : '') . $part
                . ($GLOBALS['pv_console_colour'] ? C_RESET : '') . PHP_EOL;
            if (is_callable($GLOBALS['pv_console_editor_redraw'])) ($GLOBALS['pv_console_editor_redraw'])();
        }
    }
}

/** Do not persist or recall potentially sensitive operator commands. */
function pv_console_history_safe(string $line): bool
{
    return !preg_match('/(?:password|passwd|secret|token|credential|\bconfirm\b|\bcreate(?:account)?\b|\bresetpassword\b)/i', $line);
}

function pv_console_color(string $line, string $source): string
{
    if (str_contains($line, '[AUTH      ]')) return C_MAGENTA;
    if (str_contains($line, '[MOVE      ]') || str_contains($line, '[WORLD     ]')) return C_GREEN;
    if (str_contains($line, '[BATTLE    ]') || str_contains($line, '[PVP       ]') || str_contains($line, '[WILD      ]')) return C_YELLOW;
    if (str_contains($line, '[TRADE     ]') || str_contains($line, '[SHOP      ]') || str_contains($line, '[LAB       ]')) return C_CYAN;
    if (str_contains($line, '[HTTP      ]') || $source === 'APACHE') return C_DIM;
    return C_WHITE;
}

function pv_console_redact_line(string $line): string
{
    $line = preg_replace('/\b((?:proxy-)?authorization)\s*(?:=|:)\s*[^|\r\n]*/i', '$1=[REDACTED]', $line) ?? $line;
    $line = preg_replace('/\b((?:set-)?cookie)\s*(?:=|:)\s*[^|\r\n]*/i', '$1=[REDACTED]', $line) ?? $line;
    $line = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9+\/=_\-.~]+/i', '$1 [REDACTED]', $line) ?? $line;
    $sensitive='(?:password|passwd|pwd|mypassword|(?:access_|refresh_|id_|auth_|csrf_|xsrf_|action_|reset_)?token|session(?:_?id)?|sessid|phpsessid|cookie|authorization|secret|client_secret|api_?key|private_key|otp)';
    $line=preg_replace('/(' . $sensitive . ')\s*(?:=|:|=>)\s*(?:\'[^\']*\'|"[^"]*"|[^\s,;&|]+)/i','$1=[REDACTED]',$line)??$line;
    $line=preg_replace('/([?&]' . $sensitive . '=)[^&\s]+/i','$1[REDACTED]',$line)??$line;
    return pv_console_safe_text($line);
}

function pv_console_emit(string $source, string $line, bool $gameplayOnly): void
{
    $line = pv_console_redact_line(rtrim($line, "\r\n"));
    if ($line === '') return;

    // Hide diagnostic noise from both current and historical v23.6.x logs.
    // The operator console is a player/gameplay activity stream; PHP, Apache,
    // application and HTTP failure diagnostics stay in their normal log files.
    if ($source === 'PHPERR' || $source === 'APACHE-ERR') return;
    if (str_contains($line, '[PHP       ]') || str_contains($line, '[APP       ]')) return;
    if (str_contains($line, '[ERROR]') || str_contains($line, '[ERROR ]') || str_contains($line, '[WARN ]')) return;
    if (preg_match('/\bPHP (?:Warning|Notice|Deprecated|Fatal error|Parse error|Recoverable fatal error):/i', $line)) return;
    if ($source === 'WORLD' && str_contains($line, '[HTTP      ]') && preg_match('/\bstatus=[45][0-9]{2}\b/', $line)) return;
    if ($source === 'APACHE' && preg_match('/\s[45][0-9]{2}\s/', $line)) return;

    // Hide historical presence and movement noise left by older v23.6.x logs.
    // The normal console is an operator activity stream, not a per-step movement
    // trace. Literal raw requests remain available explicitly via --apache-access.
    if ($source === 'WORLD') {
        if (str_contains($line, '[MOVE      ]')) return;
        if (str_contains($line, '[HTTP      ]') && str_contains($line, 'action=presence_poll')) return;
        if (str_contains($line, '[HTTP      ]') && (
            str_contains($line, 'action=vortex_move') ||
            str_contains($line, 'action=region_move') ||
            str_contains($line, '/map_move.php') ||
            str_contains($line, '/world_map_move.php')
        )) return;
    }
    if ($gameplayOnly && (str_contains($line, '[HTTP      ]') || $source === 'APACHE')) return;
    $prefix = $source === 'WORLD' ? '' : '[' . str_pad($source, 10) . '] ';
    pv_console_write_line($prefix . $line, pv_console_color($line, $source));
}

function pv_console_tail_lines(string $path, int $maxLines = 35): array
{
    if (!is_file($path) || filesize($path) === 0) return [];
    $fh = @fopen($path, 'rb');
    if (!$fh) return [];
    $size = filesize($path);
    $chunk = min($size, 256 * 1024);
    fseek($fh, -$chunk, SEEK_END);
    $data = (string)stream_get_contents($fh);
    fclose($fh);
    $lines = preg_split('/\r\n|\r|\n/', $data) ?: [];
    if ($chunk < $size && $lines) array_shift($lines);
    return array_slice(array_values(array_filter($lines, static fn($v) => $v !== '')), -$maxLines);
}

function pv_console_rotation_signature(string $path): string
{
    $rotated = $path . '.1';
    if (!is_file($rotated)) return '';
    clearstatcache(true, $rotated);
    $size = (int)@filesize($rotated);
    $fh = @fopen($rotated, 'rb');
    if (!$fh) return (string)$size;
    $head = (string)fread($fh, 192);
    fclose($fh);
    return $size . ':' . hash('sha256', $head);
}

function pv_console_open_source(string $path, bool $fromEnd = true): array
{
    clearstatcache(true, $path);
    $size = is_file($path) ? (int)@filesize($path) : 0;
    return [
        'path' => $path,
        'pos' => $fromEnd ? $size : 0,
        'size' => $size,
        'rotation' => pv_console_rotation_signature($path),
        'pending' => '',
    ];
}

function pv_console_read_source(array &$source, string $label, bool $gameplayOnly): void
{
    $path = (string)$source['path'];
    if (!is_file($path)) {
        $source['pos'] = 0;
        $source['pending'] = '';
        $source['size'] = 0;
        $source['rotation'] = pv_console_rotation_signature($path);
        return;
    }

    clearstatcache(true, $path);
    $newSize = (int)@filesize($path);
    $rotation = pv_console_rotation_signature($path);

    // The console deliberately does not keep the log file open between polls.
    // This matters on Windows/XAMPP because an open reader can prevent the
    // application logger from renaming worldserver.log during rotation.
    if ($rotation !== (string)($source['rotation'] ?? '') || $newSize < (int)($source['pos'] ?? 0)) {
        $source['pos'] = 0;
        $source['pending'] = '';
    }
    $source['rotation'] = $rotation;

    $pos = max(0, min((int)($source['pos'] ?? 0), $newSize));
    if ($newSize <= $pos) {
        $source['size'] = $newSize;
        return;
    }

    $fh = @fopen($path, 'rb');
    if (!$fh) return;
    if ($pos > 0) @fseek($fh, $pos, SEEK_SET);
    // Bound each poll so busy logs never starve the command prompt.
    $data = (string)fread($fh, 256 * 1024);
    $pos = (int)ftell($fh);
    $data = (string)($source['pending'] ?? '') . $data;
    $records = explode("\n", $data);
    $source['pending'] = (string)array_pop($records);
    if (strlen($source['pending']) > 16384) {
        pv_console_emit($label, substr($source['pending'], 0, 16384) . ' [truncated]', $gameplayOnly);
        $source['pending'] = '';
    }
    foreach ($records as $line) {
        pv_console_emit($label, substr($line, 0, 16384), $gameplayOnly);
    }
    fclose($fh);

    $source['pos'] = $pos;
    $source['size'] = $newSize;
}


function pv_console_banner(array $config, array $consoleCfg, string $worldLog, bool $gameplayOnly, bool $showApacheAccess, string $apacheAccess, bool $tailOnly): void
{
    pv_console_write_line('============================================================', C_CYAN);
    pv_console_write_line('  POKEMON VORTEX NXT - LOCAL WORLD SERVER CONSOLE', C_CYAN);
    pv_console_write_line('============================================================', C_CYAN);
    pv_console_write_line('  Version            : ' . (string)($config['asset_version'] ?? 'unknown'));
    pv_console_write_line('  Application logging: ' . ((bool)($consoleCfg['enabled'] ?? true) ? 'ENABLED' : 'DISABLED'));
    pv_console_write_line('  Request traffic    : ' . ((bool)($consoleCfg['request_logging'] ?? true) ? 'ENABLED' : 'DISABLED'));
    pv_console_write_line('  Movement traffic   : SUPPRESSED');
    pv_console_write_line('  View mode          : ' . ($gameplayOnly ? 'GAMEPLAY ONLY' : 'MEANINGFUL APPLICATION TRAFFIC'));
    pv_console_write_line('  Activity log       : ' . $worldLog);
    pv_console_write_line('  Apache access log  : ' . ($showApacheAccess ? (is_file($apacheAccess) ? $apacheAccess : 'not detected') : 'hidden (add --apache-access to show)'));
    pv_console_write_line('  Command input      : ' . ($tailOnly ? 'DISABLED (--tail-only)' : 'LOCAL OPERATOR ONLY - type help'));
    pv_console_write_line('  Chat commands      : EXCLUDED');
    pv_console_write_line('------------------------------------------------------------', C_CYAN);
    pv_console_write_line('  Ctrl+C closes the console; Apache/MySQL keep running.');
    pv_console_write_line('  Activity logs redact credentials. Temporary credentials from');
    pv_console_write_line('  account commands appear only in the local command response.');
    pv_console_write_line('============================================================', C_CYAN);
}

function pv_console_run_command(string $line, array &$context): bool
{
    if (trim($line) === '') return true;
    if (strlen($line) > 8192 || preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $line)) {
        pv_console_write_line('ERROR: Commands must be at most 8192 bytes and contain no control characters.', C_RED);
        return false;
    }
    try {
        $context['last_ok'] = true;
        $bufferLevel = ob_get_level();
        ob_start();
        try { $result = pv_admin_dispatch($line, $context); }
        finally { while (ob_get_level() > $bufferLevel) ob_end_clean(); }
        foreach ($result as $out) pv_console_write_line((string)$out, ($context['last_ok'] ?? true) ? C_CYAN : C_RED);
        return (bool)($context['last_ok'] ?? true);
    } catch (Throwable $error) {
        // Known command errors are normally handled by the dispatcher. Never
        // print arbitrary driver exception messages (they can expose secrets).
        pv_console_write_line('ERROR: The command could not complete. Check the local server error log.', C_RED);
        return false;
    }
}

function pv_console_poll(array &$sources, array &$context, bool $gameplayOnly, bool $tailOnly): void
{
    foreach ($sources as $label => &$source) pv_console_read_source($source, $label, $gameplayOnly);
    unset($source);
    if ($tailOnly) return;
    try {
        $bufferLevel = ob_get_level();
        ob_start();
        try { $tickLines = pv_admin_tick($context); }
        finally { while (ob_get_level() > $bufferLevel) ob_end_clean(); }
        foreach ($tickLines as $line) pv_console_write_line((string)$line, C_YELLOW);
    } catch (Throwable $error) {
        if (!($context['_console_tick_failed'] ?? false)) {
            pv_console_write_line('Scheduled command polling is unavailable; check database availability and the server error log.', C_YELLOW);
            $context['_console_tick_failed'] = true;
        }
    }
}

function pv_console_stty(array $arguments): ?string
{
    if (!function_exists('proc_open')) return null;
    $process = @proc_open(array_merge(['stty'], $arguments), [0=>STDIN, 1=>['pipe','w'], 2=>['file','/dev/null','a']], $pipes);
    if (!is_resource($process)) return null;
    $result = (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return proc_close($process) === 0 ? trim($result) : null;
}

/** Native Unix terminal editor; avoids differences in PHP's libedit/readline. */
function pv_console_unix_editor(array &$context, array &$sources, bool $gameplayOnly): bool
{
    $saved = pv_console_stty(['-g']);
    if ($saved === null || pv_console_stty(['-icanon','-echo','-isig','-ixon','min','0','time','0']) === null) return false;
    $restored = false;
    $restore = static function () use ($saved, &$restored): void {
        if (!$restored) { pv_console_stty([$saved]); @stream_set_blocking(STDIN, true); $restored = true; }
    };
    register_shutdown_function($restore);
    @stream_set_blocking(STDIN, false);
    @stream_set_read_buffer(STDIN, 0);
    $characters = []; $cursor = 0; $start = 0; $pending = ''; $history = []; $historyIndex = 0; $draft = [];
    $columns = 100; $lastSize = 0; $escapeAt = 0.0;
    $width = static function (string $s): int {
        return function_exists('mb_strwidth') ? mb_strwidth($s, 'UTF-8') : count(preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($s));
    };
    $draw = static function () use (&$characters, &$cursor, &$start, &$columns, $width): void {
        $available = max(4, $columns - 9);
        $start = min($start, $cursor);
        while ($start < $cursor && $width(implode('', array_slice($characters, $start, $cursor-$start))) >= $available) $start++;
        $visible = ''; $used = 0;
        for ($i=$start; $i<count($characters); $i++) {
            $charWidth = max(0, $width($characters[$i]));
            if ($used + $charWidth > $available) break;
            $visible .= $characters[$i]; $used += $charWidth;
        }
        echo "\r\033[2K" . ($GLOBALS['pv_console_colour'] ? C_CYAN : '') . 'vortex> '
            . ($GLOBALS['pv_console_colour'] ? C_RESET : '') . $visible;
        $offset = 8 + $width(implode('', array_slice($characters, $start, $cursor-$start)));
        echo "\r\033[" . ($offset + 1) . 'G';
        flush();
    };
    $GLOBALS['pv_console_editor_redraw'] = $draw;
    try {
        $draw();
        while (!($context['exit'] ?? false) && !($context['restart_worker'] ?? false)) {
            if ($lastSize !== time()) {
                $lastSize = time();
                $size = pv_console_stty(['size']);
                if ($size !== null && preg_match('/^\d+\s+(\d+)$/', $size, $m)) {
                    $newColumns = max(20, (int)$m[1]);
                    if ((int)$m[1] > 0 && $newColumns !== $columns) { $columns = $newColumns; $draw(); }
                }
            }
            $read = [STDIN]; $write = null; $except = null;
            $ready = @stream_select($read, $write, $except, 0, 100000);
            if ($ready > 0) {
                $chunk = fread(STDIN, 4096);
                if ($chunk === '' && feof(STDIN)) { $context['exit'] = true; break; }
                $pending .= $chunk;
            }
            while ($pending !== '') {
                $key = ''; $char = $pending[0]; $consume = 1;
                if ($char === "\033") {
                    if (preg_match('/^\x1B(?:\[|O)(?:[0-9;]*)([A-Za-z~])/', $pending, $m)) {
                        $consume = strlen($m[0]);
                        $map = ["\033[A"=>'up',"\033[B"=>'down',"\033[C"=>'right',"\033[D"=>'left',
                            "\033[H"=>'home',"\033[F"=>'end',"\033OH"=>'home',"\033OF"=>'end',
                            "\033[1~"=>'home',"\033[4~"=>'end',"\033[7~"=>'home',"\033[8~"=>'end',"\033[3~"=>'delete'];
                        $key = $map[$m[0]] ?? 'ignore';
                    } elseif (strlen($pending) < 16 && ($pending === "\033" || str_starts_with($pending,"\033["))) {
                        if ($escapeAt === 0.0) $escapeAt = microtime(true);
                        if (microtime(true)-$escapeAt < 0.1) break;
                        $key = 'escape'; $consume = strlen($pending);
                    } else $key = 'ignore';
                } elseif ($char === "\r" || $char === "\n") $key = 'enter';
                elseif ($char === "\x7f" || $char === "\x08") $key = 'backspace';
                elseif ($char === "\x03" || ($char === "\x04" && !$characters)) $key = 'exit';
                elseif ($char === "\x01") $key = 'home';
                elseif ($char === "\x05") $key = 'end';
                elseif ($char === "\x15") $key = 'escape';
                elseif (ord($char) < 32) $key = 'ignore';
                elseif (ord($char) >= 128) {
                    if (preg_match('/^./us', $pending, $m)) { $char = $m[0]; $consume = strlen($char); }
                    elseif (strlen($pending) < 4) break;
                    else $key = 'ignore';
                }
                $pending = substr($pending, $consume); $escapeAt = 0.0;
                if ($key === 'exit') { $context['exit'] = true; break; }
                if ($key === 'enter') {
                    $line = implode('', $characters);
                    $GLOBALS['pv_console_editor_redraw'] = null;
                    echo "\r\033[2K";
                    if (trim($line) !== '') {
                        pv_console_write_line(pv_console_history_safe($line) ? 'vortex> ' . $line : 'vortex> [sensitive command submitted]', C_DIM);
                        if (pv_console_history_safe($line) && (end($history) ?: '') !== $line) {
                            $history[] = $line;
                            if (count($history)>100) array_shift($history);
                        }
                        pv_console_run_command($line, $context);
                    }
                    $characters=[]; $cursor=0; $start=0; $historyIndex=count($history); $draft=[];
                    $GLOBALS['pv_console_editor_redraw'] = $draw;
                    if (($context['exit'] ?? false) || ($context['restart_worker'] ?? false)) break;
                } elseif ($key === 'backspace' && $cursor > 0) { array_splice($characters, --$cursor, 1); }
                elseif ($key === 'delete' && $cursor < count($characters)) array_splice($characters,$cursor,1);
                elseif ($key === 'left') $cursor=max(0,$cursor-1);
                elseif ($key === 'right') $cursor=min(count($characters),$cursor+1);
                elseif ($key === 'home') $cursor=0;
                elseif ($key === 'end') $cursor=count($characters);
                elseif ($key === 'escape') { $characters=[]; $cursor=0; $start=0; $historyIndex=count($history); $draft=[]; }
                elseif ($key === 'up' && $historyIndex>0) {
                    if ($historyIndex===count($history)) $draft=$characters;
                    $characters=preg_split('//u',$history[--$historyIndex],-1,PREG_SPLIT_NO_EMPTY) ?: [];
                    $cursor=count($characters); $start=0;
                } elseif ($key === 'down' && $historyIndex<count($history)) {
                    $historyIndex++;
                    $characters=$historyIndex===count($history) ? $draft : (preg_split('//u',$history[$historyIndex],-1,PREG_SPLIT_NO_EMPTY) ?: []);
                    $cursor=count($characters); $start=0;
                } elseif ($key === '' && strlen(implode('',$characters)) + strlen($char) <=4096) {
                    array_splice($characters,$cursor++,0,[$char]);
                }
                $draw();
            }
            if (!($context['exit'] ?? false) && !($context['restart_worker'] ?? false)) pv_console_poll($sources,$context,$gameplayOnly,false);
        }
    } finally {
        $GLOBALS['pv_console_editor_redraw'] = null;
        echo "\r\033[2K";
        $restore();
    }
    return true;
}

function pv_console_worker_reply(array $context, bool $ok = true): void
{
    $payload = ['type'=>'response', 'ok'=>$ok, 'exit'=>(bool)($context['exit'] ?? false), 'restart'=>(bool)($context['restart_worker'] ?? false),
        'prompt'=>'vortex> ', 'lines'=>$GLOBALS['pv_console_output']];
    $GLOBALS['pv_console_output'] = [];
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
    flush();
}

$context = ['exit'=>false];
if (!$options['tail-only']) {
    try {
        // Suppress accidental output from legacy bootstrap/config includes so
        // the pipe protocol always contains one complete JSON response per line.
        ob_start();
        require_once __DIR__ . '/includes/admin/core.php';
        $context = pv_admin_boot();
        ob_end_clean();
    } catch (Throwable $error) {
        if (ob_get_level()) ob_end_clean();
        pv_console_write_line('ERROR: The command engine could not start. Check config/app.php and the local server error log. Use --tail-only to view activity.', C_RED);
        if ($options['worker']) pv_console_worker_reply(['exit'=>true], false);
        exit(1);
    }
}

if ($options['command'] !== null) {
    $ok = pv_console_run_command((string)$options['command'], $context);
    if ($options['worker']) pv_console_worker_reply(array_merge($context, ['exit'=>true]), $ok);
    exit($ok ? 0 : 1);
}

// Piped scripts are finite and intentionally omit the live log stream. This
// avoids waiting forever after EOF or mixing activity with command responses.
if (!$options['worker'] && !$options['tail-only'] && !$stdinTty) {
    $ok = true;
    while (($line = fgets(STDIN, 8194)) !== false) {
        if (strlen($line) > 8192 && !str_ends_with($line, "\n")) {
            while (($rest = fgets(STDIN, 8194)) !== false && !str_ends_with($rest, "\n")) {}
            pv_console_write_line('ERROR: Input line exceeds 8192 bytes.', C_RED); $ok = false; continue;
        }
        if (!pv_console_run_command(rtrim($line, "\r\n"), $context)) $ok = false;
        if (($context['exit'] ?? false) || ($context['restart_worker'] ?? false)) break;
    }
    exit($ok ? 0 : 1);
}

pv_console_banner($config, $consoleCfg, $worldLog, $gameplayOnly, $showApacheAccess, $apacheAccess, (bool)$options['tail-only']);
foreach (pv_console_tail_lines($worldLog, 30) as $line) pv_console_emit('WORLD', $line, $gameplayOnly);
$sources = ['WORLD'=>pv_console_open_source($worldLog, true)];
if ($showApacheAccess && is_file($apacheAccess)) $sources['APACHE'] = pv_console_open_source($apacheAccess, true);

if ($options['worker']) {
    // The frontend sends exactly one outstanding request. A command is never
    // retried automatically: if its worker dies the operator must inspect state.
    pv_console_worker_reply($context);
    while (($raw = fgets(STDIN, 32770)) !== false) {
        $ok = true;
        try {
            if (strlen($raw) > 32768 && !str_ends_with($raw, "\n")) {
                while (($rest = fgets(STDIN, 32770)) !== false && !str_ends_with($rest, "\n")) {}
                throw new InvalidArgumentException('Oversized request.');
            }
            $request = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($request) || !isset($request['type'])) throw new InvalidArgumentException('Invalid request.');
            if ($request['type'] === 'command' && isset($request['command']) && is_string($request['command'])) {
                $ok = pv_console_run_command($request['command'], $context);
            } elseif ($request['type'] === 'close') {
                $context['exit'] = true;
            } elseif ($request['type'] !== 'poll') throw new InvalidArgumentException('Invalid request type.');
            if (!($context['exit'] ?? false) && !($context['restart_worker'] ?? false)) pv_console_poll($sources, $context, $gameplayOnly, (bool)$options['tail-only']);
        } catch (Throwable $error) {
            pv_console_write_line('ERROR: Invalid local console request.', C_RED); $ok = false;
        }
        pv_console_worker_reply($context, $ok);
        if (($context['exit'] ?? false) || ($context['restart_worker'] ?? false)) break;
    }
    exit(0);
}

if ($options['tail-only']) {
    while (true) { pv_console_poll($sources, $context, $gameplayOnly, true); usleep(200000); }
}

if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, static function () use (&$context): void { $context['exit'] = true; });
    pcntl_signal(SIGTERM, static function () use (&$context): void { $context['exit'] = true; });
}

if (!pv_console_unix_editor($context, $sources, $gameplayOnly)) {
    pv_console_write_line('Basic input mode: logs refresh after each command. The stty utility enables simultaneous live output.', C_YELLOW);
    while (!($context['exit'] ?? false) && !($context['restart_worker'] ?? false)) {
        pv_console_poll($sources, $context, $gameplayOnly, false);
        echo 'vortex> ';
        $line = fgets(STDIN);
        if ($line === false) break;
        pv_console_run_command(rtrim($line, "\r\n"), $context);
    }
}
if ($context['restart_worker'] ?? false) {
    pv_console_write_line(getenv('PV_CONSOLE_SUPERVISED') === '1'
        ? 'Reloading the command process. Selected trainer and pending confirmation are cleared.'
        : 'Reload is ready. Reopen this console to load the updated PHP files and configuration.', C_YELLOW);
    exit(75);
}
pv_console_write_line('Console closed. Apache and MySQL remain running.', C_DIM);
