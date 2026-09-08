<?php
/**
 * Pokemon Vortex NXT local server console.
 * CLI-only live log viewer intended for the XAMPP host machine.
 */
declare(strict_types=1);

// This operator window is intentionally gameplay-only. PHP diagnostics continue
// to be logged by the server, but the console process itself never displays them.
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('PV_SERVER_CONSOLE_CLI', true);

$root = __DIR__;
$configFile = $root . '/config/app.php';
$config = is_file($configFile) ? require $configFile : [];
$consoleCfg = is_array($config['server_console'] ?? null) ? $config['server_console'] : [];
$relativeLog = trim((string)($consoleCfg['log_file'] ?? 'storage/logs/worldserver.log'));
$worldLog = preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $relativeLog)
    ? $relativeLog
    : $root . '/' . ltrim(str_replace('\\', '/', $relativeLog), '/');
$xamppRoot = dirname(dirname($root));
$apacheAccess = $xamppRoot . '/apache/logs/access.log';
$showApacheAccess = in_array('--apache-access', $argv, true);
$gameplayOnly = in_array('--gameplay-only', $argv, true);

$logDir = dirname($worldLog);
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
if (!is_file($worldLog)) @touch($worldLog);

if (function_exists('sapi_windows_vt100_support')) {
    @sapi_windows_vt100_support(STDOUT, true);
}

const C_RESET = "\033[0m";
const C_DIM = "\033[2m";
const C_RED = "\033[91m";
const C_YELLOW = "\033[93m";
const C_GREEN = "\033[92m";
const C_CYAN = "\033[96m";
const C_BLUE = "\033[94m";
const C_MAGENTA = "\033[95m";
const C_WHITE = "\033[97m";

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
    return $line;
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
    echo pv_console_color($line, $source) . $prefix . $line . C_RESET . PHP_EOL;
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
    ];
}

function pv_console_read_source(array &$source, string $label, bool $gameplayOnly): void
{
    $path = (string)$source['path'];
    if (!is_file($path)) {
        $source['pos'] = 0;
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
    while (($line = fgets($fh)) !== false) {
        $pos = (int)ftell($fh);
        pv_console_emit($label, $line, $gameplayOnly);
    }
    fclose($fh);

    $source['pos'] = $pos;
    $source['size'] = $newSize;
}

$version = (string)($config['asset_version'] ?? 'unknown');
$enabled = (bool)($consoleCfg['enabled'] ?? true);
$requestLogging = (bool)($consoleCfg['request_logging'] ?? true);

echo C_CYAN . "============================================================" . C_RESET . PHP_EOL;
echo C_CYAN . "  POKEMON VORTEX NXT - LOCAL WORLD SERVER CONSOLE" . C_RESET . PHP_EOL;
echo C_CYAN . "============================================================" . C_RESET . PHP_EOL;
echo "  Version            : " . $version . PHP_EOL;
echo "  Application logging: " . ($enabled ? 'ENABLED' : 'DISABLED') . PHP_EOL;
echo "  Request traffic    : " . ($requestLogging ? 'ENABLED' : 'DISABLED') . PHP_EOL;
echo "  Presence heartbeats: " . ((bool)($consoleCfg['suppress_presence_polls'] ?? true) ? 'SUPPRESSED' : 'VISIBLE') . PHP_EOL;
echo "  Movement traffic   : SUPPRESSED" . PHP_EOL;
echo "  View mode          : " . ($gameplayOnly ? 'GAMEPLAY ONLY' : 'MEANINGFUL APPLICATION TRAFFIC') . PHP_EOL;
echo "  Activity log       : " . $worldLog . PHP_EOL;
echo "  Diagnostic errors  : HIDDEN FROM CONSOLE" . PHP_EOL;
echo "  Apache access log  : " . ($showApacheAccess ? (is_file($apacheAccess) ? $apacheAccess : 'not detected') : 'hidden (add --apache-access to show)') . PHP_EOL;
echo C_CYAN . "------------------------------------------------------------" . C_RESET . PHP_EOL;
echo "  Ctrl+C closes this console. XAMPP Apache/MySQL keep running." . PHP_EOL;
echo "  Passwords, cookies and security tokens are never written here." . PHP_EOL;
echo C_CYAN . "============================================================" . C_RESET . PHP_EOL . PHP_EOL;

if (!$enabled) {
    echo C_YELLOW . "Server console logging is disabled in config/app.php." . C_RESET . PHP_EOL;
}

foreach (pv_console_tail_lines($worldLog, 30) as $line) {
    pv_console_emit('WORLD', $line, $gameplayOnly);
}
if (filesize($worldLog) > 0) echo C_DIM . "--- live stream ---" . C_RESET . PHP_EOL;

$sources = [
    'WORLD' => pv_console_open_source($worldLog, true),
];
if ($showApacheAccess && is_file($apacheAccess)) $sources['APACHE'] = pv_console_open_source($apacheAccess, true);

while (true) {
    foreach ($sources as $label => &$source) {
        pv_console_read_source($source, $label, $gameplayOnly);
    }
    unset($source);
    usleep(200000);
}
