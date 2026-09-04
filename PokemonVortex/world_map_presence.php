<?php
declare(strict_types=1);
define('PV_DISABLE_OUTPUT_FILTER', true);
require_once __DIR__ . '/includes/world_maps.php';
require_once __DIR__ . '/includes/bot_runtime.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
function pv_world_presence_json(array $payload,int $status=200):never{http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
if(!pv_is_logged_in())pv_world_presence_json(['ok'=>false,'error'=>'session'],401);
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')pv_world_presence_json(['ok'=>false,'error'=>'method'],405);
try{$db=pv_db();}catch(Throwable $e){pv_log('World presence DB unavailable: '.$e->getMessage());pv_world_presence_json(['ok'=>false,'error'=>'service'],503);}
$uid=(int)$_SESSION['myid'];$world=pv_world_normalize_key((string)($_SESSION['world_key']??''));$area=pv_world_area_key((string)($_SESSION['world_area']??''));
if(!pv_world_is_region_world($world)||$area===''||pv_world_area($world,$area)===null)pv_world_presence_json(['ok'=>false,'error'=>'world'],409);
pv_bot_tick($db,48);
pv_world_presence_json(['ok'=>true,'world'=>$world,'area'=>$area,'players'=>pv_world_players($db,$uid,$world,$area)]);
