<?php
declare(strict_types=1);
define('PV_DISABLE_OUTPUT_FILTER', true);
require_once __DIR__ . '/includes/map_runtime.php';
require_once __DIR__ . '/includes/bot_runtime.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function pv_map_presence_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!pv_is_logged_in()) pv_map_presence_json(['ok'=>false,'error'=>'session'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') pv_map_presence_json(['ok'=>false,'error'=>'method'], 405);

try { $db = pv_db(); }
catch (Throwable $e) {
    pv_log('Map presence DB unavailable: ' . $e->getMessage());
    pv_map_presence_json(['ok'=>false,'error'=>'service'], 503);
}

pv_admin_world_follow_teleport();
$uid = (int)$_SESSION['myid'];
$worldKey = (string)($_SESSION['world_key'] ?? 'vortex');
if ($worldKey !== 'vortex') pv_map_presence_json(['ok'=>false,'error'=>'world'], 409);
$map = max(1, min(25, (int)($_SESSION['map'] ?? 1)));
pv_bot_tick($db, 48, $worldKey, (string)$map, 45);

pv_map_presence_json([
    'ok'=>true,
    'world'=>$worldKey,
    'map'=>$map,
    'players'=>pv_map_players($db, $uid, $map, $worldKey),
    'selfMeta'=>pv_admin_world_player_meta($db,$uid),'environment'=>pv_admin_world_environment($db),
]);
