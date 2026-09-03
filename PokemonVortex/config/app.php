<?php
declare(strict_types=1);

return [
    'app_name' => 'Pokémon Vortex',
    'base_path' => '/PokemonVortex',
    'debug' => false,
    'asset_version' => '23.5.1',
    // Set to 'kyurem', 'pikachu2015', or 'none'. This private config controls the live Event Center rotation.
    'active_event' => 'none',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'pokemon_vortex',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'from' => 'no-reply@localhost',
    ],
];
