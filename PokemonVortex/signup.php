<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (pv_is_logged_in()) pv_redirect('dashboard.php');
$starterNames=['Bulbasaur','Charmander','Squirtle','Pidgey','Chikorita','Cyndaquil','Totodile','Pichu','Treecko','Torchic','Mudkip','Poochyena','Turtwig','Chimchar','Piplup','Shinx','Snivy','Tepig','Oshawott','Lillipup','Chespin','Fennekin','Froakie','Bunnelby'];
$error=''; $dbReady=false;
try { $db=pv_db(); $dbReady=pv_table_exists('members')&&pv_table_exists('pguide'); } catch(Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST' && $dbReady) {
    if (!pv_verify_csrf()) $error='The form expired. Please try again.';
    $username=trim((string)($_POST['username']??'')); $email=trim((string)($_POST['email']??''));
    $password=(string)($_POST['password']??''); $password2=(string)($_POST['password2']??'');
    $starter=(string)($_POST['starter']??''); $trainer=(int)($_POST['trainer']??1); $terms=!empty($_POST['terms']);
    if (!$error && !preg_match('/^[A-Za-z0-9_-]{3,24}$/',$username)) $error='Username must be 3–24 characters using letters, numbers, dashes or underscores.';
    if (!$error && !filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Enter a valid email address.';
    if (!$error && strlen($password)<8) $error='Password must be at least 8 characters.';
    if (!$error && $password!==$password2) $error='The passwords do not match.';
    if (!$error && !in_array($starter,$starterNames,true)) $error='Choose one of the available starter Pokémon.';
    if (!$error && ($trainer<1 || $trainer>28)) $error='Choose a valid trainer sprite.';
    if (!$error && !$terms) $error='Please accept the Terms of Service to create the account.';
    // The local console uses this same reservation, closing signup/admin name races.
    $accountCreationLock = null;
    if (!$error) {
        try { $accountCreationLock = pv_admin_account_name_lock($db, $username); }
        catch (Throwable $e) { $error = 'This trainer name is being registered. Please retry shortly.'; }
    }
    try {
    if (!$error) {
        $stmt=$db->prepare('SELECT id FROM members WHERE username=? LIMIT 1'); $stmt->bind_param('s',$username); $stmt->execute(); $exists=$stmt->get_result()->num_rows>0; $stmt->close();
        if($exists) $error='That username is already in use.';
    }
    if (!$error) {
        $stmt=$db->prepare('SELECT id FROM members WHERE email=? LIMIT 1'); $stmt->bind_param('s',$email); $stmt->execute(); $exists=$stmt->get_result()->num_rows>0; $stmt->close();
        if($exists) $error='That email address is already linked to a trainer account.';
    }
    if (!$error) {
        $stmt=$db->prepare('SELECT * FROM pguide WHERE name=? LIMIT 1'); $stmt->bind_param('s',$starter); $stmt->execute(); $guide=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if(!$guide) $error='Starter data is temporarily unavailable. Please try again shortly.';
    }
    if (!$error) {
        $hash=pv_password_hash($password);
        $now=time();
        $ip=substr((string)($_SERVER['REMOTE_ADDR']??'local'),0,64);
        $eb='1';
        $number=(string)random_int(1,18);
        $secret=bin2hex(random_bytes(20));
        $last=(string)$now;

        $db->begin_transaction();
        try {
            $stmt=$db->prepare('INSERT INTO members (username,password,email,registered,llogin,last_login,ip,eb,number,secret_key,total_poke,sidequest) VALUES (?,?,?,?,?,?,?,?,?,?,1,1)');
            if(!$stmt) throw new RuntimeException('Could not prepare trainer account creation.');
            $stmt->bind_param('ssssisssss',$username,$hash,$email,$last,$now,$last,$ip,$eb,$number,$secret);
            if(!$stmt->execute()) throw new RuntimeException('Could not create trainer account: '.$stmt->error);
            $uid=(int)$db->insert_id;
            $stmt->close();

            $display='No'; $forum=''; $skype=''; $memonmap=1; $messonoff=0; $notify=0; $layout=2;
            $stmt=$db->prepare('INSERT INTO members_options (id,trainer,forum,skype,display,memonmap,messonoff,messnotifyonoff,layout) VALUES (?,?,?,?,?,?,?,?,?)');
            if(!$stmt) throw new RuntimeException('Could not prepare trainer options.');
            $stmt->bind_param('iisssiiii',$uid,$trainer,$forum,$skype,$display,$memonmap,$messonoff,$notify,$layout);
            if(!$stmt->execute()) throw new RuntimeException('Could not create trainer options: '.$stmt->error);
            $stmt->close();

            $gender=random_int(0,1)===0?'Male':'Female';
            $lvl=18; $exp=9000; $ball='Poke Ball';
            $pid=(int)$guide['id'];
            $a1=(string)($guide['a1']??''); $a2=(string)($guide['a2']??''); $a3=(string)($guide['a3']??''); $a4=(string)($guide['a4']??'');
            $t1=(string)($guide['type1']??''); $t2=(string)($guide['type2']??'');
            $stmt=$db->prepare('INSERT INTO pokemon (pid,name,a1,a2,a3,a4,lvl,exp,t1,t2,rowner,owner,ball,gender,ot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            if(!$stmt) throw new RuntimeException('Could not prepare starter Pokémon.');
            $stmt->bind_param('isssssiisssisss',$pid,$starter,$a1,$a2,$a3,$a4,$lvl,$exp,$t1,$t2,$username,$uid,$ball,$gender,$username);
            if(!$stmt->execute()) throw new RuntimeException('Could not create starter Pokémon: '.$stmt->error);
            $pokemonId=(int)$db->insert_id;
            $stmt->close();

            $ivs=array_map(static fn()=>random_int(1,31),range(1,6));
            $natures=['Hardy','Lonely','Brave','Adamant','Naughty','Bold','Docile','Relaxed','Impish','Lax','Timid','Hasty','Serious','Jolly','Naive','Modest','Mild','Quiet','Bashful','Rash','Calm','Gentle','Sassy','Careful','Quirky'];
            $nature=$natures[array_rand($natures)];
            $ability='';
            if (pv_table_exists('abilities')) {
                $stmt=$db->prepare('SELECT ability1,ability2,ability3 FROM abilities WHERE name=? LIMIT 1');
                if($stmt){
                    $stmt->bind_param('s',$starter); $stmt->execute(); $ar=$stmt->get_result()->fetch_assoc(); $stmt->close();
                    if($ar){ $choices=array_values(array_filter([$ar['ability1']??null,$ar['ability2']??null,$ar['ability3']??null],static fn($v)=>is_string($v)&&trim($v)!=='')); if($choices)$ability=(string)$choices[array_rand($choices)]; }
                }
            }
            $happiness=70;
            $stmt=$db->prepare('INSERT INTO pokemon_stats (id,hp_iv,attack_iv,defense_iv,spatk_iv,spdef_iv,speed_iv,nature,ability,ball,gender,ot,happiness) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            if(!$stmt) throw new RuntimeException('Could not prepare starter Pokémon stats.');
            $stmt->bind_param('iiiiiiisssssi',$pokemonId,$ivs[0],$ivs[1],$ivs[2],$ivs[3],$ivs[4],$ivs[5],$nature,$ability,$ball,$gender,$username,$happiness);
            if(!$stmt->execute()) throw new RuntimeException('Could not create starter Pokémon stats: '.$stmt->error);
            $stmt->close();

            $stmt=$db->prepare('UPDATE members SET s1=? WHERE id=?');
            if(!$stmt) throw new RuntimeException('Could not prepare starter team assignment.');
            $stmt->bind_param('ii',$pokemonId,$uid);
            if(!$stmt->execute() || $stmt->affected_rows!==1) throw new RuntimeException('Could not assign starter Pokémon to the active team.');
            $stmt->close();

            foreach([['badges','id'],['events','id'],['comments','userid'],['items','uid']] as [$table,$column]){
                if(!pv_table_exists($table)) throw new RuntimeException('Required account table is unavailable: '.$table);
                $sql='INSERT INTO `'.$table.'` (`'.$column.'`) VALUES (?)';
                $stmt=$db->prepare($sql);
                if(!$stmt) throw new RuntimeException('Could not prepare account defaults for '.$table.'.');
                $stmt->bind_param('i',$uid);
                if(!$stmt->execute()) throw new RuntimeException('Could not create account defaults for '.$table.': '.$stmt->error);
                $stmt->close();
            }

            $stmt=$db->prepare('UPDATE pguide SET amount=amount+1 WHERE id=?');
            if(!$stmt) throw new RuntimeException('Could not prepare Pokédex population update.');
            $stmt->bind_param('i',$pid);
            if(!$stmt->execute()) throw new RuntimeException('Could not update Pokédex population: '.$stmt->error);
            $stmt->close();

            $db->commit();
            $registrationComplete=true;
        } catch(Throwable $e) {
            try { $db->rollback(); } catch(Throwable $rollbackError) {}
            pv_log('Atomic account creation failed for '.$username.': '.$e->getMessage());
            $error='We could not create your trainer account right now. No partial account was saved; please try again shortly.';
        }
        if (!empty($registrationComplete)) {
            pv_server_event('AUTH','Trainer account created',['username'=>$username,'uid'=>$uid,'starter'=>$starter]);
            if ($accountCreationLock !== null) pv_admin_account_name_unlock($db, $accountCreationLock);
            pv_redirect('login.php?reg=1');
        }
    }
    } finally {
        if ($accountCreationLock !== null) pv_admin_account_name_unlock($db, $accountCreationLock);
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#2a75bb"><title>Create Trainer · Pokemon Vortex NXT</title><link rel="stylesheet" href="<?= pv_h(pv_asset('css/vortex-modern.css')) ?>?v=<?= pv_asset_version() ?>"><script defer src="<?= pv_h(pv_asset('js/vortex-modern.js')) ?>?v=<?= pv_asset_version() ?>"></script>
<style>.pv-starters{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:7px;margin:8px 0 16px}.pv-starter{position:relative}.pv-starter input{position:absolute;opacity:0}.pv-starter label{display:block;padding:8px 4px;text-align:center;border:1px solid rgba(90,180,239,.16);border-radius:10px;background:linear-gradient(145deg,#fff,#f3faff);cursor:pointer}.pv-starter label img{width:58px;height:58px;object-fit:contain;display:block;margin:auto}.pv-starter label span{display:block;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pv-starter input:checked+label{border-color:#2a75bb;background:linear-gradient(145deg,#fff8cf,#fff);box-shadow:0 0 0 3px rgba(42,117,187,.10)}@media(max-width:700px){.pv-starters{grid-template-columns:repeat(4,1fr)}}</style></head>
<body class="pv-modern-page pv-auth-page"><a class="pv-skip-link" href="#auth-panel">Skip to account creation</a><div class="pv-atmosphere" aria-hidden="true"><i></i><i></i><i></i><i></i></div><div class="pv-auth-wrap" style="padding:18px 0"><main class="pv-auth" style="width:min(1180px,calc(100% - 24px));grid-template-columns:.72fr 1.28fr">
<section class="pv-auth-side"><a class="pv-brand" href="<?= pv_h(pv_url('index.php')) ?>"><span class="pv-brand-mark"><img src="<?= pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG')) ?>" alt=""></span><?= pv_nxt_brand('YOUR ADVENTURE STARTS HERE') ?></a><h1 style="margin-top:38px">Begin your<br><span style="color:#2a75bb">adventure.</span></h1><p class="pv-subtle">Choose your trainer identity and starter. Your first Pokémon starts at level 18, ready to explore, train and battle by your side.</p><?php pv_nxt_sprite_lineup(['Bulbasaur','Charmander','Squirtle','Pikachu','Eevee'], 'nxt-auth-lineup'); ?></section>
<section class="pv-auth-panel" id="auth-panel" style="padding-top:32px;padding-bottom:32px"><span class="pv-eyebrow">Account Creation</span><h1>Create trainer</h1><p>Choose your details, then pick the Pokémon that will begin your adventure.</p>
<?php if($error): ?><div class="pv-flash error"><?= pv_h($error) ?></div><?php endif; ?>
<?php if(!$dbReady): ?><div class="pv-flash error">Account services are temporarily unavailable. Please try again shortly.</div><?php else: ?>
<form method="post" autocomplete="on"><?= pv_csrf_field() ?>
<div class="nxt-form-row"><div class="pv-field"><label for="nxt-username">Username</label><input id="nxt-username" name="username" maxlength="24" value="<?= pv_h($_POST['username']??'') ?>" autocomplete="username" required></div><div class="pv-field"><label for="nxt-email">Email</label><input id="nxt-email" name="email" type="email" maxlength="190" value="<?= pv_h($_POST['email']??'') ?>" autocomplete="email" required></div></div>
<div class="nxt-form-row"><div class="pv-field"><label for="nxt-password">Password</label><input id="nxt-password" name="password" type="password" minlength="8" autocomplete="new-password" required></div><div class="pv-field"><label for="nxt-password2">Confirm password</label><input id="nxt-password2" name="password2" type="password" minlength="8" autocomplete="new-password" required></div></div>
<div class="pv-trainer-picker"><div class="pv-field"><label for="trainer">Trainer appearance</label><select name="trainer" id="trainer" data-trainer-select data-sprite-base="<?= pv_h(pv_static('images/player/')) ?>" required><?php for($i=1;$i<=28;$i++): ?><option value="<?= $i ?>" <?= (int)($_POST['trainer']??1)===$i?'selected':'' ?>>Trainer <?= $i ?></option><?php endfor; ?></select><small class="pv-field-help">Choose the trainer sprite shown on maps and profile screens.</small></div><div class="pv-trainer-preview"><span>TRAINER ID</span><img data-trainer-preview src="<?= pv_h(pv_static('images/player/'.(int)($_POST['trainer']??1).'.gif')) ?>" alt="Selected trainer appearance"></div></div>
<fieldset class="nxt-starter-fieldset"><legend>Choose your starter Pokémon</legend><div class="pv-starters">
<?php foreach($starterNames as $idx=>$name): $checked=(($_POST['starter']??'Bulbasaur')===$name); ?><div class="pv-starter"><input type="radio" id="starter<?= $idx ?>" name="starter" value="<?= pv_h($name) ?>" <?= $checked?'checked':'' ?>><label for="starter<?= $idx ?>"><img src="<?= pv_h(pv_static_file('images/pokemon/'.$name.'.gif')) ?>" alt=""><span><?= pv_h($name) ?></span></label></div><?php endforeach; ?>
</div></fieldset><label style="display:flex;gap:8px;align-items:flex-start;margin:10px 0 16px"><input type="checkbox" name="terms" value="1" required style="margin-top:4px"><span>I agree to the <a href="<?= pv_h(pv_url('terms.php')) ?>" target="_blank" rel="noopener">Terms of Service</a> and <a href="<?= pv_h(pv_url('privacy.php')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.</span></label>
<button type="submit">Create Trainer Account <span aria-hidden="true">→</span></button></form><?php endif; ?>
<div class="pv-auth-links"><a href="<?= pv_h(pv_url('login.php')) ?>">Already have an account?</a><a href="<?= pv_h(pv_url('index.php')) ?>">Back home</a></div></section></main></div></body></html>
