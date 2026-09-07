<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/map_runtime.php';
require_once __DIR__ . '/includes/bot_runtime.php';
require_once __DIR__ . '/includes/ui.php';
pv_require_login();

try {
    $db = pv_db();
} catch (Throwable $e) {
    pv_log('Map page DB unavailable: ' . $e->getMessage());
    pv_redirect('dashboard.php?service=unavailable');
}

$catalog = pv_map_catalog();
$requested = filter_input(INPUT_GET, 'map', FILTER_VALIDATE_INT);
$map = is_int($requested) ? max(1, min(25, $requested)) : max(1, min(25, (int)($_SESSION['map'] ?? 1)));
$uid = (int)$_SESSION['myid'];
$worldKey = 'vortex';

$blocks = pv_map_blocks($db, $map);
if ((int)($_SESSION['map'] ?? 0) === $map) {
    [$x, $y] = pv_map_position($db, $uid, $map, $worldKey);
} else {
    [$x, $y] = pv_map_spawn($map, $blocks);
}
pv_map_upsert_player($db, $uid, $map, $x, $y, $worldKey);
pv_bot_tick($db, 48, $worldKey, (string)$map, 45);

$players = pv_map_players($db, $uid, $map, $worldKey);
$blockedDirections = pv_map_blocked_directions($db, $map, $x, $y);
[$category, $mapName] = $catalog[$map];
$trainer = max(1, min(29, (int)($_SESSION['map_preferences'][2] ?? 1)));
$night = (int)($_SESSION['night'] ?? 0) === 1;
$overlayRel = 'images/maps/overlays/' . ($night ? 'night' : 'day') . $map . '.png';
$overlayAbs = __DIR__ . '/html/static/' . $overlayRel;
$overlayUrl = is_file($overlayAbs) ? pv_static($overlayRel) : '';
$mapImage = pv_static_file('images/maps/v3/map' . $map . '.png', 'images/maps/map' . $map . '.png');
$mapCount = count($players) + 1;

pv_page_start($mapName, 'map_select.php', true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('map_select.php'); ?>
<main class="pv-main-column">
    <section class="pv-page pv-map-page">
        <div class="pv-page-head pv-map-head">
            <div>
                <span class="pv-eyebrow">WORLD EXPLORATION // MAP <?= (int)$map ?></span>
                <h1><?= pv_h($mapName) ?></h1>
                <p class="pv-subtle">Explore the Vortex world, search for wild Pokémon and move between connected regions.</p>
            </div>
            <div class="pv-map-head-actions">
                <a class="pv-button pv-button-secondary" href="<?= pv_h(pv_url('map_select.php')) ?>">All maps</a>
                <a class="pv-button" href="<?= pv_h(pv_url('options.php')) ?>">Map options</a>
            </div>
        </div>

        <div class="pv-map-layout">
            <div class="pv-map-console">
                <div class="pv-map-console-top">
                    <span><i></i> EXPLORATION READY</span>
                    <b><?= pv_h(strtoupper($category)) ?> BIOME</b>
                    <em id="pv-map-coordinates">X <?= (int)$x ?> // Y <?= (int)$y ?></em>
                </div>
                <div class="pv-map-viewport-wrap" aria-label="<?= pv_h($mapName) ?> exploration map">
                    <div class="pv-map-stage<?= $night ? ' is-night' : '' ?>" id="pv-map-stage">
                        <img class="pv-map-art" id="pv-map-art" src="<?= pv_h($mapImage) ?>" alt="<?= pv_h($mapName) ?> map" draggable="false">
                        <?php if ($overlayUrl !== ''): ?><img class="pv-map-weather-overlay" src="<?= pv_h($overlayUrl) ?>" alt="" aria-hidden="true"><?php endif; ?>
                        <div class="pv-map-grid" aria-hidden="true"></div>
                        <div class="pv-map-actors" id="pv-map-actors"></div>
                    </div>
                </div>
                <noscript><div class="pv-map-noscript">Map movement requires JavaScript enabled in your browser.</div></noscript>
                <div class="pv-map-console-bottom">
                    <span id="pv-map-status">Ready. Use WASD, arrow keys, numpad or the movement pad.</span>
                    <span><?= $night ? 'NIGHT' : 'DAY' ?> CYCLE</span>
                </div>
            </div>

            <aside class="pv-map-sidebar">
                <section class="pv-map-control-panel">
                    <div class="pv-map-panel-label">MOVEMENT CONTROL</div>
                    <div class="pv-map-compass" aria-label="Map movement controls">
                        <button type="button" data-map-direction="5" aria-label="Move up-left">↖</button>
                        <button type="button" data-map-direction="1" aria-label="Move up">↑</button>
                        <button type="button" data-map-direction="7" aria-label="Move up-right">↗</button>
                        <button type="button" data-map-direction="3" aria-label="Move left">←</button>
                        <div class="pv-map-trainer-core"><img src="<?= pv_h(pv_static_file('images/sprites/' . $trainer . 'whole.gif', 'images/sprites/1whole.gif')) ?>" alt="Your trainer"></div>
                        <button type="button" data-map-direction="4" aria-label="Move right">→</button>
                        <button type="button" data-map-direction="6" aria-label="Move down-left">↙</button>
                        <button type="button" data-map-direction="2" aria-label="Move down">↓</button>
                        <button type="button" data-map-direction="8" aria-label="Move down-right">↘</button>
                    </div>
                    <p>Routes follow the terrain, ledges and connected region gates. Use the movement pad or keyboard to explore.</p>
                </section>

                <section class="pv-map-encounter-panel">
                    <div class="pv-map-panel-label">WILD ENCOUNTER SCANNER</div>
                    <div id="pv-map-encounter" aria-live="polite">
                        <div class="pv-map-quiet"><strong>Scanning the area.</strong><span>Move around the map to search for wild Pokémon.</span></div>
                    </div>
                </section>

                <section class="pv-map-region-panel">
                    <div class="pv-map-panel-label">AREA INFO</div>
                    <dl>
                        <div><dt>Biome</dt><dd><?= pv_h($category) ?></dd></div>
                        <div><dt>Map</dt><dd><?= (int)$map ?> / 25</dd></div>
                        <div><dt>Trainers here</dt><dd><?= (int)$mapCount ?></dd></div>
                        <div><dt>Cycle</dt><dd><?= $night ? 'Night' : 'Day' ?></dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </section>
</main>
</div>
<script>
window.PV_MAP_CONFIG = <?= json_encode([
    'base'=>pv_base(),
    'world'=>$worldKey,
    'map'=>$map,
    'x'=>$x,
    'y'=>$y,
    'trainer'=>$trainer,
    'players'=>$players,
    'blocked'=>array_keys($blocks),
    'blockedDirections'=>$blockedDirections,
    'moveUrl'=>pv_url('map_move.php'),
    'presenceUrl'=>pv_url('map_presence.php'),
    'botProfileBase'=>pv_url('bot_trainer.php?id='),
    'presenceInterval'=>2000,
    'csrf'=>pv_csrf_token(),
    'spriteBase'=>pv_static('images/sprites/'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script defer src="<?= pv_h(pv_asset('js/vortex-map.js')) ?>?v=<?= pv_asset_version() ?>"></script>
<?php pv_page_end(); ?>
