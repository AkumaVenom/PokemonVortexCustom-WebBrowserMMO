<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/map_runtime.php';
require_once __DIR__ . '/includes/bot_runtime.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();

try{$db=pv_db();}
catch(Throwable $e){pv_log('Map select DB unavailable: '.$e->getMessage());pv_redirect('dashboard.php?service=unavailable');}

$worlds=pv_world_catalog();
$requestedWorld=pv_world_normalize_key((string)($_GET['world']??'vortex'));
$selectedWorld=isset($worlds[$requestedWorld])?$requestedWorld:'vortex';
$status=(string)($_GET['status']??'');
$cutoff=time()-1800;
pv_bot_tick($db,60);

$catalog=pv_map_catalog();
$groupCopy=[
    'Grass'=>['GRS','Grasslands','Open routes, woodland clearings and classic wild-Pokémon habitats.'],
    'Cave'=>['CAV','Caverns','Rocky tunnels and deeper encounter zones with distinct Pokémon pools.'],
    'Electric'=>['ELC','Power Zones','Charged industrial routes and high-voltage exploration sectors.'],
    'Fire'=>['FIR','Volcanic Zones','Hot routes, volcanic terrain and fire-aligned encounter sectors.'],
    'Ice'=>['ICE','Frozen Zones','Cold routes and frozen terrain with specialized encounter pools.'],
    'Ghost'=>['GST','Spectral Zones','Late-world regions with eerie terrain and unusual encounters.'],
];
$counts=array_fill(1,25,0);
if(pv_world_map_column_available($db))$stmt=$db->prepare("SELECT m.map,COUNT(*) c FROM mapusers m INNER JOIN online o ON o.id=m.id WHERE m.world_key='vortex' AND CAST(o.time AS UNSIGNED)>=? GROUP BY m.map");
else $stmt=$db->prepare('SELECT m.map,COUNT(*) c FROM mapusers m INNER JOIN online o ON o.id=m.id WHERE CAST(o.time AS UNSIGNED)>=? GROUP BY m.map');
if($stmt){$stmt->bind_param('i',$cutoff);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc()){$i=(int)$row['map'];if($i>=1&&$i<=25)$counts[$i]=(int)$row['c'];}$stmt->close();}
if(pv_bot_registry_ready($db)){
    $botCounts=$db->query("SELECT map_key,COUNT(*) c FROM bot_trainers WHERE enabled=1 AND world_key='vortex' GROUP BY map_key");
    if($botCounts){while($row=$botCounts->fetch_assoc()){$i=(int)$row['map_key'];if($i>=1&&$i<=25)$counts[$i]+=(int)$row['c'];}$botCounts->free();}
}
$groups=[];foreach($catalog as $id=>$meta)$groups[$meta[0]][$id]=$meta[1];

$regionData=[];
foreach(pv_world_region_keys() as $regionKey){
    $areas=pv_world_ready_areas($regionKey);$manifest=pv_world_manifest($regionKey);
    $areaGroups=['Settlements'=>[],'Routes & Wild Areas'=>[],'Caves & Landmarks'=>[]];
    foreach($areas as $key=>$area){
        $cat=(string)($area['category']??'Area');
        if(in_array($cat,['City','Town'],true))$areaGroups['Settlements'][$key]=$area;
        elseif(in_array($cat,['Route','Forest','Sea Route','Coast'],true))$areaGroups['Routes & Wild Areas'][$key]=$area;
        else $areaGroups['Caves & Landmarks'][$key]=$area;
    }
    $presence=[];
    if($areas&&pv_world_map_column_available($db)){
        $stmt=$db->prepare('SELECT m.map,COUNT(*) c FROM mapusers m INNER JOIN online o ON o.id=m.id WHERE m.world_key=? AND CAST(o.time AS UNSIGNED)>=? GROUP BY m.map');
        if($stmt){$stmt->bind_param('si',$regionKey,$cutoff);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$presence[(string)$row['map']]=(int)$row['c'];$stmt->close();}
        if(pv_bot_registry_ready($db)){
            $stmt=$db->prepare('SELECT map_key,COUNT(*) c FROM bot_trainers WHERE enabled=1 AND world_key=? GROUP BY map_key');
            if($stmt){$stmt->bind_param('s',$regionKey);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc()){$key=(string)$row['map_key'];$presence[$key]=(int)($presence[$key]??0)+(int)$row['c'];}$stmt->close();}
        }
    }
    $regionData[$regionKey]=['areas'=>$areas,'manifest'=>$manifest,'groups'=>$areaGroups,'counts'=>$presence];
}

pv_page_start('World Maps','map_select.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('map_select.php'); ?>
<main class="pv-main-column">
<section class="pv-page pv-map-select-page">
    <div class="pv-section-hero pv-map-select-hero">
        <div><span class="pv-eyebrow">WORLD MAP // MULTI-REGION EXPLORATION</span><h1>Explore the Pokémon world</h1><p class="pv-subtle">Choose a region, enter an area, search for wild Pokémon and meet other trainers exploring nearby.</p></div>
        <div class="pv-mini-status"><i></i> WORLD MAP READY</div>
    </div>

    <div class="pv-world-switcher" aria-label="World selection">
        <?php foreach($worlds as $key=>$world):$active=$selectedWorld===$key;$prepared=($world['status']??'')==='prepared';?>
            <a class="pv-world-card<?=$active?' is-active':''?><?=$prepared?' is-prepared':''?>" href="<?=pv_h(pv_url('map_select.php?world='.$key))?>">
                <span class="pv-world-code"><?=pv_h($key==='vortex'?'VOR':($key==='kanto'?'KAN':'HON'))?></span>
                <span class="pv-world-card-copy"><small><?=pv_h((string)$world['subtitle'])?></small><strong><?=pv_h((string)$world['label'])?></strong><em><?=pv_h((string)$world['description'])?></em></span>
                <b><?=$prepared?'PREPARED':'ENTER'?></b>
            </a>
        <?php endforeach;?>
    </div>

    <?php if($status==='unavailable'):?><div class="pv-flash info">That world area is not available yet. No unfinished map has been exposed to your trainer.</div><?php endif;?>

    <div class="pv-map-legend">
        <span><i></i> Keyboard: WASD / arrows / numpad</span><span><i></i> Terrain-aware movement</span><span><i></i> Nearby trainers</span><span><i></i> Wild Pokémon habitats</span>
    </div>

    <?php if($selectedWorld==='vortex'):?>
        <div class="pv-world-section-head"><div><span>ACTIVE WORLD</span><h2>Vortex World</h2><p>Explore all 25 areas of the original Vortex world.</p></div><strong>25 / 25 ONLINE</strong></div>
        <?php foreach($groups as $category=>$maps):[$code,$title,$description]=$groupCopy[$category];?>
        <section class="pv-map-group">
            <div class="pv-map-group-head"><div class="pv-map-group-code"><?=pv_h($code)?></div><div><span><?=pv_h(strtoupper($category))?> BIOME</span><h2><?=pv_h($title)?></h2><p><?=pv_h($description)?></p></div></div>
            <div class="pv-map-card-grid">
            <?php foreach($maps as $id=>$name):?>
                <a class="pv-map-card" href="<?=pv_h(pv_url('map.php?map='.$id))?>"><span class="pv-map-card-image"><img src="<?=pv_h(pv_static_file('images/maps/map'.$id.'.png','images/maps/v3/map'.$id.'.png'))?>" alt="<?=pv_h($name)?> preview"></span><span class="pv-map-card-body"><small>MAP // <?=str_pad((string)$id,2,'0',STR_PAD_LEFT)?></small><strong><?=pv_h($name)?></strong><em><?=(int)$counts[$id]?> trainer<?=$counts[$id]===1?'':'s'?> active</em></span><b>ENTER ›</b></a>
            <?php endforeach;?>
            </div>
        </section>
        <?php endforeach;?>
    <?php else:
        $data=$regionData[$selectedWorld]??['areas'=>[],'manifest'=>[],'groups'=>[],'counts'=>[]];
        $areas=$data['areas'];$manifest=$data['manifest'];$areaGroups=$data['groups'];$regionCounts=$data['counts'];
        $worldDef=$worlds[$selectedWorld];$label=(string)$worldDef['label'];$upper=strtoupper($label);
        $isHoenn=$selectedWorld==='hoenn';
        $sourceName=$isHoenn?'Pokémon Emerald':'FireRed/LeafGreen';
        $heroTitle=$isHoenn?'Explore Hoenn':'Explore Kanto';
        $heroBody=$isHoenn
            ?'Travel through Hoenn cities, routes, caves and landmarks, with wild Pokémon suited to each location.'
            :'Travel through Kanto cities, routes, caves and landmarks, with wild Pokémon suited to each location.';
    ?>
        <div class="pv-world-section-head"><div><span>ACTIVE REGION</span><h2><?=pv_h($label)?></h2><p><?=pv_h((string)$worldDef['description'])?></p></div><strong><?=count($areas)?> AREAS ONLINE</strong></div>
        <?php if(!empty($manifest['master_asset'])):?><section class="pv-region-overworld-source"><img src="<?=pv_h(pv_static((string)$manifest['master_asset']))?>" alt="<?=pv_h($label)?> overworld"><div><span class="pv-eyebrow"><?=pv_h($upper)?> REGION OVERVIEW</span><h2><?=pv_h($heroTitle)?></h2><p><?=pv_h($heroBody)?></p><div class="pv-region-readiness"><span><i></i> Full-size regional maps</span><span><i></i> Quick area previews</span><span><i></i> Saved trainer positions</span><span><i></i> <?=pv_h($label)?> wild habitats</span></div></div></section><?php endif;?>
        <?php foreach($areaGroups as $groupName=>$groupAreas):if(!$groupAreas)continue;$code=$groupName==='Settlements'?'CTY':($groupName==='Caves & Landmarks'?'LMK':'RTE');?>
            <section class="pv-map-group"><div class="pv-map-group-head"><div class="pv-map-group-code"><?=pv_h($code)?></div><div><span><?=pv_h($upper)?> AREAS</span><h2><?=pv_h($groupName)?></h2><p><?=$groupName==='Settlements'?'Cities and towns across '.$label.'.':($groupName==='Caves & Landmarks'?'Caves, mountains, dungeons and major landmarks to explore.':'Routes, forests and sea areas with their own wild Pokémon.')?></p></div></div><div class="pv-map-card-grid">
            <?php foreach($groupAreas as $key=>$area):$preview=(string)($area['preview_asset']??'');if($preview==='')$preview=(string)($area['logical_asset']??'');if($preview==='')$preview=(string)$area['asset'];?>
                <a class="pv-map-card" href="<?=pv_h(pv_world_area_url($selectedWorld,$key))?>"><span class="pv-map-card-image"><img src="<?=pv_h(pv_static($preview))?>" alt="<?=pv_h((string)$area['name'])?> preview" loading="lazy"></span><span class="pv-map-card-body"><small><?=pv_h($upper)?> // <?=pv_h(strtoupper($key))?></small><strong><?=pv_h((string)$area['name'])?></strong><em><?=(int)($regionCounts[$key]??0)?> trainer<?=((int)($regionCounts[$key]??0)===1)?'':'s'?> active<?=trim((string)($area['encounter_profile']??''))!==''?' · wild habitat':''?></em></span><b>ENTER ›</b></a>
            <?php endforeach;?></div></section>
        <?php endforeach;?>
    <?php endif;?>
</section>
</main>
</div>
<?php pv_page_end(); ?>
