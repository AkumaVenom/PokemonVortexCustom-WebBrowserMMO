<!-- User Nav -->
<?php
$uid=(int)($_SESSION['myid']??0);
$ch = mysql_num_rows(mysql_query("SELECT id FROM flashchat_connections WHERE userid >= 1"));
$sm = mysql_num_rows(mysql_query("SELECT id FROM messages WHERE receiverid = '{$uid}' AND receiverdelete = '1' AND receiverread = '1'"));
$trd = mysql_num_rows(mysql_query("SELECT offers FROM upfortrade WHERE owner = '{$uid}' AND offers > 0"));
if((int)($_SESSION['clanowner']??0) === 1){
    $clan=mysql_real_escape_string((string)($_SESSION['clan']??''));
    $clanr = mysql_num_rows(mysql_query("SELECT id FROM clan_requests WHERE clan = '{$clan}'"));
} else {
    $clanr = mysql_num_rows(mysql_query("SELECT id FROM clan_requests WHERE id = '{$uid}'"));
}
$user=pv_h((string)($_SESSION['myuser']??'Trainer'));
?>
<div id="usernav">Logged in as: <a href="members.php?uid=<?=$uid?>" title="<?=$user?>"><?=$user?></a> · New Messages: <a href="messages.php"><?=$sm?></a> · Trade Offers: <a href="trade.php?cat=uft&amp;order=offers"><?=$trd?></a> · Clan Requests: <a href="clans.php?view=Requests"><?=$clanr?></a> · Chat Users: <a href="chat.php"><?=$ch?></a></div>
<!-- End User Nav -->
