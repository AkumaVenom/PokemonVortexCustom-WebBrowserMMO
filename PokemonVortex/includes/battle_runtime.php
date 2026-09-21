<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/battle_experience.php';
require_once __DIR__ . '/gameplay.php';

/**
 * Modern database boundary for the recovered standard trainer/NPC battle engine.
 *
 * Battle-owned persistence and hydration use prepared mysqli statements.
 * Species experience is awarded when an opponent faints, with a durable receipt
 * independent of the final match result. No request-controlled SQL identifiers are
 * accepted: the only dynamic table/column names are resolved from private
 * allowlists in this file.
 */

function pv_battle_runtime_guard_session(mysqli $db, int $uid): void
{
    $uid = max(1, $uid);
    $stmt = $db->prepare('SELECT banned FROM members WHERE id=? LIMIT 1');
    if (!$stmt) throw new RuntimeException('Could not prepare trainer session guard.');
    $stmt->bind_param('i', $uid);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Could not validate trainer session.');
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $_SESSION = [];
        pv_redirect('login.php?expired=1');
    }

    if ((string)($row['banned'] ?? '0') === '1') {
        $_SESSION = [];
        foreach (['online', 'mapusers'] as $table) {
            $delete = $db->prepare("DELETE FROM {$table} WHERE id=?");
            if ($delete) {
                $delete->bind_param('i', $uid);
                @$delete->execute();
                $delete->close();
            }
        }
        pv_redirect('login.php?action=Banned');
    }

    $now = time();
    if (!isset($_SESSION['updatetime']) || (int)$_SESSION['updatetime'] < $now - 240) {
        $_SESSION['updatetime'] = $now;
        $touch = $db->prepare('UPDATE online SET time=? WHERE id=?');
        if ($touch) {
            $touch->bind_param('ii', $now, $uid);
            @$touch->execute();
            $touch->close();
        }
    }
}

/** @return array<string,int> */
function pv_battle_runtime_nav_counts(mysqli $db, int $uid, bool $clanOwner, string $clanName): array
{
    $uid = max(1, $uid);
    $counts = ['chat' => 0, 'messages' => 0, 'trades' => 0, 'clan_requests' => 0];

    $result = $db->query('SELECT COUNT(*) AS c FROM flashchat_connections WHERE userid >= 1');
    if ($result) {
        $row = $result->fetch_assoc();
        $counts['chat'] = max(0, (int)($row['c'] ?? 0));
        $result->free();
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM messages WHERE receiverid=? AND receiverdelete='1' AND receiverread='1'");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        if ($stmt->execute()) $counts['messages'] = max(0, (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0));
        $stmt->close();
    }

    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM upfortrade WHERE owner=? AND offers>0');
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        if ($stmt->execute()) $counts['trades'] = max(0, (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0));
        $stmt->close();
    }

    if ($clanOwner) {
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM clan_requests WHERE clan=?');
        if ($stmt) {
            $stmt->bind_param('s', $clanName);
            if ($stmt->execute()) $counts['clan_requests'] = max(0, (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0));
            $stmt->close();
        }
    } else {
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM clan_requests WHERE id=?');
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            if ($stmt->execute()) $counts['clan_requests'] = max(0, (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0));
            $stmt->close();
        }
    }

    return $counts;
}

function pv_battle_runtime_usernav(mysqli $db, int $uid): string
{
    $counts = pv_battle_runtime_nav_counts(
        $db,
        $uid,
        (int)($_SESSION['clanowner'] ?? 0) === 1,
        trim((string)($_SESSION['clan'] ?? ''))
    );
    $user = pv_h((string)($_SESSION['myuser'] ?? 'Trainer'));
    return '<div id="usernav">Logged in as: <a href="members.php?uid=' . $uid . '" title="' . $user . '">' . $user . '</a>'
        . ' · New Messages: <a href="messages.php">' . $counts['messages'] . '</a>'
        . ' · Trade Offers: <a href="trade.php?cat=uft&amp;order=offers">' . $counts['trades'] . '</a>'
        . ' · Clan Requests: <a href="clans.php?view=Requests">' . $counts['clan_requests'] . '</a>'
        . ' · Chat Users: <a href="chat.php">' . $counts['chat'] . '</a></div>';
}

/** @return array<int,array<string,mixed>> */
function pv_battle_runtime_party(mysqli $db, string $tableKey, int $ownerId, array $orderedIds): array
{
    $tables = [
        'player' => 'pokemon',
        'clan' => 'pokemon',
        'event' => 'eventpokemon',
        'gym' => 'gympokemon',
        'side' => 'sidepokemon',
    ];
    $table = $tables[$tableKey] ?? '';
    if ($table === '') throw new InvalidArgumentException('Unsupported battle party table.');

    $ids = [];
    foreach ($orderedIds as $id) {
        $id = max(0, (int)$id);
        if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
        if (count($ids) === 6) break;
    }
    if ($ownerId <= 0 || $ids === []) return [];

    $padded = array_pad($ids, 6, 0);
    $originalTrainer = $table === 'pokemon' ? "COALESCE(NULLIF(ot,''),rowner)" : 'rowner';
    $stmt = $db->prepare(
        "SELECT id,name,t1,t2,lvl,exp,a1,a2,a3,a4,{$originalTrainer} AS original_trainer FROM {$table} " .
        'WHERE owner=? AND id IN (?,?,?,?,?,?)'
    );
    if (!$stmt) throw new RuntimeException('Could not prepare battle party hydration.');
    $stmt->bind_param('iiiiiii', $ownerId, $padded[0], $padded[1], $padded[2], $padded[3], $padded[4], $padded[5]);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Could not hydrate battle party.');
    }
    $result = $stmt->get_result();
    $byId = [];
    while ($row = $result->fetch_assoc()) $byId[(int)$row['id']] = $row;
    $stmt->close();

    $ordered = [];
    foreach ($ids as $id) if (isset($byId[$id])) $ordered[] = $byId[$id];
    return $ordered;
}

/** @return array<string,int> */
function pv_battle_runtime_items(mysqli $db, int $uid): array
{
    $columns = ['Potion','Super_Potion','Hyper_Potion','Full_Heal','Awakening','Parlyz_Heal','Antidote','Burn_Heal','Ice_Heal','Poke_Ball','Great_Ball','Ultra_Ball','Master_Ball'];
    $stmt = $db->prepare('SELECT `' . implode('`,`', $columns) . '` FROM items WHERE uid=? LIMIT 1');
    if (!$stmt) throw new RuntimeException('Could not prepare battle inventory hydration.');
    $stmt->bind_param('i', $uid);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Could not hydrate battle inventory.');
    }
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $out = [];
    foreach ($columns as $column) $out[$column] = max(0, (int)($row[$column] ?? 0));
    return $out;
}

function pv_battle_runtime_consume_item(mysqli $db, int $uid, string $column): bool
{
    $allowed = ['Potion','Super_Potion','Hyper_Potion','Full_Heal','Awakening','Parlyz_Heal','Antidote','Burn_Heal','Ice_Heal'];
    if (!in_array($column, $allowed, true)) return false;
    $stmt = $db->prepare("UPDATE items SET `{$column}`=`{$column}`-1 WHERE uid=? AND `{$column}`>0");
    if (!$stmt) return false;
    $stmt->bind_param('i', $uid);
    $ok = $stmt->execute() && $stmt->affected_rows === 1;
    $stmt->close();
    return $ok;
}

/** @return array<string,int> */
function pv_battle_runtime_member_snapshot(mysqli $db, int $uid): array
{
    $stmt = $db->prepare('SELECT btime,uniques,battle,totalexp,total_poke FROM members WHERE id=? LIMIT 1');
    if (!$stmt) throw new RuntimeException('Could not prepare battle result snapshot.');
    $stmt->bind_param('i', $uid);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Could not load battle result snapshot.');
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new RuntimeException('Trainer battle result row is unavailable.');
    return [
        'btime' => (int)$row['btime'],
        'uniques' => (int)$row['uniques'],
        'battle' => (int)$row['battle'],
        'totalexp' => (int)$row['totalexp'],
        'total_poke' => (int)$row['total_poke'],
    ];
}

/**
 * Persist one participating Pokémon's EXP/level/happiness as an owner-bound
 * transaction. The optional durable receipt makes a fainted opponent safe to
 * retry after a lost response, while ownership remains bound to the trainer.
 */
function pv_battle_runtime_award_participant(mysqli $db, int $uid, int $pokemonId, int $expGain, int $happiness, string $receiptKey = '', ?array &$awardResult = null): bool
{
    $uid = max(1, $uid);
    $pokemonId = max(1, $pokemonId);
    $expGain = max(0, $expGain);
    $happiness = max(0, $happiness);
    $awardResult = ['gained'=>0, 'level_ups'=>0];
    if ($expGain <= 0 && $receiptKey === '') return true;
    if ($receiptKey !== '' && !preg_match('/^[a-f0-9]{32}:[1-6]:[1-9][0-9]*$/D', $receiptKey)) throw new InvalidArgumentException('Invalid experience receipt.');

    if (!$db->begin_transaction()) throw new RuntimeException('Could not start experience transaction.');
    try {
        $stmt = $db->prepare('SELECT name,lvl,exp,exp_curve_version FROM pokemon WHERE id=? AND owner=? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Could not lock battle participant.');
        $stmt->bind_param('ii', $pokemonId, $uid);
        if (!$stmt->execute()) throw new RuntimeException('Could not read battle participant.');
        $pokemon = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$pokemon) {
            $db->rollback();
            return false;
        }

        if ($receiptKey !== '') {
            $stmt = $db->prepare('SELECT reward_exp FROM pokemon_exp_awards WHERE reward_key=? AND user_id=? AND pokemon_id=? FOR UPDATE');
            if (!$stmt) throw new RuntimeException('Could not load experience receipt.');
            $stmt->bind_param('sii', $receiptKey, $uid, $pokemonId);
            if (!$stmt->execute()) throw new RuntimeException('Could not read experience receipt.');
            $receipt = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($receipt) {
                $awardResult = ['gained'=>(int)$receipt['reward_exp'], 'level_ups'=>0, 'lvl'=>(int)$pokemon['lvl'], 'exp'=>(int)$pokemon['exp'], 'name'=>(string)$pokemon['name']];
                if (!$db->commit()) throw new RuntimeException('Could not commit experience transaction.');
                return true;
            }
        }
        $awardResult = pv_exp_award($pokemon, $expGain);
        $awardResult['name'] = (string)$pokemon['name'];
        $finalExp = $awardResult['exp'];
        $finalLevel = $awardResult['lvl'];
        $stmt = $db->prepare('UPDATE pokemon SET lvl=?,exp=?,exp_curve_version=1 WHERE id=? AND owner=?');
        if (!$stmt) throw new RuntimeException('Could not prepare participant progression.');
        $stmt->bind_param('iiii', $finalLevel, $finalExp, $pokemonId, $uid);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Could not persist participant progression.');
        }
        $stmt->close();
        if ($receiptKey !== '') {
            $gained = (int)$awardResult['gained'];
            $now = time();
            $stmt = $db->prepare('INSERT INTO pokemon_exp_awards (reward_key,user_id,pokemon_id,reward_exp,created_at) VALUES (?,?,?,?,?)');
            if (!$stmt) throw new RuntimeException('Could not prepare experience receipt.');
            $stmt->bind_param('siiii', $receiptKey, $uid, $pokemonId, $gained, $now);
            if (!$stmt->execute()) throw new RuntimeException('Could not save experience receipt.');
            $stmt->close();
        }

        if ($happiness > 0) {
            $stmt = $db->prepare('UPDATE pokemon_stats SET happiness=LEAST(255,happiness+?) WHERE id=?');
            if (!$stmt) throw new RuntimeException('Could not prepare participant happiness.');
            $stmt->bind_param('ii', $happiness, $pokemonId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Could not persist participant happiness.');
            }
            $stmt->close();
        }

        if (!$db->commit()) throw new RuntimeException('Could not commit experience transaction.');
        return true;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Persist each newly fainted opponent independently of the final win/loss. */
function pv_battle_runtime_reward_fainted(mysqli $db, int $uid): void
{
    try {
        foreach (pv_battle_exp_pending() as $opponentSlot => $pending) {
            $opponent = $pending['opponent'];
            $eligible = $pending['eligible'];
            $participants = count($eligible);
            foreach ($eligible as $id => $ownSlot) {
                $state = $_SESSION['s'.$ownSlot];
                $originalTrainer = trim((string)($state[16] ?? ''));
                $traded = $originalTrainer !== '' && strcasecmp($originalTrainer, (string)($_SESSION['myuser'] ?? '')) !== 0;
                $gain = pv_exp_battle_gain((string)$opponent['name'], (int)$opponent['level'], $participants, true, $traded);
                $receiptKey = (string)$_SESSION['pv_standard_exp']['id'].':'.$opponentSlot.':'.$id;
                $result = null;
                if (!pv_battle_runtime_award_participant($db, $uid, (int)$id, $gain, 1, $receiptKey, $result)) continue;
                $_SESSION['pv_exp_progress_dirty'] = $uid;
                $_SESSION['pv_standard_exp']['awards'][$opponentSlot][$id] = (int)$result['gained'];
                $newLevel = (int)$result['lvl'];
                $hpIncrease = max(0, $newLevel - (int)$state[4]) * (str_starts_with((string)$result['name'], 'Shiny ') ? 5 : 4);
                $_SESSION['s'.$ownSlot][4] = $newLevel;
                $_SESSION['s'.$ownSlot][5] = (int)$result['exp'];
                $_SESSION['s'.$ownSlot][10] += $hpIncrease;
                $_SESSION['s'.$ownSlot][11] += $hpIncrease;
                $_SESSION['s'.$ownSlot][12] = 100 * $_SESSION['s'.$ownSlot][10] / max(1, $_SESSION['s'.$ownSlot][11]);
            }
            $_SESSION['pv_standard_exp']['opponents'][$opponentSlot]['done'] = true;
        }
    } finally {
        if ((int)($_SESSION['pv_exp_progress_dirty'] ?? 0) === $uid) {
            // Refresh a partially committed batch even if a later recipient
            // failed. Keep the marker across combat cleanup for later retries.
            try {
                pv_recalculate_trainer_progress($db, $uid, true);
                unset($_SESSION['pv_exp_progress_dirty']);
            } catch (Throwable $e) {
                pv_log('Trainer progress refresh failed after committed experience: '.$e->getMessage());
            }
        }
    }
}


/**
 * Persist the standard battle win counter/money and optional clan win in one
 * transaction so the two accepted result side effects cannot split.
 */
function pv_battle_runtime_record_victory(mysqli $db, int $uid, int $now, int $money, string $clanName = ''): void
{
    $uid = max(1, $uid);
    $money = max(0, $money);
    $clanName = trim($clanName);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('UPDATE members SET btime=?,battle=battle+1,money=money+? WHERE id=?');
        if (!$stmt) throw new RuntimeException('Could not prepare trainer victory settlement.');
        $stmt->bind_param('iii', $now, $money, $uid);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Could not persist trainer victory settlement.');
        }
        $stmt->close();

        if ($clanName !== '') {
            $stmt = $db->prepare('UPDATE clans SET wins=wins+1 WHERE name=?');
            if (!$stmt) throw new RuntimeException('Could not prepare clan victory settlement.');
            $stmt->bind_param('s', $clanName);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Could not persist clan victory settlement.');
            }
            $stmt->close();
        }
        $db->commit();
        pv_server_event('BATTLE', 'Standard battle victory', ['reward_money'=>$money,'clan'=>$clanName]);
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Persist the standard defeat counter and optional clan defeat atomically. */
function pv_battle_runtime_record_defeat(mysqli $db, int $uid, int $now, string $clanName = ''): void
{
    $uid = max(1, $uid);
    $clanName = trim($clanName);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('UPDATE members SET btime=?,losses=losses+1 WHERE id=?');
        if (!$stmt) throw new RuntimeException('Could not prepare trainer defeat settlement.');
        $stmt->bind_param('ii', $now, $uid);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Could not persist trainer defeat settlement.');
        }
        $stmt->close();

        if ($clanName !== '') {
            $stmt = $db->prepare('UPDATE clans SET losses=losses+1 WHERE name=?');
            if (!$stmt) throw new RuntimeException('Could not prepare clan defeat settlement.');
            $stmt->bind_param('s', $clanName);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Could not persist clan defeat settlement.');
            }
            $stmt->close();
        }
        $db->commit();
        pv_server_event('BATTLE', 'Standard battle defeat', ['clan'=>$clanName]);
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
