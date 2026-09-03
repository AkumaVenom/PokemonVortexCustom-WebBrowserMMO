<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
pv_require_login();
pv_redirect('change_attacks.php');
