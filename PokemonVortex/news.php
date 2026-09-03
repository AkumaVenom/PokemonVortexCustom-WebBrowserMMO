<?php
require_once __DIR__ . '/includes/ui.php';
pv_page_start('News', 'news.php', pv_is_logged_in());
?>
<main class="pv-content-wrap pv-narrow"><section class="pv-panel pv-prose">
<span class="pv-eyebrow">LATEST UPDATE</span><h1>Game News</h1>
<article class="pv-news-item"><time>Battle Arena Update</time><h2>Adventure Expansion</h2><p>Exploration, Pokémon management, battles, trading and community features now come together in one polished adventure, with your progress, collection and trainer journey carrying across every part of the game.</p></article>
<article class="pv-news-item"><time>Collection Update</time><h2>Thousands of Pokémon forms available</h2><p>Discover thousands of Normal, Shiny, Dark, Metallic, Mystic and Shadow Pokémon forms, each supported by abilities and a wide range of battle moves.</p></article>
</section></main><?php pv_page_end(); ?>
