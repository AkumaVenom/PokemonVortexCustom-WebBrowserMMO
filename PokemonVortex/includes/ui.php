<?php
require_once __DIR__ . '/bootstrap.php';

function pv_page_start(string $title, string $active = '', bool $loggedIn = false): void {
    $app = pv_h((string)pv_config('app_name', 'Pokémon Vortex'));
    $version = pv_asset_version();
    $nav = $loggedIn ? [
        'dashboard.php' => 'Dashboard',
        'map_select.php' => 'Explore',
        'battle_select.php' => 'Battle',
        'your_pokemon.php' => 'Pokémon',
        'trade.php' => 'Trade',
        'community.php' => 'Community',
    ] : [
        'index.php' => 'Home',
        'news.php' => 'News',
        'about.php' => 'About',
        'login.php' => 'Log In',
        'signup.php' => 'Create Account',
    ];
    $trainer = $loggedIn ? trim((string)($_SESSION['myuser'] ?? 'Trainer')) : '';
    $trainerInitial = $trainer !== '' ? strtoupper(mb_substr($trainer, 0, 1)) : 'T';

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="color-scheme" content="dark"><meta name="theme-color" content="#020912">';
    echo '<meta name="description" content="Explore, battle, collect and trade Pokémon in a persistent browser RPG.">';
    echo '<meta name="robots" content="index,follow">';
    echo '<title>' . pv_h($title) . ' · ' . $app . '</title>';
    echo '<link rel="icon" href="' . pv_h(pv_url('favicon.png')) . '">';
    echo '<link rel="stylesheet" href="' . pv_h(pv_asset('css/vortex-modern.css')) . '?v=' . $version . '">';
    echo '<script defer src="' . pv_h(pv_asset('js/vortex-modern.js')) . '?v=' . $version . '"></script>';
    echo '</head><body class="pv-modern-page ' . ($loggedIn ? 'pv-session-active' : 'pv-session-public') . '">';
    echo '<a class="pv-skip-link" href="#pv-main-content">Skip to content</a>';
    echo '<div class="pv-atmosphere" aria-hidden="true"><i></i><i></i><i></i><i></i></div>';
    echo '<div class="pv-site-shell">';

    echo '<header class="pv-topbar">';
    echo '<a class="pv-brand" href="' . pv_h(pv_url($loggedIn ? 'dashboard.php' : 'index.php')) . '">';
    echo '<span class="pv-brand-mark">PV</span><span class="pv-brand-copy">POKÉMON VORTEX<small>ONLINE BATTLE RPG</small></span></a>';
    echo '<button class="pv-nav-toggle" type="button" aria-expanded="false" aria-controls="pv-primary-nav" data-pv-nav-toggle><span></span><span></span><span></span><b>Menu</b></button>';
    echo '<nav class="pv-nav" id="pv-primary-nav" aria-label="Primary">';
    foreach ($nav as $href => $label) {
        $cls = ($active === $href) ? 'active' : '';
        echo '<a class="' . $cls . '" href="' . pv_h(pv_url($href)) . '">' . pv_h($label) . '</a>';
    }
    if ($loggedIn) {
        echo '<a class="pv-trainer-chip ' . ($active === 'your_account.php' ? 'active' : '') . '" href="' . pv_h(pv_url('your_account.php')) . '"><span>' . pv_h($trainerInitial) . '</span><em>' . pv_h($trainer ?: 'Trainer') . '</em></a>';
        echo '<a class="danger" href="' . pv_h(pv_url('logout.php')) . '">Log Out</a>';
    }
    echo '</nav></header>';

    echo '<div class="pv-command-strip" aria-label="Game status">';
    echo '<span class="pv-command-state"><i></i>' . ($loggedIn ? 'TRAINER ONLINE' : 'START YOUR JOURNEY') . '</span>';
    echo '<span>WORLD // VORTEX</span><span>MODE // ONLINE RPG</span><span class="pv-command-tail">EXPLORE · BATTLE · COLLECT · TRADE</span>';
    echo '</div>';
    echo '<div id="pv-main-content" tabindex="-1"></div>';
}

function pv_page_end(): void {
    echo '<footer class="pv-footer">';
    echo '<div class="pv-footer-main"><strong>Pokémon Vortex</strong><span>Explore · Battle · Collect · Trade</span></div>';
    echo '<div class="pv-footer-links">';
    echo '<a href="' . pv_h(pv_url('contactus.php')) . '">Support</a>';
    echo '<a href="' . pv_h(pv_url('terms.php')) . '">Terms</a>';
    echo '<a href="' . pv_h(pv_url('privacy.php')) . '">Privacy</a>';
    echo '<a href="' . pv_h(pv_url('legal.php')) . '">Legal</a>';
    echo '<a href="' . pv_h(pv_url('credits.php')) . '">Credits</a>';
    echo '</div><div class="pv-footer-signal"><i></i><span>POKÉMON VORTEX</span></div>';
    echo '</footer></div></body></html>';
}

function pv_game_side_menu(string $active = ''): void {
    $groups = [
        'Adventure' => [
            'dashboard.php' => 'Overview',
            'map_select.php' => 'World Maps',
            'battle_select.php' => 'Battle Arena',
            'sidequest.php' => 'Sidequests',
        ],
        'Collection' => [
            'your_pokemon.php' => 'Your Pokémon',
            'change_team.php' => 'Change Team',
            'pokedex.php' => 'Pokédex',
            'items.php' => 'Items & Shop',
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
    $trainer = trim((string)($_SESSION['myuser'] ?? 'Trainer'));
    echo '<aside class="pv-side-menu" aria-label="Game navigation">';
    echo '<div class="pv-side-profile"><span class="pv-side-signal"><i></i>ONLINE</span><strong>' . pv_h($trainer ?: 'Trainer') . '</strong><small>TRAINER PROFILE</small></div>';
    foreach ($groups as $group => $links) {
        echo '<div class="pv-side-group"><div class="pv-side-label">' . pv_h($group) . '</div>';
        foreach ($links as $href => $label) {
            echo '<a class="' . ($active === $href ? 'active' : '') . '" href="' . pv_h(pv_url($href)) . '"><span>' . pv_h($label) . '</span><i>›</i></a>';
        }
        echo '</div>';
    }
    echo '</aside>';
}

function pv_pokemon_image(string $name): string {
    return pv_static_file('images/pokemon/' . $name . '.gif');
}
