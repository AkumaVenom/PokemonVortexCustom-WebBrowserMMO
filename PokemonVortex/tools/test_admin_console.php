<?php
/**
 * Integration regression suite. USE ONLY A DISPOSABLE COPY OF THE GAME DATABASE.
 * Applies schema repairs, creates/deletes CommandTest_* accounts, and temporarily
 * changes maintenance settings. Requires PHP mysqli, proc_open, and local TCP.
 *
 * PV_ADMIN_TEST_DATABASE=1 PV_TEST_DB_HOST=127.0.0.1 PV_TEST_DB_PORT=3306 \
 * PV_TEST_DB_NAME=pokemon_vortex_test PV_TEST_DB_USER=root PV_TEST_DB_PASSWORD=... \
 * php tools/test_admin_console.php --allow-disposable-db
 *
 * Environment credentials are never printed. No project config file is edited.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--allow-disposable-db', $argv, true) || getenv('PV_ADMIN_TEST_DATABASE') !== '1' || !getenv('PV_TEST_DB_NAME')) {
    fwrite(STDERR, "Refusing to run: provide --allow-disposable-db, PV_ADMIN_TEST_DATABASE=1 and explicit PV_TEST_DB_NAME for a disposable database. See this file's header.\n");
    exit(2);
}
$testDb = [
    'host'=>(string)(getenv('PV_TEST_DB_HOST') ?: '127.0.0.1'),
    'port'=>(int)(getenv('PV_TEST_DB_PORT') ?: '3306'),
    'name'=>(string)getenv('PV_TEST_DB_NAME'),
    'user'=>(string)(getenv('PV_TEST_DB_USER') ?: 'root'),
    'pass'=>(string)(getenv('PV_TEST_DB_PASSWORD') ?: ''), 'charset'=>'utf8mb4',
];
if (!in_array($testDb['host'], ['127.0.0.1','::1','localhost'], true)) {
    fwrite(STDERR, "Refusing to run against a non-local database.\n"); exit(2);
}
define('PV_DISABLE_OUTPUT_FILTER', true);
define('PV_SERVER_CONSOLE_CLI', true);
require_once dirname(__DIR__).'/includes/bootstrap.php';
$GLOBALS['pv_config']['db'] = $testDb;
$GLOBALS['pv_config']['server_console']['request_logging'] = false;
$GLOBALS['pv_config']['admin_console'] = array_replace((array)pv_config('admin_console', []), ['enabled'=>true,'operator_rank'=>'OWNER','enable_developer_commands'=>false,'disabled_commands'=>[]]);
require_once dirname(__DIR__).'/includes/admin/core.php';
ini_set('display_errors', '1');

$passed = 0; $failed = 0; $failures = []; $fixtures = [];
$suffix = bin2hex(random_bytes(4));
$context = pv_admin_boot(); $context['operator'] = 'regression-'.$suffix;
$settingsBefore = null;
$http = null;

function ct_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function ct_test(string $name, callable $body): void {
    global $passed,$failed,$failures;
    try { $body(); $passed++; echo "PASS ".$name."\n"; }
    catch (Throwable $e) { $failed++; $failures[]=$name.': '.$e->getMessage(); echo "FAIL ".$name.': '.$e->getMessage()."\n"; }
}
function ct_command(string $line, bool $expected = true): array {
    global $context;
    $lines = pv_admin_dispatch($line, $context);
    if ((bool)$context['last_ok'] !== $expected) {
        $verb = explode(' ',trim($line),2)[0];
        throw new RuntimeException('Unexpected '.($context['last_ok']?'acceptance':'rejection').' for '.$verb.($context['last_ok']?'':': '.implode(' | ',$lines)));
    }
    return $lines;
}
function ct_confirm(string $line): array {
    global $context;
    ct_command($line);
    ct_assert(is_array($context['pending']), 'Expected confirmation was not requested.');
    return ct_command('confirm '.$context['pending']['token']);
}
function ct_value(string $sql, array $params=[]): mixed { return array_values(pv_admin_row($sql,$params) ?? [null])[0]; }
function ct_state(int $uid): array { return pv_admin_player_state($uid); }
function ct_throws(callable $call): void { $thrown=false; try{$call();}catch(InvalidArgumentException $e){$thrown=true;} ct_assert($thrown,'Invalid input was accepted.'); }
function ct_schema_fingerprint(): array {
    return pv_admin_rows("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'console\\_%' OR TABLE_NAME='pokemon_stats') ORDER BY TABLE_NAME,ORDINAL_POSITION");
}
function ct_http_start(array $config): array {
    $app=dirname(__DIR__); $dir=sys_get_temp_dir().'/pv-console-regression-'.bin2hex(random_bytes(8));
    if (!mkdir($dir,0700)) throw new RuntimeException('Could not create private HTTP fixture directory.');
    $key=bin2hex(random_bytes(24));
    $router=<<<'ROUTER'
<?php
define('PV_DISABLE_OUTPUT_FILTER', true);
if (!hash_equals(TEST_KEY, (string)($_SERVER['HTTP_X_PV_TEST_KEY'] ?? ''))) { http_response_code(404); exit; }
require APP_ROOT.'/includes/bootstrap.php';
$GLOBALS['pv_config']['db']=TEST_DB;
$GLOBALS['pv_config']['server_console']['request_logging']=false;
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path==='/probe') {
    $_SERVER['SCRIPT_NAME']='/map_move.php';
    $_SESSION=[];
    if ((int)($_SERVER['HTTP_X_PV_TEST_UID']??0)>0) {
        $_SESSION['myid']=(int)$_SERVER['HTTP_X_PV_TEST_UID'];
        $_SESSION['pv_console_epoch']=(int)($_SERVER['HTTP_X_PV_TEST_EPOCH']??0);
        $_SESSION['pv_console_revision']=(int)($_SERVER['HTTP_X_PV_TEST_REVISION']??0);
        $_SESSION['pv_wild_battle']=['fixture'=>'must be invalidated'];
        $_SESSION['s1']=['fixture'=>'must be invalidated'];
    }
    pv_db();
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'handlers_loaded'=>function_exists('pv_admin_dispatch'),'session'=>$_SESSION]);
    exit;
}
$allow=['/includes/admin/core.php','/includes/admin/accounts.php','/includes/admin/pokemon.php','/includes/admin/world.php','/includes/admin/social.php','/includes/admin/system.php','/tools/test_admin_console.php','/tools/admin_console.php','/server_console.php'];
if (in_array($path,$allow,true) && is_file(APP_ROOT.$path)) { require APP_ROOT.$path; exit; }
http_response_code(404);
ROUTER;
    $definitions='<?php const APP_ROOT='.var_export($app,true).'; const TEST_KEY='.var_export($key,true).'; const TEST_DB='.var_export($config,true).'; ?>';
    file_put_contents($dir.'/router.php',$definitions.$router);
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if (!$socket) throw new RuntimeException('Cannot reserve local HTTP test port: '.$error);
    $address=stream_socket_get_name($socket,false); fclose($socket);
    $port=(int)substr(strrchr($address,':'),1);
    $command=[PHP_BINARY];
    if (php_ini_loaded_file()) { $command[]='-c';$command[]=php_ini_loaded_file(); }
    $command=array_merge($command,['-S','127.0.0.1:'.$port,'-t',$app,$dir.'/router.php']);
    $process=proc_open($command,[0=>['pipe','r'],1=>['file',$dir.'/http.log','a'],2=>['file',$dir.'/http.log','a']],$pipes,$app);
    if (!is_resource($process)) throw new RuntimeException('Could not start localhost HTTP test worker.');
    fclose($pipes[0]);
    for($i=0;$i<40;$i++) { $sock=@stream_socket_client('tcp://127.0.0.1:'.$port,$e,$s,.1); if($sock){fclose($sock);return compact('process','port','dir','key');} usleep(50000); }
    proc_terminate($process);proc_close($process);throw new RuntimeException('HTTP test worker did not start.');
}
function ct_http(string $path='/probe',int $uid=0,string $method='GET',?int $epoch=null,?int $revision=null): array {
    global $http;
    if (!$http) throw new RuntimeException('HTTP fixture is unavailable.');
    $state=$uid ? ct_state($uid) : [];
    $headers=['Accept: application/json','X-PV-Test-Key: '.$http['key'],'X-PV-Test-Uid: '.$uid,'X-PV-Test-Epoch: '.($epoch??(int)($state['session_epoch']??0)),'X-PV-Test-Revision: '.($revision??(int)($state['data_revision']??0))];
    $stream=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true,'timeout'=>10,'follow_location'=>0]]);
    $body=file_get_contents('http://127.0.0.1:'.$http['port'].$path,false,$stream);
    $status=0;foreach(($http_response_header??[]) as $header)if(preg_match('~^HTTP/\S+ (\d+)~',$header,$m))$status=(int)$m[1];
    return ['status'=>$status,'body'=>$body,'json'=>json_decode((string)$body,true)];
}

try {
    ct_test('Full migration and repeated repair preserve data and schema',function() {
        global $settingsBefore;
        require_once dirname(__DIR__).'/includes/schema.php';
        $db=pv_admin_db();
        // The first repair may legitimately seed missing baseline AI trainers.
        pv_apply_schema_migrations($db);
        $before=[ct_value('SELECT COUNT(*) FROM members'),ct_value('SELECT COUNT(*) FROM pokemon')];
        $fingerprint=ct_schema_fingerprint();
        pv_apply_schema_migrations($db);
        ct_assert($fingerprint===ct_schema_fingerprint(),'Repeated migration changed the console schema.');
        ct_assert($before===[ct_value('SELECT COUNT(*) FROM members'),ct_value('SELECT COUNT(*) FROM pokemon')],'Repair changed account/specimen totals.');
        $settingsBefore=pv_admin_rows('SELECT * FROM console_settings');
        $GLOBALS['pv_admin_schema_ready']=true;
    });
    if ($failed) throw new RuntimeException('Cannot safely continue without successful schema setup.');
    ct_test('Tokenizer handles quotes, escapes and optional slash without executing text',function() {
        ct_assert(pv_admin_tokenize('/givepokemon "Trainer Name" "Shiny Pikachu"')===['givepokemon','Trainer Name','Shiny Pikachu'],'Quoted arguments broken.');
        ct_assert(pv_admin_tokenize('setnickname 1 "Name \\"Quoted\\""')===['setnickname','1','Name "Quoted"'],'Escaped quotes broken.');
        ct_assert(pv_admin_tokenize('help ""')===['help',''],'Empty quoted argument lost.');
        ct_assert(pv_admin_tokenize('help $(whoami);')===['help','$(whoami);'],'Shell syntax was transformed.');
        ct_throws(static fn()=>pv_admin_tokenize('help "unfinished'));
        ct_throws(static fn()=>pv_admin_tokenize("help\0"));
        ct_throws(static fn()=>pv_admin_tokenize(str_repeat('x',4097)));
        ct_command('not_a_command',false);ct_command('help "unterminated',false);ct_command('help "$(whoami)"',false);
    });
    ct_test('Complete non-chat command inventory and aliases resolve',function() {
        $registry=pv_admin_registry();
        $expected=explode(' ','help commands who online serverinfo version uptime time ping motd rules profile stats playerinfo playtime location where afk back unstuck save resetplayer pokemon team bag givepokemon removepokemon heal healall evolve devolve setlevel setexp learn forget setnature setability setivs setevs setshiny setgender setnickname clonepokemon pokemoninfo giveitem removeitem setitem iteminfo clearinventory money balance givemoney removemoney setmoney economy trade tradecancel tradehistory spectate blocktrade friends friend tp teleport goto bring teleportplayer setlocation spawn despawn weather setweather day night clearweather warn warnings kick ban unban jail unjail freeze unfreeze invisible visible ipinfo history event createaccount deleteaccount resetpassword setrank promote demote lockaccount unlockaccount debug reload reloadconfig reloadscripts saveall dbsave clearcache gc memory threads connections logs test shutdown restart battle win lose sethp setstatus addmove clearmoves sit dance title titles settitle');
        ct_assert(!array_diff($expected,array_keys($registry)),'Missing proposed commands: '.implode(', ',array_diff($expected,array_keys($registry))));
        foreach(['say','shout','global','whisper','w','reply','r','ignore','unignore','mute','unmute','announce','broadcast','emote'] as $chat)ct_assert(!isset($registry[$chat]),'Chat command registered: '.$chat);
        foreach(['gp'=>'givepokemon','gi'=>'giveitem','tp'=>'teleport','balance'=>'money'] as $a=>$target)ct_assert(pv_admin_resolve($a,$registry)===$target,'Alias mismatch: '.$a);
        ct_command('event announce "should not exist"',false);
    });
    ct_test('Rank, developer opt-in, disabled aliases and dynamic help are enforced',function() {
        global $context;
        $saved=$context;$context['rank']='PLAYER';
        ct_command('givemoney MissingTrainer 1',false);ct_command('help givemoney',false);
        $text=implode("\n",ct_command('commands'));ct_assert(!str_contains($text,'givepokemon <'),'PLAYER help leaked privileged syntax.');
        $context=$saved;
        ct_command('clonepokemon 1',false);ct_command('help clonepokemon',false);
        $GLOBALS['pv_config']['admin_console']['disabled_commands']=['gp'];
        ct_command('givepokemon MissingTrainer Pikachu',false);ct_command('gp MissingTrainer Pikachu',false);ct_command('help gp',false);
        $GLOBALS['pv_config']['admin_console']['disabled_commands']=[];
    });
    $nameA='CommandTest_'.$suffix.'A';$nameB='CommandTest_'.$suffix.'B';
    $a=0;$b=0;$secretA='';$pid=0;
    ct_test('Account creation commits complete defaults and keeps generated password out of audit',function()use($nameA,$nameB,&$a,&$b,&$secretA){
        global $fixtures,$context;
        foreach([$nameA,$nameB] as $name){
            $lines=ct_command('createaccount '.$name);
            $row=pv_admin_row('SELECT * FROM members WHERE username=?',[$name]);ct_assert((bool)$row,'Created account missing.');
            $uid=(int)$row['id'];$fixtures[]=$uid;if($name===$nameA)$a=$uid;else $b=$uid;
            preg_match('/Generated password \(shown once\): (\S+)/',implode("\n",$lines),$match);$secret=$match[1]??'';
            ct_assert($secret!==''&&password_verify($secret,(string)$row['password']),'Generated password does not verify against stored hash.');
            if($name===$nameA)$secretA=$secret;
            ct_assert((int)$row['s1']>0&&(int)ct_value('SELECT COUNT(*) FROM pokemon WHERE owner=?',[$uid])===1,'Complete starter missing.');
            foreach(['members_options'=>'id','items'=>'uid','badges'=>'id','events'=>'id','comments'=>'userid'] as $table=>$column)ct_assert((int)ct_value("SELECT COUNT(*) FROM $table WHERE $column=?",[$uid])===1,'Missing default '.$table);
            $audit=ct_value('SELECT CONCAT(arguments_json,result_text) FROM console_audit WHERE operator_name=? AND command_name=? ORDER BY id DESC LIMIT 1',[$context['operator'],'createaccount']);
            ct_assert(!str_contains((string)$audit,$secret),'Password leaked into database audit.');
        }
        ct_command('createaccount '.$nameA,false);
        ct_assert((int)ct_value('SELECT COUNT(*) FROM members WHERE username=?',[$nameA])===1,'Duplicate account created.');
        ct_command('select #'.$a);ct_assert($context['selected']===$a,'ID targeting failed.');
        ct_command('select '.$nameA);ct_assert($context['selected']===$a,'Name targeting failed.');
        ct_command('playerinfo');
    });
    if (!$a || !$b) throw new RuntimeException('Cannot continue mutation checks without account fixtures.');
    ct_test('Currency updates reject underflow, overflow and malformed integers atomically',function()use($a){
        ct_command('setmoney #'.$a.' 100');ct_command('givemoney #'.$a.' 25');ct_command('removemoney #'.$a.' 26');
        ct_assert((int)ct_value('SELECT money FROM members WHERE id=?',[$a])===99,'Currency arithmetic mismatch.');
        foreach(['removemoney #'.$a.' 100','givemoney #'.$a.' -1','setmoney #'.$a.' 1e3','givemoney #'.$a.' 2000000000'] as $line)ct_command($line,false);
        ct_assert((int)ct_value('SELECT money FROM members WHERE id=?',[$a])===99,'Rejected currency mutation changed the balance.');
    });
    ct_test('Inventory aliases, quantities and catalog validation prevent overspend',function()use($a){
        ct_command('setitem #'.$a.' "Poke Ball" 3');ct_command('gi #'.$a.' "Poke Ball" 2');
        $column=pv_admin_item('Poke Ball')['column'];
        ct_command('removeitem #'.$a.' "Poke Ball" 6',false);ct_command('giveitem #'.$a.' "Poke Ball" 1000000',false);ct_command('giveitem #'.$a.' "not a real item" 1',false);
        ct_assert((int)ct_value('SELECT `'.$column.'` FROM items WHERE uid=?',[$a])===5,'Rejected inventory mutation changed quantity.');
        ct_command('removeitem #'.$a.' "Poke Ball" 1');ct_assert((int)ct_value('SELECT `'.$column.'` FROM items WHERE uid=?',[$a])===4,'Valid item removal failed.');
    });
    ct_test('Destructive confirmations are cancellable, expiring, single-use and bound to selection',function()use($a,$b){
        global $context;
        $column=pv_admin_item('Poke Ball')['column'];
        ct_command('select #'.$a);ct_command('clearinventory #'.$a);$token=$context['pending']['token'];
        ct_assert((int)ct_value('SELECT `'.$column.'` FROM items WHERE uid=?',[$a])===4,'Unconfirmed operation mutated inventory.');
        ct_command('cancel');ct_command('confirm '.$token,false);
        ct_command('clearinventory #'.$a);$token=$context['pending']['token'];$context['pending']['expires']=time()-1;ct_command('confirm '.$token,false);
        ct_command('clearinventory #'.$a);$token=$context['pending']['token'];ct_command('select #'.$b);ct_command('confirm '.$token,false);
        ct_assert((int)ct_value('SELECT `'.$column.'` FROM items WHERE uid=?',[$a])===4,'Rejected confirmation mutated inventory.');
        ct_command('select #'.$a);ct_command('clearinventory #'.$a);$token=$context['pending']['token'];ct_command('confirm '.$token);ct_command('confirm '.$token,false);
        ct_assert((int)ct_value('SELECT `'.$column.'` FROM items WHERE uid=?',[$a])===0,'Confirmed operation failed.');
    });
    ct_test('Owned Pokémon mutations preserve identity and roll back invalid stat edits',function()use($a,&$pid){
        ct_command('gp #'.$a.' "Shiny Pikachu" 25');
        $p=pv_admin_row('SELECT * FROM pokemon WHERE owner=? ORDER BY id DESC LIMIT 1',[$a]);$pid=(int)$p['id'];
        ct_assert($p['name']==='Shiny Pikachu'&&(int)$p['lvl']===25,'Created species/level wrong.');
        ct_command('setlevel '.$pid.' 30');ct_command('setlevel '.$pid.' 101',false);
        ct_assert((int)ct_value('SELECT lvl FROM pokemon WHERE id=?',[$pid])===30,'Invalid level mutation was committed.');
        ct_command('setivs '.$pid.' 31 30 29 28 27 26');ct_command('setevs '.$pid.' 252 252 4 0 0 0');
        $before=pv_admin_row('SELECT * FROM pokemon_stats WHERE id=?',[$pid]);ct_command('setevs '.$pid.' 252 252 252 0 0 0',false);ct_command('setivs '.$pid.' 32 0 0 0 0 0',false);
        ct_assert($before===pv_admin_row('SELECT * FROM pokemon_stats WHERE id=?',[$pid]),'Invalid stats partially committed.');
        ct_command('setnickname '.$pid.' "Console Test Pikachu"');ct_command('pokemoninfo '.$pid);
        ct_command('setshiny '.$pid.' false');ct_assert(ct_value('SELECT name FROM pokemon WHERE id=?',[$pid])==='Pikachu','Shiny conversion did not preserve specimen.');
        ct_assert((int)ct_value('SELECT total_poke FROM members WHERE id=?',[$a])===(int)ct_value('SELECT COUNT(*) FROM pokemon WHERE owner=?',[$a]),'Collection counter drifted.');
    });
    ct_test('Password reset requires confirmation, revokes sessions and redacts audit',function()use($a,$secretA){
        global $context;
        $before=ct_state($a);$lines=ct_confirm('resetpassword #'.$a);
        preg_match('/Generated password \(shown once\): (\S+)/',implode("\n",$lines),$match);$secret=$match[1]??'';
        $hash=(string)ct_value('SELECT password FROM members WHERE id=?',[$a]);
        ct_assert($secret!==''&&password_verify($secret,$hash)&&!password_verify($secretA,$hash),'Replacement credentials incorrect.');
        ct_assert((int)ct_state($a)['session_epoch']===(int)$before['session_epoch']+1,'Session generation was not revoked.');
        $audit=(string)ct_value('SELECT CONCAT(arguments_json,result_text) FROM console_audit WHERE operator_name=? AND command_name=? ORDER BY id DESC LIMIT 1',[$context['operator'],'resetpassword']);
        ct_assert(!str_contains($audit,$secret),'Replacement password leaked into audit.');
    });
    ct_test('Social commands use real friendships and trading restrictions without chat',function()use($a,$b){
        ct_command('select #'.$a);ct_command('trade #'.$b);ct_command('tradehistory');ct_command('tradecancel');ct_command('friends');
        ct_command('friend #'.$b.' add');ct_command('select #'.$b);ct_command('friend #'.$a.' accept');
        ct_assert((int)ct_value('SELECT COUNT(*) FROM trainer_friends WHERE user_id=? AND friend_id=?',[$a,$b])===1,'Friendship was not persisted.');
        ct_command('select #'.$a);ct_command('blocktrade #'.$b.' on');ct_assert((int)ct_value('SELECT COUNT(*) FROM console_trade_blocks WHERE user_id=? AND target_id=?',[$a,$b])===1,'Trade restriction missing.');
        ct_command('blocktrade #'.$b.' off');ct_command('friend #'.$b.' remove');
        ct_assert((int)ct_value('SELECT COUNT(*) FROM trainer_friends WHERE user_id=? AND friend_id=?',[$a,$b])===0,'Friend removal failed.');
    });
    ct_test('Local HTTP fixture starts and normal web requests never load command handlers',function()use($testDb,$a){
        global $http;$http=ct_http_start($testDb);$response=ct_http('/probe',$a);
        ct_assert($response['status']===200&&($response['json']['ok']??false),'Normal authenticated request failed.');
        ct_assert(($response['json']['handlers_loaded']??true)===false,'Command dispatcher leaked into web runtime.');
    });
    ct_test('Every command handler and integration runner returns HTTP 404',function(){
        foreach(['core','accounts','pokemon','world','social','system'] as $module)ct_assert(ct_http('/includes/admin/'.$module.'.php')['status']===404,'HTTP callable handler: '.$module);
        ct_assert(ct_http('/tools/test_admin_console.php')['status']===404,'Regression runner exposed through HTTP.');
        ct_assert(ct_http('/server_console.php')['status']===404,'Main command worker exposed through HTTP.');
    });
    ct_test('Freeze, jail, account lock, ban and kick take effect on authenticated web requests',function()use($a){
        ct_command('freeze #'.$a);ct_assert(ct_http('/probe',$a)['status']===403,'Freeze not enforced.');ct_command('unfreeze #'.$a);
        ct_confirm('jail #'.$a.' 30s "regression holding"');ct_assert(ct_http('/probe',$a)['status']===403,'Jail not enforced.');ct_command('unjail #'.$a);
        ct_confirm('lockaccount #'.$a);ct_assert(ct_http('/probe',$a)['status']===401,'Lock not enforced.');ct_command('unlockaccount #'.$a);
        ct_confirm('ban #'.$a.' 30s "regression ban"');ct_assert(ct_http('/probe',$a)['status']===401,'Ban not enforced.');ct_command('unban #'.$a);
        $old=(int)ct_state($a)['session_epoch'];ct_command('kick #'.$a.' "regression reconnect"');ct_assert(ct_http('/probe',$a,'GET',$old)['status']===401,'Kicked session remains authorized.');
        ct_assert(ct_http('/probe',$a)['status']===200,'New session cannot resume after kick.');
    });
    ct_test('Expired bans release and stale browser mutations cannot overwrite console changes',function()use($a){
        ct_confirm('ban #'.$a.' 30s "regression expiry"');
        pv_admin_exec('UPDATE console_player_state SET ban_until=? WHERE user_id=?',[time()-1,$a]);
        ct_assert(ct_http('/probe',$a)['status']===200,'Expired ban did not release.');
        ct_assert((int)ct_state($a)['ban_until']===0&&(string)ct_value('SELECT banned FROM members WHERE id=?',[$a])==='0','Expired ban was not persisted clear.');
        $revision=(int)ct_state($a)['data_revision'];ct_command('givemoney #'.$a.' 1');
        ct_assert(ct_http('/probe',$a,'POST',null,$revision)['status']===409,'Stale POST accepted after mutation.');
        $fresh=ct_http('/probe',$a,'GET',null,$revision);
        ct_assert($fresh['status']===200&&!isset($fresh['json']['session']['pv_wild_battle'])&&!isset($fresh['json']['session']['s1']),'Stale battle snapshots survived refresh.');
    });
    ct_test('Maintenance shutdown/restart schedule is confirmed, web enforced and reversible',function(){
        global $context;
        ct_command('shutdown 0');ct_assert(ct_http('/probe')['status']===200,'Unconfirmed shutdown blocked server.');ct_command('cancel');
        ct_confirm('shutdown 0');ct_assert(ct_http('/probe')['status']===503,'Confirmed shutdown did not gate requests.');ct_command('resume');ct_assert(ct_http('/probe')['status']===200,'Resume did not reopen game.');
        ct_confirm('restart 60');$schedule=pv_admin_setting('maintenance_schedule');ct_assert($schedule['kind']==='restart'&&$schedule['due']>=time()+58,'Restart schedule incorrect.');
        ct_command('resume');
    });
    ct_test('Developer battle commands affect only test state and grant no real rewards',function()use($a,$b,$pid){
        $GLOBALS['pv_config']['admin_console']['enable_developer_commands']=true;
        ct_command('select #'.$a);$before=pv_admin_rows('SELECT id,money,wins,losses FROM members WHERE id IN (?,?) ORDER BY id',[$a,$b]);
        ct_command('battle #'.$b);ct_command('battle status');ct_command('win');
        ct_assert($before===pv_admin_rows('SELECT id,money,wins,losses FROM members WHERE id IN (?,?) ORDER BY id',[$a,$b]),'Test duel changed production rewards or outcomes.');
        $GLOBALS['pv_config']['admin_console']['enable_developer_commands']=false;
    });
    ct_test('Reset keeps identity/credentials and deletion removes owned state after confirmation',function()use($a){
        $identity=pv_admin_row('SELECT username,password FROM members WHERE id=?',[$a]);
        ct_command('resetplayer #'.$a);ct_command('cancel');ct_assert((int)ct_value('SELECT COUNT(*) FROM pokemon WHERE owner=?',[$a])>1,'Cancelled reset erased collection.');
        ct_confirm('resetplayer #'.$a);
        ct_assert($identity===pv_admin_row('SELECT username,password FROM members WHERE id=?',[$a]),'Reset changed identity/credentials.');
        ct_assert((int)ct_value('SELECT COUNT(*) FROM pokemon WHERE owner=?',[$a])===1,'Reset did not restore one starter.');
        $p=pv_admin_row('SELECT name,lvl FROM pokemon WHERE owner=?',[$a]);ct_assert($p['name']==='Bulbasaur'&&(int)$p['lvl']===18,'Reset starter incorrect.');
        ct_confirm('deleteaccount #'.$a);ct_assert(ct_value('SELECT id FROM members WHERE id=?',[$a])===null,'Delete left account present.');
        ct_assert((int)ct_value('SELECT COUNT(*) FROM pokemon WHERE owner=?',[$a])===0&&(int)ct_value('SELECT COUNT(*) FROM items WHERE uid=?',[$a])===0,'Delete left owned gameplay rows.');
    });
    ct_test('Audit records successful and rejected mutations with operator and target',function()use($a){
        global $context;
        $rows=pv_admin_rows('SELECT * FROM console_audit WHERE operator_name=?',[$context['operator']]);
        ct_assert((bool)array_filter($rows,static fn($r)=>$r['outcome']==='failed'),'No failure audit recorded.');
        ct_assert((bool)array_filter($rows,static fn($r)=>$r['outcome']==='success'&&str_contains($r['target_text'],'#'.$a)),'Success audit lacks target.');
        ct_assert(!array_filter($rows,static fn($r)=>$r['outcome']==='started'),'Completed command left an unfinished audit entry.');
    });
} catch (Throwable $e) {
    $failed++;$failures[]='Suite setup: '.$e->getMessage();echo 'FAIL suite setup: '.$e->getMessage()."\n";
} finally {
    if ($http) {
        proc_terminate($http['process']);proc_close($http['process']);
        foreach(glob($http['dir'].'/*')?:[] as $file)@unlink($file);@rmdir($http['dir']);
    }
    $context['rank']='OWNER';$context['pending']=null;$context['selected']=0;
    $GLOBALS['pv_config']['admin_console']['enabled']=true;$GLOBALS['pv_config']['admin_console']['disabled_commands']=[];
    foreach($fixtures as $uid) {
        try { if(ct_value('SELECT id FROM members WHERE id=?',[$uid])!==null)ct_confirm('deleteaccount #'.$uid); }
        catch(Throwable $e){$failed++;$failures[]='Fixture cleanup #'.$uid.': '.$e->getMessage();}
    }
    if (is_array($settingsBefore)) {
        try { pv_admin_transaction(static function()use($settingsBefore):void{
            pv_admin_exec('DELETE FROM console_settings');
            foreach($settingsBefore as $row)pv_admin_exec('INSERT INTO console_settings(setting_key,value_json,updated_at) VALUES(?,?,?)',[$row['setting_key'],$row['value_json'],(int)$row['updated_at']]);
        }); } catch(Throwable $e){$failed++;$failures[]='Settings cleanup: '.$e->getMessage();}
    }
    pv_admin_release_locks();
}
echo "\nRESULT: ".$passed." passed; ".$failed." failed.\n";
if($failures)foreach($failures as $failure)echo '  '.$failure."\n";
exit($failed ? 1 : 0);
