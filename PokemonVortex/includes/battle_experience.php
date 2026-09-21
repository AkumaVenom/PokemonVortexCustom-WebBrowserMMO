<?php
declare(strict_types=1);
require_once __DIR__ . '/experience.php';

/** Keep original species and per-opponent participation separate from Transform. */
function pv_battle_exp_begin(bool $resuming = false): void
{
    $opponents = [];
    for ($slot = 1; $slot <= min(6, (int)($_SESSION['opponent_profile'][2] ?? 0)); $slot++) {
        $row = $_SESSION['ops'.$slot] ?? [];
        if ((int)($row[1] ?? 0) <= 0) continue;
        $opponents[$slot] = [
            'id'=>(int)$row[1], 'name'=>(string)$row[0], 'level'=>max(1, (int)$row[4]),
            'participants'=>[], 'done'=>$resuming && (int)($row[10] ?? 0) <= 0,
        ];
    }
    $_SESSION['pv_standard_exp'] = ['id'=>bin2hex(random_bytes(16)), 'opponents'=>$opponents, 'awards'=>[]];
}

function pv_battle_exp_note_active(): void
{
    $ownSlot = (int)($_SESSION['y_p'][0] ?? 0);
    $opponentSlot = (int)($_SESSION['y_p'][1] ?? 0);
    $own = $_SESSION['s'.$ownSlot] ?? [];
    $opponent = $_SESSION['ops'.$opponentSlot] ?? [];
    $snapshot = $_SESSION['pv_standard_exp']['opponents'][$opponentSlot] ?? null;
    if (!is_array($snapshot) || !empty($snapshot['done']) || (int)($own[10] ?? 0) <= 0 || (int)($opponent[10] ?? 0) <= 0) return;
    if ((int)($own[1] ?? 0) <= 0 || (int)($opponent[1] ?? 0) !== (int)$snapshot['id']) return;
    $_SESSION['pv_standard_exp']['opponents'][$opponentSlot]['participants'][(int)$own[1]] = true;
    $_SESSION['s'.$ownSlot][13] = 1;
}

/** Freeze eligible survivors when each opposing Pokémon faints, not at match end. */
function pv_battle_exp_pending(): array
{
    $pending = [];
    foreach ((array)($_SESSION['pv_standard_exp']['opponents'] ?? []) as $slot => $opponent) {
        $current = $_SESSION['ops'.$slot] ?? [];
        if (!empty($opponent['done']) || (int)($current[1] ?? 0) !== (int)$opponent['id'] || (int)($current[10] ?? 1) > 0) continue;
        $eligible = [];
        for ($ownSlot = 1; $ownSlot <= 6; $ownSlot++) {
            $own = $_SESSION['s'.$ownSlot] ?? [];
            $id = (int)($own[1] ?? 0);
            if ($id > 0 && !empty($opponent['participants'][$id]) && (int)($own[10] ?? 0) > 0) $eligible[$id] = $ownSlot;
        }
        $pending[(int)$slot] = ['opponent'=>$opponent, 'eligible'=>$eligible];
    }
    return $pending;
}

function pv_battle_exp_total(): int
{
    $total = 0;
    foreach ((array)($_SESSION['pv_standard_exp']['awards'] ?? []) as $awards) $total += array_sum($awards);
    return $total;
}
