<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/battle_catalog.php';
require_once __DIR__ . '/includes/event_battle_catalog.php';
pv_require_login();

try { $db = pv_db(); }
catch (Throwable $e) { pv_log('Battle Arena DB unavailable: '.$e->getMessage()); pv_redirect('dashboard.php?service=unavailable'); }
$uid=(int)$_SESSION['myid'];
$now=time();
$stmt=$db->prepare("UPDATE online SET activity='Reviewing the Battle Arena',time=? WHERE id=?");
if($stmt){$stmt->bind_param('ii',$now,$uid);$stmt->execute();$stmt->close();}
$_SESSION['battle_count']=0;

$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($_POST['challenge_trainer'])){
    pv_require_csrf();
    $mode=(string)($_POST['lookup_mode']??'Username');
    $target=trim((string)($_POST['target']??''));
    $targetId=0;
    if($target==='') $error='Enter a trainer username or numeric trainer ID.';
    else{
        if($mode==='ID'){
            $id=filter_var($target,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if($id!==false){$stmt=$db->prepare('SELECT id FROM members WHERE id=? LIMIT 1');if($stmt){$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if($row)$targetId=(int)$row['id'];}}
        }else{
            $stmt=$db->prepare('SELECT id FROM members WHERE username=? LIMIT 1');if($stmt){$stmt->bind_param('s',$target);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if($row)$targetId=(int)$row['id'];}
        }
        if($targetId===$uid)$error='Choose another trainer to battle.';
        elseif($targetId<=0)$error='That trainer could not be found.';
        else pv_redirect('battle.php?bid='.$targetId);
    }
}

$legacyType=(string)($_GET['type']??'');
$mode=(string)($_GET['mode']??'league');
if($legacyType==='frontier')$mode='facility';
elseif($legacyType==='event')$mode='events';
elseif($legacyType==='gym')$mode='league';
if(!in_array($mode,['league','facility','trainers','events'],true))$mode='league';
$region=preg_replace('/[^a-z]/','',strtolower((string)($_GET['region']??'')))?:'';
$catalog=pv_battle_catalog();$groups=pv_battle_groups();
$storage=pv_battle_catalog_storage_status($db);
$badges=pv_battle_catalog_badges($db,$uid);
$progress=pv_battle_catalog_progress($badges);
$eventCatalog=pv_event_battle_catalog();$eventGroups=pv_event_battle_groups();
if($mode==='events' && $region==='' && isset($_GET['team'])){$legacyEventTeam=preg_replace('/[^a-z]/','',strtolower((string)$_GET['team']))?:'';if(isset($eventGroups[$legacyEventTeam]))$region=$legacyEventTeam;}
$eventStorage=pv_event_battle_storage_status($db);$eventFlags=pv_event_battle_flags($db,$uid);$eventProgress=pv_event_battle_progress($eventFlags);
$eventBadgeCount=pv_event_battle_completed_badge_count($eventFlags);

function pv_battle_url(string $route): string { return pv_url('battle.php?'.http_build_query(['gymleader'=>$route],'','&',PHP_QUERY_RFC3986)); }
function pv_battle_group_progress(array $badges,array $ids): array { return pv_battle_catalog_progress($badges,$ids); }
function pv_battle_card(array $trainer,array $badges,bool $enabled): void {
    $id=(int)$trainer['id'];$complete=(int)($badges['g'.$id]??0)===1;
    $href=$enabled?pv_battle_url((string)$trainer['route']):'#';
    echo '<article class="pv-arena-opponent'.($complete?' is-complete':'').(!$enabled?' is-disabled':'').'">';
    echo '<div class="pv-arena-opponent-head"><span>#'.str_pad((string)$id,2,'0',STR_PAD_LEFT).' // '.pv_h(strtoupper((string)$trainer['division'])).'</span><b>'.($complete?'COMPLETE':'READY').'</b></div>';
    echo '<div class="pv-arena-opponent-main"><div class="pv-arena-trainer"><img src="'.pv_h(pv_static('images/sprites/trainers/'.(string)$trainer['sprite'])).'" alt="'.pv_h((string)$trainer['display']).'"></div><div><small>'.pv_h((string)$trainer['venue']).'</small><h3>'.pv_h((string)$trainer['display']).'</h3><p>Lv. '.(int)$trainer['level'].' team · '.count((array)$trainer['roster']).' Pokémon</p></div></div>';
    echo '<div class="pv-arena-team" aria-label="Opponent team">';foreach((array)$trainer['roster'] as $name)echo '<span title="'.pv_h((string)$name).'"><img src="'.pv_h(pv_static('images/pokemon/'.rawurlencode((string)$name).'.gif')).'" alt="'.pv_h((string)$name).'"></span>';echo '</div>';
    echo '<div class="pv-arena-reward"><span>';if((string)$trainer['badge']!=='')echo '<img src="'.pv_h(pv_static('images/badges/'.rawurlencode((string)$trainer['badge']))).'" alt="">';echo '<em>'.pv_h((string)$trainer['reward']).'</em></span>';
    if($enabled)echo '<a class="pv-button" href="'.pv_h($href).'">'.($complete?'Rebattle':'Battle').' ›</a>';else echo '<span class="pv-arena-locked">TEMPORARILY UNAVAILABLE</span>';echo '</div></article>';
}

function pv_event_battle_url(string $route): string { return pv_url('battle.php?'.http_build_query(['eventtrainer'=>$route],'','&',PHP_QUERY_RFC3986)); }
function pv_event_level_label(array $levels): string {
    $clean=array_values(array_unique(array_map('intval',$levels)));
    if($clean===[]) return 'Lv. ?';
    if(count($clean)===1) return 'Lv. '.$clean[0];
    return 'Lv. '.min($clean).'–'.max($clean);
}
function pv_event_battle_card(array $trainer,array $flags,bool $enabled,array $group): void {
    $id=(int)$trainer['id'];$complete=(int)($flags['g'.$id]??0)===1;$href=$enabled?pv_event_battle_url((string)$trainer['route']):'#';
    echo '<article class="pv-arena-opponent pv-event-opponent'.($complete?' is-complete':'').(!$enabled?' is-disabled':'').'">';
    echo '<div class="pv-arena-opponent-head"><span>#'.str_pad((string)$id,2,'0',STR_PAD_LEFT).' // '.pv_h(strtoupper((string)$trainer['division'])).'</span><b>'.($complete?'COMPLETE':'READY').'</b></div>';
    echo '<div class="pv-arena-opponent-main"><div class="pv-arena-trainer"><img src="'.pv_h(pv_static((string)$trainer['sprite'])).'" alt="'.pv_h((string)$trainer['display']).'"></div><div><small>'.pv_h((string)$group['label']).' SPECIAL BATTLE</small><h3>'.pv_h((string)$trainer['display']).'</h3><p>'.pv_h(pv_event_level_label((array)$trainer['levels'])).' team · '.count((array)$trainer['roster']).' Pokémon</p></div></div>';
    echo '<div class="pv-arena-team" aria-label="Opponent team">';foreach((array)$trainer['roster'] as $name)echo '<span title="'.pv_h((string)$name).'"><img src="'.pv_h(pv_static('images/pokemon/'.rawurlencode((string)$name).'.gif')).'" alt="'.pv_h((string)$name).'"></span>';echo '</div>';
    echo '<div class="pv-arena-reward"><span><img src="'.pv_h(pv_static('images/specialbadges/'.rawurlencode((string)$group['badge']))).'" alt=""><em>'.pv_h((string)$group['badge_label']).' progress</em></span>';
    if($enabled)echo '<a class="pv-button" href="'.pv_h($href).'">'.($complete?'Rebattle':'Battle').' ›</a>';else echo '<span class="pv-arena-locked">TEMPORARILY UNAVAILABLE</span>';echo '</div></article>';
}

pv_page_start('Battle Arena','battle_select.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('battle_select.php'); ?>
<main class="pv-main-column">
<section class="pv-page pv-arena-page">
    <?php if($mode==='events'):?>
    <div class="pv-section-hero pv-arena-hero pv-event-hero">
        <div><span class="pv-eyebrow">BATTLE ARENA // SPECIAL EVENTS</span><h1>Special Events</h1><p class="pv-subtle">Challenge villain organizations and Vortex Staff in special battle ladders, earn event badges and track your victories.</p></div>
        <div class="pv-arena-progress-orb"><strong><?= (int)$eventProgress['done'] ?></strong><span>/ 32</span><small>BATTLES WON</small></div>
    </div>
    <?php else:?>
    <div class="pv-section-hero pv-arena-hero">
        <div><span class="pv-eyebrow">BATTLE ARENA // LEAGUES & FACILITIES</span><h1>Battle Arena</h1><p class="pv-subtle">Challenge Gym Leaders, Elite Four members, Champions and advanced battle facilities from across the Pokémon world.</p></div>
        <div class="pv-arena-progress-orb"><strong><?= (int)$progress['done'] ?></strong><span>/ 95</span><small>BATTLES WON</small></div>
    </div>
    <?php endif;?>

    <?php if(!empty($_GET['invalid_battle'])):?><div class="pv-flash error">That battle is no longer available. Choose an opponent from the <?= $mode==='events'?'Special Event':'Battle Arena' ?> list.</div><?php endif;?>
    <?php if($error!==''):?><div class="pv-flash error"><?=pv_h($error)?></div><?php endif;?>
    <?php if($mode==='events' && !$eventStorage['ready']):?><div class="pv-flash warning"><strong>Special Events are temporarily unavailable.</strong> The game host needs to complete the latest maintenance update before these battles can be used.</div><?php elseif($mode!=='events' && !$storage['ready']):?><div class="pv-flash warning"><strong>Battle Arena is temporarily unavailable.</strong> The game host needs to complete the latest maintenance update before these battles can be used.</div><?php endif;?>

    <?php if($mode==='events'):?>
    <div class="pv-arena-summary-grid">
        <div><span>SPECIAL EVENT COMPLETION</span><strong><?= (int)$eventProgress['percent'] ?>%</strong><i><b style="width:<?= (int)$eventProgress['percent'] ?>%"></b></i><small><?= (int)$eventProgress['done'] ?> of 32 event battles complete</small></div>
        <div><span>EVENT OPPONENTS</span><strong><?= (int)$eventStorage['trainers'] ?> / 32</strong><small><?= (int)$eventStorage['pokemon'] ?> / 181 opponent Pokémon ready</small></div>
        <div><span>EVENT BADGES</span><strong><?= (int)$eventBadgeCount ?> / 7</strong><small>Defeat every trainer in an organization to earn its event badge.</small></div>
    </div>
    <?php else:?>
    <div class="pv-arena-summary-grid">
        <div><span>OVERALL COMPLETION</span><strong><?= (int)$progress['percent'] ?>%</strong><i><b style="width:<?= (int)$progress['percent'] ?>%"></b></i><small><?= (int)$progress['done'] ?> of 95 unique progression battles complete</small></div>
        <div><span>ARENA OPPONENTS</span><strong><?= (int)$storage['trainers'] ?> / 95</strong><small><?= (int)$storage['pokemon'] ?> / 402 opponent Pokémon ready</small></div>
        <div><span>LEGENDARY MAP ACCESS</span><strong><?=pv_battle_catalog_all_completed($badges)?'UNLOCKED':'LOCKED'?></strong><small>Complete all 95 Battle Arena challenges to unlock Legendary Map access.</small></div>
    </div>
    <?php endif;?>

    <nav class="pv-arena-tabs" aria-label="Battle modes">
        <a class="<?=$mode==='league'?'active':''?>" href="<?=pv_h(pv_url('battle_select.php?mode=league'))?>">Leagues</a>
        <a class="<?=$mode==='facility'?'active':''?>" href="<?=pv_h(pv_url('battle_select.php?mode=facility'))?>">Battle Facilities</a>
        <a class="<?=$mode==='trainers'?'active':''?>" href="<?=pv_h(pv_url('battle_select.php?mode=trainers'))?>">Trainer Battles</a>
        <a class="<?=$mode==='events'?'active':''?>" href="<?=pv_h(pv_url('battle_select.php?mode=events'))?>">Special Events</a>
    </nav>

    <?php if($mode==='league' || $mode==='facility'):
        $set=$groups[$mode]??[];
        if($region!=='' && isset($set[$region]))$set=[$region=>$set[$region]];
    ?>
        <div class="pv-arena-region-switch">
            <?php foreach(($groups[$mode]??[]) as $key=>$g):$gp=pv_battle_group_progress($badges,(array)$g['ids']);?>
            <a class="<?=$region===$key?'active':''?>" href="<?=pv_h(pv_url('battle_select.php?mode='.$mode.'&region='.$key))?>"><span><?=pv_h((string)$g['label'])?></span><b><?= (int)$gp['done'] ?>/<?= (int)$gp['total'] ?></b></a>
            <?php endforeach;?>
            <?php if($region!==''):?><a href="<?=pv_h(pv_url('battle_select.php?mode='.$mode))?>"><span>Show All</span><b>×</b></a><?php endif;?>
        </div>
        <?php foreach($set as $key=>$g):$gp=pv_battle_group_progress($badges,(array)$g['ids']);?>
        <section class="pv-arena-region">
            <div class="pv-world-section-head"><div><span><?=pv_h(strtoupper($mode))?> GROUP</span><h2><?=pv_h((string)$g['label'])?></h2><p><?=pv_h((string)$g['subtitle'])?></p></div><strong><?= (int)$gp['done'] ?> / <?= (int)$gp['total'] ?> COMPLETE</strong></div>
            <div class="pv-arena-opponent-grid">
            <?php foreach((array)$g['ids'] as $id):$tr=$catalog[(int)$id];$tr['id']=(int)$id;pv_battle_card($tr,$badges,(bool)$storage['ready']);endforeach;?>
            </div>
        </section>
        <?php endforeach;?>
    <?php elseif($mode==='trainers'):?>
        <section class="pv-arena-network-grid">
            <div class="pv-arena-network-card"><span class="pv-eyebrow">DIRECT TRAINER BATTLE</span><h2>Challenge a trainer</h2><p>Battle another registered trainer's current team in an AI-controlled match. Search by username or Trainer ID.</p><form method="post" class="pv-arena-challenge-form"><?=pv_csrf_field()?><input type="hidden" name="challenge_trainer" value="1"><label>Lookup method<select name="lookup_mode"><option>Username</option><option value="ID">Trainer ID</option></select></label><label>Trainer<input type="text" name="target" maxlength="45" autocomplete="off" placeholder="Username or ID" required></label><button class="pv-button" type="submit">Challenge Trainer ›</button></form></div>
            <div class="pv-arena-network-card"><span class="pv-eyebrow">LIVE MULTIPLAYER</span><h2>Live Battle Arena</h2><p>For real-time battles against another player, enter the Live PvP lobby and send or accept a challenge.</p><a class="pv-button" href="<?=pv_h(pv_url('live_battle_arena.php'))?>">Open Live PvP ›</a></div>
        </section>
    <?php else:
        $set=$eventGroups;
        if($region!=='' && isset($set[$region]))$set=[$region=>$set[$region]];
    ?>
        <div class="pv-arena-region-switch pv-event-switch">
            <?php foreach($eventGroups as $key=>$g):$gp=pv_event_battle_progress($eventFlags,(array)$g['ids']);?>
            <a class="<?=$region===$key?'active':''?>" href="<?=pv_h(pv_url('battle_select.php?mode=events&region='.$key))?>"><span><?=pv_h((string)$g['label'])?></span><b><?= (int)$gp['done'] ?>/<?= (int)$gp['total'] ?></b></a>
            <?php endforeach;?>
            <?php if($region!==''):?><a href="<?=pv_h(pv_url('battle_select.php?mode=events'))?>"><span>Show All</span><b>×</b></a><?php endif;?>
        </div>
        <?php foreach($set as $key=>$g):$gp=pv_event_battle_progress($eventFlags,(array)$g['ids']);$groupComplete=pv_event_battle_group_complete($eventFlags,(string)$key);?>
        <section class="pv-arena-region pv-event-region <?=$groupComplete?'is-complete':''?>">
            <div class="pv-event-region-head">
                <div class="pv-event-banner"><img src="<?=pv_h(pv_static('images/gyms/'.(string)$g['banner']))?>" alt="<?=pv_h((string)$g['label'])?>"></div>
                <div class="pv-event-region-copy"><span>EVENT GROUP</span><h2><?=pv_h((string)$g['label'])?></h2><p><?=pv_h((string)$g['subtitle'])?></p></div>
                <div class="pv-event-badge-state"><img src="<?=pv_h(pv_static('images/specialbadges/'.(string)$g['badge']))?>" alt=""><strong><?=$groupComplete?'BADGE SECURED':((int)$gp['done'].' / '.(int)$gp['total'].' COMPLETE')?></strong><small><?=pv_h((string)$g['badge_label'])?></small></div>
            </div>
            <div class="pv-arena-opponent-grid">
            <?php foreach((array)$g['ids'] as $id):$tr=$eventCatalog[(int)$id];$tr['id']=(int)$id;pv_event_battle_card($tr,$eventFlags,(bool)$eventStorage['ready'],$g);endforeach;?>
            </div>
        </section>
        <?php endforeach;?>
    <?php endif;?>
</section>
</main>
</div>
<?php pv_page_end(); ?>
