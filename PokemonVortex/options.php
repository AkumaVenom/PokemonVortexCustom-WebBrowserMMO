<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/ui.php';
pv_require_login();
$db=pv_db();$uid=(int)$_SESSION['myid'];

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        pv_require_csrf();
        $mapPresence=isset($_POST['memonmap'])?1:0;$messages=isset($_POST['messonoff'])?1:0;$notify=isset($_POST['messnotifyonoff'])?1:0;$trainer=max(1,min(40,(int)($_POST['trainer']??1)));
        $mapTime=strtolower(trim((string)($_POST['map_time']??'')));
        if(!in_array($mapTime,['day','night'],true))throw new RuntimeException('Map time preference is invalid.');
        $night=$mapTime==='night'?1:0;
        $db->begin_transaction();
        try{
            $stmt=$db->prepare('UPDATE members SET memonmap=?,messonoff=?,messnotifyonoff=? WHERE id=?');if(!$stmt)throw new RuntimeException('Trainer preferences could not be saved.');$stmt->bind_param('iiii',$mapPresence,$messages,$notify,$uid);$stmt->execute();$stmt->close();
            $stmt=$db->prepare('INSERT INTO members_options (id,trainer,memonmap,messonoff,messnotifyonoff) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE trainer=VALUES(trainer),memonmap=VALUES(memonmap),messonoff=VALUES(messonoff),messnotifyonoff=VALUES(messnotifyonoff)');if(!$stmt)throw new RuntimeException('Trainer preferences could not be saved.');$stmt->bind_param('iiiii',$uid,$trainer,$mapPresence,$messages,$notify);$stmt->execute();$stmt->close();
            $db->commit();
            if (!isset($_SESSION['map_preferences']) || !is_array($_SESSION['map_preferences'])) $_SESSION['map_preferences']=[0,1,1];
            $_SESSION['map_preferences'][1]=$mapPresence;
            $_SESSION['map_preferences'][2]=max(1,min(29,$trainer));
            // Reconstruct the recovered Vortex map Day/Night preference on the
            // modern CSRF-protected endpoint. Historical behavior was session-scoped.
            $_SESSION['night']=$night;
            pv_server_event('ACCOUNT','Trainer options saved',['show_players'=>$mapPresence,'messages'=>$messages,'notifications'=>$notify,'trainer_sprite'=>$trainer,'map_time'=>$mapTime]);
            $_SESSION['pv_options_flash']=['type'=>'success','text'=>'Trainer preferences saved.'];
        }catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('Preferences could not be saved safely.');}
    }catch(RuntimeException $e){$_SESSION['pv_options_flash']=['type'=>'error','text'=>$e->getMessage()];}
    pv_redirect('options.php');
}
$flash=$_SESSION['pv_options_flash']??null;unset($_SESSION['pv_options_flash']);
$stmt=$db->prepare('SELECT m.memonmap,m.messonoff,m.messnotifyonoff,COALESCE(o.trainer,1) trainer FROM members m LEFT JOIN members_options o ON o.id=m.id WHERE m.id=? LIMIT 1');$settings=['memonmap'=>1,'messonoff'=>1,'messnotifyonoff'=>1,'trainer'=>1];if($stmt){$stmt->bind_param('i',$uid);$stmt->execute();$settings=array_merge($settings,$stmt->get_result()->fetch_assoc()?:[]);$stmt->close();}
$sprites=[];$spriteRoot=__DIR__.'/html/static/images/sprites';for($i=1;$i<=40;$i++){if(is_file($spriteRoot.'/'.$i.'.gif'))$sprites[]=$i;}
$night=(int)($_SESSION['night']??0)===1;

pv_page_start('Options','your_account.php',true);
?>
<div class="pv-game-layout"><?php pv_game_side_menu('options.php');?><main class="pv-main-column"><section class="pv-page pv-options-page">
<div class="pv-page-head"><div><span class="pv-eyebrow">TRAINER PREFERENCES</span><h1>Options</h1><p class="pv-subtle">Control your Vortex map cycle, world-map visibility, private communications and trainer avatar.</p></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('your_account.php'))?>">Your Account</a></div>
<?php if(is_array($flash)):?><div class="pv-alert pv-alert-<?=pv_h((string)$flash['type'])?>"><?=pv_h((string)$flash['text'])?></div><?php endif;?>
<form method="post" class="pv-options-form"><?=pv_csrf_field()?>
<section class="pv-card"><span class="pv-eyebrow">PRIVACY & SOCIAL</span><div class="pv-option-list"><label><input type="checkbox" name="memonmap" <?=(int)$settings['memonmap']?'checked':''?>><span><strong>Show other trainers on world maps</strong><small>Displays live trainer markers for other players exploring the same region. Enabled by default.</small></span></label><label><input type="checkbox" name="messonoff" <?=(int)$settings['messonoff']?'checked':''?>><span><strong>Accept private messages</strong><small>When disabled, other trainers cannot send new private messages to you.</small></span></label><label><input type="checkbox" name="messnotifyonoff" <?=(int)$settings['messnotifyonoff']?'checked':''?>><span><strong>Show message notifications</strong><small>Displays unread-message indicators while you play.</small></span></label></div></section>
<section class="pv-card"><span class="pv-eyebrow">VORTEX MAP TIME</span><p class="pv-subtle">Choose Day or Night for the original 25-map Vortex world. This changes the map atmosphere and which wild Pokémon can appear.</p><div class="pv-option-list"><label><input type="radio" name="map_time" value="day" <?=$night?'':'checked'?>><span><strong>Day</strong><small>Explore the Vortex world during the day and encounter its daytime Pokémon.</small></span></label><label><input type="radio" name="map_time" value="night" <?=$night?'checked':''?>><span><strong>Night</strong><small>Explore the Vortex world at night and encounter its nighttime Pokémon.</small></span></label></div></section>
<section class="pv-card"><span class="pv-eyebrow">TRAINER AVATAR</span><p class="pv-subtle">Choose how your trainer appears on world maps and profile pages.</p><div class="pv-sprite-grid"><?php foreach($sprites as $sprite):?><label class="pv-sprite-choice"><input type="radio" name="trainer" value="<?=$sprite?>" <?=(int)$settings['trainer']===$sprite?'checked':''?>><img src="<?=pv_h(pv_static_file('images/sprites/'.$sprite.'.gif','images/sprites/1.gif'))?>" alt="Trainer sprite <?=$sprite?>"><span>#<?=str_pad((string)$sprite,2,'0',STR_PAD_LEFT)?></span></label><?php endforeach;?></div></section>
<div class="pv-options-footer"><div><strong>Preferences apply immediately after saving.</strong><span>These preferences control your Vortex presentation, trainer visibility and social experience.</span></div><button type="submit">Save Options</button></div>
</form>
</section></main></div>
<?php pv_page_end(); ?>
