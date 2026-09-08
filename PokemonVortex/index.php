<?php
require_once __DIR__ . '/includes/ui.php';
$ready = false; $species = 0; $trainers = 0; $moves = 0; $online = 0;
try {
    $db = pv_db();
    $ready = pv_table_exists('pguide') && pv_table_exists('members');
    if ($ready) {
        if ($r=$db->query("SELECT COUNT(*) c FROM pguide")) $species=(int)($r->fetch_assoc()['c']??0);
        if ($r=$db->query("SELECT COUNT(*) c FROM members")) $trainers=(int)($r->fetch_assoc()['c']??0);
        if ($r=$db->query("SELECT COUNT(*) c FROM attacks")) $moves=(int)($r->fetch_assoc()['c']??0);
        if (pv_table_exists('online') && ($r=$db->query('SELECT COUNT(*) c FROM online WHERE time >= '.(time()-900)))) $online=(int)($r->fetch_assoc()['c']??0);
    }
} catch (Throwable $e) { $ready=false; }
pv_page_start('Home', 'index.php', pv_is_logged_in());
?>
<main class="nxt-home">
<section class="pv-hero nxt-home-hero">
  <div class="pv-hero-copy">
    <span class="pv-eyebrow">YOUR POKÉMON ADVENTURE · PLAY IN YOUR BROWSER</span>
    <h1>Pokemon<br><span>Vortex <em>NXT</em></span></h1>
    <p>One trainer. A world of possibilities. Explore Vortex, Kanto, Johto and Hoenn, find your favourite Pokémon, and take your team all the way to the top.</p>
    <div class="pv-actions">
      <?php if (pv_is_logged_in()): ?>
        <a class="pv-btn" href="<?= pv_h(pv_url('dashboard.php')) ?>">Continue adventure <span aria-hidden="true">→</span></a>
        <a class="pv-btn secondary" href="<?= pv_h(pv_url('map_select.php')) ?>">Explore the world</a>
      <?php else: ?>
        <a class="pv-btn" href="<?= pv_h(pv_url('signup.php')) ?>">Choose your starter <span aria-hidden="true">→</span></a>
        <a class="pv-btn secondary" href="<?= pv_h(pv_url('login.php')) ?>">Trainer login</a>
      <?php endif; ?>
    </div>
    <div class="pv-hero-tags"><span>Wild encounters</span><span>Live battles</span><span>Trading</span><span>Ranked rivals</span></div>
  </div>
  <div class="nxt-home-cast" aria-hidden="true">
    <img class="nxt-hero-charizard" src="<?=pv_h(pv_pokemon_image('Charizard'))?>" alt="Charizard" width="288" height="288" loading="eager" decoding="async">
    <?php pv_nxt_sprite_lineup(['Bulbasaur','Charmander','Squirtle','Pikachu','Eevee']); ?>
  </div>
</section>

<aside class="pv-world-status nxt-network-status" aria-label="Game status">
  <div><div class="pv-status-label">POKEMON VORTEX NXT</div>
    <div class="pv-status-online"><?= $ready ? 'Ready for adventure' : 'Under maintenance' ?></div>
    <div class="pv-status-copy"><?= $ready ? 'Your next chapter starts here.' : 'Please check back shortly.' ?></div></div>
  <div class="pv-status-grid">
    <div class="pv-status-row"><span>Trainers online</span><strong><?= $ready ? number_format($online) : '—' ?></strong></div>
    <div class="pv-status-row"><span>Registered trainers</span><strong><?= $ready ? number_format($trainers) : '—' ?></strong></div>
    <div class="pv-status-row"><span>Pokémon forms</span><strong><?= $ready ? number_format($species) : '—' ?></strong></div>
    <div class="pv-status-row"><span>Battle moves</span><strong><?= $ready ? number_format($moves) : '—' ?></strong></div>
  </div>
</aside>

<section class="nxt-region-cards" aria-label="Choose a world">
<?php foreach ([
  ['vortex','Vortex World','25 areas to discover.','images/maps/v3/map1.png','Eevee'],
  ['kanto','Kanto','Your classic adventure.','images/worlds/kanto/Kanto_Overworld.jpg','Pikachu'],
  ['johto','Johto','Ancient towers and new discoveries.','images/worlds/johto/JohtoWorldMap.png','Cyndaquil'],
  ['hoenn','Hoenn','A whole region to explore.','images/worlds/hoenn/Hoenn_Overworld.jpg','Mudkip']
] as [$world,$label,$copy,$mapArt,$partner]): ?>
  <a class="nxt-region-card" href="<?=pv_h(pv_url('map_select.php?world='.$world))?>">
    <img src="<?=pv_h(pv_static_file($mapArt))?>" alt="<?=pv_h($label)?> map preview" width="480" height="154" loading="lazy" decoding="async">
    <div><span class="pv-eyebrow">EXPLORE</span><h2><?=pv_h($label)?></h2><p><?=pv_h($copy)?></p></div>
  </a>
<?php endforeach; ?>
</section>

<section class="pv-grid cols-3 nxt-home-features" aria-label="Choose your adventure">
<?php foreach ([
  ['battle_select.php','Lucario','Your team. Your strategy.','Build a six-Pokémon team. Take on gyms, challenge trainers and find your place in the arena.','Find a battle'],
  ['your_pokemon.php','Gengar','A collection like no other.','Discover Normal, Shiny, Dark, Metallic, Mystic and Shadow forms. Make every encounter count.','Your collection'],
  ['rival_hub.php','Dragonite','Rise through the ranks.','Meet your rivals, challenge the ranked ladder and turn your next victory into a new personal best.','Meet your rivals']
] as [$href,$partner,$title,$copy,$action]): ?>
  <article class="pv-card nxt-feature-card">
    <div class="nxt-feature-copy"><h3><?=pv_h($title)?></h3><p><?=pv_h($copy)?></p><div class="pv-actions"><a class="pv-btn secondary" href="<?=pv_h(pv_url($href))?>"><?=pv_h($action)?></a></div></div>
    <div class="nxt-feature-art" aria-hidden="true"><img src="<?=pv_h(pv_pokemon_image($partner))?>" alt="" width="112" height="112" loading="lazy" decoding="async"></div>
  </article>
<?php endforeach; ?>
</section>

<section class="pv-home-banner"><div><span class="pv-eyebrow">BETTER TOGETHER</span><h2>Find your people. Trade your favourites.</h2><p>Meet trainers, share Pokémon and make your mark with a clan of your own.</p></div><a class="pv-btn" href="<?= pv_h(pv_url(pv_is_logged_in() ? 'community.php' : 'signup.php')) ?>"><?= pv_is_logged_in() ? 'Open community' : 'Join the adventure' ?></a></section>
</main>
<?php pv_page_end(); ?>
