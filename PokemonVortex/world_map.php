<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/world_maps.php';
require_once __DIR__ . '/includes/bot_runtime.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();

$world=pv_world_normalize_key((string)($_GET['world']??'kanto'));
$areaKey=pv_world_area_key((string)($_GET['area']??''));
$area=pv_world_area($world,$areaKey);
$worldDef=pv_world_definition($world);
if(!pv_world_is_region_world($world)||$area===null||!is_array($worldDef)){
    pv_redirect('map_select.php?world='.rawurlencode($world).'&status=unavailable');
}
$worldLabel=(string)($worldDef['label']??ucfirst($world));
$worldSubtitle=(string)($worldDef['subtitle']??($worldLabel.' World'));
$sourceLabel=$world==='hoenn'?'Pokémon Emerald':'FireRed/LeafGreen';
$renderTiles=pv_world_render_tiles($area);
$renderMode=$renderTiles!==[]?'tiled':'single';
if(pv_world_needs_render_tiles($area)&&$renderTiles===[]){
    pv_log('Browser-safe region map tiles are unavailable for '.$world.'/'.$areaKey.'; falling back to the authoritative source PNG.');
}

try{$db=pv_db();}
catch(Throwable $e){pv_log('World map DB unavailable: '.$e->getMessage());pv_redirect('dashboard.php?service=unavailable');}

$uid=(int)$_SESSION['myid'];
[$x,$y]=pv_world_position($db,$uid,$world,$areaKey,(array)$area['spawn_points']);
$areaBlocks=pv_world_blocks($db,$world,$areaKey);
$entryPoints=[];
foreach((array)$area['spawn_points'] as $point){
    $sx=(int)($point[0]??0);$sy=(int)($point[1]??0);
    if($sx>=1&&$sx<=(int)$area['columns']&&$sy>=1&&$sy<=(int)$area['rows']&&!isset($areaBlocks[$sx.':'.$sy])&&!pv_world_unsuitable_arrival($area,$sx,$sy))$entryPoints[]=[$sx,$sy];
}
// Authored caves and islands contain separate walkable sections. Use only
// collision-validated map arrivals, never caller-supplied coordinates.
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($_POST['entry_point'])&&pv_verify_csrf()){
    $entryIndex=filter_var($_POST['entry_point'],FILTER_VALIDATE_INT);
    if(is_int($entryIndex)&&isset($entryPoints[$entryIndex])){
        [$x,$y]=$entryPoints[$entryIndex];
        unset($_SESSION['wb'],$_SESSION['lvl'],$_SESSION['pv_pending_wild_encounter']);
    }
}
$positionInvalid=pv_world_arrival_needs_recovery($area,$areaBlocks,$x,$y);
if($positionInvalid){
    $safeSpawn=null;
    foreach($entryPoints as $spawn){
        $sx=(int)($spawn[0]??0);$sy=(int)($spawn[1]??0);
        if($sx>=1&&$sx<=(int)$area['columns']&&$sy>=1&&$sy<=(int)$area['rows']&&!isset($areaBlocks[$sx.':'.$sy])){$safeSpawn=[$sx,$sy];break;}
    }
    if($safeSpawn===null)pv_redirect('map_select.php?world='.rawurlencode($world).'&status=unavailable');
    [$x,$y]=$safeSpawn;
    unset($_SESSION['wb'],$_SESSION['lvl'],$_SESSION['pv_pending_wild_encounter']);
}
if((string)($_SESSION['world_key']??'')!==$world||(string)($_SESSION['world_area']??'')!==$areaKey){
    unset($_SESSION['wb'],$_SESSION['lvl'],$_SESSION['pv_pending_wild_encounter']);
}
pv_world_presence_upsert($db,$uid,$world,$areaKey,$x,$y);
pv_bot_tick($db,48,$world,$areaKey,45);
$players=pv_world_players($db,$uid,$world,$areaKey);
$blockedDirections=pv_world_blocked_directions($db,$area,$x,$y);
$connectedAreas=pv_world_connected_areas($area);
$hasEncounters=trim((string)($area['encounter_profile']??''))!=='';
$trainer=max(1,min(29,(int)($_SESSION['map_preferences'][2]??1)));

pv_page_start((string)$area['name'],'map_select.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('map_select.php'); ?>
<main class="pv-main-column">
<section class="pv-page pv-map-page pv-world-map-page">
    <div class="pv-page-head pv-map-head">
        <div>
            <span class="pv-eyebrow"><?=pv_h(strtoupper($worldLabel))?> EXPLORATION // <?=pv_h(strtoupper($areaKey))?></span>
            <h1><?=pv_h((string)$area['name'])?></h1>
            <p class="pv-subtle">Explore <?=pv_h((string)$area['name'])?>, search for wild Pokémon and travel through connected areas while other trainers explore nearby.</p>
        </div>
        <div class="pv-map-head-actions"><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('map_select.php?world='.$world))?>"><?=pv_h($worldLabel)?> areas</a><a class="pv-button" href="<?=pv_h(pv_url('options.php'))?>">Map options</a></div>
    </div>

    <div class="pv-map-layout">
        <div class="pv-map-console">
            <div class="pv-map-console-top"><span><i></i> <?=pv_h(strtoupper($worldLabel))?> AREA READY</span><b><?=pv_h(strtoupper((string)$area['category']))?></b><em id="pv-world-coordinates">X <?=(int)$x?> // Y <?=(int)$y?></em></div>
            <div class="pv-map-viewport-wrap pv-world-map-viewport pv-region-native-viewport" id="pv-world-map-viewport" aria-label="<?=pv_h((string)$area['name'])?> exploration map">
                <div class="pv-world-map-stage pv-region-native-stage" id="pv-world-map-stage" data-map-render="<?=pv_h($renderMode)?>" style="width:<?=(int)$area['width']?>px;height:<?=(int)$area['height']?>px;--pv-world-tile:<?=(int)$area['display_tile_size']?>px">
                    <?php if($renderTiles!==[]):?>
                    <div class="pv-world-map-tile-layer" id="pv-world-map-art" aria-hidden="true" data-world-map-tile-count="<?=count($renderTiles)?>">
                        <?php foreach($renderTiles as $tile):?>
                        <img class="pv-world-map-render-tile" data-world-map-image src="<?=pv_h(pv_static((string)$tile['asset']).'?v='.pv_asset_version())?>" alt="" draggable="false" loading="eager" decoding="async" width="<?=(int)$tile['width']?>" height="<?=(int)$tile['height']?>" style="left:<?=(int)$tile['left']?>px;top:<?=(int)$tile['top']?>px;--pv-map-render-tile-width:<?=(int)$tile['width']?>px;--pv-map-render-tile-height:<?=(int)$tile['height']?>px">
                        <?php endforeach;?>
                    </div>
                    <?php else:?>
                    <img class="pv-world-map-art pv-region-native-art" id="pv-world-map-art" data-world-map-image src="<?=pv_h(pv_static((string)$area['asset']).'?v='.pv_asset_version())?>" alt="<?=pv_h((string)$area['name'])?> map" draggable="false" loading="eager" decoding="async" width="<?=(int)$area['width']?>" height="<?=(int)$area['height']?>">
                    <?php endif;?>
                    <div class="pv-map-actors" id="pv-world-map-actors"></div>
                </div>
            </div>
            <noscript><div class="pv-map-noscript">Map movement requires JavaScript enabled in your browser.</div></noscript>
            <div class="pv-map-console-bottom"><span id="pv-world-map-status">Ready. Use WASD, arrow keys, numpad or the movement pad.</span><span><?=pv_h(strtoupper($worldSubtitle))?></span></div>
        </div>
        <aside class="pv-map-sidebar">
            <section class="pv-map-control-panel"><div class="pv-map-panel-label">MOVEMENT CONTROL</div><div class="pv-map-compass" aria-label="Map movement controls">
                <button type="button" data-world-direction="5" aria-label="Move up-left">↖</button><button type="button" data-world-direction="1" aria-label="Move up">↑</button><button type="button" data-world-direction="7" aria-label="Move up-right">↗</button>
                <button type="button" data-world-direction="3" aria-label="Move left">←</button><div class="pv-map-trainer-core"><img src="<?=pv_h(pv_static_file('images/sprites/'.$trainer.'whole.gif','images/sprites/1whole.gif'))?>" alt="Your trainer"></div><button type="button" data-world-direction="4" aria-label="Move right">→</button>
                <button type="button" data-world-direction="6" aria-label="Move down-left">↙</button><button type="button" data-world-direction="2" aria-label="Move down">↓</button><button type="button" data-world-direction="8" aria-label="Move down-right">↘</button>
            </div><p>Use the movement pad or keyboard to follow open paths, avoid blocked terrain and travel through connected exits.</p></section>
            <section class="pv-map-encounter-panel"><div class="pv-map-panel-label">WILD ENCOUNTER SCANNER</div><div id="pv-world-map-encounter" aria-live="polite"><div class="pv-map-quiet"><?php if($hasEncounters):?><strong>Wild Pokémon can appear here.</strong><span>Move through the area to search for wild Pokémon found around this location.</span><?php else:?><strong>No roaming habitat in this area.</strong><span>Try a connected route or wild area to find Pokémon.</span><?php endif;?></div></div></section>
            <?php if($connectedAreas):?><section class="pv-map-region-panel pv-world-connections"><div class="pv-map-panel-label">CONNECTED AREAS</div><div class="pv-world-connection-list"><?php foreach($connectedAreas as $connected):?><a href="<?=pv_h(pv_world_area_url($world,(string)$connected['key']))?>"><span><?=pv_h((string)$connected['category'])?></span><strong><?=pv_h((string)$connected['name'])?></strong><b>TRAVEL ›</b></a><?php endforeach;?></div></section><?php endif;?>
            <section class="pv-map-region-panel"><div class="pv-map-panel-label">AREA TRAVEL</div><form class="pv-world-entry-form" method="post" action="<?=pv_h(pv_world_area_url($world,$areaKey))?>"><?=pv_csrf_field()?><?php if(count($entryPoints)>1):?><label for="pv-world-entry">Choose an entrance or section</label><select name="entry_point" id="pv-world-entry"><?php foreach($entryPoints as $index=>$point):?><option value="<?=$index?>"><?=$index===0?'Main entrance':'Section '.($index+1)?></option><?php endforeach;?></select><button class="pv-button pv-button-secondary" type="submit">Travel to selected entry</button><?php else:?><input type="hidden" name="entry_point" value="0"><button class="pv-button pv-button-secondary" type="submit">Return to entrance</button><?php endif;?></form></section>
            <section class="pv-map-region-panel"><div class="pv-map-panel-label">AREA INFO</div><dl><div><dt>World</dt><dd><?=pv_h($worldLabel)?></dd></div><div><dt>Area</dt><dd><?=pv_h((string)$area['name'])?></dd></div><div><dt>Type</dt><dd><?=pv_h((string)$area['category'])?></dd></div><div><dt>Trainers visible</dt><dd><?=count($players)+1?></dd></div></dl></section>
        </aside>
    </div>
</section>
</main>
</div>
<script>
window.PV_WORLD_MAP_CONFIG = <?=json_encode([
    'base'=>pv_base(),'world'=>$world,'worldLabel'=>$worldLabel,'area'=>$areaKey,'x'=>$x,'y'=>$y,
    'columns'=>(int)$area['columns'],'rows'=>(int)$area['rows'],'tileSize'=>(int)$area['display_tile_size'],'logicalTileSize'=>(int)$area['tile_size'],
    'trainer'=>$trainer,'players'=>$players,'blockedDirections'=>$blockedDirections,
    'moveUrl'=>pv_url('world_map_move.php'),'presenceUrl'=>pv_url('world_map_presence.php'),'presenceInterval'=>2000,
    'botProfileBase'=>pv_url('bot_trainer.php?id='),
    'csrf'=>pv_csrf_token(),'spriteBase'=>pv_static('images/sprites/'),'renderMode'=>$renderMode,'renderImageCount'=>$renderTiles!==[]?count($renderTiles):1,
],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
</script>
<script defer src="<?=pv_h(pv_asset('js/world-map.js'))?>?v=<?=pv_asset_version()?>"></script>
<?php pv_page_end(); ?>
