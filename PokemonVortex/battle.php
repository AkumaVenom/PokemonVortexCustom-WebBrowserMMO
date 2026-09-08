<?php

include('kick.php');
require_once __DIR__ . '/includes/gameplay.php';
require_once __DIR__ . '/includes/combat.php';
require_once __DIR__ . '/includes/battle_catalog.php';
require_once __DIR__ . '/includes/event_battle_catalog.php';
require_once __DIR__ . '/includes/sidequest_catalog.php';
require_once __DIR__ . '/includes/battle_runtime.php';
require_once __DIR__ . '/includes/rival_runtime.php';
if(!isset($_SESSION['myid']) || $_SESSION['access'] != 9){
	pv_redirect('login.php?goawaxP=1');
}

try {
	$battleDb = pv_db();
	pv_battle_runtime_guard_session($battleDb, (int)$_SESSION['myid']);
} catch (Throwable $e) {
	pv_log('Standard battle runtime unavailable: ' . $e->getMessage());
	pv_redirect('dashboard.php?service=unavailable');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	pv_require_csrf();
	if (!pv_consume_action_token('battle.php', (string)($_POST['battle_action_token'] ?? ''))) {
		// Ignore stale/double-submitted combat forms rather than replaying a state-changing turn.
		pv_redirect('battle.php');
	}

	// Recovered battle forms are treated only as commands. Move slots, item names
	// and team selections are validated against server-owned session state before
	// the legacy combat engine sees them.
	if (isset($_POST['attack'])) {
		$attackSlot = filter_var($_POST['attack'], FILTER_VALIDATE_INT);
		if ($attackSlot === false || $attackSlot < 1 || $attackSlot > 4) unset($_POST['attack']);
		else $_POST['attack'] = (string)$attackSlot;
	}
	if (isset($_POST['item'])) {
		$allowedBattleItems = ['Potion','Super Potion','Hyper Potion','Full Heal','Awakening','Parlyz Heal','Paralyze Heal','Antidote','Burn Heal','Ice Heal'];
		if (!in_array((string)$_POST['item'], $allowedBattleItems, true)) unset($_POST['item']);
	}
	if (isset($_POST['active_pokemon'])) {
		$activeId = filter_var($_POST['active_pokemon'], FILTER_VALIDATE_INT);
		$validActive = false;
		if ($activeId !== false && $activeId > 0) {
			for ($slot = 1; $slot <= 6; $slot++) {
				if ((int)($_SESSION['s'.$slot][1] ?? 0) === (int)$activeId && (int)($_SESSION['s'.$slot][10] ?? 0) > 0) {
					$validActive = true; break;
				}
			}
		}
		if (!$validActive) unset($_POST['active_pokemon']);
		else $_POST['active_pokemon'] = (string)$activeId;
	}
}

/*
 * ---------------- Javascript check. -------------------
 * $_SESSION['nojs-check'] is set to a random token when the Select your next Pokemon screen is displayed.
 * The check condition is passed if the puzzle is solved and the token matches the one expected.
 * If the session token is NULL, then we weren't expecting one anyway - so this is also considered a pass.
 * ------------------------------------------------------
 */
$noJSpass = (!isset($_SESSION['nojs-check']) || intval(@$_POST['nojs-check'])===$_SESSION['nojs-check']);
unset($_SESSION['nojs-check']);

function displayNOJSpuzzle(){
	if (isset($_SESSION['nojs-check'], $_SESSION['nojs-check-a'], $_SESSION['nojs-check-b'])) { ?>
  		<div id="nojs-solve" style="display:none;background:#fff4f2;color:#8f3434;width:300px;margin:auto;padding:12px;border:1px solid rgba(232,69,69,.24);border-radius:14px;">
		<?php
		if (isset($_SESSION['nojs-check-err'])) {
			unset($_SESSION['nojs-check-err']);
			?>
			<span style="color:#b33d3d;display:block;">Incorrect answer. Please try again.</span>
			<?php
		} ?>
		<input type="hidden" id="nojs-solve-a" value="<?=$_SESSION['nojs-check-a']?>" /> 
		<input type="hidden" id="nojs-solve-b" value="<?=$_SESSION['nojs-check-b']?>" />
		<label style="font-size:13px;">Please solve the following <?=$_SESSION['nojs-check-a']?> + <?=$_SESSION['nojs-check-b']?> = <input id="nojs-solve-v" type="text" name="nojs-check" style="width:30px;padding:6px;font-size:13px;"></label>
		<noscript><span style="display:block;color:#60788c;padding-top:10px;font-size:9px;">Note If you enable Javascript in your browser, you will no longer have to solve these puzzles.</span></noscript>
		</div>
		<?php
	}
}
function pv_battle_form_security_fields(): string {
    return pv_csrf_field() . '<input type="hidden" name="battle_action_token" value="' . pv_h(pv_action_token('battle.php')) . '">';
}

/**
 * Return the correct post-battle route for ordinary Trainer Snapshot battles.
 *
 * A Rival Network match must never expose the recovered direct rebattle link:
 * that URL starts an unranked snapshot battle and bypasses ranked protection /
 * retaliation launch checks. Ranked competitors are routed back through Rival
 * Hub so every subsequent ranked result is explicitly armed and counted.
 */
function pv_battle_trainer_rebattle_link(): string {
    $trainerId = max(1, (int)($_SESSION['myid'] ?? 0));
    $opponentId = max(0, (int)($_SESSION['opponent_profile'][0] ?? 0));
    if ($opponentId > 0 && (pv_rival_session_context($trainerId, $opponentId) || (int)($GLOBALS['pv_battle_ranked_opponent'] ?? 0) === $opponentId)) {
        return '<a href="' . pv_h(pv_url('rival_hub.php')) . '" class="deselected">Return to Rival Hub for Next Ranked Battle</a>';
    }
    return '<a href="battle.php?bid=' . $opponentId . '" class="deselected">Rebattle Opponent</a>';
}


/**
 * Resolve NPC battle move metadata through the unified combat contract.
 * Missing rows retain the established deterministic fallback so malformed or
 * historical NPC data cannot soft-lock a battle with null arithmetic.
 */
function pv_battle_move_data(string $move, string $fallbackType = 'Normal'): array {
    try {
        return pv_combat_move_data(pv_db(), $move, $fallbackType);
    } catch (Throwable $e) {
        pv_log('Battle move lookup fallback for '.trim($move).': '.$e->getMessage());
        return ['attack'=>trim($move) !== '' ? trim($move) : 'Struggle','type'=>$fallbackType ?: 'Normal','power'=>50,'accuracy'=>100,'category'=>'Physical'];
    }
}

/**
 * Return the first living slot in a hydrated battle party.
 *
 * v20 deliberately initializes all six session slots so recovered arithmetic
 * can safely read them. The historical opponent-selection code used isset()
 * as a proxy for "this team slot exists", which becomes false once every
 * slot is initialized and can cascade from a real slot 1 into empty slot 6.
 * Bound selection by the authoritative team count instead.
 */
function pv_battle_first_alive_slot(string $prefix, int $teamCount): int {
    $teamCount = max(0, min(6, $teamCount));
    for ($slot = 1; $slot <= $teamCount; $slot++) {
        $state = $_SESSION[$prefix . $slot] ?? null;
        if (is_array($state) && (int)($state[10] ?? 0) > 0 && (int)($state[1] ?? 0) > 0) {
            return $slot;
        }
    }
    return 0;
}

function generateBattleButtonText($base) {
	if (rand(0,1)) {
		$base = " ".$base;
	}
	if (rand(0,1)) {
		$base .= " ";
	}
	$endings = array("",".","!","?","..","...");
	$base .= $endings[rand(0,count($endings)-1)];
	if (rand(0,1)) {
		$base .= " ";
	}
	return $base;
}

$random = rand(1309,9206);

/**
 * Advance one Sidequest opponent exactly once. The defeated opponent id must
 * still be the trainer's authoritative database progress value, otherwise a
 * stale/replayed battle result cannot skip or repeat progression.
 */
function pv_advance_sidequest_after_win(mysqli $db, int $uid, int $defeatedSidequestId): array {
	$uid = max(1, $uid);
	$defeatedSidequestId = max(0, $defeatedSidequestId);
	if ($defeatedSidequestId <= 0 || !pv_sidequest_by_id($defeatedSidequestId)) {
		return ['advanced' => false, 'progress' => max(0, (int)($_SESSION['sidequest'] ?? 0))];
	}

	$db->begin_transaction();
	try {
		$stmt = $db->prepare('SELECT sidequest FROM members WHERE id=? FOR UPDATE');
		if (!$stmt) throw new RuntimeException('Could not lock Sidequest progression.');
		$stmt->bind_param('i', $uid);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if (!$row) throw new RuntimeException('Trainer progression could not be loaded.');

		$current = max(0, (int)$row['sidequest']);
		if ($current !== $defeatedSidequestId) {
			$db->rollback();
			$_SESSION['sidequest'] = $current;
			return ['advanced' => false, 'progress' => $current];
		}

		$next = $current + 1;
		$stmt = $db->prepare('UPDATE members SET sidequest=? WHERE id=? AND sidequest=?');
		if (!$stmt) throw new RuntimeException('Could not prepare Sidequest progression update.');
		$stmt->bind_param('iii', $next, $uid, $current);
		if (!$stmt->execute() || $stmt->affected_rows !== 1) {
			$stmt->close();
			throw new RuntimeException('Sidequest progression changed before the win could be recorded.');
		}
		$stmt->close();
		$db->commit();
		$_SESSION['sidequest'] = $next;
		return ['advanced' => true, 'progress' => $next];
	} catch (Throwable $e) {
		$db->rollback();
		pv_log('Sidequest battle progression failed for trainer ' . $uid . ' after opponent ' . $defeatedSidequestId . ': ' . $e->getMessage());
		return ['advanced' => false, 'progress' => max(0, (int)($_SESSION['sidequest'] ?? 0))];
	}
}

// v20 normalizes the battle entry route before the recovered engine sees it.
// Exactly one opponent mode is permitted, and sidequest battles must match the
// trainer's current server-side progression rather than trusting a URL value.
$battleRoute = [
	'bid' => max(0, (int)($_GET['bid'] ?? 0)),
	'gymleader' => trim((string)($_GET['gymleader'] ?? '')),
	'sidequest' => max(0, (int)($_GET['sidequest'] ?? 0)),
	'eventtrainer' => trim((string)($_GET['eventtrainer'] ?? '')),
	'clanbattle' => trim((string)($_GET['clanbattle'] ?? '')),
];
foreach (['gymleader','eventtrainer','clanbattle'] as $routeKey) {
	$battleRoute[$routeKey] = preg_replace('/[\x00-\x1F\x7F]/u', '', $battleRoute[$routeKey]) ?? '';
	if (strlen($battleRoute[$routeKey]) > 100) $battleRoute[$routeKey] = substr($battleRoute[$routeKey], 0, 100);
}
$activeBattleRoutes = [];
foreach ($battleRoute as $routeKey => $routeValue) {
	if (($routeKey === 'bid' || $routeKey === 'sidequest') ? ((int)$routeValue > 0) : ($routeValue !== '')) {
		$activeBattleRoutes[] = $routeKey;
	}
}
if (count($activeBattleRoutes) > 1) {
	pv_redirect('battle_select.php?invalid_battle=1');
}
$battleStartRequested = count($activeBattleRoutes) === 1;
$battleRouteType = $battleStartRequested ? $activeBattleRoutes[0] : '';
$battleFailureRoute = static function(string $type): string {
	if ($type === 'side') return 'sidequest.php?invalid_battle=1';
	if ($type === 'clan') return 'clans.php?view=Battle&invalid_battle=1';
	if ($type === 'event') return 'battle_select.php?mode=events&invalid_battle=1';
	return 'battle_select.php?invalid_battle=1';
};
if ($battleRouteType === 'gymleader') {
    $catalogOpponent = pv_battle_catalog_by_route((string)$battleRoute['gymleader']);
    if (!$catalogOpponent) pv_redirect('battle_select.php?invalid_battle=1');
    $battleStorage = pv_battle_catalog_storage_status(pv_db());
    if (empty($battleStorage['ready'])) pv_redirect('battle_select.php?schema=upgrade');
}
if ($battleRouteType === 'eventtrainer') {
    $eventCatalogOpponent = pv_event_battle_by_route((string)$battleRoute['eventtrainer']);
    if (!$eventCatalogOpponent) pv_redirect('battle_select.php?mode=events&invalid_battle=1');
    $eventBattleStorage = pv_event_battle_storage_status(pv_db());
    if (empty($eventBattleStorage['ready'])) pv_redirect('battle_select.php?mode=events&schema=upgrade');
}
if ($battleRouteType === 'sidequest') {
	// v22.6 exposes only battle positions present in the complete authoritative
	// source-generation Sidequest catalog. Reward milestones have no catalog row
	// and therefore cannot be entered as fabricated trainer routes.
	$sidequestCatalogOpponent = pv_sidequest_by_id((int)$battleRoute['sidequest']);
	if (!$sidequestCatalogOpponent) pv_redirect('sidequest.php?invalid_battle=1');
	$sidequestStorage = pv_sidequest_storage_status(pv_db());
	if (empty($sidequestStorage['ready'])) pv_redirect('sidequest.php?schema=upgrade');

	// Session progress is only a UI cache. Re-read MySQL before authorizing the
	// requested Sidequest so another login/session cannot leave this tab stale.
	$uid = (int)$_SESSION['myid'];
	$stmt = pv_db()->prepare('SELECT sidequest FROM members WHERE id=? LIMIT 1');
	if (!$stmt) pv_redirect('sidequest.php?invalid_battle=1');
	$stmt->bind_param('i', $uid);
	$stmt->execute();
	$progressRow = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$progressRow) pv_redirect('sidequest.php?invalid_battle=1');
	$_SESSION['sidequest'] = max(0, (int)$progressRow['sidequest']);
	if ((int)$battleRoute['sidequest'] !== (int)$_SESSION['sidequest']) {
		pv_redirect('sidequest.php?invalid_battle=1');
	}
}
if ($battleRouteType === 'bid' && (int)$battleRoute['bid'] === (int)$_SESSION['myid']) {
	pv_redirect('battle_select.php?invalid_battle=1');
}
if ($battleRouteType === 'clanbattle' && empty($_SESSION['clan_battle'][0])) {
	pv_redirect('clans.php?view=Battle');
}

if($battleStartRequested){
    if (!pv_rival_retry_pending_result($battleDb, (int)$_SESSION['myid'])) {
        pv_redirect('rankings.php');
    }
    $rankedLaunch = $battleRouteType === 'bid'
        ? pv_rival_session_context((int)$_SESSION['myid'], (int)$battleRoute['bid']) : null;
    if ($rankedLaunch) {
        if (($rankedLaunch['phase'] ?? '') === 'active'
            && (int)($_SESSION['opponent_profile'][0] ?? 0) === (int)$battleRoute['bid']
            && (string)($_SESSION['opponent_profile'][3] ?? '') === '') {
            pv_redirect('battle.php');
        }
        $_SESSION['pv_rival_battle']['phase'] = 'active';
    } else {
        unset($_SESSION['pv_rival_battle']);
    }
	// Unset previous battle sessions only after the new route has been validated.
	unset($_SESSION['opponent_profile'],$_SESSION['s1'],$_SESSION['s2'],$_SESSION['s3'],$_SESSION['s4'],$_SESSION['s5'],$_SESSION['s6'],$_SESSION['ops1'],$_SESSION['ops2'],$_SESSION['ops3'],$_SESSION['ops4'],$_SESSION['ops5'],$_SESSION['ops6'],$_SESSION['position'],$_SESSION['your_profile'],$_SESSION['y_p']);
	// The recovered engine treats this ten-element array as a tiny move cache.
	// Seed it explicitly so the first turn never reads offsets from null under PHP 8.
	$_SESSION['attack_short'] = array_fill(0, 10, '');
}
else {
	// battle.php without a route is only valid while continuing an initialized
	// battle. Direct/stale navigation must not fall into the recovered engine.
	if (empty($_SESSION['opponent_profile']) || !is_array($_SESSION['opponent_profile'])) {
		pv_redirect('battle_select.php?invalid_battle=1');
	}
	if (!$noJSpass) {
		unset($_SESSION['nojs-check']);
		
		// Opponent types
		
		switch ($_SESSION['opponent_profile'][3]) {
			default: $loc = 'battle.php?bid='.$_SESSION['opponent_profile'][0]; break;
			case 'event': $loc = 'battle.php?eventtrainer='.rawurlencode((string)$_SESSION['opponent_profile'][1]); break;
			case 'gym': $loc = 'battle.php?gymleader='.$_SESSION['opponent_profile'][1]; break;
			case 'side': $loc = 'battle.php?sidequest='.$_SESSION['opponent_profile'][0]; break;
			case 'clan': $loc = 'battle.php?clanbattle='.$_SESSION['opponent_profile'][4]; break;
		}
		$_SESSION['nojs-check-err'] = true;
		header('Location: '.$loc);
		exit;
	}	
}
if(empty($_REQUEST['ajax'])){
	$time = time(); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php
if($_SESSION['layout'] == '1'){
	echo '<link rel="stylesheet" type="text/css" href="html/static/css/blue/game.css" media="screen" />';
	echo '<link rel="stylesheet" type="text/css" href="html/static/css/blue/global.css" media="screen" />';
}
elseif($_SESSION['layout'] == '0'){
	echo '<link rel="stylesheet" type="text/css" href="html/static/css/red/global.css" media="screen" />';
	echo '<link rel="stylesheet" type="text/css" href="html/static/css/red/game.css" media="screen" />';
}
if($_SESSION['layout'] == '2'){
	echo '<link rel="stylesheet" type="text/css" href="html/static/css/black/global.css" media="screen" />';
	echo '<link rel="stylesheet" type="text/css" href="html/static/css/black/game.css" media="screen" />';
}
?>
<!--[if lt IE 7]>
	<script type="text/javascript" language="javascript" src="html/static/js/ie6-.js"></script>
<![endif]-->
<noscript><link rel="stylesheet" type="text/css" href="html/static/css/noscript.css" media="all" /></noscript>
<style>#hide {display:none;}</style>
<link rel="icon" href="favicon.png" type="image/x-icon" /> 
<link rel="shortcut icon" href="favicon.png" type="image/x-icon" /> 
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link rel="stylesheet" href="<?= pv_h(pv_asset('css/vortex-modern.css')) ?>?v=<?= pv_asset_version() ?>" />
<script defer src="<?= pv_h(pv_asset('js/vortex-modern.js')) ?>?v=<?= pv_asset_version() ?>"></script>
<script type="text/javascript">
(function () {
    'use strict';
    window.solveJScap = function () {
        var a = document.getElementById('nojs-solve-a');
        var b = document.getElementById('nojs-solve-b');
        var v = document.getElementById('nojs-solve-v');
        if (!a || !b || !v) return;
        v.value = String((parseInt(a.value, 10) || 0) + (parseInt(b.value, 10) || 0));
    };
    window.disableSubmitButton = function (form) {
        if (!form || form.dataset.pvSubmitting === '1') return;
        form.dataset.pvSubmitting = '1';
        var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        window.setTimeout(function () {
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].disabled = true;
                buttons[i].setAttribute('aria-busy', 'true');
            }
        }, 0);
    };
})();
</script>
<noscript><style type="text/css">#nojs-solve{display:block !important;}</style></noscript>
<title>Pokemon Vortex NXT - Battle</title>
<style>
.hidden
{
	position: absolute;
	left: -10000px;
	top: auto;
	width: 1px;
	height: 1px;
	overflow: hidden;
}
</style>

</head>
<body class="pv-legacy-battle-page" data-pv-battle-network-isolated="1" data-pv-battle-action="<?php echo !empty($_POST['attack']) ? 'attack' : (!empty($_POST['item']) ? 'item' : 'idle'); ?>" data-pv-battle-move="<?php echo htmlspecialchars((string)($_SESSION['attack_short'][0] ?? $_POST['attack'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-pv-battle-item="<?php echo htmlspecialchars((string)($_POST['item'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<?php
include_once("analytics.php"); ?>
<div id="menuBox"></div>
<div id="container">
<div id="header">
<div id="headerAd">
<?php
include(__DIR__ . '/includes/ads/headerad.php');
?>

</div>
<div id="title">
<h1><a href="index.php"><em>Pokemon Vortex NXT</em></a></h1>
</div>
<ul id="nav">
<li><a href="map_select.php" id="mapsTab" class="deselected"><em>Maps</em></a></li>
<li><a href="battle_select.php" id="battleTab" class="deselected"><em>Battle</em></a></li>
<li><a href="your_account.php" id="yourAccountTab" class="deselected"><em>Your Account</em></a></li>
<li><a href="community.php" id="communityTab" class="deselected"><em>Community</em></a></li>
</ul>
<ul id="logout">
<li><a href="logout.php">Logout</a></li>
</ul>
</div>
<?php echo pv_battle_runtime_usernav($battleDb, (int)$_SESSION['myid']); ?>
<div id="contentContainer">
<div id="sidebar">
<div id="sidebarContainer">
<div id="sidebarLoading"></div>
<div id="sidebarContent"></div>
</div>
<ul id="sidebarTabs">
<li><a href="pokedex.php" id="pokedexTab" class="deselected"><em>Pok&eacute;Dex</em></a></li>
<li><a href="members.php" id="membersTab" class="deselected"><em>Members</em></a></li>
<li><a href="options.php" id="optionsTab" class="deselected"><em>Options</em></a></li>
</ul>
</div> 
<div id="content">
<div id="loading"></div>
<div id="scroll">
<div id="suggestResults"></div>
<div id="showDetails"></div>
<div id="errorBox"></div>
<div style="float: right;">
<?php
include(__DIR__ . '/includes/ads/sidead.php');
?>

</div>
<div id="scrollContent">
<div id="ajax">
<?php
}
// Pokemon type damage conversions in style of the attacker

if(($_SESSION['position'] ?? 0) == 2 && !isset($_POST['choose'])){
	function convert($atype, $ty, $ty2){
        return pv_combat_type_multiplier((string)$atype, (string)$ty, (string)$ty2);
    }
	
	//--------------------------------------------USE AN ITEM AND OPPONENT ATTACKS----------------------------------------------//
	
	if(!empty($_POST['item'])){
		$u = (int)($_SESSION['y_p'][0] ?? 0);
		$n = (int)($_SESSION['y_p'][1] ?? 0);
		$itemMap = array(
			"Potion" => array(0, "Potion"),
			"Super Potion" => array(1, "Super_Potion"),
			"Hyper Potion" => array(2, "Hyper_Potion"),
			"Full Heal" => array(3, "Full_Heal"),
			"Awakening" => array(4, "Awakening"),
			"Parlyz Heal" => array(5, "Parlyz_Heal"),
			"Paralyze Heal" => array(5, "Parlyz_Heal"),
			"Antidote" => array(6, "Antidote"),
			"Burn Heal" => array(7, "Burn_Heal"),
			"Ice Heal" => array(8, "Ice_Heal")
		);
		$requestedItem = (string)$_POST['item'];
		if (!isset($itemMap[$requestedItem])) {
			$_POST['item'] = '';
			$item_statement = "That battle item is not available.";
		} else {
			$itemIndex = (int)$itemMap[$requestedItem][0];
			if ((int)($_SESSION['items'][$itemIndex] ?? 0) <= 0) {
				$_POST['item'] = '';
				$item_statement = "You do not have any of that item left.";
			}
		}
		if ($_POST['item'] === '') {
			// A forged/stale item command never mutates combat or inventory state.
		} else {
		$tu = rand(1,4);
		$tu = $tu + 5;
		$oattack = $_SESSION['ops'.$n][$tu]; // Opponents attack session [6] to [9]
		if($oattack == $_SESSION['attack_short'][3]){ // If opponent attack session is set
			$hittingd = $_SESSION['attack_short'][3]; // Opponent attack name
			$hittinge = $_SESSION['attack_short'][4]; // opponent attack type
			$hittingf = $_SESSION['attack_short'][5]; // opponent attack power
			$hittingg = $_SESSION['attack_short'][6]; // opponent attack accuracy
			$category = $_SESSION['attack_short'][8]; // opponent attack category
		}
		if($oattack != $_SESSION['attack_short'][3] || !$_SESSION['attack_short'][3]){ // If opponent has no attack session set
			$r_a = pv_battle_move_data((string)$oattack, (string)($_SESSION['ops'.$n][2] ?? 'Normal'));
			$hittingd = $r_a['attack']; // return name
			$hittinge = $r_a['type']; // return type
			$hittingf = $r_a['power']; // return power
			$hittingg = $r_a['accuracy']; // return accuracy
			$category = $r_a['category']; // return category
			$_SESSION['attack_short'][3] = $hittingd; // Opponent attack name session
			$_SESSION['attack_short'][4] = $hittinge; // Opponent attack type session
			$_SESSION['attack_short'][5] = $hittingf; // Opponent attack power session
			$_SESSION['attack_short'][6] = $hittingg; // opponent attack accuracy session
			$_SESSION['attack_short'][8] = $category; // opponent attack category session
		}
		if($hittinge == $_SESSION['ops'.$n][2] || $hittinge == $_SESSION['ops'.$n][3]){ // If the opponent's attack is the same type as itself
			$opmultn = 1.5; // return STAB
		}
		else {
			$opmultn = 1; // return standard damage
		}
		$h = $_SESSION['ops'.$n][4] / 30; // Opponent's level divided by 30
		$hh = $_SESSION['attack_short'][5] / 2; // Attack power divided by 2
		$y = $hittinge; // Opponent attack type
		$g = $_SESSION['s'.$u][2]; // Your pokemon type 1
		$f = $_SESSION['s'.$u][3]; // Your Pokemon type 2
		$damages2 = convert("$y", "$g", "$f"); // use type chart to get weakness / resistances
		if(strstr($_SESSION['ops'.$n][0],'Dark ') || strstr($_SESSION['s'.$u][0],'Metallic ')){ // If your opponent is dar of you are metallic
			$d_a = 1; // standard damage set
			if(strstr($_SESSION['s'.$u][0],'Metallic ')){ // If your pokemon is Metallic
				$d_a = $d_a - 0.25; // 25% defence boost
			}
			if(strstr($_SESSION['ops'.$n][0],'Dark ')){ // if your opponent is Dark
				$d_a = $d_a + 0.25; // 25% attack boost
			}
			$h2 = $h * $hh; // lvl / 30 * atkpwr / 2
			$h3 = $h2 * $d_a; // lvl / 30 * atkpwr / 2 * dmg-multiplier
			$h4 = $h3 * $damages2; // lvl / 30 * atkpwr / 2 * dmg-multiplier * type-markup
			$hhh = round($h4 * $opmultn); // round to the nearest whole number(lvl/30*atkpwr/2*dmg-multipolier*type-markup*STAB)
		} // end dark and metallic damage
		
		
		else { // standard pokemon damage
			// Critical hit chance --- 6.25%
			$crit = rand(1,16);
			if($crit == '1'){
				$d_a = 1.5;
			}
			else{
				$d_a = 1;
			}
			$h2 = $h * $hh; // lvl / 30 * atkpwr / 2
			$h3 = $h2 * $d_a; // lvl / 30 * atkpwr / 2 * dmg-multiplier
			$h4 = $h3 * $damages2; // lvl / 30 * atkpwr / 2 * dmg-muliplier * type-markup
			$hhh = round($h4 * $opmultn); // rouns to the nearest whole number(lvl / 30 * atkpwr / 2 * dmg-multiplier * type-markup * STAB)
		}
		if(strstr($_SESSION['ops'.$n][0],'Mystic')){ // If your opponent is Mysitc
			$ran = rand(1,4);
			if($ran == 2){ // 1 in 4 chance of your Pokemon being scared
				$you_scared = 2;
				$www = 0; // damage to opponent cancelled
			}
		}
		if(strstr($_SESSION['s'.$u][0],'Mystic')){ // If your Pokemon is Mystic
			$rand = rand(1,4);
			if($rand == 2){ // 1 in 4 chance of opponent Pokemon being scared
				$op_scared = 2;
				$hhh = 0; // damage to you cancelled
			}
		}
		if($_SESSION['attack_short'][6] == '95'){ // If opponents attack accuracy is 95
			$acc = rand(1,100);
			if($acc > 95){ // 95 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] == '90'){ // If opponents attack accuracy is 90
			$acc = rand(1,100);
			if($acc > 90){ // 90 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] == '85'){ // If opponents attack accuracy is 85
			$acc = rand(1,100);
			if($acc > 85){ // 85 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] == '80'){ // If opponents attack accuracy is 80
			$acc = rand(1,100);
			if($acc > 80){ // 80 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] == '75'){ // If opponents attack accuracy is 75
			$acc = rand(1,100);
			if($acc > 75){ // 75 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] == '70'){ // If opponents attack accuracy is 70
			$acc = rand(1,100);
			if($acc > 70){ // 70 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] == '60'){ // If Opponents attack accuracy is 60
			$acc = rand(1,100);
			if($acc > 60){ // 60 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] == '55'){ // If opponents attack accuracy is 55
			$acc = rand(1,100);
			if($acc > 55){ // 55 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] == '50'){ // If opponents attack accuracy is 50
			$acc = rand(1,100);
			if($acc > 50){ // 50 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] == '30'){ // If opponents attack accuracy is 30
			$acc = rand(1,100);
			if($acc > 30){ // 30 in 100 chance of hitting
				$op_missed = 1;
				$you_scared = 0; // Accuracy overrides Mystic's scare
				$hhh = 0;
			}
		}
		//------------------Opponents Transform and Sketch attacks-------------------//
		if(!$op_missed){
			if($oattack == 'Transform'){
				$_SESSION['ops'.$n][0] = $_SESSION['s'.$u][0]; // Change your Pokemon name to the opponents
				$_SESSION['ops'.$n][2] = $_SESSION['s'.$u][2]; // Change your Pokemon type 1 to the opponents
				$_SESSION['ops'.$n][3] = $_SESSION['s'.$u][3]; // Change your Pokemon type 2 to the opponents
				$_SESSION['ops'.$n][6] = $_SESSION['s'.$u][6]; // Change your Pokemon attack 1 to the opponents
				$_SESSION['ops'.$n][7] = $_SESSION['s'.$u][7]; // Change your Pokemon attack 2 to the opponents
				$_SESSION['ops'.$n][8] = $_SESSION['s'.$u][8]; // Change your Pokemon attack 3 to the opponents
				$_SESSION['ops'.$n][9] = $_SESSION['s'.$u][9]; // Change your Pokemon attack 4 to the opponents
			}
			if($oattack == 'Sketch'){
				$_SESSION['ops'.$n][$tu] = $_SESSION['attack_short'][0];
			}
		}		
		//----------------Opponent inflicting a status effect on you--------------------//
		
		if(!$_SESSION['s'.$u][14] && !$op_missed && !$op_scared){ // Make sure there isn't already a status effect in play and the opponent didn't miss
		
		//--------------------------BURN------------------------//
		
			// Attacks with 10% chance to burn
			if($oattack == 'Blaze Kick' || $oattack == 'Blue Flare' || $oattack == 'Ember' || $oattack == 'Fire Blast' || $oattack == 'Fire Fang' || $oattack == 'Fire Punch' || $oattack == 'Flame Wheel' || $oattack == 'Flamethrower' || $oattack == 'Heat Wave' || $oattack == 'Ice Burn' || $oattack == 'Searing Shot'){
				if($_SESSION['s'.$u][2] == 'Fire' || $_SESSION['s'.$u][3] == 'Fire'){
					// Don't burn
				}
				else{
					$brn = rand(1,10);
					if($brn == '1'){
						$_SESSION['s'.$u][14] = 'Burn';
					}
				}
			}
			// Attacks with 30% chance to burn
			if($oattack == 'Lava Plume' || $oattack == 'Scald'){
				if($_SESSION['s'.$u][2] == 'Fire' || $_SESSION['s'.$u][3] == 'Fire'){
					// Insert Statement
				}
				else{
					$brn = rand(1,10);
					if($brn < 4){
						$_SESSION['s'.$u][14] = 'Burn';
					}
				}
			}
			// Attacks with 50% chance to burn
			if($oattack == 'Sacred Fire'){
				if($_SESSION['s'.$u][2] == 'Fire' || $_SESSION['s'.$u][3] == 'Fire'){
					// Insert statement
				}
				else{
					$brn = rand(1,2);
					if($brn == '1'){
						$_SESSION['s'.$u][14] = 'Burn';
					}
				}
			}
			// Attacks with 100% chance to burn
			if($oattack == 'Will-O-Wisp' || $oattack == 'Inferno'){
				if($_SESSION['s'.$u][2] == 'Fire' || $_SESSION['s'.$u][3] == 'Fire'){
					// Insert statement
				}
				else{
					$_SESSION['s'.$u][14] = 'Burn';
				}
			}
			
			//---------------------FREEZE------------------//
			
			// Attacks with 10% chance to freeze
			if($oattack == 'Blizzard' || $oattack == 'Freeze-Dry' || $oattack == 'Ice Beam' || $oattack == 'Ice Fang' || $oattack == 'Ice Punch' || $oattack == 'Powder Snow' ){
				if($_SESSION['s'.$u][2] == 'Ice' || $_SESSION['s'.$u][3] == 'Ice'){
					// Insert statement
				}
				else{
					$frz = rand(1,10);
					if($frz == '1'){
						$_SESSION['s'.$u][14] = 'Frozen';
					}
				}
			}
			
			//-----------------PARALYSIS-----------------//
			
			// Attacks with 10% chance to paralyze
			if($oattack == 'Bolt Strike' || $oattack == 'Freeze Shock' || $oattack == 'Thunder Fang' || $oattack == 'Thunderbolt' || $oattack == 'Thunderpunch' || $oattack == 'Thundershock'){
				if($_SESSION['s'.$u][2] == 'Electric' || $_SESSION['s'.$u][3] == 'Electric'){
					// Insert statement
				}
				else{
					$par = rand(1,10);
					if($par == '1'){
						$_SESSION['s'.$u][14] = 'Paralyzed';
					}
				}
			}
			// Attacks with 30% chance to paralyze
			if($oattack == 'Body Slam' || $oattack == 'Bounce' || $oattack == 'Discharge' || $oattack == 'Force Palm' || $oattack == 'Lick' || $oattack == 'Spark' || $oattack == 'Thunder'){
				if($_SESSION['s'.$u][2] == 'Electric' || $_SESSION['s'.$u][3] == 'Electric'){
					//Insert statement
				}
				else{
					$par = rand(1,10);
					if($par < 4){
						$_SESSION['s'.$u][14] = 'Paralyzed';
					}
				}
			}
			// Attacks wih 100% chance to paralyze
			if($oattack == 'Glare' || $oattack == 'Nuzzle' || $oattack == 'Stun Spore' || $oattack == 'Thunder Wave' || $oattack == 'Zap Cannon'){
				if($_SESSION['s'.$u][2] == 'Electric' || $_SESSION['s'.$u][3] == 'Electric'){
					// Insert Statement
				}
				else{
					$_SESSION['s'.$u][14] = 'Paralyzed';
				}
			}
			
			//----------------POISON----------------//
			
			// Attacks with 10% chance of poison
			if($oattack == 'Cross Poison' || $oattack == 'Poison Tail' || $oattack == 'Sludge Wave'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn == '1'){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 20% chance of poison
			if($oattack == 'Twineedle'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn < 3){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 30% chance of poison
			if($oattack == 'Gunk Shot' || $oattack == 'Poison Jab' || $oattack == 'Poison Sting' || $oattack == 'Sludge' || $oattack == 'Sludge Bomb'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
					$psn = rand(1,10);
					if($psn < 4){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 40% chance to poison
			if($oattack == 'Smog'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn < 5){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 100% chance to poison
			if($oattack == 'Toxic Spikes' || $oattack == 'Poison Powder' || $oattack == 'Poison Gas'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$_SESSION['s'.$u][14] = 'Poison';
				}
			}
			
			//--------------SLEEP----------------------//
			
			// Attacks with 30% chance to sleep
			if($oattack == 'Relic Song'){
				// Add a check here for sleep immunity
				$slp = rand(1,10);
				if($slp < 4){
					$_SESSION['s'.$u][14] = 'Sleep';
				}
			}
			// Attacks with 100% chance to sleep
			if($oattack == 'Dark Void' || $oattack == 'Grasswhistle' || $oattack == 'Hypnosis' || $oattack == 'Lovely Kiss' || $oattack == 'Sing' || $oattack == 'Sleep Powder' || $oattack == 'Spore' || $oattack == 'Yawn'){
				// Add a check here for sleep immunity
				$_SESSION['s'.$u][14] = 'Sleep';
			}
			
			//------------------CONFUSION------------------//
			
			// Attacks with 10% to confuse
			if($oattack == 'Confusion' || $oattack == 'Hurricane' || $oattack == 'Psybeam' || $oattack == 'Signal Beam'){
				// Add a check here for confusion immunity
				$conf = rand(1,10);
				if($conf == '1'){
					$_SESSION['s'.$u][14] = 'Confused';
				}
			}
			
			//---------------BADLY POISONED------------------//
			
			// Attacks with 30% chance to badly poison
			if($oattack == 'Poison Fang'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$bpsn = rand(1,10);
					if($bpsn < 4){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 100% chance to badly poison
			if($_SESSION['attack_short'][3] == 'Toxic' || $_SESSION['attack_short'][3] == 'Toxic Spikes'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$_SESSION['s'.$u][14] = 'Poison';
				}
			}
				
//---------------------------Status effect damages----------------------------------------------//
// Only Poison and Burn are needed when you use an item since they're the only ones that do damage unless you're attacking //

		}
		$damg_u = 0;
		if(($_SESSION['s'.$u][14] ?? '') == 'Poison'){ // If your Pokemon is Poisoned
			$percent = 12;
			$maxhp = $_SESSION['s'.$u][11];
			$damg_u = ($percent / 100) * $maxhp;
			$state = "was hurt by it's poisoning.";
		}
		if(($_SESSION['s'.$u][14] ?? '') == 'Burn'){ // If your Pokemon is Burned
			$percent = 12;
			$maxhp = $_SESSION['s'.$u][11];
			$damg_u = ($percent / 100) * $maxhp;
			$state = "was hurt by it's burn.";
		}
		if(($_SESSION['s'.$u][14] ?? '') == 'Sleep'){ // If your Pokemon is asleep
			$sl_wake = rand(1,4);
			if($sl_wake == 1){
				unset($_SESSION['s'.$u][14]);
			}
		}
		if(($_SESSION['s'.$u][14] ?? '') == 'Frozen'){ // If your Pokemon is frozen
			$thaw = rand(1,4);
			if($thaw == 1){
				unset($_SESSION['s'.$u][14]);
			}
		}
			
		$_SESSION['s'.$u][10] = $_SESSION['s'.$u][10] - $hhh - round($damg_u); // Your pokemon's HP minus final damage
		
		$i_u = (string)$_POST['item']; // Item conditions
		$consumeItem = false;
		$healAmount = 0;
		switch($i_u){
			case "Potion":
				$item_statement = "Your Pok&eacute;mon regained 20 HP.";
				$healAmount = 20; $consumeItem = true;
				break;
			case "Super Potion":
				$item_statement = "Your Pok&eacute;mon regained 100 HP.";
				$healAmount = 100; $consumeItem = true;
				break;
			case "Hyper Potion":
				$item_statement = "Your Pok&eacute;mon regained 250 HP.";
				$healAmount = 250; $consumeItem = true;
				break;
			case "Full Heal":
				if(in_array((string)($_SESSION['s'.$u][14] ?? ''), array('Sleep','Poison','Burn','Frozen','Paralyzed'), true)){
					$item_statement = "Your Pok&eacute;mon has been healed of its status affliction."; $consumeItem = true;
				} else $item_statement = "The Full Heal had no effect.";
				break;
			case "Awakening":
				if((string)($_SESSION['s'.$u][14] ?? '') === 'Sleep'){ $item_statement = "Your Pok&eacute;mon woke up."; $consumeItem = true; }
				else $item_statement = "The Awakening had no effect.";
				break;
			case "Parlyz Heal":
			case "Paralyze Heal":
				if((string)($_SESSION['s'.$u][14] ?? '') === 'Paralyzed'){ $item_statement = "Your Pok&eacute;mon has been healed of its paralysis."; $consumeItem = true; }
				else $item_statement = "The Parlyz Heal had no effect.";
				break;
			case "Antidote":
				if((string)($_SESSION['s'.$u][14] ?? '') === 'Poison'){ $item_statement = "Your Pok&eacute;mon has been healed of its poison."; $consumeItem = true; }
				else $item_statement = "The Antidote had no effect.";
				break;
			case "Burn Heal":
				if((string)($_SESSION['s'.$u][14] ?? '') === 'Burn'){ $item_statement = "Your Pok&eacute;mon has been healed of its burn."; $consumeItem = true; }
				else $item_statement = "The Burn Heal had no effect.";
				break;
			case "Ice Heal":
				if((string)($_SESSION['s'.$u][14] ?? '') === 'Frozen'){ $item_statement = "Your Pok&eacute;mon has been defrosted."; $consumeItem = true; }
				else $item_statement = "The Ice Heal had no effect.";
				break;
		}

		if ($consumeItem) {
			$itemIndex = (int)$itemMap[$requestedItem][0];
			$itemColumn = (string)$itemMap[$requestedItem][1];
			$uid = (int)$_SESSION['myid'];
			if (pv_battle_runtime_consume_item($battleDb, $uid, $itemColumn)) {
				$_SESSION['items'][$itemIndex] = max(0, (int)$_SESSION['items'][$itemIndex] - 1);
				if ($healAmount > 0) $_SESSION['s'.$u][10] += $healAmount;
				elseif (in_array($i_u, array('Full Heal','Awakening','Parlyz Heal','Paralyze Heal','Antidote','Burn Heal','Ice Heal'), true)) unset($_SESSION['s'.$u][14]);
			} else {
				$item_statement = "That item could not be consumed. Your inventory was not changed.";
			}
		}
		if($_SESSION['s'.$u][10] > $_SESSION['s'.$u][11]){ // If your pokemon's HP is over it's max HP
			$_SESSION['s'.$u][10] = $_SESSION['s'.$u][11]; // Set the HP to it's max
		}
		if($_SESSION['s'.$u][10] < 0){ // If Pokemon's HP is 0 or lower
			$_SESSION['s'.$u][10] = 0; // Set the Pokemon's HP to 0
		}
		}
	}
	
	//----------------------------------------------YOU AND AN OPPONENT ATTACKS---------------------------------------------//
	
	if(!empty($_POST['attack'])){
		$rat = (int)$_POST['attack'];

		$u = $_SESSION['y_p'][0];
		$n = $_SESSION['y_p'][1];
		$rat = $rat + 5;
		$attack = $_SESSION['s'.$u][$rat]; // Attack you used
		$_SESSION['s'.$u][13] = 1;
		if($attack == $_SESSION['attack_short'][0]){
			$hittinga = $_SESSION['attack_short'][0]; // Your Attack name
			$hittingb = $_SESSION['attack_short'][1]; // Your attack type
			$hittingc = $_SESSION['attack_short'][2]; // Your attack power
			$hittingh = $_SESSION['attack_short'][7]; // Your attack accuracy
			$categoryb = $_SESSION['attack_short'][9]; // Your attack category
		}

		$tu = rand(1,4);
		$tu = $tu + 5;
		$oattack = $_SESSION['ops'.$n][$tu]; // Opponents attack
		if($oattack == $_SESSION['attack_short'][3]){
			$hittingd = $_SESSION['attack_short'][3]; // Opponents attack name
			$hittinge = $_SESSION['attack_short'][4]; // Opponents attack type
			$hittingf = $_SESSION['attack_short'][5]; // Opponents attack power
			$hittingg = $_SESSION['attack_short'][6]; // Opponents attack accuracy
			$categorya = $_SESSION['attack_short'][8]; // Opponents attack category
		}

		if($attack != $_SESSION['attack_short'][0] || !$_SESSION['attack_short'][0]){ // Your attack session not set
			$r_u = pv_battle_move_data((string)$attack, (string)($_SESSION['s'.$u][2] ?? 'Normal'));
			$hittinga = $r_u['attack'];
			$hittingb = $r_u['type'];
			$hittingc = $r_u['power'];
			$hittingh = $r_u['accuracy'];
			$categoryb = $r_u['category'];
			$_SESSION['attack_short'][0] = $hittinga; // Your attack name session
			$_SESSION['attack_short'][1] = $hittingb; // Your attack type session
			$_SESSION['attack_short'][2] = $hittingc; // Your attack power session
			$_SESSION['attack_short'][7] = $hittingh; // Your attack accuracy session
			$_SESSION['attack_short'][9] = $categoryb; // Your attack category session
		}
		if($oattack != $_SESSION['attack_short'][3] || !$_SESSION['attack_short'][3]){ // Opponents attack session not set
			$r_a = pv_battle_move_data((string)$oattack, (string)($_SESSION['ops'.$n][2] ?? 'Normal'));
			$hittingd = $r_a['attack'];
			$hittinge = $r_a['type'];
			$hittingf = $r_a['power'];
			$hittingg = $r_a['accuracy'];
			$categorya = $r_a['category'];
			$_SESSION['attack_short'][3] = $hittingd; // Opponents attack name session
			$_SESSION['attack_short'][4] = $hittinge; // Opponents attack type session
			$_SESSION['attack_short'][5] = $hittingf; // Opponents attack power session
			$_SESSION['attack_short'][6] = $hittingg; // Opponents attack accuracy session
			$_SESSION['attack_short'][8] = $categorya; // Opponents attack category session
		}
		if($hittingb == $_SESSION['s'.$u][2] || $hittingb == $_SESSION['s'.$u][3] ){ // If your Pokemon shares type with the attack
			$multn = 1.5; // STAB
		}
		else {
			$multn = 1; // Standard damage multiplier
		}
		if($hittinge == $_SESSION['ops'.$n][2] || $hittinge == $_SESSION['ops'.$n][3] ){ // If your opponent shares type with the attack
			$opmultn = 1.5; // Opponent STAB
		}
		else {
			$opmultn = 1; // Opponent Standard damage multiplier
		}

		$w = $_SESSION['s'.$u][4] / 30; // Your pokemon's level divided by 30
		$ww = $_SESSION['attack_short'][2] / 2; // Your attack power divided by 2
		$y = $_SESSION['attack_short'][1]; // Your attack type
		$g = $_SESSION['ops'.$n][2]; // Opponents Pokemon type 1
		$f = $_SESSION['ops'.$n][3]; // Opponents Pokemon type 2
		$damages = convert("$y", "$g", "$f"); // Get type markup
		if(strstr($_SESSION['s'.$u][0],'Dark ') || strstr($_SESSION['ops'.$n][0],'Metallic ')){ // If your Pokemon is Dark or opponent is Metallic
			$d_a = 1; // Standard damage multiplier
			if(strstr($_SESSION['ops'.$n][0],'Metallic ')){ // If opponent is Metallic
				$d_a = $d_a - 0.25; // 25% defence boost
			}
			if(strstr($_SESSION['s'.$u][0],'Dark ')){ // If your Pokemon is Dark
				$d_a = $d_a + 0.25; // 25% Attack boost
			} // work out final damage to opponent
			$w2 = $w * $ww; // lvl / 30 * atkpwr / 2
			$w3 = $w2 * $d_a; // lvl / 30 * atkpwr / 2 * dmg-multiplier
			$w4 = $w3 * $damages; // lvl / 30 * atkpwr / 2 * dmg-multiplier * type-markup
			$www = round($w4 * $multn); // round to nearest whole number(lvl / 30 * atkpwr / 2 * dmg-multiplier * type-markup * STAB)
		} // End if metallic or dark
		else { // non dark/metallic final damage
		
			// Critical hit chance --- 6.25%
			$crit = rand(1,16);
			if($crit == 1){
				$d_a = 1.5;
			}
			else{
				$d_a = 1;
			}
			$w2 = $w * $ww; // lvl / 30 * atkpwr / 2
			$w3 = $w2 * $d_a; // lvl / 30 * atkpwr / 2 * dmg-multiplier
			$w4 = $w3 * $damages; // lvl / 30 * atkpwr / 2 * dmg-multiplier * type-markup
			$www = round($w4 * $multn); // round to nearest whole number(lvl / 30 * atkpwr / 2 * type-markup * STAB)
		}

		$h = $_SESSION['ops'.$n][4] / 30; // Opponents level divided by 30
		$hh = $_SESSION['attack_short'][5] / 2; // Opponents attack power divided by 2
		$qw = $_SESSION['attack_short'][4]; // Opponents attack type
		$wew = $_SESSION['s'.$u][2]; // Your Pokemon type 1
		$efe = $_SESSION['s'.$u][3]; // Your Pokemon type 2
		$damages2 = convert("$qw", "$wew", "$efe"); // Get type-markup
		if(strstr($_SESSION['ops'.$n][0],'Dark ') || strstr($_SESSION['s'.$u][0],'Metallic ')){ // If your Pokemon is Metallic or opponent is Dark
			$d_a = 1; // Standard multiplier
			if(strstr($_SESSION['s'.$u][0],'Metallic ')){ // If your Pokemon is Metallic
				$d_a = $d_a - 0.25; // 25% defence boost
			}
			if(strstr($_SESSION['ops'.$n][0],'Dark ')){ // If opponent is Dark
				$d_a = $d_a + 0.25; // 25% Attack boost
			}
			// work out final damage to your Pokemon
			$h2 = $h * $hh; // lvl / 30 * atkpwr / 2
			$h3 = $h2 * $d_a; // lvl / 30 * atkpwr / 2 * dmg-multiplier
			$h4 = $h3 * $damages2; // lvl / 30 * atkpwr / 2 * dmg-muliplier * type-markup
			$hhh = round($h4 * $opmultn); // round to nearest whole number(lvl / 30 * atkpwr / 2 * dmg-multiplier * type-markup * STAB)
		} // End if metallic or dark
		else { // non dark/metallic final damage
		
			// Critical hit chance --- 6.25%
			$crit = rand(1,16);
			if($crit == 1){
				$d_a = 1.5;
			}
			else{
				$d_a = 1;
			}
			$h2 = $h * $hh; // lvl / 30 * atkpwr / 2
			$h3 = $h2 * $d_a; // lvl / 30 * atkpwr / 2 * dmg-multiplier
			$h4 = $h3 * $damages2; // lvl / 30 * atkpwr / 2 * dmg-multiplier * type-markup
			$hhh = round($h4 * $opmultn); // round to nearest whole number(lvl / 30 * atkpwr / 2 * type-markup * STAB)
		}

		if(strstr($_SESSION['ops'.$n][0],'Mystic')){ // If opponent is mystic
			$ran = rand(1,4);
			if($ran == 2){ // 1 in 4 chance of being scared
				$you_scared = 2;
				$www = 0;
			}
		}
		if(strstr($_SESSION['s'.$u][0],'Mystic')){ // If your Pokemon is mystic
			$rand = rand(1,4);
			if($rand == 2){ // 1 in 4 chance of scaring opponent
				$op_scared = 2;
				$hhh = 0;
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '95'){ // If attack accuracy is 95
			if($_SESSION['attack_short'][6] == '95'){ // If opponents attack accuracy is 95
				$acc = rand(1,100);
				if($acc > 95){ // 95 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '95'){ // If your attack accuracy is 95
				$acc = rand(1,100);
				if($acc > 95){ // 95 in 100 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '90'){ // If attack accuracy is 90
			if($_SESSION['attack_short'][6] == '90'){ // If opponents attack accuracy is 90
				$acc = rand(1,100);
				if($acc > 90){ // 90 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '90'){ // If your attack accuracy is 90
				$acc = rand(1,100);
				if($acc > 90){ // 90 in 100 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '85'){ // If attack accuracy is 85
			if($_SESSION['attack_short'][6] == '85'){ // if opponents attack accuracy is 85
				$acc = rand(1,100);
				if($acc > 85){ // 85 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '85'){ // If your attack accuracy is 85
				$acc = rand(1,100);
				if($acc > 85){ // 85 in 100 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '80'){ // If attack acuracy is 80
			if($_SESSION['attack_short'][6] == '80'){ // If opponents attack accuracy is 80
				$acc = rand(1,100);
				if($acc > 80){ // 80 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '80'){ // If your attack accuracy is 80
				$acc = rand(1,100);
				if($acc > 80){ // 80 in 10 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '75'){ // If attack accuracy is 75
			if($_SESSION['attack_short'][6] == '75'){ // If opponents attack accuracy is 75
				$acc = rand(1,100);
				if($acc > 75){ // 75 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '75'){ // If your attack accuracy is 75
				$acc = rand(1,100);
				if($acc > 75){ // 75 in 100 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '70'){ // If attack accuracy is 70
			if($_SESSION['attack_short'][6] == '70'){ // If opponents attack accuracy is 70
				$acc = rand(1,100);
				if($acc > 70){ // 70 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '70'){ // If your attack accuracy is 70
				$acc = rand(1,100);
				if($acc > 70){ // 70 in 100 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '60'){ // If attack accuracy is 60
			if($_SESSION['attack_short'][6] == '60'){ // If opponents attack accuracy is 60
				$acc = rand(1,100);
				if($acc > 60){ // 60 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '60'){ // If your attack accuracy is 60
				$acc = rand(1,100);
				if($acc > 60){ // 60 in 100 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '55'){ // If attack accuracy is 55
			if($_SESSION['attack_short'][6] == '55'){ // If opponents attack accuracy is 55
				$acc = rand(1,100);
				if($acc > 55){ // 55 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '55'){ // If your attack accuracy is 55
				$acc = rand(1,100);
				if($acc > 55){ // 55 in 100 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '50'){ // If attack accuracy is 50
			if($_SESSION['attack_short'][6] == '50'){ // If opponents attack accuracy is 50
				$acc = rand(1,100);
				if($acc > 50){ // 50 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '50'){ // If your attack accuracy is 50
				$acc = rand(1,100);
				if($acc > 50){ // 50 in 100 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		if($_SESSION['attack_short'][6] || $_SESSION['attack_short'][7] == '30'){ // If attack accuracy is 30
			if($_SESSION['attack_short'][6] == '30'){ // If opponents attack accuracy is 30
				$acc = rand(1,100);
				if($acc > 30){ // 30 in 100 chance of hitting
					$op_missed = 1;
					$hhh = 0;
				}
			}
			if($_SESSION['attack_short'][7] == '30'){ // If your attack accuracy is 30
				$acc = rand(1,100);
				if($acc > 30){ // 30 in 100 chance of hitting
					$u_missed = 1;
					$www = 0;
				}
			}
		}
		//-----------------Opponents Transform and Sketch attacks-------------------//
		if(!$op_missed){
			if($oattack == 'Transform'){
				$_SESSION['ops'.$n][0] = $_SESSION['s'.$u][0]; // Change your Pokemon name to the opponents
				$_SESSION['ops'.$n][2] = $_SESSION['s'.$u][2]; // Change your Pokemon type 1 to the opponents
				$_SESSION['ops'.$n][3] = $_SESSION['s'.$u][3]; // Change your Pokemon type 2 to the opponents
				$_SESSION['ops'.$n][6] = $_SESSION['s'.$u][6]; // Change your Pokemon attack 1 to the opponents
				$_SESSION['ops'.$n][7] = $_SESSION['s'.$u][7]; // Change your Pokemon attack 2 to the opponents
				$_SESSION['ops'.$n][8] = $_SESSION['s'.$u][8]; // Change your Pokemon attack 3 to the opponents
				$_SESSION['ops'.$n][9] = $_SESSION['s'.$u][9]; // Change your Pokemon attack 4 to the opponents
			}
			if($oattack == 'Sketch'){
				$_SESSION['ops'.$n][$tu] = $_SESSION['s'.$u][$rat];
			}
		}
		//----------------Opponent inflicting a status effect on you--------------------//
		
		if(!$_SESSION['s'.$u][14] && !$op_missed && !$op_scared){ // Make sure there isn't already a status effect in play and the opponent didn't miss
		
		//--------------------------BURN------------------------//
		
			// Attacks with 10% chance to burn
			if($oattack == 'Blaze Kick' || $oattack == 'Blue Flare' || $oattack == 'Ember' || $oattack == 'Fire Blast' || $oattack == 'Fire Fang' || $oattack == 'Fire Punch' || $oattack == 'Flame Wheel' || $oattack == 'Flamethrower' || $oattack == 'Heat Wave' || $oattack == 'Ice Burn' || $oattack == 'Searing Shot'){
				if($_SESSION['s'.$u][2] == 'Fire' || $_SESSION['s'.$u][3] == 'Fire'){
					// Insert statement
				}
				else{
					$brn = rand(1,10);
					if($brn == '1'){
						$_SESSION['s'.$u][14] = 'Burn';
					}
				}
			}
			// Attacks with 30% chance to burn
			if($oattack == 'Lava Plume' || $oattack == 'Scald'){
				if($_SESSION['s'.$u][2] == 'Fire' || $_SESSION['s'.$u][3] == 'Fire'){
					// Insert statement
				}
				else{
					$brn = rand(1,10);
					if($brn < 4){
						$_SESSION['s'.$u][14] = 'Burn';
					}
				}
			}
			// Attacks with 50% chance to burn
			if($oattack == 'Sacred Fire'){
				if($_SESSION['s'.$u][2] == 'Fire' || $_SESSION['s'.$u][3] == 'Fire'){
					// Insert statement
				}
				else{
					$brn = rand(1,2);
					if($brn == '1'){
						$_SESSION['s'.$u][14] = 'Burn';
					}
				}
			}
			// Attacks with 100% chance to burn
			if($oattack == 'Will-O-Wisp' || $oattack == 'Inferno'){
				if($_SESSION['s'.$u][2] == 'Fire' || $_SESSION['s'.$u][3] == 'Fire'){
					// Insert statement
				}
				else{
					$_SESSION['s'.$u][14] = 'Burn';
				}
			}
			
			//---------------------FREEZE------------------//
			
			// Attacks with 10% chance to freeze
			if($oattack == 'Blizzard' || $oattack == 'Freeze-Dry' || $oattack == 'Ice Beam' || $oattack == 'Ice Fang' || $oattack == 'Ice Punch' || $oattack == 'Powder Snow' ){
				if($_SESSION['s'.$u][2] == 'Ice' || $_SESSION['s'.$u][3] == 'Ice'){
					// Insert statement
				}
				else{
					$frz = rand(1,10);
					if($frz == '1'){
						$_SESSION['s'.$u][14] = 'Frozen';
					}
				}
			}
			
			//-----------------PARALYSIS-----------------//
			
			// Attacks with 10% chance to paralyze
			if($oattack == 'Bolt Strike' || $oattack == 'Freeze Shock' || $oattack == 'Thunder Fang' || $oattack == 'Thunderbolt' || $oattack == 'Thunderpunch' || $oattack == 'Thundershock'){
				if($_SESSION['s'.$u][2] == 'Electric' || $_SESSION['s'.$u][3] == 'Electric'){
					// Insert statement
				}
				else{
					$par = rand(1,10);
					if($par == '1'){
						$_SESSION['s'.$u][14] = 'Paralyzed';
					}
				}
			}
			// Attacks with 30% chance to paralyze
			if($oattack == 'Body Slam' || $oattack == 'Bounce' || $oattack == 'Discharge' || $oattack == 'Force Palm' || $oattack == 'Lick' || $oattack == 'Spark' || $oattack == 'Thunder'){
				if($_SESSION['s'.$u][2] == 'Electric' || $_SESSION['s'.$u][3] == 'Electric'){
					// Insert statement
				}
				else{
					$par = rand(1,10);
					if($par < 4){
						$_SESSION['s'.$u][14] = 'Paralyzed';
					}
				}
			}
			// Attacks wih 100% chance to paralyze
			if($oattack == 'Glare' || $oattack == 'Nuzzle' || $oattack == 'Stun Spore' || $oattack == 'Thunder Wave' || $oattack == 'Zap Cannon'){
				if($_SESSION['s'.$u][2] == 'Electric' || $_SESSION['s'.$u][3] == 'Electric'){
					// Insert statement
				}
				else{
					$_SESSION['s'.$u][14] = 'Paralyzed';
				}
			}
			
			//----------------POISON----------------//
			
			// Attacks with 10% chance of poison
			if($oattack == 'Cross Poison' || $oattack == 'Poison Tail' || $oattack == 'Sludge Wave'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn == '1'){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 20% chance of poison
			if($oattack == 'Twineedle'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert stetement
				}
				else{
					$psn = rand(1,10);
					if($psn < 3){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 30% chance of poison
			if($oattack == 'Gunk Shot' || $oattack == 'Poison Jab' || $oattack == 'Poison Sting' || $oattack == 'Sludge' || $oattack == 'Sludge Bomb'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn < 4){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 40% chance to poison
			if($oattack == 'Smog'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn < 5){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 100% chance to poison
			if($oattack == 'Toxic Spikes' || $oattack == 'Poison Powder' || $oattack == 'Poison Gas'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$_SESSION['s'.$u][14] = 'Poison';
				}
			}
			
			//--------------SLEEP----------------------//
			
			// Attacks with 30% chance to sleep
			if($oattack == 'Relic Song'){
				// Add a check here for sleep immunity
				$slp = rand(1,10);
				if($slp < 4){
					$_SESSION['s'.$u][14] = 'Sleep';
					$u_sleep = 1;
				}
			}
			// Attacks with 100% chance to sleep
			if($oattack == 'Dark Void' || $oattack == 'Grasswhistle' || $oattack == 'Hypnosis' || $oattack == 'Lovely Kiss' || $oattack == 'Sing' || $oattack == 'Sleep Powder' || $oattack == 'Spore' || $oattack == 'Yawn'){
				// Add a check here for sleep immunity
				$_SESSION['s'.$u][14] = 'Sleep';
				$u_sleep = 1;
			}
			
			//------------------CONFUSION------------------//
			
			// Attacks with 10% to confuse
			if($oattack == 'Confusion' || $oattack == 'Hurricane' || $oattack == 'Psybeam' || $oattack == 'Signal Beam'){
				// Add a check here for confusion immunity
				$conf = rand(1,10);
				if($conf == '1'){
					$_SESSION['s'.$u][14] = 'Confused';
				}
			}
			
			//---------------BADLY POISONED------------------//
			
			// Attacks with 30% chance to badly poison
			if($oattack == 'Poison Fang'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$bpsn = rand(1,10);
					if($bpsn < 4){
						$_SESSION['s'.$u][14] = 'Poison';
					}
				}
			}
			// Attacks with 100% chance to badly poison
			if($_SESSION['attack_short'][3] == 'Toxic' || $_SESSION['attack_short'][3] == 'Toxic Spikes'){
				if($_SESSION['s'.$u][2] == 'Poison' || $_SESSION['s'.$u][3] == 'Poison' || $_SESSION['s'.$u][2] == 'Steel' || $_SESSION['s'.$u][3] == 'Steel'){
					// Insert statement
				}
				else{
					$_SESSION['s'.$u][14] = 'Poison';
				}
			}
			
		} // End opponent inflicting status effect on you
		
		//------------------------------------------- Your transform and sketch attacks--------------------------------------//
		
		if(!$u_missed){
			if($attack == 'Transform'){
				$_SESSION['s'.$u][0] = $_SESSION['ops'.$n][0]; // Change your Pokemon name to the opponents
				$_SESSION['s'.$u][2] = $_SESSION['ops'.$n][2]; // Change your Pokemon type 1 to the opponents
				$_SESSION['s'.$u][3] = $_SESSION['ops'.$n][3]; // Change your Pokemon type 2 to the opponents
				$_SESSION['s'.$u][6] = $_SESSION['ops'.$n][6]; // Change your Pokemon attack 1 to the opponents
				$_SESSION['s'.$u][7] = $_SESSION['ops'.$n][7]; // Change your Pokemon attack 2 to the opponents
				$_SESSION['s'.$u][8] = $_SESSION['ops'.$n][8]; // Change your Pokemon attack 3 to the opponents
				$_SESSION['s'.$u][9] = $_SESSION['ops'.$n][9]; // Change your Pokemon attack 4 to the opponents
			}
			if($attack == 'Sketch'){
				$_SESSION['s'.$u][$rat] = $_SESSION['ops'.$n][$tu];
			}
		}
		
		//-------------------You inflicting a status effect on the opponent------------------------//
		
		if(!$_SESSION['ops'.$n][14] && !$u_missed && !$u_scared && !$u_sleep){ // Make sure there isn't a status effect in play and you didn't miss, not scares and not asleep
		
		//--------------------------BURN------------------------//
		
			// Attacks with 10% chance to burn
			if($attack == 'Blaze Kick' || $attack == 'Blue Flare' || $attack == 'Ember' || $attack == 'Fire Blast' || $attack == 'Fire Fang' || $attack == 'Fire Punch' || $attack == 'Flame Wheel' || $attack == 'Flamethrower' || $attack == 'Heat Wave' || $attack == 'Ice Burn' || $attack == 'Searing Shot'){
				if($_SESSION['ops'.$n][2] == 'Fire' || $_SESSION['ops'.$n][3] == 'Fire'){
					// Insert statement
				}
				else{
					$brn = rand(1,10);
					if($brn == '1'){
						$_SESSION['ops'.$n][14] = 'Burn';
					}
				}
			}
			// Attacks with 30% chance to burn
			if($attack == 'Lava Plume' || $attack == 'Scald'){
				if($_SESSION['ops'.$n][2] == 'Fire' || $_SESSION['ops'.$n][3] == 'Fire'){
					// Insert statement
				}
				else{
					$brn = rand(1,10);
					if($brn < 4){
						$_SESSION['ops'.$n][14] = 'Burn';
					}
				}
			}
			// Attacks with 50% chance to burn
			if($attack == 'Sacred Fire'){
				if($_SESSION['ops'.$n][2] == 'Fire' || $_SESSION['ops'.$n][3] == 'Fire'){
					// Insert statement
				}
				else{
					$brn = rand(1,2);
					if($brn == '1'){
						$_SESSION['ops'.$n][14] = 'Burn';
					}
				}
			}
			// Attacks with 100% chance to burn
			if($attack == 'Will-O-Wisp' || $attack == 'Inferno'){
				if($_SESSION['ops'.$n][2] == 'Fire' || $_SESSION['ops'.$n][3] == 'Fire'){
					// Insert statement
				}
				else{
					$_SESSION['ops'.$n][14] = 'Burn';
				}
			}
			
			//---------------------FREEZE------------------//
			
			// Attacks with 10% chance to freeze
			if($attack == 'Blizzard' || $attack == 'Freeze-Dry' || $attack == 'Ice Beam' || $attack == 'Ice Fang' || $attack == 'Ice Punch' || $attack == 'Powder Snow' ){
				if($_SESSION['ops'.$n][2] == 'Ice' || $_SESSION['ops'.$n][3] == 'Ice'){
					// Insert statement
				}
				else{
					$frz = rand(1,10);
					if($frz == '1'){
						$_SESSION['ops'.$n][14] = 'Frozen';
					}
				}
			}
			
			//-----------------PARALYSIS-----------------//
			
			// Attacks with 10% chance to paralyze
			if($attack == 'Bolt Strike' || $attack == 'Freeze Shock' || $attack == 'Thunder Fang' || $attack == 'Thunderbolt' || $attack == 'Thunderpunch' || $attack == 'Thundershock'){
				if($_SESSION['ops'.$n][2] == 'Electric' || $_SESSION['ops'.$n][3] == 'Electric'){
					// Insert statement
				}
				else{
					$par = rand(1,10);
					if($par == '1'){
						$_SESSION['ops'.$n][14] = 'Paralyzed';
					}
				}
			}
			// Attacks with 30% chance to paralyze
			if($attack == 'Body Slam' || $attack == 'Bounce' || $attack == 'Discharge' || $attack == 'Force Palm' || $attack == 'Lick' || $attack == 'Spark' || $attack == 'Thunder'){
				if($_SESSION['ops'.$n][2] == 'Electric' || $_SESSION['ops'.$n][3] == 'Electric'){
					// Insert statement
				}
				else{
					$par = rand(1,10);
					if($par < 4){
						$_SESSION['ops'.$n][14] = 'Paralyzed';
					}
				}
			}
			// Attacks wih 100% chance to paralyze
			if($attack == 'Glare' || $attack == 'Nuzzle' || $attack == 'Stun Spore' || $attack == 'Thunder Wave' || $attack == 'Zap Cannon'){
				if($_SESSION['ops'.$n][2] == 'Electric' || $_SESSION['ops'.$n][3] == 'Electric'){
					// Insert statement
				}
				else{
					$_SESSION['ops'.$n][14] = 'Paralyzed';
				}
			}
			
			//----------------POISON----------------//
			
			// Attacks with 10% chance of poison
			if($attack == 'Cross Poison' || $attack == 'Poison Tail' || $attack == 'Sludge Wave'){
				if($_SESSION['ops'.$n][2] == 'Poison' || $_SESSION['ops'.$n][3] == 'Poison' || $_SESSION['ops'.$n][2] == 'Steel' || $_SESSION['ops'.$n][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn == '1'){
						$_SESSION['ops'.$n][14] = 'Poison';
					}
				}
			}
			// Attacks with 20% chance of poison
			if($attack == 'Twineedle'){
				if($_SESSION['ops'.$n][2] == 'Poison' || $_SESSION['ops'.$n][3] == 'Poison' || $_SESSION['ops'.$n][2] == 'Steel' || $_SESSION['ops'.$n][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn < 3){
						$_SESSION['ops'.$n][14] = 'Poison';
					}
				}
			}
			// Attacks with 30% chance of poison
			if($attack == 'Gunk Shot' || $attack == 'Poison Jab' || $attack == 'Poison Sting' || $attack == 'Sludge' || $attack == 'Sludge Bomb'){
				if($_SESSION['ops'.$n][2] == 'Poison' || $_SESSION['ops'.$n][3] == 'Poison' || $_SESSION['ops'.$n][2] == 'Steel' || $_SESSION['ops'.$n][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn < 4){
						$_SESSION['ops'.$n][14] = 'Poison';
					}
				}
			}
			// Attacks with 40% chance to poison
			if($attack == 'Smog'){
				if($_SESSION['ops'.$n][2] == 'Poison' || $_SESSION['ops'.$n][3] == 'Poison' || $_SESSION['ops'.$n][2] == 'Steel' || $_SESSION['ops'.$n][3] == 'Steel'){
					// Insert statement
				}
				else{
					$psn = rand(1,10);
					if($psn < 5){
						$_SESSION['ops'.$n][14] = 'Poison';
					}
				}
			}
			// Attacks with 100% chance to poison
			if($attack == 'Toxic Spikes' || $attack == 'Poison Powder' || $attack == 'Poison Gas'){
				if($_SESSION['ops'.$n][2] == 'Poison' || $_SESSION['ops'.$n][3] == 'Poison' || $_SESSION['ops'.$n][2] == 'Steel' || $_SESSION['ops'.$n][3] == 'Steel'){
					// Insert statement
				}
				else{
					$_SESSION['ops'.$n][14] = 'Poison';
				}
			}
			
			//--------------SLEEP----------------------//
			
			// Attacks with 30% chance to sleep
			if($attack == 'Relic Song'){
				// Add a check here for sleep immunity
				$slp = rand(1,10);
				if($slp < 4){
					$_SESSION['ops'.$n][14] = 'Sleep';
				}
			}
			// Attacks with 100% chance to sleep
			if($attack == 'Dark Void' || $attack == 'Grasswhistle' || $attack == 'Hypnosis' || $attack == 'Lovely Kiss' || $attack == 'Sing' || $attack == 'Sleep Powder' || $attack == 'Spore' || $attack == 'Yawn'){
				// Add a check here for sleep immunity
				$_SESSION['ops'.$n][14] = 'Sleep';
			}
			
			//------------------CONFUSION------------------//
			
			// Attacks with 10% to confuse
			if($attack == 'Confusion' || $attack == 'Hurricane' || $attack == 'Psybeam' || $attack == 'Signal Beam'){
				// Add a check here for confusion immunity
				$conf = rand(1,10);
				if($conf == '1'){
					$_SESSION['ops'.$n][14] = 'Confused';
				}
			}
			
			//---------------BADLY POISONED------------------//
			
			// Attacks with 30% chance to badly poison
			if($attack == 'Poison Fang'){
				if($_SESSION['ops'.$n][2] == 'Poison' || $_SESSION['ops'.$n][3] == 'Poison' || $_SESSION['ops'.$n][2] == 'Steel' || $_SESSION['ops'.$n][3] == 'Steel'){
					// Insert statement
				}
				else{
					$bpsn = rand(1,10);
					if($bpsn < 4){
						$_SESSION['ops'.$n][14] = 'Poison';
					}
				}
			}
			// Attacks with 100% chance to badly poison
			if($_SESSION['attack_short'][3] == 'Toxic' || $_SESSION['attack_short'][3] == 'Toxic Spikes'){
				if($_SESSION['ops'.$n][2] == 'Poison' || $_SESSION['ops'.$n][3] == 'Poison' || $_SESSION['ops'.$n][2] == 'Steel' || $_SESSION['ops'.$n][3] == 'Steel'){
					// Insert statement
				}
				else{
					$_SESSION['ops'.$n][14] = 'Poison';
				}
			}

		} // End inflicting status effect on opponent
		
		//------------------------------------Status effect damages and effects to you and opponent-----------------------------------//
		
		//-------------------------Your status effect damages--------------------------//
		
		if($_SESSION['s'.$u][14] == 'Poison'){ // If your Pokemon is Poisoned
			$percent_u = 12;
			$maxhp_u = $_SESSION['s'.$u][11];
			$damg_u = ($percent_u / 100) * $maxhp_u;
			$state_u = "was hurt by it's poisoning.";
		}
		if($_SESSION['s'.$u][14] == 'Burn'){ // If your Pokemon is Burned
			$percent_u = 12;
			$maxhp_u = $_SESSION['s'.$u][11];
			$damg_u = ($percent_u / 100) * $maxhp_u;
			$state_u = "was hurt by it's burn.";
		}
		if($_SESSION['s'.$u][14] == 'Sleep'){ // If your pokemon is asleep
			$wake_u = rand(1,4);
			if($wake_u == 1){
				unset($_SESSION['s'.$u][14]);
			}
			else{
				$www = 0;
			}
		}
		if($_SESSION['s'.$u][14] == 'Paralyzed'){ // If your pokemon is paralyzed
			$para_u = rand(1,4);
			if($para_u == 1){
				$www = 0;
			}
		}
		if($_SESSION['s'.$u][14] == 'Frozen'){ // If your pokemon is frozen
			$frz_u = rand(1,5);
			if($frz_u > 1){
				$www = 0;
			}
			if($frz_u == 1){ // Thaw out of frozen state
				unset($_SESSION['s'.$u][14]);
			}
		}
		if($_SESSION['s'.$u][14] == 'Confused'){ // If your Pokemon is confused
			$conf_u = rand(1,2);
			if($conf_u == 1){
				$percent_u = 10;
				$maxhp_u = $_SESSION['s'.$u][11];
				$damg_u = ($percent_u / 100) * $maxhp_u;
				$www = 0;
			}
		}
		
		//--------------------------Opponent status effect damages------------------------------//
		
		if($_SESSION['ops'.$n][14] == 'Poison'){ // If opponent is Poisoned
			$percent = 12;
			$maxhp_op = $_SESSION['ops'.$n][11];
			$damg_op = ($percent / 100) * $maxhp_op;
			$state_op = "was hurt by it's poisoning.";
		}
		if($_SESSION['ops'.$n][14] == 'Burn'){ // If opponent is Burned
			$percent = 12;
			$maxhp_op = $_SESSION['ops'.$n][11];
			$damg_op = ($percent / 100) * $maxhp_op;
			$state_op = "was hurt by it's burn.";
		}
		if($_SESSION['ops'.$n][14] == 'Sleep'){ // If opponent is asleep
			$wake_op = rand(1,4);
			if($wake_op == 1){
				unset($_SESSION['ops'.$n][14]);
			}
			else{
				$hhh = 0;
			}
		}
		if($_SESSION['ops'.$n][14] == 'Paralyzed'){ // If opponent is paralyzed
			$para_op = rand(1,4);
			if($para_op == 1){
				$hhh = 0;
			}
		}
		if($_SESSION['ops'.$n][14] == 'Frozen'){ // If opponent is frozen
			$frz_op = rand(1,5);
			if($frz_op > 1){
				$hhh = 0;
			}
			if($frz_op == 1){
				unset($_SESSION['ops'.$n][14]);
			}
		}
		if($_SESSION['ops'.$n][14] == 'Confused'){ // If opponent is confused
			$conf_op = rand(1,2);
			if($conf_op == 1){
				$percent_op = 10;
				$maxhp_op = $_SESSION['s'.$u][11];
				$damg_op = ($percent_op / 100) * $maxhp_op;
				$hhh = 0;
			}
		}

		$_SESSION['s'.$u][10] = $_SESSION['s'.$u][10] - $hhh - round($damg_u); // Your pokemon HP session minus final damage
		$_SESSION['ops'.$n][10] = $_SESSION['ops'.$n][10] - $www - round($damg_op);// Opponents HP session minus final damage

		if($_SESSION['s'.$u][10] < 0){ // if your HP below 0
			$_SESSION['s'.$u][10] = 0; // set HP to 0
		}
		if($_SESSION['ops'.$n][10] < 0){ // if opponents HP below 0
			$_SESSION['ops'.$n][10] = 0; // set to 0
		}
		$div = pv_safe_divide((float)$_SESSION['s'.$u][10], (float)$_SESSION['s'.$u][11], 0.0);
		$_SESSION['s'.$u][12] = $div * 100;
		$div = pv_safe_divide((float)$_SESSION['ops'.$n][10], (float)$_SESSION['ops'.$n][11], 0.0);
		$_SESSION['ops'.$n][12] = $div * 100;
	}
	if(!empty($_POST['active_pokemon'])){ // Get the Pokemon you're using
		$atp = $_POST['active_pokemon'];
		if($atp == $_SESSION['s1'][1]){ // slot 1
			$_SESSION['y_p'][0] = 1;
		}
		if($atp == $_SESSION['s2'][1]){ // slot 2
			$_SESSION['y_p'][0] = 2;
		}
		if($atp == $_SESSION['s3'][1]){ // slot 3
			$_SESSION['y_p'][0] = 3;
		}
		if($atp == $_SESSION['s4'][1]){ // slot 4
			$_SESSION['y_p'][0] = 4;
		}
		if($atp == $_SESSION['s5'][1]){ // slot 5
			$_SESSION['y_p'][0] = 5;
		}
		if($atp == $_SESSION['s6'][1]){ // slot 6
			$_SESSION['y_p'][0] = 6;
		}
		$opponentCount = (int)($_SESSION['opponent_profile'][2] ?? 0);
		$spot = pv_battle_first_alive_slot('ops', $opponentCount);
		// A living opponent must exist while selecting a combatant. If the party
		// is already defeated, retain slot 1 only for the recovered victory view;
		// never select one of the intentionally empty compatibility slots.
		$_SESSION['y_p'][1] = $spot > 0 ? $spot : 1;
	}
	// Self-heal stale v20/v20.1 battle sessions as well: an opponent slot is
	// valid only when it falls inside the authoritative hydrated team count and
	// contains a real Pokémon row. This prevents an already-open broken tab from
	// continuing to render the recovered empty-slot glitch sprite.
	$opponentCount = (int)($_SESSION['opponent_profile'][2] ?? 0);
	$currentOpponentSlot = (int)($_SESSION['y_p'][1] ?? 0);
	$currentOpponentState = $_SESSION['ops'.$currentOpponentSlot] ?? null;
	if ($currentOpponentSlot < 1 || $currentOpponentSlot > $opponentCount || !is_array($currentOpponentState) || (int)($currentOpponentState[1] ?? 0) <= 0) {
		$recoveredOpponentSlot = pv_battle_first_alive_slot('ops', $opponentCount);
		if ($recoveredOpponentSlot > 0) $_SESSION['y_p'][1] = $recoveredOpponentSlot;
	}
	$q = max(1, min(6, (int)($_SESSION['y_p'][1] ?? 1)));
	$p = max(1, min(6, (int)($_SESSION['y_p'][0] ?? 1)));
	echo "<form action=\"battle.php\" method=\"post\" name=\"1{$random}\" id=\"1{$random}\" style='display:none;' >" . pv_battle_form_security_fields() . "
	<input type='submit' value='Continue' />
	</form>";
	echo "<form action=\"battle.php\" method=\"post\" name=\"{$random}\" id=\"{$random}\" onsubmit=\"disableSubmitButton(this);\">" . pv_battle_form_security_fields();
	echo "<h2>";
	if((!empty($_POST['attack']) && $_SESSION['s'.$p][11] != 0) || (!empty($_POST['item']) && $_SESSION['s'.$p][11] != 0)){
		if(!empty($_POST['item']) && $_SESSION['s'.$p][11] != 0){
			echo "Item Results / Select an Attack";
		}
		else{
			echo "Attack Results / Select an Attack";
		}
	}
	else{
		echo "Select an Attack";
	}

	$pvPlayerResolvedMoveType = htmlspecialchars((string)($_SESSION['attack_short'][1] ?? 'Normal'), ENT_QUOTES, 'UTF-8');
	$pvEnemyResolvedMoveType = htmlspecialchars((string)($_SESSION['attack_short'][4] ?? 'Normal'), ENT_QUOTES, 'UTF-8');
	$pvPlayerCurrentHp = max(0, (int)($_SESSION['s'.$p][10] ?? 0));
	$pvPlayerMaxHp = max(1, (int)($_SESSION['s'.$p][11] ?? 1));
	$pvEnemyCurrentHp = max(0, (int)($_SESSION['ops'.$q][10] ?? 0));
	$pvEnemyMaxHp = max(1, (int)($_SESSION['ops'.$q][11] ?? 1));
	echo '</h2><table class="pv-legacy-combat-table" data-pv-player-move-type="' . $pvPlayerResolvedMoveType . '" data-pv-enemy-move-type="' . $pvEnemyResolvedMoveType . '" data-pv-player-current-hp="' . $pvPlayerCurrentHp . '" data-pv-player-max-hp="' . $pvPlayerMaxHp . '" data-pv-enemy-current-hp="' . $pvEnemyCurrentHp . '" data-pv-enemy-max-hp="' . $pvEnemyMaxHp . '" cellpadding="0" cellspacing="0" style="width: 80%; text-align: center; margin: 0 auto;"><tr style="vertical-align: bottom;"><td class="pv-legacy-battle-fighter" style="width: 50%;">';
	echo '<h3>Your ' . $_SESSION['s'.$p][0] . '</h3><img class="pv-legacy-battle-sprite pv-legacy-battle-player" src="html/static/images/pokemon/' . $_SESSION['s'.$p][0] . '.gif" width="96" height="96" /><br /><em>Level:</em> ' . $_SESSION['s'.$p][4] . '</td><td class="pv-legacy-battle-fighter" style="width: 50%;"><h3>' . htmlentities($_SESSION['opponent_profile'][1]) . '\'s ' . $_SESSION['ops'.$q][0] . '</h3><img class="pv-legacy-battle-sprite pv-legacy-battle-enemy" src="html/static/images/pokemon/' . $_SESSION['ops'.$q][0] . '.gif" width="96" height="96" /><br /><em>Level:</em> ' . $_SESSION['ops'.$q][4] . '</span></td></tr>';
	echo '<tr style="vertical-align: middle;"><td style="width: 50%; padding: 10px 0;">
	<strong>HP: <img src="html/static/images/misc/hpbar.gif" height="10" width="' . $_SESSION['s'.$p][12] . '" style="border:1px solid black" /> ' . $_SESSION['s'.$p][10] . ' </strong>'; // display your status effect
	if($_SESSION['s'.$u][14] == 'Poison'){
		echo '<img src="html/static/images/misc/poison.png" />';
	}
	if($_SESSION['s'.$u][14] == 'Sleep'){
		echo '<img src="html/static/images/misc/sleep.png" />';
	}
	if($_SESSION['s'.$u][14] == 'Burn'){
		echo '<img src="html/static/images/misc/burn.png" />';
	}
	if($_SESSION['s'.$u][14] == 'Paralyzed'){
		echo '<img src="html/static/images/misc/paralyze.png" />';
	}
	if($_SESSION['s'.$u][14] == 'Frozen'){
		echo '<img src="html/static/images/misc/freeze.png" />';
	}
	echo '</td>
	<td style="width: 50%; padding: 10px 0;">
	<strong>HP: <img src="html/static/images/misc/hpbar.gif" height="10" width="' . $_SESSION['ops'.$q][12] . '" style="border:1px solid black" /> ' . $_SESSION['ops'.$q][10] . ' </strong>'; // display opponents status effect
	if($_SESSION['ops'.$n][14] == 'Poison'){
		echo '<img src="html/static/images/misc/poison.png" />';
	}
	if($_SESSION['ops'.$n][14] == 'Sleep'){
		echo '<img src="html/static/images/misc/sleep.png" />';
	}
	if($_SESSION['ops'.$n][14] == 'Burn'){
		echo '<img src="html/static/images/misc/burn.png" />';
	}
	if($_SESSION['ops'.$n][14] == 'Paralyzed'){
		echo '<img src="html/static/images/misc/paralyze.png" />';
	}
	if($_SESSION['ops'.$n][14] == 'Frozen'){
		echo '<img src="html/static/images/misc/freeze.png" />';
	}
	echo '</td></tr>';
	
	//---------------------------------------------------Damage if both Pokemon attack-------------------------------------------//
	
	if(!empty($_POST['attack'])){
		
		//---------------opponent attacking you-----------------//
		
		echo '<tr><td style="width: 50%; padding: 0 15px;" valign="top"><strong>';
					// If the opponent attack hits, isn't scared, asleep, confused, frozen or paralyzed
		if($op_scared != 2 && !$op_missed && $_SESSION['ops'.$n][14] != 'Sleep' && $conf_op != 1 && $frz_op != 1 && $para_op != 1){
			if($oattack == 'Will-O-Wisp' || $oattack == 'Glare' || $oattack == 'Stun Spore' || $oattack == 'Thunder Wave' || $oattack == 'Poison Gas' || $oattack == 'Poison Powder' || $oattack == 'Toxic Spikes' || $oattack == 'Toxic' || $oattack == 'Dark Void' || $oattack == 'Grasswhistle' || $oattack == 'Hypnosis' || $oattack == 'Lovely Kiss' || $oattack == 'Rest' || $oattack == 'Sing' || $oattack == 'Sleep Powder' || $oattack == 'Spore' || $oattack == 'Yawn' || $oattack == 'Confuse Ray' || $oattack == 'Flatter' || $oattack == 'Supersonic' || $oattack == 'Swagger' || $oattack == 'Sweet Kiss' || $oattack == 'Teeter Dance'){ // status effect attacks that do no damage
				echo $_SESSION['ops'.$q][0] . ' attacked your ' . $_SESSION['s'.$p][0] . ' with ' . $oattack . '';
			}
			elseif($oattack == 'Transform'){
				echo 'Ditto used Transform and turned into ' . $_SESSION['s'.$p][0] . '.';
			}
			elseif($oattack == 'Sketch'){
				echo $_SESSION['ops'.$q][0] . ' used ' . $oattack . ' and copied ' . $attack . '.';
			}
			else{
				echo $_SESSION['ops'.$q][0] . ' attacked your ' . $_SESSION['s'.$p][0] . ' with ' . $oattack . ' and ';
				if($hhh == 0){
					echo 'had no effect.';
				}
				else{
					echo 'did '. $hhh . ' HP damage.'; if($crit == 1 && !$_SESSION['attack_short'][9] == 'Status'){ echo '<br /><br />It was a critical hit!'; }
				}
	
				if($damages2 == 4 && $hhh != 0){
					echo '<br/><br/>The attack was ultra effective!';
				}
				if($damages2 == 2 && $hhh != 0){
					echo '<br/><br/>The attack was super effective!';
				}
				if($damages2 < 1 && $damages2 > 0 && $hhh != 0){
					echo '<br/><br/>The attack was not very effective.';
				}
				if($damages2 == 0 && $hhh != 0){
					echo '<br/><br/>The attack did no damage.';
				}
			}
			if($_SESSION['s'.$p][10] == 0){
				echo '<br/><br/>' . $_SESSION['s'.$p][0] . ' has fainted.';
			}
		}
		if($op_scared == 2){ // If your opponent is scared and didn't miss
			echo $_SESSION['ops'.$q][0] . ' is scared and could not attack.';
		}
		if($op_missed == 1){ // If the opponents attack missed
			echo $_SESSION['ops'.$q][0] . ' attacked your ' . $_SESSION['s'.$p][0] . ' with ' . $oattack . ' but missed';
		}
		if($state_op){
			echo '<br /><br />' . $_SESSION['ops'.$q][0] . ' ' . $state_op;
		}
		if($_SESSION['ops'.$n][14] == 'Sleep'){ // If the opponent is asleep
			echo $_SESSION['ops'.$q][0] . ' is fast asleep.';
		}
		if($wake_op == 1){ // If opponent wakes up
			echo $_SESSION['ops'.$q][0] . ' woke up.';
		}
		if($frz_op > 1){ // If the opponent is frozen
			echo $_SESSION['ops'.$q][0] . ' is frozen solid and could not move.';
		}
		if($frz_op == 1){ // If the opponent thaws out of freeze state
			echo $_SESSION['ops'.$q][0] . ' thawed out of it\'s frozen state.';
		}
		if($conf_op == 1){ // If the opponent is confused
			echo $_SESSION['ops'.$q][0] . ' hurt itself in it\'s confusion.';
		}
		if($para_op == 1){ // If the opponent is paralyzed
			echo $_SESSION['ops'.$q][0] . ' is paralyzed and could not move.';
		}

		echo '</strong></td><td style="width: 50%; padding: 0 15px;" valign="top"><strong>';
		
		//-------------------You attacking opponent------------------------//
		
				// If your attack hits, you're not scared, asleep, frozen, paralyzed or confused
		if($you_scared != 2 && !$u_missed && $_SESSION['s'.$u][14] != 'Sleep' && $conf_u != 1 && $frz_u != 1 && $para_u != 1){
			if($attack == 'Will-O-Wisp' || $attack == 'Glare' || $attack == 'Stun Spore' || $attack == 'Thunder Wave' || $attack == 'Poison Gas' || $attack == 'Poison Powder' || $attack == 'Toxic Spikes' || $attack == 'Toxic' || $attack == 'Dark Void' || $attack == 'Grasswhistle' || $attack == 'Hypnosis' || $attack == 'Lovely Kiss' || $attack == 'Rest' || $attack == 'Sing' || $attack == 'Sleep Powder' || $attack == 'Spore' || $attack == 'Yawn' || $attack == 'Confuse Ray' || $attack == 'Flatter' || $attack == 'Supersonic' || $attack == 'Swagger' || $attack == 'Sweet Kiss' || $attack == 'Teeter Dance'){ // status effect attacks that do no damage
				echo 'Your ' . $_SESSION['s'.$p][0] . ' attacked ' . $_SESSION['ops'.$q][0] . ' with ' . $attack . '';
			}
			elseif($attack == 'Transform'){
				echo 'Your Ditto used Transform and turned into ' . $_SESSION['ops'.$q][0] . '.';
			}
			elseif($attack == 'Sketch'){
				echo 'Your ' . $_SESSION['s'.$p][0] . ' used ' . $attack . ' and copied ' . $oattack . '.';
			}
			else{
				echo 'Your ' . $_SESSION['s'.$p][0] . ' attacked ' . $_SESSION['ops'.$q][0] . ' with ' . $attack . ' and ';
				if($www == 0){
					echo 'had no effect.';
				}
				else{
					echo 'did '. $www . ' HP damage.'; if($crit == 1 && !$_SESSION['attack_short'][8] == 'Status'){ echo '<br /><br />It was a critical hit!'; }
				}
				if($damages == 4 && $www != 0){
					echo '<br/><br/>The attack was ultra effective!';
				}
				if($damages == 2 && $www != 0){
					echo '<br/><br/>The attack was super effective!';
				}
				if($damages < 1 && $damages > 0 && $www != 0){
					echo '<br/><br/>The attack was not very effective.';
				}
				if($damages == 0){
					echo '<br/><br/>The attack did no damage.';
				}
			}
			if($_SESSION['ops'.$q][10] == 0){ // If the opponent has fainted
				echo '<br/><br/>' . $_SESSION['ops'.$q][0] . ' has fainted.';
			}
		}
		if($you_scared == 2){ // If your Pokemon is scared and didn't miss the attack
			echo $_SESSION['s'.$p][0] . ' is scared and could not attack.';
		}
		if($u_missed == 1){ // If your attack missed
			echo 'Your ' . $_SESSION['s'.$p][0] . ' attacked ' . $_SESSION['ops'.$q][0] . ' with ' . $attack . ' but missed';
		}
		if($state_u){
			echo '<br /><br />Your ' . $_SESSION['s'.$p][0] . ' ' . $state_u;
		}
		if($_SESSION['s'.$u][14] == 'Sleep'){ // You're asleep
			echo $_SESSION['s'.$p][0] . ' is fast asleep.';
		}
		if($wake_u == 1){ // You woke up
			echo $_SESSION['s'.$p][0] . ' woke up.';
		}
		if($frz_u > 1){ // You're frozen
			echo $_SESSION['s'.$p][0] . ' is frozen solid and could not move.';
		}
		if($frz_u == 1){ // You thawed out of freeze
			echo $_SESSION['s'.$p][0] . ' thawed out of it\'s frozen state.';
		}
		if($conf_u == 1){ // Hurt yourself in confusion
			echo $_SESSION['s'.$p][0] . ' hurt itself in it\'s confusion.';
		}
		if($para_u == 1){ // You're paralyzed
			echo $_SESSION['s'.$p][0] . ' is paralyzed and could not move.';
		}
		echo '</strong></td></tr><tr><td style="width:50%;"><div class="hr"></div></td><td style="width:50%;"><div class="hr"></div></td></tr>';
	}
	 //------------------------------------Damage to your Pokemon if you use an item-----------------------------------------------------//
	if(!empty($_POST['item'])){
		echo '<tr><td style="width: 50%; padding: 0 15px;" valign="top"><strong>';
				// If the opponents attack didn't miss or opponent isn't scared
		if($op_scared != 2 && !$op_missed && $_SESSION['ops'.$n][14] != 'Sleep' && $conf_op != 1 && $frz_op != 1 && $para_op != 1){
			if($oattack == 'Will-O-Wisp' || $oattack == 'Glare' || $oattack == 'Stun Spore' || $oattack == 'Thunder Wave' || $oattack == 'Poison Gas' || $oattack == 'Poison Powder' || $oattack == 'Toxic Spikes' || $oattack == 'Toxic' || $oattack == 'Dark Void' || $oattack == 'Grasswhistle' || $oattack == 'Hypnosis' || $oattack == 'Lovely Kiss' || $oattack == 'Rest' || $oattack == 'Sing' || $oattack == 'Sleep Powder' || $oattack == 'Spore' || $oattack == 'Yawn' || $oattack == 'Confuse Ray' || $oattack == 'Flatter' || $oattack == 'Supersonic' || $oattack == 'Swagger' || $oattack == 'Sweet Kiss' || $oattack == 'Teeter Dance'){ // status effect attacks that do no damage
				echo $_SESSION['ops'.$q][0] . ' attacked your ' . $_SESSION['s'.$p][0] . ' with ' . $oattack . '';
			}
			elseif($oattack == 'Transform'){
				echo 'Ditto used Transform and turned into ' . $_SESSION['s'.$p][0] . '.';
			}
			elseif($oattack == 'Sketch'){
				echo $_SESSION['ops'.$q][0] . ' used ' . $oattack . ' and copied ' . $_SESSION['attack_short'][0] . '.';
			}
			else{
				echo $_SESSION['ops'.$q][0] . ' attacked your ' . $_SESSION['s'.$p][0] . ' with ' . $oattack . ' and ';
				if($hhh == 0){
					echo 'had no effect.';
				}
				else{
					echo 'did '. $hhh . ' HP damage.'; if($crit == '1' && !$_SESSION['attack_short'][8] == 'Status'){ echo '<br /><br />It was a critical hit!'; }
				}
				if($damages2 == '4' && $hhh != 0){
					echo '<br/><br/>The attack was ultra effective!';
				}
				if($damages2 == '2' && $hhh != 0){
					echo '<br/><br/>The attack was super effective!';
				}
				if($damages2 < 1 && $damages2 > 0 && $hhh != 0){
					echo '<br/><br/>The attack was not very effective.';
				}
				if($damages2 == '0' && $hhh != 0){
					echo '<br/><br/>The attack did no damage.';
				}
			}
			if($_SESSION['s'.$p][10] == 0){ // If your Pokemon fainted
				echo '<br/><br/>' . $_SESSION['s'.$p][0] . ' has fainted.';
			}
		}

		if($op_scared == '2'){ // If your opponent is scared and didn't miss the attack
			echo $_SESSION['ops'.$q][0] . ' is scared an could not attack.';
		}
		if($op_missed == '1'){ // If your opponent missed the attack
			echo $_SESSION['ops'.$q][0] . ' attacked your ' . $_SESSION['s'.$p][0] . ' with ' . $oattack . ' but missed ';
		}
		
		if($state){
			echo '<br /><br />Your ' . $_SESSION['s'.$p][0] . ' ' . $state;
		}

		echo '<br/><br/>' . $item_statement;
		

		echo '</strong></td><td style="width: 50%; padding: 0 15px;" valign="top"><strong>Your ' . $_SESSION['s'.$p][0] . ' could not attack.';

		echo '</strong></td></tr><tr><td style="width:50%;"><div class="hr"></div></td><td style="width:50%;"><div class="hr"></div></td></tr>';

	}
	//------------------------------------------ End attacking damages and item use calculations------------------------------------//
	if($_SESSION['ops'.$q][10] != 0 && $_SESSION['s'.$p][10] != 0){
		$one = 'checked="checked"';
		if($_SESSION['attack_short'][0] == $_SESSION['s'.$p][7]){
			$two = 'checked="checked"';
		}
		if($_SESSION['attack_short'][0] == $_SESSION['s'.$p][8]){
			$three = 'checked="checked"';
		}
		if($_SESSION['attack_short'][0] == $_SESSION['s'.$p][9]){
			$four = 'checked="checked"';
		}
		$pvCheckedMoves = [1 => $one ?? '', 2 => $two ?? '', 3 => $three ?? '', 4 => $four ?? ''];
		echo '<td style="width: 50%; padding: 0 10px;"><table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 0 auto; text-align: left;"><tr><td><p><strong>Select an attack:</strong></p><p>';
		for ($pvMoveSlot = 1; $pvMoveSlot <= 4; $pvMoveSlot++) {
			$pvMoveName = (string)($_SESSION['s'.$p][$pvMoveSlot + 5] ?? 'Struggle');
			$pvMoveData = pv_battle_move_data($pvMoveName, (string)($_SESSION['s'.$p][2] ?? 'Normal'));
			echo '<input type="radio" name="attack" id="attack' . $pvMoveSlot . '" value="' . $pvMoveSlot . '" ' . $pvCheckedMoves[$pvMoveSlot]
				. ' data-pv-move-name="' . htmlspecialchars($pvMoveName, ENT_QUOTES, 'UTF-8') . '"'
				. ' data-pv-move-type="' . htmlspecialchars((string)($pvMoveData['type'] ?? 'Normal'), ENT_QUOTES, 'UTF-8') . '"'
				. ' data-pv-move-power="' . (int)($pvMoveData['power'] ?? 0) . '"'
				. ' data-pv-move-accuracy="' . (int)($pvMoveData['accuracy'] ?? 100) . '" />'
				. $pvMoveSlot . '. ' . htmlspecialchars($pvMoveName, ENT_QUOTES, 'UTF-8') . ($pvMoveSlot < 4 ? '<br />' : '');
		}
		echo '</p></td></tr></table></td>';

		echo '<td style="width: 50%; padding: 0 10px;">
		<table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 0 auto; text-align: left;"><tr><td><p><strong>Attacks:</strong></p><p>1. ' . $_SESSION['ops'.$q][6] . '<br />2. ' . $_SESSION['ops'.$q][7] . '<br />3. ' . $_SESSION['ops'.$q][8] . '<br />4. ' . $_SESSION['ops'.$q][9] . '</p></td></tr></table></td>';

		echo '</tr></table>';
		echo '<input type="hidden" name="action" value="attack" /><br /><input type="submit" value="'.generateBattleButtonText('Attack').'" /></form>
		<div class="hr"></div><h2 style="margin-top: 30px;">Or Use an Item</h2>
		<table cellpadding="0" cellspacing="0" style="margin: 0 auto;">
		<tr style="vertical-align: text-top;"><td>
		<form action="battle.php" method="post" id="itemForm" name="itemForm" onsubmit="disableSubmitButton(this);">' . pv_battle_form_security_fields() . '
		<table cellpadding="0" cellspacing="0" style="width: 260px; margin: 0 20px;">
		<tr style="text-align: center;"><td>
		<strong>Item:</strong></td>
		<td width="80"><strong>Quantity:</strong></td></tr>';


		$quick = array("Potion", "Super Potion", "Hyper Potion", "Full Heal", "Awakening", "Parlyz Heal", "Antidote", "Burn Heal", "Ice Heal");
		for($a=0;$a<9;$a++){
			echo '<tr><td style="text-align: left;"><input type="radio" name="item" id="item2" value="' . $quick[$a] . '" ';
			if($_SESSION['items'][$a] == 0){
				echo "disabled";
			}
			echo '/> <label for="item2"><img src="html/static/images/items/' . $quick[$a] . '.png" height="24" width="24" align="absmiddle">';

			if($_SESSION['items'][$a] == 0){
				echo '<s>';
			}
			echo $quick[$a];
			if($_SESSION['items'][$a] == 0){
				echo '</s>';
			}
			echo '</label></td><td align="center">' . $_SESSION['items'][$a] . '</td></tr>';
		}
		echo '<tr><td colspan="2"><center><input name="items" type="submit" value="Use Item" /><br /></center></td></tr></td></tr></table></form></td></td></tr>';
	}
	if($_SESSION['ops'.$q][10] == 0 || $_SESSION['s'.$p][10] == 0){
		echo '<input name="choose" type="hidden" value="pokechu">';
		echo '<tr><td colspan="2"><center><input type="submit" value="'.generateBattleButtonText('Continue').'"'.(rand(1,2)===2?' style="margin-top: 25px;"':'').'></center></td></tr>';
	}
	echo "</table>";
}
if($battleStartRequested){
	
		/**** Set battle start JS-check token ****/
		$_SESSION['nojs-check-a'] = rand(11,20);
		$_SESSION['nojs-check-b'] = rand(1,10);
		$_SESSION['nojs-check'] = $_SESSION['nojs-check-a'] + $_SESSION['nojs-check-b'];
		/*****************************************/

		$battleDb = pv_db();
		$get_op = false;
		$type = '';
		if($battleRouteType === 'eventtrainer'){
			$eventCatalogOpponent = pv_event_battle_by_route((string)$battleRoute['eventtrainer']);
			if (!$eventCatalogOpponent) pv_redirect('battle_select.php?mode=events&invalid_battle=1');
			$stmt = $battleDb->prepare('SELECT s1,s2,s3,s4,s5,s6,id,trainer FROM event WHERE id=? AND trainer=? LIMIT 1');
			$eventId = (int)$eventCatalogOpponent['id'];
			$eventTrainer = (string)$eventCatalogOpponent['route'];
			$stmt->bind_param('is', $eventId, $eventTrainer);
			$stmt->execute(); $get_op = $stmt->get_result(); $stmt->close();
			$type = 'event';
		}
		elseif($battleRouteType === 'gymleader'){
			$catalogOpponent = pv_battle_catalog_by_route((string)$battleRoute['gymleader']);
			if (!$catalogOpponent) pv_redirect('battle_select.php?invalid_battle=1');
			$stmt = $battleDb->prepare('SELECT s1,s2,s3,s4,s5,s6,id,leader FROM gym WHERE id=? AND leader=? LIMIT 1');
			$gymLeader = (string)$catalogOpponent['route'];
			$gymId = (int)$catalogOpponent['id'];
			$stmt->bind_param('is', $gymId, $gymLeader);
			$stmt->execute(); $get_op = $stmt->get_result(); $stmt->close();
			$type = 'gym';
		}
		elseif($battleRouteType === 'sidequest'){
			$sidequestId = (int)$_SESSION['sidequest'];
			$sidequestCatalogOpponent = pv_sidequest_by_id($sidequestId);
			if (!$sidequestCatalogOpponent) pv_redirect('sidequest.php?invalid_battle=1');
			$sidequestTrainer = (string)$sidequestCatalogOpponent['trainer'];
			$stmt = $battleDb->prepare('SELECT s1,s2,s3,s4,s5,s6,id,name FROM sidequests WHERE id=? AND name=? LIMIT 1');
			$stmt->bind_param('is', $sidequestId, $sidequestTrainer);
			$stmt->execute(); $get_op = $stmt->get_result(); $stmt->close();
			$type = 'side';
		}
		elseif($battleRouteType === 'clanbattle'){
			$clanSecret = (string)($_SESSION['clan_battle'][0] ?? '');
			$stmt = $battleDb->prepare('SELECT s1,s2,s3,s4,s5,s6,id,username FROM members WHERE secret_key=? LIMIT 1');
			$stmt->bind_param('s', $clanSecret);
			$stmt->execute(); $get_op = $stmt->get_result(); $stmt->close();
			$type = 'clan';
		}
		elseif($battleRouteType === 'bid'){
			$battleOpponentId = (int)$battleRoute['bid'];
			$stmt = $battleDb->prepare('SELECT s1,s2,s3,s4,s5,s6,id,username FROM members WHERE id=? LIMIT 1');
			$stmt->bind_param('i', $battleOpponentId);
			$stmt->execute(); $get_op = $stmt->get_result(); $stmt->close();
		}
		$count_op = $get_op instanceof mysqli_result ? $get_op->num_rows : 0;
		if($count_op === 0){
			// Never enter the recovered combat engine without a real server-side
			// opponent record. A fabricated/missing route must not become a
			// zero-HP opponent or leave stale battle session data behind.
			unset($_SESSION['opponent_profile']);
			pv_redirect($battleFailureRoute($type));
		}
		else{
			$get_op1 = $get_op->fetch_assoc() ?: [];
			// Build the opponent party from integer server-owned slot ids only. The
			// recovered code had six separate IN() branches and assumed slots were
			// contiguous; this handles gaps safely and keeps the table name allowlisted.
			$opponentTeamIds = [];
			for ($slotNo = 1; $slotNo <= 6; $slotNo++) {
				$teamPokemonId = max(0, (int)($get_op1['s'.$slotNo] ?? 0));
				if ($teamPokemonId > 0 && !in_array($teamPokemonId, $opponentTeamIds, true)) $opponentTeamIds[] = $teamPokemonId;
			}
			$opponentPartyKey = $type === '' ? 'player' : $type;
			$opponentRows = pv_battle_runtime_party(
				$battleDb,
				$opponentPartyKey,
				max(0, (int)$get_op1['id']),
				$opponentTeamIds
			);
			$o_num = count($opponentRows);
			if ($o_num <= 0) {
				// Do not allow an empty/invalid team to be interpreted as an
				// already-defeated opponent (which historically could yield an
				// immediate win/reward path).
				unset($_SESSION['opponent_profile']);
				pv_redirect($battleFailureRoute($type));
			}
			if($type == 'event'){
				$_SESSION['opponent_profile'] = array("{$get_op1['id']}","{$get_op1['trainer']}","{$o_num}","{$type}");
			}
			elseif($type == 'gym'){
				$_SESSION['opponent_profile'] = array("{$get_op1['id']}","{$get_op1['leader']}","{$o_num}","{$type}");
			}
			elseif($type == 'side'){
				$_SESSION['opponent_profile'] = array("{$get_op1['id']}","{$get_op1['name']}","{$o_num}","{$type}");
			}
			elseif($type == 'clan'){
				$_SESSION['opponent_profile'] = array("{$get_op1['id']}","{$get_op1['username']}","{$o_num}","{$type}","{$battleRoute['clanbattle']}");
			}
			else{
				$_SESSION['opponent_profile'] = array("{$get_op1['id']}","{$get_op1['username']}","{$o_num}","{$type}");
			}
			for ($emptySlot = 1; $emptySlot <= 6; $emptySlot++) {
				$_SESSION['ops'.$emptySlot] = ['',0,'','',0,0,'','','','',0,0,'0','0','0','0'];
			}
			foreach ($opponentRows as $index => $goo) {
				$ohp = strstr((string)$goo['name'], 'Shiny') ? ((int)$goo['lvl'] * 5) : ((int)$goo['lvl'] * 4);
				$slotNo = $index + 1;
				$_SESSION['ops'.$slotNo] = array(
					$goo['name'],$goo['id'],$goo['t1'],$goo['t2'],$goo['lvl'],$goo['exp'],
					$goo['a1'],$goo['a2'],$goo['a3'],$goo['a4'],$ohp,$ohp,"100","0","0","0"
				);
			}


				if(!isset($_SESSION['items'])){
					$trainerId = (int)$_SESSION['myid'];
					$itt = pv_battle_runtime_items($battleDb, $trainerId);
					$_SESSION['items'] = array((int)$itt['Potion'],(int)$itt['Super_Potion'],(int)$itt['Hyper_Potion'],(int)$itt['Full_Heal'],(int)$itt['Awakening'],(int)$itt['Parlyz_Heal'],(int)$itt['Antidote'],(int)$itt['Burn_Heal'],(int)$itt['Ice_Heal'],(int)$itt['Poke_Ball'],(int)$itt['Great_Ball'],(int)$itt['Ultra_Ball'],(int)$itt['Master_Ball']);
				}

				// Re-read the trainer's six active slots from the database at battle start.
				// The recovered my_team session value is only a compatibility cache and can
				// legitimately be stale after a team edit made earlier in the same login.
				$trainerId = (int)$_SESSION['myid'];
				$playerTeamSlots = [0,0,0,0,0,0];
				$stmt = $battleDb->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');
				if ($stmt) {
					$stmt->bind_param('i', $trainerId);
					$stmt->execute();
					$memberTeam = $stmt->get_result()->fetch_assoc() ?: [];
					$stmt->close();
					for ($slotNo = 1; $slotNo <= 6; $slotNo++) $playerTeamSlots[$slotNo-1] = max(0, (int)($memberTeam['s'.$slotNo] ?? 0));
				}
				$_SESSION['my_team'] = $playerTeamSlots;
				$playerTeamIds = [];
				foreach ($playerTeamSlots as $teamPokemonId) {
					if ($teamPokemonId > 0 && !in_array($teamPokemonId, $playerTeamIds, true)) $playerTeamIds[] = $teamPokemonId;
				}
				$playerRows = pv_battle_runtime_party($battleDb, 'player', $trainerId, $playerTeamIds);
				$u_num = count($playerRows);
				if ($u_num <= 0) pv_redirect('change_team.php?battle_team_required=1');

				$_SESSION['your_profile'] = array((string)$_SESSION['myid'],(string)$_SESSION['myuser'],(string)$u_num,(string)$_SESSION['myeb']);
				for ($emptySlot = 1; $emptySlot <= 6; $emptySlot++) {
					$_SESSION['s'.$emptySlot] = ['',0,'','',0,0,'','','','',0,0,'0','0','0','0'];
				}
				foreach ($playerRows as $index => $goo) {
					$hp = strstr((string)$goo['name'], 'Shiny') ? ((int)$goo['lvl'] * 5) : ((int)$goo['lvl'] * 4);
					$slotNo = $index + 1;
					$_SESSION['s'.$slotNo] = array(
						$goo['name'],$goo['id'],$goo['t1'],$goo['t2'],$goo['lvl'],$goo['exp'],
						$goo['a1'],$goo['a2'],$goo['a3'],$goo['a4'],$hp,$hp,"100","0","0","0"
					);
				}

				$_SESSION['position'] = 1;
				}
			}
			echo "<form action=\"battle.php\" method=\"post\" name=\"{$random}\" id=\"{$random}\" onsubmit=\"solveJScap(); disableSubmitButton(this);\">" . pv_battle_form_security_fields();
			if(($_SESSION['position'] ?? 0) == 1 || (($_POST['choose'] ?? '') === "pokechu") ){
				$all_dead_op = 1;
				$all_dead_op = $_SESSION['ops1'][10] + $_SESSION['ops2'][10] + $_SESSION['ops3'][10] + $_SESSION['ops4'][10] + $_SESSION['ops5'][10] + $_SESSION['ops6'][10];
				$all_dead_u = $_SESSION['s1'][10] + $_SESSION['s2'][10] + $_SESSION['s3'][10] + $_SESSION['s4'][10] + $_SESSION['s5'][10] + $_SESSION['s6'][10];
                // Ranked W/L and RP belong to the completed combat result, not
                // the legacy ten-second money/EXP reward gate below.
                $rankedTrainer = (int)$_SESSION['myid'];
                $rankedOpponent = (int)($_SESSION['opponent_profile'][0] ?? 0);
                if (isset($_SESSION['ops1']) && ($all_dead_op == 0 || $all_dead_u == 0)
                    && (string)($_SESSION['opponent_profile'][3] ?? '') === ''
                    && ($terminalRankedContext = pv_rival_session_context($rankedTrainer, $rankedOpponent))
                    && ($terminalRankedContext['phase'] ?? '') !== 'armed') {
                    $GLOBALS['pv_battle_ranked_opponent'] = $rankedOpponent;
                    $rankedOutcome = $all_dead_op == 0 ? 'win' : 'loss';
                    $rankedResult = pv_rival_complete_session_battle($battleDb, $rankedTrainer, $rankedOpponent, $rankedOutcome);
                    if ($rankedResult) {
                        $change = (int)$rankedResult['attacker_rating_change'];
                        echo '<div class="pv-rival-battle-result is-' . $rankedOutcome . '"><strong>Ranked ' . ($rankedOutcome === 'win' ? 'Victory' : 'Defeat') . '</strong><span>'
                            . ($change > 0 ? '+' : '') . number_format($change) . ' RP · Rating ' . number_format((int)$rankedResult['attacker_rating'])
                            . ' RP · Ranked record ' . number_format((int)$rankedResult['attacker_ranked_wins']) . ' W · ' . number_format((int)$rankedResult['attacker_ranked_losses'])
                            . ' L · Both trainers updated.</span><a href="' . pv_h(pv_url('rankings.php')) . '">Return to Trainer Rankings →</a></div>';
                    } else {
                        echo '<div class="pv-flash warning"><strong>Ranked result waiting to save.</strong><span>Your result is retained. Open Trainer Rankings to retry saving it.</span><a href="' . pv_h(pv_url('rankings.php')) . '">Return to Trainer Rankings →</a></div>';
                    }
                }
				if($all_dead_op == 0 && isset($_SESSION['ops1'])){

					$tb = pv_battle_runtime_member_snapshot($battleDb, (int)$_SESSION['myid']);
					$tbb = $tb['btime'];
					$time = time();
					$secs = $time - $tbb;
					if($secs < 10){
						echo '<div class="errorMsg">' . (!empty($GLOBALS['pv_battle_ranked_opponent']) ? 'Standard battle rewards are on cooldown. Your ranked result is handled separately above.' : 'You have already completed a battle within the last 10 seconds. This is in effect to prevent cheating of any kind.') . '</div>
						<p class="optionsList autowidth"><strong>Options:</strong><br />';
						if($_SESSION['opponent_profile'][3] == 'gym'){
							echo '<a href="battle.php?gymleader=' . $_SESSION['opponent_profile'][1] . '" class="deselected">Rebattle Opponent</a>';
						}
						elseif($_SESSION['opponent_profile'][3] == 'side'){
							echo '<a href="battle.php?sidequest=' . $_SESSION['opponent_profile'][0] . '" class="deselected">Rebattle Opponent</a>';
						}
						elseif($_SESSION['opponent_profile'][3] == 'event'){
							echo '<a href="battle.php?eventtrainer=' . rawurlencode((string)$_SESSION['opponent_profile'][1]) . '" class="deselected">Rebattle Opponent</a>';
						}
						elseif($_SESSION['opponent_profile'][3] == 'clan'){
							echo '<a href="clans.php?view=Battle" class="deselected">Back To Clan Battles</a>';
						}
						else{
							echo pv_battle_trainer_rebattle_link();
						}
						echo '</a><br />
						<a href="change_team.php" class="deselected">View/Modify Team</a><br />
						<a href="your_pokemon.php" class="deselected">View All Pokemon</a><br />
						<a href="items.php" class="deselected">Pok&eacute;mart</a></p>';
					}
					else{
						echo '<h2>Congratulations! You won the battle!</h2>
						<h3>Your team beat ' . htmlentities($_SESSION['opponent_profile'][1]) . '\'s team.</h3>';
						
						for($sa=1;$sa<=6;$sa++){
							if($_SESSION['s'.$sa][13] == 1){

								echo '<p><img src="html/static/images/pokemon/' . $_SESSION['s'.$sa][0] . '.gif" align="absmiddle"> <strong><a href="pokedex.php?pid=' . $_SESSION['s'.$sa][1] . '">' . $_SESSION['s'.$sa][0] . '</a></strong></p>';
								$ya += 1;
								$u_level += $_SESSION['s'.$sa][4];
							}
						}

						for($asw=1;$asw<=$_SESSION['opponent_profile'][2];$asw++){
							$amount_level += $_SESSION['ops'.$asw][4];
						}
						$exp2 = pv_safe_divide((float)$amount_level, max(1, (int)$u_level), 0.0);
						$r_e_x = $exp2 * 500;
						$exp2 = round($r_e_x * $_SESSION['your_profile'][3]); // add * 2 to the end for double experience
						$tottal = 0;
						for($sa=1;$sa<=6;$sa++){
							if($_SESSION['s'.$sa][13] == 1){
								$tottal += $exp2;
								$id = (int)$_SESSION['s'.$sa][1];
								$happy = rand(1,2);
								if (!pv_battle_runtime_award_participant($battleDb, (int)$_SESSION['myid'], $id, (int)$exp2, $happy)) {
									pv_log('Standard battle reward skipped for non-owned participant ' . $id . ' on trainer ' . (int)$_SESSION['myid']);
								}
							}
						}

						function randmoney($ex){
							$rvar = rand(1,1000);
							switch($rvar){
								case ($rvar <= 500):
								$moni = round($ex * 0.5);
								break;
								case ($rvar >= 501 && $rvar <= 800):
								$moni = round($ex * 1.2);
								break;
								case ($rvar >= 801 && $rvar <= 950):
								$moni = round($ex * 1.5);
								break;
								case ($rvar >= 951 && $rvar <= 994):
								$moni = round($ex * 2);
								break;
								case ($rvar >= 995 && $rvar <= 999):
								$moni = round($ex * 4);
								break;
								case ($rvar == 1000):
								$moni = round($ex * 7.5);
								break;
							}
							return $moni;
						}
						if($exp2 > 0){
							echo '<p>Each Pokemon above gained ';
							echo number_format($exp2); 
							$money = randmoney($exp2);
							if(!isset($_SESSION['battle_count'])){
								$_SESSION['battle_count'] = 1;
							}
							else{
								$_SESSION['battle_count'] +=1;
							}
							if($_SESSION['battle_count'] >= 2){
								
							}
							echo ' experience points.<br />';
							if($_SESSION['opponent_profile'][3] == 'side'){
									$sidequestAdvance = pv_advance_sidequest_after_win(
										pv_db(),
										(int)$_SESSION['myid'],
										(int)$_SESSION['opponent_profile'][0]
									);
								}
								elseif($_SESSION['opponent_profile'][3] == 'event'){
									try {
										$eventResult = pv_event_battle_award(pv_db(), (int)$_SESSION['myid'], (int)$_SESSION['opponent_profile'][0]);
									} catch (Throwable $e) {
										pv_log('Special Event progression settlement failed: ' . $e->getMessage());
									}
								}
								elseif($_SESSION['opponent_profile'][3] == 'gym'){
									try {
										$badgeResult = pv_battle_catalog_award(pv_db(), (int)$_SESSION['myid'], (int)$_SESSION['opponent_profile'][0]);
										if (!empty($badgeResult['all_complete'])) $_SESSION['map_preferences'][0] = 1;
									} catch (Throwable $e) {
										pv_log('Battle Arena progression settlement failed: ' . $e->getMessage());
									}
								}

								echo '<br />You also won <img src="html/static/images/misc/pmoney.gif">' . number_format($money) . ' to buy items with.</p>';
								echo '<p class="optionsList autowidth"><strong>Options:</strong><br />';
								if($_SESSION['opponent_profile'][3] == 'gym'){
									echo '<a href="battle.php?gymleader=' . $_SESSION['opponent_profile'][1] . '" class="deselected">Rebattle Opponent</a>';
									echo '<br /><a href="battle_select.php?mode=league" class="deselected">Return to Battle Arena</a>';
								}
								elseif($_SESSION['opponent_profile'][3] == 'side'){
									echo '<a href="sidequest.php" class="deselected">Continue Sidequests</a>';
								}
								elseif($_SESSION['opponent_profile'][3] == 'event'){
									echo '<a href="battle.php?eventtrainer=' . rawurlencode((string)$_SESSION['opponent_profile'][1]) . '" class="deselected">Rebattle Opponent</a>';
									echo '<br /><a href="battle_select.php?mode=events" class="deselected">Return to Special Events</a>';
								}
								elseif($_SESSION['opponent_profile'][3] == 'clan'){
									echo '<a href="clans.php?view=Battle" class="deselected">Back To Clan Battles</a>';
								}
								else{
									echo pv_battle_trainer_rebattle_link();
								}
								echo '</a><br />
								<a href="change_team.php" class="deselected">View/Modify Team</a><br />
								<a href="your_pokemon.php" class="deselected">View All Pokemon</a><br />
								<a href="items.php" class="deselected">Pok&eacute;mart</a></p>';
							
								// Persist the battle result first, then rebuild progression from the
								// authoritative Pokemon rows. This avoids the recovered code's stale
								// counters, division-by-zero paths and invalid log(0/1) scoring.
								$trainerId = max(1, (int)$_SESSION['myid']);
								$money = max(0, (int)$money);
								$clanName = (($_SESSION['opponent_profile'][3] ?? '') === 'clan') ? trim((string)($_SESSION['clan'] ?? '')) : '';
								pv_battle_runtime_record_victory($battleDb, $trainerId, (int)$time, $money, $clanName);
								if(($_SESSION['opponent_profile'][3] ?? '') === 'clan') unset($_SESSION['clan_battle']);


								pv_recalculate_trainer_progress($battleDb, $trainerId, true);
							}
						}

						unset($_SESSION['opponent_profile'],$_SESSION['s1'],$_SESSION['s2'],$_SESSION['s3'],$_SESSION['s4'],$_SESSION['s5'],$_SESSION['s6'],$_SESSION['ops1'],$_SESSION['ops2'],$_SESSION['ops3'],$_SESSION['ops4'],$_SESSION['ops5'],$_SESSION['ops6'],$_SESSION['position'],$_SESSION['your_profile'],$_SESSION['y_p']); 
}
						elseif($all_dead_u == 0 && isset($_SESSION['ops1'])){
							$tb = pv_battle_runtime_member_snapshot($battleDb, (int)$_SESSION['myid']);
							$tbb = $tb['btime'];
							$time = time();
							$secs = $time - $tbb;
							if($secs < 9){
								echo '<div class="errorMsg">' . (!empty($GLOBALS['pv_battle_ranked_opponent']) ? 'Standard battle rewards are on cooldown. Your ranked result is handled separately above.' : 'You have already completed a battle within the last 10 seconds. This is in effect to prevent cheating of any kind.') . '</div>
								<p class="optionsList autowidth"><strong>Options:</strong><br />';
								if($_SESSION['opponent_profile'][3] == 'gym'){
									echo '<a href="battle.php?gymleader=' . $_SESSION['opponent_profile'][1] . '" class="deselected">Rebattle Opponent</a>';
								}
								elseif($_SESSION['opponent_profile'][3] == 'side'){
									echo '<a href="battle.php?sidequest=' . $_SESSION['opponent_profile'][0] . '" class="deselected">Rebattle Opponent</a>';
								}
								elseif($_SESSION['opponent_profile'][3] == 'event'){
									echo '<a href="battle.php?eventtrainer=' . rawurlencode((string)$_SESSION['opponent_profile'][1]) . '" class="deselected">Rebattle Opponent</a>';
								}
								elseif($_SESSION['opponent_profile'][3] == 'clan'){
									echo '<a href="clans.php?view=Battle" class="deselected">Back To Clan Battles</a>';
								}
								else{
									echo pv_battle_trainer_rebattle_link();
								}
								echo '</a><br />
								<a href="change_team.php" class="deselected">View/Modify Team</a><br />
								<a href="your_pokemon.php" class="deselected">View All Pokemon</a><br />
								<a href="items.php" class="deselected">Pok&eacute;mart</a></p>';
							}
							else{
								$trainerId = max(1, (int)$_SESSION['myid']);
								$clanName = (($_SESSION['opponent_profile'][3] ?? '') === 'clan') ? trim((string)($_SESSION['clan'] ?? '')) : '';
								pv_battle_runtime_record_defeat($battleDb, $trainerId, (int)$time, $clanName);
								if(($_SESSION['opponent_profile'][3] ?? '') === 'clan') unset($_SESSION['clan_battle']);

								echo '<h2>Sorry, you lost the battle.</h2>
								<h3>Your team lost to ' . htmlentities($_SESSION['opponent_profile'][1]) . '\'s team.</h3>';
								echo '<p class="optionsList autowidth"><strong>Options:</strong><br />';
								if($_SESSION['opponent_profile'][3] == 'gym'){
									echo '<a href="battle.php?gymleader=' . $_SESSION['opponent_profile'][1] . '" class="deselected">Rebattle Opponent</a>';
								}
								elseif($_SESSION['opponent_profile'][3] == 'side'){
									echo '<a href="battle.php?sidequest=' . $_SESSION['opponent_profile'][0] . '" class="deselected">Rebattle Opponent</a>';
								}
								elseif($_SESSION['opponent_profile'][3] == 'event'){
									echo '<a href="battle.php?eventtrainer=' . rawurlencode((string)$_SESSION['opponent_profile'][1]) . '" class="deselected">Rebattle Opponent</a>';
								}
								elseif($_SESSION['opponent_profile'][3] == 'clan'){
									echo '<a href="clans.php?view=Battle" class="deselected">Back To Clan Battles</a>';
								}
								else{
									echo pv_battle_trainer_rebattle_link();
								}
								echo '</a><br />
								<a href="change_team.php" class="deselected">View/Modify Team</a><br />
								<a href="your_pokemon.php" class="deselected">View All Pokemon</a><br />
								<a href="items.php" class="deselected">Pok&eacute;mart</a></p>';
							}
							unset($_SESSION['opponent_profile'],$_SESSION['s1'],$_SESSION['s2'],$_SESSION['s3'],$_SESSION['s4'],$_SESSION['s5'],$_SESSION['s6'],$_SESSION['ops1'],$_SESSION['ops2'],$_SESSION['ops3'],$_SESSION['ops4'],$_SESSION['ops5'],$_SESSION['ops6'],$_SESSION['position'],$_SESSION['your_profile'],$_SESSION['y_p']); 
						}
						elseif($all_dead_u == 0 && $all_dead_op == 0 && !isset($_SESSION['ops1'])){
							echo '<h2>An error has occurred, please refresh the page or return to the battle select page you came from.</h2>';
						}
						else{

						echo '<h3>Select your next Pok&eacute;mon to battle:</h3><table cellspacing="0" cellpadding="0" class="pokemonList"><tr><td nowrap="nowrap" id="y_p">';
						for($i=1;$i<=$_SESSION['your_profile'][2];$i++){

							$pvRosterPlayerCurrentHp = max(0, (int)($_SESSION['s'.$i][10] ?? 0));
							$pvRosterPlayerMaxHp = max(1, (int)($_SESSION['s'.$i][11] ?? 1));
							echo '<table class="pv-battle-roster-card pv-battle-roster-player" data-pv-roster-current-hp="' . $pvRosterPlayerCurrentHp . '" data-pv-roster-max-hp="' . $pvRosterPlayerMaxHp . '" cellpadding="3" cellspacing="0"><tr><td><input type="radio" name="active_pokemon" value="'. $_SESSION['s'.$i][1] . '"';
							if($_SESSION['s1'][10] != 0 && $i == 1){  
								echo ' checked="checked"';
							} 
							elseif($_SESSION['s1'][10] == 0 && $_SESSION['s2'][10] != 0 && $i == 2){
								echo ' checked="checked"';
							} 
							elseif($_SESSION['s2'][10] == 0 && $_SESSION['s1'][10] == 0 && $_SESSION['s3'][10] != 0 && $i == 3){
								echo ' checked="checked"';
							}
							elseif($_SESSION['s3'][10] == 0 && $_SESSION['s2'][10] == 0 && $_SESSION['s1'][10] == 0 && $_SESSION['s4'][10] != 0 && $i == 4){
								echo ' checked="checked"';
							} 
							elseif($_SESSION['s4'][10] == 0 && $_SESSION['s3'][10] == 0 && $_SESSION['s2'][10] == 0 && $_SESSION['s1'][10] == 0 && $_SESSION['s5'][10] != 0 && $i == 5){
								echo ' checked="checked"';
							}
							elseif($_SESSION['s5'][10] == 0 && $_SESSION['s4'][10] == 0 && $_SESSION['s3'][10] == 0 && $_SESSION['s2'][10] == 0 && $_SESSION['s1'][10] == 0 && $_SESSION['s6'][10] != 0 && $i == 6){
								echo ' checked="checked"';
							}
							if($_SESSION['s'.$i][10] == 0){
								echo " disabled";
							}
							echo '/><img src="html/static/images/pokemon/' . $_SESSION['s'.$i][0] . '.gif" width="96" height="96" /></td><td><strong><a href="pokedex.php?pid=' . $_SESSION['s'.$i][1] . '">';
							if($_SESSION['s'.$i][10] == 0){
								echo "<s>";
							}
							echo $_SESSION['s'.$i][0] . '</a></strong><br /><em>Level:</em>' . $_SESSION['s'.$i][4] . '<br /><em>HP:</em>' . $_SESSION['s'.$i][10];
							if($_SESSION['s'.$i][10] == 0){
								echo "</s>";
							}
							echo '</p></td></tr></table>';
						}
						echo '</td></tr></table>';
						echo '<h2>' . htmlentities($_SESSION['opponent_profile'][1]) . '\'s Pok&eacute;mon Team:</h2>
						<h3>The order shown is the order you will battle them in.</h3>
						<table cellspacing="0" cellpadding="0" class="pokemonList">
						<tr>
						<td nowrap="nowrap" id="opponent_pokemon">';
	
						for($i=1;$i<=$_SESSION['opponent_profile'][2];$i++){
							$pvRosterEnemyCurrentHp = max(0, (int)($_SESSION['ops'.$i][10] ?? 0));
							$pvRosterEnemyMaxHp = max(1, (int)($_SESSION['ops'.$i][11] ?? 1));
							echo '<table class="pv-battle-roster-card pv-battle-roster-enemy" data-pv-roster-current-hp="' . $pvRosterEnemyCurrentHp . '" data-pv-roster-max-hp="' . $pvRosterEnemyMaxHp . '" cellpadding="3" cellspacing="0"><tr><td>';
							echo '<img src="html/static/images/pokemon/' . $_SESSION['ops'.$i][0] . '.gif" width="96" height="96" /></td><td><p><strong><a href="pokedex.php?';
							if($_SESSION['opponent_profile'][3] == 'gym' || $_SESSION['opponent_profile'][3] == 'side' || $_SESSION['opponent_profile'][3] == 'event'){
								echo'dex=' . $_SESSION['ops'.$i][0] . '">';
							}
							else{
								echo'pid=' . $_SESSION['ops'.$i][1] . '">';
							}
							if($_SESSION['ops'.$i][10] == 0){
								echo "<s>";
							}
							echo $_SESSION['ops'.$i][0];
							echo '</a></strong><br /><em>Level:</em> ' . $_SESSION['ops'.$i][4] . '<br /><em>HP:</em> ' . $_SESSION['ops'.$i][10];
							if($_SESSION['ops'.$i][10] == 0){
								echo "</s>";
							}
							echo "</p></td></tr></table>";
						}
						echo '</td></tr></table><input type="hidden" name="action" value="select_attack" />';

						displayNOJSpuzzle();

						echo '
							<p><input type="submit" value="'.generateBattleButtonText('Continue').'" /></p></form>';
						$_SESSION['position'] = 2;
					}
				}
				if(!$battleStartRequested && !isset($_SESSION['opponent_profile']) && empty($_POST['choose'])){
					echo '<div class="errorMsg">An error has occurred. Please try again later.</div>';
				}
					
				if(!$_REQUEST['ajax']){

					echo '</div>';
					include('disclaimer.php');
					echo '</div></div>
					</div>
					</div>
					</div>
					</body>
					</html>';
				} 
				?>