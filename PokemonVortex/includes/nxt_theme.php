<?php
/** Pokemon Vortex NXT presentation metadata. No database or gameplay access. */
declare(strict_types=1);

function pv_nxt_section(string $route): string {
    $groups = [
        'adventure' => ['dashboard.php','map_select.php','map.php','world_map.php','sidequest.php','sidequest_progress.php'],
        'battle' => ['battle_select.php','battle.php','wildbattle.php','live_battle.php','live_battle_arena.php','live_battle_result.php','bot_live_battle.php','event_center.php'],
        'ranked' => ['rankings.php','rival_hub.php','ai_activity.php','bot_trainer.php'],
        'collection' => ['your_pokemon.php','pokedex.php','dex.php','change_team.php','your_team.php','change_attacks.php','evolve.php','fossil_lab.php'],
        'trade' => ['items.php','trade.php','put_up_for_trade.php','make_an_offer.php','view_offers.php'],
        'community' => ['community.php','members.php','messages.php','clans.php','chat.php'],
        'account' => ['your_account.php','options.php','login.php','signup.php','forgot_password.php','_setup.php'],
    ];
    foreach ($groups as $section => $routes) {
        if (in_array($route, $routes, true)) return $section;
    }
    return 'world';
}

function pv_nxt_brand(string $caption = 'A WORLD OF POKÉMON'): string {
    return '<span class="pv-brand-copy"><b class="nxt-brand-title">Pokemon Vortex <em>NXT</em></b><small>' . pv_h($caption) . '</small></span>';
}

function pv_nxt_sprite_lineup(array $names, string $class = ''): void {
    echo '<div class="nxt-sprite-lineup ' . pv_h($class) . '" aria-hidden="true">';
    foreach (array_slice($names, 0, 6) as $index => $name) {
        echo '<img width="96" height="96" style="--nxt-order:' . $index . '" src="'
            . pv_h(pv_static_file('images/pokemon/' . $name . '.gif', 'images/Pokeball.PNG'))
            . '" alt="" loading="eager" decoding="async">';
    }
    echo '</div>';
}

/** Apply only to full HTML documents. JSON, action responses and battle fragments stay bare. */
function pv_nxt_document(string $html): string {
    if (stripos($html, '<html') === false || stripos($html, '</head>') === false) return $html;
    $route = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $section = pv_nxt_section($route);
    $html = preg_replace_callback('~<body\b([^>]*)>~i', static function (array $match) use ($route, $section): string {
        if (stripos($match[1], 'data-nxt-section=') !== false) return $match[0];
        return '<body' . $match[1] . ' data-nxt-section="' . pv_h($section) . '" data-nxt-route="'
            . pv_h($route) . '" data-nxt-images="' . pv_h(pv_static('images/')) . '">';
    }, $html, 1) ?? $html;
    if (stripos($html, 'vortex-nxt.css') === false) {
        $version = pv_h(pv_asset_version());
        $assets = '<link rel="stylesheet" href="' . pv_h(pv_asset('css/vortex-nxt.css')) . '?v=' . $version . '">'
            . '<script defer src="' . pv_h(pv_asset('js/vortex-nxt.js')) . '?v=' . $version . '"></script>';
        $html = preg_replace('~</head>~i', $assets . '</head>', $html, 1) ?? $html;
    }
    return $html;
}
