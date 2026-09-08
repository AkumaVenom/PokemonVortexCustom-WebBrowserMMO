<?php
declare(strict_types=1);

return [
    'app_name' => 'Pokemon Vortex NXT',
    'base_path' => '/PokemonVortex',
    'debug' => false,
    'asset_version' => '28.0.0',
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
    // Local operator console logging. Disable this on hosts where activity logs are not wanted.
    'server_console' => [
        'enabled' => true,
        'request_logging' => true,
        // Successful map-presence heartbeats are background synchronization noise, not operator activity.
        'suppress_presence_polls' => true,
        // Diagnostic warnings/errors remain in normal PHP/Apache logs but never enter the operator activity stream.
        'suppress_diagnostics' => true,
        'log_file' => 'storage/logs/worldserver.log',
        'max_file_bytes' => 20971520,
        'retained_files' => 5,
    ],
];
