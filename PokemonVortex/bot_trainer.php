<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bot_runtime.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();

try { $db = pv_db(); }
catch (Throwable $e) { pv_log('Bot trainer profile DB unavailable: '.$e->getMessage()); pv_redirect('map_select.php?service=unavailable'); }

$botId=max(0,(int)($_GET['id']??0));
$profile=$botId>0?pv_bot_profile($db,$botId):null;
if(!$profile)pv_redirect('map_select.php?status=unavailable');
pv_bot_tick($db,24);
$profile=pv_bot_profile($db,$botId)??$profile;

$teamIds=[];
for($slot=1;$slot<=6;$slot++){
    $pokemonId=max(0,(int)($profile['s'.$slot]??0));
    if($pokemonId>0&&!in_array($pokemonId,$teamIds,true))$teamIds[]=$pokemonId;
}
$team=[];
if($teamIds){
    $idList=implode(',',array_map('intval',$teamIds));
    $stmt=$db->prepare("SELECT id,name,lvl,t1,t2,ball FROM pokemon WHERE CAST(owner AS UNSIGNED)=? AND id IN ({$idList}) ORDER BY FIELD(id,{$idList})");
    if($stmt){$stmt->bind_param('i',$botId);$stmt->execute();$team=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();}
}

$world=pv_world_normalize_key((string)$profile['world_key']);
$mapKey=(string)$profile['map_key'];
if($world==='vortex'){
    $catalog=pv_map_catalog();
    $mapId=max(1,min(25,(int)$mapKey));
    $location=(string)($catalog[$mapId][1]??('Vortex Map '.$mapId));
    $locationUrl=pv_url('map.php?map='.$mapId);
    $worldLabel='Vortex World';
}else{
    $area=pv_world_area($world,$mapKey);
    $location=(string)($area['name']??ucwords(str_replace('-',' ',$mapKey)));
    $locationUrl=$area?pv_world_area_url($world,$mapKey):pv_url('map_select.php?world='.$world);
    $worldDef=pv_world_definition($world);
    $worldLabel=(string)($worldDef['label']??ucfirst($world));
}
$actions=[
    'spawned'=>'Entered the world','move'=>'Exploring the map','idle'=>'Surveying the area',
    'caught_wild'=>'Caught a wild Pokémon','won_wild'=>'Won a wild Pokémon battle','lost_wild'=>'Lost a wild Pokémon battle',
];
$lastAction=$actions[(string)$profile['last_action']]??'Exploring the world';
$lastWild=trim((string)$profile['last_wild_name']);
$trainer=max(1,min(29,(int)$profile['trainer_sprite']));
$username=(string)$profile['username'];

pv_page_start($username,'map_select.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('map_select.php'); ?>
<main class="pv-main-column"><section class="pv-page pv-bot-profile-page">
<?php if((string)($_GET['error']??'')==='live'):?>
<div class="pv-flash danger"><strong>Live AI Battle could not start.</strong><span>Make sure your active team is valid, then try the challenge again.</span></div>
<?php endif;?>
<div class="pv-section-hero pv-bot-profile-hero">
    <div class="pv-bot-profile-identity">
        <span class="pv-bot-trainer-avatar"><img src="<?=pv_h(pv_static_file('images/sprites/'.$trainer.'whole.gif','images/sprites/1whole.gif'))?>" alt="<?=pv_h($username)?>"></span>
        <div><span class="pv-eyebrow">AUTONOMOUS TRAINER // AI <?=str_pad((string)(int)$profile['bot_index'],4,'0',STR_PAD_LEFT)?></span><h1><?=pv_h($username)?></h1><p class="pv-subtle">A persistent trainer roaming the MMO world. This trainer battles wild Pokémon, catches Pokémon for its collection and can battle players.</p></div>
    </div>
    <div class="pv-mini-status"><i></i> AI TRAINER ACTIVE</div>
</div>

<div class="pv-bot-profile-grid">
<section class="pv-panel pv-bot-interaction-card">
    <div class="pv-map-panel-label">TRAINER INTERACTION</div>
    <h2>Challenge <?=pv_h($username)?></h2>
    <p>Choose a trainer snapshot battle for the classic battle flow, or start a Live AI Battle against this trainer's current team.</p>
    <div class="pv-actions pv-bot-actions">
        <a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('battle.php?bid='.$botId))?>">Trainer Snapshot Battle</a>
        <form method="post" action="<?=pv_h(pv_url('bot_live_battle.php'))?>">
            <?=pv_csrf_field()?>
            <input type="hidden" name="battle_action_token" value="<?=pv_h(pv_action_token('bot_live_battle.php'))?>">
            <input type="hidden" name="bot_id" value="<?=$botId?>">
            <button class="pv-button" type="submit">Start Live AI Battle</button>
        </form>
    </div>
    <div class="pv-bot-scope-note"><strong>Autonomy rules</strong><span>This AI may battle players and wild Pokémon and may catch wild Pokémon. It does not autonomously battle gyms, Battle Arena trainers, event trainers or Sidequest opponents.</span></div>
</section>

<section class="pv-panel pv-bot-location-card">
    <div class="pv-map-panel-label">LIVE WORLD STATE</div>
    <dl class="pv-bot-stat-list">
        <div><dt>World</dt><dd><?=pv_h($worldLabel)?></dd></div>
        <div><dt>Location</dt><dd><a href="<?=pv_h($locationUrl)?>"><?=pv_h($location)?></a></dd></div>
        <div><dt>Coordinates</dt><dd>X <?=number_format((int)$profile['x'])?> · Y <?=number_format((int)$profile['y'])?></dd></div>
        <div><dt>Current activity</dt><dd><?=pv_h($lastAction)?></dd></div>
        <?php if($lastWild!==''):?><div><dt>Last wild encounter</dt><dd><?=pv_h($lastWild)?> · Lv. <?=number_format((int)$profile['last_wild_level'])?></dd></div><?php endif;?>
    </dl>
</section>
</div>

<section class="pv-panel pv-bot-team-panel">
    <div class="pv-page-head"><div><span class="pv-eyebrow">CURRENT ACTIVE TEAM</span><h2><?=pv_h($username)?>'s Pokémon</h2><p class="pv-subtle">This is the persisted team used by trainer snapshots and Live AI Battles. Future catches can fill empty slots or promote stronger Pokémon into the team.</p></div><strong class="pv-bot-team-count"><?=count($team)?> / 6</strong></div>
    <?php if($team):?><div class="pv-bot-team-grid">
    <?php foreach($team as $slot=>$pokemon):?>
        <article class="pv-bot-pokemon-card"><span class="pv-bot-pokemon-slot">SLOT <?=($slot+1)?></span><div class="pv-bot-pokemon-sprite"><img src="<?=pv_h(pv_static('images/pokemon/'.rawurlencode((string)$pokemon['name']).'.gif'))?>" alt="<?=pv_h((string)$pokemon['name'])?>"></div><div><strong><?=pv_h((string)$pokemon['name'])?></strong><span>Lv. <?=number_format((int)$pokemon['lvl'])?></span><small><?=pv_h(trim((string)$pokemon['t1'].((string)$pokemon['t2']!==''?' / '.(string)$pokemon['t2']:'')))?></small></div></article>
    <?php endforeach;?></div><?php else:?><div class="pv-empty-state"><strong>No valid active team.</strong><span>This bot cannot be challenged until its team is repaired by the schema population process.</span></div><?php endif;?>
</section>

<section class="pv-bot-metrics-grid">
    <article><span>WILD BATTLES</span><strong><?=number_format((int)$profile['wild_battles'])?></strong><small><?=number_format((int)$profile['wild_wins'])?> wins</small></article>
    <article><span>CAPTURES</span><strong><?=number_format((int)$profile['captures'])?></strong><small><?=number_format((int)$profile['total_poke'])?> owned Pokémon</small></article>
    <article><span>LIVE PLAYER BATTLES</span><strong><?=number_format((int)$profile['player_battles'])?></strong><small><?=number_format((int)$profile['player_wins'])?> W · <?=number_format((int)$profile['player_losses'])?> L</small></article>
    <article><span>TRAINER SPRITE</span><strong>#<?=number_format($trainer)?></strong><small>persistent identity</small></article>
</section>
</section></main></div>
<?php pv_page_end(); ?>
