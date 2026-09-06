<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/collection.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();
$db=pv_db();$uid=(int)$_SESSION['myid'];

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($_POST['release_pokemon'])){
    try{
        pv_require_csrf();$pid=max(0,(int)($_POST['pokemon_id']??0));$confirm=trim((string)($_POST['confirm']??''));
        if(!hash_equals('RELEASE',strtoupper($confirm)))throw new RuntimeException('Type RELEASE to confirm this permanent action.');
        $name=pv_collection_release($db,$uid,$pid);$_SESSION['pv_collection_flash']=['type'=>'success','text'=>$name.' was released from your collection.'];
    }catch(RuntimeException $e){$_SESSION['pv_collection_flash']=['type'=>'error','text'=>$e->getMessage()];}
    pv_redirect('your_pokemon.php');
}

$flash=$_SESSION['pv_collection_flash']??null;unset($_SESSION['pv_collection_flash']);
$search=trim((string)($_GET['q']??''));$variant=trim((string)($_GET['variant']??''));$sort=trim((string)($_GET['sort']??'newest'));$page=max(1,(int)($_GET['page']??1));
$data=pv_collection_rows($db,$uid,$search,$variant,$sort,$page,24);$summary=pv_collection_summary($db,$uid);$team=array_flip(pv_collection_team_ids($db,$uid));
$releaseId=max(0,(int)($_GET['release']??0));$releasePokemon=$releaseId?pv_collection_pokemon($db,$uid,$releaseId):null;

function pv_collection_query(array $changes=[]): string{ $base=['q'=>(string)($_GET['q']??''),'variant'=>(string)($_GET['variant']??''),'sort'=>(string)($_GET['sort']??'newest'),'page'=>(int)($_GET['page']??1)];$q=array_merge($base,$changes);return http_build_query(array_filter($q,static fn($v)=>$v!==''&&$v!==0&&$v!==null)); }

pv_page_start('Your Pokémon','your_pokemon.php',true);
?>
<div class="pv-game-layout"><?php pv_game_side_menu('your_pokemon.php'); ?><main class="pv-main-column"><section class="pv-page pv-collection-page">
<div class="pv-page-head"><div><span class="pv-eyebrow">COLLECTION ARCHIVE</span><h1>Your Pokémon</h1><p class="pv-subtle">Search, view and manage every Pokémon in your collection.</p></div><div class="pv-actions"><a class="pv-button" href="<?=pv_h(pv_url('change_team.php'))?>">Change Team</a><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('pokedex.php'))?>">Open Pokédex</a></div></div>
<?php if(is_array($flash)):?><div class="pv-alert pv-alert-<?=pv_h((string)$flash['type'])?>"><?=pv_h((string)$flash['text'])?></div><?php endif;?>
<div class="pv-stat-grid pv-stat-grid-wide"><div><span>Pokémon owned</span><strong><?=number_format($summary['owned'])?></strong></div><div><span>Unique Pokédex entries</span><strong><?=number_format($summary['unique_forms'])?></strong></div><div><span>Total experience</span><strong><?=number_format($summary['total_exp'])?></strong></div><div><span>Highest level</span><strong><?=number_format($summary['highest_level'])?></strong></div></div>

<form method="get" class="pv-collection-filter"><label><span>Search collection</span><input name="q" value="<?=pv_h($search)?>" placeholder="Pokémon name"></label><label><span>Form</span><select name="variant"><option value="">All forms</option><?php foreach(['Normal','Shiny','Dark','Metallic','Mystic','Shadow'] as $v):?><option <?=$variant===$v?'selected':''?>><?=pv_h($v)?></option><?php endforeach;?></select></label><label><span>Sort</span><select name="sort"><?php foreach(['newest'=>'Newest','oldest'=>'Oldest','name'=>'Name','level'=>'Level','exp'=>'Experience'] as $k=>$label):?><option value="<?=pv_h($k)?>" <?=$sort===$k?'selected':''?>><?=pv_h($label)?></option><?php endforeach;?></select></label><button type="submit">Apply</button><?php if($search!==''||$variant!==''||$sort!=='newest'):?><a href="<?=pv_h(pv_url('your_pokemon.php'))?>">Clear</a><?php endif;?></form>

<?php if(!$data['rows']):?><div class="pv-empty-state"><span class="pv-brand-mark"><img src="<?=pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG'))?>" alt=""></span><div><h2>No Pokémon found</h2><p>Adjust the filters or explore world maps to expand your collection.</p><a class="pv-button" href="<?=pv_h(pv_url('map_select.php'))?>">Explore World Maps</a></div></div><?php else:?>
<div class="pv-collection-grid">
<?php foreach($data['rows'] as $p):$display=pv_collection_display_name($p);$sprite=pv_pokemon_sprite($display);$isTeam=isset($team[(int)$p['id']]);$variantName='Normal';foreach(['Shiny','Dark','Metallic','Mystic','Shadow'] as $vx){if(str_starts_with((string)$p['name'],$vx.' ')){$variantName=$vx;break;}}?>
<article class="pv-collection-card <?=$isTeam?'is-team':''?>">
<div class="pv-collection-card-art"><img src="<?=pv_h($sprite)?>" alt="<?=pv_h($display)?>"><?php if($isTeam):?><span>ACTIVE TEAM</span><?php endif;?></div>
<div class="pv-collection-card-body"><small>#<?=str_pad((string)(int)$p['pid'],4,'0',STR_PAD_LEFT)?> · <?=pv_h($variantName)?></small><h3><?=pv_h($display)?></h3><div class="pv-collection-types"><span><?=pv_h((string)$p['t1'])?></span><?php if(trim((string)$p['t2'])!==''):?><span><?=pv_h((string)$p['t2'])?></span><?php endif;?></div><dl><div><dt>Level</dt><dd><?=number_format((int)$p['lvl'])?></dd></div><div><dt>EXP</dt><dd><?=number_format((int)$p['exp'])?></dd></div><div><dt>Nature</dt><dd><?=pv_h((string)($p['nature']?:'—'))?></dd></div><div><dt>Ability</dt><dd><?=pv_h((string)($p['ability']?:'—'))?></dd></div></dl></div>
<div class="pv-collection-card-actions"><a href="<?=pv_h(pv_url('pokedex.php?pid='.(int)$p['id']))?>">Details</a><a href="<?=pv_h(pv_url('change_attacks.php?pid='.(int)$p['id']))?>">Moves</a><a href="<?=pv_h(pv_url('evolve.php?pid='.(int)$p['id']))?>">Evolve</a><?php if(!$isTeam):?><a class="danger" href="<?=pv_h(pv_url('your_pokemon.php?release='.(int)$p['id']))?>">Release</a><?php endif;?></div>
</article>
<?php endforeach;?>
</div>
<div class="pv-pagination"><span>Page <?=number_format($data['page'])?> of <?=number_format($data['pages'])?> · <?=number_format($data['total'])?> Pokémon</span><div><?php if($data['page']>1):?><a href="<?=pv_h(pv_url('your_pokemon.php?'.pv_collection_query(['page'=>$data['page']-1])))?>">← Previous</a><?php endif;?><?php if($data['page']<$data['pages']):?><a href="<?=pv_h(pv_url('your_pokemon.php?'.pv_collection_query(['page'=>$data['page']+1])))?>">Next →</a><?php endif;?></div></div>
<?php endif;?>

<?php if($releasePokemon):$rd=pv_collection_display_name($releasePokemon);?><section class="pv-danger-zone"><div><span class="pv-eyebrow">PERMANENT ACTION</span><h2>Release <?=pv_h($rd)?>?</h2><p>This removes the Pokémon from your collection permanently. It cannot be undone.</p></div><form method="post"><?=pv_csrf_field()?><input type="hidden" name="release_pokemon" value="1"><input type="hidden" name="pokemon_id" value="<?=(int)$releasePokemon['id']?>"><label><span>Type RELEASE to confirm</span><input name="confirm" autocomplete="off" required></label><div class="pv-actions"><button class="pv-button-danger" type="submit">Release Pokémon</button><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('your_pokemon.php'))?>">Cancel</a></div></form></section><?php endif;?>
</section></main></div>
<?php pv_page_end(); ?>
