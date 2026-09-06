<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/rival_runtime.php';
pv_require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') pv_redirect('rival_hub.php');
pv_require_csrf();
if (!pv_consume_action_token('rival_action.php', (string)($_POST['rival_action_token'] ?? ''))) {
    $_SESSION['pv_rival_flash'] = ['type'=>'warning','message'=>'That battle order expired. Refresh the Rival Hub and choose the target again.'];
    pv_redirect('rival_hub.php');
}

$uid = max(1, (int)($_SESSION['myid'] ?? 0));
$targetId = max(0, (int)($_POST['target_id'] ?? 0));
$action = (string)($_POST['action'] ?? 'challenge');
$retaliationId = $action === 'retaliate' ? max(0, (int)($_POST['retaliation_id'] ?? 0)) : 0;

try {
    $db = pv_db();
    pv_rival_begin_attack($db, $uid, $targetId, $retaliationId);
    pv_server_event('PVP', 'Ranked Rival battle armed', [
        'target_uid'=>$targetId,
        'mode'=>$retaliationId > 0 ? 'retaliation' : 'challenge',
    ]);
    // Existing Trainer Snapshot combat remains the authoritative animated battle.
    pv_redirect('battle.php?bid=' . $targetId);
} catch (Throwable $e) {
    pv_log('Rival battle launch rejected: '.$e->getMessage(), ['user_id'=>$uid,'target_id'=>$targetId]);
    $_SESSION['pv_rival_flash'] = ['type'=>'warning','message'=>$e->getMessage()];
    pv_redirect('rival_hub.php');
}
