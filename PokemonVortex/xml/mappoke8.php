<?php

//--------------------------------CAVE MAPS-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,62); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Burmy (Sand)","Sandshrew","Cleffa","Zubat","Paras","Diglett","Machop","Geodude","Onix","Cubone","Rhyhorn","Kangaskhan","Ditto","Dratini","Bonsly","Unown (A)","Unown (B)","Unown (C)","Unown (D)","Unown (E)","Unown (F)","Unown (G)","Unown (H)","Unown (I)","Unown (J)","Unown (K)","Unown (L)","Unown (M)","Gligar","Shuckle","Teddiursa","Phanpy","Larvitar","Makuhita","Nosepass","Mawile","Aron","Trapinch","Bagon","Beldum","Riolu","Hippopotas","Roggenrola","Drilbur","Timburr","Tympole","Throh","Sawk","Sandile","Dwebble","Scraggy","Pawniard","Durant","Deino","Axew","Druddigon","Golett","Mienfoo","Binacle","Helioptile","Carbink","Noibat","Gible");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("2653","163","1039","247","277","301","397","445","571","625","667","691","793","883","2845","1207","1213","1219","1225","1231","1237","1243","1249","1255","1261","1267","1273","1279","1405","1441","1459","1549","1639","1939","1957","1981","1987","2131","2389","2407","2899","2911","3523","3553","3571","3589","3607","3613","3685","3727","3739","4165","4213","4219","4081","4147","4153","4135","4961","4997","5051","5153","2875");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("2654","164","1040","248","278","302","398","446","572","626","668","692","794","884","2846","1208","1214","1220","1226","1232","1238","1244","1250","1256","1262","1268","1274","1280","1406","1442","1460","1550","1640","1940","1958","1982","1988","2132","2390","2408","2900","2912","3524","3554","3572","3590","3608","3614","3686","3728","3740","4166","4214","4220","4082","4148","4154","4136","4962","4998","5052","5154","2876");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//------------ Low leveled MYSTIC Pok�mon ID's -------------//
		$array1 = array("2656","166","1042","250","280","304","400","448","574","628","670","694","796","886","2848","1210","1216","1222","1228","1234","1240","1246","1252","1258","1264","1270","1276","1282","1408","1444","1462","1552","1642","1942","1960","1984","1990","2134","2392","2410","2902","2914","3526","3556","3574","3592","3610","3616","3688","3730","3742","4168","4216","4222","4084","4150","4156","4138","4964","5000","5054","5156","2878");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//---------------- Low leveled DARK Pok�mon ID's ---------------//
		$array1 = array("2655","165","1041","249","279","303","399","447","573","627","669","693","795","885","2847","1209","1215","1221","1227","1233","1239","1245","1251","1257","1263","1269","1275","1281","1407","1443","1461","1551","1641","1941","1959","1983","1989","2133","2391","2409","2901","2913","3525","3555","3573","3591","3609","3615","3687","3729","3741","4167","4215","4221","4083","4149","4155","4137","4963","4999","5053","5155","2877");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("2657","167","1043","251","281","305","401","449","575","629","671","695","797","887","2849","1211","1217","1223","1229","1235","1241","1247","1253","1259","1265","1271","1277","1283","1409","1445","1463","1553","1643","1943","1961","1985","1991","2135","2393","2411","2903","2915","3527","3557","3575","3593","3611","3617","3689","3731","3743","4169","4217","4223","4085","4151","4157","4139","4965","5001","5055","5157","2879");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("2658","168","1044","252","282","306","402","450","576","630","672","696","798","888","2850","1212","1218","1224","1230","1236","1242","1248","1254","1260","1266","1272","1278","1284","1410","1446","1464","1554","1644","1944","1962","1986","1992","2136","2394","2412","2904","2916","3528","3558","3576","3594","3612","3618","3690","3732","3744","4170","4218","4224","4086","4152","4158","4140","4966","5002","5056","5158","2880");
	}



	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,20); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Groudon","Arceus","Regigigas","Palkia","Dialga","Deoxys","Jirachi","Registeel","Regirock","Mewtwo","Cobalion","Terrakion","Virizion","Reshiram","Zekrom","Kyurem","Genesect","Landorus","Zygarde","Diancie");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("2461","3223","3163","3151","3145","2479","2473","2437","2425","901","4249","4255","4261","4279","4285","4297","4321","4291","5183","5189");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------ Legendary DARK Pok�mon ID's --------------//
				$array1 = array("2463","3225","3165","3153","3147","2481","2475","2439","2427","903","4251","4257","4263","4281","4287","4299","4323","4293","5185","5191");
			}
			if($rare == 9){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------- Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("2462","3224","3164","3152","3146","2480","2474","2438","2426","902","4250","4256","4262","4280","4286","4298","4322","4292","5184","5190");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("2464","3226","3166","3154","3148","2482","2476","2440","2428","904","4252","4258","4264","4282","4288","4300","4324","4294","5186","5192");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("2465","3227","3167","3155","3149","2483","2477","2441","2429","905","4253","4259","4265","4283","4289","4301","4325","4295","5187","5193");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("2466","3228","3168","3156","3150","2484","2478","2442","2430","906","4254","4260","4266","4284","4290","4302","4326","4296","5188","5194");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
		$level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Steelix","Metang","Bronzong","Graveler","Golem","Onix","Pupitar","Fraxure","Boldore","Excadrill","Ferrothorn","Zweilous","Barbaracle","Heliolisk","Noivern","Vibrava","Shelgon","Machoke","Golurk","Dugtrio","Lairon","Gurdurr");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("1411","2413","2839","451","457","571","1645","4087","3529","3559","4009","4225","4967","5003","5159","2137","2395","403","4159","307","1993","3577");
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