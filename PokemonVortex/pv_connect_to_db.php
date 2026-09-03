<?php
require_once __DIR__ . '/includes/bootstrap.php';
try {
    $mysql_connection = pv_db();
} catch (Throwable $e) {
    if (!headers_sent()) http_response_code(503);
    echo '<div class="pv-system-error"><strong>Game services are temporarily unavailable.</strong><br>Please try again shortly.</div>';
    return;
}

if (!empty($_SESSION['myid']) && pv_table_exists('members')) {
    $uid = (int)$_SESSION['myid'];
    $ban = mysql_query("SELECT banned FROM members WHERE id = {$uid} LIMIT 1");
    $banned = mysql_fetch_array($ban);
    if ($banned && (string)($banned['banned'] ?? '0') === '1') {
        $_SESSION = [];
        mysql_query("DELETE FROM online WHERE id = {$uid}");
        mysql_query("DELETE FROM mapusers WHERE id = {$uid}");
        pv_redirect('login.php?action=Banned');
    }

    $now = time();
    if (!isset($_SESSION['updatetime']) || (int)$_SESSION['updatetime'] < $now - 240) {
        $_SESSION['updatetime'] = $now;
        @mysql_query("UPDATE online SET time = {$now} WHERE id = {$uid}");
    }
}
