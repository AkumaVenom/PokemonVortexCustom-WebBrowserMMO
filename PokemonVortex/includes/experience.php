<?php
declare(strict_types=1);

/** Species-aware Generation III experience. No database or network dependency. */
function pv_exp_name_key(string $name): string
{
    $name = preg_replace('/^(?:(?:Shiny|Dark|Metallic|Mystic|Shadow|Ancient)\s+)+/i', '', trim($name)) ?? $name;
    $name = str_replace(['♀', '♂', '’', 'é'], ['f', 'm', "'", 'e'], $name);
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name) ?? $name);
}

function pv_exp_data(): array
{
    static $data = null;
    if ($data === null) {
        $data = require dirname(__DIR__) . '/data/firered_progression.php';
        if (count($data['experience_tables'] ?? []) !== 6) throw new RuntimeException('Experience tables are incomplete.');
    }
    return $data;
}

function pv_exp_species(string $name): array
{
    static $species = null;
    static $aliases = [];
    if ($species === null) {
        $species = [];
        $supplement = dirname(__DIR__) . '/data/experience_species.json';
        if (is_file($supplement)) {
            $extra = json_decode((string)file_get_contents($supplement), true, 512, JSON_THROW_ON_ERROR);
            foreach (($extra['species'] ?? []) as $key => $row) $species[pv_exp_name_key((string)$key)] = $row;
            $aliases = (array)($extra['aliases'] ?? []);
        }
        // FireRed bytes are authoritative wherever that game contains the species.
        foreach (pv_exp_data()['species'] as $dex => $row) {
            $row['national_dex'] = (int)$dex;
            $row['source'] = 'firered-rom';
            $species[pv_exp_name_key($row['name'])] = $row;
        }
        // Forms already present in Generation III share that ROM species' yield.
        // Later Mega/Primal forms retain the explicitly sourced supplemental policy.
        foreach (['castform-sunny'=>'castform', 'castform-rainy'=>'castform',
            'castform-snowy'=>'castform', 'deoxys-normal'=>'deoxys',
            'deoxys-attack'=>'deoxys', 'deoxys-defense'=>'deoxys', 'deoxys-speed'=>'deoxys'] as $form=>$base) {
            $species[pv_exp_name_key($form)] = $species[$base];
        }
    }
    $key = pv_exp_name_key($name);
    if (isset($aliases[$key])) $key = pv_exp_name_key((string)$aliases[$key]);
    if (isset($species[$key])) return $species[$key];
    // Cosmetic/event forms use their species growth group, never a catalogue ID.
    $base = preg_replace('/\s*\([^)]*\)\s*/', '', $name) ?? $name;
    $baseKey = pv_exp_name_key($base);
    if (isset($species[$baseKey])) return $species[$baseKey];
    throw new InvalidArgumentException('No experience metadata for Pokémon: ' . $name);
}

function pv_exp_at_level(string $species, int $level): int
{
    $group = pv_exp_species($species)['growth_group'];
    return (int)pv_exp_data()['experience_tables'][$group][max(1, min(100, $level))];
}

function pv_exp_level_for(string $species, int $exp): int
{
    $table = pv_exp_data()['experience_tables'][pv_exp_species($species)['growth_group']];
    $lo = 1; $hi = 100; $exp = max(0, $exp);
    while ($lo < $hi) {
        $mid = intdiv($lo + $hi + 1, 2);
        if ($exp >= $table[$mid]) $lo = $mid; else $hi = $mid - 1;
    }
    return $lo;
}

/** Preserve level and fractional legacy 500-EXP progress during the one-time conversion. */
function pv_exp_state(array $row): array
{
    $name = (string)$row['name'];
    $level = max(1, min(100, (int)($row['lvl'] ?? 1)));
    $exp = max(0, (int)($row['exp'] ?? 0));
    $floor = pv_exp_at_level($name, $level);
    $cap = pv_exp_at_level($name, 100);
    if ((int)($row['exp_curve_version'] ?? 0) < 1) {
        // Corrupt/over-awarded legacy values must not invent additional levels.
        $remainder = max(0, min(499, $exp - $level * 500));
        $exp = $level === 100 ? $cap : $floor + intdiv((pv_exp_at_level($name, $level + 1) - $floor) * $remainder, 500);
    } else {
        // Rows created/modified outside gameplay may be inconsistent; never demote a stored level.
        $exp = min($cap, max($floor, $exp));
        $level = max($level, pv_exp_level_for($name, $exp));
    }
    return ['lvl'=>$level, 'exp'=>$exp, 'exp_curve_version'=>1];
}

function pv_exp_award(array $row, int $gain): array
{
    $state = pv_exp_state($row);
    $cap = pv_exp_at_level((string)$row['name'], 100);
    $newExp = $state['exp'] + min($cap - $state['exp'], max(0, $gain));
    $level = pv_exp_level_for((string)$row['name'], $newExp);
    return ['lvl'=>$level, 'exp'=>$newExp, 'gained'=>$newExp - $state['exp'],
        'level_ups'=>max(0, $level - $state['lvl']), 'exp_curve_version'=>1];
}

/** Preserve fractional level progress on an explicit species/form change. */
function pv_exp_change_species(array $row, string $target): array
{
    $state = pv_exp_state($row);
    $level = $state['lvl'];
    $floor = pv_exp_at_level($target, $level);
    if ($level >= 100) return ['lvl'=>100, 'exp'=>$floor, 'exp_curve_version'=>1];
    $oldFloor = pv_exp_at_level((string)$row['name'], $level);
    $oldStep = pv_exp_at_level((string)$row['name'], $level + 1) - $oldFloor;
    $newStep = pv_exp_at_level($target, $level + 1) - $floor;
    return ['lvl'=>$level, 'exp'=>$floor + intdiv(($state['exp'] - $oldFloor) * $newStep, max(1, $oldStep)), 'exp_curve_version'=>1];
}

/** Gen III's integer rounding order; no account/event level-ratio multipliers. */
function pv_exp_battle_gain(string $defeatedSpecies, int $level, int $participants = 1, bool $trainer = false, bool $traded = false): int
{
    if ($participants < 1) return 0;
    $base = (int)pv_exp_species($defeatedSpecies)['base_exp'];
    $exp = max(1, intdiv(intdiv($base * max(1, min(100, $level)), 7), $participants));
    if ($trainer) $exp = intdiv($exp * 150, 100);
    if ($traded) $exp = intdiv($exp * 150, 100);
    return $exp;
}
