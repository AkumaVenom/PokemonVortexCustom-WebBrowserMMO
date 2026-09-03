<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();

$uid = (int)$_SESSION['myid'];
$username = (string)($_SESSION['myuser'] ?? 'Trainer');
$now = time();
$notice = '';
$error = '';
if (!empty($_GET['resume_blocked'])) {
    $error = 'That live battle was already initialized in another browser session and cannot be restarted from full HP. Start a new challenge to battle safely.';
} elseif (!empty($_GET['team_unavailable'])) {
    $error = 'That live battle could not start because one of the active teams is no longer valid. Reconfigure the team and create a new challenge.';
} elseif (!empty($_GET['stale'])) {
    $error = 'That live battle session is no longer current. Start or enter the latest challenge from the arena.';
}

function pv_live_challenge_load(mysqli $db, int $challengeId): ?array {
    $stmt = $db->prepare("SELECT c.*, m1.username challenger_name, m2.username target_name
        FROM live_battle_challenges c
        LEFT JOIN members m1 ON m1.id=c.challenger_id
        LEFT JOIN members m2 ON m2.id=c.target_id
        WHERE c.id=? LIMIT 1");
    $stmt->bind_param('i', $challengeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function pv_live_valid_team_count(mysqli $db, int $trainerId): int {
    if ($trainerId <= 0) return 0;
    $stmt = $db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');
    if (!$stmt) return 0;
    $stmt->bind_param('i', $trainerId);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $ids = [];
    for ($slot = 1; $slot <= 6; $slot++) {
        $pokemonId = max(0, (int)($member['s'.$slot] ?? 0));
        if ($pokemonId > 0 && !in_array($pokemonId, $ids, true)) $ids[] = $pokemonId;
    }
    if ($ids === []) return 0;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = 'i'.str_repeat('i', count($ids));
    $args = [$trainerId, ...$ids];
    $stmt = $db->prepare("SELECT COUNT(*) c FROM pokemon WHERE CAST(owner AS UNSIGNED)=? AND id IN ({$placeholders})");
    if (!$stmt) return 0;
    $stmt->bind_param($types, ...$args);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return max(0, min(6, $count));
}

try {
    $db = pv_db();
    if (!pv_table_exists('live_battle_challenges')) {
        throw new RuntimeException('Live Battle is temporarily unavailable while the game host finishes maintenance.');
    }

    // Presence heartbeat. The row is deliberately disposable and expires quickly.
    $stmt = $db->prepare("INSERT INTO live_battle_members (userid,username,time) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE username=VALUES(username), time=VALUES(time)");
    $stmt->bind_param('isi', $uid, $username, $now);
    $stmt->execute();
    $stmt->close();
    $cutoff = $now - 300;
    $stmt = $db->prepare('DELETE FROM live_battle_members WHERE time < ?');
    $stmt->bind_param('i', $cutoff);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare("UPDATE live_battle_challenges SET status='expired', responded_at=? WHERE status='pending' AND created_at < ?");
    $stmt->bind_param('ii', $now, $cutoff);
    $stmt->execute();
    $stmt->close();
    $battleCutoff = $now - 86400;
    // Accepted handshakes are only useful while their exact battle row exists.
    // Expire old accepted records before pruning abandoned battle rows so the
    // Offers view never advertises a dead 24-hour-old match indefinitely.
    $stmt = $db->prepare("UPDATE live_battle_challenges SET status='expired', responded_at=? WHERE status='accepted' AND responded_at > 0 AND responded_at < ?");
    $stmt->bind_param('ii', $now, $battleCutoff);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare('DELETE FROM live_battle WHERE created_at > 0 AND created_at < ?');
    $stmt->bind_param('i', $battleCutoff);
    $stmt->execute();
    $stmt->close();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        pv_require_csrf();
        $action = (string)($_POST['arena_action'] ?? '');
        $challengeId = max(0, (int)($_POST['challenge_id'] ?? 0));
        $targetId = max(0, (int)($_POST['target_id'] ?? 0));

        if ($action === 'challenge') {
            if ($targetId <= 0 || $targetId === $uid) throw new RuntimeException('Choose another online trainer to challenge.');
            $stmt = $db->prepare('SELECT m.id,m.username FROM live_battle_members l JOIN members m ON m.id=l.userid WHERE l.userid=? AND l.time>=? LIMIT 1');
            $stmt->bind_param('ii', $targetId, $cutoff);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$target) throw new RuntimeException('That trainer is no longer in the Live Battle Arena.');

            $db->begin_transaction();
            $stmt = $db->prepare("UPDATE live_battle_challenges SET status='cancelled', responded_at=? WHERE challenger_id=? AND status='pending'");
            $stmt->bind_param('ii', $now, $uid);
            $stmt->execute();
            $stmt->close();
            $stmt = $db->prepare("INSERT INTO live_battle_challenges (challenger_id,target_id,status,created_at,responded_at) VALUES (?,?,'pending',?,0)");
            $stmt->bind_param('iii', $uid, $targetId, $now);
            $stmt->execute();
            $newId = (int)$db->insert_id;
            $stmt->close();
            $db->commit();
            pv_redirect('live_battle_arena.php?view=offers&sent=' . $newId);
        }

        if ($action === 'accept' || $action === 'decline') {
            $db->begin_transaction();
            $stmt = $db->prepare("SELECT id,challenger_id,target_id,status FROM live_battle_challenges WHERE id=? AND target_id=? FOR UPDATE");
            $stmt->bind_param('ii', $challengeId, $uid);
            $stmt->execute();
            $challenge = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$challenge || $challenge['status'] !== 'pending') throw new RuntimeException('That live battle offer is no longer available.');

            $challengerId = (int)$challenge['challenger_id'];
            if ($action === 'decline') {
                $stmt = $db->prepare("UPDATE live_battle_challenges SET status='declined', responded_at=? WHERE id=?");
                $stmt->bind_param('ii', $now, $challengeId);
                $stmt->execute();
                $stmt->close();
                $db->commit();
                $notice = 'Live battle offer declined.';
            } else {
                $stmt = $db->prepare('SELECT userid FROM live_battle_members WHERE userid=? AND time>=? LIMIT 1');
                $stmt->bind_param('ii', $challengerId, $cutoff);
                $stmt->execute();
                $online = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$online) throw new RuntimeException('The challenger has left the arena.');
                if (pv_live_valid_team_count($db, $uid) <= 0) {
                    throw new RuntimeException('Configure at least one valid Pokémon in your active team before accepting a live battle.');
                }
                if (pv_live_valid_team_count($db, $challengerId) <= 0) {
                    throw new RuntimeException('The challenger no longer has a valid active team. Ask them to configure their team and send a new challenge.');
                }

                // A pair may have only one accepted match. Do not supersede a live
                // immutable match while either browser can still submit to it.
                $stmt = $db->prepare("SELECT id FROM live_battle_challenges WHERE id<>? AND status='accepted' AND ((challenger_id=? AND target_id=?) OR (challenger_id=? AND target_id=?)) LIMIT 1 FOR UPDATE");
                $stmt->bind_param('iiiii', $challengeId, $uid, $challengerId, $challengerId, $uid);
                $stmt->execute();
                $activePairMatch = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($activePairMatch) throw new RuntimeException('Finish the existing accepted match with this trainer before starting a rematch.');

                // Retire duplicate pending offers between the same pair so they
                // cannot create a second match after this acceptance.
                $stmt = $db->prepare("UPDATE live_battle_challenges SET status='expired', responded_at=? WHERE id<>? AND status='pending' AND ((challenger_id=? AND target_id=?) OR (challenger_id=? AND target_id=?))");
                $stmt->bind_param('iiiiii', $now, $challengeId, $uid, $challengerId, $challengerId, $uid);
                $stmt->execute();
                $stmt->close();

                // Every accepted challenge receives a new immutable match row.
                // Never delete or reuse an older row: its exact id may still be
                // open in another browser and may also be a durable result receipt.
                $stmt = $db->prepare('INSERT INTO live_battle (uid_1,uid_2,created_at) VALUES (?,?,?)');
                $stmt->bind_param('iii', $uid, $challengerId, $now);
                if (!$stmt->execute()) throw new RuntimeException('The live battle session could not be created.');
                $battleId = (int)$db->insert_id;
                $stmt->close();
                if ($battleId <= 0) throw new RuntimeException('The live battle session did not receive a valid id.');

                $stmt = $db->prepare("UPDATE live_battle_challenges SET status='accepted', responded_at=?, battle_id=? WHERE id=? AND status='pending'");
                $stmt->bind_param('iii', $now, $battleId, $challengeId);
                if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new RuntimeException('That live battle offer changed before it could be accepted.');
                }
                $stmt->close();
                $db->commit();

                $_SESSION['live'] = [0 => 1, 1 => 2, 3 => $challengerId, 4 => $battleId];
                pv_redirect('live_battle.php?battle=Live');
            }
        }

        if ($action === 'cancel') {
            $stmt = $db->prepare("UPDATE live_battle_challenges SET status='cancelled', responded_at=? WHERE id=? AND challenger_id=? AND status='pending'");
            $stmt->bind_param('iii', $now, $challengeId, $uid);
            $stmt->execute();
            $notice = $stmt->affected_rows === 1 ? 'Challenge cancelled.' : 'That challenge is no longer pending.';
            $stmt->close();
        }

        if ($action === 'join') {
            $challenge = pv_live_challenge_load($db, $challengeId);
            if (!$challenge || (int)$challenge['challenger_id'] !== $uid || $challenge['status'] !== 'accepted') {
                throw new RuntimeException('That accepted battle is no longer available.');
            }
            $targetId = (int)$challenge['target_id'];
            $battleId = max(0, (int)($challenge['battle_id'] ?? 0));

            // One-time compatibility path for a challenge accepted immediately before
            // the v20 migration. Once found, persist the exact row id onto the challenge.
            if ($battleId <= 0) {
                $stmt = $db->prepare('SELECT id FROM live_battle WHERE uid_1=? AND uid_2=? ORDER BY id DESC LIMIT 1');
                $stmt->bind_param('ii', $targetId, $uid);
                $stmt->execute();
                $legacyBattle = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $battleId = (int)($legacyBattle['id'] ?? 0);
                if ($battleId > 0) {
                    $stmt = $db->prepare("UPDATE live_battle_challenges SET battle_id=? WHERE id=? AND challenger_id=? AND status='accepted' AND battle_id=0");
                    $stmt->bind_param('iii', $battleId, $challengeId, $uid);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            if ($battleId <= 0) throw new RuntimeException('The live battle session could not be found. Ask the opponent to send a new challenge.');
            $stmt = $db->prepare('SELECT id FROM live_battle WHERE id=? AND uid_1=? AND uid_2=? LIMIT 1');
            $stmt->bind_param('iii', $battleId, $targetId, $uid);
            $stmt->execute();
            $battleRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$battleRow) throw new RuntimeException('That accepted battle has been replaced or is no longer available.');
            $_SESSION['live'] = [0 => 2, 1 => 1, 3 => $targetId, 4 => $battleId];
            pv_redirect('live_battle.php?battle=Live');
        }
    }

    $view = strtolower((string)($_GET['view'] ?? 'arena'));
    if (!in_array($view, ['arena','offers'], true)) $view = 'arena';

    $incoming = [];
    $stmt = $db->prepare("SELECT c.*,m.username challenger_name FROM live_battle_challenges c JOIN members m ON m.id=c.challenger_id WHERE c.target_id=? AND c.status='pending' ORDER BY c.created_at DESC");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $incoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $outgoing = [];
    $stmt = $db->prepare("SELECT c.*,m.username target_name,COALESCE(lb.settled_2,0) user_settled FROM live_battle_challenges c JOIN members m ON m.id=c.target_id LEFT JOIN live_battle lb ON lb.id=c.battle_id WHERE c.challenger_id=? AND c.created_at>=? ORDER BY c.created_at DESC LIMIT 12");
    $stmt->bind_param('ii', $uid, $cutoff);
    $stmt->execute();
    $outgoing = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $online = [];
    $stmt = $db->prepare('SELECT l.userid,l.username,l.time FROM live_battle_members l WHERE l.userid<>? AND l.time>=? ORDER BY l.username ASC');
    $stmt->bind_param('ii', $uid, $cutoff);
    $stmt->execute();
    $online = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) { try { $db->rollback(); } catch (Throwable $ignored) {} }
    pv_log('Live battle arena: ' . $e->getMessage());
    $error = $e->getMessage();
    $incoming = $incoming ?? [];
    $outgoing = $outgoing ?? [];
    $online = $online ?? [];
    $view = $view ?? 'arena';
}

pv_page_start('Live Battle Arena', 'battle_select.php', true);
?>
<div class="pv-game-layout"><?php pv_game_side_menu('battle_select.php'); ?><main class="pv-main-column"><section class="pv-page pv-live-arena-modern">
<div class="pv-page-head"><div><span class="pv-eyebrow">TRAINER VS TRAINER // LIVE LINK</span><h1>Live Battle Arena</h1><p class="pv-subtle">Challenge another trainer who is currently connected to the arena. Battle invitations expire automatically after five minutes.</p></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('battle_select.php'))?>">Battle Hub</a></div>
<?php if($notice):?><div class="pv-flash success"><?=pv_h($notice)?></div><?php endif;?>
<?php if($error):?><div class="pv-flash danger"><?=pv_h($error)?></div><?php endif;?>
<nav class="pv-live-arena-tabs"><a class="<?=$view==='arena'?'active':''?>" href="<?=pv_h(pv_url('live_battle_arena.php'))?>">Arena</a><a class="<?=$view==='offers'?'active':''?>" href="<?=pv_h(pv_url('live_battle_arena.php?view=offers'))?>">Offers <b><?=count($incoming)?></b></a></nav>

<?php if($view==='arena'):?>
<div class="pv-live-arena-grid">
<section class="pv-panel"><div class="pv-section-heading"><div><span>CONNECTED TRAINERS</span><h2>Available opponents</h2></div><small><?=count($online)?> online</small></div>
<?php if(!$online):?><div class="pv-empty-state"><strong>No other trainers are in the arena.</strong><span>Stay on this page or return later. Arena presence expires automatically when a trainer leaves.</span></div><?php else:?><div class="pv-live-trainer-list">
<?php foreach($online as $t):?><article><div class="pv-trainer-avatar small"><?=pv_h(strtoupper(mb_substr((string)$t['username'],0,1)))?></div><div><small>ONLINE TRAINER // #<?=number_format((int)$t['userid'])?></small><strong><?=pv_h((string)$t['username'])?></strong><span>Signal active</span></div><form method="post"><?=pv_csrf_field()?><input type="hidden" name="arena_action" value="challenge"><input type="hidden" name="target_id" value="<?=(int)$t['userid']?>"><button class="pv-button" type="submit">Challenge</button></form></article><?php endforeach;?>
</div><?php endif;?></section>
<section class="pv-panel"><div class="pv-section-heading"><div><span>INCOMING SIGNALS</span><h2>Battle requests</h2></div><small><?=count($incoming)?> pending</small></div>
<?php if(!$incoming):?><div class="pv-empty-state"><strong>No incoming challenges.</strong><span>When another trainer challenges you, the request will appear here.</span></div><?php else:?><div class="pv-live-offer-list">
<?php foreach($incoming as $c):?><article><div><small>CHALLENGE // <?=date('H:i:s',(int)$c['created_at'])?> UTC</small><strong><?=pv_h((string)$c['challenger_name'])?></strong><span>wants to battle your active team.</span></div><div class="pv-actions"><form method="post"><?=pv_csrf_field()?><input type="hidden" name="arena_action" value="accept"><input type="hidden" name="challenge_id" value="<?=(int)$c['id']?>"><button class="pv-button" type="submit">Accept</button></form><form method="post"><?=pv_csrf_field()?><input type="hidden" name="arena_action" value="decline"><input type="hidden" name="challenge_id" value="<?=(int)$c['id']?>"><button class="pv-button pv-button-secondary" type="submit">Decline</button></form></div></article><?php endforeach;?>
</div><?php endif;?></section>
</div>
<?php else:?>
<section class="pv-panel"><div class="pv-section-heading"><div><span>TRANSMISSION HISTORY</span><h2>Recent challenges</h2></div><small>last five minutes</small></div>
<?php if(!$outgoing):?><div class="pv-empty-state"><strong>No recent outgoing challenges.</strong><span>Return to the arena and challenge an online trainer.</span></div><?php else:?><div class="pv-live-offer-list">
<?php foreach($outgoing as $c): $status=(string)$c['status']; $battleId=max(0,(int)($c['battle_id']??0)); $settled=(int)($c['user_settled']??0)===1;?><article class="status-<?=pv_h($status)?>"><div><small>TO // <?=pv_h((string)$c['target_name'])?></small><strong><?=pv_h($status==='completed'?'Completed':ucfirst($status))?></strong><span><?=date('H:i:s',(int)$c['created_at'])?> UTC</span></div><div class="pv-actions"><?php if($status==='pending'):?><form method="post"><?=pv_csrf_field()?><input type="hidden" name="arena_action" value="cancel"><input type="hidden" name="challenge_id" value="<?=(int)$c['id']?>"><button class="pv-button pv-button-secondary" type="submit">Cancel</button></form><?php elseif(($status==='accepted' && $settled) || $status==='completed'):?><a class="pv-button" href="<?=pv_h(pv_url('live_battle_result.php?match='.$battleId))?>">View Result</a><?php elseif($status==='accepted'):?><form method="post"><?=pv_csrf_field()?><input type="hidden" name="arena_action" value="join"><input type="hidden" name="challenge_id" value="<?=(int)$c['id']?>"><button class="pv-button" type="submit">Enter Battle</button></form><?php endif;?></div></article><?php endforeach;?>
</div><?php endif;?></section>
<?php endif;?>
</section></main></div>
<?php pv_page_end(); ?>
