<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED') && !defined('PV_SCHEMA_MIGRATIONS')) { http_response_code(404); exit; }

function pv_admin_social_migrate(mysqli $db, array &$changes): void
{
    pv_schema_ensure_table($db, 'console_trade_blocks', "CREATE TABLE `console_trade_blocks` (
        `user_id` INT NOT NULL,
        `target_id` INT NOT NULL,
        `created_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`,`target_id`),
        KEY `idx_console_trade_blocks_target` (`target_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    // The legacy unique(sender,receiver,status) forbids a second accepted or
    // declined request after friends remove/re-add each other. Preserve all
    // historical rows while keeping duplicate pending requests impossible.
    if (pv_schema_table_exists($db, 'friend_requests')) {
        pv_schema_add_column($db, 'friend_requests', 'pending_guard', "TINYINT GENERATED ALWAYS AS (CASE WHEN status='pending' THEN 1 ELSE NULL END) STORED", $changes);
        pv_schema_add_index($db, 'friend_requests', 'uq_friend_request_pending', '`sender_id`,`receiver_id`,`pending_guard`', $changes, true);
        pv_schema_add_index($db, 'friend_requests', 'idx_friend_request_pair_status', '`sender_id`,`receiver_id`,`status`', $changes);
        if (pv_schema_index_exists($db, 'friend_requests', 'uq_friend_request_pair')) {
            if (!$db->query('ALTER TABLE `friend_requests` DROP INDEX `uq_friend_request_pair`')) {
                throw new RuntimeException('Could not repair friend-request history uniqueness.');
            }
            $changes[] = 'Preserved friend-request history and restricted uniqueness to pending requests';
        }
    }
}
