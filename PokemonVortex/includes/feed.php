<?php
// Anything printed on this page will be loaded into #getPkmn

	require '../pv_connect_to_db.php';

		$getpokemon = mysql_query("SELECT * FROM pokemon ORDER BY id DESC LIMIT 1");
		$pokemon = mysql_fetch_assoc($getpokemon);
		$get_stats = mysql_query("SELECT * FROM pokemon_stats WHERE id = '{$pokemon['id']}'");
		$stats = mysql_fetch_assoc($get_stats);
		
		$name = $pokemon['name'];
		$level = $pokemon['lvl'];
		$owner = $pokemon['rowner'];
		$id = $pokemon['id'];
		$ownerid = $pokemon['owner'];
		$gender = $stats['gender'];

		echo '<br /><br /><table width="300" height="120" align="center" style="text-align: center;"><tr><td><strong>' . $owner . ' caught a:</strong></td></tr><tr><td><strong>' . $name . ' </strong><img src="html/static/images/misc/' . $gender . '.png" /></td></tr><tr><td><strong>Lvl: ' . $level . '</strong></td></tr><tr><td><img src="html/static/images/pokemon/' . $name . '.gif" /></td></tr>';

?>