<?php

//--------------------------------PSYCHIC/GHOST MAPS-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,34); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Venonat","Abra","Drowzee","Mime Jr.","Porygon","Natu","Wynaut","Girafarig","Ralts","Meditite","Volbeat","Illumise","Spoink","Baltoy","Chingling","Kricketot","Drifloon","Bronzor","Exeggcute","Purrloin","Sandile","Scraggy","Sigilyph","Trubbish","Zorua","Gothita","Solosis","Foongus","Elgyem","Golett","Pawniard","Vullaby","Deino","Espurr","Inkay");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("289","379","577","2851","823","1063","2323","1381","1843","2005","2041","2047","2113","2221","2815","2587","2767","2833","613","3433","3685","3739","3751","3793","3805","3829","3847","3961","4051","4153","4165","4195","4219","4883","4949");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("290","380","578","2852","824","1064","2324","1382","1844","2006","2042","2048","2114","2222","2816","2588","2768","2834","614","3434","3686","3740","3752","3794","3806","3830","3848","3962","4052","4154","4166","4196","4220","4884","4950");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//------------ Low leveled MYSTIC Pok�mon ID's -------------//
		$array1 = array("292","382","580","2854","826","1066","2326","1384","1846","2008","2044","2050","2116","2224","2818","2590","2770","2836","616","3436","3688","3742","3754","3796","3808","3832","3850","3964","4054","4156","4168","4198","4222","4886","4952");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//---------------- Low leveled DARK Pok�mon ID's ---------------//
		$array1 = array("291","381","579","2853","825","1065","2325","1383","1845","2007","2043","2049","2115","2223","2817","2589","2769","2835","615","3435","3687","3741","3753","3795","3807","3831","3849","3963","4053","4155","4167","4197","4221","4885","4951");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("293","383","581","2855","827","1067","2327","1385","1847","2009","2045","2051","2117","2225","2819","2591","2771","2837","617","3437","3689","3743","3755","3797","3809","3833","3851","3965","4055","4157","4169","4199","4223","4887","4953");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("294","384","582","2856","828","1068","2328","1386","1848","2010","2046","2052","2118","2226","2820","2592","2772","2838","618","3438","3690","3744","3756","3798","3810","3834","3852","3966","4056","4158","4170","4200","4224","4888","4954");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,6); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Mew","Rotom","Mesprit","Azelf","Uxie","Celebi","Yveltal");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("907","3091","3133","3139","3127","1669","5177");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------ Legendary DARK Pok�mon ID's --------------//
				$array1 = array("909","3093","3135","3141","3129","1671","5179");
			}
			if($rare == 9){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------- Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("908","3092","3134","3140","3128","1670","5178");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("910","3094","3136","3142","3130","1672","5180");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("911","3095","3137","3143","3131","1673","5181");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("912","3096","3138","3144","3132","1674","5182");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
              $level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Kadabra","Bronzong","Hypno","Chimecho","Malamar","Xatu","Kirlia");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("385","2839","583","2311","4955","1069","1849");
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