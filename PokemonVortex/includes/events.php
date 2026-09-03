<?php
require_once __DIR__ . '/gameplay.php';

function pv_event_catalog(): array {
    return [
        'pikachu2015' => [
            'label' => 'Cosplay Pikachu Collection Challenge',
            'image' => 'images/newyear2015.png',
            'summary' => 'Collect all 28 normal Unown forms under your own Original Trainer name to earn a one-use Cosplay Pikachu promo code.',
        ],
        'kyurem' => [
            'label' => 'Kyurem Fusion Research',
            'image' => 'images/events/kyurem.png',
            'summary' => 'Acquire DNA Splicers and fuse a reserve Kyurem with a matching-form Reshiram or Zekrom.',
        ],
    ];
}

function pv_active_event_key(): string {
    $key = strtolower(trim((string)pv_config('active_event', 'none')));
    return array_key_exists($key, pv_event_catalog()) ? $key : 'none';
}

function pv_event_access(mysqli $db, int $uid, bool $lock = false): array {
    $sql = 'SELECT event_page,event FROM members_options WHERE id=?' . ($lock ? ' FOR UPDATE' : '') . ' LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('Event access state is unavailable.');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: ['event_page'=>0,'event'=>''];
    $stmt->close();
    return $row;
}

function pv_event_ticket_count(mysqli $db, int $uid, bool $lock = false): int {
    $sql = 'SELECT event_ticket FROM items WHERE uid=?' . ($lock ? ' FOR UPDATE' : '') . ' LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('Event ticket inventory is unavailable.');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return max(0, (int)($row['event_ticket'] ?? 0));
}

function pv_event_unlock(mysqli $db, int $uid, string $eventKey): string {
    if ($eventKey === 'none') return 'no_event';
    $db->begin_transaction();
    try {
        $access = pv_event_access($db, $uid, true);
        if ((int)($access['event_page'] ?? 0) === 1 && (string)($access['event'] ?? '') === $eventKey) {
            $db->commit();
            return 'already_unlocked';
        }
        $tickets = pv_event_ticket_count($db, $uid, true);
        if ($tickets <= 0) {
            $db->rollback();
            return 'no_ticket';
        }
        $stmt = $db->prepare('UPDATE items SET event_ticket=event_ticket-1 WHERE uid=? AND event_ticket>0');
        if (!$stmt) throw new RuntimeException('Could not prepare Event Ticket consumption.');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $consumed = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$consumed) throw new RuntimeException('The Event Ticket changed before it could be used.');

        $stmt = $db->prepare('UPDATE members_options SET event_page=1,event=? WHERE id=?');
        if (!$stmt) throw new RuntimeException('Could not prepare Event Center access.');
        $stmt->bind_param('si', $eventKey, $uid);
        if (!$stmt->execute()) throw new RuntimeException('Could not unlock the Event Center.');
        $stmt->close();
        $db->commit();
        return 'unlocked';
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }
}

function pv_event_has_access(mysqli $db, int $uid, string $eventKey): bool {
    if ($eventKey === 'none') return false;
    $row = pv_event_access($db, $uid, false);
    return (int)($row['event_page'] ?? 0) === 1 && hash_equals($eventKey, (string)($row['event'] ?? ''));
}

function pv_event_buy_splicers(mysqli $db, int $uid): string {
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT i.DNA_Splicers,m.money FROM items i JOIN members m ON m.id=i.uid WHERE i.uid=? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Could not lock DNA Splicer purchase state.');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new RuntimeException('Trainer inventory is unavailable.');
        if ((int)$row['DNA_Splicers'] > 0) {
            $db->commit();
            return 'already_owned';
        }
        if ((int)$row['money'] < 500000) {
            $db->rollback();
            return 'insufficient_money';
        }
        $stmt = $db->prepare('UPDATE members SET money=money-500000 WHERE id=? AND money>=500000');
        if (!$stmt) throw new RuntimeException('Could not prepare DNA Splicer payment.');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $paid = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$paid) throw new RuntimeException('DNA Splicer payment could not be completed.');
        $stmt = $db->prepare('UPDATE items SET DNA_Splicers=1 WHERE uid=? AND DNA_Splicers=0');
        if (!$stmt) throw new RuntimeException('Could not prepare DNA Splicer delivery.');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $delivered = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$delivered) throw new RuntimeException('DNA Splicers could not be delivered.');
        $db->commit();
        return 'purchased';
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }
}

function pv_event_variant_and_species(string $name): array {
    foreach (['Shiny','Dark','Metallic','Mystic','Shadow'] as $variant) {
        $prefix = $variant . ' ';
        if (str_starts_with($name, $prefix)) return [$variant, substr($name, strlen($prefix))];
    }
    return ['', $name];
}

function pv_event_fuse_kyurem(mysqli $db, int $uid, int $kyuremId, int $partnerId): array {
    if ($kyuremId <= 0 || $partnerId <= 0 || $kyuremId === $partnerId) throw new RuntimeException('Select two different Pokémon to fuse.');
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Could not lock the active team.');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$member) throw new RuntimeException('Trainer state is unavailable.');
        $teamIds = [];
        for ($slot=1; $slot<=6; $slot++) {
            $id=max(0,(int)($member['s'.$slot]??0));
            if($id>0) $teamIds[]=$id;
        }
        if (in_array($kyuremId, $teamIds, true) || in_array($partnerId, $teamIds, true)) throw new RuntimeException('Fusion Pokémon must be in your reserve collection, not the active team.');

        $stmt = $db->prepare('SELECT DNA_Splicers FROM items WHERE uid=? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Could not lock DNA Splicer inventory.');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $inventory = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$inventory || (int)$inventory['DNA_Splicers'] <= 0) throw new RuntimeException('DNA Splicers are required before fusion.');

        $idList = implode(',', [$kyuremId, $partnerId]);
        $stmt = $db->prepare("SELECT id,name,owner,exp FROM pokemon WHERE CAST(owner AS UNSIGNED)=? AND id IN ({$idList}) FOR UPDATE");
        if (!$stmt) throw new RuntimeException('Could not lock the selected fusion Pokémon.');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $byId=[]; foreach($rows as $row) $byId[(int)$row['id']]=$row;
        if (!isset($byId[$kyuremId], $byId[$partnerId])) throw new RuntimeException('One or both selected Pokémon are no longer owned by this trainer.');
        $kyurem=$byId[$kyuremId]; $partner=$byId[$partnerId];
        [$variantA,$speciesA]=pv_event_variant_and_species((string)$kyurem['name']);
        [$variantB,$speciesB]=pv_event_variant_and_species((string)$partner['name']);
        if ($speciesA !== 'Kyurem' || !in_array($speciesB, ['Reshiram','Zekrom'], true)) throw new RuntimeException('Fusion requires Kyurem plus Reshiram or Zekrom.');
        if ($variantA !== $variantB) throw new RuntimeException('Both Pokémon must have the same form variant.');

        $resultBase = $speciesB === 'Reshiram' ? 'Kyurem (White)' : 'Kyurem (Black)';
        $resultName = ($variantA !== '' ? $variantA . ' ' : '') . $resultBase;
        $ability = $speciesB === 'Reshiram' ? 'Turboblaze' : 'Teravolt';

        $stmt = $db->prepare('UPDATE pokemon SET name=?,lvl=100,exp=50000,ball=? WHERE id=? AND CAST(owner AS UNSIGNED)=?');
        if (!$stmt) throw new RuntimeException('Could not prepare the fused Kyurem update.');
        $ball='Cherish Ball';
        $stmt->bind_param('ssii', $resultName, $ball, $kyuremId, $uid);
        if (!$stmt->execute() || $stmt->affected_rows < 0) throw new RuntimeException('Could not create the fused Kyurem.');
        $stmt->close();

        $stmt = $db->prepare("INSERT INTO pokemon_stats (id,ability,ball) VALUES (?,?,?) ON DUPLICATE KEY UPDATE ability=VALUES(ability),ball=VALUES(ball)");
        $stmt->bind_param('iss', $kyuremId, $ability, $ball);
        if (!$stmt->execute()) throw new RuntimeException('Could not update fused Pokémon metadata.');
        $stmt->close();

        $stmt = $db->prepare('DELETE FROM pokemon_stats WHERE id=?');
        if (!$stmt) throw new RuntimeException('Could not prepare fusion-partner metadata cleanup.');
        $stmt->bind_param('i', $partnerId);
        if (!$stmt->execute()) throw new RuntimeException('Could not remove fusion-partner metadata.');
        $stmt->close();
        $stmt = $db->prepare('DELETE FROM pokemon WHERE id=? AND CAST(owner AS UNSIGNED)=?');
        if (!$stmt) throw new RuntimeException('Could not prepare fusion-partner consumption.');
        $stmt->bind_param('ii', $partnerId, $uid);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Could not consume the fusion partner.');
        $stmt->close();

        $db->commit();

        // Fusion is already authoritative after commit. Treat progression
        // recalculation as a post-commit synchronization step so an unrelated
        // recalculation failure cannot report a committed fusion as failed.
        try {
            pv_recalculate_trainer_progress($db, $uid, true);
        } catch (Throwable $recalcError) {
            pv_log('event_kyurem_progress_recalc_failed', [
                'user_id' => $uid,
                'pokemon_id' => $kyuremId,
                'error' => $recalcError->getMessage(),
            ]);
        }
        return ['result'=>$resultName,'ability'=>$ability,'pokemon_id'=>$kyuremId];
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }
}

function pv_event_kyurem_candidates(mysqli $db, int $uid): array {
    $stmt=$db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');
    $stmt->bind_param('i',$uid); $stmt->execute(); $member=$stmt->get_result()->fetch_assoc()?:[]; $stmt->close();
    $team=[]; for($i=1;$i<=6;$i++){ $id=max(0,(int)($member['s'.$i]??0)); if($id>0)$team[]=$id; }
    $stmt=$db->prepare("SELECT id,name,lvl,exp FROM pokemon WHERE CAST(owner AS UNSIGNED)=? AND (name LIKE '%Kyurem' OR name LIKE '%Reshiram' OR name LIKE '%Zekrom') ORDER BY name,lvl DESC,id");
    $stmt->bind_param('i',$uid); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    $kyurem=[];$partners=[];
    foreach($rows as $row){ if(in_array((int)$row['id'],$team,true)) continue; [, $species]=pv_event_variant_and_species((string)$row['name']); if($species==='Kyurem')$kyurem[]=$row; elseif(in_array($species,['Reshiram','Zekrom'],true))$partners[]=$row; }
    return ['kyurem'=>$kyurem,'partners'=>$partners];
}

function pv_event_required_unown_forms(): array {
    $forms=[];
    foreach(range('A','Z') as $letter)$forms[]='Unown ('.$letter.')';
    $forms[]='Unown (Ex)';
    $forms[]='Unown (Qm)';
    return $forms;
}

function pv_event_owned_unown_forms(mysqli $db, int $uid, string $username, bool $lock = false): array {
    $required=pv_event_required_unown_forms();
    $placeholders=implode(',',array_fill(0,count($required),'?'));
    $sql="SELECT DISTINCT name FROM pokemon WHERE CAST(owner AS UNSIGNED)=? AND ot=? AND name IN ({$placeholders})".($lock?' FOR UPDATE':'');
    $stmt=$db->prepare($sql);
    if(!$stmt)throw new RuntimeException('Could not verify the Unown collection.');
    $types='is'.str_repeat('s',count($required));
    $params=[$uid,$username,...$required];
    $stmt->bind_param($types,...$params);
    $stmt->execute();$r=$stmt->get_result();$owned=[];while($row=$r->fetch_assoc())$owned[(string)$row['name']]=true;$stmt->close();
    return array_values(array_intersect($required,array_keys($owned)));
}

function pv_event_claim_cosplay_pikachu(mysqli $db, int $uid, string $username, string $ip): array {
    $eventKey='pikachu2015';
    $db->begin_transaction();
    try {
        $stmt=$db->prepare('SELECT promo_code_id FROM event_completions WHERE user_id=? AND event_key=? FOR UPDATE');
        if(!$stmt)throw new RuntimeException('Could not verify event completion.');
        $stmt->bind_param('is',$uid,$eventKey);$stmt->execute();$existing=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($existing){$db->commit();return['status'=>'already_claimed','promo_code_id'=>(int)($existing['promo_code_id']??0)];}

        $owned=pv_event_owned_unown_forms($db,$uid,$username,true);$count=count($owned);
        if($count<28){$db->rollback();return['status'=>'incomplete','count'=>$count];}

        $forms=['Pikachu (Belle)','Pikachu (Libre)','Pikachu (Ph. D.)','Pikachu (Pop Star)','Pikachu (Rock Star)'];
        $variants=['','Shiny ','Dark ','Mystic ','Metallic ','Shadow '];
        $prize=$variants[random_int(0,count($variants)-1)].$forms[random_int(0,count($forms)-1)];
        $code=strtoupper(bin2hex(random_bytes(16)));$type='pokemon';
        $stmt=$db->prepare('INSERT INTO promo_codes (code,prize,type,owner) VALUES (?,?,?,?)');
        if(!$stmt)throw new RuntimeException('Could not prepare the event promo code.');
        $stmt->bind_param('sssi',$code,$prize,$type,$uid);
        if(!$stmt->execute())throw new RuntimeException('Could not create the event promo code.');
        $promoId=(int)$db->insert_id;$stmt->close();

        $now=time();
        $stmt=$db->prepare('INSERT INTO event_completions (user_id,event_key,promo_code_id,completed_at) VALUES (?,?,?,?)');
        if(!$stmt)throw new RuntimeException('Could not prepare event completion.');
        $stmt->bind_param('isii',$uid,$eventKey,$promoId,$now);
        if(!$stmt->execute())throw new RuntimeException('Could not record event completion.');
        $stmt->close();

        // Preserve a historical audit row without treating its auto-increment id
        // as trainer identity.
        $stmt=$db->prepare('INSERT INTO done_event (username,ip) VALUES (?,?)');
        if($stmt){$stmt->bind_param('ss',$username,$ip);$stmt->execute();$stmt->close();}
        $db->commit();
        return['status'=>'claimed','code'=>$code,'prize'=>$prize,'count'=>$count,'promo_code_id'=>$promoId];
    }catch(Throwable $e){try{$db->rollback();}catch(Throwable $ignored){}throw$e;}
}

function pv_event_cosplay_status(mysqli $db, int $uid, string $username): array {
    $owned=pv_event_owned_unown_forms($db,$uid,$username,false);$count=count($owned);$eventKey='pikachu2015';
    $stmt=$db->prepare('SELECT ec.promo_code_id,pc.code,pc.prize FROM event_completions ec LEFT JOIN promo_codes pc ON pc.id=ec.promo_code_id WHERE ec.user_id=? AND ec.event_key=? LIMIT 1');
    if(!$stmt)return['count'=>$count,'done'=>false,'code'=>null,'owned'=>$owned];
    $stmt->bind_param('is',$uid,$eventKey);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
    $done=is_array($row);$code=($done&&!empty($row['code']))?['code'=>(string)$row['code'],'prize'=>(string)($row['prize']??'')]:null;
    return['count'=>$count,'done'=>$done,'code'=>$code,'owned'=>$owned];
}

