<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/collection.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();
$db=pv_db();$uid=(int)$_SESSION['myid'];
$pokemonId=max(0,(int)($_GET['pid']??$_POST['pokemon_id']??0));

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{pv_require_csrf();$slot=(int)($_POST['slot']??0);$move=trim((string)($_POST['move']??''));$result=pv_move_teach($db,$uid,$pokemonId,$slot,$move);$_SESSION['pv_move_flash']=['type'=>'success','text'=>'Move updated: '.$result['old'].' → '.$result['new'].' for ₽'.number_format((int)$result['price']).'.'];}
    catch(RuntimeException $e){$_SESSION['pv_move_flash']=['type'=>'error','text'=>$e->getMessage()];}
    pv_redirect('change_attacks.php?pid='.$pokemonId);
}
$flash=$_SESSION['pv_move_flash']??null;unset($_SESSION['pv_move_flash']);
$pokemon=$pokemonId?pv_collection_pokemon($db,$uid,$pokemonId):null;
if(!$pokemon){
    $stmt=$db->prepare("SELECT p.id,p.name,p.lvl,COALESCE(ps.display_form,'') display_form FROM pokemon p LEFT JOIN pokemon_stats ps ON ps.id=p.id WHERE CAST(p.owner AS UNSIGNED)=? ORDER BY p.name,p.id LIMIT 120");$rows=[];if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$rows[]=$row;$stmt->close();}
    pv_page_start('Move Lab','your_pokemon.php',true);?>
    <div class="pv-game-layout"><?php pv_game_side_menu('your_pokemon.php');?><main class="pv-main-column"><section class="pv-page"><div class="pv-page-head"><div><span class="pv-eyebrow">MOVE LAB</span><h1>Choose a Pokémon</h1><p class="pv-subtle">Select a Pokémon before changing its move set.</p></div></div><div class="pv-pokemon-selection-grid"><?php foreach($rows as $p):$d=pv_collection_display_name($p);?><a class="pv-pokemon-select-card" href="<?=pv_h(pv_url('change_attacks.php?pid='.(int)$p['id']))?>"><img src="<?=pv_h(pv_pokemon_sprite($d))?>" alt="<?=pv_h($d)?>"><strong><?=pv_h($d)?></strong><small>Lv. <?=number_format((int)$p['lvl'])?></small><span>Open Move Lab</span></a><?php endforeach;?></div></section></main></div><?php pv_page_end();exit;
}
$display=pv_collection_display_name($pokemon);$search=trim((string)($_GET['q']??''));$moves=pv_move_catalog($db,$pokemon,$search,180);$current=[1=>(string)$pokemon['a1'],2=>(string)$pokemon['a2'],3=>(string)$pokemon['a3'],4=>(string)$pokemon['a4']];
$stmt=$db->prepare('SELECT money FROM members WHERE id=? LIMIT 1');$money=0;if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$money=(int)($stmt->get_result()->fetch_assoc()['money']??0);$stmt->close();}
$recent=[];$stmt=$db->prepare('SELECT slot_no,old_move,new_move,price,created_at FROM move_lab_transactions WHERE user_id=? AND pokemon_id=? ORDER BY id DESC LIMIT 6');if($stmt){$stmt->bind_param('ii',$uid,$pokemonId);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$recent[]=$row;$stmt->close();}

pv_page_start('Move Lab · '.$display,'your_pokemon.php',true);
?>
<div class="pv-game-layout"><?php pv_game_side_menu('your_pokemon.php');?><main class="pv-main-column"><section class="pv-page pv-movelab-page">
<div class="pv-page-head"><div><span class="pv-eyebrow">MOVE LAB // POKÉMON #<?=$pokemonId?></span><h1><?=pv_h($display)?></h1><p class="pv-subtle">Teach compatible moves and fine-tune your battle set using trainer funds.</p></div><div class="pv-wallet"><small>AVAILABLE FUNDS</small><strong>₽<?=number_format($money)?></strong></div></div>
<?php if(is_array($flash)):?><div class="pv-alert pv-alert-<?=pv_h((string)$flash['type'])?>"><?=pv_h((string)$flash['text'])?></div><?php endif;?>
<div class="pv-movelab-hero"><div class="pv-movelab-pokemon"><img src="<?=pv_h(pv_pokemon_sprite($display))?>" alt="<?=pv_h($display)?>"><div><small><?=pv_h((string)$pokemon['t1'])?><?=trim((string)$pokemon['t2'])!==''?' / '.pv_h((string)$pokemon['t2']):''?></small><h2><?=pv_h($display)?></h2><span>Level <?=number_format((int)$pokemon['lvl'])?> · <?=pv_h((string)($pokemon['nature']?:'Unknown nature'))?></span></div></div><div class="pv-movelab-current"><?php foreach($current as $slot=>$move):?><div><small>SLOT <?=$slot?></small><strong><?=pv_h($move!==''?$move:'Empty')?></strong></div><?php endforeach;?></div></div>
<form method="get" class="pv-dex-search"><input type="hidden" name="pid" value="<?=$pokemonId?>"><input name="q" value="<?=pv_h($search)?>" placeholder="Search compatible moves"><button type="submit">Search</button><?php if($search!==''):?><a href="<?=pv_h(pv_url('change_attacks.php?pid='.$pokemonId))?>">Clear</a><?php endif;?></form>
<div class="pv-movelab-grid">
<?php foreach($moves as $move):$already=false;foreach($current as $cm)if(strcasecmp($cm,(string)$move['attack'])===0)$already=true;?>
<article class="pv-move-card <?=$already?'is-known':''?>"><header><div><small><?=pv_h(strtoupper((string)$move['type']))?></small><h3><?=pv_h((string)$move['attack'])?></h3></div><strong>₽<?=number_format((int)$move['price'])?></strong></header><div class="pv-move-stats"><span>Power <b><?=number_format((int)$move['power'])?></b></span><span>Accuracy <b><?=number_format((int)$move['accuracy'])?>%</b></span><span><?=pv_h((string)$move['category'])?></span></div><?php if($already):?><p class="pv-move-known">Already known</p><?php else:?><form method="post"><?=pv_csrf_field()?><input type="hidden" name="pokemon_id" value="<?=$pokemonId?>"><input type="hidden" name="move" value="<?=pv_h((string)$move['attack'])?>"><label><span>Replace slot</span><select name="slot"><?php foreach($current as $slot=>$old):?><option value="<?=$slot?>"><?=$slot?> · <?=pv_h($old!==''?$old:'Empty')?></option><?php endforeach;?></select></label><button type="submit" <?=$money<(int)$move['price']?'disabled':''?>>Teach Move</button></form><?php endif;?></article>
<?php endforeach;?>
</div>
<?php if(!$moves):?><div class="pv-empty-state"><span class="pv-brand-mark">MV</span><div><h2>No compatible moves found</h2><p>Try a different search term or review the moves available to this Pokémon.</p></div></div><?php endif;?>
<div class="pv-shop-bottom-grid"><section class="pv-card"><span class="pv-eyebrow">MOVE COMPATIBILITY</span><p>Available moves depend on the Pokémon's natural move set and elemental typing, giving each species its own training options.</p></section><section class="pv-card"><span class="pv-eyebrow">RECENT CHANGES</span><div class="pv-receipt-list"><?php if(!$recent):?><p class="pv-subtle">No Move Lab changes recorded for this Pokémon.</p><?php else:foreach($recent as $row):?><div><strong>Slot <?=(int)$row['slot_no']?> · <?=pv_h((string)$row['old_move'])?> → <?=pv_h((string)$row['new_move'])?></strong><span>₽<?=number_format((int)$row['price'])?> · <?=date('j M H:i',(int)$row['created_at'])?> UTC</span></div><?php endforeach;endif;?></div></section></div>
</section></main></div>
<?php pv_page_end(); ?>
