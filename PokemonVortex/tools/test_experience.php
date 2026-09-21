<?php
declare(strict_types=1);
/** Deterministic progression regressions. No database, credentials or server required. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/experience.php';

$checks = 0;
function exp_check($actual, $expected, string $label): void
{
    global $checks;
    $checks++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

/** Independent integer formula oracle; the production implementation reads ROM tables. */
function exp_reference(string $group, int $level): int
{
    if ($level <= 1) return $level;
    $cube = $level ** 3;
    switch ($group) {
        case 'medium_fast': return $cube;
        case 'erratic':
            if ($level <= 50) return intdiv($cube * (100 - $level), 50);
            if ($level <= 68) return intdiv($cube * (150 - $level), 100);
            if ($level <= 98) return intdiv($cube * intdiv(1911 - 10 * $level, 3), 500);
            return intdiv($cube * (160 - $level), 100);
        case 'fluctuating':
            if ($level <= 15) return intdiv($cube * (intdiv($level + 1, 3) + 24), 50);
            if ($level <= 36) return intdiv($cube * ($level + 14), 50);
            return intdiv($cube * (intdiv($level, 2) + 32), 50);
        case 'medium_slow': return intdiv(6 * $cube, 5) - 15 * $level ** 2 + 100 * $level - 140;
        case 'fast': return intdiv(4 * $cube, 5);
        case 'slow': return intdiv(5 * $cube, 4);
    }
    throw new RuntimeException('Unknown reference group');
}

try {
    $data = pv_exp_data();
    exp_check($data['source']['rom_sha256'], '729041b940afe031302d630fdbe57c0c145f3f7b6d9b8eca5e98678d0ca4d059', 'ROM provenance');
    exp_check(count($data['species']), 386, 'ROM species count');
    exp_check(array_keys($data['species']), range(1, 386), 'National dex coverage');
    $representatives = ['medium_fast'=>'Pikachu', 'erratic'=>'Nincada', 'fluctuating'=>'Shroomish',
        'medium_slow'=>'Bulbasaur', 'fast'=>'Chansey', 'slow'=>'Dratini'];
    $totals = ['medium_fast'=>1000000, 'erratic'=>600000, 'fluctuating'=>1640000,
        'medium_slow'=>1059860, 'fast'=>800000, 'slow'=>1250000];
    foreach ($representatives as $group => $name) {
        exp_check(pv_exp_species($name)['growth_group'], $group, $name . ' growth group');
        exp_check(count($data['experience_tables'][$group]), 101, $group . ' table length');
        exp_check($data['experience_tables'][$group][100], $totals[$group], $group . ' level 100 anchor');
        for ($level = 0; $level <= 100; $level++) {
            $expected = exp_reference($group, $level);
            exp_check($data['experience_tables'][$group][$level], $expected, $group . ' raw ROM level ' . $level);
            if ($level === 0) continue;
            exp_check(pv_exp_at_level($name, $level), $expected, $name . ' level threshold ' . $level);
            exp_check(pv_exp_level_for($name, $expected), $level, $name . ' exact threshold ' . $level);
            exp_check(pv_exp_level_for($name, $expected - 1), max(1, $level - 1), $name . ' threshold minus one ' . $level);
            if ($level < 100) {
                exp_check(pv_exp_level_for($name, exp_reference($group, $level + 1) - 1), $level,
                    $name . ' top of interval ' . $level);
            }
        }
        exp_check(pv_exp_level_for($name, -500), 1, $name . ' negative EXP clamp');
        exp_check(pv_exp_level_for($name, PHP_INT_MAX), 100, $name . ' max EXP clamp');
        exp_check(pv_exp_at_level($name, -1), 1, $name . ' low level clamp');
        exp_check(pv_exp_at_level($name, 101), $totals[$group], $name . ' high level clamp');
        foreach (['Shiny', 'Dark', 'Metallic', 'Mystic', 'Shadow', 'Ancient'] as $prefix) {
            exp_check(pv_exp_at_level($prefix . ' ' . $name, 50), exp_reference($group, 50), $prefix . ' ' . $name);
        }
    }
    // Every real ROM species must resolve by its name and use its own growth table.
    foreach ($data['species'] as $dex => $entry) {
        exp_check(pv_exp_species($entry['name'])['national_dex'], $dex, 'Species lookup dex ' . $dex);
        exp_check(pv_exp_species($entry['name'])['base_exp'], $entry['base_exp'], 'Species yield dex ' . $dex);
        for ($level = 1; $level <= 100; $level++) {
            exp_check(pv_exp_at_level($entry['name'], $level), exp_reference($entry['growth_group'], $level),
                'Species curve dex ' . $dex . ' level ' . $level);
        }
    }
    exp_check($data['species'][290]['internal_id'], 301, 'Hoenn reordered Nincada mapping');
    exp_check($data['species'][358]['internal_id'], 411, 'Hoenn reordered Chimecho mapping');
    exp_check($data['species'][386]['internal_id'], 410, 'Hoenn reordered Deoxys mapping');
    exp_check(pv_exp_at_level('Mr. Mime', 50), 125000, 'Punctuation species lookup');
    exp_check(pv_exp_at_level('Farfetch’d', 50), 125000, 'Curly apostrophe lookup');
    exp_check(pv_exp_species('Nidoran♀')['national_dex'], 29, 'Female Nidoran lookup');
    exp_check(pv_exp_species('Nidoran♂')['national_dex'], 32, 'Male Nidoran lookup');
    exp_check(pv_exp_at_level('Shiny Deoxys (Attack)', 100), 1250000, 'Cosmetic form lookup');

    // A normal early battle should leave progress, rather than grant a flat level.
    $row = ['name'=>'Bulbasaur', 'lvl'=>5, 'exp'=>135, 'exp_curve_version'=>1];
    $gain = pv_exp_battle_gain('Pidgey', 3);
    exp_check($gain, 23, 'Level three Pidgey ROM yield');
    $award = pv_exp_award($row, $gain);
    exp_check($award['lvl'], 5, 'One early wild victory does not force a level');
    exp_check($award['exp'], 158, 'Early battle cumulative EXP');
    exp_check($award['level_ups'], 0, 'Early battle level-up count');
    $award = pv_exp_award($row, 44);
    exp_check($award['lvl'], 6, 'Exact next threshold award');
    exp_check($award['exp'], 179, 'Exact next threshold cumulative EXP');
    exp_check($award['gained'], 44, 'Exact next threshold credited gain');
    $award = pv_exp_award($row, 1000);
    exp_check($award['lvl'], 12, 'Legitimate large award can cross multiple levels');
    exp_check($award['exp'], 1135, 'Large award retains residual EXP');
    exp_check($award['level_ups'], 7, 'Large award reports all crossed levels');
    exp_check(pv_exp_award($row, -20)['exp'], 135, 'Negative award ignored');
    exp_check(pv_exp_award($row, 0)['exp'], 135, 'Zero award stable');

    foreach ($representatives as $group => $name) {
        $cap = $totals[$group];
        $almost = ['name'=>$name, 'lvl'=>99, 'exp'=>$cap - 1, 'exp_curve_version'=>1];
        $award = pv_exp_award($almost, 5000);
        exp_check($award['lvl'], 100, $name . ' cap level');
        exp_check($award['exp'], $cap, $name . ' cap EXP');
        exp_check($award['gained'], 1, $name . ' credits only remaining EXP');
        $maxed = ['name'=>$name, 'lvl'=>100, 'exp'=>$cap, 'exp_curve_version'=>1];
        exp_check(pv_exp_award($maxed, 5000)['gained'], 0, $name . ' maxed recipient gains none');
        exp_check(pv_exp_award($almost, PHP_INT_MAX)['exp'], $cap, $name . ' integer overflow safe cap');
        $legacy = ['name'=>$name, 'lvl'=>50, 'exp'=>25250];
        $converted = pv_exp_award($legacy, 0);
        $expected = exp_reference($group, 50) + intdiv(exp_reference($group, 51) - exp_reference($group, 50), 2);
        exp_check($converted['lvl'], 50, $name . ' legacy conversion preserves level');
        exp_check($converted['exp'], $expected, $name . ' legacy half-level progress');
        exp_check($converted['exp_curve_version'], 1, $name . ' marks migration version');
        exp_check(pv_exp_award(['name'=>$name] + $converted, 0)['exp'], $expected, $name . ' migration idempotent');
        exp_check(pv_exp_award(['name'=>$name, 'lvl'=>50, 'exp'=>0], 0)['lvl'], 50, $name . ' malformed legacy level preserved');
        exp_check(pv_exp_award(['name'=>$name, 'lvl'=>50, 'exp'=>PHP_INT_MAX], 0)['lvl'], 50,
            $name . ' corrupt legacy EXP cannot invent levels');
    }
    $battleCases = [
        ['Pidgey', 5, 1, false, false, 39],
        ['Pidgey', 5, 2, false, false, 19],
        ['Pidgey', 5, 2, true, false, 28],
        ['Pidgey', 5, 2, false, true, 28],
        ['Pidgey', 5, 2, true, true, 42],
        ['Pidgey', 5, 1, true, true, 87],
        ['Magikarp', 1, 6, false, false, 1],
        ['Chansey', 50, 1, false, false, 1821],
        ['Chansey', 50, 1, true, false, 2731],
        ['Chansey', 50, 1, true, true, 4096],
        ['Shiny Chansey', 50, 1, false, false, 1821],
        ['Pidgey', 5, 0, false, false, 0],
    ];
    foreach ($battleCases as $case) {
        [$name, $level, $participants, $trainer, $traded, $expected] = $case;
        exp_check(pv_exp_battle_gain($name, $level, $participants, $trainer, $traded), $expected,
            'Battle rounding ' . json_encode($case));
    }
    // Partial progress survives evolution, including an explicit cross-group replacement.
    $evolved = pv_exp_change_species(['name'=>'Bulbasaur', 'lvl'=>5, 'exp'=>157, 'exp_curve_version'=>1], 'Ivysaur');
    exp_check($evolved['lvl'], 5, 'Same-group evolution level');
    exp_check($evolved['exp'], 157, 'Same-group evolution preserves cumulative EXP');
    $changed = pv_exp_change_species(['name'=>'Bulbasaur', 'lvl'=>5, 'exp'=>157, 'exp_curve_version'=>1], 'Pikachu');
    exp_check($changed['lvl'], 5, 'Cross-group replacement preserves level');
    exp_check($changed['exp'], 170, 'Cross-group replacement preserves fractional progress');
    foreach (['Castform (Fire)'=>'Castform','Castform (Water)'=>'Castform','Castform (Ice)'=>'Castform',
        'Deoxys (Attack)'=>'Deoxys','Deoxys (Defense)'=>'Deoxys','Deoxys (Speed)'=>'Deoxys'] as $form=>$baseName) {
        exp_check(pv_exp_species($form)['base_exp'],pv_exp_species($baseName)['base_exp'],'Generation III form ROM yield '.$form);
        exp_check(pv_exp_species($form)['source'],'firered-rom','Generation III form ROM authority '.$form);
    }
    $sql=(string)file_get_contents(dirname(__DIR__,2).'/database/pokemon_vortex_full.sql');
    preg_match_all("/INSERT INTO `pguide`[^\n]+/",$sql,$guideLines);
    preg_match_all("/\\([0-9]+,'([^']+)'/",implode('',$guideLines[0]),$catalog);
    exp_check(count($catalog[1]),4728,'Full shipped catalogue coverage');
    foreach ($catalog[1] as $name) {
        $profile=pv_exp_species($name);
        exp_check(isset($data['experience_tables'][$profile['growth_group']]),true,'Known group for '.$name);
        exp_check((int)$profile['base_exp']>0,true,'Positive yield for '.$name);
    }
    $unknownThrows = false;
    try { pv_exp_at_level('Clearly Invalid Species', 5); }
    catch (InvalidArgumentException $exception) { $unknownThrows = true; }
    exp_check($unknownThrows, true, 'Unknown species cannot silently receive wrong curve');
    echo 'PASS ' . $checks . ' experience checks (all 386 ROM species, six growth curves, battle rounding, migration and caps).' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL after ' . $checks . ' checks: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
