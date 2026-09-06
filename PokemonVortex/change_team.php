<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/collection.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();
$db=pv_db();$uid=(int)$_SESSION['myid'];

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        pv_require_csrf();
        $submitted=[];
        for($i=1;$i<=6;$i++)$submitted[]=(int)($_POST['slot'.$i]??0);
        $slots=pv_collection_save_team($db,$uid,$submitted);
        // Keep the recovered compatibility session cache synchronized with the
        // authoritative member row. Modern battle initialization re-reads the DB,
        // but older routes still consult my_team in a few non-critical UI paths.
        $_SESSION['my_team']=array_map('intval',array_pad(array_slice($slots,0,6),6,0));
        $_SESSION['pv_team_flash']=['type'=>'success','text'=>'Your active team was updated successfully.'];
    }
    catch(RuntimeException $e){$_SESSION['pv_team_flash']=['type'=>'error','text'=>$e->getMessage()];}
    pv_redirect('change_team.php');
}
$flash=$_SESSION['pv_team_flash']??null;unset($_SESSION['pv_team_flash']);
$current=pv_collection_team_ids($db,$uid);$current=array_pad($current,6,0);
$stmt=$db->prepare("SELECT p.id,p.name,p.lvl,COALESCE(ps.display_form,'') display_form FROM pokemon p LEFT JOIN pokemon_stats ps ON ps.id=p.id WHERE CAST(p.owner AS UNSIGNED)=? ORDER BY p.name ASC,p.lvl DESC,p.id ASC");$owned=[];if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$owned[]=$row;$stmt->close();}
$byId=[];foreach($owned as $row)$byId[(int)$row['id']]=$row;

pv_page_start('Change Team','your_pokemon.php',true);
?>
<div class="pv-game-layout"><?php pv_game_side_menu('change_team.php'); ?><main class="pv-main-column"><section class="pv-page pv-team-builder-page">
<div class="pv-page-head"><div><span class="pv-eyebrow">ACTIVE TEAM CONFIGURATION</span><h1>Change Team</h1><p class="pv-subtle">Choose up to six different Pokémon. Slot 1 enters wild battles first.</p></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('your_pokemon.php'))?>">Collection Archive</a></div>
<?php if(is_array($flash)):?><div class="pv-alert pv-alert-<?=pv_h((string)$flash['type'])?>"><?=pv_h((string)$flash['text'])?></div><?php endif;?>
<?php if(!$owned):?><div class="pv-empty-state"><span class="pv-brand-mark"><img src="<?=pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG'))?>" alt=""></span><div><h2>No Pokémon available</h2><p>Capture or obtain a Pokémon before configuring an active team.</p><a class="pv-button" href="<?=pv_h(pv_url('map_select.php'))?>">Explore Maps</a></div></div><?php else:?>
<form method="post" class="pv-team-builder"><?=pv_csrf_field()?>
<div class="pv-team-slot-grid">
<?php for($slot=1;$slot<=6;$slot++):$selected=(int)$current[$slot-1];$row=$byId[$selected]??null;$display=$row?pv_collection_display_name($row):'';?>
<section class="pv-team-slot <?=$selected>0?'occupied':''?>"><header><span>SLOT <?=str_pad((string)$slot,2,'0',STR_PAD_LEFT)?></span><strong><?=$slot===1?'LEAD':'PARTNER'?></strong></header><div class="pv-team-slot-preview"><?php if($row):?><img src="<?=pv_h(pv_pokemon_sprite($display))?>" alt="<?=pv_h($display)?>"><div><h3><?=pv_h($display)?></h3><p>Level <?=number_format((int)$row['lvl'])?></p></div><?php else:?><span>+</span><div><h3>Empty slot</h3><p>Optional battle position</p></div><?php endif;?></div><label><span>Assigned Pokémon</span><select name="slot<?=$slot?>" <?=$slot===1?'required':''?>><option value="0"><?=$slot===1?'Choose lead Pokémon':'Leave empty'?></option><?php foreach($owned as $p):$pd=pv_collection_display_name($p);?><option value="<?=(int)$p['id']?>" <?=(int)$p['id']===$selected?'selected':''?>><?=pv_h($pd)?> · Lv. <?=number_format((int)$p['lvl'])?> · #<?=(int)$p['id']?></option><?php endforeach;?></select></label></section>
<?php endfor;?>
</div>
<div class="pv-team-builder-footer"><div><strong><?=number_format(count($owned))?> Pokémon available</strong><span>Each Pokémon can occupy only one active battle slot.</span></div><button type="submit">Save Active Team</button></div>
</form>
<?php endif;?>
</section></main></div>
<?php pv_page_end(); ?>
