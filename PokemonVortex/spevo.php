<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
pv_require_login();

$pid = pv_request_int('pid', 0, 0);
if ($pid < 1 && isset($_POST['pid'])) $pid = max(0, (int)$_POST['pid']);
if ($pid < 1) $pid = max(0, (int)($_SESSION['evid'] ?? $_SESSION['ev'] ?? 0));

pv_redirect($pid > 0 ? 'evolve.php?pid=' . $pid : 'your_pokemon.php', 302);
