<?php
include(__DIR__ . '/../kick.php');
if(!$_SESSION['myid']){ // Check the user is logged in
	echo "<div class=\"errorMsg\">You are not logged in.</div>";
	exit();
}
include(__DIR__ . '/../pv_connect_to_db.php');
$time = time();
$_REQUEST['read'] = mysql_real_escape_string($_REQUEST['read']);
$_REQUEST['forwardsent'] = mysql_real_escape_string($_REQUEST['forwardsent']);
$_REQUEST['forward'] = mysql_real_escape_string($_REQUEST['forward']);
$_REQUEST['deletesent'] = mysql_real_escape_string($_REQUEST['deletesent']);
$_REQUEST['delete'] = mysql_real_escape_string($_REQUEST['delete']);
$_REQUEST['reply'] = mysql_real_escape_string($_REQUEST['reply']);
$_REQUEST['readsent'] = mysql_real_escape_string($_REQUEST['readsent']);


$option = $_REQUEST['option'];
if($_REQUEST['delete']){ // Users request to delete an inbox message
	$del = mysql_query("UPDATE messages SET receiverdelete = '0' WHERE id = '{$_REQUEST['delete']}' AND receiverid = '{$_SESSION['myid']}'");
	if($del){
		echo "<div class=\"actionMsg\">Message Deleted</div>";
	}
}


if($_REQUEST['deletesent']){ // Users request to delete a message they saved as sent
	$del = mysql_query("UPDATE messages SET senderdelete = '0' WHERE id = '{$_REQUEST['deletesent']}' AND senderid = '{$_SESSION['myid']}'");
	if($del){
		echo "<div class=\"actionMsg\">Message Deleted</div>";
	}
}
if($_REQUEST['forward']){ // Users request to forward a recieved message
	$l = mysql_query("SELECT * FROM messages WHERE receiverid = '{$_SESSION['myid']}' AND id = '{$_REQUEST['forward']}'");
	$ll = mysql_fetch_array($l);
	$ll['subject'] = stripslashes($ll['subject']);
	$ll['message'] = stripslashes($ll['message']);
	?>    
<div id="messageContent">
<div id="newMessage" style="height:300px;">
<form method="post" action="messages.php" onsubmit="return disableSubmitButton(this);return false;">
<table>
<tr>
<td>Username:</td>
<td><input type="text" id="messageusername" name="messageusername" size="30" value=""></td>
</tr>
<tr>
<td>Title:</td>
<td><input type="text" id="messagetitle" name="messagetitle" size="30" value="<?php echo $ll['subject'];?>"></td>
</tr>
<tr>
<td valign="top">Message:</td>
<td><textarea name="messagetext" id="messagetext" rows="10" cols="60"><?php echo $ll['message'];?></textarea></td>
</tr>
<tr>
<td>Save Message?</td>
<td><input type="checkbox" name="keep" id="keep" value="1"></td>
</tr>
<tr>
<td><input type="submit" value="Send" name="send"></td>
</tr>
</table>
</form>
</div>
          <?php
}

if($_REQUEST['forwardsent']){ // Users request to forward a recieved message
	$l = mysql_query("SELECT * FROM messages WHERE senderid = '{$_SESSION['myid']}' AND id = '{$_REQUEST['forwardsent']}'");
	$ll = mysql_fetch_array($l);
	$ll['subject'] = stripslashes($ll['subject']);
	$ll['message'] = stripslashes($ll['message']);
	?>
<div id="messageContent">
<div id="newMessage" style="height:300px;">
<form method="post" action="messages.php" onsubmit="return disableSubmitButton(this);return false;">
<table>
<tr>
<td>Username:</td>
<td><input type="text" id="messageusername" name="messageusername" size="30" value=""></td>
<tr>
<td>Title:</td>
<td><input type="text" id="messagetitle" name="messagetitle" size="30" value="<?php echo $ll['subject'];?>"></td>
</tr>
<tr>
<td valign="top">Message:</td>
<td><textarea name="messagetext" id="messagetext" rows="10" cols="60"><?php echo $ll['message'];?></textarea></td>
</tr>
<tr>
<td>Save Message?</td>
<td><input type="checkbox" name="keep" id="keep" value="1"></td>
</tr>
<tr>
<td><input type="submit" value="Send" name="send"></td>
</tr>
</table>
</form>
</div>
		  <?php
}

if($_REQUEST['reply']){ // Users request to reply to a recieved message
	$l = mysql_query("SELECT * FROM messages WHERE receiverid = '{$_SESSION['myid']}' AND id = '{$_REQUEST['reply']}'");
	$ll = mysql_fetch_array($l);
	$ll['subject'] = stripslashes($ll['subject']);
	$ll['message'] = stripslashes($ll['message']);
	$ll['senderusername'] = stripslashes($ll['senderusername']);
	?>
    
    
<div id="messageContent">
<div id="newMessage" style="height:300px;">
<form method="post" action="messages.php" onsubmit="return disableSubmitButton(this);return false;">
<table>
<tr>
<td>Username:</td>
<td><input type="text" id="messageusername" name="messageusername" size="30" value="<?php echo $ll['senderusername'];?>"></td>
<tr>
<td>Title:</td>
<td><input type="text" id="messagetitle" name="messagetitle" size="30" value="RE: <?php echo $ll['subject'];?>"></td>
</tr>
<tr>
<td valign="top">Message:</td>
<td><textarea name="messagetext" id="messagetext" rows="10" cols="60">
[quote]
<?php echo $ll['message'];?>
[/quote]
</textarea></td>
</tr>
<tr>
<td>Save Message?</td>
<td><input type="checkbox" name="keep" id="keep" value="1"></td>
</tr>
<tr>
<td><input type="submit" value="Send" name="send"></td>
</tr>
</table>
</form>
</div>
		  <?php
}

if($_REQUEST['readsent']){ // Users request to view sent messages
	$seant = mysql_query("SELECT * FROM messages WHERE senderid = '{$_SESSION['myid']}' AND senderdelete = '1' AND id = '{$_REQUEST['readsent']}'");
	mysql_query("UPDATE messages SET senderread = '0' WHERE senderid = '{$_SESSION['myid']}' AND id = '{$_REQUEST['readsent']}'");
	$seaa = mysql_fetch_array($seant);
	$date = date("jS F H:i",$seaa['time']);
	$sare = $seaa['message'];
	$codep = htmlentities($sare);
	$code = $codep;
	
	// Select words to filter and replace
	
	$replace = array("Damn","Cock","Dick","Bitch","Shit","Fuck","fuck","bitch","damn","shit","cock","dick","[u]","[/u]","[i]","[/i]","[b]","[/b]","[s]","[/s]","[sub]","[/sub]","[sup]","[/sup]","[quote]","[/quote]");
	
	// Select the string to replace the filtered words
	
	$with111 = array("****","****","****","*****","****","****","****","*****","****","****","****","****","<u>","</u>","<i>","</i>","<b>","</b>","<s>","</s>","<sub>","</sub>","<sup>","</sup>","<blockquote style=\"border: 2px solid #666666;background-color:#CCCCCC;padding:5px;\">","</blockquote>");
	
	$newcode = str_replace($replace, $with111, $code);
	
	echo "<div style=\"font-size:15px;\"><p style=\"float:left;\">{$seaa['subject']}</p><p style=\"float:right;font-weight: bold;color: #990000;\">$date</p></div><div style=\"border-top: 1px dotted #CCCCCC;clear: both;\"></div><p style=\"font-size:12px;\">{$newcode}</p>";
}

if($option){ // Users navigation choices
	if($option == 'inbox'){ // User requests inbox view
		$inbox1 = mysql_query("SELECT * FROM messages WHERE receiverid = '{$_SESSION['myid']}' AND receiverdelete = '1' ORDER BY time DESC");
		$incount = mysql_num_rows($inbox1); // Check if there are any messages to display...
		if($incount != '0'){
			
			// If there are messages to display...
			
			while($in = mysql_fetch_array($inbox1)){
				$senderuser = $in['senderusername'];
				$subject = $in['subject'];
				$style = 'dark';
				if($in['receiverread'] == 1){
					$style = 'highlight';
				}
				
				echo "<li id=\"open{$in['id']}\" class=\"" . $style . "\">From: <a href=\"members.php?uid={$in['senderid']}\" onclick=\"membersTab('uid={$in['senderid']}', 1); return false;\" title=\"{$in['senderusername']}\">{$senderuser}</a>. Subject: <a href=\"messages.php?inbox={$in['id']}\" onclick=\"messagefunctions('{$in['id']}','read'); return false;\">{$subject}</a></li>";
			}
		}
		
		// If there are no messages to display
		
		if($incount == '0'){
			echo "<b>You have no messages.</b>";
		}
	}
	
	if($option == 'sent'){ // User requests sent box
		$sent = mysql_query("SELECT * FROM messages WHERE senderid = '{$_SESSION['myid']}' AND senderdelete = '1' ORDER BY time DESC");
		$sent1 = mysql_num_rows($sent); // Check if there are any messages to display
		if($sent1 == '0'){
			
			// If there are no messages to display...
			
			echo 'You have no saved messages.';
		}
		if($sent1 > '0'){
			
			// If there are messages to display...
			
			while($sent2 = mysql_fetch_array($sent)){
				$style = 'dark';
				if($sent2['senderread'] == 1){
					$style = 'highlight';
				}
				
				echo "<li id=\"open{$sent2['id']}\" class=\"". $style ."\">To: <a href=\"members.php?uid={$sent2['receiverid']}\" onclick=\"membersTab('uid={$sent2['receiverrid']}', 1); return false;\" title=\"{$sent2['senderusername']}\">{$sent2['receiverusername']}</a>. Subject: <a href=\"messages.php?inbox={$sent2['id']}\" onclick=\"messagefunctions('{$sent2['id']}','readsent'); return false;\">{$sent2['subject']}</a></li>";
			}
		}
	}
}

$read = $_REQUEST['read'];
if($read){
	$read1 = mysql_query("SELECT * FROM messages WHERE receiverid = '{$_SESSION['myid']}' AND id = '$read'");
	$inread = mysql_num_rows($read1);
	
	if($inread == '0'){
		echo "<div class=\"errorMsg\">And error has occured</div>";
	}
	if($inread != '0'){
		mysql_query("UPDATE messages SET receiverread = '0' WHERE receiverid = '{$_SESSION['myid']}' AND id = '$read'");
		$re = mysql_fetch_array($read1);
		$date = date("jS F H:i",$re['time']);
		$codep = htmlentities($re['message']);
		$code = $codep;
		
		// Select words to filter and replace
		
		$replace = array("Damn","Cock","Dick","Bitch","Shit","Fuck","fuck","bitch","damn","shit","cock","dick","[u]","[/u]","[i]","[/i]","[b]","[/b]","[s]","[/s]","[sub]","[/sub]","[sup]","[/sup]","[quote]","[/quote]");
		
		// Select the string to replace the filtered words
		
		$with111 = array("****","****","****","*****","****","****","****","*****","****","****","****","****","<u>","</u>","<i>","</i>","<b>","</b>","<s>","</s>","<sub>","</sub>","<sup>","</sup>","<blockquote style=\"border: 2px solid #666666;background-color:#CCCCCC;padding:5px;\">","</blockquote>");
		
		$newcode = str_replace($replace, $with111, $code);
		
		echo "<div style=\"font-size:15px;\"><p style=\"float:left;\">{$re['subject']}</p><p style=\"float:right;font-weight: bold;color: #990000;\">$date</p></div><div style=\"border-top: 1px dotted #CCCCCC;clear: both;\"></div><p style=\"font-size:12px;\">{$newcode}</p>";
	}
}
include(__DIR__ . '/../pv_disconnect_from_db.php');
?>
