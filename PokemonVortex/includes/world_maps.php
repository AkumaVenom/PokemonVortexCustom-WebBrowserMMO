<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wild_level_balance.php';

/**
 * Multi-world exploration registry.
 *
 * Vortex keeps its recovered numeric 1-25 runtime in map.php. Region worlds
 * (Kanto, Hoenn, and future additions) use stable world/area namespaces so
 * their maps, saved positions, collision and multiplayer presence never
 * collide with the original Vortex network.
 */
function pv_world_normalize_key(string $world): string {
    $world = strtolower(trim($world));
    return preg_match('/^[a-z0-9][a-z0-9_-]{0,23}$/', $world) ? $world : 'vortex';
}

function pv_world_area_key(string $area): string {
    $area = strtolower(trim($area));
    return preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $area) ? $area : '';
}

function pv_world_region_keys(): array {
    return ['kanto','hoenn'];
}

function pv_world_is_region_world(string $world): bool {
    return in_array(pv_world_normalize_key($world), pv_world_region_keys(), true);
}

function pv_world_manifest(string $world): array {
    static $cache = [];
    $world = pv_world_normalize_key($world);
    if (!pv_world_is_region_world($world)) return ['areas'=>[]];
    if (isset($cache[$world]) && is_array($cache[$world])) return $cache[$world];
    $file = dirname(__DIR__) . '/config/worlds/' . $world . '.php';
    $loaded = is_file($file) ? require $file : [];
    $manifest = is_array($loaded) ? $loaded : [];
    if (!isset($manifest['areas']) || !is_array($manifest['areas'])) $manifest['areas'] = [];
    $cache[$world] = $manifest;
    return $manifest;
}

function pv_world_kanto_manifest(): array { return pv_world_manifest('kanto'); }
function pv_world_hoenn_manifest(): array { return pv_world_manifest('hoenn'); }

function pv_world_catalog(): array {
    static $cached = null;
    if (is_array($cached)) return $cached;
    $catalog = [
        'vortex' => [
            'key'=>'vortex','label'=>'Vortex World','subtitle'=>'Original World',
            'description'=>'Explore 25 regions across the original Vortex world.',
            'status'=>'active','area_count'=>25,'ready_count'=>25,
        ],
    ];
    foreach (pv_world_region_keys() as $worldKey) {
        $manifest = pv_world_manifest($worldKey);
        $ready = 0;
        foreach (array_keys((array)$manifest['areas']) as $areaKey) {
            if (pv_world_area($worldKey, (string)$areaKey) !== null) $ready++;
        }
        $catalog[$worldKey] = [
            'key'=>$worldKey,
            'label'=>(string)($manifest['label'] ?? ucfirst($worldKey)),
            'subtitle'=>(string)($manifest['subtitle'] ?? ucfirst($worldKey).' World'),
            'description'=>(string)($manifest['description'] ?? 'Explore the regions of '.ucfirst($worldKey).'.'),
            'status'=>$ready>0?'active':'prepared',
            'area_count'=>count((array)$manifest['areas']),
            'ready_count'=>$ready,
        ];
    }
    return $cached = $catalog;
}

function pv_world_definition(string $world): ?array {
    $world = pv_world_normalize_key($world);
    $catalog = pv_world_catalog();
    return $catalog[$world] ?? null;
}

function pv_world_area(string $world, string $area): ?array {
    // One filesystem validation per area/request, including collision headers.
    // AI ticks and encounter generation reuse this expanded regional catalogue.
    static $cache = [];
    $world = pv_world_normalize_key($world);
    $area = pv_world_area_key($area);
    if (!pv_world_is_region_world($world) || $area === '') return null;
    $cacheKey = $world . '/' . $area;
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];
    $cache[$cacheKey] = null;

    $manifest = pv_world_manifest($world);
    $raw = $manifest['areas'][$area] ?? null;
    if (!is_array($raw) || empty($raw['ready'])) return null;

    $name = trim((string)($raw['name'] ?? ''));
    $category = trim((string)($raw['category'] ?? 'Area'));
    $asset = str_replace('\\','/',ltrim((string)($raw['asset'] ?? ''),'/'));
    if ($name === '' || $asset === '' || str_contains($asset, '..')) return null;

    $root = realpath(dirname(__DIR__) . '/html/static');
    $assetAbs = $root ? realpath($root . '/' . $asset) : false;
    if (!$root || !$assetAbs || !str_starts_with($assetAbs, $root . DIRECTORY_SEPARATOR) || !is_file($assetAbs)) return null;

    // Each image retains its native dimensions. Logical and display tile sizes
    // are explicit so original grids and subdivided collision stay aligned.
    $tileSize = max(8,min(64,(int)($raw['tile_size'] ?? $manifest['tile_size'] ?? 16)));
    $displayTileSize = max($tileSize,min(128,(int)($raw['display_tile_size'] ?? $manifest['display_tile_size'] ?? $tileSize)));
    $columns = max(0,(int)($raw['columns'] ?? 0));
    $rows = max(0,(int)($raw['rows'] ?? 0));
    $imageInfo = @getimagesize($assetAbs);
    $width = is_array($imageInfo)?(int)($imageInfo[0]??0):0;
    $height = is_array($imageInfo)?(int)($imageInfo[1]??0):0;
    if ($columns<=0 && $width>0 && $width%$displayTileSize===0) $columns=intdiv($width,$displayTileSize);
    if ($rows<=0 && $height>0 && $height%$displayTileSize===0) $rows=intdiv($height,$displayTileSize);
    if ($columns<=0 || $rows<=0 || $width<=0 || $height<=0) return null;
    $gridWidth=$columns*$displayTileSize; $gridHeight=$rows*$displayTileSize;
    if($width<$gridWidth||$height<$gridHeight)return null;
    if(($width-$gridWidth)>=$displayTileSize||($height-$gridHeight)>=$displayTileSize)return null;

    $spawnPoints=[];
    foreach((array)($raw['spawn_points']??[]) as $point){
        if(!is_array($point)||count($point)<2)continue;
        $x=(int)$point[0];$y=(int)$point[1];
        if($x>=1&&$x<=$columns&&$y>=1&&$y<=$rows)$spawnPoints[]=[$x,$y];
    }
    if($spawnPoints===[])return null;

    $collisionRel=str_replace('\\','/',ltrim(trim((string)($raw['collision_asset']??'')),'/'));
    $collisionAbs='';
    if($collisionRel!==''){
        if(str_contains($collisionRel,'..'))return null;
        $publicRoot=realpath(dirname(__DIR__));
        $resolved=$publicRoot?realpath($publicRoot.'/'.$collisionRel):false;
        if(!$publicRoot||!$resolved||!str_starts_with($resolved,$publicRoot.DIRECTORY_SEPARATOR)||!is_file($resolved))return null;
        $collisionAbs=$resolved;
        $collisionData=json_decode((string)@file_get_contents($resolved),true);
        if(!is_array($collisionData)||!isset($collisionData['blocked'])||!is_array($collisionData['blocked'])||(int)($collisionData['columns']??0)!==$columns||(int)($collisionData['rows']??0)!==$rows)return null;
    }

    $transitions=[];
    foreach((array)($raw['transitions']??[]) as $transition){
        if(!is_array($transition))continue;
        $tx=(int)($transition['x']??0);$ty=(int)($transition['y']??0);
        $targetArea=pv_world_area_key((string)($transition['target_area']??''));
        $targetWorld=pv_world_normalize_key((string)($transition['target_world']??$world));
        $targetX=(int)($transition['target_x']??0);$targetY=(int)($transition['target_y']??0);
        if($tx<1||$tx>$columns||$ty<1||$ty>$rows||$targetArea===''||$targetX<1||$targetY<1||!pv_world_is_region_world($targetWorld))continue;
        $transitions[]=['x'=>$tx,'y'=>$ty,'target_world'=>$targetWorld,'target_area'=>$targetArea,'target_x'=>$targetX,'target_y'=>$targetY];
    }

    $cleanAsset = static function(mixed $value): string {
        $rel=str_replace('\\','/',ltrim(trim((string)$value),'/'));
        return str_contains($rel,'..')?'':$rel;
    };

    return $cache[$cacheKey] = [
        'world'=>$world,'key'=>$area,'name'=>$name,'category'=>$category,
        'asset'=>$asset,'asset_abs'=>$assetAbs,
        'logical_asset'=>$cleanAsset($raw['logical_asset']??''),
        'preview_asset'=>$cleanAsset($raw['preview_asset']??''),
        'tile_size'=>$tileSize,'display_tile_size'=>$displayTileSize,
        'movement_step'=>max(1,min(2,(int)($raw['movement_step']??1))),
        'columns'=>$columns,'rows'=>$rows,'width'=>$width,'height'=>$height,
        'spawn_points'=>$spawnPoints,'transitions'=>$transitions,
        'unsafe_arrival_points'=>(array)($raw['unsafe_arrival_points']??[]),
        'unsafe_arrival_regions'=>(array)($raw['unsafe_arrival_regions']??[]),
        'encounter_profile'=>trim((string)($raw['encounter_profile']??'')),
        'collision_asset'=>$collisionRel,'collision_asset_abs'=>$collisionAbs,
        'connections'=>array_values(array_filter(array_map('pv_world_area_key',(array)($raw['connections']??[])))),
        'source_box'=>(array)($raw['source_box']??[]),
    ];
}

/**
 * Browser-safe region-map presentation.
 *
 * Kanto/Hoenn retain their authoritative source PNGs and exact 1:1 display
 * dimensions.  Very large single bitmap layers can exceed Chromium/Edge GPU
 * compositor texture limits on some Windows hardware/display-scale paths and
 * paint as a black surface even though overlays continue to render.  Maps
 * larger than the conservative threshold are therefore presented from exact,
 * lossless 1024px source-derived tiles.  No movement/collision coordinates,
 * source assets, or visual scaling are changed.
 */
function pv_world_render_tile_size(): int { return 1024; }
function pv_world_render_tile_threshold(): int { return 2048; }

function pv_world_needs_render_tiles(array $area): bool {
    return max((int)($area['width'] ?? 0), (int)($area['height'] ?? 0)) > pv_world_render_tile_threshold();
}

function pv_world_render_tiles(array $area): array {
    static $cache = [];

    $world = pv_world_normalize_key((string)($area['world'] ?? ''));
    $areaKey = pv_world_area_key((string)($area['key'] ?? ''));
    $width = max(0, (int)($area['width'] ?? 0));
    $height = max(0, (int)($area['height'] ?? 0));
    if (!pv_world_is_region_world($world) || $areaKey === '' || $width <= 0 || $height <= 0 || !pv_world_needs_render_tiles($area)) return [];

    $cacheKey = $world . '/' . $areaKey . '/' . $width . 'x' . $height;
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];

    $staticRoot = realpath(dirname(__DIR__) . '/html/static');
    if (!$staticRoot) return $cache[$cacheKey] = [];

    $tileSize = pv_world_render_tile_size();
    $tiles = [];
    for ($top = 0, $row = 0; $top < $height; $top += $tileSize, $row++) {
        $tileHeight = min($tileSize, $height - $top);
        for ($left = 0, $column = 0; $left < $width; $left += $tileSize, $column++) {
            $tileWidth = min($tileSize, $width - $left);
            $relative = 'images/worlds/' . $world . '/render-tiles/' . $areaKey . '/tile-' . $column . '-' . $row . '.png';
            $absolute = $staticRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_file($absolute)) return $cache[$cacheKey] = [];
            $info = @getimagesize($absolute);
            if (!is_array($info) || (int)($info[0] ?? 0) !== $tileWidth || (int)($info[1] ?? 0) !== $tileHeight) return $cache[$cacheKey] = [];
            $tiles[] = [
                'asset' => $relative,
                'left' => $left,
                'top' => $top,
                'width' => $tileWidth,
                'height' => $tileHeight,
                'column' => $column,
                'row' => $row,
            ];
        }
    }

    return $cache[$cacheKey] = $tiles;
}

function pv_world_ready_areas(string $world): array {
    $world=pv_world_normalize_key($world);
    if(!pv_world_is_region_world($world))return[];
    $manifest=pv_world_manifest($world);$areas=[];
    foreach(array_keys((array)$manifest['areas']) as $key){$area=pv_world_area($world,(string)$key);if($area!==null)$areas[$area['key']]=$area;}
    return $areas;
}

function pv_world_area_url(string $world,string $area): string {
    return pv_url('world_map.php?world='.rawurlencode(pv_world_normalize_key($world)).'&area='.rawurlencode(pv_world_area_key($area)));
}

function pv_world_map_column_available(mysqli $db): bool {
    static $cache = [];
    $key = spl_object_id($db);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='mapusers' AND column_name='world_key' LIMIT 1");
    if (!$stmt) return $cache[$key] = false;
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $cache[$key] = $exists;
}

function pv_world_presence_upsert(mysqli $db, int $uid, string $world, string $area, int $x, int $y): void {
    $world = pv_world_normalize_key($world);
    $area = substr(pv_world_area_key($area), 0, 45);
    if ($uid <= 0 || $area === '') throw new RuntimeException('Invalid world presence state.');
    $username = substr((string)($_SESSION['myuser'] ?? 'Trainer'), 0, 45);
    $trainer = max(1, min(29, (int)($_SESSION['map_preferences'][2] ?? 1)));
    if (!pv_world_map_column_available($db)) throw new RuntimeException('World map namespace migration is required.');
    $stmt=$db->prepare('INSERT INTO mapusers (id,username,trainer,world_key,map,x,y,time) VALUES (?,?,?,?,?,?,?,CURTIME()) ON DUPLICATE KEY UPDATE username=VALUES(username),trainer=VALUES(trainer),world_key=VALUES(world_key),map=VALUES(map),x=VALUES(x),y=VALUES(y),time=CURTIME()');
    if(!$stmt) throw new RuntimeException('World presence is unavailable.');
    $stmt->bind_param('isissii',$uid,$username,$trainer,$world,$area,$x,$y);
    if(!$stmt->execute()){ $err=$stmt->error; $stmt->close(); throw new RuntimeException('Could not update world presence: '.$err); }
    $stmt->close();
    $now=time();
    $stmt=$db->prepare('INSERT INTO world_map_positions (user_id,world_key,area_key,x,y,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE x=VALUES(x),y=VALUES(y),updated_at=VALUES(updated_at)');
    if($stmt){$stmt->bind_param('issiii',$uid,$world,$area,$x,$y,$now);$stmt->execute();$stmt->close();}
    // Recovered online.activity is VARCHAR(45), even though area_key is wider.
    $activity=substr('Exploring '.ucfirst($world).' / '.$area,0,45);
    $stmt=$db->prepare('UPDATE online SET activity=?,time=? WHERE id=?');
    if($stmt){$stmt->bind_param('sii',$activity,$now,$uid);$stmt->execute();$stmt->close();}
    $_SESSION['world_key']=$world;
    $_SESSION['world_area']=$area;
    $_SESSION['mapx']=$x; $_SESSION['mapy']=$y;
}

function pv_world_position(mysqli $db, int $uid, string $world, string $area, array $spawnPoints): array {
    $stmt=$db->prepare('SELECT x,y FROM world_map_positions WHERE user_id=? AND world_key=? AND area_key=? LIMIT 1');
    if($stmt){$stmt->bind_param('iss',$uid,$world,$area);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if($row)return[(int)$row['x'],(int)$row['y']];}
    if (pv_world_map_column_available($db)) {
        $stmt=$db->prepare('SELECT x,y FROM mapusers WHERE id=? AND world_key=? AND map=? LIMIT 1');
        if($stmt){$stmt->bind_param('iss',$uid,$world,$area);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if($row)return[(int)$row['x'],(int)$row['y']];}
    }
    // First visit uses the reviewed route entrance. Other sections are an
    // explicit travel choice rather than a random new-player arrival.
    return array_values($spawnPoints)[0];
}

function pv_world_players(mysqli $db, int $uid, string $world, string $area): array {
    if (!pv_world_map_column_available($db)) return [];
    // Reuse the normalized positive visibility preference used by the Vortex runtime.
    $show = 1;
    $stmt=$db->prepare('SELECT COALESCE(o.memonmap,m.memonmap,1) AS memonmap FROM members m LEFT JOIN members_options o ON o.id=m.id WHERE m.id=? LIMIT 1');
    if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if($row)$show=(int)$row['memonmap']!==0?1:0;}
    if(!$show)return[];
    $cutoff=time()-1800;
    $stmt=$db->prepare('SELECT m.id,m.username,m.trainer,m.x,m.y FROM mapusers m INNER JOIN online o ON o.id=m.id WHERE m.world_key=? AND m.map=? AND m.id<>? AND CAST(o.time AS UNSIGNED)>=? ORDER BY m.username LIMIT 60');
    if(!$stmt)return[];
    $stmt->bind_param('ssii',$world,$area,$uid,$cutoff);$stmt->execute();$r=$stmt->get_result();$players=[];
    while($row=$r->fetch_assoc())$players[]=['id'=>(int)$row['id'],'username'=>(string)$row['username'],'trainer'=>max(1,min(29,(int)$row['trainer'])),'x'=>(int)$row['x'],'y'=>(int)$row['y'],'bot'=>false];
    $stmt->close();

    $remaining=max(0,60-count($players));
    if($remaining>0 && pv_table_exists('bot_trainers')){
        $stmt=$db->prepare('SELECT b.user_id AS id,m.username,b.trainer_sprite AS trainer,b.x,b.y FROM bot_trainers b INNER JOIN members m ON m.id=b.user_id WHERE b.enabled=1 AND b.world_key=? AND b.map_key=? AND b.user_id<>? ORDER BY b.bot_index LIMIT '.(int)$remaining);
        if($stmt){$stmt->bind_param('ssi',$world,$area,$uid);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$players[]=['id'=>(int)$row['id'],'username'=>(string)$row['username'],'trainer'=>max(1,min(29,(int)$row['trainer'])),'x'=>(int)$row['x'],'y'=>(int)$row['y'],'bot'=>true];$stmt->close();}
    }
    return$players;
}

function pv_world_blocks(mysqli $db, string $world, string $area): array {
    $world = pv_world_normalize_key($world);
    $area = pv_world_area_key($area);
    static $runtimeCache=[];
    $runtimeKey=spl_object_id($db).'|'.$world.'|'.$area;
    if(isset($runtimeCache[$runtimeKey]))return $runtimeCache[$runtimeKey];
    $blocks=[];

    // Static region collision ships with each Kanto/Hoenn area. Database rows remain an additive runtime-tuning layer.
    $definition = pv_world_area($world, $area);
    $collisionAbs = trim((string)($definition['collision_asset_abs'] ?? ''));
    if ($collisionAbs !== '') {
        static $staticCollisionCache=[];
        $cacheKey=$world.'|'.$area.'|'.$collisionAbs;
        if(!isset($staticCollisionCache[$cacheKey])){
            $local=[];$decoded=json_decode((string)@file_get_contents($collisionAbs),true);
            if(is_array($decoded))foreach((array)($decoded['blocked']??[]) as $point){
                if(!is_array($point)||count($point)<2)continue;$x=(int)$point[0];$y=(int)$point[1];
                if($x>=1&&$x<=(int)($definition['columns']??0)&&$y>=1&&$y<=(int)($definition['rows']??0))$local[$x.':'.$y]=true;
            }
            $staticCollisionCache[$cacheKey]=$local;
        }
        $blocks=$staticCollisionCache[$cacheKey];
    }

    $stmt=$db->prepare('SELECT xblock,yblock FROM world_map_blocks WHERE world_key=? AND area_key=?');
    if($stmt){
        $stmt->bind_param('ss',$world,$area);$stmt->execute();$r=$stmt->get_result();
        while($row=$r->fetch_assoc())$blocks[(int)$row['xblock'].':'.(int)$row['yblock']]=true;
        $stmt->close();
    }
    return $runtimeCache[$runtimeKey]=$blocks;
}

/**
 * Return true when a one-cell regional move is blocked by bounds, a blocked
 * destination, or diagonal corner clipping. Diagonal travel is only legal
 * when both orthogonal side cells are also open, so trainers cannot squeeze
 * between touching trees, walls, buildings, cliffs or other blockers.
 */
function pv_world_step_blocked(array $area, array $blocks, int $x, int $y, int $tx, int $ty): bool {
    $columns=(int)($area['columns']??0);$rows=(int)($area['rows']??0);
    if($x<1||$x>$columns||$y<1||$y>$rows||isset($blocks[$x.':'.$y]))return true;
    if($tx<1||$tx>$columns||$ty<1||$ty>$rows||isset($blocks[$tx.':'.$ty]))return true;
    $dx=$tx-$x;$dy=$ty-$y;
    if(abs($dx)>1||abs($dy)>1||($dx===0&&$dy===0))return true;
    if(abs($dx)===1&&abs($dy)===1){
        $sideAX=$x+$dx;$sideAY=$y;
        $sideBX=$x;$sideBY=$y+$dy;
        if($sideAX<1||$sideAX>$columns||$sideAY<1||$sideAY>$rows||isset($blocks[$sideAX.':'.$sideAY]))return true;
        if($sideBX<1||$sideBX>$columns||$sideBY<1||$sideBY>$rows||isset($blocks[$sideBX.':'.$sideBY]))return true;
    }
    return false;
}

/**
 * One visual footstep, checked at every collision subdivision. New half-size
 * artwork uses two 16px cells; established maps use one 32px cell. Stop at the
 * first door/stair trigger and never tunnel through an intermediate obstacle.
 * A wall in the second subdivision allows the first safe half-step, retaining
 * access to narrow cropped approaches and the section arrival coordinates.
 *
 * @return array{x:int,y:int,moved:bool,transition:?array}
 */
function pv_world_walk_direction(array $area, array $blocks, int $x, int $y, int $dx, int $dy): array {
    $result=['x'=>$x,'y'=>$y,'moved'=>false,'transition'=>null];
    if(abs($dx)>1||abs($dy)>1||($dx===0&&$dy===0))return $result;
    $steps=max(1,min(2,(int)($area['movement_step']??1)));
    for($step=0;$step<$steps;$step++){
        $tx=$result['x']+$dx;$ty=$result['y']+$dy;
        if(pv_world_step_blocked($area,$blocks,$result['x'],$result['y'],$tx,$ty))break;
        $result=['x'=>$tx,'y'=>$ty,'moved'=>true,'transition'=>pv_world_transition($area,$tx,$ty)];
        if($result['transition']!==null)break;
    }
    return $result;
}

/** Water/decorative landing cells reviewed for this area; movement is separate. */
function pv_world_unsuitable_arrival(array $area, int $x, int $y): bool {
    foreach((array)($area['unsafe_arrival_points']??[]) as $point){
        if(is_array($point)&&count($point)>=2&&(int)$point[0]===$x&&(int)$point[1]===$y)return true;
    }
    foreach((array)($area['unsafe_arrival_regions']??[]) as $box){
        if(!is_array($box)||count($box)<4)continue;
        if($x>=(int)$box[0]&&$x<=(int)$box[2]&&$y>=(int)$box[1]&&$y<=(int)$box[3])return true;
    }
    return false;
}

/**
 * Validate a restored arrival on page entry. A saved lake or decorative island
 * must not override a corrected route entrance. Valid progress remains saved.
 * Checked against current DB collision too, so later tuning cannot strand an
 * account behind newly added blockers. This is not run on every footstep.
 */
function pv_world_arrival_needs_recovery(array $area, array $blocks, int $x, int $y): bool {
    $columns=(int)($area['columns']??0);$rows=(int)($area['rows']??0);
    if($x<1||$x>$columns||$y<1||$y>$rows||isset($blocks[$x.':'.$y])||pv_world_unsuitable_arrival($area,$x,$y))return true;
    $seeds=(array)($area['spawn_points']??[]);
    // A legitimate stair landing may enter a separate section without its own
    // selector entry. Keep that saved progress when any installed warp reaches it.
    foreach(pv_world_region_keys() as $world){
        foreach((array)(pv_world_manifest($world)['areas']??[]) as $source){
            foreach((array)($source['transitions']??[]) as $transition){
                if((string)($transition['target_world']??$world)===(string)($area['world']??'')&&(string)($transition['target_area']??'')===(string)($area['key']??'')){
                    $seeds[]=[(int)($transition['target_x']??0),(int)($transition['target_y']??0)];
                }
            }
        }
    }
    $targets=[];
    foreach($seeds as $point){
        if(!is_array($point)||count($point)<2)continue;
        $sx=(int)$point[0];$sy=(int)$point[1];
        if($sx>=1&&$sx<=$columns&&$sy>=1&&$sy<=$rows&&!isset($blocks[$sx.':'.$sy])&&!pv_world_unsuitable_arrival($area,$sx,$sy))$targets[$sx.':'.$sy]=true;
    }
    if($targets===[])return true;
    $queue=[[$x,$y]];$seen=[$x.':'.$y=>true];
    for($head=0;$head<count($queue);$head++){
        [$cx,$cy]=$queue[$head];
        if(isset($targets[$cx.':'.$cy]))return false;
        foreach([[0,-1],[0,1],[-1,0],[1,0]] as $delta){
            $nx=$cx+$delta[0];$ny=$cy+$delta[1];$key=$nx.':'.$ny;
            if($nx<1||$nx>$columns||$ny<1||$ny>$rows||isset($seen[$key])||isset($blocks[$key]))continue;
            $seen[$key]=true;$queue[]=[$nx,$ny];
        }
    }
    return true;
}

function pv_world_connected_areas(array $area): array {
    $out=[];
    foreach ((array)($area['connections'] ?? []) as $key) {
        $target=pv_world_area((string)$area['world'], (string)$key);
        if ($target!==null) $out[$target['key']]=$target;
    }
    return $out;
}

function pv_world_encounter_profiles(string $world): array {
    static $cache=[];
    $world=pv_world_normalize_key($world);
    if(!pv_world_is_region_world($world))return[];
    if(isset($cache[$world])&&is_array($cache[$world]))return$cache[$world];
    $file=dirname(__DIR__).'/config/worlds/'.$world.'_encounters.php';
    $loaded=is_file($file)?require $file:[];
    return $cache[$world]=is_array($loaded)?$loaded:[];
}

/**
 * Map a successful regional encounter onto the recovered Vortex ordinary
 * variety distribution. The legacy Vortex maps use 336 successful ordinary
 * encounter outcomes (rand_num 665..1000): each special variety owns five
 * outcomes and Normal owns the remaining 311. Keeping this as a dedicated,
 * deterministic helper makes the parity contract executable and auditable.
 */
function pv_world_vortex_variant_from_roll(int $roll): string {
    if($roll<1||$roll>336)throw new InvalidArgumentException('Vortex variety roll must be between 1 and 336.');
    if($roll<=5)return 'Shiny ';
    if($roll<=10)return 'Dark ';
    if($roll<=15)return 'Mystic ';
    if($roll<=20)return 'Metallic ';
    if($roll<=25)return 'Shadow ';
    return '';
}

function pv_world_vortex_variant_roll(): string {
    return pv_world_vortex_variant_from_roll(random_int(1,336));
}

function pv_world_encounter_html(mysqli $db,array $area,int $x,int $y): string {
    unset($_SESSION['wb'],$_SESSION['lvl'],$_SESSION['pv_pending_wild_encounter']);
    $world=pv_world_normalize_key((string)($area['world']??''));
    // The scanner needs only the region label, not every area's image/collision.
    $worldDef=pv_world_manifest($world);$worldLabel=(string)($worldDef['label']??ucfirst($world));
    $profileKey=trim((string)($area['encounter_profile']??''));
    if($profileKey==='')return '<div class="pv-map-quiet"><strong>No wild habitat detected.</strong><span>Wild Pokémon do not appear in this settlement or landmark.</span></div>';
    $profiles=pv_world_encounter_profiles($world);$profile=$profiles[$profileKey]??null;
    if(!is_array($profile))return '<div class="pv-map-quiet"><strong>Habitat data unavailable.</strong><span>Continue exploring this '.pv_h($worldLabel).' area.</span></div>';
    $chance=max(0.0,min(0.75,(float)($profile['chance']??0.25)));
    if(random_int(1,10000)>(int)round($chance*10000))return '<div class="pv-map-quiet"><strong>No wild Pokémon appeared.</strong><span>Keep exploring — '.pv_h($worldLabel).' encounters are random.</span></div>';
    $entries=(array)($profile['entries']??[]);$total=0;
    foreach($entries as $e){if(is_array($e)&&count($e)>=4)$total+=max(0,(int)$e[3]);}
    if($total<=0)return '<div class="pv-map-quiet"><strong>No wild Pokémon appeared.</strong><span>Keep exploring this '.pv_h($worldLabel).' area.</span></div>';
    $roll=random_int(1,$total);$picked=null;
    foreach($entries as $e){if(!is_array($e)||count($e)<4)continue;$roll-=max(0,(int)$e[3]);if($roll<=0){$picked=$e;break;}}
    if(!is_array($picked))return'';
    $baseSpecies=trim((string)$picked[0]);$min=max(2,(int)$picked[1]);$max=max($min,(int)$picked[2]);[$min,$max]=pv_wild_balanced_level_range($min,$max,2);$level=random_int($min,$max);

    // Species rarity is resolved first from the area's existing weighted table.
    // Variety is a second, independent roll using the recovered Vortex ordinary
    // encounter distribution, so this feature cannot distort route species odds.
    $variantPrefix=pv_world_vortex_variant_roll();
    $variantLabel=$variantPrefix===''?'Normal':trim($variantPrefix);
    $species=$variantPrefix.$baseSpecies;

    $stmt=$db->prepare('SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=? LIMIT 1');
    if(!$stmt)return '<div class="pv-map-quiet"><strong>A wild signal faded.</strong><span>Continue exploring.</span></div>';
    $stmt->bind_param('s',$species);$stmt->execute();$guide=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$guide){pv_log($worldLabel.' encounter variety missing from pguide: '.$species.' (base '.$baseSpecies.')');return '<div class="pv-map-quiet"><strong>A wild signal faded.</strong><span>Continue exploring.</span></div>';}
    $displayName=(string)$guide['name'];
    $spriteName=$displayName;$spriteRoot=dirname(__DIR__).'/html/static/images/pokemon/';
    if(!is_file($spriteRoot.$spriteName.'.gif')){
        pv_log($worldLabel.' encounter variety sprite missing: '.$spriteName);
        $spriteName=$baseSpecies;
    }
    $token=bin2hex(random_bytes(24));
    $_SESSION['pv_pending_wild_encounter']=[
        'token'=>$token,'pid'=>(int)$guide['id'],'name'=>$displayName,'display_name'=>$displayName,'sprite_name'=>$spriteName,'level'=>$level,
        'base_species'=>$baseSpecies,'variant'=>$variantLabel,
        'world'=>$world,'area'=>(string)$area['key'],'map'=>0,'x'=>$x,'y'=>$y,'created_at'=>time(),'consumed'=>false,
    ];
    $_SESSION['wb']=(int)$guide['id'];$_SESSION['lvl']=$level;
    $sprite=pv_static_file('images/pokemon/'.$spriteName.'.gif','images/Pokeball.PNG');
    $owned=false;$uid=(int)($_SESSION['myid']??0);
    $stmt=$db->prepare('SELECT 1 FROM pokemon WHERE owner=? AND pid=? LIMIT 1');
    if($stmt){$pid=(int)$guide['id'];$stmt->bind_param('ii',$uid,$pid);$stmt->execute();$owned=(bool)$stmt->get_result()->fetch_row();$stmt->close();}
    return '<div class="pv-map-wild-card"><img src="'.pv_h($sprite).'" alt="'.pv_h($displayName).'"><div><small>'.pv_h(strtoupper($worldLabel)).' WILD SIGNAL</small><strong>Wild '.pv_h($displayName).' appeared.</strong><span>Level '.$level.($owned?' · Already registered':' · New scan').'</span></div><form method="post" action="'.pv_h(pv_url('wildbattle.php')).'">'.pv_csrf_field().'<input type="hidden" name="encounter_token" value="'.pv_h($token).'"><button class="pv-button" type="submit" name="start_wild_battle" value="1">Battle!</button></form></div>';
}

function pv_world_transition(array $area, int $x, int $y): ?array {
    foreach((array)$area['transitions'] as $transition){if((int)$transition['x']===$x&&(int)$transition['y']===$y)return$transition;}
    return null;
}

function pv_world_blocked_directions(mysqli $db, array $area, int $x, int $y): array {
    $deltas=[1=>[0,-1],2=>[0,1],3=>[-1,0],4=>[1,0],5=>[-1,-1],6=>[-1,1],7=>[1,-1],8=>[1,1]];
    $blocks=pv_world_blocks($db,(string)$area['world'],(string)$area['key']);$blocked=[];
    foreach($deltas as $direction=>$delta){
        $walk=pv_world_walk_direction($area,$blocks,$x,$y,$delta[0],$delta[1]);
        if(!$walk['moved']){$blocked[]=$direction;continue;}
        $transition=$walk['transition'];
        if($transition){
            $target=pv_world_area((string)$transition['target_world'],(string)$transition['target_area']);
            if($target===null){$blocked[]=$direction;continue;}
            $targetX=(int)$transition['target_x'];$targetY=(int)$transition['target_y'];
            $targetBlocks=pv_world_blocks($db,(string)$target['world'],(string)$target['key']);
            if($targetX<1||$targetX>(int)$target['columns']||$targetY<1||$targetY>(int)$target['rows']||isset($targetBlocks[$targetX.':'.$targetY]))$blocked[]=$direction;
            continue;
        }
    }
    return$blocked;
}
