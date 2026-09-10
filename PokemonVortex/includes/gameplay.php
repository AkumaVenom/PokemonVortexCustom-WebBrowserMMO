<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin/social_runtime.php';

/**
 * Shared gameplay integrity helpers for reconstructed legacy systems.
 * These helpers deliberately recalculate collection/progression state from
 * authoritative rows instead of trusting stale legacy counters.
 */

function pv_safe_divide(float|int $numerator, float|int $denominator, float $fallback = 0.0): float
{
    $den = (float)$denominator;
    if (!is_finite($den) || abs($den) < PHP_FLOAT_EPSILON) return $fallback;
    $value = (float)$numerator / $den;
    return is_finite($value) ? $value : $fallback;
}

function pv_progress_score(int|float $totalExp, int $ownedCount, int $uniqueCount, int $battleWins): float
{
    $totalExp = max(0.0, (float)$totalExp);
    $ownedCount = max(0, $ownedCount);
    $uniqueCount = max(0, $uniqueCount);
    $battleWins = max(0, $battleWins);
    if ($ownedCount === 0 || $uniqueCount === 0 || $totalExp <= 0.0 || $battleWins <= 1) return 0.0;

    $average = pv_safe_divide($totalExp, $ownedCount, 0.0);
    $score = (sqrt($totalExp) * sqrt(max(0.0, $average)) * sqrt((float)$uniqueCount) * log((float)$battleWins)) / 1000.0;
    return is_finite($score) ? round(max(0.0, $score), 1) : 0.0;
}

function pv_clan_progress_score(int|float $exp, int $members, int $wins): float
{
    $exp = max(0.0, (float)$exp);
    $members = max(0, $members);
    $wins = max(0, $wins);
    if ($exp <= 0.0 || $members === 0 || $wins <= 1) return 0.0;

    $average = pv_safe_divide($exp, $members, 0.0);
    $score = (sqrt((float)$members) * sqrt($exp) * sqrt(max(0.0, $average)) * log((float)$wins)) / 10000.0;
    return is_finite($score) ? round(max(0.0, $score), 1) : 0.0;
}

function pv_recalculate_clan_progress(mysqli $db, string $clanName): void
{
    $clanName = trim($clanName);
    if ($clanName === '') return;

    $stmt = $db->prepare('SELECT COUNT(*) AS member_count, COALESCE(SUM(exp),0) AS total_exp FROM clan_members WHERE clan_name=? OR clan=?');
    if (!$stmt) return;
    $stmt->bind_param('ss', $clanName, $clanName);
    $stmt->execute();
    $aggregate = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $memberCount = max(0, (int)($aggregate['member_count'] ?? 0));
    $totalExp = max(0, (int)($aggregate['total_exp'] ?? 0));

    $stmt = $db->prepare('SELECT wins FROM clans WHERE name=? LIMIT 1');
    if (!$stmt) return;
    $stmt->bind_param('s', $clanName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $wins = max(0, (int)($row['wins'] ?? 0));
    $points = pv_clan_progress_score($totalExp, $memberCount, $wins);

    $stmt = $db->prepare('UPDATE clans SET members=?, exp=?, points=? WHERE name=?');
    if ($stmt) {
        $stmt->bind_param('iids', $memberCount, $totalExp, $points, $clanName);
        $stmt->execute();
        $stmt->close();
    }
}

function pv_recalculate_trainer_progress(mysqli $db, int $uid, bool $refreshSession = false): array
{
    $uid = max(1, $uid);

    $stmt = $db->prepare('SELECT battle, clan_name FROM members WHERE id=? LIMIT 1');
    if (!$stmt) return ['count'=>0,'unique'=>0,'total_exp'=>0,'average_exp'=>0,'points'=>0.0];
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $stmt = $db->prepare('SELECT COUNT(*) AS owned_count, COUNT(DISTINCT pid) AS unique_count, COALESCE(SUM(exp),0) AS total_exp FROM pokemon WHERE CAST(owner AS UNSIGNED)=?');
    if (!$stmt) return ['count'=>0,'unique'=>0,'total_exp'=>0,'average_exp'=>0,'points'=>0.0];
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $ownedCount = max(0, (int)($stats['owned_count'] ?? 0));
    $uniqueCount = max(0, (int)($stats['unique_count'] ?? 0));
    $totalExp = max(0, (int)($stats['total_exp'] ?? 0));
    $averageExp = $ownedCount > 0 ? (int)round($totalExp / $ownedCount) : 0;
    $points = pv_progress_score($totalExp, $ownedCount, $uniqueCount, (int)($member['battle'] ?? 0));

    $stmt = $db->prepare('UPDATE members SET total_poke=?, uniques=?, totalexp=?, averageexp=?, points=? WHERE id=?');
    if ($stmt) {
        $stmt->bind_param('iiiidi', $ownedCount, $uniqueCount, $totalExp, $averageExp, $points, $uid);
        $stmt->execute();
        $stmt->close();
    }

    $clanName = trim((string)($member['clan_name'] ?? ''));
    if ($clanName !== '') {
        $stmt = $db->prepare('UPDATE clan_members SET exp=? WHERE id=?');
        if ($stmt) {
            $stmt->bind_param('ii', $totalExp, $uid);
            $stmt->execute();
            $stmt->close();
        }
        pv_recalculate_clan_progress($db, $clanName);
    }

    if ($refreshSession && (int)($_SESSION['myid'] ?? 0) === $uid) {
        $_SESSION['your_pokemon'] = [];
        $stmt = $db->prepare('SELECT DISTINCT pid FROM pokemon WHERE CAST(owner AS UNSIGNED)=? ORDER BY pid');
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) $_SESSION['your_pokemon'][] = (int)$row['pid'];
            $stmt->close();
        }
    }

    return [
        'count'=>$ownedCount,
        'unique'=>$uniqueCount,
        'total_exp'=>$totalExp,
        'average_exp'=>$averageExp,
        'points'=>$points,
    ];
}

function pv_pokemon_sprite(string $name): string
{
    $name = trim($name);
    if ($name === '') return pv_static('images/Pokeball.PNG');

    $root = dirname(__DIR__) . '/html/static/images/pokemon/';
    if (is_file($root . $name . '.gif')) {
        return pv_static('images/pokemon/' . $name . '.gif');
    }

    // Some recovered catalogue rows represent a species once while the art
    // archive stores only its visual sub-forms. Keep those species usable in
    // collection/battle screens instead of falling back to a generic ball.
    $plain = preg_replace('/^(Shiny|Dark|Mystic|Shadow|Metallic)\s+/i', '', $name) ?: $name;
    $prefix = substr($name, 0, strlen($name) - strlen($plain));
    $formFallbacks = [
        'Unown' => 'Unown (A)',
        'Burmy' => 'Burmy (Plant)',
        'Wormadam' => 'Wormadam (Plant)',
        'Cherrim' => 'Cherrim (Non Sunny)',
        'Shellos' => 'Shellos (East)',
        'Gastrodon' => 'Gastrodon (East)',
        'Basculin' => 'Basculin (Blue Stripe)',
        'Deerling' => 'Deerling (Autumn)',
        'Sawsbuck' => 'Sawsbuck (Autumn)',
        'Meloetta' => 'Meloetta (Aria)',
        'Vivillon' => 'Vivillon (Archipelago)',
        'Flabebe' => 'Flabebe (Red)',
        'Floette' => 'Floette (Blue)',
        'Florges' => 'Florges (Blue)',
        'Meowstic' => 'Meowstic (F)',
        'Aegislash' => 'Aegislash (Blade)',
        'Pumpkaboo' => 'Pumpkaboo (Average)',
        'Gourgeist' => 'Gourgeist (Average)',
        'Xerneas' => 'Xerneas (Active)',
    ];
    if (isset($formFallbacks[$plain])) {
        $candidate = $prefix . $formFallbacks[$plain];
        if (is_file($root . $candidate . '.gif')) return pv_static('images/pokemon/' . $candidate . '.gif');
    }

    return pv_static_file('images/pokemon/' . $name . '.gif', 'images/Pokeball.PNG');
}

function pv_item_sprite(string $name): string
{
    $name = trim(str_replace('_', ' ', $name));
    if ($name === '') return pv_static('images/Pokeball.PNG');
    return pv_static_file('images/items/' . $name . '.png', 'images/Pokeball.PNG');
}

/**
 * Transaction-safe Trade Center runtime.
 *
 * Pokémon placed in a listing or an offer remain in the authoritative `pokemon`
 * table with owner=0 while escrowed.  Normalized offer rows record exactly who
 * owns each escrowed Pokémon so every decline, withdrawal, cancellation and
 * accepted trade can be reversed or finalized without recreating Pokémon rows.
 */
function pv_trade_team_ids(mysqli $db, int $uid): array
{
    $stmt = $db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');
    if (!$stmt) return [];
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $ids = [];
    foreach (['s1','s2','s3','s4','s5','s6'] as $slot) {
        $id = (int)($row[$slot] ?? 0);
        if ($id > 0) $ids[$id] = true;
    }
    return array_keys($ids);
}

function pv_trade_refresh_listing_offer_count(mysqli $db, int $listingId): void
{
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM trade_offers WHERE listing_id=? AND status='pending'");
    if (!$stmt) return;
    $stmt->bind_param('i', $listingId);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    $stmt = $db->prepare('UPDATE upfortrade SET offers=? WHERE id=?');
    if ($stmt) {
        $stmt->bind_param('ii', $count, $listingId);
        $stmt->execute();
        $stmt->close();
    }
}

function pv_trade_username(mysqli $db, int $uid): string
{
    $stmt = $db->prepare('SELECT username FROM members WHERE id=? LIMIT 1');
    if (!$stmt) return 'Trainer';
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $name = trim((string)($stmt->get_result()->fetch_assoc()['username'] ?? ''));
    $stmt->close();
    return $name !== '' ? $name : 'Trainer';
}

function pv_trade_offer_item_rows(mysqli $db, int $offerId, bool $forUpdate = false): array
{
    $sql = 'SELECT i.pokemon_id,i.original_owner_id,p.name,p.owner,p.lvl,p.exp '
         . 'FROM trade_offer_items i LEFT JOIN pokemon p ON p.id=i.pokemon_id '
         . 'WHERE i.offer_id=? ORDER BY i.id' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $db->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('i', $offerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function pv_trade_release_offer_items_locked(mysqli $db, int $offerId): array
{
    $items = pv_trade_offer_item_rows($db, $offerId, true);
    $owners = [];
    $update = $db->prepare("UPDATE pokemon SET owner=? WHERE id=? AND CAST(owner AS UNSIGNED)=0");
    if (!$update) throw new RuntimeException('The offered Pokémon could not be returned.');

    foreach ($items as $item) {
        $pokemonId = (int)($item['pokemon_id'] ?? 0);
        $ownerId = (int)($item['original_owner_id'] ?? 0);
        if ($pokemonId <= 0 || $ownerId <= 0 || !isset($item['owner'])) {
            $update->close();
            throw new RuntimeException('A Pokémon in this offer is no longer available.');
        }
        $currentOwner = (int)$item['owner'];
        if ($currentOwner !== 0) {
            $update->close();
            throw new RuntimeException('A Pokémon in this offer is no longer held by the Trade Center.');
        }
        $update->bind_param('ii', $ownerId, $pokemonId);
        if (!$update->execute() || $update->affected_rows !== 1) {
            $update->close();
            throw new RuntimeException('The Trade Center could not return an offered Pokémon.');
        }
        $owners[$ownerId] = true;
    }
    $update->close();
    return array_keys($owners);
}

function pv_trade_create_offer(mysqli $db, int $listingPokemonId, int $offererId, array $pokemonIds, int $expectedListingOwnerId = 0): int
{
    $listingPokemonId = max(1, $listingPokemonId);
    $offererId = max(1, $offererId);
    $pokemonIds = array_values(array_unique(array_filter(array_map('intval', $pokemonIds), static fn(int $id): bool => $id > 0)));
    if (!$pokemonIds) throw new RuntimeException('Choose at least one Pokémon for your offer.');
    if (count($pokemonIds) > 6) throw new RuntimeException('A trade offer can contain up to six Pokémon.');

    $team = array_fill_keys(pv_trade_team_ids($db, $offererId), true);
    foreach ($pokemonIds as $pokemonId) {
        if (isset($team[$pokemonId])) throw new RuntimeException('Active-team Pokémon cannot be offered for trade. Remove them from your team first.');
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT id,pid,owner,name FROM upfortrade WHERE pid=? LIMIT 1 FOR UPDATE');
        if (!$stmt) throw new RuntimeException('The Trade Center could not load this listing.');
        $stmt->bind_param('i', $listingPokemonId);
        $stmt->execute();
        $listing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$listing) throw new RuntimeException('This trade listing is no longer available.');

        $listingId = (int)$listing['id'];
        $listingOwnerId = (int)$listing['owner'];
        if ($listingOwnerId === $offererId) throw new RuntimeException('You cannot make an offer on your own Pokémon.');
        if ($expectedListingOwnerId > 0 && $listingOwnerId !== $expectedListingOwnerId) throw new RuntimeException('The listing owner changed before this offer could be created.');
        pv_console_assert_trade_allowed($db, $offererId, $listingOwnerId);

        $stmt = $db->prepare("SELECT id FROM trade_offers WHERE listing_id=? AND offerer_id=? AND status='pending' LIMIT 1 FOR UPDATE");
        if (!$stmt) throw new RuntimeException('The Trade Center could not validate your offer.');
        $stmt->bind_param('ii', $listingId, $offererId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) throw new RuntimeException('You already have a pending offer on this Pokémon. Withdraw it before sending another.');

        $stmt = $db->prepare('SELECT id,owner,name FROM pokemon WHERE id=? LIMIT 1 FOR UPDATE');
        if (!$stmt) throw new RuntimeException('The Trade Center could not validate your Pokémon.');
        $validated = [];
        foreach ($pokemonIds as $pokemonId) {
            $stmt->bind_param('i', $pokemonId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row || (int)$row['owner'] !== $offererId) {
                $stmt->close();
                throw new RuntimeException('One of the selected Pokémon is no longer available in your collection.');
            }
            $validated[] = $pokemonId;
        }
        $stmt->close();

        $createdAt = time();
        $status = 'pending';
        $stmt = $db->prepare('INSERT INTO trade_offers (listing_id,listing_pokemon_id,listing_owner_id,offerer_id,status,created_at,resolved_at) VALUES (?,?,?,?,?,?,0)');
        if (!$stmt) throw new RuntimeException('The Trade Center could not create your offer.');
        $stmt->bind_param('iiiisi', $listingId, $listingPokemonId, $listingOwnerId, $offererId, $status, $createdAt);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('The Trade Center could not create your offer.');
        }
        $offerId = (int)$db->insert_id;
        $stmt->close();

        $insertItem = $db->prepare('INSERT INTO trade_offer_items (offer_id,pokemon_id,original_owner_id) VALUES (?,?,?)');
        $escrow = $db->prepare("UPDATE pokemon SET owner='0' WHERE id=? AND CAST(owner AS UNSIGNED)=?");
        if (!$insertItem || !$escrow) throw new RuntimeException('The Trade Center could not reserve your Pokémon for this offer.');
        foreach ($validated as $pokemonId) {
            $insertItem->bind_param('iii', $offerId, $pokemonId, $offererId);
            if (!$insertItem->execute()) throw new RuntimeException('One of the selected Pokémon is already involved in another trade.');
            $escrow->bind_param('ii', $pokemonId, $offererId);
            if (!$escrow->execute() || $escrow->affected_rows !== 1) throw new RuntimeException('A selected Pokémon could not be reserved for this offer.');
        }
        $insertItem->close();
        $escrow->close();
        pv_trade_refresh_listing_offer_count($db, $listingId);
        $db->commit();

        pv_server_event('TRADE','Trade offer created',[
            'offer_id'=>$offerId,
            'listing_pokemon_id'=>$listingPokemonId,
            'seller_uid'=>$listingOwnerId,
            'offered_pokemon_ids'=>$validated,
        ]);
        pv_recalculate_trainer_progress($db, $offererId, true);
        return $offerId;
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof RuntimeException) throw $e;
        pv_log('Trade offer creation failed: ' . $e->getMessage());
        throw new RuntimeException('The Trade Center could not create your offer. Please try again.');
    }
}

function pv_trade_resolve_offer(mysqli $db, int $offerId, int $actorId, string $action): array
{
    $offerId = max(1, $offerId);
    $actorId = max(1, $actorId);
    $action = strtolower(trim($action));
    if (!in_array($action, ['accept','decline','withdraw'], true)) throw new RuntimeException('Invalid trade action.');

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT o.*,u.pid AS live_pid,u.owner AS live_listing_owner,u.name AS listing_name FROM trade_offers o LEFT JOIN upfortrade u ON u.id=o.listing_id WHERE o.id=? AND o.status='pending' LIMIT 1 FOR UPDATE");
        if (!$stmt) throw new RuntimeException('The Trade Center could not load this offer.');
        $stmt->bind_param('i', $offerId);
        $stmt->execute();
        $offer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$offer) throw new RuntimeException('This offer is no longer pending.');

        $listingId = (int)$offer['listing_id'];
        $listingPokemonId = (int)$offer['listing_pokemon_id'];
        $sellerId = (int)$offer['listing_owner_id'];
        $offererId = (int)$offer['offerer_id'];

        if ($action === 'withdraw' && $actorId !== $offererId) throw new RuntimeException('Only the trainer who sent this offer can withdraw it.');
        if (in_array($action, ['accept','decline'], true) && $actorId !== $sellerId) throw new RuntimeException('Only the listing owner can resolve this offer.');

        if ($action !== 'accept') {
            $affected = pv_trade_release_offer_items_locked($db, $offerId);
            $status = $action === 'decline' ? 'declined' : 'withdrawn';
            $resolvedAt = time();
            $stmt = $db->prepare('UPDATE trade_offers SET status=?,resolved_at=? WHERE id=? AND status=\'pending\'');
            if (!$stmt) throw new RuntimeException('The offer could not be resolved.');
            $stmt->bind_param('sii', $status, $resolvedAt, $offerId);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('This offer changed before it could be resolved.');
            }
            $stmt->close();
            pv_trade_refresh_listing_offer_count($db, $listingId);
            $db->commit();
            pv_server_event('TRADE','Trade offer resolved',[
                'offer_id'=>$offerId,
                'status'=>$status,
                'listing_pokemon_id'=>$listingPokemonId,
                'seller_uid'=>$sellerId,
                'offerer_uid'=>$offererId,
            ]);
            foreach ($affected as $uid) pv_recalculate_trainer_progress($db, (int)$uid, (int)$uid === $actorId);
            return ['status'=>$status,'listing_pokemon_id'=>$listingPokemonId,'seller_id'=>$sellerId,'offerer_id'=>$offererId];
        }

        pv_console_assert_trade_allowed($db, $sellerId, $offererId);

        if ((int)($offer['live_pid'] ?? 0) !== $listingPokemonId || (int)($offer['live_listing_owner'] ?? 0) !== $sellerId) {
            throw new RuntimeException('This trade listing is no longer available.');
        }

        $stmt = $db->prepare('SELECT id,owner,name FROM pokemon WHERE id=? LIMIT 1 FOR UPDATE');
        if (!$stmt) throw new RuntimeException('The listed Pokémon could not be validated.');
        $stmt->bind_param('i', $listingPokemonId);
        $stmt->execute();
        $listedPokemon = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$listedPokemon || (int)$listedPokemon['owner'] !== 0) throw new RuntimeException('The listed Pokémon is no longer held by the Trade Center.');

        $acceptedItems = pv_trade_offer_item_rows($db, $offerId, true);
        if (!$acceptedItems) throw new RuntimeException('This offer contains no Pokémon.');
        foreach ($acceptedItems as $item) {
            if (!isset($item['owner']) || (int)$item['owner'] !== 0) throw new RuntimeException('An offered Pokémon is no longer held by the Trade Center.');
        }

        $sellerName = pv_trade_username($db, $sellerId);
        $offererName = pv_trade_username($db, $offererId);

        // Refund every competing pending offer before the listing disappears.
        $stmt = $db->prepare("SELECT id,offerer_id FROM trade_offers WHERE listing_id=? AND status='pending' AND id<>? ORDER BY id FOR UPDATE");
        if (!$stmt) throw new RuntimeException('Competing offers could not be finalized.');
        $stmt->bind_param('ii', $listingId, $offerId);
        $stmt->execute();
        $r = $stmt->get_result();
        $otherOffers = [];
        while ($row = $r->fetch_assoc()) $otherOffers[] = [(int)$row['id'], (int)$row['offerer_id']];
        $stmt->close();

        $recalc = [$sellerId=>true, $offererId=>true];
        foreach ($otherOffers as [$otherOfferId, $otherOffererId]) {
            foreach (pv_trade_release_offer_items_locked($db, $otherOfferId) as $uid) $recalc[(int)$uid] = true;
            $recalc[$otherOffererId] = true;
        }
        if ($otherOffers) {
            $resolvedAt = time();
            $stmt = $db->prepare("UPDATE trade_offers SET status='cancelled',resolved_at=? WHERE listing_id=? AND status='pending' AND id<>?");
            if (!$stmt) throw new RuntimeException('Competing offers could not be closed.');
            $stmt->bind_param('iii', $resolvedAt, $listingId, $offerId);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $db->prepare('UPDATE pokemon SET owner=?,rowner=? WHERE id=? AND CAST(owner AS UNSIGNED)=0');
        if (!$stmt) throw new RuntimeException('The listed Pokémon could not be transferred.');
        $stmt->bind_param('isi', $offererId, $sellerName, $listingPokemonId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('The listed Pokémon could not be transferred.');
        }
        $stmt->close();

        $stmt = $db->prepare('UPDATE pokemon SET owner=?,rowner=? WHERE id=? AND CAST(owner AS UNSIGNED)=0');
        if (!$stmt) throw new RuntimeException('The offered Pokémon could not be transferred.');
        foreach ($acceptedItems as $item) {
            $pokemonId = (int)$item['pokemon_id'];
            $stmt->bind_param('isi', $sellerId, $offererName, $pokemonId);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('An offered Pokémon could not be transferred.');
            }
        }
        $stmt->close();

        $resolvedAt = time();
        $stmt = $db->prepare("UPDATE trade_offers SET status='accepted',resolved_at=? WHERE id=? AND status='pending'");
        if (!$stmt) throw new RuntimeException('The accepted offer could not be finalized.');
        $stmt->bind_param('ii', $resolvedAt, $offerId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('The accepted offer changed before it could be finalized.');
        }
        $stmt->close();

        $stmt = $db->prepare('DELETE FROM upfortrade WHERE id=? AND owner=?');
        if (!$stmt) throw new RuntimeException('The completed listing could not be closed.');
        $stmt->bind_param('ii', $listingId, $sellerId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('The completed listing could not be closed.');
        }
        $stmt->close();

        $db->commit();
        pv_server_event('TRADE','Trade completed',[
            'offer_id'=>$offerId,
            'listing_pokemon_id'=>$listingPokemonId,
            'seller_uid'=>$sellerId,
            'offerer_uid'=>$offererId,
            'offered_count'=>count($acceptedItems),
        ]);
        foreach (array_keys($recalc) as $uid) pv_recalculate_trainer_progress($db, (int)$uid, (int)$uid === $actorId);
        return ['status'=>'accepted','listing_pokemon_id'=>$listingPokemonId,'seller_id'=>$sellerId,'offerer_id'=>$offererId];
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof RuntimeException) throw $e;
        pv_log('Trade offer resolution failed: ' . $e->getMessage());
        throw new RuntimeException('The Trade Center could not resolve this offer. Please try again.');
    }
}

function pv_trade_remove_listing(mysqli $db, int $listingPokemonId, int $ownerId): array
{
    $listingPokemonId = max(1, $listingPokemonId);
    $ownerId = max(1, $ownerId);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT id,pid,owner,name FROM upfortrade WHERE pid=? AND owner=? LIMIT 1 FOR UPDATE');
        if (!$stmt) throw new RuntimeException('The Trade Center could not load this listing.');
        $stmt->bind_param('ii', $listingPokemonId, $ownerId);
        $stmt->execute();
        $listing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$listing) throw new RuntimeException('This listing is no longer available.');
        $listingId = (int)$listing['id'];

        $stmt = $db->prepare('SELECT id,owner FROM pokemon WHERE id=? LIMIT 1 FOR UPDATE');
        if (!$stmt) throw new RuntimeException('The listed Pokémon could not be validated.');
        $stmt->bind_param('i', $listingPokemonId);
        $stmt->execute();
        $pokemon = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$pokemon || (int)$pokemon['owner'] !== 0) throw new RuntimeException('The listed Pokémon is no longer held by the Trade Center.');

        $stmt = $db->prepare("SELECT id,offerer_id FROM trade_offers WHERE listing_id=? AND status='pending' ORDER BY id FOR UPDATE");
        if (!$stmt) throw new RuntimeException('Pending offers could not be loaded.');
        $stmt->bind_param('i', $listingId);
        $stmt->execute();
        $r = $stmt->get_result();
        $offers = [];
        while ($row = $r->fetch_assoc()) $offers[] = [(int)$row['id'], (int)$row['offerer_id']];
        $stmt->close();

        $recalc = [$ownerId=>true];
        foreach ($offers as [$offerId, $offererId]) {
            foreach (pv_trade_release_offer_items_locked($db, $offerId) as $uid) $recalc[(int)$uid] = true;
            $recalc[$offererId] = true;
        }
        if ($offers) {
            $resolvedAt = time();
            $stmt = $db->prepare("UPDATE trade_offers SET status='cancelled',resolved_at=? WHERE listing_id=? AND status='pending'");
            if (!$stmt) throw new RuntimeException('Pending offers could not be cancelled.');
            $stmt->bind_param('ii', $resolvedAt, $listingId);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $db->prepare("UPDATE pokemon SET owner=? WHERE id=? AND CAST(owner AS UNSIGNED)=0");
        if (!$stmt) throw new RuntimeException('The listed Pokémon could not be returned.');
        $stmt->bind_param('ii', $ownerId, $listingPokemonId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('The listed Pokémon could not be returned.');
        }
        $stmt->close();

        $stmt = $db->prepare('DELETE FROM upfortrade WHERE id=? AND owner=?');
        if (!$stmt) throw new RuntimeException('The listing could not be removed.');
        $stmt->bind_param('ii', $listingId, $ownerId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('The listing could not be removed.');
        }
        $stmt->close();
        $db->commit();

        pv_server_event('TRADE','Trade listing removed',[
            'listing_pokemon_id'=>$listingPokemonId,
            'affected_trainers'=>array_keys($recalc),
        ]);
        foreach (array_keys($recalc) as $uid) pv_recalculate_trainer_progress($db, (int)$uid, (int)$uid === $ownerId);
        return ['listing_pokemon_id'=>$listingPokemonId,'affected_trainers'=>array_keys($recalc)];
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof RuntimeException) throw $e;
        pv_log('Trade listing removal failed: ' . $e->getMessage());
        throw new RuntimeException('The Trade Center could not remove this listing. Please try again.');
    }
}
