<?php
declare(strict_types=1);

/**
 * Authoritative Vortex-world encounter catalogue reconstructed from the
 * recovered mappoke/nightmappoke source pools.
 *
 * The species lists and source rarity/variant windows are preserved. Runtime
 * selection intentionally uses each declared list's real length instead of
 * the historical hand-maintained rand(0, N) bounds, repairing truncated and
 * out-of-range pools without changing the recovered catalogue itself.
 */
return [
    'day' => [
        1 => [
            'source' => 'mappoke1.php',
            'low_level' => [5, 20],
            'low' => [
                'Smeargle',
                'Miltank',
                'Dunsparce',
                'Happiny',
                'Pichu',
                'Rattata',
                'Buneary',
                'Cherubi',
                'Bulbasaur',
                'Oddish',
                'Mankey',
                'Caterpie',
                'Pidgey',
                'Nidoran (F)',
                'Chikorita',
                'Turtwig',
                'Exeggcute',
                'Lotad',
                'Tropius',
                'Wurmple',
                'Burmy (Plant)',
                'Burmy (Steel)',
                'Treecko',
                'Ekans',
                'Igglybuff',
                'Meowth',
                'Ponyta',
                'Farfetchd',
                'Doduo',
                'Lickitung',
                'Scyther',
                'Tauros',
                'Eevee',
                'Sentret',
                'Ledyba',
                'Togepi',
                'Hoppip',
                'Aipom',
                'Sunkern',
                'Yanma',
                'Girafarig',
                'Pineco',
                'Snubbull',
                'Heracross',
                'Skarmory',
                'Phanpy',
                'Stantler',
                'Tyrogue',
                'Taillow',
                'Shroomish',
                'Slakoth',
                'Nincada',
                'Whismur',
                'Skitty',
                'Plusle',
                'Volbeat',
                'Budew',
                'Spinda',
                'Swablu',
                'Zangoose',
                'Seviper',
                'Castform',
                'Kecleon',
                'Starly',
                'Bidoof',
                'Kricketot',
                'Combee',
                'Glameow',
                'Chatot',
                'Munchlax',
                'Snivy',
                'Patrat',
                'Lillipup',
                'Pansage',
                'Pidove',
                'Blitzle',
                'Drilbur',
                'Audino',
                'Sewaddle',
                'Cottonee',
                'Petilil',
                'Maractus',
                'Minccino',
                'Karrablast',
                'Foongus',
                'Ferroseed',
                'Shelmet',
                'Bouffalant',
                'Rufflet',
                'Chespin',
                'Bunnelby',
                'Fletchling',
                'Scatterbug',
                'Litleo',
                'Flabebe (Blue)',
                'Flabebe (Orange)',
                'Flabebe (Red)',
                'Flabebe (White)',
                'Flabebe (Yellow)',
                'Skiddo',
                'Pancham',
                'Furfrou',
                'Espurr',
                'Spritzee',
                'Inkay',
                'Hawlucha',
                'Dedenne',
                'Goomy',
                'Klefki',
                'Swirlix',
                'Bellsprout'
            ],
            'low_source_index_max' => 110,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Shaymin (Sky)', 'Celebi', 'Latias', 'Shaymin', 'Mew', 'Virizion', 'Xerneas (Active)', 'Tornadus'],
            'rare_source_index_max' => 7,
            'legendary_variants' => [
                10 => 'Shiny',
                9 => 'Dark',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => [
                'Pikachu',
                'Raticate',
                'Lopunny',
                'Weepinbell',
                'Beedrill',
                'Ivysaur',
                'Butterfree',
                'Pidgeot',
                'Servine',
                'Herdier',
                'Tranquill',
                'Fletchinder',
                'Spewpa',
                'Swirlix'
            ]
        ],
        10 => [
            'source' => 'mappoke10.php',
            'low_level' => [5, 20],
            'low' => [
                'Tentacool',
                'Slowpoke',
                'Seel',
                'Shellder',
                'Magikarp',
                'Lapras',
                'Wooper',
                'Corsola',
                'Mudkip',
                'Barboach',
                'Spheal',
                'Clamperl',
                'Finneon',
                'Oshawott',
                'Panpour',
                'Basculin (Red Stripe)',
                'Ducklett',
                'Frillish',
                'Alomomola',
                'Castform (Water)',
                'Clauncher',
                'Skrelp',
                'Relicanth'
            ],
            'low_source_index_max' => 22,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Kyogre', 'Lugia', 'Keldeo'],
            'rare_source_index_max' => 3,
            'legendary_variants' => [
                10 => 'Shiny',
                9 => 'Dark',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Dewott', 'Tentacruel', 'Gyarados', 'Marshtomp']
        ],
        3 => [
            'source' => 'mappoke3.php',
            'low_level' => [5, 20],
            'low' => [
                'Venonat',
                'Abra',
                'Drowzee',
                'Mime Jr.',
                'Porygon',
                'Natu',
                'Wynaut',
                'Girafarig',
                'Ralts',
                'Meditite',
                'Volbeat',
                'Illumise',
                'Spoink',
                'Baltoy',
                'Chingling',
                'Kricketot',
                'Drifloon',
                'Bronzor',
                'Exeggcute',
                'Purrloin',
                'Sandile',
                'Scraggy',
                'Sigilyph',
                'Trubbish',
                'Zorua',
                'Gothita',
                'Solosis',
                'Foongus',
                'Elgyem',
                'Golett',
                'Pawniard',
                'Vullaby',
                'Deino',
                'Espurr',
                'Inkay'
            ],
            'low_source_index_max' => 34,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Mew', 'Rotom', 'Mesprit', 'Azelf', 'Uxie', 'Celebi', 'Yveltal'],
            'rare_source_index_max' => 6,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Kadabra', 'Bronzong', 'Hypno', 'Chimecho', 'Malamar', 'Xatu', 'Kirlia']
        ],
        4 => [
            'source' => 'mappoke4.php',
            'low_level' => [5, 20],
            'low' => [
                'Squirtle',
                'Psyduck',
                'Poliwag',
                'Tentacool',
                'Krabby',
                'Goldeen',
                'Staryu',
                'Magikarp',
                'Totodile',
                'Azurill',
                'Lotad',
                'Wingull',
                'Surskit',
                'Wailmer',
                'Corphish',
                'Feebas',
                'Luvdisc',
                'Piplup',
                'Buizel',
                'Oshawott',
                'Panpour',
                'Tympole',
                'Basculin (Red Stripe)',
                'Basculin (Blue Stripe)',
                'Ducklett',
                'Vanillite',
                'Cubchoo',
                'Alomomola',
                'Castform (Water)',
                'Froakie',
                'Clauncher',
                'Horsea',
                'Qwilfish'
            ],
            'low_source_index_max' => 32,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Manaphy', 'Phione', 'Suicune', 'Keldeo'],
            'rare_source_index_max' => 3,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Wartortle', 'Golduck', 'Tentacruel', 'Simipour']
        ],
        5 => [
            'source' => 'mappoke5.php',
            'low_level' => [5, 20],
            'low' => [
                'Rattata',
                'Pidgey',
                'Grimer',
                'Elekid',
                'Magnemite',
                'Voltorb',
                'Pichu',
                'Electrike',
                'Pachirisu',
                'Starly',
                'Mareep',
                'Dunsparce',
                'Swablu',
                'Burmy (Steel)',
                'Koffing',
                'Pidove',
                'Blitzle',
                'Audino',
                'Trubbish',
                'Emolga',
                'Foongus',
                'Joltik',
                'Ferroseed',
                'Klink',
                'Tynamo',
                'Stunfisk',
                'Rufflet',
                'Durant',
                'Fletchling',
                'Helioptile',
                'Dedenne',
                'Klefki'
            ],
            'low_source_index_max' => 31,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 680,
                    'max' => 684,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 685,
                    'max' => 689,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 784],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Zapdos', 'Raikou', 'Thundurus', 'Zekrom', 'Genesect'],
            'rare_source_index_max' => 4,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Raichu', 'Altaria', 'Electabuzz', 'Staravia', 'Pidgeotto', 'Zebstrika']
        ],
        6 => [
            'source' => 'mappoke6.php',
            'low_level' => [5, 20],
            'low' => [
                'Unown (N)',
                'Unown (O)',
                'Unown (P)',
                'Unown (Q)',
                'Unown (R)',
                'Unown (S)',
                'Unown (T)',
                'Unown (U)',
                'Unown (V)',
                'Unown (W)',
                'Unown (X)',
                'Unown (Y)',
                'Unown (Z)',
                'Unown (Ex)',
                'Unown (Qm)',
                'Seel',
                'Smoochum',
                'Sneasel',
                'Swinub',
                'Delibird',
                'Snorunt',
                'Spheal',
                'Snover',
                'Zubat',
                'Slowpoke',
                'Wynaut',
                'Bronzor',
                'Vanillite',
                'Cubchoo',
                'Cryogonal',
                'Castform (Ice)',
                'Bergmite'
            ],
            'low_source_index_max' => 31,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Articuno', 'Suicune', 'Lugia', 'Regice', 'Kyurem', 'Diancie'],
            'rare_source_index_max' => 5,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Dewgong', 'Jynx', 'Weavile', 'Golbat', 'Vanillish', 'Beartic']
        ],
        7 => [
            'source' => 'mappoke7.php',
            'low_level' => [5, 20],
            'low' => [
                'Charmander',
                'Vulpix',
                'Growlithe',
                'Ponyta',
                'Magby',
                'Cyndaquil',
                'Slugma',
                'Torchic',
                'Torkoal',
                'Chimchar',
                'Numel',
                'Houndour',
                'Solrock',
                'Lunatone',
                'Onix',
                'Aron',
                'Trapinch',
                'Larvitar',
                'Tepig',
                'Pansear',
                'Darumaka',
                'Litwick',
                'Heatmor',
                'Larvesta',
                'Castform (Fire)',
                'Fennekin',
                'Litleo'
            ],
            'low_source_index_max' => 26,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Heatran', 'Ho-oh', 'Moltres', 'Entei', 'Reshiram', 'Victini'],
            'rare_source_index_max' => 6,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Magmar', 'Flareon', 'Houndoom', 'Charmeleon', 'Simisear', 'Lampent', 'Pyroar']
        ],
        8 => [
            'source' => 'mappoke8.php',
            'low_level' => [5, 20],
            'low' => [
                'Burmy (Sand)',
                'Sandshrew',
                'Cleffa',
                'Zubat',
                'Paras',
                'Diglett',
                'Machop',
                'Geodude',
                'Onix',
                'Cubone',
                'Rhyhorn',
                'Kangaskhan',
                'Ditto',
                'Dratini',
                'Bonsly',
                'Unown (A)',
                'Unown (B)',
                'Unown (C)',
                'Unown (D)',
                'Unown (E)',
                'Unown (F)',
                'Unown (G)',
                'Unown (H)',
                'Unown (I)',
                'Unown (J)',
                'Unown (K)',
                'Unown (L)',
                'Unown (M)',
                'Gligar',
                'Shuckle',
                'Teddiursa',
                'Phanpy',
                'Larvitar',
                'Makuhita',
                'Nosepass',
                'Mawile',
                'Aron',
                'Trapinch',
                'Bagon',
                'Beldum',
                'Riolu',
                'Hippopotas',
                'Roggenrola',
                'Drilbur',
                'Timburr',
                'Tympole',
                'Throh',
                'Sawk',
                'Sandile',
                'Dwebble',
                'Scraggy',
                'Pawniard',
                'Durant',
                'Deino',
                'Axew',
                'Druddigon',
                'Golett',
                'Mienfoo',
                'Binacle',
                'Helioptile',
                'Carbink',
                'Noibat',
                'Gible'
            ],
            'low_source_index_max' => 62,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => [
                'Groudon',
                'Arceus',
                'Regigigas',
                'Palkia',
                'Dialga',
                'Deoxys',
                'Jirachi',
                'Registeel',
                'Regirock',
                'Mewtwo',
                'Cobalion',
                'Terrakion',
                'Virizion',
                'Reshiram',
                'Zekrom',
                'Kyurem',
                'Genesect',
                'Landorus',
                'Zygarde',
                'Diancie'
            ],
            'rare_source_index_max' => 20,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => [
                'Steelix',
                'Metang',
                'Bronzong',
                'Graveler',
                'Golem',
                'Onix',
                'Pupitar',
                'Fraxure',
                'Boldore',
                'Excadrill',
                'Ferrothorn',
                'Zweilous',
                'Barbaracle',
                'Heliolisk',
                'Noivern',
                'Vibrava',
                'Shelgon',
                'Machoke',
                'Golurk',
                'Dugtrio',
                'Lairon',
                'Gurdurr'
            ]
        ]
    ],
    'night' => [
        1 => [
            'source' => 'nightmappoke1.php',
            'low_level' => [5, 20],
            'low' => [
                'Smeargle',
                'Cacnea',
                'Pichu (Notched)',
                'Rattata',
                'Buneary',
                'Oddish',
                'Mankey',
                'Weedle',
                'Nidoran (M)',
                'Carnivine',
                'Seedot',
                'Zigzagoon',
                'Spearow',
                'Ekans',
                'Venonat',
                'Meowth',
                'Ponyta',
                'Tangela',
                'Scyther',
                'Pinsir',
                'Tauros',
                'Eevee',
                'Sentret',
                'Hoothoot',
                'Spinarak',
                'Yanma',
                'Murkrow',
                'Girafarig',
                'Pineco',
                'Snubbull',
                'Heracross',
                'Skarmory',
                'Phanpy',
                'Stantler',
                'Poochyena',
                'Slakoth',
                'Nincada',
                'Whismur',
                'Minun',
                'Illumise',
                'Spinda',
                'Swablu',
                'Zangoose',
                'Seviper',
                'Kecleon',
                'Absol',
                'Kricketot',
                'Combee',
                'Glameow',
                'Stunky',
                'Skorupi',
                'Croagunk',
                'Snivy',
                'Patrat',
                'Lillipup',
                'Drilbur',
                'Venipede',
                'Maractus',
                'Trubbish',
                'Deerling (Autumn)',
                'Deerling (Spring)',
                'Deerling (Summer)',
                'Deerling (Winter)',
                'Foongus',
                'Joltik',
                'Ferroseed',
                'Shelmet',
                'Bouffalant',
                'Rufflet',
                'Durant',
                'Bunnelby',
                'Pancham',
                'Inkay',
                'Hawlucha',
                'Dedenne',
                'Goomy',
                'Klefki'
            ],
            'low_source_index_max' => 76,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Latios', 'Rayquaza', 'Cresselia', 'Azelf', 'Uxie', 'Mesprit', 'Genesect', 'Yveltal'],
            'rare_source_index_max' => 8,
            'legendary_variants' => [
                10 => 'Shiny',
                9 => 'Dark',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => [
                'Pikachu',
                'Raticate',
                'Lopunny',
                'Weepinbell',
                'Beedrill',
                'Ivysaur',
                'Butterfree',
                'Pidgeot',
                'Servine',
                'Herdier',
                'Tranquill',
                'Fletchinder',
                'Spewpa',
                'Swirlix'
            ]
        ],
        10 => [
            'source' => 'nightmappoke10.php',
            'low_level' => [5, 20],
            'low' => [
                'Tentacool',
                'Slowpoke',
                'Seel',
                'Shellder',
                'Magikarp',
                'Lapras',
                'Wooper',
                'Corsola',
                'Mudkip',
                'Barboach',
                'Spheal',
                'Clamperl',
                'Finneon',
                'Oshawott',
                'Panpour',
                'Basculin (Red Stripe)',
                'Ducklett',
                'Frillish',
                'Alomomola',
                'Castform (Water)',
                'Clauncher',
                'Skrelp',
                'Relicanth'
            ],
            'low_source_index_max' => 22,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Kyogre', 'Lugia', 'Keldeo'],
            'rare_source_index_max' => 3,
            'legendary_variants' => [
                10 => 'Shiny',
                9 => 'Dark',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Dewott', 'Tentacruel', 'Gyarados', 'Marshtomp']
        ],
        3 => [
            'source' => 'nightmappoke3.php',
            'low_level' => [5, 20],
            'low' => [
                'Venonat',
                'Abra',
                'Gastly',
                'Drowzee',
                'Hoothoot',
                'Porygon',
                'Spinarak',
                'Natu',
                'Murkrow',
                'Misdreavus',
                'Girafarig',
                'Poochyena',
                'Sableye',
                'Volbeat',
                'Illumise',
                'Baltoy',
                'Shuppet',
                'Duskull',
                'Drifloon',
                'Bronzor',
                'Spiritomb',
                'Purrloin',
                'Munna',
                'Woobat',
                'Sigilyph',
                'Yamask',
                'Zorua',
                'Gothita',
                'Solosis',
                'Foongus',
                'Elgyem',
                'Litwick',
                'Golett',
                'Pawniard',
                'Deino',
                'Espurr',
                'Phantump',
                'Pumpkaboo (Average)',
                'Inkay',
                'Honedge',
                'Pumpkaboo (Large)',
                'Pumpkaboo (Super)',
                'Pumpkaboo (Small)'
            ],
            'low_source_index_max' => 42,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Giratina', 'Rotom', 'Darkrown', 'Darkrai', 'Yveltal'],
            'rare_source_index_max' => 4,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => [
                'Haunter',
                'Kadabra',
                'Bronzong',
                'Hypno',
                'Noctowl',
                'Venomoth',
                'Ariados',
                'Dusclops',
                'Malamar'
            ]
        ],
        4 => [
            'source' => 'nightmappoke4.php',
            'low_level' => [5, 20],
            'low' => [
                'Squirtle',
                'Poliwag',
                'Tentacool',
                'Krabby',
                'Staryu',
                'Magikarp',
                'Chinchou',
                'Azurill',
                'Remoraid',
                'Wailmer',
                'Corphish',
                'Feebas',
                'Luvdisc',
                'Buizel',
                'Shellos (East)',
                'Shellos (West)',
                'Mantyke',
                'Oshawott',
                'Panpour',
                'Tympole',
                'Basculin (Red Stripe)',
                'Basculin (Blue Stripe)',
                'Vanillite',
                'Frillish',
                'Alomomola',
                'Castform (Water)',
                'Skrelp',
                'Carvanha'
            ],
            'low_source_index_max' => 27,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Manaphy', 'Phione', 'Suicune'],
            'rare_source_index_max' => 2,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Wartortle', 'Tentacruel', 'Simipour']
        ],
        5 => [
            'source' => 'nightmappoke5.php',
            'low_level' => [5, 20],
            'low' => [
                'Rattata',
                'Spearow',
                'Grimer',
                'Elekid',
                'Magnemite',
                'Voltorb',
                'Shinx',
                'Electrike',
                'Pachirisu',
                'Hoothoot',
                'Gulpin',
                'Stunky',
                'Swablu',
                'Koffing',
                'Blitzle',
                'Drilbur',
                'Audino',
                'Trubbish',
                'Foongus',
                'Joltik',
                'Ferroseed',
                'Klink',
                'Tynamo',
                'Stunfisk',
                'Rufflet',
                'Durant',
                'Pichu (Notched)',
                'Dedenne',
                'Klefki'
            ],
            'low_source_index_max' => 28,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Zapdos', 'Darkrai', 'Darkrown', 'Jirachi', 'Zekrom'],
            'rare_source_index_max' => 4,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Raichu', 'Electabuzz', 'Zebstrika', 'Excadrill', 'Whirlipede']
        ],
        6 => [
            'source' => 'nightmappoke6.php',
            'low_level' => [5, 20],
            'low' => [
                'Unown (N)',
                'Unown (O)',
                'Unown (P)',
                'Unown (Q)',
                'Unown (R)',
                'Unown (S)',
                'Unown (T)',
                'Unown (U)',
                'Unown (V)',
                'Unown (W)',
                'Unown (X)',
                'Unown (Y)',
                'Unown (Z)',
                'Unown (Ex)',
                'Unown (Qm)',
                'Seel',
                'Smoochum',
                'Sneasel',
                'Swinub',
                'Delibird',
                'Snorunt',
                'Spheal',
                'Snover',
                'Zubat',
                'Slowpoke',
                'Wynaut',
                'Bronzor',
                'Vanillite',
                'Cubchoo',
                'Cryogonal',
                'Castform (Ice)',
                'Bergmite'
            ],
            'low_source_index_max' => 3,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Articuno', 'Suicune', 'Lugia', 'Regice', 'Kyurem', 'Diancie'],
            'rare_source_index_max' => 5,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Dewgong', 'Jynx', 'Weavile', 'Golbat', 'Vanillish', 'Beartic']
        ],
        7 => [
            'source' => 'nightmappoke7.php',
            'low_level' => [5, 20],
            'low' => [
                'Charmander',
                'Vulpix',
                'Growlithe',
                'Ponyta',
                'Magby',
                'Cyndaquil',
                'Slugma',
                'Torchic',
                'Torkoal',
                'Chimchar',
                'Numel',
                'Houndour',
                'Solrock',
                'Lunatone',
                'Onix',
                'Aron',
                'Trapinch',
                'Larvitar',
                'Tepig',
                'Pansear',
                'Darumaka',
                'Litwick',
                'Heatmor',
                'Larvesta',
                'Castform (Fire)',
                'Fennekin',
                'Litleo'
            ],
            'low_source_index_max' => 26,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => ['Heatran', 'Ho-oh', 'Moltres', 'Entei', 'Reshiram', 'Victini'],
            'rare_source_index_max' => 6,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => ['Magmar', 'Flareon', 'Houndoom', 'Charmeleon', 'Simisear', 'Lampent', 'Pyroar']
        ],
        8 => [
            'source' => 'nightmappoke8.php',
            'low_level' => [5, 20],
            'low' => [
                'Burmy (Sand)',
                'Sandshrew',
                'Cleffa',
                'Zubat',
                'Paras',
                'Diglett',
                'Machop',
                'Geodude',
                'Onix',
                'Cubone',
                'Rhyhorn',
                'Kangaskhan',
                'Ditto',
                'Dratini',
                'Bonsly',
                'Unown (A)',
                'Unown (B)',
                'Unown (C)',
                'Unown (D)',
                'Unown (E)',
                'Unown (F)',
                'Unown (G)',
                'Unown (H)',
                'Unown (I)',
                'Unown (J)',
                'Unown (K)',
                'Unown (L)',
                'Unown (M)',
                'Gligar',
                'Shuckle',
                'Teddiursa',
                'Phanpy',
                'Larvitar',
                'Makuhita',
                'Nosepass',
                'Mawile',
                'Aron',
                'Trapinch',
                'Bagon',
                'Beldum',
                'Riolu',
                'Hippopotas',
                'Roggenrola',
                'Drilbur',
                'Timburr',
                'Tympole',
                'Throh',
                'Sawk',
                'Sandile',
                'Dwebble',
                'Scraggy',
                'Pawniard',
                'Durant',
                'Deino',
                'Axew',
                'Druddigon',
                'Golett',
                'Mienfoo',
                'Binacle',
                'Helioptile',
                'Carbink',
                'Noibat',
                'Gible'
            ],
            'low_source_index_max' => 62,
            'variant_windows' => [
                [
                    'min' => 665,
                    'max' => 669,
                    'variant' => 'Shiny'
                ],
                [
                    'min' => 670,
                    'max' => 674,
                    'variant' => 'Mystic'
                ],
                [
                    'min' => 675,
                    'max' => 679,
                    'variant' => 'Dark'
                ],
                [
                    'min' => 700,
                    'max' => 704,
                    'variant' => 'Metallic'
                ],
                [
                    'min' => 705,
                    'max' => 709,
                    'variant' => 'Shadow'
                ]
            ],
            'rare_signal' => [782, 783],
            'rare_roll' => [1, 25],
            'legendary_roll_max' => 12,
            'legendary_level' => [23, 24],
            'legendary' => [
                'Groudon',
                'Arceus',
                'Regigigas',
                'Palkia',
                'Dialga',
                'Deoxys',
                'Jirachi',
                'Registeel',
                'Regirock',
                'Mewtwo',
                'Cobalion',
                'Terrakion',
                'Virizion',
                'Reshiram',
                'Zekrom',
                'Kyurem',
                'Genesect',
                'Landorus',
                'Zygarde',
                'Diancie'
            ],
            'rare_source_index_max' => 20,
            'legendary_variants' => [
                10 => 'Dark',
                9 => 'Shiny',
                8 => 'Mystic',
                11 => 'Metallic',
                12 => 'Shadow'
            ],
            'high_level' => [22, 24],
            'high' => [
                'Steelix',
                'Metang',
                'Bronzong',
                'Graveler',
                'Golem',
                'Onix',
                'Pupitar',
                'Fraxure',
                'Boldore',
                'Excadrill',
                'Ferrothorn',
                'Zweilous',
                'Barbaracle',
                'Heliolisk',
                'Noivern',
                'Vibrava',
                'Shelgon',
                'Machoke',
                'Golurk',
                'Dugtrio',
                'Lairon',
                'Gurdurr'
            ]
        ]
    ]
];
