<?php
declare(strict_types=1);

require_once __DIR__ . '/collection.php';
require_once __DIR__ . '/evolution.php';

/**
 * Read-only Pokédex data service.
 *
 * The reconstructed catalogue deliberately reads from the same authoritative
 * pguide / abilities / attacks / pokemon data used by gameplay. No external
 * API or browser-side data source is required for a detail page to resolve.
 */

function pv_pokedex_guide(mysqli $db, int $speciesId): ?array
{
    if ($speciesId < 1) return null;
    $stmt = $db->prepare('SELECT id,name,type1,type2,starter,amount,a1,a2,a3,a4 FROM pguide WHERE id=? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $speciesId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function pv_pokedex_variant_label(string $name): string
{
    [$prefix] = pv_evolution_variant_parts($name);
    $variant = trim($prefix);
    return $variant !== '' ? $variant : 'Normal';
}

function pv_pokedex_abilities(mysqli $db, string $name): array
{
    [, $baseName] = pv_evolution_variant_parts($name);
    $candidates = array_values(array_unique(array_filter([trim($name), trim($baseName)])));
    foreach ($candidates as $candidate) {
        $stmt = $db->prepare('SELECT ability1,ability2,ability3 FROM abilities WHERE name=? LIMIT 1');
        if (!$stmt) return [];
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if (!$row) continue;
        $abilities = [];
        foreach (['ability1','ability2','ability3'] as $field) {
            $value = trim((string)($row[$field] ?? ''));
            if ($value !== '' && !in_array($value, $abilities, true)) $abilities[] = $value;
        }
        return $abilities;
    }
    return [];
}

function pv_pokedex_move_profile(mysqli $db, array $guide): array
{
    $profile = [];
    $stmt = $db->prepare('SELECT attack,type,power,accuracy,category FROM attacks WHERE attack=? LIMIT 1');
    foreach (['a1','a2','a3','a4'] as $index => $field) {
        $name = trim((string)($guide[$field] ?? ''));
        if ($name === '') continue;
        $move = [
            'slot' => $index + 1,
            'attack' => $name,
            'type' => '',
            'power' => 0,
            'accuracy' => 0,
            'category' => '',
            'has_metadata' => false,
        ];
        if ($stmt) {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $meta = $stmt->get_result()->fetch_assoc() ?: null;
            if ($meta) {
                $move['type'] = trim((string)($meta['type'] ?? ''));
                $move['power'] = max(0, (int)($meta['power'] ?? 0));
                $move['accuracy'] = max(0, (int)($meta['accuracy'] ?? 0));
                $move['category'] = trim((string)($meta['category'] ?? ''));
                $move['has_metadata'] = true;
            }
        }
        $profile[] = $move;
    }
    if ($stmt) $stmt->close();
    return $profile;
}

function pv_pokedex_variant_family(mysqli $db, int $uid, string $name): array
{
    [, $baseName] = pv_evolution_variant_parts($name);
    $baseName = trim($baseName);
    if ($baseName === '') return [];

    $wanted = [
        $baseName,
        'Shiny ' . $baseName,
        'Dark ' . $baseName,
        'Metallic ' . $baseName,
        'Mystic ' . $baseName,
        'Shadow ' . $baseName,
    ];
    $sql = "SELECT g.id,g.name,g.type1,g.type2,g.amount,COUNT(p.id) AS owned_count
            FROM pguide g
            LEFT JOIN pokemon p ON p.pid=g.id AND CAST(p.owner AS UNSIGNED)=?
            WHERE g.name IN (?,?,?,?,?,?)
            GROUP BY g.id,g.name,g.type1,g.type2,g.amount";
    $stmt = $db->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('issssss', $uid, $wanted[0], $wanted[1], $wanted[2], $wanted[3], $wanted[4], $wanted[5]);
    $stmt->execute();
    $result = $stmt->get_result();
    $byName = [];
    while ($row = $result->fetch_assoc()) $byName[(string)$row['name']] = $row;
    $stmt->close();

    $out = [];
    foreach ($wanted as $wantedName) {
        if (isset($byName[$wantedName])) $out[] = $byName[$wantedName];
    }
    return $out;
}

function pv_pokedex_owned_specimens(mysqli $db, int $uid, int $speciesId, int $limit = 8): array
{
    if ($uid < 1 || $speciesId < 1) return ['count' => 0, 'rows' => []];
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM pokemon WHERE CAST(owner AS UNSIGNED)=? AND pid=?');
    $count = 0;
    if ($stmt) {
        $stmt->bind_param('ii', $uid, $speciesId);
        $stmt->execute();
        $count = max(0, (int)(($stmt->get_result()->fetch_assoc()['c'] ?? 0)));
        $stmt->close();
    }

    $limit = max(1, min(12, $limit));
    $sql = "SELECT p.id,p.pid,p.name,p.lvl,p.exp,p.a1,p.a2,p.a3,p.a4,p.ball,p.gender,
                   COALESCE(ps.nature,'') AS nature,COALESCE(ps.ability,'') AS ability,
                   COALESCE(ps.happiness,0) AS happiness,COALESCE(ps.display_form,'') AS display_form,
                   COALESCE(ps.hp_iv,0) AS hp_iv,COALESCE(ps.attack_iv,0) AS attack_iv,
                   COALESCE(ps.defense_iv,0) AS defense_iv,COALESCE(ps.spatk_iv,0) AS spatk_iv,
                   COALESCE(ps.spdef_iv,0) AS spdef_iv,COALESCE(ps.speed_iv,0) AS speed_iv
            FROM pokemon p LEFT JOIN pokemon_stats ps ON ps.id=p.id
            WHERE CAST(p.owner AS UNSIGNED)=? AND p.pid=?
            ORDER BY p.lvl DESC,p.exp DESC,p.id DESC LIMIT {$limit}";
    $stmt = $db->prepare($sql);
    $rows = [];
    if ($stmt) {
        $stmt->bind_param('ii', $uid, $speciesId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $stmt->close();
    }
    return ['count' => $count, 'rows' => $rows];
}

function pv_pokedex_adjacent(mysqli $db, int $speciesId): array
{
    $out = ['previous' => null, 'next' => null];
    $stmt = $db->prepare('SELECT id,name FROM pguide WHERE id<? ORDER BY id DESC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $speciesId);
        $stmt->execute();
        $out['previous'] = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
    $stmt = $db->prepare('SELECT id,name FROM pguide WHERE id>? ORDER BY id ASC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $speciesId);
        $stmt->execute();
        $out['next'] = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
    return $out;
}

function pv_pokedex_resolve_evolution_entry(mysqli $db, string $currentName, string $baseSpecies): ?array
{
    [$prefix] = pv_evolution_variant_parts($currentName);
    $candidates = [];
    if ($prefix !== '') $candidates[] = $prefix . trim($baseSpecies);
    $candidates[] = trim($baseSpecies);
    foreach (array_values(array_unique(array_filter($candidates))) as $candidate) {
        $stmt = $db->prepare('SELECT id,name,type1,type2 FROM pguide WHERE name=? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if ($row) return $row;
    }
    return null;
}

function pv_pokedex_evolution_routes(mysqli $db, string $name): array
{
    $forward = [];
    foreach (pv_evolution_rules_for($name) as $rule) {
        $entry = pv_pokedex_resolve_evolution_entry($db, $name, (string)($rule['target'] ?? ''));
        if (!$entry) continue;
        $forward[] = [
            'entry' => $entry,
            'requirement' => pv_evolution_requirement_label($rule),
            'rule' => $rule,
        ];
    }

    $previous = [];
    foreach (pv_evolution_parent_rules_for($name) as $rule) {
        $entry = pv_pokedex_resolve_evolution_entry($db, $name, (string)($rule['source'] ?? ''));
        if (!$entry) continue;
        $previous[] = [
            'entry' => $entry,
            'requirement' => pv_evolution_requirement_label($rule),
            'rule' => $rule,
        ];
    }

    return ['previous' => $previous, 'forward' => $forward];
}
