<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/gameplay.php';
require_once __DIR__ . '/includes/evolution.php';

pv_require_login();
$db = pv_db();
$uid = (int)$_SESSION['myid'];
$pokemonId = pv_request_int('pid', 0, 0);
if ($pokemonId < 1 && isset($_POST['pokemon_id'])) {
    $pokemonId = max(0, (int)$_POST['pokemon_id']);
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    pv_require_csrf();
    $pokemonId = max(0, (int)($_POST['pokemon_id'] ?? 0));
    $ruleKey = strtolower(trim((string)($_POST['rule_key'] ?? '')));
    $replaceMoves = isset($_POST['replace_moves']) && (string)$_POST['replace_moves'] === '1';
    try {
        $result = pv_evolve_pokemon($db, $uid, $pokemonId, $ruleKey, $replaceMoves);
        $_SESSION['pv_evolution_flash'] = $result;
        pv_redirect('evolve.php?pid=' . $pokemonId, 303);
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$flash = null;
if (!empty($_SESSION['pv_evolution_flash']) && is_array($_SESSION['pv_evolution_flash'])) {
    $candidateFlash = $_SESSION['pv_evolution_flash'];
    unset($_SESSION['pv_evolution_flash']);
    if ((int)($candidateFlash['pokemon_id'] ?? 0) === $pokemonId) $flash = $candidateFlash;
}

$pokemon = $pokemonId > 0 ? pv_evolution_load_owned_pokemon($db, $uid, $pokemonId) : null;
$options = $pokemon ? pv_evolution_candidates($db, $uid, $pokemon) : [];
$knownMoves = $pokemon ? pv_evolution_move_list($pokemon) : [];
$happiness = $pokemon ? max(0, (int)($pokemon['stat_happiness'] ?? 0)) : 0;
$gender = $pokemon ? trim((string)($pokemon['stat_gender'] ?? $pokemon['gender'] ?? '')) : '';

pv_page_start('Evolution Lab', 'your_pokemon.php', true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('your_pokemon.php'); ?>
<main class="pv-main-column">
<section class="pv-page pv-evolution-page">
    <div class="pv-page-head">
        <div>
            <span class="pv-eyebrow">POKÉMON COLLECTION // EVOLUTION LAB</span>
            <h1>Evolution Lab</h1>
            <p class="pv-subtle">See your Pokémon's available evolutions and evolve when every requirement is met.</p>
        </div>
        <a class="pv-button pv-button-secondary" href="<?= pv_h(pv_url('your_pokemon.php')) ?>">Back to Collection</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="pv-alert pv-alert-error"><?= pv_h($error) ?></div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="pv-evolution-result" role="status">
            <div class="pv-evolution-result-sprites">
                <img src="<?= pv_h(pv_pokemon_sprite((string)$flash['old_name'])) ?>" alt="<?= pv_h((string)$flash['old_name']) ?>">
                <span aria-hidden="true">→</span>
                <img src="<?= pv_h(pv_pokemon_sprite((string)$flash['new_name'])) ?>" alt="<?= pv_h((string)$flash['new_name']) ?>">
            </div>
            <div>
                <small>EVOLUTION COMPLETE</small>
                <h2><?= pv_h((string)$flash['old_name']) ?> evolved into <?= pv_h((string)$flash['new_name']) ?></h2>
                <p>The evolved Pokémon remains in the same team slot and keeps its level, experience, IVs, nature, original trainer and Poké Ball.</p>
                <?php if (!empty($flash['consumed_item'])): ?>
                    <span class="pv-evolution-chip">Consumed <?= pv_h(pv_evolution_item_label((string)$flash['consumed_item'])) ?></span>
                <?php endif; ?>
                <span class="pv-evolution-chip"><?= !empty($flash['replace_moves']) ? 'Moves updated to evolved defaults' : 'Existing moves preserved' ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$pokemon): ?>
        <div class="pv-empty-state pv-evolution-empty">
            <span class="pv-empty-icon">DNA</span>
            <h2>Pokémon unavailable</h2>
            <p>Select one of your Pokémon from the collection to open its evolution analysis.</p>
            <a class="pv-button" href="<?= pv_h(pv_url('your_pokemon.php')) ?>">View Your Pokémon</a>
        </div>
    <?php else: ?>
        <div class="pv-evolution-focus">
            <div class="pv-evolution-source">
                <span class="pv-panel-kicker">CURRENT SPECIES</span>
                <div class="pv-evolution-source-art"><img src="<?= pv_h(pv_pokemon_sprite((string)$pokemon['name'])) ?>" alt="<?= pv_h((string)$pokemon['name']) ?>"></div>
                <div>
                    <h2><?= pv_h((string)$pokemon['name']) ?></h2>
                    <p>Level <?= number_format(max(1, (int)$pokemon['lvl'])) ?><?= $gender !== '' ? ' · ' . pv_h($gender) : '' ?></p>
                </div>
            </div>
            <div class="pv-evolution-vitals" aria-label="Evolution status">
                <div><small>HAPPINESS</small><strong><?= number_format($happiness) ?></strong><span>/ 255</span></div>
                <div><small>KNOWN MOVES</small><strong><?= count($knownMoves) ?></strong><span>/ 4</span></div>
                <div><small>PATHS FOUND</small><strong><?= count($options) ?></strong><span>evolution paths</span></div>
            </div>
            <div class="pv-evolution-moves">
                <small>CURRENT MOVESET</small>
                <div>
                    <?php if ($knownMoves): foreach ($knownMoves as $move): ?>
                        <span><?= pv_h($move) ?></span>
                    <?php endforeach; else: ?>
                        <span>No moves recorded</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="pv-section-heading pv-evolution-heading">
            <div><span>EVOLUTION ROUTES</span><h2><?= $options ? 'Available analysis' : 'No further evolution detected' ?></h2></div>
            <small>Requirements are checked again when you confirm</small>
        </div>

        <?php if (!$options): ?>
            <div class="pv-empty-state pv-evolution-empty">
                <span class="pv-empty-icon">MAX</span>
                <h2>No evolution path found</h2>
                <p><?= pv_h((string)$pokemon['name']) ?> has no further evolution path available.</p>
                <a class="pv-button pv-button-secondary" href="<?= pv_h(pv_url('pokedex.php')) ?>">Open Pokédex</a>
            </div>
        <?php else: ?>
            <div class="pv-evolution-grid">
                <?php foreach ($options as $option):
                    $target = $option['target'];
                    $ready = !empty($option['available']);
                    $method = strtolower((string)($option['rule']['method'] ?? ''));
                    $itemName = trim((string)($option['rule']['item'] ?? ''));
                ?>
                <article class="pv-evolution-card <?= $ready ? 'is-ready' : 'is-locked' ?>">
                    <div class="pv-evolution-card-status"><i></i><?= $ready ? 'READY TO EVOLVE' : 'REQUIREMENTS PENDING' ?></div>
                    <div class="pv-evolution-card-art">
                        <img src="<?= pv_h(pv_pokemon_sprite((string)$target['name'])) ?>" alt="<?= pv_h((string)$target['name']) ?>">
                        <span class="pv-evolution-method-icon" aria-hidden="true"><?= $method === 'item' ? 'ITEM' : ($method === 'happiness' ? '♥' : ($method === 'move' ? 'MOVE' : 'LV')) ?></span>
                    </div>
                    <div class="pv-evolution-card-copy">
                        <small>EVOLVES INTO</small>
                        <h3><?= pv_h((string)$target['name']) ?></h3>
                        <p class="pv-evolution-requirement"><?= pv_h((string)$option['requirement']) ?></p>
                        <p class="pv-evolution-status-copy"><?= pv_h((string)$option['status']) ?></p>
                        <div class="pv-evolution-types">
                            <?php if (trim((string)($target['type1'] ?? '')) !== ''): ?><span><?= pv_h((string)$target['type1']) ?></span><?php endif; ?>
                            <?php if (trim((string)($target['type2'] ?? '')) !== ''): ?><span><?= pv_h((string)$target['type2']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($ready): ?>
                    <form class="pv-evolution-form" method="post" action="<?= pv_h(pv_url('evolve.php?pid=' . $pokemonId)) ?>">
                        <?= pv_csrf_field() ?>
                        <input type="hidden" name="pokemon_id" value="<?= $pokemonId ?>">
                        <input type="hidden" name="rule_key" value="<?= pv_h((string)$option['key']) ?>">
                        <label class="pv-check-row">
                            <input type="checkbox" name="replace_moves" value="1" checked>
                            <span><strong>Adopt evolved moveset</strong><small>Prioritize <?= pv_h((string)$target['name']) ?>'s evolved move set. If its default moves repeat, unique moves your Pokémon already knows fill the remaining slots.</small></span>
                        </label>
                        <?php if ($method === 'item' && $itemName !== ''): ?>
                            <div class="pv-evolution-cost"><img src="<?= pv_h(pv_item_sprite($itemName)) ?>" alt=""><span>1 × <?= pv_h(pv_evolution_item_label($itemName)) ?></span></div>
                        <?php endif; ?>
                        <button class="pv-button" type="submit">Evolve into <?= pv_h((string)$target['name']) ?></button>
                    </form>
                    <?php else: ?>
                        <div class="pv-evolution-locked-note"><span>LOCKED</span><p>Meet the requirement above to unlock this evolution.</p></div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
</main>
</div>
<?php pv_page_end(); ?>
