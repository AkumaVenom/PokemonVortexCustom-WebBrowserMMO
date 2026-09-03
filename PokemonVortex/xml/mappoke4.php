<?php

//--------------------------------WATER MAPS-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,32); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Squirtle","Psyduck","Poliwag","Tentacool","Krabby","Goldeen","Staryu","Magikarp","Totodile","Azurill","Lotad","Wingull","Surskit","Wailmer","Corphish","Feebas","Luvdisc","Piplup","Buizel","Oshawott","Panpour","Tympole","Basculin (Red Stripe)","Basculin (Blue Stripe)","Ducklett","Vanillite","Cubchoo","Alomomola","Castform (Water)","Froakie","Clauncher","Horsea","Qwilfish");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("43","325","361","433","589","709","721","775","949","1951","1783","1831","1861","2083","2209","2257","2383","2539","2707","3385","3469","3589","3679","4393","3865","3877","4099","3985","4375","4513","4985","697","1429");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("44","326","362","434","590","710","722","776","950","1952","1784","1832","1862","2084","2210","2258","2384","2540","2708","3386","3470","3590","3680","4394","3866","3878","4100","3986","4376","4514","4986","698","1430");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//------------ Low leveled MYSTIC Pok�mon ID's -------------//
		$array1 = array("46","328","364","436","592","712","724","778","952","1954","1786","1834","1864","2086","2212","2260","2386","2542","2710","3388","3472","3592","3682","4396","3868","3880","4102","3988","4378","4516","4988","700","1432");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//---------------- Low leveled DARK Pok�mon ID's ---------------//
		$array1 = array("45","327","363","435","591","711","723","777","951","1953","1785","1833","1863","2085","2211","2259","2385","2541","2709","3387","3471","3591","3681","4395","3867","3879","4101","3987","4377","4515","4987","699","1431");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("47","329","365","437","593","713","725","779","953","1955","1787","1835","1865","2087","2213","2261","2387","2543","2711","3389","3473","3593","3683","4397","3869","3881","4103","3989","4379","4517","4989","701","1433");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("48","330","366","438","594","714","726","780","954","1956","1788","1836","1866","2088","2214","2262","2388","2544","2712","3390","3474","3594","3684","4398","3870","3882","4104","3990","4380","4518","4990","702","1434");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,3); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Manaphy","Phione","Suicune","Keldeo");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("3199","3193","1633","4303");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------ Legendary DARK Pok�mon ID's --------------//
				$array1 = array("3201","3195","1635","4305");
			}
			if($rare == 9){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------- Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("3200","3194","1634","4304");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("3202","3196","1636","4306");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("3203","3197","1637","4307");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("3204","3198","1638","4308");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
              $level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Wartortle","Golduck","Tentacruel","Simipour");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("49","331","439","3475");
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