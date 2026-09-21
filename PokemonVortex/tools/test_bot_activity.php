<?php
declare(strict_types=1);
/**
 * Database regression for v34.0.2 wild activity accounting and encounter intent.
 * Run with --integration and an upgraded, disposable PV_TEST_DB_NAME beginning pv_test_ and
 * PV_TEST_DB_HOST/PORT/USER/PASS (PASSWORD is also accepted), or DB_SOCKET.
 * Mutates one bot in that test database and creates/drops a tiny sibling schema.
 * Never point this script at a live database. No browser or worker is required.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--integration', $argv, true)) {
    fwrite(STDERR, "This destructive fixture requires --integration and a disposable PV_TEST_DB_NAME beginning pv_test_.\n");
    exit(1);
}
if (!extension_loaded('mysqli')) { fwrite(STDERR, "mysqli is required.\n"); exit(1); }
require_once dirname(__DIR__) . '/includes/schema.php';

$checks = 0;
function activity_check($actual, $expected, string $label): void
{
    global $checks;
    $checks++;
    if ($actual !== $expected) throw new RuntimeException($label . ': expected '
        . var_export($expected, true) . ', got ' . var_export($actual, true));
}
function activity_expect_failure(callable $run, string $needle): void
{
    $caught = null;
    try { $run(); } catch (Throwable $error) { $caught = $error; }
    activity_check($caught !== null, true, 'Expected failure: ' . $needle);
    activity_check(str_contains($caught->getMessage(), $needle), true, 'Useful failure: ' . $needle);
}

class BotActivityDatabase extends mysqli
{
    public string $failQuery = '';
    public bool $returnFalse = false;
    public string $failPrepare = '';
    public int $fixtureUser = 0;
    public array $pending = [];
    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        if ($this->failQuery !== '' && str_starts_with($query, $this->failQuery)) {
            $this->failQuery = '';
            if ($this->returnFalse) return false;
            throw new RuntimeException('Injected migration write failure');
        }
        return parent::query($query, $result_mode);
    }
    public function prepare(string $query): mysqli_stmt|false
    {
        if ($this->failPrepare !== '' && str_starts_with($query, $this->failPrepare)) {
            $this->failPrepare = '';
            $this->pending = $this->fixtureUser > 0 ? $this->snapshot() : [];
            throw new RuntimeException('Injected encounter aggregate failure');
        }
        return parent::prepare($query);
    }
    public function snapshot(): array
    {
        $uid = $this->fixtureUser;
        return [
            'bot' => parent::query('SELECT * FROM bot_trainers WHERE user_id=' . $uid)->fetch_assoc(),
            'member' => parent::query('SELECT * FROM members WHERE id=' . $uid)->fetch_assoc(),
            'pokemon' => parent::query("SELECT * FROM pokemon WHERE owner='" . $uid . "' ORDER BY id")->fetch_all(MYSQLI_ASSOC),
            'stats' => parent::query("SELECT ps.* FROM pokemon_stats ps JOIN pokemon p ON p.id=ps.id WHERE p.owner='" . $uid . "' ORDER BY ps.id")->fetch_all(MYSQLI_ASSOC),
            'guide' => parent::query("SELECT id,amount FROM pguide WHERE name IN ('Pikachu','Pidgey') ORDER BY id")->fetch_all(MYSQLI_ASSOC),
        ];
    }
}
function activity_connect(string $name): BotActivityDatabase
{
    $password = getenv('PV_TEST_DB_PASS');
    if ($password === false) $password = getenv('PV_TEST_DB_PASSWORD');
    $db = new BotActivityDatabase(getenv('PV_TEST_DB_HOST') ?: '127.0.0.1',
        getenv('PV_TEST_DB_USER') ?: 'root', $password === false ? '' : $password,
        $name, (int)(getenv('PV_TEST_DB_PORT') ?: 3306), getenv('PV_TEST_DB_SOCKET') ?: null);
    $db->set_charset('utf8mb4');
    return $db;
}
function activity_migration_snapshot(mysqli $db): array
{
    return [
        $db->query('SELECT * FROM members ORDER BY id')->fetch_all(MYSQLI_ASSOC),
        $db->query('SELECT * FROM bot_trainers ORDER BY user_id')->fetch_all(MYSQLI_ASSOC),
        $db->query('SELECT * FROM pokemon ORDER BY id')->fetch_all(MYSQLI_ASSOC),
    ];
}
function activity_legacy_tables(mysqli $db): void
{
    $db->query('DROP TABLE IF EXISTS bot_trainers,members,pokemon');
    $db->query("CREATE TABLE members (id INT PRIMARY KEY,battle INT UNSIGNED NOT NULL DEFAULT 0,clan_name VARCHAR(50) NOT NULL DEFAULT '',total_poke INT NOT NULL DEFAULT 0,uniques INT NOT NULL DEFAULT 0,totalexp BIGINT NOT NULL DEFAULT 0,averageexp BIGINT NOT NULL DEFAULT 0,points DOUBLE NOT NULL DEFAULT 0) ENGINE=InnoDB");
    $db->query('CREATE TABLE bot_trainers (user_id INT PRIMARY KEY,wild_battles INT UNSIGNED NOT NULL DEFAULT 0,wild_wins INT UNSIGNED NOT NULL DEFAULT 0,captures INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB');
    $db->query('CREATE TABLE pokemon (id INT PRIMARY KEY,owner VARCHAR(45) NOT NULL,pid INT NOT NULL,exp BIGINT NOT NULL) ENGINE=InnoDB');
}
function activity_active_exp(array $snapshot): array
{
    $active = [];
    for ($slot = 1; $slot <= 6; $slot++) $active[] = (int)$snapshot['member']['s' . $slot];
    $exp = [];
    foreach ($snapshot['pokemon'] as $row) if (in_array((int)$row['id'], $active, true)) $exp[(int)$row['id']] = (int)$row['exp'];
    return $exp;
}

$db = null;
$migration = null;
$migrationName = '';
$exitCode = 0;
try {
    $name = (string)getenv('PV_TEST_DB_NAME');
    if (!preg_match('/^pv_test_[a-z0-9_]+$/D', $name)) throw new RuntimeException('Requires a disposable PV_TEST_DB_NAME beginning pv_test_.');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = activity_connect($name);
    $migrationName = substr($name, 0, 40) . '_activity_' . bin2hex(random_bytes(4));
    $db->query('CREATE DATABASE `' . $migrationName . '` CHARACTER SET utf8mb4');
    $migration = activity_connect($migrationName);

    // Empty installation: the first repair establishes native defaults even
    // when there are no rows to convert. A subsequently seeded bot stays native.
    activity_legacy_tables($migration);
    $changes = [];
    pv_schema_migrate_bot_wild_counters($migration, $changes);
    activity_check(pv_schema_column_exists($migration, 'bot_trainers', 'wild_encounters'), true, 'Fresh repair adds encounter cursor');
    $migration->query('INSERT INTO members (id,battle) VALUES (1,0)');
    $migration->query('INSERT INTO bot_trainers (user_id) VALUES (1)');
    $fresh = $migration->query('SELECT wild_encounters,wild_stats_version FROM bot_trainers')->fetch_assoc();
    activity_check((int)$fresh['wild_encounters'], 0, 'Fresh cursor begins at zero');
    activity_check((int)$fresh['wild_stats_version'], 1, 'New bot uses native counter marker');
    $freshBefore = activity_migration_snapshot($migration);
    pv_schema_migrate_bot_wild_counters($migration, $changes);
    activity_check(activity_migration_snapshot($migration), $freshBefore, 'Fresh repeated repair does not change state');

    // Populated legacy rows include deliberately inconsistent unsigned totals.
    activity_legacy_tables($migration);
    $migration->query('INSERT INTO members (id,battle) VALUES (1,90),(2,1),(3,0),(4,999)');
    $migration->query('UPDATE members SET points=135 WHERE id=1');
    $migration->query('UPDATE members SET points=888 WHERE id=4');
    $migration->query("INSERT INTO pokemon VALUES (1,'1',25,10000),(2,'1',16,20000),(3,'2',25,5000)");
    $migration->query('INSERT INTO bot_trainers VALUES (1,100,80,60),(2,2,1,8),(3,0,0,0)');
    $changes = [];
    pv_schema_add_column($migration, 'bot_trainers', 'wild_encounters', 'INT UNSIGNED NOT NULL DEFAULT 0', $changes);
    pv_schema_add_column($migration, 'bot_trainers', 'wild_stats_version', 'TINYINT UNSIGNED NOT NULL DEFAULT 0', $changes);
    $legacyBefore = activity_migration_snapshot($migration);
    $migration->failQuery = 'UPDATE members m JOIN bot_trainers';
    $migration->returnFalse = true;
    mysqli_report(MYSQLI_REPORT_OFF);
    activity_expect_failure(function () use ($migration, &$changes): void { pv_schema_migrate_bot_wild_counters($migration, $changes); }, 'separate trainer wins');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    activity_check(activity_migration_snapshot($migration), $legacyBefore, 'False-return migration failure rolls back cursor and counters');
    $migration->returnFalse = false;
    $migration->failQuery = 'UPDATE bot_trainers SET wild_battles=';
    activity_expect_failure(function () use ($migration, &$changes): void { pv_schema_migrate_bot_wild_counters($migration, $changes); }, 'Injected migration write failure');
    activity_check(activity_migration_snapshot($migration), $legacyBefore, 'Exception after member repair rolls back the entire conversion');
    $migration->failPrepare = 'UPDATE members SET total_poke=?';
    activity_expect_failure(function () use ($migration, &$changes): void { pv_schema_migrate_bot_wild_counters($migration, $changes); }, 'Injected encounter aggregate failure');
    activity_check(activity_migration_snapshot($migration), $legacyBefore, 'Aggregate failure rolls back the migration marker and all counter repairs');
    activity_check((int)$migration->query("SELECT IS_FREE_LOCK('pokemon_vortex_bot_tick')")->fetch_row()[0], 1, 'Failed repair releases world worker lock');
    pv_schema_migrate_bot_wild_counters($migration, $changes);
    $rows = $migration->query('SELECT * FROM bot_trainers ORDER BY user_id')->fetch_all(MYSQLI_ASSOC);
    foreach ([[0,100,40,20,60],[1,2,0,0,8],[2,0,0,0,0]] as [$row, $encounters, $battles, $wins, $captures]) {
        foreach (['wild_encounters'=>$encounters,'wild_battles'=>$battles,'wild_wins'=>$wins,'captures'=>$captures,'wild_stats_version'=>1] as $column=>$expected) {
            activity_check((int)$rows[$row][$column], $expected, 'Legacy row ' . ($row + 1) . ' ' . $column);
        }
    }
    activity_check((int)$migration->query('SELECT battle FROM members WHERE id=1')->fetch_row()[0], 30, 'Legacy captures removed from trainer wins');
    activity_check((int)$migration->query('SELECT battle FROM members WHERE id=2')->fetch_row()[0], 0, 'Unsigned underflow clamps at zero');
    activity_check((int)$migration->query('SELECT battle FROM members WHERE id=4')->fetch_row()[0], 999, 'Human trainer wins remain unchanged');
    activity_check((float)$migration->query('SELECT points FROM members WHERE id=1')->fetch_row()[0], 102.0, 'Trainer score recalculates after captures are removed from wins');
    activity_check((float)$migration->query('SELECT points FROM members WHERE id=4')->fetch_row()[0], 888.0, 'Human score remains unchanged');
    $converted = activity_migration_snapshot($migration);
    pv_schema_migrate_bot_wild_counters($migration, $changes);
    activity_check(activity_migration_snapshot($migration), $converted, 'Populated repeated repair is idempotent');
    $migration->query('INSERT INTO members (id,battle) VALUES (5,7)');
    $migration->query('INSERT INTO bot_trainers (user_id,wild_battles,wild_wins,captures,wild_encounters) VALUES (5,10,7,4,14)');
    $native = activity_migration_snapshot($migration);
    pv_schema_migrate_bot_wild_counters($migration, $changes);
    activity_check(activity_migration_snapshot($migration), $native, 'New native bot is never reinterpreted as legacy');
    $migration->close();
    $migration = null;
    $db->query('DROP DATABASE `' . $migrationName . '`');
    $migrationName = '';

    // Use one ordinary trainer and six unevolved species with no level-based
    // evolution. A small retention cap exercises both retained and released catches.
    if (!pv_schema_column_exists($db, 'bot_trainers', 'wild_stats_version')) throw new RuntimeException('Run Upgrade / Repair on the disposable fixture before this test.');
    $uid = pv_bot_seed_one($db, 10000);
    $db->fixtureUser = $uid;
    $db->query('UPDATE bot_trainers SET enabled=1,wild_encounters=0,wild_battles=0,wild_wins=0,captures=0,wild_stats_version=1,last_action=\'idle\' WHERE user_id=' . $uid);
    $db->query('DELETE FROM ai_activity WHERE bot_user_id=' . $uid);
    $db->query("DELETE ps FROM pokemon_stats ps JOIN pokemon p ON p.id=ps.id WHERE p.owner='" . $uid . "'");
    $db->query("DELETE FROM pokemon WHERE owner='" . $uid . "'");
    $bot = pv_bot_profile($db, $uid);
    $guide = pv_bot_population_row($db, 'SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=?', 's', ['Pikachu']);
    $wildGuide = pv_bot_population_row($db, 'SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=?', 's', ['Pidgey']);
    if (!$bot || !$guide || !$wildGuide) throw new RuntimeException('Missing seeded trainer or species fixture');
    $ids = [];
    for ($slot = 0; $slot < 6; $slot++) $ids[] = pv_bot_create_pokemon($db, $uid, (string)$bot['username'], $guide, 20);
    pv_bot_population_write($db, 'UPDATE members SET s1=?,s2=?,s3=?,s4=?,s5=?,s6=?,battle=0,losses=0,clan_name=? WHERE id=?', 'iiiiiisi', [...$ids, '', $uid]);
    pv_recalculate_trainer_progress($db, $uid, false);
    $profile = pv_bot_activity_profile(10000);
    $profile['collection_cap'] = 8;
    $wild = ['level'=>20,'display'=>'Pidgey','guide'=>$wildGuide];

    // Faults occur after the outcome/cursor writes, at aggregate refresh. Exercise
    // every mutation branch, then prove retry keeps the same persisted intent.
    foreach (['caught_wild','won_wild','lost_wild'] as $required) {
        $db->query('UPDATE bot_trainers SET wild_encounters=' . ($required === 'caught_wild' ? 0 : 1) . ' WHERE user_id=' . $uid);
        $observed = false;
        for ($attempt = 0; $attempt < 80 && !$observed; $attempt++) {
            $before = $db->snapshot();
            $db->failPrepare = 'UPDATE members SET total_poke=?';
            activity_expect_failure(fn() => pv_bot_simulate_wild($db, $bot, $wild, $profile), 'Injected encounter aggregate failure');
            activity_check($db->snapshot(), $before, 'Failed encounter rolls back Pokémon, friendship, counts, roster and cursor');
            activity_check(pv_bot_wild_intent($db->snapshot()['bot']), pv_bot_wild_intent($before['bot']), 'Retry preserves committed intent');
            $observed = ($db->pending['bot']['last_action'] ?? '') === $required;
        }
        activity_check($observed, true, 'Rollback exercised actual ' . $required . ' mutations');
    }
    $db->query('UPDATE bot_trainers SET wild_encounters=0 WHERE user_id=' . $uid);
    $outcomes = ['caught_wild'=>0,'released_wild'=>0,'won_wild'=>0,'lost_wild'=>0];
    $intents = ['train'=>0,'capture'=>0];
    for ($encounter = 0; $encounter < 128; $encounter++) {
        if ($encounter === 64) {
            // The stale profile is deliberately reused across a new connection.
            $replacement = activity_connect($name);
            $replacement->fixtureUser = $uid;
            $db->close();
            $db = $replacement;
        }
        $before = $db->snapshot();
        $expectedIntent = pv_bot_wild_intent($before['bot']);
        $result = pv_bot_simulate_wild($db, $bot, $wild, $profile);
        $after = $db->snapshot();
        $action = (string)$result['action'];
        activity_check(isset($outcomes[$action]), true, 'Encounter returns a specific outcome');
        $outcomes[$action]++;
        $intents[$result['intent']]++;
        activity_check($result['intent'], $expectedIntent, 'Stale caller and reconnect cannot reset persisted intent');
        activity_check((int)$after['bot']['wild_encounters'] - (int)$before['bot']['wild_encounters'], 1, 'Only committed encounters advance cursor');
        activity_check((int)$after['bot']['wild_battles'] - (int)$before['bot']['wild_battles'], $result['captured'] ? 0 : 1, 'Captures are not wild battles');
        activity_check((int)$after['bot']['wild_wins'] - (int)$before['bot']['wild_wins'], $result['won'] ? 1 : 0, 'Only defeats count as wild wins');
        activity_check((int)$after['bot']['captures'] - (int)$before['bot']['captures'], $result['captured'] ? 1 : 0, 'Capture counter reflects capture results');
        activity_check((int)$after['member']['battle'] - (int)$before['member']['battle'], $result['won'] ? 1 : 0, 'Trainer victory counter excludes captures');
        activity_check((int)$after['member']['losses'] - (int)$before['member']['losses'], $action === 'lost_wild' ? 1 : 0, 'Wild defeats persist actual trainer losses');
        activity_check($after['bot']['last_action'], $action, 'Stored last action agrees with returned result');
        $ownedDelta = count($after['pokemon']) - count($before['pokemon']);
        activity_check($ownedDelta, $action === 'caught_wild' ? 1 : 0, 'Retained and released captures have correct collection effect');
        $oldExp = activity_active_exp($before);
        $newExp = activity_active_exp($after);
        if ($result['won']) {
            $gain = pv_exp_battle_gain('Pidgey', 20, 6);
            foreach ($oldExp as $id=>$exp) activity_check($newExp[$id] - $exp, $gain, 'Defeated species yield shared with active teammate');
            activity_check((int)$result['exp'], $gain * 6, 'Reported team reward matches stored EXP');
        } else {
            activity_check($newExp, $oldExp, 'Capture or loss awards no training EXP');
            activity_check((int)$result['exp'], 0, 'Capture or loss reports zero EXP');
        }
        activity_check((int)$after['member']['total_poke'], count($after['pokemon']), 'Collection aggregate commits with encounter');
        activity_check((int)$after['member']['totalexp'], array_sum(array_map(static fn(array $row): int => (int)$row['exp'], $after['pokemon'])), 'EXP aggregate commits with encounter');
        pv_rival_log_bot_world_action($db, $bot, $action, $result);
    }
    activity_check($intents, ['train'=>64,'capture'=>64], 'Long-running work reserves exactly half of encounters for training');
    foreach ($outcomes as $action=>$count) activity_check($count > 0, true, 'Actual database run includes ' . $action);
    activity_check($outcomes['caught_wild'], 2, 'Only available reserve capacity is retained');
    $feed = $db->query('SELECT category,headline FROM ai_activity WHERE bot_user_id=' . $uid)->fetch_all(MYSQLI_ASSOC);
    activity_check(count($feed), 128, 'Every committed wild result is visible in the activity feed');
    $feedWins = $feedLosses = $feedCaptures = 0;
    foreach ($feed as $item) {
        if ($item['category'] === 'capture') $feedCaptures++;
        if ($item['headline'] === 'Won a wild battle') $feedWins++;
        if ($item['headline'] === 'Lost a wild battle') $feedLosses++;
    }
    activity_check($feedWins, $outcomes['won_wild'], 'Visible wild wins exactly match actual wins');
    activity_check($feedLosses, $outcomes['lost_wild'], 'Visible wild losses exactly match actual losses');
    activity_check($feedCaptures, $outcomes['caught_wild'] + $outcomes['released_wild'], 'Visible catches exactly match actual captures');
    echo 'PASS ' . $checks . ' bot activity database checks: ' . json_encode(['intents'=>$intents,'outcomes'=>$outcomes], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL after ' . $checks . ' checks: ' . $error->getMessage() . PHP_EOL . $error->getTraceAsString() . PHP_EOL);
    $exitCode = 1;
} finally {
    if ($migration instanceof mysqli) $migration->close();
    if ($db instanceof mysqli) {
        if ($migrationName !== '') $db->query('DROP DATABASE IF EXISTS `' . $migrationName . '`');
        $db->close();
    }
}
exit($exitCode);
