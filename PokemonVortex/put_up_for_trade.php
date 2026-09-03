<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/gameplay.php';
pv_require_login();

try {
    $db = pv_db();
} catch (Throwable $e) {
    pv_log('Trade listing DB unavailable: ' . $e->getMessage());
    http_response_code(503);
    pv_page_start('Trade Center', 'trade.php', true);
    echo '<main class="pv-page"><div class="pv-alert pv-alert-error">The Trade Center is temporarily unavailable. Please try again shortly.</div></main>';
    pv_page_end();
    exit;
}

$uid = (int)$_SESSION['myid'];
$username = (string)($_SESSION['myuser'] ?? 'Trainer');
$success = [];
$errors = [];

$stmt = $db->prepare('SELECT s1,s2,s3,s4,s5,s6 FROM members WHERE id=? LIMIT 1');
$stmt->bind_param('i', $uid);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$teamIds = array_values(array_filter(array_map('intval', [
    $member['s1'] ?? 0, $member['s2'] ?? 0, $member['s3'] ?? 0,
    $member['s4'] ?? 0, $member['s5'] ?? 0, $member['s6'] ?? 0,
]), static fn(int $id): bool => $id > 0));
$teamLookup = array_fill_keys($teamIds, true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    pv_require_csrf();
    $selected = $_POST['pokemon'] ?? [];
    if (!is_array($selected)) $selected = [$selected];
    $selected = array_values(array_unique(array_filter(array_map('intval', $selected), static fn(int $id): bool => $id > 0)));
    if (count($selected) > 100) $selected = array_slice($selected, 0, 100);

    if (!$selected) {
        $errors[] = 'Choose at least one Pokémon to list for trade.';
    } else {
        $find = $db->prepare('SELECT id,name,a1,a2,a3,a4,lvl,exp,rowner,owner FROM pokemon WHERE id=? AND CAST(owner AS UNSIGNED)=? LIMIT 1');
        $already = $db->prepare('SELECT id FROM upfortrade WHERE pid=? LIMIT 1');
        $insert = $db->prepare('INSERT INTO upfortrade (name,pid,owner,a1,a2,a3,a4,lvl,exp,rowner,date) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $detach = $db->prepare("UPDATE pokemon SET owner='0' WHERE id=? AND CAST(owner AS UNSIGNED)=?");

        foreach ($selected as $pokemonId) {
            if (isset($teamLookup[$pokemonId])) {
                $errors[] = "Pokémon #{$pokemonId} is on your active team. Remove it from the team before trading it.";
                continue;
            }

            $find->bind_param('ii', $pokemonId, $uid);
            $find->execute();
            $pokemon = $find->get_result()->fetch_assoc();
            if (!$pokemon) {
                $errors[] = "Pokémon #{$pokemonId} is no longer available in your collection.";
                continue;
            }

            $already->bind_param('i', $pokemonId);
            $already->execute();
            if ($already->get_result()->fetch_assoc()) {
                $errors[] = pv_h((string)$pokemon['name']) . ' is already listed for trade.';
                continue;
            }

            $name = (string)$pokemon['name'];
            $a1 = (string)($pokemon['a1'] ?? '');
            $a2 = (string)($pokemon['a2'] ?? '');
            $a3 = (string)($pokemon['a3'] ?? '');
            $a4 = (string)($pokemon['a4'] ?? '');
            $lvl = max(1, (int)($pokemon['lvl'] ?? 1));
            $exp = max(0, (int)($pokemon['exp'] ?? 0));
            $rowner = trim((string)($pokemon['rowner'] ?? '')) ?: $username;
            $now = time();

            $db->begin_transaction();
            $insert->bind_param('siissssiisi', $name, $pokemonId, $uid, $a1, $a2, $a3, $a4, $lvl, $exp, $rowner, $now);
            if (!$insert->execute()) {
                $db->rollback();
                pv_log('Trade listing insert failed for Pokémon ' . $pokemonId . ': ' . $insert->error);
                $errors[] = 'The listing for ' . pv_h($name) . ' could not be created.';
                continue;
            }

            $detach->bind_param('ii', $pokemonId, $uid);
            if (!$detach->execute() || $detach->affected_rows !== 1) {
                $db->rollback();
                @ $db->query('DELETE FROM upfortrade WHERE pid=' . (int)$pokemonId . ' AND owner=' . (int)$uid);
                $errors[] = 'The listing for ' . pv_h($name) . ' could not be finalized.';
                continue;
            }
            $db->commit();
            pv_server_event('TRADE','Pokémon listed for trade',['pokemon_id'=>$pokemonId,'pokemon'=>$name]);
            $success[] = $name;
        }

        foreach ([$find, $already, $insert, $detach] as $prepared) $prepared->close();
        if ($success) pv_recalculate_trainer_progress($db, $uid, true);
    }
}

$variant = strtolower(trim((string)($_GET['variant'] ?? 'all')));
$allowedVariants = ['all','normal','shiny','dark','metallic','mystic','shadow'];
if (!in_array($variant, $allowedVariants, true)) $variant = 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 60;
$offset = ($page - 1) * $perPage;

$where = ['CAST(p.owner AS UNSIGNED)=?'];
$params = [$uid];
$types = 'i';
if ($teamIds) $where[] = 'p.id NOT IN (' . implode(',', array_map('intval', $teamIds)) . ')';
$where[] = 'NOT EXISTS (SELECT 1 FROM upfortrade u WHERE u.pid=p.id)';
if ($variant === 'normal') {
    $where[] = "p.name NOT LIKE 'Shiny %' AND p.name NOT LIKE 'Dark %' AND p.name NOT LIKE 'Metallic %' AND p.name NOT LIKE 'Mystic %' AND p.name NOT LIKE 'Shadow %'";
} elseif ($variant !== 'all') {
    $where[] = 'p.name LIKE ?';
    $params[] = ucfirst($variant) . ' %';
    $types .= 's';
}
$whereSql = implode(' AND ', $where);

$countSql = 'SELECT COUNT(*) AS c FROM pokemon p WHERE ' . $whereSql;
$stmt = $db->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$lastPage = max(1, (int)ceil($total / $perPage));
if ($page > $lastPage) { $page = $lastPage; $offset = ($page - 1) * $perPage; }

$sql = 'SELECT p.id,p.name,p.lvl,p.exp FROM pokemon p WHERE ' . $whereSql . ' ORDER BY p.name,p.id LIMIT ? OFFSET ?';
$stmt = $db->prepare($sql);
$listTypes = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$result = $stmt->get_result();
$pokemon = [];
while ($row = $result->fetch_assoc()) $pokemon[] = $row;
$stmt->close();

pv_page_start('List Pokémon for Trade', 'trade.php', true);
?>
<div class="pv-game-layout">
<?php pv_game_side_menu('trade.php'); ?>
<main class="pv-main-column">
<section class="pv-page">
    <div class="pv-page-head">
        <div>
            <span class="pv-eyebrow">TRADE CENTER // LISTINGS</span>
            <h1>List Pokémon for Trade</h1>
            <p class="pv-subtle">Choose Pokémon from your reserve collection. Active-team Pokémon stay protected until you remove them from your team.</p>
        </div>
        <a class="pv-button pv-button-secondary" href="<?= pv_h(pv_url('trade.php')) ?>">Back to Trade Center</a>
    </div>

    <?php if ($success): ?>
        <div class="pv-alert pv-alert-success"><strong>Listing created.</strong> <?= pv_h(implode(', ', $success)) ?> <?= count($success) === 1 ? 'is' : 'are' ?> now available in the Trade Center.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="pv-alert pv-alert-error"><?= $error ?></div><?php endforeach; ?>

    <div class="pv-toolbar pv-trade-filter" role="navigation" aria-label="Pokémon form filters">
        <?php foreach ($allowedVariants as $filter): ?>
            <a class="<?= $variant === $filter ? 'active' : '' ?>" href="<?= pv_h(pv_url('put_up_for_trade.php?variant=' . rawurlencode($filter))) ?>"><?= pv_h(ucfirst($filter)) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="pv-section-heading">
        <div><span>AVAILABLE RESERVES</span><h2><?= number_format($total) ?> Pokémon eligible</h2></div>
        <small>Page <?= (int)$page ?> / <?= (int)$lastPage ?></small>
    </div>

    <?php if (!$pokemon): ?>
        <div class="pv-empty-state"><strong>No eligible Pokémon in this view.</strong><span>Pokémon already on your team or already listed for trade are intentionally hidden.</span></div>
    <?php else: ?>
    <form method="post" action="<?= pv_h(pv_url('put_up_for_trade.php?variant=' . rawurlencode($variant) . '&page=' . $page)) ?>">
        <?= pv_csrf_field() ?>
        <div class="pv-pokemon-selection-grid">
        <?php foreach ($pokemon as $row):
            $pid = (int)$row['id']; $name = (string)$row['name']; ?>
            <label class="pv-pokemon-select-card">
                <input type="checkbox" name="pokemon[]" value="<?= $pid ?>">
                <span class="pv-pokemon-select-check" aria-hidden="true">✓</span>
                <span class="pv-pokemon-select-image"><img src="<?= pv_h(pv_pokemon_sprite($name)) ?>" alt="<?= pv_h($name) ?>" loading="lazy" decoding="async"></span>
                <strong><?= pv_h($name) ?></strong>
                <small>Lv. <?= max(1,(int)$row['lvl']) ?> · <?= number_format(max(0,(int)$row['exp'])) ?> EXP</small>
                <a href="<?= pv_h(pv_url('pokedex.php?pid=' . $pid)) ?>">View details</a>
            </label>
        <?php endforeach; ?>
        </div>
        <div class="pv-form-actions pv-sticky-actionbar">
            <span><strong>Select one or more Pokémon.</strong> Listings can be managed from the Trade Center.</span>
            <button class="pv-button" type="submit">Create Trade Listings</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($lastPage > 1): ?>
        <nav class="pv-pagination" aria-label="Collection pages">
            <?php if ($page > 1): ?><a href="<?= pv_h(pv_url('put_up_for_trade.php?variant=' . rawurlencode($variant) . '&page=' . ($page-1))) ?>">← Previous</a><?php endif; ?>
            <span>Page <?= (int)$page ?> of <?= (int)$lastPage ?></span>
            <?php if ($page < $lastPage): ?><a href="<?= pv_h(pv_url('put_up_for_trade.php?variant=' . rawurlencode($variant) . '&page=' . ($page+1))) ?>">Next →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
</main>
</div>
<?php pv_page_end(); ?>
