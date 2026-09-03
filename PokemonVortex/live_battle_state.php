<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/gameplay.php';
require_once __DIR__ . '/includes/live_battle_runtime.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$userId = max(0, (int)($_SESSION['myid'] ?? 0));
if ($userId <= 0 || (int)($_SESSION['access'] ?? 0) !== 9) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'refresh'=>false]);
    exit;
}

$userSlot = (int)($_SESSION['live'][0] ?? 0);
$opponentSlot = (int)($_SESSION['live'][1] ?? 0);
$opponentId = max(0, (int)($_SESSION['live'][3] ?? 0));
$battleId = max(0, (int)($_SESSION['live'][4] ?? 0));
$requestBattleId = max(0, (int)($_GET['match'] ?? 0));
if (!in_array($userSlot, [1,2], true) || !in_array($opponentSlot, [1,2], true)
    || $userSlot === $opponentSlot || $opponentId <= 0 || $battleId <= 0
    || $requestBattleId !== $battleId) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'refresh'=>true,'stale'=>true]);
    exit;
}

try {
    $state = pv_live_runtime_load(pv_db(), $battleId, $userSlot, $opponentSlot, $userId, $opponentId);
    $revision = max(0, (int)($state['revision'] ?? 0));
    $phase = (string)($state['phase'] ?? '');
    $turn = max(0, (int)($state['turn'] ?? 0));
    $clientRevision = max(0, (int)($_GET['revision'] ?? 0));
    $clientPhase = trim((string)($_GET['phase'] ?? ''));
    $clientTurn = max(0, (int)($_GET['turn'] ?? 0));
    $clientWaiting = (int)($_GET['waiting'] ?? 0) === 1;
    $settled = (int)($state['_settlement'][(string)$userSlot]['settled'] ?? 0) === 1
        || (int)($state['_settlement'][(string)$opponentSlot]['settled'] ?? 0) === 1;

    // A page that is still choosing a command is not reloaded just because the
    // opponent locked theirs. Waiting pages advance on the next revision; all
    // pages advance when the shared phase, turn, or terminal state changes.
    $refresh = $settled || $phase === 'complete' || $phase !== $clientPhase || $turn !== $clientTurn
        || ($clientWaiting && $revision > $clientRevision);
    echo json_encode([
        'ok'=>true,
        'match'=>$battleId,
        'revision'=>$revision,
        'phase'=>$phase,
        'turn'=>$turn,
        'refresh'=>$refresh,
        'settled'=>$settled,
        'updated_at'=>max(0, (int)($state['updated_at'] ?? 0)),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    pv_log('Live Battle state poll: ' . $e->getMessage());
    http_response_code(409);
    echo json_encode(['ok'=>false,'refresh'=>true,'stale'=>true]);
}
