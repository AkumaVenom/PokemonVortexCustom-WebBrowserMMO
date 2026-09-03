<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
pv_require_login();
if (!headers_sent()) pv_redirect('put_up_for_trade.php');
echo '<p>Pokémon listings are created from the secure listing screen.</p>';
