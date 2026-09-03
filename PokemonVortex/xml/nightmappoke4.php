<?php

//--------------------------------WATER MAPS AT NIGHT-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,27); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Squirtle","Poliwag","Tentacool","Krabby","Staryu","Magikarp","Chinchou","Azurill","Remoraid","Wailmer","Corphish","Feebas","Luvdisc","Buizel","Shellos (East)","Shellos (West)","Mantyke","Oshawott","Panpour","Tympole","Basculin (Red Stripe)","Basculin (Blue Stripe)","Vanillite","Frillish","Alomomola","Castform (Water)","Skrelp","Carvanha");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("43","361","433","589","721","775","1021","1951","1501","2083","2209","2257","2383","2707","2737","2749","2965","3385","3469","3589","3679","4393","3877","3973","3985","4375","4973","2071");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("44","362","434","590","722","776","1022","1952","1502","2084","2210","2258","2384","2708","2738","2750","2966","3386","3470","3590","3680","4394","3878","3974","3986","4376","4974","2072");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//------------ Low leveled MYSTIC Pok�mon ID's -------------//
		$array1 = array("46","364","436","592","724","778","1024","1954","1504","2086","2212","2260","2386","2710","2740","2752","2968","3388","3472","3592","3682","4396","3880","3976","3988","4378","4976","2074");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//---------------- Low leveled DARK Pok�mon ID's ---------------//
		$array1 = array("45","363","435","591","723","777","1023","1953","1503","2085","2211","2259","2385","2709","2739","2751","2967","3387","3471","3591","3681","4395","3879","3975","3987","4377","4975","2073");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("47","365","437","593","725","779","1025","1955","1505","2087","2213","2261","2387","2711","2741","2753","2969","3389","3473","3593","3683","4397","3881","3977","3989","4379","4977","2075");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("48","366","438","594","726","780","1026","1956","1506","2088","2214","2262","2388","2712","2742","2754","2970","3390","3474","3594","3684","4398","3882","3978","3990","4380","4978","2076");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,2); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Manaphy","Phione","Suicune");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("3199","3193","1633");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------ Legendary DARK Pok�mon ID's --------------//
				$array1 = array("3201","3195","1635");
			}
			if($rare == 9){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------- Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("3200","3194","1634");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("3202","3196","1636");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("3203","3197","1637");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("3204","3198","1638");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
              $level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Wartortle","Tentacruel","Simipour");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("49","439","3475");
			$parray = $array[$g];
		}
	}
}
$parray1 = $array1[$g];
if($parray && $parray1){
	$q = count($_SESSION['your_pokemon']);

	for($i=0;$i<$q;$i++){ // Determine if you already have the encountered Pok�mon
		if($_SESSION['your_pokemon'][$i] == $parray1){
			$pb = "&lt;strong&gt;";
			$pb2 = "&lt;/strong&gt;";
			$ball = "&lt;img src=\"html/static/images/misc/pb.gif\"&gt;";
			break;
		}
	}
	$_SESSION['wb'] = $parray1;
	$_SESSION['lvl'] = $level;
	$r = rand(5,100000); // Generate a random number for the battle form
	//---------- Display the encountered Pok�mon -------------//
	echo "<foundPokemon>&lt;form name=\"{$r}\" action=\"wildbattle.php\" method=\"POST\"&gt;&lt;center&gt;&lt;img src=\"html/static/images/pokemon/$parray.gif\" width=\"96\" height=\"96\"&gt;&lt;p&gt;$pb Wild $parray appeared.$pb2 $ball&lt;/p&gt;&lt;/center&gt;&lt;p&gt;Level: $level &lt;input id=\"finding\" type=\"hidden\" name=\"wildpoke\" value=\"Battle\"&gt;&lt;input id=\"{$r}\" type=\"submit\" name=\"{$r}\" value=\"Battle!\"&gt;&lt;/p&gt;&lt;/form&gt;</foundPokemon>";
}
?>