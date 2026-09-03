<?php
session_start();
if(empty($_SESSION['myid'])){
	header("/login.php");
	exit();
}
include(__DIR__ . '/../pv_connect_to_db.php');
?>


<div style="width:194px;max-height:250px;overflow: auto;">
<?php
$query = mysql_query("SELECT * FROM pokemon WHERE name LIKE '{$_REQUEST['q']}%' AND owner = '{$_SESSION['myid']}'");
$counting = mysql_num_rows($query);
if($counting == 0){
	echo "Sorry Invalid Search";
}
if($counting > 0){
	while($query2 = mysql_fetch_array($query)){
		echo "<a href=\"#\" onclick=\"selectSuggestion('{$query2['name']}')\">{$query2['name']}</a>";
		echo "<br/>";
	}
}
?>
</div>