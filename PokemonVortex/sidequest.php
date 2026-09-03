<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/sidequest_catalog.php';
pv_require_login();

try { $db = pv_db(); }
catch (Throwable $e) { pv_log('Sidequest DB unavailable: '.$e->getMessage()); pv_redirect('dashboard.php?service=unavailable'); }

$uid=(int)$_SESSION['myid'];

// Sidequests begin at progression position 1. Initialize only never-started rows;
// never rewind a trainer who has already advanced through the recovered chain.
$stmt=$db->prepare('UPDATE members SET sidequest=1 WHERE id=? AND (sidequest IS NULL OR sidequest<1)');
if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();}

function pv_sidequest_read_progress(mysqli $db,int $uid): int {
    $stmt=$db->prepare('SELECT sidequest FROM members WHERE id=? LIMIT 1');
    if(!$stmt) return 1;
    $stmt->bind_param('i',$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
    return min(697,max(1,(int)($row['sidequest']??1)));
}

/**
 * Claim one source-generation regional milestone exactly once.
 * The member row and inventory row are locked together so refreshes,
 * duplicate submits and concurrent tabs cannot duplicate money or items.
 */
function pv_claim_sidequest_milestone(mysqli $db,int $uid,int $expectedProgress): ?array {
    $milestones=pv_sidequest_reward_milestones();
    if(!isset($milestones[$expectedProgress]))return null;
    $config=$milestones[$expectedProgress];$pool=(array)$config['pool'];
    if($pool===[])return null;
    $allowedColumns=['Helix_Fossil','Dome_Fossil','Old_Amber','Master_Ball','Latiasite','Latiosite','Blue_Orb','Red_Orb','Root_Fossil','Claw_Fossil'];

    $db->begin_transaction();
    try{
        $stmt=$db->prepare('SELECT sidequest FROM members WHERE id=? FOR UPDATE');
        if(!$stmt)throw new RuntimeException('Could not lock Sidequest progression.');
        $stmt->bind_param('i',$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
        if(!$row||(int)$row['sidequest']!==$expectedProgress){$db->rollback();return null;}

        $stmt=$db->prepare('SELECT id FROM items WHERE uid=? ORDER BY id ASC LIMIT 1 FOR UPDATE');
        if(!$stmt)throw new RuntimeException('Could not lock trainer inventory.');
        $stmt->bind_param('i',$uid);$stmt->execute();$item=$stmt->get_result()->fetch_assoc();$stmt->close();
        if(!$item){
            $stmt=$db->prepare('INSERT INTO items (uid) VALUES (?)');
            if(!$stmt)throw new RuntimeException('Could not initialize trainer inventory.');
            $stmt->bind_param('i',$uid);if(!$stmt->execute())throw new RuntimeException('Could not initialize trainer inventory.');
            $itemId=(int)$db->insert_id;$stmt->close();
        }else{$itemId=(int)$item['id'];}

        $reward=$pool[random_int(0,count($pool)-1)];
        $column=(string)($reward['column']??'');$quantity=max(1,(int)($reward['quantity']??1));
        if(!in_array($column,$allowedColumns,true))throw new RuntimeException('Invalid Sidequest reward configuration.');
        $money=random_int(20000,30000);$next=(int)$config['next'];

        $stmt=$db->prepare('UPDATE members SET sidequest=?,money=money+? WHERE id=? AND sidequest=?');
        if(!$stmt)throw new RuntimeException('Could not prepare Sidequest milestone settlement.');
        $stmt->bind_param('iiii',$next,$money,$uid,$expectedProgress);
        if(!$stmt->execute()||$stmt->affected_rows!==1){$stmt->close();throw new RuntimeException('Sidequest progression changed before settlement.');}$stmt->close();

        $sql='UPDATE items SET `'.$column.'`=`'.$column.'`+? WHERE id=? AND uid=?';
        $stmt=$db->prepare($sql);if(!$stmt)throw new RuntimeException('Could not prepare Sidequest item delivery.');
        $stmt->bind_param('iii',$quantity,$itemId,$uid);
        if(!$stmt->execute()||$stmt->affected_rows!==1){$stmt->close();throw new RuntimeException('Could not deliver Sidequest reward.');}$stmt->close();

        $db->commit();$_SESSION['sidequest']=$next;
        return ['region'=>(string)$config['region'],'prize'=>(string)$reward['label'],'money'=>$money,'next'=>$next];
    }catch(Throwable $e){
        $db->rollback();pv_log('Sidequest reward failed for trainer '.$uid.' at milestone '.$expectedProgress.': '.$e->getMessage());return null;
    }
}

$storage=pv_sidequest_storage_status($db);
$rewardNotice=null;$claimError='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($_POST['claim_milestone'])){
    pv_require_csrf();$expected=max(0,(int)($_POST['milestone']??0));
    if(!$storage['ready'])$claimError='Sidequest rewards are temporarily unavailable. Please try again after game maintenance.';
    else{
        $rewardNotice=pv_claim_sidequest_milestone($db,$uid,$expected);
        if(!$rewardNotice)$claimError='That regional milestone is not currently available or was already claimed.';
    }
}

$rawProgress=pv_sidequest_read_progress($db,$uid);$_SESSION['sidequest']=$rawProgress;
$progress=pv_sidequest_progress_snapshot($rawProgress);$current=$progress['active'];$catalog=pv_sidequest_catalog();
$region=$progress['region'];$milestone=$progress['milestone'];
$activityRegion=is_array($region)?(string)$region['label']:'Sidequest';
$now=time();$activity='Exploring '.$activityRegion.' Sidequests';
$stmt=$db->prepare('UPDATE online SET activity=?,time=? WHERE id=?');
if($stmt){$stmt->bind_param('sii',$activity,$now,$uid);$stmt->execute();$stmt->close();}

function pv_sidequest_level_label(array $levels): string {
    $levels=array_values(array_unique(array_map('intval',$levels)));if($levels===[])return 'Lv. ?';
    return count($levels)===1?'Lv. '.$levels[0]:'Lv. '.min($levels).'–'.max($levels);
}
function pv_sidequest_battle_url(int $id): string { return pv_url('battle.php?'.http_build_query(['sidequest'=>$id],'','&',PHP_QUERY_RFC3986)); }
function pv_sidequest_team_html(array $trainer): string {
    $html='<div class="pv-sidequest-team" aria-label="Opponent team">';
    foreach((array)$trainer['roster'] as $i=>$name){$level=(int)($trainer['levels'][$i]??100);$n=(string)$name;
        $html.='<span title="'.pv_h($n).' · Lv. '.$level.'"><img src="'.pv_h(pv_static('images/pokemon/'.rawurlencode($n).'.gif')).'" alt="'.pv_h($n).'"><small>Lv. '.$level.'</small></span>';
    }
    return $html.'</div>';
}
function pv_sidequest_region_map(?array $region,?array $trainer): string {
    if($trainer&&strcasecmp((string)$trainer['place'],'Birth Island')===0)return 'birthisland.png';
    if($trainer&&strcasecmp((string)$trainer['place'],'Navel Rock')===0)return 'navelrock.png';
    return (string)($region['map']??'sidemap.png');
}
function pv_sidequest_upcoming_steps(int $progress,int $limit=4): array {
    $steps=[];$milestones=pv_sidequest_reward_milestones();
    for($p=$progress;$p<=696&&count($steps)<$limit;$p++){
        if(isset($milestones[$p])){$steps[]=['type'=>'reward','id'=>$p,'label'=>$milestones[$p]['region'].' Reward','place'=>'Regional milestone'];continue;}
        $battle=pv_sidequest_by_id($p);if($battle)$steps[]=['type'=>'battle','id'=>$p,'label'=>$battle['trainer'],'place'=>$battle['place'],'team'=>count((array)$battle['roster'])];
    }
    return $steps;
}

pv_page_start('Sidequests','sidequest.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('sidequest.php'); ?>
<main class="pv-main-column">
<section class="pv-page pv-sidequest-page-modern">
    <div class="pv-section-hero pv-sidequest-hero">
        <div>
            <span class="pv-eyebrow">SIDEQUESTS // CAMPAIGN</span>
            <h1>Sidequests</h1>
            <p class="pv-subtle">Travel through Kanto, Johto, the Sevii Islands, Legendary Isles, TCG Island, Orange Islands and Hoenn as you clear a long chain of trainer battles and regional rewards.</p>
        </div>
        <div class="pv-sidequest-progress-orb"><strong><?= (int)$progress['done'] ?></strong><span>/ 690</span><small>TOTAL BATTLES</small></div>
    </div>

    <?php if(!empty($_GET['invalid_battle'])):?><div class="pv-flash error">That is not your current Sidequest battle. Continue from the assignment shown below.</div><?php endif;?>
    <?php if(!empty($_GET['schema'])):?><div class="pv-flash warning"><strong>Sidequests are temporarily unavailable.</strong> The game host needs to complete the latest maintenance update.</div><?php endif;?>
    <?php if(!$storage['ready']):?><div class="pv-flash warning"><strong>Sidequests are temporarily unavailable.</strong> The game host needs to complete the latest maintenance update before the campaign can continue.</div><?php endif;?>
    <?php if($rewardNotice):?><div class="pv-flash success"><strong><?=pv_h((string)$rewardNotice['region'])?> milestone secured.</strong> You received <?=pv_h((string)$rewardNotice['prize'])?> and <img class="pv-inline-money" src="<?=pv_h(pv_static('images/misc/pmoney.gif'))?>" alt=""> <?=number_format((int)$rewardNotice['money'])?>. The next Sidequest step is now unlocked.</div><?php endif;?>
    <?php if($claimError!==''):?><div class="pv-flash error"><?=pv_h($claimError)?></div><?php endif;?>

    <div class="pv-sidequest-summary-grid">
        <div><span>CAMPAIGN COMPLETION</span><strong><?= (int)$progress['percent'] ?>%</strong><i><b style="width:<?= (int)$progress['percent'] ?>%"></b></i><small><?= (int)$progress['done'] ?> of 690 battles complete</small></div>
        <div><span>SIDEQUEST OPPONENTS</span><strong><?= (int)$storage['trainers'] ?> / <?= (int)$storage['expected_trainers'] ?></strong><small>Sidequest trainers available across the campaign</small></div>
        <div><span>EXPEDITION STATUS</span><strong><?=$progress['complete']?'COMPLETE':($progress['claim_ready']?'REWARD READY':'ACTIVE')?></strong><small><?=$progress['complete']?'Every available Sidequest is complete.':('Step '.(int)$rawProgress.' of 697')?></small></div>
    </div>

    <section class="pv-sidequest-roadmap" aria-label="Sidequest campaigns">
    <?php foreach($progress['regions'] as $key=>$r):$isActive=is_array($region)&&($region['key']??'')===$key;$status=$r['complete']?'COMPLETE':($isActive?'ACTIVE':'LOCKED'); ?>
        <div class="<?=$isActive?'is-active':($r['complete']?'is-complete':'')?>"><b><?=pv_h(strtoupper((string)$r['label']))?></b><span><?= (int)$r['total'] ?> battles<?= $r['reward']!==null?' + reward':'' ?></span><em><?=$status?> · <?= (int)$r['done'] ?>/<?= (int)$r['total'] ?></em></div>
    <?php endforeach;?>
    </section>

    <?php if($current):$mapFile=pv_sidequest_region_map($region,$current);?>
    <section class="pv-sidequest-current">
        <div class="pv-sidequest-map-card">
            <div class="pv-world-section-head"><div><span>CURRENT EXPEDITION</span><h2><?=pv_h((string)($region['label']??'Sidequest'))?> Assignment #<?=str_pad((string)$current['id'],3,'0',STR_PAD_LEFT)?></h2></div><strong><?=pv_h((string)$current['place'])?></strong></div>
            <div class="pv-sidequest-map-frame"><img src="<?=pv_h(pv_static('images/sidemaps/'.rawurlencode($mapFile)))?>" alt="<?=pv_h((string)($region['label']??'Sidequest'))?> Sidequest map"></div>
            <p>Follow the current assignment shown here. Winning the battle advances the campaign, while regional milestones unlock their reward before the next journey begins.</p>
        </div>
        <article class="pv-sidequest-target">
            <span class="pv-eyebrow">CURRENT OPPONENT // <?=pv_h(strtoupper((string)$current['place']))?></span>
            <div class="pv-sidequest-target-head"><div><small>BATTLE #<?=str_pad((string)$current['id'],3,'0',STR_PAD_LEFT)?></small><h2><?=pv_h((string)$current['trainer'])?></h2><p><?=pv_h(pv_sidequest_level_label((array)$current['levels']))?> team · <?=count((array)$current['roster'])?> Pokémon</p></div><b>READY</b></div>
            <?=pv_sidequest_team_html($current)?>
            <div class="pv-sidequest-target-footer"><div><span>Location</span><strong><?=pv_h((string)$current['place'])?></strong><small>Sidequest battle</small></div><?php if($storage['ready']):?><a class="pv-button" href="<?=pv_h(pv_sidequest_battle_url((int)$current['id']))?>">Battle Assignment ›</a><?php else:?><span class="pv-arena-locked">TEMPORARILY UNAVAILABLE</span><?php endif;?></div>
        </article>
    </section>

    <section class="pv-sidequest-sequence">
        <div class="pv-world-section-head"><div><span>EXPEDITION QUEUE</span><h2>Upcoming steps</h2><p>Reward milestones are shown explicitly and cannot be entered as battles.</p></div><strong><?=690-(int)$progress['done']?> BATTLES REMAIN</strong></div>
        <div class="pv-sidequest-sequence-grid">
        <?php foreach(pv_sidequest_upcoming_steps((int)$rawProgress,4) as $i=>$step):?>
            <div class="<?=$i===0?'is-current':''?>"><span><?=$step['type']==='reward'?'MILESTONE':'#'.str_pad((string)$step['id'],3,'0',STR_PAD_LEFT)?></span><b><?=pv_h((string)$step['label'])?></b><small><?=pv_h((string)$step['place'])?><?=$step['type']==='battle'?' · '.(int)$step['team'].' Pokémon':''?></small><em><?=$i===0?'CURRENT':'LOCKED'?></em></div>
        <?php endforeach;?>
        </div>
    </section>

    <?php elseif($progress['claim_ready']&&is_array($milestone)):?>
    <section class="pv-sidequest-reward-panel">
        <div><span class="pv-eyebrow"><?=pv_h(strtoupper((string)$milestone['region']))?> MILESTONE // POSITION <?= (int)$rawProgress ?></span><h2>Regional expedition complete</h2><p>Claim one random regional reward plus ₽20,000–30,000. After you claim it, the next Sidequest region unlocks.</p></div>
        <div class="pv-sidequest-rewards">
        <?php foreach((array)$milestone['pool'] as $reward):?>
            <span><img src="<?=pv_h(pv_static('images/items/'.rawurlencode((string)$reward['image'])))?>" alt=""><b><?=pv_h((string)$reward['label'])?></b><small><?=number_format(100/max(1,count((array)$milestone['pool'])),1)?>% chance</small></span>
        <?php endforeach;?>
        </div>
        <form method="post"><?=pv_csrf_field()?><input type="hidden" name="claim_milestone" value="1"><input type="hidden" name="milestone" value="<?= (int)$rawProgress ?>"><button class="pv-button" type="submit">Claim <?=pv_h((string)$milestone['region'])?> Reward ›</button></form>
    </section>

    <?php elseif($progress['complete']):?>
    <section class="pv-sidequest-handoff">
        <span class="pv-eyebrow">SIDEQUEST JOURNEY // COMPLETE</span><h2>All Sidequests complete</h2>
        <p>You have cleared all 690 Sidequest battles and claimed every regional milestone reward through Hoenn. Congratulations on completing the full journey.</p>
        <div class="pv-sidequest-handoff-status"><b>KANTO → JOHTO → SEVII → LEGENDARY ISLES → TCG → ORANGE → HOENN</b><span>COMPLETE</span></div>
    </section>
    <?php else:?>
    <section class="pv-sidequest-handoff"><span class="pv-eyebrow">EXPEDITION STATUS</span><h2>Sidequest progress unavailable</h2><p>Your current Sidequest step could not be loaded. Please contact the game host before continuing the campaign.</p></section>
    <?php endif;?>

    <section class="pv-sidequest-source-note">
        <strong>Campaign rules</strong>
        <p>Sidequests advance one step at a time. Complete the current trainer battle, claim each regional milestone reward when it appears, and continue until the journey through Hoenn is complete.</p>
    </section>
</section>
</main>
</div>
<?php pv_page_end(); ?>
