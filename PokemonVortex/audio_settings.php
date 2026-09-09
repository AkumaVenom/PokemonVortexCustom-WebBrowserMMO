<?php
declare(strict_types=1);
define('PV_DISABLE_OUTPUT_FILTER', true);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/audio.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
function pv_audio_reply(int $code, array $data): never {
    $data['account'] = (int)($_SESSION['access'] ?? 0)===9 ? max(0,(int)($_SESSION['myid'] ?? 0)) : 0;
    $data['csrf'] = pv_csrf_token();
    http_response_code($code); echo json_encode($data,JSON_UNESCAPED_SLASHES); exit;
}
$uid = (int)($_SESSION['access'] ?? 0)===9 ? max(0,(int)($_SESSION['myid'] ?? 0)) : 0;
if (!$uid) pv_audio_reply(401,['ok'=>false,'error'=>'Sign in again to save sound settings.']);
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); pv_audio_reply(405,['ok'=>false,'error'=>'Unsupported request.']); }
if ($method === 'POST' && (!pv_verify_csrf() || !pv_same_origin_request())) pv_audio_reply(403,['ok'=>false,'error'=>'Refresh this page before saving sound settings.']);
if ($method === 'POST' && (!isset($_POST['expected_account']) || !is_string($_POST['expected_account'])
    || !preg_match('/^\d{1,12}$/D',$_POST['expected_account']) || (int)$_POST['expected_account']!==$uid)) {
    pv_audio_reply(409,['ok'=>false,'account_mismatch'=>true,'error'=>'Refresh the page for your current account.']);
}
try {
    $db = pv_db();
    if ($method === 'POST') {
        // Session identity owns the write; expected_account only rejects stale pages.
        foreach (['enabled','music','effects','revision'] as $field) {
            if (!isset($_POST[$field]) || !is_string($_POST[$field]) || !preg_match('/^\d{1,12}$/D',$_POST[$field])) pv_audio_reply(422,['ok'=>false,'error'=>'Invalid sound settings.']);
        }
        $enabled=(int)$_POST['enabled']; $music=(int)$_POST['music']; $effects=(int)$_POST['effects']; $revision=(int)$_POST['revision'];
        if ($enabled>1 || $music>100 || $effects>100) pv_audio_reply(422,['ok'=>false,'error'=>'Invalid sound settings.']);
        // Optimistic revision prevents old tabs from silently overwriting a newer choice.
        $stmt=$db->prepare('UPDATE members SET sound_enabled=?,music_volume=?,sfx_volume=?,audio_revision=audio_revision+1 WHERE id=? AND audio_revision=?');
        if (!$stmt) throw new RuntimeException('Sound settings unavailable.');
        $stmt->bind_param('iiiii',$enabled,$music,$effects,$uid,$revision);
        if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Sound settings could not be saved.'); }
        $changed=$stmt->affected_rows; $stmt->close();
        $prefs=pv_audio_preferences($db,$uid);
        if ($changed!==1) pv_audio_reply(409,['ok'=>false,'conflict'=>true,'preferences'=>$prefs,'error'=>'Sound settings changed in another tab.']);
        pv_audio_reply(200,['ok'=>true,'preferences'=>$prefs]);
    }
    pv_audio_reply(200,['ok'=>true,'preferences'=>pv_audio_preferences($db,$uid)]);
} catch (Throwable $e) {
    pv_log('Audio preference service: '.$e->getMessage());
    pv_audio_reply(503,['ok'=>false,'error'=>'Sound settings could not be saved. Try again after Upgrade / Repair.']);
}
