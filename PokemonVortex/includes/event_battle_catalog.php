<?php
declare(strict_types=1);

/**
 * v22.5.0 authoritative Special Event battle catalog.
 * Trainer routes and teams reproduce the recovered 10 February 2016 Pokémon Vortex
 * Special Battles contract. Exposed event IDs preserve their original non-contiguous
 * events.gN identities and remain separate from League/facility badges.g1..g95.
 */
function pv_event_battle_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) return $catalog;
    $catalog = [
        1 => [
            'route' => 'James',
            'display' => 'James',
            'group' => 'rocket',
            'division' => 'Team Rocket Operative',
            'sprite' => 'images/sprites/events/james.gif',
            'roster' => ['Weezing', 'Amoonguss', 'Victreebel', 'Carnivine', 'Inkay', 'Chimecho'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        2 => [
            'route' => 'Jessie',
            'display' => 'Jessie',
            'group' => 'rocket',
            'division' => 'Team Rocket Operative',
            'sprite' => 'images/sprites/events/jessie.gif',
            'roster' => ['Pumpkaboo (Average)', 'Arbok', 'Woobat', 'Seviper', 'Frillish', 'Lickitung'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        3 => [
            'route' => 'Butch',
            'display' => 'Butch',
            'group' => 'rocket',
            'division' => 'Team Rocket Operative',
            'sprite' => 'images/sprites/events/butch.gif',
            'roster' => ['Raticate', 'Hitmontop', 'Primeape', 'Cloyster', 'Mightyena', 'Shuckle'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        4 => [
            'route' => 'Cassidy',
            'display' => 'Cassidy',
            'group' => 'rocket',
            'division' => 'Team Rocket Operative',
            'sprite' => 'images/sprites/events/cassidy.gif',
            'roster' => ['Raticate', 'Drowzee', 'Sableye', 'Houndour', 'Tentacruel', 'Granbull'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        5 => [
            'route' => 'Rocket Giovanni',
            'display' => 'Giovanni',
            'group' => 'rocket',
            'division' => 'Team Rocket Boss',
            'sprite' => 'images/sprites/trainers/giovanni.gif',
            'roster' => ['Persian', 'Golem', 'Nidoking', 'Kingler', 'Cloyster', 'Mewtwo (Armor)'],
            'levels' => [135, 135, 135, 135, 135, 135],
        ],
        6 => [
            'route' => 'Mellisa',
            'display' => 'Mellisa',
            'group' => 'aqua',
            'division' => 'Team Aqua Operative',
            'sprite' => 'images/sprites/events/mellisa.gif',
            'roster' => ['Walrein', 'Crawdaunt', 'Walrein', 'Crawdaunt', 'Walrein', 'Crawdaunt'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        7 => [
            'route' => 'Amber',
            'display' => 'Amber',
            'group' => 'aqua',
            'division' => 'Team Aqua Operative',
            'sprite' => 'images/sprites/events/amber.gif',
            'roster' => ['Ninjask', 'Shedinja', 'Volbeat', 'Pelipper', 'Volbeat', 'Carvanha'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        8 => [
            'route' => 'Matt',
            'display' => 'Matt',
            'group' => 'aqua',
            'division' => 'Team Aqua Admin',
            'sprite' => 'images/sprites/events/matt.gif',
            'roster' => ['Sharpedo', 'Vibrava', 'Azumarill', 'Mightyena', 'Golbat', 'Sharpedo'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        9 => [
            'route' => 'Shelly',
            'display' => 'Shelly',
            'group' => 'aqua',
            'division' => 'Team Aqua Admin',
            'sprite' => 'images/sprites/events/shelly.gif',
            'roster' => ['Ludicolo', 'Vibrava', 'Crawdaunt', 'Walrein', 'Mightyena', 'Sharpedo'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        10 => [
            'route' => 'Archie',
            'display' => 'Archie',
            'group' => 'aqua',
            'division' => 'Team Aqua Leader',
            'sprite' => 'images/sprites/events/archie.gif',
            'roster' => ['Mightyena', 'Crobat', 'Sharpedo', 'Masquerain', 'Tentacruel', 'Kyogre'],
            'levels' => [135, 135, 135, 135, 135, 135],
        ],
        11 => [
            'route' => 'Kate',
            'display' => 'Kate',
            'group' => 'magma',
            'division' => 'Team Magma Operative',
            'sprite' => 'images/sprites/events/kate.gif',
            'roster' => ['Numel', 'Golbat', 'Magcargo', 'Camerupt', 'Mightyena', 'Crobat'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        12 => [
            'route' => 'Mack',
            'display' => 'Mack',
            'group' => 'magma',
            'division' => 'Team Magma Operative',
            'sprite' => 'images/sprites/events/mack.gif',
            'roster' => ['Slugma', 'Magcargo', 'Swellow', 'Anorith', 'Camerupt', 'Armaldo'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        13 => [
            'route' => 'Courtney',
            'display' => 'Courtney',
            'group' => 'magma',
            'division' => 'Team Magma Admin',
            'sprite' => 'images/sprites/events/courtney.gif',
            'roster' => ['Numel', 'Mightyena', 'Camerupt', 'Crobat', 'Swellow', 'Ninetales'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        14 => [
            'route' => 'Tabitha',
            'display' => 'Tabitha',
            'group' => 'magma',
            'division' => 'Team Magma Admin',
            'sprite' => 'images/sprites/events/tabitha.gif',
            'roster' => ['Swellow', 'Numel', 'Camerupt', 'Golbat', 'Mightyena', 'Torkoal'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        15 => [
            'route' => 'Maxie',
            'display' => 'Maxie',
            'group' => 'magma',
            'division' => 'Team Magma Leader',
            'sprite' => 'images/sprites/events/maxie.gif',
            'roster' => ['Houndoom', 'Swellow', 'Camerupt', 'Crobat', 'Mightyena', 'Groudon'],
            'levels' => [135, 135, 135, 135, 135, 135],
        ],
        16 => [
            'route' => 'Sird',
            'display' => 'Sird',
            'group' => 'galactic',
            'division' => 'Team Galactic Commander',
            'sprite' => 'images/sprites/events/sird.gif',
            'roster' => ['Glameow', 'Wurmple', 'Stunky', 'Houndour', 'Croagunk', 'Murkrow'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        17 => [
            'route' => 'Jupiter',
            'display' => 'Jupiter',
            'group' => 'galactic',
            'division' => 'Team Galactic Commander',
            'sprite' => 'images/sprites/events/jupiter.gif',
            'roster' => ['Crobat', 'Gastrodon (East)', 'Skuntank', 'Tangrowth', 'Toxicroak', 'Sableye'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        18 => [
            'route' => 'Saturn',
            'display' => 'Saturn',
            'group' => 'galactic',
            'division' => 'Team Galactic Commander',
            'sprite' => 'images/sprites/events/saturn.gif',
            'roster' => ['Bronzong', 'Toxicroak', 'Octillery', 'Rhyperior', 'Magmortar', 'Crobat'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        19 => [
            'route' => 'Mars',
            'display' => 'Mars',
            'group' => 'galactic',
            'division' => 'Team Galactic Commander',
            'sprite' => 'images/sprites/events/mars.gif',
            'roster' => ['Kangaskhan', 'Bronzong', 'Crobat', 'Purugly', 'Yanmega', 'Electivire'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        20 => [
            'route' => 'Cyrus',
            'display' => 'Cyrus',
            'group' => 'galactic',
            'division' => 'Team Galactic Boss',
            'sprite' => 'images/sprites/events/cyrus.gif',
            'roster' => ['Honchkrow', 'Uxie', 'Mesprit', 'Azelf', 'Dialga', 'Palkia'],
            'levels' => [135, 135, 135, 135, 135, 135],
        ],
        21 => [
            'route' => 'Plasma Grunt',
            'display' => 'Plasma Grunt',
            'group' => 'plasma',
            'division' => 'Team Plasma Grunt',
            'sprite' => 'images/sprites/events/plasmagrunt.gif',
            'roster' => ['Patrat', 'Sandile', 'Scraggy', 'Purrloin', 'Trubbish', 'Lillipup'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        22 => [
            'route' => 'Plasma Grunt 2',
            'display' => 'Plasma Grunt 2',
            'group' => 'plasma',
            'division' => 'Team Plasma Grunt',
            'sprite' => 'images/sprites/events/plasmagrunt2.gif',
            'roster' => ['Watchog', 'Krookodile', 'Scrafty', 'Liepard', 'Garbodor', 'Stoutland'],
            'levels' => [100, 100, 100, 100, 100, 100],
        ],
        23 => [
            'route' => 'N',
            'display' => 'N',
            'group' => 'plasma',
            'division' => 'Team Plasma King',
            'sprite' => 'images/sprites/events/n.gif',
            'roster' => ['Reshiram', 'Vanilluxe', 'Archeops', 'Zoroark', 'Klinklang', 'Zekrom'],
            'levels' => [125, 125, 125, 125, 125, 125],
        ],
        24 => [
            'route' => 'Ghetsis',
            'display' => 'Ghetsis',
            'group' => 'plasma',
            'division' => 'Team Plasma Leader',
            'sprite' => 'images/sprites/events/geechisu.gif',
            'roster' => ['Cofagrigus', 'Bouffalant', 'Seismitoad', 'Bisharp', 'Eelektross', 'Hydreigon'],
            'levels' => [135, 135, 135, 135, 135, 135],
        ],
        32 => [
            'route' => 'Aliana',
            'display' => 'Aliana',
            'group' => 'flare',
            'division' => 'Team Flare Scientist',
            'sprite' => 'images/sprites/events/aliana.gif',
            'roster' => ['Mightyena', 'Druddigon', 'Pyroar', 'Diggersby'],
            'levels' => [120, 120, 120, 120],
        ],
        33 => [
            'route' => 'Bryony',
            'display' => 'Bryony',
            'group' => 'flare',
            'division' => 'Team Flare Scientist',
            'sprite' => 'images/sprites/events/bryony.gif',
            'roster' => ['Liepard', 'Bisharp', 'Liepard', 'Pangoro'],
            'levels' => [120, 120, 120, 120],
        ],
        34 => [
            'route' => 'Celosia',
            'display' => 'Celosia',
            'group' => 'flare',
            'division' => 'Team Flare Scientist',
            'sprite' => 'images/sprites/events/celosia.gif',
            'roster' => ['Manectric', 'Honedge', 'Drapion', 'Manectric', 'Aegislash (Shield)'],
            'levels' => [120, 120, 120, 120, 120],
        ],
        35 => [
            'route' => 'Mable',
            'display' => 'Mable',
            'group' => 'flare',
            'division' => 'Team Flare Scientist',
            'sprite' => 'images/sprites/events/mable.gif',
            'roster' => ['Houndoom', 'Weavile', 'Houndoom', 'Malamar'],
            'levels' => [120, 120, 120, 120],
        ],
        36 => [
            'route' => 'Xerosic',
            'display' => 'Xerosic',
            'group' => 'flare',
            'division' => 'Team Flare Scientist',
            'sprite' => 'images/sprites/events/xerosic.gif',
            'roster' => ['Crobat', 'Malamar', 'Aegislash (Blade)', 'Malamar'],
            'levels' => [125, 125, 125, 125],
        ],
        37 => [
            'route' => 'Lysandre',
            'display' => 'Lysandre',
            'group' => 'flare',
            'division' => 'Team Flare Leader',
            'sprite' => 'images/sprites/events/lysandre.gif',
            'roster' => ['Mienshao', 'Honchkrow', 'Pyroar', 'Gyarados (Mega)'],
            'levels' => [140, 140, 140, 145],
        ],
        26 => [
            'route' => 'Rob',
            'display' => 'Rob',
            'group' => 'staff',
            'division' => 'Vortex Administrator',
            'sprite' => 'images/sprites/events/rob.gif',
            'roster' => ['Mystic Politoed', 'Shadow Porygon2', 'Mystic Milotic', 'Dark Venusaur (Mega)', 'Shiny Umbreon', 'Mystic Volcanion'],
            'levels' => [150, 150, 150, 150, 150, 150],
        ],
        27 => [
            'route' => 'Patrick',
            'display' => 'Patrick',
            'group' => 'staff',
            'division' => 'Vortex Administrator',
            'sprite' => 'images/sprites/events/patrick.gif',
            'roster' => ['Shiny Munchlax', 'Mystic Sableye', 'Shiny Dragonite', 'Shiny Arceus (Fire)', 'Mystic Spiritomb', 'Dark Granbull'],
            'levels' => [150, 150, 150, 150, 150, 150],
        ],
    ];
    return $catalog;
}

function pv_event_battle_required_ids(): array
{
    return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 32, 33, 34, 35, 36, 37, 26, 27];
}

function pv_event_battle_groups(): array
{
    return [
        'rocket' => ['label' => 'Team Rocket', 'subtitle' => 'Kanto criminal syndicate operatives culminating in Giovanni.', 'ids' => [1, 2, 3, 4, 5], 'badge' => 'rocket.gif', 'badge_label' => 'Team Rocket Event Badge', 'banner' => 'teamrocket.png'],
        'aqua' => ['label' => 'Team Aqua', 'subtitle' => 'Hoenn ocean-expansion cell culminating in Archie.', 'ids' => [6, 7, 8, 9, 10], 'badge' => 'aqua.gif', 'badge_label' => 'Team Aqua Event Badge', 'banner' => 'teamaqua.png'],
        'magma' => ['label' => 'Team Magma', 'subtitle' => 'Hoenn land-expansion cell culminating in Maxie.', 'ids' => [11, 12, 13, 14, 15], 'badge' => 'magma.gif', 'badge_label' => 'Team Magma Event Badge', 'banner' => 'teammagma.png'],
        'galactic' => ['label' => 'Team Galactic', 'subtitle' => "Face Team Galactic's commanders before taking on Cyrus.", 'ids' => [16, 17, 18, 19, 20], 'badge' => 'galactic.gif', 'badge_label' => 'Team Galactic Event Badge', 'banner' => 'teamgalactic.png'],
        'plasma' => ['label' => 'Team Plasma', 'subtitle' => 'Unova liberation faction culminating in N and Ghetsis.', 'ids' => [21, 22, 23, 24], 'badge' => 'plasma.gif', 'badge_label' => 'Team Plasma Event Badge', 'banner' => 'teamplasma.png'],
        'flare' => ['label' => 'Team Flare', 'subtitle' => 'Kalos scientist cell culminating in Lysandre.', 'ids' => [32, 33, 34, 35, 36, 37], 'badge' => 'flare.gif', 'badge_label' => 'Team Flare Event Badge', 'banner' => 'teamflare.png'],
        'staff' => ['label' => 'Vortex Staff Challenge', 'subtitle' => 'A high-level challenge against Vortex staff trainers Rob and Patrick.', 'ids' => [26, 27], 'badge' => 'admins.gif', 'badge_label' => 'Pokémon Vortex Admins Event Badge', 'banner' => 'admins.png'],
    ];
}

function pv_event_battle_species_data(): array
{
    static $species = null;
    if ($species !== null) return $species;
    $species = [
        'Aegislash (Blade)' => ['type1' => 'Steel', 'type2' => 'Ghost', 'moves' => ['Aerial Ace', 'Autotomize', 'Fury Cutter', 'Head Smash']],
        'Aegislash (Shield)' => ['type1' => 'Steel', 'type2' => 'Ghost', 'moves' => ['Aerial Ace', 'Autotomize', 'Fury Cutter', 'Head Smash']],
        'Amoonguss' => ['type1' => 'Grass', 'type2' => 'Poison', 'moves' => ['Absorb', 'Astonish', 'Bide', 'Growth']],
        'Anorith' => ['type1' => 'Rock', 'type2' => 'Bug', 'moves' => ['Harden', 'Scratch', 'Mud Sport', 'Water Gun']],
        'Arbok' => ['type1' => 'Poison', 'type2' => '', 'moves' => ['Bite', 'Crunch', 'Fire Fang', 'Ice Fang']],
        'Archeops' => ['type1' => 'Rock', 'type2' => 'Flying', 'moves' => ['Leer', 'Quick Attack', 'Rock Throw', 'Wing Attack']],
        'Armaldo' => ['type1' => 'Rock', 'type2' => 'Bug', 'moves' => ['Harden', 'Mud Sport', 'Scratch', 'Water Gun']],
        'Azelf' => ['type1' => 'Psychic', 'type2' => '', 'moves' => ['Confusion', 'Last Resort', 'Natural Gift', 'Rest']],
        'Azumarill' => ['type1' => 'Water', 'type2' => 'Fairy', 'moves' => ['Tackle', 'Tail Whip', 'Water Gun', 'Water Sport']],
        'Bisharp' => ['type1' => 'Dark', 'type2' => 'Steel', 'moves' => ['Fury Cutter', 'Guillotine', 'Iron Head', 'Leer']],
        'Bouffalant' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Leer', 'Pursuit', 'Rage', 'Fury Attack']],
        'Bronzong' => ['type1' => 'Steel', 'type2' => 'Psychic', 'moves' => ['Block', 'Confusion', 'Hypnosis', 'Imprison']],
        'Camerupt' => ['type1' => 'Fire', 'type2' => 'Ground', 'moves' => ['Ember', 'Eruption', 'Fissure', 'Focus Energy']],
        'Carnivine' => ['type1' => 'Grass', 'type2' => '', 'moves' => ['Bind', 'Growth', 'Bite', 'Vine Whip']],
        'Carvanha' => ['type1' => 'Water', 'type2' => 'Dark', 'moves' => ['Bite', 'Leer', 'Rage', 'Focus Energy']],
        'Chimecho' => ['type1' => 'Psychic', 'type2' => '', 'moves' => ['Astonish', 'Confusion', 'Growl', 'Healing Wish']],
        'Cloyster' => ['type1' => 'Water', 'type2' => 'Ice', 'moves' => ['Aurora Beam', 'Hydro Pump', 'Protect', 'Shell Smash']],
        'Cofagrigus' => ['type1' => 'Ghost', 'type2' => '', 'moves' => ['Astonish', 'Disable', 'Haze', 'Protect']],
        'Crawdaunt' => ['type1' => 'Water', 'type2' => 'Dark', 'moves' => ['Bubble', 'Harden', 'Leer', 'Swift']],
        'Croagunk' => ['type1' => 'Poison', 'type2' => 'Fighting', 'moves' => ['Astonish', 'Mud-Slap', 'Poison Sting', 'Taunt']],
        'Crobat' => ['type1' => 'Poison', 'type2' => 'Flying', 'moves' => ['Absorb', 'Astonish', 'Bite', 'Cross Poison']],
        'Dark Granbull' => ['type1' => 'Fairy', 'type2' => 'Normal', 'moves' => ['Charm', 'Fire Fang', 'Ice Fang', 'Outrage']],
        'Dark Venusaur (Mega)' => ['type1' => 'Grass', 'type2' => 'Poison', 'moves' => ['Growl', 'Leech Seed', 'Petal Dance', 'Tackle']],
        'Dialga' => ['type1' => 'Steel', 'type2' => 'Dragon', 'moves' => ['Dragon Breath', 'Scary Face', 'Metal Claw', 'Ancient Power']],
        'Diggersby' => ['type1' => 'Normal', 'type2' => 'Ground', 'moves' => ['Agility', 'Bulldoze', 'Hammer Arm', 'Leer']],
        'Drapion' => ['type1' => 'Poison', 'type2' => 'Dark', 'moves' => ['Bite', 'Fire Fang', 'Ice Fang', 'Knock Off']],
        'Drowzee' => ['type1' => 'Psychic', 'type2' => '', 'moves' => ['Hypnosis', 'Pound', 'Disable', 'Confusion']],
        'Druddigon' => ['type1' => 'Dragon', 'type2' => '', 'moves' => ['Leer', 'Scratch', 'Hone Claws', 'Bite']],
        'Eelektross' => ['type1' => 'Electric', 'type2' => '', 'moves' => ['Acid', 'Coil', 'Crunch', 'Crush Claw']],
        'Electivire' => ['type1' => 'Electric', 'type2' => '', 'moves' => ['Electric Terrain', 'Fire Punch', 'Ion Deluge', 'Leer']],
        'Frillish' => ['type1' => 'Water', 'type2' => 'Ghost', 'moves' => ['Bubble', 'Water Sport', 'Absorb', 'Night Shade']],
        'Garbodor' => ['type1' => 'Poison', 'type2' => '', 'moves' => ['Poison Gas', 'Pound', 'Recycle', 'Toxic Spikes']],
        'Gastrodon (East)' => ['type1' => 'Water', 'type2' => 'Ground', 'moves' => ['Harden', 'Mud-Slap', 'Mud Sport', 'Water Pulse']],
        'Glameow' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Fake Out', 'Scratch', 'Growl', 'Hypnosis']],
        'Golbat' => ['type1' => 'Poison', 'type2' => 'Flying', 'moves' => ['Absorb', 'Astonish', 'Bite', 'Screech']],
        'Golem' => ['type1' => 'Rock', 'type2' => 'Ground', 'moves' => ['Defense Curl', 'Heavy Slam', 'Mud Sport', 'Rock Polish']],
        'Granbull' => ['type1' => 'Fairy', 'type2' => 'Normal', 'moves' => ['Charm', 'Fire Fang', 'Ice Fang', 'Outrage']],
        'Groudon' => ['type1' => 'Ground', 'type2' => '', 'moves' => ['Ancient Power', 'Mud Shot', 'Scary Face', 'Earth Power']],
        'Gyarados (Mega)' => ['type1' => 'Water', 'type2' => 'Dark', 'moves' => ['Bite', 'Thrash', 'Leer', 'Twister']],
        'Hitmontop' => ['type1' => 'Fighting', 'type2' => '', 'moves' => ['Close Combat', 'Detect', 'Endeavor', 'Focus Energy']],
        'Honchkrow' => ['type1' => 'Dark', 'type2' => 'Flying', 'moves' => ['Astonish', 'Haze', 'Night Slash', 'Pursuit']],
        'Honedge' => ['type1' => 'Steel', 'type2' => 'Ghost', 'moves' => ['Swords Dance', 'Tackle', 'Fury Cutter', 'Metal Sound']],
        'Houndoom' => ['type1' => 'Dark', 'type2' => 'Fire', 'moves' => ['Ember', 'Howl', 'Inferno', 'Leer']],
        'Houndour' => ['type1' => 'Dark', 'type2' => 'Fire', 'moves' => ['Ember', 'Leer', 'Howl', 'Smog']],
        'Hydreigon' => ['type1' => 'Dark', 'type2' => 'Dragon', 'moves' => ['Bite', 'Dragon Rage', 'Focus Energy', 'Hyper Voice']],
        'Inkay' => ['type1' => 'Dark', 'type2' => 'Psychic', 'moves' => ['Constrict', 'Peck', 'Tackle', 'Reflect']],
        'Kangaskhan' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Comet Punch', 'Leer', 'Fake Out', 'Tail Whip']],
        'Kingler' => ['type1' => 'Water', 'type2' => '', 'moves' => ['Bubble', 'Leer', 'Mud Sport', 'Vice Grip']],
        'Klinklang' => ['type1' => 'Steel', 'type2' => '', 'moves' => ['Charge', 'Gear Grind', 'Gear Up', 'Magnetic Flux']],
        'Krookodile' => ['type1' => 'Ground', 'type2' => 'Dark', 'moves' => ['Bite', 'Leer', 'Power Trip', 'Rage']],
        'Kyogre' => ['type1' => 'Water', 'type2' => '', 'moves' => ['Ancient Power', 'Water Pulse', 'Scary Face', 'Aqua Tail']],
        'Lickitung' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Rollout', 'Lick', 'Supersonic', 'Defense Curl']],
        'Liepard' => ['type1' => 'Dark', 'type2' => '', 'moves' => ['Assist', 'Growl', 'Sand Attack', 'Scratch']],
        'Lillipup' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Leer', 'Tackle', 'Odor Sleuth', 'Bite']],
        'Ludicolo' => ['type1' => 'Water', 'type2' => 'Grass', 'moves' => ['Astonish', 'Growl', 'Mega Drain', 'Nature Power']],
        'Magcargo' => ['type1' => 'Fire', 'type2' => 'Rock', 'moves' => ['Earth Power', 'Ember', 'Rock Throw', 'Shell Smash']],
        'Magmortar' => ['type1' => 'Fire', 'type2' => '', 'moves' => ['Ember', 'Leer', 'Smog', 'Smokescreen']],
        'Malamar' => ['type1' => 'Dark', 'type2' => 'Psychic', 'moves' => ['Constrict', 'Peck', 'Reflect', 'Reversal']],
        'Manectric' => ['type1' => 'Electric', 'type2' => '', 'moves' => ['Electric Terrain', 'Fire Fang', 'Howl', 'Leer']],
        'Masquerain' => ['type1' => 'Bug', 'type2' => 'Flying', 'moves' => ['Bubble', 'Bug Buzz', 'Ominous Wind', 'Quick Attack']],
        'Mesprit' => ['type1' => 'Psychic', 'type2' => '', 'moves' => ['Confusion', 'Copycat', 'Healing Wish', 'Natural Gift']],
        'Mewtwo (Armor)' => ['type1' => 'Psychic', 'type2' => '', 'moves' => ['Confusion', 'Disable', 'Laser Focus', 'Psywave']],
        'Mienshao' => ['type1' => 'Fighting', 'type2' => '', 'moves' => ['Aura Sphere', 'Detect', 'Fake Out', 'Meditate']],
        'Mightyena' => ['type1' => 'Dark', 'type2' => '', 'moves' => ['Bite', 'Crunch', 'Fire Fang', 'Howl']],
        'Murkrow' => ['type1' => 'Dark', 'type2' => 'Flying', 'moves' => ['Astonish', 'Peck', 'Pursuit', 'Haze']],
        'Mystic Milotic' => ['type1' => 'Water', 'type2' => '', 'moves' => ['Refresh', 'Water Gun', 'Water Pulse', 'Water Sport']],
        'Mystic Politoed' => ['type1' => 'Water', 'type2' => '', 'moves' => ['Bubble Beam', 'Double Slap', 'Hypnosis', 'Perish Song']],
        'Mystic Sableye' => ['type1' => 'Dark', 'type2' => 'Ghost', 'moves' => ['Leer', 'Scratch', 'Foresight', 'Night Shade']],
        'Mystic Spiritomb' => ['type1' => 'Ghost', 'type2' => 'Dark', 'moves' => ['Confuse Ray', 'Curse', 'Pursuit', 'Shadow Sneak']],
        'Mystic Volcanion' => ['type1' => 'Fire', 'type2' => 'Water', 'moves' => ['Flare Blitz', 'Steam Eruption', 'Take Down', 'Mist']],
        'Nidoking' => ['type1' => 'Poison', 'type2' => 'Ground', 'moves' => ['Double Kick', 'Focus Energy', 'Megahorn', 'Peck']],
        'Ninetales' => ['type1' => 'Fire', 'type2' => '', 'moves' => ['Confuse Ray', 'Flamethrower', 'Imprison', 'Nasty Plot']],
        'Ninjask' => ['type1' => 'Bug', 'type2' => 'Flying', 'moves' => ['Absorb', 'Bug Bite', 'Double Team', 'Fury Cutter']],
        'Numel' => ['type1' => 'Fire', 'type2' => 'Ground', 'moves' => ['Growl', 'Tackle', 'Ember', 'Focus Energy']],
        'Octillery' => ['type1' => 'Water', 'type2' => '', 'moves' => ['Aurora Beam', 'Constrict', 'Gunk Shot', 'Octazooka']],
        'Palkia' => ['type1' => 'Water', 'type2' => 'Dragon', 'moves' => ['Dragon Breath', 'Scary Face', 'Water Pulse', 'Ancient Power']],
        'Pangoro' => ['type1' => 'Fighting', 'type2' => 'Dark', 'moves' => ['Arm Thrust', 'Bullet Punch', 'Entrainment', 'Hammer Arm']],
        'Patrat' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Tackle', 'Leer', 'Bite', 'Bide']],
        'Pelipper' => ['type1' => 'Water', 'type2' => 'Flying', 'moves' => ['Growl', 'Hurricane', 'Hydro Pump', 'Protect']],
        'Persian' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Bite', 'Fake Out', 'Growl', 'Play Rough']],
        'Primeape' => ['type1' => 'Fighting', 'type2' => '', 'moves' => ['Final Gambit', 'Fling', 'Focus Energy', 'Leer']],
        'Pumpkaboo (Average)' => ['type1' => 'Ghost', 'type2' => 'Grass', 'moves' => ['Astonish', 'Confuse Ray', 'Trick', 'Scary Face']],
        'Purrloin' => ['type1' => 'Dark', 'type2' => '', 'moves' => ['Scratch', 'Growl', 'Assist', 'Sand Attack']],
        'Purugly' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Fake Out', 'Growl', 'Scratch', 'Swagger']],
        'Pyroar' => ['type1' => 'Fire', 'type2' => 'Normal', 'moves' => ['Ember', 'Hyper Beam', 'Leer', 'Tackle']],
        'Raticate' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Focus Energy', 'Quick Attack', 'Scary Face', 'Swords Dance']],
        'Reshiram' => ['type1' => 'Dragon', 'type2' => 'Fire', 'moves' => ['Dragon Rage', 'Fire Fang', 'Imprison', 'Ancient Power']],
        'Rhyperior' => ['type1' => 'Ground', 'type2' => 'Rock', 'moves' => ['Fury Attack', 'Hammer Arm', 'Horn Attack', 'Horn Drill']],
        'Sableye' => ['type1' => 'Dark', 'type2' => 'Ghost', 'moves' => ['Leer', 'Scratch', 'Foresight', 'Night Shade']],
        'Sandile' => ['type1' => 'Ground', 'type2' => 'Dark', 'moves' => ['Leer', 'Rage', 'Bite', 'Sand Attack']],
        'Scrafty' => ['type1' => 'Dark', 'type2' => 'Fighting', 'moves' => ['Feint Attack', 'Headbutt', 'Leer', 'Sand Attack']],
        'Scraggy' => ['type1' => 'Dark', 'type2' => 'Fighting', 'moves' => ['Headbutt', 'Leer', 'Sand Attack', 'Feint Attack']],
        'Seismitoad' => ['type1' => 'Water', 'type2' => 'Ground', 'moves' => ['Acid', 'Bubble', 'Growl', 'Round']],
        'Seviper' => ['type1' => 'Poison', 'type2' => '', 'moves' => ['Swagger', 'Wrap', 'Bite', 'Lick']],
        'Shadow Porygon2' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Conversion', 'Conversion 2', 'Defense Curl', 'Magic Coat']],
        'Sharpedo' => ['type1' => 'Water', 'type2' => 'Dark', 'moves' => ['Bite', 'Feint', 'Focus Energy', 'Leer']],
        'Shedinja' => ['type1' => 'Bug', 'type2' => 'Ghost', 'moves' => ['Absorb', 'Harden', 'Sand Attack', 'Scratch']],
        'Shiny Arceus (Fire)' => ['type1' => 'Fire', 'type2' => '', 'moves' => ['Cosmic Power', 'Natural Gift', 'Punishment', 'Seismic Toss']],
        'Shiny Dragonite' => ['type1' => 'Dragon', 'type2' => 'Flying', 'moves' => ['Fire Punch', 'Hurricane', 'Leer', 'Roost']],
        'Shiny Munchlax' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Last Resort', 'Lick', 'Metronome', 'Odor Sleuth']],
        'Shiny Umbreon' => ['type1' => 'Dark', 'type2' => '', 'moves' => ['Helping Hand', 'Pursuit', 'Tackle', 'Tail Whip']],
        'Shuckle' => ['type1' => 'Bug', 'type2' => 'Rock', 'moves' => ['Bide', 'Constrict', 'Rollout', 'Sticky Web']],
        'Skuntank' => ['type1' => 'Poison', 'type2' => 'Dark', 'moves' => ['Flamethrower', 'Focus Energy', 'Poison Gas', 'Scratch']],
        'Slugma' => ['type1' => 'Fire', 'type2' => '', 'moves' => ['Smog', 'Yawn', 'Ember', 'Rock Throw']],
        'Stoutland' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Bite', 'Fire Fang', 'Ice Fang', 'Leer']],
        'Stunky' => ['type1' => 'Poison', 'type2' => 'Dark', 'moves' => ['Focus Energy', 'Scratch', 'Poison Gas', 'Screech']],
        'Swellow' => ['type1' => 'Normal', 'type2' => 'Flying', 'moves' => ['Air Slash', 'Brave Bird', 'Focus Energy', 'Growl']],
        'Tangrowth' => ['type1' => 'Grass', 'type2' => '', 'moves' => ['AncientPower', 'Block', 'Constrict', 'Ingrain']],
        'Tentacruel' => ['type1' => 'Water', 'type2' => 'Poison', 'moves' => ['Acid', 'Constrict', 'Poison Sting', 'Reflect Type']],
        'Torkoal' => ['type1' => 'Fire', 'type2' => '', 'moves' => ['Ember', 'Smog', 'Withdraw', 'Rapid Spin']],
        'Toxicroak' => ['type1' => 'Poison', 'type2' => 'Fighting', 'moves' => ['Astonish', 'Mud-Slap', 'Poison Sting', 'Mud-Slap']],
        'Trubbish' => ['type1' => 'Poison', 'type2' => '', 'moves' => ['Poison Gas', 'Pound', 'Recycle', 'Toxic Spikes']],
        'Uxie' => ['type1' => 'Psychic', 'type2' => '', 'moves' => ['Confusion', 'Flail', 'Memento', 'Natural Gift']],
        'Vanilluxe' => ['type1' => 'Ice', 'type2' => '', 'moves' => ['Astonish', 'Freeze-Dry', 'Harden', 'Icicle Spear']],
        'Vibrava' => ['type1' => 'Ground', 'type2' => 'Dragon', 'moves' => ['Bide', 'Dragon Breath', 'Feint Attack', 'Sand Attack']],
        'Victreebel' => ['type1' => 'Grass', 'type2' => 'Poison', 'moves' => ['Leaf Tornado', 'Razor Leaf', 'Sleep Powder', 'Spit Up']],
        'Volbeat' => ['type1' => 'Bug', 'type2' => '', 'moves' => ['Flash', 'Tackle', 'Double Team', 'Confuse Ray']],
        'Walrein' => ['type1' => 'Ice', 'type2' => 'Water', 'moves' => ['Crunch', 'Defense Curl', 'Growl', 'Ice Fang']],
        'Watchog' => ['type1' => 'Normal', 'type2' => '', 'moves' => ['Bite', 'Confuse Ray', 'Leer', 'Low Kick']],
        'Weavile' => ['type1' => 'Dark', 'type2' => 'Ice', 'moves' => ['Assurance', 'Embargo', 'Leer', 'Quick Attack']],
        'Weezing' => ['type1' => 'Poison', 'type2' => '', 'moves' => ['Double Hit', 'Poison Gas', 'Smog', 'Smokescreen']],
        'Woobat' => ['type1' => 'Psychic', 'type2' => 'Flying', 'moves' => ['Confusion', 'Odor Sleuth', 'Gust', 'Assurance']],
        'Wurmple' => ['type1' => 'Bug', 'type2' => '', 'moves' => ['String Shot', 'Tackle', 'Poison Sting', 'Bug Bite']],
        'Yanmega' => ['type1' => 'Bug', 'type2' => 'Flying', 'moves' => ['AncientPower', 'Air Slash', 'Bug Bite', 'Bug Buzz']],
        'Zekrom' => ['type1' => 'Dragon', 'type2' => 'Electric', 'moves' => ['Dragon Rage', 'Thunder Fang', 'Imprison', 'Ancient Power']],
        'Zoroark' => ['type1' => 'Dark', 'type2' => '', 'moves' => ['Hone Claws', 'Imprison', 'Leer', 'Night Daze']],
    ];
    return $species;
}


function pv_event_battle_by_route(string $route): ?array
{
    $needle = trim($route);
    if ($needle === '') return null;
    foreach (pv_event_battle_catalog() as $id => $trainer) {
        if (hash_equals((string)$trainer['route'], $needle)) {
            $trainer['id'] = (int)$id;
            return $trainer;
        }
    }
    return null;
}

function pv_event_battle_progress(array $flags, ?array $ids = null): array
{
    $ids = $ids ?? pv_event_battle_required_ids();
    $total = count($ids); $done = 0;
    foreach ($ids as $id) if ((int)($flags['g'.(int)$id] ?? 0) === 1) $done++;
    return ['done'=>$done,'total'=>$total,'percent'=>$total>0?(int)floor(($done/$total)*100):0];
}

function pv_event_battle_flags(mysqli $db, int $uid): array
{
    $uid=max(1,$uid); $flags=['id'=>$uid];
    foreach(range(1,38) as $id) $flags['g'.$id]=0;
    $stmt=$db->prepare('SELECT * FROM events WHERE id=? LIMIT 1');
    if(!$stmt) return $flags;
    $stmt->bind_param('i',$uid); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if(is_array($row)) foreach($flags as $key=>$value) if(array_key_exists($key,$row)) $flags[$key]=(int)$row[$key];
    return $flags;
}

function pv_event_battle_group_complete(array $flags, string $groupKey): bool
{
    $groups=pv_event_battle_groups();
    if(!isset($groups[$groupKey])) return false;
    foreach((array)$groups[$groupKey]['ids'] as $id) if((int)($flags['g'.(int)$id]??0)!==1) return false;
    return true;
}

function pv_event_battle_completed_badge_count(array $flags): int
{
    $done=0;
    foreach(array_keys(pv_event_battle_groups()) as $groupKey) if(pv_event_battle_group_complete($flags,(string)$groupKey)) $done++;
    return $done;
}

function pv_event_battle_storage_status(mysqli $db): array
{
    $version=0;
    $meta=@$db->query("SELECT version FROM pv_schema_meta WHERE id=1 LIMIT 1");
    if($meta instanceof mysqli_result && ($row=$meta->fetch_assoc())) $version=(int)($row['version']??0);
    $hasEventPokemon=false; $r=@$db->query("SHOW TABLES LIKE 'eventpokemon'");
    if($r instanceof mysqli_result) $hasEventPokemon=$r->num_rows>0;
    $hasG38=false; $r=@$db->query("SHOW COLUMNS FROM events LIKE 'g38'");
    if($r instanceof mysqli_result) $hasG38=$r->num_rows>0;
    $idList=implode(',',array_map('intval',pv_event_battle_required_ids()));
    $trainers=0; $r=@$db->query('SELECT COUNT(*) c FROM event WHERE id IN ('.$idList.')');
    if($r instanceof mysqli_result && ($row=$r->fetch_assoc())) $trainers=(int)($row['c']??0);
    $pokemon=0;
    if($hasEventPokemon){$r=@$db->query('SELECT COUNT(*) c FROM eventpokemon WHERE owner IN ('.$idList.')');if($r instanceof mysqli_result && ($row=$r->fetch_assoc()))$pokemon=(int)($row['c']??0);}
    return ['ready'=>$version>=25 && $hasEventPokemon && $hasG38 && $trainers===32 && $pokemon===181,'schema_version'=>$version,'trainers'=>$trainers,'pokemon'=>$pokemon,'expected_trainers'=>32,'expected_pokemon'=>181];
}

function pv_event_battle_seed(mysqli $db, array &$changes): void
{
    $catalog=pv_event_battle_catalog(); $species=pv_event_battle_species_data();
    $db->begin_transaction();
    try {
        $ownerIds=implode(',',array_map('intval',pv_event_battle_required_ids()));
        if(!$db->query('DELETE FROM eventpokemon WHERE owner IN ('.$ownerIds.')')) throw new RuntimeException('Could not clear stale Special Event NPC Pokémon: '.$db->error);
        $trainerStmt=$db->prepare('INSERT INTO event (id,trainer,s1,s2,s3,s4,s5,s6) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE trainer=VALUES(trainer),s1=VALUES(s1),s2=VALUES(s2),s3=VALUES(s3),s4=VALUES(s4),s5=VALUES(s5),s6=VALUES(s6)');
        $pokeStmt=$db->prepare('INSERT INTO eventpokemon (id,owner,name,a1,a2,a3,a4,lvl,exp,t1,t2,rowner) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE owner=VALUES(owner),name=VALUES(name),a1=VALUES(a1),a2=VALUES(a2),a3=VALUES(a3),a4=VALUES(a4),lvl=VALUES(lvl),exp=VALUES(exp),t1=VALUES(t1),t2=VALUES(t2),rowner=VALUES(rowner)');
        if(!$trainerStmt || !$pokeStmt) throw new RuntimeException('Special Event seed statements could not be prepared.');
        foreach($catalog as $id=>$trainer){
            $ids=[]; $levels=(array)$trainer['levels'];
            foreach((array)$trainer['roster'] as $slot=>$name){
                $pokemonId=2000000+((int)$id*10)+((int)$slot+1); $ids[]=$pokemonId;
                $meta=$species[(string)$name]??null;
                if(!is_array($meta)) throw new RuntimeException('Missing Special Event species metadata for '.$name);
                $moves=array_pad((array)$meta['moves'],4,'Tackle'); $level=max(1,(int)($levels[$slot]??100)); $exp=$level*$level*$level;
                $type1=(string)$meta['type1'];$type2=(string)$meta['type2'];$ownerName=(string)$trainer['display'];$name=(string)$name;
                $a1=(string)$moves[0];$a2=(string)$moves[1];$a3=(string)$moves[2];$a4=(string)$moves[3];$owner=(int)$id;
                $pokeStmt->bind_param('iisssssiisss',$pokemonId,$owner,$name,$a1,$a2,$a3,$a4,$level,$exp,$type1,$type2,$ownerName);
                if(!$pokeStmt->execute()) throw new RuntimeException('Could not seed Special Event Pokémon for '.$ownerName.': '.$pokeStmt->error);
            }
            $slots=array_pad($ids,6,0);$eid=(int)$id;$route=(string)$trainer['route'];
            $s1=$slots[0];$s2=$slots[1];$s3=$slots[2];$s4=$slots[3];$s5=$slots[4];$s6=$slots[5];
            $trainerStmt->bind_param('isiiiiii',$eid,$route,$s1,$s2,$s3,$s4,$s5,$s6);
            if(!$trainerStmt->execute()) throw new RuntimeException('Could not seed Special Event trainer '.$route.': '.$trainerStmt->error);
        }
        $trainerStmt->close();$pokeStmt->close();$db->commit();
    } catch(Throwable $e){$db->rollback();throw $e;}
    $changes[]='Synchronized 32 Special Event opponents and 181 deterministic NPC Pokémon';
}

function pv_event_battle_award(mysqli $db, int $uid, int $opponentId): array
{
    $catalog=pv_event_battle_catalog();
    if($uid<=0 || !isset($catalog[$opponentId])) return ['awarded'=>false,'already'=>false,'group_complete'=>false,'all_complete'=>false,'progress'=>['done'=>0,'total'=>32,'percent'=>0]];
    $column='g'.$opponentId; $group=(string)$catalog[$opponentId]['group'];
    $db->begin_transaction();
    try{
        $stmt=$db->prepare('INSERT INTO events (id) VALUES (?) ON DUPLICATE KEY UPDATE id=VALUES(id)');
        if(!$stmt) throw new RuntimeException('Special Event progression row could not be prepared.');
        $stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();
        $stmt=$db->prepare('SELECT * FROM events WHERE id=? FOR UPDATE');
        if(!$stmt) throw new RuntimeException('Special Event progression could not be locked.');
        $stmt->bind_param('i',$uid);$stmt->execute();$flags=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();
        $already=(int)($flags[$column]??0)===1;
        if(!$already){if(!$db->query('UPDATE events SET `'.$column.'`=1 WHERE id='.(int)$uid)) throw new RuntimeException('Special Event progression could not be updated.');$flags[$column]=1;}
        $groupComplete=pv_event_battle_group_complete($flags,$group);$allComplete=pv_event_battle_progress($flags)['done']===32;
        $db->commit();
        return ['awarded'=>!$already,'already'=>$already,'group_complete'=>$groupComplete,'all_complete'=>$allComplete,'progress'=>pv_event_battle_progress($flags),'badges'=>pv_event_battle_completed_badge_count($flags)];
    } catch(Throwable $e){$db->rollback();throw $e;}
}
