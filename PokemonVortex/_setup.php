<?php
declare(strict_types=1);
define('PV_DISABLE_OUTPUT_FILTER', true);

$remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}

$config = require __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/schema.php';

$status = null;
$error = null;
$details = [];
$actionDone = null;
if (!headers_sent()) header('X-Robots-Tag: noindex, nofollow');

function pv_setup_connect(array $dbConfig, bool $withDatabase): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $host = (string)($dbConfig['host'] ?? '127.0.0.1');
    $user = (string)($dbConfig['user'] ?? 'root');
    $password = (string)($dbConfig['pass'] ?? '');
    $database = $withDatabase ? (string)($dbConfig['name'] ?? 'pokemon_vortex') : '';
    $port = (int)($dbConfig['port'] ?? 3306);
    $charset = (string)($dbConfig['charset'] ?? 'utf8mb4');

    $conn = @new mysqli($host, $user, $password, $database, $port);
    if ($conn->connect_errno) {
        $hint = $password === ''
            ? 'The package supports a blank database password, but this MySQL/MariaDB account did not accept one. If your local database uses a password, set db.pass in config/app.php to that credential and retry.'
            : 'Could not connect using the database credential configured in config/app.php. Check that MySQL/MariaDB is running and that the configured host, user and password are correct.';
        throw new RuntimeException($hint);
    }

    $conn->set_charset($charset);
    return $conn;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!class_exists('mysqli')) {
        $error = 'The PHP mysqli extension is not available.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        $dbConfig = $config['db'];

        try {
            if ($action === 'upgrade') {
                if (!hash_equals('UPGRADE', strtoupper($confirm))) {
                    throw new RuntimeException('Type UPGRADE to confirm the safe database repair.');
                }

                $conn = pv_setup_connect($dbConfig, true);
                try {
                    $changes = pv_apply_schema_migrations($conn);
                    $status = 'Database upgrade completed successfully.';
                    $actionDone = 'upgrade';
                    $credentialMode = ((string)($dbConfig['pass'] ?? '')) === '' ? 'blank local database password' : 'configured local database password';
                    $details = $changes !== []
                        ? array_merge(['Existing trainer progress was preserved.', 'Connected using the ' . $credentialMode . '; database-service credentials were not changed.'], $changes)
                        : ['Existing trainer progress was preserved.', 'Connected using the ' . $credentialMode . '; database-service credentials were not changed.', 'The database already matches the current gameplay schema.'];
                } finally {
                    $conn->close();
                }
            } elseif ($action === 'rebuild') {
                if (!hash_equals('INSTALL', strtoupper($confirm))) {
                    throw new RuntimeException('Type INSTALL to confirm the full database rebuild.');
                }

                $conn = pv_setup_connect($dbConfig, false);
                try {
                    $sqlFile = dirname(__DIR__) . '/database/pokemon_vortex_full.sql';
                    if (!is_file($sqlFile)) {
                        throw new RuntimeException('Database installer file not found. Keep the database folder next to public_html.');
                    }
                    $sql = file_get_contents($sqlFile);
                    if ($sql === false) throw new RuntimeException('Could not read the database installer.');

                    // Database service credentials are deployment configuration, not game schema.
                    // The packaged SQL deliberately does not CREATE/ALTER/DROP MySQL users or passwords.

                    $dbName = str_replace('`', '``', (string)$dbConfig['name']);
                    if (!$conn->multi_query("DROP DATABASE IF EXISTS `{$dbName}`;\n" . $sql)) {
                        throw new RuntimeException('Database import could not start: ' . $conn->error);
                    }
                    do {
                        if ($result = $conn->store_result()) $result->free();
                        if (!$conn->more_results()) break;
                    } while ($conn->next_result());
                    if ($conn->errno) throw new RuntimeException('Database import stopped: ' . $conn->error);

                    if (!$conn->select_db((string)$dbConfig['name'])) {
                        throw new RuntimeException('The imported game database could not be selected.');
                    }
                    $changes = pv_apply_schema_migrations($conn);

                    $status = 'Fresh installation completed successfully.';
                    $actionDone = 'rebuild';
                    $credentialMode = ((string)($dbConfig['pass'] ?? '')) === '' ? 'blank local database password' : 'configured local database password';
                    $details = [
                        'Game schema created and repaired to the current runtime version.',
                        'Pokémon, ability, attack and progression data imported.',
                        'Trading, clans, items, maps and account compatibility fields verified.',
                        'Connected using the ' . $credentialMode . '.',
                        'MySQL/MariaDB user accounts and passwords were left unchanged by the game installer.',
                        'Account registration is ready.'
                    ];
                    if ($changes !== []) $details[] = count($changes) . ' compatibility migration(s) were applied after import.';
                } finally {
                    $conn->close();
                }
            } else {
                throw new RuntimeException('Choose either Upgrade / Repair or Fresh Rebuild.');
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$base = htmlspecialchars((string)$config['base_path'], ENT_QUOTES, 'UTF-8');
$assetVersion = rawurlencode((string)($config['asset_version'] ?? '9.0.0'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#07111f">
<title>Pokémon Vortex Local Setup</title>
<link rel="stylesheet" href="<?=$base?>/assets/css/vortex-modern.css?v=<?=$assetVersion?>">
<style>
.pv-setup-choice{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:18px}.pv-setup-box{padding:18px;border:1px solid rgba(91,194,236,.18);background:linear-gradient(145deg,#fff,#f3faff)}.pv-setup-box h2{margin:0 0 8px;font-size:16px}.pv-setup-box p{min-height:62px;margin:0 0 14px;color:#647f92;font-size:12px;line-height:1.55}.pv-setup-safe{border-top:2px solid rgba(77,230,166,.45)}.pv-setup-danger{border-top:2px solid rgba(236,175,74,.45)}.pv-setup-box button{width:100%}.pv-setup-details{max-height:220px;overflow:auto;margin-top:10px;padding-right:5px}.pv-setup-details span{display:block;padding:4px 0;color:#688094;font-size:10px}@media(max-width:760px){.pv-setup-choice{grid-template-columns:1fr}.pv-setup-box p{min-height:0}}
</style>
</head>
<body class="pv-modern-page">
<div class="pv-auth-wrap">
<main class="pv-auth" style="grid-template-columns:.9fr 1.1fr;max-width:1050px">
<section class="pv-auth-side">
<span class="pv-eyebrow">LOCAL GAME SETUP</span>
<h1>Prepare the<br><span style="color:#2a75bb">Battle Arena.</span></h1>
<p class="pv-subtle">This maintenance screen is restricted to the local machine and is never linked from the player-facing game.</p>
<img src="<?=$base?>/html/static/images/frontv3.png" alt="Pokémon Vortex">
</section>
<section class="pv-auth-panel">
<div class="pv-brand"><span class="pv-brand-mark"><img src="<?=pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG'))?>" alt=""></span><span class="pv-brand-copy">LOCAL SETUP<small>PRIVATE MAINTENANCE</small></span></div>
<br>
<h1>Database maintenance</h1>
<p>Use the safe upgrade when moving to a newer build. A fresh rebuild is only for a new installation or when you intentionally want to erase all trainer progress.</p>

<?php if ($error): ?>
<div class="pv-flash error"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div>
<?php endif; ?>

<?php if ($status): ?>
<div class="pv-flash success">
<strong><?=htmlspecialchars($status, ENT_QUOTES, 'UTF-8')?></strong>
<div class="pv-setup-details">
<?php foreach ($details as $d): ?><span>✓ <?=htmlspecialchars($d, ENT_QUOTES, 'UTF-8')?></span><?php endforeach; ?>
</div>
</div>
<?php if ($actionDone === 'rebuild'): ?>
<a class="pv-btn" href="<?=$base?>/signup.php">Create First Trainer</a>
<?php else: ?>
<a class="pv-btn" href="<?=$base?>/dashboard.php">Return to Game</a>
<?php endif; ?>
<?php else: ?>
<div class="pv-setup-choice">
<form method="post" class="pv-setup-box pv-setup-safe">
<input type="hidden" name="action" value="upgrade">
<h2>Upgrade / Repair</h2>
<p>Adds missing compatibility fields and indexes without deleting trainers, Pokémon, inventory or progression.</p>
<div class="pv-field"><label>Confirmation</label><input name="confirm" autocomplete="off" placeholder="Type UPGRADE" required></div>
<button type="submit">Upgrade Existing Database</button>
</form>
<form method="post" class="pv-setup-box pv-setup-danger">
<input type="hidden" name="action" value="rebuild">
<h2>Fresh Rebuild</h2>
<p>Recreates the complete database from the supplied game data. Existing accounts and all game progress will be permanently removed.</p>
<div class="pv-field"><label>Confirmation</label><input name="confirm" autocomplete="off" placeholder="Type INSTALL" required></div>
<button type="submit">Build Fresh Database</button>
</form>
</div>
<?php endif; ?>
<p class="pv-auth-links"><a href="<?=$base?>/index.php">← Back to game</a></p>
</section>
</main>
</div>
</body>
</html>
