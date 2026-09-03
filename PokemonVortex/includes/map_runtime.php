<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/world_maps.php';
require_once __DIR__ . '/vortex_encounters.php';


function pv_map_session_defaults(): void {
    if (!isset($_SESSION['map_preferences']) || !is_array($_SESSION['map_preferences'])) {
        $_SESSION['map_preferences'] = [0, 1, 1];
    }
    $_SESSION['map_preferences'][0] = (int)($_SESSION['map_preferences'][0] ?? 0);
    $_SESSION['map_preferences'][1] = (int)($_SESSION['map_preferences'][1] ?? 0);
    $_SESSION['map_preferences'][2] = max(1, min(29, (int)($_SESSION['map_preferences'][2] ?? 1)));
    if (!isset($_SESSION['your_pokemon']) || !is_array($_SESSION['your_pokemon'])) {
        $_SESSION['your_pokemon'] = [];
    }
}
pv_map_session_defaults();

/**
 * Runtime support for the reconstructed V3 world maps.
 * The original client trusted JavaScript for movement and depended on retired
 * remote assets. This layer keeps coordinates and transitions server-authoritative.
 */

function pv_map_catalog(): array {
    return [
        1=>['Grass','Grass Region I'], 2=>['Grass','Grass Region II'], 3=>['Grass','Grass Region III'],
        4=>['Grass','Grass Region IV'], 5=>['Grass','Grass Region V'], 6=>['Grass','Grass Region VI'],
        7=>['Grass','Grass Region VII'], 8=>['Grass','Grass Region VIII'], 9=>['Grass','Grass Region IX'],
        10=>['Cave','Cave Region I'], 11=>['Cave','Cave Region II'], 12=>['Cave','Cave Region III'],
        13=>['Cave','Cave Region IV'], 14=>['Cave','Cave Region V'], 15=>['Cave','Cave Region VI'],
        16=>['Electric','Electric Region I'], 17=>['Electric','Electric Region II'],
        18=>['Fire','Fire Region I'], 19=>['Fire','Fire Region II'], 20=>['Fire','Fire Region III'], 21=>['Fire','Fire Region IV'],
        22=>['Ice','Ice Region I'], 23=>['Ice','Ice Region II'],
        24=>['Ghost','Ghost Region I'], 25=>['Ghost','Ghost Region II'],
    ];
}

function pv_map_spawn_points(int $map): array {
    $points = [
        1=>[[27,6],[6,6],[16,9],[5,12],[6,12]],
        2=>[[27,9],[8,8],[14,20],[3,22],[22,20]],
        3=>[[10,15],[7,20],[16,9],[25,21],[27,12]],
        4=>[[21,9],[27,17],[15,14],[8,11],[8,19]],
        5=>[[22,6],[25,14],[16,18],[10,21],[6,9]],
        6=>[[6,21],[16,20],[22,19],[28,13],[6,12]],
        7=>[[18,21],[22,13],[16,9],[18,8],[6,12]],
        8=>[[8,17],[12,12],[18,21],[18,7],[6,12]],
        9=>[[26,6],[12,22],[16,9],[18,7],[6,12]],
        10=>[[27,6],[6,6],[17,11],[18,7],[6,12]],
        11=>[[27,6],[6,6],[17,11],[18,7],[6,12]],
        12=>[[27,6],[6,7],[16,10],[18,7],[6,12]],
        15=>[[27,6],[6,6],[16,9],[18,7],[6,14]],
        19=>[[27,12],[6,6],[16,9],[18,7],[6,12]],
        21=>[[27,6],[13,7],[16,9],[18,7],[6,12]],
        23=>[[27,6],[6,6],[16,9],[18,8],[6,12]],
    ];
    return $points[$map] ?? [[15,13],[8,8],[23,18],[12,20],[20,7]];
}

function pv_map_spawn(int $map, array $blocks = []): array {
    $points = pv_map_spawn_points($map);
    if ($blocks) {
        $points = array_values(array_filter($points, static function (array $point) use ($blocks): bool {
            return count($point) >= 2 && !pv_map_is_blocked($blocks, (int)$point[0], (int)$point[1]);
        }));
    }
    if ($points) return $points[array_rand($points)];

    $fallback = pv_map_nearest_walkable($blocks, 15, 13);
    return $fallback ?? [15,13];
}

function pv_map_transition(int $map, int $x, int $y): ?array {
    switch ($map) {
        case 1:
            if ($x === 31) return [4,1,$y];
            if ($y === 26) return [2,$x,2];
            if ($x === 7 && $y === 3) return [22,15,24];
            break;
        case 2:
            if ($y === 1) return [1,$x,25];
            if ($x === 31) return [5,1,$y];
            if ($y === 26) return [3,$x,2];
            break;
        case 3:
            if ($y === 1) return [2,$x,25];
            if ($x === 31) return [6,1,$y];
            if ($x === 14 && $y === 18) return [12,15,24];
            break;
        case 4:
            if ($x === 0) return [1,30,$y];
            if ($x === 31) return [7,1,$y];
            break;
        case 5:
            if ($x === 31) return [8,1,$y];
            if ($x === 0) return [2,30,$y];
            break;
        case 6:
            if ($x === 31) return [9,1,$y];
            if ($x === 0) return [3,30,$y];
            break;
        case 7:
            if ($x === 0) return [4,30,$y];
            if ($y === 26) return [8,$x,2];
            if ($x === 24 && $y === 9) return [21,21,24];
            break;
        case 8:
            if ($x === 0) return [5,30,$y];
            if ($y === 1) return [7,$x,25];
            if ($y === 26) return [9,$x,2];
            if ($x === 6 && $y === 9) return [16,9,25];
            break;
        case 9:
            if ($x === 0) return [6,30,$y];
            if ($y === 1) return [8,$x,25];
            break;
        case 10:
            if ($y === 26) return [11,$x,2];
            if ($x === 31) return [13,1,$y];
            break;
        case 11:
            if ($y === 26) return [12,$x,2];
            if ($y === 1) return [10,$x,25];
            if ($x === 31) return [14,1,$y];
            break;
        case 12:
            if ($y === 1) return [11,$x,25];
            if ($x === 31) return [15,1,$y];
            if ($x === 15 && $y === 25) return [3,14,19];
            break;
        case 13:
            if ($y === 26) return [14,$x,2];
            if ($x === 0) return [10,30,$y];
            break;
        case 14:
            if ($y === 26) return [15,$x,2];
            if ($y === 1) return [13,$x,25];
            if ($x === 0) return [11,30,$y];
            break;
        case 15:
            if ($y === 1) return [14,$x,25];
            if ($x === 0) return [12,30,$y];
            break;
        case 16:
            if ($x === 31) return [17,1,$y];
            if ($y === 26) return [8,6,10];
            break;
        case 17:
            if ($x === 0) return [16,30,$y];
            break;
        case 18:
            if ($x === 31) return [20,1,$y];
            if ($y === 26) return [19,$x,1];
            break;
        case 19:
            if ($y === 0) return [18,$x,25];
            if ($x === 31) return [21,1,$y];
            break;
        case 20:
            if ($y === 26) return [21,$x,1];
            if ($x === 0) return [18,30,$y];
            break;
        case 21:
            if ($x === 0) return [19,30,$y];
            if ($y === 0) return [20,$x,25];
            if ($x === 21 && $y === 25) return [7,24,10];
            break;
        case 22:
            if ($y === 0) return [23,$x,25];
            if ($x === 15 && $y === 25) return [1,7,4];
            break;
        case 23:
            if ($y === 26) return [22,$x,1];
            break;
        case 24:
            if ($y === 26) return [25,$x,1];
            break;
        case 25:
            if ($y === 0) return [24,$x,25];
            break;
    }
    return null;
}

function pv_map_encounter_mode(int $map, int $x, int $y): int {
    // Small sub-regions in the recovered maps use a different encounter pool.
    if ($map === 3 && $y > 18 && $x > 1) return 4;
    if (($map === 6 || $map === 9) && $y > 18 && $x > 0) return 4;
    if ($map === 11 && $x > 16 && $y > 16) return 10;
    if ($map === 12 && $x > 16 && $y < 8) return 10;
    if ($map === 14 && $x < 10 && $y > 16) return 10;
    if ($map === 15 && $x < 10 && $y < 10) return 10;
    if ($map === 13 && $x > 12 && $x < 27 && $y < 10) return 10;

    if ($map >= 1 && $map <= 9) return 1;
    if ($map >= 10 && $map <= 15) return 8;
    if ($map >= 16 && $map <= 17) return 5;
    if ($map >= 18 && $map <= 21) return 7;
    if ($map >= 22 && $map <= 23) return 6;
    return 3; // ghost maps 24-25
}

function pv_map_direction_delta(int $direction): ?array {
    return match ($direction) {
        1 => [0,-1], 2 => [0,1], 3 => [-1,0], 4 => [1,0],
        5 => [-1,-1], 6 => [-1,1], 7 => [1,-1], 8 => [1,1],
        default => null,
    };
}


/**
 * Recovered one-way ledge restrictions that were historically enforced only
 * by map.js. They now live on the server so direct requests cannot bypass them.
 */
function pv_map_ledge_blocked(int $map, int $x, int $y, int $direction): bool {
    $blocked = [];
    $add = static function (array $dirs) use (&$blocked): void {
        foreach ($dirs as $dir) $blocked[(int)$dir] = true;
    };

    switch ($map) {
        case 10:
            if (($x > 10 && $x < 21 && $y === 7) || ($x > 20 && $x < 25 && $y === 8)) $add([5,1,7]);
            if ($x === 21 && $y === 8) unset($blocked[5]);
            if ($x > 10 && $x < 22 && $y === 6) $add([6]);
            if ($x > 10 && $x < 21 && $y === 6) $add([2]);
            if ($x > 9 && $x < 21 && $y === 6) $add([8]);
            if ($x > 20 && $x < 25 && $y === 7) $add([6,2]);
            if ($x > 20 && $x < 24 && $y === 7) $add([8]);
            if ($x > 8 && $x < 10 && $y === 8) $add([5,1,7]);
            if ($x === 10 && $y === 8) $add([1,5]);
            if (($x === 9 && $y === 7) || ($x === 8 && $y === 7)) $add([2,8]);
            break;
        case 11:
            if (($x === 16 && $y === 10) || ($x === 10 && $y === 12)) $add([5]);
            if ($x > 12 && $x < 16 && $y === 9) $add([6,2,8]);
            if (($x === 12 && $y === 9) || ($x === 9 && $y === 11)) $add([8]);
            if ($x > 9 && $x < 12 && $y === 10) $add([2,8]);
            if ($x > 10 && $x < 13 && $y === 11) $add([5,1]);
            break;
        case 12:
            if (($x === 25 && $y === 11) || ($x === 23 && $y === 9) || ($x === 21 && $y === 11)) $add([6]);
            if (($x === 12 && $y === 11) || ($x === 8 && $y === 8)) $add([8]);
            if (($x === 8 && $y === 10) || ($x === 9 && $y === 9)) $add([5]);
            if ($x === 16 && $y === 10) $add([7]);
            if (($x > 16 && $x < 23 && $y === 9) || ($x > 12 && $x < 21 && $y === 11) || ($x > 2 && $x < 8 && $y === 9)) $add([6,2,8]);
            if (($x > 2 && $x < 8 && $y === 10) || ($x > 12 && $x < 21 && $y === 12) || ($x > 16 && $x < 23 && $y === 10)) $add([5,1,7]);
            break;
        case 15:
            if (($x === 24 && $y === 11) || ($x === 23 && $y === 12) || ($x === 25 && $y === 12) || ($x === 26 && $y === 13)) $add([6]);
            if (($x === 13 && $y === 11) || ($x === 14 && $y === 12) || ($x === 12 && $y === 12) || ($x === 11 && $y === 13)) $add([8]);
            if (($x === 24 && $y === 13) || ($x === 25 && $y === 14)) $add([7]);
            if (($x === 13 && $y === 13) || ($x === 12 && $y === 14)) $add([5]);
            if (($x > 13 && $x < 24 && $y === 11) || ($x > 14 && $x < 23 && $y === 12)) $add([6,2,8]);
            if (($x > 13 && $x < 24 && $y === 12) || ($x > 14 && $x < 23 && $y === 13)) $add([5,1,7]);
            break;
        case 18:
            if (($x === 9 && $y === 10) || ($x === 10 && $y === 9)) $add([5]);
            if (($x === 10 && $y === 7) || ($x === 9 && $y === 8) || ($x === 8 && $y === 9)) $add([8]);
            if ($x > 10 && $x < 31 && $y === 7) $add([6,2,8]);
            if ($x > 10 && $x < 31 && $y === 8) $add([5,1,7]);
            break;
        case 19:
            if (($x === 17 && $y === 14) || ($x === 16 && $y === 13) || ($x === 15 && $y === 12)) $add([6]);
            if (($x === 14 && $y === 13) || ($x === 15 && $y === 14) || ($x === 16 && $y === 15) || ($x === 17 && $y === 16)) $add([7]);
            if ($x > 17 && $x < 31 && $y === 15) $add([6,2,8]);
            if ($x > 17 && $x < 31 && $y === 16) $add([5,1,7]);
            break;
        case 20:
            if ($x === 13 && $y === 9) $add([7]);
            if (($x === 14 && $y === 8) || ($x === 13 && $y === 7)) $add([6]);
            if (($x === 24 && $y === 12) || ($x === 23 && $y === 13) || ($x === 22 && $y === 14)) $add([8]);
            if (($x === 24 && $y === 14) || ($x === 23 && $y === 15)) $add([5]);
            if (($x > 0 && $x < 13 && $y === 7) || ($x > 24 && $x < 29 && $y === 12)) $add([6,2,8]);
            if (($x > 0 && $x < 13 && $y === 8) || ($x > 24 && $x < 29 && $y === 13)) $add([5,1,7]);
            break;
        case 21:
            if (($x === 12 && $y === 15) || ($x === 13 && $y === 16)) $add([6]);
            if ($x === 12 && $y === 17) $add([7]);
            if ($x > 0 && $x < 12 && $y === 15) $add([6,2,8]);
            if ($x > 0 && $x < 12 && $y === 16) $add([5,1,7]);
            break;
    }
    return isset($blocked[$direction]);
}

function pv_map_static_blocks(int $map): array {
    static $cache = [];
    $map = max(1, min(25, $map));
    if (array_key_exists($map, $cache)) return $cache[$map];

    $path = dirname(__DIR__) . '/config/vortex_collision/map' . $map . '.json';
    $decoded = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    if (!is_array($decoded)
        || (int)($decoded['map'] ?? 0) !== $map
        || (int)($decoded['columns'] ?? 0) !== 30
        || (int)($decoded['rows'] ?? 0) !== 25) {
        pv_log('Vortex collision definition unavailable or invalid for map ' . $map);
        return $cache[$map] = [];
    }

    $sourceRel = str_replace('\\', '/', ltrim(trim((string)($decoded['source_asset'] ?? '')), '/'));
    $sourceHash = strtolower(trim((string)($decoded['source_sha256'] ?? '')));
    if ($sourceRel !== '' && preg_match('/^[a-f0-9]{64}$/', $sourceHash) && !str_contains($sourceRel, '..')) {
        $sourceAbs = dirname(__DIR__) . '/' . $sourceRel;
        if (!is_file($sourceAbs) || !hash_equals($sourceHash, strtolower((string)@hash_file('sha256', $sourceAbs)))) {
            pv_log('Vortex collision artwork hash mismatch for map ' . $map);
        }
    }

    $blocks = [];
    foreach ((array)($decoded['blocked'] ?? []) as $point) {
        if (!is_array($point) || count($point) < 2) continue;
        $x = (int)$point[0];
        $y = (int)$point[1];
        if ($x >= 1 && $x <= 30 && $y >= 1 && $y <= 25) $blocks[$x . ':' . $y] = true;
    }
    return $cache[$map] = $blocks;
}

function pv_map_blocks(mysqli $db, int $map): array {
    static $cache = [];
    if (array_key_exists($map, $cache)) return $cache[$map];

    // The shipped Vortex collision layer is the normal terrain boundary. The
    // historical map_blocks table remains an additive local tuning layer.
    $blocks = pv_map_static_blocks($map);
    $stmt = $db->prepare('SELECT xblock,yblock FROM map_blocks WHERE mapnumber=?');
    if ($stmt) {
        $stmt->bind_param('i', $map);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $x = (int)$row['xblock'];
            $y = (int)$row['yblock'];
            if ($x >= 1 && $x <= 30 && $y >= 1 && $y <= 25) $blocks[$x . ':' . $y] = true;
        }
        $stmt->close();
    }
    return $cache[$map] = $blocks;
}

function pv_map_is_blocked(array $blocks, int $x, int $y): bool {
    return isset($blocks[$x . ':' . $y]);
}

function pv_map_nearest_walkable(array $blocks, int $x, int $y): ?array {
    $x = max(1, min(30, $x));
    $y = max(1, min(25, $y));
    if (!pv_map_is_blocked($blocks, $x, $y)) return [$x,$y];

    $best = null;
    $bestScore = null;
    for ($ty = 1; $ty <= 25; $ty++) {
        for ($tx = 1; $tx <= 30; $tx++) {
            if (pv_map_is_blocked($blocks, $tx, $ty)) continue;
            $dx = abs($tx - $x);
            $dy = abs($ty - $y);
            $score = [max($dx,$dy), $dx + $dy, $ty, $tx];
            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $best = [$tx,$ty];
            }
        }
    }
    return $best;
}

function pv_map_step_blocked(array $blocks, int $x, int $y, int $tx, int $ty): bool {
    if ($tx < 1 || $tx > 30 || $ty < 1 || $ty > 25 || pv_map_is_blocked($blocks, $tx, $ty)) return true;
    $dx = $tx - $x;
    $dy = $ty - $y;
    if (abs($dx) === 1 && abs($dy) === 1) {
        if (pv_map_is_blocked($blocks, $x + $dx, $y)) return true;
        if (pv_map_is_blocked($blocks, $x, $y + $dy)) return true;
    }
    return false;
}

function pv_map_blocked_directions(mysqli $db, int $map, int $x, int $y): array {
    $blocked = [];
    $mapBlocks = pv_map_blocks($db, $map);
    for ($direction = 1; $direction <= 8; $direction++) {
        $delta = pv_map_direction_delta($direction);
        if (!$delta || pv_map_ledge_blocked($map, $x, $y, $direction)) {
            $blocked[] = $direction;
            continue;
        }
        $tx = $x + $delta[0];
        $ty = $y + $delta[1];
        $transition = pv_map_transition($map, $tx, $ty);
        if ($transition) {
            [$nextMap, $nextX, $nextY] = $transition;
            if (pv_map_is_blocked(pv_map_blocks($db, $nextMap), $nextX, $nextY)) $blocked[] = $direction;
            continue;
        }
        if (pv_map_step_blocked($mapBlocks, $x, $y, $tx, $ty)) $blocked[] = $direction;
    }
    return $blocked;
}

function pv_map_upsert_player(mysqli $db, int $uid, int $map, int $x, int $y, string $world = 'vortex'): void {
    $world = pv_world_normalize_key($world);
    $username = substr((string)($_SESSION['myuser'] ?? 'Trainer'), 0, 45);
    $trainer = max(1, min(29, (int)($_SESSION['map_preferences'][2] ?? 1)));
    if (pv_world_map_column_available($db)) {
        $stmt = $db->prepare('INSERT INTO mapusers (id,username,trainer,world_key,map,x,y,time) VALUES (?,?,?,?,?,?,?,CURTIME()) ON DUPLICATE KEY UPDATE username=VALUES(username),trainer=VALUES(trainer),world_key=VALUES(world_key),map=VALUES(map),x=VALUES(x),y=VALUES(y),time=CURTIME()');
        if ($stmt) {
            $mapKey=(string)$map;
            $stmt->bind_param('isissii', $uid, $username, $trainer, $world, $mapKey, $x, $y);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // Pre-v21 compatibility until Upgrade / Repair adds world_key.
        $stmt = $db->prepare('INSERT INTO mapusers (id,username,trainer,map,x,y,time) VALUES (?,?,?,?,?,?,CURTIME()) ON DUPLICATE KEY UPDATE username=VALUES(username),trainer=VALUES(trainer),map=VALUES(map),x=VALUES(x),y=VALUES(y),time=CURTIME()');
        if ($stmt) {
            $stmt->bind_param('isiiii', $uid, $username, $trainer, $map, $x, $y);
            $stmt->execute();
            $stmt->close();
        }
    }
    $activity = 'Exploring ' . ($world === 'vortex' ? 'Vortex map ' . $map : ucfirst($world) . ' / ' . $map);
    $now = time();
    $stmt = $db->prepare('UPDATE online SET activity=?,time=? WHERE id=?');
    if ($stmt) {
        $stmt->bind_param('sii', $activity, $now, $uid);
        $stmt->execute();
        $stmt->close();
    }
    $_SESSION['world_key'] = $world;
    $_SESSION['map'] = $map;
    $_SESSION['mapx'] = $x;
    $_SESSION['mapy'] = $y;
    $_SESSION['mapp'] = $map;
}

function pv_map_position(mysqli $db, int $uid, int $map, string $world = 'vortex'): array {
    $world = pv_world_normalize_key($world);
    $row = null;
    if (pv_world_map_column_available($db)) {
        $stmt = $db->prepare('SELECT x,y FROM mapusers WHERE id=? AND world_key=? AND map=? LIMIT 1');
        if ($stmt) {
            $mapKey=(string)$map;
            $stmt->bind_param('iss', $uid, $world, $mapKey);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } else {
        $stmt = $db->prepare('SELECT x,y FROM mapusers WHERE id=? AND map=? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ii', $uid, $map);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    $blocks = pv_map_blocks($db, $map);
    if ($row) {
        $x = (int)$row['x'];
        $y = (int)$row['y'];
        if ($x >= 1 && $x <= 30 && $y >= 1 && $y <= 25 && !pv_map_is_blocked($blocks, $x, $y)) return [$x,$y];

        $safe = pv_map_nearest_walkable($blocks, $x, $y) ?? pv_map_spawn($map, $blocks);
        pv_map_upsert_player($db, $uid, $map, (int)$safe[0], (int)$safe[1], $world);
        return [(int)$safe[0], (int)$safe[1]];
    }
    return pv_map_spawn($map, $blocks);
}

function pv_map_show_players_enabled(mysqli $db, int $uid): bool {
    // v20.1 normalizes memonmap to a positive boolean: 1 = show other trainers.
    // Read it from MySQL on each presence refresh so multiple browser sessions
    // for the same trainer cannot disagree after an Options change.
    $enabled = 1;
    $stmt = $db->prepare('SELECT COALESCE(o.memonmap,m.memonmap,1) AS memonmap FROM members m LEFT JOIN members_options o ON o.id=m.id WHERE m.id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) $enabled = ((int)$row['memonmap'] !== 0) ? 1 : 0;
    }
    if (!isset($_SESSION['map_preferences']) || !is_array($_SESSION['map_preferences'])) $_SESSION['map_preferences'] = [0,1,1];
    $_SESSION['map_preferences'][1] = $enabled;
    return $enabled === 1;
}

function pv_map_players(mysqli $db, int $uid, int $map, string $world = 'vortex'): array {
    if (!pv_map_show_players_enabled($db, $uid)) return [];
    $world = pv_world_normalize_key($world);
    $cutoff = time() - 1800;
    if (pv_world_map_column_available($db)) {
        $stmt = $db->prepare('SELECT m.id,m.username,m.trainer,m.x,m.y FROM mapusers m INNER JOIN online o ON o.id=m.id WHERE m.world_key=? AND m.map=? AND m.id<>? AND CAST(o.time AS UNSIGNED)>=? ORDER BY m.username LIMIT 60');
        if (!$stmt) return [];
        $mapKey=(string)$map;
        $stmt->bind_param('ssii', $world, $mapKey, $uid, $cutoff);
    } else {
        $stmt = $db->prepare('SELECT m.id,m.username,m.trainer,m.x,m.y FROM mapusers m INNER JOIN online o ON o.id=m.id WHERE m.map=? AND m.id<>? AND CAST(o.time AS UNSIGNED)>=? ORDER BY m.username LIMIT 60');
        if (!$stmt) return [];
        $stmt->bind_param('iii', $map, $uid, $cutoff);
    }
    $stmt->execute();
    $r = $stmt->get_result();
    $players = [];
    while ($row = $r->fetch_assoc()) {
        $players[] = [
            'id'=>(int)$row['id'],
            'username'=>(string)$row['username'],
            'trainer'=>max(1,min(29,(int)$row['trainer'])),
            'x'=>(int)$row['x'],
            'y'=>(int)$row['y'],
        ];
    }
    $stmt->close();
    return $players;
}

function pv_map_species_candidates(string $displayName): array {
    $displayName = trim(html_entity_decode($displayName, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($displayName === '') return [];

    $candidates = [$displayName];
    $prefix = '';
    $base = $displayName;
    if (preg_match('/^(Shiny|Dark|Mystic|Shadow|Metallic)\s+(.+)$/i', $displayName, $m)) {
        $prefix = ucfirst(strtolower($m[1])) . ' ';
        $base = trim($m[2]);
    }

    // The recovered encounter art contains visual sub-forms (Unown letters,
    // Burmy cloaks, Castform weather forms, etc.) that are not separate rows in
    // the recovered pguide table.  Preserve the display form for presentation,
    // while resolving combat data against the canonical species row.
    $stripped = preg_replace('/\s*\([^)]*\)\s*$/u', '', $base) ?: $base;
    if ($stripped !== $base) $candidates[] = $prefix . $stripped;

    $aliases = [
        'Flabébé' => 'Flabebe',
        'Ho-Oh' => 'Ho-oh',
        'Mr Mime' => 'Mr. Mime',
        'Mime Jr' => 'Mime Jr.',
    ];
    foreach (array_values($candidates) as $candidate) {
        $plain = preg_replace('/^(Shiny|Dark|Mystic|Shadow|Metallic)\s+/i', '', $candidate) ?: $candidate;
        $candidatePrefix = substr($candidate, 0, strlen($candidate) - strlen($plain));
        if (isset($aliases[$plain])) $candidates[] = $candidatePrefix . $aliases[$plain];
    }

    return array_values(array_unique(array_filter(array_map('trim', $candidates))));
}

function pv_map_resolve_species(mysqli $db, string $displayName): ?array {
    foreach (pv_map_species_candidates($displayName) as $candidate) {
        $stmt = $db->prepare('SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) return $row;
    }
    return null;
}

function pv_map_encounter_sprite_name(string $displayName, string $canonicalName): string {
    $displayName = trim($displayName);
    $root = dirname(__DIR__) . '/html/static/images/pokemon/';
    if ($displayName !== '' && is_file($root . $displayName . '.gif')) return $displayName;
    if ($canonicalName !== '' && is_file($root . $canonicalName . '.gif')) return $canonicalName;

    // Canonical species whose recovered art exists only as a visual form.
    $fallbacks = [
        'Unown' => 'Unown (A)',
        'Burmy' => 'Burmy (Sand)',
        'Wormadam' => 'Wormadam (Sand)',
        'Shellos' => 'Shellos (East)',
        'Gastrodon' => 'Gastrodon (East)',
        'Basculin' => 'Basculin (Red)',
        'Flabebe' => 'Flabebe (Red)',
    ];
    $plain = preg_replace('/^(Shiny|Dark|Mystic|Shadow|Metallic)\s+/i', '', $canonicalName) ?: $canonicalName;
    $prefix = substr($canonicalName, 0, strlen($canonicalName) - strlen($plain));
    if (isset($fallbacks[$plain]) && is_file($root . $prefix . $fallbacks[$plain] . '.gif')) {
        return $prefix . $fallbacks[$plain];
    }
    return $canonicalName;
}

function pv_map_encounter_html(int $map, int $x, int $y): string {
    unset($_SESSION['wb'], $_SESSION['lvl'], $_SESSION['pv_pending_wild_encounter']);
    $rand_num = random_int(0, 1000);
    if ($rand_num <= 664) {
        return '<div class="pv-map-quiet"><strong>No wild Pokémon appeared.</strong><span>Keep exploring — encounters are random.</span></div>';
    }

    $mode = pv_map_encounter_mode($map, $x, $y);
    $night = (int)($_SESSION['night'] ?? 0) === 1;
    $legendaryUnlocked = (int)($_SESSION['map_preferences'][0] ?? 0) === 1;

    try {
        $rolled = pv_vortex_encounter_roll($mode, $night, $legendaryUnlocked, $rand_num);
    } catch (Throwable $e) {
        pv_log('Vortex encounter catalogue error: ' . $e->getMessage());
        return '<div class="pv-map-quiet"><strong>No wild Pokémon appeared.</strong><span>Continue exploring this region.</span></div>';
    }

    $displayName = trim((string)($rolled['display_name'] ?? ''));
    $level = max(5, min(99, (int)($rolled['level'] ?? 0)));
    $db = null;
    if ($displayName === '' || $level < 5) {
        return '<div class="pv-map-quiet"><strong>No wild Pokémon appeared.</strong><span>Keep exploring — encounters are random.</span></div>';
    }

    try {
        if (!$db instanceof mysqli) $db = pv_db();
        $guide = pv_map_resolve_species($db, $displayName);
    } catch (Throwable $e) {
        pv_log('Map encounter species lookup failed: ' . $e->getMessage());
        $guide = null;
    }
    if (!$guide) {
        pv_log('Unsupported recovered encounter species: ' . $displayName);
        return '<div class="pv-map-quiet"><strong>A faint signal disappeared.</strong><span>Keep exploring — that signal could not be identified.</span></div>';
    }

    $canonicalName = (string)$guide['name'];
    $spriteName = pv_map_encounter_sprite_name($displayName, $canonicalName);
    $token = bin2hex(random_bytes(24));
    $_SESSION['pv_pending_wild_encounter'] = [
        'token' => $token,
        'pid' => (int)$guide['id'],
        'name' => $canonicalName,
        'display_name' => $displayName,
        'sprite_name' => $spriteName,
        'level' => $level,
        'tier' => (string)($rolled['tier'] ?? 'standard'),
        'pool_mode' => (int)($rolled['mode'] ?? $mode),
        'pool_period' => (string)($rolled['period'] ?? ($night ? 'night' : 'day')),
        'map' => $map,
        'x' => $x,
        'y' => $y,
        'created_at' => time(),
        'consumed' => false,
    ];

    // Maintain the two legacy values for compatibility with any surviving
    // diagnostics, but they now point at the resolved local pguide row.
    $_SESSION['wb'] = (int)$guide['id'];
    $_SESSION['lvl'] = $level;

    $sprite = pv_static_file('images/pokemon/' . $spriteName . '.gif', 'images/Pokeball.PNG');
    $owned = false;
    try {
        $stmt = $db->prepare('SELECT 1 FROM pokemon WHERE owner=? AND pid=? LIMIT 1');
        if ($stmt) {
            $uid = (int)($_SESSION['myid'] ?? 0);
            $pid = (int)$guide['id'];
            $stmt->bind_param('ii', $uid, $pid);
            $stmt->execute();
            $owned = (bool)$stmt->get_result()->fetch_row();
            $stmt->close();
        }
    } catch (Throwable $e) {}

    return '<div class="pv-map-wild-card">'
        . '<img src="' . pv_h($sprite) . '" alt="' . pv_h($displayName) . '">' 
        . '<div><small>WILD SIGNAL LOCKED</small><strong>Wild ' . pv_h($displayName) . ' appeared.</strong>'
        . '<span>Level ' . $level . ($owned ? ' · Already registered' : ' · New scan') . '</span></div>'
        . '<form method="post" action="' . pv_h(pv_url('wildbattle.php')) . '">'
        . pv_csrf_field()
        . '<input type="hidden" name="encounter_token" value="' . pv_h($token) . '">'
        . '<button class="pv-button" type="submit" name="start_wild_battle" value="1">Battle!</button>'
        . '</form></div>';
}

