<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED') && !defined('PV_SCHEMA_MIGRATIONS')) { http_response_code(404); exit; }

/** Additive account-console storage. Kept separate from CLI command handlers. */
function pv_admin_accounts_migrate(mysqli $db, array &$changes): void
{
    pv_schema_ensure_table($db, 'console_player_state', "CREATE TABLE console_player_state (
        user_id INT NOT NULL PRIMARY KEY,
        rank_name VARCHAR(24) NOT NULL DEFAULT 'PLAYER',
        locked TINYINT NOT NULL DEFAULT 0, frozen TINYINT NOT NULL DEFAULT 0,
        jail_until BIGINT NOT NULL DEFAULT 0, ban_until BIGINT NOT NULL DEFAULT 0,
        session_epoch BIGINT NOT NULL DEFAULT 0, afk TINYINT NOT NULL DEFAULT 0,
        invisible TINYINT NOT NULL DEFAULT 0, play_seconds BIGINT NOT NULL DEFAULT 0,
        last_seen BIGINT NOT NULL DEFAULT 0, title VARCHAR(80) NOT NULL DEFAULT '',
        pose VARCHAR(16) NOT NULL DEFAULT '', data_revision BIGINT NOT NULL DEFAULT 0,
        location_revision BIGINT NOT NULL DEFAULT 0, location_json TEXT NULL,
        heal_revision BIGINT NOT NULL DEFAULT 0,
        KEY idx_console_player_presence (invisible,afk,last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_add_column($db, 'console_player_state', 'heal_revision', 'BIGINT NOT NULL DEFAULT 0', $changes);
    pv_schema_ensure_table($db, 'console_moderation', "CREATE TABLE console_moderation (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL, username VARCHAR(45) NOT NULL,
        action_name VARCHAR(32) NOT NULL, reason VARCHAR(1000) NOT NULL DEFAULT '',
        operator_name VARCHAR(80) NOT NULL, created_at BIGINT NOT NULL,
        expires_at BIGINT NOT NULL DEFAULT 0,
        KEY idx_console_moderation_user (user_id,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    // Account reset/delete covers legacy relationship tables as well as modern ones.
    // Every table touched by the transaction must participate in rollback.
    foreach (['members','members_options','pokemon','pokemon_stats','pguide','items','badges','events',
        'comments','done_event','blocked','friends','clans','clan_members','clan_requests','clan_applications',
        'online','mapusers','world_map_positions','world_map_blocks','messages','message_notify',
        'trainer_messages','trainer_friends','trainer_blocks','friend_requests','password_resets',
        'upfortrade','trade_offers','trade_offer_items','utraded','live_battle','live_battle_members',
        'live_battle_offer','live_battle_challenges','trainer_rank_state','rival_battles','rival_retaliations',
        'ai_activity','bot_trainers','shop_transactions','move_lab_transactions','wild_battle_results',
        'event_completions','flashchat_connections','flashchat_users','reg','login_trys',
        'console_player_state','console_moderation'] as $table) {
        pv_schema_ensure_innodb($db, $table, $changes);
    }
}
