<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Ranked Rival Network runtime (v25).
 *
 * This layer is intentionally separate from the recovered battle engines. The
 * existing Trainer Snapshot / Live battle code remains authoritative for combat,
 * animation, rewards and Pokémon progression. Rival Network only owns ladder
 * rating, protection shields, retaliation rights and the autonomous ranked
 * simulation used while no player is actively watching a battle.
 */

const PV_RIVAL_START_RATING = 1000;
const PV_RIVAL_SHIELD_SECONDS = 900;       // 15 minutes after a ranked attack.
const PV_RIVAL_RETALIATION_SECONDS = 86400; // 24 hours to answer an attack.
const PV_RIVAL_BOT_ATTACK_COOLDOWN = 600; // Regular-rival default; the shared scheduler supplies profile/contender cooldowns.
const PV_RIVAL_AUTONOMOUS_HISTORY_SECONDS = 604800; // Seven-day rolling AI-only history keeps higher activity sustainable.

function pv_rival_table_exists(mysqli $db, string $table): bool
{
    static $cache = [];
    if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
    $key = spl_object_id($db) . ':' . strtolower($table);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $cache[$key] = $ok;
}

function pv_rival_ready(mysqli $db): bool
{
    return pv_rival_table_exists($db, 'trainer_rank_state')
        && pv_rival_table_exists($db, 'rival_battles')
        && pv_rival_table_exists($db, 'rival_retaliations')
        && pv_rival_table_exists($db, 'ai_activity');
}

function pv_rival_seed_rating_from_row(array $member, int $botIndex = 0): int
{
    // Ranked competition starts from one neutral rating for every human and AI.
    // Legacy Battle Arena progression and bot identity must never grant free
    // ladder standing before a Ranked Rival result has actually been settled.
    return PV_RIVAL_START_RATING;
}

function pv_rival_ensure_state(mysqli $db, int $userId): bool
{
    if ($userId <= 0 || !pv_rival_ready($db)) return false;
    $stmt = $db->prepare('SELECT 1 FROM trainer_rank_state WHERE user_id=? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if ($exists) return true;

    $stmt = $db->prepare('SELECT m.battle,m.wins,m.losses,COALESCE(b.bot_index,0) bot_index FROM members m LEFT JOIN bot_trainers b ON b.user_id=m.id AND b.enabled=1 WHERE m.id=? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    $rating = pv_rival_seed_rating_from_row($row, (int)($row['bot_index'] ?? 0));
    $now = time();
    $stmt = $db->prepare('INSERT IGNORE INTO trainer_rank_state (user_id,rating,peak_rating,ranked_wins,ranked_losses,current_streak,best_streak,shield_until,shield_source_user_id,last_ranked_at,last_attack_at,last_defense_at,updated_at) VALUES (?,?,?,0,0,0,0,0,0,0,0,0,?)');
    if (!$stmt) return false;
    $stmt->bind_param('iiii', $userId, $rating, $rating, $now);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function pv_rival_ensure_all_states(mysqli $db): int
{
    if (!pv_rival_ready($db)) return 0;
    // Every trainer enters Ranked Rival competition at the same neutral 1,000 RP.
    // Legacy member progression and bot index are intentionally excluded.
    $sql = "INSERT IGNORE INTO trainer_rank_state
        (user_id,rating,peak_rating,ranked_wins,ranked_losses,current_streak,best_streak,shield_until,shield_source_user_id,last_ranked_at,last_attack_at,last_defense_at,updated_at)
        SELECT m.id,1000,1000,0,0,0,0,0,0,0,0,0,UNIX_TIMESTAMP()
        FROM members m
        LEFT JOIN trainer_rank_state existing ON existing.user_id=m.id
        WHERE existing.user_id IS NULL";
    if (!$db->query($sql)) return 0;
    $created = max(0, (int)$db->affected_rows);

    // Repair only never-played legacy seed rows from pre-v25.2.6 installs. A
    // trainer with any authoritative ranked W/L history keeps their earned RP.
    // This makes the repair safe to run on every request and non-destructive to
    // already-settled competitive results.
    @$db->query('UPDATE trainer_rank_state SET rating=1000,peak_rating=1000,updated_at=UNIX_TIMESTAMP() WHERE ranked_wins=0 AND ranked_losses=0 AND last_ranked_at=0 AND (rating<>1000 OR peak_rating<>1000)');
    return $created;
}

function pv_rival_state(mysqli $db, int $userId): ?array
{
    if ($userId <= 0 || !pv_rival_ready($db)) return null;
    pv_rival_ensure_state($db, $userId);
    $stmt = $db->prepare('SELECT * FROM trainer_rank_state WHERE user_id=? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function pv_rival_tier(int $rating): array
{
    if ($rating >= 1450) return ['name'=>'Champion','short'=>'CHAMPION','ball'=>'images/items/Master Ball.png','class'=>'champion'];
    if ($rating >= 1250) return ['name'=>'Master Ball','short'=>'MASTER','ball'=>'images/items/Master Ball.png','class'=>'master'];
    if ($rating >= 1100) return ['name'=>'Ultra Ball','short'=>'ULTRA','ball'=>'images/items/Ultra Ball.png','class'=>'ultra'];
    if ($rating >= 950) return ['name'=>'Great Ball','short'=>'GREAT','ball'=>'images/items/Great Ball.png','class'=>'great'];
    return ['name'=>'Poké Ball','short'=>'POKÉ','ball'=>'images/items/Poke Ball.png','class'=>'poke'];
}

function pv_rival_rank_position(mysqli $db, int $userId, int $rating, ?int $rankedWins = null, ?int $rankedLosses = null): int
{
    if (!pv_rival_ready($db) || $userId <= 0) return 0;
    if ($rankedWins === null || $rankedLosses === null) {
        $stmt = $db->prepare('SELECT ranked_wins,ranked_losses FROM trainer_rank_state WHERE user_id=? LIMIT 1');
        if (!$stmt) return 0;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        if ($rankedWins === null) $rankedWins = max(0, (int)($row['ranked_wins'] ?? 0));
        if ($rankedLosses === null) $rankedLosses = max(0, (int)($row['ranked_losses'] ?? 0));
    }
    $rankedWins = max(0, (int)$rankedWins);
    $rankedLosses = max(0, (int)$rankedLosses);

    // Rating points determine global position for humans and AI alike. Wins,
    // fewer losses and finally user id resolve equal-rating ties consistently
    // with Rankings and Top AI Rivals. Only active-team trainers occupy a rank.
    $stmt = $db->prepare('SELECT COUNT(*)+1 AS rank_pos FROM trainer_rank_state rs JOIN members m ON m.id=rs.user_id WHERE COALESCE(m.s1,0)>0 AND (rs.rating>? OR (rs.rating=? AND rs.ranked_wins>?) OR (rs.ranked_wins=? AND rs.rating=? AND rs.ranked_losses<?) OR (rs.ranked_wins=? AND rs.rating=? AND rs.ranked_losses=? AND rs.user_id<?))');
    if (!$stmt) return 0;
    $stmt->bind_param('iiiiiiiiii', $rating, $rating, $rankedWins, $rankedWins, $rating, $rankedLosses, $rankedWins, $rating, $rankedLosses, $userId);
    $stmt->execute();
    $rank = max(1, (int)($stmt->get_result()->fetch_assoc()['rank_pos'] ?? 1));
    $stmt->close();
    return $rank;
}

function pv_rival_has_team(mysqli $db, int $userId): bool
{
    if ($userId <= 0) return false;
    $stmt = $db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $m = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$m) return false;
    $ids = [];
    for ($i=1; $i<=6; $i++) if ((int)($m['s'.$i] ?? 0) > 0) $ids[] = (int)$m['s'.$i];
    if ($ids === []) return false;
    $idList = implode(',', array_map('intval', array_unique($ids)));
    $result = $db->query('SELECT COUNT(*) c FROM pokemon WHERE CAST(owner AS UNSIGNED)='.(int)$userId.' AND id IN ('.$idList.')');
    if (!$result) return false;
    $count = (int)($result->fetch_assoc()['c'] ?? 0);
    $result->free();
    return $count > 0;
}

function pv_rival_team_power(mysqli $db, int $userId): float
{
    if ($userId <= 0) return 1.0;
    $stmt = $db->prepare('SELECT s1,s2,s3,s4,s5,s6,totalexp,uniques FROM members WHERE id=? LIMIT 1');
    if (!$stmt) return 1.0;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $m = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $ids = [];
    for ($i=1; $i<=6; $i++) {
        $id = max(0, (int)($m['s'.$i] ?? 0));
        if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
    }
    if ($ids === []) return 1.0;
    $idList = implode(',', array_map('intval', $ids));
    $result = $db->query('SELECT COUNT(*) c,COALESCE(AVG(lvl),1) avg_lvl,COALESCE(MAX(lvl),1) max_lvl FROM pokemon WHERE CAST(owner AS UNSIGNED)='.(int)$userId.' AND id IN ('.$idList.')');
    $stats = $result ? ($result->fetch_assoc() ?: []) : [];
    if ($result) $result->free();
    $count = max(1, (int)($stats['c'] ?? 1));
    $avg = max(1.0, (float)($stats['avg_lvl'] ?? 1));
    $max = max(1.0, (float)($stats['max_lvl'] ?? 1));
    $collectionBonus = min(28.0, sqrt(max(0, (int)($m['uniques'] ?? 0))) * 1.25);
    return ($avg * 9.0) + ($max * 2.5) + ($count * 14.0) + $collectionBonus;
}

function pv_rival_shield_remaining(array $state, ?int $now = null): int
{
    $now ??= time();
    return max(0, (int)($state['shield_until'] ?? 0) - $now);
}

function pv_rival_open_retaliation(mysqli $db, int $retaliationId, int $defenderId, int $attackerId): ?array
{
    if ($retaliationId <= 0 || $defenderId <= 0 || $attackerId <= 0 || !pv_rival_ready($db)) return null;
    $now = time();
    $stmt = $db->prepare("SELECT * FROM rival_retaliations WHERE id=? AND defender_id=? AND attacker_id=? AND status='open' AND expires_at>? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('iiii', $retaliationId, $defenderId, $attackerId, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function pv_rival_begin_attack(mysqli $db, int $attackerId, int $defenderId, int $retaliationId = 0): array
{
    if (!pv_rival_retry_pending_result($db, $attackerId)) {
        throw new RuntimeException('Your completed ranked result is still waiting to save. Return to Trainer Rankings and try again.');
    }
    if (!pv_rival_ready($db)) throw new RuntimeException('The Rival Network database upgrade has not been applied yet.');
    if ($attackerId <= 0 || $defenderId <= 0 || $attackerId === $defenderId) throw new RuntimeException('Choose a valid rival.');
    if (!pv_rival_has_team($db, $attackerId)) throw new RuntimeException('Your active team needs at least one valid Pokémon before entering a ranked battle.');
    if (!pv_rival_has_team($db, $defenderId)) throw new RuntimeException('That rival does not currently have a valid active team.');
    pv_rival_ensure_state($db, $attackerId);
    pv_rival_ensure_state($db, $defenderId);

    $now = time();
    $retaliation = $retaliationId > 0 ? pv_rival_open_retaliation($db, $retaliationId, $attackerId, $defenderId) : null;
    $defenderState = pv_rival_state($db, $defenderId);
    if (!$retaliation && $defenderState && pv_rival_shield_remaining($defenderState, $now) > 0) {
        throw new RuntimeException('That rival is under temporary battle protection. Choose another target or use an available retaliation.');
    }

    $db->begin_transaction();
    try {
        // Lock both trainer states in deterministic id order. The fast protection
        // check above improves UX, while this locked check is authoritative and
        // prevents two concurrent launch requests from bypassing a newly applied
        // target shield.
        $firstId = min($attackerId, $defenderId);
        $secondId = max($attackerId, $defenderId);
        $stmt = $db->prepare('SELECT user_id,shield_until FROM trainer_rank_state WHERE user_id IN (?,?) ORDER BY user_id FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Could not lock the Rival Network target.');
        $stmt->bind_param('ii', $firstId, $secondId);
        $stmt->execute();
        $lockedRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $lockedStates = [];
        foreach ($lockedRows as $lockedRow) $lockedStates[(int)$lockedRow['user_id']] = $lockedRow;
        if (!isset($lockedStates[$attackerId], $lockedStates[$defenderId])) throw new RuntimeException('Rival rating state is incomplete.');
        if (!$retaliation && (int)$lockedStates[$defenderId]['shield_until'] > $now) {
            throw new RuntimeException('That rival just entered temporary battle protection. Choose another target or use an available retaliation.');
        }

        // Attacking from the protected state deliberately exposes the trainer again.
        $stmt = $db->prepare('UPDATE trainer_rank_state SET shield_until=0,shield_source_user_id=0,last_attack_at=?,updated_at=? WHERE user_id=?');
        if (!$stmt) throw new RuntimeException('Could not arm the ranked battle.');
        $stmt->bind_param('iii', $now, $now, $attackerId);
        if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Could not arm the ranked battle.'); }
        $stmt->close();

        if ($retaliation) {
            $stmt = $db->prepare("UPDATE rival_retaliations SET status='used',used_at=? WHERE id=? AND defender_id=? AND attacker_id=? AND status='open'");
            if (!$stmt) throw new RuntimeException('Could not consume the retaliation order.');
            $stmt->bind_param('iiii', $now, $retaliationId, $attackerId, $defenderId);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) { $stmt->close(); throw new RuntimeException('That retaliation is no longer available.'); }
            $stmt->close();
        }
        if (!$db->commit()) throw new RuntimeException('Could not commit ranked battle.');
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }

    $context = [
        'attacker_id'=>$attackerId,
        'defender_id'=>$defenderId,
        'retaliation_id'=>$retaliationId,
        'source'=>$retaliationId > 0 ? 'retaliation' : 'challenge',
        'phase'=>'armed',
        'started_at'=>$now,
        'nonce'=>bin2hex(random_bytes(18)),
    ];
    $_SESSION['pv_rival_battle'] = $context;
    return $context;
}

function pv_rival_session_context(int $attackerId, int $defenderId): ?array
{
    $ctx = $_SESSION['pv_rival_battle'] ?? null;
    if (!is_array($ctx)) return null;
    if ((int)($ctx['attacker_id'] ?? 0) !== $attackerId || (int)($ctx['defender_id'] ?? 0) !== $defenderId) return null;
    if (($ctx['phase'] ?? 'armed') === 'armed' && time() - (int)($ctx['started_at'] ?? 0) > 7200) {
        unset($_SESSION['pv_rival_battle']);
        return null;
    }
    return $ctx;
}

function pv_rival_elo_delta(int $ratingA, int $ratingB, bool $aWon): int
{
    $expectedA = 1.0 / (1.0 + pow(10.0, ($ratingB - $ratingA) / 400.0));
    $actualA = $aWon ? 1.0 : 0.0;
    return max(5, min(28, (int)round(abs(32.0 * ($actualA - $expectedA)))));
}

function pv_rival_log_ai_activity(mysqli $db, int $botUserId, string $category, string $headline, string $detail = '', int $relatedUserId = 0, int $ratingDelta = 0): void
{
    if ($botUserId <= 0 || !pv_rival_table_exists($db, 'ai_activity')) return;
    $category = substr(trim($category), 0, 32);
    $headline = substr(trim($headline), 0, 160);
    $detail = substr(trim($detail), 0, 255);
    if ($headline === '') return;
    $now = time();
    $stmt = $db->prepare('INSERT INTO ai_activity (bot_user_id,category,headline,detail,related_user_id,rating_delta,created_at) VALUES (?,?,?,?,?,?,?)');
    if (!$stmt) return;
    $stmt->bind_param('isssiii', $botUserId, $category, $headline, $detail, $relatedUserId, $ratingDelta, $now);
    @$stmt->execute();
    $stmt->close();
}

function pv_rival_record_match(mysqli $db, int $attackerId, int $defenderId, int $winnerId, string $source, int $retaliationId = 0, string $summary = '', bool $createRetaliation = true, string $sessionNonce = ''): array
{
    if (!pv_rival_ready($db)) throw new RuntimeException('The Rival Network is not available.');
    if ($attackerId <= 0 || $defenderId <= 0 || $attackerId === $defenderId || !in_array($winnerId, [$attackerId,$defenderId], true)) {
        throw new RuntimeException('Invalid ranked match result.');
    }
    pv_rival_ensure_state($db, $attackerId);
    pv_rival_ensure_state($db, $defenderId);

    $source = in_array($source, ['challenge','retaliation','autonomous'], true) ? $source : 'challenge';
    if ($sessionNonce !== '' && (!preg_match('/^[a-f0-9]{36}$/D', $sessionNonce) || $source === 'autonomous')) {
        throw new RuntimeException('Invalid ranked settlement identity.');
    }
    // Human-involved history is retained permanently. Its server-issued nonce
    // serves as a durable receipt without a schema migration. Both participant
    // locks below serialize duplicate receipts for this same battle.
    if ($sessionNonce !== '') $summary = 'Ranked Trainer Battle [' . $sessionNonce . ']';
    $now = time();
    $shieldUntil = $now + PV_RIVAL_SHIELD_SECONDS;
    $loserId = $winnerId === $attackerId ? $defenderId : $attackerId;

    $db->begin_transaction();
    try {
        // Lock in user-id order to avoid reciprocal attack deadlocks.
        $firstId = min($attackerId, $defenderId);
        $secondId = max($attackerId, $defenderId);
        $stmt = $db->prepare('SELECT * FROM trainer_rank_state WHERE user_id IN (?,?) ORDER BY user_id FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Could not lock Rival Network ratings.');
        $stmt->bind_param('ii', $firstId, $secondId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $states = [];
        foreach ($rows as $row) $states[(int)$row['user_id']] = $row;
        if (!isset($states[$attackerId], $states[$defenderId])) throw new RuntimeException('Rival rating state is incomplete.');
        if ($sessionNonce !== '') {
            $stmt = $db->prepare('SELECT * FROM rival_battles WHERE attacker_id=? AND defender_id=? AND summary=? ORDER BY id LIMIT 1 FOR UPDATE');
            if (!$stmt) throw new RuntimeException('Could not check ranked battle receipt.');
            $stmt->bind_param('iis', $attackerId, $defenderId, $summary);
            if (!$stmt->execute()) throw new RuntimeException('Could not read ranked battle receipt.');
            $receipt = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($receipt) {
                $result = pv_rival_match_result($receipt, $states[$attackerId], $states[$defenderId]);
                if (!$db->commit()) throw new RuntimeException('Could not finish ranked receipt lookup.');
                return $result + ['already_settled'=>true];
            }
        }
        // Autonomous operations resolve immediately and therefore must still
        // respect protection at settlement time. Player-launched battles are
        // allowed to finish after a valid launch even if another battle changes
        // the target's protection while the animated match is in progress.
        if ($source === 'autonomous' && (int)$states[$defenderId]['shield_until'] > $now) {
            throw new RuntimeException('Autonomous target entered battle protection before settlement.');
        }

        $aRating = max(100, (int)$states[$attackerId]['rating']);
        $dRating = max(100, (int)$states[$defenderId]['rating']);
        $attackerWon = $winnerId === $attackerId;
        $delta = pv_rival_elo_delta($aRating, $dRating, $attackerWon);
        $newA = max(100, $aRating + ($attackerWon ? $delta : -$delta));
        $newD = max(100, $dRating + ($attackerWon ? -$delta : $delta));

        $aStreak = $attackerWon ? max(1, (int)$states[$attackerId]['current_streak'] + 1) : 0;
        $dStreak = !$attackerWon ? max(1, (int)$states[$defenderId]['current_streak'] + 1) : 0;

        $stmt = $db->prepare('UPDATE trainer_rank_state SET rating=?,peak_rating=GREATEST(peak_rating,?),ranked_wins=ranked_wins+?,ranked_losses=ranked_losses+?,current_streak=?,best_streak=GREATEST(best_streak,?),shield_until=0,shield_source_user_id=0,last_ranked_at=?,last_attack_at=?,updated_at=? WHERE user_id=?');
        if (!$stmt) throw new RuntimeException('Could not update attacker ranking.');
        $aWinInc = $attackerWon ? 1 : 0; $aLossInc = $attackerWon ? 0 : 1;
        $aWins = max(0, (int)$states[$attackerId]['ranked_wins']) + $aWinInc;
        $aLosses = max(0, (int)$states[$attackerId]['ranked_losses']) + $aLossInc;
        $stmt->bind_param('iiiiiiiiii', $newA, $newA, $aWinInc, $aLossInc, $aStreak, $aStreak, $now, $now, $now, $attackerId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) { $stmt->close(); throw new RuntimeException('Could not update attacker ranking.'); }
        $stmt->close();

        // The attacked trainer receives protection regardless of win/loss. This is
        // the anti-chain-attack shield and also creates the one-use retaliation.
        $stmt = $db->prepare('UPDATE trainer_rank_state SET rating=?,peak_rating=GREATEST(peak_rating,?),ranked_wins=ranked_wins+?,ranked_losses=ranked_losses+?,current_streak=?,best_streak=GREATEST(best_streak,?),shield_until=?,shield_source_user_id=?,last_ranked_at=?,last_defense_at=?,updated_at=? WHERE user_id=?');
        if (!$stmt) throw new RuntimeException('Could not update defender ranking.');
        $dWinInc = $attackerWon ? 0 : 1; $dLossInc = $attackerWon ? 1 : 0;
        $dWins = max(0, (int)$states[$defenderId]['ranked_wins']) + $dWinInc;
        $dLosses = max(0, (int)$states[$defenderId]['ranked_losses']) + $dLossInc;
        $stmt->bind_param('iiiiiiiiiiii', $newD, $newD, $dWinInc, $dLossInc, $dStreak, $dStreak, $shieldUntil, $attackerId, $now, $now, $now, $defenderId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) { $stmt->close(); throw new RuntimeException('Could not update defender ranking.'); }
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO rival_battles (attacker_id,defender_id,winner_id,loser_id,source,attacker_rating_before,defender_rating_before,rating_delta,retaliation_id,summary,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        if (!$stmt) throw new RuntimeException('Could not persist ranked match history.');
        $summary = substr(trim($summary), 0, 255);
        $stmt->bind_param('iiiisiiiisi', $attackerId, $defenderId, $winnerId, $loserId, $source, $aRating, $dRating, $delta, $retaliationId, $summary, $now);
        if (!$stmt->execute()) { $error=$stmt->error; $stmt->close(); throw new RuntimeException('Could not persist ranked match history: '.$error); }
        $battleId = (int)$db->insert_id;
        $stmt->close();

        $newRetaliationId = 0;
        if ($createRetaliation) {
            $expiresAt = $now + PV_RIVAL_RETALIATION_SECONDS;
            $stmt = $db->prepare("INSERT INTO rival_retaliations (battle_id,defender_id,attacker_id,status,created_at,expires_at,used_at) VALUES (?,?,?,'open',?,?,0)");
            if (!$stmt) throw new RuntimeException('Could not create retaliation order.');
            $stmt->bind_param('iiiii', $battleId, $defenderId, $attackerId, $now, $expiresAt);
            if (!$stmt->execute()) { $error=$stmt->error; $stmt->close(); throw new RuntimeException('Could not create retaliation order: '.$error); }
            $newRetaliationId = (int)$db->insert_id;
            $stmt->close();
        }

        if (!$db->commit()) throw new RuntimeException('Could not commit ranked battle.');
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }

    // Preserve the legacy bot_trainers.player_* contract. Those counters are
    // explicitly labelled "LIVE PLAYER BATTLES" on bot_trainer.php and are owned
    // solely by pv_bot_settle_live_result(). Ranked Rival Network results already
    // have their own authoritative W/L telemetry in trainer_rank_state, so touching
    // the legacy counters here would mix two different battle modes and could make
    // a bot appear to have played the same human-facing battle twice.
    try {
        $botFlags = [];
        foreach ([$attackerId,$defenderId] as $id) {
            $stmt = $db->prepare('SELECT 1 FROM bot_trainers WHERE user_id=? AND enabled=1 LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $id); $stmt->execute(); $botFlags[$id] = (bool)$stmt->get_result()->fetch_row(); $stmt->close();
            }
        }

        if (!empty($botFlags[$attackerId])) {
            $targetName = 'a rival';
            $stmt = $db->prepare('SELECT username FROM members WHERE id=? LIMIT 1');
            if ($stmt) { $stmt->bind_param('i',$defenderId); $stmt->execute(); $targetName=(string)($stmt->get_result()->fetch_assoc()['username']??$targetName); $stmt->close(); }
            pv_rival_log_ai_activity(
                $db,
                $attackerId,
                'ranked',
                ($winnerId === $attackerId ? 'Won' : 'Lost') . ' a ranked rival battle',
                ($winnerId === $attackerId ? 'Defeated ' : 'Challenged ') . $targetName . ' in the Rival Network.',
                $defenderId,
                $winnerId === $attackerId ? $delta : -$delta
            );
        }

    } catch (Throwable $e) {
        pv_log('Ranked activity feed update failed after committed battle ' . $battleId . ': ' . $e->getMessage());
    }

    return [
        'battle_id'=>$battleId,
        'winner_id'=>$winnerId,
        'loser_id'=>$loserId,
        'rating_delta'=>$delta,
        'attacker_rating'=>$newA,
        'attacker_rating_change'=>$newA - $aRating,
        'defender_rating'=>$newD,
        'defender_rating_change'=>$newD - $dRating,
        'attacker_ranked_wins'=>$aWins,
        'attacker_ranked_losses'=>$aLosses,
        'attacker_streak'=>$aStreak,
        'defender_ranked_wins'=>$dWins,
        'defender_ranked_losses'=>$dLosses,
        'defender_streak'=>$dStreak,
        'shield_until'=>$shieldUntil,
        'retaliation_id'=>$newRetaliationId,
    ];
}

/** Reconstruct an existing receipt without changing either trainer again. */
function pv_rival_match_result(array $battle, array $attacker, array $defender): array
{
    $won = (int)$battle['winner_id'] === (int)$battle['attacker_id'];
    $delta = (int)$battle['rating_delta'];
    $aBefore = (int)$battle['attacker_rating_before'];
    $dBefore = (int)$battle['defender_rating_before'];
    return [
        'battle_id'=>(int)$battle['id'], 'winner_id'=>(int)$battle['winner_id'],
        'loser_id'=>(int)$battle['loser_id'], 'rating_delta'=>$delta,
        'attacker_rating'=>(int)$attacker['rating'], 'defender_rating'=>(int)$defender['rating'],
        'attacker_rating_change'=>max(100, $aBefore + ($won ? $delta : -$delta)) - $aBefore,
        'defender_rating_change'=>max(100, $dBefore + ($won ? -$delta : $delta)) - $dBefore,
        'attacker_ranked_wins'=>(int)$attacker['ranked_wins'],
        'attacker_ranked_losses'=>(int)$attacker['ranked_losses'],
        'attacker_streak'=>(int)$attacker['current_streak'],
        'defender_ranked_wins'=>(int)$defender['ranked_wins'],
        'defender_ranked_losses'=>(int)$defender['ranked_losses'],
        'defender_streak'=>(int)$defender['current_streak'],
        'shield_until'=>(int)$defender['shield_until'], 'retaliation_id'=>0,
    ];
}

function pv_rival_complete_session_battle(mysqli $db, int $attackerId, int $defenderId, string $outcome): ?array
{
    if (!in_array($outcome, ['win','loss'], true)) return null;
    $ctx = pv_rival_session_context($attackerId, $defenderId);
    if (!$ctx || ($ctx['phase'] ?? '') === 'armed') return null;
    // Only the authoritative combat terminal path supplies this outcome. Once
    // recorded, a retry cannot reverse it and it must not expire as an idle launch.
    $outcome = (string)($ctx['pending_outcome'] ?? $outcome);
    $_SESSION['pv_rival_battle']['phase'] = 'pending';
    $_SESSION['pv_rival_battle']['pending_outcome'] = $outcome;
    $winnerId = $outcome === 'win' ? $attackerId : $defenderId;
    try {
        $nonce = (string)($ctx['nonce'] ?? '');
        if (!preg_match('/^[a-f0-9]{36}$/D', $nonce)) throw new RuntimeException('Ranked battle identity is missing.');
        $result = pv_rival_record_match(
            $db, $attackerId, $defenderId, $winnerId,
            (string)($ctx['source'] ?? 'challenge'),
            max(0, (int)($ctx['retaliation_id'] ?? 0)), '', true, $nonce
        );
        unset($_SESSION['pv_rival_battle']);
        $_SESSION['pv_rival_last_result'] = $result + ['outcome'=>(int)$result['winner_id'] === $attackerId ? 'win' : 'loss','opponent_id'=>$defenderId];
        return $result;
    } catch (Throwable $e) {
        pv_log('Rival Network settlement pending: '.$e->getMessage(), ['attacker'=>$attackerId,'defender'=>$defenderId,'outcome'=>$outcome]);
        return null;
    }
}

/** Retry only a server-resolved result; never infer an outcome from a URL. */
function pv_rival_retry_pending_result(mysqli $db, int $userId): bool
{
    $ctx = $_SESSION['pv_rival_battle'] ?? null;
    if (!is_array($ctx) || (int)($ctx['attacker_id'] ?? 0) !== $userId || !isset($ctx['pending_outcome'])) return true;
    return pv_rival_complete_session_battle($db, $userId, (int)$ctx['defender_id'], (string)$ctx['pending_outcome']) !== null;
}

function pv_rival_bot_ranked_operation(mysqli $db, array $bot, int $cooldownSeconds = PV_RIVAL_BOT_ATTACK_COOLDOWN): ?array
{
    if (!pv_rival_ready($db)) return null;
    $botId = max(0, (int)($bot['user_id'] ?? 0));
    if ($botId <= 0 || !pv_rival_has_team($db, $botId)) return null;
    $now = time();
    $cooldownSeconds = max(60, min(14400, $cooldownSeconds));
    if (array_key_exists('rank_last_attack_at', $bot) && (int)$bot['rank_last_attack_at'] > $now - $cooldownSeconds) return null;
    pv_rival_ensure_state($db, $botId);
    $state = pv_rival_state($db, $botId);
    if (!$state) return null;
    if ((int)($state['last_attack_at'] ?? 0) > $now - $cooldownSeconds) return null;

    $rating = max(100, (int)$state['rating']);
    $low = max(100, $rating - 260);
    $high = $rating + 260;
    $stmt = $db->prepare(
        'SELECT r.user_id,r.rating,m.username,CASE WHEN b.user_id IS NULL THEN 0 ELSE 1 END is_bot '
        . 'FROM trainer_rank_state r JOIN members m ON m.id=r.user_id '
        . 'LEFT JOIN bot_trainers b ON b.user_id=r.user_id AND b.enabled=1 '
        . 'WHERE r.user_id<>? AND r.rating BETWEEN ? AND ? AND r.shield_until<=? '
        . 'AND COALESCE(m.s1,0)>0 '
        . 'AND EXISTS (SELECT 1 FROM pokemon tp WHERE tp.id=m.s1 AND CAST(tp.owner AS UNSIGNED)=m.id) '
        . 'ORDER BY CASE WHEN b.user_id IS NULL THEN 0 ELSE 1 END ASC, ABS(r.rating-?) ASC, MOD(r.user_id*31+?,97) ASC LIMIT 18'
    );
    if (!$stmt) return null;
    $minuteSeed = intdiv($now, 60);
    $stmt->bind_param('iiiiii', $botId, $low, $high, $now, $rating, $minuteSeed);
    $stmt->execute();
    $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // A narrow RP window must never be able to stall autonomous Ranked play.
    // If every nearby rival is protected or the Elo field has spread apart,
    // widen to the whole eligible field while still preferring close RP.
    if ($candidates === []) {
        $stmt = $db->prepare(
            'SELECT r.user_id,r.rating,m.username,CASE WHEN b.user_id IS NULL THEN 0 ELSE 1 END is_bot '
            . 'FROM trainer_rank_state r JOIN members m ON m.id=r.user_id '
            . 'LEFT JOIN bot_trainers b ON b.user_id=r.user_id AND b.enabled=1 '
            . 'WHERE r.user_id<>? AND r.shield_until<=? AND COALESCE(m.s1,0)>0 '
            . 'AND EXISTS (SELECT 1 FROM pokemon tp WHERE tp.id=m.s1 AND CAST(tp.owner AS UNSIGNED)=m.id) '
            . 'ORDER BY ABS(r.rating-?) ASC, CASE WHEN b.user_id IS NULL THEN 0 ELSE 1 END ASC, MOD(r.user_id*31+?,97) ASC LIMIT 24'
        );
        if (!$stmt) return null;
        $stmt->bind_param('iiii', $botId, $now, $rating, $minuteSeed);
        $stmt->execute();
        $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    if ($candidates === []) return null;

    // AI should visibly compete with real trainers, not disappear into a private
    // bot-only ladder. Humans are kept in the candidate window and are preferred
    // most of the time when they are currently attackable. The defender shield
    // prevents this preference from turning into dog-piling.
    $humanCandidates = array_values(array_filter($candidates, static fn(array $row): bool => (int)($row['is_bot'] ?? 0) === 0));
    $humanTargetPercent = max(20, min(75, (int)($bot['human_target_percent'] ?? 42)));
    $pool = ($humanCandidates !== [] && random_int(1,100) <= $humanTargetPercent) ? $humanCandidates : $candidates;

    // Rotate through the selected close-ranked pool rather than hammering one id.
    $offset = (($botId * 17) + intdiv($now, 45)) % count($pool);
    $target = $pool[$offset];
    $targetId = max(0, (int)$target['user_id']);
    if ($targetId <= 0 || !pv_rival_has_team($db, $targetId)) return null;

    // Autonomous operations resolve immediately. Their own shield is cleared by
    // the same transactional settlement that records the match, so a race-lost
    // target does not expose the AI without an actual completed operation.
    $botPower = pv_rival_team_power($db, $botId);
    $targetPower = pv_rival_team_power($db, $targetId);
    $targetRating = max(100, (int)$target['rating']);
    $chance = 50.0 + (($botPower - $targetPower) / 11.0) + (($rating - $targetRating) / 32.0) + (float)($bot['ranked_win_bonus'] ?? 0.0);
    $chance = max(22.0, min(78.0, $chance));
    $won = random_int(1,10000) <= (int)round($chance * 100);
    $winnerId = $won ? $botId : $targetId;

    try {
        $result = pv_rival_record_match(
            $db,
            $botId,
            $targetId,
            $winnerId,
            'autonomous',
            0,
            'Autonomous AI ranked operation resolved from persisted team strength and ladder rating.',
            (int)($target['is_bot'] ?? 0) === 0
        );
        $result['target_id'] = $targetId;
        $result['target_name'] = (string)$target['username'];
        $result['won'] = $won;
        return $result;
    } catch (Throwable $e) {
        pv_log('Autonomous ranked operation failed for '.$botId.': '.$e->getMessage());
        return null;
    }
}

function pv_rival_log_bot_world_action(mysqli $db, array $bot, string $action, ?array $wildResult = null): void
{
    if (!pv_rival_ready($db)) return;
    $uid = max(0, (int)($bot['user_id'] ?? 0));
    if ($uid <= 0) return;
    $world = strtoupper((string)($bot['world_key'] ?? 'VORTEX'));
    $map = (string)($bot['map_key'] ?? '');
    if ($wildResult) {
        $species = trim((string)($wildResult['species'] ?? 'wild Pokémon'));
        $level = max(1, (int)($wildResult['level'] ?? 1));
        if (!empty($wildResult['captured'])) {
            $training=(int)($wildResult['exp']??0);$levelUps=(int)($wildResult['level_ups']??0);$trainingText=$training>0?' · +'.number_format($training).' team EXP'.($levelUps>0?' · '.$levelUps.' level'.($levelUps===1?'':'s').' gained':''):'';
            pv_rival_log_ai_activity($db,$uid,'capture','Caught '.$species,'Captured a Lv. '.$level.' '.$species.' while roaming '.$world.' · '.$map.$trainingText.'.');
        } elseif (!empty($wildResult['won'])) {
            $training=(int)($wildResult['exp']??0);$levelUps=(int)($wildResult['level_ups']??0);$trainingText=$training>0?' · +'.number_format($training).' team EXP'.($levelUps>0?' · '.$levelUps.' level'.($levelUps===1?'':'s').' gained':''):'';
            pv_rival_log_ai_activity($db,$uid,'wild','Won a wild battle','Defeated a Lv. '.$level.' '.$species.' while training in '.$world.' · '.$map.$trainingText.'.');
        } else {
            pv_rival_log_ai_activity($db,$uid,'wild','Lost a wild battle','Was defeated by a Lv. '.$level.' '.$species.' in '.$world.' · '.$map.' and continued training.');
        }
        return;
    }
    // World movement is useful texture, but sample it so ranked/capture events stay
    // dominant and the activity table remains bounded during long-running servers.
    if ($action === 'move' && (($uid + intdiv(time(),60)) % 9 === 0)) {
        pv_rival_log_ai_activity($db,$uid,'roam','Moved to a new training sector','Exploring '.$world.' · '.$map.' and scouting new opponents.');
    }
}

function pv_rival_housekeeping(mysqli $db): void
{
    if (!pv_rival_ready($db)) return;
    $now = time();
    // Housekeeping is intentionally sampled. Presence polling can call this runtime every few seconds; expiry may lag by only a few seconds, but no page request is forced to run maintenance every poll.
    if (($now % 13) === 0) @$db->query("UPDATE rival_retaliations SET status='expired' WHERE status='open' AND expires_at<=".(int)$now);
    // Keep a compact rolling activity feed without turning high AI activity into a hot delete path.
    if (($now % 29) === 0) {
        @$db->query('DELETE FROM ai_activity WHERE created_at<'.(int)($now - 604800));
        $result = $db->query('SELECT id FROM ai_activity ORDER BY id DESC LIMIT 1 OFFSET 6000');
        if ($result) {
            $row = $result->fetch_assoc(); $result->free();
            if ($row) @$db->query('DELETE FROM ai_activity WHERE id<='.(int)$row['id']);
        }
    }

    // Human-involved Rival history is retained. Autonomous-only history is kept
    // on a rolling window so a long-running 2,000-trainer world cannot grow the
    // local XAMPP database without bound merely from background simulation.
    if (($now % 113) === 0) {
        $botOnlyCutoff = $now - (14 * 86400);
        @$db->query("DELETE rr FROM rival_retaliations rr INNER JOIN bot_trainers ba ON ba.user_id=rr.attacker_id AND ba.enabled=1 INNER JOIN bot_trainers bd ON bd.user_id=rr.defender_id AND bd.enabled=1 WHERE rr.status<>'open' AND rr.expires_at<".(int)$botOnlyCutoff);
    }
    if (($now % 211) === 0) {
        $battleCutoff = $now - PV_RIVAL_AUTONOMOUS_HISTORY_SECONDS;
        @$db->query("DELETE rb FROM rival_battles rb INNER JOIN bot_trainers ba ON ba.user_id=rb.attacker_id AND ba.enabled=1 INNER JOIN bot_trainers bd ON bd.user_id=rb.defender_id AND bd.enabled=1 WHERE rb.source='autonomous' AND rb.created_at<".(int)$battleCutoff);
    }
}
