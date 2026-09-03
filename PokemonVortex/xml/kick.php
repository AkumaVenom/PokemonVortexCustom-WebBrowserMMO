<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (empty($_SESSION['myid'])) { http_response_code(401); exit; }
