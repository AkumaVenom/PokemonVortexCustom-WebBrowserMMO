<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/rival_ui.php';
require_once __DIR__ . '/includes/bot_runtime.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

pv_require_login();

/**
 * Render one authoritative ladder row.
 *
 * The row's global_rank is always the trainer's true mixed player/AI position.
 * Player spotlight rows may therefore be surfaced above the Top 100 without
 * pretending that their actual global placement changed.
 */
function pv_rankings_render_row(array $row, int $visualIndex, int $uid, int $now, bool $playerSpotlight = false): void
{
    $rating = (int)$row['rating'];
    $tier = pv_rival_tier($rating);
    $trainer = pv_rival_trainer_sprite($row);
    $isMe = (int)$row['user_id'] === $uid;
    $isPinned = !empty($row['_pv_pinned']);
    $isBot = pv_rival_is_bot($row);
    $botActivity = $isBot ? pv_bot_activity_profile((int)$row['bot_index']) : null;

    $classes = ['pv-ranking-row'];
    if ($isMe) $classes[] = 'is-you';
    if ($isPinned) $classes[] = 'is-pinned-you';
    if ($playerSpotlight) $classes[] = 'is-player-spotlight';
    if (!$playerSpotlight && !$isPinned && $visualIndex >= 0 && $visualIndex < 3) {
        $classes[] = 'is-podium';
        $classes[] = 'podium-' . ($visualIndex + 1);
    }
    ?>
    <a role="row" class="<?=pv_h(implode(' ', $classes))?>" href="<?=pv_h(pv_rival_profile_url($row) . '&ranked=1')?>">
        <span class="pv-ranking-number"><?=number_format((int)($row['global_rank'] ?? ($visualIndex + 1)))?></span>
        <span class="pv-ranking-trainer">
            <i>
                <img class="trainer" src="<?=pv_h(pv_static_file('images/sprites/'.$trainer.'whole.gif','images/sprites/1whole.gif'))?>" alt="">
                <img class="lead" src="<?=pv_h(pv_rival_lead_url($row))?>" alt="">
            </i>
            <b><?=pv_h((string)$row['username'])?></b>
            <?php if ($botActivity): ?><em><?=pv_h(strtoupper((string)$botActivity['label']))?></em><?php else: ?><em>PLAYER</em><?php endif; ?>
            <?=$isMe ? '<small>YOU</small>' : ''?>
        </span>
        <span class="pv-rival-tier pv-tier-<?=pv_h((string)$tier['class'])?>"><img src="<?=pv_h(pv_rival_ball_url($rating))?>" alt=""><?=pv_h((string)$tier['short'])?></span>
        <span class="pv-ranking-rating"><strong><?=number_format($rating)?></strong><small>peak <?=number_format((int)$row['peak_rating'])?></small></span>
        <span><?=number_format((int)$row['ranked_wins'])?> W · <?=number_format((int)$row['ranked_losses'])?> L</span>
        <span>×<?=number_format((int)$row['current_streak'])?></span>
        <span><?=pv_h(pv_rival_time_ago((int)$row['last_ranked_at'], $now))?></span>
    </a>
    <?php
}

function pv_rankings_render_header(): void
{
    ?>
    <div class="pv-ranking-row pv-ranking-header" role="row">
        <span>#</span><span>Trainer</span><span>Tier</span><span>Rating (RP)</span><span>Ranked W / L</span><span>Streak</span><span>Activity</span>
    </div>
    <?php
}

$db = pv_db();
$uid = max(1, (int)($_SESSION['myid'] ?? 0));
$now = time();
$rankedResultSaved = pv_rival_retry_pending_result($db, $uid);
$ready = pv_rival_ready($db);
$rows = [];
$playerRows = [];
$myState = null;
$myRank = 0;
$counts = ['total'=>0,'humans'=>0,'bots'=>0,'battles24'=>0];
$scope = (string)($_GET['scope'] ?? 'all');
if (!in_array($scope, ['all','human','ai'], true)) $scope = 'all';

if ($ready) {
    pv_rival_ensure_all_states($db);
    pv_rival_housekeeping($db);
    pv_bot_tick($db, 18, '', '', 35);
    pv_bot_ranked_pulse($db, PV_BOT_RANKED_PULSE_MAX_OPERATIONS, 1200);
    $now = time();

    $myState = pv_rival_state($db, $uid);
    if ($myState) {
        $myRank = pv_rival_rank_position($db, $uid, (int)$myState['rating'], (int)$myState['ranked_wins'], (int)$myState['ranked_losses']);
    }

    $r = $db->query("SELECT COUNT(*) total,SUM(b.user_id IS NULL) humans,SUM(b.user_id IS NOT NULL) bots FROM trainer_rank_state rs JOIN members m ON m.id=rs.user_id LEFT JOIN bot_trainers b ON b.user_id=rs.user_id AND b.enabled=1 WHERE COALESCE(m.s1,0)>0");
    if ($r) {
        $counts = array_merge($counts, $r->fetch_assoc() ?: []);
        $r->free();
    }
    $r = $db->query('SELECT COUNT(*) c FROM rival_battles WHERE created_at>=' . (int)($now - 86400));
    if ($r) {
        $counts['battles24'] = (int)($r->fetch_assoc()['c'] ?? 0);
        $r->free();
    }

    $where = $scope === 'human' ? ' AND b.user_id IS NULL ' : ($scope === 'ai' ? ' AND b.user_id IS NOT NULL ' : '');
    $select = "SELECT rs.*,(SELECT COUNT(*)+1 FROM trainer_rank_state gr JOIN members gm ON gm.id=gr.user_id WHERE COALESCE(gm.s1,0)>0 AND (gr.rating>rs.rating OR (gr.rating=rs.rating AND gr.ranked_wins>rs.ranked_wins) OR (gr.ranked_wins=rs.ranked_wins AND gr.rating=rs.rating AND gr.ranked_losses<rs.ranked_losses) OR (gr.ranked_wins=rs.ranked_wins AND gr.rating=rs.rating AND gr.ranked_losses=rs.ranked_losses AND gr.user_id<rs.user_id))) global_rank,m.username,COALESCE(b.bot_index,0) bot_index,COALESCE(b.trainer_sprite,0) trainer_sprite,COALESCE(o.trainer,1) trainer,p.name lead_name,CASE WHEN b.user_id IS NULL THEN 0 ELSE 1 END is_bot FROM trainer_rank_state rs JOIN members m ON m.id=rs.user_id LEFT JOIN bot_trainers b ON b.user_id=rs.user_id AND b.enabled=1 LEFT JOIN members_options o ON o.id=rs.user_id LEFT JOIN pokemon p ON p.id=m.s1 AND CAST(p.owner AS UNSIGNED)=m.id WHERE COALESCE(m.s1,0)>0";
    $order = ' ORDER BY rs.rating DESC,rs.ranked_wins DESC,rs.ranked_losses ASC,rs.user_id ASC';

    // The Players filter is an actual all-player ledger, not a Top-100 slice.
    // AI and mixed views stay bounded at 100 to keep the autonomous field light.
    $sql = $select . $where . $order . ($scope === 'human' ? '' : ' LIMIT 100');
    $r = $db->query($sql);
    if ($r) {
        $rows = $r->fetch_all(MYSQLI_ASSOC);
        $r->free();
    }

    // On the mixed board, every eligible human is also surfaced immediately in
    // a dedicated player standings block. Their displayed rank remains the true
    // shared player/AI global rank; this does not promote or reorder anyone.
    if ($scope === 'all') {
        $r = $db->query($select . ' AND b.user_id IS NULL' . $order);
        if ($r) {
            $playerRows = $r->fetch_all(MYSQLI_ASSOC);
            $r->free();
        }
    }

    // Preserve the v25.2.4 safeguard as a second line of defense: the signed-in
    // player also remains appended beneath the mixed Top 100 when outside it.
    if ($scope !== 'ai' && !array_filter($rows, static fn(array $row): bool => (int)$row['user_id'] === $uid)) {
        $r = $db->query($select . ' AND rs.user_id=' . (int)$uid . ' LIMIT 1');
        if ($r) {
            $pinned = $r->fetch_assoc();
            $r->free();
            if ($pinned) {
                $pinned['_pv_pinned']=1;$rows[]=$pinned;
            }
        }
    }
}

$cycle = pv_bot_ranked_cycle(time());
$now = (int)$cycle['server_now'];
$refreshAt = (int)$cycle['refresh_at'];
$refreshRemaining = max(0, $refreshAt - $now);
$refreshText = sprintf('%02d:%02d', intdiv($refreshRemaining, 60), $refreshRemaining % 60);
$myTier = pv_rival_tier((int)($myState['rating'] ?? PV_RIVAL_START_RATING));

if ($scope === 'human') {
    $ladderEyebrow = 'ALL PLAYER TRAINERS · LIVE 1-MINUTE REFRESH';
    $ladderTitle = 'Player Trainer Ladder';
    $ladderCopy = 'Every eligible player trainer is listed here with their true shared global rank. Rating points decide position. More ranked wins, then fewer losses, break equal-rating ties.';
} elseif ($scope === 'ai') {
    $ladderEyebrow = 'TOP 100 AI · LIVE 1-MINUTE REFRESH';
    $ladderTitle = 'Autonomous Trainer Ladder';
    $ladderCopy = 'AI rivals keep challenging one another for rating points. Leading contenders return regularly, alongside Master and Elite rivals and trainers from across the full field.';
} else {
    $ladderEyebrow = 'TOP 100 GLOBAL · LIVE 1-MINUTE REFRESH';
    $ladderTitle = 'Global Trainer Ladder';
    $ladderCopy = 'The Top 100 is ordered by rating points. AI rivals compete throughout each active minute, with repeat opportunities for leading contenders. Wins earn RP and losses cost RP; opponent rating determines the amount. Every eligible player is also surfaced above it with their true global rank so human trainers can never disappear inside the 10,000-trainer AI field.';
}

pv_page_start('Trainer Rankings', 'rankings.php', true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('rankings.php'); ?>
<main class="pv-main-column"><section class="pv-page pv-rankings-page">
<?php pv_pokemon_banner('GLOBAL BATTLE LADDER','Trainer Rankings','Players and autonomous AI trainers compete on one persistent global ladder ordered by rating points (RP). Ranked wins increase your rating and losses decrease it. Open a trainer and choose Start Ranked Battle to compete.',['Mewtwo','Lucario','Dragonite','Gardevoir','Pikachu']); ?>
<?php if (!$rankedResultSaved): ?>
<div class="pv-flash warning"><strong>Ranked result waiting to save.</strong><span>Your completed battle is retained in this session. Refresh this page to retry; another ranked battle can start once it saves.</span></div>
<?php endif; ?>
<?php if (!$ready): ?>
<div class="pv-flash warning"><strong>Trainer Rankings are waiting to be activated.</strong><span>Open Setup and run Upgrade once to bring the Rival Network ladder online.</span></div>
<?php else: ?>
<section class="pv-ranking-summary">
    <article class="pv-ranking-self"><span class="pv-ranking-ball"><img src="<?=pv_h(pv_static_file((string)$myTier['ball'],'images/items/Poke Ball.png'))?>" alt=""></span><div><span class="pv-eyebrow">YOUR GLOBAL POSITION</span><h2>#<?=number_format($myRank)?></h2><strong><?=number_format((int)($myState['rating'] ?? 0))?> RP</strong><small><?=pv_h((string)$myTier['name'])?> · Peak <?=number_format((int)($myState['peak_rating'] ?? 0))?></small></div></article>
    <article><img src="<?=pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG'))?>" alt=""><strong><?=number_format((int)$counts['total'])?></strong><span>Ranked Trainers</span></article>
    <article><img src="<?=pv_h(pv_static_file('images/sprites/2whole.gif','images/Pokeball.PNG'))?>" alt=""><strong><?=number_format((int)$counts['humans'])?></strong><span>Player Trainers</span></article>
    <article><img src="<?=pv_h(pv_static_file('images/items/Ultra Ball.png','images/Pokeball.PNG'))?>" alt=""><strong><?=number_format((int)$counts['bots'])?></strong><span>Autonomous AI</span></article>
    <article><img src="<?=pv_h(pv_static_file('images/pokemon/Lucario.gif','images/Pokeball.PNG'))?>" alt=""><strong><?=number_format((int)$counts['battles24'])?></strong><span>Ranked Battles · 24h</span></article>
</section>

<?php if ($scope === 'all' && $playerRows): ?>
<section class="pv-rankings-panel pv-player-standings-panel" aria-labelledby="pv-player-standings-title">
    <div class="pv-rival-section-head">
        <div>
            <span class="pv-eyebrow">PLAYER TRAINERS · ALWAYS VISIBLE</span>
            <h2 id="pv-player-standings-title">Player Trainer Standings</h2>
            <p>Every eligible human trainer is shown here on every Rankings refresh. The rank number is the trainer's real position against the complete player + AI field: rating points first, then more ranked wins and fewer losses for ties.</p>
        </div>
        <span class="pv-rival-section-count"><?=number_format(count($playerRows))?> PLAYERS</span>
    </div>
    <div class="pv-ranking-table pv-player-ranking-table" role="table" aria-label="Player trainer standings">
        <?php pv_rankings_render_header(); ?>
        <?php foreach ($playerRows as $index => $row) pv_rankings_render_row($row, $index, $uid, $now, true); ?>
    </div>
</section>
<?php endif; ?>

<section class="pv-rankings-panel" data-pv-rankings-refresh-seconds="<?=PV_BOT_RANKED_PULSE_SECONDS?>" data-pv-rankings-server-now="<?=$now?>" data-pv-rankings-refresh-at="<?=$refreshAt?>" data-pv-rankings-cycle-start="<?=(int)$cycle['bucket_start']?>">
    <div class="pv-rival-section-head">
        <div><span class="pv-eyebrow"><?=pv_h($ladderEyebrow)?></span><h2><?=pv_h($ladderTitle)?></h2><p><?=pv_h($ladderCopy)?></p></div>
        <div class="pv-ranking-live-tools">
            <div class="pv-ranking-refresh-clock" role="timer" aria-label="Time until Trainer Rankings refresh"><i aria-hidden="true"></i><span><small>NEXT REFRESH</small><strong data-pv-rankings-countdown><?=pv_h($refreshText)?></strong><em data-pv-rankings-refresh-status>LIVE</em></span></div>
            <nav class="pv-ranking-filters" aria-label="Ranking filters"><a class="<?=$scope==='all'?'active':''?>" href="<?=pv_h(pv_url('rankings.php?scope=all'))?>">All</a><a class="<?=$scope==='human'?'active':''?>" href="<?=pv_h(pv_url('rankings.php?scope=human'))?>">Players</a><a class="<?=$scope==='ai'?'active':''?>" href="<?=pv_h(pv_url('rankings.php?scope=ai'))?>">AI</a></nav>
        </div>
    </div>
    <?php if ($rows): ?>
    <div class="pv-ranking-table" role="table" aria-label="Trainer rankings">
        <?php pv_rankings_render_header(); ?>
        <?php foreach ($rows as $index => $row) pv_rankings_render_row($row, $index, $uid, $now, false); ?>
    </div>
    <?php else: ?>
    <div class="pv-empty-state"><strong>No ranked trainers are available yet.</strong><span>The ladder will populate as valid trainer teams are created.</span></div>
    <?php endif; ?>
</section>
<?php endif; ?>
</section></main></div>
<script>
(()=>{
  'use strict';
  const panel=document.querySelector('[data-pv-rankings-refresh-seconds]');
  if(!panel)return;

  const seconds=Math.max(1,Number(panel.dataset.pvRankingsRefreshSeconds)||60);
  const countdown=panel.querySelector('[data-pv-rankings-countdown]');
  const status=panel.querySelector('[data-pv-rankings-refresh-status]');
  const serverNow=Math.max(0,Number(panel.dataset.pvRankingsServerNow)||0);
  const refreshAt=Math.max(0,Number(panel.dataset.pvRankingsRefreshAt)||0);
  const initialRemainingMs=(serverNow>0&&refreshAt>serverNow)
    ? Math.max(0,(refreshAt-serverNow)*1000)
    : seconds*1000;
  const loadedAt=performance.now();
  const dueAt=loadedAt+initialRemainingMs;
  let reloading=false;

  // Remove only our previous cache-buster from the visible address. Scope and
  // every player-selected filter remain intact.
  try{
    const clean=new URL(window.location.href);
    if(clean.searchParams.has('_pv_rank_refresh')){
      clean.searchParams.delete('_pv_rank_refresh');
      window.history.replaceState(window.history.state,'',clean.toString());
    }
  }catch(_ignored){}

  const format=(remainingMs)=>{
    const total=Math.max(0,Math.ceil(remainingMs/1000));
    const minutes=Math.floor(total/60);
    const secs=total%60;
    return String(minutes).padStart(2,'0')+':'+String(secs).padStart(2,'0');
  };

  const refresh=()=>{
    if(reloading)return;
    reloading=true;
    if(countdown)countdown.textContent='00:00';
    if(status)status.textContent='REFRESHING…';
    const url=new URL(window.location.href);
    url.searchParams.set('_pv_rank_refresh',String(Date.now()));
    window.location.replace(url.toString());
  };

  const remainingMs=()=>Math.max(0,dueAt-performance.now());
  const tick=()=>{
    const remaining=remainingMs();
    if(countdown)countdown.textContent=format(remaining);
    if(remaining<=0)refresh();
  };

  // dueAt is anchored to the server's fixed ranked bucket, so reloading this
  // page cannot manufacture another minute. performance.now() is used
  // only as a monotonic local clock after that authoritative server anchor.
  tick();
  const ticker=window.setInterval(tick,250);

  // The watchdog is scheduled for the remaining portion of the current server
  // cycle, not a new one-minute duration from this page load.
  window.setTimeout(refresh,initialRemainingMs+750);

  const catchUp=()=>{if(remainingMs()<=0)refresh();else tick();};
  document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')catchUp();},{passive:true});
  window.addEventListener('focus',catchUp,{passive:true});
  window.addEventListener('pageshow',catchUp,{passive:true});
  window.addEventListener('beforeunload',()=>window.clearInterval(ticker),{once:true});
})();
</script>
<?php pv_page_end(); ?>
