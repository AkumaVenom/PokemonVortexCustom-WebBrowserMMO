<?php
require_once __DIR__ . '/gameplay.php';

/**
 * Durable Live PvP result settlement.
 *
 * The recovered battle engine keeps transient HP/turn state in the PHP session,
 * but reward/loss settlement must be database-idempotent. Each participant may
 * settle a specific live_battle row only once, even if the browser retries the
 * final request or reconnects after the DB commit.
 */

function pv_live_reward_money(int $experience): int {
    $experience = max(0, $experience);
    if ($experience === 0) return 0;
    $roll = random_int(1, 1000);
    if ($roll <= 500) return (int)round($experience * 0.5);
    if ($roll <= 800) return (int)round($experience * 1.2);
    if ($roll <= 950) return (int)round($experience * 1.5);
    if ($roll <= 994) return (int)round($experience * 2.0);
    if ($roll <= 999) return (int)round($experience * 4.0);
    return (int)round($experience * 7.5);
}

function pv_live_session_participants(string $prefix, int $count, bool $onlyParticipated = false): array {
    $rows = [];
    $count = min(6, max(0, $count));
    for ($slot = 1; $slot <= $count; $slot++) {
        $state = $_SESSION[$prefix . $slot] ?? null;
        if (!is_array($state)) continue;
        $id = max(0, (int)($state[1] ?? 0));
        if ($id <= 0) continue;
        if ($onlyParticipated && (int)($state[13] ?? 0) !== 1) continue;
        $rows[] = [
            'id' => $id,
            'level' => max(1, (int)($state[4] ?? 1)),
            'name' => trim((string)($state[0] ?? '')),
        ];
    }
    return $rows;
}

function pv_live_clear_combat_session(bool $keepLiveIdentity = true): void {
    $keys = [
        'opponent_profile','s1','s2','s3','s4','s5','s6','ops1','ops2','ops3','ops4','ops5','ops6',
        'position','your_profile','y_p','attack_short','your_attack','numero','numero1','pos','live_initialized_battle_id'
    ];
    foreach ($keys as $key) unset($_SESSION[$key]);
    if (!$keepLiveIdentity) unset($_SESSION['live']);
}

/**
 * @return array{outcome:string,reward_exp:int,reward_money:int,settled_at:int,already_settled:bool}
 */
function pv_live_settle_result(mysqli $db, int $battleId, int $userSlot, int $opponentSlot, int $userId, int $opponentId, string $outcome): array {
    if (!in_array($userSlot, [1, 2], true) || !in_array($opponentSlot, [1, 2], true) || $userSlot === $opponentSlot) {
        throw new RuntimeException('Invalid live battle participant role.');
    }
    if ($battleId <= 0 || $userId <= 0 || $opponentId <= 0 || !in_array($outcome, ['win', 'loss'], true)) {
        throw new RuntimeException('Invalid live battle result.');
    }

    $settledColumn = 'settled_' . $userSlot;
    $outcomeColumn = 'outcome_' . $userSlot;
    $expColumn = 'reward_exp_' . $userSlot;
    $moneyColumn = 'reward_money_' . $userSlot;
    $settledAtColumn = 'settled_at_' . $userSlot;

    $db->begin_transaction();
    try {
        $otherSettledColumn = 'settled_' . $opponentSlot;
        $otherOutcomeColumn = 'outcome_' . $opponentSlot;
        $sql = "SELECT {$settledColumn} AS settled, {$outcomeColumn} AS outcome, {$expColumn} AS reward_exp, {$moneyColumn} AS reward_money, {$settledAtColumn} AS settled_at, {$otherSettledColumn} AS other_settled, {$otherOutcomeColumn} AS other_outcome FROM live_battle WHERE id=? AND uid_{$userSlot}=? AND uid_{$opponentSlot}=? FOR UPDATE";
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Could not lock the live battle result.');
        $stmt->bind_param('iii', $battleId, $userId, $opponentId);
        $stmt->execute();
        $battle = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$battle) throw new RuntimeException('This live battle is no longer available.');

        if ((int)($battle['settled'] ?? 0) === 1) {
            $db->commit();
            return [
                'outcome' => (string)($battle['outcome'] ?? $outcome),
                'reward_exp' => max(0, (int)($battle['reward_exp'] ?? 0)),
                'reward_money' => max(0, (int)($battle['reward_money'] ?? 0)),
                'settled_at' => max(0, (int)($battle['settled_at'] ?? 0)),
                'already_settled' => true,
            ];
        }

        // The first committed settlement becomes the durable match result.
        // The second side must be complementary even if its browser/session snapshot
        // is stale, preventing impossible win/win or loss/loss receipts.
        if ((int)($battle['other_settled'] ?? 0) === 1) {
            $otherOutcome = (string)($battle['other_outcome'] ?? '');
            if (in_array($otherOutcome, ['win','loss'], true)) {
                $expectedOutcome = $otherOutcome === 'win' ? 'loss' : 'win';
                if ($outcome !== $expectedOutcome) {
                    pv_log('Live battle settlement normalized conflicting outcome for match ' . $battleId . ', user ' . $userId . '.');
                    $outcome = $expectedOutcome;
                }
            }
        }

        $now = time();
        $rewardExp = 0;
        $rewardMoney = 0;

        // Lock the member row so money/battle/loss counters and the settlement
        // marker commit atomically with any Pokémon experience changes.
        $stmt = $db->prepare('SELECT id,eb FROM members WHERE id=? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Could not lock trainer progression.');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$member) throw new RuntimeException('Trainer progression is unavailable.');

        if ($outcome === 'win') {
            $ownCount = max(0, (int)($_SESSION['your_profile'][2] ?? 0));
            $opponentCount = max(0, (int)($_SESSION['opponent_profile'][2] ?? 0));
            $participants = pv_live_session_participants('s', $ownCount, true);
            $opponents = pv_live_session_participants('ops', $opponentCount, false);
            if ($participants === [] || $opponents === []) {
                throw new RuntimeException('The live battle reward snapshot is incomplete.');
            }

            $userLevelTotal = array_sum(array_column($participants, 'level'));
            $opponentLevelTotal = array_sum(array_column($opponents, 'level'));
            $multiplier = max(0.1, min(10.0, (float)($member['eb'] ?? 1)));
            $rewardExp = (int)round(pv_safe_divide((float)$opponentLevelTotal, max(1, $userLevelTotal), 0.0) * 500 * $multiplier);
            $rewardExp = max(0, min(10000000, $rewardExp));
            $rewardMoney = max(0, min(1000000000, pv_live_reward_money($rewardExp)));

            $ids = array_values(array_unique(array_map(static fn($row) => (int)$row['id'], $participants)));
            if ($ids === []) throw new RuntimeException('No eligible Pokémon participated in the live battle.');
            $idList = implode(',', array_map('intval', $ids));
            $stmt = $db->prepare("SELECT id,lvl,exp FROM pokemon WHERE owner=? AND id IN ({$idList}) FOR UPDATE");
            if (!$stmt) throw new RuntimeException('Could not lock participating Pokémon.');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $ownedRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $ownedIds = array_map(static fn($row) => (int)$row['id'], $ownedRows);
            sort($ownedIds);
            $expectedIds = $ids;
            sort($expectedIds);
            if ($ownedIds !== $expectedIds) throw new RuntimeException('A participating Pokémon is no longer owned by this trainer.');

            $updatePokemon = $db->prepare('UPDATE pokemon SET exp=?, lvl=? WHERE id=? AND owner=?');
            if (!$updatePokemon) throw new RuntimeException('Could not prepare Pokémon progression.');
            foreach ($ownedRows as $pokemon) {
                $newExp = max(0, (int)$pokemon['exp']) + $rewardExp;
                $newLevel = min(100, max(1, (int)floor($newExp / 500)));
                if ((int)$pokemon['lvl'] >= 100) $newLevel = 100;
                $pid = (int)$pokemon['id'];
                $updatePokemon->bind_param('iiii', $newExp, $newLevel, $pid, $userId);
                if (!$updatePokemon->execute() || $updatePokemon->affected_rows < 0) throw new RuntimeException('Could not apply Pokémon experience.');
            }
            $updatePokemon->close();

            $stmt = $db->prepare('UPDATE members SET btime=?, battle=battle+1, money=money+? WHERE id=?');
            if (!$stmt) throw new RuntimeException('Could not prepare trainer victory progression.');
            $stmt->bind_param('iii', $now, $rewardMoney, $userId);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Could not apply trainer victory progression.');
            $stmt->close();
        } else {
            $stmt = $db->prepare('UPDATE members SET btime=?, losses=losses+1 WHERE id=?');
            if (!$stmt) throw new RuntimeException('Could not prepare trainer defeat progression.');
            $stmt->bind_param('ii', $now, $userId);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Could not apply trainer defeat progression.');
            $stmt->close();
        }

        $stmt = $db->prepare("UPDATE live_battle SET {$settledColumn}=1, {$outcomeColumn}=?, {$expColumn}=?, {$moneyColumn}=?, {$settledAtColumn}=? WHERE id=? AND uid_{$userSlot}=? AND uid_{$opponentSlot}=? AND {$settledColumn}=0");
        if (!$stmt) throw new RuntimeException('Could not prepare live battle settlement marker.');
        $stmt->bind_param('siiiiii', $outcome, $rewardExp, $rewardMoney, $now, $battleId, $userId, $opponentId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Live battle result changed before it could be settled.');
        $stmt->close();

        // Once both trainers have independently settled, close the accepted
        // challenge record as completed. The battle row remains available as a
        // durable result receipt until normal retention cleanup.
        $stmt = $db->prepare('SELECT settled_1,settled_2 FROM live_battle WHERE id=? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Could not verify Live Battle completion.');
        $stmt->bind_param('i', $battleId);
        $stmt->execute();
        $settlementState = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        if ((int)($settlementState['settled_1'] ?? 0) === 1 && (int)($settlementState['settled_2'] ?? 0) === 1) {
            $stmt = $db->prepare("UPDATE live_battle_challenges SET status='completed', responded_at=? WHERE battle_id=? AND status='accepted'");
            if ($stmt) {
                $stmt->bind_param('ii', $now, $battleId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $db->commit();

        // Settlement is durable once the transaction commits. Progression
        // recalculation is important, but a secondary cache/recalculation
        // failure must never make a committed PvP reward look uncommitted to
        // the caller (which could encourage a retry of an already-settled row).
        if ($outcome === 'win') {
            try {
                pv_recalculate_trainer_progress($db, $userId, true);
            } catch (Throwable $recalcError) {
                pv_log('live_battle_progress_recalc_failed', [
                    'battle_id' => $battleId,
                    'user_id' => $userId,
                    'error' => $recalcError->getMessage(),
                ]);
            }
        }
        pv_server_event('PVP', 'Live PvP result settled', [
            'battle_id'=>$battleId,
            'opponent_uid'=>$opponentId,
            'outcome'=>$outcome,
            'reward_exp'=>$rewardExp,
            'reward_money'=>$rewardMoney,
        ]);
        return [
            'outcome' => $outcome,
            'reward_exp' => $rewardExp,
            'reward_money' => $rewardMoney,
            'settled_at' => $now,
            'already_settled' => false,
        ];
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }
}
