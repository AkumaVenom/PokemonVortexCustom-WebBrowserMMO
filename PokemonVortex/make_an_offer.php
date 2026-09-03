<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/gameplay.php';
pv_require_login();

$db = pv_db();
$uid = (int)$_SESSION['myid'];
$pid = max(1, (int)($_GET['pid'] ?? $_POST['pid'] ?? 0));

$stmt = $db->prepare("SELECT u.id,u.pid,u.owner,u.name,u.lvl,u.exp,u.a1,u.a2,u.a3,u.a4,u.offers,COALESCE(NULLIF(m.username,''),NULLIF(u.rowner,''),'Trainer') AS owner_name FROM upfortrade u LEFT JOIN members m ON m.id=u.owner WHERE u.pid=? LIMIT 1");
$stmt->bind_param('i',$pid); $stmt->execute(); $listing=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$listing) pv_redirect('trade.php?view=browse');
if ((int)$listing['owner'] === $uid) pv_redirect('view_offers.php?pid='.$pid);

$stmt = $db->prepare("SELECT id,created_at FROM trade_offers WHERE listing_id=? AND offerer_id=? AND status='pending' LIMIT 1");
$listingId=(int)$listing['id']; $stmt->bind_param('ii',$listingId,$uid); $stmt->execute(); $existing=$stmt->get_result()->fetch_assoc(); $stmt->close();

$error='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    pv_require_csrf();
    if ($existing) {
        $error='You already have a pending offer on this listing.';
    } else {
        $selected=$_POST['pokemon'] ?? [];
        if (!is_array($selected)) $selected=[$selected];
        try {
            pv_trade_create_offer($db,$pid,$uid,$selected);
            pv_redirect('trade.php?view=sent&notice=offer_sent');
        } catch (RuntimeException $e) {
            $error=$e->getMessage();
        }
    }
}

$teamIds=pv_trade_team_ids($db,$uid);
$where='CAST(p.owner AS UNSIGNED)=?';
if($teamIds) $where.=' AND p.id NOT IN ('.implode(',',array_map('intval',$teamIds)).')';
$stmt=$db->prepare("SELECT p.id,p.name,p.lvl,p.exp FROM pokemon p WHERE $where ORDER BY p.name,p.id LIMIT 240");
$stmt->bind_param('i',$uid); $stmt->execute(); $r=$stmt->get_result(); $pokemon=[]; while($row=$r->fetch_assoc())$pokemon[]=$row; $stmt->close();

pv_page_start('Make Trade Offer','trade.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('trade.php'); ?>
<main class="pv-main-column"><section class="pv-page">
<div class="pv-page-head">
<div><span class="pv-eyebrow">TRADE CENTER // BUILD OFFER</span><h1>Make an Offer</h1><p class="pv-subtle">Choose up to six reserve Pokémon to offer. They stay reserved for this trade until the offer is accepted, declined or withdrawn.</p></div>
<a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('trade.php?view=browse'))?>">Back to Market</a>
</div>
<?php if($error!==''):?><div class="pv-alert pv-alert-error"><?=pv_h($error)?></div><?php endif;?>

<article class="pv-trade-focus">
<div class="pv-trade-focus-image"><img src="<?=pv_h(pv_pokemon_sprite((string)$listing['name']))?>" alt="<?=pv_h((string)$listing['name'])?>"></div>
<div><small>LISTING #<?= (int)$listing['id'] ?> // <?=pv_h((string)$listing['owner_name'])?></small><h2><?=pv_h((string)$listing['name'])?></h2><p>Lv. <?=max(1,(int)$listing['lvl'])?> · <?=number_format(max(0,(int)$listing['exp']))?> EXP · <?=number_format((int)$listing['offers'])?> current offers</p><div class="pv-move-strip"><?php foreach(['a1','a2','a3','a4'] as $move):if(trim((string)$listing[$move])!==''):?><span><?=pv_h((string)$listing[$move])?></span><?php endif;endforeach;?></div></div>
</article>

<?php if($existing):?>
<div class="pv-alert pv-alert-info"><strong>Offer already pending.</strong> Your current offer was sent on <?=pv_h(date('M j, Y H:i',(int)$existing['created_at']))?>.</div>
<form method="post" action="<?=pv_h(pv_url('offer.php'))?>" data-pv-confirm="Withdraw your current offer and return the Pokémon to your collection?">
<?=pv_csrf_field()?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="offer_id" value="<?=(int)$existing['id']?>"><button class="pv-button pv-button-secondary" type="submit">Withdraw Current Offer</button>
</form>
<?php elseif(!$pokemon):?>
<div class="pv-empty-state"><strong>No reserve Pokémon are eligible.</strong><span>Active-team Pokémon cannot enter a trade offer. Move a Pokémon out of your team or catch another Pokémon first.</span></div>
<?php else:?>
<form method="post" action="<?=pv_h(pv_url('make_an_offer.php?pid='.$pid))?>" data-pv-offer-form>
<?=pv_csrf_field()?><input type="hidden" name="pid" value="<?=$pid?>">
<div class="pv-section-heading"><div><span>YOUR RESERVES</span><h2>Select 1–6 Pokémon</h2></div><small><span data-pv-offer-count>0</span> selected</small></div>
<div class="pv-pokemon-selection-grid">
<?php foreach($pokemon as $row):$id=(int)$row['id'];$name=(string)$row['name'];?>
<label class="pv-pokemon-select-card">
<input type="checkbox" name="pokemon[]" value="<?=$id?>" data-pv-offer-item>
<span class="pv-pokemon-select-check" aria-hidden="true">✓</span>
<span class="pv-pokemon-select-image"><img src="<?=pv_h(pv_pokemon_sprite($name))?>" alt="<?=pv_h($name)?>" loading="lazy" decoding="async"></span>
<strong><?=pv_h($name)?></strong><small>Lv. <?=max(1,(int)$row['lvl'])?> · <?=number_format(max(0,(int)$row['exp']))?> EXP</small>
<a href="<?=pv_h(pv_url('pokedex.php?pid='.$id))?>">View details</a>
</label>
<?php endforeach;?>
</div>
<div class="pv-form-actions pv-sticky-actionbar"><span><strong>Your selected Pokémon will be reserved for this offer.</strong> The trade only happens if the listing owner accepts.</span><button class="pv-button" type="submit">Send Trade Offer</button></div>
</form>
<?php endif;?>
</section></main></div>
<?php pv_page_end(); ?>
