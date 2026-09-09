<?php
declare(strict_types=1);
require_once __DIR__ . '/battle_catalog.php';
require_once __DIR__ . '/event_battle_catalog.php';
require_once __DIR__ . '/sidequest_catalog.php';
require_once __DIR__ . '/bot_runtime.php';

/**
 * Pokémon Vortex recovered-schema compatibility layer.
 *
 * The historical source set came from several server revisions whose PHP files
 * do not all agree on the exact table layout.  These migrations are deliberately
 * additive and idempotent so an existing trainer database can be repaired
 * without deleting progress.
 */

function pv_schema_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function pv_schema_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}


function pv_schema_table_engine(mysqli $db, string $table): string
{
    $stmt = $db->prepare('SELECT engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$stmt) return '';
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return strtoupper((string)($row['engine'] ?? ''));
}

function pv_schema_ensure_innodb(mysqli $db, string $table, array &$changes): void
{
    if (!pv_schema_table_exists($db, $table)) return;
    if (pv_schema_table_engine($db, $table) === 'INNODB') return;
    if (!$db->query('ALTER TABLE ' . pv_schema_quote_identifier($table) . ' ENGINE=InnoDB')) {
        throw new RuntimeException("Could not convert {$table} to InnoDB: " . $db->error);
    }
    $changes[] = "Converted {$table} to InnoDB";
}

function pv_schema_column_exists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function pv_schema_index_exists(mysqli $db, string $table, string $index): bool
{
    $stmt = $db->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function pv_schema_add_column(mysqli $db, string $table, string $column, string $definition, array &$changes): void
{
    if (!pv_schema_table_exists($db, $table) || pv_schema_column_exists($db, $table, $column)) return;
    $sql = 'ALTER TABLE ' . pv_schema_quote_identifier($table)
         . ' ADD COLUMN ' . pv_schema_quote_identifier($column) . ' ' . $definition;
    if (!$db->query($sql)) {
        throw new RuntimeException("Could not add {$table}.{$column}: " . $db->error);
    }
    $changes[] = "Added {$table}.{$column}";
}

function pv_schema_add_index(mysqli $db, string $table, string $index, string $columns, array &$changes, bool $unique = false): void
{
    if (!pv_schema_table_exists($db, $table) || pv_schema_index_exists($db, $table, $index)) return;
    $sql = 'ALTER TABLE ' . pv_schema_quote_identifier($table)
         . ' ADD ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . pv_schema_quote_identifier($index)
         . ' (' . $columns . ')';
    if (!$db->query($sql)) {
        throw new RuntimeException("Could not add index {$table}.{$index}: " . $db->error);
    }
    $changes[] = "Added index {$table}.{$index}";
}

function pv_schema_ensure_table(mysqli $db, string $table, string $sql, array &$changes): void
{
    if (pv_schema_table_exists($db, $table)) return;
    if (!$db->query($sql)) {
        throw new RuntimeException("Could not create {$table}: " . $db->error);
    }
    $changes[] = "Created {$table}";
}

/**
 * Apply every additive compatibility migration required by the recovered PHP
 * runtime. Returns a concise list of actual changes performed.
 */
function pv_apply_schema_migrations(mysqli $db): array
{
    $changes = [];
    $db->set_charset('utf8mb4');

    // Account/runtime fields referenced by legacy dashboard, maps, battles and clans.
    $memberColumns = [
        'llogin' => 'BIGINT NOT NULL DEFAULT 0',
        'total_poke' => 'INT NOT NULL DEFAULT 0',
        'btime' => 'BIGINT NOT NULL DEFAULT 0',
        'time' => 'BIGINT NOT NULL DEFAULT 0',
        'clan_tag' => "VARCHAR(45) NOT NULL DEFAULT ''",
        'wins' => 'INT NOT NULL DEFAULT 0',
        'losses' => 'INT NOT NULL DEFAULT 0',
        'sv' => 'INT NOT NULL DEFAULT 0',
        'sv2' => 'INT NOT NULL DEFAULT 0',
        'ev' => 'INT NOT NULL DEFAULT 0',
        'ev2' => 'INT NOT NULL DEFAULT 0',
        'memonmap' => 'TINYINT NOT NULL DEFAULT 1',
        'messonoff' => 'TINYINT NOT NULL DEFAULT 1',
        'messnotifyonoff' => 'TINYINT NOT NULL DEFAULT 1',
        'messnotify' => 'TINYINT NOT NULL DEFAULT 0',
        // v32 account-persistent audio; additive defaults also cover new signups.
        'sound_enabled' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1',
        'music_volume' => 'TINYINT UNSIGNED NOT NULL DEFAULT 35',
        'sfx_volume' => 'TINYINT UNSIGNED NOT NULL DEFAULT 70',
        'audio_revision' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
    ];
    foreach ($memberColumns as $column => $definition) {
        pv_schema_add_column($db, 'members', $column, $definition, $changes);
    }

    foreach ([
        'messnotify' => 'TINYINT NOT NULL DEFAULT 0',
        'event' => "VARCHAR(80) NOT NULL DEFAULT ''",
        'event_page' => 'TINYINT NOT NULL DEFAULT 0',
    ] as $column => $definition) {
        pv_schema_add_column($db, 'members_options', $column, $definition, $changes);
    }

    foreach ([
        'ball' => "VARCHAR(45) NOT NULL DEFAULT 'Poke Ball'",
        'gender' => "VARCHAR(12) NOT NULL DEFAULT ''",
        'ot' => "VARCHAR(45) NOT NULL DEFAULT ''",
        'happiness' => 'INT NOT NULL DEFAULT 0',
        'display_form' => "VARCHAR(80) NOT NULL DEFAULT ''",
    ] as $column => $definition) {
        pv_schema_add_column($db, 'pokemon_stats', $column, $definition, $changes);
    }

    // Trading data was truncated in the recovered base dump.
    foreach ([
        'pid' => 'INT NOT NULL DEFAULT 0',
        'name' => "VARCHAR(80) NOT NULL DEFAULT ''",
        'a1' => 'VARCHAR(80) NULL',
        'a2' => 'VARCHAR(80) NULL',
        'a3' => 'VARCHAR(80) NULL',
        'a4' => 'VARCHAR(80) NULL',
        'lvl' => 'INT NOT NULL DEFAULT 1',
        'exp' => 'BIGINT NOT NULL DEFAULT 0',
        'rowner' => 'VARCHAR(45) NULL',
        'date' => 'BIGINT NOT NULL DEFAULT 0',
    ] as $column => $definition) {
        pv_schema_add_column($db, 'upfortrade', $column, $definition, $changes);
    }

    // Convert stateful gameplay tables to InnoDB so multi-table actions (trading,
    // account creation, events, fossils, evolution and item consumption) can be committed or rolled back atomically.
    foreach (['members','members_options','items','pokemon','pokemon_stats','pguide','badges','events','comments','done_event','promo_codes','clans','clan_members','upfortrade'] as $transactionalTable) {
        pv_schema_ensure_innodb($db, $transactionalTable, $changes);
    }

    // Transaction-safe trading. The recovered `utraded` representation grouped offers by
    // timestamps and detached Pokémon without a reliable parent offer row.  Keep that table for
    // import compatibility, but all new gameplay uses normalized InnoDB offer tables.
    pv_schema_ensure_innodb($db, 'pokemon', $changes);
    pv_schema_ensure_innodb($db, 'upfortrade', $changes);
    pv_schema_ensure_table($db, 'trade_offers', "CREATE TABLE `trade_offers` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `listing_id` INT NOT NULL,
        `listing_pokemon_id` INT NOT NULL,
        `listing_owner_id` INT NOT NULL,
        `offerer_id` INT NOT NULL,
        `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
        `created_at` BIGINT NOT NULL DEFAULT 0,
        `resolved_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_trade_offer_listing` (`listing_id`,`status`),
        KEY `idx_trade_offer_owner` (`listing_owner_id`,`status`),
        KEY `idx_trade_offer_offerer` (`offerer_id`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_table($db, 'trade_offer_items', "CREATE TABLE `trade_offer_items` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `offer_id` BIGINT UNSIGNED NOT NULL,
        `pokemon_id` INT NOT NULL,
        `original_owner_id` INT NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_trade_offer_items_pokemon` (`pokemon_id`),
        KEY `idx_trade_offer_items_offer` (`offer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    // Recover any offers created by the historical timestamp-grouped trade code before
    // this upgrade.  Linked offers are imported into the normalized tables; orphaned
    // escrow is returned to its original trainer instead of leaving owner=0 Pokémon.
    if (pv_schema_table_exists($db, 'utraded') && pv_schema_table_exists($db, 'trade_offers') && pv_schema_table_exists($db, 'trade_offer_items')) {
        @$db->query("INSERT INTO trade_offers (listing_id,listing_pokemon_id,listing_owner_id,offerer_id,status,created_at,resolved_at)
            SELECT u.id,u.pid,u.owner,t.owner,'pending',t.time,0
            FROM upfortrade u
            JOIN (SELECT oid,owner,time FROM utraded GROUP BY oid,owner,time) t ON t.oid=u.pid
            LEFT JOIN trade_offers o ON o.listing_id=u.id AND o.offerer_id=t.owner AND o.status='pending'
            WHERE o.id IS NULL");
        @$db->query("INSERT IGNORE INTO trade_offer_items (offer_id,pokemon_id,original_owner_id)
            SELECT o.id,t.id,t.owner
            FROM utraded t
            JOIN upfortrade u ON u.pid=t.oid
            JOIN trade_offers o ON o.listing_id=u.id AND o.offerer_id=t.owner AND o.created_at=t.time AND o.status='pending'");
        @$db->query("UPDATE pokemon p JOIN utraded t ON t.id=p.id LEFT JOIN upfortrade u ON u.pid=t.oid
            SET p.owner=t.owner, p.rowner=t.rowner
            WHERE u.id IS NULL AND CAST(p.owner AS UNSIGNED)=0");
        @$db->query("DELETE t FROM utraded t LEFT JOIN upfortrade u ON u.pid=t.oid WHERE u.id IS NULL");
        @$db->query("DELETE t FROM utraded t JOIN trade_offer_items i ON i.pokemon_id=t.id");
        @$db->query("UPDATE upfortrade u SET offers=(SELECT COUNT(*) FROM trade_offers o WHERE o.listing_id=u.id AND o.status='pending')");
    }

    // Clan/community revisions use both the historical and the later field names.
    foreach ([
        'approved' => 'TINYINT NOT NULL DEFAULT 1',
        'motto' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'points' => 'BIGINT NOT NULL DEFAULT 0',
        'exp' => 'BIGINT NOT NULL DEFAULT 0',
        'wins' => 'INT NOT NULL DEFAULT 0',
        'losses' => 'INT NOT NULL DEFAULT 0',
        'members' => 'INT NOT NULL DEFAULT 1',
    ] as $column => $definition) {
        pv_schema_add_column($db, 'clans', $column, $definition, $changes);
    }
    if (pv_schema_table_exists($db, 'clans')) {
        if (pv_schema_column_exists($db, 'clans', 'moto') && pv_schema_column_exists($db, 'clans', 'motto')) {
            @$db->query("UPDATE `clans` SET `motto`=`moto` WHERE (`motto`='' OR `motto` IS NULL) AND `moto` IS NOT NULL");
        }
        if (pv_schema_column_exists($db, 'clans', 'point') && pv_schema_column_exists($db, 'clans', 'points')) {
            @$db->query("UPDATE `clans` SET `points`=`point` WHERE `points`=0 AND `point` IS NOT NULL");
        }
    }

    pv_schema_add_column($db, 'blocked', 'bname', "VARCHAR(45) NOT NULL DEFAULT ''", $changes);
    pv_schema_add_column($db, 'clan_requests', 'clan_id', 'INT NOT NULL DEFAULT 0', $changes);
    pv_schema_add_column($db, 'clan_requests', 'username', "VARCHAR(45) NOT NULL DEFAULT ''", $changes);
    pv_schema_add_column($db, 'comments', 'comment', 'TEXT NULL', $changes);
    pv_schema_add_column($db, 'live_battle_members', 'time', 'BIGINT NOT NULL DEFAULT 0', $changes);

    // Multi-world exploration namespace. Existing Vortex presence rows remain
    // `vortex`; future Kanto areas use stable string area keys in mapusers.map.
    pv_schema_add_column($db, 'mapusers', 'world_key', "VARCHAR(24) NOT NULL DEFAULT 'vortex'", $changes);
    pv_schema_ensure_innodb($db, 'mapusers', $changes);
    pv_schema_ensure_table($db, 'world_map_blocks', "CREATE TABLE `world_map_blocks` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `world_key` VARCHAR(24) NOT NULL,
        `area_key` VARCHAR(64) NOT NULL,
        `xblock` INT NOT NULL,
        `yblock` INT NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_world_map_block` (`world_key`,`area_key`,`xblock`,`yblock`),
        KEY `idx_world_map_area` (`world_key`,`area_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_table($db, 'world_map_positions', "CREATE TABLE `world_map_positions` (
        `user_id` INT NOT NULL,
        `world_key` VARCHAR(24) NOT NULL,
        `area_key` VARCHAR(64) NOT NULL,
        `x` INT NOT NULL,
        `y` INT NOT NULL,
        `updated_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`,`world_key`,`area_key`),
        KEY `idx_world_map_positions_area` (`world_key`,`area_key`,`updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    // Normalized per-trainer/per-event completion ledger. The recovered
    // done_event.id column is an auto-increment row id and cannot safely serve
    // as trainer identity across multiple event rotations.
    pv_schema_ensure_table($db, 'event_completions', "CREATE TABLE `event_completions` (
        `user_id` INT NOT NULL,
        `event_key` VARCHAR(40) NOT NULL,
        `promo_code_id` INT NOT NULL DEFAULT 0,
        `completed_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`,`event_key`),
        KEY `idx_event_completion_event` (`event_key`,`completed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    // Live PvP battle runtime columns were missing from several recovered dumps.
    pv_schema_add_column($db, 'live_battle', 'created_at', 'BIGINT NOT NULL DEFAULT 0', $changes);
    foreach ([
        'choose_1' => 'TINYINT NOT NULL DEFAULT 0',
        'choose_2' => 'TINYINT NOT NULL DEFAULT 0',
        'initialized_1' => 'TINYINT NOT NULL DEFAULT 0',
        'initialized_2' => 'TINYINT NOT NULL DEFAULT 0',
        'pokemon_choice_1' => 'INT NOT NULL DEFAULT 0',
        'pokemon_choice_2' => 'INT NOT NULL DEFAULT 0',
        'pokemon_attack_1' => "VARCHAR(90) NOT NULL DEFAULT ''",
        'pokemon_attack_1_2' => "VARCHAR(90) NOT NULL DEFAULT ''",
        'pokemon_attack_2' => "VARCHAR(90) NOT NULL DEFAULT ''",
        'pokemon_attack_2_2' => "VARCHAR(90) NOT NULL DEFAULT ''",
    ] as $column => $definition) {
        pv_schema_add_column($db, 'live_battle', $column, $definition, $changes);
    }

    // v21 makes Live PvP result settlement durable and idempotent. Each side of
    // one immutable live_battle row can settle exactly once, independently of
    // browser retries or the opponent's completion timing.
    foreach ([
        'settled_1' => 'TINYINT NOT NULL DEFAULT 0',
        'settled_2' => 'TINYINT NOT NULL DEFAULT 0',
        'outcome_1' => "VARCHAR(12) NOT NULL DEFAULT ''",
        'outcome_2' => "VARCHAR(12) NOT NULL DEFAULT ''",
        'reward_exp_1' => 'INT NOT NULL DEFAULT 0',
        'reward_exp_2' => 'INT NOT NULL DEFAULT 0',
        'reward_money_1' => 'INT NOT NULL DEFAULT 0',
        'reward_money_2' => 'INT NOT NULL DEFAULT 0',
        'settled_at_1' => 'BIGINT NOT NULL DEFAULT 0',
        'settled_at_2' => 'BIGINT NOT NULL DEFAULT 0',
    ] as $column => $definition) {
        pv_schema_add_column($db, 'live_battle', $column, $definition, $changes);
    }

    // The old item shop/evolution scripts reference the complete item catalogue.
    $itemColumns = [
        'Abomasite','Absolite','Aerodactylite','Aggronite','Alakazite','Altarianite','Ampharosite',
        'Armor_Fossil','Audinite','Banettite','Beedrillite','Blastoisinite','Blazikenite','Blue_Orb',
        'Cameruptite','CharizarditeX','CharizarditeY','Claw_Fossil','Cover_Fossil','Dawn_Stone',
        'Deepseascale','Deepseatooth','Diancite','DNA_Splicers','Dome_Fossil','Dragon_Scale',
        'Dubious_Disc','Dusk_Stone','Electirizer','Fire_Stone','Galladite','Garchompite','Gardevoirite',
        'Gengarite','Glalitite','Gyaradosite','Helix_Fossil','Heracronite','Houndoominite','Ice_Rock',
        'Jaw_Fossil','Kangaskhanite','Kings_Rock','Latiasite','Latiosite','Leaf_Stone','Lopunnite',
        'Lucarionite','Magmarizer','Manectite','Mawilite','Medichamite','Metagrossite','Metal_Coat',
        'MewtwoniteX','MewtwoniteY','Moon_Stone','Moss_Rock','Old_Amber','Oval_Stone','Parlyz_Heal',
        'Pidgeotite','Pinsirite','Plume_Fossil','Prism_Scale','Protector','Razor_Claw','Razor_Fang',
        'Reaper_Cloth','Red_Orb','Root_Fossil','Sablenite','Sachet','Sail_Fossil','Salamencite',
        'Sceptilite','Scizorite','Sharpedonite','Shiny_Stone','Skull_Fossil','Slowbronite','Steelixite',
        'Sun_Stone','Swampertite','Thunder_Stone','Tyranitarite','Up_Grade','Venusaurite','Water_Stone',
        'Whipped_Dream'
    ];
    foreach ($itemColumns as $column) {
        pv_schema_add_column($db, 'items', $column, 'INT NOT NULL DEFAULT 0', $changes);
    }

    // Supporting tables absent from some recovered dumps.
    pv_schema_ensure_table($db, 'clan_members', "CREATE TABLE `clan_members` (
        `id` INT NOT NULL, `username` VARCHAR(45) NOT NULL DEFAULT '', `clan` VARCHAR(45) NOT NULL DEFAULT '',
        `clan_name` VARCHAR(45) NOT NULL DEFAULT '', `clan_id` INT NOT NULL DEFAULT 0, `owner` TINYINT NOT NULL DEFAULT 0,
        `exp` BIGINT NOT NULL DEFAULT 0, PRIMARY KEY (`id`), KEY `idx_clan` (`clan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    pv_schema_ensure_table($db, 'friends', "CREATE TABLE `friends` (
        `id` INT NOT NULL AUTO_INCREMENT, `uid` INT NOT NULL DEFAULT 0, `username` VARCHAR(45) NOT NULL DEFAULT '',
        `fid` INT NOT NULL DEFAULT 0, `fname` VARCHAR(45) NOT NULL DEFAULT '', `bid` INT NOT NULL DEFAULT 0,
        `bname` VARCHAR(45) NOT NULL DEFAULT '', PRIMARY KEY (`id`), KEY `idx_uid` (`uid`), KEY `idx_fid` (`fid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    pv_schema_ensure_table($db, 'password_resets', "CREATE TABLE `password_resets` (
        `user_id` INT NOT NULL, `token_hash` CHAR(64) NOT NULL, `expires_at` BIGINT NOT NULL DEFAULT 0,
        `created_at` BIGINT NOT NULL DEFAULT 0, PRIMARY KEY (`user_id`), UNIQUE KEY `uq_password_resets_token` (`token_hash`),
        KEY `idx_password_resets_expiry` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    pv_schema_ensure_table($db, 'pv_schema_meta', "CREATE TABLE `pv_schema_meta` (
        `id` TINYINT UNSIGNED NOT NULL, `version` INT UNSIGNED NOT NULL DEFAULT 0,
        `updated_at` DATETIME NOT NULL, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    // v20.1 normalizes the recovered map-members toggle to positive semantics:
    // 1 means show other trainers. Previous revisions mixed 0=show legacy logic
    // with a modern checked checkbox that wrote 1, producing browser/session drift.
    $previousSchemaVersion = 0;
    $metaResult = $db->query('SELECT `version` FROM `pv_schema_meta` WHERE `id`=1 LIMIT 1');
    if ($metaResult && ($metaRow = $metaResult->fetch_assoc())) $previousSchemaVersion = max(0, (int)$metaRow['version']);
    if ($previousSchemaVersion < 21) {
        if (pv_schema_table_exists($db, 'members_options') && pv_schema_column_exists($db, 'members_options', 'memonmap')) {
            if (!$db->query('UPDATE `members_options` SET `memonmap`=1')) throw new RuntimeException('Could not normalize world-map trainer visibility preferences.');
            $changes[] = 'Enabled world-map trainer presence by default';
        }
        if (pv_schema_table_exists($db, 'members') && pv_schema_column_exists($db, 'members', 'memonmap')) {
            @$db->query('UPDATE `members` SET `memonmap`=1');
        }
    }
    if (pv_schema_table_exists($db, 'members_options') && pv_schema_column_exists($db, 'members_options', 'memonmap')) {
        @$db->query('ALTER TABLE `members_options` MODIFY `memonmap` TINYINT NOT NULL DEFAULT 1');
    }
    if (pv_schema_table_exists($db, 'members') && pv_schema_column_exists($db, 'members', 'memonmap')) {
        @$db->query('ALTER TABLE `members` MODIFY `memonmap` TINYINT NOT NULL DEFAULT 1');
    }


    pv_schema_ensure_table($db, 'live_battle_challenges', "CREATE TABLE `live_battle_challenges` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `challenger_id` INT NOT NULL,
        `target_id` INT NOT NULL,
        `battle_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `status` ENUM('pending','accepted','declined','cancelled','expired','completed') NOT NULL DEFAULT 'pending',
        `created_at` BIGINT NOT NULL DEFAULT 0,
        `responded_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_live_challenge_target` (`target_id`,`status`,`created_at`),
        KEY `idx_live_challenge_challenger` (`challenger_id`,`status`,`created_at`),
        KEY `idx_live_challenge_battle` (`battle_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_add_column($db, 'live_battle_challenges', 'battle_id', 'BIGINT UNSIGNED NOT NULL DEFAULT 0', $changes);
    if (pv_schema_table_exists($db, 'live_battle_challenges') && pv_schema_column_exists($db, 'live_battle_challenges', 'status')) {
        $statusType = '';
        $stmt = $db->prepare("SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='live_battle_challenges' AND column_name='status' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: [];
            $statusType = strtolower((string)($row['COLUMN_TYPE'] ?? ''));
            $stmt->close();
        }
        if (!str_contains($statusType, "'completed'")) {
            if (!$db->query("ALTER TABLE `live_battle_challenges` MODIFY `status` ENUM('pending','accepted','declined','cancelled','expired','completed') NOT NULL DEFAULT 'pending'")) {
                throw new RuntimeException('Could not upgrade Live Battle challenge completion states.');
            }
            $changes[] = 'Added completed Live Battle challenge state';
        }
    }
    pv_schema_add_index($db, 'live_battle_challenges', 'idx_live_challenge_battle', '`battle_id`', $changes);
    pv_schema_add_index($db, 'live_battle', 'idx_live_pair', '`uid_1`,`uid_2`', $changes);
    pv_schema_add_index($db, 'live_battle', 'idx_live_created', '`created_at`', $changes);
    pv_schema_ensure_innodb($db, 'live_battle', $changes);
    pv_schema_ensure_innodb($db, 'live_battle_challenges', $changes);

    // v23.7 introduces 1,000 persistent autonomous trainers. Bots are ordinary
    // member/Pokémon owners for combat compatibility, while this registry is the
    // authoritative control plane for AI identity, map presence and simulation.
    pv_schema_ensure_table($db, 'bot_trainers', "CREATE TABLE `bot_trainers` (
        `user_id` INT NOT NULL,
        `bot_index` INT NOT NULL,
        `enabled` TINYINT NOT NULL DEFAULT 1,
        `trainer_sprite` TINYINT NOT NULL DEFAULT 1,
        `world_key` VARCHAR(24) NOT NULL DEFAULT 'vortex',
        `map_key` VARCHAR(64) NOT NULL DEFAULT '1',
        `x` INT NOT NULL DEFAULT 1,
        `y` INT NOT NULL DEFAULT 1,
        `next_action_at` BIGINT NOT NULL DEFAULT 0,
        `last_action_at` BIGINT NOT NULL DEFAULT 0,
        `last_action` VARCHAR(32) NOT NULL DEFAULT '',
        `last_wild_name` VARCHAR(80) NOT NULL DEFAULT '',
        `last_wild_level` INT NOT NULL DEFAULT 0,
        `wild_battles` INT UNSIGNED NOT NULL DEFAULT 0,
        `wild_wins` INT UNSIGNED NOT NULL DEFAULT 0,
        `captures` INT UNSIGNED NOT NULL DEFAULT 0,
        `player_battles` INT UNSIGNED NOT NULL DEFAULT 0,
        `player_wins` INT UNSIGNED NOT NULL DEFAULT 0,
        `player_losses` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` BIGINT NOT NULL DEFAULT 0,
        `updated_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`),
        UNIQUE KEY `uq_bot_trainers_index` (`bot_index`),
        KEY `idx_bot_trainers_due` (`enabled`,`next_action_at`),
        KEY `idx_bot_trainers_location` (`enabled`,`world_key`,`map_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_innodb($db, 'bot_trainers', $changes);

    // v25 Ranked Rival Network. This progression is deliberately isolated from
    // the recovered members.points formula so the historic collection ranking
    // remains compatible while PvP/AI competition can use a stable Elo ladder.
    pv_schema_ensure_table($db, 'trainer_rank_state', "CREATE TABLE `trainer_rank_state` (
        `user_id` INT NOT NULL,
        `rating` INT NOT NULL DEFAULT 1000,
        `peak_rating` INT NOT NULL DEFAULT 1000,
        `ranked_wins` INT UNSIGNED NOT NULL DEFAULT 0,
        `ranked_losses` INT UNSIGNED NOT NULL DEFAULT 0,
        `current_streak` INT UNSIGNED NOT NULL DEFAULT 0,
        `best_streak` INT UNSIGNED NOT NULL DEFAULT 0,
        `shield_until` BIGINT NOT NULL DEFAULT 0,
        `shield_source_user_id` INT NOT NULL DEFAULT 0,
        `last_ranked_at` BIGINT NOT NULL DEFAULT 0,
        `last_attack_at` BIGINT NOT NULL DEFAULT 0,
        `last_defense_at` BIGINT NOT NULL DEFAULT 0,
        `updated_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`),
        KEY `idx_rank_rating` (`rating`,`user_id`),
        KEY `idx_rank_shield` (`shield_until`),
        KEY `idx_rank_activity` (`last_ranked_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_innodb($db, 'trainer_rank_state', $changes);

    pv_schema_ensure_table($db, 'rival_battles', "CREATE TABLE `rival_battles` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `attacker_id` INT NOT NULL,
        `defender_id` INT NOT NULL,
        `winner_id` INT NOT NULL,
        `loser_id` INT NOT NULL,
        `source` VARCHAR(24) NOT NULL DEFAULT 'challenge',
        `attacker_rating_before` INT NOT NULL DEFAULT 1000,
        `defender_rating_before` INT NOT NULL DEFAULT 1000,
        `rating_delta` INT NOT NULL DEFAULT 0,
        `retaliation_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `summary` VARCHAR(255) NOT NULL DEFAULT '',
        `created_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_rival_battles_attacker` (`attacker_id`,`created_at`),
        KEY `idx_rival_battles_defender` (`defender_id`,`created_at`),
        KEY `idx_rival_battles_recent` (`created_at`),
        KEY `idx_rival_battles_winner` (`winner_id`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_innodb($db, 'rival_battles', $changes);

    pv_schema_ensure_table($db, 'rival_retaliations', "CREATE TABLE `rival_retaliations` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `battle_id` BIGINT UNSIGNED NOT NULL,
        `defender_id` INT NOT NULL,
        `attacker_id` INT NOT NULL,
        `status` VARCHAR(16) NOT NULL DEFAULT 'open',
        `created_at` BIGINT NOT NULL DEFAULT 0,
        `expires_at` BIGINT NOT NULL DEFAULT 0,
        `used_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_rival_retaliation_battle` (`battle_id`,`defender_id`),
        KEY `idx_rival_retaliation_owner` (`defender_id`,`status`,`expires_at`),
        KEY `idx_rival_retaliation_target` (`attacker_id`,`status`,`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_innodb($db, 'rival_retaliations', $changes);

    pv_schema_ensure_table($db, 'ai_activity', "CREATE TABLE `ai_activity` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `bot_user_id` INT NOT NULL,
        `category` VARCHAR(32) NOT NULL DEFAULT '',
        `headline` VARCHAR(160) NOT NULL DEFAULT '',
        `detail` VARCHAR(255) NOT NULL DEFAULT '',
        `related_user_id` INT NOT NULL DEFAULT 0,
        `rating_delta` INT NOT NULL DEFAULT 0,
        `created_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_ai_activity_recent` (`created_at`),
        KEY `idx_ai_activity_bot` (`bot_user_id`,`created_at`),
        KEY `idx_ai_activity_category` (`category`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_innodb($db, 'ai_activity', $changes);

    // Production collection/economy/network tables. These normalized tables
    // replace small or revision-specific historical structures while leaving
    // those old tables intact for import compatibility.
    pv_schema_ensure_table($db, 'trainer_messages', "CREATE TABLE `trainer_messages` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `sender_id` INT NOT NULL,
        `receiver_id` INT NOT NULL,
        `subject` VARCHAR(120) NOT NULL DEFAULT '',
        `body` TEXT NOT NULL,
        `created_at` BIGINT NOT NULL DEFAULT 0,
        `read_at` BIGINT NOT NULL DEFAULT 0,
        `sender_deleted` TINYINT NOT NULL DEFAULT 0,
        `receiver_deleted` TINYINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_trainer_messages_receiver` (`receiver_id`,`receiver_deleted`,`created_at`),
        KEY `idx_trainer_messages_sender` (`sender_id`,`sender_deleted`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    pv_schema_ensure_table($db, 'trainer_friends', "CREATE TABLE `trainer_friends` (
        `user_id` INT NOT NULL,
        `friend_id` INT NOT NULL,
        `created_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`,`friend_id`),
        KEY `idx_trainer_friends_friend` (`friend_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    pv_schema_ensure_table($db, 'trainer_blocks', "CREATE TABLE `trainer_blocks` (
        `user_id` INT NOT NULL,
        `blocked_user_id` INT NOT NULL,
        `created_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`,`blocked_user_id`),
        KEY `idx_trainer_blocks_target` (`blocked_user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    pv_schema_ensure_table($db, 'friend_requests', "CREATE TABLE `friend_requests` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `sender_id` INT NOT NULL,
        `receiver_id` INT NOT NULL,
        `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
        `created_at` BIGINT NOT NULL DEFAULT 0,
        `resolved_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_friend_request_pair` (`sender_id`,`receiver_id`,`status`),
        KEY `idx_friend_requests_receiver` (`receiver_id`,`status`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    pv_schema_ensure_table($db, 'clan_applications', "CREATE TABLE `clan_applications` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `clan_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
        `created_at` BIGINT NOT NULL DEFAULT 0,
        `resolved_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_clan_application_pending` (`clan_id`,`user_id`,`status`),
        KEY `idx_clan_applications_clan` (`clan_id`,`status`,`created_at`),
        KEY `idx_clan_applications_user` (`user_id`,`status`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    pv_schema_ensure_table($db, 'shop_transactions', "CREATE TABLE `shop_transactions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `item_key` VARCHAR(80) NOT NULL,
        `item_label` VARCHAR(100) NOT NULL,
        `quantity` INT NOT NULL,
        `unit_price` INT NOT NULL,
        `total_price` INT NOT NULL,
        `created_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_shop_transactions_user` (`user_id`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    pv_schema_ensure_table($db, 'move_lab_transactions', "CREATE TABLE `move_lab_transactions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `pokemon_id` INT NOT NULL,
        `slot_no` TINYINT NOT NULL,
        `old_move` VARCHAR(80) NOT NULL DEFAULT '',
        `new_move` VARCHAR(80) NOT NULL,
        `price` INT NOT NULL DEFAULT 0,
        `created_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_move_lab_user` (`user_id`,`created_at`),
        KEY `idx_move_lab_pokemon` (`pokemon_id`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    foreach (['trainer_messages','trainer_friends','trainer_blocks','friend_requests','clan_applications','shop_transactions','move_lab_transactions'] as $table) {
        pv_schema_ensure_innodb($db, $table, $changes);
    }


    // Wild battle completion ledger. A server-issued battle id may be rewarded
    // or captured only once even if the browser retries a request after a
    // network interruption. This is deliberately separate from the session
    // battle state so DB commits remain crash-safe and idempotent.
    pv_schema_ensure_table($db, 'wild_battle_results', "CREATE TABLE `wild_battle_results` (
        `battle_id` CHAR(32) NOT NULL,
        `user_id` INT NOT NULL,
        `outcome` VARCHAR(16) NOT NULL,
        `wild_pid` INT NOT NULL DEFAULT 0,
        `wild_name` VARCHAR(80) NOT NULL DEFAULT '',
        `wild_level` INT NOT NULL DEFAULT 1,
        `reward_exp` INT NOT NULL DEFAULT 0,
        `reward_money` INT NOT NULL DEFAULT 0,
        `captured_pokemon_id` INT NOT NULL DEFAULT 0,
        `created_at` BIGINT NOT NULL DEFAULT 0,
        PRIMARY KEY (`battle_id`),
        KEY `idx_wild_battle_user` (`user_id`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);

    // Complete the small set of species referenced by the recovered map pools
    // that were absent from the supplied pguide revision.  These rows make every
    // recovered world-map encounter battle-compatible without reusing unrelated
    // historic numeric IDs.
    if (pv_schema_table_exists($db, 'pguide')) {
        $encounterSpecies = [
            'Darkrown' => ['Dark','Ghost','Dark Pulse','Shadow Ball','Psychic','Night Shade'],
            'Ditto' => ['Normal','', 'Transform','Tackle','Copycat','Struggle'],
            'Farfetchd' => ['Normal','Flying','Peck','Fury Attack','Leer','Aerial Ace'],
            'Flabebe' => ['Fairy','', 'Tackle','Vine Whip','Fairy Wind','Lucky Chant'],
            'Mime Jr.' => ['Psychic','Fairy','Confusion','Barrier','Copycat','Psybeam'],
            'Nidoran (F)' => ['Poison','', 'Growl','Scratch','Tail Whip','Double Kick'],
            'Nidoran (M)' => ['Poison','', 'Leer','Peck','Focus Energy','Double Kick'],
        ];
        $variants = ['', 'Shiny ', 'Dark ', 'Metallic ', 'Mystic ', 'Shadow '];
        foreach ($encounterSpecies as $species => $meta) {
            [$type1,$type2,$a1,$a2,$a3,$a4] = $meta;
            foreach ($variants as $variant) {
                $name = $variant . $species;
                $stmt = $db->prepare('SELECT id FROM pguide WHERE name=? LIMIT 1');
                if (!$stmt) continue;
                $stmt->bind_param('s', $name); $stmt->execute(); $exists = (bool)$stmt->get_result()->fetch_row(); $stmt->close();
                if ($exists) continue;
                $starter = 1; $amount = 0;
                $stmt = $db->prepare('INSERT INTO pguide (name,type1,type2,starter,amount,a1,a2,a3,a4) VALUES (?,?,?,?,?,?,?,?,?)');
                if ($stmt) {
                    $stmt->bind_param('sssiissss', $name,$type1,$type2,$starter,$amount,$a1,$a2,$a3,$a4);
                    if ($stmt->execute()) $changes[] = 'Added recovered map species: ' . $name;
                    $stmt->close();
                }
            }
        }
    }

    if (pv_schema_table_exists($db, 'abilities')) {
        $abilitySeeds = [
            'Darkrown'=>['Pressure','Cursed Body',''],
            'Ditto'=>['Limber','Imposter',''],
            'Farfetchd'=>['Keen Eye','Inner Focus','Defiant'],
            'Flabebe'=>['Flower Veil','Symbiosis',''],
            'Mime Jr.'=>['Soundproof','Filter','Technician'],
            'Nidoran (F)'=>['Poison Point','Rivalry','Hustle'],
            'Nidoran (M)'=>['Poison Point','Rivalry','Hustle'],
        ];
        $variants = ['', 'Shiny ', 'Dark ', 'Metallic ', 'Mystic ', 'Shadow '];
        foreach ($abilitySeeds as $species => $abilities) {
            foreach ($variants as $variant) {
                $name = $variant . $species;
                $stmt = $db->prepare('SELECT id FROM abilities WHERE name=? LIMIT 1');
                if (!$stmt) continue;
                $stmt->bind_param('s',$name); $stmt->execute(); $exists=(bool)$stmt->get_result()->fetch_row(); $stmt->close();
                if ($exists) continue;
                [$ability1,$ability2,$ability3]=$abilities;
                $stmt=$db->prepare('INSERT INTO abilities (name,ability1,ability2,ability3) VALUES (?,?,?,?)');
                if($stmt){$stmt->bind_param('ssss',$name,$ability1,$ability2,$ability3);$stmt->execute();$stmt->close();}
            }
        }
    }


    // v22.4 reconstructs the complete League + Battle Facility NPC progression ladder.
    // The recovered badge table stopped at g86 even though the UI/engine exposes g1..g95.
    foreach (range(1, 95) as $battleBadgeId) {
        pv_schema_add_column($db, 'badges', 'g' . $battleBadgeId, 'TINYINT NOT NULL DEFAULT 0', $changes);
    }
    pv_schema_ensure_innodb($db, 'badges', $changes);
    pv_schema_ensure_innodb($db, 'gym', $changes);
    pv_schema_ensure_table($db, 'gympokemon', "CREATE TABLE `gympokemon` (
        `id` INT NOT NULL,
        `owner` INT NOT NULL DEFAULT 0,
        `name` VARCHAR(80) NOT NULL DEFAULT '',
        `a1` VARCHAR(80) NULL,
        `a2` VARCHAR(80) NULL,
        `a3` VARCHAR(80) NULL,
        `a4` VARCHAR(80) NULL,
        `lvl` INT NOT NULL DEFAULT 1,
        `exp` BIGINT NOT NULL DEFAULT 0,
        `t1` VARCHAR(30) NULL,
        `t2` VARCHAR(30) NULL,
        `rowner` VARCHAR(80) NULL,
        PRIMARY KEY (`id`),
        KEY `idx_gympokemon_owner` (`owner`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_innodb($db, 'gympokemon', $changes);
    pv_battle_catalog_seed($db, $changes);

    // v22.5 reconstructs the complete recovered Special Event trainer network.
    // Event progress is intentionally independent from badges.g1..g95 and from
    // modern promo-code event_completions. The historical `events` row stores
    // the original non-contiguous gN identities. Reserved legacy flags remain untouched.
    pv_schema_ensure_table($db, 'event', "CREATE TABLE `event` (
        `id` INT NOT NULL,
        `trainer` VARCHAR(80) NOT NULL DEFAULT '',
        `s1` INT NULL, `s2` INT NULL, `s3` INT NULL, `s4` INT NULL, `s5` INT NULL, `s6` INT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    foreach (['trainer' => "VARCHAR(80) NOT NULL DEFAULT ''", 's1'=>'INT NULL','s2'=>'INT NULL','s3'=>'INT NULL','s4'=>'INT NULL','s5'=>'INT NULL','s6'=>'INT NULL'] as $eventColumn=>$eventDefinition) {
        pv_schema_add_column($db, 'event', $eventColumn, $eventDefinition, $changes);
    }
    pv_schema_ensure_innodb($db, 'event', $changes);

    pv_schema_ensure_table($db, 'events', "CREATE TABLE `events` (`id` INT NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    foreach (range(1, 38) as $eventFlagId) {
        pv_schema_add_column($db, 'events', 'g' . $eventFlagId, 'TINYINT NOT NULL DEFAULT 0', $changes);
    }
    pv_schema_ensure_innodb($db, 'events', $changes);

    pv_schema_ensure_table($db, 'eventpokemon', "CREATE TABLE `eventpokemon` (
        `id` INT NOT NULL,
        `owner` INT NOT NULL DEFAULT 0,
        `name` VARCHAR(80) NOT NULL DEFAULT '',
        `a1` VARCHAR(80) NULL, `a2` VARCHAR(80) NULL, `a3` VARCHAR(80) NULL, `a4` VARCHAR(80) NULL,
        `lvl` INT NOT NULL DEFAULT 1,
        `exp` BIGINT NOT NULL DEFAULT 0,
        `t1` VARCHAR(30) NULL, `t2` VARCHAR(30) NULL,
        `rowner` VARCHAR(80) NULL,
        PRIMARY KEY (`id`),
        KEY `idx_eventpokemon_owner` (`owner`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_innodb($db, 'eventpokemon', $changes);
    pv_event_battle_seed($db, $changes);

    // v22.6 reconstructs the complete Sidequest campaign exposed by this source generation:
    // 690 battles across Kanto, Johto, Sevii/Legendary Isles, TCG Island, Orange Islands
    // and Hoenn, with the six original reward milestones retained as non-battle positions.
    pv_schema_ensure_table($db, 'sidequests', "CREATE TABLE `sidequests` (
        `id` INT NOT NULL,
        `name` VARCHAR(80) NOT NULL DEFAULT '',
        `place` VARCHAR(100) NOT NULL DEFAULT '',
        `s1` INT NULL, `s2` INT NULL, `s3` INT NULL, `s4` INT NULL, `s5` INT NULL, `s6` INT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_add_column($db, 'sidequests', 'place', "VARCHAR(100) NOT NULL DEFAULT ''", $changes);
    pv_schema_ensure_innodb($db, 'sidequests', $changes);

    pv_schema_ensure_table($db, 'sidepokemon', "CREATE TABLE `sidepokemon` (
        `id` INT NOT NULL,
        `owner` INT NOT NULL DEFAULT 0,
        `name` VARCHAR(80) NOT NULL DEFAULT '',
        `a1` VARCHAR(80) NULL, `a2` VARCHAR(80) NULL, `a3` VARCHAR(80) NULL, `a4` VARCHAR(80) NULL,
        `lvl` INT NOT NULL DEFAULT 1,
        `exp` BIGINT NOT NULL DEFAULT 0,
        `t1` VARCHAR(30) NULL, `t2` VARCHAR(30) NULL,
        `rowner` VARCHAR(80) NULL,
        PRIMARY KEY (`id`),
        KEY `idx_sidepokemon_owner` (`owner`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $changes);
    pv_schema_ensure_innodb($db, 'sidepokemon', $changes);
    pv_schema_add_index($db, 'sidepokemon', 'idx_sidepokemon_owner', '`owner`', $changes);
    pv_sidequest_seed($db, $changes);
    if (pv_schema_table_exists($db, 'members') && pv_schema_column_exists($db, 'members', 'sidequest')) {
        if (!$db->query('UPDATE members SET sidequest=1 WHERE sidequest IS NULL OR sidequest<1')) {
            throw new RuntimeException('Could not initialize never-started Sidequest progression: ' . $db->error);
        }
        $changes[] = 'Initialized never-started Sidequest accounts at source-era battle #1';
    }

    // Rebuild the legacy legendary-map unlock from the complete g1..g95 contract.
    // This repairs the historical g45 omission and prevents partial legacy data from
    // leaving the account-level unlock flag ahead of authoritative badge progress.
    if (pv_schema_table_exists($db, 'members') && pv_schema_table_exists($db, 'badges')) {
        $battleCompletionTerms = [];
        foreach (pv_battle_catalog_required_badge_ids() as $battleBadgeId) {
            $battleCompletionTerms[] = 'COALESCE(b.`g' . $battleBadgeId . '`,0)=1';
        }
        $battleCompletionSql = implode(' AND ', $battleCompletionTerms);
        if (!$db->query('UPDATE members m LEFT JOIN badges b ON b.id=m.id SET m.badges=IF(b.id IS NOT NULL AND ' . $battleCompletionSql . ',1,0)')) {
            throw new RuntimeException('Could not reconcile complete Battle Arena progression: ' . $db->error);
        }
        $changes[] = 'Reconciled legendary-map unlock against all 95 Battle Arena progression flags';
    }

    // Useful indexes for the most frequently traversed gameplay paths.
    pv_schema_add_index($db, 'pokemon', 'idx_pokemon_owner', '`owner`', $changes);
    pv_schema_add_index($db, 'pokemon', 'idx_pokemon_name', '`name`', $changes);
    pv_schema_add_index($db, 'upfortrade', 'idx_upfortrade_owner', '`owner`', $changes);
    pv_schema_add_index($db, 'upfortrade', 'idx_upfortrade_pid', '`pid`', $changes);
    if (pv_schema_table_exists($db, 'upfortrade') && pv_schema_column_exists($db, 'upfortrade', 'pid')) {
        // Historical dumps occasionally contain duplicate listing rows. Keep the oldest row
        // so one Pokémon can never appear as multiple simultaneous listings.
        @$db->query('DELETE u1 FROM `upfortrade` u1 INNER JOIN `upfortrade` u2 ON u1.`pid`=u2.`pid` AND u1.`id`>u2.`id` WHERE u1.`pid`>0');
        pv_schema_add_index($db, 'upfortrade', 'uq_upfortrade_pid', '`pid`', $changes, true);
    }
    pv_schema_add_index($db, 'mapusers', 'idx_mapusers_map', '`map`', $changes);
    pv_schema_add_index($db, 'mapusers', 'idx_mapusers_world_map', '`world_key`,`map`', $changes);
    pv_schema_add_index($db, 'clans', 'idx_clans_owner', '`owner`', $changes);
    pv_schema_add_index($db, 'messages', 'idx_messages_receiver', '`receiverid`', $changes);
    pv_schema_add_index($db, 'messages', 'idx_messages_sender', '`senderid`', $changes);
    pv_schema_add_index($db, 'trade_offers', 'idx_trade_offer_listing_pokemon', '`listing_pokemon_id`,`status`', $changes);
    if (pv_schema_index_exists($db, 'trade_offer_items', 'uq_trade_offer_pokemon')) {
        if (!$db->query('ALTER TABLE `trade_offer_items` DROP INDEX `uq_trade_offer_pokemon`')) {
            throw new RuntimeException('Could not repair the trade offer Pokémon index: ' . $db->error);
        }
        $changes[] = 'Repaired trade_offer_items Pokémon index';
    }
    pv_schema_add_index($db, 'trade_offer_items', 'idx_trade_offer_items_pokemon', '`pokemon_id`', $changes);
    pv_schema_add_index($db, 'trade_offer_items', 'idx_trade_offer_items_owner', '`original_owner_id`', $changes);

    // Population repair is idempotent: interrupted local setup runs can resume
    // without duplicating bots or touching human trainer progress.
    $botPopulation = pv_bot_ensure_population($db, PV_BOT_POPULATION_TARGET);

    // Create one ranked state row for every human and autonomous trainer. The
    // deterministic initial spread is only a bootstrap; subsequent movement is
    // entirely driven by ranked battle results.
    if (pv_schema_table_exists($db, 'trainer_rank_state')) {
        $rankSeedSql = "INSERT IGNORE INTO trainer_rank_state
            (user_id,rating,peak_rating,ranked_wins,ranked_losses,current_streak,best_streak,shield_until,shield_source_user_id,last_ranked_at,last_attack_at,last_defense_at,updated_at)
            SELECT m.id,1000,1000,0,0,0,0,0,0,0,0,0,UNIX_TIMESTAMP()
            FROM members m";
        if (!$db->query($rankSeedSql)) throw new RuntimeException('Could not seed Ranked Rival Network trainer states: ' . $db->error);
        if ($db->affected_rows > 0) $changes[] = 'Seeded ' . (int)$db->affected_rows . ' Ranked Rival Network trainer state row(s) at neutral 1,000 RP';
        if (!$db->query('UPDATE trainer_rank_state SET rating=1000,peak_rating=1000,updated_at=UNIX_TIMESTAMP() WHERE ranked_wins=0 AND ranked_losses=0 AND last_ranked_at=0 AND (rating<>1000 OR peak_rating<>1000)')) {
            throw new RuntimeException('Could not normalize untouched Ranked Rival Network seed ratings: ' . $db->error);
        }
        if ($db->affected_rows > 0) $changes[] = 'Normalized ' . (int)$db->affected_rows . ' untouched Ranked Rival seed rating row(s) to 1,000 RP';
    }
    if ((int)($botPopulation['created'] ?? 0) > 0) {
        $changes[] = 'Seeded ' . (int)$botPopulation['created'] . ' autonomous trainer bot account(s)';
    }
    $changes[] = 'Autonomous trainer population verified: ' . (int)$botPopulation['total'] . ' accounts (target ' . PV_BOT_POPULATION_TARGET . '); existing identities and progression preserved';

    // Recalculate the account collection count only when the source tables exist.
    if (pv_schema_table_exists($db, 'members') && pv_schema_table_exists($db, 'pokemon') && pv_schema_column_exists($db, 'members', 'total_poke')) {
        @$db->query('UPDATE `members` m SET `total_poke`=(SELECT COUNT(*) FROM `pokemon` p WHERE p.`owner`=m.`id`)');
    }

    if (pv_schema_table_exists($db, 'pv_schema_meta')) {
        $db->query("INSERT INTO `pv_schema_meta` (`id`,`version`,`updated_at`) VALUES (1,29,NOW()) ON DUPLICATE KEY UPDATE `version`=VALUES(`version`),`updated_at`=VALUES(`updated_at`)");
    }

    return $changes;
}
