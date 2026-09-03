<?php

//--------------------------------PSYCHIC/GHOST MAPS AT NIGHT-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,42); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Venonat","Abra","Gastly","Drowzee","Hoothoot","Porygon","Spinarak","Natu","Murkrow","Misdreavus","Girafarig","Poochyena","Sableye","Volbeat","Illumise","Baltoy","Shuppet","Duskull","Drifloon","Bronzor","Spiritomb","Purrloin","Munna","Woobat","Sigilyph","Yamask","Zorua","Gothita","Solosis","Foongus","Elgyem","Litwick","Golett","Pawniard","Deino","Espurr","Phantump","Pumpkaboo (Average)","Inkay","Honedge","Pumpkaboo (Large)","Pumpkaboo (Super)","Pumpkaboo (Small)");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("289","379","553","577","979","823","1003","1063","1189","1201","1381","1729","1975","2041","2047","2221","2281","2293","2767","2833","2869","3433","3481","3541","3751","3757","3805","3829","3847","3961","4051","4063","4153","4165","4219","4883","5081","5099","4949","4901","5105","5111","5093");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("290","380","554","578","980","824","1004","1064","1190","1202","1382","1730","1976","2042","2048","2222","2282","2294","2768","2834","2870","3434","3482","3542","3752","3758","3806","3830","3848","3962","4052","4064","4154","4166","4220","4884","5082","5100","4950","4902","5106","5112","5094");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//------------ Low leveled MYSTIC Pok�mon ID's -------------//
		$array1 = array("292","382","556","580","982","826","1006","1066","1192","1204","1384","1732","1978","2044","2050","2224","2284","2296","2770","2836","2872","3436","3484","3544","3754","3760","3808","3832","3850","3964","4054","4066","4156","4168","4222","4886","5084","5102","4952","4904","5108","5114","5096");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//---------------- Low leveled DARK Pok�mon ID's ---------------//
		$array1 = array("291","381","555","579","981","825","1005","1065","1191","1203","1383","1731","1977","2043","2049","2223","2283","2295","2769","2835","2871","3435","3483","3543","3753","3759","3807","3831","3849","3963","4053","4065","4155","4167","4221","4885","5083","5101","4951","4903","5107","5113","5095");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("293","383","557","581","983","827","1007","1067","1193","1205","1385","1733","1979","2045","2051","2225","2285","2297","2771","2837","2873","3437","3485","3545","3755","3761","3809","3833","3851","3965","4055","4067","4157","4169","4223","4887","5085","5103","4953","4905","5109","5115","5097");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("294","384","558","582","984","828","1008","1068","1194","1206","1386","1734","1980","2046","2052","2226","2286","2298","2772","2838","2874","3438","3486","3546","3756","3762","3810","3834","3852","3966","4056","4068","4158","4170","4224","4888","5086","5104","4954","4906","5110","5116","5098");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,4); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Giratina","Rotom","Darkrown","Darkrai","Yveltal");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("3169","3091","3325","3205","5177");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------ Legendary DARK Pok�mon ID's --------------//
				$array1 = array("3171","3093","3327","3207","5179");
			}
			if($rare == 9){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------- Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("3170","3092","3326","3206","5178");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("3172","3094","3328","3208","5180");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("3173","3095","3329","3209","5181");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("3174","3096","3330","3210","5182");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
              $level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Haunter","Kadabra","Bronzong","Hypno","Noctowl","Venomoth","Ariados","Dusclops","Malamar");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("559","385","2839","583","985","295","1009","2299","4955");
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