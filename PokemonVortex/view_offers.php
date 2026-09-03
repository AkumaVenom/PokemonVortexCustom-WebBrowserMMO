<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/gameplay.php';
pv_require_login();
$db=pv_db();
$uid=(int)$_SESSION['myid'];
$pid=max(1,(int)($_GET['pid']??0));
$stmt=$db->prepare("SELECT u.id,u.pid,u.owner,u.name,u.lvl,u.exp,u.a1,u.a2,u.a3,u.a4,u.offers,u.date FROM upfortrade u WHERE u.pid=? AND u.owner=? LIMIT 1");
$stmt->bind_param('ii',$pid,$uid);$stmt->execute();$listing=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$listing) pv_redirect('trade.php?view=mine');
$listingId=(int)$listing['id'];
$stmt=$db->prepare("SELECT o.id,o.offerer_id,o.created_at,COALESCE(m.username,'Trainer') AS offerer_name FROM trade_offers o LEFT JOIN members m ON m.id=o.offerer_id WHERE o.listing_id=? AND o.status='pending' ORDER BY o.created_at ASC,o.id ASC");
$stmt->bind_param('i',$listingId);$stmt->execute();$r=$stmt->get_result();$offers=[];while($o=$r->fetch_assoc()){$o['items']=pv_trade_offer_item_rows($db,(int)$o['id']);$offers[]=$o;}$stmt->close();
pv_page_start('Review Trade Offers','trade.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('trade.php'); ?>
<main class="pv-main-column"><section class="pv-page">
<div class="pv-page-head"><div><span class="pv-eyebrow">TRADE CENTER // OFFER REVIEW</span><h1>Review Offers</h1><p class="pv-subtle">Compare every pending offer before making a final exchange decision.</p></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('trade.php?view=mine'))?>">Your Listings</a></div>
<article class="pv-trade-focus">
<div class="pv-trade-focus-image"><img src="<?=pv_h(pv_pokemon_sprite((string)$listing['name']))?>" alt="<?=pv_h((string)$listing['name'])?>"></div>
<div><small>YOUR LISTING // #<?=$pid?></small><h2><?=pv_h((string)$listing['name'])?></h2><p>Lv. <?=max(1,(int)$listing['lvl'])?> · <?=number_format(max(0,(int)$listing['exp']))?> EXP · <?=count($offers)?> pending <?=count($offers)===1?'offer':'offers'?></p><div class="pv-move-strip"><?php foreach(['a1','a2','a3','a4'] as $m):if(trim((string)$listing[$m])!==''):?><span><?=pv_h((string)$listing[$m])?></span><?php endif;endforeach;?></div></div>
<form method="post" action="<?=pv_h(pv_url('offer.php'))?>" data-pv-confirm="Remove this listing and return any Pokémon tied to its offers?"><?=pv_csrf_field()?><input type="hidden" name="action" value="remove_listing"><input type="hidden" name="pid" value="<?=$pid?>"><button class="pv-button pv-button-secondary" type="submit">Remove Listing</button></form>
</article>

<div class="pv-section-heading"><div><span>PENDING OFFERS</span><h2><?=count($offers)?> <?=count($offers)===1?'proposal':'proposals'?></h2></div><small>Accepting is final</small></div>
<?php if(!$offers):?>
<div class="pv-empty-state"><strong>No pending offers yet.</strong><span>Your listing remains visible in the public market until you remove it or accept an offer.</span></div>
<?php else:?>
<div class="pv-trade-offer-grid">
<?php foreach($offers as $offer):?>
<article class="pv-trade-offer-card">
<div class="pv-trade-offer-head"><div><small>OFFER #<?=(int)$offer['id']?></small><h3><?=pv_h((string)$offer['offerer_name'])?></h3><span><?=pv_h(date('M j, Y H:i',(int)$offer['created_at']))?></span></div><strong><?=count($offer['items'])?> <?=count($offer['items'])===1?'Pokémon':'Pokémon'?></strong></div>
<div class="pv-trade-offer-items">
<?php foreach($offer['items'] as $item):$name=(string)($item['name']??'Pokémon');?>
<div><img src="<?=pv_h(pv_pokemon_sprite($name))?>" alt="<?=pv_h($name)?>"><span><strong><?=pv_h($name)?></strong><small>Lv. <?=max(1,(int)($item['lvl']??1))?> · <?=number_format(max(0,(int)($item['exp']??0)))?> EXP</small></span></div>
<?php endforeach;?>
</div>
<div class="pv-trade-offer-actions">
<form method="post" action="<?=pv_h(pv_url('offer.php'))?>" data-pv-confirm="Accept this trade? The ownership exchange is immediate and cannot be undone."><?=pv_csrf_field()?><input type="hidden" name="action" value="accept"><input type="hidden" name="offer_id" value="<?=(int)$offer['id']?>"><button class="pv-button" type="submit">Accept Offer</button></form>
<form method="post" action="<?=pv_h(pv_url('offer.php'))?>"><?=pv_csrf_field()?><input type="hidden" name="action" value="decline"><input type="hidden" name="offer_id" value="<?=(int)$offer['id']?>"><button class="pv-button pv-button-secondary" type="submit">Decline</button></form>
</div>
</article>
<?php endforeach;?>
</div>
<?php endif;?>
</section></main></div>
<?php pv_page_end(); ?>
