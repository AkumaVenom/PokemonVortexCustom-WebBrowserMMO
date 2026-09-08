<?php
/**
 * Additional Hoenn habitat profiles from original FRLG/Emerald slot data.
 * Existing profiles are retained in hoenn_encounters.php.
 * Source species and slot weights are preserved; original slots above level 24
 * roll 21-24. Fixed original levels <=24 remain fixed. Ordinary Vortex varieties
 * are applied independently by the existing shared human/AI encounter roller.
 * Source evidence and section mapping: docs/data/region_expansion_encounters.json.
 */
return json_decode(<<<'JSON'
{
  "gba-abandoned-ship-hidden-floor-corridors": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        99
      ],
      [
        "Tentacruel",
        21,
        24,
        1
      ]
    ]
  },
  "gba-abandoned-ship-rooms-b1f": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        99
      ],
      [
        "Tentacruel",
        21,
        24,
        1
      ]
    ]
  },
  "gba-cave-of-origin-entrance": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        90
      ],
      [
        "Golbat",
        21,
        24,
        10
      ]
    ]
  },
  "gba-cave-of-origin-1f": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        60
      ],
      [
        "Sableye",
        21,
        24,
        30
      ],
      [
        "Golbat",
        21,
        24,
        10
      ]
    ]
  },
  "gba-desert-underpass": {
    "chance": 0.33,
    "entries": [
      [
        "Ditto",
        21,
        24,
        50
      ],
      [
        "Whismur",
        21,
        24,
        34
      ],
      [
        "Loudred",
        21,
        24,
        16
      ]
    ]
  },
  "gba-magma-hideout-1f": {
    "chance": 0.33,
    "entries": [
      [
        "Geodude",
        21,
        24,
        55
      ],
      [
        "Torkoal",
        21,
        24,
        30
      ],
      [
        "Graveler",
        21,
        24,
        15
      ]
    ]
  },
  "gba-mirage-tower-1f": {
    "chance": 0.33,
    "entries": [
      [
        "Sandshrew",
        21,
        21,
        20
      ],
      [
        "Trapinch",
        21,
        21,
        20
      ],
      [
        "Sandshrew",
        20,
        20,
        20
      ],
      [
        "Trapinch",
        20,
        20,
        20
      ],
      [
        "Sandshrew",
        22,
        22,
        5
      ],
      [
        "Trapinch",
        22,
        22,
        5
      ],
      [
        "Sandshrew",
        23,
        23,
        4
      ],
      [
        "Trapinch",
        23,
        23,
        4
      ],
      [
        "Sandshrew",
        24,
        24,
        1
      ],
      [
        "Trapinch",
        24,
        24,
        1
      ]
    ]
  },
  "gba-mirage-tower-2f": {
    "chance": 0.33,
    "entries": [
      [
        "Sandshrew",
        21,
        21,
        20
      ],
      [
        "Trapinch",
        21,
        21,
        20
      ],
      [
        "Sandshrew",
        20,
        20,
        20
      ],
      [
        "Trapinch",
        20,
        20,
        20
      ],
      [
        "Sandshrew",
        22,
        22,
        5
      ],
      [
        "Trapinch",
        22,
        22,
        5
      ],
      [
        "Sandshrew",
        23,
        23,
        4
      ],
      [
        "Trapinch",
        23,
        23,
        4
      ],
      [
        "Sandshrew",
        24,
        24,
        1
      ],
      [
        "Trapinch",
        24,
        24,
        1
      ]
    ]
  },
  "gba-mirage-tower-3f": {
    "chance": 0.33,
    "entries": [
      [
        "Sandshrew",
        21,
        21,
        20
      ],
      [
        "Trapinch",
        21,
        21,
        20
      ],
      [
        "Sandshrew",
        20,
        20,
        20
      ],
      [
        "Trapinch",
        20,
        20,
        20
      ],
      [
        "Sandshrew",
        22,
        22,
        5
      ],
      [
        "Trapinch",
        22,
        22,
        5
      ],
      [
        "Sandshrew",
        23,
        23,
        4
      ],
      [
        "Trapinch",
        23,
        23,
        4
      ],
      [
        "Sandshrew",
        24,
        24,
        1
      ],
      [
        "Trapinch",
        24,
        24,
        1
      ]
    ]
  },
  "gba-mirage-tower-4f": {
    "chance": 0.33,
    "entries": [
      [
        "Sandshrew",
        21,
        21,
        20
      ],
      [
        "Trapinch",
        21,
        21,
        20
      ],
      [
        "Sandshrew",
        20,
        20,
        20
      ],
      [
        "Trapinch",
        20,
        20,
        20
      ],
      [
        "Sandshrew",
        22,
        22,
        5
      ],
      [
        "Trapinch",
        22,
        22,
        5
      ],
      [
        "Sandshrew",
        23,
        23,
        4
      ],
      [
        "Trapinch",
        23,
        23,
        4
      ],
      [
        "Sandshrew",
        24,
        24,
        1
      ],
      [
        "Trapinch",
        24,
        24,
        1
      ]
    ]
  },
  "gba-meteor-falls-stevens-cave": {
    "chance": 0.33,
    "entries": [
      [
        "Golbat",
        21,
        24,
        65
      ],
      [
        "Solrock",
        21,
        24,
        35
      ]
    ]
  },
  "gba-mt-pyre-3f": {
    "chance": 0.33,
    "entries": [
      [
        "Shuppet",
        21,
        24,
        75
      ],
      [
        "Shuppet",
        24,
        24,
        15
      ],
      [
        "Shuppet",
        23,
        23,
        5
      ],
      [
        "Shuppet",
        22,
        22,
        5
      ]
    ]
  },
  "gba-mt-pyre-4f": {
    "chance": 0.33,
    "entries": [
      [
        "Shuppet",
        21,
        24,
        70
      ],
      [
        "Shuppet",
        24,
        24,
        10
      ],
      [
        "Shuppet",
        23,
        23,
        5
      ],
      [
        "Shuppet",
        22,
        22,
        5
      ],
      [
        "Duskull",
        21,
        24,
        10
      ]
    ]
  },
  "gba-mt-pyre-5f": {
    "chance": 0.33,
    "entries": [
      [
        "Shuppet",
        21,
        24,
        70
      ],
      [
        "Shuppet",
        24,
        24,
        10
      ],
      [
        "Shuppet",
        23,
        23,
        5
      ],
      [
        "Shuppet",
        22,
        22,
        5
      ],
      [
        "Duskull",
        21,
        24,
        10
      ]
    ]
  },
  "gba-route107": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route108": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-underwater-route124": {
    "chance": 0.31,
    "entries": [
      [
        "Clamperl",
        21,
        24,
        65
      ],
      [
        "Chinchou",
        21,
        24,
        30
      ],
      [
        "Relicanth",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route125": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route126": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-underwater-route126": {
    "chance": 0.31,
    "entries": [
      [
        "Clamperl",
        21,
        24,
        65
      ],
      [
        "Chinchou",
        21,
        24,
        30
      ],
      [
        "Relicanth",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route127": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route128": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route129": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        4
      ],
      [
        "Wailord",
        21,
        24,
        1
      ]
    ]
  },
  "gba-route130": {
    "chance": 0.33,
    "entries": [
      [
        "Wynaut",
        21,
        24,
        75
      ],
      [
        "Wynaut",
        20,
        20,
        10
      ],
      [
        "Wynaut",
        15,
        15,
        5
      ],
      [
        "Wynaut",
        10,
        10,
        5
      ],
      [
        "Wynaut",
        5,
        5,
        5
      ],
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route131": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route132": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route133": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-route134": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Wingull",
        21,
        24,
        35
      ],
      [
        "Pelipper",
        21,
        24,
        5
      ]
    ]
  },
  "gba-seafloor-cavern-room5": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        90
      ],
      [
        "Golbat",
        21,
        24,
        10
      ]
    ]
  },
  "gba-seafloor-cavern-room4": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        90
      ],
      [
        "Golbat",
        21,
        24,
        10
      ]
    ]
  },
  "gba-seafloor-cavern-room2": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        90
      ],
      [
        "Golbat",
        21,
        24,
        10
      ]
    ]
  },
  "gba-seafloor-cavern-room6": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        125
      ],
      [
        "Golbat",
        21,
        24,
        15
      ],
      [
        "Tentacool",
        21,
        24,
        60
      ]
    ]
  },
  "gba-seafloor-cavern-room7": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        125
      ],
      [
        "Golbat",
        21,
        24,
        15
      ],
      [
        "Tentacool",
        21,
        24,
        60
      ]
    ]
  },
  "gba-seafloor-cavern-room3": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        90
      ],
      [
        "Golbat",
        21,
        24,
        10
      ]
    ]
  },
  "gba-seafloor-cavern-room8": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        90
      ],
      [
        "Golbat",
        21,
        24,
        10
      ]
    ]
  },
  "gba-shoal-cave-low-tide-inner-room-water": {
    "chance": 0.31,
    "entries": [
      [
        "Tentacool",
        21,
        24,
        60
      ],
      [
        "Zubat",
        21,
        24,
        30
      ],
      [
        "Spheal",
        21,
        24,
        10
      ]
    ]
  },
  "gba-shoal-cave-low-tide-inner-room": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        75
      ],
      [
        "Spheal",
        21,
        24,
        60
      ],
      [
        "Golbat",
        21,
        24,
        5
      ],
      [
        "Tentacool",
        21,
        24,
        60
      ]
    ]
  },
  "gba-shoal-cave-low-tide-stairs-room": {
    "chance": 0.33,
    "entries": [
      [
        "Zubat",
        21,
        24,
        45
      ],
      [
        "Spheal",
        21,
        24,
        50
      ],
      [
        "Golbat",
        21,
        24,
        5
      ]
    ]
  },
  "gba-artisan-cave-b1f": {
    "chance": 0.33,
    "entries": [
      [
        "Smeargle",
        21,
        24,
        100
      ]
    ]
  },
  "gba-artisan-cave-1f": {
    "chance": 0.33,
    "entries": [
      [
        "Smeargle",
        21,
        24,
        100
      ]
    ]
  }
}
JSON, true, 512, JSON_THROW_ON_ERROR);
