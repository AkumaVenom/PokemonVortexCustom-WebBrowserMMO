<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/network.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();
$db=pv_db();$uid=(int)$_SESSION['myid'];

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $return='messages.php';
    try{
        pv_require_csrf();$action=trim((string)($_POST['action']??''));
        if($action==='send'){
            $receiver=max(0,(int)($_POST['receiver_id']??0));$subject=(string)($_POST['subject']??'');$body=(string)($_POST['body']??'');$id=pv_network_send_message($db,$uid,$receiver,$subject,$body);$_SESSION['pv_message_flash']=['type'=>'success','text'=>'Message sent successfully.'];$return='messages.php?view='.$id;
        }elseif($action==='delete'){
            $id=max(0,(int)($_POST['message_id']??0));pv_network_delete_message($db,$uid,$id);$_SESSION['pv_message_flash']=['type'=>'success','text'=>'Message removed from this mailbox.'];
        }else throw new RuntimeException('Choose a message action.');
    }catch(RuntimeException $e){$_SESSION['pv_message_flash']=['type'=>'error','text'=>$e->getMessage()];}
    pv_redirect($return);
}
$flash=$_SESSION['pv_message_flash']??null;unset($_SESSION['pv_message_flash']);
$box=trim((string)($_GET['box']??'inbox'));if(!in_array($box,['inbox','sent','compose'],true))$box='inbox';$viewId=max(0,(int)($_GET['view']??0));$message=$viewId?pv_network_message($db,$uid,$viewId):null;
$replyTo=max(0,(int)($_GET['reply']??0));$reply=$replyTo?pv_network_message($db,$uid,$replyTo):null;
$toName=trim((string)($_GET['to']??''));$recipient=$toName!==''?pv_network_find_user($db,$toName):null;
if($reply){$other=(int)$reply['sender_id']===$uid?(int)$reply['receiver_id']:(int)$reply['sender_id'];$recipient=pv_network_user($db,$other);$box='compose';}

$rows=[];$unread=0;
if($box!=='compose'){
    if($box==='inbox'){$sql='SELECT m.id,m.subject,m.created_at,m.read_at,s.username counterpart FROM trainer_messages m JOIN members s ON s.id=m.sender_id WHERE m.receiver_id=? AND m.receiver_deleted=0 ORDER BY m.id DESC LIMIT 100';}
    else{$sql='SELECT m.id,m.subject,m.created_at,m.read_at,r.username counterpart FROM trainer_messages m JOIN members r ON r.id=m.receiver_id WHERE m.sender_id=? AND m.sender_deleted=0 ORDER BY m.id DESC LIMIT 100';}
    $stmt=$db->prepare($sql);if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc()){$rows[]=$row;if($box==='inbox'&&(int)$row['read_at']===0)$unread++;}$stmt->close();}
}

pv_page_start('Messages','community.php',true);
?>
<div class="pv-game-layout"><?php pv_game_side_menu('messages.php');?><main class="pv-main-column"><section class="pv-page pv-messages-page">
<div class="pv-page-head"><div><span class="pv-eyebrow">TRAINER MAIL</span><h1>Messages</h1><p class="pv-subtle">Send private messages to other trainers and keep in touch with your friends.</p></div><a class="pv-button" href="<?=pv_h(pv_url('messages.php?box=compose'))?>">Compose</a></div>
<?php if(is_array($flash)):?><div class="pv-alert pv-alert-<?=pv_h((string)$flash['type'])?>"><?=pv_h((string)$flash['text'])?></div><?php endif;?>
<div class="pv-message-layout">
<nav class="pv-message-nav"><a class="<?=$box==='inbox'?'active':''?>" href="<?=pv_h(pv_url('messages.php?box=inbox'))?>"><span>Inbox</span><b><?=$unread?></b></a><a class="<?=$box==='sent'?'active':''?>" href="<?=pv_h(pv_url('messages.php?box=sent'))?>"><span>Sent</span></a><a class="<?=$box==='compose'?'active':''?>" href="<?=pv_h(pv_url('messages.php?box=compose'))?>"><span>Compose</span></a></nav>
<div class="pv-message-content">
<?php if($message):$isReceiver=(int)$message['receiver_id']===$uid;$counter=$isReceiver?(string)$message['sender_name']:(string)$message['receiver_name'];?>
<article class="pv-message-reader"><header><div><small><?=$isReceiver?'FROM':'TO'?> // <?=pv_h(strtoupper($counter))?></small><h2><?=pv_h((string)$message['subject'])?></h2><span><?=date('j M Y · H:i',(int)$message['created_at'])?> UTC</span></div><div class="pv-actions"><?php if($isReceiver):?><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('messages.php?reply='.(int)$message['id']))?>">Reply</a><?php endif;?><form method="post"><?=pv_csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="message_id" value="<?=(int)$message['id']?>"><button class="pv-button-danger" type="submit">Delete</button></form></div></header><div class="pv-message-body"><?=nl2br(pv_h((string)$message['body']))?></div></article>
<?php elseif($box==='compose'):
$replySubject=$reply?'Re: '.preg_replace('/^Re:\s*/i','',(string)$reply['subject']):'';?>
<form method="post" class="pv-message-compose"><?=pv_csrf_field()?><input type="hidden" name="action" value="send"><div class="pv-field"><label>Recipient</label><?php if($recipient):?><input type="hidden" name="receiver_id" value="<?=(int)$recipient['id']?>"><div class="pv-recipient-chip"><span><?=pv_h(strtoupper(mb_substr((string)$recipient['username'],0,1)))?></span><strong><?=pv_h((string)$recipient['username'])?></strong><a href="<?=pv_h(pv_url('messages.php?box=compose'))?>">Change</a></div><?php else:?><select name="receiver_id" required><option value="">Choose trainer</option><?php $stmt=$db->prepare('SELECT id,username FROM members WHERE id<>? ORDER BY username LIMIT 300');if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$r=$stmt->get_result();while($u=$r->fetch_assoc()):?><option value="<?=(int)$u['id']?>"><?=pv_h((string)$u['username'])?></option><?php endwhile;$stmt->close();}?></select><?php endif;?></div><div class="pv-field"><label>Subject <small>120 characters maximum</small></label><input name="subject" maxlength="120" value="<?=pv_h($replySubject)?>" required></div><div class="pv-field"><label>Message <small>4,000 characters maximum</small></label><textarea name="body" rows="12" maxlength="4000" required></textarea></div><div class="pv-actions"><button type="submit">Send Message</button><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('messages.php'))?>">Cancel</a></div></form>
<?php else:?>
<div class="pv-message-list-head"><strong><?=pv_h(ucfirst($box))?></strong><span><?=number_format(count($rows))?> recent messages</span></div><div class="pv-message-list"><?php if(!$rows):?><div class="pv-empty-state compact"><span class="pv-brand-mark">MSG</span><div><h2>No messages</h2><p><?=$box==='inbox'?'Your inbox is currently clear.':'No sent messages are currently stored.'?></p></div></div><?php else:foreach($rows as $row):?><a class="pv-message-row <?=($box==='inbox'&&(int)$row['read_at']===0)?'unread':''?>" href="<?=pv_h(pv_url('messages.php?view='.(int)$row['id']))?>"><i></i><div><small><?=$box==='inbox'?'FROM':'TO'?> <?=pv_h((string)$row['counterpart'])?></small><strong><?=pv_h((string)$row['subject'])?></strong></div><time><?=date('j M · H:i',(int)$row['created_at'])?></time><span>›</span></a><?php endforeach;endif;?></div>
<?php endif;?>
</div></div>
</section></main></div>
<?php pv_page_end(); ?>
