<?php
declare(strict_types=1);

/**
 * Legacy AJAX Members tab compatibility route.
 *
 * The reconstructed trainer network is served by /members.php.  This endpoint
 * intentionally performs no legacy SQL or state mutation; it only translates
 * an old profile reference into the modern production route.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
pv_require_login();

$uid = filter_input(INPUT_GET, 'uid', FILTER_VALIDATE_INT);
if (is_int($uid) && $uid > 0) {
    pv_redirect('members.php?profile=' . $uid);
}

pv_redirect('members.php');
