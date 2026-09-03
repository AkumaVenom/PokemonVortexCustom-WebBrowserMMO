<?php
require_once __DIR__ . '/kick.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/events.php';

$uid=max(0,(int)($_SESSION['myid']??0));
if($uid<=0 || (int)($_SESSION['access']??0)!==9) pv_redirect('login.php?goawayxP=1');
$db=pv_db();
$eventKey=pv_active_event_key();
$catalog=pv_event_catalog();

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    pv_require_csrf();
    $actions=['unlock','claim_cosplay','buy_dna_splicers','fuse_kyurem'];
    $chosen=[]; foreach($actions as $action) if(isset($_POST[$action])) $chosen[]=$action;
    if(count($chosen)!==1){ http_response_code(400); exit('Invalid Event Center action.'); }
    $action=$chosen[0];
    $flash=['type'=>'danger','message'=>'The Event Center action could not be completed.'];
    try{
        if($action==='unlock'){
            $status=pv_event_unlock($db,$uid,$eventKey);
            $messages=[
                'unlocked'=>['success','Event Center access unlocked. One Event Ticket was consumed.'],
                'already_unlocked'=>['info','This event is already unlocked for your trainer.'],
                'no_ticket'=>['danger','You need an Event Ticket to unlock the active event.'],
                'no_event'=>['info','There is no active event to unlock right now.'],
            ];
            [$flash['type'],$flash['message']]=$messages[$status]??['danger','The Event Center could not be unlocked.'];
        } else {
            if($eventKey==='none' || !pv_event_has_access($db,$uid,$eventKey)) throw new RuntimeException('Unlock the currently active event before using its actions.');
            if($action==='buy_dna_splicers'){
                if($eventKey!=='kyurem') throw new RuntimeException('DNA Splicer research is not the active event.');
                $status=pv_event_buy_splicers($db,$uid);
                if($status==='purchased') $flash=['type'=>'success','message'=>'DNA Splicers purchased for ₽500,000. They remain attached to your trainer account.'];
                elseif($status==='already_owned') $flash=['type'=>'info','message'=>'DNA Splicers are already attached to your trainer account.'];
                else $flash=['type'=>'danger','message'=>'You need ₽500,000 to purchase DNA Splicers.'];
            } elseif($action==='fuse_kyurem'){
                if($eventKey!=='kyurem') throw new RuntimeException('Kyurem fusion is not the active event.');
                $kyuremId=filter_var($_POST['kyurem_id']??null,FILTER_VALIDATE_INT);
                $partnerId=filter_var($_POST['partner_id']??null,FILTER_VALIDATE_INT);
                if($kyuremId===false || $partnerId===false) throw new RuntimeException('Select a Kyurem and a fusion partner.');
                $result=pv_event_fuse_kyurem($db,$uid,(int)$kyuremId,(int)$partnerId);
                $flash=['type'=>'success','message'=>'Fusion complete: '.$result['result'].' was created at level 100 with '.$result['ability'].'.'];
            } elseif($action==='claim_cosplay'){
                if($eventKey!=='pikachu2015') throw new RuntimeException('The Cosplay Pikachu challenge is not the active event.');
                $result=pv_event_claim_cosplay_pikachu($db,$uid,(string)($_SESSION['myuser']??''),(string)($_SERVER['REMOTE_ADDR']??''));
                if($result['status']==='claimed') $flash=['type'=>'success','message'=>'Challenge complete. Your one-use promo code has been generated.'];
                elseif($result['status']==='already_claimed') $flash=['type'=>'info','message'=>'This trainer has already completed the Cosplay Pikachu challenge.'];
                else $flash=['type'=>'danger','message'=>'Collection incomplete: '.number_format((int)($result['count']??0)).' of 28 qualifying Unown forms found.'];
            }
        }
    }catch(Throwable $e){
        pv_log('Event Center action failed for user '.$uid.': '.$e->getMessage());
        $flash=['type'=>'danger','message'=>$e->getMessage()];
    }
    $_SESSION['pv_event_flash']=$flash;
    pv_redirect('event_center.php');
}

$flash=$_SESSION['pv_event_flash']??null; unset($_SESSION['pv_event_flash']);
$ticketCount=0;$access=['event_page'=>0,'event'=>''];
try{ $ticketCount=pv_event_ticket_count($db,$uid,false); $access=pv_event_access($db,$uid,false); }
catch(Throwable $e){ pv_log('Event Center read failed for user '.$uid.': '.$e->getMessage()); }
$hasAccess=$eventKey!=='none' && (int)($access['event_page']??0)===1 && (string)($access['event']??'')===$eventKey;

pv_page_start('Event Center','battle_select.php',true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('battle_select.php'); ?>
<main class="pv-main-column">
<section class="pv-page pv-event-center-modern">
    <div class="pv-page-head"><div><span class="pv-eyebrow">SPECIAL OPERATIONS // EVENT CENTER</span><h1>Event Center</h1><p class="pv-subtle">Unlock special challenges, collect event rewards and take part in limited-time activities when an event is active.</p></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('battle_select.php'))?>">Battle Hub</a></div>
    <?php if(is_array($flash)):?><div class="pv-flash <?=pv_h((string)$flash['type'])?>"><?=pv_h((string)$flash['message'])?></div><?php endif;?>

    <div class="pv-event-status-grid">
        <article class="pv-panel"><small>ACTIVE EVENT</small><strong><?=pv_h($eventKey==='none'?'No event running':$catalog[$eventKey]['label'])?></strong><span><?=pv_h($eventKey==='none'?'The Event Center is standing by for the next scheduled challenge.':$catalog[$eventKey]['summary'])?></span></article>
        <article class="pv-panel"><small>EVENT TICKETS</small><strong><?=number_format($ticketCount)?></strong><span>Persistent trainer inventory</span></article>
        <article class="pv-panel"><small>ACCESS</small><strong><?=$hasAccess?'UNLOCKED':'LOCKED'?></strong><span><?=$hasAccess?'Current event authorized for this trainer':'One ticket is required while an event is active'?></span></article>
    </div>

    <?php if($eventKey==='none'):?>
        <section class="pv-panel"><div class="pv-empty-state"><strong>No live event is currently scheduled.</strong><span>Your Event Tickets are preserved. When an event is activated, return here to unlock it without losing existing trainer progress.</span></div></section>
    <?php elseif(!$hasAccess):?>
        <section class="pv-panel pv-event-unlock"><div class="pv-section-heading"><div><span>ACCESS GATE</span><h2>Unlock <?=pv_h($catalog[$eventKey]['label'])?></h2></div><small>1 ticket</small></div>
            <p><?=pv_h($catalog[$eventKey]['summary'])?></p>
            <form method="post"><?=pv_csrf_field()?><button class="pv-button" type="submit" name="unlock" value="1" <?=$ticketCount<=0?'disabled':''?>>Use Event Ticket</button></form>
            <?php if($ticketCount<=0):?><div class="pv-flash info">You currently have no Event Tickets. Your existing account and Pokémon remain unchanged.</div><?php endif;?>
        </section>
    <?php elseif($eventKey==='kyurem'):
        $stmt=$db->prepare('SELECT DNA_Splicers FROM items WHERE uid=? LIMIT 1');$stmt->bind_param('i',$uid);$stmt->execute();$splicerRow=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();
        $hasSplicers=(int)($splicerRow['DNA_Splicers']??0)>0;
        $candidates=pv_event_kyurem_candidates($db,$uid);
    ?>
        <section class="pv-panel pv-event-feature">
            <div class="pv-event-hero"><img src="<?=pv_h(pv_static_file($catalog[$eventKey]['image']))?>" alt="Kyurem fusion event"><div><span class="pv-eyebrow">FUSION RESEARCH</span><h2>Kyurem Black & White</h2><p>Fuse a reserve Kyurem with a matching-form Reshiram or Zekrom. Both Pokémon must be yours and must be off your active team.</p></div></div>
            <?php if(!$hasSplicers):?>
                <div class="pv-event-purchase"><div><small>REQUIRED EQUIPMENT</small><strong>DNA Splicers</strong><span>One-time account purchase · ₽500,000</span></div><form method="post"><?=pv_csrf_field()?><button class="pv-button" type="submit" name="buy_dna_splicers" value="1">Purchase DNA Splicers</button></form></div>
            <?php else:?>
                <div class="pv-flash success">DNA Splicers online. Fusion controls are available.</div>
                <?php if(!$candidates['kyurem'] || !$candidates['partners']):?>
                    <div class="pv-empty-state"><strong>Eligible storage Pokémon are incomplete.</strong><span>You need at least one Kyurem and one matching-form Reshiram or Zekrom outside your active team.</span></div>
                <?php else:?>
                    <form method="post" class="pv-event-fusion-form"><?=pv_csrf_field()?>
                        <label><span>Kyurem</span><select name="kyurem_id" required><?php foreach($candidates['kyurem'] as $p):?><option value="<?=(int)$p['id']?>"><?=pv_h((string)$p['name'])?> · Lv. <?=number_format((int)$p['lvl'])?></option><?php endforeach;?></select></label>
                        <label><span>Reshiram / Zekrom</span><select name="partner_id" required><?php foreach($candidates['partners'] as $p):?><option value="<?=(int)$p['id']?>"><?=pv_h((string)$p['name'])?> · Lv. <?=number_format((int)$p['lvl'])?></option><?php endforeach;?></select></label>
                        <button class="pv-button" type="submit" name="fuse_kyurem" value="1">Fuse Pokémon</button>
                    </form>
                    <p class="pv-subtle">Fusion permanently consumes the selected Reshiram/Zekrom. The selected Kyurem becomes the matching Black or White form at level 100.</p>
                <?php endif;?>
            <?php endif;?>
        </section>
    <?php elseif($eventKey==='pikachu2015'): $status=pv_event_cosplay_status($db,$uid,(string)($_SESSION['myuser']??''));?>
        <section class="pv-panel pv-event-feature">
            <div class="pv-event-hero"><img src="<?=pv_h(pv_static_file($catalog[$eventKey]['image']))?>" alt="Cosplay Pikachu event"><div><span class="pv-eyebrow">COLLECTION CHALLENGE</span><h2>Cosplay Pikachu</h2><p>Collect every normal Unown form from A–Z plus ! and ?. Only Pokémon caught under your current Original Trainer name qualify.</p></div></div>
            <div class="pv-event-progress"><div><small>QUALIFYING UNOWN</small><strong><?=number_format(min(28,(int)$status['count']))?> / 28</strong></div><progress max="28" value="<?=min(28,(int)$status['count'])?>"></progress></div>
            <?php if($status['done']):?>
                <?php if($status['code']):?><div class="pv-event-code"><small>YOUR ONE-USE PROMO CODE</small><strong><?=pv_h((string)$status['code']['code'])?></strong><span>Prize: <?=pv_h((string)$status['code']['prize'])?></span></div><?php else:?><div class="pv-flash info">This challenge was completed and its promo code has already been consumed.</div><?php endif;?>
            <?php else:?>
                <form method="post"><?=pv_csrf_field()?><button class="pv-button" type="submit" name="claim_cosplay" value="1" <?=(int)$status['count']<28?'disabled':''?>>Verify Collection & Claim Code</button></form>
            <?php endif;?>
        </section>
    <?php endif;?>

    <section class="pv-panel"><div class="pv-section-heading"><div><span>EVENT RULES</span><h2>How access works</h2></div></div><div class="pv-event-rules"><p><strong>Event Tickets stay in your inventory until used.</strong> One ticket is spent when you unlock an active event for the first time.</p><p><strong>Meet the event requirements to claim rewards.</strong> Make sure you have the required Pokémon, items or money before starting an event action.</p><p><strong>No active event means no ticket is spent.</strong> The unlock button only becomes available when an event is running.</p></div></section>
</section>
</main>
</div>
<?php pv_page_end(); ?>
