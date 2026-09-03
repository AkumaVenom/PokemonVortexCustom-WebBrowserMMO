<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/pokedex.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();

$db = pv_db();
$uid = (int)$_SESSION['myid'];
$instanceId = max(0, (int)($_GET['pid'] ?? 0));
$speciesId = max(0, (int)($_GET['dex'] ?? 0));
$detailRequested = $instanceId > 0 || $speciesId > 0;
$owned = $instanceId > 0 ? pv_collection_pokemon($db, $uid, $instanceId) : null;
$detailError = '';

if ($instanceId > 0) {
    if ($owned) {
        $speciesId = max(0, (int)$owned['pid']);
    } else {
        $detailError = 'That Pokémon is unavailable or is not part of your collection.';
    }
}

$guide = ($detailError === '' && $speciesId > 0) ? pv_pokedex_guide($db, $speciesId) : null;
if ($detailRequested && $detailError === '' && !$guide) {
    $detailError = 'That Pokédex entry could not be found.';
}

$abilities = [];
$moveProfile = [];
$variantFamily = [];
$specimens = ['count' => 0, 'rows' => []];
$adjacent = ['previous' => null, 'next' => null];
$evolutions = ['previous' => [], 'forward' => []];
$variantLabel = 'Normal';
$baseSpecies = '';

if ($guide) {
    $name = (string)$guide['name'];
    [, $baseSpecies] = pv_evolution_variant_parts($name);
    $variantLabel = pv_pokedex_variant_label($name);
    $abilities = pv_pokedex_abilities($db, $name);
    $moveProfile = pv_pokedex_move_profile($db, $guide);
    $variantFamily = pv_pokedex_variant_family($db, $uid, $name);
    $specimens = pv_pokedex_owned_specimens($db, $uid, $speciesId);
    $adjacent = pv_pokedex_adjacent($db, $speciesId);
    $evolutions = pv_pokedex_evolution_routes($db, $name);
}

pv_page_start('Pokédex', 'pokedex.php', true);
?>
<div class="pv-game-layout"><?php pv_game_side_menu('pokedex.php'); ?><main class="pv-main-column"><section class="pv-page pv-dex-page">
<?php if ($detailRequested): ?>
    <?php if ($guide):
        $catalogueName = (string)$guide['name'];
        $display = $owned ? pv_collection_display_name($owned) : $catalogueName;
        $sprite = pv_pokemon_sprite($display);
        $ownedCount = (int)($specimens['count'] ?? 0);
        $type1 = trim((string)($guide['type1'] ?? ''));
        $type2 = trim((string)($guide['type2'] ?? ''));
    ?>
    <div class="pv-page-head pv-dex-detail-head">
        <div>
            <span class="pv-eyebrow">POKÉDEX // ENTRY #<?=str_pad((string)$speciesId, 4, '0', STR_PAD_LEFT)?></span>
            <h1><?=pv_h($display)?></h1>
            <p class="pv-subtle">View this Pokémon's types, abilities, default moves, evolutions, forms and the copies you own.</p>
        </div>
        <div class="pv-dex-head-actions">
            <a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('pokedex.php'))?>">Back to Pokédex</a>
        </div>
    </div>

    <nav class="pv-dex-entry-nav" aria-label="Pokédex entry navigation">
        <?php if ($adjacent['previous']): ?>
            <a href="<?=pv_h(pv_url('pokedex.php?dex='.(int)$adjacent['previous']['id']))?>"><small>← PREVIOUS</small><strong>#<?=str_pad((string)(int)$adjacent['previous']['id'],4,'0',STR_PAD_LEFT)?> <?=pv_h((string)$adjacent['previous']['name'])?></strong></a>
        <?php else: ?><span></span><?php endif; ?>
        <div><small>POKÉDEX NUMBER</small><strong>#<?=str_pad((string)$speciesId,4,'0',STR_PAD_LEFT)?></strong></div>
        <?php if ($adjacent['next']): ?>
            <a class="is-next" href="<?=pv_h(pv_url('pokedex.php?dex='.(int)$adjacent['next']['id']))?>"><small>NEXT →</small><strong>#<?=str_pad((string)(int)$adjacent['next']['id'],4,'0',STR_PAD_LEFT)?> <?=pv_h((string)$adjacent['next']['name'])?></strong></a>
        <?php else: ?><span></span><?php endif; ?>
    </nav>

    <div class="pv-dex-detail-grid pv-dex-detail-grid-production">
        <section class="pv-dex-hero pv-dex-hero-production">
            <div class="pv-dex-scan"><img src="<?=pv_h($sprite)?>" alt="<?=pv_h($display)?>"><i></i><i></i></div>
            <div class="pv-dex-identity">
                <div class="pv-dex-status-line"><span class="pv-dex-variant-badge"><?=pv_h($variantLabel)?></span><span class="pv-dex-owned-badge <?=$ownedCount>0?'is-owned':''?>"><?=$ownedCount>0?'REGISTERED · '.number_format($ownedCount).' OWNED':'NOT YET OWNED'?></span></div>
                <span class="pv-eyebrow">SPECIES</span>
                <h2><?=pv_h($catalogueName)?></h2>
                <?php if ($baseSpecies !== $catalogueName): ?><p class="pv-dex-base-species">Base species: <strong><?=pv_h($baseSpecies)?></strong></p><?php endif; ?>
                <div class="pv-collection-types pv-dex-types">
                    <?php if ($type1 !== ''): ?><span data-type="<?=pv_h(strtolower($type1))?>"><?=pv_h($type1)?></span><?php endif; ?>
                    <?php if ($type2 !== ''): ?><span data-type="<?=pv_h(strtolower($type2))?>"><?=pv_h($type2)?></span><?php endif; ?>
                </div>
                <div class="pv-dex-telemetry">
                    <div><span>WORLD REGISTERED</span><strong><?=number_format(max(0,(int)$guide['amount']))?></strong></div>
                    <div><span>YOUR COPIES</span><strong><?=number_format($ownedCount)?></strong></div>
                    <div><span>VARIANT</span><strong><?=pv_h($variantLabel)?></strong></div>
                    <div><span>POKÉDEX ID</span><strong>#<?=str_pad((string)$speciesId,4,'0',STR_PAD_LEFT)?></strong></div>
                </div>
            </div>
        </section>

        <section class="pv-card pv-dex-ability-card">
            <span class="pv-eyebrow">ABILITIES</span>
            <?php if ($abilities): ?>
                <div class="pv-dex-ability-list"><?php foreach ($abilities as $index => $ability): ?><div><small>ABILITY <?=($index+1)?></small><strong><?=pv_h($ability)?></strong></div><?php endforeach; ?></div>
            <?php else: ?>
                <p class="pv-subtle">No ability information is available for this Pokédex entry.</p>
            <?php endif; ?>
        </section>

        <section class="pv-card pv-dex-move-card">
            <div class="pv-dex-section-head"><div><span class="pv-eyebrow">DEFAULT MOVES</span><h3>Default moves</h3></div><small>4 SLOTS</small></div>
            <?php if ($moveProfile): ?>
                <div class="pv-dex-move-profile">
                <?php foreach ($moveProfile as $move): ?>
                    <div class="pv-dex-move-row">
                        <div class="pv-dex-move-index">0<?=number_format((int)$move['slot'])?></div>
                        <div class="pv-dex-move-name"><strong><?=pv_h((string)$move['attack'])?></strong><span><?=pv_h((string)($move['type'] ?: 'Move'))?><?=trim((string)$move['category'])!==''?' · '.pv_h((string)$move['category']):''?></span></div>
                        <div class="pv-dex-move-stat"><small>POWER</small><strong><?=!empty($move['has_metadata']) && (int)$move['power']>0?number_format((int)$move['power']):'—'?></strong></div>
                        <div class="pv-dex-move-stat"><small>ACC</small><strong><?=!empty($move['has_metadata']) && (int)$move['accuracy']>0?number_format((int)$move['accuracy']).'%':'—'?></strong></div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?><p class="pv-subtle">No default moves are listed for this Pokédex entry.</p><?php endif; ?>
        </section>

        <section class="pv-card pv-dex-evolution-card">
            <div class="pv-dex-section-head"><div><span class="pv-eyebrow">EVOLUTION PATHS</span><h3>Evolution chain</h3></div><small><?=number_format(count($evolutions['previous'])+count($evolutions['forward']))?> ROUTES</small></div>
            <?php if (!$evolutions['previous'] && !$evolutions['forward']): ?>
                <div class="pv-dex-final-stage"><strong>No registered evolution route</strong><span>This Pokémon has no further evolution listed.</span></div>
            <?php else: ?>
                <div class="pv-dex-evolution-network">
                    <?php foreach ($evolutions['previous'] as $route): $entry=$route['entry']; ?>
                        <a class="pv-dex-evolution-node is-previous" href="<?=pv_h(pv_url('pokedex.php?dex='.(int)$entry['id']))?>"><img src="<?=pv_h(pv_pokemon_sprite((string)$entry['name']))?>" alt=""><div><small>EVOLVES FROM</small><strong><?=pv_h((string)$entry['name'])?></strong><span><?=pv_h((string)$route['requirement'])?></span></div></a>
                    <?php endforeach; ?>
                    <div class="pv-dex-evolution-current"><img src="<?=pv_h(pv_pokemon_sprite($catalogueName))?>" alt=""><div><small>CURRENT ENTRY</small><strong><?=pv_h($catalogueName)?></strong></div></div>
                    <?php foreach ($evolutions['forward'] as $route): $entry=$route['entry']; ?>
                        <a class="pv-dex-evolution-node is-forward" href="<?=pv_h(pv_url('pokedex.php?dex='.(int)$entry['id']))?>"><img src="<?=pv_h(pv_pokemon_sprite((string)$entry['name']))?>" alt=""><div><small>EVOLVES TO</small><strong><?=pv_h((string)$entry['name'])?></strong><span><?=pv_h((string)$route['requirement'])?></span></div></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="pv-card pv-dex-variant-card">
            <div class="pv-dex-section-head"><div><span class="pv-eyebrow">VORTEX VARIANT FAMILY</span><h3><?=pv_h($baseSpecies)?> forms</h3></div><small><?=number_format(count($variantFamily))?> ENTRIES</small></div>
            <div class="pv-dex-variant-grid">
                <?php foreach ($variantFamily as $variant): $active=(int)$variant['id']===$speciesId; ?>
                <a class="pv-dex-variant-tile <?=$active?'is-active':''?> <?=(int)$variant['owned_count']>0?'is-owned':''?>" href="<?=pv_h(pv_url('pokedex.php?dex='.(int)$variant['id']))?>">
                    <img src="<?=pv_h(pv_pokemon_sprite((string)$variant['name']))?>" alt="<?=pv_h((string)$variant['name'])?>">
                    <div><small>#<?=str_pad((string)(int)$variant['id'],4,'0',STR_PAD_LEFT)?> · <?=pv_h(pv_pokedex_variant_label((string)$variant['name']))?></small><strong><?=pv_h((string)$variant['name'])?></strong><span><?=(int)$variant['owned_count']>0?'Owned × '.number_format((int)$variant['owned_count']):'Not owned'?></span></div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($owned): ?>
        <section class="pv-card pv-dex-specimen pv-dex-selected-specimen">
            <div class="pv-dex-section-head"><div><span class="pv-eyebrow">SELECTED POKÉMON // #<?=(int)$owned['id']?></span><h3><?=pv_h(pv_collection_display_name($owned))?></h3></div><small>TRAINER OWNED</small></div>
            <div class="pv-stat-grid pv-dex-specimen-stats"><div><span>Level</span><strong><?=number_format((int)$owned['lvl'])?></strong></div><div><span>EXP</span><strong><?=number_format((int)$owned['exp'])?></strong></div><div><span>Nature</span><strong><?=pv_h((string)($owned['nature']?:'—'))?></strong></div><div><span>Ability</span><strong><?=pv_h((string)($owned['ability']?:'—'))?></strong></div><div><span>Happiness</span><strong><?=number_format((int)($owned['happiness']??0))?></strong></div><div><span>Gender</span><strong><?=pv_h((string)($owned['gender']?:'—'))?></strong></div></div>
            <div class="pv-dex-ivs"><?php foreach(['HP'=>'hp_iv','ATK'=>'attack_iv','DEF'=>'defense_iv','SP.ATK'=>'spatk_iv','SP.DEF'=>'spdef_iv','SPEED'=>'speed_iv'] as $label=>$field):?><div><span><?=$label?></span><strong><?=number_format((int)$owned[$field])?></strong><i><b style="width:<?=max(0,min(100,(int)round(((int)$owned[$field]/31)*100)))?>%"></b></i></div><?php endforeach;?></div>
            <div class="pv-actions"><a class="pv-button" href="<?=pv_h(pv_url('change_attacks.php?pid='.(int)$owned['id']))?>">Move Lab</a><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('evolve.php?pid='.(int)$owned['id']))?>">Evolution Lab</a><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('your_pokemon.php'))?>">Your Pokémon</a></div>
        </section>
        <?php endif; ?>

        <section class="pv-card pv-dex-owned-card">
            <div class="pv-dex-section-head"><div><span class="pv-eyebrow">YOUR COLLECTION</span><h3>Pokémon you own</h3></div><small><?=number_format($ownedCount)?> OWNED</small></div>
            <?php if (!$specimens['rows']): ?>
                <div class="pv-dex-empty-owned"><strong>Not yet owned</strong><span>Catch, trade for or otherwise obtain this exact form to add it to your collection.</span></div>
            <?php else: ?>
                <div class="pv-dex-specimen-list">
                    <?php foreach ($specimens['rows'] as $specimen): ?>
                    <a href="<?=pv_h(pv_url('pokedex.php?pid='.(int)$specimen['id']))?>" class="<?=$owned && (int)$owned['id']===(int)$specimen['id']?'is-active':''?>"><img src="<?=pv_h(pv_pokemon_sprite(pv_collection_display_name($specimen)))?>" alt=""><div><small>POKÉMON #<?=(int)$specimen['id']?></small><strong><?=pv_h(pv_collection_display_name($specimen))?></strong><span>Lv. <?=number_format((int)$specimen['lvl'])?> · <?=pv_h((string)($specimen['nature']?:'Nature unknown'))?> · <?=pv_h((string)($specimen['ability']?:'Ability unknown'))?></span></div><b>Inspect →</b></a>
                    <?php endforeach; ?>
                </div>
                <?php if ($ownedCount > count($specimens['rows'])): ?><p class="pv-subtle pv-dex-owned-note">Showing the highest-level <?=number_format(count($specimens['rows']))?> of <?=number_format($ownedCount)?> owned copies.</p><?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
    <?php else: ?>
        <div class="pv-page-head"><div><span class="pv-eyebrow">POKÉDEX</span><h1>Pokédex entry unavailable</h1><p class="pv-subtle">That Pokédex entry is unavailable. Return to the Pokédex and choose another Pokémon.</p></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('pokedex.php'))?>">Back to Pokédex</a></div>
        <section class="pv-card pv-dex-error-state"><strong>Entry could not be resolved</strong><p><?=pv_h($detailError !== '' ? $detailError : 'The requested Pokédex entry is unavailable.')?></p></section>
    <?php endif; ?>
<?php else:
$q=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$per=48;$where='1=1';$types='';$args=[];if($q!==''){$where='g.name LIKE ?';$types='s';$args[]='%'.$q.'%';}
$stmt=$db->prepare("SELECT COUNT(*) c FROM pguide g WHERE {$where}");if($types!==''&&$stmt)$stmt->bind_param($types,...$args);$total=0;if($stmt){$stmt->execute();$total=(int)($stmt->get_result()->fetch_assoc()['c']??0);$stmt->close();}$pages=max(1,(int)ceil($total/$per));$page=min($page,$pages);$off=($page-1)*$per;
$sql="SELECT g.id,g.name,g.type1,g.type2,g.amount,COUNT(p.id) owned_count FROM pguide g LEFT JOIN pokemon p ON p.pid=g.id AND CAST(p.owner AS UNSIGNED)=? WHERE {$where} GROUP BY g.id,g.name,g.type1,g.type2,g.amount ORDER BY g.id ASC LIMIT ? OFFSET ?";$stmt=$db->prepare($sql);$entries=[];if($stmt){$types2='i'.$types.'ii';$args2=[$uid,...$args,$per,$off];$stmt->bind_param($types2,...$args2);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$entries[]=$row;$stmt->close();}
?>
<div class="pv-page-head"><div><span class="pv-eyebrow">POKÉDEX</span><h1>Pokédex</h1><p class="pv-subtle">Browse Pokémon and Vortex variants, check abilities and evolutions, and see which forms you already own.</p></div><div class="pv-dex-counter"><small>ENTRIES</small><strong><?=number_format($total)?></strong></div></div>
<form method="get" class="pv-dex-search"><input name="q" value="<?=pv_h($q)?>" placeholder="Search Pokémon or Vortex variant"><button type="submit">Search</button><?php if($q!==''):?><a href="<?=pv_h(pv_url('pokedex.php'))?>">Clear</a><?php endif;?></form>
<?php if (!$entries): ?><section class="pv-card pv-dex-error-state"><strong>No matching Pokédex entries</strong><p>Try another species or variant name.</p></section><?php else: ?>
<div class="pv-dex-grid"><?php foreach($entries as $g):?><a class="pv-dex-card <?=(int)$g['owned_count']>0?'is-owned':''?>" href="<?=pv_h(pv_url('pokedex.php?dex='.(int)$g['id']))?>"><div><img src="<?=pv_h(pv_pokemon_sprite((string)$g['name']))?>" alt="<?=pv_h((string)$g['name'])?>"><?php if((int)$g['owned_count']>0):?><span>OWNED × <?=number_format((int)$g['owned_count'])?></span><?php endif;?></div><small>#<?=str_pad((string)(int)$g['id'],4,'0',STR_PAD_LEFT)?> · <?=pv_h(pv_pokedex_variant_label((string)$g['name']))?></small><strong><?=pv_h((string)$g['name'])?></strong><p><?=pv_h((string)$g['type1'])?><?=trim((string)$g['type2'])!==''?' / '.pv_h((string)$g['type2']):''?></p></a><?php endforeach;?></div>
<?php endif; ?>
<div class="pv-pagination"><span>Page <?=number_format($page)?> of <?=number_format($pages)?></span><div><?php if($page>1):?><a href="<?=pv_h(pv_url('pokedex.php?'.http_build_query(['q'=>$q,'page'=>$page-1])))?>">← Previous</a><?php endif;?><?php if($page<$pages):?><a href="<?=pv_h(pv_url('pokedex.php?'.http_build_query(['q'=>$q,'page'=>$page+1])))?>">Next →</a><?php endif;?></div></div>
<?php endif; ?>
</section></main></div>
<?php pv_page_end(); ?>
