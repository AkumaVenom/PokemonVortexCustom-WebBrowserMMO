<?php
declare(strict_types=1);
/**
 * php tools/test_experience_battles.php
 * Optional database checks use only TEMPORARY fixture tables on one connection:
 * PV_TEST_DB_NAME=... PV_TEST_DB_PORT=3306 PV_TEST_DB_USER=root php ... --database
 * Credentials may use PV_TEST_DB_HOST / PV_TEST_DB_PASSWORD / PV_TEST_DB_SOCKET.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('PV_DISABLE_OUTPUT_FILTER', true);
define('PV_SERVER_CONSOLE_CLI', true);
require_once dirname(__DIR__).'/includes/battle_runtime.php';
require_once dirname(__DIR__).'/includes/wild_battle.php';
require_once dirname(__DIR__).'/includes/live_battle_settlement.php';
ini_set('display_errors', '1');
$checks = 0;
function xp_assert(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function xp_fighter(int $id, string $name, int $level = 20, int $hp = 80, string $ot = 'Trainer'): array {
    return [$name,$id,'Normal','',$level,pv_exp_at_level($name,$level),'Tackle','','','',$hp,80,100,0,'',0,$ot];
}
$_SESSION = ['myid'=>1, 'myuser'=>'Trainer', 'opponent_profile'=>[2,'Opponent',2,'gym'],
    's1'=>xp_fighter(11,'Bulbasaur'), 's2'=>xp_fighter(12,'Squirtle'), 's3'=>xp_fighter(13,'Pikachu'),
    'ops1'=>xp_fighter(91,'Pidgey'), 'ops2'=>xp_fighter(92,'Ditto'), 'y_p'=>[1,1]];
pv_battle_exp_begin();
$nonce = $_SESSION['pv_standard_exp']['id'];
pv_battle_exp_note_active();
$_SESSION['y_p'] = [2,1]; pv_battle_exp_note_active();
$_SESSION['s1'][10] = 0; $_SESSION['ops1'][10] = 0;
$pending = pv_battle_exp_pending();
xp_assert(array_keys($pending[1]['eligible']) === [12], 'Fainted and unused Pokémon must not share EXP.');
xp_assert(!isset($pending[2]), 'An undefeated opponent must not pay EXP.');
$_SESSION['pv_standard_exp']['opponents'][1]['done'] = true;
$_SESSION['y_p'] = [2,2]; pv_battle_exp_note_active();
$_SESSION['ops2'][0] = 'Mewtwo'; $_SESSION['ops2'][10] = 0;
$pending = pv_battle_exp_pending();
xp_assert($pending[2]['opponent']['name'] === 'Ditto', 'Transform must not replace the original EXP yield.');
xp_assert(array_keys($pending[2]['eligible']) === [12], 'Participation must be tracked separately for each opponent.');
$_SESSION['pv_standard_exp']['awards'] = [1=>[12=>50], 2=>[12=>80]];
xp_assert(pv_battle_exp_total() === 130, 'The result must sum actual rewards across opponents.');
pv_battle_exp_begin(true);
xp_assert(pv_battle_exp_pending() === [], 'Upgrade recovery must not invent rewards for old fainted opponents.');
xp_assert($_SESSION['pv_standard_exp']['id'] !== $nonce, 'A new battle must have a distinct receipt namespace.');
xp_assert(pv_exp_battle_gain('Pidgey', 20, 2, false) === intdiv(pv_exp_battle_gain('Pidgey',20),2), 'Wild EXP must split among surviving participants.');
xp_assert(pv_exp_battle_gain('Pidgey', 20, 2, true) === intdiv(pv_exp_battle_gain('Pidgey',20,2)*150,100), 'Trainer bonus must follow integer participant splitting.');

if (in_array('--database', $argv, true)) {
    if (!getenv('PV_TEST_DB_NAME')) throw new RuntimeException('Set PV_TEST_DB_NAME explicitly for database checks.');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli((string)(getenv('PV_TEST_DB_HOST') ?: '127.0.0.1'), (string)(getenv('PV_TEST_DB_USER') ?: 'root'),
        (string)(getenv('PV_TEST_DB_PASSWORD') ?: ''), (string)getenv('PV_TEST_DB_NAME'),
        (int)(getenv('PV_TEST_DB_PORT') ?: 3306), getenv('PV_TEST_DB_SOCKET') ?: null);
    $db->set_charset('utf8mb4');
    // These tables disappear when this connection closes and shadow real tables.
    $db->query("CREATE TEMPORARY TABLE pokemon (id INT PRIMARY KEY,pid INT DEFAULT 1,name VARCHAR(80),lvl INT,exp INT,exp_curve_version INT DEFAULT 1,owner VARCHAR(30),ot VARCHAR(80) DEFAULT 'Trainer',rowner VARCHAR(80) DEFAULT 'Trainer') ENGINE=InnoDB");
    $db->query('CREATE TEMPORARY TABLE pokemon_stats (id INT PRIMARY KEY,happiness INT DEFAULT 70) ENGINE=InnoDB');
    $db->query('CREATE TEMPORARY TABLE pokemon_exp_awards (reward_key VARCHAR(96) PRIMARY KEY,user_id INT,pokemon_id INT,reward_exp INT,created_at BIGINT) ENGINE=InnoDB');
    $db->query("CREATE TEMPORARY TABLE members (id INT PRIMARY KEY,battle INT DEFAULT 0,money INT DEFAULT 0,losses INT DEFAULT 0,btime BIGINT DEFAULT 0,eb FLOAT DEFAULT 10,clan_name VARCHAR(80) DEFAULT '',total_poke INT DEFAULT 0,uniques INT DEFAULT 0,totalexp BIGINT DEFAULT 0,averageexp INT DEFAULT 0,points DOUBLE DEFAULT 0) ENGINE=InnoDB");
    $db->query('CREATE TEMPORARY TABLE wild_battle_results (battle_id VARCHAR(64) PRIMARY KEY,user_id INT,outcome VARCHAR(16),wild_pid INT,wild_name VARCHAR(80),wild_level INT,reward_exp INT,reward_money INT,captured_pokemon_id INT,created_at BIGINT) ENGINE=InnoDB');
    $db->query("CREATE TEMPORARY TABLE live_battle (id INT PRIMARY KEY,uid_1 INT,uid_2 INT,settled_1 INT DEFAULT 0,settled_2 INT DEFAULT 0,outcome_1 VARCHAR(10) DEFAULT '',outcome_2 VARCHAR(10) DEFAULT '',reward_exp_1 INT DEFAULT 0,reward_exp_2 INT DEFAULT 0,reward_money_1 INT DEFAULT 0,reward_money_2 INT DEFAULT 0,settled_at_1 BIGINT DEFAULT 0,settled_at_2 BIGINT DEFAULT 0) ENGINE=InnoDB");
    $db->query('INSERT INTO members(id) VALUES (1),(2)');
    $base = pv_exp_at_level('Bulbasaur',20);
    $db->query("INSERT INTO pokemon(id,name,lvl,exp,owner) VALUES (11,'Bulbasaur',20,{$base},'1'),(12,'Bulbasaur',20,{$base},'1'),(13,'Bulbasaur',20,{$base},'1'),(14,'Bulbasaur',20,{$base},'2')");
    $cap = pv_exp_at_level('Bulbasaur',100);
    $db->query("INSERT INTO pokemon(id,name,lvl,exp,owner) VALUES (15,'Bulbasaur',100,{$cap},'1')");
    $db->query('INSERT INTO pokemon_stats(id) VALUES (11),(12),(13),(14),(15)');
    $value = static fn(string $sql) => $db->query($sql)->fetch_row()[0];
    $receipt = str_repeat('a',32).':1:11'; $result = null;
    xp_assert(pv_battle_runtime_award_participant($db,1,11,100,1,$receipt,$result), 'An owned participant must receive the award.');
    xp_assert((int)$value('SELECT exp FROM pokemon WHERE id=11') === $base+100, 'EXP must use the species curve without linear level jumps.');
    pv_battle_runtime_award_participant($db,1,11,100,1,$receipt,$result);
    xp_assert((int)$value('SELECT exp FROM pokemon WHERE id=11') === $base+100 && (int)$value('SELECT happiness FROM pokemon_stats WHERE id=11') === 71, 'Retrying a KO receipt must not repeat EXP or happiness.');
    xp_assert(!pv_battle_runtime_award_participant($db,1,14,100,1,str_repeat('a',32).':1:14',$result), 'Ownership changes must reject rewards.');
    pv_battle_runtime_award_participant($db,1,15,100,1,str_repeat('a',32).':1:15',$result);
    xp_assert($result['gained'] === 0 && (int)$value('SELECT exp FROM pokemon WHERE id=15') === $cap, 'Level-100 EXP must be capped.');
    // A failed receipt insert must roll back Pokémon EXP/happiness as one unit.
    $collision = str_repeat('b',32).':1:11';
    $db->query("INSERT INTO pokemon_exp_awards VALUES ('{$collision}',2,14,100,0)");
    $failed = false;
    try { pv_battle_runtime_award_participant($db,1,11,500,1,$collision,$result); } catch (Throwable $e) { $failed=true; }
    xp_assert($failed && (int)$value('SELECT exp FROM pokemon WHERE id=11') === $base+100, 'Receipt failure must atomically roll back the award.');
    $_SESSION['myid']=1; $_SESSION['myuser']='Trainer';
    $db->query("UPDATE pokemon SET ot='Other Trainer' WHERE id=12");
    $wild = ['id'=>str_repeat('c',32),'status'=>'active','wild'=>['pid'=>1,'name'=>'Pidgey','display_name'=>'Pidgey','level'=>20],
        'team'=>[11=>['hp'=>20],12=>['hp'=>20],13=>['hp'=>0]],'participants'=>[11=>true,12=>true,13=>true],'log'=>[]];
    $normal = pv_exp_battle_gain('Pidgey',20,2,false,false);
    $traded = pv_exp_battle_gain('Pidgey',20,2,false,true);
    pv_wild_finalize_win($db,$wild);
    xp_assert((int)$value('SELECT exp FROM pokemon WHERE id=11') === $base+100+$normal, 'Wild reward must divide EXP between living participants.');
    xp_assert((int)$value('SELECT exp FROM pokemon WHERE id=12') === $base+$traded, 'Traded Pokémon must receive the Gen3 1.5x bonus.');
    xp_assert((int)$value('SELECT exp FROM pokemon WHERE id=13') === $base, 'Fainted wild-battle participants must receive no EXP.');
    xp_assert($wild['result']['exp'] === $normal+$traded, 'Wild receipt must report actual total EXP, including bonuses.');
    $money=(int)$value('SELECT money FROM members WHERE id=1');
    $wild['status']='active';$wild['rewarded']=false;pv_wild_finalize_win($db,$wild);
    xp_assert((int)$value('SELECT money FROM members WHERE id=1') === $money && (int)$value('SELECT exp FROM pokemon WHERE id=12') === $base+$traded, 'Lost-response wild replay must not duplicate rewards.');
    $_SESSION['your_profile']=[1,'Trainer',1,10];$_SESSION['opponent_profile']=[2,'Opponent',1,'live'];
    $_SESSION['s1']=xp_fighter(11,'Bulbasaur');$_SESSION['s1'][13]=1;$_SESSION['ops1']=xp_fighter(91,'Pidgey',20,0);
    $db->query('INSERT INTO live_battle(id,uid_1,uid_2) VALUES (1,1,2)');
    $before=(int)$value('SELECT SUM(exp) FROM pokemon');
    $live=pv_live_settle_result($db,1,1,2,1,2,'win');
    xp_assert($live['reward_exp']===0 && (int)$value('SELECT SUM(exp) FROM pokemon')===$before, 'Link PvP must preserve Pokémon EXP while settling trainer rewards.');
    // Exercise the actual per-opponent dispatcher, not just the award helper.
    $_SESSION['opponent_profile']=[2,'Opponent',2,'gym'];
    $_SESSION['s1']=xp_fighter(11,'Bulbasaur');$_SESSION['s2']=xp_fighter(12,'Bulbasaur');
    $_SESSION['ops1']=xp_fighter(91,'Pidgey');$_SESSION['ops2']=xp_fighter(92,'Ditto');
    $_SESSION['y_p']=[1,1];pv_battle_exp_begin();pv_battle_exp_note_active();
    $_SESSION['y_p']=[2,1];pv_battle_exp_note_active();
    $_SESSION['s1'][10]=0;$_SESSION['ops1'][10]=0;
    $firstBefore=(int)$value('SELECT exp FROM pokemon WHERE id=11');
    $secondBefore=(int)$value('SELECT exp FROM pokemon WHERE id=12');
    // Set the actual original trainer in the hydrated combat snapshot.
    $_SESSION['s2'][16]='Other Trainer';
    $firstGain=pv_exp_battle_gain('Pidgey',20,1,true,true);
    pv_battle_runtime_reward_fainted($db,1);
    xp_assert((int)$value('SELECT exp FROM pokemon WHERE id=11')===$firstBefore && (int)$value('SELECT exp FROM pokemon WHERE id=12')===$secondBefore+$firstGain, 'KO dispatcher must reward only eligible survivors, with the traded bonus.');
    pv_battle_runtime_reward_fainted($db,1);
    xp_assert((int)$value('SELECT exp FROM pokemon WHERE id=12')===$secondBefore+$firstGain, 'Completed KOs must not be dispatched twice.');
    $_SESSION['y_p']=[2,2];pv_battle_exp_note_active();
    $_SESSION['ops2'][0]='Mewtwo';$_SESSION['ops2'][10]=0;
    $secondGain=pv_exp_battle_gain('Ditto',20,1,true,true);
    pv_battle_runtime_reward_fainted($db,1);
    xp_assert(pv_battle_exp_total()===$firstGain+$secondGain && (int)$value('SELECT exp FROM pokemon WHERE id=12')===$secondBefore+$firstGain+$secondGain, 'Sequential KOs must pay the original species yield even after Transform.');
    xp_assert((int)$value('SELECT totalexp FROM members WHERE id=1') === (int)$value("SELECT SUM(exp) FROM pokemon WHERE owner='1'"), 'KO rewards must refresh trainer totals before any terminal victory, loss or withdrawal.');
    foreach ([16=>['Ditto','Shiny Mewtwo',4],17=>['Shiny Ditto','Mewtwo',5]] as $id=>$form) {
        [$realName,$transformedName,$hpStep]=$form;
        $next=pv_exp_at_level($realName,21)-1;
        $db->query("INSERT INTO pokemon(id,name,lvl,exp,owner) VALUES ({$id},'{$realName}',20,{$next},'1')");
        $db->query("INSERT INTO pokemon_stats(id) VALUES ({$id})");
        $_SESSION['opponent_profile']=[2,'Opponent',1,'gym'];$_SESSION['s1']=xp_fighter($id,$transformedName);
        $_SESSION['ops1']=xp_fighter(91,'Pidgey');$_SESSION['y_p']=[1,1];
        pv_battle_exp_begin();pv_battle_exp_note_active();$_SESSION['ops1'][10]=0;
        pv_battle_runtime_reward_fainted($db,1);
        xp_assert($_SESSION['s1'][4]===21 && $_SESSION['s1'][11]===80+$hpStep, 'Transform must not change the owned Pokémon form used for level-up HP: '.$realName);
    }
    // A secondary totals failure retains a retry marker after EXP is committed.
    $db->query('ALTER TABLE members DROP COLUMN averageexp');
    $_SESSION['opponent_profile']=[2,'Opponent',1,'gym'];$_SESSION['s1']=xp_fighter(11,'Bulbasaur');
    $_SESSION['ops1']=xp_fighter(91,'Pidgey');$_SESSION['y_p']=[1,1];
    pv_battle_exp_begin();pv_battle_exp_note_active();$_SESSION['ops1'][10]=0;
    pv_battle_runtime_reward_fainted($db,1);
    xp_assert((int)($_SESSION['pv_exp_progress_dirty']??0)===1, 'A failed aggregate refresh must retain the retry marker.');
    $committed=(int)$value('SELECT exp FROM pokemon WHERE id=11');
    $db->query('ALTER TABLE members ADD COLUMN averageexp INT DEFAULT 0');
    pv_battle_runtime_reward_fainted($db,1);
    xp_assert(!isset($_SESSION['pv_exp_progress_dirty']) && (int)$value('SELECT exp FROM pokemon WHERE id=11')===$committed && (int)$value('SELECT totalexp FROM members WHERE id=1')===(int)$value("SELECT SUM(exp) FROM pokemon WHERE owner='1'"), 'Retrying an aggregate refresh must fix totals without repeating a completed KO.');
    // A later recipient failure must still refresh the already-committed subset.
    $_SESSION['s1']=xp_fighter(11,'Bulbasaur');$_SESSION['s2']=xp_fighter(12,'Bulbasaur');
    $_SESSION['ops1']=xp_fighter(91,'Pidgey');$_SESSION['y_p']=[1,1];
    pv_battle_exp_begin();pv_battle_exp_note_active();$_SESSION['y_p']=[2,1];pv_battle_exp_note_active();$_SESSION['ops1'][10]=0;
    $conflict=$_SESSION['pv_standard_exp']['id'].':1:12';
    $db->query("INSERT INTO pokemon_exp_awards VALUES ('{$conflict}',2,14,100,0)");
    $failed=false;try { pv_battle_runtime_reward_fainted($db,1); } catch(Throwable $e) { $failed=true; }
    xp_assert($failed && (int)$value('SELECT totalexp FROM members WHERE id=1')===(int)$value("SELECT SUM(exp) FROM pokemon WHERE owner='1'"), 'Partial KO failure must refresh the EXP that already committed.');
    $committed=(int)$value('SELECT exp FROM pokemon WHERE id=11');
    $db->query("DELETE FROM pokemon_exp_awards WHERE reward_key='{$conflict}'");
    pv_battle_runtime_reward_fainted($db,1);
    xp_assert((int)$value('SELECT exp FROM pokemon WHERE id=11')===$committed && !empty($_SESSION['pv_standard_exp']['opponents'][1]['done']), 'Partial KO retry must finish the missing reward without repeating the committed recipient.');
    $db->close();
}
echo "PASS {$checks} battle experience checks".(!in_array('--database',$argv,true)?' (database checks not requested)':'')."\n";
