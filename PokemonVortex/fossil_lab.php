<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/gameplay.php';
pv_require_login();
$db = pv_db();
$uid = (int)$_SESSION['myid'];
$username = (string)($_SESSION['myuser'] ?? 'Trainer');

$fossilMap = [
    'Omanyte' => ['Helix_Fossil','Helix Fossil'],
    'Kabuto' => ['Dome_Fossil','Dome Fossil'],
    'Aerodactyl' => ['Old_Amber','Old Amber'],
    'Lileep' => ['Root_Fossil','Root Fossil'],
    'Anorith' => ['Claw_Fossil','Claw Fossil'],
    'Cranidos' => ['Skull_Fossil','Skull Fossil'],
    'Shieldon' => ['Armor_Fossil','Armor Fossil'],
    'Tirtouga' => ['Cover_Fossil','Cover Fossil'],
    'Archen' => ['Plume_Fossil','Plume Fossil'],
    'Tyrunt' => ['Jaw_Fossil','Jaw Fossil'],
    'Amaura' => ['Sail_Fossil','Sail Fossil'],
];

$success = null;
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    pv_require_csrf();
    $species = trim((string)($_POST['species'] ?? ''));
    if (!isset($fossilMap[$species])) {
        $error = 'Choose a valid fossil to restore.';
    } else {
        [$column,$fossilLabel] = $fossilMap[$species];
        $db->begin_transaction();
        try {
            $sql = 'UPDATE items SET `' . $column . '`=`' . $column . '`-1 WHERE uid=? AND `' . $column . '`>0';
            $stmt = $db->prepare($sql);
            if (!$stmt) throw new RuntimeException('The Fossil Lab could not access your inventory.');
            $stmt->bind_param('i',$uid);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('You do not have the required ' . $fossilLabel . '.');
            }
            $stmt->close();

            $roll = random_int(1,10);
            $prefix = match($roll) {
                1 => 'Shiny ', 2 => 'Dark ', 3 => 'Mystic ', 4 => 'Shadow ', 5 => 'Metallic ', default => ''
            };
            $name = $prefix . $species;
            $gender = random_int(1,2) === 1 ? 'Male' : 'Female';

            $stmt = $db->prepare('SELECT id,name,a1,a2,a3,a4,type1,type2 FROM pguide WHERE name=? LIMIT 1 FOR UPDATE');
            if (!$stmt) throw new RuntimeException('The Fossil Lab could not prepare that restoration.');
            $stmt->bind_param('s',$name); $stmt->execute(); $guide=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$guide) throw new RuntimeException('That Pokémon cannot be restored right now.');

            $level = 5; $exp = 2500; $ball = 'Poke Ball';
            $stmt = $db->prepare('INSERT INTO pokemon (name,pid,owner,a1,a2,a3,a4,lvl,t1,t2,exp,rowner,ball,gender,ot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            if (!$stmt) throw new RuntimeException('That Pokémon could not be restored.');
            $pid=(int)$guide['id'];$a1=(string)$guide['a1'];$a2=(string)$guide['a2'];$a3=(string)$guide['a3'];$a4=(string)$guide['a4'];$t1=(string)$guide['type1'];$t2=(string)$guide['type2'];
            $stmt->bind_param('siissssississss',$name,$pid,$uid,$a1,$a2,$a3,$a4,$level,$t1,$t2,$exp,$username,$ball,$gender,$username);
            if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('That Pokémon could not be restored.'); }
            $pokemonId=(int)$db->insert_id; $stmt->close();

            $ivs = array_map(static fn(): int => random_int(0,31), range(1,6));
            [$hpIv,$atkIv,$defIv,$spaIv,$spdIv,$speIv] = $ivs;
            $natures = ['Hardy','Lonely','Brave','Adamant','Naughty','Bold','Docile','Relaxed','Impish','Lax','Timid','Hasty','Serious','Jolly','Naive','Modest','Mild','Quiet','Bashful','Rash','Calm','Gentle','Sassy','Careful','Quirky'];
            $nature = $natures[random_int(0,count($natures)-1)];
            $baseSpecies = preg_replace('/^(Shiny|Dark|Mystic|Shadow|Metallic)\s+/','',$name) ?: $species;
            $stmt=$db->prepare('SELECT ability1,ability2,ability3 FROM abilities WHERE name=? LIMIT 1');
            $ability='';
            if($stmt){$stmt->bind_param('s',$baseSpecies);$stmt->execute();$ar=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();$pool=array_values(array_filter([(string)($ar['ability1']??''),(string)($ar['ability2']??''),(string)($ar['ability3']??'')],static fn(string $v):bool=>$v!==''));if($pool)$ability=$pool[random_int(0,count($pool)-1)];}

            $stmt=$db->prepare('INSERT INTO pokemon_stats (id,hp_iv,attack_iv,defense_iv,spatk_iv,spdef_iv,speed_iv,nature,ability,ot,gender,ball) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            if(!$stmt) throw new RuntimeException('That Pokémon could not be restored.');
            $stmt->bind_param('iiiiiiisssss',$pokemonId,$hpIv,$atkIv,$defIv,$spaIv,$spdIv,$speIv,$nature,$ability,$username,$gender,$ball);
            if(!$stmt->execute()){ $stmt->close(); throw new RuntimeException('That Pokémon could not be restored.'); }
            $stmt->close();

            $stmt=$db->prepare('UPDATE pguide SET amount=amount+1 WHERE name=?');
            if($stmt){$stmt->bind_param('s',$name);$stmt->execute();$stmt->close();}
            $db->commit();
            pv_recalculate_trainer_progress($db,$uid,true);
            $success=['name'=>$name,'id'=>$pokemonId,'gender'=>$gender,'nature'=>$nature,'ability'=>$ability,'fossil'=>$fossilLabel];
        } catch (Throwable $e) {
            $db->rollback();
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'The Fossil Lab could not complete the restoration.';
            if (!($e instanceof RuntimeException)) pv_log('Fossil restoration failed: ' . $e->getMessage());
        }
    }
}

$stmt=$db->prepare('SELECT * FROM items WHERE uid=? LIMIT 1');
$stmt->bind_param('i',$uid);$stmt->execute();$inventory=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();

pv_page_start('Fossil Lab','your_pokemon.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('your_pokemon.php'); ?>
<main class="pv-main-column"><section class="pv-page">
<div class="pv-page-head"><div><span class="pv-eyebrow">POKÉMON COLLECTION // RESTORATION LAB</span><h1>Fossil Lab</h1><p class="pv-subtle">Restore ancient Pokémon from fossils in your inventory. Each successful restoration creates a level 5 Pokémon with fresh IVs, nature, ability and form roll.</p></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('items.php'))?>">View Items</a></div>
<?php if($error!==''):?><div class="pv-alert pv-alert-error"><?=pv_h($error)?></div><?php endif;?>
<?php if($success):?><div class="pv-fossil-result"><img src="<?=pv_h(pv_pokemon_sprite((string)$success['name']))?>" alt="<?=pv_h((string)$success['name'])?>"><div><small>RESTORATION COMPLETE</small><h2><?=pv_h((string)$success['name'])?></h2><p><?=pv_h((string)$success['gender'])?> · <?=pv_h((string)$success['nature'])?><?=trim((string)$success['ability'])!==''?' · '.pv_h((string)$success['ability']):''?></p><a href="<?=pv_h(pv_url('pokedex.php?pid='.(int)$success['id']))?>">View Pokémon details →</a></div></div><?php endif;?>
<div class="pv-section-heading"><div><span>AVAILABLE RESTORATIONS</span><h2>Restore a fossil</h2></div><small>One fossil consumed per restoration</small></div>
<div class="pv-fossil-grid">
<?php foreach($fossilMap as $species=>[$column,$label]):$count=max(0,(int)($inventory[$column]??0));?>
<article class="pv-fossil-card <?= $count>0?'available':'locked' ?>">
<div class="pv-fossil-card-image"><img src="<?=pv_h(pv_pokemon_sprite($species))?>" alt="<?=pv_h($species)?>"></div>
<div><small><?=pv_h(strtoupper($label))?></small><h3><?=pv_h($species)?></h3><p><?=number_format($count)?> in inventory</p></div>
<form method="post" action="<?=pv_h(pv_url('fossil_lab.php'))?>"><?=pv_csrf_field()?><input type="hidden" name="species" value="<?=pv_h($species)?>"><button class="pv-button <?= $count>0?'':'pv-button-secondary' ?>" type="submit" <?=$count>0?'':'disabled'?>><?=$count>0?'Restore':'Unavailable'?></button></form>
</article>
<?php endforeach;?>
</div>
</section></main></div>
<?php pv_page_end(); ?>
