<?php

//--------------------------------GRASS MAPS-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,110); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Smeargle","Miltank","Dunsparce","Happiny","Pichu","Rattata","Buneary","Cherubi","Bulbasaur","Oddish","Mankey","Caterpie","Pidgey","Nidoran (F)","Chikorita","Turtwig","Exeggcute","Lotad","Tropius","Wurmple","Burmy (Plant)","Burmy (Steel)","Treecko","Ekans","Igglybuff","Meowth","Ponyta","Farfetchd","Doduo","Lickitung","Scyther","Tauros","Eevee","Sentret","Ledyba","Togepi","Hoppip","Aipom","Sunkern","Yanma","Girafarig","Pineco","Snubbull","Heracross","Skarmory","Phanpy","Stantler","Tyrogue","Taillow","Shroomish","Slakoth","Nincada","Whismur","Skitty","Plusle","Volbeat","Budew","Spinda","Swablu","Zangoose","Seviper","Castform","Kecleon","Starly","Bidoof","Kricketot","Combee","Glameow","Chatot","Munchlax","Snivy","Patrat","Lillipup","Pansage","Pidove","Blitzle","Drilbur","Audino","Sewaddle","Cottonee","Petilil","Maractus","Minccino","Karrablast","Foongus","Ferroseed","Shelmet","Bouffalant","Rufflet","Chespin","Bunnelby","Fletchling","Scatterbug","Litleo","Flabebe (Blue)","Flabebe (Orange)","Flabebe (Red)","Flabebe (White)","Flabebe (Yellow)","Skiddo","Pancham","Furfrou","Espurr","Spritzee","Inkay","Hawlucha","Dedenne","Goomy","Klefki","Swirlix","Bellsprout");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("1573","1609","1399","2857","1033","115","2779","2719","7","259","337","61","97","175","913","2503","613","1783","2305","1753","2647","2659","1675","139","1045","313","463","499","505","649","739","769","799","967","991","1051","1123","1141","1147","1159","1381","1387","1417","1447","1525","1549","1567","1579","1819","1873","1885","1903","1921","1963","2029","2041","3331","2125","2161","2173","2179","2269","2275","2557","2575","2587","2689","2803","2863","2893","3349","3403","3415","3445","3493","3511","3553","3565","3619","3655","3667","3721","3817","3949","3961","4003","4117","4177","4183","4477","4531","4543","4561","4693","4705","4711","4717","4723","4729","4799","4811","4823","4883","4925","4949","5039","5045","5057","5075","4937","415");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("1574","1610","1400","2858","1034","116","2780","2720","8","260","338","62","98","176","914","2504","614","1784","2306","1754","2648","2660","1676","140","1046","314","464","500","506","650","740","770","800","968","992","1052","1124","1142","1148","1160","1382","1388","1418","1448","1526","1550","1568","1580","1820","1874","1886","1904","1922","1964","2030","2042","3332","2126","2162","2174","2180","2270","2276","2558","2576","2588","2690","2804","2864","2894","3350","3404","3416","3446","3494","3512","3554","3566","3620","3656","3668","3722","3818","3950","3962","4004","4118","4178","4184","4478","4532","4544","4562","4694","4706","4712","4718","4724","4730","4800","4812","4824","4884","4926","4950","5040","5046","5058","5076","4938","416");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//------------ Low leveled DARK Pok�mon ID's -------------//
		$array1 = array("1575","1611","1401","2859","1035","117","2781","2721","9","261","339","63","99","177","915","2505","615","1785","2307","1755","2649","2661","1677","141","1047","315","465","501","507","651","741","771","801","969","993","1053","1125","1143","1149","1161","1383","1389","1419","1449","1527","1551","1569","1581","1821","1875","1887","1905","1923","1965","2031","2043","3333","2127","2163","2175","2181","2271","2277","2559","2577","2589","2691","2805","2865","2895","3351","3405","3417","3447","3495","3513","3555","3567","3621","3657","3669","3723","3819","3951","3963","4005","4119","4179","4185","4479","4533","4545","4563","4695","4707","4713","4719","4725","4731","4801","4813","4825","4885","4927","4951","5041","5047","5059","5077","4939","417");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//---------------- Low leveled MYSTIC Pok�mon ID's ---------------//
		$array1 = array("1576","1612","1402","2860","1036","118","2782","2722","10","262","340","64","100","178","916","2506","616","1786","2308","1756","2650","2662","1678","142","1048","316","466","502","508","652","742","772","802","970","994","1054","1126","1144","1150","1162","1384","1390","1420","1450","1528","1552","1570","1582","1822","1876","1888","1906","1924","1966","2032","2044","3334","2128","2164","2176","2182","2272","2278","2560","2578","2590","2692","2806","2866","2896","3352","3406","3418","3448","3496","3514","3556","3568","3622","3658","3670","3724","3820","3952","3964","4006","4120","4180","4186","4480","4534","4546","4564","4696","4708","4714","4720","4726","4732","4802","4814","4826","4886","4928","4952","5042","5048","5060","5078","4940","418");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("1577","1613","1403","2861","1037","119","2783","2723","11","263","341","65","101","179","917","2507","617","1787","2309","1757","2651","2663","1679","143","1049","317","467","503","509","653","743","773","803","971","995","1055","1127","1145","1151","1163","1385","1391","1421","1451","1529","1553","1571","1583","1823","1877","1889","1907","1925","1967","2033","2045","3335","2129","2165","2177","2183","2273","2279","2561","2579","2591","2693","2807","2867","2897","3353","3407","3419","3449","3497","3515","3557","3569","3623","3659","3671","3725","3821","3953","3965","4007","4121","4181","4187","4481","4535","4547","4565","4697","4709","4715","4721","4727","4733","4803","4815","4827","4887","4929","4953","5043","5049","5061","5079","4941","419");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("1578","1614","1404","2862","1038","120","2784","2724","12","264","342","66","102","180","918","2508","618","1788","2310","1758","2652","2664","1680","144","1050","318","468","504","510","654","744","774","804","972","996","1056","1128","1146","1152","1164","1386","1392","1422","1452","1530","1554","1572","1584","1824","1878","1890","1908","1926","1968","2034","2046","3336","2130","2166","2178","2184","2274","2280","2562","2580","2592","2694","2808","2868","2898","3354","3408","3420","3450","3498","3516","3558","3570","3624","3660","3672","3726","3822","3954","3966","4008","4122","4182","4188","4482","4536","4548","4566","4698","4710","4716","4722","4728","4734","4804","4816","4828","4888","4930","4954","5044","5050","5062","5080","4942","420");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,7); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Shaymin (Sky)","Celebi","Latias","Shaymin","Mew","Virizion","Xerneas (Active)","Tornadus");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("3217","1669","2443","3211","907","4261","5165","4267");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------ Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("3218","1670","2444","3212","908","4262","5166","4268");
			}
			if($rare == 9){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------- Legendary DARK Pok�mon ID's --------------//
				$array1 = array("3219","1671","2445","3213","909","4263","5167","4269");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("3220","1672","2446","3214","910","4264","5168","4270");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("3221","1673","2447","3215","911","4265","5169","4271");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("3222","1674","2448","3216","912","4266","5170","4272");
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

	for($i=0;$i<$q;$i++){ // Determine if you already have the encountered Pokemon
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
	echo "<foundPokemon>&lt;form name=\"{$r}\" action=\"wildbattle.php\" method=\"POST\"&gt;&lt;center&gt;&lt;tir&gt;&lt;img src=\"html/static/images/pokemon/$parray.gif\" width=\"96\" height=\"96\"&gt;&lt;/tir&gt;&lt;p&gt;$pb Wild $parray appeared.$pb2 $ball&lt;/p&gt;&lt;/center&gt;&lt;p&gt;Level: $level &lt;input id=\"finding\" type=\"hidden\" name=\"wildpoke\" value=\"Battle\"&gt;&lt;input id=\"{$r}\" type=\"submit\" name=\"{$r}\" value=\"Battle!\"&gt;&lt;/p&gt;&lt;/form&gt;</foundPokemon>";
}
?>