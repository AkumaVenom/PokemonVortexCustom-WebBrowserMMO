<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/gameplay.php';
pv_require_login();

try {
    $db = pv_db();
} catch (Throwable $e) {
    pv_log('Trade Center DB unavailable: ' . $e->getMessage());
    http_response_code(503);
    pv_page_start('Trade Center', 'trade.php', true);
    echo '<main class="pv-page"><div class="pv-alert pv-alert-error">The Trade Center is temporarily unavailable. Please try again shortly.</div></main>';
    pv_page_end();
    exit;
}

$uid = (int)$_SESSION['myid'];
$view = strtolower(trim((string)($_GET['view'] ?? 'browse')));
if (!in_array($view, ['browse','mine','received','sent'], true)) $view = 'browse';
$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) > 60) $q = mb_substr($q, 0, 60);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

function pv_trade_count(mysqli $db, string $sql, int $uid): int {
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
}

$counts = [
    'market' => pv_trade_count($db, 'SELECT COUNT(*) AS c FROM upfortrade WHERE owner<>?', $uid),
    'mine' => pv_trade_count($db, 'SELECT COUNT(*) AS c FROM upfortrade WHERE owner=?', $uid),
    'received' => pv_trade_count($db, "SELECT COUNT(*) AS c FROM trade_offers WHERE listing_owner_id=? AND status='pending'", $uid),
    'sent' => pv_trade_count($db, "SELECT COUNT(*) AS c FROM trade_offers WHERE offerer_id=? AND status='pending'", $uid),
];

$noticeKey = (string)($_GET['notice'] ?? '');
$noticeMap = [
    'listed' => ['success','Listing created','Your Pokémon is now visible in the Trade Center.'],
    'offer_sent' => ['success','Offer sent','Your selected Pokémon are reserved for this offer while the listing owner decides.'],
    'offer_withdrawn' => ['success','Offer withdrawn','Your offered Pokémon have been returned to your collection.'],
    'offer_declined' => ['success','Offer declined','The offered Pokémon were returned to their trainer.'],
    'offer_accepted' => ['success','Trade completed','The Pokémon have changed owners and all other pending offers were returned.'],
    'listing_removed' => ['success','Listing removed','Your Pokémon and all pending offer Pokémon were safely returned.'],
];
$notice = $noticeMap[$noticeKey] ?? null;
$tradeError = trim((string)($_SESSION['trade_error'] ?? ''));
unset($_SESSION['trade_error']);

$rows = [];
$total = 0;
if ($view === 'browse' || $view === 'mine') {
    $conditions = $view === 'mine' ? ['u.owner=?'] : ['u.owner<>?'];
    $types = 'i'; $params = [$uid];
    if ($q !== '') {
        $conditions[] = '(u.name LIKE ? OR COALESCE(m.username,u.rowner) LIKE ?)';
        $like = '%' . $q . '%';
        $types .= 'ss'; $params[] = $like; $params[] = $like;
    }
    $where = implode(' AND ', $conditions);
    $countSql = 'SELECT COUNT(*) AS c FROM upfortrade u LEFT JOIN members m ON m.id=u.owner WHERE ' . $where;
    $stmt = $db->prepare($countSql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params); $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0); $stmt->close();
    }
    $lastPage = max(1, (int)ceil($total / $perPage));
    if ($page > $lastPage) { $page = $lastPage; $offset = ($page - 1) * $perPage; }
    $sql = 'SELECT u.id,u.pid,u.name,u.lvl,u.exp,u.a1,u.a2,u.a3,u.a4,u.owner,u.offers,u.date,COALESCE(NULLIF(m.username,\'\'),NULLIF(u.rowner,\'\'),\'Trainer\') AS owner_name '
         . 'FROM upfortrade u LEFT JOIN members m ON m.id=u.owner WHERE ' . $where . ' ORDER BY u.date DESC,u.id DESC LIMIT ? OFFSET ?';
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $listTypes = $types . 'ii'; $listParams = array_merge($params, [$perPage,$offset]);
        $stmt->bind_param($listTypes, ...$listParams); $stmt->execute();
        $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) $rows[] = $row; $stmt->close();
    }
} elseif ($view === 'received') {
    $stmt = $db->prepare("SELECT o.id,o.listing_pokemon_id,o.offerer_id,o.created_at,u.name AS listing_name,u.lvl AS listing_lvl,m.username AS offerer_name,COUNT(i.id) AS item_count,GROUP_CONCAT(p.name ORDER BY i.id SEPARATOR '|||') AS item_names FROM trade_offers o JOIN upfortrade u ON u.id=o.listing_id LEFT JOIN members m ON m.id=o.offerer_id LEFT JOIN trade_offer_items i ON i.offer_id=o.id LEFT JOIN pokemon p ON p.id=i.pokemon_id WHERE o.listing_owner_id=? AND o.status='pending' GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100");
    if ($stmt) { $stmt->bind_param('i',$uid); $stmt->execute(); $r=$stmt->get_result(); while($row=$r->fetch_assoc())$rows[]=$row; $stmt->close(); }
    $total = count($rows); $lastPage = 1;
} else {
    $stmt = $db->prepare("SELECT o.id,o.listing_pokemon_id,o.status,o.created_at,o.resolved_at,u.name AS live_listing_name,COALESCE(u.name,p.name,'Pokémon') AS listing_name,COALESCE(m.username,'Trainer') AS seller_name,COUNT(i.id) AS item_count,GROUP_CONCAT(ip.name ORDER BY i.id SEPARATOR '|||') AS item_names FROM trade_offers o LEFT JOIN upfortrade u ON u.id=o.listing_id LEFT JOIN pokemon p ON p.id=o.listing_pokemon_id LEFT JOIN members m ON m.id=o.listing_owner_id LEFT JOIN trade_offer_items i ON i.offer_id=o.id LEFT JOIN pokemon ip ON ip.id=i.pokemon_id WHERE o.offerer_id=? GROUP BY o.id ORDER BY (o.status='pending') DESC,o.created_at DESC LIMIT 100");
    if ($stmt) { $stmt->bind_param('i',$uid); $stmt->execute(); $r=$stmt->get_result(); while($row=$r->fetch_assoc())$rows[]=$row; $stmt->close(); }
    $total = count($rows); $lastPage = 1;
}

pv_page_start('Trade Center', 'trade.php', true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('trade.php'); ?>
<main class="pv-main-column">
<section class="pv-page">
    <div class="pv-page-head">
        <div><span class="pv-eyebrow">TRAINER HUB // TRADE CENTER</span><h1>Trade Center</h1><p class="pv-subtle">List Pokémon, make offers and trade safely with other trainers.</p></div>
        <a class="pv-button" href="<?= pv_h(pv_url('put_up_for_trade.php')) ?>">List Pokémon</a>
    </div>

    <?php if ($notice): ?><div class="pv-alert pv-alert-<?= pv_h($notice[0]) ?>"><strong><?= pv_h($notice[1]) ?>.</strong> <?= pv_h($notice[2]) ?></div><?php endif; ?>
    <?php if ($tradeError !== ''): ?><div class="pv-alert pv-alert-error"><strong>Trade action could not be completed.</strong> <?= pv_h($tradeError) ?></div><?php endif; ?>

    <div class="pv-trade-overview">
        <a href="<?= pv_h(pv_url('trade.php?view=browse')) ?>" class="<?= $view==='browse'?'active':'' ?>"><small>MARKET</small><strong><?= number_format($counts['market']) ?></strong><span>Listings available</span></a>
        <a href="<?= pv_h(pv_url('trade.php?view=mine')) ?>" class="<?= $view==='mine'?'active':'' ?>"><small>YOUR LISTINGS</small><strong><?= number_format($counts['mine']) ?></strong><span>Active listings</span></a>
        <a href="<?= pv_h(pv_url('trade.php?view=received')) ?>" class="<?= $view==='received'?'active':'' ?>"><small>OFFERS RECEIVED</small><strong><?= number_format($counts['received']) ?></strong><span>Awaiting your decision</span></a>
        <a href="<?= pv_h(pv_url('trade.php?view=sent')) ?>" class="<?= $view==='sent'?'active':'' ?>"><small>OFFERS SENT</small><strong><?= number_format($counts['sent']) ?></strong><span>Currently pending</span></a>
    </div>

    <div class="pv-trade-tabs" role="navigation" aria-label="Trade Center sections">
        <?php foreach (['browse'=>'Browse Market','mine'=>'Your Listings','received'=>'Received Offers','sent'=>'Sent Offers'] as $key=>$label): ?>
            <a class="<?= $view===$key?'active':'' ?>" href="<?= pv_h(pv_url('trade.php?view='.$key)) ?>"><?= pv_h($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($view === 'browse' || $view === 'mine'): ?>
        <form class="pv-trade-search" method="get" action="<?= pv_h(pv_url('trade.php')) ?>">
            <input type="hidden" name="view" value="<?= pv_h($view) ?>">
            <label><span>SEARCH MARKET</span><input type="search" name="q" value="<?= pv_h($q) ?>" placeholder="Pokémon or trainer name" maxlength="60"></label>
            <button class="pv-button pv-button-secondary" type="submit">Search</button>
            <?php if ($q !== ''): ?><a href="<?= pv_h(pv_url('trade.php?view='.$view)) ?>">Clear</a><?php endif; ?>
        </form>

        <div class="pv-section-heading"><div><span><?= $view==='mine'?'YOUR LISTINGS':'PUBLIC MARKET' ?></span><h2><?= number_format($total) ?> <?= $total===1?'listing':'listings' ?></h2></div><small><?= $q!==''?'Filtered results':'Latest first' ?></small></div>
        <?php if (!$rows): ?>
            <div class="pv-empty-state"><strong><?= $view==='mine'?'You have no active listings.':'No trade listings matched this view.' ?></strong><span><?= $view==='mine'?'Choose a reserve Pokémon and create your first listing.':'Try a different search or check back as trainers list more Pokémon.' ?></span></div>
        <?php else: ?>
            <div class="pv-trade-market-grid">
            <?php foreach ($rows as $row): $pid=(int)$row['pid']; $name=(string)$row['name']; ?>
                <article class="pv-trade-card">
                    <div class="pv-trade-card-image"><img src="<?= pv_h(pv_pokemon_sprite($name)) ?>" alt="<?= pv_h($name) ?>" loading="lazy" decoding="async"><span>LV <?= max(1,(int)$row['lvl']) ?></span></div>
                    <div class="pv-trade-card-body">
                        <div class="pv-trade-card-title"><div><small>#<?= $pid ?> // <?= pv_h((string)$row['owner_name']) ?></small><h3><?= pv_h($name) ?></h3></div><b><?= number_format(max(0,(int)$row['offers'])) ?> <?= (int)$row['offers']===1?'offer':'offers' ?></b></div>
                        <div class="pv-trade-meta"><span><?= number_format(max(0,(int)$row['exp'])) ?> EXP</span><span><?= (int)$row['date']>0 ? pv_h(date('M j, Y',(int)$row['date'])) : 'Active listing' ?></span></div>
                        <div class="pv-move-strip"><?php foreach (['a1','a2','a3','a4'] as $move): if(trim((string)$row[$move])!==''): ?><span><?= pv_h((string)$row[$move]) ?></span><?php endif; endforeach; ?></div>
                        <div class="pv-trade-card-actions">
                            <a href="<?= pv_h(pv_url('pokedex.php?pid='.$pid)) ?>">Details</a>
                            <?php if ((int)$row['owner'] === $uid): ?>
                                <a class="primary" href="<?= pv_h(pv_url('view_offers.php?pid='.$pid)) ?>">View Offers</a>
                                <form method="post" action="<?= pv_h(pv_url('offer.php')) ?>" data-pv-confirm="Remove this listing and return any Pokémon tied to its offers?"><?= pv_csrf_field() ?><input type="hidden" name="action" value="remove_listing"><input type="hidden" name="pid" value="<?= $pid ?>"><button type="submit">Remove</button></form>
                            <?php else: ?>
                                <a class="primary" href="<?= pv_h(pv_url('make_an_offer.php?pid='.$pid)) ?>">Make Offer</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (($lastPage ?? 1) > 1): ?><nav class="pv-pagination" aria-label="Market pages"><?php if($page>1):?><a href="<?=pv_h(pv_url('trade.php?view='.$view.'&q='.rawurlencode($q).'&page='.($page-1)))?>">← Previous</a><?php endif;?><span>Page <?=$page?> of <?=$lastPage?></span><?php if($page<$lastPage):?><a href="<?=pv_h(pv_url('trade.php?view='.$view.'&q='.rawurlencode($q).'&page='.($page+1)))?>">Next →</a><?php endif;?></nav><?php endif; ?>

    <?php elseif ($view === 'received'): ?>
        <div class="pv-section-heading"><div><span>INCOMING EXCHANGE REQUESTS</span><h2><?= number_format($total) ?> pending <?= $total===1?'offer':'offers' ?></h2></div><small>Review before accepting</small></div>
        <?php if (!$rows): ?><div class="pv-empty-state"><strong>No offers are waiting.</strong><span>Offers from other trainers will appear here while your listings remain active.</span></div><?php else: ?>
        <div class="pv-offer-list">
        <?php foreach($rows as $row): $names=array_values(array_filter(explode('|||',(string)($row['item_names']??'')))); ?>
            <article class="pv-offer-row">
                <img src="<?=pv_h(pv_pokemon_sprite((string)$row['listing_name']))?>" alt="<?=pv_h((string)$row['listing_name'])?>">
                <div><small>OFFER #<?= (int)$row['id'] ?> // <?= pv_h((string)($row['offerer_name'] ?: 'Trainer')) ?></small><h3>For <?=pv_h((string)$row['listing_name'])?></h3><p><?= number_format((int)$row['item_count']) ?> Pokémon offered · <?= pv_h(implode(', ', array_slice($names,0,4))) ?><?=count($names)>4?'…':''?></p></div>
                <a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('view_offers.php?pid='.(int)$row['listing_pokemon_id']))?>">Review Offer</a>
            </article>
        <?php endforeach; ?>
        </div><?php endif; ?>

    <?php else: ?>
        <div class="pv-section-heading"><div><span>YOUR OUTGOING OFFERS</span><h2><?= number_format($total) ?> recent <?= $total===1?'offer':'offers' ?></h2></div><small>Pending and resolved</small></div>
        <?php if (!$rows): ?><div class="pv-empty-state"><strong>You have not made any offers yet.</strong><span>Browse the market and choose up to six reserve Pokémon to propose an exchange.</span></div><?php else: ?>
        <div class="pv-offer-list">
        <?php foreach($rows as $row): $status=(string)$row['status']; $names=array_values(array_filter(explode('|||',(string)($row['item_names']??'')))); ?>
            <article class="pv-offer-row">
                <img src="<?=pv_h(pv_pokemon_sprite((string)$row['listing_name']))?>" alt="<?=pv_h((string)$row['listing_name'])?>">
                <div><small>OFFER #<?= (int)$row['id'] ?> // TO <?= pv_h((string)$row['seller_name']) ?></small><h3><?=pv_h((string)$row['listing_name'])?></h3><p><?= number_format((int)$row['item_count']) ?> offered · <?=pv_h(implode(', ',array_slice($names,0,4)))?><?=count($names)>4?'…':''?></p></div>
                <span class="pv-trade-status pv-trade-status-<?=pv_h($status)?>"><?=pv_h(strtoupper($status))?></span>
                <?php if($status==='pending'): ?><form method="post" action="<?=pv_h(pv_url('offer.php'))?>" data-pv-confirm="Withdraw this offer and return your Pokémon?"><?=pv_csrf_field()?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="offer_id" value="<?=(int)$row['id']?>"><button class="pv-button pv-button-secondary" type="submit">Withdraw</button></form><?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div><?php endif; ?>
    <?php endif; ?>
</section>
</main>
</div>
<?php pv_page_end(); ?>
