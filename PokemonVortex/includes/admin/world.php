<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !defined('PV_SERVER_CONSOLE_CLI')) { http_response_code(404); exit; }
require_once dirname(__DIR__).'/map_runtime.php';
require_once dirname(__DIR__).'/events.php';
require_once __DIR__.'/world_runtime.php';

function pv_admin_world_specs(): array
{
    return [
        'location'=>['usage'=>'location [player]','summary'=>'Show selected or named player world and exact position.','rank'=>'PLAYER','min'=>0,'max'=>1],
        'where'=>['usage'=>'where [player]','summary'=>'Show authoritative map position.','rank'=>'MODERATOR','min'=>0,'max'=>1],
        'locations'=>['usage'=>'locations [world] [search]','summary'=>'List valid teleport world:map locations; quote search text.','rank'=>'GAME MASTER','min'=>0,'max'=>2],
        'unstuck'=>['usage'=>'unstuck [player]','summary'=>'Move to a collision-checked safe entrance in the current area.','rank'=>'PLAYER','min'=>0,'max'=>1],
        'teleport'=>['usage'=>'teleport <world:area|vortex:1> [x y]','summary'=>'Teleport selected player; coordinates must be walkable.','rank'=>'GAME MASTER','min'=>1,'max'=>3],
        'tp'=>['alias'=>'teleport'],
        'goto'=>['usage'=>'goto <player>','summary'=>'Teleport selected player to another trainer.','rank'=>'GAME MASTER','min'=>1,'max'=>1],
        'bring'=>['usage'=>'bring <player>','summary'=>'Bring a player to the selected trainer.','rank'=>'GAME MASTER','min'=>1,'max'=>1],
        'teleportplayer'=>['usage'=>'teleportplayer <player> <world:area> [x y]','summary'=>'Teleport a named player to a validated destination.','rank'=>'GAME MASTER','min'=>2,'max'=>4],
        'setlocation'=>['usage'=>'setlocation <player> <world:area> [x y]','summary'=>'Set persistent position with authoritative session synchronization.','rank'=>'ADMIN','min'=>2,'max'=>4],
        'spawn'=>['usage'=>'spawn <species|#speciesID> [level] [minutes]','summary'=>'Queue one catchable wild encounter for the selected player in their current map.','rank'=>'GAME MASTER','min'=>1,'max'=>3],
        'despawn'=>['usage'=>'despawn <spawnID>','summary'=>'Remove an unstarted admin encounter.','rank'=>'GAME MASTER','min'=>1,'max'=>1],
        'weather'=>['usage'=>'weather [clear|rain|sun|snow|fog|storm]','summary'=>'Read/set live map atmosphere; no combat damage or species changes.','rank'=>'GAME MASTER','min'=>0,'max'=>1],
        'setweather'=>['alias'=>'weather'],
        'clearweather'=>['usage'=>'clearweather','summary'=>'Clear the global map atmosphere override.','rank'=>'GAME MASTER','min'=>0,'max'=>0],
        'day'=>['usage'=>'day [auto]','summary'=>'Force daytime; auto restores each trainer map-time preference.','rank'=>'GAME MASTER','min'=>0,'max'=>1],
        'night'=>['usage'=>'night','summary'=>'Force nighttime and Vortex nighttime wild encounter tables.','rank'=>'GAME MASTER','min'=>0,'max'=>0],
        'event'=>['usage'=>'event <list|info|create|start|stop|join|leave|reward> [...]','summary'=>'Manage Event Center events; quote names containing spaces.','rank'=>'PLAYER','min'=>1,'max'=>null,'details'=>['PLAYER: event list | event info <key> | event join [key] | event leave [key]. Join/leave act on the selected trainer.','GAME MASTER: event create <name> [summary] | event start <key> | event stop [key].','GAME MASTER: event reward <player> money <amount> | event reward <player> item <item> <amount> | event reward <player> pokemon <species> [level].','Rewards require enrollment in the active event. Grants and receipts commit atomically; repeating a reward intentionally grants another.','Built-in challenges retain their existing reward mechanics. Custom events are staff-hosted challenges displayed in the Event Center. Console join grants access without spending a ticket.']],
        'sit'=>['usage'=>'sit','summary'=>'Toggle selected trainer sitting pose on multiplayer maps.','rank'=>'PLAYER','min'=>0,'max'=>0],
        'dance'=>['usage'=>'dance','summary'=>'Toggle selected trainer dance animation on multiplayer maps.','rank'=>'PLAYER','min'=>0,'max'=>0],
        'title'=>['usage'=>'title <unlocked title|none>','summary'=>'Equip an unlocked map title for the selected trainer.','rank'=>'PLAYER','min'=>1,'max'=>1],
        'titles'=>['usage'=>'titles [player]','summary'=>'List unlocked titles and the currently equipped title.','rank'=>'PLAYER','min'=>0,'max'=>1],
        'settitle'=>['usage'=>'settitle <player> <title|none>','summary'=>'Grant and equip a persistent title; none unequips it.','rank'=>'GAME MASTER','min'=>2,'max'=>2],
    ];
}

function pv_admin_world_current(array $player): array
{
    $uid=(int)$player['id'];
    $row=pv_admin_row('SELECT world_key,map,x,y FROM mapusers WHERE id=?',[$uid]);
    if(!$row && pv_table_exists('bot_trainers'))$row=pv_admin_row('SELECT world_key,map_key AS map,x,y FROM bot_trainers WHERE user_id=?',[$uid]);
    if(!$row){$state=pv_admin_player_state($uid);$loc=json_decode((string)($state['location_json']??''),true);if(is_array($loc))return $loc;throw new RuntimeException('No saved map position for this trainer. Use teleportplayer <player> vortex:1 first.');}
    return ['world'=>(string)$row['world_key'],'map'=>(string)$row['map'],'area'=>$row['world_key']==='vortex'?'':(string)$row['map'],'x'=>(int)$row['x'],'y'=>(int)$row['y']];
}

function pv_admin_world_resolve(string $reference, ?int $x=null, ?int $y=null): array
{
    $parts=explode(':',strtolower(trim($reference)),2);
    if(count($parts)!==2){if(ctype_digit($reference))$parts=['vortex',$reference];else throw new InvalidArgumentException('Use world:area, for example vortex:1 or kanto:pallet-town. Run locations [world] to inspect valid keys.');}
    [$world,$map]=$parts; $db=pv_admin_db();
    if($world==='vortex'){
        $id=pv_admin_int($map,1,25,'Vortex map');$blocks=pv_map_blocks($db,$id);
        if($x===null){[$x,$y]=pv_map_spawn($id,$blocks);}
        if($x<1||$x>30||$y<1||$y>25||pv_map_is_blocked($blocks,$x,$y))throw new InvalidArgumentException('Destination is outside walkable terrain. Omit x y to use a safe entrance.');
        return ['world'=>'vortex','map'=>(string)$id,'area'=>'','x'=>$x,'y'=>$y];
    }
    if(!pv_world_is_region_world($world))throw new InvalidArgumentException('Unknown world. Run locations to list supported worlds.');
    $area=pv_world_area($world,$map);if(!$area)throw new InvalidArgumentException('Unknown/unavailable area. Run locations '.$world.' to list valid keys.');
    $blocks=pv_world_blocks($db,$world,$map);
    if($x===null){foreach($area['spawn_points'] as $point){$sx=(int)$point[0];$sy=(int)$point[1];if(!pv_world_arrival_needs_recovery($area,$blocks,$sx,$sy)){$x=$sx;$y=$sy;break;}}}
    if($x===null||$y===null||pv_world_arrival_needs_recovery($area,$blocks,$x,$y))throw new InvalidArgumentException('Destination is not a safe walkable arrival. Omit x y to use the reviewed entrance.');
    return ['world'=>$world,'map'=>$map,'area'=>$map,'x'=>$x,'y'=>$y];
}

function pv_admin_world_teleport(array $player, array $location): array
{
    $uid=(int)$player['id'];
    return pv_admin_transaction(function()use($uid,$player,$location):array{
        pv_admin_player('#'.$uid,[],true);pv_admin_require_idle($uid);
        $state=pv_admin_player_state($uid,true);
        $jail=(int)($state['jail_until']??0);
        if($jail<0||$jail>time())throw new RuntimeException('Release this trainer with unjail before teleporting.');
        $trainer=(int)(pv_admin_row('SELECT trainer FROM members_options WHERE id=?',[$uid])['trainer']??1);
        $trainer=max(1,min(29,$trainer));
        pv_admin_exec('INSERT INTO mapusers (id,username,trainer,world_key,map,x,y,time) VALUES (?,?,?,?,?,?,?,CURTIME()) ON DUPLICATE KEY UPDATE username=VALUES(username),trainer=VALUES(trainer),world_key=VALUES(world_key),map=VALUES(map),x=VALUES(x),y=VALUES(y),time=CURTIME()',[$uid,$player['username'],$trainer,$location['world'],$location['map'],$location['x'],$location['y']]);
        if($location['world']!=='vortex')pv_admin_exec('INSERT INTO world_map_positions (user_id,world_key,area_key,x,y,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE x=VALUES(x),y=VALUES(y),updated_at=VALUES(updated_at)',[$uid,$location['world'],$location['map'],$location['x'],$location['y'],time()]);
        if(pv_table_exists('bot_trainers'))pv_admin_exec('UPDATE bot_trainers SET world_key=?,map_key=?,x=?,y=?,updated_at=? WHERE user_id=?',[$location['world'],$location['map'],$location['x'],$location['y'],time(),$uid]);
        pv_admin_state_update($uid,['location_revision'=>(int)$state['location_revision']+1,'location_json'=>json_encode($location,JSON_THROW_ON_ERROR)]);
        pv_admin_touch($uid);
        return [$player['username'].' moved to '.$location['world'].':'.$location['map'].' ('.$location['x'].', '.$location['y'].'). Browser maps synchronize on their next request.'];
    });
}

function pv_admin_world_event(array $args, array &$context): array
{
    $sub=strtolower(array_shift($args)??'');$catalog=pv_event_catalog();$active=pv_active_event_key();
    if(!in_array($sub,['list','info','create','start','stop','join','leave','reward'],true))throw new InvalidArgumentException('event list | info <key> | create <name> [summary] | start <key> | stop [key] | join [key] | leave [key] | reward <player> money <amount> | reward <player> item <item> <amount> | reward <player> pokemon <species> [level]. Quote names with spaces.');
    if(in_array($sub,['create','start','stop','reward'],true)){
        $ranks=['PLAYER','VIP','HELPER','MODERATOR','GAME MASTER','ADMIN','DEVELOPER','OWNER'];$rank=array_search((string)($context['rank']??''),$ranks,true);
        if($rank===false||$rank<4)throw new RuntimeException('This event action requires GAME MASTER access in the local console.');
    }
    if($sub==='list'){
        pv_admin_require_args($args,0,0,'event list');$out=[];foreach($catalog as $key=>$event)$out[]=$key.' | '.$event['label'].($key===$active?' | ACTIVE':'');return $out?:['No events registered.'];
    }
    if($sub==='create'){
        pv_admin_require_args($args,1,2,'event create <name> [summary]');$name=trim($args[0]);
        if($name===''||strlen($name)>120||preg_match('/[\x00-\x1f\x7f]/',$name))throw new InvalidArgumentException('Event name must contain 1–120 bytes of printable text.');
        $key=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name)),'-');$key=substr($key,0,64);
        if($key===''||$key==='none'||isset($catalog[$key]))throw new InvalidArgumentException('That event key is reserved or already exists. Choose another name.');
        $summary=trim($args[1]??'Staff-hosted community challenge. Join through the local server operator; awarded rewards are recorded here.');
        if(strlen($summary)>500||preg_match('/[\x00-\x1f\x7f]/',$summary))throw new InvalidArgumentException('Summary must be printable text up to 500 bytes.');
        pv_admin_exec('INSERT INTO console_events (event_key,label,summary,created_at) VALUES (?,?,?,?)',[$key,$name,$summary,time()]);return ['Created staff-hosted event '.$key.'. Run event start '.$key.' to open it in the Event Center.'];
    }
    if($sub==='reward'){
        pv_admin_require_args($args,3,4,'event reward <player> <money amount|item name amount|pokemon species [level]>');
        if($active==='none')throw new RuntimeException('Start an event before awarding an event reward.');
        $player=pv_admin_player($args[0],$context);$kind=strtolower($args[1]);$grantArgs=['#'.$player['id'],$args[2]];
        if($kind==='money'){if(count($args)!==3)throw new InvalidArgumentException('event reward <player> money <amount>');$command='givemoney';}
        elseif($kind==='item'){if(count($args)!==4)throw new InvalidArgumentException('event reward <player> item <item> <amount>');$command='giveitem';$grantArgs[]=$args[3];}
        elseif($kind==='pokemon'){$command='givepokemon';if(isset($args[3]))$grantArgs[]=$args[3];}
        else throw new InvalidArgumentException('Reward type must be money, item or pokemon.');
        $registry=pv_admin_registry();
        if(!pv_admin_available($command,$registry[$command],$context))throw new RuntimeException('The underlying reward command is disabled or not permitted: '.$command);
        return pv_admin_transaction(function()use($active,$player,$kind,$command,$grantArgs,&$context):array{
            pv_admin_player('#'.$player['id'],[],true);
            $access=pv_admin_row('SELECT event_page,event FROM members_options WHERE id=? FOR UPDATE',[(int)$player['id']]);
            if(!$access||(int)$access['event_page']!==1||$access['event']!==$active)throw new RuntimeException('This trainer has not joined the active event. Use select <player> then event join.');
            $result=pv_admin_pokemon_handle($command,$grantArgs,$context);
            pv_admin_exec('INSERT INTO console_event_rewards (event_key,user_id,reward_json,granted_at,operator_name) VALUES (?,?,?,?,?)',[$active,(int)$player['id'],json_encode(['type'=>$kind,'arguments'=>array_slice($grantArgs,1)],JSON_THROW_ON_ERROR),time(),$context['operator']]);
            $result[]='Recorded event reward for '.$active.'. Repeat commands intentionally grant another reward.';return $result;
        });
    }
    if($sub==='info')pv_admin_require_args($args,1,1,'event info <key>');else pv_admin_require_args($args,$sub==='start'?1:0,1,'event '.$sub.' [key]');
    $key=(string)($args[0]??$active);
    if($sub==='leave'&&!$args){$leavingPlayer=pv_admin_player('',$context);$access=pv_admin_row('SELECT event FROM members_options WHERE id=?',[(int)$leavingPlayer['id']]);$key=(string)($access['event']??$active);}
    if(!isset($catalog[$key]))throw new InvalidArgumentException('Unknown event; use event list.');
    if($sub==='info'){
        $count=pv_admin_row('SELECT COUNT(*) AS n FROM members_options WHERE event_page=1 AND event=?',[$key]);
        return [$key.' | '.$catalog[$key]['label'],(string)$catalog[$key]['summary'],'Status: '.($active===$key?'active':'stopped').'; current access holders: '.(int)($count['n']??0).'.',!empty($catalog[$key]['custom'])?'Staff-hosted challenge; the operator enrolls trainers and grants rewards from the console.':'Built-in event rules and reward mechanics remain available in the Event Center.'];
    }
    if($sub==='start'){
        pv_admin_transaction(function()use($key):void{pv_admin_set_setting('active_event',$key);pv_admin_exec('UPDATE console_events SET started_at=?,stopped_at=0 WHERE event_key=?',[time(),$key]);});
        return ['Event Center is now running '.$key.'. Existing event progress is preserved.'];
    }
    if($sub==='stop'){
        if($active!==$key)throw new RuntimeException('That event is not active.');
        pv_admin_transaction(function()use($key):void{pv_admin_set_setting('active_event','none');pv_admin_exec('UPDATE console_events SET stopped_at=? WHERE event_key=?',[time(),$key]);});
        return ['Stopped '.$key.'. Tickets, participation and reward records are preserved.'];
    }
    $player=pv_admin_player('',$context);$uid=(int)$player['id'];
    if($sub==='join'&&$active!==$key)throw new RuntimeException('Only the active event can be joined.');
    return pv_admin_transaction(function()use($sub,$key,$player,$uid):array{
        pv_admin_player('#'.$uid,[],true);
        if($sub==='join'){
            pv_admin_exec('UPDATE members_options SET event_page=1,event=? WHERE id=?',[$key,$uid]);
            pv_admin_exec('INSERT INTO console_event_participants (event_key,user_id,joined_at,left_at) VALUES (?,?,?,0) ON DUPLICATE KEY UPDATE joined_at=VALUES(joined_at),left_at=0',[$key,$uid,time()]);
        }else{
            pv_admin_exec("UPDATE members_options SET event_page=0,event='' WHERE id=? AND event=?",[$uid,$key]);
            pv_admin_exec('UPDATE console_event_participants SET left_at=? WHERE event_key=? AND user_id=?',[time(),$key,$uid]);
        }
        pv_admin_touch($uid);return [$player['username'].($sub==='join'?' joined ':' left ').$key.'.'.($sub==='join'?' Console enrollment grants access without consuming an Event Ticket.':'')];
    });
}

function pv_admin_world_handle(string $command, array $args, array &$context): array
{
    $db=pv_admin_db();
    if($command==='event')return pv_admin_world_event($args,$context);
    if($command==='locations'){
        if(!$args)return array_map(static fn(array $w):string=>$w['key'].' | '.$w['label'].' | '.$w['ready_count'].' playable areas',pv_world_catalog());
        $world=strtolower($args[0]);$search=strtolower($args[1]??'');$out=[];
        if($world==='vortex'){foreach(pv_map_catalog() as $key=>$area){$line='vortex:'.$key.' | '.$area[1];if($search===''||str_contains(strtolower($line),$search))$out[]=$line;}}
        elseif(pv_world_is_region_world($world)){foreach(pv_world_ready_areas($world) as $key=>$area){$line=$world.':'.$key.' | '.$area['name'];if($search===''||str_contains(strtolower($line),$search))$out[]=$line;}}
        else throw new InvalidArgumentException('Unknown world. Run locations for the catalog.');
        return $out?:['No matching playable locations.'];
    }
    if(in_array($command,['location','where'],true)){
        $player=pv_admin_player($args[0]??'',$context);$loc=pv_admin_world_current($player);
        return [$player['username'].' (#'.$player['id'].') | '.$loc['world'].':'.$loc['map'].' | X '.$loc['x'].' Y '.$loc['y']];
    }
    if(in_array($command,['teleport','teleportplayer','setlocation','goto','bring','unstuck'],true)){
        if(in_array($command,['goto','bring'],true)){
            $selected=pv_admin_player('',$context);$other=pv_admin_player($args[0],$context);
            $player=$command==='goto'?$selected:$other;$loc=pv_admin_world_current($command==='goto'?$other:$selected);
            $loc=pv_admin_world_resolve($loc['world'].':'.$loc['map'],$loc['x'],$loc['y']);
        }elseif($command==='unstuck'){
            $player=pv_admin_player($args[0]??'',$context);try{$loc=pv_admin_world_current($player);$ref=$loc['world'].':'.$loc['map'];}catch(RuntimeException $e){$ref='vortex:1';}$loc=pv_admin_world_resolve($ref);
        }else{
            $player=pv_admin_player($command==='teleport'?'':array_shift($args),$context);$ref=array_shift($args);
            if(count($args)!==0&&count($args)!==2)throw new InvalidArgumentException('Supply both x and y, or omit both for a safe entrance.');
            $loc=pv_admin_world_resolve($ref,$args?pv_admin_int($args[0],1,10000,'x'):null,$args?pv_admin_int($args[1],1,10000,'y'):null);
        }
        return pv_admin_world_teleport($player,$loc);
    }
    if($command==='spawn'){
        $player=pv_admin_player('',$context);$uid=(int)$player['id'];pv_admin_require_idle($uid);
        if(pv_table_exists('bot_trainers')&&pv_admin_row('SELECT user_id FROM bot_trainers WHERE user_id=?',[$uid]))throw new RuntimeException('Test encounter spawns require a human trainer with a browser session. Select a human trainer.');
        $loc=pv_admin_world_current($player);
        $species=str_starts_with($args[0],'#')?pv_admin_row('SELECT id,name FROM pguide WHERE id=?',[pv_admin_int(substr($args[0],1),1,2147483647,'species ID')]):pv_admin_row('SELECT id,name FROM pguide WHERE name=?',[$args[0]]);
        if(!$species)throw new InvalidArgumentException('Unknown exact species name or #speciesID. Quote names containing spaces.');
        $level=pv_admin_int($args[1]??'5',1,PV_WILD_LEVEL_CAP,'wild level');$minutes=pv_admin_int($args[2]??'15',1,60,'minutes');
        pv_admin_exec('INSERT INTO console_world_spawns (user_id,world_key,map_key,species_id,level,created_at,expires_at) VALUES (?,?,?,?,?,?,?)',[$uid,$loc['world'],$loc['map'],(int)$species['id'],$level,time(),time()+$minutes*60]);$id=$db->insert_id;
        return ['Spawn #'.$id.': '.$species['name'].' Lv. '.$level.' for '.$player['username'].' at '.$loc['world'].':'.$loc['map'].'.','Move in that map to find the one-use encounter; expires in '.$minutes.' minutes. Normal battle/capture rewards apply.'];
    }
    if($command==='despawn'){
        $id=pv_admin_int($args[0],1,PHP_INT_MAX,'spawn ID');$changed=pv_admin_exec('UPDATE console_world_spawns SET removed_at=? WHERE id=? AND claimed_at=0 AND removed_at=0',[time(),$id]);
        if(!$changed)throw new RuntimeException('Spawn does not exist, was removed, or its battle already started. Active battles are not cancelled.');
        return ['Removed spawn #'.$id.'. Existing unstarted scanner links are invalidated.'];
    }
    if(in_array($command,['weather','clearweather','day','night'],true)){
        if($command==='weather'&&!$args){$env=pv_admin_world_environment($db);return ['Map weather: '.$env['weather'].'; period: '.$env['period'].'. Weather is visual; Vortex day/night controls encounter pools.'];}
        if($command==='day'||$command==='night'){
            if($args&&$args[0]!=='auto')throw new InvalidArgumentException('day accepts only the optional auto argument.');$period=$args?'auto':$command;pv_admin_set_setting('world_period',$period);
            return ['World period: '.$period.'. Vortex encounters and map atmosphere update on the next request. Regional species tables remain habitat based.'];
        }
        $weather=$command==='clearweather'?'clear':strtolower($args[0]);if(!in_array($weather,['clear','rain','sun','snow','fog','storm'],true))throw new InvalidArgumentException('Weather: clear, rain, sun, snow, fog or storm.');pv_admin_set_setting('world_weather',$weather);
        return ['Map atmosphere set to '.$weather.'. Live maps update automatically. Weather does not change combat damage or encounter species.'];
    }
    if(in_array($command,['sit','dance','title','titles','settitle'],true)){
        $player=pv_admin_player(in_array($command,['settitle','titles'],true)?($args[0]??''):'',$context);$uid=(int)$player['id'];
        if($command==='titles'){$state=pv_admin_player_state($uid);$rows=pv_admin_rows('SELECT title FROM console_player_titles WHERE user_id=? ORDER BY title',[$uid]);return array_merge(['Equipped: '.((string)$state['title']?:'none')],array_map(static fn(array $r):string=>(string)$r['title'],$rows));}
        return pv_admin_transaction(function()use($command,$args,$player,$uid):array{
            pv_admin_player('#'.$uid,[],true);$state=pv_admin_player_state($uid,true);
            if($command==='sit'||$command==='dance'){$pose=($state['pose']??'')===$command?'standing':$command;pv_admin_state_update($uid,['pose'=>$pose]);return [$player['username'].' pose: '.$pose.'.'];}
            $title=trim($args[$command==='settitle'?1:0]);if(strtolower($title)==='none')$title='';
            if(strlen($title)>80||preg_match('/[\x00-\x1f\x7f]/',$title))throw new InvalidArgumentException('Titles must be printable text up to 80 bytes.');
            if($title!==''&&$command==='settitle')pv_admin_exec('INSERT IGNORE INTO console_player_titles (user_id,title,granted_at) VALUES (?,?,?)',[$uid,$title,time()]);
            elseif($title!==''&&!pv_admin_row('SELECT title FROM console_player_titles WHERE user_id=? AND title=?',[$uid,$title]))throw new RuntimeException('That title is not unlocked. Use titles to inspect grants.');
            pv_admin_state_update($uid,['title'=>$title]);return [$player['username'].' title: '.($title?:'none').'.'];
        });
    }
    throw new InvalidArgumentException('Unknown world command.');
}
