<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
pv_require_login();
$q = trim((string)($_GET['q'] ?? $_POST['pokemon_name'] ?? $_REQUEST['pokemon_name'] ?? ''));
if (mb_strlen($q) > 60) $q = mb_substr($q, 0, 60);
pv_redirect('trade.php?view=browse' . ($q !== '' ? '&q=' . rawurlencode($q) : ''));
