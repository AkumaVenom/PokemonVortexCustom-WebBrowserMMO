<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/economy.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();

try { $db=pv_db(); } catch(Throwable $e){ pv_log('PokéMart DB unavailable: '.$e->getMessage()); pv_redirect('dashboard.php?service=unavailable'); }
$uid=(int)$_SESSION['myid'];
$catalog=pv_shop_catalog();
$category=trim((string)($_GET['cat']??'medicine'));if(!isset($catalog[$category]))$category='medicine';

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        pv_require_csrf();
        $key=trim((string)($_POST['item_key']??''));$qty=max(1,min(99,(int)($_POST['quantity']??1)));
        $result=pv_shop_purchase($db,$uid,$key,$qty);
        $_SESSION['pv_shop_flash']=['type'=>'success','text'=>'Purchased '.$result['quantity'].' × '.$result['item']['label'].' for ₽'.number_format($result['total']).'.'];
        pv_redirect('items.php?cat='.rawurlencode((string)$result['item']['category']));
    }catch(RuntimeException $e){$_SESSION['pv_shop_flash']=['type'=>'error','text'=>$e->getMessage()];pv_redirect('items.php?cat='.rawurlencode($category));}
}

$flash=$_SESSION['pv_shop_flash']??null;unset($_SESSION['pv_shop_flash']);
$inventory=pv_shop_inventory($db,$uid);
$stmt=$db->prepare('SELECT money FROM members WHERE id=? LIMIT 1');$money=0;if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$money=max(0,(int)($stmt->get_result()->fetch_assoc()['money']??0));$stmt->close();}
$recent=[];$stmt=$db->prepare('SELECT item_label,quantity,total_price,created_at FROM shop_transactions WHERE user_id=? ORDER BY id DESC LIMIT 8');if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$recent[]=$row;$stmt->close();}

pv_page_start('Items & PokéMart','items.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('items.php'); ?>
<main class="pv-main-column">
<section class="pv-page pv-shop-page">
    <div class="pv-page-head"><div><span class="pv-eyebrow">POKÉMON SUPPLIES</span><h1>Items & PokéMart</h1><p class="pv-subtle">Restock battle supplies, capture gear, evolution items and fossils.</p></div><div class="pv-wallet"><small>AVAILABLE FUNDS</small><strong>₽<?=number_format($money)?></strong></div></div>
    <?php if(is_array($flash)):?><div class="pv-alert pv-alert-<?=pv_h((string)$flash['type'])?>"><?=pv_h((string)$flash['text'])?></div><?php endif;?>

    <nav class="pv-shop-tabs" aria-label="PokéMart departments">
    <?php foreach($catalog as $key=>$cat):?><a class="<?=$category===$key?'active':''?>" href="<?=pv_h(pv_url('items.php?cat='.rawurlencode($key)))?>"><strong><?=pv_h($cat['label'])?></strong><small><?=count($cat['items'])?> items</small></a><?php endforeach;?>
    </nav>

    <div class="pv-shop-department-head"><div><span>DEPARTMENT // <?=pv_h(strtoupper($category))?></span><h2><?=pv_h($catalog[$category]['label'])?></h2><p><?=pv_h($catalog[$category]['description'])?></p></div><?php if($category==='fossils'):?><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('fossil_lab.php'))?>">Open Fossil Lab</a><?php elseif($category==='evolution'):?><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('your_pokemon.php'))?>">Choose Pokémon</a><?php endif;?></div>

    <div class="pv-shop-grid">
    <?php foreach($catalog[$category]['items'] as $key=>$item):$owned=max(0,(int)($inventory[$item['column']]??0));$sprite=pv_item_sprite((string)$item['label']);?>
        <article class="pv-shop-card">
            <div class="pv-shop-art"><img src="<?=pv_h($sprite)?>" alt="<?=pv_h($item['label'])?>"><span>OWNED <?=number_format($owned)?></span></div>
            <div class="pv-shop-copy"><small><?=pv_h(strtoupper($catalog[$category]['label']))?></small><h3><?=pv_h($item['label'])?></h3><p><?=pv_h($item['description'])?></p><strong>₽<?=number_format((int)$item['price'])?></strong></div>
            <form method="post" class="pv-shop-buy"><?=pv_csrf_field()?><input type="hidden" name="item_key" value="<?=pv_h($key)?>"><label>QTY<select name="quantity"><?php foreach([1,2,3,5,10,25,50] as $q):?><option value="<?=$q?>"><?=$q?></option><?php endforeach;?></select></label><button type="submit" <?=$money<(int)$item['price']?'disabled':''?>>Buy</button></form>
        </article>
    <?php endforeach;?>
    </div>

    <div class="pv-shop-bottom-grid">
        <section class="pv-card"><span class="pv-eyebrow">INVENTORY LINK</span><h3>Battle-ready supplies</h3><p>Medicine can heal your Pokémon in battle, Poké Balls can catch wild Pokémon, and evolution items can be used in the Evolution Lab when a Pokémon meets the right requirements.</p><div class="pv-actions"><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('map_select.php'))?>">Explore Maps</a><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('your_pokemon.php'))?>">Your Pokémon</a></div></section>
        <section class="pv-card"><span class="pv-eyebrow">RECENT PURCHASES</span><div class="pv-receipt-list"><?php if(!$recent):?><p class="pv-subtle">No purchases recorded yet.</p><?php else:foreach($recent as $row):?><div><strong><?=pv_h($row['item_label'])?> × <?=number_format((int)$row['quantity'])?></strong><span>₽<?=number_format((int)$row['total_price'])?> · <?=date('j M H:i',(int)$row['created_at'])?> UTC</span></div><?php endforeach;endif;?></div></section>
    </div>
</section>
</main></div>
<?php pv_page_end(); ?>
