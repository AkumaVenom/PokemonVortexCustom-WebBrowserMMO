<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (pv_is_logged_in()) pv_redirect('dashboard.php');
$messages = [];
if (isset($_GET['reg'])) $messages[] = ['success','Trainer account created. You can log in now.'];
if (isset($_GET['reset'])) $messages[] = ['success','Your password has been updated. You can log in now.'];
if (isset($_GET['action']) && $_GET['action']==='Logout') $messages[] = ['success','You have been logged out safely.'];
if (isset($_GET['action']) && $_GET['action']==='Banned') $messages[] = ['error','This account is not permitted to log in.'];
if (isset($_GET['action']) && $_GET['action']==='Attempts') $messages[] = ['error','Too many failed attempts. Try again after the temporary lock expires.'];
if (isset($_GET['error'])) $messages[] = ['error','The username or password is incorrect.'];
if (isset($_GET['expired'])) $messages[] = ['error','Your session expired. Please log in again.'];
$dbReady = false;
try { $dbReady = pv_table_exists('members'); } catch(Throwable $e) {}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#07111f"><title>Log In · Pokémon Vortex</title><link rel="stylesheet" href="<?= pv_h(pv_asset('css/vortex-modern.css')) ?>?v=<?= pv_asset_version() ?>"><script defer src="<?= pv_h(pv_asset('js/vortex-modern.js')) ?>?v=<?= pv_asset_version() ?>"></script></head>
<body class="pv-modern-page pv-auth-page"><a class="pv-skip-link" href="#auth-panel">Skip to login</a><div class="pv-atmosphere" aria-hidden="true"><i></i><i></i><i></i><i></i></div><div class="pv-auth-wrap"><main class="pv-auth">
<section class="pv-auth-side"><a class="pv-brand" href="<?= pv_h(pv_url('index.php')) ?>"><span class="pv-brand-mark">PV</span><span class="pv-brand-copy">POKÉMON VORTEX<small>BATTLE ARENA</small></span></a><h1 style="margin-top:44px">Welcome back,<br><span style="color:#76ddff">Trainer.</span></h1><p class="pv-subtle">Your collection, team, map progress, items and battle history are waiting for you.</p><img src="<?= pv_h(pv_static('images/frontv3.png')) ?>" alt="Battle Arena"></section>
<section class="pv-auth-panel" id="auth-panel"><span class="pv-eyebrow">Trainer Access</span><h1>Log in</h1><p>Enter your trainer credentials to continue.</p>
<?php foreach($messages as [$type,$text]): ?><div class="pv-flash <?= pv_h($type) ?>"><?= pv_h($text) ?></div><?php endforeach; ?>
<?php if (!$dbReady): ?><div class="pv-flash error">Account services are temporarily unavailable. Please try again shortly.</div><?php endif; ?>
<form method="post" action="<?= pv_h(pv_url('checklogin.php')) ?>" autocomplete="on">
<?= pv_csrf_field() ?>
<div class="pv-field"><label for="myusername">Username</label><input name="myusername" id="myusername" maxlength="30" autocomplete="username" required autofocus></div>
<div class="pv-field"><label for="mypassword">Password</label><input name="mypassword" id="mypassword" type="password" autocomplete="current-password" required></div>
<button type="submit" <?= !$dbReady?'disabled':'' ?>>Enter Battle Arena <span aria-hidden="true">→</span></button>
</form><div class="pv-auth-links"><a href="<?= pv_h(pv_url('signup.php')) ?>">Create an account</a><a href="<?= pv_h(pv_url('forgot_password.php')) ?>">Forgot password</a><a href="<?= pv_h(pv_url('index.php')) ?>">Back to home</a></div>
</section></main></div></body></html>
