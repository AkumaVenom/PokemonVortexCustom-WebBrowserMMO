<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED')) { http_response_code(404); exit; }

/** Consume a server-admin heal once per authenticated browser session.
 * HP is battle-local in Vortex; idle teams already begin every battle fully healed.
 * This hook does not load any CLI command handlers or touch ranked/live results.
 */
function pv_console_apply_heal(mysqli $db, int $uid, array $state): void
{
    $revision = max(0, (int)($state['heal_revision'] ?? 0));
    if ($revision <= (int)($_SESSION['pv_console_heal_revision'] ?? 0)) return;
    $_SESSION['pv_console_heal_revision'] = $revision;
    if (is_array($_SESSION['pv_wild_battle'] ?? null) && ($_SESSION['pv_wild_battle']['status'] ?? '') === 'active') {
        foreach ($_SESSION['pv_wild_battle']['team'] as &$fighter) {
            $fighter['hp'] = (int)$fighter['max_hp'];
            $fighter['status'] = 'none';
        }
        unset($fighter);
    }
    // Ranked and real-time PvP snapshots must never be healed by local support tools.
    if (!empty($_SESSION['pv_ranked_battle']) || !empty($_SESSION['pv_rival_battle'])
        || !empty($_SESSION['live_battle_id']) || !empty($_SESSION['livebattle'])
        || (string)($_SESSION['opponent_profile'][3] ?? '') === 'live') return;
    for ($i=1; $i<=6; $i++) {
        $slot = 's'.$i;
        if (is_array($_SESSION[$slot] ?? null) && (int)($_SESSION[$slot][1] ?? 0)>0 && (int)($_SESSION[$slot][11] ?? 0)>0) {
            $_SESSION[$slot][10] = (int)$_SESSION[$slot][11];
            $_SESSION[$slot][12] = 100;
            $_SESSION[$slot][14] = '';
        }
    }
}
