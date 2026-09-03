<?php
include(__DIR__ . '/../kick.php');
session_start();
if(empty($_SESSION['myid'])){
	include(__DIR__ . '/../pv_disconnect_from_db.php');
	pv_redirect('login.php?goawayxP=1');
exit();
}
if($_SESSION['access'] == 9){
	include(__DIR__ . '/../pv_connect_to_db.php');
	$time = time();
	mysql_query("UPDATE members SET time = '$time' WHERE id = '{$_SESSION['myid']}'");
	if($_REQUEST['x'] && $_REQUEST['y']){
		$se = mysql_query("SELECT * FROM mapusers WHERE map = '{$_SESSION['map']}' AND x = '{$_REQUEST['x']}' AND y = '{$_REQUEST['y']}'");
		$s2 = mysql_num_rows($se);
		if($s2 == 0){
			echo "<p>Sorry, there on no members on this part of the map at this time.</p>";
		}
		else {
			echo "<table style=\"width: 100%;\" border=\"0\" cellpadding=\"3\" cellspacing=\"0\">";
			?>
            <tr>
            <th style="width: 35%;text-align:left;">Username</th>
            <th style="width: 65%;">Options</th>
            </tr>
			<?php
            while($s = mysql_fetch_array($se)){
				echo "<tr class=\"dark\" nowrap=\"nowrap\"><td style=\"text-align: left;\"><strong><a href=\"members.php?uid=" . $s['id'] . "\" onclick=\"membersTab('uid=" . $s['id'] . "', 1); return false;\">" . $s['username'] . "</a></strong></td><td style=\"width: 25%;\"><a href=\"/battle2.php?bid=" . $s['id'] . "\">Battle</a> | <a href=\"message.php?rid=" . $s['id'] . "\">Message</a> | <a href=\"trade.php?type=Username&amp;input=" . $s['username'] . "&amp;search=1\">View Trades</a></td></tr>";
			}
			echo "</table>";
		}
	}
}
else {
	include(__DIR__ . '/../pv_disconnect_from_db.php');
	pv_redirect('login.php?goawayxP=1');
}
?>