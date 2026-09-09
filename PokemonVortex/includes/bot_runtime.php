<?php
declare(strict_types=1);

require_once __DIR__ . '/map_runtime.php';
require_once __DIR__ . '/rival_runtime.php';
require_once __DIR__ . '/evolution.php';
require_once __DIR__ . '/bot_population.php';

/**
 * Persistent server-authoritative autonomous trainer runtime.
 *
 * Bots are represented by ordinary members/pokemon rows so trainer-snapshot and
 * Live Battle combat consume the same team data as human trainers. bot_trainers
 * is the control plane: it owns identity classification, simulated presence,
 * movement cadence and lightweight wild-battle/capture progression. Bots never
 * execute Battle Arena, event or Sidequest progression.
 */

function pv_bot_table_exists(mysqli $db, string $table): bool
{
    static $cache = [];
    $table = trim($table);
    if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
    $key = spl_object_id($db) . ':' . strtolower($table);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $cache[$key] = $exists;
}

function pv_bot_registry_ready(mysqli $db): bool
{
    static $cache = [];
    $key = spl_object_id($db);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='bot_trainers' LIMIT 1");
    if (!$stmt) return $cache[$key] = false;
    $stmt->execute();
    $ready = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $cache[$key] = $ready;
}

function pv_bot_is_bot(mysqli $db, int $userId): bool
{
    if ($userId <= 0 || !pv_bot_registry_ready($db)) return false;
    static $cache = [];
    $key = spl_object_id($db) . ':' . $userId;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $db->prepare('SELECT 1 FROM bot_trainers WHERE user_id=? AND enabled=1 LIMIT 1');
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $cache[$key] = $exists;
}

function pv_bot_profile(mysqli $db, int $userId): ?array
{
    if ($userId <= 0 || !pv_bot_registry_ready($db)) return null;
    $stmt = $db->prepare('SELECT b.*,m.username,m.s1,m.s2,m.s3,m.s4,m.s5,m.s6,m.total_poke,m.battle,m.wins,m.losses FROM bot_trainers b JOIN members m ON m.id=b.user_id WHERE b.user_id=? AND b.enabled=1 LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function pv_bot_starter_names(): array
{
    return ['Bulbasaur','Charmander','Squirtle','Pidgey','Chikorita','Cyndaquil','Totodile','Pichu','Treecko','Torchic','Mudkip','Poochyena','Turtwig','Chimchar','Piplup','Shinx','Snivy','Tepig','Oshawott','Lillipup','Chespin','Fennekin','Froakie','Bunnelby'];
}

/**
 * v25.2 activity classes. The first 24 identities are persistent Master Rivals,
 * the next 72 are Elite Rivals, and the remainder are regular rivals. The
 * classification is deterministic so an existing trainer database gains
 * the new behavior immediately without a destructive migration.
 */
function pv_bot_activity_profile(int $botIndex): array
{
    $botIndex = max(1, $botIndex);
    if ($botIndex <= 24) {
        return [
            'key'=>'master','label'=>'Master Rival','movement_min'=>4,'movement_max'=>6,
            'action_min'=>5,'action_max'=>12,'wild_chance'=>58,'training_multiplier'=>1.28,
            'capture_bonus'=>10,'collection_cap'=>48,'rank_attempt_chance'=>88,
            'rank_cooldown_min'=>90,'rank_cooldown_max'=>150,'ranked_win_bonus'=>7.0,
            'human_target_percent'=>55,
        ];
    }
    if ($botIndex <= 96) {
        return [
            'key'=>'elite','label'=>'Elite Rival','movement_min'=>3,'movement_max'=>4,
            'action_min'=>10,'action_max'=>22,'wild_chance'=>46,'training_multiplier'=>1.12,
            'capture_bonus'=>6,'collection_cap'=>42,'rank_attempt_chance'=>72,
            'rank_cooldown_min'=>180,'rank_cooldown_max'=>300,'ranked_win_bonus'=>3.5,
            'human_target_percent'=>50,
        ];
    }
    return [
        'key'=>'rival','label'=>'Active Rival','movement_min'=>2,'movement_max'=>3,
        'action_min'=>18,'action_max'=>40,'wild_chance'=>34,'training_multiplier'=>1.0,
        'capture_bonus'=>0,'collection_cap'=>36,'rank_attempt_chance'=>48,
        'rank_cooldown_min'=>600,'rank_cooldown_max'=>1200,'ranked_win_bonus'=>0.0,
        'human_target_percent'=>42,
    ];
}

function pv_bot_ranked_cooldown_for(array $profile, int $botIndex): int
{
    $min = max(60, (int)($profile['rank_cooldown_min'] ?? 600));
    $max = max($min, (int)($profile['rank_cooldown_max'] ?? $min));
    if ($max === $min) return $min;
    return $min + (($botIndex * 197) % (($max - $min) + 1));
}

const PV_BOT_RANKED_PULSE_SECONDS = 60;
const PV_BOT_RANKED_PULSE_TARGET_OPERATIONS = 16;
const PV_BOT_RANKED_PULSE_MAX_OPERATIONS = 16;
const PV_BOT_RANKED_CONTENDER_COOLDOWN = 120;

/**
 * Return the single authoritative wall-clock cycle used by both the server
 * ranked pulse and the Trainer Rankings browser clock. A manual reload inside
 * a cycle therefore receives the same refresh_at value instead of inventing a
 * fresh one-minute client countdown.
 *
 * @return array{server_now:int,bucket_start:int,refresh_at:int,bucket_id:int}
 */
function pv_bot_ranked_cycle(?int $now = null): array
{
    $now = max(0, $now ?? time());
    $bucketStart = intdiv($now, PV_BOT_RANKED_PULSE_SECONDS) * PV_BOT_RANKED_PULSE_SECONDS;
    return [
        'server_now' => $now,
        'bucket_start' => $bucketStart,
        'refresh_at' => $bucketStart + PV_BOT_RANKED_PULSE_SECONDS,
        'bucket_id' => intdiv($bucketStart, PV_BOT_RANKED_PULSE_SECONDS),
    ];
}

/** The same slot rotation continues across page requests within a minute. */
function pv_bot_ranked_lane(int $settled): string
{
    return ['contender','featured','contender','field'][max(0, $settled) % 4];
}

/** A request cap limits this batch, never the shared minute's total target. */
function pv_bot_ranked_remaining(int $settled, int $maxOperations): int
{
    return min(max(0, min(PV_BOT_RANKED_PULSE_MAX_OPERATIONS, $maxOperations)),
        max(0, PV_BOT_RANKED_PULSE_TARGET_OPERATIONS - max(0, $settled)));
}

/** Server-only cooldown SQL, derived from the same identity profiles as PHP. */
function pv_bot_ranked_cooldown_sql(): string
{
    $clauses = [];
    foreach ([24, 96, PV_BOT_POPULATION_TARGET] as $index) {
        $profile = pv_bot_activity_profile($index);
        $min = max(60, (int)$profile['rank_cooldown_min']);
        $span = max(1, (int)$profile['rank_cooldown_max'] - $min + 1);
        $expression = '(' . $min . ' + MOD(b.bot_index*197,' . $span . '))';
        $clauses[] = $index === PV_BOT_POPULATION_TARGET ? 'ELSE ' . $expression : 'WHEN b.bot_index<=' . $index . ' THEN ' . $expression;
    }
    return '(CASE ' . implode(' ', $clauses) . ' END)';
}

/**
 * Recurring leaders compete alongside featured identities and a rotating field.
 * Every lane validates owned active teams. Ratings are earned by settlement;
 * being selected here does not award points or guarantee a victory.
 */
function pv_bot_ranked_candidates_sql(string $lane, int $now, int $bucket): string
{
    if (!in_array($lane, ['contender','featured','field'], true)) throw new InvalidArgumentException('Invalid ranked candidate lane.');
    $cooldown = $lane === 'contender' ? (string)PV_BOT_RANKED_CONTENDER_COOLDOWN : pv_bot_ranked_cooldown_sql();
    $sql = 'SELECT b.*,m.username,rs.rating,COALESCE(rs.last_attack_at,0) rank_last_attack_at '
        . 'FROM bot_trainers b JOIN members m ON m.id=b.user_id '
        . 'JOIN trainer_rank_state rs ON rs.user_id=b.user_id '
        . 'WHERE b.enabled=1 AND COALESCE(m.s1,0)>0 '
        . 'AND COALESCE(rs.last_attack_at,0)<=' . max(0, $now) . '-' . $cooldown . ' '
        . 'AND EXISTS (SELECT 1 FROM pokemon ap WHERE ap.id=m.s1 AND CAST(ap.owner AS UNSIGNED)=m.id) ';
    if ($lane === 'featured') $sql .= 'AND b.bot_index<=96 ';
    $sql .= $lane === 'contender'
        ? 'ORDER BY rs.rating DESC,COALESCE(rs.last_attack_at,0) ASC,rs.ranked_wins DESC, '
        : 'ORDER BY COALESCE(rs.last_attack_at,0) ASC, ';
    return $sql . 'MOD(b.bot_index*37+' . max(0, $bucket) . ',10007) ASC,b.user_id ASC LIMIT 48';
}

/**
 * One shared, non-blocking service owns every autonomous ranked launch.
 * Up to 16 committed matches per real minute; no idle-time catch-up fabrication.
 * Half of scheduled slots favour RP leaders, a quarter Master/Elite identities,
 * and a quarter the full field. Empty lanes lend their slot to another lane.
 */
function pv_bot_ranked_pulse(mysqli $db, int $maxOperations = PV_BOT_RANKED_PULSE_MAX_OPERATIONS, int $budgetMs = 1200): int
{
    if (!pv_bot_registry_ready($db) || !pv_rival_ready($db) || $maxOperations <= 0) return 0;
    $maxOperations = min(PV_BOT_RANKED_PULSE_MAX_OPERATIONS, $maxOperations);
    $budgetMs = max(150, min(2000, $budgetMs));
    $lock = $db->query("SELECT GET_LOCK('pokemon_vortex_ranked_pulse',0) AS acquired");
    $acquired = $lock ? (int)($lock->fetch_assoc()['acquired'] ?? 0) : 0;
    if ($lock) $lock->free();
    if ($acquired !== 1) return 0;

    $started = microtime(true);
    $completed = 0;
    try {
        $now = time();
        $cycle = pv_bot_ranked_cycle($now);
        $bucketStart = (int)$cycle['bucket_start'];
        $bucketEnd = (int)$cycle['refresh_at'];
        $result = $db->query("SELECT COUNT(*) AS settled FROM rival_battles WHERE source='autonomous' "
            . 'AND created_at>=' . $bucketStart . ' AND created_at<' . $bucketEnd);
        // Fail closed: a failed counter read must not manufacture an empty quota.
        if (!$result) throw new RuntimeException('Could not read the ranked service count.');
        $alreadySettled = max(0, (int)($result->fetch_assoc()['settled'] ?? 0));
        $result->free();
        $needed = pv_bot_ranked_remaining($alreadySettled, $maxOperations);
        if ($needed <= 0) return 0;

        // Paid only when this minute still needs work; map polling after quota
        // completion does not repeatedly seed/normalize the entire population.
        pv_rival_ensure_all_states($db);
        $lanes = ['contender','featured','field'];
        $pools = [];
        foreach ($lanes as $lane) {
            if (((microtime(true) - $started) * 1000.0) >= $budgetMs || time() >= $bucketEnd) return 0;
            $result = $db->query(pv_bot_ranked_candidates_sql($lane, $now, (int)$cycle['bucket_id']));
            if (!$result) throw new RuntimeException('Could not load ranked competitors.');
            $pools[$lane] = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }

        $attempted = [];
        while ($completed < $needed) {
            // Check the budget even if every prior candidate failed. A protected
            // or broken target pool must not create an unbounded page request.
            if (((microtime(true) - $started) * 1000.0) >= $budgetMs || time() >= $bucketEnd) break;
            $preferred = pv_bot_ranked_lane($alreadySettled + $completed);
            $bot = null;
            $selectedLane = $preferred;
            foreach (array_unique(array_merge([$preferred], $lanes)) as $lane) {
                while ($pools[$lane] !== []) {
                    $candidate = array_shift($pools[$lane]);
                    $id = (int)$candidate['user_id'];
                    if (isset($attempted[$id])) continue;
                    $attempted[$id] = true;
                    $bot = $candidate;
                    $selectedLane = $lane;
                    break 2;
                }
            }
            if ($bot === null) break;
            $profile = pv_bot_activity_profile(max(1, (int)$bot['bot_index']));
            $bot['ranked_win_bonus'] = (float)$profile['ranked_win_bonus'];
            $bot['human_target_percent'] = (int)$profile['human_target_percent'];
            $cooldown = $selectedLane === 'contender' ? PV_BOT_RANKED_CONTENDER_COOLDOWN
                : pv_bot_ranked_cooldown_for($profile, (int)$bot['bot_index']);
            if (pv_rival_bot_ranked_operation($db, $bot, $cooldown) !== null) $completed++;
        }
    } catch (Throwable $e) {
        pv_log('Active ranked competition service failed: ' . $e->getMessage());
    } finally {
        $db->query("DO RELEASE_LOCK('pokemon_vortex_ranked_pulse')");
    }
    return $completed;
}

function pv_bot_disabled_password_hash(): string
{
    static $hash = null;
    if (is_string($hash) && $hash !== '') return $hash;
    // Bot accounts are explicitly rejected by checklogin.php. This unknown
    // one-request credential exists only to satisfy the normal members schema.
    $hash = pv_password_hash(bin2hex(random_bytes(32)));
    return $hash;
}

function pv_bot_username(int $index): string
{
    // Deterministic, collision-resistant and immediately recognizable in tools/logs.
    return 'VortexAI' . str_pad((string)$index, 4, '0', STR_PAD_LEFT);
}

function pv_bot_region_assignment(int $index): array
{
    // Preserve the accepted v23.7.0 placement contract for the original 1,000
    // identities. Existing bots are never relocated by a population repair.
    if ($index <= 400) return ['vortex', (string)(((($index - 1) % 25) + 1))];
    if ($index <= 1000) {
        $world = $index <= 700 ? 'kanto' : 'hoenn';
        $areas = array_keys(pv_world_ready_areas($world));
        if ($areas === []) return ['vortex', (string)(((($index - 1) % 25) + 1))];
        $offset = $world === 'kanto' ? $index - 401 : $index - 701;
        return [$world, (string)$areas[$offset % count($areas)]];
    }

    // Database-free fallback for tooling/tests. Runtime seeding for identities
    // above 1,000 uses pv_bot_lowest_population_assignment() below.
    $maps = pv_bot_population_maps_for_index($index);
    if ($maps === []) return ['vortex', (string)(((($index - 1) % 25) + 1))];
    $choice = $maps[($index - 1001) % count($maps)];
    return [(string)$choice['world'], (string)$choice['map']];
}

function pv_bot_population_maps(): array
{
    static $maps = null;
    if (is_array($maps)) return $maps;
    $maps = [];
    for ($map = 1; $map <= 25; $map++) $maps[] = ['world'=>'vortex','map'=>(string)$map];
    foreach (pv_world_region_keys() as $world) {
        foreach (array_keys(pv_world_ready_areas($world)) as $mapKey) {
            $maps[] = ['world'=>(string)$world,'map'=>(string)$mapKey];
        }
    }
    return $maps;
}

/** New expansion identities populate regional maps even if Vortex is empty. */
function pv_bot_population_maps_for_index(int $index): array
{
    $maps = pv_bot_population_maps();
    if ($index < PV_BOT_REGIONAL_EXPANSION_START) return $maps;
    static $regionalMaps = null;
    if ($regionalMaps === null) {
        $regionalMaps = array_values(array_filter($maps,
            static fn(array $map): bool => pv_world_is_region_world((string)$map['world'])));
    }
    if ($regionalMaps === []) {
        throw new RuntimeException('No playable regional maps are available for the new trainer population.');
    }
    return $regionalMaps;
}

function pv_bot_population_count_key(string $world, string $mapKey): string
{
    return pv_world_normalize_key($world) . "\0" . (string)$mapKey;
}

function pv_bot_lowest_population_choice(array $counts, int $index): array
{
    $maps = pv_bot_population_maps_for_index($index);
    if ($maps === []) return pv_bot_region_assignment($index);
    $lowest = PHP_INT_MAX;
    $candidates = [];
    foreach ($maps as $map) {
        $key = pv_bot_population_count_key((string)$map['world'], (string)$map['map']);
        $count = max(0, (int)($counts[$key] ?? 0));
        if ($count < $lowest) {
            $lowest = $count;
            $candidates = [$map];
        } elseif ($count === $lowest) {
            $candidates[] = $map;
        }
    }
    if ($candidates === []) return pv_bot_region_assignment($index);
    $offset = max(0, $index - 1001) % count($candidates);
    $choice = $candidates[$offset];
    return [(string)$choice['world'], (string)$choice['map']];
}

function pv_bot_lowest_population_assignment(mysqli $db, int $index): array
{
    return pv_bot_lowest_population_choice(pv_bot_population_counts($db), $index);
}

function pv_bot_spawn_occupied(mysqli $db, string $world, string $mapKey): array
{
    if (!pv_bot_registry_ready($db)) return [];
    $occupied = [];
    $stmt = $db->prepare('SELECT x,y FROM bot_trainers WHERE enabled=1 AND world_key=? AND map_key=?');
    if (!$stmt) return [];
    $stmt->bind_param('ss', $world, $mapKey);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $occupied[(int)$row['x'].':'.(int)$row['y']] = true;
    $stmt->close();
    return $occupied;
}

function pv_bot_spawn_for(mysqli $db, string $world, string $mapKey, int $seed = 0): array
{
    $world = pv_world_normalize_key($world);
    $seed = max(0, $seed);
    if ($world === 'vortex') {
        $map = max(1, min(25, (int)$mapKey));
        $mapKey = (string)$map;
        $blocks = pv_map_blocks($db, $map);
        $occupied = pv_bot_spawn_occupied($db, $world, $mapKey);
        $columns = 30; $rows = 25; $total = $columns * $rows;
        $start = $total > 0 ? (($seed * 73) % $total) : 0;
        for ($i = 0; $i < $total; $i++) {
            $cell = ($start + $i) % $total;
            $x = ($cell % $columns) + 1;
            $y = intdiv($cell, $columns) + 1;
            if (!pv_map_is_blocked($blocks, $x, $y) && !isset($occupied[$x.':'.$y])) return [$x,$y];
        }
        return pv_map_spawn($map, $blocks);
    }
    $area = pv_world_area($world, $mapKey);
    if (!$area) return [15,13];
    $mapKey = (string)$area['key'];
    $blocks = pv_world_blocks($db, $world, $mapKey);
    $occupied = pv_bot_spawn_occupied($db, $world, $mapKey);
    $point = pv_bot_region_spawn_from_points($area, $blocks, $occupied, $seed);
    if ($point === null) {
        throw new RuntimeException('No accessible AI spawn in ' . $world . '/' . $mapKey . '.');
    }
    return $point;
}

/**
 * Spawn only in components reachable from authored entrances. Original GBA
 * maps contain decorative islands/plateaus with passable pixels but no actual
 * entrance; scanning arbitrary open cells would strand trainers on those.
 */
function pv_bot_region_spawn_from_points(array $area, array $blocks, array $occupied, int $seed): ?array
{
    $anchors = array_values((array)($area['spawn_points'] ?? []));
    if ($anchors === []) return null;
    $offset = max(0, $seed) % count($anchors);
    $anchors = array_merge(array_slice($anchors, $offset), array_slice($anchors, 0, $offset));
    $queue = []; $visited = []; $candidates = []; $fallback = null;
    foreach ($anchors as $point) {
        $x = (int)($point[0] ?? 0); $y = (int)($point[1] ?? 0);
        if (!pv_bot_region_position_open($area, $blocks, $x, $y)) continue;
        $key = $x.':'.$y;
        if (isset($visited[$key])) continue;
        // Prefer an audited route/entrance tile before spreading nearby. A
        // passable cell elsewhere in the entrance component may be water or
        // decorative terrain even though it shares a walkable connection.
        if (!isset($occupied[$key])) return [$x,$y];
        $visited[$key] = true; $queue[] = [$x,$y];
        $fallback ??= [$x,$y];
    }
    // Bounded breadth-first placement spreads local arrivals while never
    // crossing a blocked wall or visiting an isolated decorative component.
    for ($head = 0; $head < count($queue) && $head < 4096 && count($candidates) < 64; $head++) {
        [$x,$y] = $queue[$head];
        if (!isset($occupied[$x.':'.$y])) $candidates[] = [$x,$y];
        foreach ([[0,-1],[0,1],[-1,0],[1,0]] as [$dx,$dy]) {
            $tx = $x + $dx; $ty = $y + $dy; $key = $tx.':'.$ty;
            if (isset($visited[$key]) || !pv_bot_region_position_open($area, $blocks, $tx, $ty)) continue;
            $visited[$key] = true; $queue[] = [$tx,$ty];
        }
    }
    // A fully occupied entrance component may share a legal tile, matching the
    // existing presence behavior, but can never force a spawn into collision.
    return $candidates === [] ? $fallback : $candidates[(max(0, $seed) * 73) % count($candidates)];
}

function pv_bot_random_ability(mysqli $db, string $species): string
{
    $stmt = $db->prepare('SELECT ability1,ability2,ability3 FROM abilities WHERE name=? LIMIT 1');
    if (!$stmt) return '';
    $stmt->bind_param('s', $species);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $choices = array_values(array_filter([(string)($row['ability1'] ?? ''),(string)($row['ability2'] ?? ''),(string)($row['ability3'] ?? '')], static fn(string $v): bool => trim($v) !== ''));
    return $choices ? (string)$choices[array_rand($choices)] : '';
}

function pv_bot_create_pokemon(mysqli $db, int $userId, string $username, array $guide, int $level, string $displayName = '', string $ball = 'Poke Ball'): int
{
    $level = max(2, min(100, $level));
    $species = trim((string)($guide['name'] ?? ''));
    if ($species === '') throw new RuntimeException('Bot capture species is unavailable.');
    $name = trim($displayName) !== '' ? trim($displayName) : $species;
    $pid = max(1, (int)($guide['id'] ?? 0));
    $moves = [];
    for ($i = 1; $i <= 4; $i++) $moves[$i] = trim((string)($guide['a'.$i] ?? '')) ?: 'Struggle';
    $t1 = trim((string)($guide['type1'] ?? 'Normal')) ?: 'Normal';
    $t2 = trim((string)($guide['type2'] ?? ''));
    $exp = $level * 500;
    $gender = random_int(0,1) === 0 ? 'Male' : 'Female';
    $stmt = $db->prepare('INSERT INTO pokemon (pid,name,a1,a2,a3,a4,lvl,exp,t1,t2,rowner,owner,ball,gender,ot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    if (!$stmt) throw new RuntimeException('Could not prepare bot Pokémon creation.');
    $stmt->bind_param('isssssiisssisss', $pid, $name, $moves[1], $moves[2], $moves[3], $moves[4], $level, $exp, $t1, $t2, $username, $userId, $ball, $gender, $username);
    if (!$stmt->execute()) { $error=$stmt->error; $stmt->close(); throw new RuntimeException('Could not create bot Pokémon: '.$error); }
    $pokemonId = (int)$db->insert_id;
    $stmt->close();

    $ivs = [];
    for ($i=0;$i<6;$i++) $ivs[] = random_int(1,31);
    $natures=['Hardy','Lonely','Brave','Adamant','Naughty','Bold','Docile','Relaxed','Impish','Lax','Timid','Hasty','Serious','Jolly','Naive','Modest','Mild','Quiet','Bashful','Rash','Calm','Gentle','Sassy','Careful','Quirky'];
    $nature=$natures[array_rand($natures)];
    $ability=pv_bot_random_ability($db,$species);
    $happiness=70;
    $displayForm = $name !== $species ? $name : '';
    $stmt=$db->prepare('INSERT INTO pokemon_stats (id,hp_iv,attack_iv,defense_iv,spatk_iv,spdef_iv,speed_iv,nature,ability,ball,gender,ot,happiness,display_form) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    if(!$stmt) throw new RuntimeException('Could not prepare bot Pokémon stats.');
    $stmt->bind_param('iiiiiiisssssis',$pokemonId,$ivs[0],$ivs[1],$ivs[2],$ivs[3],$ivs[4],$ivs[5],$nature,$ability,$ball,$gender,$username,$happiness,$displayForm);
    if(!$stmt->execute()){$error=$stmt->error;$stmt->close();throw new RuntimeException('Could not create bot Pokémon stats: '.$error);}
    $stmt->close();
    return $pokemonId;
}

function pv_bot_assign_team_slot(mysqli $db, int $userId, int $pokemonId, int $level): void
{
    $stmt=$db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? FOR UPDATE');
    if(!$stmt)throw new RuntimeException('Could not lock bot active team.');
    $stmt->bind_param('i',$userId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$row)throw new RuntimeException('Bot active team owner is unavailable.');
    for($slot=1;$slot<=6;$slot++){
        if(max(0,(int)($row['s'.$slot]??0))===0){
            $stmt=$db->prepare('UPDATE members SET s'.$slot.'=? WHERE id=?');
            if(!$stmt)throw new RuntimeException('Could not prepare bot active-team assignment.');
            $stmt->bind_param('ii',$pokemonId,$userId);
            if(!$stmt->execute()||$stmt->affected_rows!==1){$error=$stmt->error;$stmt->close();throw new RuntimeException('Could not assign bot active-team Pokémon: '.$error);}
            $stmt->close();return;
        }
    }
    // Once a team is full, promote a meaningfully stronger new catch over the
    // weakest current team member. Reserve collection ownership is never lost.
    $ids=[];for($slot=1;$slot<=6;$slot++)$ids[]=max(0,(int)($row['s'.$slot]??0));
    $idList=implode(',',array_map('intval',$ids));
    if($idList==='')return;
    $result=$db->query("SELECT id,lvl FROM pokemon WHERE id IN ({$idList}) AND CAST(owner AS UNSIGNED)=".(int)$userId);
    if(!$result)throw new RuntimeException('Could not inspect the bot active team.');
    $levels=[];while($p=$result->fetch_assoc())$levels[(int)$p['id']]=(int)$p['lvl'];$result->free();
    $weakestSlot=0;$weakestLevel=PHP_INT_MAX;
    for($slot=1;$slot<=6;$slot++){ $id=(int)$row['s'.$slot];$l=$levels[$id]??PHP_INT_MAX;if($l<$weakestLevel){$weakestLevel=$l;$weakestSlot=$slot;} }
    if($weakestSlot>0 && $level >= ($weakestLevel + 4)){
        $stmt=$db->prepare('UPDATE members SET s'.$weakestSlot.'=? WHERE id=?');
        if(!$stmt)throw new RuntimeException('Could not prepare bot team promotion.');
        $stmt->bind_param('ii',$pokemonId,$userId);
        if(!$stmt->execute()||$stmt->affected_rows!==1){$error=$stmt->error;$stmt->close();throw new RuntimeException('Could not promote bot team Pokémon: '.$error);}
        $stmt->close();
    }
}

function pv_bot_write_presence(mysqli $db,int $uid,string $username,int $trainer,string $world,string $mapKey,int $x,int $y,int $now): void
{
    if(!pv_world_map_column_available($db))return;
    $stmt=$db->prepare('INSERT INTO mapusers (id,username,trainer,world_key,map,x,y,time) VALUES (?,?,?,?,?,?,?,CURTIME()) ON DUPLICATE KEY UPDATE username=VALUES(username),trainer=VALUES(trainer),world_key=VALUES(world_key),map=VALUES(map),x=VALUES(x),y=VALUES(y),time=CURTIME()');
    if($stmt){$stmt->bind_param('isissii',$uid,$username,$trainer,$world,$mapKey,$x,$y);$stmt->execute();$stmt->close();}
    if($world!=='vortex'){
        $stmt=$db->prepare('INSERT INTO world_map_positions (user_id,world_key,area_key,x,y,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE x=VALUES(x),y=VALUES(y),updated_at=VALUES(updated_at)');
        if($stmt){$stmt->bind_param('issiii',$uid,$world,$mapKey,$x,$y,$now);$stmt->execute();$stmt->close();}
    }
}

function pv_bot_move_vortex(mysqli $db,array $bot): array
{
    $map=max(1,min(25,(int)$bot['map_key']));$x=(int)$bot['x'];$y=(int)$bot['y'];
    $directions=[1,2,3,4,5,6,7,8];shuffle($directions);$blocks=pv_map_blocks($db,$map);
    foreach($directions as $direction){
        $delta=pv_map_direction_delta($direction);if(!$delta)continue;
        if(pv_map_ledge_blocked($map,$x,$y,$direction))continue;
        $tx=$x+$delta[0];$ty=$y+$delta[1];$transition=pv_map_transition($map,$tx,$ty);
        if($transition){[$nextMap,$nx,$ny]=$transition;$targetBlocks=pv_map_blocks($db,$nextMap);if(!pv_map_is_blocked($targetBlocks,$nx,$ny))return['world'=>'vortex','map'=>(string)$nextMap,'x'=>$nx,'y'=>$ny,'moved'=>true];continue;}
        if($tx<1||$tx>30||$ty<1||$ty>25)continue;
        if(!pv_map_step_blocked($blocks,$x,$y,$tx,$ty))return['world'=>'vortex','map'=>(string)$map,'x'=>$tx,'y'=>$ty,'moved'=>true];
    }
    return['world'=>'vortex','map'=>(string)$map,'x'=>$x,'y'=>$y,'moved'=>false];
}

function pv_bot_move_region(mysqli $db,array $bot): array
{
    $world = pv_world_normalize_key((string)$bot['world_key']);
    $mapKey = pv_world_area_key((string)$bot['map_key']);
    $area = pv_world_area($world, $mapKey);
    if (!$area) {
        [$world,$mapKey] = pv_bot_region_assignment((int)$bot['bot_index']);
        [$x,$y] = pv_bot_spawn_for($db, $world, $mapKey, (int)$bot['bot_index']);
        return ['world'=>$world,'map'=>$mapKey,'x'=>$x,'y'=>$y,'moved'=>true];
    }
    $x = (int)$bot['x']; $y = (int)$bot['y'];
    $blocks = pv_world_blocks($db, $world, $mapKey);
    // A repaired collision mask can invalidate a saved bot tile. Recover before
    // moving so old rows cannot remain inside newly corrected buildings/walls.
    if (!pv_bot_region_position_open($area, $blocks, $x, $y)) {
        [$x,$y] = pv_bot_spawn_for($db, $world, $mapKey, (int)$bot['bot_index']);
        if (!pv_bot_region_position_open($area, $blocks, $x, $y)) {
            throw new RuntimeException('No safe AI position in ' . $world . '/' . $mapKey . '.');
        }
        return ['world'=>$world,'map'=>$mapKey,'x'=>$x,'y'=>$y,'moved'=>true];
    }
    $directions = [1,2,3,4,5,6,7,8]; shuffle($directions);
    foreach ($directions as $direction) {
        $delta = pv_map_direction_delta($direction);
        if (!$delta) continue;
        // Use the player's movement stride and validate every fine-grid cell.
        // Resized expansion maps take two 16px steps to match the original
        // maps' 32px walking distance, stopping at walls and stair triggers.
        $walk = pv_world_walk_direction($area, $blocks, $x, $y, (int)$delta[0], (int)$delta[1]);
        if (empty($walk['moved'])) continue;
        $tx = (int)$walk['x']; $ty = (int)$walk['y'];
        $transition = $walk['transition'];
        if ($transition) {
            $target = pv_world_area((string)$transition['target_world'], (string)$transition['target_area']);
            if (!$target) continue;
            $nx = (int)$transition['target_x']; $ny = (int)$transition['target_y'];
            $targetBlocks = pv_world_blocks($db, (string)$target['world'], (string)$target['key']);
            if (pv_bot_region_position_open($target, $targetBlocks, $nx, $ny)) {
                return ['world'=>(string)$target['world'],'map'=>(string)$target['key'],'x'=>$nx,'y'=>$ny,'moved'=>true];
            }
            continue;
        }
        return ['world'=>$world,'map'=>$mapKey,'x'=>$tx,'y'=>$ty,'moved'=>true];
    }
    return ['world'=>$world,'map'=>$mapKey,'x'=>$x,'y'=>$y,'moved'=>false];
}

function pv_bot_region_position_open(array $area, array $blocks, int $x, int $y): bool
{
    return $x >= 1 && $x <= (int)($area['columns'] ?? 0)
        && $y >= 1 && $y <= (int)($area['rows'] ?? 0) && !isset($blocks[$x.':'.$y]);
}

/** The same connected-area links available to human regional explorers. */
function pv_bot_region_travel_targets(array $area): array
{
    $targets = [];
    foreach (pv_world_connected_areas($area) as $target) {
        if ((string)$target['key'] === (string)$area['key']) continue;
        $targets[pv_bot_population_count_key((string)$target['world'], (string)$target['key'])] = $target;
    }
    return array_values($targets);
}

/** Cache only regions with playable destinations, as in the player selector. */
function pv_bot_region_admission_areas(): array
{
    static $regions = null;
    if (is_array($regions)) return $regions;
    $regions = [];
    foreach (pv_world_region_keys() as $world) {
        $areas = pv_world_ready_areas((string)$world);
        if ($areas !== []) $regions[(string)$world] = array_values($areas);
    }
    return $regions;
}

/**
 * Give each playable map the same regional population share. A large region
 * needs more trainers than a small one; only regions above their rounded-up
 * share supply visitors. Vortex home identities retain their existing policy.
 */
function pv_bot_region_admission_choice(array $counts, string $currentWorld, int $seed): ?string
{
    $regions = pv_bot_region_admission_areas();
    $currentWorld = pv_world_normalize_key($currentWorld);
    if (!isset($regions[$currentWorld]) || count($regions) < 2) return null;
    $total = 0; $mapTotal = 0;
    foreach ($regions as $world => $areas) {
        $total += max(0, (int)($counts[$world] ?? 0));
        $mapTotal += count($areas);
    }
    if ($total <= 0 || $mapTotal <= 0) return null;
    $currentCount = max(0, (int)($counts[$currentWorld] ?? 0));
    $share = (int)ceil($total * count($regions[$currentWorld]) / $mapTotal);
    if ($currentCount <= $share) return null;
    $lowestCount = 0; $lowestMaps = 1; $targets = [];
    foreach ($regions as $world => $areas) {
        if ($world === $currentWorld) continue;
        $count = max(0, (int)($counts[$world] ?? 0));
        $mapCount = count($areas);
        if ($count >= (int)ceil($total * $mapCount / $mapTotal)) continue;
        // Compare density with integer cross-products; rotate equally sparse ties.
        $comparison = $count * $lowestMaps <=> $lowestCount * $mapCount;
        if ($targets === [] || $comparison < 0) {
            $lowestCount = $count; $lowestMaps = $mapCount; $targets = [$world];
        } elseif ($comparison === 0) $targets[] = $world;
    }
    return $targets === [] ? null : (string)$targets[max(0, $seed) % count($targets)];
}

/** Least-occupied playable destinations first, with stable rotating ties. */
function pv_bot_region_admission_destinations(string $world, array $counts, int $seed): array
{
    $areas = pv_bot_region_admission_areas()[$world] ?? [];
    if ($areas === []) return [];
    $offset = max(0, $seed) % count($areas);
    $ranked = [];
    for ($i = 0; $i < count($areas); $i++) {
        $area = $areas[($offset + $i) % count($areas)];
        $key = pv_bot_population_count_key($world, (string)$area['key']);
        $ranked[] = ['area'=>$area, 'count'=>max(0, (int)($counts[$key] ?? 0)), 'tie'=>$i];
    }
    usort($ranked, static fn(array $a, array $b): int => ($a['count'] <=> $b['count']) ?: ($a['tie'] <=> $b['tie']));
    return array_column($ranked, 'area');
}

/** A rare scheduled world-selection action, not a migration or a map warp. */
function pv_bot_admit_region(mysqli $db, array $bot): ?array
{
    $currentWorld = pv_world_normalize_key((string)($bot['world_key'] ?? ''));
    if (!pv_world_is_region_world($currentWorld)) return null;
    $counts = [];
    try { $result = $db->query('SELECT world_key,COUNT(*) AS bot_count FROM bot_trainers WHERE enabled=1 GROUP BY world_key'); }
    catch (mysqli_sql_exception $error) { return null; }
    // A failed population read must never be treated as an empty new world.
    if (!$result) return null;
    while ($row = $result->fetch_assoc()) {
        $world = pv_world_normalize_key((string)($row['world_key'] ?? ''));
        $counts[$world] = max(0, (int)($row['bot_count'] ?? 0));
    }
    $result->free();
    $seed = max(1, (int)($bot['bot_index'] ?? 1));
    $world = pv_bot_region_admission_choice($counts, $currentWorld, $seed);
    if ($world === null) return null;
    try { $mapCounts = pv_bot_population_counts($db); }
    catch (Throwable $error) { return null; }
    $areas = pv_bot_region_admission_destinations($world, $mapCounts, $seed);
    // Bound recovery work if operator collision overrides temporarily close
    // entrances. A later scheduled action can retry without disrupting walking.
    for ($attempt = 0; $attempt < min(8, count($areas)); $attempt++) {
        $area = $areas[$attempt];
        $mapKey = (string)$area['key'];
        try { [$x,$y] = pv_bot_spawn_for($db, $world, $mapKey, $seed); }
        catch (RuntimeException $error) { continue; }
        $blocks = pv_world_blocks($db, $world, $mapKey);
        if (!pv_bot_region_position_open($area, $blocks, $x, $y)) continue;
        return ['world'=>$world,'map'=>$mapKey,'x'=>$x,'y'=>$y,'moved'=>true];
    }
    return null;
}

/**
 * Occasional map-link travel gives the existing persistent population access to
 * newly added routes, rooms and dungeon floors without reseeding identities or
 * resetting their collections. Walking still uses the same collision masks as
 * players, and every destination is resolved from the playable world catalog.
 */
function pv_bot_travel_region(mysqli $db, array $bot): ?array
{
    $area = pv_world_area((string)$bot['world_key'], (string)$bot['map_key']);
    if (!$area) return null;
    $targets = pv_bot_region_travel_targets($area);
    if ($targets === []) return null;
    shuffle($targets);
    foreach ($targets as $target) {
        $world = (string)$target['world']; $mapKey = (string)$target['key'];
        [$x,$y] = pv_bot_spawn_for($db, $world, $mapKey, max(1, (int)$bot['bot_index']));
        $blocks = pv_world_blocks($db, $world, $mapKey);
        if (!pv_bot_region_position_open($target, $blocks, $x, $y)) continue;
        return ['world'=>$world,'map'=>$mapKey,'x'=>$x,'y'=>$y,'moved'=>true];
    }
    return null;
}

function pv_bot_roll_wild(mysqli $db,string $world,string $mapKey,int $x,int $y,int $encounterChance=16): ?array
{
    $encounterChance=max(0,min(90,$encounterChance));
    if($encounterChance<=0||random_int(1,100)>$encounterChance)return null;
    if($world==='vortex'){
        $map=max(1,min(25,(int)$mapKey));
        try{$rolled=pv_vortex_encounter_roll(pv_map_encounter_mode($map,$x,$y),false,false,random_int(665,1000));}catch(Throwable $e){return null;}
        $display=trim((string)($rolled['display_name']??''));if($display==='')return null;$guide=pv_map_resolve_species($db,$display);if(!$guide)return null;
        return['guide'=>$guide,'display'=>$display,'level'=>pv_wild_capped_level((int)($rolled['level']??5),5)];
    }
    $area=pv_world_area($world,$mapKey);if(!$area)return null;$profileKey=trim((string)($area['encounter_profile']??''));if($profileKey==='')return null;$profile=pv_world_encounter_profiles($world)[$profileKey]??null;if(!is_array($profile))return null;
    $entries=(array)($profile['entries']??[]);$total=0;foreach($entries as $e)if(is_array($e)&&count($e)>=4)$total+=max(0,(int)$e[3]);if($total<=0)return null;
    $roll=random_int(1,$total);$picked=null;foreach($entries as $e){if(!is_array($e)||count($e)<4)continue;$roll-=max(0,(int)$e[3]);if($roll<=0){$picked=$e;break;}}if(!$picked)return null;
    $base=trim((string)$picked[0]);$min=max(2,(int)$picked[1]);$max=max($min,(int)$picked[2]);[$min,$max]=pv_wild_balanced_level_range($min,$max,2);$display=pv_world_vortex_variant_roll().$base;
    $stmt=$db->prepare('SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=? LIMIT 1');if(!$stmt)return null;$stmt->bind_param('s',$display);$stmt->execute();$guide=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$guide && $display!==$base){$display=$base;$stmt=$db->prepare('SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=? LIMIT 1');if($stmt){$stmt->bind_param('s',$display);$stmt->execute();$guide=$stmt->get_result()->fetch_assoc();$stmt->close();}}
    return $guide?['guide'=>$guide,'display'=>$display,'level'=>random_int($min,$max)]:null;
}


function pv_bot_move_burst(mysqli $db,array $bot,int $steps): array
{
    $steps=max(1,min(8,$steps));$current=$bot;$movedSteps=0;$last=['world'=>(string)($bot['world_key']??'vortex'),'map'=>(string)($bot['map_key']??'1'),'x'=>(int)($bot['x']??1),'y'=>(int)($bot['y']??1),'moved'=>false];
    $travelled = false;
    // Roll once per scheduled action, never once per footstep. Population reads
    // occur only on rare world-selection attempts; ordinary walking is unchanged.
    if (pv_world_is_region_world((string)$current['world_key'])) {
        $travel = random_int(1,96) === 1 ? pv_bot_admit_region($db, $current) : null;
        if ($travel === null && random_int(1,24) === 1) $travel = pv_bot_travel_region($db, $current);
        if ($travel !== null) {
            $last = $travel; $travelled = true;
            $current['world_key'] = (string)$travel['world']; $current['map_key'] = (string)$travel['map'];
            $current['x'] = (int)$travel['x']; $current['y'] = (int)$travel['y'];
        }
    }
    for($i=0;$i<$steps;$i++){
        $last=(string)($current['world_key']??'vortex')==='vortex'?pv_bot_move_vortex($db,$current):pv_bot_move_region($db,$current);
        if(empty($last['moved']))break;
        $movedSteps++;
        $current['world_key']=(string)$last['world'];$current['map_key']=(string)$last['map'];$current['x']=(int)$last['x'];$current['y']=(int)$last['y'];
    }
    $last['moved']=$travelled||$movedSteps>0;$last['moved_steps']=$movedSteps;$last['travelled']=$travelled;
    return $last;
}

function pv_bot_active_team_ids(mysqli $db,int $uid): array
{
    if($uid<=0)return [];
    $stmt=$db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');if(!$stmt)return [];
    $stmt->bind_param('i',$uid);$stmt->execute();$m=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();
    $ids=[];for($slot=1;$slot<=6;$slot++){$id=max(0,(int)($m['s'.$slot]??0));if($id>0&&!in_array($id,$ids,true))$ids[]=$id;}
    return $ids;
}

/** Award the same wild-battle EXP curve used by human Wild Battles to every
 * active AI team member. This intentionally trains the whole six-Pokémon rival
 * team rather than leaving reserve slots permanently under-levelled. */
function pv_bot_award_wild_training(mysqli $db,int $uid,int $wildLevel,float $multiplier=1.0): array
{
    $ids=pv_bot_active_team_ids($db,$uid);if($ids===[])return['exp'=>0,'level_ups'=>0,'team_ids'=>[]];
    $wildLevel=pv_wild_capped_level($wildLevel,1);$multiplier=max(0.75,min(1.5,$multiplier));
    $expGain=max(75,(int)round($wildLevel*55*$multiplier));$idList=implode(',',array_map('intval',$ids));$levelUps=0;
    $db->begin_transaction();
    try{
        $stmt=$db->prepare("SELECT id,lvl,exp FROM pokemon WHERE CAST(owner AS UNSIGNED)=? AND id IN ({$idList}) FOR UPDATE");if(!$stmt)throw new RuntimeException('Could not lock AI training team.');
        $stmt->bind_param('i',$uid);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
        $update=$db->prepare('UPDATE pokemon SET lvl=?,exp=? WHERE id=? AND CAST(owner AS UNSIGNED)=?');if(!$update)throw new RuntimeException('Could not prepare AI team progression.');
        foreach($rows as $row){$old=max(1,(int)$row['lvl']);$newExp=max(0,(int)$row['exp'])+$expGain;$new=$old>=100?100:min(100,max(1,(int)floor($newExp/500)));$levelUps+=max(0,$new-$old);$pid=(int)$row['id'];$update->bind_param('iiii',$new,$newExp,$pid,$uid);if(!$update->execute())throw new RuntimeException('Could not persist AI team experience.');}
        $update->close();
        $db->query("UPDATE pokemon_stats SET happiness=LEAST(255,happiness+1) WHERE id IN ({$idList})");
        $stmt=$db->prepare('UPDATE members SET battle=battle+1 WHERE id=?');if(!$stmt)throw new RuntimeException('Could not update AI wild-battle progression.');$stmt->bind_param('i',$uid);if(!$stmt->execute())throw new RuntimeException('Could not persist AI battle progression.');$stmt->close();
        $db->commit();
    }catch(Throwable $e){try{$db->rollback();}catch(Throwable $ignored){}throw $e;}
    return['exp'=>$expGain,'level_ups'=>$levelUps,'team_ids'=>$ids];
}

/** Automatically follow every currently satisfied level evolution, including a
 * second stage when an AI Pokémon has already passed both thresholds. Item,
 * happiness and special-move evolutions remain governed by their own rules. */
function pv_bot_auto_evolve_team(mysqli $db,int $uid,array $teamIds): array
{
    $evolved=[];
    foreach(array_values(array_unique(array_map('intval',$teamIds))) as $pokemonId){
        if($pokemonId<=0)continue;
        for($stage=0;$stage<3;$stage++){
            $pokemon=pv_evolution_load_owned_pokemon($db,$uid,$pokemonId);if(!$pokemon)break;
            $level=max(1,(int)($pokemon['lvl']??1));$gender=trim((string)($pokemon['stat_gender']??$pokemon['gender']??''));$selected=null;
            foreach(pv_evolution_rules_for((string)($pokemon['name']??'')) as $rule){
                if(strtolower(trim((string)($rule['method']??'')))!=='level')continue;
                if($level<max(1,(int)($rule['min_level']??1)))continue;
                $requiredGender=trim((string)($rule['required_gender']??''));if($requiredGender!==''&&strcasecmp($requiredGender,$gender)!==0)continue;
                $selected=$rule;break;
            }
            if(!$selected)break;
            try{$result=pv_evolve_pokemon($db,$uid,$pokemonId,pv_evolution_rule_key($selected),true);$evolved[]=$result;}
            catch(Throwable $e){pv_log('AI level evolution failed for '.$uid.'/'.$pokemonId.': '.$e->getMessage());break;}
        }
    }
    return $evolved;
}

function pv_bot_team_average_level(mysqli $db,int $uid): float
{
    $stmt=$db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');if(!$stmt)return 1.0;$stmt->bind_param('i',$uid);$stmt->execute();$m=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();$ids=[];for($i=1;$i<=6;$i++){if((int)($m['s'.$i]??0)>0)$ids[]=(int)$m['s'.$i];}if(!$ids)return 1.0;$idList=implode(',',array_map('intval',$ids));$r=$db->query("SELECT AVG(lvl) a FROM pokemon WHERE CAST(owner AS UNSIGNED)=".(int)$uid." AND id IN ({$idList})");if(!$r)return 1.0;$avg=(float)($r->fetch_assoc()['a']??1);$r->free();return max(1.0,$avg);
}

function pv_bot_simulate_wild(mysqli $db,array $bot,array $wild,array $activityProfile=[]): array
{
    $uid=(int)$bot['user_id'];$username=(string)$bot['username'];$level=pv_wild_capped_level((int)$wild['level'],2);
    if($activityProfile===[])$activityProfile=pv_bot_activity_profile(max(1,(int)($bot['bot_index']??1)));
    $avg=pv_bot_team_average_level($db,$uid);$classBonus=(float)($activityProfile['ranked_win_bonus']??0.0)*0.45;
    $winChance=max(54,min(97,(int)round(74+($avg-$level)*2.2+$classBonus)));$won=random_int(1,100)<=$winChance;$captured=false;$pokemonId=0;
    $training=['exp'=>0,'level_ups'=>0,'team_ids'=>[]];$evolutions=[];
    if($won){
        try{$training=pv_bot_award_wild_training($db,$uid,$level,(float)($activityProfile['training_multiplier']??1.0));$evolutions=pv_bot_auto_evolve_team($db,$uid,(array)$training['team_ids']);}
        catch(Throwable $e){pv_log('AI wild training failed for '.$uid.': '.$e->getMessage());}

        $stmt=$db->prepare('SELECT total_poke FROM members WHERE id=? LIMIT 1');$total=0;if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$total=(int)($stmt->get_result()->fetch_assoc()['total_poke']??0);$stmt->close();}
        $cap=max(6,(int)($activityProfile['collection_cap']??36));$catchChance=0;
        if($total<$cap){$catchChance=$total<6?82:($total<12?38:($total<20?20:($total<28?10:4)));$catchChance=min(92,$catchChance+max(0,(int)($activityProfile['capture_bonus']??0)));}
        if($catchChance>0 && random_int(1,100)<=$catchChance){
            $db->begin_transaction();
            try{
                $pokemonId=pv_bot_create_pokemon($db,$uid,$username,(array)$wild['guide'],$level,(string)$wild['display']);
                pv_bot_assign_team_slot($db,$uid,$pokemonId,$level);
                $stmt=$db->prepare('UPDATE members SET total_poke=total_poke+1 WHERE id=?');if(!$stmt)throw new RuntimeException('Could not prepare bot collection count update.');$stmt->bind_param('i',$uid);if(!$stmt->execute()||$stmt->affected_rows!==1){$error=$stmt->error;$stmt->close();throw new RuntimeException('Could not update bot collection count: '.$error);}$stmt->close();
                $stmt=$db->prepare('UPDATE pguide SET amount=amount+1 WHERE id=?');if(!$stmt)throw new RuntimeException('Could not prepare captured-species population update.');$pid=(int)$wild['guide']['id'];$stmt->bind_param('i',$pid);if(!$stmt->execute()){$error=$stmt->error;$stmt->close();throw new RuntimeException('Could not update captured-species population: '.$error);}$stmt->close();
                $db->commit();$captured=true;
            }catch(Throwable $e){try{$db->rollback();}catch(Throwable $ignored){}$pokemonId=0;pv_log('Bot capture failed for '.$uid.': '.$e->getMessage());}
        }
        try{pv_recalculate_trainer_progress($db,$uid,false);}catch(Throwable $e){pv_log('AI trainer progress refresh failed for '.$uid.': '.$e->getMessage());}
    }else{
        $stmt=$db->prepare('UPDATE members SET losses=losses+1 WHERE id=?');if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();}
    }
    return['won'=>$won,'captured'=>$captured,'pokemon_id'=>$pokemonId,'species'=>(string)$wild['display'],'level'=>$level,'exp'=>(int)($training['exp']??0),'level_ups'=>(int)($training['level_ups']??0),'evolutions'=>$evolutions];
}

function pv_bot_tick(mysqli $db,int $limit=24,string $focusWorld='',string $focusMap='',int $budgetMs=65): int
{
    if(!pv_bot_registry_ready($db))return 0;$limit=max(1,min(64,$limit));$budgetMs=max(15,min(150,$budgetMs));
    $focusWorld=trim($focusWorld);$focusMap=trim($focusMap);
    $lock=$db->query("SELECT GET_LOCK('pokemon_vortex_bot_tick',0) AS acquired");$acquired=$lock?(int)($lock->fetch_assoc()['acquired']??0):0;if($lock)$lock->free();if($acquired!==1)return 0;
    $processed=0;$now=time();$started=microtime(true);
    try{
        $rankReady=pv_rival_ready($db);
        $base=$rankReady
            ? 'SELECT b.*,m.username,COALESCE(rs.last_attack_at,0) rank_last_attack_at FROM bot_trainers b JOIN members m ON m.id=b.user_id LEFT JOIN trainer_rank_state rs ON rs.user_id=b.user_id WHERE b.enabled=1 AND b.next_action_at<='.(int)$now.' '
            : 'SELECT b.*,m.username,0 rank_last_attack_at FROM bot_trainers b JOIN members m ON m.id=b.user_id WHERE b.enabled=1 AND b.next_action_at<='.(int)$now.' ';
        if($focusWorld!==''&&$focusMap!==''){
            $stmt=$db->prepare($base.'ORDER BY CASE WHEN b.world_key=? AND b.map_key=? THEN 0 ELSE 1 END,b.next_action_at,CASE WHEN b.bot_index<=24 THEN 0 WHEN b.bot_index<=96 THEN 1 ELSE 2 END,b.bot_index LIMIT '.(int)$limit);
            if($stmt){$stmt->bind_param('ss',$focusWorld,$focusMap);$stmt->execute();$bots=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();}else $bots=[];
        }else{
            $result=$db->query($base.'ORDER BY b.next_action_at,CASE WHEN b.bot_index<=24 THEN 0 WHEN b.bot_index<=96 THEN 1 ELSE 2 END,b.bot_index LIMIT '.(int)$limit);$bots=$result?$result->fetch_all(MYSQLI_ASSOC):[];if($result)$result->free();
        }
        foreach($bots as $bot){
            if($processed>0&&((microtime(true)-$started)*1000.0)>=$budgetMs)break;
            $uid=(int)($bot['user_id']??0);
            try{
                $profile=pv_bot_activity_profile(max(1,(int)($bot['bot_index']??1)));$steps=random_int((int)$profile['movement_min'],(int)$profile['movement_max']);
                $move=pv_bot_move_burst($db,$bot,$steps);
                $world=(string)$move['world'];$mapKey=(string)$move['map'];$x=(int)$move['x'];$y=(int)$move['y'];$wild=pv_bot_roll_wild($db,$world,$mapKey,$x,$y,(int)$profile['wild_chance']);$wildResult=null;$action=!empty($move['moved'])?'move':'idle';
                if($wild){$wildResult=pv_bot_simulate_wild($db,$bot,$wild,$profile);$action=$wildResult['captured']?'caught_wild':($wildResult['won']?'won_wild':'lost_wild');}
                $next=$now+random_int((int)$profile['action_min'],(int)$profile['action_max']);$wildName=$wildResult?(string)$wildResult['species']:'';$wildLevel=$wildResult?(int)$wildResult['level']:0;$battleInc=$wildResult?1:0;$winInc=$wildResult&&!empty($wildResult['won'])?1:0;$captureInc=$wildResult&&!empty($wildResult['captured'])?1:0;
                $stmt=$db->prepare('UPDATE bot_trainers SET world_key=?,map_key=?,x=?,y=?,next_action_at=?,last_action_at=?,last_action=?,last_wild_name=?,last_wild_level=?,wild_battles=wild_battles+?,wild_wins=wild_wins+?,captures=captures+?,updated_at=? WHERE user_id=? AND enabled=1');
                if($stmt){$stmt->bind_param('ssiiiissiiiiii',$world,$mapKey,$x,$y,$next,$now,$action,$wildName,$wildLevel,$battleInc,$winInc,$captureInc,$now,$uid);$stmt->execute();$stmt->close();}
                pv_bot_write_presence($db,$uid,(string)$bot['username'],max(1,min(28,(int)$bot['trainer_sprite'])),$world,$mapKey,$x,$y,$now);

                try{
                    $activityBot=$bot;$activityBot['world_key']=$world;$activityBot['map_key']=$mapKey;$activityBot['ranked_win_bonus']=(float)$profile['ranked_win_bonus'];$activityBot['human_target_percent']=(int)$profile['human_target_percent'];
                    pv_rival_log_bot_world_action($db,$activityBot,$action,$wildResult);
                    if($wildResult&&!empty($wildResult['evolutions']))foreach((array)$wildResult['evolutions'] as $evo){pv_rival_log_ai_activity($db,$uid,'evolution','Evolved '.$evo['old_name'].' into '.$evo['new_name'],'Training pushed '.$evo['old_name'].' past its level requirement, evolving it into '.$evo['new_name'].'.');}
                    // Ranked matches are scheduled below by the single shared
                    // service; movement must not bypass its quota or cooldowns.
                }catch(Throwable $rivalError){pv_log('Rival Network bot activity failed for '.$uid.': '.$rivalError->getMessage());}
                $processed++;
            }catch(Throwable $e){
                pv_log('Autonomous trainer tick failed for '.$uid.': '.$e->getMessage());
                $retry=$now+180;$idle='idle';$stmt=$db->prepare('UPDATE bot_trainers SET next_action_at=?,last_action_at=?,last_action=?,updated_at=? WHERE user_id=? AND enabled=1');if($stmt){$stmt->bind_param('iisii',$retry,$now,$idle,$now,$uid);$stmt->execute();$stmt->close();}
            }
        }
        // Active-world requests also service the shared one-minute Ranked bucket.
        // This prevents autonomous Ranked play from depending on somebody having
        // Trainer Rankings open; the bucket accounting keeps the work bounded.
        if($rankReady){try{pv_bot_ranked_pulse($db,4,350);}catch(Throwable $rankPulseError){pv_log('Ranked service heartbeat failed: '.$rankPulseError->getMessage());}}
        try{pv_rival_housekeeping($db);}catch(Throwable $ignored){}
    }finally{$db->query("DO RELEASE_LOCK('pokemon_vortex_bot_tick')");}
    return $processed;
}

function pv_bot_live_choose_action(mysqli $db,array $state,int $botSlot): ?array
{
    $participant=$state['participants'][(string)$botSlot]??null;if(!is_array($participant))return null;$phase=(string)($state['phase']??'');
    if(in_array($phase,['select','switch'],true)){
        if(pv_live_runtime_active_is_ready($participant))return null;$best=null;foreach((array)$participant['team'] as $fighter){if((int)($fighter['hp']??0)<=0)continue;if($best===null||(int)$fighter['level']>(int)$best['level'])$best=$fighter;}return $best?['action'=>'select','payload'=>['pokemon_id'=>(int)$best['id']]]:null;
    }
    if($phase!=='command'||is_array($participant['command']??null)||!pv_live_runtime_active_is_ready($participant))return null;
    $activeIndex=pv_live_runtime_active_index($participant);if($activeIndex<0)return null;$active=$participant['team'][$activeIndex];$opponentSlot=$botSlot===1?2:1;$enemy=$state['participants'][(string)$opponentSlot]??[];$enemyIndex=pv_live_runtime_active_index((array)$enemy);$enemyFighter=$enemyIndex>=0?($enemy['team'][$enemyIndex]??[]):[];
    $hpRatio=(int)$active['max_hp']>0?((int)$active['hp']/(int)$active['max_hp']):1.0;
    if($hpRatio<0.24 && random_int(1,100)<=28){$choices=[];foreach((array)$participant['team'] as $fighter){if((int)($fighter['hp']??0)>0&&(int)$fighter['id']!==(int)$participant['active'])$choices[]=$fighter;}if($choices){usort($choices,static fn(array $a,array $b):int=>(int)$b['hp']<=>(int)$a['hp']);return['action'=>'switch','payload'=>['pokemon_id'=>(int)$choices[0]['id']]];}}
    $bestSlot=1;$bestScore=-INF;
    foreach((array)$active['moves'] as $index=>$moveName){$move=pv_combat_move_data($db,(string)$moveName,(string)($active['type1']??'Normal'));$effect=pv_combat_type_multiplier((string)$move['type'],(string)($enemyFighter['type1']??'Normal'),(string)($enemyFighter['type2']??''));$stab=(strcasecmp((string)$move['type'],(string)($active['type1']??''))===0||strcasecmp((string)$move['type'],(string)($active['type2']??''))===0)?1.5:1.0;$score=max(1,(int)$move['power'])*max(0.05,$effect)*$stab*max(0.25,(int)$move['accuracy']/100);$score*=random_int(92,108)/100;if($score>$bestScore){$bestScore=$score;$bestSlot=$index+1;}}
    return['action'=>'attack','payload'=>['move_slot'=>$bestSlot]];
}

function pv_bot_live_autoplay(mysqli $db,int $battleId,int $botSlot,int $humanSlot,int $botId,int $humanId,int $maxSteps=8): array
{
    $state=pv_live_runtime_load($db,$battleId,$botSlot,$humanSlot,$botId,$humanId);
    for($step=0;$step<$maxSteps;$step++){
        if(($state['phase']??'')==='complete')break;$choice=pv_bot_live_choose_action($db,$state,$botSlot);if(!$choice)break;
        $state=pv_live_runtime_submit($db,$battleId,$botSlot,$humanSlot,$botId,$humanId,(string)$choice['action'],(array)$choice['payload']);
        // Stop when the bot is waiting on the human command/selection. Continue
        // only to cover automatic replacement selection after a resolved turn.
        $botParticipant=$state['participants'][(string)$botSlot]??[];$phase=(string)($state['phase']??'');
        if($phase==='command' && is_array($botParticipant['command']??null))break;
        if(in_array($phase,['select','switch'],true) && pv_live_runtime_active_is_ready((array)$botParticipant))break;
    }
    return $state;
}

function pv_bot_settle_live_result(mysqli $db,int $battleId,int $botSlot,int $humanSlot,int $botId,int $humanId,string $outcome): void
{
    if(!in_array($botSlot,[1,2],true)||!in_array($humanSlot,[1,2],true)||$botSlot===$humanSlot||!in_array($outcome,['win','loss'],true))return;
    $settled='settled_'.$botSlot;$outcomeCol='outcome_'.$botSlot;$exp='reward_exp_'.$botSlot;$money='reward_money_'.$botSlot;$settledAt='settled_at_'.$botSlot;$now=time();
    $db->begin_transaction();
    try{
        $stmt=$db->prepare("SELECT {$settled} AS settled FROM live_battle WHERE id=? AND uid_{$botSlot}=? AND uid_{$humanSlot}=? FOR UPDATE");if(!$stmt)throw new RuntimeException('Could not lock bot Live Battle settlement.');$stmt->bind_param('iii',$battleId,$botId,$humanId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$row){$db->rollback();return;}if((int)$row['settled']===1){$db->commit();return;}
        $stmt=$db->prepare("UPDATE live_battle SET {$settled}=1,{$outcomeCol}=?,{$exp}=0,{$money}=0,{$settledAt}=? WHERE id=? AND uid_{$botSlot}=? AND uid_{$humanSlot}=? AND {$settled}=0");if(!$stmt)throw new RuntimeException('Could not persist bot Live Battle settlement.');$stmt->bind_param('siiii',$outcome,$now,$battleId,$botId,$humanId);$stmt->execute();$stmt->close();
        if($outcome==='win'){$stmt=$db->prepare('UPDATE bot_trainers SET player_battles=player_battles+1,player_wins=player_wins+1,updated_at=? WHERE user_id=?');if($stmt){$stmt->bind_param('ii',$now,$botId);$stmt->execute();$stmt->close();}$stmt=$db->prepare('UPDATE members SET wins=wins+1,battle=battle+1 WHERE id=?');if($stmt){$stmt->bind_param('i',$botId);$stmt->execute();$stmt->close();}}
        else{$stmt=$db->prepare('UPDATE bot_trainers SET player_battles=player_battles+1,player_losses=player_losses+1,updated_at=? WHERE user_id=?');if($stmt){$stmt->bind_param('ii',$now,$botId);$stmt->execute();$stmt->close();}$stmt=$db->prepare('UPDATE members SET losses=losses+1 WHERE id=?');if($stmt){$stmt->bind_param('i',$botId);$stmt->execute();$stmt->close();}}
        $db->commit();
    }catch(Throwable $e){try{$db->rollback();}catch(Throwable $ignored){}throw $e;}
}
