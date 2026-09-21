<?php
declare(strict_types=1);
/** Pure checks by default; --integration requires an upgraded disposable test DB.
 * PV_TEST_DB_NAME=pv_test_bots PV_TEST_DB_SOCKET=/tmp/mysql.sock php this-file --integration
 * The integration fixture intentionally changes that database. Never use live data. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/includes/bot_runtime.php';
set_exception_handler(static function(Throwable $error):void{fwrite(STDERR,$error->getMessage().PHP_EOL.$error->getTraceAsString().PHP_EOL);exit(1);});

$checks=0;
function bot_check(bool $condition,string $label):void
{
    global $checks;$checks++;
    if(!$condition)throw new RuntimeException('FAIL: '.$label);
}
function bot_fixture_rows(array $levels):array
{
    $rows=[];foreach($levels as $id=>$level)$rows[]=['id'=>$id,'lvl'=>$level];return $rows;
}

$rows=bot_fixture_rows([1=>100,2=>100,3=>100,4=>100,5=>100,6=>100,7=>20,8=>30]);
bot_check(pv_bot_training_roster([1,2,3,4,5,6],$rows)===[1,2,3,4,5,8],'a capped team trains a reserve while retaining five ranked anchors');
$rows[7]['lvl']=100;
bot_check(pv_bot_training_roster([1,2,3,4,5,8],$rows)===[1,2,3,4,5,7],'training proceeds to the next reserve after graduation');
bot_check(pv_bot_training_roster([1,2,3,4,5,7],$rows)===[1,2,3,4,5,7],'an unfinished trainee keeps its slot');
$fixed=pv_bot_training_roster([999,2,2,0,5,6],$rows);
bot_check(count(array_unique($fixed))===6&&!in_array(999,$fixed,true)&&!in_array(0,$fixed,true),'stale, duplicate and missing team slots are repaired from owned specimens');
bot_check(pv_bot_training_roster([1,0,0,0,0,0],bot_fixture_rows([1=>12]))===[1,0,0,0,0,0],'a single starter is never duplicated');
bot_check(pv_bot_training_roster([0,0,0,0,0,0],[])===[0,0,0,0,0,0],'an empty roster does not invent specimen IDs');
foreach([1,25,97] as $index){
    $profile=pv_bot_activity_profile($index);
    foreach([0,6,36,42,48,1000] as $total){
        $chance=pv_bot_capture_chance($total,$profile);
        bot_check($chance>0&&$chance<=92,'capture remains possible at every collection size for profile '.$index);
        bot_check($chance===pv_bot_capture_chance(0,$profile),'collection retention never suppresses capture-intent success for profile '.$index);
    }
    for($cursor=0;$cursor<8;$cursor++){
        $intent=pv_bot_wild_intent(['bot_index'=>$index,'wild_encounters'=>$cursor]);
        $nextIntent=pv_bot_wild_intent(['bot_index'=>$index,'wild_encounters'=>$cursor+1]);
        bot_check($intent!==$nextIntent,'every pair of committed encounters contains training and hunting for profile '.$index);
    }
    for($combatRoll=1;$combatRoll<=100;$combatRoll++){
        $training=pv_bot_wild_outcome('train',20,10,$profile,$combatRoll,1);
        $hunting=pv_bot_wild_outcome('capture',20,10,$profile,$combatRoll,1);
        bot_check($training!=='caught_wild','a training turn cannot be replaced by a catch');
        bot_check(($training==='lost_wild')===($hunting==='lost_wild'),'hunting never conceals a combat loss');
    }
}
bot_check(pv_bot_wild_intent(['bot_index'=>1,'wild_encounters'=>0])!==pv_bot_wild_intent(['bot_index'=>2,'wild_encounters'=>0]),'fresh bots are staggered across hunting and training');
$profile=pv_bot_activity_profile(97);
bot_check(pv_bot_wild_outcome('train',1,14,$profile,54,1)==='won_wild','a weak team can legitimately win at its strength boundary');
bot_check(pv_bot_wild_outcome('train',1,14,$profile,55,1)==='lost_wild','a weak team can legitimately lose past its strength boundary');
bot_check(pv_bot_wild_outcome('train',100,14,$profile,97,1)==='won_wild'&&pv_bot_wild_outcome('train',100,14,$profile,98,1)==='lost_wild','strong teams retain the existing bounded simulated combat odds');
bot_check(pv_bot_wild_outcome('capture',20,10,$profile,1,82)==='caught_wild'&&pv_bot_wild_outcome('capture',20,10,$profile,1,83)==='won_wild','failed capture attempts resolve the successful combat as a defeat');
bot_check(pv_bot_wild_outcome('capture',20,10,$profile,100,1)==='lost_wild','a failed combat cannot catch or earn defeat EXP');
bot_check(pv_bot_ranked_remaining(15,4)===1&&pv_bot_ranked_remaining(16,16)===0,'ranked batches preserve the shared sixteen-match minute quota');
bot_check(pv_bot_ranked_cycle(119)['refresh_at']===120&&pv_bot_ranked_cycle(120)['refresh_at']===180,'ranked cycles advance at the exact minute boundary');

if(!in_array('--integration',$argv,true)){echo 'PASS '.$checks.' bot runtime policy checks'.PHP_EOL;exit;}
$name=(string)getenv('PV_TEST_DB_NAME');
if(!preg_match('/^pv_test_[a-z0-9_]+$/D',$name))throw new RuntimeException('Integration requires a disposable PV_TEST_DB_NAME beginning pv_test_.');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);

/** Delay a real candidate query to reproduce the old setup-budget starvation. */
class BotSlowSetupDatabase extends mysqli
{
    public bool $delayCandidate=false;
    public bool $failPowerOnce=false;
    public function query(string $query,int $result_mode=MYSQLI_STORE_RESULT):mysqli_result|bool
    {
        if($this->delayCandidate&&str_contains($query,'rank_last_attack_at')){$this->delayCandidate=false;usleep(220000);}
        if($this->failPowerOnce&&str_contains($query,'COALESCE(AVG(lvl),1)')){$this->failPowerOnce=false;throw new RuntimeException('Injected single-candidate power read failure.');}
        return parent::query($query,$result_mode);
    }
}
$db=new BotSlowSetupDatabase((string)(getenv('PV_TEST_DB_HOST')?:'localhost'),(string)(getenv('PV_TEST_DB_USER')?:'root'),(string)(getenv('PV_TEST_DB_PASSWORD')?:''),$name,(int)(getenv('PV_TEST_DB_PORT')?:3306),(string)(getenv('PV_TEST_DB_SOCKET')?:''));
$db->set_charset('utf8mb4');
// All autonomous actors in this disposable fixture are controlled below.
$db->query('UPDATE bot_trainers SET enabled=0');
$db->query('DELETE FROM rival_battles');
$db->query('DELETE FROM rival_retaliations');
$uid=pv_bot_seed_one($db,1,['vortex','1']);$rival=pv_bot_seed_one($db,2,['vortex','1']);
$db->query('UPDATE bot_trainers SET enabled=1,ranked_retry_at=0 WHERE user_id IN ('.$uid.','.$rival.')');
$bot=pv_bot_profile($db,$uid);$username=(string)$bot['username'];
$guide=pv_bot_population_row($db,'SELECT id,name,type1,type2,a1,a2,a3,a4 FROM pguide WHERE name=?','s',['Bulbasaur']);
bot_check(is_array($guide),'species data exists in the fixture');
$db->query("DELETE ps FROM pokemon_stats ps JOIN pokemon p ON p.id=ps.id WHERE p.owner='".$uid."'");
$db->query("DELETE FROM pokemon WHERE owner='".$uid."'");
$ids=[];
for($i=0;$i<8;$i++)$ids[]=pv_bot_create_pokemon($db,$uid,$username,$guide,$i<6?100:20+$i);
pv_bot_population_write($db,'UPDATE members SET s1=?,s2=?,s3=?,s4=?,s5=?,s6=?,total_poke=8 WHERE id=?','iiiiiii',[...array_slice($ids,0,6),$uid]);
$team=pv_bot_refresh_training_team($db,$uid);
bot_check($team===[...array_slice($ids,0,5),$ids[7]],'database training roster rotates the capped active team');
$before=pv_bot_population_row($db,'SELECT * FROM pokemon WHERE id=?','i',[$ids[7]]);
$reward=pv_bot_award_wild_training($db,$uid,'Pidgey',10);
$after=pv_bot_population_row($db,'SELECT * FROM pokemon WHERE id=?','i',[$ids[7]]);
$expected=pv_exp_battle_gain('Pidgey',10);
bot_check((int)$after['exp']-(int)$before['exp']===$expected&&$reward['exp']===$expected,'one trainable participant receives exactly the ROM species yield');
$capped=pv_bot_population_row($db,'SELECT exp,lvl FROM pokemon WHERE id=?','i',[$ids[0]]);
bot_check((int)$capped['lvl']===100&&(int)$capped['exp']===pv_exp_at_level('Bulbasaur',100),'capped anchors never accumulate overflowing EXP');
foreach($team as $id)pv_bot_population_write($db,'UPDATE pokemon SET lvl=20,exp=?,exp_curve_version=1 WHERE id=?','ii',[pv_exp_at_level('Bulbasaur',20),$id],false);
$reward=pv_bot_award_wild_training($db,$uid,'Pidgey',10);
bot_check($reward['exp']===count($team)*pv_exp_battle_gain('Pidgey',10,count($team)),'team reward is shared rather than multiplied by six');

// Retirement is transactionally safe and cannot select human-origin gifts,
// named reserves, active slots, or the oldest companion.
$db->query('UPDATE pokemon SET lvl=100,exp='.pv_exp_at_level('Bulbasaur',100).' WHERE id IN ('.implode(',',$ids).')');
pv_bot_population_write($db,'UPDATE pokemon_stats SET nickname=? WHERE id=?','si',['Keep',$ids[5]],false);
pv_bot_population_write($db,'UPDATE pokemon SET ot=? WHERE id=?','si',['HumanGift',$ids[6]],false);
$db->begin_transaction();
bot_check(!pv_bot_make_capture_room($db,$uid,$username,8),'full protected collections use catch-and-release');$db->rollback();
pv_bot_population_write($db,'UPDATE pokemon_stats SET nickname=? WHERE id=?','si',['',$ids[5]],false);
$db->begin_transaction();
bot_check(pv_bot_make_capture_room($db,$uid,$username,8),'one trained bot-native reserve makes room for a catch');
bot_check(pv_bot_population_row($db,'SELECT id FROM pokemon WHERE id=?','i',[$ids[5]])===null,'only the eligible reserve is retired');
bot_check(pv_bot_population_row($db,'SELECT id FROM pokemon WHERE id=?','i',[$ids[6]])!==null,'a human-origin gift is preserved');
$db->rollback();
bot_check(pv_bot_population_row($db,'SELECT id FROM pokemon WHERE id=?','i',[$ids[5]])!==null,'failed/retried captures can roll back the reserve retirement');
$db->begin_transaction();
pv_bot_population_write($db,'INSERT INTO upfortrade (pid,owner) VALUES (?,?)','ii',[$ids[5],$uid]);
bot_check(!pv_bot_make_capture_room($db,$uid,$username,8),'a listed reserve is never retired');$db->rollback();

// Ranked resolves with nobody logged in and must attempt one operation even
// when setup exceeds its soft budget. Repeat until the shared quota is full.
// Keep the quota assertion in one actual minute even on a slow CI database.
if(time()%60>45)sleep(61-time()%60);
pv_rival_ensure_all_states($db);
$db->query('UPDATE trainer_rank_state SET shield_until=0,last_attack_at=0');
$db->delayCandidate=true;
bot_check(pv_bot_ranked_pulse($db,1,150)===1,'ranked progresses after deliberately slow setup without a browser');
$db->query('UPDATE trainer_rank_state SET shield_until=0,last_attack_at=0');
$db->failPowerOnce=true;
bot_check(pv_bot_ranked_pulse($db,1,1500)===1,'one broken ranked candidate does not terminate the pulse');
$retry=$db->query('SELECT COUNT(*) n FROM bot_trainers WHERE ranked_retry_at>'.time());
bot_check((int)$retry->fetch_assoc()['n']>=1,'failed ranked candidates receive a persisted retry deadline');
for($i=0;$i<20;$i++){
    $db->query('UPDATE trainer_rank_state SET shield_until=0,last_attack_at=0');
    $db->query('UPDATE bot_trainers SET ranked_retry_at=0 WHERE user_id IN ('.$uid.','.$rival.')');
    pv_bot_ranked_pulse($db,4,500);
}
$cycle=pv_bot_ranked_cycle();$result=$db->query("SELECT COUNT(*) n FROM rival_battles WHERE source='autonomous' AND created_at>=".$cycle['bucket_start'].' AND created_at<'.$cycle['refresh_at']);
bot_check((int)$result->fetch_assoc()['n']===16,'repeated pulses stop exactly at sixteen committed matches in one minute');
bot_check(pv_bot_ranked_pulse($db,16,500)===0,'a completed minute quota cannot award duplicate extra matches');

// World work is independent of Ranked quota and honors per-account pauses.
$now=time();
$db->query('UPDATE bot_trainers SET next_action_at='.($now-3600).',last_action_at=0 WHERE user_id='.$uid);
$db->query('UPDATE bot_trainers SET next_action_at='.($now-10).',last_action_at=0,world_key=\'vortex\',map_key=\'2\' WHERE user_id='.$rival);
bot_check(pv_bot_tick($db,1,'vortex','2',500,false)===1,'offline world tick processes a due trainer');
$row=pv_bot_profile($db,$uid);bot_check((int)$row['last_action_at']>0,'oldest overdue work wins over the viewed-map preference');
$db->query('UPDATE bot_trainers SET enabled=0 WHERE user_id='.$uid);
$db->query('UPDATE bot_trainers SET next_action_at=0,last_action_at=0 WHERE user_id='.$rival);
$db->query('INSERT INTO console_player_state (user_id,frozen) VALUES ('.$rival.',1) ON DUPLICATE KEY UPDATE frozen=1');
bot_check(pv_bot_tick($db,10,'','',500,false)===0,'disabled and frozen trainers remain intentionally paused');
$db->query('UPDATE console_player_state SET frozen=0 WHERE user_id='.$rival);
bot_check(pv_bot_tick($db,10,'','',500,false)===1,'unfreezing resumes the existing trainer');
echo 'PASS '.$checks.' bot runtime checks including real database integration'.PHP_EOL;
