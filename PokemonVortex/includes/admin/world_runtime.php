<?php
declare(strict_types=1);
// Web-safe effects of local commands. This file never dispatches commands.
if (!defined('PV_BOOTSTRAPPED')) { http_response_code(404); exit; }

function pv_admin_world_table_ready(mysqli $db, string $table): bool
{
    static $ready=[];
    $key=spl_object_id($db).':'.$table;
    if(!array_key_exists($key,$ready))$ready[$key]=pv_table_exists($table);
    return $ready[$key];
}

function pv_admin_world_setting(mysqli $db, string $key, mixed $fallback = null): mixed
{
    if (!pv_admin_world_table_ready($db,'console_settings')) return $fallback;
    $stmt = $db->prepare('SELECT value_json FROM console_settings WHERE setting_key=? LIMIT 1');
    $stmt->bind_param('s', $key); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) return $fallback;
    $value = json_decode((string)$row['value_json'], true);
    return json_last_error() === JSON_ERROR_NONE ? $value : $fallback;
}

function pv_admin_world_environment(mysqli $db): array
{
    $period = (string)pv_admin_world_setting($db, 'world_period', 'auto');
    $weather = (string)pv_admin_world_setting($db, 'world_weather', 'clear');
    if (!in_array($period, ['auto','day','night'], true)) $period = 'auto';
    if (!in_array($weather, ['clear','rain','sun','snow','fog','storm'], true)) $weather = 'clear';
    return ['period'=>$period, 'weather'=>$weather, 'signature'=>$period.':'.$weather];
}

function pv_admin_world_night(mysqli $db, bool $preference = false): bool
{
    $period = (string)pv_admin_world_setting($db, 'world_period', 'auto');
    return $period === 'night' || ($period !== 'day' && $preference);
}

function pv_admin_world_location_url(array $location): string
{
    if (($location['world'] ?? 'vortex') === 'vortex') return pv_url('map.php?map='.max(1,min(25,(int)($location['map']??1))));
    return pv_url('world_map.php?world='.rawurlencode((string)$location['world']).'&area='.rawurlencode((string)($location['area']??$location['map']??'')));
}

function pv_admin_world_session_sync(mysqli $db, int $uid, array $state): void
{
    $revision = (int)($state['location_revision'] ?? 0);
    if ($revision <= 0 || $revision <= (int)($_SESSION['pv_console_location_revision'] ?? 0)) return;
    $location = json_decode((string)($state['location_json'] ?? ''), true);
    if (!is_array($location) || !isset($location['world'],$location['map'],$location['x'],$location['y'])) return;
    $positionStmt=$db->prepare('SELECT world_key,map,x,y FROM mapusers WHERE id=? LIMIT 1');
    $positionStmt->bind_param('i',$uid);$positionStmt->execute();$position=$positionStmt->get_result()->fetch_assoc();$positionStmt->close();
    if($position)$location=['world'=>(string)$position['world_key'],'map'=>(string)$position['map'],'area'=>$position['world_key']==='vortex'?'':(string)$position['map'],'x'=>(int)$position['x'],'y'=>(int)$position['y']];
    $_SESSION['pv_console_location_revision'] = $revision;
    $_SESSION['world_key'] = (string)$location['world'];
    $_SESSION['mapx'] = (int)$location['x']; $_SESSION['mapy'] = (int)$location['y'];
    if ($location['world'] === 'vortex') {
        $_SESSION['map'] = (int)$location['map']; $_SESSION['mapp'] = (int)$location['map'];
        unset($_SESSION['world_area']);
    } else {
        $_SESSION['world_area'] = (string)($location['area'] ?? $location['map']);
    }
    unset($_SESSION['wb'],$_SESSION['lvl'],$_SESSION['pv_pending_wild_encounter']);
    $route = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!in_array($route, ['map.php','world_map.php','map_move.php','world_map_move.php','map_presence.php','world_map_presence.php'], true)) {
        // Preserve a one-use travel destination until an exploration request consumes it.
        $_SESSION['pv_console_location_redirect'] = pv_admin_world_location_url($location);
        return;
    }
    $_SESSION['pv_console_location_redirect'] = pv_admin_world_location_url($location);
}

function pv_admin_world_follow_teleport(): void
{
    $url = (string)($_SESSION['pv_console_location_redirect'] ?? '');
    $route = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $clientRevision=$_POST['console_revision']??$_GET['console_revision']??null;
    if($url==='' && $clientRevision!==null && (int)$clientRevision!==(int)($_SESSION['pv_console_location_revision']??0)) {
        $world=(string)($_SESSION['world_key']??'vortex');
        $url=pv_admin_world_location_url(['world'=>$world,'map'=>$world==='vortex'?(string)($_SESSION['map']??1):(string)($_SESSION['world_area']??''),'area'=>(string)($_SESSION['world_area']??'')]);
    }
    if ($url === '') return;
    unset($_SESSION['pv_console_location_redirect']);
    if (in_array($route, ['map_move.php','world_map_move.php','map_presence.php','world_map_presence.php'], true)) {
        header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store');
        echo json_encode(['ok'=>true,'transition'=>true,'redirect'=>$url], JSON_UNESCAPED_SLASHES); exit;
    }
    header('Location: '.$url); exit;
}

function pv_admin_world_player_meta(mysqli $db, int $uid): array
{
    $empty = ['title'=>'','pose'=>'standing','afk'=>false];
    if (!pv_admin_world_table_ready($db,'console_player_state')) return $empty;
    $stmt=$db->prepare('SELECT title,pose,afk FROM console_player_state WHERE user_id=? LIMIT 1');
    $stmt->bind_param('i',$uid); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) return $empty;
    return ['title'=>(string)$row['title'],'pose'=>in_array($row['pose'],['sit','dance'],true)?$row['pose']:'standing','afk'=>(bool)$row['afk']];
}

function pv_admin_world_presence_filter(mysqli $db, array $players): array
{
    if (!$players || !pv_admin_world_table_ready($db,'console_player_state')) return $players;
    $ids=array_values(array_unique(array_map(static fn(array $p):int=>(int)$p['id'],$players)));
    $result=$db->query('SELECT user_id,invisible,afk,title,pose FROM console_player_state WHERE user_id IN ('.implode(',',$ids).')');
    $states=[]; while($row=$result->fetch_assoc()) $states[(int)$row['user_id']]=$row;
    $visible=[];
    foreach($players as $player){
        $state=$states[(int)$player['id']]??[];
        if(!empty($state['invisible'])) continue;
        $player['afk']=!empty($state['afk']); $player['title']=(string)($state['title']??'');
        $player['pose']=in_array(($state['pose']??''),['sit','dance'],true)?$state['pose']:'standing';
        $visible[]=$player;
    }
    return $visible;
}

function pv_admin_world_spawn_html(mysqli $db, int $uid, string $world, string $map, int $x, int $y): ?string
{
    if ($uid <= 0 || !pv_admin_world_table_ready($db,'console_world_spawns')) return null;
    $now=time();
    $stmt=$db->prepare('SELECT s.id,s.species_id,s.level,p.name FROM console_world_spawns s JOIN pguide p ON p.id=s.species_id WHERE s.user_id=? AND s.world_key=? AND s.map_key=? AND s.claimed_at=0 AND s.removed_at=0 AND s.expires_at>? ORDER BY s.id LIMIT 1');
    $stmt->bind_param('issi',$uid,$world,$map,$now); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) return null;
    $name=(string)$row['name']; $token=bin2hex(random_bytes(24)); $level=(int)$row['level'];
    $_SESSION['pv_pending_wild_encounter']=[
        'token'=>$token,'pid'=>(int)$row['species_id'],'name'=>$name,'display_name'=>$name,'sprite_name'=>$name,
        'level'=>$level,'world'=>$world,'area'=>$world==='vortex'?'':$map,'map'=>$world==='vortex'?(int)$map:0,
        'x'=>$x,'y'=>$y,'created_at'=>$now,'consumed'=>false,'console_spawn_id'=>(int)$row['id'],
    ];
    $_SESSION['wb']=(int)$row['species_id']; $_SESSION['lvl']=$level;
    $sprite=pv_static_file('images/pokemon/'.$name.'.gif','images/Pokeball.PNG');
    return '<div class="pv-map-wild-card"><img src="'.pv_h($sprite).'" alt="'.pv_h($name).'"><div><small>LOCAL ADMIN ENCOUNTER</small><strong>Wild '.pv_h($name).' appeared.</strong><span>Level '.$level.'</span></div><form method="post" action="'.pv_h(pv_url('wildbattle.php')).'">'.pv_csrf_field().'<input type="hidden" name="encounter_token" value="'.pv_h($token).'"><button class="pv-button" type="submit" name="start_wild_battle" value="1">Battle!</button></form></div>';
}

function pv_admin_world_claim_spawn(mysqli $db, int $uid, array $pending): void
{
    $id=(int)($pending['console_spawn_id']??0); if($id<=0)return;
    $now=time();
    $stmt=$db->prepare('UPDATE console_world_spawns SET claimed_at=? WHERE id=? AND user_id=? AND claimed_at=0 AND removed_at=0 AND expires_at>?');
    $stmt->bind_param('iiii',$now,$id,$uid,$now); $stmt->execute(); $ok=$stmt->affected_rows===1; $stmt->close();
    if(!$ok)throw new RuntimeException('This admin encounter expired, was removed or was already started. Return to the map.');
}

function pv_admin_world_custom_events(mysqli $db): array
{
    if(!pv_admin_world_table_ready($db,'console_events'))return[];
    $rows=$db->query('SELECT event_key,label,summary FROM console_events ORDER BY created_at,event_key');$events=[];
    while($row=$rows->fetch_assoc())$events[$row['event_key']]=['label'=>$row['label'],'summary'=>$row['summary'],'custom'=>true];
    return $events;
}
