<?php

//--------------------------------ELECTRIC MAPS-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,31); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Rattata","Pidgey","Grimer","Elekid","Magnemite","Voltorb","Pichu","Electrike","Pachirisu","Starly","Mareep","Dunsparce","Swablu","Burmy (Steel)","Koffing","Pidove","Blitzle","Audino","Trubbish","Emolga","Foongus","Joltik","Ferroseed","Klink","Tynamo","Stunfisk","Rufflet","Durant","Fletchling","Helioptile","Dedenne","Klefki");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("115","97","529","1597","487","601","1033","2017","2701","2557","1075","1399","2161","2659","655","3493","3511","3565","3793","3943","3961","3991","4003","4015","4033","4129","4183","4213","4543","4997","5045","5075");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("116","98","530","1598","488","602","1034","2018","2702","2558","1076","1400","2162","2660","656","3494","3512","3566","3794","3944","3962","3992","4004","4016","4034","4130","4184","4214","4544","4998","5046","5076");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//------------ Low leveled MYSTIC Pok�mon ID's -------------//
		$array1 = array("118","100","532","1600","490","604","1036","2020","2704","2560","1078","1402","2164","2662","658","3496","3514","3568","3796","3946","3964","3994","4006","4018","4036","4132","4186","4216","4546","5000","5048","5078");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//---------------- Low leveled DARK Pok�mon ID's ---------------//
		$array1 = array("117","99","531","1599","489","603","1035","2019","2703","2559","1077","1401","2163","2661","657","3495","3513","3567","3795","3945","3963","3993","4005","4017","4035","4131","4185","4215","4545","4999","5047","5077");
	}
	if($rand_num >= 680  && $rand_num < 685){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("119","101","533","1601","491","605","1037","2021","2705","2561","1079","1403","2165","2663","659","3497","3515","3569","3797","3947","3965","3995","4007","4019","4037","4133","4187","4217","4547","5001","5049","5079");
	}
	if($rand_num >= 685 && $rand_num < 690){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("120","102","534","1602","492","606","1038","2022","2706","2562","1080","1404","2166","2664","660","3498","3516","3570","3798","3948","3966","3996","4008","4020","4038","4134","4188","4218","4548","5002","5050","5080");
	}



	if($rand_num >= 782 && $rand_num <= 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,4); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Zapdos","Raikou","Thundurus","Zekrom","Genesect");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("871","1621","4273","4285","4321");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------ Legendary DARK Pok�mon ID's --------------//
				$array1 = array("873","1623","4275","4287","4323");
			}
			if($rare == 9){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------- Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("872","1622","4274","4286","4322");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("874","1624","4276","4288","4324");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("875","1625","4277","4289","4325");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("876","1626","4278","4290","4326");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
		$level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Raichu","Altaria","Electabuzz","Staravia","Pidgeotto","Zebstrika");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("157","2167","751","2563","103","3517");
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