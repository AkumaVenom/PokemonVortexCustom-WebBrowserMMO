<?php
/**
 * Local operator activity stream for the Pokémon Vortex XAMPP server console.
 *
 * This file is loaded by bootstrap.php for web requests. It writes a concise,
 * human-readable activity stream without storing passwords, session ids,
 * cookies, CSRF/action tokens, password-reset tokens, or raw request bodies.
 */

declare(strict_types=1);

if (!defined('PV_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

function pv_server_console_settings(): array
{
    $cfg = pv_config('server_console', []);
    return is_array($cfg) ? $cfg : [];
}

function pv_server_console_enabled(): bool
{
    return (bool)(pv_server_console_settings()['enabled'] ?? true);
}

function pv_server_console_log_path(): string
{
    $configured = trim((string)(pv_server_console_settings()['log_file'] ?? ''));
    if ($configured !== '') {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $configured)) {
            return $configured;
        }
        return dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $configured), '/');
    }
    return dirname(__DIR__) . '/storage/logs/worldserver.log';
}

function pv_server_console_request_id(): string
{
    if (!isset($GLOBALS['pv_server_console_request_id'])) {
        try {
            $GLOBALS['pv_server_console_request_id'] = bin2hex(random_bytes(4));
        } catch (Throwable $e) {
            $GLOBALS['pv_server_console_request_id'] = substr(hash('sha256', uniqid('', true)), 0, 8);
        }
    }
    return (string)$GLOBALS['pv_server_console_request_id'];
}

function pv_server_console_clean_text($value, int $limit = 180): string
{
    if ($value === null) return '';
    if (is_bool($value)) return $value ? 'true' : 'false';
    if (is_int($value) || is_float($value)) return (string)$value;
    if (!is_string($value)) return '[complex]';
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    if (function_exists('mb_substr')) return mb_substr($value, 0, $limit, 'UTF-8');
    return substr($value, 0, $limit);
}

function pv_server_console_sensitive_key(string $key): bool
{
    $key = strtolower(trim($key));
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;
    $exact = [
        'password','pass','passwd','pwd','mypassword','cookie','set_cookie','session','session_id','sessionid','sessid',
        'phpsessid','csrf','csrf_token','xsrf_token','token','access_token','refresh_token','id_token','auth_token',
        'action_token','reset_token','secret','client_secret','authorization','proxy_authorization','auth','hash','otp',
        'key','api_key','apikey','private_key','signature'
    ];
    if (in_array($normalized, $exact, true)) return true;
    foreach ([
        'password','passwd','cookie','session_id','sessionid','phpsessid','csrf','xsrf','action_token','reset_token',
        'access_token','refresh_token','id_token','auth_token','authorization','client_secret','api_key','private_key'
    ] as $needle) {
        if (str_contains($normalized, $needle)) return true;
    }
    return false;
}

function pv_server_console_redact_message(string $message): string
{
    $message = pv_server_console_clean_text($message, 360);

    // Header-shaped secrets may contain spaces (for example "Authorization: Bearer ...")
    // so mask the entire header value before applying generic key/value redaction.
    $message = preg_replace('/\b((?:proxy-)?authorization)\s*(?:=|:)\s*[^|\r\n]*/i', '$1=[REDACTED]', $message) ?? $message;
    $message = preg_replace('/\b((?:set-)?cookie)\s*(?:=|:)\s*[^|\r\n]*/i', '$1=[REDACTED]', $message) ?? $message;
    $message = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9+\/=_\-.~]+/i', '$1 [REDACTED]', $message) ?? $message;

    $sensitive = '(?:password|passwd|pwd|mypassword|(?:access_|refresh_|id_|auth_|csrf_|xsrf_|action_|reset_)?token|session(?:_?id)?|sessid|phpsessid|cookie|authorization|secret|client_secret|api_?key|private_key|otp)';
    $message = preg_replace('/(' . $sensitive . ')\s*(?:=|:|=>)\s*(?:\'[^\']*\'|"[^"]*"|[^\s,;&|]+)/i', '$1=[REDACTED]', $message) ?? $message;
    $message = preg_replace('/([?&]' . $sensitive . '=)[^&\s]+/i', '$1[REDACTED]', $message) ?? $message;
    return $message;
}

function pv_server_console_context(array $context): string
{
    $parts = [];
    foreach ($context as $key => $value) {
        $key = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$key) ?: 'field';
        if (pv_server_console_sensitive_key($key)) continue;
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $subKey => $subValue) {
                if (pv_server_console_sensitive_key((string)$subKey)) continue;
                if (is_scalar($subValue) || $subValue === null) {
                    $safe[] = pv_server_console_redact_message(pv_server_console_clean_text($subValue, 60));
                }
                if (count($safe) >= 12) break;
            }
            $text = implode(',', $safe);
        } else {
            $text = pv_server_console_redact_message(pv_server_console_clean_text($value));
        }
        if ($text === '') continue;
        if (preg_match('/[\s=\[\]"]/', $text)) {
            $text = '"' . str_replace('"', "'", $text) . '"';
        }
        $parts[] = $key . '=' . $text;
    }
    return implode(' ', $parts);
}

function pv_server_console_actor(): array
{
    $uid = max(0, (int)($_SESSION['myid'] ?? 0));
    $username = pv_server_console_clean_text((string)($_SESSION['myuser'] ?? ''), 48);
    return [
        'trainer' => $username !== '' ? $username : 'guest',
        'uid' => $uid,
        'ip' => pv_server_console_clean_text(pv_client_ip(), 64),
    ];
}

function pv_server_console_rotate_if_needed(string $path): void
{
    $settings = pv_server_console_settings();
    $maxBytes = max(1024 * 1024, (int)($settings['max_file_bytes'] ?? 20 * 1024 * 1024));
    $keep = max(1, min(9, (int)($settings['retained_files'] ?? 5)));
    clearstatcache(true, $path);
    if (!is_file($path) || (int)@filesize($path) < $maxBytes) return;

    $lockPath = $path . '.rotate.lock';
    $lock = @fopen($lockPath, 'c');
    if (!$lock) return;
    if (!@flock($lock, LOCK_EX)) { fclose($lock); return; }
    clearstatcache(true, $path);
    if (is_file($path) && (int)@filesize($path) >= $maxBytes) {
        for ($i = $keep; $i >= 1; $i--) {
            $from = $i === 1 ? $path : $path . '.' . ($i - 1);
            $to = $path . '.' . $i;
            if (is_file($from)) {
                if (is_file($to)) @unlink($to);
                @rename($from, $to);
            }
        }
    }
    @flock($lock, LOCK_UN);
    fclose($lock);
}

function pv_server_log(string $level, string $channel, string $message, array $context = []): void
{
    // Operator logging is strictly observational. A logging failure must never
    // interrupt, roll back, or alter gameplay/account state.
    try {
        if (!pv_server_console_enabled()) return;

        $level = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $level) ?: 'INFO', 0, 5));
        $channel = strtoupper(substr(preg_replace('/[^A-Za-z0-9_-]/', '', $channel) ?: 'SYSTEM', 0, 10));
        $settings = pv_server_console_settings();

        if ((bool)($settings['suppress_diagnostics'] ?? true)) {
            if (in_array($channel, ['PHP', 'APP'], true)) return;
            if ($channel === 'HTTP' && in_array($level, ['WARN', 'ERROR'], true)) return;
        }

        $path = pv_server_console_log_path();
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) return;

        pv_server_console_rotate_if_needed($path);
        $actor = pv_server_console_actor();
        $context = array_merge([
            'rid' => pv_server_console_request_id(),
            'trainer' => $actor['trainer'],
            'uid' => $actor['uid'],
            'ip' => $actor['ip'],
        ], $context);

        $micro = microtime(true);
        $millis = (int)(($micro - floor($micro)) * 1000);
        $timestamp = gmdate('Y-m-d H:i:s', (int)$micro) . '.' . str_pad((string)$millis, 3, '0', STR_PAD_LEFT);
        $line = sprintf(
            "[%s UTC] [%-5s] [%-10s] %s",
            $timestamp,
            $level,
            $channel,
            pv_server_console_redact_message($message)
        );
        $ctx = pv_server_console_context($context);
        if ($ctx !== '') $line .= ' | ' . $ctx;
        $line .= PHP_EOL;
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Logging is non-critical by design. Never leak a console/logging
        // exception back into the live application.
        return;
    }
}

function pv_server_event(string $channel, string $event, array $context = [], string $level = 'INFO'): void
{
    try {
        pv_server_log($level, $channel, $event, $context);
    } catch (Throwable $e) {
        return;
    }
}

function pv_server_console_request_fields(array $source): string
{
    $keys = [];
    foreach (array_keys($source) as $key) {
        $key = (string)$key;
        if ($key === '' || pv_server_console_sensitive_key($key)) continue;
        $keys[] = substr(preg_replace('/[^A-Za-z0-9_.-]/', '_', $key) ?: 'field', 0, 40);
        if (count($keys) >= 16) break;
    }
    sort($keys, SORT_STRING);
    return implode(',', $keys);
}

function pv_server_console_should_log_http_request(string $route, int $status, ?array $settings = null): bool
{
    $settings = $settings ?? pv_server_console_settings();
    if (!(bool)($settings['request_logging'] ?? true)) return false;

    $route = strtolower(basename($route));

    // Per-step movement is intentionally absent from the normal worldserver
    // activity stream. Holding a direction key can generate many requests per
    // second and obscures the operator events that actually need attention.
    // Raw movement traffic is still available explicitly through Apache's
    // access log when the console is launched with --apache-access.
    if (in_array($route, ['map_move.php', 'world_map_move.php'], true)) return false;

    // The operator console intentionally contains no request-error diagnostics.
    // Apache/PHP retain their own diagnostic logs in the background, but 4xx/5xx
    // traffic is not mirrored into the worldserver activity stream.
    if ($status >= 400 && (bool)($settings['suppress_diagnostics'] ?? true)) return false;

    // Presence endpoints are background synchronization noise while a trainer
    // remains on a map and are always omitted from the normal activity stream.
    if ((bool)($settings['suppress_presence_polls'] ?? true)) {
        if (in_array($route, ['map_presence.php', 'world_map_presence.php'], true)) return false;
    }

    return true;
}

function pv_server_console_request_action(string $route): string
{
    $post = $_POST;
    $route = strtolower($route);
    if ($route === 'map_move.php') return 'vortex_move';
    if ($route === 'world_map_move.php') return 'region_move';
    if ($route === 'map_presence.php' || $route === 'world_map_presence.php') return 'presence_poll';
    if ($route === 'live_battle_state.php') return 'live_pvp_poll';
    if ($route === 'checklogin.php') return 'login';
    if ($route === 'logout.php') return 'logout';
    if ($route === 'signup.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') return 'signup';
    if ($route === 'wildbattle.php' && isset($post['battle_action'])) return 'wild_' . pv_server_console_clean_text((string)$post['battle_action'], 32);
    if ($route === 'wildbattle.php' && isset($post['start_wild_battle'])) return 'wild_start';
    if ($route === 'live_battle.php' && isset($post['command'])) return 'live_pvp_' . pv_server_console_clean_text((string)$post['command'], 32);
    if ($route === 'battle.php') {
        foreach (['attack', 'item', 'change', 'change_pokemon', 'run'] as $key) {
            if (array_key_exists($key, $post)) return 'battle_' . $key;
        }
        return 'battle';
    }
    $keys = pv_server_console_request_fields($post);
    return $keys !== '' ? 'post:' . $keys : 'view';
}

function pv_server_console_register_request_logging(): void
{
    try {
        if (!pv_server_console_enabled()) return;
        if (PHP_SAPI === 'cli' || defined('PV_SERVER_CONSOLE_CLI')) return;
        if (isset($GLOBALS['pv_server_console_request_registered'])) return;
        $GLOBALS['pv_server_console_request_registered'] = true;
        $GLOBALS['pv_server_console_request_started'] = microtime(true);

        register_shutdown_function(static function (): void {
            try {
                $settings = pv_server_console_settings();

                $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
                $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '/');
                $route = basename($path);
                if ($route === '') $route = 'index.php';
                $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
                $status = http_response_code();
                if ($status < 100) $status = 200;
                $started = (float)($GLOBALS['pv_server_console_request_started'] ?? microtime(true));
                $durationMs = max(0, (int)round((microtime(true) - $started) * 1000));
                $action = pv_server_console_request_action($route);
                $queryKeys = pv_server_console_request_fields($_GET);
                $postKeys = pv_server_console_request_fields($_POST);
                $level = $status >= 500 ? 'ERROR' : ($status >= 400 ? 'WARN' : 'INFO');

                if (pv_server_console_should_log_http_request($route, $status, $settings)) {
                    pv_server_log($level, 'HTTP', $method . ' ' . $path, [
                        'status' => $status,
                        'ms' => $durationMs,
                        'action' => $action,
                        'query_keys' => $queryKeys,
                        'post_keys' => $postKeys,
                    ]);
                }
            } catch (Throwable $e) {
                return;
            }
        });
    } catch (Throwable $e) {
        return;
    }
}
