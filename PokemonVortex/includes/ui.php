<?php
require_once __DIR__ . '/bootstrap.php';

function pv_page_start(string $title, string $active = '', bool $loggedIn = false): void {
    $app = pv_h((string)pv_config('app_name', 'Pokemon Vortex NXT'));
    $version = pv_asset_version();
    $nav = $loggedIn ? [
        'dashboard.php' => ['Dashboard','images/items/Poke Ball.png'],
        'map_select.php' => ['Explore','images/pokemon/Eevee.gif'],
        'battle_select.php' => ['Battle','images/misc/gym.gif'],
        'rival_hub.php' => ['Rival Hub','images/items/Ultra Ball.png'],
        'rankings.php' => ['Rankings','images/items/Master Ball.png'],
        'your_pokemon.php' => ['Pokémon','images/pokemon/Pikachu.gif'],
        'trade.php' => ['Trade','images/items/Great Ball.png'],
        'community.php' => ['Community','images/sprites/2whole.gif'],
    ] : [
        'index.php' => ['Home','images/items/Poke Ball.png'],
        'news.php' => ['News','images/pokemon/Pikachu.gif'],
        'about.php' => ['About','images/pokemon/Eevee.gif'],
        'login.php' => ['Log In','images/items/Great Ball.png'],
        'signup.php' => ['Create Account','images/items/Ultra Ball.png'],
    ];
    $trainer = $loggedIn ? trim((string)($_SESSION['myuser'] ?? 'Trainer')) : '';
    $trainerInitial = $trainer !== '' ? strtoupper(mb_substr($trainer, 0, 1)) : 'T';

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="color-scheme" content="light"><meta name="theme-color" content="#2a75bb">';
    echo '<meta name="description" content="Explore, battle, collect and trade Pokémon in a persistent browser RPG.">';
    echo '<meta name="robots" content="index,follow">';
    echo '<title>' . pv_h($title) . ' · ' . $app . '</title>';
    echo '<link rel="icon" href="' . pv_h(pv_url('favicon.png')) . '">';
    echo '<link rel="stylesheet" href="' . pv_h(pv_asset('css/vortex-modern.css')) . '?v=' . $version . '">';
    echo '<script defer src="' . pv_h(pv_asset('js/vortex-modern.js')) . '?v=' . $version . '"></script>';
    echo '</head><body class="pv-modern-page pv-pokemon-theme-v25 ' . ($loggedIn ? 'pv-session-active' : 'pv-session-public') . '">';
    echo '<a class="pv-skip-link" href="#pv-main-content">Skip to content</a>';
    echo '<div class="pv-atmosphere" aria-hidden="true"><i></i><i></i><i></i><i></i></div>';
    echo '<div class="pv-global-spritefield" aria-hidden="true">';
    foreach (['Pikachu','Eevee','Charizard','Gengar','Lucario','Mudkip','Treecko','Torchic','Snorlax','Dragonite'] as $decorName) {
        echo '<img src="' . pv_h(pv_static_file('images/pokemon/'.$decorName.'.gif','images/Pokeball.PNG')) . '" alt="">';
    }
    echo '<i class="pv-float-ball ball-a"><img src="' . pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG')) . '" alt=""></i>';
    echo '<i class="pv-float-ball ball-b"><img src="' . pv_h(pv_static_file('images/items/Great Ball.png','images/Pokeball.PNG')) . '" alt=""></i>';
    echo '<i class="pv-float-ball ball-c"><img src="' . pv_h(pv_static_file('images/items/Ultra Ball.png','images/Pokeball.PNG')) . '" alt=""></i>';
    echo '</div>';
    echo '<div class="pv-site-shell">';

    echo '<header class="pv-topbar">';
    echo '<a class="pv-brand" href="' . pv_h(pv_url($loggedIn ? 'dashboard.php' : 'index.php')) . '">';
    echo '<span class="pv-brand-mark"><img src="' . pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG')) . '" alt=""></span>' . pv_nxt_brand() . '</a>';
    echo '<button class="pv-nav-toggle" type="button" aria-expanded="false" aria-controls="pv-primary-nav" data-pv-nav-toggle><span></span><span></span><span></span><b>Menu</b></button>';
    echo '<nav class="pv-nav" id="pv-primary-nav" aria-label="Primary">';
    foreach ($nav as $href => $entry) {
        $label = is_array($entry) ? (string)$entry[0] : (string)$entry;
        $icon = is_array($entry) ? (string)($entry[1] ?? '') : '';
        $cls = ($active === $href) ? 'active' : '';
        $current = $active === $href ? ' aria-current="page"' : '';
        echo '<a class="' . $cls . '"' . $current . ' href="' . pv_h(pv_url($href)) . '">';
        if ($icon !== '') echo '<img class="pv-nav-icon" src="' . pv_h(pv_static_file($icon,'images/items/Poke Ball.png')) . '" alt="">';
        echo '<span>' . pv_h($label) . '</span></a>';
    }
    if ($loggedIn) {
        echo '<a class="pv-trainer-chip ' . ($active === 'your_account.php' ? 'active' : '') . '" href="' . pv_h(pv_url('your_account.php')) . '"><span>' . pv_h($trainerInitial) . '</span><em>' . pv_h($trainer ?: 'Trainer') . '</em></a>';
        echo '<a class="danger" href="' . pv_h(pv_url('logout.php')) . '">Log Out</a>';
    }
    echo '</nav></header>';

    echo '<div class="pv-command-strip" aria-label="Explore regions">';
    echo '<span class="pv-command-state">' . ($loggedIn ? 'WORLD SELECT' : 'EXPLORE THE WORLD') . '</span>';
    foreach (['vortex' => 'Vortex World', 'kanto' => 'Kanto', 'johto' => 'Johto', 'hoenn' => 'Hoenn'] as $world => $label) {
        echo '<a href="' . pv_h(pv_url('map_select.php?world=' . $world)) . '">' . pv_h($label) . '</a>';
    }
    echo '</div>';
    echo '<div id="pv-main-content" tabindex="-1"></div>';
}

function pv_page_end(): void {
    echo '<footer class="pv-footer">';
    echo '<div class="pv-footer-main"><strong>Pokemon Vortex NXT</strong><span>Explore · Battle · Collect · Trade</span></div>';
    echo '<div class="pv-footer-links">';
    echo '<button class="nxt-motion-toggle" type="button" data-nxt-motion aria-pressed="false" hidden>Pause motion</button>';
    echo '<a href="' . pv_h(pv_url('contactus.php')) . '">Support</a>';
    echo '<a href="' . pv_h(pv_url('terms.php')) . '">Terms</a>';
    echo '<a href="' . pv_h(pv_url('privacy.php')) . '">Privacy</a>';
    echo '<a href="' . pv_h(pv_url('legal.php')) . '">Legal</a>';
    echo '<a href="' . pv_h(pv_url('credits.php')) . '">Credits</a>';
    echo '</div><div class="pv-footer-signal"><i></i><span>Pokemon Vortex NXT</span></div>';
    echo '</footer></div></body></html>';
}

function pv_game_side_menu(string $active = ''): void {
    $groups = [
        'Adventure' => [
            'dashboard.php' => 'Trainer Home',
            'map_select.php' => 'World Maps',
            'sidequest.php' => 'Sidequests',
        ],
        'Competitive' => [
            'rival_hub.php' => 'Rival Hub',
            'rankings.php' => 'Trainer Rankings',
            'ai_activity.php' => 'AI Activity',
            'battle_select.php' => 'Battle Arena',
            'live_battle_arena.php' => 'Live PvP',
            'event_center.php' => 'Event Center',
        ],
        'Collection' => [
            'your_pokemon.php' => 'Your Pokémon',
            'change_team.php' => 'Change Team',
            'pokedex.php' => 'Pokédex',
            'items.php' => 'Items & Shop',
            'fossil_lab.php' => 'Fossil Lab',
            'trade.php' => 'Trade Center',
        ],
        'Trainer Network' => [
            'community.php' => 'Community',
            'messages.php' => 'Messages',
            'clans.php' => 'Clans',
            'members.php' => 'Trainers',
        ],
        'Account' => [
            'your_account.php' => 'Your Account',
            'options.php' => 'Options',
        ],
    ];
    $icons = ['Adventure'=>'Eevee','Competitive'=>'Lucario','Collection'=>'Pikachu','Trainer Network'=>'Chatot','Account'=>'Rotom'];
    $trainer = trim((string)($_SESSION['myuser'] ?? 'Trainer'));
    echo '<aside class="pv-side-menu" aria-label="Game navigation">';
    echo '<div class="pv-side-profile"><img class="pv-side-pokeball" src="' . pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG')) . '" alt=""><span class="pv-side-signal"><i></i>ONLINE</span><strong>' . pv_h($trainer ?: 'Trainer') . '</strong><small>TRAINER PROFILE</small></div>';
    foreach ($groups as $group => $links) {
        echo '<div class="pv-side-group"><div class="pv-side-label"><img width="28" height="28" src="' . pv_h(pv_static_file('images/pokemon/'.$icons[$group].'.gif')) . '" alt="">' . pv_h($group) . '</div>';
        foreach ($links as $href => $label) {
            echo '<a class="' . ($active === $href ? 'active' : '') . '"' . ($active === $href ? ' aria-current="page"' : '') . ' href="' . pv_h(pv_url($href)) . '"><span>' . pv_h($label) . '</span><i>›</i></a>';
        }
        echo '</div>';
    }
    echo '</aside>';
}

function pv_pokemon_banner(string $eyebrow, string $title, string $description, array $pokemon = ['Pikachu','Eevee','Lucario']): void {
    echo '<section class="pv-pokemon-banner"><div class="pv-pokemon-banner-copy"><span class="pv-eyebrow">' . pv_h($eyebrow) . '</span><h1>' . pv_h($title) . '</h1><p>' . pv_h($description) . '</p></div><div class="pv-pokemon-banner-team" aria-hidden="true">';
    $slot=0;
    foreach (array_slice($pokemon,0,3) as $name) {
        $slot++;
        echo '<span class="slot-'.$slot.'"><img src="' . pv_h(pv_static_file('images/pokemon/'.$name.'.gif','images/Pokeball.PNG')) . '" alt=""></span>';
    }
    echo '<i><img src="' . pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG')) . '" alt=""></i></div></section>';
}

function pv_pokemon_image(string $name): string {
    return pv_static_file('images/pokemon/' . $name . '.gif');
}
