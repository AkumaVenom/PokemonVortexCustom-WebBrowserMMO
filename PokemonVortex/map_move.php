<?php
declare(strict_types=1);
define('PV_DISABLE_OUTPUT_FILTER', true);
require_once __DIR__ . '/includes/map_runtime.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function pv_map_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!pv_is_logged_in()) pv_map_json(['ok'=>false,'error'=>'session'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') pv_map_json(['ok'=>false,'error'=>'method'], 405);
if (!pv_verify_csrf()) pv_map_json(['ok'=>false,'error'=>'csrf'], 419);

$direction = filter_var($_POST['direction'] ?? null, FILTER_VALIDATE_INT);
$delta = is_int($direction) ? pv_map_direction_delta($direction) : null;
if (!$delta) pv_map_json(['ok'=>false,'error'=>'direction'], 422);

// A small server-side cadence guard protects the recovered encounter engine from key-repeat floods.
$nowMicro = microtime(true);
$lastMove = (float)($_SESSION['pv_last_map_move'] ?? 0.0);
if (($nowMicro - $lastMove) < 0.055) pv_map_json(['ok'=>false,'error'=>'rate'], 429);
$_SESSION['pv_last_map_move'] = $nowMicro;

try { $db = pv_db(); }
catch (Throwable $e) { pv_log('Map DB unavailable: '.$e->getMessage()); pv_map_json(['ok'=>false,'error'=>'service'], 503); }

pv_admin_world_follow_teleport();
$uid = (int)$_SESSION['myid'];
$worldKey = (string)($_SESSION['world_key'] ?? 'vortex');
if ($worldKey !== 'vortex') pv_map_json(['ok'=>false,'error'=>'world'], 409);
$map = max(1, min(25, (int)($_SESSION['map'] ?? 1)));
[$currentX, $currentY] = pv_map_position($db, $uid, $map, $worldKey);
$targetX = $currentX + $delta[0];
$targetY = $currentY + $delta[1];

if (pv_map_ledge_blocked($map, $currentX, $currentY, $direction)) {
    pv_map_json([
        'ok'=>true,'moved'=>false,'map'=>$map,'x'=>$currentX,'y'=>$currentY,'encounter'=>'',
        'blockedDirections'=>pv_map_blocked_directions($db, $map, $currentX, $currentY),
    ]);
}

$transition = pv_map_transition($map, $targetX, $targetY);
if ($transition) {
    [$nextMap, $nextX, $nextY] = $transition;
    $blocks = pv_map_blocks($db, $nextMap);
    if (pv_map_is_blocked($blocks, $nextX, $nextY)) {
        pv_map_json(['ok'=>true,'moved'=>false,'map'=>$map,'x'=>$currentX,'y'=>$currentY,'encounter'=>'','blockedDirections'=>pv_map_blocked_directions($db, $map, $currentX, $currentY)]);
    }
    pv_map_upsert_player($db, $uid, $nextMap, $nextX, $nextY, $worldKey);
    pv_map_json([
        'ok'=>true,'moved'=>true,'transition'=>true,'map'=>$nextMap,'x'=>$nextX,'y'=>$nextY,
        'redirect'=>pv_url('map.php?map='.$nextMap),
    ]);
}

if ($targetX < 1 || $targetX > 30 || $targetY < 1 || $targetY > 25) {
    pv_map_json(['ok'=>true,'moved'=>false,'map'=>$map,'x'=>$currentX,'y'=>$currentY,'encounter'=>'','blockedDirections'=>pv_map_blocked_directions($db, $map, $currentX, $currentY)]);
}

$blocks = pv_map_blocks($db, $map);
if (pv_map_step_blocked($blocks, $currentX, $currentY, $targetX, $targetY)) {
    pv_map_json(['ok'=>true,'moved'=>false,'map'=>$map,'x'=>$currentX,'y'=>$currentY,'encounter'=>'','blockedDirections'=>pv_map_blocked_directions($db, $map, $currentX, $currentY)]);
}

pv_map_upsert_player($db, $uid, $map, $targetX, $targetY, $worldKey);
$encounter = pv_map_encounter_html($map, $targetX, $targetY);
$players = pv_map_players($db, $uid, $map, $worldKey);

pv_map_json([
    'ok'=>true,
    'moved'=>true,
    'transition'=>false,
    'map'=>$map,
    'x'=>$targetX,
    'y'=>$targetY,
    'encounter'=>$encounter,
    'players'=>$players,
    'blockedDirections'=>pv_map_blocked_directions($db, $map, $targetX, $targetY),
]);
