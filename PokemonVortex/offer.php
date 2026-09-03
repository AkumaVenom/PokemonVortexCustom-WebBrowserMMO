<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/gameplay.php';
pv_require_login();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST') pv_redirect('trade.php');
pv_require_csrf();
$db=pv_db();
$uid=(int)$_SESSION['myid'];
$action=strtolower(trim((string)($_POST['action']??'')));
try{
    if($action==='remove_listing'){
        $pid=max(1,(int)($_POST['pid']??0));
        pv_trade_remove_listing($db,$pid,$uid);
        pv_redirect('trade.php?view=mine&notice=listing_removed');
    }
    $offerId=max(1,(int)($_POST['offer_id']??0));
    $result=pv_trade_resolve_offer($db,$offerId,$uid,$action);
    $notice=match($result['status']??''){
        'accepted'=>'offer_accepted',
        'declined'=>'offer_declined',
        'withdrawn'=>'offer_withdrawn',
        default=>''
    };
    $view=($action==='withdraw')?'sent':'received';
    pv_redirect('trade.php?view='.$view.($notice!==''?'&notice='.$notice:''));
}catch(RuntimeException $e){
    $_SESSION['trade_error']=$e->getMessage();
    if($action==='withdraw') pv_redirect('trade.php?view=sent&notice=error');
    if($action==='remove_listing') pv_redirect('trade.php?view=mine&notice=error');
    pv_redirect('trade.php?view=received&notice=error');
}
