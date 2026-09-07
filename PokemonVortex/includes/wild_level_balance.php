<?php
declare(strict_types=1);

/**
 * Global Wild Encounter level-balance contract.
 *
 * Wild encounters are intentionally capped at level 24 so one high-level wild
 * battle cannot accelerate player or autonomous-trainer progression by an
 * excessive amount under the shared Wild Battle EXP curve.
 *
 * Existing low/mid-game ranges are preserved exactly. A source range whose
 * maximum exceeds the cap is compressed rather than flattened: ranges already
 * starting at 24+ are remapped into a small late-game band that still rolls a
 * true minimum/maximum pair, with historically harder ranges biased closer to
 * level 24.
 */
const PV_WILD_LEVEL_CAP = 24;

/** @return array{0:int,1:int} */
function pv_wild_balanced_level_range(int $sourceMin, int $sourceMax, int $absoluteMin = 1): array
{
    $absoluteMin = max(1, min(PV_WILD_LEVEL_CAP - 1, $absoluteMin));
    $sourceMin = max($absoluteMin, $sourceMin);
    $sourceMax = max($sourceMin, $sourceMax);

    if ($sourceMax <= PV_WILD_LEVEL_CAP) {
        return [$sourceMin, $sourceMax];
    }

    if ($sourceMin < PV_WILD_LEVEL_CAP) {
        return [$sourceMin, PV_WILD_LEVEL_CAP];
    }

    // Preserve a variable late-game range instead of collapsing to 24-24.
    // Higher historical minimums compress progressively closer to the cap.
    if ($sourceMin < 29) {
        $balancedMin = 20;
    } elseif ($sourceMin < 34) {
        $balancedMin = 21;
    } elseif ($sourceMin < 39) {
        $balancedMin = 22;
    } else {
        $balancedMin = 23;
    }

    $balancedMin = max($absoluteMin, min(PV_WILD_LEVEL_CAP - 1, $balancedMin));
    return [$balancedMin, PV_WILD_LEVEL_CAP];
}

function pv_wild_capped_level(int $level, int $absoluteMin = 1): int
{
    $absoluteMin = max(1, min(PV_WILD_LEVEL_CAP, $absoluteMin));
    return max($absoluteMin, min(PV_WILD_LEVEL_CAP, $level));
}
