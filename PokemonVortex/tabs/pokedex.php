<?php
include(__DIR__ . '/../kick.php');
include(__DIR__ . '/../pv_connect_to_db.php');
if(!$_SESSION['myid']){ // Check the user is logged in
	include(__DIR__ . '/../pv_disconnect_from_db.php');
	pv_redirect('login.php?goawayxP=1');
	exit();
}
if($_SESSION['access'] == 9){

	if($_REQUEST['pid']){ // Display the requested Pokemon

		$h1 = mysql_real_escape_string($_REQUEST['pid']);
		$h2 = 'id';

		$text = '<p><span class=\'small\'>';
		$text2 = '</span></p>';
		$text3 = $text . "<a href=\"pokedex.php?pid=";
		$text4 = "\" onclick=\"pokedexTab('pid=";
		$kunsdsd = mysql_query("SELECT * FROM pokemon WHERE $h2 = '$h1'$st");
		$kun2sdsd = mysql_fetch_array($kunsdsd);
		$statss = mysql_query("SELECT * FROM pokemon_stats WHERE $h2 = '$h1'");
		$stats = mysql_fetch_array($statss);
		$kunnsdsd = mysql_query("SELECT * FROM pguide WHERE name LIKE '{$kun2sdsd['name']}'");
		$kunisdsd = mysql_fetch_array($kunnsdsd);

		//----------------------- Check to see if the Pokemon has damaged data caused by v3 BETA and display fix button -----------------//

		if($kun2sdsd['rowner'] === $_SESSION['myuser']){ // Make sure the Pokemon is yours
			if($stats['ot'] == ''){ // Check if the original trainer is blank which will imply broken Pokemon
				echo '<div class="noticeMsg">This Pok&eacute;mon seems to have some broken data which will now be corrected.</div>';

				// Pokemon that can only be female
				if(strstr($kun2sdsd['name'],'Nidoran (F)') || strstr($kun2sdsd['name'],'Nidorina') || strstr($kun2sdsd['name'],'Nidoqueen') || strstr($kun2sdsd['name'],'Chansey') || strstr($kun2sdsd['name'],'Kangaskhan') || strstr($kun2sdsd['name'],'Jynx') || strstr($kun2sdsd['name'],'Miltank') || strstr($kun2sdsd['name'],'Blissey') || strstr($kun2sdsd['name'],'Illumise') || strstr($kun2sdsd['name'],'Wormadam') || strstr($kun2sdsd['name'],'Vespiquen') || strstr($kun2sdsd['name'],'Froslass') || strstr($kun2sdsd['name'],'Petlil') || strstr($kun2sdsd['name'],'Lilligant') || strstr($kun2sdsd['name'],'Vullaby') || strstr($kun2sdsd['name'],'Mandibuzz') || strstr($kun2sdsd['name'],'Flabebe') || strstr($kun2sdsd['name'],'Floette') || strstr($kun2sdsd['name'],'Florges') || strstr($kun2sdsd['name'],'Smoochum') || strstr($kun2sdsd['name'],'Latias') || strstr($kun2sdsd['name'],'Happiny') || strstr($kun2sdsd['name'],'Cresselia')){
					$genda = 'Female';
				}
				// Pokemon that can only be male
				elseif(strstr($kun2sdsd['name'],'Nidoran (M)') || strstr($kun2sdsd['name'],'Nidorino') || strstr($kun2sdsd['name'],'Nidoking') || strstr($kun2sdsd['name'],'Hitmonlee') || strstr($kun2sdsd['name'],'Hitmonchan') || strstr($kun2sdsd['name'],'Tauros') || strstr($kun2sdsd['name'],'Hitmontop') || strstr($kun2sdsd['name'],'Volbeat') || strstr($kun2sdsd['name'],'Mothim') || strstr($kun2sdsd['name'],'Gallade') || strstr($kun2sdsd['name'],'Throh') || strstr($kun2sdsd['name'],'Sawk') || strstr($kun2sdsd['name'],'Rufflet') || strstr($kun2sdsd['name'],'Braviary') || strstr($kun2sdsd['name'],'Tyrogue') || strstr($kun2sdsd['name'],'Latios') || strstr($kun2sdsd['name'],'Tornadus') || strstr($kun2sdsd['name'],'Thundurus') || strstr($kun2sdsd['name'],'Landorus')){
					$genda = 'Male';
				}
				// Pokemon that are genderless
				elseif(strstr($kun2sdsd['name'],'Magnemite') || strstr($kun2sdsd['name'],'Magneton') || strstr($kun2sdsd['name'],'Voltorb') || strstr($kun2sdsd['name'],'Electrode') || strstr($kun2sdsd['name'],'Staryu') || strstr($kun2sdsd['name'],'Starmie') || strstr($kun2sdsd['name'],'Porygon') || strstr($kun2sdsd['name'],'Porygon2') || strstr($kun2sdsd['name'],'Shedinja') || strstr($kun2sdsd['name'],'Lunatone') || strstr($kun2sdsd['name'],'Solrock') || strstr($kun2sdsd['name'],'Baltoy') || strstr($kun2sdsd['name'],'Claydol') || strstr($kun2sdsd['name'],'Beldum') || strstr($kun2sdsd['name'],'Metang') || strstr($kun2sdsd['name'],'Metagross') || strstr($kun2sdsd['name'],'Bronzor') || strstr($kun2sdsd['name'],'Bronzong') || strstr($kun2sdsd['name'],'Magnezone') || strstr($kun2sdsd['name'],'Porygon-Z') || strstr($kun2sdsd['name'],'Rotom') || strstr($kun2sdsd['name'],'Phione') || strstr($kun2sdsd['name'],'Manaphy') || strstr($kun2sdsd['name'],'Klink') || strstr($kun2sdsd['name'],'Klang') || strstr($kun2sdsd['name'],'Klinklang') || strstr($kun2sdsd['name'],'Cryogonal') || strstr($kun2sdsd['name'],'Golett') || strstr($kun2sdsd['name'],'Golurk') || strstr($kun2sdsd['name'],'Carbink') || strstr($kun2sdsd['name'],'Ditto') || strstr($kun2sdsd['name'],'Articuno') || strstr($kun2sdsd['name'],'Zapdos') || strstr($kun2sdsd['name'],'Moltres') || strstr($kun2sdsd['name'],'Mewtwo') || strstr($kun2sdsd['name'],'Mew') || strstr($kun2sdsd['name'],'Unown') || strstr($kun2sdsd['name'],'Raikou') || strstr($kun2sdsd['name'],'Entei') || strstr($kun2sdsd['name'],'Suicune') || strstr($kun2sdsd['name'],'Lugia') || strstr($kun2sdsd['name'],'Ho-oh') || strstr($kun2sdsd['name'],'Celebi') || strstr($kun2sdsd['name'],'Regirock') || strstr($kun2sdsd['name'],'Regice') || strstr($kun2sdsd['name'],'Registeel') || strstr($kun2sdsd['name'],'Kyogre') || strstr($kun2sdsd['name'],'Groudon') || strstr($kun2sdsd['name'],'Rayquaza') || strstr($kun2sdsd['name'],'Jirachi') || strstr($kun2sdsd['name'],'Deoxys') || strstr($kun2sdsd['name'],'Uxie') || strstr($kun2sdsd['name'],'Mesprit') || strstr($kun2sdsd['name'],'Azelf') || strstr($kun2sdsd['name'],'Dialga') || strstr($kun2sdsd['name'],'Palkia') || strstr($kun2sdsd['name'],'Regigigas') || strstr($kun2sdsd['name'],'Giratina') || strstr($kun2sdsd['name'],'Darkrai') || strstr($kun2sdsd['name'],'Darkrown') || strstr($kun2sdsd['name'],'Shaymin') || strstr($kun2sdsd['name'],'Arceus') || strstr($kun2sdsd['name'],'Victini') || strstr($kun2sdsd['name'],'Cobalion') || strstr($kun2sdsd['name'],'Terrakion') || strstr($kun2sdsd['name'],'Virizion') || strstr($kun2sdsd['name'],'Reshiram') || strstr($kun2sdsd['name'],'Zekrom') || strstr($kun2sdsd['name'],'Kyurem') || strstr($kun2sdsd['name'],'Keldeo') || strstr($kun2sdsd['name'],'Meloetta') || strstr($kun2sdsd['name'],'Genesect') || strstr($kun2sdsd['name'],'Xerneas') || strstr($kun2sdsd['name'],'Yveltal') || strstr($kun2sdsd['name'],'Zygarde') || strstr($kun2sdsd['name'],'Missingno') || strstr($kun2sdsd['name'],'Volcanion')){
					$genda = 'None';
				}
				elseif(strstr($kun2sdsd['name'],'Combee')){ // combee has a 12.5% chance of being a female, this overrides the original gender determination
					$gend = rand(1,8);
					if($gend == '1'){
						$genda = 'Female';
					}
					elseif($gend > 1){
						$genda = 'Male';
					}
				}
				else{
					$gend = rand(1,2);
					if($gend == '1'){
						$genda = 'Male';
					}
					elseif($gend == '2'){
						$genda = 'Female';
					}
				}
				// Start the fixing process
				mysql_query("UPDATE pokemon_stats SET ot = '{$_SESSION['myuser']}', gender = '$genda', ball = 'Poke Ball' WHERE id = '$h1'");
			}
		}

		//----------------------- End Pokemon fix -----------------------//


		if($stats['gender'] == Male){ // Check if the Pokemon is Male
			$gender = '<img src="html/static/images/misc/Male.png">';
		}
		elseif($stats['gender'] == Female){ // Check if the Pokemon is Female
			$gender = '<img src="html/static/images/misc/Female.png">';
		}
		elseif($stats['gender'] == ''){
			$gender = '';
		}

		echo '
		<center>
		<h3><img src="html/static/images/items/' . $stats['ball'] . '.png"> ' . $kun2sdsd['name'] . '  ' . $gender . '</h3>

		<img src="html/static/images/pokemon/' . $kun2sdsd['name'] . '.gif"><br/>
		<strong>Level:</strong> ' . $kun2sdsd['lvl'] . '<br/>
		<strong>Experience:</strong> ' . number_format($kun2sdsd['exp']) . '<br/><br/>
		<strong>Number:</strong> ' . $kunisdsd['dex_num'] . '<br/><br/>
		<strong>Attacks:</strong><br/>
		' . $kun2sdsd['a1'] . '<br/>
		' . $kun2sdsd['a2'] . '<br/>
		' . $kun2sdsd['a3'] . '<br/>
		' . $kun2sdsd['a4'] . '<br/><br/>
		<strong>Type:</strong> <img src="html/static/images/types/' . $kunisdsd['type1'] . '.gif" align="absmiddle"> ';
		if($kunisdsd['type2'] != ""){ echo '<img src="html/static/images/types/' . $kunisdsd['type2'] . '.gif" align="absmiddle">'; } 
		echo '<br />';
		if($kun2sdsd['rowner'] != ""){ echo '<p><strong>Owner:</strong> ' . $kun2sdsd['rowner']; }
		echo '<p><strong>Original Trainer:</strong> ' . $stats['ot'];
		echo '<p><strong>Happiness: </strong>';
		
		// Display Pokemons Happiness
		
		if($stats['happiness'] >= 73){ echo '<img src="html/static/images/misc/heart.png"> '; }
		if($stats['happiness'] >= 146){ echo '<img src="html/static/images/misc/heart.png"> '; }
		if($stats['happiness'] >= 220){ echo '<img src="html/static/images/misc/heart.png"> '; }
		if($stats['happiness'] >= 255){ echo '<img src="html/static/images/misc/maxheart.png" title="Maximum Happiness">'; }
		echo ' <sup>[?]</sup>';

		if($kunisdsd['ev'] != 0 || strstr($kunisdsd['ev'],'Stone') || strstr($kunisdsd['ev'],'Dragon Scale') || strstr($kunisdsd['ev'],'Dubious Disc') || strstr($kunisdsd['ev'],'Kings Rock') || strstr($kunisdsd['ev'],'Magmarizer') || strstr($kunisdsd['ev'],'Metal Coat') || strstr($kunisdsd['ev'],'Prism Scale') || strstr($kunisdsd['ev'],'Protector') || strstr($kunisdsd['ev'],'Razor Claw') || strstr($kunisdsd['ev'],'Razor Fang') || strstr($kunisdsd['ev'],'Reaper Cloth') || strstr($kunisdsd['ev'],'ite') || strstr($kunisdsd['ev'],'Up Grade') || strstr($kunisdsd['ev'],'Electirizer') || strstr($kunisdsd['ev'],'Sachet') || strstr($kunisdsd['ev'],'Whipped Dream') || strstr($kunisdsd['ev'],'Rock') || strstr($kunisdsd['ev'],'Happiness') || strstr($kunisdsd['ev'],'Deepseatooth') || strstr($kunisdsd['ev'],'Deepseascale')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep'] . '\', 1); return false;">' . $kunisdsd['ep'] . '</a>';
			if(is_numeric($kunisdsd['ev'])){
				echo ' at level ' . $kunisdsd['ev'] . '.';
			}
			elseif(strstr($kunisdsd['ev'],'Happiness')){
				echo ' at level 3 ' . $kunisdsd['ev'] . '.';
			}
			else{
				echo ' using a ' . $kunisdsd['ev'] . '.';
			}
			echo '</span></p>';
		}
		else{
			echo '<p><span class="small">' . $kun2sdsd['name'] . '  is at its final stage of evolution.</span></p>';
		}
		if(strstr($kunisdsd['ev1'],'Stone') || strstr($kunisdsd['ev1'],'Deepseatooth') || strstr($kunisdsd['ev1'],'Deepseascale')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep1'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep1'] . '\', 1); return false;">' . $kunisdsd['ep1'] . '</a> using a ' . $kunisdsd['ev1'] . '.</span></p>';
		}
		if(strstr($kunisdsd['ev2'],'Stone') || strstr($kunisdsd['ev2'],'Deepseatooth') || strstr($kunisdsd['ev2'],'Deepseascale')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep2'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep2'] . '\', 1); return false;">' . $kunisdsd['ep2'] . '</a> using a ' . $kunisdsd['ev2'] . '.</span></p>';
		}
		elseif(is_numeric($kunisdsd['ev2']) && $kunisdsd['ev2'] > 0){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep2'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep2'] . '\', 1); return false;">' . $kunisdsd['ep2'] . '</a> at level ' . $kunisdsd['ev2'] . '.</span></p>';
		}
		if(strstr($kunisdsd['ev3'],'Stone')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep3'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep3'] . '\', 1); return false;">' . $kunisdsd['ep3'] . '</a> using a ' . $kunisdsd['ev3'] . '.</span></p>';
		}
		elseif(is_numeric($kunisdsd['ev3']) && $kunisdsd['ev3'] > 0){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep3'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep3'] . '\', 1); return false;">' . $kunisdsd['ep3'] . '</a> at level ' . $kunisdsd['ev3'] . '.</span></p>';
		}
		if(strstr($kunisdsd['ev4'],'Stone') || strstr($kunisdsd['ev4'],'Rock')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep4'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep4'] . '\', 1); return false;">' . $kunisdsd['ep4'] . '</a> using a ' . $kunisdsd['ev4'] . '.</span></p>';
		}
		if(strstr($kunisdsd['ev5'],'Stone') || strstr($kunisdsd['ev5'],'Rock')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep5'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep5'] . '\', 1); return false;">' . $kunisdsd['ep5'] . '</a> using a ' . $kunisdsd['ev5'] . '.</span></p>';
		}
		if(strstr($kunisdsd['ev6'],'Stone')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep6'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep6'] . '\', 1); return false;">' . $kunisdsd['ep6'] . '</a> using a ' . $kunisdsd['ev6'] . '.</span></p>';
		}
		elseif(strstr($kunisdsd['ev6'],'Happiness')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep6'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep6'] . '\', 1); return false;">' . $kunisdsd['ep6'] . '</a> at level 3 ' . $kunisdsd['ev6'] . '.</span></p>';
		}
		if(strstr($kunisdsd['ev7'],'Stone')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep7'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep7'] . '\', 1); return false;">' . $kunisdsd['ep7'] . '</a> using a ' . $kunisdsd['ev7'] . '.</span></p>';
		}
		elseif(strstr($kunisdsd['ev7'],'Happiness')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep7'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep7'] . '\', 1); return false;">' . $kunisdsd['ep7'] . '</a> at level 3 ' . $kunisdsd['ev7'] . '.</span></p>';
		}
		if(strstr($kunisdsd['ev8'],'Happiness')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kunisdsd['ep8'] . '" onclick="pokedexTab(\'dex=' . $kunisdsd['ep8'] . '\', 1); return false;">' . $kunisdsd['ep8'] . '</a> at level 4 ' . $kunisdsd['ev7'] . '.</span></p>';
		}



		echo '</center>';
	}
	if($_REQUEST['dex']){
		$kunnsdsd = mysql_query("SELECT * FROM pguide WHERE name = '{$_REQUEST['dex']}'");
		if(mysql_num_rows($kunnsdsd) > 0){
			$kun2sdsd = mysql_fetch_array($kunnsdsd);
			echo '
			<center>
			<h3>' . $kun2sdsd['name'] . '</h3>
			<img src="html/static/images/pokemon/' . $kun2sdsd['name'] . '.gif"><br/>
			<strong>Number:</strong> ' . $kun2sdsd['pokedex_number'] . '<br/><br/>
			<strong>Attacks:</strong><br/>
			' . $kun2sdsd['a1'] . '<br/>
			' . $kun2sdsd['a2'] . '<br/>
			' . $kun2sdsd['a3'] . '<br/>
			' . $kun2sdsd['a4'] . '<br/><br/>
			<strong>Type:</strong> <img src="html/static/images/types/' . $kun2sdsd['type1'] . '.gif" align="absmiddle"> ';
			if($kun2sdsd['type2'] != ""){ echo '<img src="html/static/images/types/' . $kun2sdsd['type2'] . '.gif" align="absmiddle">'; } 
			echo '<br />';

			if($kun2sdsd['ev'] != 0 || strstr($kun2sdsd['ev'],'Stone') || strstr($kun2sdsd['ev'],'Dragon Scale') || strstr($kun2sdsd['ev'],'Dubious Disc') || strstr($kun2sdsd['ev'],'Kings Rock') || strstr($kun2sdsd['ev'],'Magmarizer') || strstr($kun2sdsd['ev'],'Metal Coat') || strstr($kun2sdsd['ev'],'Prism Scale') || strstr($kun2sdsd['ev'],'Protector') || strstr($kun2sdsd['ev'],'Razor Claw') || strstr($kun2sdsd['ev'],'Razor Fang') || strstr($kun2sdsd['ev'],'Reaper Cloth') || strstr($kun2sdsd['ev'],'ite') || strstr($kun2sdsd['ev'],'Up Grade') || strstr($kun2sdsd['ev'],'Electirizer') || strstr($kun2sdsd['ev'],'Sachet') || strstr($kun2sdsd['ev'],'Whipped Dream') || strstr($kun2sdsd['ev'],'Rock') || strstr($kun2sdsd['ev'],'Happiness') || strstr($kun2sdsd['ev'],'Deepseatooth') || strstr($kun2sdsd['ev'],'Deepseascale')){
				echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep'] . '\', 1); return false;">' . $kun2sdsd['ep'] . '</a>';
				if(is_numeric($kun2sdsd['ev'])){
					echo ' at level ' . $kun2sdsd['ev'] . '.';
				}
				elseif(strstr($kunisdsd['ev'],'Happiness')){
					echo ' at level 3 ' . $kunisdsd['ev'] . '.';
				}
				else{
					echo ' using a ' . $kun2sdsd['ev'] . '.';
				}
				echo '</span></p>';
			}
			else{
				echo '<p><span class="small">' . $kun2sdsd['name'] . '  is at its final stage of evolution.</span></p>';
			}
		}
		else{
			echo '<div class="errorMsg">Error!</div>';
		}
		if(strstr($kun2sdsd['ev1'],'Stone') || strstr($kun2sdsd['ev1'],'Deepseatooth') || strstr($kun2sdsd['ev1'],'Deepseascale')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep1'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep1'] . '\', 1); return false;">' . $kun2sdsd['ep1'] . '</a> using a ' . $kun2sdsd['ev1'] . '.</span></p>';
		}
		if(strstr($kun2sdsd['ev2'],'Stone') || strstr($kun2sdsd['ev2'],'Deepseatooth') || strstr($kun2sdsd['ev2'],'Deepseascale')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep2'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep2'] . '\', 1); return false;">' . $kun2sdsd['ep2'] . '</a> using a ' . $kun2sdsd['ev2'] . '.</span></p>';
		}
		elseif(is_numeric($kun2sdsd['ev2']) && $kun2sdsd['ev2'] > 0){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep2'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep2'] . '\', 1); return false;">' . $kun2sdsd['ep2'] . '</a> at level ' . $kun2sdsd['ev2'] . '.</span></p>';
		}
		if(strstr($kun2sdsd['ev3'],'Stone')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep3'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep3'] . '\', 1); return false;">' . $kun2sdsd['ep3'] . '</a> using a ' . $kun2sdsd['ev3'] . '.</span></p>';
		}
		elseif(is_numeric($kun2sdsd['ev3']) && $kun2sdsd['ev3'] > 0){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep3'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep3'] . '\', 1); return false;">' . $kun2sdsd['ep3'] . '</a> at level ' . $kun2sdsd['ev3'] . '.</span></p>';
		}
		if(strstr($kun2sdsd['ev4'],'Stone') || strstr($kun2sdsd['ev4'],'Rock')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep4'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep4'] . '\', 1); return false;">' . $kun2sdsd['ep4'] . '</a> using a ' . $kun2sdsd['ev4'] . '.</span></p>';
		}
		if(strstr($kun2sdsd['ev5'],'Stone') || strstr($kun2sdsd['ev5'],'Rock')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep5'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep5'] . '\', 1); return false;">' . $kun2sdsd['ep5'] . '</a> using a ' . $kun2sdsd['ev5'] . '.</span></p>';
		}
		if(strstr($kun2sdsd['ev6'],'Stone') || strstr($kun2sdsd['ev6'],'Happiness')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep6'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep6'] . '\', 1); return false;">' . $kun2sdsd['ep6'] . '</a> at level 3 ' . $kun2sdsd['ev6'] . '.</span></p>';
		}
		if(strstr($kun2sdsd['ev7'],'Stone') || strstr($kun2sdsd['ev7'],'Happiness')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep7'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep7'] . '\', 1); return false;">' . $kun2sdsd['ep7'] . '</a> at level 3 ' . $kun2sdsd['ev7'] . '.</span></p>';
		}
		if(strstr($kun2sdsd['ev8'],'Stone') || strstr($kun2sdsd['ev8'],'Happiness')){
			echo '<p><span class="small">' . $kun2sdsd['name'] . ' evolves into <a href="pokedex.php?dex=' . $kun2sdsd['ep8'] . '" onclick="pokedexTab(\'dex=' . $kun2sdsd['ep8'] . '\', 1); return false;">' . $kun2sdsd['ep8'] . '</a> at level 4 ' . $kun2sdsd['ev8'] . '.</span></p>';
		}
		echo '</center>';
	}







	if(!$_REQUEST['pid'] && !$_REQUEST['dex']){
		?>
        <center>
        <h2>Pok&eacute;dex</h2><br/>
        <img src="html/static/images/misc/pb.gif"> = You have this Pok&eacute;mon.<br />
        <img src="html/static/images/misc/npb.gif"> = You do not have this Pok&eacute;mon.<br /><br />
        Click a Pok&eacute;mon's name to get a detailed description of them.<br /><br />
        <a href="#A">A</a> <a href="#B">B</a> <a href="#C">C</a> <a href="#D">D</a> <a href="#E">E</a> <a href="#F">F</a> <a href="#G">G</a> <a href="#H">H</a> <a href="#I">I</a> <a href="#J">J</a> <a href="#K">K</a> <a href="#L">L</a> <a href="#M">M</a> <a href="#N">N</a> <a href="#O">O</a> <a href="#P">P</a> <a href="#Q">Q</a> <a href="#R">R</a> <a href="#S">S</a> <a href="#T">T</a> <a href="#U">U</a> <a href="#V">V</a> <a href="#W">W</a> <a href="#X">X</a> <a href="#Y">Y</a> <a href="#Z">Z</a> 
        <br/>
        <br/>
        <table><tr>
        <td style="width:70px;"><h3>Number</h3></td><td style="width:150px;text-align:center;"><h3>Pok&eacute;mon</h3></td><td style="width:25px;text-align;left;"><b><sup>Nor</sup></b></td><td style="width:25px;text-align;center;"><b><sup>Met</sup></b></td><td style="width:25px;text-align;center;"><b><sup>Shi</sup></b></td><td style="width:25px;text-align;center;"><b><sup>Dar</sup></b></td><td style="width:25px;text-align;center;"><b><sup>Mys</sup></b></td><td style="width:25px;text-align;left;"><b><sup>Sha</sup></b></td></tr></table><br/>

		<?php
		$dark = "Dark ";
		$shiny = "Shiny ";
		$mystic = "Mystic ";
		$letter = "A";
		$metallic = "Metallic ";
		$shadow = "Shadow ";
		
		$nb2 = mysql_query("SELECT name FROM pokemon WHERE owner = '{$_SESSION['myid']}' GROUP BY pid");
		$hmuo = mysql_num_rows($nb2);
		while($nb12 = mysql_fetch_array($nb2)){
			$array_names[] = $nb12['name'];
		}




		$Aname = array("Abomasnow","Abomasnow (Mega)","Abra","Absol","Absol (Mega)","Accelgor","Aegislash (Blade)","Aegislash (Shield)","Aerodactyl","Aerodactyl (Mega)","Aggron","Aggron (Mega)","Aipom","Alakazam","Alakazam (Mega)","Alomomola","Altaria","Altaria (Mega)","Amaura","Ambipom","Amoonguss","Ampharos","Ampharos (Mega)","Anorith","Arbok","Arcanine","Arceus","Arceus (Bug)","Arceus (Dark)","Arceus (Dragon)","Arceus (Electric)","Arceus (Fairy)","Arceus (Fighting)","Arceus (Fire)","Arceus (Flying)","Arceus (Ghost)","Arceus (Grass)","Arceus (Ground)","Arceus (Ice)","Arceus (Poison)","Arceus (Psychic)","Arceus (Rock)","Arceus (Steel)","Arceus (Unknown)","Arceus (Water)","Archen","Archeops","Ariados","Armaldo","Aromatisse","Aron","Articuno","Audino","Audino (Mega)","Aurorus","Avalugg","Axew","Azelf","Azumarill","Azurill");
		
		$Anumber = array("500","897","63","390","892","691","819","820","143","877","334","888","191","65","872","668","362","837","464","665","182","880","375","24","59","541","542","543","544","545","898","546","547","548","549","550","551","552","553","554","555","556","736","557","634","635","169","376","822","332","145","598","838","858","684","527","185","326","000","000");
		
		$Adex = array("460","460","063","359","359","617","681","681","142","142","306","306","190","065","065","594","334","334","698","424","591","181","181","347","024","059","493","493","493","493","493","493","493","493","493","493","493","493","493","493","493","493","493","493","493","566","567","168","348","683","304","144","531","531","699","713","610","482","184","298");
		
		$Bname = array("Bagon","Baltoy","Banette","Banette (Mega)","Barbaracle","Barboach","Basculin (Blue Stripe)","Basculin (Red Stripe)","Bastiodon","Bayleef","Beartic","Beautifly","Beedrill","Beedrill (Mega)","Beheeyem","Beldum","Bellossom","Bellsprout","Bergmite","Bibarel","Bidoof","Binacle","Bisharp","Blastoise","Blastoise (Mega)","Blaziken","Blaziken (Mega)","Blissey","Blitzle","Boldore","Bonsly","Bouffalant","Braviary","Braixen","Breloom","Bronzong","Bronzor","Budew","Buizel","Bulbasaur","Buneary","Bunnelby","Burmy (Plant)","Burmy (Sand)","Burmy (Steel)","Butterfree");
		
		$Bnumber = array("402","371","385","891","828","367","737","617","444","154","688","295","15","680","405","183","69","857","434","433","827","699","9","871","285","885","270","589","592","478","700","702","750","314","477","476","559","455","1","467","755","445","446","447","12","000");
		
		$Bdex = array("371","343","354","354","689","339","550","550","411","153","614","267","015","015","606","374","182","069","712","400","399","688","625","009","009","257","257","242","522","525","438","626","628","654","286","437","436","406","418","001","427","659","412","412","412","012");
		
		$Cname = array("Cacnea","Cacturne","Camerupt","Camerupt (Mega)","Carbink","Carnivine","Carracosta","Carvanha","Cascoon","Castform","Castform (Fire)","Castform (Ice)","Castform (Water)","Caterpie","Caterpie (Christmas)","Celebi","Chandelure","Chansey","Charizard","Charizard (Mega X)","Charizard (Mega Y)","Charmander","Charmeleon","Chatot","Cherrim (Non Sunny)","Cherrim (Sunny)","Cherubi","Chesnaught","Chespin","Chikorita","Chimchar","Chimecho","Chinchou","Chingling","Cinccino","Clamperl","Clauncher","Clawitzer","Claydol","Clefable","Clefairy","Cleffa","Cloyster","Cobalion","Cofagrigus","Combee","Combusken","Conkeldurr","Corphish","Corsola","Cottonee","Cradily","Cranidos","Crawdaunt","Cresselia","Croagunk","Crobat","Croconaw","Crustle","Cryogonal","Cubchoo","Cubone","Cyndaquil");
		
		$Cnumber = array("359","360","351","842","495","632","346","296","379","731","732","733","10","726","279","683","113","6","869","870","4","5","481","459","458","457","748","746","153","424","389","171","473","641","397","831","832","372","36","35","174","91","712","631","452","284","601","369","250","613","374","441","370","535","493","170","160","626","689","687","104","156","000");
		
		$Cdex = array("331","332","323","323","703","455","565","318","268","351","351","351","351","010","???","251","609","113","006","006","006","004","005","441","421","421","420","652","650","152","390","358","170","433","573","366","692","693","344","036","035","173","091","638","563","415","256","534","341","222","546","346","408","342","488","453","169","159","558","615","613","104","155");
		
		$Dname = array("Darkrai","Darkrown","Darmanitan","Darmanitan (Zen Mode)","Darumaka","Dedenne","Deerling (Autumn)","Deerling (Spring)","Deerling (Summer)","Deerling (Winter)","Deino","Delcatty","Delibird","Deoxys","Deoxys (Attack)","Deoxys (Defense)","Deoxys (Speed)","Delphox","Dewgong","Dewott","Dialga","Diancie","Diancie (Mega)","Diggersby","Diglett","Ditto","Dodrio","Doduo","Donphan","Doublade","Dragalge","Dragonair","Dragonite","Drapion","Dratini","Dratinice","Dratinilic","Dratinire","Drifblim","Drifloon","Drilbur","Drowzee","Druddigon","Ducklett","Dugtrio","Dunsparce","Duosion","Durant","Dusclops","Dusknoir","Duskull","Dustox","Dwebble");
		
		$Dnumber = array("538","558","622","623","621","841","653","654","655","656","707","329","253","417","418","419","420","751","87","569","528","865","756","50","133","85","84","260","818","830","149","150","492","148","739","741","740","466","465","596","96","695","648","51","234","646","706","387","517","386","297","625","000");
		
		$Ddex = array("491","???","555","555","554","702","585","585","585","585","633","301","225","386","386","386","386","655","087","502","483","719","719","660","050","132","085","084","232","680","691","148","149","452","147","???","???","???","426","425","529","096","621","580","051","206","578","632","356","477","355","269","557");
		
		$Ename = array("Eelektrik","Eelektross","Eevee","Ekans","Electabuzz","Electivire","Electrike","Electrode","Elekid","Elgyem","Emboar","Emolga","Empoleon","Entei","Escavalier","Espeon","Espurr","Excadrill","Exeggcute","Exeggutor","Exploud");
		
		$Enumber = array("677","678","134","23","125","506","337","101","267","679","567","661","429","272","663","197","814","597","102","103","323");
		
		$Edex = array("603","604","133","023","125","466","309","101","239","605","500","587","395","244","589","196","677","530","102","103","295");
		
		$Fname = array("Farfetchd","Fearow","Feebas","Fennekin","Feraligatr","Ferroseed","Ferrothorn","Finneon","Flaaffy","Flabebe (Blue)","Flabebe (Orange)","Flabebe (Red)","Flabebe (White)","Flabebe (Yellow)","Flareon","Fletchinder","Fletchling","Floatzel","Floette (Blue)","Floette (Eternal)","Floette (Orange)","Floette (Red)","Floette (White)","Floette (Yellow)","Florges (Blue)","Florges (Orange)","Florges (Red)","Florges (White)","Florges (Yellow)","Flygon","Foongus","Forretress","Fraxure","Frillish","Froakie","Frogadier","Froslass","Furfrou","Furfrou (Dandy)","Furfrou (Deputante)","Furfrou (Diamond)","Furfrou (Heart)","Furfrou (Kabuki)","Furfrou (La Reine)","Furfrou (Matron)","Furfrou (Pharaoh)","Furfrou (Star)","Furret");
		
		$Fnumber = array("83","22","377","749","161","671","672","496","181","784","785","786","787","788","137","758","757","456","790","791","792","793","794","795","796","797","798","799","358","664","233","685","666","752","753","518","804","807","808","813","163","000","000","000","000","000","000","000");
		
		$Fdex = array("083","022","349","653","160","597","598","456","180","669","669","669","669","669","136","662","661","419","670","670","670","670","670","670","671","671","671","671","671","330","590","205","611","592","656","657","478","676","676","676","676","676","676","676","676","676","676","162");
		
		$Gname = array("Gabite","Gallade","Gallade (Mega)","Galvantula","Garbodor","Garchomp","Garchomp (Mega)","Gardevoir","Gardevoir (Mega)","Gastly","Gastrodon (East)","Gastrodon (West)","Genesect","Genesect (Aqua)","Genesect (Blaze)","Genesect (Ice)","Genesect (Lightning)","Gengar","Gengar (Mega)","Geodude","Gible","Gigalith","Girafarig","Giratina","Giratina (Origin)","Glaceon","Glalie","Glalie (Mega)","Glameow","Gligar","Gliscor","Gloom","Gogoat","Golbat","Goldeen","Golduck","Golem","Golett","Golurk","Goodra","Goomy","Gorebyss","Gothita","Gothitelle","Gothorita","Gourgeist (Average)","Gourgeist (Large)","Gourgeist (Small)","Gourgeist (Super)","Granbull","Graveler","Greninja","Grimer","Grotle","Groudon","Groudon (Primal)","Grovyle","Growlithe","Grumpig","Gulpin","Gurdurr","Gyarados","Gyarados (Mega)");
		
		$Gnumber = array("484","515","760","637","485","895","310","886","92","461","463","724","728","727","725","726","94","873","74","483","593","231","532","533","511","393","471","235","512","44","801","42","118","55","76","696","697","845","843","399","642","644","643","854","238","75","754","88","422","414","281","58","354","344","600","130","876","000","000","000","000","000","000");
		
		$Gdex = array("444","475","475","596","569","445","445","282","282","092","423","423","649","649","649","649","649","094","094","074","443","526","203","487","487","471","362","362","431","207","472","044","673","042","118","055","076","622","623","706","704","368","574","576","575","711","711","711","711","210","075","658","088","388","383","383","253","058","326","316","533","130","130");
		
		$Hname = array("Happiny","Hariyama","Haunter","Hawlucha","Haxorus","Heatmor","Heatran","Heliolisk","Helioptile","Heracross","Heracross (Mega)","Herdier","Hippopotas","Hippowdon","Hitmonchan","Hitmonlee","Hitmontop","Ho-oh","Honchkrow","Honedge","Hoopa","Hoopa (Unbound)","Hoothoot","Hoppip","Horsea","Houndoom","Houndoom (Mega)","Houndour","Huntail","Hydreigon","Hypno");
		
		$Hnumber = array("480","325","93","840","686","705","530","834","833","242","882","574","489","490","107","106","265","278","470","817","867","164","188","116","257","883","256","398","709","97","000");
		
		$Hdex = array("440","297","093","701","612","631","485","695","694","214","214","507","449","450","107","106","237","250","430","679","721","721","163","187","116","229","229","228","367","635","097");
		
		$Iname = array("Igglybuff","Illumise","Infernape","Inkay","Ivysaur");
		
		$Inumber = array("175","342","426","825","2");
		
		$Idex = array("174","314","392","686","002");
		
		$Jname = array("Jellicent","Jigglypuff","Jirachi","Jolteon","Joltik","Jumpluff","Jynx");
		
		$Jnumber = array("667","39","416","136","669","190","124");
		
		$Jdex = array("593","039","385","135","595","189","124");
		
		$Kname = array("Kabuto","Kabutops","Kadabra","Kakuna","Kangaskhan","Kangaskhan (Mega)","Karrablast","Kecleon","Keldeo","Keldeo (Resolution)","Kingdra","Kingler","Kirlia","Klang","Klefki","Klink","Klinklang","Koffing","Krabby","Kricketot","Kricketune","Krokorok","Krookodile","Kyogre","Kyogre (Primal)","Kyurem","Kyurem (Black)","Kyurem (White)");
		
		$Knumber = array("140","141","64","14","115","874","658","379","717","739","257","99","308","670","846","669","671","109","98","431","432","615","616","409","918","716","740","741");
		
		$Kdex = array("140","141","064","014","115","115","588","352","647","647","230","099","281","600","707","599","601","109","098","401","402","552","553","382","382","646","646","646");
		
		$Lname = array("Lairon","Lampent","Landorus","Landorus (Therian)","Lanturn","Lapras","Larvesta","Larvitar","Latias","Latias (Mega)","Latios","Latios (Mega)","Leafeon","Leavanny","Ledian","Ledyba","Lickilicky","Lickitung","Liepard","Lileep","Lilligant","Lillipup","Linoone","Litleo","Litwick","Lombre","Lopunny","Lopunny (Mega)","Lotad","Loudred","Lucario","Lucario (Mega)","Ludicolo","Lugia","Lumineon","Lunatone","Luvdisc","Luxio","Luxray");
		
		$Lnumber = array("333","682","719","719","172","131","710","274","411","893","412","894","510","609","167","166","503","108","577","373","616","573","292","782","681","299","468","298","322","488","896","300","277","497","365","401","438","439","000");
		
		$Ldex = array("305","608","645","645","171","131","636","246","380","380","381","381","470","542","166","165","463","108","510","345","549","506","264","667","607","271","428","428","270","294","448","448","272","249","457","337","370","404","405");
		
		$Mname = array("Machamp","Machoke","Machop","Magby","Magcargo","Magikarp","Magmar","Magmortar","Magnemite","Magneton","Magnezone","Makuhita","Malamar","Mamoswine","Manaphy","Mandibuzz","Manectric","Manectric (Mega)","Mankey","Mantine","Mantyke","Maractus","Mareep","Marill","Marowak","Marshtomp","Masquerain","Mawile","Mawile (Mega)","Medicham","Medicham (Mega)","Meditite","Meganium","Meloetta (Aria)","Meloetta (Pirouette)","Meowstic (F)","Meowstic (M)","Meowth","Mesprit","Metagross","Metagross (Mega)","Metang","Metapod","Mew","Mewtwo","Mewtwo (Armor)","Mewtwo (Mega X)","Mewtwo (Mega Y)","Mienfoo","Mienshao","Mightyena","Milotic","Miltank","Mime Jr.","Minccino","Minun","Misdreavus","Mismagius","Missingno.","Moltres","Monferno","Mothim","Mr. Mime","Mudkip","Muk","Munchlax","Munna","Murkrow","Musharna");
		
		$Mnumber = array("68","67","66","268","247","129","126","507","81","82","502","324","826","513","537","704","338","890","56","254","498","624","180","184","105","287","312","331","887","336","889","335","155","722","723","815","816","52","526","407","406","11","151","150","560","878","879","693","694","290","378","269","479","640","340","201","469","0","147","425","451","122","286","89","486","584","199","585","000");
		
		$Mdex = array("068","067","066","240","219","129","126","467","081","082","462","296","687","473","490","630","310","310","056","226","458","556","179","183","105","259","284","303","303","308","308","307","154","648","648","678","678","052","481","376","376","375","011","151","150","150","150","150","619","620","262","350","241","439","572","312","200","429","???","146","391","414","122","258","089","446","517","198","518");
		
		$Nname = array("Natu","Nidoking","Nidoqueen","Nidoran (F)","Nidoran (M)","Nidorina","Nidorino","Nincada","Ninetales","Ninjask","Noctowl","Noibat","Noivern","Nosepass","Numel","Nuzleaf");
		
		$Nnumber = array("178","34","31","29","32","30","33","318","38","319","165","859","860","327","350","302");
		
		$Ndex = array("177","034","031","029","032","030","033","290","038","291","164","714","715","299","322","274");
		
		$Oname = array("Octillery","Oddish","Omanyte","Omastar","Onix","Oshawott");
		
		$Onumber = array("252","43","139","140","95","568");
		
		$Odex = array("224","043","138","139","095","501");
		
		$Pname = array("Pachirisu","Palkia","Palpitoad","Pancham","Pangoro","Panpour","Pansage","Pansear","Paras","Parasect","Patrat","Pawniard","Pelipper","Persian","Petilil","Phanpy","Phantump","Phione","Pichu","Pichu (Notched)","Pidgeot","Pidgeot (Mega)","Pidgeotto","Pidgey","Pidove","Pignite","Pikachu","Pikachu (Belle)","Pikachu (Libre)","Pikachu (Ph. D.)","Pikachu (Pop Star)","Pikachu (Rock Star)","Piloswine","Pineco","Pinsir","Pinsir (Mega)","Piplup","Plusle","Politoed","Poliwag","Poliwhirl","Poliwrath","Ponyta","Poochyena","Porygon","Porygon-Z","Porygon2","Primeape","Prinplup","Probopass","Psyduck","Pumpkaboo (Average)","Pumpkaboo (Large)","Pumpkaboo (Small)","Pumpkaboo (Super)","Pupitar","Purrloin","Purugly","Pyroar");
		
		$Pnumber = array("454","529","603","802","803","582","578","580","46","47","571","698","307","53","615","259","847","536","173","734","18","17","16","586","566","25","249","232","127","875","427","339","187","60","61","62","77","289","138","514","261","57","428","516","54","850","275","576","472","783","000","000","000","000","000","000","000","000","000");
		
		$Pdex = array("417","484","536","674","675","515","511","513","046","047","504","624","279","053","548","231","708","489","172","172","018","018","017","016","519","499","025","025","025","025","025","025","221","204","127","127","393","311","186","060","061","062","077","261","137","474","233","057","394","476","054","710","710","710","710","247","509","432","668");
		
		$Qname = array("Quagsire","Quilladin","Quilava","Qwilfish");
		
		$Qnumber = array("196","747","157","239");
		
		$Qdex = array("195","651","156","211");
		
		$Rname = array("Raichu","Raikou","Ralts","Rampardos","Rapidash","Raticate","Rattata","Rayquaza","Rayquaza (Mega)","Regice","Regigigas","Regirock","Registeel","Relicanth","Remoraid","Reshiram","Reuniclus","Rhydon","Rhyhorn","Rhyperior","Riolu","Roggenrola","Roselia","Roserade","Rotom","Rotom (Cut)","Rotom (Frost)","Rotom (Halloween)","Rotom (Heat)","Rotom (Spin)","Rotom (Wash)","Rufflet");
		
		$Rnumber = array("26","271","308","442","78","20","19","415","409","531","408","410","400","251","717","647","112","111","504","487","591","343","440","519","522","521","725","523","520","524","701","000");
		
		$Rdex = array("026","243","280","409","078","020","019","384","384","378","486","377","379","369","223","643","579","112","111","464","447","524","315","407","479","479","479","479","479","479","479","627");
		
		$Sname = array("Sableye","Sableye (Mega)","Salamence","Salamence (Mega)","Samurott","Sandile","Sandshrew","Sandslash","Sawk","Sawsbuck (Autumn)","Sawsbuck (Spring)","Sawsbuck (Summer)","Sawsbuck (Winter)","Scatterbug","Sceptile","Sceptile (Mega)","Scizor","Scizor (Mega)","Scolipede","Scrafty","Scraggy","Scyther","Seadra","Seaking","Sealeo","Seedot","Seel","Seismitoad","Sentret","Serperior","Servine","Seviper","Sewaddle","Sharpedo","Sharpedo (Mega)","Shaymin","Shaymin (Sky)","Shedinja","Shelgon","Shellder","Shellos (East)","Shellos (West)","Shelmet","Shieldon","Shiftry","Shinx","Shroomish","Shuckle","Shuppet","Sigilyph","Silcoon","Simipour","Simisage","Simisear","Skarmory","Skiddo","Skiploom","Skitty","Skorupi","Skrelp","Skuntank","Slaking","Slakoth","Sliggoo","Slowbro","Slowbro (Mega)","Slowking","Slowpoke","Slugma","Slurpuff","Smeargle","Smoochum","Sneasel","Snivy","Snorlax","Snorunt","Snover","Snubbull","Solosis","Solrock","Spearow","Spewpa","Spheal","Spinarak","Spinda","Spiritomb","Spoink","Spritzee","Squirtle","Stantler","Staraptor","Staravia","Starly","Starmie","Staryu","Steelix","Steelix (Mega)","Stoutland","Stunfisk","Stunky","Sudowoodo","Suicune","Sunflora","Sunkern","Surskit","Swablu","Swadloon","Swalot","Swampert","Swampert (Mega)","Swanna","Swellow","Swinub","Swirlix","Swoobat","Sylveon");
		
		$Snumber = array("330","404","570","618","27","28","606","657","658","659","660","760","282","240","881","612","628","627","123","117","119","395","301","86","604","162","564","563","364","607","347","539","540","320","403","90","460","462","690","443","303","437","313","241","384","629","294","583","579","581","255","800","189","328","491","829","475","317","315","844","80","200","79","246","824","263","266","243","626","144","392","499","237","645","366","21","761","394","168","355","482","353","821","7","262","432","431","430","121","120","236","575","692","474","186","273","193","192","311","361","608","345","288","649","305","248","823","595","839","000","000","000","000","000","000","000");
		
		$Sdex = array("302","302","373","373","503","551","027","028","539","586","586","586","586","664","254","254","212","212","545","560","559","123","117","119","364","273","086","537","161","497","496","336","540","319","319","492","492","292","372","090","422","422","616","410","275","403","285","213","353","561","266","516","512","514","227","672","188","300","451","690","435","289","287","705","080","080","199","079","218","685","235","238","215","495","143","361","459","209","577","338","021","665","363","167","327","442","325","682","007","234","398","397","396","121","120","208","208","508","618","434","185","245","192","191","283","333","541","317","260","260","581","277","220","684","528","700");
		
		$Tname = array("Taillow","Talonflame","Tangela","Tangrowth","Tauros","Teddiursa","Tentacool","Tentacruel","Tepig","Terrakion","Throh","Thundurus","Thundurus (Therian)","Timburr","Tirtouga","Togekiss","Togepi","Togetic","Torchic","Torkoal","Tornadus","Tornadus (Therian)","Torterra","Totodile","Toxicroak","Tranquill","Trapinch","Treecko","Trevenant","Tropius","Trubbish","Turtwig","Tympole","Tynamo","Typhlosion","Tyranitar","Tyranitar (Mega)","Tyrantrum","Tyrogue","Tyrunt");
		
		$Tnumber = array("304","759","114","501","128","243","72","73","561","709","601","712","743","595","628","504","175","176","282","351","711","744","419","158","490","583","355","279","848","384","632","417","388","636","421","602","676","158","276","884");
		
		$Tdex = array("276","663","114","465","128","216","072","073","498","639","538","642","642","532","564","468","175","176","255","324","641","641","389","158","454","520","328","252","709","357","568","387","535","602","157","248","248","697","236","696");
		
		$Uname = array("Umbreon","Unfezant","Unown (A)","Unown (B)","Unown (C)","Unown (D)","Unown (E)","Unown (Ex)","Unown (F)","Unown (G)","Unown (H)","Unown (I)","Unown (J)","Unown (K)","Unown (L)","Unown (M)","Unown (N)","Unown (O)","Unown (P)","Unown (Q)","Unown (Qm)","Unown (R)","Unown (S)","Unown (T)","Unown (U)","Unown (V)","Unown (W)","Unown (X)","Unown (Y)","Unown (Z)","Ursaring","Uxie");
		
		$Unumber = array("198","588","202","203","204","205","206","228","207","208","209","210","211","212","213","214","215","216","217","218","229","219","220","221","222","223","224","225","226","227","245","525");
		
		$Udex = array("197","521","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","201","217","480");
		
		$Vname = array("Vanillish","Vanillite","Vanilluxe","Vaporeon","Venipede","Venomoth","Venonat","Venusaur","Venusaur (Mega)","Vespiquen","Vibrava","Victini","Victreebel","Vigoroth","Vileplume","Virizion","Vivillon (Archipelago)","Vivillon (Continental)","Vivillon (Elegant)","Vivillon (Fancy)","Vivillon (Garden)","Vivillon (High Plains)","Vivillon (Icy Snow)","Vivillon (Jungle)","Vivillon (Marine)","Vivillon (Meadow)","Vivillon (Modern)","Vivillon (Monsoon)","Vivillon (Ocean)","Vivillon (Pokeball)","Vivillon (Polar)","Vivillon (River)","Vivillon (Sandstorm)","Vivillon (Savanna)","Vivillon (Sun)","Vivillon (Tundra)","Volbeat","Volcanion","Volcarona","Voltorb","Vullaby","Vulpix");
		
		$Vnumber = array("651","650","652","135","610","49","48","3","868","453","357","561","71","316","45","714","762","763","764","781","765","766","767","768","769","770","771","772","773","774","775","776","777","778","779","780","341","866","711","100","703","37");
		
		$Vdex = array("583","582","584","134","543","049","048","003","003","416","329","494","071","288","045","640","666","666","666","666","666","666","666","666","666","666","666","666","666","666","666","666","666","666","666","666","313","720","637","100","629","037");
		
		$Wname = array("Wailmer","Wailord","Walrein","Wartortle","Watchog","Weavile","Weedle","Weepinbell","Weezing","Whimsicott","Whirlipede","Whiscash","Whismur","Wigglytuff","Wingull","Wobbuffet","Woobat","Wooper","Wormadam (Plant)","Wormadam (Sand)","Wormadam (Steel)","Wurmple","Wynaut");
		
		$Wnumber = array("348","349","396","8","572","501","13","70","110","614","611","368","321","40","306","230","594","195","448","449","450","293","391");
		
		$Wdex = array("320","321","365","008","505","461","013","070","110","547","544","340","293","040","278","202","527","194","413","413","413","265","360");
		
		$Xname = array("Xatu","Xerneas (Active)","Xerneas (Neutral)");
		
		$Xnumber = array("179","861","862");
		
		$Xdex = array("178","716","716");
		
		$Yname = array("Yamask","Yanma","Yanmega","Yveltal");
		
		$Ynumber = array("630","194","509","863");
		
		$Ydex = array("562","193","469","717");
		
		$Zname = array("Zangoose","Zapdos","Zebstrika","Zekrom","Zigzagoon","Zoroark","Zorua","Zubat","Zweilous","Zygarde");
		
		$Znumber = array("363","146","590","718","291","639","638","41","708","864");
		
		$Zdex = array("335","145","523","644","263","571","570","041","634","718");
		
		
		$alpha = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		$lcount = count($alpha);
		$num = 'number';
		$na = 'name';
		$dex = 'dex';
		for($l=0;$l<$lcount;$l++){

			$a = $alpha[$l];
			$zz = ${$a . $na};
			$zzz = ${$a . $num};
			$zzzz = ${$a . $dex};
			?>
			<div style="text-align:left;width:380px;"><h2><a name="<?=$a?>"><?=$a?></a>:</h3></div><div style="width:400px; border-bottom: 2px solid #666666;"></div>
			<?php
			
			$overall = count($zzz);
			echo"<table>";
			for($q=0;$q<$overall;$q++){

				echo "<tr><td style=\"width:70px\">#{$zzzz[$q]}</td><td style=\"width:150px;text-align:center;\"><a href=\"dex.php?pokemon={$zz[$q]}\" target=\"_BLANK\">{$zz[$q]}</a></td>";
				echo "<td style=\"width:25px;text-align;center;\">";
				if(in_array($zz[$q], $array_names)) {
					echo "<img src=\"html/static/images/misc/pb.gif\">";
				}
				else{
					echo "<img src=\"html/static/images/misc/npb.gif\">";
				}
				echo "</td><td style=\"width:25px;text-align;center;\">";



				$zal = $zz[$q]; 
				$metallicnameb = $metallic . $zal;
				if(in_array($metallicnameb, $array_names)) {
					echo "<img src=\"html/static/images/misc/pb.gif\">";
				}
				else{
					echo "<img src=\"html/static/images/misc/npb.gif\">";
				}
				echo "</td><td style=\"width:25px;text-align;center;\">";


				$zal = $zz[$q]; $shinynameb = $shiny . $zal;
				if(in_array($shinynameb, $array_names)) {
					echo "<img src=\"html/static/images/misc/pb.gif\">";
				}
				else{
					echo "<img src=\"html/static/images/misc/npb.gif\">";
				}
				echo "</td><td style=\"width:25px;text-align;center;\">";


				$zal = $zz[$q]; $darknameb = $dark . $zal;
				if(in_array($darknameb, $array_names)) {
					echo "<img src=\"html/static/images/misc/pb.gif\">";
				}
				else{
					echo "<img src=\"html/static/images/misc/npb.gif\">";
				}
				echo "</td><td style=\"width:25px;text-align;center;\">";



				$zal = $zz[$q]; $mysticnameb = $mystic . $zal;
				if(in_array($mysticnameb, $array_names)) {
					echo "<img src=\"html/static/images/misc/pb.gif\">";
				}
				else{
					echo "<img src=\"html/static/images/misc/npb.gif\">";
				}
				echo "</td><td style=\"width:25px;text-align;center;\">";

				$zal = $zz[$q]; $shadownameb = $shadow . $zal;
				if(in_array($shadownameb, $array_names)) {
					echo "<img src=\"html/static/images/misc/pb.gif\">";
				}
				else{
					echo "<img src=\"html/static/images/misc/npb.gif\">";
				}
				echo "</td></tr>";
			}
			unset($zz,$zzz);
			echo "</table>";
		}
		?>


		</center>
		<?php
	}
}
else {
	pv_redirect('login.php?goawayxP=1');
}
include(__DIR__ . '/../pv_disconnect_from_db.php'); ?>