<?php
require_once __DIR__ . '/includes/ui.php';
http_response_code(404);
pv_page_start('Page Not Found', '', pv_is_logged_in());
?>
<main class="pv-content-wrap pv-narrow">
<section class="pv-panel pv-prose pv-error-page">
  <span class="pv-eyebrow">LOST ROUTE // 404</span>
  <h1>That route could not be found.</h1>
  <p>The requested page does not exist or has moved to another part of the game.</p>
  <div class="pv-actions">
    <a class="pv-btn" href="<?= pv_h(pv_url(pv_is_logged_in() ? 'dashboard.php' : 'index.php')) ?>"><?= pv_is_logged_in() ? 'Return to Dashboard' : 'Return Home' ?></a>
    <?php if (pv_is_logged_in()): ?><a class="pv-btn secondary" href="<?= pv_h(pv_url('map_select.php')) ?>">Explore World</a><?php endif; ?>
  </div>
</section>
</main>
<?php pv_page_end(); ?>
