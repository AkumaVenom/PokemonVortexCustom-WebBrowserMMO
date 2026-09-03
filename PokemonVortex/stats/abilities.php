<?php
require'pv_connect_to_db.php';
	$pokemon = $_SESSION['aop1'][0];
	$filter = array(
		'Shiny ',
		'Dark ',
		'Mystic ',
		'Shadow ',
		'Metallic '
	);
	$with = '';
	$output = str_replace($filter, $with, $pokemon);

	$get_ability = mysql_query("SELECT * FROM
						abilities WHERE
						name = '$output'
					");
					$abilities = mysql_fetch_array($get_ability);

						if(!$abilities['ability2'] && !$abilities['ability3']){ // If the Pokemon only has one ability
							$ability = $abilities['ability1'];
						}
						elseif(!$abilities['ability2'] && $abilities['ability3'] != ''){ // If the Pokemon only has two abilities, 1 and hidden
							$get = rand(1,2);
							if($get == 1){
								$ability = $abilities['ability1'];
							}
							if($get == 2){
								$ability = $abilities['ability3'];
							}
						}
						elseif($abilities['ability2'] != '' && !$abilities['ability3']){ // If the Pokemon has two abilities and no hidden ability
							$get = rand(1,2);
							if($get == 1){
								$ability = $abilities['ability1'];
							}
							if($get == 2){
								$ability = $abilities['ability2'];
							}
						}
						else{ // If the Pokemon has three abilities
							$get = rand(1,3);
							if($get == 1){
								$ability = $abilities['ability1'];
							}
							if($get == 2){
								$ability = $abilities['ability2'];
							}
							if($get == 3){
								$ability = $abilities['ability3'];
							}
						}