<?php
require_once __DIR__ . '/includes/ui.php';
pv_require_login();
$db=pv_db(); $id=(int)$_SESSION['myid'];
$stmt=$db->prepare('SELECT username,email,money,battle,totalexp,registered,last_login,points,uniques,total_poke FROM members WHERE id=? LIMIT 1');
$stmt->bind_param('i',$id); $stmt->execute(); $m=$stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
$formatDate = static function($value): string {
    $raw=trim((string)$value); if($raw==='') return '—';
    if(ctype_digit($raw)) { $ts=(int)$raw; return $ts>0 ? date('j M Y, H:i',$ts).' UTC' : '—'; }
    $ts=strtotime($raw); return $ts ? date('j M Y, H:i',$ts).' UTC' : $raw;
};
pv_page_start('Your Account', 'your_account.php', true);
?>
<main class="pv-game-layout"><?php pv_game_side_menu('your_account.php'); ?><section class="pv-main-column">
<section class="pv-panel pv-section-hero">
  <div><span class="pv-eyebrow">TRAINER PROFILE</span><h1><?= pv_h($m['username'] ?? 'Trainer') ?></h1><p class="pv-muted">Manage your trainer profile, privacy preferences and account access.</p></div>
  <a class="pv-btn secondary" href="<?= pv_h(pv_url('options.php')) ?>">Open Options</a>
</section>
<section class="pv-stat-grid pv-stat-grid-wide">
  <div><span>PokéMoney</span><strong><?= number_format((int)($m['money'] ?? 0)) ?></strong></div>
  <div><span>Battles</span><strong><?= number_format((int)($m['battle'] ?? 0)) ?></strong></div>
  <div><span>Pokémon owned</span><strong><?= number_format((int)($m['total_poke'] ?? 0)) ?></strong></div>
  <div><span>Unique forms</span><strong><?= number_format((int)($m['uniques'] ?? 0)) ?></strong></div>
</section>
<section class="pv-account-grid">
  <article class="pv-panel pv-account-panel"><span class="pv-eyebrow">ACCOUNT DETAILS</span><dl class="pv-detail-list"><div><dt>Trainer name</dt><dd><?= pv_h($m['username'] ?? '—') ?></dd></div><div><dt>Email address</dt><dd><?= pv_h($m['email'] ?? '—') ?></dd></div><div><dt>Registered</dt><dd><?= pv_h($formatDate($m['registered'] ?? '')) ?></dd></div><div><dt>Last sign in</dt><dd><?= pv_h($formatDate($m['last_login'] ?? '')) ?></dd></div></dl></article>
  <article class="pv-panel pv-account-panel"><span class="pv-eyebrow">TRAINER PROGRESS</span><dl class="pv-detail-list"><div><dt>Total experience</dt><dd><?= number_format((int)($m['totalexp'] ?? 0)) ?></dd></div><div><dt>Trainer points</dt><dd><?= number_format((int)($m['points'] ?? 0)) ?></dd></div></dl><div class="pv-actions"><a class="pv-btn secondary" href="<?= pv_h(pv_url('your_pokemon.php')) ?>">Your Pokémon</a><a class="pv-btn secondary" href="<?= pv_h(pv_url('pokedex.php')) ?>">Pokédex</a></div></article>
</section>
<section class="pv-panel pv-compact-panel"><div class="pv-page-head"><div><span class="pv-eyebrow">ACCOUNT ACCESS</span><h2>Password & security</h2><p class="pv-muted">Use password recovery if you ever lose access to your trainer account. Never share your password with another player.</p></div><a class="pv-btn secondary" href="<?= pv_h(pv_url('forgot_password.php')) ?>">Password Recovery</a></div></section>
</section></main>
<?php pv_page_end(); ?>
