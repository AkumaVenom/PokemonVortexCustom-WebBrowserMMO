<?php 
header("Content-type: text/xml");
echo "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>";
include(__DIR__ . '/../kick.php');
include(__DIR__ . '/../pv_connect_to_db.php');
if(!$_SESSION['myid']){
	echo "<sessionExpired>Your session has expired, please log back in.</sessionExpired>";
	exit();
}
if($_SESSION['myid'] && $_REQUEST['move'] && $_REQUEST['map'] && $_REQUEST['main']){
	$map = (int)$_REQUEST['map'];
	$m = $_REQUEST['main'];
	unset($_SESSION['wb'], $_SESSION['lvl']);
	$move = $_REQUEST['move'];
	if($move > 0 && $move < 9){
		if($move == '1'){$statment = 'y = y - 1';}
		if($move == '2'){$statment = 'y = y + 1';}
		if($move == '3'){$statment = 'x = x - 1';}
		if($move == '4'){$statment = 'x = x + 1';}
		if($move == '5'){$statment = 'x = x - 1, y = y - 1';}
		if($move == '6'){$statment = 'x = x - 1, y = y + 1';}
		if($move == '7'){$statment = 'x = x + 1, y = y - 1';}
		if($move == '8'){$statment = 'x = x + 1, y = y + 1';}
		mysql_query("UPDATE mapusers SET $statment WHERE id = '{$_SESSION['myid']}' AND map = '{$m}'");

	}

	$rand_num = rand(0,1000);

	if($rand_num > 664){
		if($_SESSION['night'] == '1'){
			$include = "nightmappoke{$map}.php";
		}
		else{
			$include = "mappoke{$map}.php";
		}
		include("$include");
	}
	else
	{
		echo "<foundPokemon>&lt;b&gt;No wild Pok&amp;eacute;mon appeared.&lt;/b&gt;&lt;br&gt;&lt;i&gt;Keep moving around to find one.&lt;/i&gt;</foundPokemon>";
	}
}
?>