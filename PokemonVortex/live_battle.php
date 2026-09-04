<?php
declare(strict_types=1);

require_once __DIR__ . '/kick.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/live_battle_settlement.php';
require_once __DIR__ . '/includes/live_battle_runtime.php';
require_once __DIR__ . '/includes/bot_runtime.php';

$userId = max(0, (int)($_SESSION['myid'] ?? 0));
if ($userId <= 0 || (int)($_SESSION['access'] ?? 0) !== 9) pv_redirect('login.php?goawaxP=1');

$userSlot = (int)($_SESSION['live'][0] ?? 0);
$opponentSlot = (int)($_SESSION['live'][1] ?? 0);
$opponentId = max(0, (int)($_SESSION['live'][3] ?? 0));
$battleId = max(0, (int)($_SESSION['live'][4] ?? 0));
if (!in_array($userSlot, [1,2], true) || !in_array($opponentSlot, [1,2], true)
    || $userSlot === $opponentSlot || $opponentId <= 0 || $battleId <= 0) {
    unset($_SESSION['live']);
    pv_redirect('live_battle_arena.php?stale=1');
}

$db = pv_db();
$opponentIsBot = pv_bot_is_bot($db, $opponentId);
$error = trim((string)($_SESSION['pv_live_runtime_error'] ?? ''));
unset($_SESSION['pv_live_runtime_error']);
$state = null;
try {
    $state = pv_live_runtime_initialize($db, $battleId, $userSlot, $opponentSlot, $userId, $opponentId);
    if ($opponentIsBot) {
        $state = pv_bot_live_autoplay($db, $battleId, $opponentSlot, $userSlot, $opponentId, $userId);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        pv_require_csrf();
        if (!pv_consume_action_token('live_battle.php', (string)($_POST['battle_action_token'] ?? ''))) pv_redirect('live_battle.php');
        $action = trim((string)($_POST['runtime_action'] ?? ''));
        $payload = [
            'pokemon_id' => max(0, (int)($_POST['pokemon_id'] ?? 0)),
            'move_slot' => max(0, (int)($_POST['move_slot'] ?? 0)),
            'item' => trim((string)($_POST['item'] ?? '')),
        ];
        try {
            $state = pv_live_runtime_submit($db, $battleId, $userSlot, $opponentSlot, $userId, $opponentId, $action, $payload);
            if ($opponentIsBot) {
                $state = pv_bot_live_autoplay($db, $battleId, $opponentSlot, $userSlot, $opponentId, $userId);
            }
        } catch (Throwable $commandError) {
            $_SESSION['pv_live_runtime_error'] = 'That battle action could not be used. Check your active Pokémon and try again.';
            pv_log('Authoritative Live Battle command: ' . $commandError->getMessage());
        }
        pv_redirect('live_battle.php');
    }
    $state = pv_live_runtime_load($db, $battleId, $userSlot, $opponentSlot, $userId, $opponentId);
    if ($opponentIsBot && ($state['phase'] ?? '') !== 'complete') {
        $state = pv_bot_live_autoplay($db, $battleId, $opponentSlot, $userSlot, $opponentId, $userId);
    }
    if (($state['phase'] ?? '') === 'complete') {
        $outcome = (int)($state['winner_slot'] ?? 0) === $userSlot ? 'win' : 'loss';
        if ($opponentIsBot) {
            $botOutcome = (int)($state['winner_slot'] ?? 0) === $opponentSlot ? 'win' : 'loss';
            try {
                pv_bot_settle_live_result($db, $battleId, $opponentSlot, $userSlot, $opponentId, $userId, $botOutcome);
            } catch (Throwable $botSettlementError) {
                pv_log('Live AI Battle bot settlement: ' . $botSettlementError->getMessage());
            }
        }
        pv_live_runtime_project_session($state, $userSlot);
        pv_live_settle_result($db, $battleId, $userSlot, $opponentSlot, $userId, $opponentId, $outcome);
        $_SESSION['live_last_result_battle_id'] = $battleId;
        pv_redirect('live_battle_result.php?match=' . $battleId);
    }
} catch (Throwable $e) {
    $error = trim($error . ' The match could not be loaded. Return to the Live Battle Arena and start a new challenge.');
    pv_log('Authoritative Live Battle runtime: ' . $e->getMessage());
}

function pv_live_page_fighter(array $participant): ?array
{
    $index = pv_live_runtime_active_index($participant);
    return $index >= 0 && isset($participant['team'][$index]) ? $participant['team'][$index] : null;
}

function pv_live_page_sprite(string $name): string
{
    return 'html/static/images/pokemon/' . rawurlencode($name) . '.gif';
}

function pv_live_page_fighter_card(array $participant, bool $enemy): void
{
    $fighter = pv_live_page_fighter($participant);
    $label = $enemy ? 'OPPOSING TRAINER' : 'ACTIVE PARTNER';
    $class = $enemy ? 'pv-wild-enemy' : 'pv-wild-player';
    if (!$fighter) {
        echo '<article class="pv-wild-fighter ' . $class . ' pv-live-runtime-empty"><div class="pv-wild-fighter-hud"><div><small>' . $label . '</small><h2>Awaiting selection</h2><span>' . pv_h((string)($participant['name'] ?? 'Trainer')) . '</span></div><strong>—</strong></div><div class="pv-wild-hp"><i style="width:0%"></i></div><div class="pv-wild-sprite-zone"><span class="pv-wild-scan-ring"></span></div></article>';
        return;
    }
    $hp = max(0, (int)$fighter['hp']);
    $maxHp = max(1, (int)$fighter['max_hp']);
    $percent = max(0, min(100, (int)round(($hp / $maxHp) * 100)));
    $hud = '<div class="pv-wild-fighter-hud"><div><small>' . $label . '</small><h2>' . pv_h((string)$fighter['name']) . '</h2><span>Lv. ' . number_format((int)$fighter['level']) . ' · ' . pv_h((string)$participant['name']) . '</span></div><strong>' . number_format($hp) . ' / ' . number_format($maxHp) . ' HP</strong></div><div class="pv-wild-hp"><i style="width:' . $percent . '%"></i></div>';
    $sprite = '<div class="pv-wild-sprite-zone"><span class="pv-wild-scan-ring"></span><img src="' . pv_h(pv_live_page_sprite((string)$fighter['name'])) . '" alt="' . pv_h((string)$fighter['name']) . '"></div>';
    echo '<article class="pv-wild-fighter ' . $class . ' pv-combat-fighter-card">' . ($enemy ? $hud.$sprite : $sprite.$hud) . '</article>';
}

function pv_live_page_form_fields(string $action): string
{
    return pv_csrf_field()
        . '<input type="hidden" name="battle_action_token" value="' . pv_h(pv_action_token('live_battle.php')) . '">'
        . '<input type="hidden" name="runtime_action" value="' . pv_h($action) . '">';
}

$own = is_array($state) ? ($state['participants'][(string)$userSlot] ?? []) : [];
$opponent = is_array($state) ? ($state['participants'][(string)$opponentSlot] ?? []) : [];
$phase = (string)($state['phase'] ?? 'unavailable');
$turn = max(0, (int)($state['turn'] ?? 0));
$revision = max(0, (int)($state['revision'] ?? 0));
$ownWaiting = in_array($phase, ['select','switch'], true) ? pv_live_runtime_active_is_ready($own) : ($phase === 'command' && is_array($own['command'] ?? null));
$inventory = $state ? pv_live_runtime_inventory($db, $userId) : [];

pv_page_start('Live Battle', 'battle_select.php', true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('battle_select.php'); ?>
<main class="pv-main-column"><section class="pv-page pv-live-runtime-page">
<div class="pv-page-head"><div><span class="pv-eyebrow"><?=$opponentIsBot?'LIVE BATTLE // AUTONOMOUS TRAINER':'LIVE BATTLE // REAL-TIME PVP'?></span><h1><?=$opponentIsBot?'Live AI Battle':'Live Battle'?></h1><p class="pv-subtle"><?=$opponentIsBot?'Battle a persistent autonomous trainer using the same server-authoritative Live Battle engine.':'Battle another trainer in real time.'?> Match #<?=number_format($battleId)?> · Turn <?=number_format($turn)?></p></div><div class="pv-combat-runtime-signal"><i></i><strong>MATCH STATUS</strong><span><?=pv_h(strtoupper($phase))?></span></div></div>
<?php if($error): ?><div class="pv-flash danger"><?=pv_h($error)?></div><?php endif; ?>
<?php if($state): ?>
<div class="pv-combat-modern-surface" data-pv-live-runtime>
<div class="pv-wild-arena"><?php pv_live_page_fighter_card($opponent, true); ?><div class="pv-wild-versus"><span>VS</span><i></i></div><?php pv_live_page_fighter_card($own, false); ?></div>
<div class="pv-wild-lower-grid">
<section class="pv-wild-log-panel"><div class="pv-map-panel-label">TURN LOG</div><div class="pv-wild-log">
<?php foreach(array_reverse((array)($state['log'] ?? [])) as $entry): ?><div class="tone-<?=pv_h((string)($entry['tone'] ?? 'info'))?>"><i></i><span><?=pv_h((string)($entry['message'] ?? ''))?></span></div><?php endforeach; ?>
</div></section>
<section class="pv-wild-control-panel"><div class="pv-map-panel-label">BATTLE COMMAND // <?=pv_h(strtoupper($phase))?></div>
<?php if($ownWaiting): ?>
<div class="pv-live-runtime-wait" data-pv-live-runtime-wait><span class="pv-wild-scan-ring"></span><i></i><strong>Move locked</strong><p><?=$opponentIsBot?'The AI trainer is choosing its next action.':'Waiting for '.pv_h((string)($opponent['name'] ?? 'the other trainer')).'. Your turn will continue after they choose an action.'?></p></div>
<?php elseif(in_array($phase, ['select','switch'], true)): ?>
<div class="pv-live-runtime-section-head"><strong><?=$phase==='switch'?'Choose your replacement Pokémon':'Choose your active Pokémon'?></strong><span>Choose a Pokémon from your active team that can still battle.</span></div>
<div class="pv-wild-team-grid pv-live-runtime-team-grid">
<?php foreach((array)($own['team'] ?? []) as $fighter): $living=(int)$fighter['hp']>0; ?><form method="post"><?=pv_live_page_form_fields('select')?><input type="hidden" name="pokemon_id" value="<?=(int)$fighter['id']?>"><button type="submit" <?=!$living?'disabled':''?>><img src="<?=pv_h(pv_live_page_sprite((string)$fighter['name']))?>" alt=""><span><strong><?=pv_h((string)$fighter['name'])?></strong><small>Lv. <?=(int)$fighter['level']?> · <?=(int)$fighter['hp']?> / <?=(int)$fighter['max_hp']?> HP</small></span></button></form><?php endforeach; ?>
</div>
<?php elseif($phase === 'command'): $active=pv_live_page_fighter($own); ?>
<div class="pv-live-runtime-command-grid">
<section><div class="pv-live-runtime-section-head"><strong>Fight</strong><span>Choose one move each turn.</span></div><div class="pv-wild-move-grid">
<?php foreach((array)($active['moves'] ?? []) as $index=>$move): ?><form method="post"><?=pv_live_page_form_fields('attack')?><input type="hidden" name="move_slot" value="<?=$index+1?>"><button type="submit"><small>MOVE SLOT <?=$index+1?></small><strong><?=pv_h((string)$move)?></strong><span>Choose move</span></button></form><?php endforeach; ?>
</div></section>
<section><div class="pv-live-runtime-section-head"><strong>Items</strong><span>Choose an item to use this turn.</span></div><div class="pv-wild-item-grid">
<?php foreach($inventory as $item=>$quantity): ?><form method="post"><?=pv_live_page_form_fields('item')?><input type="hidden" name="item" value="<?=pv_h($item)?>"><button type="submit" <?=$quantity<=0?'disabled':''?>><strong><?=pv_h($item)?></strong><span><?=number_format($quantity)?> available</span></button></form><?php endforeach; ?>
</div></section>
<section><div class="pv-live-runtime-section-head"><strong>Team</strong><span>Switching happens before attacks.</span></div><div class="pv-wild-team-grid pv-live-runtime-team-grid compact">
<?php foreach((array)($own['team'] ?? []) as $fighter): $canSwitch=(int)$fighter['hp']>0 && (int)$fighter['id']!==(int)($own['active']??0); ?><form method="post"><?=pv_live_page_form_fields('switch')?><input type="hidden" name="pokemon_id" value="<?=(int)$fighter['id']?>"><button type="submit" <?=!$canSwitch?'disabled':''?>><img src="<?=pv_h(pv_live_page_sprite((string)$fighter['name']))?>" alt=""><span><strong><?=pv_h((string)$fighter['name'])?></strong><small><?=(int)$fighter['hp']?> / <?=(int)$fighter['max_hp']?> HP</small></span></button></form><?php endforeach; ?>
</div></section>
</div>
<?php elseif($phase === 'complete'): ?><div class="pv-empty-state"><strong>This match has finished.</strong><span>Your result is still being recorded. Use the button below to finish and continue.</span></div><div class="pv-actions"><a class="pv-button" href="<?=pv_h(pv_url('live_battle.php'))?>">Retry Result</a></div>
<?php else: ?><div class="pv-empty-state"><strong>This match could not be loaded.</strong><span>Return to the arena and start a new challenge.</span></div><?php endif; ?>
<?php if($phase !== 'complete'): ?><form method="post" class="pv-live-runtime-forfeit" onsubmit="return confirm('Forfeit this Live Battle?');"><?=pv_live_page_form_fields('forfeit')?><button class="pv-button pv-button-secondary" type="submit">Forfeit Match</button></form><?php endif; ?>
</section></div></div>
<?php else: ?><div class="pv-panel"><div class="pv-empty-state"><strong>This match could not be loaded.</strong><span>Return to the arena and start a fresh challenge.</span></div><div class="pv-actions"><a class="pv-button" href="<?=pv_h(pv_url('live_battle_arena.php'))?>">Live Battle Arena</a></div></div><?php endif; ?>
</section></main></div>
<?php if($state && $phase !== 'complete'): ?><script>
(() => {
  const matchId=<?=json_encode($battleId)?>, revision=<?=json_encode($revision)?>, phase=<?=json_encode($phase)?>, turn=<?=json_encode($turn)?>, waiting=<?=json_encode($ownWaiting)?>;
  let busy=false;
  const poll=async()=>{if(busy||document.visibilityState!=='visible')return;busy=true;try{const q=new URLSearchParams({match:String(matchId),revision:String(revision),phase,turn:String(turn),waiting:waiting?'1':'0'});const response=await fetch('live_battle_state.php?'+q.toString(),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});const data=await response.json();if(data&&data.refresh)window.location.replace('live_battle.php');}catch(_){/* Read-only polling retries without replaying commands. */}finally{busy=false;}};
  window.setInterval(poll,900);document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')poll();});
})();
</script><?php endif; ?>
<?php pv_page_end(); ?>
