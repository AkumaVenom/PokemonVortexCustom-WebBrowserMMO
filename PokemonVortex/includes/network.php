<?php
declare(strict_types=1);
require_once __DIR__ . '/gameplay.php';

function pv_network_user(mysqli $db,int $uid): ?array
{
    $stmt=$db->prepare('SELECT id,username,clan_name,clan_tag,money,battle,losses,points,uniques,total_poke,totalexp,messonoff FROM members WHERE id=? LIMIT 1');
    if(!$stmt)return null;$stmt->bind_param('i',$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();return $row?:null;
}

function pv_network_find_user(mysqli $db,string $username): ?array
{
    $username=trim($username);if($username==='')return null;
    $stmt=$db->prepare('SELECT id,username,clan_name,clan_tag,battle,losses,points,uniques,total_poke,totalexp,messonoff FROM members WHERE username=? LIMIT 1');
    if(!$stmt)return null;$stmt->bind_param('s',$username);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();return $row?:null;
}

function pv_network_is_blocked(mysqli $db,int $a,int $b): bool
{
    $stmt=$db->prepare('SELECT 1 FROM trainer_blocks WHERE (user_id=? AND blocked_user_id=?) OR (user_id=? AND blocked_user_id=?) LIMIT 1');
    if(!$stmt)return false;$stmt->bind_param('iiii',$a,$b,$b,$a);$stmt->execute();$yes=(bool)$stmt->get_result()->fetch_row();$stmt->close();return $yes;
}

function pv_network_are_friends(mysqli $db,int $a,int $b): bool
{
    $stmt=$db->prepare('SELECT 1 FROM trainer_friends WHERE user_id=? AND friend_id=? LIMIT 1');if(!$stmt)return false;$stmt->bind_param('ii',$a,$b);$stmt->execute();$yes=(bool)$stmt->get_result()->fetch_row();$stmt->close();return $yes;
}

function pv_network_friend_request(mysqli $db, int $uid, int $target): void
{
    if ($uid === $target || $target <= 0) throw new RuntimeException('Choose another trainer.');
    $db->begin_transaction();
    try {
        pv_console_lock_social_pair($db, $uid, $target);
        if (pv_network_is_blocked($db, $uid, $target)) throw new RuntimeException('A block is active between these trainers.');
        if (pv_network_are_friends($db, $uid, $target)) throw new RuntimeException('You are already friends with that trainer.');
        $stmt = $db->prepare("SELECT id FROM friend_requests WHERE status='pending' AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) LIMIT 1 FOR UPDATE");
        if (!$stmt) throw new RuntimeException('Friend requests could not be checked.');
        $stmt->bind_param('iiii', $uid, $target, $target, $uid);
        $stmt->execute();
        $existing = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if ($existing) throw new RuntimeException('A friend request is already pending between these trainers.');
        $now = time();
        $stmt = $db->prepare("INSERT INTO friend_requests (sender_id,receiver_id,status,created_at,resolved_at) VALUES (?,?,'pending',?,0)");
        if (!$stmt) throw new RuntimeException('The friend request could not be created.');
        $stmt->bind_param('iii', $uid, $target, $now);
        if (!$stmt->execute()) throw new RuntimeException('The friend request could not be created.');
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof RuntimeException) throw $e;
        pv_log('Friend request creation failure: '.$e->getMessage());
        throw new RuntimeException('The friend request could not be created. Please try again.');
    }
    pv_server_event('SOCIAL', 'Friend request sent', ['target_uid'=>$target]);
}

function pv_network_resolve_friend_request(mysqli $db,int $uid,int $requestId,string $decision): void
{
    if(!in_array($decision,['accepted','declined'],true))throw new RuntimeException('Choose a valid friend-request action.');
    $stmt=$db->prepare('SELECT sender_id FROM friend_requests WHERE id=? AND receiver_id=? LIMIT 1');
    if(!$stmt)throw new RuntimeException('The friend request could not be loaded.');
    $stmt->bind_param('ii',$requestId,$uid);$stmt->execute();$initial=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$initial)throw new RuntimeException('That friend request could not be found.');
    $db->begin_transaction();
    try{
        pv_console_lock_social_pair($db,$uid,(int)$initial['sender_id']);
        $stmt=$db->prepare("SELECT sender_id,receiver_id,status FROM friend_requests WHERE id=? AND receiver_id=? FOR UPDATE");if(!$stmt)throw new RuntimeException('The friend request could not be loaded.');$stmt->bind_param('ii',$requestId,$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$row||$row['status']!=='pending')throw new RuntimeException('That friend request is no longer pending.');
        $sender=(int)$row['sender_id'];if(pv_network_is_blocked($db,$uid,$sender))$decision='declined';$now=time();
        $stmt=$db->prepare('UPDATE friend_requests SET status=?,resolved_at=? WHERE id=? AND status=\'pending\'');if(!$stmt)throw new RuntimeException('The friend request could not be updated.');$stmt->bind_param('sii',$decision,$now,$requestId);$stmt->execute();$stmt->close();
        if($decision==='accepted'){
            $stmt=$db->prepare('INSERT IGNORE INTO trainer_friends (user_id,friend_id,created_at) VALUES (?,?,?),(?,?,?)');if(!$stmt)throw new RuntimeException('The friendship could not be saved.');$stmt->bind_param('iiiiii',$uid,$sender,$now,$sender,$uid,$now);$stmt->execute();$stmt->close();
        }
        $db->commit();
        pv_server_event('SOCIAL','Friend request resolved',['request_id'=>$requestId,'sender_uid'=>$sender,'decision'=>$decision]);
    }catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;pv_log('Friend request resolution failure: '.$e->getMessage());throw new RuntimeException('The friend request could not be updated. Please try again.');}
}

function pv_network_remove_friend(mysqli $db, int $uid, int $friendId): void
{
    $db->begin_transaction();
    try {
        pv_console_lock_social_pair($db, $uid, $friendId);
        $stmt = $db->prepare('DELETE FROM trainer_friends WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)');
        if (!$stmt) throw new RuntimeException('The friendship could not be updated.');
        $stmt->bind_param('iiii', $uid, $friendId, $friendId, $uid);
        if (!$stmt->execute()) throw new RuntimeException('The friendship could not be updated.');
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof RuntimeException) throw $e;
        throw new RuntimeException('The friendship could not be updated. Please try again.');
    }
    pv_server_event('SOCIAL', 'Friend removed', ['friend_uid'=>$friendId]);
}

function pv_network_block(mysqli $db,int $uid,int $target): void
{
    if($target<=0||$target===$uid)throw new RuntimeException('Choose another trainer.');
    $db->begin_transaction();
    try{
        pv_console_lock_social_pair($db,$uid,$target);
        $now=time();$stmt=$db->prepare('INSERT IGNORE INTO trainer_blocks (user_id,blocked_user_id,created_at) VALUES (?,?,?)');if(!$stmt)throw new RuntimeException('The block could not be saved.');$stmt->bind_param('iii',$uid,$target,$now);$stmt->execute();$stmt->close();
        $stmt=$db->prepare('DELETE FROM trainer_friends WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)');if($stmt){$stmt->bind_param('iiii',$uid,$target,$target,$uid);$stmt->execute();$stmt->close();}
        $stmt=$db->prepare("UPDATE friend_requests SET status='declined',resolved_at=? WHERE status='pending' AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))");if($stmt){$stmt->bind_param('iiiii',$now,$uid,$target,$target,$uid);$stmt->execute();$stmt->close();}
        $db->commit();
        pv_server_event('SOCIAL','Trainer blocked',['target_uid'=>$target]);
    }catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('That trainer could not be blocked. Please try again.');}
}

function pv_network_unblock(mysqli $db,int $uid,int $target): void
{
    $stmt=$db->prepare('DELETE FROM trainer_blocks WHERE user_id=? AND blocked_user_id=?');if(!$stmt)throw new RuntimeException('The block could not be removed.');$stmt->bind_param('ii',$uid,$target);$stmt->execute();$stmt->close();pv_server_event('SOCIAL','Trainer unblocked',['target_uid'=>$target]);
}

function pv_network_send_message(mysqli $db,int $uid,int $receiverId,string $subject,string $body): int
{
    $subject=trim($subject);$body=trim($body);
    if($receiverId<=0||$receiverId===$uid)throw new RuntimeException('Choose another trainer as the recipient.');
    if($subject===''||mb_strlen($subject)>120)throw new RuntimeException('Enter a subject up to 120 characters.');
    if($body===''||mb_strlen($body)>4000)throw new RuntimeException('Enter a message up to 4,000 characters.');
    $receiver=pv_network_user($db,$receiverId);if(!$receiver)throw new RuntimeException('That trainer could not be found.');
    if((int)($receiver['messonoff']??1)===0)throw new RuntimeException('That trainer is not accepting private messages.');
    if(pv_network_is_blocked($db,$uid,$receiverId))throw new RuntimeException('Messages cannot be exchanged while a block is active.');
    $cutoff=time()-10;$stmt=$db->prepare('SELECT id FROM trainer_messages WHERE sender_id=? AND created_at>? ORDER BY id DESC LIMIT 1');if($stmt){$stmt->bind_param('ii',$uid,$cutoff);$stmt->execute();$recent=(bool)$stmt->get_result()->fetch_row();$stmt->close();if($recent)throw new RuntimeException('Please wait a few seconds before sending another message.');}
    $now=time();$stmt=$db->prepare('INSERT INTO trainer_messages (sender_id,receiver_id,subject,body,created_at,read_at,sender_deleted,receiver_deleted) VALUES (?,?,?,?,?,0,0,0)');if(!$stmt)throw new RuntimeException('The message could not be sent.');$stmt->bind_param('iissi',$uid,$receiverId,$subject,$body,$now);if(!$stmt->execute()){ $stmt->close();throw new RuntimeException('The message could not be sent.');}$id=(int)$db->insert_id;$stmt->close();pv_server_event('SOCIAL','Private message sent',['message_id'=>$id,'receiver_uid'=>$receiverId]);return $id;
}

function pv_network_message(mysqli $db,int $uid,int $messageId): ?array
{
    $stmt=$db->prepare('SELECT m.*,s.username sender_name,r.username receiver_name FROM trainer_messages m JOIN members s ON s.id=m.sender_id JOIN members r ON r.id=m.receiver_id WHERE m.id=? AND ((m.sender_id=? AND m.sender_deleted=0) OR (m.receiver_id=? AND m.receiver_deleted=0)) LIMIT 1');if(!$stmt)return null;$stmt->bind_param('iii',$messageId,$uid,$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$row)return null;
    if((int)$row['receiver_id']===$uid && (int)$row['read_at']===0){$now=time();$stmt=$db->prepare('UPDATE trainer_messages SET read_at=? WHERE id=? AND receiver_id=? AND read_at=0');if($stmt){$stmt->bind_param('iii',$now,$messageId,$uid);$stmt->execute();$stmt->close();}$row['read_at']=$now;}
    return $row;
}

function pv_network_delete_message(mysqli $db,int $uid,int $messageId): void
{
    $stmt=$db->prepare('UPDATE trainer_messages SET receiver_deleted=IF(receiver_id=?,1,receiver_deleted),sender_deleted=IF(sender_id=?,1,sender_deleted) WHERE id=? AND (receiver_id=? OR sender_id=?)');if(!$stmt)throw new RuntimeException('The message could not be removed.');$stmt->bind_param('iiiii',$uid,$uid,$messageId,$uid,$uid);$stmt->execute();$stmt->close();pv_server_event('SOCIAL','Private message removed',['message_id'=>$messageId]);
}

function pv_clan_current(mysqli $db,int $uid): ?array
{
    $stmt=$db->prepare('SELECT cm.clan_id,cm.owner is_owner,c.id,c.name,c.tag,c.motto,c.points,c.exp,c.wins,c.losses,c.members,c.owner owner_name FROM clan_members cm JOIN clans c ON c.id=cm.clan_id WHERE cm.id=? LIMIT 1');if(!$stmt)return null;$stmt->bind_param('i',$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();return $row?:null;
}

function pv_clan_create(mysqli $db,int $uid,string $name,string $tag,string $motto): int
{
    $name=trim($name);$tag=strtoupper(trim($tag));$motto=trim($motto);
    if(mb_strlen($name)<3||mb_strlen($name)>45)throw new RuntimeException('Clan names must be between 3 and 45 characters.');
    if(!preg_match('/^[A-Z0-9]{2,6}$/D',$tag))throw new RuntimeException('Clan tags must contain 2–6 letters or numbers.');
    if(mb_strlen($motto)>180)throw new RuntimeException('Clan mottos may contain up to 180 characters.');
    if(pv_clan_current($db,$uid))throw new RuntimeException('Leave your current clan before creating another one.');
    $user=pv_network_user($db,$uid);if(!$user)throw new RuntimeException('Trainer account could not be loaded.');$username=(string)$user['username'];
    $db->begin_transaction();
    try{
        $stmt=$db->prepare('SELECT id FROM clans WHERE name=? OR tag=? LIMIT 1 FOR UPDATE');if(!$stmt)throw new RuntimeException('Clan registry could not be checked.');$stmt->bind_param('ss',$name,$tag);$stmt->execute();$exists=(bool)$stmt->get_result()->fetch_row();$stmt->close();if($exists)throw new RuntimeException('That clan name or tag is already in use.');
        $approved=1;$members=1;$zero=0;$stmt=$db->prepare('INSERT INTO clans (name,owner,tag,motto,approved,points,exp,wins,losses,members) VALUES (?,?,?,?,?,0,0,0,0,?)');if(!$stmt)throw new RuntimeException('The clan could not be created.');$stmt->bind_param('ssssii',$name,$username,$tag,$motto,$approved,$members);if(!$stmt->execute()){ $stmt->close();throw new RuntimeException('The clan could not be created.');}$clanId=(int)$db->insert_id;$stmt->close();
        $ownerFlag=1;$stmt=$db->prepare('INSERT INTO clan_members (id,username,clan,clan_name,clan_id,owner,exp) VALUES (?,?,?,?,?,?,?)');if(!$stmt)throw new RuntimeException('Clan membership could not be created.');$exp=(int)$user['totalexp'];$stmt->bind_param('isssiii',$uid,$username,$name,$name,$clanId,$ownerFlag,$exp);$stmt->execute();$stmt->close();
        $stmt=$db->prepare('UPDATE members SET clan_name=?,clan_tag=? WHERE id=?');if(!$stmt)throw new RuntimeException('Trainer clan status could not be saved.');$stmt->bind_param('ssi',$name,$tag,$uid);$stmt->execute();$stmt->close();
        $db->commit();pv_server_event('CLAN','Clan created',['clan_id'=>$clanId,'name'=>$name,'tag'=>$tag]);return $clanId;
    }catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;pv_log('Clan create failure: '.$e->getMessage());throw new RuntimeException('The clan could not be created. Please try again.');}
}

function pv_clan_apply(mysqli $db,int $uid,int $clanId): void
{
    if($clanId<=0)throw new RuntimeException('Choose a clan.');if(pv_clan_current($db,$uid))throw new RuntimeException('Leave your current clan before applying to another one.');
    $stmt=$db->prepare('SELECT id FROM clans WHERE id=? LIMIT 1');if(!$stmt)throw new RuntimeException('Clan registry is unavailable.');$stmt->bind_param('i',$clanId);$stmt->execute();$exists=(bool)$stmt->get_result()->fetch_row();$stmt->close();if(!$exists)throw new RuntimeException('That clan no longer exists.');
    $now=time();$stmt=$db->prepare("SELECT id FROM clan_applications WHERE clan_id=? AND user_id=? AND status='pending' LIMIT 1");if($stmt){$stmt->bind_param('ii',$clanId,$uid);$stmt->execute();$pending=(bool)$stmt->get_result()->fetch_row();$stmt->close();if($pending)throw new RuntimeException('You already have a pending application to that clan.');}
    $stmt=$db->prepare("INSERT INTO clan_applications (clan_id,user_id,status,created_at,resolved_at) VALUES (?,?,'pending',?,0)");if(!$stmt)throw new RuntimeException('The clan application could not be submitted.');$stmt->bind_param('iii',$clanId,$uid,$now);$stmt->execute();$stmt->close();pv_server_event('CLAN','Clan application submitted',['clan_id'=>$clanId]);
}

function pv_clan_resolve_application(mysqli $db,int $leaderId,int $applicationId,string $decision): void
{
    if(!in_array($decision,['accepted','declined'],true))throw new RuntimeException('Choose a valid clan application action.');$clan=pv_clan_current($db,$leaderId);if(!$clan||!(int)$clan['is_owner'])throw new RuntimeException('Only the clan leader can review applications.');$clanId=(int)$clan['clan_id'];
    $db->begin_transaction();
    try{
        $stmt=$db->prepare("SELECT user_id,status FROM clan_applications WHERE id=? AND clan_id=? FOR UPDATE");if(!$stmt)throw new RuntimeException('The application could not be loaded.');$stmt->bind_param('ii',$applicationId,$clanId);$stmt->execute();$app=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$app||$app['status']!=='pending')throw new RuntimeException('That application is no longer pending.');$applicant=(int)$app['user_id'];
        if($decision==='accepted'){
            if(pv_clan_current($db,$applicant))throw new RuntimeException('That trainer has already joined a clan.');$u=pv_network_user($db,$applicant);if(!$u)throw new RuntimeException('The applicant account no longer exists.');$name=(string)$clan['name'];$tag=(string)$clan['tag'];$username=(string)$u['username'];$exp=(int)$u['totalexp'];$owner=0;
            $stmt=$db->prepare('INSERT INTO clan_members (id,username,clan,clan_name,clan_id,owner,exp) VALUES (?,?,?,?,?,?,?)');if(!$stmt)throw new RuntimeException('Clan membership could not be saved.');$stmt->bind_param('isssiii',$applicant,$username,$name,$name,$clanId,$owner,$exp);$stmt->execute();$stmt->close();
            $stmt=$db->prepare('UPDATE members SET clan_name=?,clan_tag=? WHERE id=?');if(!$stmt)throw new RuntimeException('Trainer clan status could not be updated.');$stmt->bind_param('ssi',$name,$tag,$applicant);$stmt->execute();$stmt->close();
        }
        $now=time();$stmt=$db->prepare('UPDATE clan_applications SET status=?,resolved_at=? WHERE id=?');if(!$stmt)throw new RuntimeException('The application could not be resolved.');$stmt->bind_param('sii',$decision,$now,$applicationId);$stmt->execute();$stmt->close();
        $db->commit();pv_server_event('CLAN','Clan application resolved',['application_id'=>$applicationId,'applicant_uid'=>$applicant,'decision'=>$decision,'clan'=>(string)$clan['name']]);pv_recalculate_clan_progress($db,(string)$clan['name']);
    }catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;pv_log('Clan application failure: '.$e->getMessage());throw new RuntimeException('The clan request could not be updated. Please try again.');}
}

function pv_clan_leave(mysqli $db,int $uid): void
{
    $clan=pv_clan_current($db,$uid);if(!$clan)throw new RuntimeException('You are not currently in a clan.');if((int)$clan['is_owner'])throw new RuntimeException('Transfer leadership or disband the clan before leaving.');$name=(string)$clan['name'];
    $db->begin_transaction();try{$stmt=$db->prepare('DELETE FROM clan_members WHERE id=?');if(!$stmt)throw new RuntimeException('Clan membership could not be removed.');$stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();$stmt=$db->prepare("UPDATE members SET clan_name='',clan_tag='' WHERE id=?");if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();}$db->commit();pv_server_event('CLAN','Trainer left clan',['clan'=>$name]);pv_recalculate_clan_progress($db,$name);}catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('You could not leave the clan. Please try again.');}
}

function pv_clan_remove_member(mysqli $db,int $leaderId,int $memberId): void
{
    $clan=pv_clan_current($db,$leaderId);if(!$clan||!(int)$clan['is_owner'])throw new RuntimeException('Only the clan leader can remove members.');if($memberId===$leaderId)throw new RuntimeException('Transfer leadership or disband the clan instead.');$clanId=(int)$clan['clan_id'];$name=(string)$clan['name'];
    $db->begin_transaction();try{$stmt=$db->prepare('DELETE FROM clan_members WHERE id=? AND clan_id=? AND owner=0');if(!$stmt)throw new RuntimeException('The clan member could not be removed.');$stmt->bind_param('ii',$memberId,$clanId);$stmt->execute();$ok=$stmt->affected_rows===1;$stmt->close();if(!$ok)throw new RuntimeException('That trainer is no longer a removable member of this clan.');$stmt=$db->prepare("UPDATE members SET clan_name='',clan_tag='' WHERE id=?");if($stmt){$stmt->bind_param('i',$memberId);$stmt->execute();$stmt->close();}$db->commit();pv_server_event('CLAN','Clan member removed',['member_uid'=>$memberId,'clan'=>$name]);pv_recalculate_clan_progress($db,$name);}catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('That member could not be removed. Please try again.');}
}

function pv_clan_transfer(mysqli $db,int $leaderId,int $memberId): void
{
    $clan=pv_clan_current($db,$leaderId);if(!$clan||!(int)$clan['is_owner'])throw new RuntimeException('Only the clan leader can transfer leadership.');if($memberId===$leaderId)throw new RuntimeException('Choose another clan member.');$clanId=(int)$clan['clan_id'];
    $db->begin_transaction();try{$stmt=$db->prepare('SELECT username FROM clan_members WHERE id=? AND clan_id=? AND owner=0 FOR UPDATE');if(!$stmt)throw new RuntimeException('The clan member could not be verified.');$stmt->bind_param('ii',$memberId,$clanId);$stmt->execute();$m=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$m)throw new RuntimeException('Choose a current clan member.');$stmt=$db->prepare('UPDATE clan_members SET owner=CASE WHEN id=? THEN 1 WHEN id=? THEN 0 ELSE owner END WHERE clan_id=?');if(!$stmt)throw new RuntimeException('Leadership could not be transferred.');$stmt->bind_param('iii',$memberId,$leaderId,$clanId);$stmt->execute();$stmt->close();$newOwner=(string)$m['username'];$stmt=$db->prepare('UPDATE clans SET owner=? WHERE id=?');if(!$stmt)throw new RuntimeException('Clan leadership could not be saved.');$stmt->bind_param('si',$newOwner,$clanId);$stmt->execute();$stmt->close();$db->commit();pv_server_event('CLAN','Clan leadership transferred',['clan_id'=>$clanId,'new_owner_uid'=>$memberId,'new_owner'=>$newOwner]);}catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('Leadership could not be transferred. Please try again.');}
}

function pv_clan_disband(mysqli $db,int $leaderId): void
{
    $clan=pv_clan_current($db,$leaderId);if(!$clan||!(int)$clan['is_owner'])throw new RuntimeException('Only the clan leader can disband the clan.');$clanId=(int)$clan['clan_id'];
    $db->begin_transaction();try{$stmt=$db->prepare('SELECT id FROM clan_members WHERE clan_id=? FOR UPDATE');if(!$stmt)throw new RuntimeException('Clan membership could not be locked.');$stmt->bind_param('i',$clanId);$stmt->execute();$r=$stmt->get_result();$ids=[];while($row=$r->fetch_assoc())$ids[]=(int)$row['id'];$stmt->close();foreach($ids as $id){$stmt=$db->prepare("UPDATE members SET clan_name='',clan_tag='' WHERE id=?");if($stmt){$stmt->bind_param('i',$id);$stmt->execute();$stmt->close();}}$stmt=$db->prepare('DELETE FROM clan_applications WHERE clan_id=?');if($stmt){$stmt->bind_param('i',$clanId);$stmt->execute();$stmt->close();}$stmt=$db->prepare('DELETE FROM clan_members WHERE clan_id=?');if($stmt){$stmt->bind_param('i',$clanId);$stmt->execute();$stmt->close();}$stmt=$db->prepare('DELETE FROM clans WHERE id=?');if(!$stmt)throw new RuntimeException('The clan could not be disbanded.');$stmt->bind_param('i',$clanId);$stmt->execute();$stmt->close();$db->commit();pv_server_event('CLAN','Clan disbanded',['clan_id'=>$clanId]);}catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The clan could not be disbanded. Please try again.');}
}
