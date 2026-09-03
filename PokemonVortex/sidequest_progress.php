<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/sidequest_catalog.php';
pv_require_login();
$db=pv_db();$uid=(int)$_SESSION['myid'];$current=1;
$stmt=$db->prepare('SELECT sidequest FROM members WHERE id=? LIMIT 1');
if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();$current=min(697,max(1,(int)($row['sidequest']??1)));}
$_SESSION['sidequest']=$current;$progress=pv_sidequest_progress_snapshot($current);$region=$progress['region'];
?>
<div class="pv-sidequest-progress" aria-label="Sidequest campaign progress">
  <strong><?=pv_h((string)($region['label']??'Sidequests'))?> Sidequests</strong>
  <div class="pv-progress"><span style="width:<?= (int)$progress['percent'] ?>%"></span></div>
  <small><?= (int)$progress['done'] ?> / <?= (int)$progress['total'] ?> battles</small>
</div>
