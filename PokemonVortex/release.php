<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
pv_require_login();
$pid=max(0,(int)($_GET['pid'] ?? $_POST['pid'] ?? $_POST['byeinfo'] ?? $_REQUEST['pid'] ?? 0));
pv_redirect($pid>0?'your_pokemon.php?release='.$pid:'your_pokemon.php');
