<?php
declare(strict_types=1);
/**
 * Database migration regressions using connection-local InnoDB temporary tables.
 * Requires mysqli and PV_TEST_DB_HOST/PORT/USER/PASS/NAME (or PV_TEST_DB_SOCKET).
 * Existing application rows are never modified; temporary tables disappear on exit.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!extension_loaded('mysqli')) { fwrite(STDERR, "mysqli is required.\n"); exit(1); }
if (!getenv('PV_TEST_DB_NAME')) {
    fwrite(STDERR, "Set PV_TEST_DB_NAME and PV_TEST_DB_* connection settings for an isolated test database.\n");
    exit(1);
}
define('PV_BOOTSTRAPPED', true);
require_once dirname(__DIR__) . '/includes/experience_migration.php';

// information_schema does not expose temporary tables. This focused helper checks
// the actual connection-local table used by the production migration's trade branch.
function pv_schema_table_exists(mysqli $db, string $table): bool
{
    if ($table !== 'upfortrade') throw new RuntimeException('Unexpected schema lookup in migration test');
    return $db->query('SELECT 1 FROM upfortrade LIMIT 0') !== false;
}

$checks = 0;
function migration_check($actual, $expected, string $label): void
{
    global $checks;
    $checks++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}
function migration_row(mysqli $db, int $id): array
{
    return $db->query('SELECT * FROM pokemon WHERE id=' . $id)->fetch_assoc() ?? [];
}
function migration_count(mysqli $db, string $query): int
{
    return (int)$db->query($query)->fetch_row()[0];
}
function migration_expect_failure(callable $action, string $needle): void
{
    $caught = null;
    try { $action(); } catch (Throwable $error) { $caught = $error; }
    migration_check($caught !== null, true, 'Expected failure: ' . $needle);
    migration_check(str_contains($caught->getMessage(), $needle), true, 'Failure explains ' . $needle);
}

$db = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(getenv('PV_TEST_DB_HOST') ?: '127.0.0.1', getenv('PV_TEST_DB_USER') ?: 'root',
        getenv('PV_TEST_DB_PASS') ?: '', getenv('PV_TEST_DB_NAME'),
        (int)(getenv('PV_TEST_DB_PORT') ?: 3306), getenv('PV_TEST_DB_SOCKET') ?: null);
    $db->set_charset('utf8mb4');
    $definitions = [
        'pokemon' => 'id INT PRIMARY KEY, pid INT NOT NULL DEFAULT 0, name VARCHAR(80) NOT NULL, lvl INT NOT NULL, exp BIGINT NOT NULL, exp_curve_version TINYINT UNSIGNED NOT NULL DEFAULT 0, owner VARCHAR(45) NOT NULL',
        'pokemon_exp_migration' => 'pokemon_id INT PRIMARY KEY, species VARCHAR(80) NOT NULL, old_level INT NOT NULL, old_exp BIGINT NOT NULL, new_level INT NOT NULL, new_exp BIGINT NOT NULL, migrated_at BIGINT NOT NULL',
        'members' => "id INT PRIMARY KEY, battle INT NOT NULL DEFAULT 10, clan_name VARCHAR(50) NOT NULL DEFAULT '', total_poke INT NOT NULL DEFAULT 0, uniques INT NOT NULL DEFAULT 0, totalexp BIGINT NOT NULL DEFAULT 0, averageexp BIGINT NOT NULL DEFAULT 0, points DOUBLE NOT NULL DEFAULT 0",
        'clan_members' => 'id INT PRIMARY KEY, clan_name VARCHAR(50) NOT NULL, clan VARCHAR(50) NOT NULL, exp BIGINT NOT NULL DEFAULT 0',
        'clans' => 'name VARCHAR(50) PRIMARY KEY, wins INT NOT NULL DEFAULT 10, members INT NOT NULL DEFAULT 0, exp BIGINT NOT NULL DEFAULT 0, points DOUBLE NOT NULL DEFAULT 0',
        'upfortrade' => 'id INT PRIMARY KEY, pid INT NOT NULL, lvl INT NOT NULL, exp BIGINT NOT NULL',
    ];
    foreach ($definitions as $table => $columns) {
        $db->query('CREATE TEMPORARY TABLE `' . $table . '` (' . $columns . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    $db->query("INSERT INTO members (id,clan_name) VALUES (1,'Migration Test'),(2,''),(3,'Migration Test')");
    $db->query("INSERT INTO clans (name) VALUES ('Migration Test')");
    $db->query("INSERT INTO clan_members (id,clan_name,clan,exp) VALUES (1,'Migration Test','Migration Test',999),(3,'Migration Test','Migration Test',777)");
    $db->query("INSERT INTO pokemon (id,pid,name,lvl,exp,exp_curve_version,owner) VALUES
        (11,1,'Bulbasaur',5,2750,0,'1'),
        (12,25,'Pikachu',50,25250,0,'1'),
        (13,290,'Nincada',50,9999999,0,'2'),
        (14,147,'Dratini',100,12345,0,'2'),
        (15,113,'Chansey',0,0,0,'2'),
        (16,252,'Treecko',20,0,0,'1'),
        (17,25,'Pikachu',10,1234,1,'1'),
        (18,290,'Nincada',20,10000,0,'0')");
    // The listing ID intentionally matches a different specimen's ID.
    $db->query('INSERT INTO upfortrade (id,pid,lvl,exp) VALUES (11,18,99,99999),(888,11,99,99999)');
    migration_check(pv_exp_migrate_legacy($db, 2), 7, 'Batched initial conversion count');
    $expected = [11=>[5,157], 12=>[50,128825], 14=>[100,1250000], 15=>[1,1],
        16=>[20,5460], 17=>[10,1234], 18=>[20,12800]];
    foreach ($expected as $id => [$level, $exp]) {
        $row = migration_row($db, $id);
        migration_check((int)$row['lvl'], $level, 'Preserved/clamped level specimen ' . $id);
        migration_check((int)$row['exp'], $exp, 'Converted cumulative EXP specimen ' . $id);
        migration_check((int)$row['exp_curve_version'], 1, 'Version marker specimen ' . $id);
    }
    $overawarded = migration_row($db, 13);
    migration_check((int)$overawarded['lvl'], 50, 'Corrupt legacy excess cannot invent levels');
    migration_check((int)$overawarded['exp'] < pv_exp_at_level('Nincada', 51), true, 'Corrupt legacy excess stays below next threshold');
    $audit = $db->query('SELECT * FROM pokemon_exp_migration WHERE pokemon_id=11')->fetch_assoc();
    migration_check((int)$audit['old_level'], 5, 'Audit original level');
    migration_check((int)$audit['old_exp'], 2750, 'Audit original EXP');
    migration_check((int)$audit['new_level'], 5, 'Audit migrated level');
    migration_check((int)$audit['new_exp'], 157, 'Audit migrated EXP');
    migration_check((int)$audit['migrated_at'] > 0, true, 'Audit timestamp');
    migration_check(migration_count($db, 'SELECT COUNT(*) FROM pokemon_exp_migration'), 7, 'Exactly one audit per legacy row');
    migration_check(migration_count($db, 'SELECT COUNT(*) FROM pokemon_exp_migration WHERE pokemon_id=17'), 0, 'Native curve row excluded from migration audit');
    $trade = $db->query('SELECT lvl,exp FROM upfortrade WHERE id=11')->fetch_assoc();
    migration_check((int)$trade['lvl'], 20, 'Trade snapshot joins specimen pid, not listing id');
    migration_check((int)$trade['exp'], 12800, 'Escrow trade snapshot EXP');
    migration_check(migration_count($db, 'SELECT exp FROM upfortrade WHERE id=888'), 157, 'Second trade snapshot maps to correct specimen');
    $member = $db->query('SELECT * FROM members WHERE id=1')->fetch_assoc();
    migration_check((int)$member['total_poke'], 4, 'Trainer count includes native curve rows');
    migration_check((int)$member['uniques'], 3, 'Trainer unique catalogue species');
    migration_check((int)$member['totalexp'], 135676, 'Trainer cumulative EXP recomputed');
    migration_check((int)$member['averageexp'], 33919, 'Trainer average EXP recomputed');
    migration_check(migration_count($db, 'SELECT exp FROM clan_members WHERE id=1'), 135676, 'Clan contribution recomputed');
    migration_check(migration_count($db, "SELECT exp FROM clans WHERE name='Migration Test'"), 136453, 'Clan aggregate includes unchanged member');
    $before = $db->query('SELECT * FROM pokemon ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $auditBefore = $db->query('SELECT * FROM pokemon_exp_migration ORDER BY pokemon_id')->fetch_all(MYSQLI_ASSOC);
    migration_check(pv_exp_migrate_legacy($db, 3), 0, 'Rerun converts zero rows');
    migration_check($db->query('SELECT * FROM pokemon ORDER BY id')->fetch_all(MYSQLI_ASSOC), $before, 'Migration rows idempotent');
    migration_check($db->query('SELECT * FROM pokemon_exp_migration ORDER BY pokemon_id')->fetch_all(MYSQLI_ASSOC), $auditBefore, 'Migration audits idempotent');

    // Simulate interrupted batches: the first batch commits; the next encounters
    // invalid metadata after a valid row, rolling back that entire second batch.
    $db->query("INSERT INTO pokemon (id,pid,name,lvl,exp,owner) VALUES
        (101,25,'Pikachu',10,5000,'1'),(102,1,'Bulbasaur',10,5000,'1'),
        (103,25,'Pikachu',20,10000,'1'),(104,9999,'Unknown Migration Species',20,10000,'1')");
    migration_expect_failure(fn() => pv_exp_migrate_legacy($db, 2), 'Unknown Migration Species');
    migration_check((int)migration_row($db, 101)['exp_curve_version'], 1, 'Committed earlier batch survives failure');
    migration_check((int)migration_row($db, 102)['exp_curve_version'], 1, 'All committed earlier rows survive');
    migration_check((int)migration_row($db, 103)['exp_curve_version'], 0, 'Valid row in failing batch rolls back');
    migration_check((int)migration_row($db, 103)['exp'], 10000, 'Failed batch preserves raw legacy EXP');
    migration_check((int)migration_row($db, 104)['exp_curve_version'], 0, 'Unknown species remains unconverted');
    migration_check(migration_count($db, 'SELECT COUNT(*) FROM pokemon_exp_migration WHERE pokemon_id IN (103,104)'), 0, 'Failed batch audits roll back atomically');
    $database = (string)$db->query('SELECT DATABASE()')->fetch_row()[0];
    $lock = 'pv-exp-migrate:' . substr(hash('sha256', $database), 0, 32);
    $lockStmt = $db->prepare('SELECT IS_FREE_LOCK(?)'); $lockStmt->bind_param('s', $lock); $lockStmt->execute();
    migration_check((int)$lockStmt->get_result()->fetch_row()[0], 1, 'Named migration lock released after failure');
    $lockStmt->close();
    $db->query("UPDATE pokemon SET name='Pikachu',pid=25 WHERE id=104");
    migration_check(pv_exp_migrate_legacy($db, 2), 2, 'Resume converts only previously failed rows');
    migration_check(migration_count($db, 'SELECT COUNT(*) FROM pokemon_exp_migration'), 11, 'Resume adds no duplicate audit records');
    migration_check(migration_count($db, 'SELECT totalexp FROM members WHERE id=1'), 153236, 'Resume repairs aggregate including previously committed batches');

    // Aggregate failures happen after row commits. A rerun must repair summaries
    // even when no rows remain to convert; this is a real interrupted-upgrade case.
    $db->query('ALTER TABLE members CHANGE totalexp broken_totalexp BIGINT NOT NULL DEFAULT 0');
    $db->query("INSERT INTO pokemon (id,pid,name,lvl,exp,owner) VALUES (201,25,'Pikachu',5,2500,'1')");
    migration_expect_failure(fn() => pv_exp_migrate_legacy($db, 1), 'totalexp');
    migration_check((int)migration_row($db, 201)['exp_curve_version'], 1, 'Rows committed before aggregate failure');
    migration_check(migration_count($db, 'SELECT COUNT(*) FROM pokemon_exp_migration WHERE pokemon_id=201'), 1, 'Audit persists before aggregate failure');
    $db->query('ALTER TABLE members CHANGE broken_totalexp totalexp BIGINT NOT NULL DEFAULT 0');
    migration_check(pv_exp_migrate_legacy($db, 1), 0, 'Aggregate-only resume needs no row conversion');
    migration_check(migration_count($db, 'SELECT totalexp FROM members WHERE id=1'), 153361, 'Aggregate-only resume recomputes correct total');
    migration_check(migration_count($db, 'SELECT COUNT(*) FROM pokemon WHERE exp_curve_version=0'), 0, 'No legacy specimens remain');

    echo 'PASS ' . $checks . ' database EXP migration checks (atomic batches, audits, resume, aggregates, escrow and idempotence).' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL after ' . $checks . ' checks: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($db instanceof mysqli) $db->close();
}
