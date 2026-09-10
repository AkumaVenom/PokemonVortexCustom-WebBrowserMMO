<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !defined('PV_SERVER_CONSOLE_CLI')) { http_response_code(404); exit; }
require_once __DIR__.'/runtime.php';
require_once dirname(__DIR__).'/gameplay.php';

function pv_admin_accounts_specs(): array
{
    $specs = [];
    $add = static function(string $command, string $usage, string $summary, string $rank, int $min, ?int $max, bool $confirm=false) use (&$specs): void {
        $specs[$command] = ['usage'=>$usage,'summary'=>$summary,'rank'=>$rank,'min'=>$min,'max'=>$max,'confirm'=>$confirm,'dev'=>false];
    };
    foreach (['profile'=>'Show public trainer and collection information.','stats'=>'Show authoritative trainer statistics.','playtime'=>'Show observed active time since console tracking was installed.'] as $name=>$summary) $add($name, $name.' [player|#ID]', $summary, 'PLAYER', 0, 1);
    $add('playerinfo','playerinfo [player|#ID]','Show account, rank and moderation state.','MODERATOR',0,1);
    foreach (['afk'=>'Set the selected or named trainer AFK.','back'=>'Clear the selected or named trainer AFK status.'] as $name=>$summary) $add($name,$name.' [player|#ID]',$summary,'PLAYER',0,1);
    $add('save','save [player|#ID]','Recalculate and commit trainer progression; requests already persist gameplay automatically.','ADMIN',0,1);
    $add('resetplayer','resetplayer <player|#ID>','Reset progression, collection, inventory, clan membership and world position to a level-18 Bulbasaur start; retain identity, credentials and moderation.','ADMIN',1,1,true);
    $add('warn','warn <player|#ID> <reason>','Record a moderation warning (no chat/message is sent).','MODERATOR',2,null);
    $add('warnings','warnings <player|#ID>','List the latest 50 recorded warnings.','MODERATOR',1,1);
    $add('kick','kick <player|#ID> <reason>','Revoke all active sessions; the trainer can sign in again.','MODERATOR',2,null);
    $add('ban','ban <player|#ID> <duration|permanent> <reason>','Ban and revoke sessions. Duration: 30s, 15m, 2h, 7d or permanent.','MODERATOR',3,null,true);
    $add('unban','unban <player|#ID>','Remove a temporary, permanent or legacy account ban.','MODERATOR',1,1);
    $add('jail','jail <player|#ID> <duration|permanent> <reason>','Place a trainer in the administrative holding area; all gameplay is blocked until release.','MODERATOR',3,null,true);
    $add('unjail','unjail <player|#ID>','Release a trainer from the administrative holding area.','MODERATOR',1,1);
    foreach (['freeze'=>'Suspend movement and gameplay actions.','unfreeze'=>'Resume movement and gameplay actions.'] as $name=>$summary) $add($name,$name.' <player|#ID>',$summary,'MODERATOR',1,1);
    foreach (['invisible'=>'Hide trainer presence on maps and public online lists.','visible'=>'Restore trainer map and public online visibility.'] as $name=>$summary) $add($name,$name.' [player|#ID]',$summary,'GAME MASTER',0,1);
    $add('ipinfo','ipinfo <player|#ID>','Show the last direct connection address and user agent; never trusts forwarded headers.','ADMIN',1,1);
    $add('history','history <player|#ID>','List the latest 100 account and moderation actions.','MODERATOR',1,1);
    $add('createaccount','createaccount <username>','Create a complete trainer with a level-18 starter and a generated password shown once.','ADMIN',1,1);
    $add('deleteaccount','deleteaccount <player|#ID>','Delete account and owned progression atomically; return other trainers’ escrow and preserve administration audit.','OWNER',1,1,true);
    $add('resetpassword','resetpassword <player|#ID>','Generate a replacement password, invalidate reset links and revoke sessions; display secret once.','ADMIN',1,1,true);
    $add('setrank','setrank <player|#ID> <rank>','Set stored trainer rank. Browser accounts never receive console access.','OWNER',2,3,true);
    $add('promote','promote <player|#ID>','Advance trainer rank by one tier; does not grant browser console access.','OWNER',1,1,true);
    $add('demote','demote <player|#ID>','Lower trainer rank by one tier.','OWNER',1,1,true);
    $add('lockaccount','lockaccount <player|#ID>','Lock account login and revoke current sessions.','ADMIN',1,1,true);
    $add('unlockaccount','unlockaccount <player|#ID>','Unlock account login; existing bans remain enforced.','ADMIN',1,1);
    return $specs;
}

function pv_admin_account_ranks(): array { return ['PLAYER','VIP','HELPER','MODERATOR','GAME MASTER','ADMIN','DEVELOPER','OWNER']; }

function pv_admin_duration(string $text): int
{
    $text = strtolower(trim($text));
    if (in_array($text, ['permanent','perm','forever'], true)) return -1;
    if (!preg_match('/^([1-9][0-9]{0,8})(s|m|h|d|w)$/', $text, $match)) throw new InvalidArgumentException('Duration must be 30s, 15m, 2h, 7d, 1w or permanent.');
    $seconds = (int)$match[1] * ['s'=>1,'m'=>60,'h'=>3600,'d'=>86400,'w'=>604800][$match[2]];
    if ($seconds > 315360000) throw new InvalidArgumentException('Duration may not exceed ten years. Use permanent for an indefinite restriction.');
    return time() + $seconds;
}

function pv_admin_moderation_record(array $player, string $action, string $reason, array $context, int $expires = 0): void
{
    $reason = trim($reason);
    if (strlen($reason) > 1000) throw new InvalidArgumentException('Reason must be no more than 1000 bytes.');
    pv_admin_exec('INSERT INTO console_moderation (user_id,username,action_name,reason,operator_name,created_at,expires_at) VALUES (?,?,?,?,?,?,?)', [(int)$player['id'],(string)$player['username'],$action,$reason,(string)($context['operator']??'local'),time(),$expires]);
}

function pv_admin_account_delete_where(string $table, array $columns, int $uid): void
{
    if (!pv_table_exists($table)) return;
    // Caller supplies private static table/column lists; no command input is an SQL identifier.
    $available = [];
    $rows = pv_admin_rows('SHOW COLUMNS FROM `'.$table.'`');
    $names = array_column($rows, 'Field');
    foreach ($columns as $column) if (in_array($column, $names, true)) $available[] = $column;
    if (!$available) return;
    pv_admin_exec('DELETE FROM `'.$table.'` WHERE '.implode(' OR ', array_map(static fn($column)=>'`'.$column.'`=?', $available)), array_fill(0,count($available),$uid));
}

/** Return all escrow before removing ownership so unrelated trainers never lose Pokémon. */
function pv_admin_account_release_trades(int $uid): void
{
    $db = pv_admin_db(); $affected = []; $listings = [];
    $offers = pv_admin_rows("SELECT * FROM trade_offers WHERE (listing_owner_id=? OR offerer_id=?) AND status='pending' ORDER BY id FOR UPDATE", [$uid,$uid]);
    foreach ($offers as $offer) {
        foreach (pv_trade_release_offer_items_locked($db, (int)$offer['id']) as $owner) $affected[(int)$owner] = true;
        pv_admin_exec("UPDATE trade_offers SET status='cancelled',resolved_at=? WHERE id=? AND status='pending'", [time(),(int)$offer['id']]);
        $listings[(int)$offer['listing_id']] = true;
    }
    foreach (pv_admin_rows('SELECT id,pid FROM upfortrade WHERE owner=? ORDER BY id FOR UPDATE', [$uid]) as $listing) {
        // The normal trade flow locks its listing before accepting. Locking here closes that race.
        $pid = (int)$listing['pid'];
        $row = pv_admin_row('SELECT id,owner FROM pokemon WHERE id=? FOR UPDATE', [$pid]);
        if (!$row || (int)$row['owner'] !== 0) throw new RuntimeException('A listing contains inconsistent escrow. Repair the listing before resetting/deleting this trainer.');
        // Include offers arriving before the listing row lock was acquired.
        foreach (pv_admin_rows("SELECT id FROM trade_offers WHERE listing_id=? AND status='pending' FOR UPDATE", [(int)$listing['id']]) as $pending) {
            foreach (pv_trade_release_offer_items_locked($db, (int)$pending['id']) as $owner) $affected[(int)$owner] = true;
            pv_admin_exec("UPDATE trade_offers SET status='cancelled',resolved_at=? WHERE id=?", [time(),(int)$pending['id']]);
        }
        pv_admin_exec('UPDATE pokemon SET owner=? WHERE id=? AND CAST(owner AS UNSIGNED)=0', [$uid,$pid]);
        pv_admin_exec('DELETE FROM upfortrade WHERE id=?', [(int)$listing['id']]);
    }
    foreach (array_keys($listings) as $listingId) pv_trade_refresh_listing_offer_count($db, (int)$listingId);
    // Historic offers are normally migrated out of utraded; handle a recovered import too.
    if (pv_table_exists('utraded')) {
        foreach (pv_admin_rows('SELECT t.id,t.owner FROM utraded t LEFT JOIN pokemon p ON p.id=t.oid WHERE t.owner=? OR CAST(p.owner AS UNSIGNED)=? FOR UPDATE', [$uid,$uid]) as $legacy) {
            if (pv_admin_exec('UPDATE pokemon SET owner=? WHERE id=? AND CAST(owner AS UNSIGNED)=0', [(int)$legacy['owner'],(int)$legacy['id']]) > 0) $affected[(int)$legacy['owner']] = true;
            pv_admin_exec('DELETE FROM utraded WHERE id=?', [(int)$legacy['id']]);
        }
    }
    foreach (array_keys($affected) as $owner) {
        if ($owner === $uid) continue;
        pv_recalculate_trainer_progress($db, $owner, false);
        pv_admin_touch($owner);
    }
}

/** Transfer clan leadership deterministically; disband only when nobody remains. */
function pv_admin_account_leave_clans(array $player): void
{
    $uid=(int)$player['id']; $username=(string)$player['username'];
    foreach (pv_admin_rows('SELECT * FROM clans WHERE owner=? ORDER BY id FOR UPDATE', [$username]) as $clan) {
        $cid=(int)$clan['id']; $name=(string)$clan['name'];
        $successor=pv_admin_row('SELECT c.id,m.username FROM clan_members c JOIN members m ON m.id=c.id WHERE (c.clan_id=? OR c.clan=? OR c.clan_name=?) AND c.id<>? ORDER BY c.id LIMIT 1 FOR UPDATE', [$cid,$name,$name,$uid]);
        if ($successor) {
            pv_admin_exec('UPDATE clans SET owner=? WHERE id=?', [(string)$successor['username'],$cid]);
            pv_admin_exec('UPDATE clan_members SET owner=CASE WHEN id=? THEN 1 ELSE 0 END WHERE clan_id=? OR clan=? OR clan_name=?', [(int)$successor['id'],$cid,$name,$name]);
            pv_admin_touch((int)$successor['id']);
        } else {
            // Heal stale legacy clan membership strings as well as normalized rows.
            foreach (pv_admin_rows('SELECT id FROM members WHERE clan_name=? AND id<>?', [$name,$uid]) as $orphan) {
                pv_admin_exec("UPDATE members SET clan_name='',clan_tag='' WHERE id=?", [(int)$orphan['id']]); pv_admin_touch((int)$orphan['id']);
            }
            pv_admin_exec('DELETE FROM clan_members WHERE clan_id=? OR clan=? OR clan_name=?', [$cid,$name,$name]);
            pv_admin_exec('DELETE FROM clan_applications WHERE clan_id=?', [$cid]);
            pv_admin_exec('DELETE FROM clan_requests WHERE clan=?', [$name]);
            pv_admin_exec('DELETE FROM clans WHERE id=?', [$cid]);
        }
    }
    pv_admin_exec('DELETE FROM clan_members WHERE id=?', [$uid]);
    pv_admin_exec('DELETE FROM clan_applications WHERE user_id=?', [$uid]);
    pv_admin_exec('DELETE FROM clan_requests WHERE id=?', [$uid]);
    pv_admin_exec("UPDATE members SET clan_name='',clan_tag='' WHERE id=?", [$uid]);
    if (!empty($player['clan_name'])) pv_recalculate_clan_progress(pv_admin_db(), (string)$player['clan_name']);
}

function pv_admin_account_cancel_battles(int $uid): void
{
    foreach (pv_admin_rows('SELECT id,uid_1,uid_2 FROM live_battle WHERE uid_1=? OR uid_2=? FOR UPDATE', [$uid,$uid]) as $battle) {
        $other=(int)$battle['uid_1']===$uid ? (int)$battle['uid_2'] : (int)$battle['uid_1'];
        if ($other>0 && pv_admin_row('SELECT id FROM members WHERE id=?', [$other])) pv_admin_touch($other);
    }
    pv_admin_account_delete_where('live_battle',['uid_1','uid_2'],$uid);
    pv_admin_account_delete_where('live_battle_challenges',['challenger_id','target_id'],$uid);
    pv_admin_account_delete_where('live_battle_members',['userid','your_id'],$uid);
    pv_admin_account_delete_where('live_battle_offer',['userid','your_id'],$uid);
}

function pv_admin_account_clear_character(array $player): void
{
    $uid=(int)$player['id'];
    pv_admin_account_release_trades($uid);
    pv_admin_account_leave_clans($player);
    pv_admin_account_cancel_battles($uid);
    foreach (pv_admin_rows('SELECT pid,COUNT(*) AS n FROM pokemon WHERE owner=? GROUP BY pid', [$uid]) as $count) {
        pv_admin_exec('UPDATE pguide SET amount=GREATEST(0,COALESCE(amount,0)-?) WHERE id=?', [(int)$count['n'],(int)$count['pid']]);
    }
    pv_admin_exec('DELETE s FROM pokemon_stats s JOIN pokemon p ON p.id=s.id WHERE p.owner=?', [$uid]);
    pv_admin_exec('DELETE FROM pokemon WHERE owner=?', [$uid]);
    foreach ([
        'items'=>['uid'], 'badges'=>['id'], 'events'=>['id'], 'event_completions'=>['user_id'],
        'world_map_positions'=>['user_id'], 'world_map_blocks'=>['user_id'], 'mapusers'=>['id'], 'online'=>['id'],
        'trainer_rank_state'=>['user_id'], 'rival_retaliations'=>['attacker_id','defender_id'],
        'wild_battle_results'=>['user_id'], 'shop_transactions'=>['user_id'], 'move_lab_transactions'=>['user_id'],
        'console_event_participants'=>['user_id'], 'console_event_rewards'=>['user_id'], 'console_player_titles'=>['user_id'], 'console_world_spawns'=>['user_id'],
        'console_test_battles'=>['player_id','opponent_id'],
    ] as $table=>$columns) pv_admin_account_delete_where($table,$columns,$uid);
    if (pv_table_exists('done_event')) pv_admin_exec('DELETE FROM done_event WHERE username=?', [(string)$player['username']]);
    pv_admin_exec('UPDATE members SET s1=0,s2=0,s3=0,s4=0,s5=0,s6=0,money=0,battle=0,wins=0,losses=0,averageexp=0,totalexp=0,badges=0,uniques=0,points=0,sidequest=1,total_poke=0,btime=0,sv=0,sv2=0,ev=0,ev2=0 WHERE id=?', [$uid]);
}

function pv_admin_account_defaults(int $uid): void
{
    foreach ([['items','uid'],['badges','id'],['events','id']] as [$table,$column]) pv_admin_exec('INSERT INTO `'.$table.'` (`'.$column.'`) VALUES (?)', [$uid]);
}

function pv_admin_account_starter(int $uid): int
{
    if (!function_exists('pv_admin_give_pokemon')) throw new RuntimeException('The console Pokémon module is required to initialize a complete trainer.');
    $player=pv_admin_row('SELECT * FROM members WHERE id=?',[$uid]);
    $id=pv_admin_give_pokemon($player,pv_admin_species('Bulbasaur'),18);
    // Preserve the accepted signup contract: every fresh character starts at 18 / 9,000 EXP.
    pv_admin_exec('UPDATE pokemon SET exp=9000 WHERE id=?',[$id]);
    pv_recalculate_trainer_progress(pv_admin_db(),$uid,false);
    return $id;
}

function pv_admin_accounts_handle(string $command, array $args, array &$context): array
{
    if ($command==='createaccount') {
        $username=trim((string)$args[0]);
        if (!preg_match('/^[A-Za-z0-9_-]{3,24}$/',$username)) throw new InvalidArgumentException('Username must contain 3–24 letters, numbers, underscores or dashes.');
        $password=bin2hex(random_bytes(12));
        $nameLock=pv_admin_account_name_lock(pv_admin_db(),$username);
        try {
        $uid=pv_admin_transaction(static function() use($username,$password,$context):int {
            if (pv_admin_row('SELECT id FROM members WHERE username=? FOR UPDATE',[$username])) throw new InvalidArgumentException('That trainer name is already in use.');
            $now=time(); $email='console+'.bin2hex(random_bytes(12)).'@local.invalid';
            pv_admin_exec("INSERT INTO members (username,password,email,registered,llogin,last_login,ip,eb,number,secret_key,total_poke,sidequest,banned) VALUES (?,?,?,?,?,?,?,?,?,?,0,1,'0')",[$username,pv_password_hash($password),$email,(string)$now,0,'0','local-console','1',(string)random_int(1,18),bin2hex(random_bytes(20))]);
            $id=(int)pv_admin_db()->insert_id;
            pv_admin_exec("INSERT INTO members_options (id,trainer,forum,skype,display,memonmap,messonoff,messnotifyonoff,layout) VALUES (?,1,'','','No',1,0,0,2)",[$id]);
            pv_admin_exec('INSERT INTO comments (userid) VALUES (?)',[$id]);
            pv_admin_account_defaults($id); pv_admin_player_state($id); pv_admin_account_starter($id);
            pv_admin_moderation_record(['id'=>$id,'username'=>$username],'createaccount','Complete local account created.',$context);
            return $id;
        });
        } finally { pv_admin_account_name_unlock(pv_admin_db(),$nameLock); }
        return ['Created '.$username.' (#'.$uid.') with a level-18 Bulbasaur and complete account defaults.','Generated password (shown once): '.$password,'Set a real email address in account options. This password is omitted from console audit and history.'];
    }
    $player=pv_admin_player((string)($args[0]??''),$context); $uid=(int)$player['id']; $label=(string)$player['username'].' (#'.$uid.')';
    $state=pv_admin_expire_restrictions(pv_admin_db(),$uid,pv_admin_player_state($uid));
    switch ($command) {
        case 'profile': case 'stats': case 'playerinfo':
            $stats=pv_admin_row('SELECT COUNT(*) AS owned,COUNT(DISTINCT pid) AS species,COALESCE(SUM(exp),0) AS exp FROM pokemon WHERE owner=?',[$uid]);
            $lines=[$label.' | Rank: '.$state['rank_name'], 'Collection: '.$stats['owned'].' Pokémon / '.$stats['species'].' distinct catalogue entries | EXP: '.$stats['exp'], 'Money: '.(int)$player['money'].' | Wins: '.(int)$player['wins'].' | Losses: '.(int)$player['losses'].' | Sidequest: '.(int)$player['sidequest'], 'Clan: '.((string)($player['clan_name']??'')?:'none').' | '.((int)$state['afk']?'AFK':'Available')];
            if($command==='playerinfo') { $lines[]='Locked: '.(int)$state['locked'].' | Frozen: '.(int)$state['frozen'].' | Invisible: '.(int)$state['invisible'].' | Ban: '.pv_admin_expiry_label((int)$state['ban_until']).' | Jail: '.pv_admin_expiry_label((int)$state['jail_until']); $lines[]='Last login: '.((int)$player['llogin']>0?gmdate('Y-m-d H:i:s',(int)$player['llogin']).' UTC':'never').' | Session generation: '.(int)$state['session_epoch'].' | Data revision: '.(int)$state['data_revision']; }
            return $lines;
        case 'playtime':
            $seconds=(int)$state['play_seconds']; return [$label.': '.intdiv($seconds,3600).'h '.intdiv($seconds%3600,60).'m '.($seconds%60).'s observed active time.','Tracking begins with this update; AFK time and request gaps over five minutes are excluded. Historical playtime is unavailable.'];
        case 'warnings': case 'history':
            $rows=pv_admin_rows('SELECT * FROM console_moderation WHERE user_id=?'.($command==='warnings'?" AND action_name='warn'":'').' ORDER BY id DESC LIMIT '.($command==='warnings'?'50':'100'),[$uid]);
            $lines=[$label.' — '.($command==='warnings'?'warnings':'account/moderation history')];
            foreach($rows as $row) $lines[]='#'.$row['id'].' '.gmdate('Y-m-d H:i:s',(int)$row['created_at']).' UTC | '.$row['action_name'].' | '.$row['operator_name'].' | '.$row['reason'].((int)$row['expires_at']!==0?' | until '.pv_admin_expiry_label((int)$row['expires_at']):'');
            if(!$rows)$lines[]='No recorded entries.'; return $lines;
        case 'ipinfo':
            $online=pv_admin_row('SELECT useragent,time,server FROM online WHERE id=?',[$uid]);
            return [$label,'Last direct address: '.((string)($player['ip']??'')?:'unavailable'),'Last user agent: '.((string)($online['useragent']??'')?:'unavailable'),'Connection information is the last recorded request, not live geolocation.'];
        case 'save':
            pv_admin_transaction(static function()use($uid):void{pv_admin_player('#'.$uid,[],true);pv_recalculate_trainer_progress(pv_admin_db(),$uid,false);});
            return [$label.': authoritative collection/progression counters committed. Gameplay already saves during each successful request; unsubmitted browser actions cannot be saved from the console.'];
        case 'resetplayer': case 'deleteaccount':
            pv_admin_transaction(static function()use($uid,$command,$context):void{
                $p=pv_admin_player('#'.$uid,[],true);$s=pv_admin_player_state($uid,true);
                pv_admin_moderation_record($p,$command,$command==='resetplayer'?'Character reset to the documented level-18 starter defaults.':'Account and gameplay relationships deleted; audit retained.',$context);
                pv_admin_account_clear_character($p);
                if($command==='resetplayer') {
                    pv_admin_account_defaults($uid);pv_admin_account_starter($uid);
                    pv_admin_state_update($uid,['session_epoch'=>(int)$s['session_epoch']+1,'data_revision'=>(int)$s['data_revision']+1,'location_revision'=>(int)$s['location_revision']+1,'location_json'=>null,'afk'=>0,'invisible'=>0,'title'=>'','pose'=>'','play_seconds'=>0,'last_seen'=>0]);
                } else {
                    // Delete every known account-owned and two-way relationship; preserve moderation/audit by design.
                    foreach([
                        'members_options'=>['id'],'comments'=>['userid'],'friends'=>['uid','fid','bid'],'blocked'=>['uid','bid'],
                        'trainer_friends'=>['user_id','friend_id'],'trainer_blocks'=>['user_id','blocked_user_id'],
                        'friend_requests'=>['sender_id','receiver_id'],'trainer_messages'=>['sender_id','receiver_id'],
                        'messages'=>['senderid','receiverid'],'message_notify'=>['id','sid'],'password_resets'=>['user_id'],
                        'rival_battles'=>['attacker_id','defender_id','winner_id','loser_id'],'ai_activity'=>['bot_user_id','related_user_id'],
                        'bot_trainers'=>['user_id'],'flashchat_connections'=>['userid'],'flashchat_users'=>['id'],
                        'console_trade_blocks'=>['user_id','target_id'], 'console_player_state'=>['user_id'],
                    ] as $table=>$columns) pv_admin_account_delete_where($table,$columns,$uid);
                    pv_admin_exec('DELETE i FROM trade_offer_items i JOIN trade_offers o ON o.id=i.offer_id WHERE o.listing_owner_id=? OR o.offerer_id=?',[$uid,$uid]);
                    pv_admin_exec('DELETE FROM trade_offer_items WHERE original_owner_id=?',[$uid]);
                    pv_admin_exec('DELETE FROM trade_offers WHERE listing_owner_id=? OR offerer_id=?',[$uid,$uid]);
                    if(pv_table_exists('reg'))pv_admin_exec('DELETE FROM reg WHERE user=?',[(string)$p['username']]);
                    pv_admin_exec('DELETE FROM login_trys WHERE username=?',[(string)$p['username']]);
                    pv_admin_exec('DELETE FROM members WHERE id=?',[$uid]);
                }
            });
            if($command==='deleteaccount'&&(int)($context['selected']??0)===$uid)$context['selected']=0;
            return [$label.($command==='deleteaccount'?' deleted. Other trainers’ escrow was returned; account audit retained.':' reset to a level-18 Bulbasaur start. Credentials, rank and moderation restrictions retained. All sessions revoked.')];
        case 'resetpassword':
            $password=bin2hex(random_bytes(12));
            pv_admin_transaction(static function()use($uid,$password,$context):void{
                $p=pv_admin_player('#'.$uid,[],true);$s=pv_admin_player_state($uid,true);
                pv_admin_exec('UPDATE members SET password=?,secret_key=? WHERE id=?',[pv_password_hash($password),bin2hex(random_bytes(20)),$uid]);
                pv_admin_exec('DELETE FROM password_resets WHERE user_id=?',[$uid]);
                pv_admin_state_update($uid,['session_epoch'=>(int)$s['session_epoch']+1]);
                pv_admin_moderation_record($p,'resetpassword','Password replaced and all sessions/reset links revoked.',$context);
            });
            return [$label.': password replaced; all active sessions and reset links revoked.','Generated password (shown once): '.$password,'This password is omitted from console audit and history.'];
    }
    return pv_admin_transaction(static function()use($command,$args,$context,$uid,$label):array{
        $player=pv_admin_player('#'.$uid,[],true);$state=pv_admin_player_state($uid,true);$changes=[];$reason='';$expiry=0;
        switch($command) {
            case 'warn':case 'kick':
                $reason=trim(implode(' ',array_slice($args,1)));if($reason==='')throw new InvalidArgumentException('A reason is required.');
                if($command==='kick')$changes['session_epoch']=(int)$state['session_epoch']+1;
                break;
            case 'ban':case 'jail':
                $expiry=pv_admin_duration((string)$args[1]);$reason=trim(implode(' ',array_slice($args,2)));if($reason==='')throw new InvalidArgumentException('A reason is required.');
                $changes[$command==='ban'?'ban_until':'jail_until']=$expiry;
                if($command==='ban'){pv_admin_exec("UPDATE members SET banned='1' WHERE id=?",[$uid]);$changes['session_epoch']=(int)$state['session_epoch']+1;}
                else $changes['data_revision']=(int)$state['data_revision']+1;
                break;
            case 'unban':pv_admin_exec("UPDATE members SET banned='0' WHERE id=?",[$uid]);$changes['ban_until']=0;break;
            case 'unjail':$changes['jail_until']=0;break;
            case 'freeze':case 'unfreeze':$changes['frozen']=$command==='freeze'?1:0;if($command==='freeze')$changes['data_revision']=(int)$state['data_revision']+1;break;
            case 'afk':case 'back':$changes['afk']=$command==='afk'?1:0;$changes['last_seen']=time();break;
            case 'invisible':case 'visible':$changes['invisible']=$command==='invisible'?1:0;break;
            case 'lockaccount':case 'unlockaccount':$changes['locked']=$command==='lockaccount'?1:0;if($command==='lockaccount')$changes['session_epoch']=(int)$state['session_epoch']+1;break;
            case 'setrank':case 'promote':case 'demote':
                $ranks=pv_admin_account_ranks();$current=array_search((string)$state['rank_name'],$ranks,true);if($current===false)$current=0;
                if($command==='setrank'){$rank=strtoupper(trim(implode(' ',array_slice($args,1))));if(!in_array($rank,$ranks,true))throw new InvalidArgumentException('Rank must be one of: '.implode(', ',$ranks));}
                else{$index=$current+($command==='promote'?1:-1);if(!isset($ranks[$index]))throw new InvalidArgumentException('Trainer is already at the '.($command==='promote'?'highest':'lowest').' rank.');$rank=$ranks[$index];}
                $changes['rank_name']=$rank;$reason=(string)$state['rank_name'].' → '.$rank.'; console remains local-only.';break;
            default:throw new InvalidArgumentException('Unknown account command.');
        }
        pv_admin_state_update($uid,$changes);pv_admin_moderation_record($player,$command,$reason,$context,$expiry);
        if(in_array($command,['kick','ban','lockaccount','jail','freeze','invisible'],true)) {
            pv_admin_exec('DELETE FROM mapusers WHERE id=?',[$uid]);
            if(in_array($command,['kick','ban','lockaccount'],true))pv_admin_exec('DELETE FROM online WHERE id=?',[$uid]);
        }
        $line=$label.': '.$command.' applied.';
        if($expiry!==0)$line.=' Until '.pv_admin_expiry_label($expiry).'.';
        if(isset($changes['rank_name']))$line.=' Rank: '.$changes['rank_name'].'.';
        if($command==='warn')$line.=' Warning recorded in moderation history; no message was sent.';
        return [$line];
    });
}

function pv_admin_expiry_label(int $expiry): string
{
    return $expiry<0?'permanent':($expiry===0?'none':gmdate('Y-m-d H:i:s',$expiry).' UTC');
}
