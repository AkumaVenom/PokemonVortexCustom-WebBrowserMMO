<?php
require_once __DIR__ . '/includes/bootstrap.php';
pv_require_login();
// The historical embedded stream depended on an external channel that is no longer part of the game runtime.
// Keep old bookmarks functional by routing players into the modern Trainer Network instead.
pv_redirect('community.php', 301);
