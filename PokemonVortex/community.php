<?php
require_once __DIR__ . '/includes/ui.php';
pv_require_login();
$db = pv_db();
$online = $trainers = $trades = $clans = 0;
try {
    if ($r=$db->query('SELECT COUNT(*) c FROM members')) $trainers=(int)($r->fetch_assoc()['c']??0);
    if (pv_table_exists('online') && ($r=$db->query('SELECT COUNT(*) c FROM online WHERE time >= '.(time()-900)))) $online=(int)($r->fetch_assoc()['c']??0);
    if (pv_table_exists('upfortrade') && ($r=$db->query('SELECT COUNT(*) c FROM upfortrade'))) $trades=(int)($r->fetch_assoc()['c']??0);
    if (pv_table_exists('clans') && ($r=$db->query('SELECT COUNT(*) c FROM clans'))) $clans=(int)($r->fetch_assoc()['c']??0);
} catch (Throwable $e) { pv_log('Community counters unavailable: '.$e->getMessage()); }
pv_page_start('Community', 'community.php', true);
?>
<main class="pv-game-layout"><?php pv_game_side_menu('community.php'); ?><section class="pv-main-column">
<section class="pv-panel pv-section-hero">
  <div><span class="pv-eyebrow">TRAINER HUB</span><h1>Community</h1><p class="pv-muted">Meet other trainers, exchange Pokémon, manage messages and build a clan around your adventures.</p></div>
  <div class="pv-mini-status"><i></i><span>TRAINERS ONLINE</span></div>
</section>

<section class="pv-stat-grid pv-stat-grid-wide">
  <div><span>Trainers online</span><strong><?= number_format($online) ?></strong></div>
  <div><span>Registered trainers</span><strong><?= number_format($trainers) ?></strong></div>
  <div><span>Trade listings</span><strong><?= number_format($trades) ?></strong></div>
  <div><span>Active clans</span><strong><?= number_format($clans) ?></strong></div>
</section>

<section class="pv-network-grid">
<a class="pv-network-card" href="<?= pv_h(pv_url('members.php')) ?>"><span class="pv-network-code pv-image-icon"><img src="<?=pv_h(pv_static_file('images/sprites/2whole.gif','images/sprites/1.gif'))?>" alt="Trainer"></span><div class="pv-network-copy"><h3>Trainer Directory</h3><p>Browse trainers, inspect profiles and find people currently online.</p></div><b>→</b></a>
<a class="pv-network-card" href="<?= pv_h(pv_url('messages.php')) ?>"><span class="pv-network-code pv-image-icon"><img src="<?=pv_h(pv_static_file('images/pokemon/Chatot.gif'))?>" alt="Messages"></span><div class="pv-network-copy"><h3>Messages</h3><p>Read private messages and keep in touch with other trainers.</p></div><b>→</b></a>
<a class="pv-network-card" href="<?= pv_h(pv_url('trade.php')) ?>"><span class="pv-network-code pv-image-icon"><img src="<?=pv_h(pv_static_file('images/items/Great Ball.png'))?>" alt="Trade"></span><div class="pv-network-copy"><h3>Trade Center</h3><p>Search listings, offer Pokémon and manage your active trades.</p></div><b>→</b></a>
<a class="pv-network-card" href="<?= pv_h(pv_url('clans.php')) ?>"><span class="pv-network-code pv-image-icon"><img src="<?=pv_h(pv_static_file('images/pokemon/Lucario.gif'))?>" alt="Clans"></span><div class="pv-network-copy"><h3>Clans</h3><p>Create or join a clan and build a shared identity with other trainers.</p></div><b>→</b></a>
</section>

<section class="pv-panel pv-prose pv-compact-panel"><span class="pv-eyebrow">PLAYER SAFETY</span><h2>Protect your trainer account</h2><p>Keep your password private. Use the built-in trade and messaging systems for player interactions, and never share sign-in credentials in messages or profile text.</p></section>
</section></main>
<?php pv_page_end(); ?>
