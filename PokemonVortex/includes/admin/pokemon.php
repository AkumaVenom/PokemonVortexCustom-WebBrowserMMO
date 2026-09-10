<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !defined('PV_SERVER_CONSOLE_CLI')) { http_response_code(404); exit; }
require_once dirname(__DIR__).'/evolution.php';
require_once dirname(__DIR__).'/economy.php';
require_once dirname(__DIR__).'/combat.php';

function pv_admin_pokemon_specs(): array
{
    $s=[];
    $add=static function(string $name,string $usage,string $summary,string $rank,int $min,?int $max,bool $dev=false,bool $confirm=false) use (&$s):void {
        $s[$name]=compact('usage','summary','rank','min','max','dev','confirm');
    };
    $add('pokemon','pokemon [player] [page]','List owned Pokémon with unique specimen IDs, 30 per page. Omit player to use select.','PLAYER',0,2);
    $add('team','team [player]','Inspect the six active team slots; omit player to use select.','PLAYER',0,1);
    $add('bag','bag [player]','Show nonzero inventory counts; omit player to use select.','PLAYER',0,1);
    $add('givepokemon','givepokemon <player> <species|#catalog-ID> [level]','Create an owned specimen (default level 5, max 100), metadata and collection count; fill an empty team slot. Quote names with spaces.','GAME MASTER',2,3);
    $add('removepokemon','removepokemon <player> <owned-ID>','Permanently remove one owned specimen; rejects escrow/live battles and the last active team member.','GAME MASTER',2,2,false,true);
    $add('heal','heal [player]','Queue one full HP/status recovery for unranked PvE sessions on their next request. Idle teams already start battles healed.','GAME MASTER',0,1);
    $add('healall','healall','Queue unranked PvE team recovery for online players; skip players with protected battle/trade state.','GAME MASTER',0,0);
    foreach (['evolve','devolve'] as $c) $add($c,"$c <owned-ID> [target-species]",'Force a configured evolution '.($c==='devolve'?'parent':'child').' route, preserving variant, specimen ID, moves and metadata. Branches require target; no item cost.','GAME MASTER',1,2);
    $add('setlevel','setlevel <owned-ID> <1-100>','Set level and the matching Vortex EXP threshold (level × 500); refresh progression.','GAME MASTER',2,2);
    $add('setexp','setexp <owned-ID> <0-2000000000>','Set EXP and recompute level using Vortex EXP/500 progression capped at 100.','GAME MASTER',2,2);
    $add('learn','learn <owned-ID> <move> [slot:1-4]','Permanently teach a catalogued move. Without slot, use a blank slot; full movesets require an explicit slot.','GAME MASTER',2,3);
    $add('forget','forget <owned-ID> <move|slot:1-4>','Permanently remove a move or slot. Keep at least one move; clearmoves is sandbox-only.','GAME MASTER',2,2);
    $add('setnature','setnature <owned-ID> <nature>','Store one of the 25 specimen natures. Existing battles retain Vortex balance; test snapshots expose calculated nature stats.','GAME MASTER',2,2);
    $add('setability','setability <owned-ID> <ability>','Set a legal species ability from the recovered ability catalog. Ability remains specimen metadata as in this baseline.','GAME MASTER',2,2);
    $add('setivs','setivs <owned-ID> <HP> <ATK> <DEF> <SPA> <SPD> <SPE>','Set six IVs (0-31). Wild HP already uses HP IV; other values remain specimen metadata and sandbox stat inputs.','GAME MASTER',7,7);
    $add('setevs','setevs <owned-ID> <HP> <ATK> <DEF> <SPA> <SPD> <SPE>','Set six EVs (0-252, total ≤510), displayed in owned Pokédex and used for sandbox stats. Live balance is unchanged.','GAME MASTER',7,7);
    $add('setshiny','setshiny <owned-ID> <true|false>','Convert normal↔Shiny catalog variant; refuses to erase Dark/Metallic/Mystic/Shadow variants.','GAME MASTER',2,2);
    $add('setgender','setgender <owned-ID> <male|female|genderless>','Set specimen gender in both canonical and stats rows; applies to gender-gated evolution.','GAME MASTER',2,2);
    $add('setnickname','setnickname <owned-ID> <nickname|none>','Set a plain-text nickname (40 characters maximum), visible in owned Pokédex. Species identity and sprites stay intact.','GAME MASTER',2,2);
    $add('clonepokemon','clonepokemon <owned-ID> [recipient]','Duplicate a specimen with a new unique ID, preserving IVs/EVs/metadata. This permanently creates a real collection Pokémon.','DEVELOPER',1,2,true);
    $add('pokemoninfo','pokemoninfo <owned-ID>','Inspect species, owner, moves, metadata, IVs/EVs and derived sandbox stats.','PLAYER',1,1);
    foreach(['giveitem','removeitem','setitem'] as $c) $add($c,"$c <player> <item> <amount>",'Adjust a real inventory column (0-1,000,000 cap); exact catalog labels or column keys accepted.','GAME MASTER',3,3);
    $add('iteminfo','iteminfo [item]','Inspect an item or list all supported inventory keys.','PLAYER',0,1);
    $add('clearinventory','clearinventory <player>','Set all item quantities to zero; irreversible without a backup.','ADMIN',1,1,false,true);
    $add('money','money [player]','Show real currency balance; omit player to use select.','PLAYER',0,1);
    foreach(['givemoney','removemoney','setmoney'] as $c) $add($c,"$c <player> <amount>",'Adjust currency transactionally; maximum balance 2,000,000,000, no negative balances.', $c==='setmoney'?'ADMIN':'GAME MASTER',2,2);
    $add('economy','economy','Show player count, currency supply/min/max/average and item supply.','ADMIN',0,0);
    $add('battle','battle <opponent> [player] | battle status | battle attack <move-slot:1-4> [side:1|2]','Start/inspect/advance a persisted console-only test duel using real team snapshots and Vortex move/type data. Omitted player uses select. No rewards, rank changes, or browser battle.','GAME MASTER',1,3,true);
    foreach(['win','lose'] as $c) $add($c,$c,'Complete the current console test duel as a '.($c==='win'?'victory':'loss').' for side 1. No live rewards or results.','DEVELOPER',0,0,true);
    $add('sethp','sethp <owned-ID> <HP>','Set a fighter’s HP only in the current active console test duel (0 through max HP).','DEVELOPER',2,2,true);
    $add('setstatus','setstatus <owned-ID> <none|poison|burn|sleep|frozen|paralyzed>','Set status only in the current console test duel; attack simulation consumes status effects.','DEVELOPER',2,2,true);
    $add('addmove','addmove <owned-ID> <move> [slot:1-4]','Teach a move only to the current test-duel snapshot; real collection remains unchanged. Use learn for permanent changes.','DEVELOPER',2,3,true);
    $add('clearmoves','clearmoves <owned-ID>','Clear only a test-duel fighter’s moves; fallback Struggle remains available to simulate combat.','DEVELOPER',1,1,true);
    $s['gp']=['alias'=>'givepokemon']; $s['gi']=['alias'=>'giveitem']; $s['balance']=['alias'=>'money'];
    return $s;
}

function pv_admin_natures(): array { return ['Hardy','Lonely','Brave','Adamant','Naughty','Bold','Docile','Relaxed','Impish','Lax','Timid','Hasty','Serious','Jolly','Naive','Modest','Mild','Quiet','Bashful','Rash','Calm','Gentle','Sassy','Careful','Quirky']; }
function pv_admin_stat_fields(): array { return ['hp','attack','defense','spatk','spdef','speed']; }

function pv_admin_species(string $reference): array
{
    $reference=trim($reference);
    $row=str_starts_with($reference,'#')
        ? pv_admin_row('SELECT * FROM pguide WHERE id=?',[pv_admin_int(substr($reference,1),1,2147483647,'catalog ID')])
        : pv_admin_row('SELECT * FROM pguide WHERE name=? LIMIT 1',[$reference]);
    if(!$row) throw new InvalidArgumentException('Unknown species: '.$reference.'. Use the exact Pokédex name, quoted if it contains spaces.');
    return $row;
}

function pv_admin_owned(string $reference, array $context=[], bool $lock=false): array
{
    $id=pv_admin_int(ltrim($reference,'#'),1,2147483647,'owned Pokémon ID');
    $p=pv_admin_row('SELECT * FROM pokemon WHERE id=?'.($lock?' FOR UPDATE':''),[$id]);
    if(!$p || !ctype_digit((string)$p['owner']) || (int)$p['owner']<=0) throw new RuntimeException('That owned Pokémon is unavailable (missing or in trade escrow).');
    if(!empty($context['selected']) && (int)$context['selected']!==(int)$p['owner']) throw new RuntimeException('That Pokémon does not belong to the selected player. Select its owner or select none.');
    $stats=pv_admin_row('SELECT * FROM pokemon_stats WHERE id=?'.($lock?' FOR UPDATE':''),[$id])?:[];
    unset($stats['id'],$stats['gender'],$stats['ot'],$stats['ball']);
    return array_merge($p,$stats);
}

function pv_admin_assert_specimen_idle(array $p): void
{
    pv_admin_require_idle((int)$p['owner']);
    if(pv_admin_row('SELECT id FROM upfortrade WHERE pid=? LIMIT 1',[(int)$p['id']])
        || pv_admin_row("SELECT oi.pokemon_id FROM trade_offer_items oi JOIN trade_offers o ON o.id=oi.offer_id WHERE oi.pokemon_id=? AND o.status='pending' LIMIT 1",[(int)$p['id']])) {
        throw new RuntimeException('That Pokémon is in trade escrow; cancel its trade before editing.');
    }
}

function pv_admin_ensure_stats(int $id): void { pv_admin_exec('INSERT INTO pokemon_stats (id) VALUES (?) ON DUPLICATE KEY UPDATE id=VALUES(id)',[$id]); }

/** Called with the recipient row locked inside a caller-owned transaction. */
function pv_admin_give_pokemon(array $player,array $guide,int $level=5): int
{
    if($level<1 || $level>100) throw new InvalidArgumentException('Pokémon level must be between 1 and 100.');
    $uid=(int)$player['id']; $name=(string)$guide['name']; $username=(string)$player['username'];
    $gender=random_int(0,1)?'Male':'Female'; $ball='Poke Ball';
    $moves=[];for($i=1;$i<=4;$i++)$moves[]=trim((string)($guide['a'.$i]??''))?:'Struggle';
    pv_admin_exec('INSERT INTO pokemon (pid,name,a1,a2,a3,a4,lvl,exp,t1,t2,rowner,owner,ball,gender,ot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[(int)$guide['id'],$name,...$moves,$level,$level*500,(string)$guide['type1'],(string)($guide['type2']??''),$username,$uid,$ball,$gender,$username]);
    $id=(int)pv_admin_db()->insert_id; $ivs=[]; for($i=0;$i<6;$i++)$ivs[]=random_int(0,31);
    $nature=pv_admin_natures()[random_int(0,24)]; $ability=pv_evolution_target_ability(pv_admin_db(),$name,'');
    pv_admin_exec('INSERT INTO pokemon_stats (id,hp_iv,attack_iv,defense_iv,spatk_iv,spdef_iv,speed_iv,nature,ability,gender,ball,ot,happiness) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,...$ivs,$nature,$ability,$gender,$ball,$username,70]);
    pv_admin_exec('UPDATE pguide SET amount=COALESCE(amount,0)+1 WHERE id=?',[(int)$guide['id']]);
    for($slot=1;$slot<=6;$slot++) if((int)($player['s'.$slot]??0)<=0) {pv_admin_exec('UPDATE members SET s'.$slot.'=? WHERE id=?',[$id,$uid]);break;}
    return $id;
}

/** Prevent legacy INT aggregate overflow rather than silently truncating progress. */
function pv_admin_progress_safe(int $uid,int $additionalExp=0): void
{
    $s=pv_admin_row('SELECT COALESCE(SUM(exp),0) total FROM pokemon WHERE CAST(owner AS UNSIGNED)=?',[$uid]);
    if((int)$s['total']+$additionalExp>2000000000) throw new RuntimeException('This would exceed the supported trainer EXP total of 2,000,000,000. Reduce EXP before this change.');
}
function pv_admin_pokemon_refresh(int $uid): void { pv_admin_progress_safe($uid); pv_recalculate_trainer_progress(pv_admin_db(),$uid,false); pv_admin_touch($uid); }

function pv_admin_catalog_switch(array $p,array $guide): void
{
    pv_admin_exec('UPDATE pokemon SET pid=?,name=?,t1=?,t2=? WHERE id=?',[(int)$guide['id'],(string)$guide['name'],(string)$guide['type1'],(string)($guide['type2']??''),(int)$p['id']]);
    if((int)$p['pid']!==(int)$guide['id']) {
        pv_admin_exec('UPDATE pguide SET amount=GREATEST(0,COALESCE(amount,0)-1) WHERE id=?',[(int)$p['pid']]);
        pv_admin_exec('UPDATE pguide SET amount=COALESCE(amount,0)+1 WHERE id=?',[(int)$guide['id']]);
    }
    pv_admin_ensure_stats((int)$p['id']);
    $ability=pv_evolution_target_ability(pv_admin_db(),(string)$guide['name'],(string)($p['ability']??''));
    pv_admin_exec("UPDATE pokemon_stats SET ability=?,display_form='' WHERE id=?",[$ability,(int)$p['id']]);
}

function pv_admin_move(string $name): string
{
    $row=pv_admin_row('SELECT attack FROM attacks WHERE attack=? LIMIT 1',[$name]);
    if(!$row) throw new InvalidArgumentException('Unknown move: '.$name.'. Use its exact catalogue name, quoted when it contains spaces.');
    return (string)$row['attack'];
}
function pv_admin_move_slot(array $moves,string $move,?string $requested=null): int
{
    if($requested!==null) return pv_admin_int($requested,1,4,'move slot')-1;
    foreach($moves as $slot=>$existing) if(strcasecmp((string)$existing,$move)===0) return (int)$slot;
    foreach($moves as $slot=>$existing) if(trim((string)$existing)==='') return (int)$slot;
    throw new RuntimeException('All four move slots are occupied; specify the slot (1-4) to replace.');
}

function pv_admin_item_catalog(): array
{
    $shop=pv_shop_flat_catalog(); $byColumn=[];
    foreach($shop as $item)$byColumn[strtolower((string)$item['column'])]=$item;
    $items=[];
    foreach(pv_admin_rows('SHOW COLUMNS FROM items') as $column) {
        $key=(string)$column['Field'];
        if(in_array(strtolower($key),['id','uid'],true) || !preg_match('/int/i',(string)$column['Type']) || !preg_match('/^[A-Za-z0-9_]+$/D',$key))continue;
        $meta=$byColumn[strtolower($key)]??['label'=>pv_evolution_item_label($key),'description'=>'Recovered inventory item; usable by the existing compatible game systems.','price'=>null];
        $meta['column']=$key; $items[strtolower($key)]=$meta;
    }
    return $items;
}
function pv_admin_item(string $reference): array
{
    $key=strtolower(str_replace([' ','-','’',"'",'é'],['_','_','','','e'],trim($reference)));
    foreach(pv_admin_item_catalog() as $item) {
        $normalize=static fn(string $s):string=>strtolower(preg_replace('/[^a-z0-9]/i','',str_replace(['é','É'],['e','E'],$s))??'');
        if(strtolower((string)$item['column'])===$key || $normalize($reference)===$normalize((string)$item['label']) || $normalize($reference)===$normalize((string)$item['column'])) return $item;
    }
    throw new InvalidArgumentException('Unknown item: '.$reference.'. Run iteminfo to list supported inventory keys.');
}

function pv_admin_pokemon_info_lines(array $p): array
{
    $lines=['Owned #'.$p['id'].' | '.$p['name'].' | owner #'.$p['owner'].' | catalog #'.$p['pid'], 'Level '.$p['lvl'].' | EXP '.$p['exp'].' | '.$p['t1'].(!empty($p['t2'])?'/'.$p['t2']:''), 'Moves: '.implode(' | ',array_map(static fn($i)=>$i.': '.((string)($p['a'.$i]??'')?:'(empty)'),[1,2,3,4])), 'Nickname: '.((string)($p['nickname']??'')?:'(none)').' | Gender: '.($p['gender']??'Unknown').' | Nature: '.($p['nature']??'Unknown').' | Ability: '.($p['ability']??'Unknown')];
    foreach(['iv','ev'] as $suffix)$lines[]=strtoupper($suffix).' (HP ATK DEF SPA SPD SPE): '.implode(' ',array_map(static fn($stat)=>(string)(int)($p[$stat.'_'.$suffix]??0),pv_admin_stat_fields()));
    $lines[]='Sandbox derived stats: '.json_encode(pv_admin_test_stats($p),JSON_UNESCAPED_SLASHES).'. Base 50 test model; existing Vortex combat formulas are retained.';
    return $lines;
}

function pv_admin_pokemon_handle(string $command,array $args,array &$context): array
{
    $command=['gp'=>'givepokemon','gi'=>'giveitem','balance'=>'money'][$command]??$command;
    if(in_array($command,['battle','win','lose','sethp','setstatus','addmove','clearmoves'],true))return pv_admin_test_handle($command,$args,$context);
    if($command==='pokemoninfo')return pv_admin_pokemon_info_lines(pv_admin_owned($args[0],$context));
    if($command==='iteminfo') {
        if(!$args)return array_map(static fn($i)=>$i['column'].' — '.$i['label'],array_values(pv_admin_item_catalog()));
        $i=pv_admin_item($args[0]);return [$i['label'].' | key '.$i['column'].' | shop price '.($i['price']===null?'not sold':number_format((int)$i['price'])),$i['description']];
    }
    if($command==='economy') {
        $r=pv_admin_row('SELECT COUNT(*) players,COALESCE(SUM(money),0) supply,COALESCE(MIN(money),0) minimum,COALESCE(MAX(money),0) maximum,COALESCE(AVG(money),0) average FROM members');
        $cols=array_map(static fn($i)=>'COALESCE(`'.$i['column'].'`,0)',array_values(pv_admin_item_catalog()));
        $items=pv_admin_row('SELECT COALESCE(SUM('.implode('+',$cols).'),0) supply FROM items');
        return ['Players: '.$r['players'].' | Currency supply: '.$r['supply'].' | Minimum: '.$r['minimum'].' | Maximum: '.$r['maximum'].' | Average: '.number_format((float)$r['average'],2),'Inventory units: '.$items['supply']];
    }
    if(in_array($command,['pokemon','team','bag','money'],true)) {
        $p=pv_admin_player($args[0]??'',$context);$uid=(int)$p['id'];
        if($command==='money')return [$p['username'].' (#'.$uid.') balance: '.number_format((int)$p['money'])];
        if($command==='bag') {
            $inventory=pv_admin_row('SELECT * FROM items WHERE uid=?',[$uid])?:[];$lines=[$p['username'].' — inventory'];
            foreach(pv_admin_item_catalog() as $item)if((int)($inventory[$item['column']]??0)>0)$lines[]=$item['label'].' ['.$item['column'].']: '.(int)$inventory[$item['column']];
            if(count($lines)===1)$lines[]='Inventory is empty.';return $lines;
        }
        if($command==='team') {
            $lines=[$p['username'].' — active team'];
            for($i=1;$i<=6;$i++){ $id=(int)($p['s'.$i]??0);$row=$id>0?pv_admin_row('SELECT id,name,lvl,exp FROM pokemon WHERE id=? AND CAST(owner AS UNSIGNED)=?',[$id,$uid]):null;$lines[]=$i.'. '.($row?'#'.$id.' '.$row['name'].' Lv.'.$row['lvl'].' EXP '.$row['exp']:'(empty)'); }
            return $lines;
        }
        $page=isset($args[1])?pv_admin_int($args[1],1,1000000,'page'):1;$offset=($page-1)*30;
        $count=(int)pv_admin_row('SELECT COUNT(*) c FROM pokemon WHERE CAST(owner AS UNSIGNED)=?',[$uid])['c'];
        $lines=[$p['username'].' — '.$count.' owned; page '.$page.'/'.max(1,(int)ceil($count/30))];
        foreach(pv_admin_rows('SELECT id,name,lvl,exp FROM pokemon WHERE CAST(owner AS UNSIGNED)=? ORDER BY id LIMIT 30 OFFSET '.$offset,[$uid]) as $row)$lines[]='#'.$row['id'].' '.$row['name'].' Lv.'.$row['lvl'].' EXP '.$row['exp'];
        return $lines;
    }
    if($command==='healall') {
        $healed=0;$skipped=[];
        foreach(pv_admin_rows('SELECT DISTINCT m.id,m.username FROM online o JOIN members m ON m.id=o.id LEFT JOIN bot_trainers b ON b.user_id=m.id WHERE o.time>=? AND b.user_id IS NULL',[time()-300]) as $p) {
            try {pv_admin_pokemon_handle('heal',['#'.$p['id']],$context);$healed++;}
            catch(RuntimeException $e){$skipped[]=$p['username'].': '.$e->getMessage();}
        }
        return array_merge(['Queued PvE recovery for '.$healed.' online players.'],array_map(static fn($s)=>'Skipped '.$s,$skipped));
    }
    if(in_array($command,['givepokemon','removepokemon','heal','giveitem','removeitem','setitem','clearinventory','givemoney','removemoney','setmoney'],true)) {
        return pv_admin_transaction(function()use($command,$args,$context):array {
            $player=pv_admin_player($args[0]??'',$context,true);$uid=(int)$player['id'];pv_admin_require_idle($uid);
            if($command==='heal') {
                $state=pv_admin_player_state($uid,true);pv_admin_state_update($uid,['heal_revision'=>(int)($state['heal_revision']??0)+1]);
                return ['Queued full HP/status recovery for '.$player['username'].' on each active unranked PvE session’s next request. Teams outside battle are already fully healed.'];
            }
            if($command==='givepokemon') {
                $guide=pv_admin_species($args[1]);$level=isset($args[2])?pv_admin_int($args[2],1,100,'level'):5;
                pv_admin_progress_safe($uid,$level*500);$id=pv_admin_give_pokemon($player,$guide,$level);pv_admin_pokemon_refresh($uid);
                return ['Gave '.$guide['name'].' Lv.'.$level.' to '.$player['username'].'; owned Pokémon #'.$id.'.'];
            }
            if($command==='removepokemon') {
                $p=pv_admin_owned($args[1],[],true);if((int)$p['owner']!==$uid)throw new RuntimeException('That Pokémon is not owned by the specified player.');pv_admin_assert_specimen_idle($p);
                $ids=[];for($i=1;$i<=6;$i++)if((int)($player['s'.$i]??0)>0 && (int)$player['s'.$i]!== (int)$p['id'])$ids[]=(int)$player['s'.$i];
                if(!$ids)throw new RuntimeException('Keep at least one active team Pokémon. Add or assign another Pokémon first.');
                $slots=array_pad(array_values(array_unique($ids)),6,0);pv_admin_exec('UPDATE members SET s1=?,s2=?,s3=?,s4=?,s5=?,s6=? WHERE id=?',[...$slots,$uid]);
                pv_admin_exec('DELETE FROM pokemon_stats WHERE id=?',[(int)$p['id']]);pv_admin_exec('DELETE FROM pokemon WHERE id=?',[(int)$p['id']]);
                pv_admin_exec('UPDATE pguide SET amount=GREATEST(0,COALESCE(amount,0)-1) WHERE id=?',[(int)$p['pid']]);pv_admin_pokemon_refresh($uid);
                return ['Removed owned #'.$p['id'].' '.$p['name'].' from '.$player['username'].'.'];
            }
            if(in_array($command,['givemoney','removemoney','setmoney'],true)) {
                $amount=pv_admin_int($args[1],$command==='setmoney'?0:1,2000000000,'amount');$old=(int)$player['money'];
                $new=match($command){'givemoney'=>$old+$amount,'removemoney'=>$old-$amount,default=>$amount};
                if($new<0 || $new>2000000000)throw new RuntimeException('Resulting balance must be between 0 and 2,000,000,000; current balance '.$old.'.');
                pv_admin_exec('UPDATE members SET money=? WHERE id=?',[$new,$uid]);pv_admin_touch($uid);return [$player['username'].' balance: '.$old.' → '.$new.'.'];
            }
            pv_shop_ensure_inventory(pv_admin_db(),$uid);$inventory=pv_admin_row('SELECT * FROM items WHERE uid=? FOR UPDATE',[$uid]);
            if($command==='clearinventory') {
                $sets=array_map(static fn($i)=>'`'.$i['column'].'`=0',array_values(pv_admin_item_catalog()));pv_admin_exec('UPDATE items SET '.implode(',',$sets).' WHERE uid=?',[$uid]);pv_admin_touch($uid);return ['Cleared inventory for '.$player['username'].'.'];
            }
            $item=pv_admin_item($args[1]);$column=$item['column'];$amount=pv_admin_int($args[2],$command==='setitem'?0:1,1000000,'quantity');$old=(int)($inventory[$column]??0);
            $new=match($command){'giveitem'=>$old+$amount,'removeitem'=>$old-$amount,default=>$amount};
            if($new<0 || $new>1000000)throw new RuntimeException('Resulting item quantity must be between 0 and 1,000,000; currently '.$old.'.');
            pv_admin_exec('UPDATE items SET `'.$column.'`=? WHERE uid=?',[$new,$uid]);pv_admin_touch($uid);return [$player['username'].' — '.$item['label'].': '.$old.' → '.$new.'.'];
        });
    }
    return pv_admin_transaction(function()use($command,$args,$context):array {
        // Lock the owner before the specimen, matching team/collection transaction order.
        $snapshot=pv_admin_owned($args[0],$context);$uid=(int)$snapshot['owner'];$player=pv_admin_player('#'.$uid,[],true);
        $p=pv_admin_owned($args[0],$context,true);if((int)$p['owner']!==$uid)throw new RuntimeException('Pokémon ownership changed; retry with its current owner.');pv_admin_assert_specimen_idle($p);
        $id=(int)$p['id'];pv_admin_ensure_stats($id);$message='';
        if(in_array($command,['evolve','devolve'],true)) {
            $rules=$command==='evolve'?pv_evolution_rules_for((string)$p['name']):pv_evolution_parent_rules_for((string)$p['name']);$field=$command==='evolve'?'target':'source';
            $targets=[];foreach($rules as $rule)$targets[(string)$rule[$field]]=true;
            if(!$targets)throw new RuntimeException('No configured '.$command.' route exists for '.$p['name'].'.');
            $chosen=$args[1]??'';
            if($chosen===''){if(count($targets)!==1)throw new RuntimeException('Choose a target: '.implode(', ',array_keys($targets)).'.');$chosen=(string)array_key_first($targets);}
            [, $chosenBase]=pv_evolution_variant_parts($chosen);$match=null;foreach(array_keys($targets) as $target)if(strcasecmp($target,$chosenBase)===0)$match=$target;
            if($match===null)throw new RuntimeException('Invalid route. Available targets: '.implode(', ',array_keys($targets)).'.');
            [$prefix]=pv_evolution_variant_parts((string)$p['name']);$guide=pv_admin_species($prefix.$match);pv_admin_catalog_switch($p,$guide);$message=$p['name'].' → '.$guide['name'].' (forced route; no items consumed).';
        } elseif($command==='setlevel' || $command==='setexp') {
            $exp=$command==='setlevel'?pv_admin_int($args[1],1,100,'level')*500:pv_admin_int($args[1],0,2000000000,'EXP');$level=max(1,min(100,(int)floor($exp/500)));
            pv_admin_progress_safe($uid,$exp-(int)$p['exp']);pv_admin_exec('UPDATE pokemon SET lvl=?,exp=? WHERE id=?',[$level,$exp,$id]);$message='Level '.$level.', EXP '.$exp.'.';
        } elseif($command==='learn' || $command==='forget') {
            $moves=[];for($i=1;$i<=4;$i++)$moves[]=(string)($p['a'.$i]??'');
            if($command==='learn'){$move=pv_admin_move($args[1]);$slot=pv_admin_move_slot($moves,$move,$args[2]??null);$moves[$slot]=$move;$message='Learned '.$move.' in slot '.($slot+1).'.';}
            else {$match=false;foreach($moves as $slot=>$move)if(ctype_digit($args[1])?($slot+1===(int)$args[1]):strcasecmp($move,$args[1])===0){$moves[$slot]='';$match=true;}if(!$match)throw new RuntimeException('That move/slot is not present.');if(!array_filter($moves,static fn($s)=>trim($s)!==''))throw new RuntimeException('Keep at least one move; use clearmoves only in a test duel.');$message='Removed requested move.';}
            pv_admin_exec('UPDATE pokemon SET a1=?,a2=?,a3=?,a4=? WHERE id=?',[...$moves,$id]);
        } elseif($command==='setnature') {
            $nature=null;foreach(pv_admin_natures() as $n)if(strcasecmp($n,$args[1])===0)$nature=$n;if($nature===null)throw new InvalidArgumentException('Valid natures: '.implode(', ',pv_admin_natures()).'.');
            pv_admin_exec('UPDATE pokemon_stats SET nature=? WHERE id=?',[$nature,$id]);$message='Nature metadata: '.$nature.'.';
        } elseif($command==='setability') {
            [, $base]=pv_evolution_variant_parts((string)$p['name']);$abilities=pv_admin_row('SELECT ability1,ability2,ability3 FROM abilities WHERE name=? LIMIT 1',[$base])?:[];$match=null;foreach($abilities as $ability)if(trim((string)$ability)!=='' && strcasecmp((string)$ability,$args[1])===0)$match=$ability;
            if($match===null)throw new InvalidArgumentException('Choose a legal species ability: '.implode(', ',array_filter($abilities)).'.');pv_admin_exec('UPDATE pokemon_stats SET ability=? WHERE id=?',[$match,$id]);$message='Ability metadata: '.$match.'.';
        } elseif($command==='setivs' || $command==='setevs') {
            $suffix=$command==='setivs'?'iv':'ev';$values=[];foreach(array_slice($args,1) as $value)$values[]=pv_admin_int($value,0,$suffix==='iv'?31:252,strtoupper($suffix));
            if($suffix==='ev' && array_sum($values)>510)throw new InvalidArgumentException('Total EVs cannot exceed 510.');$sets=array_map(static fn($f)=>$f.'_'.$suffix.'=?',pv_admin_stat_fields());pv_admin_exec('UPDATE pokemon_stats SET '.implode(',',$sets).' WHERE id=?',[...$values,$id]);$message=strtoupper($suffix).'s (HP ATK DEF SPA SPD SPE): '.implode(' ',$values).'.';
        } elseif($command==='setshiny') {
            if(!in_array(strtolower($args[1]),['true','false'],true))throw new InvalidArgumentException('Shiny value must be true or false.');$shiny=strtolower($args[1])==='true';[$prefix,$base]=pv_evolution_variant_parts((string)$p['name']);
            if(!in_array($prefix,['','Shiny '],true))throw new RuntimeException('This specimen has a '.$prefix.'variant; shiny conversion must not erase another variant.');$guide=pv_admin_species(($shiny?'Shiny ':'').$base);pv_admin_catalog_switch($p,$guide);$message='Species variant: '.$guide['name'].'.';
        } elseif($command==='setgender') {
            $g=['male'=>'Male','female'=>'Female','genderless'=>'Genderless'][strtolower($args[1])]??null;if($g===null)throw new InvalidArgumentException('Gender must be male, female or genderless.');pv_admin_exec('UPDATE pokemon SET gender=? WHERE id=?',[$g,$id]);pv_admin_exec('UPDATE pokemon_stats SET gender=? WHERE id=?',[$g,$id]);$message='Gender: '.$g.'.';
        } elseif($command==='setnickname') {
            $n=strtolower($args[1])==='none'?'':trim($args[1]);if(preg_match('/[\x00-\x1F\x7F<>]/u',$n) || (function_exists('mb_strlen')?mb_strlen($n,'UTF-8'):strlen($n))>40)throw new InvalidArgumentException('Nickname must be plain text with at most 40 characters.');pv_admin_exec('UPDATE pokemon_stats SET nickname=? WHERE id=?',[$n,$id]);$message='Nickname: '.($n?:'(none)').'.';
        } elseif($command==='clonepokemon') {
            $recipient=isset($args[1])?pv_admin_player($args[1],[],true):$player;$recipientId=(int)$recipient['id'];pv_admin_require_idle($recipientId);pv_admin_progress_safe($recipientId,(int)$p['exp']);
            $guide=pv_admin_species('#'.$p['pid']);$cloneId=pv_admin_give_pokemon($recipient,$guide,(int)$p['lvl']);
            pv_admin_exec('UPDATE pokemon SET a1=?,a2=?,a3=?,a4=?,exp=?,gender=?,ball=?,rowner=?,ot=? WHERE id=?',[(string)$p['a1'],(string)$p['a2'],(string)$p['a3'],(string)$p['a4'],(int)$p['exp'],(string)$p['gender'],(string)$p['ball'],(string)$p['rowner'],(string)$p['ot'],$cloneId]);
            $fields=['nature','ability','happiness','nickname','display_form','gender','ball','ot'];foreach(pv_admin_stat_fields() as $f){$fields[]=$f.'_iv';$fields[]=$f.'_ev';}$source=pv_admin_row('SELECT * FROM pokemon_stats WHERE id=?',[$id]);$values=[];foreach($fields as $f)$values[]=$source[$f]??'';
            pv_admin_exec('UPDATE pokemon_stats SET '.implode(',',array_map(static fn($f)=>'`'.$f.'`=?',$fields)).' WHERE id=?',[...$values,$cloneId]);pv_admin_pokemon_refresh($recipientId);return ['Cloned owned #'.$id.' as #'.$cloneId.' for '.$recipient['username'].'. Real collection specimen created.'];
        } else throw new InvalidArgumentException('Unknown Pokémon command: '.$command);
        pv_admin_pokemon_refresh($uid);return ['Owned #'.$id.': '.$message];
    });
}

/** Explicit developer stat model: base 50 for every species, never live balance. */
function pv_admin_test_stats(array $p): array
{
    $level=max(1,min(100,(int)($p['lvl']??1)));$stats=[];
    foreach(pv_admin_stat_fields() as $stat) {
        $iv=max(0,min(31,(int)($p[$stat.'_iv']??0)));$ev=max(0,min(252,(int)($p[$stat.'_ev']??0)));
        $value=(int)floor(((100+$iv+intdiv($ev,4))*$level)/100);
        $stats[$stat]=$value+($stat==='hp'?$level+10:5);
    }
    // Standard nature order uses ATK, DEF, SPEED, SPA, SPD axes.
    $nature=array_search((string)($p['nature']??'Hardy'),pv_admin_natures(),true);
    if($nature!==false){$axes=['attack','defense','speed','spatk','spdef'];$up=intdiv($nature,5);$down=$nature%5;if($up!==$down){$stats[$axes[$up]]=(int)floor($stats[$axes[$up]]*1.1);$stats[$axes[$down]]=(int)floor($stats[$axes[$down]]*0.9);}}
    return $stats;
}
function pv_admin_test_team(array $player): array
{
    $team=[];
    for($i=1;$i<=6;$i++) {
        $id=(int)($player['s'.$i]??0);if($id<=0 || isset($team[$id]))continue;
        $p=pv_admin_owned((string)$id,[],true);if((int)$p['owner']!==(int)$player['id'])throw new RuntimeException('Invalid active team ownership; repair the team first.');
        $stats=pv_admin_test_stats($p);$moves=[];for($slot=1;$slot<=4;$slot++)$moves[]=(string)($p['a'.$slot]??'');
        $team[$id]=['id'=>$id,'name'=>$p['name'],'nickname'=>$p['nickname']??'','level'=>(int)$p['lvl'],'type1'=>$p['t1'],'type2'=>$p['t2']??'','hp'=>$stats['hp'],'max_hp'=>$stats['hp'],'status'=>'none','moves'=>$moves,'stats'=>$stats,'nature'=>$p['nature']??'','ability'=>$p['ability']??''];
    }
    if(!$team)throw new RuntimeException($player['username'].' needs at least one valid active team Pokémon.');
    return $team;
}
function pv_admin_test_load(array &$context): array
{
    $id=(int)($context['test_battle_id']??0);$operator=(string)$context['operator'];
    $r=$id>0?pv_admin_row('SELECT * FROM console_test_battles WHERE id=? AND operator_name=? FOR UPDATE',[$id,$operator]):pv_admin_row('SELECT * FROM console_test_battles WHERE operator_name=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$operator]);
    if(!$r)throw new RuntimeException('No console test duel exists. Run battle <opponent> after selecting a player.');
    $state=json_decode((string)$r['state_json'],true);if(!is_array($state) || (int)($state['schema']??0)!==1)throw new RuntimeException('Test duel state is invalid; start a new battle.');
    $context['test_battle_id']=(int)$r['id'];return $state+['id'=>(int)$r['id']];
}
function pv_admin_test_save(array $state): void
{
    $state['log']=array_slice((array)($state['log']??[]),-30);
    pv_admin_exec('UPDATE console_test_battles SET status=?,state_json=?,updated_at=? WHERE id=?',[(string)$state['status'],json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),time(),(int)$state['id']]);
}
function pv_admin_test_lines(array $state): array
{
    $lines=['Console test duel #'.$state['id'].' | '.$state['status'].' | turn '.$state['turn'].' | isolated: no live rewards or ranked results.'];
    foreach([1,2] as $side){$p=$state['sides'][$side];$lines[]='Side '.$side.' '.$p['name'].' (#'.$p['uid'].')';foreach($p['team'] as $f)$lines[]='  #'.$f['id'].' '.$f['name'].' Lv.'.$f['level'].' HP '.$f['hp'].'/'.$f['max_hp'].' ['.$f['status'].'] '.implode(' | ',array_map(static fn($m)=>$m?:'(empty)',$f['moves']));}
    foreach(array_slice((array)($state['log']??[]),-5) as $log)$lines[]=$log;
    return $lines;
}
function pv_admin_test_fighter_side(array $state,int $id): int
{
    foreach([1,2] as $side)if(isset($state['sides'][$side]['team'][$id]))return $side;
    throw new RuntimeException('Owned Pokémon #'.$id.' is not in the current test duel. Start a new duel to snapshot its current team.');
}
function pv_admin_test_active(array $team): int { foreach($team as $id=>$f)if((int)$f['hp']>0)return (int)$id;return 0; }
function pv_admin_test_finish(array &$state): void
{
    $alive1=pv_admin_test_active($state['sides'][1]['team']);$alive2=pv_admin_test_active($state['sides'][2]['team']);
    if(!$alive1 || !$alive2){$state['status']='complete';$state['winner']=!$alive1?2:1;$state['log'][]='Test side '.$state['winner'].' wins; no live results were recorded.';}
}
function pv_admin_test_attack(array &$state,int $side,int $slot): void
{
    $other=$side===1?2:1;$aid=pv_admin_test_active($state['sides'][$side]['team']);$did=pv_admin_test_active($state['sides'][$other]['team']);
    if(!$aid || !$did){pv_admin_test_finish($state);return;}
    $a=&$state['sides'][$side]['team'][$aid];$d=&$state['sides'][$other]['team'][$did];
    $moveName=(string)($a['moves'][$slot-1]??'');
    if(trim($moveName)===''){if(array_filter($a['moves'],static fn($m)=>trim((string)$m)!==''))throw new RuntimeException('That test move slot is empty.');$moveName='Struggle';}
    $move=pv_combat_move_data(pv_admin_db(),$moveName,(string)$a['type1']);$state['turn']++;
    $cannot=false;
    if(in_array($a['status'],['sleep','frozen'],true)){if(random_int(1,4)===1){$a['status']='none';$state['log'][]=$a['name'].' recovered.';}else $cannot=true;}
    if($a['status']==='paralyzed' && random_int(1,4)===1)$cannot=true;
    if($cannot)$state['log'][]=$a['name'].' could not act due to '.$a['status'].'.';
    elseif(random_int(1,100)>(int)$move['accuracy'])$state['log'][]=$a['name'].' used '.$moveName.' and missed.';
    elseif((int)$move['power']<=0)$state['log'][]=$a['name'].' used '.$moveName.' (non-damaging; apply supported conditions with setstatus).';
    else {
        $special=strcasecmp((string)($move['category']??''),'Special')===0;$atk=(int)$a['stats'][$special?'spatk':'attack'];$def=max(1,(int)$d['stats'][$special?'spdef':'defense']);
        if(!$special && $a['status']==='burn')$atk=max(1,intdiv($atk,2));$type=(string)$move['type'];$mult=pv_combat_type_multiplier($type,(string)$d['type1'],(string)$d['type2']);
        $stab=(strcasecmp($type,(string)$a['type1'])===0 || strcasecmp($type,(string)$a['type2'])===0)?1.5:1.0;
        $damage=$mult<=0?0:max(1,(int)floor(((((2*$a['level']/5)+2)*(int)$move['power']*$atk/$def/50)+2)*$mult*$stab*random_int(85,100)/100));
        $damage=min($damage,(int)$d['hp']);$d['hp']-=$damage;$state['log'][]=$a['name'].' used '.$moveName.' → '.$d['name'].' lost '.$damage.' HP.';
    }
    if($a['hp']>0 && in_array($a['status'],['poison','burn'],true)){$loss=min((int)$a['hp'],max(1,intdiv((int)$a['max_hp'],8)));$a['hp']-=$loss;$state['log'][]=$a['name'].' lost '.$loss.' HP to '.$a['status'].'.';}
    unset($a,$d);pv_admin_test_finish($state);
}
function pv_admin_test_handle(string $command,array $args,array &$context): array
{
    return pv_admin_transaction(function()use($command,$args,&$context):array {
        if($command==='battle' && !in_array(strtolower($args[0]),['status','attack'],true)) {
            if(count($args)>2)throw new InvalidArgumentException('Usage: battle <opponent> [player]');
            $player=pv_admin_player($args[1]??'',$context,true);$opponent=pv_admin_player($args[0],[],true);
            if((int)$player['id']===(int)$opponent['id'])throw new RuntimeException('Choose a different opponent so fighter IDs remain unique.');
            pv_admin_require_idle((int)$player['id']);pv_admin_require_idle((int)$opponent['id']);
            $state=['schema'=>1,'status'=>'active','turn'=>0,'winner'=>0,'sides'=>[1=>['uid'=>(int)$player['id'],'name'=>$player['username'],'team'=>pv_admin_test_team($player)],2=>['uid'=>(int)$opponent['id'],'name'=>$opponent['username'],'team'=>pv_admin_test_team($opponent)]],'log'=>['Snapshots use a base-50 developer stat model with specimen IV/EV/nature inputs and real Vortex moves/type effectiveness.']];
            pv_admin_exec("UPDATE console_test_battles SET status='abandoned',updated_at=? WHERE operator_name=? AND status='active'",[time(),(string)$context['operator']]);
            pv_admin_exec('INSERT INTO console_test_battles (operator_name,player_id,opponent_id,status,state_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[(string)$context['operator'],(int)$player['id'],(int)$opponent['id'],'active',json_encode($state,JSON_THROW_ON_ERROR),time(),time()]);
            $context['test_battle_id']=(int)pv_admin_db()->insert_id;$state['id']=$context['test_battle_id'];return pv_admin_test_lines($state);
        }
        $state=pv_admin_test_load($context);
        if($command==='battle' && strtolower($args[0])==='status'){if(count($args)!==1)throw new InvalidArgumentException('Usage: battle status');return pv_admin_test_lines($state);}
        if($state['status']!=='active')throw new RuntimeException('This test duel has completed. Start another with battle <opponent>.');
        if($command==='battle') {
            if(strtolower($args[0])!=='attack' || count($args)<2 || count($args)>3)throw new InvalidArgumentException('Usage: battle attack <move-slot:1-4> [side:1|2]');
            $slot=pv_admin_int($args[1],1,4,'move slot');$side=isset($args[2])?pv_admin_int($args[2],1,2,'side'):1;pv_admin_test_attack($state,$side,$slot);
        } elseif($command==='win' || $command==='lose') {
            $state['status']='complete';$state['winner']=$command==='win'?1:2;$state['log'][]='Forced test victory for side '.$state['winner'].'; live accounts and ranked records untouched.';
        } else {
            $id=pv_admin_int(ltrim($args[0],'#'),1,2147483647,'owned Pokémon ID');$side=pv_admin_test_fighter_side($state,$id);$fighter=&$state['sides'][$side]['team'][$id];
            if($command==='sethp'){$fighter['hp']=pv_admin_int($args[1],0,(int)$fighter['max_hp'],'HP');$state['log'][]='Test #'.$id.' HP set to '.$fighter['hp'].'.';}
            elseif($command==='setstatus'){$status=strtolower($args[1]);if(!in_array($status,['none','poison','burn','sleep','frozen','paralyzed'],true))throw new InvalidArgumentException('Supported test statuses: none, poison, burn, sleep, frozen, paralyzed.');$fighter['status']=$status;$state['log'][]='Test #'.$id.' status set to '.$status.'.';}
            elseif($command==='clearmoves'){$fighter['moves']=['','','',''];$state['log'][]='Test #'.$id.' moves cleared; Struggle fallback available.';}
            elseif($command==='addmove'){$move=pv_admin_move($args[1]);$slot=pv_admin_move_slot($fighter['moves'],$move,$args[2]??null);$fighter['moves'][$slot]=$move;$state['log'][]='Test #'.$id.' learned '.$move.' in slot '.($slot+1).'.';}
            else throw new InvalidArgumentException('Unknown test command.');unset($fighter);
        }
        pv_admin_test_save($state);return pv_admin_test_lines($state);
    });
}
