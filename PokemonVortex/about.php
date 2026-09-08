<?php
require_once __DIR__ . '/includes/ui.php';
pv_page_start('About', 'about.php', pv_is_logged_in());
?>
<main class="pv-content-wrap">
  <section class="pv-panel pv-prose">
    <span class="pv-eyebrow">ABOUT THE BATTLE ARENA</span>
    <h1>A Pokémon adventure built for the browser.</h1>
    <p>Pokemon Vortex NXT is a collection-focused browser RPG where trainers explore maps, encounter Pokémon, build teams and progress through battles, events and community features.</p>
    <div class="pv-feature-grid">
      <article class="pv-feature-card"><h3>Explore</h3><p>Travel across themed regions and search for wild Pokémon encounters.</p></article>
      <article class="pv-feature-card"><h3>Battle</h3><p>Build a six-Pokémon team and challenge gyms, trainers and special battle content.</p></article>
      <article class="pv-feature-card"><h3>Collect</h3><p>Track Normal, Shiny, Dark, Metallic, Mystic and Shadow variants in your collection.</p></article>
      <article class="pv-feature-card"><h3>Community</h3><p>Trade Pokémon, send messages, browse trainers and take part in clans.</p></article>
    </div>
    <h2>Start your journey</h2>
    <p>Create a trainer, choose a starter and head straight into the world maps. Your collection, battle progress, items and profile are saved to your account as you play.</p>
    <div class="pv-actions"><a class="pv-btn" href="<?= pv_h(pv_url(pv_is_logged_in() ? 'dashboard.php' : 'signup.php')) ?>"><?= pv_is_logged_in() ? 'Go to Dashboard' : 'Create Trainer' ?></a></div>
  </section>
</main>
<?php pv_page_end(); ?>
