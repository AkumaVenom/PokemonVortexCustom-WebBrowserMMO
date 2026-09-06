<?php
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/rival_runtime.php';
pv_require_login();
$db=pv_db(); $uid=(int)$_SESSION['myid'];
$stmt=$db->prepare('SELECT * FROM members WHERE id=? LIMIT 1'); $stmt->bind_param('i',$uid); $stmt->execute(); $member=$stmt->get_result()->fetch_assoc(); $stmt->close();
if(!$member){ $_SESSION=[]; pv_redirect('login.php?expired=1'); }
$collection=0;$unique=0;$totalExp=0;$online=0;$messages=0;$offers=0;
$stmt=$db->prepare('SELECT COUNT(*) c, COUNT(DISTINCT pid) u, COALESCE(SUM(exp),0) e FROM pokemon WHERE owner=?');$stmt->bind_param('i',$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();$collection=(int)$row['c'];$unique=(int)$row['u'];$totalExp=(int)$row['e'];
$online=(int)($db->query('SELECT COUNT(*) c FROM online WHERE time >= '.(time()-900))->fetch_assoc()['c']??0);
$stmt=$db->prepare("SELECT COUNT(*) c FROM messages WHERE receiverid=? AND receiverdelete='1' AND receiverread='1'");$stmt->bind_param('i',$uid);$stmt->execute();$messages=(int)($stmt->get_result()->fetch_assoc()['c']??0);$stmt->close();
$stmt=$db->prepare('SELECT COALESCE(SUM(offers),0) c FROM upfortrade WHERE owner=?');$stmt->bind_param('i',$uid);$stmt->execute();$offers=(int)($stmt->get_result()->fetch_assoc()['c']??0);$stmt->close();
// Keep legacy ranking counters coherent with the real collection.
$avg=$collection?intdiv($totalExp,$collection):0; $points=(int)round((sqrt(max(1,$collection))*sqrt(max(1,$totalExp))*sqrt(max(1,$avg))*max(1,(int)($member['battle']??0)))/1000,1);
$stmt=$db->prepare('UPDATE members SET total_poke=?,uniques=?,totalexp=?,averageexp=?,points=? WHERE id=?');$stmt->bind_param('iiiiii',$collection,$unique,$totalExp,$avg,$points,$uid);$stmt->execute();$stmt->close();

$team=[];
$teamIds=array_filter(array_map('intval',[$member['s1']??0,$member['s2']??0,$member['s3']??0,$member['s4']??0,$member['s5']??0,$member['s6']??0]));
if($teamIds){ $ids=implode(',',array_map('intval',$teamIds)); $r=$db->query("SELECT p.*, ps.nature, ps.ability, ps.happiness FROM pokemon p LEFT JOIN pokemon_stats ps ON ps.id=p.id WHERE p.owner={$uid} AND p.id IN ({$ids})"); $by=[]; while($x=$r->fetch_assoc())$by[(int)$x['id']]=$x; foreach($teamIds as $id)if(isset($by[$id]))$team[]=$by[$id]; }
$money=(int)($member['money']??0);$battles=(int)($member['battle']??0);
$rivalState=null;$rivalRank=0;$rivalTier=pv_rival_tier(PV_RIVAL_START_RATING);
if(pv_rival_ready($db)){ pv_rival_ensure_state($db,$uid); $rivalState=pv_rival_state($db,$uid); if($rivalState){$rivalRank=pv_rival_rank_position($db,$uid,(int)$rivalState['rating']);$rivalTier=pv_rival_tier((int)$rivalState['rating']);} }
pv_page_start('Dashboard','dashboard.php',true);
?>
<div class="pv-game-layout"><?php pv_game_side_menu('dashboard.php'); ?><main class="pv-page pv-dashboard-pokemon">
<?php pv_pokemon_banner('TRAINER HOME · LIVE WORLD','Welcome back, '.(string)$member['username'],'Your team, collection and competitive Rival Network status are synchronized. Choose your next adventure and keep climbing.',['Pikachu','Eevee','Charizard','Lucario','Gengar']); ?>
<div class="pv-dashboard-quickbar"><a class="pv-button" href="<?= pv_h(pv_url('map_select.php')) ?>"><img src="<?=pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG'))?>" alt="">Explore the World</a><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('rival_hub.php'))?>"><img src="<?=pv_h(pv_static_file('images/items/Ultra Ball.png','images/Pokeball.PNG'))?>" alt="">Open Rival Hub</a></div>

<section class="pv-grid cols-4" style="margin-top:0"><article class="pv-card"><div class="pv-kpi"><?= number_format($money) ?></div><div class="pv-kpi-label">PokéMoney</div></article><article class="pv-card"><div class="pv-kpi"><?= number_format($collection) ?></div><div class="pv-kpi-label">Pokémon owned</div></article><article class="pv-card"><div class="pv-kpi"><?= number_format($unique) ?></div><div class="pv-kpi-label">Unique forms</div></article><article class="pv-card"><div class="pv-kpi"><?= number_format($battles) ?></div><div class="pv-kpi-label">Battles completed</div></article></section>

<section class="pv-card" style="margin-top:14px"><div class="pv-page-head"><div><h3 style="margin:0">Active team</h3><div class="pv-subtle">Your six battle slots</div></div><a href="<?= pv_h(pv_url('change_team.php')) ?>">Change team →</a></div>
<div class="pv-team">
<?php for($i=0;$i<6;$i++): $p=$team[$i]??null; ?><div class="pv-pokemon-card"><?php if($p): ?><img src="<?= pv_h(pv_pokemon_image((string)$p['name'])) ?>" alt="<?= pv_h($p['name']) ?>"><strong><?= pv_h($p['name']) ?></strong><span class="pv-subtle">Lv. <?= (int)$p['lvl'] ?></span><?php else: ?><div style="height:78px;display:grid;place-items:center;color:#46627e;font-size:28px">+</div><strong>Empty Slot</strong><span class="pv-subtle">Add Pokémon</span><?php endif; ?></div><?php endfor; ?>
</div></section>

<section class="pv-grid cols-3 pv-dashboard-feature-grid"><article class="pv-card pv-dashboard-feature"><div class="pv-icon pv-image-icon"><img src="<?=pv_h(pv_static_file('images/pokemon/Eevee.gif','images/Pokeball.PNG'))?>" alt=""></div><h3>World exploration</h3><p>Choose a region, meet trainers and search for wild Pokémon encounters across the persistent world.</p><div class="pv-actions"><a class="pv-btn secondary" href="<?= pv_h(pv_url('map_select.php')) ?>">Open World Maps</a></div></article><article class="pv-card pv-dashboard-feature"><div class="pv-icon pv-image-icon"><img src="<?=pv_h(pv_static_file('images/pokemon/Lucario.gif','images/Pokeball.PNG'))?>" alt=""></div><h3>Battle arena</h3><p>Challenge trainers, gym content and wild encounters while keeping the cinematic battle effects intact.</p><div class="pv-actions"><a class="pv-btn secondary" href="<?= pv_h(pv_url('battle_select.php')) ?>">Choose Battle</a></div></article><article class="pv-card pv-dashboard-feature"><div class="pv-icon pv-image-icon"><img src="<?=pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG'))?>" alt=""></div><h3>Pokédex & collection</h3><p>Browse Pokémon data, inspect your collection and manage attacks or evolutions.</p><div class="pv-actions"><a class="pv-btn secondary" href="<?= pv_h(pv_url('pokedex.php')) ?>">Open Pokédex</a></div></article></section>

<section class="pv-dashboard-rival-card"><div class="pv-dashboard-rival-ball"><img src="<?=pv_h(pv_static_file((string)$rivalTier['ball'],'images/items/Poke Ball.png'))?>" alt=""></div><div><span class="pv-eyebrow">RIVAL NETWORK</span><h2><?= $rivalState ? '#'.number_format($rivalRank).' · '.pv_h((string)$rivalTier['name']) : 'Competitive ladder waiting to be activated' ?></h2><p><?= $rivalState ? 'Your Rival Rating is '.number_format((int)$rivalState['rating']).' with '.number_format((int)$rivalState['ranked_wins']).' ranked wins. Challenge rivals or answer retaliation orders to move the ladder.' : 'Open Setup and run Upgrade once to unlock player and AI rankings, protection shields, retaliation orders and the AI Activity feed.' ?></p></div><div class="pv-dashboard-rival-actions"><a class="pv-button" href="<?=pv_h(pv_url('rival_hub.php'))?>">Rival Hub</a><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('rankings.php'))?>">Rankings</a></div></section>

<section class="pv-grid cols-4"><article class="pv-card"><div class="pv-kpi"><?= number_format($totalExp) ?></div><div class="pv-kpi-label">Total experience</div></article><article class="pv-card"><div class="pv-kpi"><?= number_format($online) ?></div><div class="pv-kpi-label">Trainers online</div></article><article class="pv-card"><div class="pv-kpi"><?= number_format($messages) ?></div><div class="pv-kpi-label">Unread messages</div></article><article class="pv-card"><div class="pv-kpi"><?= number_format($offers) ?></div><div class="pv-kpi-label">Trade offers</div></article></section>
</main></div>
<?php pv_page_end(); ?>
