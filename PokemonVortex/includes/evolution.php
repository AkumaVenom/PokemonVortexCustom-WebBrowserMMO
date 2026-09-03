<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/gameplay.php';

/**
 * Production evolution runtime for the reconstructed Pokémon Vortex catalogue.
 *
 * The recovered source expected evolution columns that are not present in the
 * recovered pguide dump.  Rules therefore live in config/evolution_rules.php,
 * while every mutation remains database-authoritative and transactional.
 */

function pv_evolution_rules(): array
{
    static $rules = null;
    if (is_array($rules)) return $rules;

    $path = dirname(__DIR__) . '/config/evolution_rules.php';
    $loaded = is_file($path) ? require $path : [];
    $rules = is_array($loaded) ? array_values(array_filter($loaded, 'is_array')) : [];
    return $rules;
}

function pv_evolution_variant_parts(string $name): array
{
    $name = trim($name);
    if (preg_match('/^(Shiny|Dark|Metallic|Mystic|Shadow)\s+(.+)$/u', $name, $match)) {
        return [$match[1] . ' ', trim($match[2])];
    }
    return ['', $name];
}

/**
 * Return every reconstructed evolution rule whose source species matches the
 * supplied catalogue name. Vortex variants inherit the base species route.
 */
function pv_evolution_rules_for(string $name): array
{
    [, $baseName] = pv_evolution_variant_parts($name);
    $baseName = trim($baseName);
    if ($baseName === '') return [];

    $matches = [];
    foreach (pv_evolution_rules() as $rule) {
        $source = trim((string)($rule['source'] ?? ''));
        if ($source !== '' && strcasecmp($source, $baseName) === 0) {
            $matches[] = $rule;
        }
    }
    return $matches;
}

/**
 * Return reconstructed rules that evolve into the supplied species. This is
 * used by read-only catalogue surfaces to show prior evolution routes.
 */
function pv_evolution_parent_rules_for(string $name): array
{
    [, $baseName] = pv_evolution_variant_parts($name);
    $baseName = trim($baseName);
    if ($baseName === '') return [];

    $matches = [];
    foreach (pv_evolution_rules() as $rule) {
        $target = trim((string)($rule['target'] ?? ''));
        if ($target !== '' && strcasecmp($target, $baseName) === 0) {
            $matches[] = $rule;
        }
    }
    return $matches;
}

function pv_evolution_rule_key(array $rule): string
{
    $stable = [
        'source' => (string)($rule['source'] ?? ''),
        'target' => (string)($rule['target'] ?? ''),
        'method' => (string)($rule['method'] ?? ''),
        'min_level' => (int)($rule['min_level'] ?? 0),
        'min_happiness' => (int)($rule['min_happiness'] ?? 0),
        'item' => (string)($rule['item'] ?? ''),
        'move' => (string)($rule['move'] ?? ''),
        'required_gender' => (string)($rule['required_gender'] ?? ''),
    ];
    return substr(hash('sha256', json_encode($stable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), 0, 24);
}

function pv_evolution_allowed_item_columns(): array
{
    static $columns = null;
    if (is_array($columns)) return $columns;

    $columns = [];
    foreach (pv_evolution_rules() as $rule) {
        $item = trim((string)($rule['item'] ?? ''));
        if ($item !== '' && preg_match('/^[A-Za-z0-9_]+$/D', $item)) {
            $columns[$item] = true;
        }
    }
    return array_keys($columns);
}

function pv_evolution_item_label(string $column): string
{
    $special = [
        'Up_Grade' => 'Up-Grade',
        'Kings_Rock' => "King's Rock",
        'Deepseascale' => 'Deep Sea Scale',
        'Deepseatooth' => 'Deep Sea Tooth',
        'Electirizer' => 'Electirizer',
        'Magmarizer' => 'Magmarizer',
        'Reaper_Cloth' => 'Reaper Cloth',
        'Whipped_Dream' => 'Whipped Dream',
    ];
    if (isset($special[$column])) return $special[$column];
    return ucwords(strtolower(str_replace('_', ' ', trim($column))));
}

function pv_evolution_load_owned_pokemon(mysqli $db, int $uid, int $pokemonId, bool $forUpdate = false): ?array
{
    if ($uid < 1 || $pokemonId < 1) return null;
    $suffix = $forUpdate ? ' FOR UPDATE' : '';
    $sql = 'SELECT p.*, COALESCE(ps.happiness,0) AS stat_happiness, COALESCE(NULLIF(ps.gender,\'\'),p.gender,\'\') AS stat_gender, COALESCE(ps.ability,\'\') AS stat_ability '
         . 'FROM pokemon p LEFT JOIN pokemon_stats ps ON ps.id=p.id '
         . 'WHERE p.id=? AND CAST(p.owner AS UNSIGNED)=? LIMIT 1' . $suffix;
    $stmt = $db->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param('ii', $pokemonId, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function pv_evolution_resolve_target(mysqli $db, string $sourceName, string $baseTarget, bool $forUpdate = false): ?array
{
    [$prefix] = pv_evolution_variant_parts($sourceName);
    $names = [];
    if ($prefix !== '') $names[] = $prefix . trim($baseTarget);
    $names[] = trim($baseTarget);

    $suffix = $forUpdate ? ' FOR UPDATE' : '';
    foreach (array_values(array_unique(array_filter($names))) as $candidate) {
        $stmt = $db->prepare('SELECT id,name,type1,type2,a1,a2,a3,a4,amount FROM pguide WHERE name=? LIMIT 1' . $suffix);
        if (!$stmt) return null;
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if ($row) return $row;
    }
    return null;
}

function pv_evolution_inventory_count(mysqli $db, int $uid, string $column): int
{
    if (!in_array($column, pv_evolution_allowed_item_columns(), true)) return 0;
    $sql = 'SELECT `' . $column . '` AS item_count FROM items WHERE uid=? LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return max(0, (int)($row['item_count'] ?? 0));
}

function pv_evolution_move_list(array $pokemon): array
{
    $moves = [];
    foreach (['a1','a2','a3','a4'] as $field) {
        $move = trim((string)($pokemon[$field] ?? ''));
        if ($move !== '') $moves[] = $move;
    }
    return $moves;
}

/**
 * Build the four-slot move profile used when the trainer elects to adopt an
 * evolved species' recovered defaults.
 *
 * Recovered pguide data contains a small number of duplicate/blank default
 * slots. Blindly copying those slots can both create duplicate attacks and
 * discard a valid move the specimen already knows. The evolved species'
 * unique defaults therefore always take priority, in source order, and any
 * remaining holes are filled from the specimen's existing unique moves.
 * A target with four unique defaults still replaces all four slots exactly.
 */
function pv_evolution_adopted_moveset(array $target, array $pokemon): array
{
    $moves = [];
    $seen = [];
    $appendUnique = static function (string $move) use (&$moves, &$seen): void {
        $move = trim($move);
        if ($move === '' || count($moves) >= 4) return;
        $key = strtolower($move);
        if (isset($seen[$key])) return;
        $seen[$key] = true;
        $moves[] = $move;
    };

    foreach (['a1','a2','a3','a4'] as $field) {
        $appendUnique((string)($target[$field] ?? ''));
    }
    foreach (['a1','a2','a3','a4'] as $field) {
        $appendUnique((string)($pokemon[$field] ?? ''));
    }

    return array_pad(array_slice($moves, 0, 4), 4, '');
}

function pv_evolution_requirement_label(array $rule): string
{
    $method = strtolower(trim((string)($rule['method'] ?? '')));
    $parts = [];
    if ($method === 'level') {
        $parts[] = 'Reach level ' . max(1, (int)($rule['min_level'] ?? 1));
    } elseif ($method === 'happiness') {
        $parts[] = 'Happiness ' . max(1, (int)($rule['min_happiness'] ?? 220));
    } elseif ($method === 'item') {
        $parts[] = 'Use ' . pv_evolution_item_label((string)($rule['item'] ?? ''));
    } elseif ($method === 'move') {
        $parts[] = 'Know ' . trim((string)($rule['move'] ?? 'required move'));
    } else {
        $parts[] = 'Evolution requirement';
    }

    $gender = trim((string)($rule['required_gender'] ?? ''));
    if ($gender !== '') $parts[] = $gender . ' only';
    return implode(' · ', $parts);
}

function pv_evolution_candidates(mysqli $db, int $uid, array $pokemon): array
{
    [, $baseName] = pv_evolution_variant_parts((string)($pokemon['name'] ?? ''));
    $level = max(1, (int)($pokemon['lvl'] ?? 1));
    $happiness = max(0, (int)($pokemon['stat_happiness'] ?? 0));
    $gender = trim((string)($pokemon['stat_gender'] ?? $pokemon['gender'] ?? ''));
    $moves = pv_evolution_move_list($pokemon);
    $result = [];

    foreach (pv_evolution_rules() as $rule) {
        if (!hash_equals(strtolower(trim((string)($rule['source'] ?? ''))), strtolower($baseName))) continue;

        $target = pv_evolution_resolve_target($db, (string)($pokemon['name'] ?? ''), (string)($rule['target'] ?? ''));
        if (!$target) continue;

        $available = true;
        $status = [];
        $method = strtolower(trim((string)($rule['method'] ?? '')));
        $inventoryCount = null;

        if ($method === 'level') {
            $required = max(1, (int)($rule['min_level'] ?? 1));
            if ($level < $required) {
                $available = false;
                $status[] = 'Requires level ' . $required . ' · Current level ' . $level;
            } else {
                $status[] = 'Level requirement met';
            }
        } elseif ($method === 'happiness') {
            $required = max(1, (int)($rule['min_happiness'] ?? 220));
            if ($happiness < $required) {
                $available = false;
                $status[] = 'Requires ' . $required . ' happiness · Current ' . $happiness;
            } else {
                $status[] = 'Happiness requirement met';
            }
        } elseif ($method === 'item') {
            $item = trim((string)($rule['item'] ?? ''));
            $inventoryCount = pv_evolution_inventory_count($db, $uid, $item);
            if ($inventoryCount < 1) {
                $available = false;
                $status[] = 'Need 1 ' . pv_evolution_item_label($item) . ' · You have 0';
            } else {
                $status[] = pv_evolution_item_label($item) . ' available · ' . $inventoryCount . ' owned';
            }
        } elseif ($method === 'move') {
            $requiredMove = trim((string)($rule['move'] ?? ''));
            $known = false;
            foreach ($moves as $move) {
                if ($requiredMove !== '' && strcasecmp($move, $requiredMove) === 0) {
                    $known = true;
                    break;
                }
            }
            if (!$known) {
                $available = false;
                $status[] = 'Must know ' . ($requiredMove !== '' ? $requiredMove : 'the required move');
            } else {
                $status[] = $requiredMove . ' is known';
            }
        } else {
            $available = false;
            $status[] = 'This evolution method is not currently available.';
        }

        $requiredGender = trim((string)($rule['required_gender'] ?? ''));
        if ($requiredGender !== '') {
            if ($gender === '' || strcasecmp($gender, $requiredGender) !== 0) {
                $available = false;
                $status[] = 'Requires a ' . strtolower($requiredGender) . ' Pokémon · This Pokémon is ' . ($gender !== '' ? strtolower($gender) : 'unspecified');
            } else {
                $status[] = $requiredGender . ' requirement met';
            }
        }

        $result[] = [
            'key' => pv_evolution_rule_key($rule),
            'rule' => $rule,
            'target' => $target,
            'available' => $available,
            'status' => implode(' · ', $status),
            'requirement' => pv_evolution_requirement_label($rule),
            'inventory_count' => $inventoryCount,
        ];
    }

    return $result;
}

function pv_evolution_target_ability(mysqli $db, string $targetName, string $currentAbility): string
{
    [, $baseTarget] = pv_evolution_variant_parts($targetName);
    foreach (array_values(array_unique([$targetName, $baseTarget])) as $lookup) {
        $stmt = $db->prepare('SELECT ability1,ability2,ability3 FROM abilities WHERE name=? LIMIT 1');
        if (!$stmt) return $currentAbility;
        $stmt->bind_param('s', $lookup);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        $pool = array_values(array_unique(array_filter(array_map('trim', [
            (string)($row['ability1'] ?? ''),
            (string)($row['ability2'] ?? ''),
            (string)($row['ability3'] ?? ''),
        ]), static fn(string $value): bool => $value !== '')));
        if (!$pool) continue;
        foreach ($pool as $ability) {
            if ($currentAbility !== '' && strcasecmp($ability, $currentAbility) === 0) return $currentAbility;
        }
        return $pool[0];
    }
    return $currentAbility;
}

function pv_evolve_pokemon(mysqli $db, int $uid, int $pokemonId, string $ruleKey, bool $replaceMoves = true): array
{
    if ($uid < 1 || $pokemonId < 1 || !preg_match('/^[a-f0-9]{24}$/D', $ruleKey)) {
        throw new RuntimeException('That evolution request is no longer valid.');
    }

    $db->begin_transaction();
    try {
        $pokemon = pv_evolution_load_owned_pokemon($db, $uid, $pokemonId, true);
        if (!$pokemon) throw new RuntimeException('That Pokémon is no longer available to evolve.');

        $candidate = null;
        foreach (pv_evolution_candidates($db, $uid, $pokemon) as $option) {
            if (hash_equals((string)$option['key'], $ruleKey)) {
                $candidate = $option;
                break;
            }
        }
        if (!$candidate) throw new RuntimeException('That evolution path is no longer available for this Pokémon.');
        if (empty($candidate['available'])) {
            throw new RuntimeException((string)($candidate['status'] ?: 'This Pokémon does not meet the evolution requirements yet.'));
        }

        $target = pv_evolution_resolve_target(
            $db,
            (string)$pokemon['name'],
            (string)($candidate['rule']['target'] ?? ''),
            true
        );
        if (!$target) throw new RuntimeException('The evolved Pokémon data is unavailable.');

        $item = trim((string)($candidate['rule']['item'] ?? ''));
        if ($item !== '') {
            if (!in_array($item, pv_evolution_allowed_item_columns(), true)) {
                throw new RuntimeException('The required evolution item is unavailable.');
            }
            $sql = 'UPDATE items SET `' . $item . '`=`' . $item . '`-1 WHERE uid=? AND `' . $item . '`>0';
            $stmt = $db->prepare($sql);
            if (!$stmt) throw new RuntimeException('Your inventory could not be updated.');
            $stmt->bind_param('i', $uid);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('You no longer have the required ' . pv_evolution_item_label($item) . '.');
            }
            $stmt->close();
        }

        $oldName = trim((string)$pokemon['name']);
        $newName = trim((string)$target['name']);
        $newPid = (int)$target['id'];
        $newType1 = trim((string)($target['type1'] ?? ''));
        $newType2 = trim((string)($target['type2'] ?? ''));

        if ($replaceMoves) {
            [$newA1, $newA2, $newA3, $newA4] = pv_evolution_adopted_moveset($target, $pokemon);
            $stmt = $db->prepare('UPDATE pokemon SET pid=?,name=?,t1=?,t2=?,a1=?,a2=?,a3=?,a4=? WHERE id=? AND CAST(owner AS UNSIGNED)=?');
            if (!$stmt) throw new RuntimeException('The evolution could not update this Pokémon.');
            $stmt->bind_param('isssssssii', $newPid, $newName, $newType1, $newType2, $newA1, $newA2, $newA3, $newA4, $pokemonId, $uid);
        } else {
            $stmt = $db->prepare('UPDATE pokemon SET pid=?,name=?,t1=?,t2=? WHERE id=? AND CAST(owner AS UNSIGNED)=?');
            if (!$stmt) throw new RuntimeException('The evolution could not update this Pokémon.');
            $stmt->bind_param('isssii', $newPid, $newName, $newType1, $newType2, $pokemonId, $uid);
        }
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('The evolution could not be completed.');
        }
        $stmt->close();

        $stmt = $db->prepare('UPDATE pguide SET amount=GREATEST(COALESCE(amount,0)-1,0) WHERE name=?');
        if ($stmt) {
            $stmt->bind_param('s', $oldName);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $db->prepare('UPDATE pguide SET amount=COALESCE(amount,0)+1 WHERE name=?');
        if ($stmt) {
            $stmt->bind_param('s', $newName);
            $stmt->execute();
            $stmt->close();
        }

        $currentAbility = trim((string)($pokemon['stat_ability'] ?? ''));
        $nextAbility = pv_evolution_target_ability($db, $newName, $currentAbility);
        if ($nextAbility !== $currentAbility) {
            $stmt = $db->prepare('UPDATE pokemon_stats SET ability=? WHERE id=?');
            if ($stmt) {
                $stmt->bind_param('si', $nextAbility, $pokemonId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $db->commit();
        pv_recalculate_trainer_progress($db, $uid, true);

        return [
            'pokemon_id' => $pokemonId,
            'old_name' => $oldName,
            'new_name' => $newName,
            'replace_moves' => $replaceMoves,
            'consumed_item' => $item,
            'ability' => $nextAbility,
        ];
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof RuntimeException) throw $e;
        pv_log('Evolution failed for user ' . $uid . ', pokemon ' . $pokemonId . ': ' . $e->getMessage());
        throw new RuntimeException('The Evolution Lab could not complete that evolution. Please try again.');
    }
}
