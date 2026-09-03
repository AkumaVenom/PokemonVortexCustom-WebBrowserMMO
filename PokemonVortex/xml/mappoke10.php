<?php

//--------------------------------CAVE WATER MAPS-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,22); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Tentacool","Slowpoke","Seel","Shellder","Magikarp","Lapras","Wooper","Corsola","Mudkip","Barboach","Spheal","Clamperl","Finneon","Oshawott","Panpour","Basculin (Red Stripe)","Ducklett","Frillish","Alomomola","Castform (Water)","Clauncher","Skrelp","Relicanth");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("433","475","517","541","775","787","1165","1495","1711","2197","2341","2359","2953","3385","3469","3679","3865","3973","3985","4375","4985","4973","2377");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("434","476","518","542","776","788","1166","1496","1712","2198","2342","2360","2954","3386","3470","3680","3866","3974","3986","4376","4986","4974","2378");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//------------ Low leveled DARK Pok�mon ID's -------------//
		$array1 = array("435","477","519","543","777","789","1167","1497","1713","2199","2343","2361","2955","3387","3471","3681","3867","3975","3987","4377","4987","4975","2379");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//---------------- Low leveled MYSTIC Pok�mon ID's ---------------//
		$array1 = array("436","478","520","544","778","790","1168","1498","1714","2200","2344","2362","2956","3388","3472","3682","3868","3976","3988","4378","4988","4976","2380");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("437","479","521","545","779","791","1169","1499","1715","2201","2345","2363","2957","3389","3473","3683","3869","3977","3989","4379","4989","4977","2381");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("438","480","522","546","780","792","1170","1500","1716","2202","2346","2364","2958","3390","3474","3684","3870","3978","3990","4380","4990","4978","2382");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,3); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Kyogre","Lugia","Keldeo");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("2455","1657","4303");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------ Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("2456","1658","4304");
			}
			if($rare == 9){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------- Legendary DARK Pok�mon ID's --------------//
				$array1 = array("2457","1659","4305");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("2458","1660","4306");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("2459","1661","4307");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("2460","1662","4308");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
		$level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Dewott","Tentacruel","Gyarados","Marshtomp");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("3391","439","781","1717");
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