<?php
declare(strict_types=1);

require_once __DIR__ . '/map_runtime.php';
require_once __DIR__ . '/rival_runtime.php';
require_once __DIR__ . '/evolution.php';

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
 * classification is deterministic so an existing 2,000-trainer database gains
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
            'rank_cooldown_min'=>600,'rank_cooldown_max'=>1200,'ranked_win_bonus'=>7.0,
            'human_target_percent'=>55,
        ];
    }
    if ($botIndex <= 96) {
        return [
            'key'=>'elite','label'=>'Elite Rival','movement_min'=>3,'movement_max'=>4,
            'action_min'=>10,'action_max'=>22,'wild_chance'=>46,'training_multiplier'=>1.12,
            'capture_bonus'=>6,'collection_cap'=>42,'rank_attempt_chance'=>72,
            'rank_cooldown_min'=>1800,'rank_cooldown_max'=>3600,'ranked_win_bonus'=>3.5,
            'human_target_percent'=>50,
        ];
    }
    return [
        'key'=>'rival','label'=>'Active Rival','movement_min'=>2,'movement_max'=>3,
        'action_min'=>18,'action_max'=>40,'wild_chance'=>34,'training_multiplier'=>1.0,
        'capture_bonus'=>0,'collection_cap'=>36,'rank_attempt_chance'=>48,
        'rank_cooldown_min'=>5400,'rank_cooldown_max'=>9000,'ranked_win_bonus'=>0.0,
        'human_target_percent'=>42,
    ];
}

function pv_bot_ranked_cooldown_for(array $profile, int $botIndex): int
{
    $min = max(300, (int)($profile['rank_cooldown_min'] ?? 5400));
    $max = max($min, (int)($profile['rank_cooldown_max'] ?? $min));
    if ($max === $min) return $min;
    return $min + (($botIndex * 197) % (($max - $min) + 1));
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
    $maps = pv_bot_population_maps();
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

function pv_bot_population_count_key(string $world, string $mapKey): string
{
    return pv_world_normalize_key($world) . "\0" . (string)$mapKey;
}

function pv_bot_lowest_population_choice(array $counts, int $index): array
{
    $maps = pv_bot_population_maps();
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
    $counts = [];
    $result = $db->query('SELECT world_key,map_key,COUNT(*) AS bot_count FROM bot_trainers WHERE enabled=1 GROUP BY world_key,map_key');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $key = pv_bot_population_count_key((string)($row['world_key'] ?? ''), (string)($row['map_key'] ?? ''));
            $counts[$key] = max(0, (int)($row['bot_count'] ?? 0));
        }
        $result->free();
    }
    return pv_bot_lowest_population_choice($counts, $index);
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
    $columns = max(1, (int)$area['columns']);
    $rows = max(1, (int)$area['rows']);
    $total = $columns * $rows;
    $start = (($seed * 73) % $total);
    for ($i = 0; $i < $total; $i++) {
        $cell = ($start + $i) % $total;
        $x = ($cell % $columns) + 1;
        $y = intdiv($cell, $columns) + 1;
        if (!isset($blocks[$x.':'.$y]) && !isset($occupied[$x.':'.$y])) return [$x,$y];
    }
    // Extremely dense maps retain the existing safe-spawn fallback rather than
    // failing population repair merely because every open tile is occupied.
    foreach ((array)$area['spawn_points'] as $point) {
        $x = (int)($point[0] ?? 0); $y = (int)($point[1] ?? 0);
        if ($x >= 1 && $x <= $columns && $y >= 1 && $y <= $rows && !isset($blocks[$x.':'.$y])) return [$x,$y];
    }
    return [1,1];
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

function pv_bot_seed_one(mysqli $db, int $index): int
{
    $username = pv_bot_username($index);
    $stmt=$db->prepare('SELECT b.user_id FROM bot_trainers b WHERE b.bot_index=? LIMIT 1');
    if($stmt){$stmt->bind_param('i',$index);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if($row)return(int)$row['user_id'];}
    $stmt=$db->prepare('SELECT id FROM members WHERE username=? LIMIT 1');
    if($stmt){$stmt->bind_param('s',$username);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if($row){
        // Never commandeer a pre-existing human identity. Probe deterministic
        // fallback names until a free identity is found.
        $base='PVAI'.str_pad((string)$index,4,'0',STR_PAD_LEFT);
        $username=$base.'N';
        for($suffix=1;$suffix<=99;$suffix++){
            $check=$db->prepare('SELECT id FROM members WHERE username=? LIMIT 1');
            if(!$check)throw new RuntimeException('Could not verify bot fallback identity.');
            $check->bind_param('s',$username);$check->execute();$collision=(bool)$check->get_result()->fetch_row();$check->close();
            if(!$collision)break;
            $username=$base.'N'.$suffix;
            if($suffix===99)throw new RuntimeException('No safe fallback username is available for bot '.$index.'.');
        }
    }}

    [$world,$mapKey]=$index<=1000?pv_bot_region_assignment($index):pv_bot_lowest_population_assignment($db,$index);
    [$x,$y]=pv_bot_spawn_for($db,$world,$mapKey,$index);
    $trainer=(($index-1)%28)+1;
    $starters=pv_bot_starter_names();$starter=$starters[($index-1)%count($starters)];
    $stmt=$db->prepare('SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=? LIMIT 1');
    if(!$stmt)throw new RuntimeException('Bot starter lookup is unavailable.');
    $stmt->bind_param('s',$starter);$stmt->execute();$guide=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$guide)throw new RuntimeException('Bot starter data is unavailable for '.$starter.'.');

    $now=time();
    $password=pv_bot_disabled_password_hash();
    $email='bot-'.$index.'@vortex.invalid';$registered=(string)$now;$last=(string)$now;$ip='bot';$eb='1';$number=(string)((($index-1)%18)+1);$secret=bin2hex(random_bytes(20));
    $db->begin_transaction();
    try{
        $stmt=$db->prepare('INSERT INTO members (username,password,email,registered,llogin,last_login,ip,eb,number,secret_key,total_poke,sidequest,money,battle,wins,losses) VALUES (?,?,?,?,?,?,?,?,?,?,0,1,0,0,0,0)');
        if(!$stmt)throw new RuntimeException('Could not prepare bot trainer account.');
        $stmt->bind_param('ssssisssss',$username,$password,$email,$registered,$now,$last,$ip,$eb,$number,$secret);
        if(!$stmt->execute())throw new RuntimeException('Could not create bot trainer account: '.$stmt->error);
        $uid=(int)$db->insert_id;$stmt->close();

        $forum='';$skype='';$display='No';$memonmap=1;$messonoff=0;$notify=0;$layout=2;
        $stmt=$db->prepare('INSERT INTO members_options (id,trainer,forum,skype,display,memonmap,messonoff,messnotifyonoff,layout) VALUES (?,?,?,?,?,?,?,?,?)');
        if(!$stmt)throw new RuntimeException('Could not prepare bot trainer options.');
        $stmt->bind_param('iisssiiii',$uid,$trainer,$forum,$skype,$display,$memonmap,$messonoff,$notify,$layout);$stmt->execute();$stmt->close();
        foreach([['badges','id'],['events','id'],['comments','userid'],['items','uid']] as [$table,$column]){
            if(!pv_bot_table_exists($db,$table))continue;
            $sql='INSERT INTO `'.$table.'` (`'.$column.'`) VALUES (?)';$stmt=$db->prepare($sql);
            if(!$stmt)throw new RuntimeException('Could not prepare bot account defaults for '.$table.'.');
            $stmt->bind_param('i',$uid);
            if(!$stmt->execute()){$error=$stmt->error;$stmt->close();throw new RuntimeException('Could not create bot account defaults for '.$table.': '.$error);}
            $stmt->close();
        }
        $starterLevel=12+(($index-1)%17);
        $pokemonId=pv_bot_create_pokemon($db,$uid,$username,$guide,$starterLevel,$starter);
        $stmt=$db->prepare('UPDATE members SET s1=?,total_poke=1 WHERE id=?');
        if(!$stmt)throw new RuntimeException('Could not assign bot starter team.');
        $stmt->bind_param('ii',$pokemonId,$uid);$stmt->execute();$stmt->close();
        $stmt=$db->prepare('UPDATE pguide SET amount=amount+1 WHERE id=?');if(!$stmt)throw new RuntimeException('Could not prepare bot Pokédex population update.');$pid=(int)$guide['id'];$stmt->bind_param('i',$pid);if(!$stmt->execute()){$error=$stmt->error;$stmt->close();throw new RuntimeException('Could not update bot Pokédex population: '.$error);}$stmt->close();
        $next=$now+random_int(5,45);$lastAction='spawned';
        $stmt=$db->prepare('INSERT INTO bot_trainers (user_id,bot_index,enabled,trainer_sprite,world_key,map_key,x,y,next_action_at,last_action_at,last_action,last_wild_name,last_wild_level,wild_battles,wild_wins,captures,player_battles,player_wins,player_losses,created_at,updated_at) VALUES (?,?,1,?,?,?,?,?,?,?,? ,\'\',0,0,0,0,0,0,0,?,?)');
        if(!$stmt)throw new RuntimeException('Could not prepare bot registry row.');
        $stmt->bind_param('iiissiiiisii',$uid,$index,$trainer,$world,$mapKey,$x,$y,$next,$now,$lastAction,$now,$now);
        if(!$stmt->execute())throw new RuntimeException('Could not create bot registry row: '.$stmt->error);$stmt->close();
        pv_bot_write_presence($db,$uid,$username,$trainer,$world,$mapKey,$x,$y,$now);
        $db->commit();
        return $uid;
    }catch(Throwable $e){try{$db->rollback();}catch(Throwable $ignored){}throw $e;}
}

function pv_bot_ensure_population(mysqli $db, int $target=2000): array
{
    if(!pv_bot_registry_ready($db))throw new RuntimeException('Bot trainer registry is unavailable.');
    $target=max(0,min(2000,$target));
    $existingIndexes=[];$existing=0;
    $r=$db->query('SELECT bot_index FROM bot_trainers ORDER BY bot_index');
    if($r){while($row=$r->fetch_assoc()){$index=(int)$row['bot_index'];$existingIndexes[$index]=true;$existing++;}$r->free();}
    $created=0;
    for($index=1;$index<=$target;$index++){
        if(isset($existingIndexes[$index]))continue;
        pv_bot_seed_one($db,$index);$created++;
    }
    return ['target'=>$target,'existing'=>$existing,'created'=>$created,'total'=>$existing+$created];
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
    $world=pv_world_normalize_key((string)$bot['world_key']);$mapKey=pv_world_area_key((string)$bot['map_key']);$area=pv_world_area($world,$mapKey);
    if(!$area){[$world,$mapKey]=pv_bot_region_assignment((int)$bot['bot_index']);[$x,$y]=pv_bot_spawn_for($db,$world,$mapKey,(int)$bot['bot_index']);return['world'=>$world,'map'=>$mapKey,'x'=>$x,'y'=>$y,'moved'=>true];}
    $x=(int)$bot['x'];$y=(int)$bot['y'];$directions=[1,2,3,4,5,6,7,8];shuffle($directions);$deltas=[1=>[0,-1],2=>[0,1],3=>[-1,0],4=>[1,0],5=>[-1,-1],6=>[-1,1],7=>[1,-1],8=>[1,1]];$blocks=pv_world_blocks($db,$world,$mapKey);
    foreach($directions as $direction){$tx=$x+$deltas[$direction][0];$ty=$y+$deltas[$direction][1];$transition=pv_world_transition($area,$tx,$ty);
        if($transition){$target=pv_world_area((string)$transition['target_world'],(string)$transition['target_area']);if(!$target)continue;$nx=(int)$transition['target_x'];$ny=(int)$transition['target_y'];$targetBlocks=pv_world_blocks($db,(string)$target['world'],(string)$target['key']);if($nx>=1&&$nx<=(int)$target['columns']&&$ny>=1&&$ny<=(int)$target['rows']&&!isset($targetBlocks[$nx.':'.$ny]))return['world'=>(string)$target['world'],'map'=>(string)$target['key'],'x'=>$nx,'y'=>$ny,'moved'=>true];continue;}
        if(!pv_world_step_blocked($area,$blocks,$x,$y,$tx,$ty))return['world'=>$world,'map'=>$mapKey,'x'=>$tx,'y'=>$ty,'moved'=>true];
    }
    return['world'=>$world,'map'=>$mapKey,'x'=>$x,'y'=>$y,'moved'=>false];
}

function pv_bot_roll_wild(mysqli $db,string $world,string $mapKey,int $x,int $y,int $encounterChance=16): ?array
{
    $encounterChance=max(0,min(90,$encounterChance));
    if($encounterChance<=0||random_int(1,100)>$encounterChance)return null;
    if($world==='vortex'){
        $map=max(1,min(25,(int)$mapKey));
        try{$rolled=pv_vortex_encounter_roll(pv_map_encounter_mode($map,$x,$y),false,false,random_int(665,1000));}catch(Throwable $e){return null;}
        $display=trim((string)($rolled['display_name']??''));if($display==='')return null;$guide=pv_map_resolve_species($db,$display);if(!$guide)return null;
        return['guide'=>$guide,'display'=>$display,'level'=>max(5,min(99,(int)($rolled['level']??5)))];
    }
    $area=pv_world_area($world,$mapKey);if(!$area)return null;$profileKey=trim((string)($area['encounter_profile']??''));if($profileKey==='')return null;$profile=pv_world_encounter_profiles($world)[$profileKey]??null;if(!is_array($profile))return null;
    $entries=(array)($profile['entries']??[]);$total=0;foreach($entries as $e)if(is_array($e)&&count($e)>=4)$total+=max(0,(int)$e[3]);if($total<=0)return null;
    $roll=random_int(1,$total);$picked=null;foreach($entries as $e){if(!is_array($e)||count($e)<4)continue;$roll-=max(0,(int)$e[3]);if($roll<=0){$picked=$e;break;}}if(!$picked)return null;
    $base=trim((string)$picked[0]);$min=max(2,(int)$picked[1]);$max=max($min,(int)$picked[2]);$display=pv_world_vortex_variant_roll().$base;
    $stmt=$db->prepare('SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=? LIMIT 1');if(!$stmt)return null;$stmt->bind_param('s',$display);$stmt->execute();$guide=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$guide && $display!==$base){$display=$base;$stmt=$db->prepare('SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=? LIMIT 1');if($stmt){$stmt->bind_param('s',$display);$stmt->execute();$guide=$stmt->get_result()->fetch_assoc();$stmt->close();}}
    return $guide?['guide'=>$guide,'display'=>$display,'level'=>random_int($min,$max)]:null;
}


function pv_bot_move_burst(mysqli $db,array $bot,int $steps): array
{
    $steps=max(1,min(8,$steps));$current=$bot;$movedSteps=0;$last=['world'=>(string)($bot['world_key']??'vortex'),'map'=>(string)($bot['map_key']??'1'),'x'=>(int)($bot['x']??1),'y'=>(int)($bot['y']??1),'moved'=>false];
    for($i=0;$i<$steps;$i++){
        $last=(string)($current['world_key']??'vortex')==='vortex'?pv_bot_move_vortex($db,$current):pv_bot_move_region($db,$current);
        if(empty($last['moved']))break;
        $movedSteps++;
        $current['world_key']=(string)$last['world'];$current['map_key']=(string)$last['map'];$current['x']=(int)$last['x'];$current['y']=(int)$last['y'];
    }
    $last['moved']=$movedSteps>0;$last['moved_steps']=$movedSteps;
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
    $wildLevel=max(1,min(100,$wildLevel));$multiplier=max(0.75,min(1.5,$multiplier));
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
    $uid=(int)$bot['user_id'];$username=(string)$bot['username'];$level=max(2,(int)$wild['level']);
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
            }catch(Throwable $e){try{$db->rollback();}catch(Throwable $ignored){}pv_log('Bot capture failed for '.$uid.': '.$e->getMessage());}
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
    $processed=0;$rankOps=0;$now=time();$started=microtime(true);
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
                    $cooldown=pv_bot_ranked_cooldown_for($profile,max(1,(int)$bot['bot_index']));$lastAttack=max(0,(int)($bot['rank_last_attack_at']??0));
                    if($rankOps<2&&$lastAttack<=$now-$cooldown&&random_int(1,100)<=(int)$profile['rank_attempt_chance']&&((microtime(true)-$started)*1000.0)<($budgetMs*0.78)){
                        if(pv_rival_bot_ranked_operation($db,$activityBot,$cooldown)!==null)$rankOps++;
                    }
                }catch(Throwable $rivalError){pv_log('Rival Network bot activity failed for '.$uid.': '.$rivalError->getMessage());}
                $processed++;
            }catch(Throwable $e){
                pv_log('Autonomous trainer tick failed for '.$uid.': '.$e->getMessage());
                $retry=$now+180;$idle='idle';$stmt=$db->prepare('UPDATE bot_trainers SET next_action_at=?,last_action_at=?,last_action=?,updated_at=? WHERE user_id=? AND enabled=1');if($stmt){$stmt->bind_param('iisii',$retry,$now,$idle,$now,$uid);$stmt->execute();$stmt->close();}
            }
        }
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
