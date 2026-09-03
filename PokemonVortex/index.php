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
<main>
<section class="pv-hero">
  <div class="pv-hero-copy">
    <span class="pv-eyebrow">Online Pokémon Adventure</span>
    <h1>Enter the<br><span>Pokémon Vortex.</span></h1>
    <p>Explore themed regions, discover wild Pokémon, assemble a six-Pokémon battle team and grow your collection across battles, events, trading and trainer progression.</p>
    <div class="pv-actions">
      <?php if (pv_is_logged_in()): ?>
        <a class="pv-btn" href="<?= pv_h(pv_url('dashboard.php')) ?>">Continue Adventure</a>
        <a class="pv-btn secondary" href="<?= pv_h(pv_url('map_select.php')) ?>">Explore World</a>
      <?php else: ?>
        <a class="pv-btn" href="<?= pv_h(pv_url('signup.php')) ?>">Create Trainer</a>
        <a class="pv-btn secondary" href="<?= pv_h(pv_url('login.php')) ?>">Trainer Login</a>
      <?php endif; ?>
    </div>
    <div class="pv-hero-tags"><span>Wild Encounters</span><span>Trainer Battles</span><span>Trading</span><span>Clans</span><span>Pokédex</span><span>Persistent Progress</span></div>
  </div>

  <aside class="pv-world-status" aria-label="Game network status">
    <div class="pv-status-label">Pokémon Vortex // Game Status</div>
    <div class="pv-status-online"><?= $ready ? 'Online' : 'Maintenance' ?></div>
    <div class="pv-status-copy"><?= $ready ? 'The game is online and ready to play.' : 'The game is temporarily unavailable. Please check back shortly.' ?></div>
    <div class="pv-status-grid">
      <div class="pv-status-row"><span>Trainers Online</span><strong><?= $ready ? number_format($online) : '—' ?></strong></div>
      <div class="pv-status-row"><span>Registered Trainers</span><strong><?= $ready ? number_format($trainers) : '—' ?></strong></div>
      <div class="pv-status-row"><span>Pokémon Forms</span><strong><?= $ready ? number_format($species) : '4,700+' ?></strong></div>
      <div class="pv-status-row"><span>Battle Moves</span><strong><?= $ready ? number_format($moves) : '400+' ?></strong></div>
    </div>
  </aside>
</section>

<section class="pv-grid cols-4" aria-label="Game highlights">
  <article class="pv-card"><div class="pv-kpi"><?= $ready ? number_format($species) : '4,700+' ?></div><div class="pv-kpi-label">Pokémon forms</div></article>
  <article class="pv-card"><div class="pv-kpi"><?= $ready ? number_format($moves) : '400+' ?></div><div class="pv-kpi-label">Battle moves</div></article>
  <article class="pv-card"><div class="pv-kpi">6</div><div class="pv-kpi-label">Active team slots</div></article>
  <article class="pv-card"><div class="pv-kpi <?= $ready ? 'pv-online' : 'pv-muted-kpi' ?>"><?= $ready ? 'LIVE' : '—' ?></div><div class="pv-kpi-label">Community</div></article>
</section>

<section class="pv-grid cols-3">
  <article class="pv-card pv-feature"><div class="pv-icon">MAP</div><h3>Explore the world</h3><p>Search grasslands, caves, electric zones, volcanic areas, icy routes and ghost regions for wild encounters.</p></article>
  <article class="pv-card pv-feature"><div class="pv-icon">VS</div><h3>Build a battle team</h3><p>Train a six-Pokémon roster, customize attacks and challenge gyms, special trainers and player teams.</p></article>
  <article class="pv-card pv-feature"><div class="pv-icon">DEX</div><h3>Complete your collection</h3><p>Hunt Normal, Shiny, Dark, Metallic, Mystic and Shadow forms while tracking progress through the Pokédex.</p></article>
</section>

<section class="pv-home-banner">
  <div><span class="pv-eyebrow">Community</span><h2>Trade. Connect. Compete.</h2><p>Manage trade offers, message other trainers, join clans and keep building a collection that persists with your account.</p></div>
  <a class="pv-btn secondary" href="<?= pv_h(pv_url(pv_is_logged_in() ? 'community.php' : 'signup.php')) ?>"><?= pv_is_logged_in() ? 'Open Community' : 'Join the Adventure' ?></a>
</section>
</main>
<?php pv_page_end(); ?>
