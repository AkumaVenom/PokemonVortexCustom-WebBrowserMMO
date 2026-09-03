<?php

//--------------------------------GRASS MAPS AT NIGHT-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,76); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Smeargle","Cacnea","Pichu (Notched)","Rattata","Buneary","Oddish","Mankey","Weedle","Nidoran (M)","Carnivine","Seedot","Zigzagoon","Spearow","Ekans","Venonat","Meowth","Ponyta","Tangela","Scyther","Pinsir","Tauros","Eevee","Sentret","Hoothoot","Spinarak","Yanma","Murkrow","Girafarig","Pineco","Snubbull","Heracross","Skarmory","Phanpy","Stantler","Poochyena","Slakoth","Nincada","Whismur","Minun","Illumise","Spinda","Swablu","Zangoose","Seviper","Kecleon","Absol","Kricketot","Combee","Glameow","Stunky","Skorupi","Croagunk","Snivy","Patrat","Lillipup","Drilbur","Venipede","Maractus","Trubbish","Deerling (Autumn)","Deerling (Spring)","Deerling (Summer)","Deerling (Winter)","Foongus","Joltik","Ferroseed","Shelmet","Bouffalant","Rufflet","Durant","Bunnelby","Pancham","Inkay","Hawlucha","Dedenne","Goomy","Klefki");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("1573","2149","4381","115","2779","259","337","79","193","2947","1801","1741","127","139","289","313","463","685","739","763","769","799","967","979","1003","1159","1189","1381","1387","1417","1447","1525","1549","1567","1729","1885","1903","1921","2035","2047","2125","2161","2173","2179","2275","2317","2587","2689","2803","2821","2923","2935","3349","3403","3415","3553","3637","3721","3793","3895","3901","3907","3913","3961","3991","4003","4117","4177","4183","4213","4531","4811","4949","5039","5045","5057","5075");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("1574","2150","4382","116","2780","260","338","80","194","2948","1802","1742","128","140","290","314","464","686","740","764","770","800","968","980","1004","1160","1190","1382","1388","1418","1448","1526","1550","1568","1730","1886","1904","1922","2036","2048","2126","2162","2174","2180","2276","2318","2588","2690","2804","2822","2924","2936","3350","3404","3416","3554","3638","3722","3794","3896","3902","3908","3914","3962","3992","4004","4118","4178","4184","4214","4532","4812","4950","5040","5046","5058","5076");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//------------ Low leveled DARK Pok�mon ID's -------------//
		$array1 = array("1575","2151","4383","117","2781","261","339","81","195","2949","1803","1743","129","141","291","315","465","687","741","765","771","801","969","981","1005","1161","1191","1383","1389","1419","1449","1527","1551","1569","1731","1887","1905","1923","2037","2049","2127","2163","2175","2181","2277","2319","2589","2691","2805","2823","2925","2937","3351","3405","3417","3555","3639","3723","3795","3897","3903","3909","3915","3963","3993","4005","4119","4179","4185","4215","4533","4813","4951","5041","5047","5059","5077");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//---------------- Low leveled MYSTIC Pok�mon ID's ---------------//
		$array1 = array("1576","2152","4384","118","2782","262","340","82","196","2950","1804","1744","130","142","292","316","466","688","742","766","772","802","970","982","1006","1162","1192","1384","1390","1420","1450","1528","1552","1570","1732","1888","1906","1924","2038","2050","2128","2164","2176","2182","2278","2320","2590","2692","2806","2824","2926","2938","3352","3406","3418","3556","3640","3724","3796","3898","3904","3910","3916","3964","3994","4006","4120","4180","4186","4216","4534","4814","4952","5042","5048","5060","5078");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("1577","2153","4385","119","2783","263","341","83","197","2951","1805","1745","131","143","293","317","467","689","743","767","773","803","971","983","1007","1163","1193","1385","1391","1421","1451","1529","1553","1571","1733","1889","1907","1925","2039","2051","2129","2165","2177","2183","2279","2321","2591","2693","2807","2825","2927","2939","3353","3407","3419","3557","3641","3725","3797","3899","3905","3911","3917","3965","3995","4007","4121","4181","4187","4217","4535","4815","4953","5043","5049","5061","5079");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("1578","2154","4386","120","2784","264","342","84","198","2952","1806","1746","132","144","294","318","468","690","744","768","774","804","972","984","1008","1164","1194","1386","1392","1422","1452","1530","1554","1572","1734","1890","1908","1926","2040","2052","2130","2166","2178","2184","2280","2322","2592","2694","2808","2826","2928","2940","3354","3408","3420","3558","3642","3726","3798","3900","3906","3912","3918","3966","3996","4008","4122","4182","4188","4218","4536","4816","4954","5044","5050","5062","5080");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,8); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Latios","Rayquaza","Cresselia","Azelf","Uxie","Mesprit","Genesect","Yveltal");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("2449","2467","3187","3139","3127","3133","4321","5177");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------ Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("2450","2468","3188","3140","3128","3134","4322","5178");
			}
			if($rare == 9){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------- Legendary DARK Pok�mon ID's --------------//
				$array1 = array("2451","2469","3189","3141","3129","3135","4323","5179");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("2452","2470","3190","3142","3130","3136","4324","5180");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("2453","2471","3191","3143","3131","3137","4325","5181");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("2454","2472","3192","3144","3132","3138","4326","5182");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
		$level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Pikachu","Raticate","Lopunny","Weepinbell","Beedrill","Ivysaur","Butterfree","Pidgeot","Servine","Herdier","Tranquill","Fletchinder","Spewpa","Swirlix");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("151","121","2785","421","91","13","73","109","3355","3421","3499","4549","4567","4937");
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