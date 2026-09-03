<?php

//--------------------------------ICE CAVE MAPS AT NIGHT-------------------------------------//

if($rand_num > 664){ // Outcome number of finding a low-leveled Pok�mon
	$level = rand(5,20); // Determine the Pok�mon's level
	$g = rand(0,3); // How many Pok�mon are on the map and picking one
	                 // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
	$mystic = "Mystic ";
	$shiny = "Shiny ";
	$dark = "Dark ";
	$metallic = "Metallic ";
	$shadow = "Shadow ";
	//--------- Low leveled Pok�mon names ----------//
	$array = array("Unown (N)","Unown (O)","Unown (P)","Unown (Q)","Unown (R)","Unown (S)","Unown (T)","Unown (U)","Unown (V)","Unown (W)","Unown (X)","Unown (Y)","Unown (Z)","Unown (Ex)","Unown (Qm)","Seel","Smoochum","Sneasel","Swinub","Delibird","Snorunt","Spheal","Snover","Zubat","Slowpoke","Wynaut","Bronzor","Vanillite","Cubchoo","Cryogonal","Castform (Ice)","Bergmite");
	//---------- Low leveled NORMAL Pok�mon ID's ------------//
	$array1 = array("1285","1291","1297","1303","1309","1315","1321","1327","1333","1339","1345","1351","1357","1363","1369","517","1591","1453","1483","1513","2329","2341","2971","247","475","2323","2833","3877","4099","4111","4369","5141");
	$parray = $array[$g];
	if($rand_num >= 665 && $rand_num < 670){ // Determine if the Pok�mon is Shiny
		$parray = $shiny . $parray;
		//---------- Low leveled SHINY Pok�mon ID's ---------------//
		$array1 = array("1286","1292","1298","1304","1310","1316","1322","1328","1334","1340","1346","1352","1358","1364","1370","518","1592","1454","1484","1514","2330","2342","2972","248","476","2324","2834","3878","4100","4112","4370","5142");
	}
	if($rand_num >= 670 && $rand_num < 675){ // Determine if the Pok�mon is Mystic
		$parray = $mystic . $parray;
		//------------ Low leveled MYSTIC Pok�mon ID's -------------//
		$array1 = array("1288","1294","1300","1306","1312","1318","1324","1330","1336","1342","1348","1354","1360","1366","1372","520","1594","1456","1486","1516","2332","2344","2974","250","478","2326","2836","3880","4102","4114","4372","5144");
	}
	if($rand_num >= 675 && $rand_num < 680){ // Determine if the Pok�mon is Dark
		$parray = $dark . $parray;
		//---------------- Low leveled DARK Pok�mon ID's ---------------//
		$array1 = array("1287","1293","1299","1305","1311","1317","1323","1329","1335","1341","1347","1353","1359","1365","1371","519","1593","1455","1485","1515","2331","2343","2973","249","477","2325","2835","3879","4101","4113","4371","5143");
	}
	if($rand_num >= 700  && $rand_num < 705){ // Determine if the Pok�mon is Metallic
		$parray = $metallic . $parray;
		//----------------- Low leveled METALLIC Pok�mon ID's ---------------//
		$array1 = array("1289","1295","1301","1307","1313","1319","1325","1331","1337","1343","1349","1355","1361","1367","1373","521","1595","1457","1487","1517","2333","2345","2975","251","479","2327","2837","3881","4103","4115","4373","5145");
	}
	if($rand_num >= 705 && $rand_num < 710){ // Determine if the Pok�mon is Shadow
		$parray = $shadow . $parray;
		//------------------ Low leveled SHADOW Pok�mon ID's ---------------//
		$array1 = array("1290","1296","1302","1308","1314","1320","1326","1332","1338","1344","1350","1356","1362","1368","1374","522","1596","1458","1488","1518","2334","2346","2976","252","480","2328","2838","3882","4104","4116","4374","5146");
	}




	if($rand_num >= 782 && $rand_num < 784 && $_SESSION['map_preferences'][0] == 1){ // Determine if the Pok�mon is a Legendary or high leveled Pok�mon
		$rare = rand(1,25);
		$g = rand(0,5); // How many Pok�mon are on the map and determine one
	                    // This must be changed if you add or remove Pok�mon to/from the map, the last number has to be increased or decreased by the amount of Pok�mon added
		$level = rand(50,75); // Determine the Pok�mon's level
		if($rare <= 12){
			$array = array("Articuno","Suicune","Lugia","Regice","Kyurem", "Diancie");
			//------------- Legendary NORMAL Pok�mon ID's ---------------//
			$array1 = array("865","1633","1657","2431","4297","5189");
			$parray = $array[$g];
			if($rare == 10){ // Determine if the Legendary is Dark
				$parray = $dark . $parray;
				//------------ Legendary DARK Pok�mon ID's --------------//
				$array1 = array("867","1635","1659","2433","4299","5191");
			}
			if($rare == 9){ // Determine if the Legendary is Shiny
				$parray = $shiny . $parray;
				//------------- Legendary SHINY Pok�mon ID's --------------//
				$array1 = array("866","1634","1658","2432","4298","5190");
			}
			if($rare == 8){ // Determine if the Legendary is Mystic
				$parray = $mystic . $parray;
				//------------- Legendary MYSTIC Pok�mon ID's ------------//
				$array1 = array("868","1636","1660","2434","4300","5192");
			}
			if($rare == 11){ // Determine if the Legendary is Metallic
				$parray = $metallic . $parray;
				//------------ Legendary METALLIC Pok�mon ID's ------------//
				$array1 = array("869","1637","1661","2435","4301","5193");
			}
			if($rare == 12){ // Determine if the Legendary is Shadow
				$parray = $shadow . $parray;
				//------------ Legendary SHADOW Pok�mon ID's -------------//
				$array1 = array("870","1638","1662","2436","4302","5194");
			}
		}
		if($rare > 12){ // Determine if the Pok�mon is a high leveled non-legendary
		$level = rand(35,46); // Determine the Pok�mon's level
			$array = array("Dewgong","Jynx","Weavile","Golbat","Vanillish","Beartic");
			//------------ High leveled non-legendary Pok�mon ID's -----------------//
			$array1 = array("523","745","2983","253","3883","4105");
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