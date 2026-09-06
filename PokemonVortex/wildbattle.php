<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/wild_battle.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();

try {
    $db = pv_db();
} catch (Throwable $e) {
    pv_log('Wild battle DB unavailable: ' . $e->getMessage());
    pv_redirect('dashboard.php?service=unavailable');
}

$uid = (int)$_SESSION['myid'];
$error = trim((string)($_SESSION['pv_wild_flash_error'] ?? ''));
unset($_SESSION['pv_wild_flash_error']);
$state = pv_wild_state();

if (is_array($state) && (time() - (int)($state['updated_at'] ?? $state['created_at'] ?? 0)) > 7200) {
    pv_wild_clear();
    $state = null;
}

// Every command uses POST -> Redirect -> GET. This removes the recovered AJAX
// dependency, makes browser refresh safe, and stops a network retry from
// replaying an attack/item/capture command.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postError = '';
    if (!pv_verify_csrf()) {
        $postError = 'Your battle link expired. Return to the map and start the encounter again.';
    } else {
        try {
            if (isset($_POST['start_wild_battle'])) {
                $token = trim((string)($_POST['encounter_token'] ?? ''));
                if ($token === '') throw new RuntimeException('The wild encounter link is no longer available. Return to the map and scan again.');
                if (!is_array($state) || !hash_equals((string)($state['encounter_token'] ?? ''), $token) || ($state['status'] ?? '') !== 'active') {
                    $startedState = pv_wild_start($db, $uid, $token);
                    pv_server_event('WILD', 'Wild battle started', [
                        'battle_id' => substr((string)($startedState['id'] ?? ''), 0, 12),
                        'pokemon' => (string)($startedState['wild']['display_name'] ?? $startedState['wild']['name'] ?? ''),
                        'level' => (int)($startedState['wild']['level'] ?? 0),
                        'map' => (int)($startedState['map'] ?? 0),
                    ]);
                }
            } elseif (isset($_POST['battle_action'])) {
                if (!is_array($state)) throw new RuntimeException('There is no active wild battle. Return to the map and find another Pokémon.');
                $battleId = trim((string)($_POST['battle_id'] ?? ''));
                if ($battleId === '' || !hash_equals((string)$state['id'], $battleId)) {
                    throw new RuntimeException('That action came from an older encounter. Return to the current battle and try again.');
                }
                $resolvedState = pv_wild_handle_action($db, $state, $_POST);
                $action = trim((string)($_POST['battle_action'] ?? ''));
                $detail = '';
                if ($action === 'attack') $detail = trim((string)($_POST['move'] ?? ''));
                elseif ($action === 'heal') $detail = trim((string)($_POST['item'] ?? ''));
                elseif ($action === 'ball') $detail = trim((string)($_POST['ball'] ?? ''));
                elseif ($action === 'switch') $detail = (string)max(0, (int)($_POST['pokemon_id'] ?? 0));
                pv_server_event('WILD', 'Wild battle action', [
                    'battle_id' => substr((string)($resolvedState['id'] ?? ''), 0, 12),
                    'action' => $action,
                    'detail' => $detail,
                    'target' => (string)($resolvedState['wild']['display_name'] ?? $resolvedState['wild']['name'] ?? ''),
                    'turn' => (int)($resolvedState['turn'] ?? 0),
                    'status' => (string)($resolvedState['status'] ?? ''),
                    'captured_id' => (int)($resolvedState['result']['pokemon_id'] ?? 0),
                ]);
                $terminalStatus = (string)($resolvedState['status'] ?? '');
                if ($terminalStatus !== '' && $terminalStatus !== 'active') {
                    $terminalEvent = match ($terminalStatus) {
                        'captured' => 'Wild Pokémon captured',
                        'won' => 'Wild battle won',
                        'lost' => 'Wild battle lost',
                        'ran' => 'Wild battle ended by run',
                        default => 'Wild battle completed',
                    };
                    pv_server_event('WILD', $terminalEvent, [
                        'battle_id' => substr((string)($resolvedState['id'] ?? ''), 0, 12),
                        'target' => (string)($resolvedState['wild']['display_name'] ?? $resolvedState['wild']['name'] ?? ''),
                        'level' => (int)($resolvedState['wild']['level'] ?? 0),
                        'outcome' => $terminalStatus,
                        'captured_id' => (int)($resolvedState['result']['pokemon_id'] ?? 0),
                        'reward_exp' => (int)($resolvedState['result']['exp'] ?? 0),
                        'reward_money' => (int)($resolvedState['result']['money'] ?? 0),
                    ]);
                }
            } else {
                throw new RuntimeException('No battle command was received.');
            }
        } catch (RuntimeException $e) {
            $postError = $e->getMessage();
        } catch (Throwable $e) {
            pv_log('Wild battle runtime failure: ' . $e->getMessage());
            $postError = 'That battle action could not be completed. Please try again.';
        }
    }
    if ($postError !== '') $_SESSION['pv_wild_flash_error'] = $postError;
    pv_redirect('wildbattle.php');
}

if (!is_array($state)) {
    $map = max(1, min(25, (int)($_SESSION['map'] ?? 1)));
    $returnUrl = pv_wild_return_url(null);
    pv_page_start('Wild Battle', 'battle_select.php', true);
    ?>
    <div class="pv-game-layout">
        <?php pv_game_side_menu('battle_select.php'); ?>
        <main class="pv-main-column">
            <section class="pv-page pv-wildbattle-page">
                <div class="pv-page-head"><div><span class="pv-eyebrow">WILD BATTLE // SIGNAL LOST</span><h1>No active encounter</h1><p class="pv-subtle">Wild battles begin from a Pokémon signal discovered while moving around a world map.</p></div></div>
                <?php if ($error !== ''): ?><div class="pv-alert pv-alert-error"><?= pv_h($error) ?></div><?php endif; ?>
                <div class="pv-wild-empty"><span class="pv-brand-mark">!</span><div><h2>Return to exploration</h2><p>Move around the current region until the encounter scanner locks onto a wild Pokémon, then choose <strong>Battle</strong>.</p><a class="pv-button" href="<?= pv_h($returnUrl) ?>">Return to Map</a></div></div>
            </section>
        </main>
    </div>
    <?php
    pv_page_end();
    exit;
}

$wild = $state['wild'];
$activeId = (int)$state['active_id'];
$active = $state['team'][$activeId] ?? reset($state['team']);
$activeId = (int)($active['id'] ?? $activeId);
$inventory = pv_wild_inventory($db, $uid);
$status = (string)($state['status'] ?? 'active');
$battleActive = $status === 'active';
$map = max(1, min(25, (int)($state['map'] ?? ($_SESSION['map'] ?? 1))));
$returnUrl = pv_wild_return_url($state);
$locationLabel = pv_wild_location_label($state);
$wildHpPct = max(0, min(100, (int)round(((int)$wild['hp'] / max(1,(int)$wild['max_hp'])) * 100)));
$playerHpPct = max(0, min(100, (int)round(((int)$active['hp'] / max(1,(int)$active['max_hp'])) * 100)));
$wildSprite = pv_static_file('images/pokemon/' . (string)$wild['sprite_name'] . '.gif', 'images/Pokeball.PNG');
$playerSprite = pv_pokemon_sprite((string)$active['name']);
$battleFx = is_array($state['fx'] ?? null) ? $state['fx'] : ['action'=>'intro','turn'=>0];

pv_page_start('Wild Battle · ' . (string)$wild['display_name'], 'battle_select.php', true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('battle_select.php'); ?>
<main class="pv-main-column">
<section class="pv-page pv-wildbattle-page">
    <div class="pv-page-head pv-wildbattle-head">
        <div><span class="pv-eyebrow">WILD BATTLE // ENCOUNTER LOCK <?= pv_h(strtoupper(substr((string)$state['id'],0,8))) ?></span><h1>Wild <?= pv_h((string)$wild['display_name']) ?></h1><p class="pv-subtle">Turn <?= (int)$state['turn'] ?> · <?= pv_h($locationLabel) ?> · encounter channel stable</p></div>
        <a class="pv-button pv-button-secondary" href="<?= pv_h($returnUrl) ?>">Return to Map</a>
    </div>

    <?php if ($error !== ''): ?><div class="pv-alert pv-alert-error"><?= pv_h($error) ?></div><?php endif; ?>

    <?php if (!$battleActive && is_array($state['result'] ?? null)): $result=$state['result']; ?>
        <div class="pv-wild-result pv-wild-result-<?= pv_h($status) ?>">
            <span><?= $status === 'captured' ? 'CAPTURE COMPLETE' : ($status === 'won' ? 'VICTORY CONFIRMED' : ($status === 'lost' ? 'TEAM DEFEATED' : 'ENCOUNTER CLOSED')) ?></span>
            <h2><?= pv_h((string)$result['title']) ?></h2>
            <p><?= pv_h((string)$result['message']) ?></p>
            <?php if ((int)($result['exp'] ?? 0) > 0 || (int)($result['money'] ?? 0) > 0): ?><div class="pv-wild-rewards"><b>+<?= number_format((int)($result['exp'] ?? 0)) ?> EXP</b><b>+₽<?= number_format((int)($result['money'] ?? 0)) ?></b></div><?php endif; ?>
            <div class="pv-wild-result-actions"><a class="pv-button" href="<?= pv_h($returnUrl) ?>">Continue Exploring</a><?php if($status==='captured' && (int)($result['pokemon_id']??0)>0):?><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('pokedex.php?pid='.(int)$result['pokemon_id']))?>">View Captured Pokémon</a><?php endif;?></div>
        </div>
    <?php endif; ?>

    <div class="pv-wild-arena <?= !$battleActive ? 'is-complete' : '' ?>" data-pv-battle-fx-stage>
        <article class="pv-wild-fighter pv-wild-enemy">
            <div class="pv-wild-fighter-hud">
                <div><small>WILD TARGET</small><h2><?= pv_h((string)$wild['display_name']) ?></h2><span>Lv. <?= (int)$wild['level'] ?> · <?= pv_h((string)$wild['type1']) ?><?= trim((string)$wild['type2'])!==''?' / '.pv_h((string)$wild['type2']):'' ?></span></div>
                <strong><?= (int)$wild['hp'] ?> / <?= (int)$wild['max_hp'] ?> HP</strong>
            </div>
            <div class="pv-wild-hp"><i style="width:<?= $wildHpPct ?>%"></i></div>
            <div class="pv-wild-sprite-zone" data-pv-fighter="enemy"><span class="pv-wild-scan-ring"></span><img src="<?= pv_h($wildSprite) ?>" alt="Wild <?= pv_h((string)$wild['display_name']) ?>"></div>
        </article>

        <div class="pv-wild-versus"><span>VS</span><i></i></div>

        <article class="pv-wild-fighter pv-wild-player">
            <div class="pv-wild-sprite-zone" data-pv-fighter="player"><span class="pv-wild-scan-ring"></span><img src="<?= pv_h($playerSprite) ?>" alt="<?= pv_h((string)$active['name']) ?>"></div>
            <div class="pv-wild-fighter-hud">
                <div><small>ACTIVE PARTNER</small><h2><?= pv_h((string)$active['name']) ?></h2><span>Lv. <?= (int)$active['level'] ?> · <?= pv_h((string)$active['type1']) ?><?= trim((string)$active['type2'])!==''?' / '.pv_h((string)$active['type2']):'' ?></span></div>
                <strong><?= (int)$active['hp'] ?> / <?= (int)$active['max_hp'] ?> HP</strong>
            </div>
            <div class="pv-wild-hp"><i style="width:<?= $playerHpPct ?>%"></i></div>
        </article>
    </div>

    <div class="pv-wild-lower-grid">
        <section class="pv-wild-log-panel">
            <div class="pv-map-panel-label">BATTLE LOG</div>
            <div class="pv-wild-log" aria-live="polite">
            <?php foreach(array_reverse(array_slice((array)$state['log'],-8)) as $entry): ?>
                <div class="tone-<?=pv_h((string)($entry['tone']??'neutral'))?>"><i></i><span><?=pv_h((string)$entry['text'])?></span></div>
            <?php endforeach; ?>
            </div>
        </section>

        <section class="pv-wild-control-panel">
            <div class="pv-map-panel-label">BATTLE COMMAND</div>
            <?php if ($battleActive): ?>
                <div class="pv-wild-command-tabs" data-pv-battle-tabs>
                    <button type="button" class="active" data-pv-battle-tab="moves">Fight</button>
                    <button type="button" data-pv-battle-tab="items">Items</button>
                    <button type="button" data-pv-battle-tab="team">Team</button>
                    <button type="button" data-pv-battle-tab="run">Run</button>
                </div>
                <div class="pv-wild-command-view active" data-pv-battle-view="moves">
                    <div class="pv-wild-move-grid">
                    <?php foreach((array)$active['moves'] as $move): $md=pv_wild_move_data($db,(string)$move,(string)$active['type1']); ?>
                        <form method="post" action="<?=pv_h(pv_url('wildbattle.php'))?>"><?=pv_csrf_field()?><input type="hidden" name="battle_id" value="<?=pv_h((string)$state['id'])?>"><input type="hidden" name="battle_turn" value="<?= (int)$state['turn'] ?>"><input type="hidden" name="battle_action" value="attack"><input type="hidden" name="move" value="<?=pv_h((string)$move)?>"><button type="submit"><small><?=pv_h((string)$md['type'])?> · <?= (int)$md['power'] ?> PWR</small><strong><?=pv_h((string)$move)?></strong><span><?= (int)$md['accuracy'] ?>% ACC</span></button></form>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="pv-wild-command-view" data-pv-battle-view="items">
                    <div class="pv-wild-item-grid">
                    <?php foreach(pv_wild_item_catalog() as $item=>$meta): $count=(int)($inventory[$meta['column']]??0); ?>
                        <form method="post" action="<?=pv_h(pv_url('wildbattle.php'))?>"><?=pv_csrf_field()?><input type="hidden" name="battle_id" value="<?=pv_h((string)$state['id'])?>"><input type="hidden" name="battle_turn" value="<?= (int)$state['turn'] ?>"><input type="hidden" name="battle_action" value="heal"><input type="hidden" name="item" value="<?=pv_h($item)?>"><button type="submit" <?=$count>0?'':'disabled'?>><?=pv_h($item)?><small><?=number_format($count)?> owned · +<?= (int)$meta['heal'] ?> HP</small></button></form>
                    <?php endforeach; ?>
                    <?php foreach(pv_wild_ball_catalog() as $ball=>$meta): $count=(int)($inventory[$meta['column']]??0); ?>
                        <form method="post" action="<?=pv_h(pv_url('wildbattle.php'))?>"><?=pv_csrf_field()?><input type="hidden" name="battle_id" value="<?=pv_h((string)$state['id'])?>"><input type="hidden" name="battle_turn" value="<?= (int)$state['turn'] ?>"><input type="hidden" name="battle_action" value="ball"><input type="hidden" name="ball" value="<?=pv_h($ball)?>"><button type="submit" <?=$count>0?'':'disabled'?>><?=pv_h($ball)?><small><?=number_format($count)?> owned · Capture</small></button></form>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="pv-wild-command-view" data-pv-battle-view="team">
                    <div class="pv-wild-team-grid">
                    <?php foreach((array)$state['team'] as $member): $pct=max(0,min(100,(int)round(((int)$member['hp']/max(1,(int)$member['max_hp']))*100))); ?>
                        <form method="post" action="<?=pv_h(pv_url('wildbattle.php'))?>"><?=pv_csrf_field()?><input type="hidden" name="battle_id" value="<?=pv_h((string)$state['id'])?>"><input type="hidden" name="battle_turn" value="<?= (int)$state['turn'] ?>"><input type="hidden" name="battle_action" value="switch"><input type="hidden" name="pokemon_id" value="<?= (int)$member['id'] ?>"><button type="submit" <?=((int)$member['hp']<=0 || (int)$member['id']===$activeId)?'disabled':''?>><img src="<?=pv_h(pv_pokemon_sprite((string)$member['name']))?>" alt=""><span><strong><?=pv_h((string)$member['name'])?></strong><small>Lv. <?= (int)$member['level'] ?> · <?= (int)$member['hp'] ?>/<?= (int)$member['max_hp'] ?> HP</small><i><b style="width:<?=$pct?>%"></b></i></span></button></form>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="pv-wild-command-view" data-pv-battle-view="run">
                    <div class="pv-wild-run-box"><h3>Withdraw from encounter?</h3><p>You can safely return to the current map. The wild Pokémon will be released from the encounter scanner.</p><form method="post" action="<?=pv_h(pv_url('wildbattle.php'))?>"><?=pv_csrf_field()?><input type="hidden" name="battle_id" value="<?=pv_h((string)$state['id'])?>"><input type="hidden" name="battle_turn" value="<?= (int)$state['turn'] ?>"><input type="hidden" name="battle_action" value="run"><button class="pv-button pv-button-danger" type="submit">Run from Battle</button></form></div>
                </div>
            <?php else: ?>
                <div class="pv-wild-command-complete"><strong>Encounter complete</strong><p>Rewards and captures have already been recorded for this encounter.</p><a class="pv-button" href="<?=pv_h($returnUrl)?>">Return to Map</a></div>
            <?php endif; ?>
        </section>
    </div>
</section>
</main>
</div>
<script type="application/json" id="pv-wild-battle-fx"><?=json_encode($battleFx, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{const root=document.querySelector('[data-pv-battle-tabs]');if(!root)return;const buttons=[...root.querySelectorAll('[data-pv-battle-tab]')];const views=[...document.querySelectorAll('[data-pv-battle-view]')];buttons.forEach(btn=>btn.addEventListener('click',()=>{buttons.forEach(b=>b.classList.toggle('active',b===btn));views.forEach(v=>v.classList.toggle('active',v.dataset.pvBattleView===btn.dataset.pvBattleTab));}));});
</script>
<?php pv_page_end(); ?>
