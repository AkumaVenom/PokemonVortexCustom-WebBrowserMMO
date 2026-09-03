<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
$dex = isset($_GET['pokemon']) ? trim((string)$_GET['pokemon']) : '';
if ($pid > 0) pv_redirect('pokedex.php?pid=' . $pid, 301);
if ($dex !== '') pv_redirect('pokedex.php?q=' . rawurlencode($dex), 301);
pv_redirect('pokedex.php', 301);
