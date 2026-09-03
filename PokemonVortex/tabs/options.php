<?php
include(__DIR__ . '/../kick.php');

if(!$_SESSION['myid'] || $_SESSION['access'] != 9){ //Check the user is logged in
	include('pv_disconnect_from_db.php');
	echo 'Your session has expired, please log back in.';
	exit();
}
if($_SESSION['access'] == 9){
	include(__DIR__ . '/../pv_connect_to_db.php');
	$time = time();
	
	//Reset the users Sidequests
	
	if($_REQUEST['sidequest'] == 'reset'){
		$_SESSION['sidequest'] = 1;
		$si = mysql_query("UPDATE members SET sidequest = '1' WHERE id = '{$_SESSION['myid']}'");
	}
	
	//Find the users of the selected maps
	
	if($_REQUEST['map'] && $_REQUEST['id'] && is_numeric($_REQUEST['map'])){
		$se = mysql_query("SELECT id, username FROM mapusers WHERE map = '{$_REQUEST['map']}' AND id != '{$_SESSION['myid']}'");
		$s2 = mysql_num_rows($se);
		echo "<h5>Members on Map " . $_REQUEST['map'] . "</h5>";
		echo '<div class="list autowidth"><table border="0" cellspacing="0" cellpadding="3" style="width: 100%;"><tr><th style="width:45%;">Username</th><th style="width:55%;">Options</th></tr>';
		if($s2 == 0){
			echo "<p>Sorry, there are no other members on this map at this time, besides yourself.</p>";
		}
		else {
			
			//Display the map users
			
			while($s = mysql_fetch_array($se)){
				echo "<tr class=\"dark\" nowrap=\"nowrap\">
				<td style=\"text-align: left;\"><strong>
				<a href=\"members.php?uid=" . $s['id'] . "\" onclick=\"membersTab('uid=" . $s['id'] . "', 1); return false;\">" . $s['username'] . "</a></strong></td><td style=\"width: 25%;\"><a href=\"/battle.php?bid=" . $s['id'] . "\">Battle</a> | <a href=\"message.php?rid=" . $s['id'] . "\">Message</a> | <a href=\"trade.php?type=Username&input=" . $s['username'] . "&search=1\">View Trades</a>
				</td></tr>";
			}
		}
		echo "</table></div>";
	}
	else {
		
		//Map display members options
		
		if($_REQUEST['map'] == 'memberson' || $_REQUEST['map'] == 'membersoff'){
			if($_REQUEST['map'] == 'membersoff'){
				$nt = 0;
			}
			else {
				$nt = 1;
			}
			$_SESSION['map_preferences'][1] = $nt;
			$a = mysql_query("UPDATE members_options SET memonmap ='$nt' WHERE id = '{$_SESSION['myid']}'");
		}
		
		//Change time of day on the maps
		
		if($_REQUEST['maps'] == 'day' || $_REQUEST['maps'] == 'night'){
			if($_REQUEST['maps'] == 'day'){
				$mtd = $_SESSION['night'] = 0;
				echo'<meta http-equiv="Refresh" content="0">';
			}
			elseif($_REQUEST['maps'] == 'night'){
				$mtn = $_SESSION['night'] = 1;
				echo'<meta http-equiv="Refresh" content="0">';
			}
		}
		
		//Message notification options
		
		if($_REQUEST['msg_notify']){
			$nug = '0';
			if($_REQUEST['msg_notify'] == 'off'){
				$nug = '1';
			}
			$a = mysql_query("UPDATE members_options SET messnotifyonoff = '$nug' WHERE id = '{$_SESSION['myid']}'");
			$_SESSION['message_preferences'][0] = $nug;
		}
		
		//Turn users messages on or off
		
		if($_REQUEST['messages']){
			$qz = 0;
			if($_REQUEST['messages'] == 'off'){
				$qz = 1;
			}
			$a = mysql_query("UPDATE members_options SET messonoff = '$qz' WHERE id = '{$_SESSION['myid']}'");
		}
		
		//Layout options
		
		if($_REQUEST['layout']){
			
			/*
			Remove comments when red and blue layouts are ready to be functional again
			The layout choice will, however probably be removed since they just look a bit outdated now.
			
			Change users layout to blue
			
			if($_REQUEST['layout'] == 'blue' && $_SESSION['layout'] == '0'){
				$la = mysql_query("UPDATE members_options SET layout = '1' WHERE id = '{$_SESSION['myid']}'");
				$_SESSION['layout'] +=1;
			}
			elseif($_REQUEST['layout'] == 'blue' && $_SESSION['layout'] == '2'){
				$la = mysql_query("UPDATE members_options SET layout = '1' WHERE id = '{$_SESSION['myid']}'");
				$_SESSION['layout'] -=1;
			}
			
			//Change users layout to red
			
			elseif($_REQUEST['layout'] == 'red' && $_SESSION['layout'] == '1'){
				$la = mysql_query("UPDATE members_options SET layout = '0' WHERE id = '{$_SESSION['myid']}'");
				$_SESSION['layout'] -=1;
			}
			elseif($_REQUEST['layout'] == 'red' && $_SESSION['layout'] == '2'){
				$la = mysql_query("UPDATE members_options SET layout = '0' WHERE id = '{$_SESSION['myid']}'");
				$_SESSION['layout'] -=2;
			}
			*/
			
			//Change users layout to black
			
			if($_REQUEST['layout'] == 'black' && $_SESSION['layout'] == '0'){
				$la = mysql_query("UPDATE members_options SET layout = '2' WHERE id = '{$_SESSION['myid']}'");
				$_SESSION['layout'] +=2;
			}
			elseif($_REQUEST['layout'] == 'black' && $_SESSION['layout'] == '1'){
				$la = mysql_query("UPDATE members_options SET layout = '2' WHERE id = '{$_SESSION['myid']}'");
				$_SESSION['layout'] +=1;
			}
		}
		
		//Get users options for page display
		
		$mm = mysql_query("SELECT memonmap, messonoff, messnotifyonoff FROM members_options WHERE id = '{$_SESSION['myid']}'");
		$j = mysql_fetch_array($mm);
		$which = "deselected";
		$ahi = "selected";
		if((int)$j['memonmap'] === 1){
			$which = "selected";
			$ahi = "deselected";
		}
		$qw = 'deselected';
		$wq = 'selected';
		if($j['messonoff']==1){
			$qw = 'selected';
			$wq = 'deselected';
		}
		$rw ='deselected';
		$wr = 'selected';
		if($j['messnotifyonoff'] == 1){
			$rw ='selected';
			$wr = 'deselected';
		}
		$red = 'deselected';
		$blue = 'selected';
		$black = 'deselected';
		if($_SESSION['layout'] == '0'){
			$red = 'selected';
			$blue = 'deselected';
			$black = 'deselected';
		}
		elseif($_SESSION['layout'] == '2'){
			$red = 'deselected';
			$blue = 'deselected';
			$black = 'selected';
		}
		if($_SESSION['night'] == '1'){
			$day = 'deselected';
			$night = 'selected';
		}
		elseif($_SESSION['night'] == '0'){
			$day = 'selected';
			$night = 'deselected';
		}
		?>
        
        <center><h1>Options</h1></center>
<div class="noticeMsg">Please note that layout changing is currently unavailable and only the default black is available.</div>
        
		<?php if($a){
			echo '<div class="actionMsg">Profile Updated.</div>';
		}
		if($la){
			echo '<div class="actionMsg">Layout changed, please refresh the page.</div>';
		}
		if($si){
			echo '<div class="actionMsg">Your Sidequests have successfully been reset.</div>';
		}
		if($mtd){
			echo'<div class="actionMsg">Time of day for maps updated, please refresh the page.</div>';
		}
		if($mtn){
			echo'<div class="actionMsg">Time of day for maps updated, please refresh the page.</div>';
		}

		?>
        
        <ul class="optionsList">
        
        <!-- Display members on the maps setting -->
        
        <p style="font-size:13px;">Show members on map? <a class="<?=$which?>" href="options.php?map=memberson" onclick="optionsTab('map=memberson', 0); return false;">Yes</a> | <a class="<?=$ahi?>" href="options.php?map=membersoff" onclick="optionsTab('map=membersoff', 0); return false;">No</a></p>
        
        <!-- Turn messages on or off option -->
        
        <p style="font-size:13px;">Allow messages: <a href="/your_profile.php?messages=on" onclick="optionsTab('messages=on', 0); return false;" class="<?=$wq?>">On</a> | <a href="/your_profile.php?messages=off" onclick="optionsTab('messages=off', 0); return false;" class="<?=$qw?>">Off</a></p>
        
        <!-- Turn message notifications on or off option -->
        
        <p style="font-size:13px;">Show New message notifications: <a href="/your_profile.php?msg_notify=on" onclick="optionsTab('msg_notify=on', 0); return false;" class="<?=$wr?>">On</a> | <a href="/your_profile.php?msg_notify=off" onclick="optionsTab('msg_notify=off', 0); return false;" class="<?=$rw?>">Off</a></p>
        
        <!-- Change layout option -->
        
        <p style="font-size:13px;">Change Layout: <a href="your_profile.php?layout=blue" onclick="optionsTab('layout=blue', 0); return false;" class="<?=$blue?>"><s>Blue</s></a> | <a href="your_profile.php?layout=red" onclick="optionsTab('layout=red', 0); return false;" class="<?=$red?>"><s>Red</s></a> | <a href="your_profile.php?layout=black" onclick="optionsTab('layout=black', 0); return false;" class="<?=$black?>">Black</a></p>
        
        <!-- Change time of day for the maps -->
        
        <p style="font-size:13px;">Map Preference: <a class="<?=$day?>" href="options.php?maps=day" onclick="optionsTab('maps=day', 0); return false;">Day</a> | <a class="<?=$night?>" href="options.php?maps=night" onclick="optionsTab('maps=night', 0); return false;">Night</a></p>
        
        <!-- Edit user profile option -->
        
        <p style="font-size:13px;">Edit Profile: <a href="/your_profile.php?action=edit">Click Here</a>
        
        <!-- Restart sidequests option -->
        
        <p style="font-size:13px;">Reset Sidequests: <a href="/options.php?sidequest=reset" onclick="optionsTab('sidequest=reset', 0); return false;">Click Here</a></p>
        
        <!-- Historical third-party social embed removed. -->
        <p style="font-size:13px;">Trainer social features are available through the <a href="../community.php">Trainer Network</a>.</p>
        </ul>
	 <?php
	}
}
else {
	include('pv_disconnect_from_db.php');
	pv_redirect('login.php?goawayxP=1');
}
?>