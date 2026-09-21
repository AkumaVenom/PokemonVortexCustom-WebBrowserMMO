<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/experience.php';
if (in_array('--help',$argv,true)) {
    echo "Usage: php tools/migrate_experience.php [--check|--apply]\n",
        "Check catalogue coverage without changing data, or apply the additive v34 upgrade.\n",
        "Before --apply: back up the database, stop AI workers and put the site in maintenance.\n",
        "Never import the fresh-install SQL over an existing database.\n";
    exit(0);
}
if (count($argv)>2 || (isset($argv[1])&&!in_array($argv[1],['--check','--apply'],true))) { fwrite(STDERR,"Unknown argument. Use --help.\n"); exit(2); }
try {
    $db=pv_db(); mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
    $names=$db->query('SELECT DISTINCT name FROM pokemon UNION SELECT DISTINCT name FROM pguide');
    $missing=[]; $checked=0;
    while ($row=$names->fetch_assoc()) { $checked++; try { pv_exp_species((string)$row['name']); } catch (InvalidArgumentException $e) { $missing[]=$row['name']; } }
    if ($missing) throw new RuntimeException('Missing species metadata: '.implode(', ',$missing).'. Add explicit metadata before upgrading; no Pokémon was converted.');
    echo 'Validated experience metadata for '.$checked." catalogue/owned names.\n";
    if (($argv[1]??'--check')==='--apply') {
        require_once dirname(__DIR__) . '/includes/schema.php';
        foreach (pv_apply_schema_migrations($db) as $change) echo $change,PHP_EOL;
        echo "Upgrade complete. Existing levels preserved; legacy EXP audit retained. Start the continuous AI service.\n";
    } else echo "Read-only check complete. Run again with --apply to upgrade.\n";
} catch (Throwable $error) { fwrite(STDERR,'Upgrade failed: '.$error->getMessage().PHP_EOL); exit(1); }
