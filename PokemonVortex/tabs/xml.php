<?php
if(!$_SESSION['myid']){ // Check the user is logged in
	include(__DIR__ . '/../pv_disconnect_from_db.php');
	header('Location: ../login.php?expired=1');
	exit();
}
if($_SESSION['access'] == 9){
	include(__DIR__ . '/../kick.php');
	include(__DIR__ . '/../pv_connect_to_db.php');
	
	$time = time();
	$move = $_REQUEST['move'];
	if($move == 'up'){
		$statment = 'y = y - 1';
	}
	if($move == 'down'){
		$statment = 'y = y + 1';
	}
	if($move == 'left'){
		$statment = 'x = x - 1';
	}
	if($move == 'right'){
		$statment = 'x = x + 1';
	}
	if($move == 'leftup'){
		$statment = 'x = x - 1, y = y - 1';
	}
	if($move == 'leftdown'){
		$statment = 'x = x - 1, y = y + 1';
	}
	if($move == 'rightup'){
		$statment = 'x = x + 1, y = y - 1';
	}
	if($move == 'rightdown'){
		$statment = 'x = x + 1, y = y + 1';
	}
	
	unset($_SESSION['mapx']);
	unset($_SESSION['mapy']);
	mysql_query("UPDATE mapusers SET $statment WHERE id = '{$_SESSION['myid']}'");
}
else {
	include(__DIR__ . '/../pv_disconnect_from_db.php');
	pv_redirect('login.php?goawayxP=1');
	exit();
}
?>
