<?php
declare(strict_types=1);

/** Additive population repair; gameplay helpers are provided by bot_runtime.php. */
const PV_BOT_POPULATION_TARGET = 10000;
const PV_BOT_ORIGINAL_POPULATION = 2000;
// The accepted first 5,000 retain their seed policy; new arrivals fill regions.
const PV_BOT_REGIONAL_EXPANSION_START = 5001;

function pv_bot_starter_level(int $index): int
{
    if ($index < 1 || $index > PV_BOT_POPULATION_TARGET) {
        throw new InvalidArgumentException('Bot index is outside the supported population.');
    }
    // Preserve the original seed contract. New beginners match human signup.
    return $index <= PV_BOT_ORIGINAL_POPULATION ? 12 + (($index - 1) % 17) : 18;
}

/** Execute a required population operation, including under MYSQLI_REPORT_OFF. */
function pv_bot_population_statement(mysqli $db, string $sql, string $types = '', array $values = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('Could not prepare autonomous trainer population operation.');
    try {
        if ($types !== '' && !$stmt->bind_param($types, ...$values)) {
            throw new RuntimeException('Could not bind autonomous trainer population operation.');
        }
        if (!$stmt->execute()) throw new RuntimeException('Could not execute autonomous trainer population operation: ' . $stmt->error);
        return $stmt;
    } catch (Throwable $e) {
        $stmt->close();
        throw $e;
    }
}

function pv_bot_population_row(mysqli $db, string $sql, string $types, array $values): ?array
{
    $stmt = pv_bot_population_statement($db, $sql, $types, $values);
    try {
        $result = $stmt->get_result();
        if (!$result) throw new RuntimeException('Could not read autonomous trainer population lookup.');
        try {
            $row = $result->fetch_assoc();
            if ($row === false) throw new RuntimeException('Could not fetch autonomous trainer population lookup.');
            return $row;
        } finally { $result->free(); }
    } finally { $stmt->close(); }
}

function pv_bot_population_write(mysqli $db, string $sql, string $types, array $values, bool $oneRow = true): void
{
    $stmt = pv_bot_population_statement($db, $sql, $types, $values);
    try {
        if ($oneRow && $stmt->affected_rows !== 1) {
            throw new RuntimeException('Autonomous trainer population write did not affect its required row.');
        }
    } finally { $stmt->close(); }
}

/** Read once per bulk repair; callers update counts only after committed seeds. */
function pv_bot_population_counts(mysqli $db): array
{
    $counts = [];
    $result = $db->query('SELECT world_key,map_key,COUNT(*) AS bot_count FROM bot_trainers WHERE enabled=1 GROUP BY world_key,map_key');
    if (!$result) throw new RuntimeException('Could not read autonomous trainer map populations.');
    try {
        while (($row = $result->fetch_assoc()) !== null) {
            if ($row === false) throw new RuntimeException('Could not fetch autonomous trainer map populations.');
            $key = pv_bot_population_count_key((string)$row['world_key'], (string)$row['map_key']);
            $counts[$key] = max(0, (int)$row['bot_count']);
        }
    } finally { $result->free(); }
    return $counts;
}

/** Presence is mandatory during creation so a partial identity cannot commit. */
function pv_bot_population_write_presence(mysqli $db, int $uid, string $username, int $trainer, string $world, string $mapKey, int $x, int $y, int $now): void
{
    if (!pv_world_map_column_available($db)) throw new RuntimeException('Bot presence schema is unavailable. Run Upgrade / Repair.');
    pv_bot_population_write($db,
        'INSERT INTO mapusers (id,username,trainer,world_key,map,x,y,time) VALUES (?,?,?,?,?,?,?,CURTIME()) ON DUPLICATE KEY UPDATE username=VALUES(username),trainer=VALUES(trainer),world_key=VALUES(world_key),map=VALUES(map),x=VALUES(x),y=VALUES(y),time=CURTIME()',
        'isissii', [$uid,$username,$trainer,$world,$mapKey,$x,$y], false);
    if ($world !== 'vortex') {
        pv_bot_population_write($db,
            'INSERT INTO world_map_positions (user_id,world_key,area_key,x,y,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE x=VALUES(x),y=VALUES(y),updated_at=VALUES(updated_at)',
            'issiii', [$uid,$world,$mapKey,$x,$y,$now], false);
    }
}

/**
 * One complete account per transaction. Optional assignment is selected by the
 * locked bulk repair using the same live-count policy as a standalone seed.
 */
function pv_bot_seed_one(mysqli $db, int $index, ?array $assignment = null): int
{
    $starterLevel = pv_bot_starter_level($index);
    $row = pv_bot_population_row($db, 'SELECT b.user_id FROM bot_trainers b WHERE b.bot_index=? LIMIT 1', 'i', [$index]);
    if ($row !== null) return (int)$row['user_id'];

    $username = pv_bot_username($index);
    $row = pv_bot_population_row($db, 'SELECT id FROM members WHERE username=? LIMIT 1', 's', [$username]);
    if ($row !== null) {
        // A pre-existing human name is never claimed, modified or deleted.
        $base = 'PVAI' . str_pad((string)$index, 4, '0', STR_PAD_LEFT) . 'N';
        $available = false;
        for ($suffix = 0; $suffix <= 99; $suffix++) {
            $username = $base . ($suffix === 0 ? '' : (string)$suffix);
            if (pv_bot_population_row($db, 'SELECT id FROM members WHERE username=? LIMIT 1', 's', [$username]) === null) {
                $available = true;
                break;
            }
        }
        if (!$available) throw new RuntimeException('No safe fallback username is available for bot ' . $index . '.');
    }

    [$world,$mapKey] = $assignment ?? ($index <= 1000 ? pv_bot_region_assignment($index) : pv_bot_lowest_population_assignment($db, $index));
    if ($index >= PV_BOT_REGIONAL_EXPANSION_START
        && (!pv_world_is_region_world($world) || !isset(pv_world_ready_areas($world)[$mapKey]))) {
        throw new InvalidArgumentException('New regional trainers require a playable regional destination.');
    }
    [$x,$y] = pv_bot_spawn_for($db, $world, $mapKey, $index);
    $trainer = (($index - 1) % 28) + 1;
    $starters = pv_bot_starter_names();
    $starter = $starters[($index - 1) % count($starters)];
    $guide = pv_bot_population_row($db, 'SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=? LIMIT 1', 's', [$starter]);
    if ($guide === null) throw new RuntimeException('Bot starter data is unavailable for ' . $starter . '.');
    foreach (['badges','events','comments','items'] as $table) {
        if (!pv_bot_table_exists($db, $table)) throw new RuntimeException('Bot account defaults are unavailable for ' . $table . '.');
    }

    $now = time();
    $password = pv_bot_disabled_password_hash();
    $email = 'bot-' . $index . '@vortex.invalid';
    $number = (string)((($index - 1) % 18) + 1);
    $secret = bin2hex(random_bytes(20));
    if (!$db->begin_transaction()) throw new RuntimeException('Could not begin autonomous trainer creation.');
    try {
        pv_bot_population_write($db,
            'INSERT INTO members (username,password,email,registered,llogin,last_login,ip,eb,number,secret_key,total_poke,sidequest,money,battle,wins,losses) VALUES (?,?,?,?,?,?,?,?,?,?,0,1,0,0,0,0)',
            'ssssisssss', [$username,$password,$email,(string)$now,$now,(string)$now,'bot','1',$number,$secret]);
        $uid = (int)$db->insert_id;
        if ($uid <= 0) throw new RuntimeException('Autonomous trainer account ID is unavailable.');
        pv_bot_population_write($db,
            'INSERT INTO members_options (id,trainer,forum,skype,display,memonmap,messonoff,messnotifyonoff,layout) VALUES (?,?,?,?,?,?,?,?,?)',
            'iisssiiii', [$uid,$trainer,'','','No',1,0,0,2]);
        foreach ([['badges','id'],['events','id'],['comments','userid'],['items','uid']] as [$table,$column]) {
            pv_bot_population_write($db, 'INSERT INTO `' . $table . '` (`' . $column . '`) VALUES (?)', 'i', [$uid]);
        }
        $pokemonId = pv_bot_create_pokemon($db, $uid, $username, $guide, $starterLevel, $starter);
        if ($pokemonId <= 0) throw new RuntimeException('Autonomous trainer starter ID is unavailable.');
        pv_bot_population_write($db, 'UPDATE members SET s1=?,total_poke=1 WHERE id=?', 'ii', [$pokemonId,$uid]);
        pv_bot_population_write($db, 'UPDATE pguide SET amount=amount+1 WHERE id=?', 'i', [(int)$guide['id']]);
        $next = $now + random_int(5,45);
        pv_bot_population_write($db,
            "INSERT INTO bot_trainers (user_id,bot_index,enabled,trainer_sprite,world_key,map_key,x,y,next_action_at,last_action_at,last_action,last_wild_name,last_wild_level,wild_battles,wild_wins,captures,player_battles,player_wins,player_losses,created_at,updated_at) VALUES (?,?,1,?,?,?,?,?,?,?,?, '',0,0,0,0,0,0,0,?,?)",
            'iiissiiiisii', [$uid,$index,$trainer,$world,$mapKey,$x,$y,$next,$now,'spawned',$now,$now]);
        pv_bot_population_write_presence($db, $uid, $username, $trainer, $world, $mapKey, $x, $y, $now);
        if (!$db->commit()) throw new RuntimeException('Could not commit autonomous trainer creation.');
        return $uid;
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }
}

function pv_bot_ensure_population(mysqli $db, int $target = PV_BOT_POPULATION_TARGET): array
{
    if (!pv_bot_registry_ready($db)) throw new RuntimeException('Bot trainer registry is unavailable.');
    $target = max(0, min(PV_BOT_POPULATION_TARGET, $target));
    $lockResult = $db->query("SELECT GET_LOCK('pokemon_vortex_bot_population',0) AS acquired");
    if (!$lockResult) throw new RuntimeException('Could not acquire autonomous trainer population lock.');
    try { $locked = (int)(($lockResult->fetch_assoc()['acquired'] ?? 0)) === 1; }
    finally { $lockResult->free(); }
    if (!$locked) throw new RuntimeException('Autonomous trainer population repair is already running. Retry Upgrade / Repair after it finishes.');
    try {
        $existingIndexes = [];
        $result = $db->query('SELECT bot_index FROM bot_trainers ORDER BY bot_index');
        if (!$result) throw new RuntimeException('Could not read existing autonomous trainer identities.');
        try {
            while (($row = $result->fetch_assoc()) !== null) {
                if ($row === false) throw new RuntimeException('Could not fetch existing autonomous trainer identities.');
                $existingIndexes[(int)$row['bot_index']] = true;
            }
        } finally { $result->free(); }
        $existing = count($existingIndexes);
        $created = 0;
        $counts = null;
        for ($index = 1; $index <= $target; $index++) {
            if (isset($existingIndexes[$index])) continue;
            // Finite work (at most 10,000 accounts), refreshed only as repair advances.
            if (function_exists('set_time_limit')) @set_time_limit(60);
            if ($index <= 1000) {
                $assignment = pv_bot_region_assignment($index);
            } else {
                if ($counts === null) $counts = pv_bot_population_counts($db);
                $assignment = pv_bot_lowest_population_choice($counts, $index);
            }
            pv_bot_seed_one($db, $index, $assignment);
            $existingIndexes[$index] = true;
            $created++;
            if ($counts !== null) {
                $key = pv_bot_population_count_key((string)$assignment[0], (string)$assignment[1]);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        return ['target'=>$target,'existing'=>$existing,'created'=>$created,'total'=>$existing+$created];
    } finally {
        $db->query("DO RELEASE_LOCK('pokemon_vortex_bot_population')");
    }
}
