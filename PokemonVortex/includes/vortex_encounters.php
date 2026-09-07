<?php
declare(strict_types=1);

require_once __DIR__ . '/wild_level_balance.php';

/**
 * Server-owned encounter selection for the original 25-map Vortex world.
 *
 * Replaces execution of the recovered public xml/mappoke*.php scripts with a
 * private, declarative catalogue.  The catalogue preserves source species,
 * day/night pools, variant windows and legendary signal windows while using
 * real array lengths for random selection so historical hand-maintained index
 * bounds cannot truncate a pool or produce an undefined slot.
 */

function pv_vortex_encounter_validate_catalog(array $catalog): void
{
    if (array_keys($catalog) !== ['day', 'night']) {
        throw new RuntimeException('Vortex encounter catalogue must contain exact day/night periods.');
    }

    $expectedModes = [1, 3, 4, 5, 6, 7, 8, 10];
    $allowedVariants = ['', 'Shiny', 'Dark', 'Metallic', 'Mystic', 'Shadow'];

    foreach (['day', 'night'] as $period) {
        if (!isset($catalog[$period]) || !is_array($catalog[$period])) {
            throw new RuntimeException('Vortex encounter catalogue period is invalid: ' . $period . '.');
        }
        $modes = array_map('intval', array_keys($catalog[$period]));
        sort($modes);
        if ($modes !== $expectedModes) {
            throw new RuntimeException('Vortex encounter catalogue has an invalid mode set for ' . $period . '.');
        }

        foreach ($expectedModes as $mode) {
            $pool = $catalog[$period][$mode] ?? null;
            if (!is_array($pool) || trim((string)($pool['source'] ?? '')) === '') {
                throw new RuntimeException("Vortex encounter pool $period/$mode is missing source metadata.");
            }
            foreach (['low', 'legendary', 'high'] as $tier) {
                $species = $pool[$tier] ?? null;
                if (!is_array($species) || $species === [] || count(array_filter(array_map('is_string', $species))) !== count($species)) {
                    throw new RuntimeException("Vortex encounter pool $period/$mode has an invalid $tier species list.");
                }
                foreach ($species as $name) {
                    if (trim($name) === '') throw new RuntimeException("Vortex encounter pool $period/$mode contains an empty species name.");
                }
            }

            foreach ([['low_level', 1, PV_WILD_LEVEL_CAP], ['legendary_level', 1, PV_WILD_LEVEL_CAP], ['high_level', 1, PV_WILD_LEVEL_CAP], ['rare_signal', 665, 1000], ['rare_roll', 1, PHP_INT_MAX]] as [$field, $absoluteMin, $absoluteMax]) {
                $range = $pool[$field] ?? null;
                if (!is_array($range) || count($range) !== 2) {
                    throw new RuntimeException("Vortex encounter pool $period/$mode has an invalid $field range.");
                }
                $min = (int)$range[0];
                $max = (int)$range[1];
                if ($min < $absoluteMin || $max > $absoluteMax || $min > $max) {
                    throw new RuntimeException("Vortex encounter pool $period/$mode has an out-of-range $field contract.");
                }
            }

            $rareRoll = $pool['rare_roll'];
            $legendaryRollMax = (int)($pool['legendary_roll_max'] ?? 0);
            if ($legendaryRollMax < (int)$rareRoll[0] || $legendaryRollMax >= (int)$rareRoll[1]) {
                throw new RuntimeException("Vortex encounter pool $period/$mode has an invalid legendary roll boundary.");
            }

            foreach ((array)($pool['variant_windows'] ?? []) as $window) {
                if (!is_array($window)) throw new RuntimeException("Vortex encounter pool $period/$mode contains invalid variant metadata.");
                $min = (int)($window['min'] ?? 0);
                $max = (int)($window['max'] ?? -1);
                $variant = trim((string)($window['variant'] ?? ''));
                if ($min < 665 || $max > 1000 || $min > $max || !in_array($variant, $allowedVariants, true) || $variant === '') {
                    throw new RuntimeException("Vortex encounter pool $period/$mode contains an invalid variant window.");
                }
            }

            foreach ((array)($pool['legendary_variants'] ?? []) as $roll=>$variant) {
                $roll = (int)$roll;
                $variant = trim((string)$variant);
                if ($roll < (int)$rareRoll[0] || $roll > $legendaryRollMax || !in_array($variant, $allowedVariants, true) || $variant === '') {
                    throw new RuntimeException("Vortex encounter pool $period/$mode contains invalid legendary variant metadata.");
                }
            }

            foreach (['low_source_index_max', 'rare_source_index_max'] as $sourceField) {
                if (!array_key_exists($sourceField, $pool) || !is_int($pool[$sourceField]) || $pool[$sourceField] < 0) {
                    throw new RuntimeException("Vortex encounter pool $period/$mode is missing source-bound provenance.");
                }
            }
        }
    }
}

function pv_vortex_encounter_catalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) return $catalog;

    $path = dirname(__DIR__) . '/config/vortex_encounters.php';
    $loaded = is_file($path) ? require $path : null;
    if (!is_array($loaded)) {
        throw new RuntimeException('Vortex encounter catalogue is unavailable or invalid.');
    }
    pv_vortex_encounter_validate_catalog($loaded);
    $catalog = $loaded;
    return $catalog;
}

function pv_vortex_encounter_pool(int $mode, bool $night): array
{
    $catalog = pv_vortex_encounter_catalog();
    $period = $night ? 'night' : 'day';
    $pool = $catalog[$period][$mode] ?? null;
    if (!is_array($pool)) {
        throw new RuntimeException('No Vortex encounter pool is registered for ' . $period . ' mode ' . $mode . '.');
    }
    return $pool;
}

function pv_vortex_encounter_random_int(int $min, int $max, ?callable $rng = null): int
{
    if ($min > $max) throw new InvalidArgumentException('Encounter random range is invalid.');
    if ($rng === null) return random_int($min, $max);

    $value = $rng($min, $max);
    if (!is_int($value) || $value < $min || $value > $max) {
        throw new RuntimeException('Encounter test RNG returned an out-of-range value.');
    }
    return $value;
}

function pv_vortex_encounter_pick(array $species, ?callable $rng = null): string
{
    $species = array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $species), static fn(string $v): bool => $v !== ''));
    if ($species === []) throw new RuntimeException('Encounter species pool is empty.');
    $index = pv_vortex_encounter_random_int(0, count($species) - 1, $rng);
    return $species[$index];
}

function pv_vortex_encounter_variant_for_signal(array $windows, int $signalRoll): string
{
    foreach ($windows as $window) {
        if (!is_array($window)) continue;
        $min = (int)($window['min'] ?? PHP_INT_MAX);
        $max = (int)($window['max'] ?? PHP_INT_MIN);
        if ($signalRoll >= $min && $signalRoll <= $max) return trim((string)($window['variant'] ?? ''));
    }
    return '';
}

function pv_vortex_encounter_rare_signal_active(array $pool, int $signalRoll): bool
{
    $range = $pool['rare_signal'] ?? null;
    if (!is_array($range) || count($range) < 2) return false;
    return $signalRoll >= (int)$range[0] && $signalRoll <= (int)$range[1];
}

/**
 * @return array{display_name:string,base_name:string,variant:string,level:int,tier:string,period:string,mode:int,source:string,signal_roll:int,rare_roll:?int}
 */
function pv_vortex_encounter_roll(
    int $mode,
    bool $night,
    bool $legendaryUnlocked,
    int $signalRoll,
    ?callable $rng = null
): array {
    if ($signalRoll < 665 || $signalRoll > 1000) {
        throw new InvalidArgumentException('Vortex encounter signal roll must be between 665 and 1000.');
    }

    $pool = pv_vortex_encounter_pool($mode, $night);
    $period = $night ? 'night' : 'day';
    $tier = 'standard';
    $rareRoll = null;
    $variant = '';

    if ($legendaryUnlocked && pv_vortex_encounter_rare_signal_active($pool, $signalRoll)) {
        $rareRange = $pool['rare_roll'] ?? [1, 25];
        $rareMin = (int)($rareRange[0] ?? 1);
        $rareMax = (int)($rareRange[1] ?? 25);
        $rareRoll = pv_vortex_encounter_random_int($rareMin, $rareMax, $rng);
        $legendaryMax = (int)($pool['legendary_roll_max'] ?? 12);

        if ($rareRoll <= $legendaryMax) {
            $tier = 'legendary';
            $baseName = pv_vortex_encounter_pick((array)($pool['legendary'] ?? []), $rng);
            $levelRange = (array)($pool['legendary_level'] ?? [50, 75]);
            $variant = trim((string)(($pool['legendary_variants'] ?? [])[$rareRoll] ?? ''));
        } else {
            $tier = 'high';
            $baseName = pv_vortex_encounter_pick((array)($pool['high'] ?? []), $rng);
            $levelRange = (array)($pool['high_level'] ?? [35, 46]);
        }
    } else {
        $baseName = pv_vortex_encounter_pick((array)($pool['low'] ?? []), $rng);
        $levelRange = (array)($pool['low_level'] ?? [5, 20]);
        $variant = pv_vortex_encounter_variant_for_signal((array)($pool['variant_windows'] ?? []), $signalRoll);
    }

    $levelMin = max(1, (int)($levelRange[0] ?? 1));
    $levelMax = max($levelMin, (int)($levelRange[1] ?? $levelMin));
    [$levelMin, $levelMax] = pv_wild_balanced_level_range($levelMin, $levelMax, 1);
    $level = pv_vortex_encounter_random_int($levelMin, $levelMax, $rng);
    $displayName = $variant !== '' ? $variant . ' ' . $baseName : $baseName;

    return [
        'display_name' => $displayName,
        'base_name' => $baseName,
        'variant' => $variant,
        'level' => $level,
        'tier' => $tier,
        'period' => $period,
        'mode' => $mode,
        'source' => (string)($pool['source'] ?? ''),
        'signal_roll' => $signalRoll,
        'rare_roll' => $rareRoll,
    ];
}
