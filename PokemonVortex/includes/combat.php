<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Unified combat metadata and type authority.
 *
 * NPC battles, Wild Battle and Live PvP all route through this module so the
 * same move metadata normalization and 18-type chart is used everywhere.
 * Vortex-specific form modifiers (Dark/Metallic/Mystic/Shiny/Shadow) remain in
 * their established battle runtimes and are deliberately not duplicated here.
 */

function pv_combat_type_chart(): array
{
    static $chart = null;
    if ($chart !== null) return $chart;

    $chart = [
        'normal'   => ['rock'=>0.5,'ghost'=>0.0,'steel'=>0.5],
        'fire'     => ['fire'=>0.5,'water'=>0.5,'grass'=>2.0,'ice'=>2.0,'bug'=>2.0,'rock'=>0.5,'dragon'=>0.5,'steel'=>2.0],
        'water'    => ['fire'=>2.0,'water'=>0.5,'grass'=>0.5,'ground'=>2.0,'rock'=>2.0,'dragon'=>0.5],
        'electric' => ['water'=>2.0,'electric'=>0.5,'grass'=>0.5,'ground'=>0.0,'flying'=>2.0,'dragon'=>0.5],
        'grass'    => ['fire'=>0.5,'water'=>2.0,'grass'=>0.5,'poison'=>0.5,'ground'=>2.0,'flying'=>0.5,'bug'=>0.5,'rock'=>2.0,'dragon'=>0.5,'steel'=>0.5],
        'ice'      => ['fire'=>0.5,'water'=>0.5,'grass'=>2.0,'ice'=>0.5,'ground'=>2.0,'flying'=>2.0,'dragon'=>2.0,'steel'=>0.5],
        'fighting' => ['normal'=>2.0,'ice'=>2.0,'poison'=>0.5,'flying'=>0.5,'psychic'=>0.5,'bug'=>0.5,'rock'=>2.0,'ghost'=>0.0,'dark'=>2.0,'steel'=>2.0,'fairy'=>0.5],
        'poison'   => ['grass'=>2.0,'poison'=>0.5,'ground'=>0.5,'rock'=>0.5,'ghost'=>0.5,'steel'=>0.0,'fairy'=>2.0],
        'ground'   => ['fire'=>2.0,'electric'=>2.0,'grass'=>0.5,'poison'=>2.0,'flying'=>0.0,'bug'=>0.5,'rock'=>2.0,'steel'=>2.0],
        'flying'   => ['electric'=>0.5,'grass'=>2.0,'fighting'=>2.0,'bug'=>2.0,'rock'=>0.5,'steel'=>0.5],
        'psychic'  => ['fighting'=>2.0,'poison'=>2.0,'psychic'=>0.5,'dark'=>0.0,'steel'=>0.5],
        'bug'      => ['fire'=>0.5,'grass'=>2.0,'fighting'=>0.5,'poison'=>0.5,'flying'=>0.5,'psychic'=>2.0,'ghost'=>0.5,'dark'=>2.0,'steel'=>0.5,'fairy'=>0.5],
        'rock'     => ['fire'=>2.0,'ice'=>2.0,'fighting'=>0.5,'ground'=>0.5,'flying'=>2.0,'bug'=>2.0,'steel'=>0.5],
        'ghost'    => ['normal'=>0.0,'psychic'=>2.0,'ghost'=>2.0,'dark'=>0.5],
        'dragon'   => ['dragon'=>2.0,'steel'=>0.5,'fairy'=>0.0],
        'dark'     => ['fighting'=>0.5,'psychic'=>2.0,'ghost'=>2.0,'dark'=>0.5,'fairy'=>0.5],
        'steel'    => ['fire'=>0.5,'water'=>0.5,'electric'=>0.5,'ice'=>2.0,'rock'=>2.0,'steel'=>0.5,'fairy'=>2.0],
        'fairy'    => ['fire'=>0.5,'fighting'=>2.0,'poison'=>0.5,'dragon'=>2.0,'dark'=>2.0,'steel'=>0.5],
    ];
    return $chart;
}

function pv_combat_type_effectiveness(string $attackType, string $defenderType): float
{
    $attack = strtolower(trim($attackType));
    $defender = strtolower(trim($defenderType));
    if ($attack === '' || $defender === '') return 1.0;
    $chart = pv_combat_type_chart();
    return (float)($chart[$attack][$defender] ?? 1.0);
}

function pv_combat_type_multiplier(string $attackType, string $defenderType1, string $defenderType2 = ''): float
{
    $multiplier = pv_combat_type_effectiveness($attackType, $defenderType1);
    if (trim($defenderType2) !== '') {
        $multiplier *= pv_combat_type_effectiveness($attackType, $defenderType2);
    }
    return $multiplier;
}

function pv_combat_effectiveness_label(float $multiplier): string
{
    if ($multiplier <= 0.0) return 'NO EFFECT';
    if ($multiplier >= 4.0) return 'EXTREMELY EFFECTIVE';
    if ($multiplier > 1.0) return 'SUPER EFFECTIVE';
    if ($multiplier < 1.0) return 'NOT VERY EFFECTIVE';
    return 'NORMAL EFFECTIVENESS';
}

function pv_combat_move_data(mysqli $db, string $move, string $fallbackType = 'Normal'): array
{
    $move = trim($move);
    if ($move === '') $move = 'Struggle';
    $fallbackType = trim($fallbackType) !== '' ? trim($fallbackType) : 'Normal';

    $stmt = $db->prepare('SELECT attack,type,power,accuracy,category FROM attacks WHERE attack=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $move);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $type = trim((string)($row['type'] ?? ''));
            $category = trim((string)($row['category'] ?? ''));
            return [
                'attack'=>(string)($row['attack'] ?? $move),
                'type'=>$type !== '' ? $type : $fallbackType,
                'power'=>max(0, (int)($row['power'] ?? 0)),
                'accuracy'=>max(1, min(100, (int)($row['accuracy'] ?? 100) ?: 100)),
                'category'=>$category !== '' ? $category : 'Physical',
            ];
        }
    }

    // Fail closed to a deterministic damaging move so recovered NPC/team data
    // cannot produce null arithmetic or a zero-damage soft lock.
    return [
        'attack'=>$move,
        'type'=>$fallbackType,
        'power'=>50,
        'accuracy'=>100,
        'category'=>'Physical',
    ];
}
