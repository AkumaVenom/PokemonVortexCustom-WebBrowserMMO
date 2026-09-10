<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !defined('PV_SERVER_CONSOLE_CLI')) { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/network.php';
require_once __DIR__ . '/social_runtime.php';

function pv_admin_social_specs(): array
{
    return [
        'trade' => ['usage'=>'trade <target> [listing-pokemon-id offered-pokemon-id ...]', 'summary'=>'For the selected trainer, inspect a target\'s listings or place a real escrow offer of up to six boxed Pokémon.', 'rank'=>'PLAYER', 'min'=>1, 'max'=>8],
        'tradecancel' => ['usage'=>'tradecancel [offer-id|all]', 'summary'=>'Withdraw selected trainer\'s outgoing offers and return their escrowed Pokémon; defaults to all.', 'rank'=>'PLAYER', 'min'=>0, 'max'=>1],
        'tradehistory' => ['usage'=>'tradehistory [player]', 'summary'=>'Read the latest 50 real trade offers involving a trainer (defaults to selected).', 'rank'=>'MODERATOR', 'min'=>0, 'max'=>1],
        'spectate' => ['usage'=>'spectate [player]', 'summary'=>'Read a current authoritative Live PvP snapshot without controlling the battle.', 'rank'=>'MODERATOR', 'min'=>0, 'max'=>1],
        'blocktrade' => ['usage'=>'blocktrade [target [on|off]]', 'summary'=>'List or change the selected trainer\'s trade blocks; blocks are enforced in browser offer creation and acceptance.', 'rank'=>'MODERATOR', 'min'=>0, 'max'=>2],
        'friends' => ['usage'=>'friends [player]', 'summary'=>'List real friendships and pending friend requests for a trainer (defaults to selected).', 'rank'=>'PLAYER', 'min'=>0, 'max'=>1],
        'friend' => ['usage'=>'friend <target> [add|accept|decline|remove]', 'summary'=>'Manage the selected trainer\'s real friend-request lifecycle; add accepts an incoming request or sends one.', 'rank'=>'PLAYER', 'min'=>1, 'max'=>2],
    ];
}

function pv_admin_social_handle(string $command, array $args, array &$context): array
{
    $db = pv_admin_db();
    if ($command === 'trade') {
        $actor = pv_admin_player('', $context);
        $target = pv_admin_player($args[0], $context);
        $uid = (int)$actor['id'];
        $targetId = (int)$target['id'];
        if ($uid === $targetId) throw new InvalidArgumentException('Select a different target trainer.');
        if (count($args) === 1) {
            $listings = pv_admin_rows('SELECT pid,name,lvl,offers FROM upfortrade WHERE owner=? ORDER BY id DESC LIMIT 51', [$targetId]);
            $lines = ['Trade listings for '.$target['username'].'; selected offerer: '.$actor['username'].'.'];
            foreach (array_slice($listings, 0, 50) as $listing) {
                $lines[] = '#'.$listing['pid'].' '.$listing['name'].' | level '.$listing['lvl'].' | pending offers '.$listing['offers'];
            }
            if (!$listings) $lines[] = 'This trainer has no listed Pokémon. They must list one through the Trade Center first.';
            if (count($listings) > 50) $lines[] = 'Showing the newest 50 listings.';
            $lines[] = 'To submit: trade "'.$target['username'].'" <listed-pokemon-id> <your-boxed-pokemon-id> [up to five more IDs].';
            $lines[] = 'An offer reserves the offered Pokémon in the existing Trade Center; the listing owner accepts it there.';
            return $lines;
        }
        if (count($args) < 3) throw new InvalidArgumentException('Supply a listed Pokémon ID and at least one offered Pokémon ID.');
        $listingId = pv_admin_int($args[1], 1, PHP_INT_MAX, 'listed Pokémon ID');
        $offered = [];
        foreach (array_slice($args, 2) as $value) $offered[] = pv_admin_int($value, 1, PHP_INT_MAX, 'offered Pokémon ID');
        if (count(array_unique($offered)) !== count($offered)) throw new InvalidArgumentException('Each offered Pokémon ID must be different.');
        $listing = pv_admin_row('SELECT owner FROM upfortrade WHERE pid=? LIMIT 1', [$listingId]);
        if (!$listing || (int)$listing['owner'] !== $targetId) throw new RuntimeException('That Pokémon is not listed by the target trainer.');
        $offerId = pv_trade_create_offer($db, $listingId, $uid, $offered, $targetId);
        pv_admin_touch($uid);
        return ['Created trade offer #'.$offerId.' from '.$actor['username'].' to '.$target['username'].' for Pokémon #'.$listingId.'.', 'Escrow reserved offered Pokémon: '.implode(', ', $offered).'. The owner can accept or decline in the Trade Center.'];
    }

    if ($command === 'tradecancel') {
        $actor = pv_admin_player('', $context);
        $uid = (int)$actor['id'];
        $which = $args[0] ?? 'all';
        if ($which === 'all') {
            $offers = pv_admin_rows("SELECT id FROM trade_offers WHERE offerer_id=? AND status='pending' ORDER BY id", [$uid]);
        } else {
            $id = pv_admin_int($which, 1, PHP_INT_MAX, 'offer ID');
            $offer = pv_admin_row("SELECT id FROM trade_offers WHERE id=? AND offerer_id=? AND status='pending'", [$id, $uid]);
            if (!$offer) throw new RuntimeException('That offer is not a pending outgoing offer belonging to the selected trainer.');
            $offers = [$offer];
        }
        if (!$offers) return ['No pending outgoing trade offers for '.$actor['username'].'.'];
        $lines = [];
        foreach ($offers as $offer) {
            try {
                pv_trade_resolve_offer($db, (int)$offer['id'], $uid, 'withdraw');
                $lines[] = 'Withdrew offer #'.$offer['id'].'; its Pokémon were returned to '.$actor['username'].'.';
            } catch (RuntimeException $e) {
                $lines[] = 'Offer #'.$offer['id'].' was not withdrawn: '.$e->getMessage();
            }
        }
        pv_admin_touch($uid);
        return $lines;
    }

    if ($command === 'tradehistory') {
        $player = pv_admin_player($args[0] ?? '', $context);
        $uid = (int)$player['id'];
        $rows = pv_admin_rows('SELECT o.id,o.listing_pokemon_id,o.listing_owner_id,o.offerer_id,o.status,o.created_at,o.resolved_at,s.username AS seller,b.username AS offerer FROM trade_offers o LEFT JOIN members s ON s.id=o.listing_owner_id LEFT JOIN members b ON b.id=o.offerer_id WHERE o.listing_owner_id=? OR o.offerer_id=? ORDER BY o.id DESC LIMIT 50', [$uid, $uid]);
        $lines = ['Trade history for '.$player['username'].' (latest 50 offers):'];
        foreach ($rows as $row) {
            $items = pv_admin_rows('SELECT pokemon_id FROM trade_offer_items WHERE offer_id=? ORDER BY id', [(int)$row['id']]);
            $offered = implode(',', array_column($items, 'pokemon_id'));
            $lines[] = '#'.$row['id'].' '.$row['status'].' | '.($row['offerer'] ?? '#'.$row['offerer_id']).' -> '.($row['seller'] ?? '#'.$row['listing_owner_id']).' | listing #'.$row['listing_pokemon_id'].' | offered '.($offered ?: '(none recorded)').' | '.gmdate('Y-m-d H:i:s', (int)$row['created_at']).' UTC';
        }
        if (!$rows) $lines[] = 'No trade offers recorded.';
        return $lines;
    }

    if ($command === 'blocktrade') {
        $actor = pv_admin_player('', $context);
        $uid = (int)$actor['id'];
        if (!$args) {
            $rows = pv_admin_rows('SELECT b.target_id,m.username FROM console_trade_blocks b LEFT JOIN members m ON m.id=b.target_id WHERE b.user_id=? ORDER BY b.target_id', [$uid]);
            $lines = ['Trade blocks set by '.$actor['username'].':'];
            foreach ($rows as $row) $lines[] = '#'.$row['target_id'].' '.($row['username'] ?? '(deleted trainer)');
            if (!$rows) $lines[] = 'No outgoing trade blocks.';
            return $lines;
        }
        $target = pv_admin_player($args[0], $context);
        $targetId = (int)$target['id'];
        if ($targetId === $uid) throw new InvalidArgumentException('A trainer cannot block trading with themself.');
        $mode = strtolower($args[1] ?? 'on');
        if (!in_array($mode, ['on','off'], true)) throw new InvalidArgumentException('Use on or off.');
        pv_admin_transaction(function () use ($db, $uid, $targetId, $mode): void {
            pv_console_lock_social_pair($db, $uid, $targetId);
            if ($mode === 'on') {
                pv_admin_exec('INSERT INTO console_trade_blocks (user_id,target_id,created_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE created_at=VALUES(created_at)', [$uid, $targetId, time()]);
            } else {
                pv_admin_exec('DELETE FROM console_trade_blocks WHERE user_id=? AND target_id=?', [$uid, $targetId]);
            }
        });
        $opposite = pv_admin_row('SELECT user_id FROM console_trade_blocks WHERE user_id=? AND target_id=?', [$targetId, $uid]);
        $lines = [$actor['username'].' trade block for '.$target['username'].': '.$mode.'.'];
        if ($mode === 'off' && $opposite) $lines[] = 'The target still has a reciprocal block, so trading remains blocked.';
        if ($mode === 'on') $lines[] = 'New offers and acceptance are blocked in the console and browser. Pending offers remain withdrawable or declinable.';
        return $lines;
    }

    if ($command === 'friends') {
        $player = pv_admin_player($args[0] ?? '', $context);
        $uid = (int)$player['id'];
        $rows = pv_admin_rows('SELECT f.friend_id,m.username FROM trainer_friends f JOIN members m ON m.id=f.friend_id WHERE f.user_id=? ORDER BY m.username', [$uid]);
        $lines = ['Friends for '.$player['username'].':'];
        foreach ($rows as $row) $lines[] = '#'.$row['friend_id'].' '.$row['username'];
        if (!$rows) $lines[] = 'No friends yet.';
        $requests = pv_admin_rows("SELECT r.id,r.sender_id,r.receiver_id,s.username AS sender,n.username AS receiver FROM friend_requests r LEFT JOIN members s ON s.id=r.sender_id LEFT JOIN members n ON n.id=r.receiver_id WHERE r.status='pending' AND (r.sender_id=? OR r.receiver_id=?) ORDER BY r.id", [$uid, $uid]);
        foreach ($requests as $request) {
            $incoming = (int)$request['receiver_id'] === $uid;
            $name = $incoming ? ($request['sender'] ?? '#'.$request['sender_id']) : ($request['receiver'] ?? '#'.$request['receiver_id']);
            $lines[] = 'Pending request #'.$request['id'].' '.($incoming ? 'from ' : 'to ').$name;
        }
        return $lines;
    }

    if ($command === 'friend') {
        $actor = pv_admin_player('', $context);
        $target = pv_admin_player($args[0], $context);
        $uid = (int)$actor['id'];
        $targetId = (int)$target['id'];
        if ($targetId === $uid) throw new InvalidArgumentException('Choose a different trainer.');
        $action = strtolower($args[1] ?? 'add');
        if (!in_array($action, ['add','accept','decline','remove'], true)) throw new InvalidArgumentException('Use add, accept, decline, or remove.');
        if ($action === 'remove') {
            if (!pv_network_are_friends($db, $uid, $targetId)) return [$actor['username'].' and '.$target['username'].' are not friends.'];
            pv_network_remove_friend($db, $uid, $targetId);
            return ['Removed the friendship between '.$actor['username'].' and '.$target['username'].'.'];
        }
        if ($action === 'add' && pv_network_are_friends($db, $uid, $targetId)) return [$actor['username'].' and '.$target['username'].' are already friends.'];
        $incoming = pv_admin_row("SELECT id FROM friend_requests WHERE sender_id=? AND receiver_id=? AND status='pending' ORDER BY id LIMIT 1", [$targetId, $uid]);
        if ($action !== 'add' || $incoming) {
            if (!$incoming) throw new RuntimeException('No pending incoming friend request from that trainer.');
            $decision = $action === 'decline' ? 'declined' : 'accepted';
            pv_network_resolve_friend_request($db, $uid, (int)$incoming['id'], $decision);
            $resolved = pv_admin_row('SELECT status FROM friend_requests WHERE id=?', [(int)$incoming['id']]);
            return ['Friend request #'.$incoming['id'].' '.($resolved['status'] ?? 'resolved').': '.$target['username'].' -> '.$actor['username'].'.'];
        }
        pv_network_friend_request($db, $uid, $targetId);
        return ['Sent a friend request from '.$actor['username'].' to '.$target['username'].'. The target can accept in their Friends page or through their selected console context.'];
    }

    if ($command === 'spectate') return pv_admin_social_spectate($args, $context);
    throw new InvalidArgumentException('Unknown social command: '.$command);
}

function pv_admin_social_spectate(array $args, array $context): array
{
    $player = $args || !empty($context['selected']) ? pv_admin_player($args[0] ?? '', $context) : null;
    $params = [];
    $where = '(b.settled_1=0 OR b.settled_2=0)';
    if ($player) {
        $where .= ' AND (b.uid_1=? OR b.uid_2=?)';
        $params = [(int)$player['id'], (int)$player['id']];
    }
    $rows = pv_admin_rows('SELECT b.id,b.uid_1,b.uid_2,b.`_2`,b.created_at,a.username AS first_name,c.username AS second_name FROM live_battle b LEFT JOIN members a ON a.id=b.uid_1 LEFT JOIN members c ON c.id=b.uid_2 WHERE '.$where.' ORDER BY b.id DESC LIMIT 50', $params);
    $lines = ['Read-only Live PvP snapshot at '.gmdate('Y-m-d H:i:s').' UTC'.($player ? ' for '.$player['username'] : '').'.'];
    $shown = 0;
    foreach ($rows as $row) {
        $state = json_decode((string)$row['_2'], true);
        if (!is_array($state) || (int)($state['schema'] ?? 0) !== 2 || ($state['phase'] ?? '') === 'complete') continue;
        $shown++;
        $lines[] = 'Battle #'.$row['id'].' | '.($row['first_name'] ?? '#'.$row['uid_1']).' vs '.($row['second_name'] ?? '#'.$row['uid_2']).' | phase '.($state['phase'] ?? '?').' | turn '.($state['turn'] ?? 0).' | revision '.($state['revision'] ?? 0);
        foreach (['1','2'] as $slot) {
            $participant = $state['participants'][$slot] ?? [];
            $activeId = (int)($participant['active'] ?? 0);
            $living = 0;
            $activeText = 'no active Pokémon selected';
            foreach (($participant['team'] ?? []) as $fighter) {
                if ((int)($fighter['hp'] ?? 0) > 0) $living++;
                if ((int)($fighter['id'] ?? 0) === $activeId) $activeText = (string)($fighter['name'] ?? 'Pokémon').' #'.$activeId.' HP '.(int)($fighter['hp'] ?? 0).'/'.(int)($fighter['max_hp'] ?? 0).' status '.((string)($fighter['status'] ?? '') ?: 'healthy');
            }
            $lines[] = '  '.($participant['name'] ?? 'Trainer '.$slot).': '.$activeText.'; '.$living.' conscious team members.';
        }
        if ($player) break;
    }
    if (!$shown) $lines[] = 'No active initialized Live PvP battle found'.($player ? ' for this trainer' : ' among the latest 50 unsettled matches').'.';
    $lines[] = 'Run spectate again to refresh. Session-only PvE battles cannot be observed from another process.';
    return $lines;
}
