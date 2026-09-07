<?php
declare(strict_types=1);

require_once __DIR__ . '/rival_runtime.php';
require_once __DIR__ . '/ui.php';

function pv_rival_trainer_sprite(array $row): int
{
    $botSprite = max(0, (int)($row['trainer_sprite'] ?? 0));
    $humanSprite = max(0, (int)($row['trainer'] ?? 0));
    return max(1, min(29, $botSprite > 0 ? $botSprite : ($humanSprite > 0 ? $humanSprite : 1)));
}

function pv_rival_profile_url(array $row): string
{
    $id = max(0, (int)($row['user_id'] ?? $row['id'] ?? 0));
    return !empty($row['is_bot']) || (int)($row['bot_index'] ?? 0) > 0
        ? pv_url('bot_trainer.php?id=' . $id)
        : pv_url('members.php?profile=' . $id);
}

function pv_rival_time_ago(int $timestamp, ?int $now = null): string
{
    if ($timestamp <= 0) return 'No ranked activity yet';
    $now ??= time();
    $seconds = max(0, $now - $timestamp);
    if ($seconds < 45) return 'Just now';
    if ($seconds < 3600) return (int)floor($seconds / 60) . 'm ago';
    if ($seconds < 86400) return (int)floor($seconds / 3600) . 'h ago';
    if ($seconds < 604800) return (int)floor($seconds / 86400) . 'd ago';
    return date('j M Y', $timestamp);
}

function pv_rival_format_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    if ($seconds <= 0) return 'Ready';
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    if ($m >= 60) {
        $h = intdiv($m, 60);
        $m %= 60;
        return sprintf('%dh %02dm', $h, $m);
    }
    return sprintf('%02d:%02d', $m, $s);
}

function pv_rival_ball_url(int $rating): string
{
    $tier = pv_rival_tier($rating);
    return pv_static_file((string)$tier['ball'], 'images/items/Poke Ball.png');
}

function pv_rival_lead_url(array $row, string $fallback = 'Pikachu'): string
{
    $name = trim((string)($row['lead_name'] ?? ''));
    if ($name === '') $name = $fallback;
    return pv_static_file('images/pokemon/' . $name . '.gif', 'images/pokemon/' . $fallback . '.gif');
}

function pv_rival_is_bot(array $row): bool
{
    return !empty($row['is_bot']) || (int)($row['bot_index'] ?? 0) > 0;
}

function pv_rival_target_card(array $row, int $now, string $variant = 'rival'): void
{
    $id = max(0, (int)($row['user_id'] ?? $row['id'] ?? 0));
    $name = trim((string)($row['username'] ?? 'Trainer')) ?: 'Trainer';
    $rating = max(100, (int)($row['rating'] ?? PV_RIVAL_START_RATING));
    $tier = pv_rival_tier($rating);
    $trainer = pv_rival_trainer_sprite($row);
    $shield = max(0, (int)($row['shield_until'] ?? 0) - $now);
    $isBot = pv_rival_is_bot($row);
    $wins = max(0, (int)($row['ranked_wins'] ?? 0));
    $losses = max(0, (int)($row['ranked_losses'] ?? 0));
    $last = max(0, (int)($row['last_ranked_at'] ?? 0));
    $profile = pv_rival_profile_url($row);
    $buttonLabel = $variant === 'elite' ? 'Challenge Elite Rival' : ($variant === 'active' ? 'Battle Competitor' : 'Start Ranked Battle');

    echo '<article class="pv-rival-target pv-rival-target-' . pv_h($variant) . '">';
    echo '<div class="pv-rival-target-glow"></div>';
    echo '<div class="pv-rival-target-head"><span class="pv-rival-tier pv-tier-' . pv_h((string)$tier['class']) . '"><img src="' . pv_h(pv_rival_ball_url($rating)) . '" alt="">' . pv_h((string)$tier['short']) . '</span>';
    echo $isBot ? '<span class="pv-ai-chip"><i></i> AUTONOMOUS AI</span>' : '<span class="pv-human-chip">PLAYER</span>';
    echo '</div>';
    echo '<div class="pv-rival-target-visual"><a class="pv-rival-trainer-avatar" href="' . pv_h($profile) . '"><img src="' . pv_h(pv_static_file('images/sprites/' . $trainer . 'whole.gif','images/sprites/1whole.gif')) . '" alt="' . pv_h($name) . '"></a>';
    echo '<span class="pv-rival-lead"><img src="' . pv_h(pv_rival_lead_url($row)) . '" alt=""></span></div>';
    echo '<div class="pv-rival-target-copy"><a class="pv-rival-name" href="' . pv_h($profile) . '">' . pv_h($name) . '</a>';
    echo '<div class="pv-rival-rating"><strong>' . number_format($rating) . '</strong><span>RIVAL RATING</span></div>';
    echo '<div class="pv-rival-mini-stats"><span><b>' . number_format($wins) . '</b> W</span><span><b>' . number_format($losses) . '</b> L</span><span><b>' . number_format(max(0,(int)($row['current_streak'] ?? 0))) . '</b> STREAK</span></div>';
    echo '<small>' . pv_h(pv_rival_time_ago($last, $now)) . '</small></div>';
    if ($shield > 0) {
        echo '<div class="pv-rival-protected"><img src="' . pv_h(pv_static_file('images/items/Great Ball.png','images/items/Poke Ball.png')) . '" alt=""><span>Battle Protection</span><strong data-pv-countdown="' . (int)($row['shield_until'] ?? 0) . '">' . pv_h(pv_rival_format_duration($shield)) . '</strong></div>';
    } else {
        echo '<form class="pv-rival-battle-form" method="post" action="' . pv_h(pv_url('rival_action.php')) . '">';
        echo pv_csrf_field();
        echo '<input type="hidden" name="rival_action_token" value="' . pv_h(pv_action_token('rival_action.php')) . '">';
        echo '<input type="hidden" name="action" value="challenge"><input type="hidden" name="target_id" value="' . $id . '">';
        echo '<button class="pv-button pv-rival-battle-button" type="submit"><img src="' . pv_h(pv_static_file('images/items/Poke Ball.png','images/Pokeball.PNG')) . '" alt="">' . pv_h($buttonLabel) . '</button></form>';
    }
    echo '</article>';
}


/** Shared competitive profile surface for player and autonomous trainers. */
function pv_rival_profile_panel(mysqli $db, int $trainerId, int $viewerId): void
{
    if (!pv_rival_ready($db)) return;
    $saved = pv_rival_retry_pending_result($db, $viewerId);
    $state = pv_rival_state($db, $trainerId);
    if (!$state) return;
    $rating = (int)$state['rating'];
    $tier = pv_rival_tier($rating);
    $shield = pv_rival_shield_remaining($state);
    $hasTeam = pv_rival_has_team($db, $trainerId);
    $rank = $hasTeam ? pv_rival_rank_position($db, $trainerId, $rating, (int)$state['ranked_wins'], (int)$state['ranked_losses']) : 0;
    ?>
    <section class="pv-card pv-panel pv-ranked-profile" aria-label="Ranked trainer record">
        <div class="pv-page-head"><div><span class="pv-eyebrow">RANKED LADDER</span><h2><?=pv_h((string)$tier['name'])?> · <?=number_format($rating)?> RP</h2></div><a class="pv-button pv-button-secondary" href="<?=pv_h(pv_url('rankings.php'))?>">Trainer Rankings</a></div>
        <div class="pv-stat-grid">
            <div><span>Global rank</span><strong><?=$rank > 0 ? '#'.number_format($rank) : 'Unplaced'?></strong></div>
            <div><span>Ranked wins</span><strong><?=number_format((int)$state['ranked_wins'])?></strong></div>
            <div><span>Ranked losses</span><strong><?=number_format((int)$state['ranked_losses'])?></strong></div>
            <div><span>Peak rating</span><strong><?=number_format((int)$state['peak_rating'])?> RP</strong></div>
        </div>
        <p>Ranked wins earn rating points and losses cost rating points. The result updates both trainers on the shared player and AI ladder.</p>
        <?php if (!$saved): ?>
            <p class="pv-flash warning">Your completed ranked result is waiting to save. Open Trainer Rankings to retry before starting another ranked battle.</p>
        <?php elseif ($trainerId === $viewerId): ?>
            <a class="pv-button" href="<?=pv_h(pv_url('rival_hub.php'))?>">Find a Ranked Rival</a>
        <?php elseif (!$hasTeam): ?>
            <p class="pv-subtle">This trainer needs an active Pokémon team before being challenged.</p>
        <?php elseif ($shield > 0): ?>
            <p class="pv-subtle">Battle protection: <?=pv_h(pv_rival_format_duration($shield))?> remaining. <a href="<?=pv_h(pv_url('rival_hub.php'))?>">Find another rival or use a retaliation.</a></p>
        <?php else: ?>
            <form class="pv-rival-battle-form" method="post" action="<?=pv_h(pv_url('rival_action.php'))?>">
                <?=pv_csrf_field()?>
                <input type="hidden" name="rival_action_token" value="<?=pv_h(pv_action_token('rival_action.php'))?>">
                <input type="hidden" name="action" value="challenge"><input type="hidden" name="target_id" value="<?=$trainerId?>">
                <button class="pv-button pv-rival-battle-button" type="submit">Start Ranked Battle</button>
            </form>
        <?php endif; ?>
    </section>
    <?php
}
