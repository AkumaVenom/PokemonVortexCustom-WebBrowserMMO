<?php
/**
 * Pokemon Vortex NXT compatibility/bootstrap layer.
 * Provides application bootstrap, security helpers and legacy PHP compatibility.
 */

if (defined('PV_BOOTSTRAPPED')) {
    return;
}
define('PV_BOOTSTRAPPED', true);

$GLOBALS['pv_config'] = require dirname(__DIR__) . '/config/app.php';
require_once __DIR__ . '/legacy_constants.php';
$config = $GLOBALS['pv_config'];

date_default_timezone_set('UTC');
ini_set('default_charset', 'UTF-8');
ini_set('display_errors', !empty($config['debug']) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/storage/logs/php-error.log');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('pokemon_vortex_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $config['base_path'] ?: '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()");
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header("Content-Security-Policy: default-src 'self' data: blob:; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

function pv_config(?string $key = null, $default = null) {
    $cfg = $GLOBALS['pv_config'] ?? [];
    if ($key === null) return $cfg;
    $parts = explode('.', $key);
    foreach ($parts as $part) {
        if (!is_array($cfg) || !array_key_exists($part, $cfg)) return $default;
        $cfg = $cfg[$part];
    }
    return $cfg;
}

function pv_base(string $path = ''): string {
    $base = rtrim((string)pv_config('base_path', ''), '/');
    if ($path === '') return $base ?: '';
    return ($base ?: '') . '/' . ltrim($path, '/');
}

function pv_url(string $path = ''): string {
    return pv_base($path);
}

function pv_asset(string $path): string {
    return pv_base('assets/' . ltrim($path, '/'));
}

function pv_static(string $path): string {
    return pv_base('html/static/' . ltrim($path, '/'));
}

function pv_asset_version(): string {
    return rawurlencode((string)pv_config('asset_version', '5.0.0'));
}

function pv_static_file(string $path, string $fallback = 'images/Pokeball.PNG'): string {
    $clean = str_replace('\\', '/', ltrim($path, '/'));
    $clean = preg_replace('~(?:^|/)\.{1,2}(?=/|$)~', '', $clean) ?? $clean;
    $root = realpath(dirname(__DIR__) . '/html/static');
    $candidate = $root ? realpath($root . '/' . $clean) : false;
    if ($root && $candidate && str_starts_with($candidate, $root . DIRECTORY_SEPARATOR) && is_file($candidate)) {
        return pv_static($clean);
    }
    return pv_static(ltrim($fallback, '/'));
}

function pv_client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
}

function pv_redirect(string $path, int $status = 302): never {
    if (preg_match('~^https?://~i', $path)) {
        header('Location: ' . $path, true, $status);
    } else {
        header('Location: ' . pv_url($path), true, $status);
    }
    exit;
}

function pv_h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pv_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function pv_csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . pv_h(pv_csrf_token()) . '">';
}

function pv_verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token']);
}

function pv_same_origin_request(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return true;
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value === '') continue;
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) return false;
        $candidate = strtolower((string)$parts['host']);
        $hostOnly = strtolower((string)preg_replace('/:\d+$/', '', $host));
        if (!hash_equals($hostOnly, $candidate)) return false;
    }
    return true;
}

function pv_require_csrf(): void {
    if (!pv_verify_csrf()) {
        if (!headers_sent()) http_response_code(419);
        exit('This form expired. Please go back, refresh the page and try again.');
    }
}

/** One-use command tokens protect stateful battle POSTs from refresh/double-submit replay. */
function pv_action_token(string $scope): string {
    $key = 'pv_action_token_' . preg_replace('/[^a-z0-9_-]/i', '_', $scope);
    if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(24));
    }
    return $_SESSION[$key];
}

function pv_consume_action_token(string $scope, ?string $submitted): bool {
    $key = 'pv_action_token_' . preg_replace('/[^a-z0-9_-]/i', '_', $scope);
    $expected = (string)($_SESSION[$key] ?? '');
    $valid = $expected !== '' && is_string($submitted) && $submitted !== '' && hash_equals($expected, $submitted);
    // Rotate regardless of success so a captured/stale token cannot be retried.
    $_SESSION[$key] = bin2hex(random_bytes(24));
    return $valid;
}

function pv_request_int(string $key, int $default = 0, ?int $min = null, ?int $max = null): int {
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    if ($value === false || $value === null) {
        $value = isset($_POST[$key]) ? filter_var($_POST[$key], FILTER_VALIDATE_INT) : false;
    }
    $value = ($value === false || $value === null) ? $default : (int)$value;
    if ($min !== null) $value = max($min, $value);
    if ($max !== null) $value = min($max, $value);
    return $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !pv_same_origin_request()) {
    if (!headers_sent()) http_response_code(403);
    exit('Request rejected.');
}

function pv_is_logged_in(): bool {
    return !empty($_SESSION['myid']) && (int)($_SESSION['access'] ?? 0) === 9;
}

function pv_require_login(): void {
    if (!pv_is_logged_in()) {
        pv_redirect('login.php?expired=1');
    }
}

function pv_password_matches(string $plain, string $stored): bool {
    if ($stored === '') return false;
    if (password_get_info($stored)['algo'] !== null) {
        return password_verify($plain, $stored);
    }
    return hash_equals(strtolower($stored), md5($plain));
}

function pv_password_hash(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

require_once __DIR__ . '/server_console.php';
pv_server_console_register_request_logging();

function pv_log(string $message, array $context = []): void {
    $line = '[' . date('c') . '] ' . $message . PHP_EOL;
    @file_put_contents(dirname(__DIR__) . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    pv_server_log('ERROR', 'APP', $message, $context);
}

/* --------------------------- Database --------------------------- */
$GLOBALS['mysql_connection'] = $GLOBALS['mysql_connection'] ?? null;
$GLOBALS['pv_last_mysql_error'] = '';

function pv_db(): mysqli {
    if (!class_exists('mysqli')) {
        throw new RuntimeException('The PHP mysqli extension is required.');
    }
    if ($GLOBALS['mysql_connection'] instanceof mysqli) {
        return $GLOBALS['mysql_connection'];
    }

    $db = pv_config('db', []);
    $dbPassword = (string)($db['pass'] ?? '');
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(
        (string)($db['host'] ?? '127.0.0.1'),
        (string)($db['user'] ?? 'root'),
        $dbPassword,
        (string)($db['name'] ?? 'pokemon_vortex'),
        (int)($db['port'] ?? 3306)
    );

    if ($conn->connect_errno) {
        $GLOBALS['pv_last_mysql_error'] = $conn->connect_error;
        throw new RuntimeException('Game database connection failed.');
    }

    $conn->set_charset((string)($db['charset'] ?? 'utf8mb4'));
    $GLOBALS['mysql_connection'] = $conn;
    return $conn;
}

function pv_table_exists(string $table): bool {
    try {
        $db = pv_db();
        $escaped = $db->real_escape_string($table);
        $r = $db->query("SHOW TABLES LIKE '{$escaped}'");
        return $r instanceof mysqli_result && $r->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function pv_normalize_legacy_query(string $query): string {
    // Known recovered-source table-name drift / typos.
    $map = [
        '/\\baccount_options\\b/i' => 'members_options',
        '/\\baccounts\\b/i' => 'members',
        '/\\bregistered\\b/i' => 'reg',
        '/\\blogin_attempt\\b/i' => 'login_trys',
        '/\\bpokmeon_stats\\b/i' => 'pokemon_stats',
        '/\\bability\\b/i' => 'abilities',
    ];
    foreach ($map as $pattern => $replacement) {
        $query = preg_replace($pattern, $replacement, $query);
    }
    return $query;
}

// PHP 8 no longer ships the mysql_* extension. These wrappers preserve the old game API.
if (!function_exists('mysql_connect')) {
    function mysql_connect($server = null, $username = null, $password = null) {
        try { return pv_db(); } catch (Throwable $e) { $GLOBALS['pv_last_mysql_error'] = $e->getMessage(); return false; }
    }
    function mysql_pconnect($server = null, $username = null, $password = null) { return mysql_connect($server, $username, $password); }
    function mysql_select_db($database_name, $link_identifier = null) {
        try { return pv_db()->select_db((string)$database_name); } catch (Throwable $e) { $GLOBALS['pv_last_mysql_error']=$e->getMessage(); return false; }
    }
    function mysql_query($query, $link_identifier = null) {
        try {
            $db = pv_db();
            $query = pv_normalize_legacy_query((string)$query);
            $result = $db->query($query);
            if ($result === false) {
                $GLOBALS['pv_last_mysql_error'] = $db->error;
                pv_log('SQL error: ' . $db->error . ' | ' . preg_replace('/\\s+/', ' ', $query));
            }
            return $result;
        } catch (Throwable $e) {
            $GLOBALS['pv_last_mysql_error'] = $e->getMessage();
            pv_log('SQL exception: ' . $e->getMessage());
            return false;
        }
    }
    function mysql_unbuffered_query($query, $link_identifier = null) { return mysql_query($query, $link_identifier); }
    function mysql_fetch_array($result, $result_type = MYSQLI_BOTH) { return $result instanceof mysqli_result ? $result->fetch_array($result_type) : false; }
    function mysql_fetch_assoc($result) { return $result instanceof mysqli_result ? $result->fetch_assoc() : false; }
    function mysql_fetch_row($result) { return $result instanceof mysqli_result ? $result->fetch_row() : false; }
    function mysql_fetch_object($result, $class_name = 'stdClass', $params = []) { return $result instanceof mysqli_result ? $result->fetch_object($class_name, $params) : false; }
    function mysql_num_rows($result) { return $result instanceof mysqli_result ? $result->num_rows : 0; }
    function mysql_affected_rows($link_identifier = null) { try { return pv_db()->affected_rows; } catch (Throwable $e) { return -1; } }
    function mysql_insert_id($link_identifier = null) { try { return pv_db()->insert_id; } catch (Throwable $e) { return 0; } }
    function mysql_real_escape_string($unescaped_string, $link_identifier = null) { try { return pv_db()->real_escape_string((string)$unescaped_string); } catch (Throwable $e) { return addslashes((string)$unescaped_string); } }
    function mysql_error($link_identifier = null) { try { return pv_db()->error ?: ($GLOBALS['pv_last_mysql_error'] ?? ''); } catch (Throwable $e) { return $GLOBALS['pv_last_mysql_error'] ?? $e->getMessage(); } }
    function mysql_errno($link_identifier = null) { try { return pv_db()->errno; } catch (Throwable $e) { return 0; } }
    function mysql_close($link_identifier = null) { if ($GLOBALS['mysql_connection'] instanceof mysqli) { $GLOBALS['mysql_connection']->close(); $GLOBALS['mysql_connection']=null; } return true; }
    function mysql_free_result($result) { if ($result instanceof mysqli_result) $result->free(); return true; }
    function mysql_set_charset($charset, $link_identifier = null) { try { return pv_db()->set_charset((string)$charset); } catch (Throwable $e) { return false; } }
    function mysql_get_server_info($link_identifier = null) { try { return pv_db()->server_info; } catch (Throwable $e) { return false; } }
    function mysql_info($link_identifier = null) { try { return pv_db()->info; } catch (Throwable $e) { return false; } }
    function mysql_thread_id($link_identifier = null) { try { return pv_db()->thread_id; } catch (Throwable $e) { return false; } }
    function mysql_result($result, $row, $field = 0) {
        if (!$result instanceof mysqli_result) return false;
        $result->data_seek((int)$row);
        $data = $result->fetch_array(MYSQLI_BOTH);
        return $data[$field] ?? false;
    }
}

require_once __DIR__ . '/nxt_theme.php';
require_once __DIR__ . '/audio.php';

/* ------------------------- Legacy output ------------------------- */
function pv_output_filter(string $html): string {
    if ($html === '') return $html;
    $isDocument = stripos($html, '<html') !== false;
    $base = pv_base();

    $staticHosts = [
        'http://static.pokemon-shqipe.co.uk/', 'https://static.pokemon-shqipe.co.uk/',
        'http://static.pokemon-shqipe.co.ukm/', 'https://static.pokemon-shqipe.co.ukm/',
        'http://static.pokemon-vortex.com/', 'https://static.pokemon-vortex.com/',
        'http://static.pokemonvortex.com/', 'https://static.pokemonvortex.com/',
        'http://static.pokemonvortex.org/', 'https://static.pokemonvortex.org/',
    ];
    foreach ($staticHosts as $host) $html = str_ireplace($host, $base . '/html/static/', $html);
    $html = preg_replace('~(?:https?:)?//static\.pokemon(?:-shqipe\.co\.uk|-vortex\.com|vortex\.(?:com|org))/~i', $base . '/html/static/', $html);
    $html = preg_replace('~https?://code\.jquery\.com/jquery-[0-9.]+(?:\.min)?\.js~i', $base . '/html/static/js/jquery.js', $html);
    $html = preg_replace('~https?://ajax\.googleapis\.com/ajax/libs/jquery/[^\"\']+~i', $base . '/html/static/js/jquery.js', $html);

    $siteHosts = [
        'http://www.pokemon-shqipe.co.uk.com/', 'http://Pokemon-Shqipe.co.uk.com/',
        'http://www.pokemon-shqipe.co.uk/', 'https://www.pokemon-shqipe.co.uk/',
        'http://pokemon-shqipe.co.uk/', 'https://pokemon-shqipe.co.uk/',
        'http://www.pokemon-vortex.com/', 'https://www.pokemon-vortex.com/',
        'http://pokemon-vortex.com/', 'https://pokemon-vortex.com/',
        'http://www.pokemonvortex.org/', 'https://www.pokemonvortex.org/',
        'http://pokemonvortex.org/', 'https://pokemonvortex.org/',
    ];
    foreach ($siteHosts as $host) $html = str_ireplace($host, $base . '/', $html);
    $html = preg_replace('~(?:https?:)?//forums\.pokemon-vortex\.com/?~i', $base . '/community.php', $html);

    // Normalize historic public branding and retired pre-release wording.
    $html = str_ireplace([
        'Pok&eacute;mon Shqipe v3', 'Pokémon Shqipe v3', 'Pok&eacute;mon Shqipe', 'Pokémon Shqipe',
        'pokemon-shqipe.co.uk', 'Pokemon-Shqipe.co.uk', 'PokÃ©mon Shqipe', 'PokÃ©mon',
        'Vortex Staff Challenge', 'Pok&eacute;mon Shqipe Lottery', 'Pokémon Shqipe Lottery',
        'during the BETA of v3', 'during the Beta', 'during the BETA',
        'approved by an administrator', 'approved by the administrator', 'contact an administrator', 'site administrator'
    ], [
        'Pokemon Vortex NXT', 'Pokemon Vortex NXT', 'Pokemon Vortex NXT', 'Pokemon Vortex NXT',
        'Pokemon Vortex NXT', 'Pokemon Vortex NXT', 'Pokemon Vortex NXT', 'Pokémon',
        'Vortex Champion Challenge', 'Pokemon Vortex NXT Lottery', 'Pokemon Vortex NXT Lottery',
        'at this time', 'at this time', 'at this time',
        'available to players', 'available to players', 'contact support', 'support team'
    ], $html);

    // Old root static paths were valid on the historic server but not in this package.
    // Point them at the bundled asset library before applying application base-path routing.
    $html = preg_replace('~(src|href)=([' . "'\"" . '])/(images|js|css)/~i', '$1=$2' . $base . '/html/static/$3/', $html);
    $html = preg_replace('~(src|href)=([' . "'\"" . '])(js|css)/(?=[^/])~i', '$1=$2' . $base . '/html/static/$3/', $html);

    if ($base !== '') {
        $quotedBase = preg_quote($base, '~');
        $html = preg_replace('~(href|src|action)=([' . "'\"" . '])/(?!/|' . ltrim($quotedBase, '\\/') . '/)~i', '$1=$2' . $base . '/', $html);
    }

    // Strip retired advertising/social widgets and ad-block takeover code.
    $html = preg_replace('~<script\b[^>]*>.*?AdBlocker.*?</script>~is', '', $html);
    $html = preg_replace('~<iframe\b[^>]*(?:adv\.php|facebook\.com/plugins)[^>]*>.*?</iframe>~is', '', $html);
    $html = preg_replace('~<img\b[^>]*fbbanner\.png[^>]*>~is', '', $html);

    // Replace historic copyright/donation footers with a clean player-facing footer.
    $legacyFooter = '<div id="copy">&copy; ' . date('Y') . ' Pokemon Vortex NXT &nbsp;·&nbsp; '
        . '<a href="' . pv_url('contactus.php') . '">Support</a> &nbsp;·&nbsp; '
        . '<a href="' . pv_url('terms.php') . '">Terms</a> &nbsp;·&nbsp; '
        . '<a href="' . pv_url('privacy.php') . '">Privacy</a> &nbsp;·&nbsp; '
        . '<a href="' . pv_url('legal.php') . '">Legal</a></div>';
    $html = preg_replace('~<div\s+id=["\']copy["\'][^>]*>.*?</div>~is', $legacyFooter, $html);

    // Add CSRF tokens to legacy POST forms without requiring each recovered page to be rewritten.
    if (stripos($html, '<form') !== false) {
        $token = pv_h(pv_csrf_token());
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $actionScope = in_array($script, ['battle.php','live_battle.php'], true) ? $script : '';
        $actionToken = $actionScope !== '' ? pv_h(pv_action_token($actionScope)) : '';
        $html = preg_replace_callback('~<form\b([^>]*)>(.*?)</form>~is', static function ($m) use ($token, $actionScope, $actionToken) {
            $attrs = $m[1];
            $body = $m[2];
            if (!preg_match('~\bmethod\s*=\s*(["\']?)post\1~i', $attrs)) return $m[0];
            $hidden = '';
            if (stripos($body, 'name="csrf_token"') === false && stripos($body, "name='csrf_token'") === false) {
                $hidden .= '<input type="hidden" name="csrf_token" value="' . $token . '">';
            }
            if ($actionScope !== '' && stripos($body, 'name="battle_action_token"') === false && stripos($body, "name='battle_action_token'") === false) {
                $hidden .= '<input type="hidden" name="battle_action_token" value="' . $actionToken . '">';
            }
            return '<form' . $attrs . '>' . $hidden . $body . '</form>';
        }, $html) ?? $html;
    }

    // Progressive loading makes large Pokédex and collection pages feel much faster.
    $html = preg_replace_callback('~<img\b(?![^>]*\bloading=)([^>]*)>~i', static function ($m) {
        return '<img loading="lazy" decoding="async"' . $m[1] . '>';
    }, $html);

    $version = pv_asset_version();
    if ($isDocument && stripos($html, 'name="viewport"') === false && stripos($html, "name='viewport'") === false) {
        $html = preg_replace('~</head>~i', '<meta name="viewport" content="width=device-width, initial-scale=1"></head>', $html, 1) ?? $html;
    }
    $injection = '<link rel="stylesheet" href="' . pv_asset('css/vortex-modern.css') . '?v=' . $version . '">'
               . '<script defer src="' . pv_asset('js/vortex-modern.js') . '?v=' . $version . '"></script>';
    if ($isDocument && stripos($html, 'vortex-modern.css') === false) {
        $html = preg_replace('~</head>~i', $injection . '</head>', $html, 1);
    }
    return pv_audio_document(pv_nxt_document($html));
}

if (!defined('PV_DISABLE_OUTPUT_FILTER')) {
    ob_start('pv_output_filter');
}
