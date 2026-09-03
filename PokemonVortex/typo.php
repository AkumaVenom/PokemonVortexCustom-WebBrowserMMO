<?php
function convert($atype, $ty, $ty2){
	$damage = 1;
	switch($atype){
		case 'Flying':
		if($ty == "Grass" || $ty2 == "Grass"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Fighting" || $ty2 == "Fighting"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Bug" || $ty2 == "Bug"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Rock" || $ty2 == "Rock"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Electric" || $ty2 == "Electric"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Electric':
		if($ty == "Electric" || $ty2 == "Electric"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Water" || $ty2 == "Water"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Grass" || $ty2 == "Grass"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Ground" || $ty2 == "Ground"){
			$vari = $damage;
			$damage = $vari * 0;
		}
		if($ty == "Flying" || $ty2 == "Flying"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Dragon" || $ty2 == "Dragon"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Fighting':
		if($ty == "Normal" || $ty2 == "Normal"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Ice" || $ty2 == "Ice"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Poison" || $ty2 == "Poison"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Flying" || $ty2 == "Flying"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Psychic" || $ty2 == "Psychic"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Bug" || $ty2 == "Bug"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Rock" || $ty2 == "Rock"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Ghost" || $ty2 == "Ghost"){
			$vari = $damage;
			$damage = $vari * 0;
		}
		if($ty == "Dark" || $ty2 == "Dark"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		break;
		case 'Fire':
		if($ty == "Fire" || $ty2 == "Fire"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Water" || $ty2 == "Water"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Grass" || $ty2 == "Grass"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Ice" || $ty2 == "Ice"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Bug" || $ty2 == "Bug"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Rock" || $ty2 == "Rock"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Dragon" || $ty2 == "Dragon"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Grass':
		if($ty == "Fire" || $ty2 == "Fire"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Water" || $ty2 == "Water"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Grass" || $ty2 == "Grass"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Poison" || $ty2 == "Poison"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Ground" || $ty2 == "Ground"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Bug" || $ty2 == "Bug"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Flying" || $ty2 == "Flying"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Rock" || $ty2 == "Rock"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Dragon" || $ty2 == "Dragon"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Steel':
		if($ty == "Electric" || $ty2 == "Electric"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Water" || $ty2 == "Water"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Fire" || $ty2 == "Fire"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Ice" || $ty2 == "Ice"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Rock" || $ty2 == "Rock"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Psychic':
		if($ty == "Fighting" || $ty2 == "Fighting"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Poison" || $ty2 == "Poison"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Psychic" || $ty2 == "Psychic"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Dark" || $ty2 == "Dark"){
			$vari = $damage;
			$damage = $vari * 0;
		}
		break;
		case 'Ghost':
		if($ty == "Normal" || $ty2 == "Normal"){
			$vari = $damage;
			$damage = $vari * 0;
		}
		if($ty == "Psychic" || $ty2 == "Psychic"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Ghost" || $ty2 == "Ghost"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Dark" || $ty2 == "Dark"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Rock':
		if($ty == "Fire" || $ty2 == "Fire"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Ice" || $ty2 == "Ice"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Fighting" || $ty2 == "Fighting"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Ground" || $ty2 == "Ground"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Flying" || $ty2 == "Flying"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Bug" || $ty2 == "Bug"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Ground':
		if($ty == "Electric" || $ty2 == "Electric"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Fire" || $ty2 == "Fire"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Grass" || $ty2 == "Grass"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Poison" || $ty2 == "Poison"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Flying" || $ty2 == "Flying"){
			$vari = $damage;
			$damage = $vari * 0;
		}
		if($ty == "Bug" || $ty2 == "Bug"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Rock" || $ty2 == "Rock"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		break;
		case 'Poison':
		if($ty == "Grass" || $ty2 == "Grass"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Poison" || $ty2 == "Poison"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Ground" || $ty2 == "Ground"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Rock" || $ty2 == "Rock"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Ghost" || $ty2 == "Ghost"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari * 0;
		}
		break;
		case 'Dark':
		if($ty == "Fighting" || $ty2 == "Fighting"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Psychic" || $ty2 == "Psychic"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Ghost" || $ty2 == "Ghost"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Dark" || $ty2 == "Dark"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Normal':
		if($ty == "Rock" || $ty2 == "Rock"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Ghost" || $ty2 == "Ghost"){
			$vari = $damage;
			$damage = $vari * 0;
		}
		break;
		case 'Water':
		if($ty == "Fire" || $ty2 == "Fire"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Water" || $ty2 == "Water"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Grass" || $ty2 == "Grass"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Ground" || $ty2 == "Ground"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Rock" || $ty2 == "Rock"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Dragon" || $ty2 == "Dragon"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Ice':
		if($ty == "Fire" || $ty2 == "Fire"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Water" || $ty2 == "Water"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Grass" || $ty2 == "Grass"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Ice" || $ty2 == "Ice"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Ground" || $ty2 == "Ground"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Flying" || $ty2 == "Flying"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Dragon" || $ty2 == "Dragon"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Bug':
		if($ty == "Fire" || $ty2 == "Fire"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Grass" || $ty2 == "Grass"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Fighting" || $ty2 == "Fighting"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Poison" || $ty2 == "Poison"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Flying" || $ty2 == "Flying"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Psychic" || $ty2 == "Psychic"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Ghost" || $ty2 == "Ghost"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Dark" || $ty2 == "Dark"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		break;
		case 'Dragon':
		if($ty == "Steel" || $ty2 == "Steel"){
			$vari = $damage;
			$damage = $vari / 2;
		}
		if($ty == "Dragon" || $ty2 == "Dragon"){
			$vari = $damage;
			$damage = $vari * 2;
		}
		break;
	}
	return $damage;
}
?>