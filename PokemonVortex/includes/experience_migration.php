<?php
declare(strict_types=1);
require_once __DIR__ . '/experience.php';
require_once __DIR__ . '/gameplay.php';

/** Restartable, audited conversion: stored levels remain authoritative for legacy rows. */
function pv_exp_migrate_legacy(mysqli $db, int $batchSize = 500): int
{
    $batchSize = max(1, min(2000, $batchSize));
    $lockName = 'pv-exp-migrate:' . substr(hash('sha256', (string)$db->query('SELECT DATABASE()')->fetch_row()[0]), 0, 32);
    $stmt = $db->prepare('SELECT GET_LOCK(?,10)'); $stmt->bind_param('s', $lockName); $stmt->execute();
    $acquired = (int)$stmt->get_result()->fetch_row()[0]; $stmt->close();
    if ($acquired !== 1) throw new RuntimeException('Experience conversion is already running.');
    $converted = 0;
    try {
        while (true) {
            $db->begin_transaction();
            try {
                $rows = $db->query('SELECT id,name,lvl,exp,exp_curve_version,owner FROM pokemon WHERE exp_curve_version=0 ORDER BY id LIMIT ' . $batchSize . ' FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
                if (!$rows) { $db->commit(); break; }
                $audit = $db->prepare('INSERT IGNORE INTO pokemon_exp_migration (pokemon_id,species,old_level,old_exp,new_level,new_exp,migrated_at) VALUES (?,?,?,?,?,?,?)');
                $update = $db->prepare('UPDATE pokemon SET lvl=?,exp=?,exp_curve_version=1 WHERE id=? AND exp_curve_version=0');
                if (!$audit || !$update) throw new RuntimeException('Could not prepare experience conversion.');
                foreach ($rows as $row) {
                    $state = pv_exp_state($row); $id=(int)$row['id']; $oldLevel=(int)$row['lvl']; $oldExp=(int)$row['exp']; $now=time();
                    $audit->bind_param('isiiiii', $id, $row['name'], $oldLevel, $oldExp, $state['lvl'], $state['exp'], $now);
                    if (!$audit->execute()) throw new RuntimeException('Could not preserve the legacy EXP audit.');
                    $update->bind_param('iii', $state['lvl'], $state['exp'], $id);
                    if (!$update->execute() || $update->affected_rows !== 1) throw new RuntimeException('Could not convert Pokémon #' . $id . '.');
                }
                $audit->close(); $update->close();
                if (!$db->commit()) throw new RuntimeException('Could not commit experience conversion.');
                $converted += count($rows);
            } catch (Throwable $error) { $db->rollback(); throw $error; }
        }
        // Recompute aggregates even on a resumed run whose row batches already committed.
        // The raw data audit remains available until an operator deliberately archives it.
        $owners = $db->query('SELECT DISTINCT CAST(p.owner AS UNSIGNED) user_id FROM pokemon p JOIN pokemon_exp_migration a ON a.pokemon_id=p.id WHERE CAST(p.owner AS UNSIGNED)>0');
        while ($row=$owners->fetch_assoc()) pv_recalculate_trainer_progress($db,(int)$row['user_id'],false);
        $owners->free();
        if (function_exists('pv_schema_table_exists') && pv_schema_table_exists($db,'upfortrade')) {
            // upfortrade.pid is the specimen ID; keep its display snapshot in sync.
            if (!$db->query('UPDATE upfortrade u JOIN pokemon p ON p.id=u.pid SET u.lvl=p.lvl,u.exp=p.exp')) throw new RuntimeException('Could not refresh trade EXP snapshots.');
        }
        return $converted;
    } finally {
        $stmt=$db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->bind_param('s',$lockName); $stmt->execute(); $stmt->close();
    }
}
