<?php
declare(strict_types=1);
define('PV_DISABLE_OUTPUT_FILTER', true);
require_once __DIR__ . '/includes/world_maps.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
function pv_world_json(array $payload,int $status=200):never{
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
if(!pv_is_logged_in())pv_world_json(['ok'=>false,'error'=>'session'],401);
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')pv_world_json(['ok'=>false,'error'=>'method'],405);
if(!pv_verify_csrf())pv_world_json(['ok'=>false,'error'=>'csrf'],419);
$direction=filter_var($_POST['direction']??null,FILTER_VALIDATE_INT);
$deltas=[1=>[0,-1],2=>[0,1],3=>[-1,0],4=>[1,0],5=>[-1,-1],6=>[-1,1],7=>[1,-1],8=>[1,1]];
if(!is_int($direction)||!isset($deltas[$direction]))pv_world_json(['ok'=>false,'error'=>'direction'],422);
$nowMicro=microtime(true);$last=(float)($_SESSION['pv_last_world_map_move']??0.0);if(($nowMicro-$last)<0.055)pv_world_json(['ok'=>false,'error'=>'rate'],429);$_SESSION['pv_last_world_map_move']=$nowMicro;
try{$db=pv_db();}catch(Throwable $e){pv_log('World map DB unavailable: '.$e->getMessage());pv_world_json(['ok'=>false,'error'=>'service'],503);}
$uid=(int)$_SESSION['myid'];$world=pv_world_normalize_key((string)($_SESSION['world_key']??''));$areaKey=pv_world_area_key((string)($_SESSION['world_area']??''));
if(!pv_world_is_region_world($world)||$areaKey==='')pv_world_json(['ok'=>false,'error'=>'world'],409);
$area=pv_world_area($world,$areaKey);if($area===null)pv_world_json(['ok'=>false,'error'=>'area'],409);
[$x,$y]=pv_world_position($db,$uid,$world,$areaKey,(array)$area['spawn_points']);$tx=$x+$deltas[$direction][0];$ty=$y+$deltas[$direction][1];
$transition=pv_world_transition($area,$tx,$ty);
if($transition){
    $target=pv_world_area((string)$transition['target_world'],(string)$transition['target_area']);
    if($target===null)pv_world_json(['ok'=>true,'moved'=>false,'world'=>$world,'area'=>$areaKey,'x'=>$x,'y'=>$y,'blockedDirections'=>pv_world_blocked_directions($db,$area,$x,$y)]);
    $nx=(int)$transition['target_x'];$ny=(int)$transition['target_y'];$targetBlocks=pv_world_blocks($db,(string)$target['world'],(string)$target['key']);
    if($nx<1||$nx>(int)$target['columns']||$ny<1||$ny>(int)$target['rows']||isset($targetBlocks[$nx.':'.$ny]))pv_world_json(['ok'=>true,'moved'=>false,'world'=>$world,'area'=>$areaKey,'x'=>$x,'y'=>$y,'blockedDirections'=>pv_world_blocked_directions($db,$area,$x,$y)]);
    pv_world_presence_upsert($db,$uid,(string)$target['world'],(string)$target['key'],$nx,$ny);
    pv_world_json(['ok'=>true,'moved'=>true,'transition'=>true,'world'=>$target['world'],'area'=>$target['key'],'x'=>$nx,'y'=>$ny,'redirect'=>pv_world_area_url((string)$target['world'],(string)$target['key'])]);
}
$blocks=pv_world_blocks($db,$world,$areaKey);
if(pv_world_step_blocked($area,$blocks,$x,$y,$tx,$ty))pv_world_json(['ok'=>true,'moved'=>false,'world'=>$world,'area'=>$areaKey,'x'=>$x,'y'=>$y,'blockedDirections'=>pv_world_blocked_directions($db,$area,$x,$y)]);
pv_world_presence_upsert($db,$uid,$world,$areaKey,$tx,$ty);
$encounter=pv_world_encounter_html($db,$area,$tx,$ty);
pv_world_json(['ok'=>true,'moved'=>true,'transition'=>false,'world'=>$world,'area'=>$areaKey,'x'=>$tx,'y'=>$ty,'players'=>pv_world_players($db,$uid,$world,$areaKey),'encounter'=>$encounter,'blockedDirections'=>pv_world_blocked_directions($db,$area,$tx,$ty)]);
