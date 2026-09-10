<?php
declare(strict_types=1);

// Deliberately no HTTP transport, even for localhost or a logged-in owner.
if (PHP_SAPI !== 'cli' || !defined('PV_SERVER_CONSOLE_CLI')) {
    http_response_code(404);
    exit;
}
if (!defined('PV_DISABLE_OUTPUT_FILTER')) define('PV_DISABLE_OUTPUT_FILTER', true);
require_once dirname(__DIR__) . '/bootstrap.php';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
$_SESSION = [];
require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/system_runtime.php';
foreach (['system', 'accounts', 'pokemon', 'world', 'social'] as $module) {
    require_once __DIR__ . '/' . $module . '.php';
}

function pv_admin_ranks(): array
{
    return ['PLAYER', 'VIP', 'HELPER', 'MODERATOR', 'GAME MASTER', 'ADMIN', 'DEVELOPER', 'OWNER'];
}

function pv_admin_rank(string $rank): string
{
    $rank = strtoupper(str_replace(['_', '-'], ' ', trim($rank)));
    if ($rank === 'GM') $rank = 'GAME MASTER';
    if (!in_array($rank, pv_admin_ranks(), true)) throw new InvalidArgumentException('Unknown rank. Use: ' . implode(', ', pv_admin_ranks()) . '.');
    return $rank;
}

function pv_admin_boot(): array
{
    $cfg = pv_config('admin_console', []);
    $operator = (string)($cfg['operator_name'] ?? '');
    if ($operator === '') $operator = (string)(getenv('USERNAME') ?: getenv('USER') ?: 'local-admin');
    return [
        'operator' => substr(preg_replace('/[^\pL\pN_.@ -]/u', '', $operator) ?: 'local-admin', 0, 80),
        'rank' => pv_admin_rank((string)($cfg['operator_rank'] ?? 'OWNER')),
        'selected' => 0, 'started_at' => time(), 'exit' => false, 'debug' => false,
        'last_ok' => true, 'pending' => null,
    ];
}

function pv_admin_db(): mysqli { return pv_db(); }

function pv_admin_statement(string $sql, array $params = []): mysqli_stmt
{
    $stmt = pv_admin_db()->prepare($sql);
    if (!$stmt) { error_log('[admin console] Prepare failed; errno=' . pv_admin_db()->errno . ', SQLSTATE=' . pv_admin_db()->sqlstate); throw new RuntimeException('Database statement could not be prepared. Run _setup.php repair and check the private PHP log.'); }
    if ($params) {
        $types = '';
        foreach ($params as $value) $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log('[admin console] Execute failed; errno=' . $stmt->errno . ', SQLSTATE=' . $stmt->sqlstate);
        $stmt->close();
        throw new RuntimeException('Database operation failed; no successful result was reported. Check the private PHP log and database schema.');
    }
    return $stmt;
}

function pv_admin_rows(string $sql, array $params = []): array
{
    $stmt = pv_admin_statement($sql, $params);
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}
function pv_admin_row(string $sql, array $params = []): ?array { return pv_admin_rows($sql, $params)[0] ?? null; }
function pv_admin_exec(string $sql, array $params = []): int
{
    $stmt = pv_admin_statement($sql, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function pv_admin_int(string $s, int $min, int $max, string $label = 'value'): int
{
    if (!preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $s)) throw new InvalidArgumentException($label . ' must be a whole number from ' . $min . ' to ' . $max . '.');
    $value = filter_var($s, FILTER_VALIDATE_INT);
    if ($value === false || $value < $min || $value > $max) throw new InvalidArgumentException($label . ' must be from ' . $min . ' to ' . $max . '.');
    return $value;
}

function pv_admin_lock_player(int $uid): void
{
    $name = pv_admin_request_lock_name($uid);
    if (isset($GLOBALS['pv_admin_command_locks'][$name])) return;
    $lock = pv_admin_row('SELECT GET_LOCK(?,5) AS acquired', [$name]);
    if ((int)($lock['acquired'] ?? 0) !== 1) throw new RuntimeException('That trainer is busy. Retry after the current request completes.');
    $GLOBALS['pv_admin_command_locks'][$name] = true;
}

function pv_admin_player(string $reference = '', array $context = [], bool $lock = false): array
{
    if ($reference === '') {
        $selected = (int)($context['selected'] ?? 0);
        if ($selected < 1) throw new InvalidArgumentException('Select a trainer first: select <username|#ID>, or provide the trainer argument shown in help.');
        $reference = '#' . $selected;
    }
    $isId = preg_match('/^#?[0-9]+$/D', $reference) === 1;
    $value = $isId ? pv_admin_int(ltrim($reference, '#'), 1, 2147483647, 'Trainer ID') : (str_starts_with($reference, 'name:') ? substr($reference, 5) : $reference);
    $where = $isId ? 'id=?' : 'username=?';
    $rows = pv_admin_rows('SELECT * FROM members WHERE ' . $where . ' LIMIT 2', [$value]);
    if (!$rows) throw new InvalidArgumentException('Trainer not found: ' . $reference . '.');
    if (count($rows) !== 1) throw new InvalidArgumentException('This trainer name is ambiguous. Use #ID.');
    $uid = (int)$rows[0]['id'];
    pv_admin_lock_player($uid);
    $GLOBALS['pv_admin_targets'][$uid] = (string)$rows[0]['username'];
    $row = pv_admin_row('SELECT * FROM members WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$uid]);
    if (!$row) throw new InvalidArgumentException('Trainer no longer exists.');
    return $row;
}

function pv_admin_target(array &$args, array $context): array
{
    return pv_admin_player($args ? (string)array_shift($args) : '', $context);
}

function pv_admin_transaction(callable $fn)
{
    $db = pv_admin_db();
    $depth = (int)($GLOBALS['pv_admin_tx_depth'] ?? 0);
    $name = 'pv_admin_' . $depth;
    if ($depth === 0) {
        if (!$db->begin_transaction()) throw new RuntimeException('Could not begin database transaction.');
    } elseif (!$db->query('SAVEPOINT ' . $name)) throw new RuntimeException('Could not create transaction savepoint.');
    $GLOBALS['pv_admin_tx_depth'] = $depth + 1;
    try {
        $result = $fn($db);
        if ($depth === 0) {
            if (!$db->commit()) throw new RuntimeException('Database commit failed.');
        } elseif (!$db->query('RELEASE SAVEPOINT ' . $name)) throw new RuntimeException('Database savepoint failed.');
        return $result;
    } catch (Throwable $e) {
        if ($depth === 0) $db->rollback(); else $db->query('ROLLBACK TO SAVEPOINT ' . $name);
        throw $e;
    } finally { $GLOBALS['pv_admin_tx_depth'] = $depth; }
}

function pv_admin_setting(string $key, $default = null)
{
    $row = pv_admin_row('SELECT value_json FROM console_settings WHERE setting_key=?', [$key]);
    if (!$row) return $default;
    try { return json_decode((string)$row['value_json'], true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('Invalid stored console setting: ' . $key . '.'); }
}
function pv_admin_set_setting(string $key, $value): void
{
    pv_admin_exec('INSERT INTO console_settings(setting_key,value_json,updated_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json),updated_at=VALUES(updated_at)', [$key, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), time()]);
}

function pv_admin_touch(int $uid): void
{
    pv_admin_lock_player($uid);
    pv_admin_player_state($uid);
    pv_admin_exec('UPDATE console_player_state SET data_revision=data_revision+1 WHERE user_id=?', [$uid]);
}

function pv_admin_require_idle(int $uid): void
{
    $battle = pv_admin_row('SELECT id FROM live_battle WHERE (uid_1=? OR uid_2=?) AND (settled_1=0 OR settled_2=0) LIMIT 1 FOR UPDATE', [$uid, $uid]);
    if ($battle) throw new RuntimeException('Trainer has an unsettled live battle. Finish the battle before changing gameplay state.');
    $trade = pv_admin_row("SELECT id FROM trade_offers WHERE (listing_owner_id=? OR offerer_id=?) AND status='pending' LIMIT 1 FOR UPDATE", [$uid, $uid]);
    if ($trade) throw new RuntimeException('Trainer has pending trade escrow. Resolve or cancel the trade first.');
}

function pv_admin_require_args(array $args, int $min, ?int $max, string $usage): void
{
    if (count($args) < $min || ($max !== null && count($args) > $max)) throw new InvalidArgumentException('Usage: ' . $usage);
}

/** Small deterministic lexer, never passed to a shell or eval. */
function pv_admin_tokenize(string $line): array
{
    if (strlen($line) > 4096) throw new InvalidArgumentException('Command is too long (maximum 4096 bytes).');
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $line)) throw new InvalidArgumentException('Control characters are not allowed in commands.');
    $tokens = []; $word = ''; $quote = ''; $inWord = false;
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
        $char = $line[$i];
        if ($char === '\\' && $i + 1 < $length && in_array($line[$i+1], ['"', "'", '\\'], true)) {
            $word .= $line[++$i]; $inWord = true; continue;
        }
        if ($quote !== '') {
            if ($char === $quote) $quote = ''; else $word .= $char;
            $inWord = true; continue;
        }
        if ($char === '"' || $char === "'") { $quote = $char; $inWord = true; continue; }
        if (ctype_space($char)) {
            if ($inWord) { $tokens[] = $word; $word = ''; $inWord = false; }
        } else { $word .= $char; $inWord = true; }
    }
    if ($quote !== '') throw new InvalidArgumentException('Unclosed quote. Enclose multiword names in matching double quotes.');
    if ($inWord) $tokens[] = $word;
    if ($tokens) $tokens[0] = strtolower(ltrim($tokens[0], '/'));
    return $tokens;
}

function pv_admin_registry(): array
{
    $registry = [];
    foreach (['system', 'accounts', 'pokemon', 'world', 'social'] as $module) {
        foreach (('pv_admin_' . $module . '_specs')() as $name => $spec) {
            if (isset($registry[$name])) throw new LogicException('Duplicate command registration: ' . $name);
            $registry[$name] = $spec + ['module'=>$module, 'rank'=>'OWNER', 'dev'=>false, 'confirm'=>false, 'min'=>0, 'max'=>null];
        }
    }
    return $registry;
}

function pv_admin_resolve(string $name, array $registry): string
{
    if (!isset($registry[$name])) throw new InvalidArgumentException('Unknown command: ' . $name . '. Type help or commands. Chat commands are not included.');
    $seen = [];
    while (isset($registry[$name]['alias'])) {
        if (isset($seen[$name])) throw new LogicException('Invalid command alias.');
        $seen[$name] = true; $name = $registry[$name]['alias'];
        if (!isset($registry[$name])) throw new LogicException('Missing command alias target.');
    }
    return $name;
}

function pv_admin_available(string $name, array $spec, array $context): bool
{
    $disabled = (array)pv_config('admin_console.disabled_commands', []);
    foreach ($disabled as $entry) {
        $entry = strtolower(ltrim(trim((string)$entry), '/'));
        $registry = pv_admin_registry();
        if (isset($registry[$entry])) $entry = pv_admin_resolve($entry, $registry);
        if ($entry === $name) return false;
    }
    if (!pv_config('admin_console.enabled', true)) return false;
    if ($spec['dev'] && !pv_config('admin_console.enable_developer_commands', false)) return false;
    return array_search(pv_admin_rank((string)($context['rank'] ?? 'PLAYER')), pv_admin_ranks(), true)
        >= array_search(pv_admin_rank((string)$spec['rank']), pv_admin_ranks(), true);
}

function pv_admin_ensure_schema(): void
{
    if (!empty($GLOBALS['pv_admin_schema_ready'])) return;
    if (pv_table_exists('console_settings')) {
        $version = pv_admin_row("SELECT value_json FROM console_settings WHERE setting_key='schema_version'");
        if ($version && json_decode($version['value_json'], true) === 330001) {
            $GLOBALS['pv_admin_schema_ready'] = true;
            return;
        }
    }
    require_once dirname(__DIR__) . '/schema.php';
    $db = pv_admin_db();
    $lockName = 'pv-admin-schema:' . substr(hash('sha256', (string)pv_config('db.name')), 0, 32);
    $lock = pv_admin_row('SELECT GET_LOCK(?,10) AS acquired', [$lockName]);
    if ((int)($lock['acquired'] ?? 0) !== 1) throw new RuntimeException('Schema repair is already running. Retry shortly.');
    try {
        // Existing installations run the same additive repair path as fresh setup.
        pv_apply_schema_migrations($db);
        $GLOBALS['pv_admin_schema_ready'] = true;
    } finally { pv_admin_row('SELECT RELEASE_LOCK(?) AS released', [$lockName]); }
}

function pv_admin_audit_start(string $command, array $args, array $context): int
{
    pv_admin_exec('INSERT INTO console_audit(created_at,operator_name,operator_rank,command_name,target_text,arguments_json,outcome,result_text) VALUES(?,?,?,?,?,?,?,?)', [time(), $context['operator'], $context['rank'], $command, 'selected:#' . (int)$context['selected'], json_encode($args, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'started', '']);
    return (int)pv_admin_db()->insert_id;
}

function pv_admin_audit_finish(int $id, string $outcome, string $result, bool $sensitive = false): void
{
    $targets = [];
    foreach (($GLOBALS['pv_admin_targets'] ?? []) as $uid => $name) $targets[] = '#' . $uid . ':' . $name;
    pv_admin_exec('UPDATE console_audit SET target_text=?,outcome=?,result_text=? WHERE id=?', [substr(implode(', ', $targets), 0, 190), $outcome, $sensitive ? 'Credential operation completed; secret output omitted.' : substr(pv_server_console_redact_message($result), 0, 2000), $id]);
    pv_server_event('ADMIN', 'Local command ' . $outcome, ['audit_id'=>$id, 'targets'=>implode(', ', $targets)]);
}

function pv_admin_release_locks(): void
{
    foreach (array_keys($GLOBALS['pv_admin_command_locks'] ?? []) as $name) {
        try { pv_admin_row('SELECT RELEASE_LOCK(?) AS released', [$name]); } catch (Throwable $e) { /* connection may have closed */ }
    }
    $GLOBALS['pv_admin_command_locks'] = [];
}

function pv_admin_dispatch(string $line, array &$context): array
{
    $context['last_ok'] = true;
    $commandStarted = microtime(true);
    $auditId = 0; $command = ''; $lockName = ''; $completed = false;
    $GLOBALS['pv_admin_targets'] = [];
    try {
        $tokens = pv_admin_tokenize(trim($line));
        if (!$tokens) return [];
        $requested = array_shift($tokens);
        $registry = pv_admin_registry();
        $command = pv_admin_resolve($requested, $registry);
        $confirmed = false;
        if ($command === 'confirm') {
            pv_admin_require_args($tokens, 1, 1, 'confirm <token>');
            $pending = $context['pending'] ?? null;
            $context['pending'] = null;
            if (!$pending || time() > $pending['expires'] || !hash_equals($pending['token'], $tokens[0])) throw new InvalidArgumentException('Confirmation is incorrect or expired. Run the original command again.');
            $command = $pending['command']; $tokens = $pending['args'];
            if ((int)$context['selected'] !== $pending['selected']) throw new InvalidArgumentException('Selected trainer changed; run the original command again.');
            $confirmed = true;
        }
        $spec = $registry[$command];
        if (!pv_admin_available($command, $spec, $context)) throw new InvalidArgumentException('Command is disabled or unavailable at the configured local operator rank. Check admin_console in config/app.php.');
        pv_admin_require_args($tokens, (int)$spec['min'], $spec['max'], (string)$spec['usage']);
        if ($command === 'event' && isset($tokens[0])) {
            $sub = strtolower($tokens[0]);
            foreach ((array)pv_config('admin_console.disabled_commands', []) as $disabled) {
                if (strtolower(ltrim(trim((string)$disabled), '/')) === 'event ' . $sub) throw new InvalidArgumentException('This event subcommand is disabled in config/app.php.');
            }
        }
        $needsConfirm = (bool)$spec['confirm'];
        if ($needsConfirm && !$confirmed) {
            $boundArgs = $tokens;
            $targetDescription = 'Selected trainer: #' . (int)$context['selected'];
            if (!in_array($command, ['shutdown','restart'], true)) {
                pv_admin_ensure_schema();
                $target = pv_admin_player($tokens[0] ?? '', $context);
                $boundArgs[0] = '#' . (int)$target['id'];
                $targetDescription = 'Bound target: ' . $target['username'] . ' (#' . $target['id'] . ')';
            } else {
                pv_admin_int($tokens[0], 0, 86400, 'Delay');
            }
            $context['pending'] = ['token'=>bin2hex(random_bytes(4)), 'command'=>$command, 'args'=>$boundArgs, 'selected'=>(int)$context['selected'], 'expires'=>time()+60];
            return ['Confirmation required: ' . $command . ' ' . implode(' ', array_map(static fn($s)=>json_encode($s, JSON_UNESCAPED_UNICODE), $tokens)), $targetDescription . '. Nothing has changed.', 'Type confirm ' . $context['pending']['token'] . ' within 60 seconds, or cancel.'];
        }
        $offline = in_array($command, ['help','commands','version','uptime','time','motd','rules','exit','cancel'], true);
        if (!$offline) {
            pv_admin_ensure_schema();
            $lockName = 'pv-admin-commands:' . substr(hash('sha256', (string)pv_config('db.name')), 0, 32);
            $acquired = pv_admin_row('SELECT GET_LOCK(?,5) AS acquired', [$lockName]);
            if ((int)($acquired['acquired'] ?? 0) !== 1) throw new RuntimeException('Another local console command is still running. Retry shortly.');
            $auditId = pv_admin_audit_start($command, $tokens, $context);
        }
        $lines = ('pv_admin_' . $spec['module'] . '_handle')($command, $tokens, $context);
        if (!is_array($lines)) throw new LogicException('Invalid command output.');
        $completed = true;
        if ($auditId) pv_admin_audit_finish($auditId, 'success', implode(' | ', $lines), in_array($command, ['createaccount','resetpassword'], true));
        if (!empty($context['debug'])) $lines[] = '[debug] ' . $command . ' | ' . number_format((microtime(true)-$commandStarted)*1000,2) . ' ms | PHP memory ' . number_format(memory_get_usage(true)/1048576,2) . ' MiB';
        return $lines;
    } catch (Throwable $e) {
        $context['last_ok'] = false;
        if ($completed) return ['ERROR: The command completed, but its final audit update failed. Inspect the resulting state before retrying.'];
        if ($auditId) {
            try { pv_admin_audit_finish($auditId, 'failed', $e->getMessage(), in_array($command, ['createaccount','resetpassword'], true)); }
            catch (Throwable $ignored) { return ['ERROR: Command did not complete cleanly and its final audit record could not be saved. Inspect state before retrying.']; }
        }
        // Never echo a mysqli exception containing values or SQL into the terminal.
        $message = $e instanceof mysqli_sql_exception ? 'Database operation failed. Check the database connection and run setup repair.' : $e->getMessage();
        return ['ERROR: ' . preg_replace('/[\x00-\x1F\x7F]/', ' ', $message)];
    } finally {
        pv_admin_release_locks();
        if ($lockName !== '') {
            try { pv_admin_row('SELECT RELEASE_LOCK(?) AS released', [$lockName]); } catch (Throwable $ignored) {}
        }
    }
}

function pv_admin_tick(array &$context): array
{
    if (empty($GLOBALS['pv_admin_schema_ready'])) return [];
    if ((int)($context['last_tick'] ?? 0) === time()) return [];
    $context['last_tick'] = time();
    try {
        $state = pv_admin_maintenance_state(pv_admin_db());
        $signature = json_encode($state);
        if (($context['maintenance_signature'] ?? null) === $signature) return [];
        $first = !isset($context['maintenance_signature']);
        $context['maintenance_signature'] = $signature;
        if ($first && empty($state['active'])) return [];
        return [!empty($state['active']) ? 'Game maintenance is active. Type resume to reopen the game.' : 'Game is accepting requests.'];
    } catch (Throwable $e) { return []; }
}
