<?php
require_once __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') pv_redirect('login.php');
if (!pv_verify_csrf()) pv_redirect('login.php?error=1');
$username = trim((string)($_POST['myusername'] ?? ''));
$password = (string)($_POST['mypassword'] ?? '');
if ($username === '' || $password === '') pv_redirect('login.php?error=1');

try { $db = pv_db(); } catch(Throwable $e) { pv_redirect('login.php?error=1'); }
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$now = time();
$cutoff = $now - 1800;
$db->query("DELETE FROM login_trys WHERE time < {$cutoff}");

$stmt=$db->prepare('SELECT attempts FROM login_trys WHERE ip=? LIMIT 1');
$stmt->bind_param('s',$ip); $stmt->execute(); $attemptRow=$stmt->get_result()->fetch_assoc(); $stmt->close();
if ((int)($attemptRow['attempts']??0) >= 6) pv_redirect('login.php?action=Attempts');

$stmt=$db->prepare('SELECT * FROM members WHERE username=? LIMIT 1');
$stmt->bind_param('s',$username); $stmt->execute(); $member=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$member || !pv_password_matches($password,(string)$member['password'])) {
    $stmt=$db->prepare('INSERT INTO login_trys (ip,username,time,attempts) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE username=VALUES(username),time=VALUES(time),attempts=attempts+1');
    $stmt->bind_param('ssi',$ip,$username,$now); $stmt->execute(); $stmt->close();
    pv_redirect('login.php?error=1');
}
if ((string)($member['banned']??'0') === '1') pv_redirect('login.php?action=Banned');

// Seamlessly upgrade old MD5 passwords the first time an old account signs in.
if (strlen((string)$member['password']) === 32 && ctype_xdigit((string)$member['password'])) {
    $newHash=pv_password_hash($password);
    $stmt=$db->prepare('UPDATE members SET password=? WHERE id=?'); $uid=(int)$member['id']; $stmt->bind_param('si',$newHash,$uid); $stmt->execute(); $stmt->close();
}

session_regenerate_id(true);
$uid=(int)$member['id'];
$settings=['layout'=>2,'messnotifyonoff'=>0,'memonmap'=>1,'trainer'=>1];
$stmt=$db->prepare('SELECT * FROM members_options WHERE id=? LIMIT 1'); $stmt->bind_param('i',$uid); $stmt->execute(); $r=$stmt->get_result()->fetch_assoc(); if($r)$settings=array_merge($settings,$r); $stmt->close();
$_SESSION['myuser']=$member['username']; $_SESSION['myid']=$uid; $_SESSION['myeb']=$member['eb']??'1'; $_SESSION['access']=9; $_SESSION['sidequest']=$member['sidequest']??0;
$_SESSION['message_preferences']=[(int)($settings['messnotifyonoff']??0)]; $_SESSION['layout']=2;
$_SESSION['map_preferences']=[(int)($member['badges']??0),(int)($settings['memonmap']??0),(int)($settings['trainer']??1)];
$_SESSION['my_team']=array_map('intval',[$member['s1']??0,$member['s2']??0,$member['s3']??0,$member['s4']??0,$member['s5']??0,$member['s6']??0]);
$_SESSION['clan']=$member['clan_name']??''; $_SESSION['clanowner']=0; $_SESSION['night']=0; $_SESSION['banned']=0; $_SESSION['your_pokemon']=[];
$stmt=$db->prepare('SELECT pid FROM pokemon WHERE owner=? GROUP BY pid'); $stmt->bind_param('i',$uid); $stmt->execute(); $r=$stmt->get_result(); while($row=$r->fetch_assoc()) $_SESSION['your_pokemon'][]=(int)$row['pid']; $stmt->close();
if (!empty($_SESSION['clan'])) { $stmt=$db->prepare('SELECT id FROM clans WHERE owner=? LIMIT 1'); $stmt->bind_param('s',$member['username']); $stmt->execute(); $_SESSION['clanowner']=$stmt->get_result()->num_rows?1:0; $stmt->close(); }
$useragent=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,250); $clantag=(string)($member['clan_tag']??'');
$stmt=$db->prepare("INSERT INTO online (id,username,clan_tag,activity,time,useragent,server) VALUES (?,?,?,'Dashboard',?,?,'local') ON DUPLICATE KEY UPDATE username=VALUES(username),clan_tag=VALUES(clan_tag),activity='Dashboard',time=VALUES(time),useragent=VALUES(useragent),server='local'");
$stmt->bind_param('issis',$uid,$member['username'],$clantag,$now,$useragent); $stmt->execute(); $stmt->close();
$stmt=$db->prepare('UPDATE members SET llogin=?, last_login=?, ip=?, time=? WHERE id=?'); $last=(string)$now; $stmt->bind_param('issii',$now,$last,$ip,$now,$uid); $stmt->execute(); $stmt->close();
$stmt=$db->prepare('DELETE FROM login_trys WHERE ip=?'); $stmt->bind_param('s',$ip); $stmt->execute(); $stmt->close();
pv_redirect('dashboard.php');
