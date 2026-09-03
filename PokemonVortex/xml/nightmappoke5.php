<?php

//--------------------------------ELECTRIC MAPS AT NIGHT-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,28); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Rattata","Spearow","Grimer","Elekid","Magnemite","Voltorb","Shinx","Electrike","Pachirisu","Hoothoot","Gulpin","Stunky","Swablu","Koffing","Blitzle","Drilbur","Audino","Trubbish","Foongus","Joltik","Ferroseed","Klink","Tynamo","Stunfisk","Rufflet","Durant","Pichu (Notched)","Dedenne","Klefki");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("115","127","529","1597","487","601","2599","2017","2701","979","2059","2821","2161","655","3511","3553","3565","3793","3961","3991","4003","4015","4033","4129","4183","4213","4381","5045","5075");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("116","128","530","1598","488","602","2600","2018","2702","980","2060","2822","2162","656","3512","3554","3566","3794","3962","3992","4004","4016","4034","4130","4184","4214","4382","5046","5076");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//------------ Low leveled MYSTIC Pok�mon ID's -------------//
		$array1 = array("118","130","532","1600","490","604","2602","2020","2704","982","2062","2824","2164","658","3514","3556","3568","3796","3964","3994","4006","4018","4036","4132","4186","4216","4384","5048","5078");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//---------------- Low leveled DARK Pok�mon ID's ---------------//
		$array1 = array("117","129","531","1599","489","603","2601","2019","2703","981","2061","2823","2163","657","3513","3555","3567","3795","3963","3993","4005","4017","4035","4131","4185","4215","4383","5047","5077");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("119","131","533","1601","491","605","2603","2021","2705","983","2063","2825","2165","659","3515","3557","3569","3797","3965","3995","4007","4019","4037","4133","4187","4217","4385","5049","5079");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("120","132","534","1602","492","606","2604","2022","2706","984","2064","2826","2166","660","3516","3558","3570","3798","3966","3996","4008","4020","4038","4134","4188","4218","4386","5050","5080");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,4); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Zapdos","Darkrai","Darkrown","Jirachi","Zekrom");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("871","3205","3325","2473","4285");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------ Legendary DARK Pok�mon ID's --------------//
				$array1 = array("873","3207","3327","2475","4287");
			}
			if($rare == 9){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------- Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("872","3206","3326","2474","4286");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("874","3208","3328","2476","4288");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("875","3209","3329","2477","4289");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("876","3210","3330","2478","4290");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
              $level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Raichu","Electabuzz","Zebstrika","Excadrill","Whirlipede");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("157","751","3517","3559","3643");
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