<?php
declare(strict_types=1);
require_once __DIR__ . '/gameplay.php';

function pv_collection_team_ids(mysqli $db, int $uid): array
{
    $stmt=$db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');
    if(!$stmt) return [];
    $stmt->bind_param('i',$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();
    $ids=[];
    for($i=1;$i<=6;$i++){ $id=(int)($row['s'.$i]??0); if($id>0)$ids[]=$id; }
    return $ids;
}

function pv_collection_summary(mysqli $db, int $uid): array
{
    $stmt=$db->prepare("SELECT COUNT(*) owned,COUNT(DISTINCT pid) unique_forms,COALESCE(SUM(exp),0) total_exp,COALESCE(MAX(lvl),0) highest_level FROM pokemon WHERE CAST(owner AS UNSIGNED)=?");
    if(!$stmt) return ['owned'=>0,'unique_forms'=>0,'total_exp'=>0,'highest_level'=>0];
    $stmt->bind_param('i',$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();
    return [
        'owned'=>max(0,(int)($row['owned']??0)),
        'unique_forms'=>max(0,(int)($row['unique_forms']??0)),
        'total_exp'=>max(0,(int)($row['total_exp']??0)),
        'highest_level'=>max(0,(int)($row['highest_level']??0)),
    ];
}

function pv_collection_rows(mysqli $db,int $uid,string $search='',string $variant='',string $sort='newest',int $page=1,int $perPage=24): array
{
    $page=max(1,$page);$perPage=max(6,min(60,$perPage));
    $where=['CAST(p.owner AS UNSIGNED)=?'];$types='i';$args=[$uid];
    $search=trim($search);
    if($search!==''){ $where[]='p.name LIKE ?';$types.='s';$args[]='%'.$search.'%'; }
    $variant=trim($variant);
    $allowedVariants=['Normal','Shiny','Dark','Metallic','Mystic','Shadow'];
    if(in_array($variant,$allowedVariants,true)){
        if($variant==='Normal') $where[]="p.name NOT REGEXP '^(Shiny|Dark|Metallic|Mystic|Shadow) '";
        else { $where[]='p.name LIKE ?';$types.='s';$args[]=$variant.' %'; }
    }
    $order=match($sort){
        'name'=>'p.name ASC,p.id DESC',
        'level'=>'p.lvl DESC,p.id DESC',
        'exp'=>'p.exp DESC,p.id DESC',
        'oldest'=>'p.id ASC',
        default=>'p.id DESC',
    };
    $whereSql=implode(' AND ',$where);
    $stmt=$db->prepare("SELECT COUNT(*) c FROM pokemon p WHERE {$whereSql}");
    if(!$stmt) return ['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
    $stmt->bind_param($types,...$args);$stmt->execute();$total=(int)($stmt->get_result()->fetch_assoc()['c']??0);$stmt->close();
    $pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
    $sql="SELECT p.id,p.pid,p.name,p.lvl,p.exp,p.t1,p.t2,p.a1,p.a2,p.a3,p.a4,p.ball,p.gender,p.rowner,p.ot,
                 COALESCE(ps.nature,'') nature,COALESCE(ps.ability,'') ability,COALESCE(ps.happiness,0) happiness,
                 COALESCE(ps.display_form,'') display_form,COALESCE(ps.hp_iv,0) hp_iv,COALESCE(ps.attack_iv,0) attack_iv,
                 COALESCE(ps.defense_iv,0) defense_iv,COALESCE(ps.spatk_iv,0) spatk_iv,COALESCE(ps.spdef_iv,0) spdef_iv,
                 COALESCE(ps.speed_iv,0) speed_iv
          FROM pokemon p LEFT JOIN pokemon_stats ps ON ps.id=p.id WHERE {$whereSql} ORDER BY {$order} LIMIT ? OFFSET ?";
    $stmt=$db->prepare($sql);if(!$stmt)return ['rows'=>[],'total'=>$total,'page'=>$page,'pages'=>$pages];
    $types2=$types.'ii';$args2=[...$args,$perPage,$offset];$stmt->bind_param($types2,...$args2);$stmt->execute();$r=$stmt->get_result();$rows=[];
    while($row=$r->fetch_assoc())$rows[]=$row;$stmt->close();
    return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
}

function pv_collection_pokemon(mysqli $db,int $uid,int $pokemonId,bool $forUpdate=false): ?array
{
    if($pokemonId<=0)return null;
    $sql="SELECT p.*,COALESCE(ps.nature,'') nature,COALESCE(ps.ability,'') ability,COALESCE(ps.happiness,0) happiness,
                COALESCE(ps.display_form,'') display_form,COALESCE(ps.hp_iv,0) hp_iv,COALESCE(ps.attack_iv,0) attack_iv,
                COALESCE(ps.defense_iv,0) defense_iv,COALESCE(ps.spatk_iv,0) spatk_iv,COALESCE(ps.spdef_iv,0) spdef_iv,
                COALESCE(ps.speed_iv,0) speed_iv
         FROM pokemon p LEFT JOIN pokemon_stats ps ON ps.id=p.id WHERE p.id=? AND CAST(p.owner AS UNSIGNED)=? LIMIT 1".($forUpdate?' FOR UPDATE':'');
    $stmt=$db->prepare($sql);if(!$stmt)return null;$stmt->bind_param('ii',$pokemonId,$uid);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();return $row?:null;
}

function pv_collection_display_name(array $row): string
{
    $form=trim((string)($row['display_form']??''));
    return $form!==''?$form:(string)($row['name']??'Pokémon');
}

function pv_collection_save_team(mysqli $db,int $uid,array $submitted): array
{
    $ids=[];
    foreach(array_slice($submitted,0,6) as $value){$id=(int)$value;if($id>0)$ids[]=$id;}
    if(count($ids)!==count(array_unique($ids)))throw new RuntimeException('Each active-team slot must contain a different Pokémon.');
    if(!$ids)throw new RuntimeException('Your active team needs at least one Pokémon.');

    $db->begin_transaction();
    try{
        $stmt=$db->prepare('SELECT id FROM members WHERE id=? FOR UPDATE');if(!$stmt)throw new RuntimeException('Trainer team could not be locked.');$stmt->bind_param('i',$uid);$stmt->execute();if(!$stmt->get_result()->fetch_assoc()){ $stmt->close();throw new RuntimeException('Trainer account could not be loaded.');}$stmt->close();
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $types=str_repeat('i',count($ids)+1);$args=[$uid,...$ids];
        $stmt=$db->prepare("SELECT id FROM pokemon WHERE CAST(owner AS UNSIGNED)=? AND id IN ({$ph}) FOR UPDATE");if(!$stmt)throw new RuntimeException('Team Pokémon could not be verified.');$stmt->bind_param($types,...$args);$stmt->execute();$r=$stmt->get_result();$found=[];while($row=$r->fetch_assoc())$found[(int)$row['id']]=true;$stmt->close();
        foreach($ids as $id)if(!isset($found[$id]))throw new RuntimeException('One of the selected Pokémon is no longer available in your collection.');
        $slots=array_pad($ids,6,0);
        $stmt=$db->prepare('UPDATE members SET s1=?,s2=?,s3=?,s4=?,s5=?,s6=? WHERE id=?');if(!$stmt)throw new RuntimeException('The team could not be saved.');
        $stmt->bind_param('iiiiiii',$slots[0],$slots[1],$slots[2],$slots[3],$slots[4],$slots[5],$uid);if(!$stmt->execute()){ $stmt->close();throw new RuntimeException('The team could not be saved.');}$stmt->close();
        $db->commit();return $slots;
    }catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;pv_log('Team save failure: '.$e->getMessage());throw new RuntimeException('Your team could not be saved. Your previous team is unchanged.');}
}

function pv_collection_release(mysqli $db,int $uid,int $pokemonId): string
{
    $db->begin_transaction();
    try{
        $pokemon=pv_collection_pokemon($db,$uid,$pokemonId,true);if(!$pokemon)throw new RuntimeException('That Pokémon is no longer in your collection.');
        $team=pv_collection_team_ids($db,$uid);if(in_array($pokemonId,$team,true))throw new RuntimeException('Remove this Pokémon from your active team before releasing it.');
        $name=pv_collection_display_name($pokemon);
        $pid=(int)$pokemon['pid'];
        $stmt=$db->prepare('DELETE FROM pokemon_stats WHERE id=?');if($stmt){$stmt->bind_param('i',$pokemonId);$stmt->execute();$stmt->close();}
        $stmt=$db->prepare('DELETE FROM pokemon WHERE id=? AND CAST(owner AS UNSIGNED)=?');if(!$stmt)throw new RuntimeException('The Pokémon could not be released.');$stmt->bind_param('ii',$pokemonId,$uid);$stmt->execute();$ok=$stmt->affected_rows===1;$stmt->close();if(!$ok)throw new RuntimeException('The Pokémon could not be released.');
        $stmt=$db->prepare('UPDATE pguide SET amount=GREATEST(0,amount-1) WHERE id=?');if($stmt){$stmt->bind_param('i',$pid);$stmt->execute();$stmt->close();}
        $db->commit();
        try{pv_recalculate_trainer_progress($db,$uid,true);}catch(Throwable $e){pv_log('Release progress refresh failed: '.$e->getMessage());}
        return $name;
    }catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;pv_log('Release failure: '.$e->getMessage());throw new RuntimeException('That Pokémon could not be released. Your collection is unchanged.');}
}

function pv_move_price(array $move): int
{
    $power=max(0,(int)($move['power']??0));
    return $power>0?max(3000,$power*750):12000;
}

function pv_move_compatible(array $pokemon,array $move,array $guideDefaults=[]): bool
{
    $moveName=strtolower(trim((string)($move['attack']??'')));
    foreach($guideDefaults as $m)if($moveName!==''&&strcasecmp($moveName,trim((string)$m))===0)return true;
    $type=trim((string)($move['type']??''));
    if(strcasecmp($type,'Normal')===0)return true;
    return $type!=='' && (strcasecmp($type,(string)($pokemon['t1']??''))===0 || strcasecmp($type,(string)($pokemon['t2']??''))===0);
}

function pv_move_catalog(mysqli $db,array $pokemon,string $search='',int $limit=120): array
{
    $stmt=$db->prepare('SELECT a1,a2,a3,a4 FROM pguide WHERE id=? LIMIT 1');$defaults=[];
    if($stmt){$pid=(int)$pokemon['pid'];$stmt->bind_param('i',$pid);$stmt->execute();$g=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();$defaults=[(string)($g['a1']??''),(string)($g['a2']??''),(string)($g['a3']??''),(string)($g['a4']??'')];}
    $search=trim($search);$sql='SELECT attack,type,power,accuracy,category FROM attacks';$args=[];$types='';
    if($search!==''){$sql.=' WHERE attack LIKE ?';$types='s';$args[]='%'.$search.'%';}$sql.=' ORDER BY attack ASC LIMIT '.max(20,min(300,$limit));
    $stmt=$db->prepare($sql);if(!$stmt)return [];
    if($types!=='')$stmt->bind_param($types,...$args);$stmt->execute();$r=$stmt->get_result();$out=[];
    while($m=$r->fetch_assoc()){if(pv_move_compatible($pokemon,$m,$defaults)){$m['price']=pv_move_price($m);$out[]=$m;}}$stmt->close();return $out;
}

function pv_move_teach(mysqli $db,int $uid,int $pokemonId,int $slot,string $newMove): array
{
    if($slot<1||$slot>4)throw new RuntimeException('Choose one of the four move slots.');$newMove=trim($newMove);if($newMove==='')throw new RuntimeException('Choose a move to learn.');
    $db->begin_transaction();
    try{
        $pokemon=pv_collection_pokemon($db,$uid,$pokemonId,true);if(!$pokemon)throw new RuntimeException('That Pokémon is no longer in your collection.');
        $stmt=$db->prepare('SELECT attack,type,power,accuracy,category FROM attacks WHERE attack=? LIMIT 1');if(!$stmt)throw new RuntimeException('Move data is unavailable.');$stmt->bind_param('s',$newMove);$stmt->execute();$move=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$move)throw new RuntimeException('That move is not available in the Move Lab.');
        $stmt=$db->prepare('SELECT a1,a2,a3,a4 FROM pguide WHERE id=? LIMIT 1');$defaults=[];if($stmt){$pid=(int)$pokemon['pid'];$stmt->bind_param('i',$pid);$stmt->execute();$g=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();$defaults=[(string)($g['a1']??''),(string)($g['a2']??''),(string)($g['a3']??''),(string)($g['a4']??'')];}
        if(!pv_move_compatible($pokemon,$move,$defaults))throw new RuntimeException('That move is not compatible with this Pokémon in the Move Lab.');
        $current=[1=>(string)$pokemon['a1'],2=>(string)$pokemon['a2'],3=>(string)$pokemon['a3'],4=>(string)$pokemon['a4']];
        foreach($current as $s=>$m)if($s!==$slot&&strcasecmp(trim($m),$newMove)===0)throw new RuntimeException('This Pokémon already knows that move.');
        $price=pv_move_price($move);
        $stmt=$db->prepare('SELECT money FROM members WHERE id=? FOR UPDATE');if(!$stmt)throw new RuntimeException('Trainer funds could not be verified.');$stmt->bind_param('i',$uid);$stmt->execute();$member=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$member|| (int)$member['money']<$price)throw new RuntimeException('You do not have enough money to teach that move.');
        $stmt=$db->prepare('UPDATE members SET money=money-? WHERE id=? AND money>=?');if(!$stmt)throw new RuntimeException('Trainer funds could not be updated.');$stmt->bind_param('iii',$price,$uid,$price);$stmt->execute();$ok=$stmt->affected_rows===1;$stmt->close();if(!$ok)throw new RuntimeException('Your balance changed before the move could be taught.');
        $column='a'.$slot;$sql='UPDATE pokemon SET `'.$column.'`=? WHERE id=? AND CAST(owner AS UNSIGNED)=?';$stmt=$db->prepare($sql);if(!$stmt)throw new RuntimeException('The move could not be saved.');$stmt->bind_param('sii',$newMove,$pokemonId,$uid);$stmt->execute();$ok=$stmt->affected_rows===1||strcasecmp($current[$slot],$newMove)===0;$stmt->close();if(!$ok)throw new RuntimeException('The move could not be saved.');
        $old=$current[$slot];$now=time();$stmt=$db->prepare('INSERT INTO move_lab_transactions (user_id,pokemon_id,slot_no,old_move,new_move,price,created_at) VALUES (?,?,?,?,?,?,?)');if(!$stmt)throw new RuntimeException('The Move Lab receipt could not be recorded.');$stmt->bind_param('iiissii',$uid,$pokemonId,$slot,$old,$newMove,$price,$now);$stmt->execute();$stmt->close();
        $db->commit();return ['old'=>$old,'new'=>$newMove,'price'=>$price];
    }catch(Throwable $e){$db->rollback();if($e instanceof RuntimeException)throw $e;pv_log('Move Lab failure: '.$e->getMessage());throw new RuntimeException('The move could not be changed. Your Pokémon and money are unchanged.');}
}
