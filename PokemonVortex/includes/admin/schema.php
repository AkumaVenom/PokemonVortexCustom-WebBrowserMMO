<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED') && !defined('PV_SCHEMA_MIGRATIONS')) { http_response_code(404); exit; }

function pv_admin_schema_migrate(mysqli $db, array &$changes): void
{
    pv_schema_ensure_table($db, 'console_settings', "CREATE TABLE console_settings (
        setting_key VARCHAR(96) NOT NULL PRIMARY KEY, value_json LONGTEXT NOT NULL,
        updated_at BIGINT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_table($db, 'console_audit', "CREATE TABLE console_audit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, created_at BIGINT NOT NULL,
        operator_name VARCHAR(80) NOT NULL, operator_rank VARCHAR(24) NOT NULL,
        command_name VARCHAR(64) NOT NULL, target_text VARCHAR(190) NOT NULL DEFAULT '',
        arguments_json TEXT NOT NULL, outcome VARCHAR(24) NOT NULL, result_text TEXT NOT NULL,
        KEY idx_console_audit_time(created_at), KEY idx_console_audit_command(command_name,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    foreach (['accounts','pokemon','world','social'] as $module) {
        require_once __DIR__ . '/' . $module . '_schema.php';
        ('pv_admin_' . $module . '_migrate')($db, $changes);
    }
    if (!$db->query("INSERT INTO console_settings(setting_key,value_json,updated_at) VALUES('schema_version','330001',UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json),updated_at=VALUES(updated_at)")) {
        throw new RuntimeException('Could not record console migration version.');
    }
}
