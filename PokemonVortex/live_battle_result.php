<?php
require_once __DIR__ . '/kick.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/live_battle_settlement.php';
require_once __DIR__ . '/includes/live_battle_runtime.php';

$uid = max(0, (int)($_SESSION['myid'] ?? 0));
if ($uid <= 0 || (int)($_SESSION['access'] ?? 0) !== 9) pv_redirect('login.php?goawaxP=1');

$battleId = max(0, (int)($_GET['match'] ?? $_SESSION['live_last_result_battle_id'] ?? 0));
if ($battleId <= 0) pv_redirect('live_battle_arena.php');

$db = pv_db();
$stmt = $db->prepare('SELECT lb.*,m1.username username_1,m2.username username_2 FROM live_battle lb JOIN members m1 ON m1.id=lb.uid_1 JOIN members m2 ON m2.id=lb.uid_2 WHERE lb.id=? AND (lb.uid_1=? OR lb.uid_2=?) LIMIT 1');
$stmt->bind_param('iii', $battleId, $uid, $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) pv_redirect('live_battle_arena.php?stale=1');

$slot = (int)$row['uid_1'] === $uid ? 1 : 2;
$otherSlot = $slot === 1 ? 2 : 1;
$settled = (int)($row['settled_'.$slot] ?? 0) === 1;
$outcome = (string)($row['outcome_'.$slot] ?? '');
$rewardExp = max(0, (int)($row['reward_exp_'.$slot] ?? 0));
$rewardMoney = max(0, (int)($row['reward_money_'.$slot] ?? 0));
$settledAt = max(0, (int)($row['settled_at_'.$slot] ?? 0));
$opponentName = (string)($row['username_'.$otherSlot] ?? 'Opponent');
$opponentSettled = (int)($row['settled_'.$otherSlot] ?? 0) === 1;

// A result URL is a safe recovery point. Schema-2 terminal state is itself the
// outcome authority, so either browser can reconnect and settle independently.
// Older rows retain complementary peer-settlement recovery for compatibility.
if (!$settled) {
    $expectedOutcome = '';
    $runtimeState = pv_live_runtime_decode((string)($row['_2'] ?? ''));
    if (is_array($runtimeState) && ($runtimeState['phase'] ?? '') === 'complete') {
        $expectedOutcome = (int)($runtimeState['winner_slot'] ?? 0) === $slot ? 'win' : 'loss';
    } elseif ($opponentSettled) {
        $peerOutcome = (string)($row['outcome_'.$otherSlot] ?? '');
        if (in_array($peerOutcome, ['win','loss'], true)) $expectedOutcome = $peerOutcome === 'win' ? 'loss' : 'win';
    }
    if (in_array($expectedOutcome, ['win','loss'], true)) {
        $opponentId = (int)$row['uid_'.$otherSlot];
        try {
            if (is_array($runtimeState)) {
                pv_live_runtime_assert_state($runtimeState, (int)$row['uid_1'], (int)$row['uid_2']);
                pv_live_runtime_project_session($runtimeState, $slot);
            }
            pv_live_settle_result($db, $battleId, $slot, $otherSlot, $uid, $opponentId, $expectedOutcome);
            $stmt = $db->prepare('SELECT lb.*,m1.username username_1,m2.username username_2 FROM live_battle lb JOIN members m1 ON m1.id=lb.uid_1 JOIN members m2 ON m2.id=lb.uid_2 WHERE lb.id=? AND (lb.uid_1=? OR lb.uid_2=?) LIMIT 1');
            $stmt->bind_param('iii', $battleId, $uid, $uid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: $row;
            $stmt->close();
            $settled = (int)($row['settled_'.$slot] ?? 0) === 1;
            $outcome = (string)($row['outcome_'.$slot] ?? '');
            $rewardExp = max(0, (int)($row['reward_exp_'.$slot] ?? 0));
            $rewardMoney = max(0, (int)($row['reward_money_'.$slot] ?? 0));
            $settledAt = max(0, (int)($row['settled_at_'.$slot] ?? 0));
            $opponentSettled = (int)($row['settled_'.$otherSlot] ?? 0) === 1;
        } catch (Throwable $e) {
            pv_log('Live battle result reconciliation deferred for match ' . $battleId . ', user ' . $uid . ': ' . $e->getMessage());
        }
    }
}

$_SESSION['live_last_result_battle_id'] = $battleId;

pv_page_start('Live Battle Result', 'battle_select.php', true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('battle_select.php'); ?>
<main class="pv-main-column">
<section class="pv-page pv-live-result-modern">
    <div class="pv-page-head">
        <div><span class="pv-eyebrow">LIVE BATTLE // MATCH #<?=number_format($battleId)?></span><h1>Battle Result</h1><p class="pv-subtle">Final result for your match against <?=pv_h($opponentName)?>.</p></div>
        <a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('live_battle_arena.php'))?>">Live Battle Arena</a>
    </div>

    <?php if(!$settled): ?>
        <div class="pv-panel">
            <div class="pv-empty-state"><strong>This match is still finishing.</strong><span>Return to the active battle to finish the match. Rewards appear after the battle is complete.</span></div>
            <div class="pv-actions"><a class="pv-button" href="<?=pv_h(pv_url('live_battle.php'))?>">Return to Battle</a></div>
        </div>
    <?php else: ?>
        <div class="pv-panel nxt-outcome-panel">
            <div class="pv-section-heading"><div><span><?= $outcome==='win' ? 'VICTORY CONFIRMED' : 'DEFEAT RECORDED' ?></span><h2><?= $outcome==='win' ? 'You won the live battle' : 'The live battle is complete' ?></h2></div><small><?= $settledAt ? pv_h(date('Y-m-d H:i:s', $settledAt)) . ' UTC' : 'settled' ?></small></div>
            <div class="pv-live-result-grid">
                <article><small>OPPONENT</small><strong><?=pv_h($opponentName)?></strong><span>Trainer-vs-trainer match</span></article>
                <article><small>OUTCOME</small><strong><?=pv_h(strtoupper($outcome ?: 'COMPLETE'))?></strong><span>Match #<?=number_format($battleId)?></span></article>
                <?php if($outcome==='win'): ?>
                <article><small>POKÉMON EXP</small><strong><?=number_format($rewardExp)?></strong><span>awarded to each participating Pokémon</span></article>
                <article><small>MONEY</small><strong>₽<?=number_format($rewardMoney)?></strong><span>added to your account</span></article>
                <?php else: ?>
                <article><small>REWARDS</small><strong>—</strong><span>Defeat recorded; no victory reward issued</span></article>
                <?php endif; ?>
            </div>
            <div class="pv-flash <?= $opponentSettled ? 'success' : 'info' ?>"><?= $opponentSettled ? 'Both trainers have finished this match. The challenge is complete.' : 'Your result is complete. The other trainer may still be finishing their side of the match.' ?></div>
            <div class="pv-actions">
                <a class="pv-button" href="<?=pv_h(pv_url('live_battle_arena.php'))?>">Return to Arena</a>
                <a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('change_team.php'))?>">Change Team</a>
                <a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('your_pokemon.php'))?>">Your Pokémon</a>
            </div>
        </div>
    <?php endif; ?>
</section>
</main>
</div>
<?php pv_page_end(); ?>
