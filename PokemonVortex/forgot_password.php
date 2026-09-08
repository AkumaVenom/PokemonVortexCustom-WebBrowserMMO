<?php
require_once __DIR__ . '/includes/ui.php';
if (pv_is_logged_in()) pv_redirect('dashboard.php');
$dbReady=false; $message=''; $error=''; $mode='request'; $resetUser=''; $resetToken='';
try { $db=pv_db(); $dbReady=pv_table_exists('members') && pv_table_exists('password_resets'); } catch(Throwable $e) {}

if ($dbReady && isset($_GET['u'], $_GET['token'])) {
    $mode='reset'; $resetUser=trim((string)$_GET['u']); $resetToken=(string)$_GET['token'];
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $dbReady) {
    if (!pv_verify_csrf()) {
        $error='This form expired. Please try again.';
    } elseif (($_POST['action']??'') === 'request') {
        $identifier=trim((string)($_POST['identifier']??''));
        $stmt=$db->prepare('SELECT id,username,email FROM members WHERE username=? OR email=? LIMIT 1');
        $stmt->bind_param('ss',$identifier,$identifier); $stmt->execute(); $member=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($member && filter_var((string)$member['email'], FILTER_VALIDATE_EMAIL)) {
            $token=bin2hex(random_bytes(32));
            $hash=hash('sha256',$token);
            $expires=time()+3600;
            $uid=(int)$member['id'];
            $now=time();
            $tokenStored=false;
            $db->begin_transaction();
            try {
                $stmt=$db->prepare('DELETE FROM password_resets WHERE user_id=?');
                if(!$stmt) throw new RuntimeException('Could not prepare old reset-token cleanup.');
                $stmt->bind_param('i',$uid);
                if(!$stmt->execute()) throw new RuntimeException('Could not clear old reset tokens.');
                $stmt->close();

                $stmt=$db->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at,created_at) VALUES (?,?,?,?)');
                if(!$stmt) throw new RuntimeException('Could not prepare password reset token.');
                $stmt->bind_param('isii',$uid,$hash,$expires,$now);
                if(!$stmt->execute()) throw new RuntimeException('Could not save password reset token.');
                $stmt->close();
                $db->commit();
                $tokenStored=true;
            } catch(Throwable $e) {
                $db->rollback();
                pv_log('Password reset token creation failed for user id '.$uid.': '.$e->getMessage());
            }

            if($tokenStored){
                $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
                $host=(string)($_SERVER['HTTP_HOST']??'localhost');
                $link=$scheme.'://'.$host.pv_url('forgot_password.php').'?u='.rawurlencode((string)$member['username']).'&token='.rawurlencode($token);
                $subject='Pokemon Vortex NXT password reset';
                $body="A password reset was requested for your Pokemon Vortex NXT trainer account.\n\nReset your password: {$link}\n\nThis link expires in one hour. If you did not request this, you can ignore this email.";
                $from=(string)pv_config('mail.from','no-reply@localhost');
                $sent=@mail((string)$member['email'],$subject,$body,'From: '.$from."\r\nContent-Type: text/plain; charset=UTF-8");
                if(!$sent) pv_log('Password reset mail could not be sent for user id '.$uid.'. Configure PHP mail/sendmail to enable delivery.');
            }
        }
        $message='If a matching trainer account exists, password reset instructions have been sent to its email address.';
    } elseif (($_POST['action']??'') === 'reset') {
        $resetUser=trim((string)($_POST['username']??'')); $resetToken=(string)($_POST['token']??'');
        $password=(string)($_POST['password']??''); $password2=(string)($_POST['password2']??''); $mode='reset';
        if(strlen($password)<8) $error='Password must be at least 8 characters.';
        elseif($password!==$password2) $error='The passwords do not match.';
        else {
            $stmt=$db->prepare('SELECT m.id,m.username FROM members m JOIN password_resets r ON r.user_id=m.id WHERE m.username=? AND r.token_hash=? AND r.expires_at>=? LIMIT 1');
            $hash=hash('sha256',$resetToken); $now=time(); $stmt->bind_param('ssi',$resetUser,$hash,$now); $stmt->execute(); $member=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if(!$member) $error='This reset link is invalid or has expired.';
            else {
                $uid=(int)$member['id'];
                $newHash=pv_password_hash($password);
                $db->begin_transaction();
                try {
                    $stmt=$db->prepare('UPDATE members SET password=? WHERE id=?');
                    if(!$stmt) throw new RuntimeException('Could not prepare password update.');
                    $stmt->bind_param('si',$newHash,$uid);
                    if(!$stmt->execute() || $stmt->affected_rows < 0) throw new RuntimeException('Could not update account password.');
                    $stmt->close();

                    // Password reset tokens are single-use and are consumed in the same
                    // transaction as the password change so a partial reset cannot occur.
                    $stmt=$db->prepare('DELETE FROM password_resets WHERE user_id=? AND token_hash=?');
                    if(!$stmt) throw new RuntimeException('Could not prepare reset-token consumption.');
                    $stmt->bind_param('is',$uid,$hash);
                    if(!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Reset token was already consumed or expired.');
                    $stmt->close();

                    $db->commit();
                    pv_redirect('login.php?reset=1');
                } catch(Throwable $e) {
                    $db->rollback();
                    pv_log('Atomic password reset failed for user id '.$uid.': '.$e->getMessage());
                    $error='Your password could not be changed. Please request a new reset link and try again.';
                }
            }
        }
    }
}
pv_page_start('Password Recovery','',false);
?>
<main class="pv-content-wrap pv-narrow"><section class="pv-panel pv-prose">
<span class="pv-eyebrow">ACCOUNT RECOVERY</span><h1>Reset your password</h1>
<?php if($message): ?><div class="pv-flash success"><?= pv_h($message) ?></div><?php endif; ?>
<?php if($error): ?><div class="pv-flash error"><?= pv_h($error) ?></div><?php endif; ?>
<?php if(!$dbReady): ?><div class="pv-flash error">Account services are temporarily unavailable. Please try again shortly.</div>
<?php elseif($mode==='reset'): ?>
<p>Choose a new password for <strong><?= pv_h($resetUser) ?></strong>.</p>
<form method="post" class="pv-form-card"><?= pv_csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="username" value="<?= pv_h($resetUser) ?>"><input type="hidden" name="token" value="<?= pv_h($resetToken) ?>">
<div class="pv-field"><label>New password</label><input type="password" name="password" minlength="8" autocomplete="new-password" required></div>
<div class="pv-field"><label>Confirm password</label><input type="password" name="password2" minlength="8" autocomplete="new-password" required></div>
<button type="submit">Set New Password</button></form>
<?php else: ?>
<p>Enter your trainer username or account email. If it matches an account, you will receive a one-hour password reset link.</p>
<form method="post" class="pv-form-card"><?= pv_csrf_field() ?><input type="hidden" name="action" value="request"><div class="pv-field"><label>Username or email</label><input name="identifier" maxlength="190" autocomplete="username" required autofocus></div><button type="submit">Send Reset Link</button></form>
<?php endif; ?>
<div class="pv-actions"><a class="pv-btn secondary" href="<?= pv_h(pv_url('login.php')) ?>">Back to Login</a></div>
</section></main><?php pv_page_end(); ?>
