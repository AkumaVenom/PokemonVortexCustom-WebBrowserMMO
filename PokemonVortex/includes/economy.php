<?php
declare(strict_types=1);
require_once __DIR__ . '/gameplay.php';

function pv_shop_catalog(): array
{
    return [
        'medicine' => [
            'label' => 'Battle Medicine',
            'description' => 'Restore HP and remove status effects during battle.',
            'items' => [
                'potion' => ['label'=>'Potion','column'=>'potion','price'=>500,'description'=>'Restores 20 HP to one active Pokémon.'],
                'super_potion' => ['label'=>'Super Potion','column'=>'super_potion','price'=>1000,'description'=>'Restores 100 HP to one active Pokémon.'],
                'hyper_potion' => ['label'=>'Hyper Potion','column'=>'hyper_potion','price'=>2000,'description'=>'Restores 250 HP to one active Pokémon.'],
                'full_heal' => ['label'=>'Full Heal','column'=>'full_heal','price'=>1000,'description'=>'Removes all major status conditions.'],
                'awakening' => ['label'=>'Awakening','column'=>'awakening','price'=>500,'description'=>'Wakes a sleeping Pokémon.'],
                'paralyze_heal' => ['label'=>'Paralyze Heal','column'=>'paralyze_heal','price'=>500,'description'=>'Removes paralysis.'],
                'antidote' => ['label'=>'Antidote','column'=>'antidote','price'=>500,'description'=>'Removes poison.'],
                'burn_heal' => ['label'=>'Burn Heal','column'=>'burn_heal','price'=>500,'description'=>'Heals a burn.'],
                'ice_heal' => ['label'=>'Ice Heal','column'=>'ice_heal','price'=>500,'description'=>'Thaws a frozen Pokémon.'],
            ],
        ],
        'balls' => [
            'label' => 'Poké Balls',
            'description' => 'Capture wild Pokémon encountered throughout the world maps.',
            'items' => [
                'poke_ball' => ['label'=>'Poke Ball','column'=>'poke_ball','price'=>250,'description'=>'Standard capture device.'],
                'great_ball' => ['label'=>'Great Ball','column'=>'great_ball','price'=>800,'description'=>'Improved capture performance.'],
                'ultra_ball' => ['label'=>'Ultra Ball','column'=>'ultra_ball','price'=>1500,'description'=>'High-performance capture device.'],
                'master_ball' => ['label'=>'Master Ball','column'=>'master_ball','price'=>100000,'description'=>'Extremely rare ball that guarantees capture of a wild Pokémon.'],
            ],
        ],
        'evolution' => [
            'label' => 'Evolution Items',
            'description' => 'Used by compatible Pokémon in the Evolution Lab.',
            'items' => [
                'fire_stone'=>['label'=>'Fire Stone','column'=>'Fire_Stone','price'=>3000,'description'=>'Radiates intense heat.'],
                'water_stone'=>['label'=>'Water Stone','column'=>'Water_Stone','price'=>3000,'description'=>'A clear blue evolutionary stone.'],
                'thunder_stone'=>['label'=>'Thunder Stone','column'=>'Thunder_Stone','price'=>3000,'description'=>'Crackles with electrical energy.'],
                'leaf_stone'=>['label'=>'Leaf Stone','column'=>'Leaf_Stone','price'=>3000,'description'=>'Contains a distinctive leaf pattern.'],
                'moon_stone'=>['label'=>'Moon Stone','column'=>'Moon_Stone','price'=>4000,'description'=>'A mysterious stone tied to lunar evolution.'],
                'sun_stone'=>['label'=>'Sun Stone','column'=>'Sun_Stone','price'=>5000,'description'=>'Glows with warm solar energy.'],
                'dawn_stone'=>['label'=>'Dawn Stone','column'=>'Dawn_Stone','price'=>5000,'description'=>'Sparkles like the morning sky.'],
                'dusk_stone'=>['label'=>'Dusk Stone','column'=>'Dusk_Stone','price'=>5000,'description'=>'Dark as the deep night.'],
                'shiny_stone'=>['label'=>'Shiny Stone','column'=>'Shiny_Stone','price'=>5000,'description'=>'A brilliantly polished evolutionary stone.'],
                'oval_stone'=>['label'=>'Oval Stone','column'=>'Oval_Stone','price'=>5000,'description'=>'A rounded stone used by a small number of species.'],
                'kings_rock'=>['label'=>"King's Rock",'column'=>'Kings_Rock','price'=>5000,'description'=>'A crown-shaped evolution item.'],
                'metal_coat'=>['label'=>'Metal Coat','column'=>'Metal_Coat','price'=>5000,'description'=>'A metallic coating used in evolution.'],
                'dragon_scale'=>['label'=>'Dragon Scale','column'=>'Dragon_Scale','price'=>5000,'description'=>'A durable scale treasured by Dragon Pokémon.'],
                'deepseascale'=>['label'=>'Deep Sea Scale','column'=>'Deepseascale','price'=>5000,'description'=>'A luminous scale from the deep sea.'],
                'deepseatooth'=>['label'=>'Deep Sea Tooth','column'=>'Deepseatooth','price'=>5000,'description'=>'A sharp tooth from the deep sea.'],
                'dubious_disc'=>['label'=>'Dubious Disc','column'=>'Dubious_Disc','price'=>5000,'description'=>'An unusual disc containing strange data.'],
                'electirizer'=>['label'=>'Electirizer','column'=>'Electirizer','price'=>5000,'description'=>'A box packed with electrical energy.'],
                'magmarizer'=>['label'=>'Magmarizer','column'=>'Magmarizer','price'=>5000,'description'=>'A box packed with tremendous heat.'],
                'prism_scale'=>['label'=>'Prism Scale','column'=>'Prism_Scale','price'=>5000,'description'=>'A mysterious scale that shines in many colors.'],
                'protector'=>['label'=>'Protector','column'=>'Protector','price'=>5000,'description'=>'A heavy protective evolution item.'],
                'razor_claw'=>['label'=>'Razor Claw','column'=>'Razor_Claw','price'=>5000,'description'=>'A sharply hooked evolution item.'],
                'razor_fang'=>['label'=>'Razor Fang','column'=>'Razor_Fang','price'=>5000,'description'=>'A sharply pointed evolution item.'],
                'reaper_cloth'=>['label'=>'Reaper Cloth','column'=>'Reaper_Cloth','price'=>5000,'description'=>'A cloth imbued with eerie energy.'],
                'up_grade'=>['label'=>'Up-Grade','column'=>'Up_Grade','price'=>5000,'description'=>'A data-filled evolutionary device.'],
                'sachet'=>['label'=>'Sachet','column'=>'Sachet','price'=>5000,'description'=>'A strongly fragrant evolution item.'],
                'whipped_dream'=>['label'=>'Whipped Dream','column'=>'Whipped_Dream','price'=>5000,'description'=>'A soft, sweet evolution item.'],
                'ice_rock'=>['label'=>'Ice Rock','column'=>'Ice_Rock','price'=>5000,'description'=>'A chilled stone used by compatible Pokémon.'],
            ],
        ],
        'fossils' => [
            'label' => 'Fossils',
            'description' => 'Take fossils to the Fossil Lab to restore ancient Pokémon.',
            'items' => [
                'helix_fossil'=>['label'=>'Helix Fossil','column'=>'Helix_Fossil','price'=>25000,'description'=>'Can be restored into Omanyte.'],
                'dome_fossil'=>['label'=>'Dome Fossil','column'=>'Dome_Fossil','price'=>25000,'description'=>'Can be restored into Kabuto.'],
                'old_amber'=>['label'=>'Old Amber','column'=>'Old_Amber','price'=>30000,'description'=>'Contains genetic material for Aerodactyl.'],
                'root_fossil'=>['label'=>'Root Fossil','column'=>'Root_Fossil','price'=>25000,'description'=>'Can be restored into Lileep.'],
                'claw_fossil'=>['label'=>'Claw Fossil','column'=>'Claw_Fossil','price'=>25000,'description'=>'Can be restored into Anorith.'],
                'skull_fossil'=>['label'=>'Skull Fossil','column'=>'Skull_Fossil','price'=>25000,'description'=>'Can be restored into Cranidos.'],
                'armor_fossil'=>['label'=>'Armor Fossil','column'=>'Armor_Fossil','price'=>25000,'description'=>'Can be restored into Shieldon.'],
                'cover_fossil'=>['label'=>'Cover Fossil','column'=>'Cover_Fossil','price'=>25000,'description'=>'Can be restored into Tirtouga.'],
                'plume_fossil'=>['label'=>'Plume Fossil','column'=>'Plume_Fossil','price'=>25000,'description'=>'Can be restored into Archen.'],
                'jaw_fossil'=>['label'=>'Jaw Fossil','column'=>'Jaw_Fossil','price'=>25000,'description'=>'Can be restored into Tyrunt.'],
                'sail_fossil'=>['label'=>'Sail Fossil','column'=>'Sail_Fossil','price'=>25000,'description'=>'Can be restored into Amaura.'],
            ],
        ],
    ];
}

function pv_shop_flat_catalog(): array
{
    $flat = [];
    foreach (pv_shop_catalog() as $categoryKey => $category) {
        foreach ($category['items'] as $key => $item) {
            $item['key'] = $key;
            $item['category'] = $categoryKey;
            $flat[$key] = $item;
        }
    }
    return $flat;
}

function pv_shop_ensure_inventory(mysqli $db, int $uid): void
{
    $stmt = $db->prepare('INSERT INTO items (uid) VALUES (?) ON DUPLICATE KEY UPDATE uid=VALUES(uid)');
    if (!$stmt) throw new RuntimeException('Inventory services are temporarily unavailable.');
    $stmt->bind_param('i', $uid);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Inventory services are temporarily unavailable.'); }
    $stmt->close();
}

function pv_shop_inventory(mysqli $db, int $uid): array
{
    pv_shop_ensure_inventory($db, $uid);
    $catalog = pv_shop_flat_catalog();
    $columns = array_values(array_unique(array_map(static fn($i)=>(string)$i['column'], $catalog)));
    $select = implode(',', array_map(static fn($c)=>'`'.str_replace('`','``',$c).'`', $columns));
    $stmt = $db->prepare("SELECT {$select} FROM items WHERE uid=? LIMIT 1");
    if (!$stmt) return [];
    $stmt->bind_param('i', $uid); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
    return $row;
}

function pv_shop_purchase(mysqli $db, int $uid, string $itemKey, int $quantity): array
{
    $catalog = pv_shop_flat_catalog();
    if (!isset($catalog[$itemKey])) throw new RuntimeException('Choose an item from the PokéMart.');
    $quantity = max(1, min(99, $quantity));
    $item = $catalog[$itemKey];
    $column = (string)$item['column'];
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $column)) throw new RuntimeException('That item cannot be purchased.');
    $unit = max(1, (int)$item['price']);
    $total = $unit * $quantity;
    $createdAt = time();

    $db->begin_transaction();
    try {
        pv_shop_ensure_inventory($db, $uid);
        $stmt = $db->prepare('SELECT money FROM members WHERE id=? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Trainer funds could not be verified.');
        $stmt->bind_param('i', $uid); $stmt->execute(); $member = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$member) throw new RuntimeException('Trainer account could not be loaded.');
        $money = max(0, (int)$member['money']);
        if ($money < $total) throw new RuntimeException('You do not have enough money for that purchase.');

        $stmt = $db->prepare('SELECT uid FROM items WHERE uid=? FOR UPDATE');
        if (!$stmt) throw new RuntimeException('Inventory could not be locked.');
        $stmt->bind_param('i', $uid); $stmt->execute(); $stmt->get_result()->fetch_assoc(); $stmt->close();

        $stmt = $db->prepare('UPDATE members SET money=money-? WHERE id=? AND money>=?');
        if (!$stmt) throw new RuntimeException('Trainer funds could not be updated.');
        $stmt->bind_param('iii', $total, $uid, $total); $stmt->execute(); $okFunds=$stmt->affected_rows===1; $stmt->close();
        if (!$okFunds) throw new RuntimeException('Your balance changed before the purchase could complete. Please try again.');

        $sql = 'UPDATE items SET `'.$column.'`=`'.$column.'`+? WHERE uid=?';
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Inventory could not be updated.');
        $stmt->bind_param('ii', $quantity, $uid); $stmt->execute(); $okItem=$stmt->affected_rows===1; $stmt->close();
        if (!$okItem) throw new RuntimeException('The item could not be delivered to your inventory.');

        $label = (string)$item['label'];
        $stmt = $db->prepare('INSERT INTO shop_transactions (user_id,item_key,item_label,quantity,unit_price,total_price,created_at) VALUES (?,?,?,?,?,?,?)');
        if (!$stmt) throw new RuntimeException('The purchase receipt could not be recorded.');
        $stmt->bind_param('issiiii', $uid, $itemKey, $label, $quantity, $unit, $total, $createdAt);
        if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('The purchase receipt could not be recorded.'); }
        $stmt->close();

        $db->commit();
        return ['item'=>$item,'quantity'=>$quantity,'total'=>$total,'balance'=>$money-$total];
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof RuntimeException) throw $e;
        pv_log('PokéMart purchase failure: '.$e->getMessage());
        throw new RuntimeException('The purchase could not be completed. Your money and items are unchanged.');
    }
}
