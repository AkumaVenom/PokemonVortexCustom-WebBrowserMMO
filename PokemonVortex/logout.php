<?php
require_once __DIR__ . '/includes/bootstrap.php';
$uid=(int)($_SESSION['myid']??0);
if($uid>0){ try{$db=pv_db(); $stmt=$db->prepare('DELETE FROM online WHERE id=?');$stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();$stmt=$db->prepare('DELETE FROM mapusers WHERE id=?');$stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();}catch(Throwable $e){} }
$_SESSION=[];
if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']); }
session_destroy();
pv_redirect('login.php?action=Logout');
