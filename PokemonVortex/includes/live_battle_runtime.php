<?php
declare(strict_types=1);

require_once __DIR__ . '/combat.php';

/**
 * Server-authoritative Live PvP runtime.
 *
 * Schema 2 deliberately replaces the recovered per-browser PHP-session combat
 * loop. One JSON state lives on the immutable live_battle row; every command is
 * validated and every turn is resolved while that row is locked FOR UPDATE.
 * Browser sessions contain identity only and never calculate battle outcomes.
 */

function pv_live_runtime_decode(?string $raw): ?array
{
    $decoded = json_decode(trim((string)$raw), true);
    if (!is_array($decoded) || (int)($decoded['schema'] ?? 0) !== 2) return null;
    if (!isset($decoded['participants']['1'], $decoded['participants']['2'])) return null;
    return $decoded;
}

function pv_live_runtime_encode(array $state): string
{
    $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json) || strlen($json) > 60000) {
        throw new RuntimeException('The authoritative Live Battle state is too large.');
    }
    return $json;
}

function pv_live_runtime_assert_state(array $state, int $uid1 = 0, int $uid2 = 0): void
{
    if ((int)($state['schema'] ?? 0) !== 2) {
        throw new RuntimeException('Unsupported authoritative Live Battle state schema.');
    }
    $phase = (string)($state['phase'] ?? '');
    if (!in_array($phase, ['select','command','switch','complete'], true)) {
        throw new RuntimeException('Invalid authoritative Live Battle phase.');
    }
    $expectedUids = [1 => $uid1, 2 => $uid2];
    foreach ([1,2] as $slot) {
        $participant = $state['participants'][(string)$slot] ?? null;
        if (!is_array($participant)) throw new RuntimeException('Incomplete authoritative Live Battle participants.');
        $participantUid = max(0, (int)($participant['uid'] ?? 0));
        if ($participantUid <= 0 || ($expectedUids[$slot] > 0 && $participantUid !== $expectedUids[$slot])) {
            throw new RuntimeException('Authoritative Live Battle participant identity mismatch.');
        }
        $team = $participant['team'] ?? null;
        if (!is_array($team) || count($team) < 1 || count($team) > 6) {
            throw new RuntimeException('Invalid authoritative Live Battle team.');
        }
        $ids = [];
        $livingIds = [];
        foreach ($team as $fighter) {
            if (!is_array($fighter)) throw new RuntimeException('Invalid authoritative Live Battle fighter.');
            $pokemonId = max(0, (int)($fighter['id'] ?? 0));
            $maxHp = max(0, (int)($fighter['max_hp'] ?? 0));
            $hp = (int)($fighter['hp'] ?? -1);
            $moves = $fighter['moves'] ?? null;
            if ($pokemonId <= 0 || isset($ids[$pokemonId]) || $maxHp <= 0 || $hp < 0 || $hp > $maxHp
                || !is_array($moves) || count($moves) !== 4) {
                throw new RuntimeException('Corrupt authoritative Live Battle fighter state.');
            }
            $ids[$pokemonId] = true;
            if ($hp > 0) $livingIds[$pokemonId] = true;
        }
        $active = max(0, (int)($participant['active'] ?? 0));
        if ($active > 0 && !isset($ids[$active])) {
            throw new RuntimeException('Invalid authoritative active Pokémon.');
        }
        $command = $participant['command'] ?? null;
        if ($command !== null && (!is_array($command) || !in_array((string)($command['type'] ?? ''), ['attack','switch','item'], true))) {
            throw new RuntimeException('Invalid authoritative Live Battle command.');
        }
        if ($command !== null) {
            $commandType = (string)$command['type'];
            if ($phase !== 'command') throw new RuntimeException('Authoritative command exists outside the command phase.');
            if ($commandType === 'attack' && ((int)($command['move_slot'] ?? 0) < 1 || (int)$command['move_slot'] > 4)) {
                throw new RuntimeException('Invalid authoritative attack command.');
            }
            if ($commandType === 'switch') {
                $target = max(0, (int)($command['pokemon_id'] ?? 0));
                if ($target <= 0 || $target === $active || !isset($livingIds[$target])) {
                    throw new RuntimeException('Invalid authoritative switch command.');
                }
            }
            if ($commandType === 'item' && !isset(pv_live_runtime_item_catalog()[(string)($command['item'] ?? '')])) {
                throw new RuntimeException('Invalid authoritative item command.');
            }
        }
    }
    if ($phase === 'command' && (!pv_live_runtime_active_is_ready($state['participants']['1']) || !pv_live_runtime_active_is_ready($state['participants']['2']))) {
        throw new RuntimeException('Command phase requires two living active Pokémon.');
    }
    $winner = max(0, (int)($state['winner_slot'] ?? 0));
    $loser = max(0, (int)($state['loser_slot'] ?? 0));
    if ($phase === 'complete' && (!in_array($winner, [1,2], true) || !in_array($loser, [1,2], true) || $winner === $loser)) {
        throw new RuntimeException('Invalid authoritative Live Battle result.');
    }
    if ($phase !== 'complete' && ($winner !== 0 || $loser !== 0)) {
        throw new RuntimeException('Premature authoritative Live Battle result.');
    }
}

function pv_live_runtime_add_log(array &$state, string $message, string $tone = 'info'): void
{
    $message = trim($message);
    if ($message === '') return;
    $message = function_exists('mb_substr') ? mb_substr($message, 0, 500) : substr($message, 0, 500);
    $state['log'][] = [
        'turn' => max(0, (int)($state['turn'] ?? 0)),
        'message' => $message,
        'tone' => in_array($tone, ['info','attack','success','danger'], true) ? $tone : 'info',
    ];
    $state['log'] = array_values(array_slice($state['log'], -14));
}

function pv_live_runtime_member_team(mysqli $db, int $userId): array
{
    $stmt = $db->prepare('SELECT id,username,s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');
    if (!$stmt) throw new RuntimeException('Could not load a Live Battle participant.');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$member) throw new RuntimeException('A Live Battle participant no longer exists.');

    $ids = [];
    for ($slot = 1; $slot <= 6; $slot++) {
        $pokemonId = max(0, (int)($member['s'.$slot] ?? 0));
        if ($pokemonId > 0 && !in_array($pokemonId, $ids, true)) $ids[] = $pokemonId;
    }
    if ($ids === []) throw new RuntimeException((string)$member['username'] . ' has no valid active Pokémon.');

    $idList = implode(',', array_map('intval', $ids));
    $stmt = $db->prepare("SELECT id,name,a1,a2,a3,a4,lvl,exp,t1,t2 FROM pokemon WHERE CAST(owner AS UNSIGNED)=? AND id IN ({$idList}) ORDER BY FIELD(id,{$idList})");
    if (!$stmt) throw new RuntimeException('Could not load an authoritative Live Battle team.');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (count($rows) !== count($ids)) {
        throw new RuntimeException((string)$member['username'] . ' must reconfigure their active team before battling.');
    }

    $team = [];
    foreach ($rows as $row) {
        $level = max(1, min(100, (int)($row['lvl'] ?? 1)));
        $name = trim((string)($row['name'] ?? 'Pokémon')) ?: 'Pokémon';
        $maxHp = str_contains($name, 'Shiny') ? $level * 5 : $level * 4;
        $moves = [];
        for ($moveSlot = 1; $moveSlot <= 4; $moveSlot++) {
            $move = trim((string)($row['a'.$moveSlot] ?? ''));
            $moves[] = $move !== '' ? $move : 'Struggle';
        }
        $team[] = [
            'id' => (int)$row['id'],
            'name' => $name,
            'level' => $level,
            'exp' => max(0, (int)($row['exp'] ?? 0)),
            'type1' => trim((string)($row['t1'] ?? 'Normal')) ?: 'Normal',
            'type2' => trim((string)($row['t2'] ?? '')),
            'moves' => $moves,
            'hp' => $maxHp,
            'max_hp' => $maxHp,
            'status' => '',
            'participated' => false,
        ];
    }

    return [
        'uid' => $userId,
        'name' => (string)$member['username'],
        'team' => $team,
        'active' => 0,
        'command' => null,
    ];
}

function pv_live_runtime_initialize(mysqli $db, int $battleId, int $userSlot, int $opponentSlot, int $userId, int $opponentId): array
{
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT uid_1,uid_2,`_2`,settled_1,settled_2 FROM live_battle WHERE id=? AND uid_{$userSlot}=? AND uid_{$opponentSlot}=? FOR UPDATE");
        if (!$stmt) throw new RuntimeException('Could not lock the Live Battle match.');
        $stmt->bind_param('iii', $battleId, $userId, $opponentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new RuntimeException('This Live Battle is no longer available.');

        $state = pv_live_runtime_decode((string)($row['_2'] ?? ''));
        if ($state !== null) {
            pv_live_runtime_assert_state($state, (int)$row['uid_1'], (int)$row['uid_2']);
            $db->commit();
            return $state;
        }
        if ((int)($row['settled_1'] ?? 0) === 1 || (int)($row['settled_2'] ?? 0) === 1) {
            throw new RuntimeException('This Live Battle already has a durable result and cannot be reinitialized.');
        }

        $uid1 = (int)$row['uid_1'];
        $uid2 = (int)$row['uid_2'];
        $state = [
            'schema' => 2,
            'revision' => 1,
            'phase' => 'select',
            'turn' => 0,
            'participants' => [
                '1' => pv_live_runtime_member_team($db, $uid1),
                '2' => pv_live_runtime_member_team($db, $uid2),
            ],
            'winner_slot' => 0,
            'loser_slot' => 0,
            'log' => [],
            'updated_at' => time(),
        ];
        pv_live_runtime_add_log($state, 'Match synchronized. Both trainers must select an active Pokémon.', 'info');
        pv_live_runtime_assert_state($state, $uid1, $uid2);
        $encoded = pv_live_runtime_encode($state);
        $stmt = $db->prepare("UPDATE live_battle SET `_2`=?,initialized_1=1,initialized_2=1,choose_1=0,choose_2=0,pokemon_choice_1=0,pokemon_choice_2=0,pokemon_attack_1='',pokemon_attack_1_2='',pokemon_attack_2='',pokemon_attack_2_2='',user_position_1=0,user_position_2=0,user_position_1_2=0,user_position_2_2=0,user_time_1=0,user_time_2=0,user_time_1_2=0,user_time_2_2=0 WHERE id=? AND uid_{$userSlot}=? AND uid_{$opponentSlot}=?");
        if (!$stmt) throw new RuntimeException('Could not initialize the authoritative Live Battle state.');
        $stmt->bind_param('siii', $encoded, $battleId, $userId, $opponentId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Could not initialize the authoritative Live Battle state.');
        $stmt->close();
        $db->commit();
        return $state;
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }
}

function pv_live_runtime_load(mysqli $db, int $battleId, int $userSlot, int $opponentSlot, int $userId, int $opponentId): array
{
    $stmt = $db->prepare("SELECT uid_1,uid_2,`_2`,settled_1,outcome_1,settled_2,outcome_2 FROM live_battle WHERE id=? AND uid_{$userSlot}=? AND uid_{$opponentSlot}=? LIMIT 1");
    if (!$stmt) throw new RuntimeException('Could not read the Live Battle state.');
    $stmt->bind_param('iii', $battleId, $userId, $opponentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new RuntimeException('This Live Battle is no longer available.');
    $state = pv_live_runtime_decode((string)($row['_2'] ?? ''));
    if ($state === null) throw new RuntimeException('This Live Battle must be initialized again from the arena.');
    pv_live_runtime_assert_state($state, (int)$row['uid_1'], (int)$row['uid_2']);
    $state['_settlement'] = [
        '1' => ['settled'=>(int)($row['settled_1'] ?? 0), 'outcome'=>(string)($row['outcome_1'] ?? '')],
        '2' => ['settled'=>(int)($row['settled_2'] ?? 0), 'outcome'=>(string)($row['outcome_2'] ?? '')],
    ];
    return $state;
}

function pv_live_runtime_fighter_index(array $participant, int $pokemonId): int
{
    foreach (($participant['team'] ?? []) as $index => $fighter) {
        if ((int)($fighter['id'] ?? 0) === $pokemonId) return (int)$index;
    }
    return -1;
}

function pv_live_runtime_active_index(array $participant): int
{
    return pv_live_runtime_fighter_index($participant, (int)($participant['active'] ?? 0));
}

function pv_live_runtime_has_living(array $participant): bool
{
    foreach (($participant['team'] ?? []) as $fighter) {
        if ((int)($fighter['hp'] ?? 0) > 0) return true;
    }
    return false;
}

function pv_live_runtime_active_is_ready(array $participant): bool
{
    $index = pv_live_runtime_active_index($participant);
    return $index >= 0 && (int)($participant['team'][$index]['hp'] ?? 0) > 0;
}

function pv_live_runtime_validate_switch(array $participant, int $pokemonId): int
{
    $index = pv_live_runtime_fighter_index($participant, $pokemonId);
    if ($index < 0 || (int)($participant['team'][$index]['hp'] ?? 0) <= 0) {
        throw new RuntimeException('Choose a living Pokémon from your authoritative active team.');
    }
    return $index;
}

function pv_live_runtime_item_catalog(): array
{
    return [
        'Potion' => ['column'=>'Potion','heal'=>20,'status'=>''],
        'Super Potion' => ['column'=>'Super_Potion','heal'=>100,'status'=>''],
        'Hyper Potion' => ['column'=>'Hyper_Potion','heal'=>250,'status'=>''],
        'Full Heal' => ['column'=>'Full_Heal','heal'=>0,'status'=>'all'],
        'Awakening' => ['column'=>'Awakening','heal'=>0,'status'=>'Sleep'],
        'Parlyz Heal' => ['column'=>'Parlyz_Heal','heal'=>0,'status'=>'Paralyzed'],
        'Antidote' => ['column'=>'Antidote','heal'=>0,'status'=>'Poison'],
        'Burn Heal' => ['column'=>'Burn_Heal','heal'=>0,'status'=>'Burn'],
        'Ice Heal' => ['column'=>'Ice_Heal','heal'=>0,'status'=>'Frozen'],
    ];
}

function pv_live_runtime_inventory(mysqli $db, int $userId): array
{
    $catalog = pv_live_runtime_item_catalog();
    $columns = array_values(array_unique(array_column($catalog, 'column')));
    $select = implode(',', array_map(static fn(string $column): string => '`'.$column.'`', $columns));
    $stmt = $db->prepare("SELECT {$select} FROM items WHERE uid=? LIMIT 1");
    if (!$stmt) return array_fill_keys(array_keys($catalog), 0);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $result = [];
    foreach ($catalog as $label => $definition) {
        $result[$label] = max(0, (int)($row[$definition['column']] ?? 0));
    }
    return $result;
}

function pv_live_runtime_use_item(mysqli $db, array &$state, int $slot, string $item): void
{
    $catalog = pv_live_runtime_item_catalog();
    if (!isset($catalog[$item])) throw new RuntimeException('That item is not supported in Live PvP.');
    $participant =& $state['participants'][(string)$slot];
    $activeIndex = pv_live_runtime_active_index($participant);
    if ($activeIndex < 0 || (int)$participant['team'][$activeIndex]['hp'] <= 0) {
        pv_live_runtime_add_log($state, $participant['name'] . ' could not use ' . $item . ' without an active Pokémon.', 'danger');
        return;
    }
    $definition = $catalog[$item];
    $fighter =& $participant['team'][$activeIndex];
    $requiredStatus = (string)$definition['status'];
    $currentStatus = (string)($fighter['status'] ?? '');
    if ((int)$definition['heal'] > 0 && (int)$fighter['hp'] >= (int)$fighter['max_hp']) {
        pv_live_runtime_add_log($state, $fighter['name'] . ' was already at full HP; ' . $item . ' was not consumed.', 'info');
        return;
    }
    if ((int)$definition['heal'] <= 0 && $requiredStatus !== 'all' && $currentStatus !== $requiredStatus) {
        pv_live_runtime_add_log($state, $fighter['name'] . ' did not need ' . $item . '; the item was not consumed.', 'info');
        return;
    }
    if ((int)$definition['heal'] <= 0 && $requiredStatus === 'all' && $currentStatus === '') {
        pv_live_runtime_add_log($state, $fighter['name'] . ' had no status to heal; ' . $item . ' was not consumed.', 'info');
        return;
    }
    $column = (string)$definition['column'];
    $userId = (int)$participant['uid'];
    $stmt = $db->prepare("UPDATE items SET `{$column}`=`{$column}`-1 WHERE uid=? AND `{$column}`>0");
    if (!$stmt) throw new RuntimeException('Could not validate the Live Battle item inventory.');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $consumed = $stmt->affected_rows === 1;
    $stmt->close();
    if (!$consumed) {
        pv_live_runtime_add_log($state, $participant['name'] . ' no longer has ' . $item . '.', 'danger');
        return;
    }

    $heal = max(0, (int)$definition['heal']);
    if ($heal > 0) {
        $before = (int)$fighter['hp'];
        $fighter['hp'] = min((int)$fighter['max_hp'], $before + $heal);
        $restored = (int)$fighter['hp'] - $before;
        pv_live_runtime_add_log($state, $fighter['name'] . ' used ' . $item . ' and restored ' . $restored . ' HP.', 'success');
        return;
    }
    if ($requiredStatus === 'all' || $currentStatus === $requiredStatus) {
        $fighter['status'] = '';
        pv_live_runtime_add_log($state, $fighter['name'] . ' used ' . $item . ' and cleared its status.', 'success');
    } else {
        pv_live_runtime_add_log($state, $fighter['name'] . ' used ' . $item . ', but it had no applicable status.', 'info');
    }
}

function pv_live_runtime_damage(mysqli $db, array &$state, int $attackerSlot, int $defenderSlot, int $moveSlot): void
{
    $attackerParticipant =& $state['participants'][(string)$attackerSlot];
    $defenderParticipant =& $state['participants'][(string)$defenderSlot];
    $attackerIndex = pv_live_runtime_active_index($attackerParticipant);
    $defenderIndex = pv_live_runtime_active_index($defenderParticipant);
    if ($attackerIndex < 0 || $defenderIndex < 0) return;
    $attacker =& $attackerParticipant['team'][$attackerIndex];
    $defender =& $defenderParticipant['team'][$defenderIndex];
    if ((int)$attacker['hp'] <= 0) {
        pv_live_runtime_add_log($state, $attacker['name'] . ' had fainted before it could act.', 'danger');
        return;
    }

    $attacker['participated'] = true;
    $moveName = (string)($attacker['moves'][$moveSlot - 1] ?? 'Struggle');
    $move = pv_combat_move_data($db, $moveName, (string)$attacker['type1']);
    if (str_contains((string)$defender['name'], 'Mystic') && random_int(1, 4) === 2) {
        pv_live_runtime_add_log($state, $attacker['name'] . ' was too scared of ' . $defender['name'] . ' to attack.', 'danger');
        return;
    }
    if (random_int(1, 100) > (int)$move['accuracy']) {
        pv_live_runtime_add_log($state, $attacker['name'] . ' used ' . $move['attack'] . ', but the attack missed.', 'attack');
        return;
    }

    $power = max(0, (int)$move['power']);
    $type = (string)$move['type'];
    $effectiveness = pv_combat_type_multiplier($type, (string)$defender['type1'], (string)$defender['type2']);
    $stab = strcasecmp($type, (string)$attacker['type1']) === 0 || strcasecmp($type, (string)$attacker['type2']) === 0 ? 1.5 : 1.0;
    $variant = 1.0;
    if (str_contains((string)$attacker['name'], 'Dark ')) $variant += 0.25;
    if (str_contains((string)$defender['name'], 'Metallic ')) $variant -= 0.25;
    $variant = max(0.1, $variant);
    $damage = (int)round(((int)$attacker['level'] / 30) * ($power / 2) * $effectiveness * $stab * $variant);
    $damage = max(0, min((int)$defender['hp'], $damage));
    $defender['hp'] = max(0, (int)$defender['hp'] - $damage);
    pv_live_runtime_add_log($state, $attacker['name'] . ' used ' . $move['attack'] . ' and dealt ' . $damage . ' HP damage (' . pv_combat_effectiveness_label($effectiveness) . ').', 'attack');
    if ((int)$defender['hp'] === 0) {
        pv_live_runtime_add_log($state, $defender['name'] . ' fainted.', 'danger');
    }
}

function pv_live_runtime_finish_or_advance(array &$state): void
{
    $alive1 = pv_live_runtime_has_living($state['participants']['1']);
    $alive2 = pv_live_runtime_has_living($state['participants']['2']);
    if (!$alive1 || !$alive2) {
        if (!$alive1 && !$alive2) {
            // Sequential initiative should prevent a double knockout, but keep a
            // deterministic invariant if imported/corrupt state reaches here.
            $winner = ((int)$state['turn'] % 2) === 0 ? 1 : 2;
        } else {
            $winner = $alive1 ? 1 : 2;
        }
        $loser = $winner === 1 ? 2 : 1;
        $state['winner_slot'] = $winner;
        $state['loser_slot'] = $loser;
        $state['phase'] = 'complete';
        pv_live_runtime_add_log($state, $state['participants'][(string)$winner]['name'] . ' won the Live Battle.', 'success');
        return;
    }

    $needsSwitch = false;
    foreach ([1,2] as $slot) {
        $participant =& $state['participants'][(string)$slot];
        if (!pv_live_runtime_active_is_ready($participant)) {
            $participant['active'] = 0;
            $needsSwitch = true;
        }
    }
    $state['phase'] = $needsSwitch ? 'switch' : 'command';
}

function pv_live_runtime_resolve_turn(mysqli $db, int $battleId, array &$state): void
{
    $commands = [
        1 => $state['participants']['1']['command'] ?? null,
        2 => $state['participants']['2']['command'] ?? null,
    ];
    if (!is_array($commands[1]) || !is_array($commands[2])) return;
    $state['turn'] = max(0, (int)$state['turn']) + 1;
    pv_live_runtime_add_log($state, 'Turn ' . $state['turn'] . ' resolved by the server.', 'info');

    // Switching occurs before items and attacks. An attack therefore targets
    // the newly switched-in Pokémon, matching a conventional turn battle.
    foreach ([1,2] as $slot) {
        if (($commands[$slot]['type'] ?? '') !== 'switch') continue;
        $participant =& $state['participants'][(string)$slot];
        $pokemonId = (int)($commands[$slot]['pokemon_id'] ?? 0);
        $index = pv_live_runtime_validate_switch($participant, $pokemonId);
        if ((int)$participant['active'] !== $pokemonId) {
            $participant['active'] = $pokemonId;
            $participant['team'][$index]['participated'] = true;
            pv_live_runtime_add_log($state, $participant['name'] . ' switched to ' . $participant['team'][$index]['name'] . '.', 'info');
        }
    }

    foreach ([1,2] as $slot) {
        if (($commands[$slot]['type'] ?? '') === 'item') {
            pv_live_runtime_use_item($db, $state, $slot, (string)($commands[$slot]['item'] ?? ''));
        }
    }

    $attackers = [];
    foreach ([1,2] as $slot) {
        if (($commands[$slot]['type'] ?? '') !== 'attack') continue;
        $participant = $state['participants'][(string)$slot];
        $index = pv_live_runtime_active_index($participant);
        $level = $index >= 0 ? (int)($participant['team'][$index]['level'] ?? 1) : 1;
        $attackers[] = ['slot'=>$slot, 'level'=>$level, 'move_slot'=>(int)($commands[$slot]['move_slot'] ?? 0)];
    }
    $tieFirst = (($battleId + (int)$state['turn']) % 2) + 1;
    usort($attackers, static function(array $a, array $b) use ($tieFirst): int {
        if ($a['level'] !== $b['level']) return $b['level'] <=> $a['level'];
        if ($a['slot'] === $b['slot']) return 0;
        return $a['slot'] === $tieFirst ? -1 : 1;
    });
    foreach ($attackers as $attack) {
        $slot = (int)$attack['slot'];
        pv_live_runtime_damage($db, $state, $slot, $slot === 1 ? 2 : 1, (int)$attack['move_slot']);
    }

    $state['participants']['1']['command'] = null;
    $state['participants']['2']['command'] = null;
    pv_live_runtime_finish_or_advance($state);
}

function pv_live_runtime_submit(mysqli $db, int $battleId, int $userSlot, int $opponentSlot, int $userId, int $opponentId, string $action, array $payload): array
{
    if (!in_array($userSlot, [1,2], true) || !in_array($opponentSlot, [1,2], true) || $userSlot === $opponentSlot) {
        throw new RuntimeException('Invalid Live Battle participant role.');
    }
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT uid_1,uid_2,`_2`,settled_1,settled_2 FROM live_battle WHERE id=? AND uid_{$userSlot}=? AND uid_{$opponentSlot}=? FOR UPDATE");
        if (!$stmt) throw new RuntimeException('Could not lock the authoritative Live Battle turn.');
        $stmt->bind_param('iii', $battleId, $userId, $opponentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new RuntimeException('This Live Battle is no longer available.');
        $state = pv_live_runtime_decode((string)($row['_2'] ?? ''));
        if ($state === null) throw new RuntimeException('This Live Battle is not using the authoritative runtime. Start a fresh challenge.');
        pv_live_runtime_assert_state($state, (int)$row['uid_1'], (int)$row['uid_2']);
        if ((int)($state['participants'][(string)$userSlot]['uid'] ?? 0) !== $userId) {
            throw new RuntimeException('Live Battle participant identity mismatch.');
        }
        if (($state['phase'] ?? '') === 'complete' || (int)($row['settled_1'] ?? 0) === 1 || (int)($row['settled_2'] ?? 0) === 1) {
            $db->commit();
            return $state;
        }

        $participant =& $state['participants'][(string)$userSlot];
        $phase = (string)($state['phase'] ?? '');
        if ($action === 'forfeit') {
            $winner = $opponentSlot;
            $loser = $userSlot;
            $winnerParticipant =& $state['participants'][(string)$winner];
            $winnerActiveIndex = pv_live_runtime_active_index($winnerParticipant);
            if ($winnerActiveIndex >= 0 && (int)$winnerParticipant['team'][$winnerActiveIndex]['hp'] > 0) {
                $winnerParticipant['team'][$winnerActiveIndex]['participated'] = true;
            } else {
                foreach ($winnerParticipant['team'] as &$fighter) {
                    if ((int)$fighter['hp'] > 0) { $fighter['participated'] = true; break; }
                }
                unset($fighter);
            }
            $state['winner_slot'] = $winner;
            $state['loser_slot'] = $loser;
            $state['phase'] = 'complete';
            $state['participants']['1']['command'] = null;
            $state['participants']['2']['command'] = null;
            pv_live_runtime_add_log($state, $participant['name'] . ' forfeited the match.', 'danger');
        } elseif ($action === 'select') {
            if (!in_array($phase, ['select','switch'], true)) throw new RuntimeException('An active Pokémon cannot be selected during this phase.');
            if (pv_live_runtime_active_is_ready($participant)) throw new RuntimeException('Your active Pokémon is already locked in.');
            $pokemonId = max(0, (int)($payload['pokemon_id'] ?? 0));
            $index = pv_live_runtime_validate_switch($participant, $pokemonId);
            $participant['active'] = $pokemonId;
            $participant['team'][$index]['participated'] = true;
            pv_live_runtime_add_log($state, $participant['name'] . ' selected ' . $participant['team'][$index]['name'] . '.', 'info');
            if (pv_live_runtime_active_is_ready($state['participants']['1']) && pv_live_runtime_active_is_ready($state['participants']['2'])) {
                $state['phase'] = 'command';
                pv_live_runtime_add_log($state, 'Both trainers are ready. Select a command.', 'success');
            }
        } else {
            if ($phase !== 'command') throw new RuntimeException('Battle commands are not available during this phase.');
            if (!pv_live_runtime_active_is_ready($participant)) throw new RuntimeException('Select a living active Pokémon first.');
            if (is_array($participant['command'] ?? null)) throw new RuntimeException('Your command is already locked for this turn.');
            if ($action === 'attack') {
                $moveSlot = (int)($payload['move_slot'] ?? 0);
                if ($moveSlot < 1 || $moveSlot > 4) throw new RuntimeException('Choose a valid move slot.');
                $participant['command'] = ['type'=>'attack','move_slot'=>$moveSlot,'submitted_at'=>time()];
            } elseif ($action === 'switch') {
                $pokemonId = max(0, (int)($payload['pokemon_id'] ?? 0));
                pv_live_runtime_validate_switch($participant, $pokemonId);
                if ($pokemonId === (int)$participant['active']) throw new RuntimeException('That Pokémon is already active.');
                $participant['command'] = ['type'=>'switch','pokemon_id'=>$pokemonId,'submitted_at'=>time()];
            } elseif ($action === 'item') {
                $item = trim((string)($payload['item'] ?? ''));
                if (!isset(pv_live_runtime_item_catalog()[$item])) throw new RuntimeException('Choose a supported Live Battle item.');
                $participant['command'] = ['type'=>'item','item'=>$item,'submitted_at'=>time()];
            } else {
                throw new RuntimeException('Unknown Live Battle command.');
            }
            if (is_array($state['participants']['1']['command']) && is_array($state['participants']['2']['command'])) {
                pv_live_runtime_resolve_turn($db, $battleId, $state);
            }
        }

        $state['revision'] = max(0, (int)($state['revision'] ?? 0)) + 1;
        $state['updated_at'] = time();
        pv_live_runtime_assert_state($state, (int)$row['uid_1'], (int)$row['uid_2']);
        $encoded = pv_live_runtime_encode($state);
        $stmt = $db->prepare("UPDATE live_battle SET `_2`=? WHERE id=? AND uid_{$userSlot}=? AND uid_{$opponentSlot}=?");
        if (!$stmt) throw new RuntimeException('Could not persist the authoritative Live Battle turn.');
        $stmt->bind_param('siii', $encoded, $battleId, $userId, $opponentId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('The Live Battle state changed before it could be saved.');
        $stmt->close();
        $db->commit();
        return $state;
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }
}

function pv_live_runtime_project_session(array $state, int $userSlot): void
{
    $opponentSlot = $userSlot === 1 ? 2 : 1;
    $own = $state['participants'][(string)$userSlot] ?? [];
    $opponent = $state['participants'][(string)$opponentSlot] ?? [];
    pv_live_clear_combat_session(true);
    $_SESSION['your_profile'] = [(string)($own['uid'] ?? 0), (string)($own['name'] ?? 'Trainer'), (string)count($own['team'] ?? []), '1'];
    $_SESSION['opponent_profile'] = [(string)($opponent['uid'] ?? 0), (string)($opponent['name'] ?? 'Opponent'), (string)count($opponent['team'] ?? []), 'live'];
    foreach ([['prefix'=>'s','participant'=>$own], ['prefix'=>'ops','participant'=>$opponent]] as $projection) {
        $team = $projection['participant']['team'] ?? [];
        for ($index = 0; $index < 6; $index++) {
            $fighter = $team[$index] ?? null;
            if (!is_array($fighter)) {
                $_SESSION[$projection['prefix'].($index + 1)] = ['',0,'','',0,0,'','','','',0,0,0,0,'',0];
                continue;
            }
            $maxHp = max(1, (int)($fighter['max_hp'] ?? 1));
            $hp = max(0, (int)($fighter['hp'] ?? 0));
            $moves = array_pad(array_slice((array)($fighter['moves'] ?? []), 0, 4), 4, 'Struggle');
            $_SESSION[$projection['prefix'].($index + 1)] = [
                (string)$fighter['name'], (int)$fighter['id'], (string)$fighter['type1'], (string)$fighter['type2'],
                (int)$fighter['level'], (int)$fighter['exp'], (string)$moves[0], (string)$moves[1], (string)$moves[2], (string)$moves[3],
                $hp, $maxHp, (int)round(($hp / $maxHp) * 100), !empty($fighter['participated']) ? 1 : 0,
                (string)($fighter['status'] ?? ''), 0,
            ];
        }
    }
}
