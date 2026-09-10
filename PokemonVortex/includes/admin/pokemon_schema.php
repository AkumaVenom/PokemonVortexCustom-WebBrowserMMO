<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED') && !defined('PV_SCHEMA_MIGRATIONS')) { http_response_code(404); exit; }

function pv_admin_pokemon_migrate(mysqli $db, array &$changes): void
{
    pv_schema_add_column($db, 'pokemon_stats', 'nickname', "VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL DEFAULT ''", $changes);
    foreach (['hp','attack','defense','spatk','spdef','speed'] as $stat) {
        pv_schema_add_column($db, 'pokemon_stats', $stat.'_ev', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0', $changes);
    }
    pv_schema_ensure_table($db, 'console_test_battles', "CREATE TABLE console_test_battles (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        operator_name VARCHAR(80) NOT NULL,
        player_id INT NOT NULL,
        opponent_id INT NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'active',
        state_json MEDIUMTEXT NOT NULL,
        created_at BIGINT NOT NULL,
        updated_at BIGINT NOT NULL,
        KEY idx_console_test_operator (operator_name,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
}
