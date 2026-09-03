<?php
require_once __DIR__ . '/includes/bootstrap.php';
pv_require_login();
pv_redirect('change_team.php', 301);
