<?php
declare(strict_types=1);

/**
 * v22.6.0 authoritative full source-generation Sidequest catalog.
 *
 * Kanto preserves the recovered 10 May 2016 roster. The later source-generation
 * campaigns preserve the recovered progression boundaries, milestone rewards,
 * landmark legendary encounters and regional identity while filling ordinary
 * missing trainer rows deterministically from locally validated Pokémon data.
 * The complete recovered generation ends after Hoenn at progress 697.
 */
function pv_sidequest_kanto_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) return $catalog;
    $catalog = [
        1 => ['trainer'=>'Schoolgirl Daisy','place'=>'Pallet Town','roster'=>['Pikachu','Caterpie','Eevee'],'levels'=>[100,100,100]],
        2 => ['trainer'=>'Scoolboy Jack','place'=>'Pallet Town','roster'=>['Caterpie','Metapod','Pikachu'],'levels'=>[100,100,100]],
        3 => ['trainer'=>'School kid Danny','place'=>'Route 1','roster'=>['Pidgey','Rattata'],'levels'=>[100,100]],
        4 => ['trainer'=>'Cool Trainer Quinn','place'=>'Route 1','roster'=>['Ivysaur','Staryu','Rattata','Pidgey'],'levels'=>[100,100,100,100]],
        5 => ['trainer'=>'Pokémon Trainer Cal','place'=>'Viridian City','roster'=>['Pikachu','Pidgey','Butterfree'],'levels'=>[100,100,100]],
        6 => ['trainer'=>'Pokémon Trainer Chris','place'=>'Viridian City','roster'=>['Caterpie','Pidgey','Rattata','Pikachu'],'levels'=>[100,100,100,100]],
        7 => ['trainer'=>'Bug Catcher Rick','place'=>'Viridian Forest','roster'=>['Caterpie','Weedle','Metapod','Shiny Butterfree'],'levels'=>[100,100,100,100]],
        8 => ['trainer'=>'Bug Catcher Anthony','place'=>'Viridian Forest','roster'=>['Weedle','Caterpie','Kakuna','Shiny Beedrill'],'levels'=>[100,100,100,100]],
        9 => ['trainer'=>'Camper Joseph','place'=>'Route 2','roster'=>['Diglett','Shiny Diglett','Dugtrio'],'levels'=>[100,100,100]],
        10 => ['trainer'=>'Pokémon Trainer Lucinda','place'=>'Route 2','roster'=>['Nidoran (M)','Nidoran (F)','Mankey'],'levels'=>[100,100,100]],
        11 => ['trainer'=>'Camper Jerry','place'=>'Pewter City','roster'=>['Dark Sandshrew','Mystic Diglett','Shadow Geodude'],'levels'=>[100,100,100]],
        12 => ['trainer'=>'Camper Liam','place'=>'Pewter City','roster'=>['Geodude','Graveler','Golem'],'levels'=>[100,100,100]],
        13 => ['trainer'=>'Hiker Teddy','place'=>'Route 3','roster'=>['Mystic Graveler','Mystic Golem','Mystic Dugtrio','Mystic Onix'],'levels'=>[100,100,100,100]],
        14 => ['trainer'=>'Bird Keeper Hank','place'=>'Route 3','roster'=>['Pidgeotto','Metallic Pidgeotto','Shiny Pidgeot'],'levels'=>[100,100,100]],
        15 => ['trainer'=>'Picknicker Juliette','place'=>'Mt. Moon','roster'=>['Jigglypuff','Wigglytuff','Nidoran (F)','Dark Wigglytuff'],'levels'=>[100,100,100,100]],
        16 => ['trainer'=>'Scientist Albert','place'=>'Mt. Moon','roster'=>['Magnemite','Shiny Magnemite','Magneton','Dark Magneton','Metallic Magneton'],'levels'=>[100,100,100,100,100]],
        17 => ['trainer'=>'Pokémon Trainer Kristen','place'=>'Route 4','roster'=>['Clefairy','Shiny Clefairy','Clefable','Shiny Clefable'],'levels'=>[100,100,100,100]],
        18 => ['trainer'=>'Picknicker Anita','place'=>'Route 4','roster'=>['Voltorb','Dark Voltorb','Dark Clefairy'],'levels'=>[100,100,100]],
        19 => ['trainer'=>'Swimmer Kevin','place'=>'Cerulean City','roster'=>['Goldeen','Seaking','Horsea','Shiny Seaking'],'levels'=>[100,100,100,100]],
        20 => ['trainer'=>'Pokémon Trainer Shelly','place'=>'Cerulean City','roster'=>['Staryu','Shiny Staryu','Starmie','Mystic Starmie','Dark Starmie'],'levels'=>[100,100,100,100,100]],
        21 => ['trainer'=>'Lass Crissy','place'=>'Route 24','roster'=>['Paras','Dark Paras','Parasect','Metallic Parasect'],'levels'=>[100,100,100,100]],
        22 => ['trainer'=>'Bug Catcher Cale','place'=>'Route 24','roster'=>['Caterpie','Weedle','Dark Metapod','Shiny Kakuna','Mystic Beedrill'],'levels'=>[100,100,100,100,100]],
        23 => ['trainer'=>'Hiker Franklin','place'=>'Route 25','roster'=>['Machop','Geodude','Shiny Machoke','Mystic Graveler','Dark Machamp'],'levels'=>[100,100,100,100,100]],
        24 => ['trainer'=>'Picknicker Kelsey','place'=>'Route 25','roster'=>['Nidoran (M)','Nidoran (F)','Dark Nidorino','Nidorina','Metallic Nidoking','Metallic Nidoqueen'],'levels'=>[100,100,100,100,100,100]],
        25 => ['trainer'=>'Camper Ricky','place'=>'Route 6','roster'=>['Squirtle','Pikachu','Rattata','Dark Raticate','Shiny Raichu'],'levels'=>[100,100,100,100,100]],
        26 => ['trainer'=>'Camper Jeff','place'=>'Route 6','roster'=>['Spearow','Rattata','Dark Fearow','Dark Raticate'],'levels'=>[100,100,100,100]],
        27 => ['trainer'=>'Gamer Hugo','place'=>'Route 11','roster'=>['Ekans','Poliwag','Horsea','Dark Arbok','Dark Poliwhirl'],'levels'=>[100,100,100,100,100]],
        28 => ['trainer'=>'Youngster Dillon','place'=>'Route 11','roster'=>['Sandshrew','Zubat','Shiny Sandslash','Dark Golbat','Mystic Sandslash'],'levels'=>[100,100,100,100,100]],
        29 => ['trainer'=>'Fisherman Barny','place'=>'S.S. Anne','roster'=>['Tentacool','Staryu','Shellder','Shiny Tentacruel','Shiny Starmie','Shiny Cloyster'],'levels'=>[100,100,100,100,100,100]],
        30 => ['trainer'=>'Gentleman Arthur','place'=>'S.S. Anne','roster'=>['Growlithe','Nidoran (F)','Nidoran (M)','Dark Nidorino','Metallic Arcanine'],'levels'=>[100,100,100,100,100]],
        31 => ['trainer'=>'Engineer Baily','place'=>'Vermillion City','roster'=>['Voltorb','Magnemite','Dark Voltorb','Dark Magneton','Metallic Electrode'],'levels'=>[100,100,100,100,100]],
        32 => ['trainer'=>'Gentleman Tucker','place'=>'Vermillion City','roster'=>['Pikachu','Voltorb','Magneton','Dark Electrode','Mystic Raichu'],'levels'=>[100,100,100,100,100]],
        33 => ['trainer'=>'Picknicker Alecia','place'=>'Route 9','roster'=>['Raticate','Shiny Fearow','Pidgeotto','Dark Primeape'],'levels'=>[100,100,100,100]],
        34 => ['trainer'=>'Hiker Jeremy','place'=>'Route 9','roster'=>['Shadow Onix','Shadow Graveler','Shadow Golem'],'levels'=>[100,100,100]],
        35 => ['trainer'=>'Picknicker Heidi','place'=>'Route 10','roster'=>['Shiny Voltorb','Shiny Pikachu','Dark Electrode','Mystic Raticate','Dark Wigglytuff'],'levels'=>[100,100,100,100,100]],
        36 => ['trainer'=>'Pokémon Herman','place'=>'Route 10','roster'=>['Magnemite','Shiny Magneton','Mystic Graveler','Dark Golem'],'levels'=>[100,100,100,100]],
        37 => ['trainer'=>'PKMN Breeder Sherman','place'=>'Rock Tunnel','roster'=>['Geodude','Rattata','Shiny Pikachu','Magnemite','Clefairy','Dark Nidoran (M)'],'levels'=>[100,100,100,100,100,100]],
        38 => ['trainer'=>'Pokémon Cooper','place'=>'Rock Tunnel','roster'=>['Slowpoke','Dark Slowpoke','Shiny Slowbro','Dark Slowbro','Metallic Slowbro'],'levels'=>[100,100,100,100,100]],
        39 => ['trainer'=>'Super Nerd Glenn','place'=>'Route 8','roster'=>['Grimer','Muk','Shiny Grimer','Shiny Muk'],'levels'=>[100,100,100,100]],
        40 => ['trainer'=>'Lass Megan','place'=>'Route 8','roster'=>['Pidgeotto','Shiny Raticate','Nidoran (M)','Dark Meowth','Mystic Pikachu'],'levels'=>[100,100,100,100,100]],
        41 => ['trainer'=>'Beauty Bridget','place'=>'Celadon City','roster'=>['Oddish','Bellsprout','Shiny Bulbasaur','Dark Weepinbell','Mystic Ivysaur'],'levels'=>[100,100,100,100,100]],
        42 => ['trainer'=>'Cool Trainer Mary','place'=>'Celadon City','roster'=>['Victreebel','Dark Tangela','Ivysaur','Mystic Vileplume'],'levels'=>[100,100,100,100]],
        43 => ['trainer'=>'Team Rocket Mike','place'=>'Team Rocket Hideout','roster'=>['Rattata','Raticate','Dark Drowzee','Shiny Arbok'],'levels'=>[100,100,100,100]],
        44 => ['trainer'=>'Team Rocket John','place'=>'Team Rocket Hideout','roster'=>['Shiny Onix','Arbok','Koffing','Dark Kangaskhan'],'levels'=>[100,100,100,100]],
        45 => ['trainer'=>'Channeler Angelica','place'=>'Pokemon Tower','roster'=>['Gastly','Gastly','Shiny Haunter','Dark Haunter','Mystic Gengar'],'levels'=>[100,100,100,100,100]],
        46 => ['trainer'=>'Team Rocket Ben','place'=>'Pokemon Tower','roster'=>['Gastly','Drowzee','Shiny Golbat','Shiny Raticate','Koffing'],'levels'=>[100,100,100,100,100]],
        47 => ['trainer'=>'Biker Ruben','place'=>'Route 16','roster'=>['Shiny Weezing','Mankey','Machoke','Shiny Primeape'],'levels'=>[100,100,100,100]],
        48 => ['trainer'=>'Cue Ball Camron','place'=>'Route 16','roster'=>['Koffing','Mystic Machoke','Primeape'],'levels'=>[100,100,100]],
        49 => ['trainer'=>'Biker Nikolas','place'=>'Route 17','roster'=>['Voltorb','Machoke','Dark Weezing','Dark Electrode'],'levels'=>[100,100,100,100]],
        50 => ['trainer'=>'Biker Jaxon','place'=>'Route 17','roster'=>['Weezing','Shadow Muk','Primeape','Machoke','Shadow Machamp'],'levels'=>[100,100,100,100,100]],
        51 => ['trainer'=>'Bird Keeper Ramiro','place'=>'Route 18','roster'=>['Spearow','Doduo','Fearow','Metallic Fearow','Dodrio'],'levels'=>[100,100,100,100,100]],
        52 => ['trainer'=>'Bird Keeper Wilton','place'=>'Route 18','roster'=>['Pidgeotto','Fearow','Dodrio','Shadow Pidgeot','Mystic Dodrio'],'levels'=>[100,100,100,100,100]],
        53 => ['trainer'=>'Juggler Kirk','place'=>'Fuchsia City','roster'=>['Abra','Drowzee','Dark Kadabra','Mystic Hypno'],'levels'=>[100,100,100,100]],
        54 => ['trainer'=>'Tamer Edgar','place'=>'Fuchsia City','roster'=>['Koffing','Grimer','Dark Weezing','Shiny Muk','Dark Venomoth'],'levels'=>[100,100,100,100,100]],
        55 => ['trainer'=>'Black Belt Hitoshi','place'=>'Fighting Dojo','roster'=>['Machoke','Primeape','Mystic Machamp','Hitmonchan'],'levels'=>[100,100,100,100]],
        56 => ['trainer'=>'Black Belt Aaron','place'=>'Fighting Dojo','roster'=>['Mystic Machoke','Primeape','Dark Machamp','Hitmonchan','Shiny Hitmonlee'],'levels'=>[100,100,100,100,100]],
        57 => ['trainer'=>'Team Rocket Jose','place'=>'Silph Co. Building','roster'=>['Cubone','Shiny Nidorino','Marowak','Shiny Nidorina','Rhyhorn'],'levels'=>[100,100,100,100,100]],
        58 => ['trainer'=>'Team Rocket Nathan','place'=>'Silph Co. Building','roster'=>['Nidorino','Nidorina','Shiny Kangaskhan','Dark Nidoqueen','Dark Nidoking'],'levels'=>[100,100,100,100,100]],
        59 => ['trainer'=>'Psychic Johan','place'=>'Saffron City','roster'=>['Kadabra','Slowpoke','Dark Haunter','Mystic Venonat'],'levels'=>[100,100,100,100]],
        60 => ['trainer'=>'Psychic Preston','place'=>'Saffron City','roster'=>['Shiny Kadabra','Mr. Mime','Venomoth','Mystic Slowbro','Dark Kadabra','Alakazam'],'levels'=>[100,100,100,100,100,100]],
        61 => ['trainer'=>'Fisherman Suarez','place'=>'Route 12','roster'=>['Poliwag','Poliwhirl','Shadow Cloyster','Shadow Seaking','Seadra'],'levels'=>[100,100,100,100,100]],
        62 => ['trainer'=>'Camper Justin','place'=>'Route 12','roster'=>['Nidoran (M)','Dark Nidorino','Shadow Electrode','Mystic Magikarp','Gyarados'],'levels'=>[100,100,100,100,100]],
        63 => ['trainer'=>'Picknicker Susie','place'=>'Route 13','roster'=>['Pidgey','Meowth','Dark Pikachu','Rattata','Shiny Persian','Shadow Raichu'],'levels'=>[100,100,100,100,100,100]],
        64 => ['trainer'=>'Bird Keeper Robert','place'=>'Route 13','roster'=>['Pidgeotto','Fearow','Shiny Dodrio','Dark Pidgeot'],'levels'=>[100,100,100,100]],
        65 => ['trainer'=>'Biker Isaac','place'=>'Route 14','roster'=>['Grimer','Shadow Muk','Mystic Venonat','Koffing','Dark Weezing'],'levels'=>[100,100,100,100,100]],
        66 => ['trainer'=>'Twins Armando & Julio','place'=>'Route 14','roster'=>['Wartortle','Mystic Charmeleon','Blastoise','Shadow Blastoise','Charizard','Shadow Charizard'],'levels'=>[100,100,100,100,100,100]],
        67 => ['trainer'=>'Black Belt Ron','place'=>'Route 15','roster'=>['Hitmonlee','Hitmonchan','Dark Hitmonchan','Dark Hitmonlee','Mystic Machamp'],'levels'=>[100,100,100,100,100]],
        68 => ['trainer'=>'Picknicker Kindra','place'=>'Route 15','roster'=>['Gloom','Oddish','Ivysaur','Dark Gloom','Shadow Venusaur'],'levels'=>[100,100,100,100,100]],
        69 => ['trainer'=>'Swimmer David','place'=>'Route 19','roster'=>['Horsea','Shadow Seadra','Shadow Seaking','Tentacool'],'levels'=>[100,100,100,100]],
        70 => ['trainer'=>'Swimmer Matthew','place'=>'Route 19','roster'=>['Poliwhirl','Shiny Poliwhirl','Poliwrath','Dark Poliwrath'],'levels'=>[100,100,100,100]],
        71 => ['trainer'=>'Swimmer Shirley','place'=>'Route 20','roster'=>['Seadra','Horsea','Mystic Seaking','Cloyster','Mystic Cloyster'],'levels'=>[100,100,100,100,100]],
        72 => ['trainer'=>'Swimmer Dean','place'=>'Route 20','roster'=>['Staryu','Shiny Starmie','Shiny Seaking','Shiny Cloyster','Lapras'],'levels'=>[100,100,100,100,100]],
        73 => ['trainer'=>'Burglar Arnie','place'=>'Cinnabar Island Mansion','roster'=>['Charmeleon','Vulpix','Shiny Growlithe','Ninetales','Shiny Rapidash'],'levels'=>[100,100,100,100,100]],
        74 => ['trainer'=>'Burglar Dusty','place'=>'Cinnabar Island Mansion','roster'=>['Ponyta','Dark Rapidash','Growlithe','Dark Arcanine'],'levels'=>[100,100,100,100]],
        75 => ['trainer'=>'Burglar Ramon','place'=>'Cinnabar Island Mansion','roster'=>['Arcanine','Shadow Rapidash','Mystic Rapidash','Magmar'],'levels'=>[100,100,100,100]],
        76 => ['trainer'=>'Fisherman Ronald','place'=>'Route 21','roster'=>['Magikarp','Goldeen','Dark Seaking','Shadow Starmie','Gyarados'],'levels'=>[100,100,100,100,100]],
        77 => ['trainer'=>'Swimmer Spencer','place'=>'Route 21','roster'=>['Shellder','Tentacool','Cloyster','Dark Tentacruel','Mystic Tentacruel'],'levels'=>[100,100,100,100,100]],
        78 => ['trainer'=>'Swimmer Amara','place'=>'Route 21','roster'=>['Seel','Shiny Seel','Psyduck','Dark Dewgong','Golduck'],'levels'=>[100,100,100,100,100]],
        79 => ['trainer'=>'Camper Bryce','place'=>'Route 21','roster'=>['Nidorino','Nidorina','Shiny Clefable','Shadow Sandslash'],'levels'=>[100,100,100,100]],
        80 => ['trainer'=>'Crush Girl Tanya','place'=>'Route 21','roster'=>['Mystic Machoke','Hitmonchan','Dark Hitmonchan','Dark Hitmonlee'],'levels'=>[100,100,100,100]],
        81 => ['trainer'=>'Cool Trainer Samuel','place'=>'Viridian City','roster'=>['Sandslash','Rhyhorn','Nidorino','Nidoking','Shadow Nidoking'],'levels'=>[100,100,100,100,100]],
        82 => ['trainer'=>'Cool Trainer Yuji','place'=>'Viridian City','roster'=>['Sandslash','Mystic Graveler','Dark Onix','Marowak','Shiny Nidoqueen'],'levels'=>[100,100,100,100,100]],
        83 => ['trainer'=>'Tamer Jason','place'=>'Viridian City','roster'=>['Rhyhorn','Shiny Marowak','Mystic Dugtrio','Rhydon','Dark Rhydon'],'levels'=>[100,100,100,100,100]],
        84 => ['trainer'=>'Engineer Mason','place'=>'Power Plant','roster'=>['Pikachu','Dark Raichu','Jolteon','Shiny Magneton','Electabuzz'],'levels'=>[100,100,100,100,100]],
        85 => ['trainer'=>'Engineer Dan','place'=>'Power Plant','roster'=>['Electabuzz','Grimer','Muk','Dark Electrode','Shadow Magneton'],'levels'=>[100,100,100,100,100]],
        86 => ['trainer'=>'???','place'=>'Power Plant','roster'=>['Zapdos'],'levels'=>[120]],
        87 => ['trainer'=>'Beauty Amanda','place'=>'Seafoam Islands','roster'=>['Psyduck','Golduck','Shadow Golduck','Seel','Shadow Dewgong'],'levels'=>[100,100,100,100,100]],
        88 => ['trainer'=>'Swimmer Jason','place'=>'Seafoam Islands','roster'=>['Krabby','Kingler','Dark Kingler','Shiny Slowbro','Mystic Slowbro'],'levels'=>[100,100,100,100,100]],
        89 => ['trainer'=>'???','place'=>'Seafoam Islands','roster'=>['Articuno'],'levels'=>[120]],
        90 => ['trainer'=>'Cool Trainer Rolando','place'=>'Victory Road','roster'=>['Raticate','Ivysaur','Dark Charmeleon','Wartortle','Dark Charizard'],'levels'=>[100,100,100,100,100]],
        91 => ['trainer'=>'Juggler Nelson','place'=>'Victory Road','roster'=>['Kadabra','Shiny Drowzee','Shiny Hypno','Kadabra','Shiny Alakazam'],'levels'=>[100,100,100,100,100]],
        92 => ['trainer'=>'Cool Trainer George','place'=>'Victory Road','roster'=>['Exeggutor','Sandslash','Mystic Cloyster','Mystic Electrode','Mystic Arcanine'],'levels'=>[100,100,100,100,100]],
        93 => ['trainer'=>'Cool Trainer Colby','place'=>'Victory Road','roster'=>['Kingler','Metallic Poliwrath','Metallic Tentacruel','Seadra','Metallic Blastoise'],'levels'=>[100,100,100,100,100]],
        94 => ['trainer'=>'Cool Trainer Caroline','place'=>'Victory Road','roster'=>['Weepinbell','Metallic Victreebel','Metallic Vileplume','Parasect','Metallic Venusaur'],'levels'=>[100,100,100,100,100]],
        95 => ['trainer'=>'???','place'=>'Victory Road','roster'=>['Moltres'],'levels'=>[120]],
        96 => ['trainer'=>'Cool Trainer Alexa','place'=>'Victory Road','roster'=>['Clefable','Wigglytuff','Shadow Persian','Metallic Dewgong','Metallic Chansey'],'levels'=>[100,100,100,100,100]],
        97 => ['trainer'=>'Pokémon Dawson','place'=>'Victory Road','roster'=>['Charmeleon','Lickitung','Metallic Lapras','Shiny Charizard','Shiny Lapras'],'levels'=>[100,100,100,100,100]],
        98 => ['trainer'=>'Tamer Vincent','place'=>'Victory Road','roster'=>['Golduck','Shiny Electabuzz','Arcanine','Metallic Kadabra','Metallic Mr. Mime'],'levels'=>[100,100,100,100,100]],
        99 => ['trainer'=>'Black Belt Scott','place'=>'Victory Road','roster'=>['Machamp','Hitmonchan','Shadow Hitmonlee','Metallic Machamp','Metallic Hitmonlee'],'levels'=>[100,100,100,100,100]],
        100 => ['trainer'=>'Cool Trainer Naomi','place'=>'Victory Road','roster'=>['Persian','Metallic Rapidash','Metallic Ninetales','Shadow Magmar','Golduck'],'levels'=>[100,100,100,100,100]],
        101 => ['trainer'=>'Cool Trainer Drake','place'=>'Victory Road','roster'=>['Dratini','Dragonair','Shiny Dratini','Shiny Dragonair','Shiny Dragonite','Shadow Dragonite'],'levels'=>[100,100,100,100,100,100]],
    ];
    return $catalog;
}

function pv_sidequest_regions(): array
{
    return [
        'kanto' => ['label'=>'Kanto','battle_start'=>1,'battle_end'=>101,'reward'=>102,'map'=>'kanto.png','source'=>'Fully recovered 2016 roster'],
        'johto' => ['label'=>'Johto','battle_start'=>103,'battle_end'=>205,'reward'=>206,'map'=>'johto.png','source'=>'Source-contract reconstruction'],
        'sevii' => ['label'=>'Sevii Islands','battle_start'=>207,'battle_end'=>306,'reward'=>307,'map'=>'seviiislands.png','source'=>'Source-contract reconstruction'],
        'legendary-isles' => ['label'=>'Legendary Isles','battle_start'=>308,'battle_end'=>311,'reward'=>null,'map'=>'navelrock.png','source'=>'Recovered Navel Rock / Birth Island sequence'],
        'tcg' => ['label'=>'TCG Island','battle_start'=>312,'battle_end'=>361,'reward'=>362,'map'=>'tcg.png','source'=>'Source-contract reconstruction'],
        'orange' => ['label'=>'Orange Islands','battle_start'=>363,'battle_end'=>482,'reward'=>483,'map'=>'orangeislands.png','source'=>'Source-contract reconstruction'],
        'hoenn' => ['label'=>'Hoenn','battle_start'=>484,'battle_end'=>695,'reward'=>696,'map'=>'hoenn.png','source'=>'Source-contract reconstruction'],
    ];
}

function pv_sidequest_reward_milestones(): array
{
    return [
        102 => ['key'=>'kantoprize','region'=>'Kanto','next'=>103,'pool'=>[
            ['column'=>'Helix_Fossil','quantity'=>1,'label'=>'a Helix Fossil','image'=>'Helix Fossil.png'],
            ['column'=>'Dome_Fossil','quantity'=>1,'label'=>'a Dome Fossil','image'=>'Dome Fossil.png'],
            ['column'=>'Old_Amber','quantity'=>1,'label'=>'an Old Amber','image'=>'Old Amber.png'],
        ]],
        206 => ['key'=>'johtoprize','region'=>'Johto','next'=>207,'pool'=>[
            ['column'=>'Master_Ball','quantity'=>50,'label'=>'50 Master Balls','image'=>'Master Ball.png'],
            ['column'=>'Latiasite','quantity'=>1,'label'=>'a Latiasite','image'=>'Latiasite.png'],
            ['column'=>'Latiosite','quantity'=>1,'label'=>'a Latiosite','image'=>'Latiosite(1).png'],
        ]],
        307 => ['key'=>'seviiislandsprize','region'=>'Sevii Islands','next'=>308,'pool'=>[
            ['column'=>'Master_Ball','quantity'=>50,'label'=>'50 Master Balls','image'=>'Master Ball.png'],
            ['column'=>'Latiosite','quantity'=>1,'label'=>'a Latiosite','image'=>'Latiosite(1).png'],
            ['column'=>'Latiasite','quantity'=>1,'label'=>'a Latiasite','image'=>'Latiasite.png'],
        ]],
        362 => ['key'=>'tcgprize','region'=>'TCG Island','next'=>363,'pool'=>[
            ['column'=>'Master_Ball','quantity'=>10,'label'=>'10 Master Balls','image'=>'Master Ball.png'],
            ['column'=>'Master_Ball','quantity'=>20,'label'=>'20 Master Balls','image'=>'Master Ball.png'],
            ['column'=>'Master_Ball','quantity'=>30,'label'=>'30 Master Balls','image'=>'Master Ball.png'],
        ]],
        483 => ['key'=>'orangeislandprize','region'=>'Orange Islands','next'=>484,'pool'=>[
            ['column'=>'Master_Ball','quantity'=>10,'label'=>'10 Master Balls','image'=>'Master Ball.png'],
            ['column'=>'Master_Ball','quantity'=>20,'label'=>'20 Master Balls','image'=>'Master Ball.png'],
            ['column'=>'Master_Ball','quantity'=>30,'label'=>'30 Master Balls','image'=>'Master Ball.png'],
        ]],
        696 => ['key'=>'hoennprize','region'=>'Hoenn','next'=>697,'pool'=>[
            ['column'=>'Master_Ball','quantity'=>10,'label'=>'10 Master Balls','image'=>'Master Ball.png'],
            ['column'=>'Blue_Orb','quantity'=>1,'label'=>'a Blue Orb','image'=>'Blue Orb.png'],
            ['column'=>'Red_Orb','quantity'=>1,'label'=>'a Red Orb','image'=>'Red Orb.png'],
            ['column'=>'Root_Fossil','quantity'=>1,'label'=>'a Root Fossil','image'=>'Root Fossil.png'],
            ['column'=>'Claw_Fossil','quantity'=>1,'label'=>'a Claw Fossil','image'=>'Claw Fossil.png'],
        ]],
    ];
}

function pv_sidequest_reserved_progress_ids(): array { return array_keys(pv_sidequest_reward_milestones()); }

function pv_sidequest_johto_pool(): array
{
    return preg_split('/\s+/', trim('Chikorita Bayleef Meganium Cyndaquil Quilava Typhlosion Totodile Croconaw Feraligatr Sentret Furret Hoothoot Noctowl Ledyba Ledian Spinarak Ariados Crobat Chinchou Lanturn Pichu Cleffa Igglybuff Togepi Togetic Natu Xatu Mareep Flaaffy Ampharos Bellossom Marill Azumarill Sudowoodo Politoed Hoppip Skiploom Jumpluff Aipom Sunkern Sunflora Yanma Wooper Quagsire Espeon Umbreon Murkrow Slowking Misdreavus Wobbuffet Girafarig Pineco Forretress Dunsparce Gligar Steelix Snubbull Granbull Qwilfish Scizor Shuckle Heracross Sneasel Teddiursa Ursaring Slugma Magcargo Swinub Piloswine Corsola Remoraid Octillery Delibird Mantine Skarmory Houndour Houndoom Kingdra Phanpy Donphan Porygon2 Stantler Smeargle Tyrogue Hitmontop Smoochum Elekid Magby Miltank Blissey Larvitar Pupitar Tyranitar')) ?: [];
}

function pv_sidequest_kanto_common_pool(): array
{
    return preg_split('/\s+/', trim('Bulbasaur Ivysaur Venusaur Charmander Charmeleon Charizard Squirtle Wartortle Blastoise Caterpie Metapod Butterfree Weedle Kakuna Beedrill Pidgey Pidgeotto Pidgeot Rattata Raticate Spearow Fearow Ekans Arbok Pikachu Raichu Sandshrew Sandslash Clefairy Clefable Vulpix Ninetales Jigglypuff Wigglytuff Zubat Golbat Oddish Gloom Vileplume Paras Parasect Venonat Venomoth Diglett Dugtrio Meowth Persian Psyduck Golduck Mankey Primeape Growlithe Arcanine Poliwag Poliwhirl Poliwrath Abra Kadabra Alakazam Machop Machoke Machamp Bellsprout Weepinbell Victreebel Tentacool Tentacruel Geodude Graveler Golem Ponyta Rapidash Slowpoke Slowbro Magnemite Magneton Doduo Dodrio Seel Dewgong Grimer Muk Shellder Cloyster Gastly Haunter Gengar Drowzee Hypno Krabby Kingler Voltorb Electrode Exeggcute Exeggutor Cubone Marowak Hitmonlee Hitmonchan Lickitung Koffing Weezing Rhyhorn Rhydon Chansey Tangela Kangaskhan Horsea Seadra Goldeen Seaking Staryu Starmie Scyther Jynx Electabuzz Magmar Pinsir Tauros Magikarp Gyarados Lapras Eevee Vaporeon Jolteon Flareon Porygon Omanyte Omastar Kabuto Kabutops Aerodactyl Snorlax Dratini Dragonair Dragonite')) ?: [];
}

function pv_sidequest_hoenn_pool(): array
{
    return preg_split('/\s+/', trim('Treecko Grovyle Sceptile Torchic Combusken Blaziken Mudkip Marshtomp Swampert Poochyena Mightyena Zigzagoon Linoone Wurmple Silcoon Beautifly Cascoon Dustox Lotad Lombre Ludicolo Seedot Nuzleaf Shiftry Taillow Swellow Wingull Pelipper Ralts Kirlia Gardevoir Surskit Masquerain Shroomish Breloom Slakoth Vigoroth Slaking Nincada Ninjask Shedinja Whismur Loudred Exploud Makuhita Hariyama Azurill Nosepass Skitty Delcatty Sableye Mawile Aron Lairon Aggron Meditite Medicham Electrike Manectric Plusle Minun Volbeat Illumise Roselia Gulpin Swalot Carvanha Sharpedo Wailmer Wailord Numel Camerupt Torkoal Spoink Grumpig Spinda Trapinch Vibrava Flygon Cacnea Cacturne Swablu Altaria Zangoose Seviper Lunatone Solrock Barboach Whiscash Corphish Crawdaunt Baltoy Claydol Lileep Cradily Anorith Armaldo Feebas Milotic Castform Kecleon Shuppet Banette Duskull Dusclops Tropius Chimecho Absol Wynaut Snorunt Glalie Spheal Sealeo Walrein Clamperl Huntail Gorebyss Relicanth Luvdisc Bagon Shelgon Salamence Beldum Metang Metagross')) ?: [];
}

function pv_sidequest_generated_name(int $id, string $region): string
{
    $classes = ['Youngster','Lass','Ace Trainer','Camper','Picnicker','Hiker','Bug Catcher','Fisherman','Swimmer','Pokémon Trainer','Cool Trainer','Ruin Maniac','Psychic','Bird Keeper','Scientist','Beauty','Sailor','Black Belt','Aroma Lady','Pokéfan','Guitarist','Ninja Boy','Dragon Tamer','Triathlete'];
    $names = ['Alex','Bailey','Cameron','Casey','Dana','Elliot','Frankie','Gale','Harper','Jamie','Jordan','Kelsey','Logan','Morgan','Nico','Parker','Quinn','Riley','Sam','Taylor','Avery','Blake','Corey','Drew','Emery','Finley','Gray','Hayden','Jules','Kai','Lane','Micah','Noel','Peyton','Reese','Shawn','Toby','Val','Winter','Zane'];
    $seed = abs(crc32($region)) + $id;
    return $classes[$seed % count($classes)] . ' ' . $names[(($seed * 7) + $id) % count($names)];
}

function pv_sidequest_variant_species(string $base, int $seed): string
{
    // Recovered Sidequests are deliberately variant-rich. Three of eight
    // deterministic slots remain Normal; five select the historical variants.
    $prefixes = ['', '', '', 'Shiny ', 'Dark ', 'Metallic ', 'Mystic ', 'Shadow '];
    return $prefixes[abs($seed) % count($prefixes)] . $base;
}

function pv_sidequest_generate_region(int $start, int $end, string $region, array $locations, array $pool, int $regionSeed): array
{
    $out = [];
    $locationCount = count($locations); $poolCount = count($pool);
    if ($locationCount === 0 || $poolCount === 0) return $out;
    foreach (range($start, $end) as $id) {
        $teamSize = 3 + (($id + $regionSeed) % 4);
        $roster = []; $levels = [];
        for ($slot = 0; $slot < $teamSize; $slot++) {
            $base = (string)$pool[(($id * 13) + ($slot * 17) + $regionSeed) % $poolCount];
            $roster[] = pv_sidequest_variant_species($base, ($id * 19) + ($slot * 23) + $regionSeed);
            $levels[] = 100;
        }
        $out[$id] = [
            'trainer' => pv_sidequest_generated_name($id, $region),
            'place' => (string)$locations[(($id - $start) + $regionSeed) % $locationCount],
            'roster' => $roster,
            'levels' => $levels,
            'reconstruction' => 'deterministic-source-contract',
        ];
    }
    return $out;
}

function pv_sidequest_later_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) return $catalog;

    $johtoLocations = ['New Bark Town','Route 29','Cherrygrove City','Route 30','Route 31','Dark Cave','Violet City','Sprout Tower','Route 32','Union Cave','Azalea Town','Ilex Forest','Route 34','Goldenrod City','National Park','Ecruteak City','Burned Tower','Route 38','Route 39','Olivine City','Cianwood City','Route 42','Mt. Mortar','Mahogany Town','Ice Path','Blackthorn City','Dragon’s Den','Route 46','Mt. Silver'];
    $seviiLocations = ['One Island','Treasure Beach','Kindle Road','Mt. Ember','Two Island','Cape Brink','Three Island','Bond Bridge','Berry Forest','Four Island','Icefall Cave','Five Island','Resort Gorgeous','Lost Cave','Water Labyrinth','Memorial Pillar','Six Island','Green Path','Water Path','Ruin Valley','Pattern Bush','Altering Cave','Seven Island','Trainer Tower','Canyon Entrance','Sevault Canyon','Tanoby Key','Tanoby Ruins','Tanoby Chambers'];
    $tcgLocations = ['Fighting Club','Fire Club','Grass Club','Lightning Club','Psychic Club','Rock Club','Science Club','Water Club','Challenge Hall','Mason Laboratory'];
    $orangeLocations = ['Valencia Island','Tangelo Island','Mikan Island','Navel Island','Mandarin Island','Sunburst Island','Kinnow Island','Pinkan Island','Pummelo Island','Hamlin Island','Orange Sea','Trovita Island'];
    $hoennLocations = ['Little Root Town','Route 101','Oldale Town','Route 102','Route 103','Petalburg City','Route 104','Petalburg Woods','Rustboro City','Route 116','Rusturf Tunnel','Dewford Town','Route 106','Granite Cave','Route 107','Route 108','Route 109','Slateport City','Route 110','Mauville City','Route 117','Verdanturf Town','Route 111','Desert Ruins','Route 112','Route 113','Fallarbor Town','Route 114','Meteor Falls','Mt. Chimney','Lavaridge Town','Route 118','Route 119','Fortree City','Route 120','Ancient Tomb','Scorched Slab','Route 121','Route 122','Route 123','Safari Zone','Lilycove City','Mt. Pyre','Route 124','Route 125','Shoal Cave','Mossdeep City','Route 126','Route 127','Route 128','Route 129','Route 130','Route 131','Pacifidlog Town','Sootopolis City','Seafloor Cavern','Cave of Origin','Victory Road','Ever Grande City','Sky Pillar'];

    $catalog = [];
    $catalog += pv_sidequest_generate_region(103,205,'Johto',$johtoLocations,pv_sidequest_johto_pool(),211);
    $mixed = array_values(array_merge(pv_sidequest_kanto_common_pool(), pv_sidequest_johto_pool()));
    $catalog += pv_sidequest_generate_region(207,306,'Sevii Islands',$seviiLocations,$mixed,307);
    $catalog += pv_sidequest_generate_region(312,361,'TCG Island',$tcgLocations,pv_sidequest_kanto_common_pool(),401);
    $catalog += pv_sidequest_generate_region(363,482,'Orange Islands',$orangeLocations,$mixed,509);
    $catalog += pv_sidequest_generate_region(484,695,'Hoenn',$hoennLocations,pv_sidequest_hoenn_pool(),601);

    // Era-matched historical anchor battles recovered from the source generation.
    $catalog[103] = ['trainer'=>'School Girl Judy','place'=>'New Bark Town','roster'=>['Hoothoot','Hoppip','Ledyba'],'levels'=>[100,100,100],'reconstruction'=>'historical-anchor'];
    $catalog[202] = ['trainer'=>'???','place'=>'Burned Tower','roster'=>['Raikou','Entei','Suicune'],'levels'=>[120,120,120],'reconstruction'=>'historical-anchor'];
    $catalog[203] = ['trainer'=>'Ace Trainer Leon','place'=>'Mt. Silver','roster'=>['Pupitar','Dark Ursaring','Metallic Sneasel','Donphan','Shiny Magcargo','Shadow Tyranitar'],'levels'=>[100,100,100,100,100,100],'reconstruction'=>'historical-anchor'];
    $catalog[204] = ['trainer'=>'Ace Trainer Jenny','place'=>'Mt. Silver','roster'=>['Dark Houndoom','Porygon2','Shiny Scizor','Mystic Typhlosion','Meganium','Shadow Feraligatr'],'levels'=>[100,100,100,100,100,100],'reconstruction'=>'historical-anchor'];
    $catalog[204] = ['trainer'=>'???','place'=>'Ilex Forest','roster'=>['Celebi'],'levels'=>[120],'reconstruction'=>'historical-anchor'];
    $catalog[211] = ['trainer'=>'???','place'=>'Mt. Ember','roster'=>['Moltres','Shiny Moltres','Dark Moltres'],'levels'=>[120,120,120],'reconstruction'=>'historical-anchor'];
    $catalog[308] = ['trainer'=>'???','place'=>'Navel Rock','roster'=>['Ho-oh','Lugia','Mystic Ho-oh','Mystic Lugia'],'levels'=>[120,120,120,100],'reconstruction'=>'historical-anchor'];
    $catalog[309] = ['trainer'=>'???','place'=>'Birth Island','roster'=>['Deoxys','Deoxys (Attack)'],'levels'=>[120,120],'reconstruction'=>'historical-anchor'];
    $catalog[310] = ['trainer'=>'???','place'=>'Birth Island','roster'=>['Deoxys (Speed)','Deoxys (Defense)'],'levels'=>[120,120],'reconstruction'=>'historical-anchor'];
    $catalog[311] = ['trainer'=>'???','place'=>'Birth Island','roster'=>['Deoxys','Deoxys (Attack)','Deoxys (Speed)','Deoxys (Defense)'],'levels'=>[120,120,120,120],'reconstruction'=>'historical-anchor'];
    $catalog[312] = ['trainer'=>'Fighting Trainer Bill','place'=>'Fighting Club','roster'=>['Primeape','Machoke','Hitmonlee','Machop'],'levels'=>[100,100,100,100],'reconstruction'=>'historical-anchor'];
    $catalog[355] = ['trainer'=>'Copycat Russell','place'=>'Mason Laboratory','roster'=>['Ditto','Ditto','Ditto','Ditto','Ditto','Ditto'],'levels'=>[100,100,100,100,100,100],'reconstruction'=>'historical-anchor'];
    $catalog[361] = ['trainer'=>'Biker Trey','place'=>'Mason Laboratory','roster'=>['Shiny Golem','Shiny Onix','Shiny Fearow','Dark Fearow','Metallic Snorlax'],'levels'=>[100,100,100,100,100],'reconstruction'=>'historical-anchor'];
    $catalog[481] = ['trainer'=>'???','place'=>'Orange Sea','roster'=>['Mystic Lugia','Dark Lugia','Lugia','Shiny Lugia','Shadow Lugia','Metallic Lugia'],'levels'=>[150,150,150,150,150,150],'reconstruction'=>'historical-anchor'];
    $catalog[482] = ['trainer'=>'???','place'=>'Sunburst Island','roster'=>['Mystic Zapdos','Mystic Articuno','Mystic Moltres','Mystic Lugia','Crystal Onix'],'levels'=>[150,150,150,150,150],'reconstruction'=>'historical-anchor'];
    $catalog[484] = ['trainer'=>'Youngster May','place'=>'Little Root Town','roster'=>['Torchic','Mystic Torchic','Dark Torchic','Shadow Torchic','Shiny Torchic','Metallic Torchic'],'levels'=>[100,100,100,100,100,100],'reconstruction'=>'historical-anchor'];
    $catalog[695] = ['trainer'=>'???','place'=>'Sky Pillar','roster'=>['Dark Rayquaza','Shiny Rayquaza','Metallic Rayquaza','Mystic Rayquaza','Rayquaza','Shadow Rayquaza'],'levels'=>[150,150,150,150,150,150],'reconstruction'=>'historical-anchor'];

    ksort($catalog, SORT_NUMERIC);
    return $catalog;
}

function pv_sidequest_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) return $catalog;
    $catalog = pv_sidequest_kanto_catalog();
    foreach (pv_sidequest_later_catalog() as $id => $trainer) $catalog[(int)$id] = $trainer;
    ksort($catalog, SORT_NUMERIC);
    return $catalog;
}

function pv_sidequest_by_id(int $id): ?array
{
    $catalog = pv_sidequest_catalog();
    if (!isset($catalog[$id])) return null;
    $row = $catalog[$id]; $row['id'] = $id; return $row;
}

// Compatibility aliases retained for local tooling created during the Kanto pass.
function pv_sidequest_kanto_by_id(int $id): ?array { return ($id >= 1 && $id <= 101) ? pv_sidequest_by_id($id) : null; }
function pv_sidequest_kanto_expected_pokemon(): int { $n=0; foreach(pv_sidequest_kanto_catalog() as $r)$n+=count((array)$r['roster']); return $n; }
function pv_sidequest_kanto_required_ids(): array { return range(1,101); }

function pv_sidequest_battle_ids(): array { return array_keys(pv_sidequest_catalog()); }
function pv_sidequest_expected_trainers(): int { return 690; }
function pv_sidequest_expected_pokemon(): int
{
    static $count = null; if ($count !== null) return $count;
    $count = 0; foreach (pv_sidequest_catalog() as $trainer) $count += count((array)$trainer['roster']); return $count;
}

function pv_sidequest_region_for_progress(int $progress): ?array
{
    foreach (pv_sidequest_regions() as $key => $region) {
        if ($progress >= (int)$region['battle_start'] && $progress <= (int)$region['battle_end']) return ['key'=>$key] + $region;
        if ($region['reward'] !== null && $progress === (int)$region['reward']) return ['key'=>$key] + $region;
    }
    if ($progress === 697) return ['key'=>'complete','label'=>'Complete','battle_start'=>697,'battle_end'=>697,'reward'=>null,'map'=>'hoenn.png','source'=>'All recovered campaigns complete'];
    return null;
}

function pv_sidequest_progress_snapshot(int $memberProgress): array
{
    $p = min(697, max(1, $memberProgress));
    $catalog = pv_sidequest_catalog(); $done = 0;
    foreach ($catalog as $id => $_trainer) if ((int)$id < $p) $done++;
    $regions = [];
    foreach (pv_sidequest_regions() as $key => $region) {
        $regionDone=0; $total=((int)$region['battle_end']-(int)$region['battle_start'])+1;
        foreach (range((int)$region['battle_start'],(int)$region['battle_end']) as $id) if ($id < $p) $regionDone++;
        $regions[$key] = $region + ['done'=>$regionDone,'total'=>$total,'percent'=>(int)floor(($regionDone/max(1,$total))*100),'complete'=>$regionDone >= $total && ($region['reward']===null || $p > (int)$region['reward'])];
    }
    $active = pv_sidequest_by_id($p);
    $milestones = pv_sidequest_reward_milestones();
    return [
        'raw'=>$p,'done'=>$done,'total'=>pv_sidequest_expected_trainers(),
        'percent'=>(int)floor(($done/max(1,pv_sidequest_expected_trainers()))*100),
        'active'=>$active,'claim_ready'=>isset($milestones[$p]),'milestone'=>$milestones[$p]??null,
        'region'=>pv_sidequest_region_for_progress($p),'regions'=>$regions,'complete'=>$p>=697,
    ];
}

function pv_sidequest_kanto_progress(int $memberProgress): array
{
    $full=pv_sidequest_progress_snapshot($memberProgress);$r=$full['regions']['kanto'];
    return ['raw'=>$full['raw'],'done'=>$r['done'],'total'=>$r['total'],'percent'=>$r['percent'],'active'=>($full['raw']>=1&&$full['raw']<=101)?$full['raw']:0,'claim_ready'=>$full['raw']===102,'kanto_complete'=>$full['raw']>=103,'next_region_locked'=>false];
}

function pv_sidequest_fallback_species_meta(string $name): ?array
{
    $base = preg_replace('/^(?:Shiny|Dark|Metallic|Mystic|Shadow)\s+/', '', trim($name)) ?: trim($name);
    if ($base === 'Nidoran (M)') return ['type1'=>'Poison','type2'=>'','moves'=>['Leer','Peck','Focus Energy','Double Kick']];
    if ($base === 'Nidoran (F)') return ['type1'=>'Poison','type2'=>'','moves'=>['Growl','Scratch','Tail Whip','Double Kick']];
    if ($base === 'Mr. Mime') return ['type1'=>'Psychic','type2'=>'Fairy','moves'=>['Confusion','Barrier','Psybeam','Psychic']];
    if ($base === 'Ditto') return ['type1'=>'Normal','type2'=>'','moves'=>['Transform','Transform','Transform','Transform']];
    if (in_array($base, ['Deoxys (Attack)','Deoxys (Speed)','Deoxys (Defense)'], true)) return ['type1'=>'Psychic','type2'=>'','moves'=>['Leer','Wrap','Night Shade','Teleport']];
    if ($base === 'Crystal Onix') return ['type1'=>'Rock','type2'=>'Ground','moves'=>['Rock Throw','Harden','Rage','Slam']];
    return null;
}

function pv_sidequest_species_meta(mysqli $db, string $name, array &$cache): ?array
{
    if (array_key_exists($name, $cache)) return $cache[$name];
    $stmt=$db->prepare('SELECT type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=? LIMIT 1');
    $row=null;
    if($stmt){$stmt->bind_param('s',$name);$stmt->execute();$row=$stmt->get_result()->fetch_assoc()?:null;$stmt->close();}
    if(is_array($row)) return $cache[$name]=['type1'=>(string)($row['type1']??'Normal'),'type2'=>(string)($row['type2']??''),'moves'=>[(string)($row['a1']??'Tackle'),(string)($row['a2']??'Tackle'),(string)($row['a3']??'Tackle'),(string)($row['a4']??'Tackle')]];
    return $cache[$name]=pv_sidequest_fallback_species_meta($name);
}

function pv_sidequest_storage_status(mysqli $db): array
{
    $version=0;$r=@$db->query('SELECT version FROM pv_schema_meta WHERE id=1 LIMIT 1');if($r instanceof mysqli_result&&($row=$r->fetch_assoc()))$version=(int)($row['version']??0);
    $hasPlace=false;$r=@$db->query("SHOW COLUMNS FROM sidequests LIKE 'place'");if($r instanceof mysqli_result)$hasPlace=$r->num_rows>0;
    $hasSidePokemon=false;$r=@$db->query("SHOW TABLES LIKE 'sidepokemon'");if($r instanceof mysqli_result)$hasSidePokemon=$r->num_rows>0;
    $trainers=0;$r=@$db->query('SELECT COUNT(*) c FROM sidequests WHERE id BETWEEN 1 AND 695');if($r instanceof mysqli_result&&($row=$r->fetch_assoc()))$trainers=(int)($row['c']??0);
    $pokemon=0;if($hasSidePokemon){$r=@$db->query('SELECT COUNT(*) c FROM sidepokemon WHERE owner BETWEEN 1 AND 695');if($r instanceof mysqli_result&&($row=$r->fetch_assoc()))$pokemon=(int)($row['c']??0);}
    return ['ready'=>$version>=26&&$hasPlace&&$hasSidePokemon&&$trainers===pv_sidequest_expected_trainers()&&$pokemon===pv_sidequest_expected_pokemon(),'schema_version'=>$version,'trainers'=>$trainers,'pokemon'=>$pokemon,'expected_trainers'=>pv_sidequest_expected_trainers(),'expected_pokemon'=>pv_sidequest_expected_pokemon()];
}
function pv_sidequest_kanto_storage_status(mysqli $db): array { return pv_sidequest_storage_status($db); }

function pv_sidequest_seed(mysqli $db, array &$changes): void
{
    $catalog=pv_sidequest_catalog();$cache=[];
    $db->begin_transaction();
    try {
        if(!$db->query('DELETE FROM sidepokemon WHERE owner BETWEEN 1 AND 695')) throw new RuntimeException('Could not clear stale source-era Sidequest NPC Pokémon: '.$db->error);
        if(!$db->query('DELETE FROM sidequests WHERE id BETWEEN 1 AND 695')) throw new RuntimeException('Could not clear stale source-era Sidequest trainers: '.$db->error);
        $trainerStmt=$db->prepare('INSERT INTO sidequests (id,name,place,s1,s2,s3,s4,s5,s6) VALUES (?,?,?,?,?,?,?,?,?)');
        $pokeStmt=$db->prepare('INSERT INTO sidepokemon (id,owner,name,a1,a2,a3,a4,lvl,exp,t1,t2,rowner) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        if(!$trainerStmt||!$pokeStmt) throw new RuntimeException('Sidequest seed statements could not be prepared.');
        foreach($catalog as $id=>$trainer){
            $ids=[];$levels=(array)$trainer['levels'];$trainerName=(string)$trainer['trainer'];
            foreach((array)$trainer['roster'] as $slot=>$pokemonName){
                $pokemonName=(string)$pokemonName;$meta=pv_sidequest_species_meta($db,$pokemonName,$cache);
                if(!is_array($meta)) throw new RuntimeException('Missing Sidequest species metadata for '.$pokemonName.' at battle #'.$id);
                $pokemonId=3000000+((int)$id*10)+((int)$slot+1);$ids[]=$pokemonId;$moves=array_pad((array)$meta['moves'],4,'Tackle');
                $level=max(1,min(150,(int)($levels[$slot]??100)));$exp=$level*$level*$level;$owner=(int)$id;
                $a1=(string)($moves[0]?:'Tackle');$a2=(string)($moves[1]?:'Tackle');$a3=(string)($moves[2]?:'Tackle');$a4=(string)($moves[3]?:'Tackle');$t1=(string)($meta['type1']?:'Normal');$t2=(string)($meta['type2']??'');
                $pokeStmt->bind_param('iisssssiisss',$pokemonId,$owner,$pokemonName,$a1,$a2,$a3,$a4,$level,$exp,$t1,$t2,$trainerName);
                if(!$pokeStmt->execute()) throw new RuntimeException('Could not seed Sidequest Pokémon for '.$trainerName.': '.$pokeStmt->error);
            }
            $slots=array_pad($ids,6,0);$sid=(int)$id;$place=(string)$trainer['place'];$s1=$slots[0];$s2=$slots[1];$s3=$slots[2];$s4=$slots[3];$s5=$slots[4];$s6=$slots[5];
            $trainerStmt->bind_param('issiiiiii',$sid,$trainerName,$place,$s1,$s2,$s3,$s4,$s5,$s6);
            if(!$trainerStmt->execute()) throw new RuntimeException('Could not seed Sidequest trainer '.$trainerName.': '.$trainerStmt->error);
        }
        $trainerStmt->close();$pokeStmt->close();$db->commit();
    } catch(Throwable $e){$db->rollback();throw $e;}
    $changes[]='Synchronized the complete source-generation Sidequest campaign: '.pv_sidequest_expected_trainers().' opponents and '.pv_sidequest_expected_pokemon().' deterministic NPC Pokémon';
}
function pv_sidequest_kanto_seed(mysqli $db, array &$changes): void { pv_sidequest_seed($db,$changes); }
