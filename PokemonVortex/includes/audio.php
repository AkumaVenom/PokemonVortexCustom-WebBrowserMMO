<?php
/** v32 account audio preferences and presentation-only soundtrack routing. */
declare(strict_types=1);

function pv_audio_defaults(): array {
    return ['enabled'=>true, 'music'=>35, 'effects'=>70, 'revision'=>0];
}

function pv_audio_preferences(mysqli $db, int $uid): array {
    $stmt = $db->prepare('SELECT sound_enabled,music_volume,sfx_volume,audio_revision FROM members WHERE id=? LIMIT 1');
    if (!$stmt) throw new RuntimeException('Sound settings are unavailable.');
    $stmt->bind_param('i', $uid);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Sound settings are unavailable.'); }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new RuntimeException('Trainer account is unavailable.');
    return ['enabled'=>(int)$row['sound_enabled']===1, 'music'=>max(0,min(100,(int)$row['music_volume'])),
        'effects'=>max(0,min(100,(int)$row['sfx_volume'])), 'revision'=>(int)$row['audio_revision']];
}

function pv_audio_catalog(): array {
    static $catalog;
    if ($catalog === null) {
        $raw = @file_get_contents(dirname(__DIR__).'/assets/audio/manifest.json');
        $catalog = $raw !== false ? (json_decode($raw, true) ?: []) : [];
    }
    return $catalog;
}

function pv_audio_map_track(string $world, string $area, bool $night = false): string {
    static $maps;
    if ($maps === null) {
        $raw = @file_get_contents(dirname(__DIR__).'/config/audio_maps.json');
        $maps = $raw !== false ? (json_decode($raw, true) ?: []) : [];
    }
    $row = $maps[$world][$area] ?? [];
    return (string)(($night ? ($row['night_track_key'] ?? null) : null) ?? $row['track_key'] ??
        ['vortex'=>'emerald.359','kanto'=>'firered.291','hoenn'=>'emerald.359','johto'=>'crystal.38','unbound'=>'unbound.291'][$world] ?? 'emerald.359');
}

function pv_audio_battle_kind(): string {
    if (isset($GLOBALS['pv_audio_kind'])) return (string)$GLOBALS['pv_audio_kind'];
    $label = strtolower((string)($_SESSION['opponent_profile'][1] ?? ''));
    $type = (string)($_SESSION['opponent_profile'][3] ?? '');
    if (str_contains($label, 'champion') || preg_match('/steven|wallace|cynthia|alder|diantha|leon/', $label)) return 'champion';
    if (str_contains($label,'elite')) return 'elite';
    if (preg_match('/magma|aqua|rocket|galactic|plasma|flare/', $label)) return 'team';
    if (str_contains($label,'frontier')) return 'frontier';
    if ($type === 'gym') return 'gym';
    if (!empty($_SESSION['pv_rival_battle'])) return 'rival';
    return 'trainer';
}

function pv_audio_context(): array {
    $route = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $section = function_exists('pv_nxt_section') ? pv_nxt_section($route) : 'world';
    $track = ['adventure'=>'emerald.405','battle'=>'emerald.457','ranked'=>'emerald.465',
        'collection'=>'emerald.383','trade'=>'emerald.404','community'=>'emerald.433',
        'account'=>'emerald.400','world'=>'emerald.405'][$section] ?? 'emerald.405';
    $battle = [];
    if ($route === 'map.php') $track = pv_audio_map_track('vortex',(string)($GLOBALS['map'] ?? 1),!empty($_SESSION['night']));
    if ($route === 'world_map.php') $track = pv_audio_map_track((string)($GLOBALS['world'] ?? ''),(string)($GLOBALS['areaKey'] ?? ''),!empty($_SESSION['night']));
    if ($route === 'map_select.php') $track = pv_audio_map_track((string)($GLOBALS['world'] ?? $_GET['world'] ?? 'vortex'),'__selection',!empty($_SESSION['night']));
    if ($route === 'wildbattle.php' && is_array($GLOBALS['state'] ?? null)) {
        $state = $GLOBALS['state'];
        $battle = ['mode'=>'wild','id'=>'wild:'.(string)($state['id'] ?? ''),'kind'=>'wild','status'=>(string)($state['status'] ?? 'active')];
        $name = strtolower((string)($state['wild']['name'] ?? ''));
        $track = ['rayquaza'=>'battle.470','mew'=>'battle.472','regirock'=>'battle.479','regice'=>'battle.479','registeel'=>'battle.479',
            'kyogre'=>'battle.480','groudon'=>'battle.480','deoxys'=>'battle.551','lugia'=>'battle.553','ho-oh'=>'battle.553'][$name] ?? 'battle.474';
    } elseif ($route === 'battle.php') {
        $kind = pv_audio_battle_kind();
        $battle = ['mode'=>'standard','id'=>'standard:'.(string)($_SESSION['pv_audio_standard_id'] ?? ''),'kind'=>$kind,
            'eventId'=>(string)($GLOBALS['pv_audio_event_id'] ?? ''), 'itemAccepted'=>!empty($GLOBALS['pv_audio_item_accepted']),
            'switchAccepted'=>!empty($GLOBALS['pv_audio_switch_accepted']), 'status'=>(string)($GLOBALS['pv_audio_battle_status'] ?? 'active')];
        $track = ['champion'=>'battle.478','elite'=>'battle.482','team'=>'battle.475','frontier'=>'battle.471','gym'=>'battle.477','rival'=>'battle.481'][$kind] ?? 'battle.476';
    } elseif (in_array($route,['live_battle.php','live_battle_result.php'],true)) {
        $status = 'active';
        if ($route === 'live_battle_result.php' && !empty($GLOBALS['settled'])) $status = ($GLOBALS['outcome'] ?? '') === 'win' ? 'won' : 'lost';
        elseif (($GLOBALS['state']['phase'] ?? '') === 'complete') $status = (int)($GLOBALS['state']['winner_slot'] ?? 0)===(int)($GLOBALS['userSlot'] ?? 0) ? 'won' : 'lost';
        $battle = ['mode'=>'live','id'=>'live:'.(int)($GLOBALS['battleId'] ?? 0),'kind'=>'trainer','status'=>$status];
        $track = 'battle.476';
    }
    return ['track'=>$track,'battle'=>$battle];
}

/** One global control in both modern and recovered full-document shells. Never AJAX. */
function pv_audio_document(string $html): string {
    if (stripos($html,'<html') === false || stripos($html,'</head>') === false || stripos($html,'id="pv-audio-config"') !== false) return $html;
    $uid = (int)($_SESSION['access'] ?? 0) === 9 ? max(0,(int)($_SESSION['myid'] ?? 0)) : 0;
    $prefs = pv_audio_defaults(); $available = true;
    if ($uid > 0) {
        try { $prefs = pv_audio_preferences(pv_db(),$uid); }
        catch (Throwable $e) { $available=false; $prefs['enabled']=false; }
    }
    $context = pv_audio_context();
    $config = ['account'=>$uid,'base'=>pv_base(),'preferences'=>$prefs,'available'=>$available,
        'csrf'=>pv_csrf_token(),'endpoint'=>pv_url('audio_settings.php'),
        'manifest'=>pv_asset('audio/manifest.json').'?v=32.0.3','context'=>$context];
    $json = json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $head = '<link rel="stylesheet" href="'.pv_h(pv_asset('css/vortex-audio.css')).'?v=32.0.1">'
        .'<script type="application/json" id="pv-audio-config">'.$json.'</script>'
        .'<script defer src="'.pv_h(pv_asset('js/vortex-audio.js')).'?v=32.0.3"></script>'
        .'<script defer src="'.pv_h(pv_asset('js/vortex-battle-audio.js')).'?v=32.0.0"></script>';
    $html = preg_replace('~</head>~i',$head.'</head>',$html,1) ?? $html;
    $control = '<aside class="pv-audio-dock" id="pv-audio-dock" aria-label="Game sound">'
        .'<div class="pv-audio-buttons"><button type="button" id="pv-audio-toggle" aria-pressed="'.($prefs['enabled'] ? 'true' : 'false').'" disabled>'.($prefs['enabled'] ? 'Sound on' : 'Sound off').'</button>'
        .'<button type="button" id="pv-audio-resume" hidden>Play sound</button>'
        .'<button type="button" id="pv-audio-details" aria-expanded="false" aria-controls="pv-audio-panel" aria-label="Sound settings" disabled>♫</button></div>'
        .'<div id="pv-audio-panel" class="pv-audio-panel" hidden><strong>Game sound</strong>'
        .'<label for="pv-audio-music">Music <output id="pv-audio-music-value">35%</output></label><input id="pv-audio-music" type="range" min="0" max="100" value="35">'
        .'<label for="pv-audio-effects">Effects <output id="pv-audio-effects-value">70%</output></label><input id="pv-audio-effects" type="range" min="0" max="100" value="70">'
        .'<p id="pv-audio-track"></p><p id="pv-audio-status" role="status">'.($available ? ($uid ? 'Saved to your account.' : 'Saved on this device.') : 'Run Upgrade / Repair to enable account sound.').'</p>'
        .'</div></aside>';
    return preg_replace('~</body>~i',$control.'</body>',$html,1) ?? $html;
}
