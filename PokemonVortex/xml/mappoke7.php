<?php

//--------------------------------FIRE CAVE MAPS-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,26); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pokemon names ----------//
	$array = array("Charmander","Vulpix","Growlithe","Ponyta","Magby","Cyndaquil","Slugma","Torchic","Torkoal","Chimchar","Numel","Houndour","Solrock","Lunatone","Onix","Aron","Trapinch","Larvitar","Tepig","Pansear","Darumaka","Litwick","Heatmor","Larvesta","Castform (Fire)","Fennekin","Litleo");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("25","223","349","463","1603","931","1471","1693","2107","2521","2095","1531","2191","2185","571","1987","2131","1639","3367","3457","3703","4063","4207","4237","4363","4495","4693");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("26","224","350","464","1604","932","1472","1694","2108","2522","2096","1532","2192","2186","572","1988","2132","1640","3368","3458","3704","4064","4208","4238","4364","4496","4694");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//------------ Low leveled MYSTIC Pok�mon ID's -------------//
		$array1 = array("28","226","352","466","1606","934","1474","1696","2110","2524","2098","1534","2194","2188","574","1990","2134","1642","3370","3460","3706","4066","4210","4240","4366","4498","4696");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//---------------- Low leveled DARK Pok�mon ID's ---------------//
		$array1 = array("27","225","351","465","1605","933","1473","1695","2109","2523","2097","1533","2193","2187","573","1989","2133","1641","3369","3459","3705","4065","4209","4239","4365","4497","4695");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("29","227","353","467","1607","935","1475","1697","2111","2525","2099","1535","2195","2189","575","1991","2135","1643","3371","3461","3707","4067","4211","4241","4367","4499","4697");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("30","228","354","468","1608","936","1476","1698","2112","2526","2100","1536","2196","2190","576","1992","2136","1644","3372","3462","3708","4068","4212","4242","4368","4500","4698");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,6); // How many Pokemon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Heatran","Ho-oh","Moltres","Entei","Reshiram","Victini");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("3157","1663","877","1627","4279","3343");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------ Legendary DARK Pok�mon ID's --------------//
				$array1 = array("3159","1665","879","1629","4281","3345");
			}
			if($rare == 9){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------- Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("3158","1664","878","1628","4280","3344");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("3160","1666","880","1630","4282","3346");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("3161","1667","881","1631","4283","3347");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("3162","1668","882","1632","4284","3348");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
		$level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Magmar","Flareon","Houndoom","Charmeleon","Simisear","Lampent","Pyroar");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("757","817","1537","31","3463","4069","4699");
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