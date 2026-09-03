<?php
require_once __DIR__ . '/includes/ui.php';
pv_page_start('Support', '', pv_is_logged_in());
?>
<main class="pv-content-wrap pv-narrow"><section class="pv-panel pv-prose">
<span class="pv-eyebrow">PLAYER SUPPORT</span><h1>Need help?</h1>
<p>For account or gameplay issues, use the account tools below and include the page and action involved when describing a problem.</p>
<div class="pv-feature-grid">
<article class="pv-feature-card"><h3>Account access</h3><p>Use password recovery from the login page if you no longer know your password.</p></article>
<article class="pv-feature-card"><h3>Gameplay issue</h3><p>Include the page you were on, the action you took and what you expected to happen so support can understand the issue clearly.</p></article>
</div>
<div class="pv-actions"><a class="pv-btn secondary" href="<?= pv_h(pv_url('login.php')) ?>">Account Access</a><a class="pv-btn secondary" href="<?= pv_h(pv_url('about.php')) ?>">Game Overview</a></div>
</section></main><?php pv_page_end(); ?>
