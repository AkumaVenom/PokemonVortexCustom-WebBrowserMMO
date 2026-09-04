<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bot_runtime.php';
require_once __DIR__ . '/includes/live_battle_runtime.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();

if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')pv_redirect('map_select.php');
pv_require_csrf();
if(!pv_consume_action_token('bot_live_battle.php',(string)($_POST['battle_action_token']??'')))pv_redirect('map_select.php');

$userId=max(0,(int)($_SESSION['myid']??0));
$botId=max(0,(int)($_POST['bot_id']??0));
if($userId<=0||$botId<=0||$userId===$botId)pv_redirect('map_select.php?status=unavailable');

try{
    $db=pv_db();
    $bot=pv_bot_profile($db,$botId);
    if(!$bot)throw new RuntimeException('That autonomous trainer is no longer available.');
    // Authoritative team hydration rejects stale slot ownership or an empty team
    // before an immutable match row is created.
    pv_live_runtime_member_team($db,$userId);
    pv_live_runtime_member_team($db,$botId);

    $now=time();
    $stmt=$db->prepare('INSERT INTO live_battle (uid_1,uid_2,created_at) VALUES (?,?,?)');
    if(!$stmt)throw new RuntimeException('The Live AI Battle session could not be prepared.');
    $stmt->bind_param('iii',$userId,$botId,$now);
    if(!$stmt->execute()){$error=$stmt->error;$stmt->close();throw new RuntimeException('The Live AI Battle session could not be created: '.$error);}
    $battleId=(int)$db->insert_id;$stmt->close();
    if($battleId<=0)throw new RuntimeException('The Live AI Battle session did not receive a valid id.');

    $_SESSION['live']=[0=>1,1=>2,3=>$botId,4=>$battleId];
    unset($_SESSION['pv_live_runtime_error']);
    pv_redirect('live_battle.php?battle=AI');
}catch(Throwable $e){
    pv_log('Live AI Battle creation: '.$e->getMessage());
    pv_redirect('bot_trainer.php?id='.$botId.'&error=live');
}
