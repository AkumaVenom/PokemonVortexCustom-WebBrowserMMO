<?php
session_start();
$_SESSION['mapx'] = $_REQUEST['x'];
$_SESSION['mapy'] = $_REQUEST['y'];
$_SESSION['mapp'] = $_REQUEST['map'];
?>