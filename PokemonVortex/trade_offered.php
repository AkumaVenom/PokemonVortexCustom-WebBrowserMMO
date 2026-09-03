<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
pv_require_login();
if (!headers_sent()) pv_redirect('trade.php?view=sent');
echo '<p>Outgoing offers are managed in the secure Trade Center.</p>';
