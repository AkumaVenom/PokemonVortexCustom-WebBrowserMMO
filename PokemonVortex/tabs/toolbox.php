<?php
include(__DIR__ . '/../kick.php');

if ((int)($_SESSION['access'] ?? 0) === 9) {
    include(__DIR__ . '/../pv_connect_to_db.php');

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $notifyAction = trim((string)($_GET['notify'] ?? $_POST['notify'] ?? ''));
    $uid = (int)($_SESSION['myid'] ?? 0);

    if ($notifyAction === 'show' && $method === 'GET' && $uid > 0) {
        if ((string)($_SESSION['message_preferences'][0] ?? '0') === '0') {
            $stmt = pv_db()->prepare('SELECT sid,suser,subject FROM message_notify WHERE id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $sid = max(0, (int)($row['sid'] ?? 0));
                    echo 'You have just received a new message from <a href="' . pv_h(pv_url('members.php?uid=' . $sid)) . '">' . pv_h((string)($row['suser'] ?? 'Trainer')) . '</a>. Subject: <a href="' . pv_h(pv_url('messages.php')) . '">' . pv_h((string)($row['subject'] ?? 'Message')) . '</a>.';
                } else {
                    echo '1';
                }
            } else {
                echo '1';
            }
        } else {
            echo '1';
        }
    } elseif ($notifyAction === 'hide' && $method === 'POST' && $uid > 0) {
        pv_require_csrf();
        $stmt = pv_db()->prepare('DELETE FROM message_notify WHERE id=?');
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();
        }
        echo '1';
    }
}

include(__DIR__ . '/../pv_disconnect_from_db.php');
?>
