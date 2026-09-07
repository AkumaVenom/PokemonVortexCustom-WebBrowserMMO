<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/rival_ui.php';
require_once __DIR__ . '/includes/bot_runtime.php';
pv_require_login();

$db = pv_db();
$uid = max(1, (int)($_SESSION['myid'] ?? 0));
$now = time();
$rankedResultSaved = pv_rival_retry_pending_result($db, $uid);
$ready = pv_rival_ready($db);
$flash = $_SESSION['pv_rival_flash'] ?? null;
unset($_SESSION['pv_rival_flash']);

$state = null; $rank = 0; $tier = pv_rival_tier(PV_RIVAL_START_RATING);
$retaliations = []; $recent = []; $rivals = []; $elite = []; $active = [];
$totalTrainers = 0; $activeToday = 0; $openRetaliations = 0;

if ($ready) {
    pv_rival_ensure_all_states($db);
    pv_rival_housekeeping($db);
    // A modest server-side tick keeps the autonomous field moving when trainers
    // visit the competitive pages, without replacing the existing world tick.
    pv_bot_tick($db, 18, '', '', 35);
    $now = time();
    $state = pv_rival_state($db, $uid);
    if ($state) {
        $rank = pv_rival_rank_position($db, $uid, (int)$state['rating'], (int)$state['ranked_wins'], (int)$state['ranked_losses']);
        $tier = pv_rival_tier((int)$state['rating']);
    }

    $stats = $db->query('SELECT COUNT(*) total, SUM(last_ranked_at>='.(int)($now-86400).') active_today FROM trainer_rank_state');
    if ($stats) { $s=$stats->fetch_assoc()?:[]; $totalTrainers=(int)($s['total']??0); $activeToday=(int)($s['active_today']??0); $stats->free(); }

    $stmt = $db->prepare("SELECT rr.id retaliation_id,rr.battle_id,rr.attacker_id user_id,rr.created_at,rr.expires_at,m.username,r.rating,r.ranked_wins,r.ranked_losses,r.current_streak,r.shield_until,r.last_ranked_at,COALESCE(b.bot_index,0) bot_index,COALESCE(b.trainer_sprite,0) trainer_sprite,COALESCE(o.trainer,1) trainer,p.name lead_name,CASE WHEN b.user_id IS NULL THEN 0 ELSE 1 END is_bot FROM rival_retaliations rr JOIN members m ON m.id=rr.attacker_id JOIN trainer_rank_state r ON r.user_id=rr.attacker_id LEFT JOIN bot_trainers b ON b.user_id=rr.attacker_id AND b.enabled=1 LEFT JOIN members_options o ON o.id=rr.attacker_id LEFT JOIN pokemon p ON p.id=m.s1 AND CAST(p.owner AS UNSIGNED)=m.id WHERE rr.defender_id=? AND rr.status='open' AND rr.expires_at>? ORDER BY rr.created_at DESC LIMIT 12");
    if ($stmt) { $stmt->bind_param('ii',$uid,$now); $stmt->execute(); $retaliations=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
    $openRetaliations=count($retaliations);

    $stmt = $db->prepare("SELECT rb.*,a.username attacker_name,d.username defender_name,w.username winner_name,CASE WHEN rb.attacker_id=? THEN d.username ELSE a.username END opponent_name,CASE WHEN rb.attacker_id=? THEN rb.defender_id ELSE rb.attacker_id END opponent_id FROM rival_battles rb JOIN members a ON a.id=rb.attacker_id JOIN members d ON d.id=rb.defender_id JOIN members w ON w.id=rb.winner_id WHERE rb.attacker_id=? OR rb.defender_id=? ORDER BY rb.created_at DESC LIMIT 14");
    if ($stmt) { $stmt->bind_param('iiii',$uid,$uid,$uid,$uid); $stmt->execute(); $recent=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }

    $rating = max(100, (int)($state['rating'] ?? PV_RIVAL_START_RATING));
    $low = max(100, $rating - 190); $high = $rating + 190;
    $baseSelect = "SELECT r.user_id,r.rating,r.ranked_wins,r.ranked_losses,r.current_streak,r.shield_until,r.last_ranked_at,m.username,COALESCE(b.bot_index,0) bot_index,COALESCE(b.trainer_sprite,0) trainer_sprite,COALESCE(o.trainer,1) trainer,p.name lead_name,CASE WHEN b.user_id IS NULL THEN 0 ELSE 1 END is_bot FROM trainer_rank_state r JOIN members m ON m.id=r.user_id LEFT JOIN bot_trainers b ON b.user_id=r.user_id AND b.enabled=1 LEFT JOIN members_options o ON o.id=r.user_id LEFT JOIN pokemon p ON p.id=m.s1 AND CAST(p.owner AS UNSIGNED)=m.id ";

    $stmt=$db->prepare($baseSelect . 'WHERE r.user_id<>? AND r.rating BETWEEN ? AND ? AND r.shield_until<=? AND COALESCE(m.s1,0)>0 ORDER BY ABS(r.rating-?) ASC,r.last_ranked_at DESC LIMIT 12');
    if($stmt){$stmt->bind_param('iiiii',$uid,$low,$high,$now,$rating);$stmt->execute();$rivals=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();}

    $eliteFloor=1100;
    $stmt=$db->prepare($baseSelect . 'WHERE r.user_id<>? AND r.rating>=? AND r.shield_until<=? AND COALESCE(m.s1,0)>0 ORDER BY r.rating DESC,r.last_ranked_at DESC LIMIT 9');
    if($stmt){$stmt->bind_param('iii',$uid,$eliteFloor,$now);$stmt->execute();$elite=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();}

    $since=$now-7200;
    $stmt=$db->prepare($baseSelect . 'WHERE r.user_id<>? AND r.last_ranked_at>=? AND r.shield_until<=? AND COALESCE(m.s1,0)>0 ORDER BY r.last_ranked_at DESC,ABS(r.rating-?) ASC LIMIT 9');
    if($stmt){$stmt->bind_param('iiii',$uid,$since,$now,$rating);$stmt->execute();$active=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();}
}
$incoming=array_slice(array_values(array_filter($recent,static fn(array $battle):bool=>(int)($battle['defender_id']??0)===$uid)),0,4);

pv_page_start('Rival Hub','rival_hub.php',true);
?>
<div class="pv-game-layout"><?php pv_game_side_menu('rival_hub.php'); ?><main class="pv-main-column"><section class="pv-page pv-rival-page">
<?php pv_pokemon_banner('RIVAL NETWORK · RANKED TRAINER BATTLES','Rival Hub','Scout competitive trainers, challenge Elite Rivals, answer retaliation calls and climb the same persistent ladder as the autonomous AI field.',['Lucario','Pikachu','Gengar','Greninja','Charizard']); ?>

<?php if (!$rankedResultSaved): ?>
<div class="pv-flash warning"><strong>Ranked result waiting to save.</strong><span>Your completed battle is retained in this session. Refresh this page to retry; another ranked battle can start once it saves.</span></div>
<?php endif; ?>
<?php if(!$ready): ?>
<div class="pv-flash warning"><strong>The Rival Network is waiting to be activated.</strong><span>Open Setup and run Upgrade once to unlock ranked battles, protection shields, retaliation orders and AI Activity.</span></div>
<?php else: ?>
<?php if(is_array($flash)): ?><div class="pv-flash <?=pv_h((string)($flash['type']??'warning'))?>"><strong>Rival Network</strong><span><?=pv_h((string)($flash['message']??''))?></span></div><?php endif; ?>

<section class="pv-rival-overview">
<article class="pv-rival-identity-card">
  <div class="pv-rival-tier-orbit pv-tier-<?=pv_h((string)$tier['class'])?>"><img src="<?=pv_h(pv_static_file((string)$tier['ball'],'images/items/Poke Ball.png'))?>" alt=""></div>
  <div><span class="pv-eyebrow">YOUR COMPETITIVE PROFILE</span><h2><?=pv_h((string)($_SESSION['myuser']??'Trainer'))?></h2><strong class="pv-rival-big-rating"><?=number_format((int)($state['rating']??PV_RIVAL_START_RATING))?></strong><small><?=pv_h((string)$tier['name'])?> tier · Global Rank #<?=number_format($rank)?></small></div>
  <div class="pv-rival-record"><span><b><?=number_format((int)($state['ranked_wins']??0))?></b> Wins</span><span><b><?=number_format((int)($state['ranked_losses']??0))?></b> Losses</span><span><b><?=number_format((int)($state['best_streak']??0))?></b> Best Streak</span></div>
</article>
<article class="pv-rival-shield-card <?=pv_rival_shield_remaining($state??[],$now)>0?'is-protected':'is-exposed'?>">
  <div class="pv-rival-shield-icon"><img src="<?=pv_h(pv_static_file('images/items/Great Ball.png','images/Pokeball.PNG'))?>" alt=""></div>
  <span class="pv-eyebrow">BATTLE PROTECTION</span>
  <?php $shieldRemaining=pv_rival_shield_remaining($state??[],$now); if($shieldRemaining>0): ?>
  <h3>Shield Active</h3><strong data-pv-countdown="<?=(int)($state['shield_until']??0)?>"><?=pv_h(pv_rival_format_duration($shieldRemaining))?></strong><p>You were recently attacked. Your shield blocks normal Rival Hub challenges until it expires. Starting any ranked battle or retaliation drops your shield immediately.</p>
  <?php else: ?><h3>Open to Challenges</h3><strong>READY</strong><p>Your trainer can currently be selected as a ranked target. If another trainer attacks you, you receive a temporary protection shield and a one-use retaliation order.</p><?php endif; ?>
</article>
<article class="pv-rival-network-card"><span class="pv-eyebrow">LIVE LADDER</span><div class="pv-rival-network-numbers"><strong><?=number_format($totalTrainers)?></strong><span>ranked trainers</span><strong><?=number_format($activeToday)?></strong><span>active today</span><strong><?=number_format($openRetaliations)?></strong><span>retaliations ready</span></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('rankings.php'))?>">View Trainer Rankings</a></article>
</section>

<?php if($incoming): ?>
<section class="pv-incoming-panel"><div class="pv-rival-section-head"><div><span class="pv-eyebrow">RECENT INCOMING CHALLENGES</span><h2>Rivals Who Came For Your Rank</h2><p>Recent attacks against your trainer. Completed attacks trigger protection and create a retaliation opportunity while the order remains open.</p></div><span class="pv-rival-section-count"><?=count($incoming)?> RECENT</span></div><div class="pv-incoming-grid"><?php foreach($incoming as $battle):$defended=(int)$battle['winner_id']===$uid;?><article class="<?=$defended?'is-held':'is-breached'?>"><span class="signal"><img src="<?=pv_h(pv_static_file($defended?'images/items/Great Ball.png':'images/items/Ultra Ball.png','images/items/Poke Ball.png'))?>" alt=""></span><div><small><?=pv_h(strtoupper((string)$battle['source']))?> · <?=pv_h(pv_rival_time_ago((int)$battle['created_at'],$now))?></small><strong><?=pv_h((string)$battle['attacker_name'])?></strong><span><?=$defended?'You defended your position':'Your rival won the attack'?> · <?=number_format((int)$battle['rating_delta'])?> RP swing</span></div><b><?=$defended?'DEFENDED':'BREACHED'?></b></article><?php endforeach;?></div></section>
<?php endif; ?>

<?php if($retaliations): ?>
<section class="pv-rival-section pv-retaliation-section"><div class="pv-rival-section-head"><div><span class="pv-eyebrow">RETALIATION ORDERS</span><h2>Battle Back</h2><p>These trainers attacked you. Retaliation ignores their current protection shield, but using it exposes your own trainer to new challenges.</p></div><span class="pv-rival-section-count"><?=count($retaliations)?> READY</span></div>
<div class="pv-retaliation-grid">
<?php foreach($retaliations as $r): $rtier=pv_rival_tier((int)$r['rating']); $trainer=pv_rival_trainer_sprite($r); ?>
<article class="pv-retaliation-card"><div class="pv-retaliation-warning"></div><span class="pv-rival-tier pv-tier-<?=pv_h((string)$rtier['class'])?>"><img src="<?=pv_h(pv_rival_ball_url((int)$r['rating']))?>" alt=""><?=pv_h((string)$rtier['short'])?></span><div class="pv-retaliation-opponent"><img class="trainer" src="<?=pv_h(pv_static_file('images/sprites/'.$trainer.'whole.gif','images/sprites/1whole.gif'))?>" alt=""><img class="pokemon" src="<?=pv_h(pv_rival_lead_url($r))?>" alt=""><div><strong><?=pv_h((string)$r['username'])?></strong><span><?=number_format((int)$r['rating'])?> rating</span><small>Order expires in <b data-pv-countdown="<?=(int)$r['expires_at']?>"><?=pv_h(pv_rival_format_duration(max(0,(int)$r['expires_at']-$now)))?></b></small></div></div><form method="post" action="<?=pv_h(pv_url('rival_action.php'))?>"><?=pv_csrf_field()?><input type="hidden" name="rival_action_token" value="<?=pv_h(pv_action_token('rival_action.php'))?>"><input type="hidden" name="action" value="retaliate"><input type="hidden" name="target_id" value="<?=(int)$r['user_id']?>"><input type="hidden" name="retaliation_id" value="<?=(int)$r['retaliation_id']?>"><button class="pv-button pv-retaliate-button" type="submit"><img src="<?=pv_h(pv_static_file('images/items/Ultra Ball.png','images/Pokeball.PNG'))?>" alt="">Retaliate Now</button></form></article>
<?php endforeach; ?>
</div></section>
<?php endif; ?>

<section class="pv-rival-section"><div class="pv-rival-section-head"><div><span class="pv-eyebrow">MATCHMAKING SCANNER</span><h2>Recommended Rivals</h2><p>Close-rating trainers are the most efficient path up the ladder. Every challenge launches through the existing animated Trainer Battle engine.</p></div><span class="pv-rival-scanner"><i></i> SCANNING</span></div>
<?php if($rivals): ?><div class="pv-rival-target-grid"><?php foreach($rivals as $row) pv_rival_target_card($row,$now,'rival'); ?></div><?php else:?><div class="pv-empty-state"><strong>No open rivals in your rating band.</strong><span>Protected trainers will return to the target pool as their short shields expire. Elite and active competitors may still be available below.</span></div><?php endif; ?></section>

<section class="pv-rival-two-column">
<div class="pv-rival-section"><div class="pv-rival-section-head"><div><span class="pv-eyebrow">HIGH-RATING TARGETS</span><h2>Elite Rivals</h2><p>Stronger trainers offer the biggest statement wins, but their teams and ratings make the climb riskier.</p></div></div><?php if($elite): ?><div class="pv-rival-target-grid compact"><?php foreach($elite as $row) pv_rival_target_card($row,$now,'elite'); ?></div><?php else:?><div class="pv-empty-state"><strong>Elite field is protected.</strong><span>Check again as shields cycle.</span></div><?php endif;?></div>
<div class="pv-rival-section"><div class="pv-rival-section-head"><div><span class="pv-eyebrow">RECENT LADDER TRAFFIC</span><h2>Active Competitors</h2><p>Trainers and autonomous AI who have recently moved the ladder.</p></div></div><?php if($active): ?><div class="pv-rival-target-grid compact"><?php foreach($active as $row) pv_rival_target_card($row,$now,'active'); ?></div><?php else:?><div class="pv-empty-state"><strong>Quiet competitive window.</strong><span>As AI operations and player battles resolve, active targets appear here automatically.</span></div><?php endif;?></div>
</section>

<section class="pv-rival-section"><div class="pv-rival-section-head"><div><span class="pv-eyebrow">YOUR BATTLE LOG</span><h2>Recent Rival Battles</h2><p>Rating movement, attack direction and battle outcomes from your competitive history.</p></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('ai_activity.php'))?>">Watch AI Activity</a></div>
<?php if($recent): ?><div class="pv-rival-history"><?php foreach($recent as $battle): $won=(int)$battle['winner_id']===$uid; $attacked=(int)$battle['attacker_id']===$uid; ?><article class="<?=$won?'is-win':'is-loss'?>"><span class="pv-rival-history-result"><?=$won?'WIN':'LOSS'?></span><div><strong><?=pv_h((string)$battle['opponent_name'])?></strong><small><?=$attacked?'You challenged this trainer':'This trainer challenged you'?> · <?=pv_h(ucfirst((string)$battle['source']))?> · <?=pv_h(pv_rival_time_ago((int)$battle['created_at'],$now))?></small></div><b class="<?=$won?'positive':'negative'?>"><?=$won?'+':'-'?><?=number_format((int)$battle['rating_delta'])?> RP</b></article><?php endforeach;?></div><?php else:?><div class="pv-empty-state"><strong>No ranked battle history yet.</strong><span>Choose an open Rival Network target to put your trainer on the competitive record.</span></div><?php endif;?></section>
<?php endif; ?>
</section></main></div>
<?php pv_page_end(); ?>
