<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
pv_require_login();
$action = strtolower(trim((string)($_GET['action'] ?? '')));
if ($action === 'edit' || $action === 'password' || isset($_GET['update'])) {
    pv_redirect('your_account.php');
}
pv_redirect('options.php');
