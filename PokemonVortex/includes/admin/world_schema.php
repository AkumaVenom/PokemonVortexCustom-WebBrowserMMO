<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED') && !defined('PV_SCHEMA_MIGRATIONS')) { http_response_code(404); exit; }

function pv_admin_world_migrate(mysqli $db, array &$changes): void
{
    pv_schema_ensure_table($db, 'console_world_spawns', "CREATE TABLE console_world_spawns (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL, world_key VARCHAR(24) NOT NULL, map_key VARCHAR(64) NOT NULL,
        species_id INT NOT NULL, level INT NOT NULL, created_at BIGINT NOT NULL,
        expires_at BIGINT NOT NULL, claimed_at BIGINT NOT NULL DEFAULT 0, removed_at BIGINT NOT NULL DEFAULT 0,
        KEY idx_spawn_owner (user_id,claimed_at,removed_at,expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_table($db, 'console_events', "CREATE TABLE console_events (
        event_key VARCHAR(64) NOT NULL PRIMARY KEY, label VARCHAR(120) NOT NULL,
        summary VARCHAR(500) NOT NULL, created_at BIGINT NOT NULL, started_at BIGINT NOT NULL DEFAULT 0,
        stopped_at BIGINT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_table($db, 'console_event_participants', "CREATE TABLE console_event_participants (
        event_key VARCHAR(64) NOT NULL, user_id INT NOT NULL, joined_at BIGINT NOT NULL,
        left_at BIGINT NOT NULL DEFAULT 0, PRIMARY KEY(event_key,user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_table($db, 'console_event_rewards', "CREATE TABLE console_event_rewards (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, event_key VARCHAR(64) NOT NULL,
        user_id INT NOT NULL, reward_json TEXT NOT NULL, granted_at BIGINT NOT NULL,
        operator_name VARCHAR(80) NOT NULL, KEY idx_event_reward (event_key,user_id,granted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_table($db, 'console_player_titles', "CREATE TABLE console_player_titles (
        user_id INT NOT NULL, title VARCHAR(80) NOT NULL, granted_at BIGINT NOT NULL,
        PRIMARY KEY(user_id,title)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    foreach (['mapusers','world_map_positions','members_options','console_world_spawns','console_events','console_event_participants','console_event_rewards','console_player_titles'] as $table) {
        pv_schema_ensure_innodb($db, $table, $changes);
    }
}
