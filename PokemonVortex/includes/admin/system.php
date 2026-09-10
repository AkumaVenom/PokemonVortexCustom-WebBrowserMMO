<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !defined('PV_SERVER_CONSOLE_CLI')) { http_response_code(404); exit; }

function pv_admin_system_specs(): array
{
    $rows = [
        'help'=>['help [command]', 'Show available commands or detailed syntax.', 'PLAYER',0,1],
        'commands'=>['commands', 'List commands allowed by the local console configuration.', 'PLAYER',0,0],
        'select'=>['select <username|#ID|none>', 'Choose the trainer used by commands with an optional player argument.', 'PLAYER',1,1],
        'who'=>['who', 'List recently active trainers (last five minutes).', 'PLAYER',0,0],
        'online'=>['online', 'Count recently active human trainers.', 'PLAYER',0,0],
        'serverinfo'=>['serverinfo', 'Show application, database and local console status.', 'PLAYER',0,0],
        'version'=>['version', 'Show the installed application release.', 'PLAYER',0,0],
        'uptime'=>['uptime', 'Show console uptime; PHP web requests do not share a persistent game process.', 'PLAYER',0,0],
        'time'=>['time', 'Show current UTC server time.', 'PLAYER',0,0],
        'ping'=>['ping', 'Measure a local database round trip in milliseconds.', 'PLAYER',0,0],
        'motd'=>['motd', 'Read the configured operator message of the day.', 'PLAYER',0,0],
        'rules'=>['rules', 'Read the configured server rules in this console.', 'PLAYER',0,0],
        'debug'=>['debug [on|off]', 'Toggle detailed local diagnostic output.', 'DEVELOPER',0,1],
        'reload'=>['reload', 'Reload configuration and invalidate supported data caches.', 'DEVELOPER',0,0],
        'reloadconfig'=>['reloadconfig', 'Reload configuration, command policy and database connection.', 'DEVELOPER',0,0],
        'reloadscripts'=>['reloadscripts', 'Invalidate server OPcache and restart this command worker.', 'DEVELOPER',0,0],
        'saveall'=>['saveall', 'Verify committed database state and count persistent trainers and Pokémon.', 'ADMIN',0,0],
        'dbsave'=>['dbsave', 'Verify storage engine durability settings; writes commit immediately.', 'DEVELOPER',0,0],
        'clearcache'=>['clearcache', 'Advance server cache revision and clear console-local cache values.', 'DEVELOPER',0,0],
        'gc'=>['gc', 'Collect PHP cycles in this console process.', 'DEVELOPER',0,0],
        'memory'=>['memory', 'Report actual memory use of this console process.', 'DEVELOPER',0,0],
        'threads'=>['threads', 'Show MySQL thread counters and this single-threaded PHP worker.', 'DEVELOPER',0,0],
        'connections'=>['connections', 'Show current database connections visible to the configured DB account.', 'DEVELOPER',0,0],
        'logs'=>['logs [count]', 'Read recent sanitized application activity (1–100 lines).', 'DEVELOPER',0,1],
        'audit'=>['audit [count]', 'Read recent command audit records without credential output.', 'ADMIN',0,1],
        'test'=>['test', 'Run read-only installation, registry and schema diagnostics.', 'DEVELOPER',0,0],
        'shutdown'=>['shutdown <seconds>', 'Schedule game maintenance; shared XAMPP services keep running. Requires confirmation.', 'ADMIN',1,1],
        'restart'=>['restart <seconds>', 'Schedule a two-second game maintenance window and fresh request caches. Requires confirmation.', 'ADMIN',1,1],
        'resume'=>['resume', 'Cancel scheduled maintenance and reopen the game.', 'ADMIN',0,0],
        'confirm'=>['confirm <token>', 'Confirm the exact pending destructive command within 60 seconds.', 'PLAYER',1,1],
        'cancel'=>['cancel', 'Discard the pending confirmation without changing game state.', 'PLAYER',0,0],
        'exit'=>['exit', 'Close this operator console; the game keeps running.', 'PLAYER',0,0],
    ];
    $specs = [];
    foreach ($rows as $name=>$r) $specs[$name] = ['usage'=>$r[0],'summary'=>$r[1],'rank'=>$r[2],'min'=>$r[3],'max'=>$r[4], 'dev'=>$r[2]==='DEVELOPER', 'confirm'=>in_array($name,['shutdown','restart'],true)];
    $specs['quit'] = ['alias'=>'exit'];
    return $specs;
}

function pv_admin_help(?string $requested, array $context): array
{
    $registry = pv_admin_registry();
    if ($requested !== null) {
        $name = pv_admin_resolve(strtolower(ltrim($requested, '/')), $registry);
        $spec = $registry[$name];
        if (!pv_admin_available($name, $spec, $context)) throw new InvalidArgumentException('That command is disabled or unavailable at the local operator rank.');
        if ($name === 'event') $spec['usage'] = 'event <' . implode('|',pv_admin_visible_event_subcommands($context)) . '> [...]';
        $aliases = [];
        foreach ($registry as $alias=>$candidate) if (isset($candidate['alias']) && pv_admin_resolve($alias,$registry)===$name) $aliases[]=$alias;
        $lines = [
            'Usage: ' . $spec['usage'], $spec['summary'],
            'Local rank: ' . $spec['rank'] . ($spec['dev'] ? '; developer commands must be explicitly enabled.' : '.'),
            'Confirmation: ' . ($spec['confirm'] ? 'required; one-use token expires in 60 seconds.' : 'not required.'),
            'Aliases: ' . ($aliases ? implode(', ',$aliases) : 'none'),
            'Quote multiword names; player selectors accept an exact username or #ID. Commands run only here.',
        ];
        foreach (($spec['details'] ?? []) as $detail) {
            if (str_starts_with((string)$detail, 'GAME MASTER:') && !in_array('create',pv_admin_visible_event_subcommands($context),true)) continue;
            $lines[] = (string)$detail;
        }
        return $lines;
    }
    $lines = ['POKEMON VORTEX — LOCAL ADMIN COMMANDS', 'Local operator: ' . $context['operator'] . ' (' . $context['rank'] . ') | selected trainer: #' . (int)$context['selected'],
        'Type help <command> for details. Slash prefixes are optional; use double quotes around multiword names.',
        'Select a trainer with select <username|#ID> for commands with an optional player target.'];
    $groups = [];
    foreach ($registry as $name=>$spec) {
        if (isset($spec['alias']) || !pv_admin_available($name,$spec,$context)) continue;
        $usage = $name === 'event' ? 'event <' . implode('|',pv_admin_visible_event_subcommands($context)) . '> [...]' : $spec['usage'];
        $groups[$spec['module']][] = str_pad($usage,52) . ' ' . $spec['summary'];
    }
    foreach ($groups as $group=>$entries) { $lines[] = ''; $lines[] = strtoupper($group); foreach ($entries as $entry) $lines[] = '  ' . $entry; }
    $lines[] = '';
    $lines[] = 'Developer commands: ' . (pv_config('admin_console.enable_developer_commands',false) ? 'enabled' : 'disabled; opt in using admin_console.enable_developer_commands in config/app.php, then reopen console.');
    $lines[] = 'Chat, whisper, announcement, broadcast, mute and text emote commands are excluded.';
    return $lines;
}

function pv_admin_visible_event_subcommands(array $context): array
{
    $subs=['list','info','join','leave'];
    if (array_search(pv_admin_rank($context['rank']),pv_admin_ranks(),true)>=array_search('GAME MASTER',pv_admin_ranks(),true)) $subs=array_merge($subs,['create','start','stop','reward']);
    $disabled=array_map(static fn($value)=>strtolower(ltrim(trim((string)$value),'/')),(array)pv_config('admin_console.disabled_commands',[]));
    return array_values(array_filter($subs,static fn($sub)=>!in_array('event '.$sub,$disabled,true)));
}

function pv_admin_recent_lines(string $path, int $count): array
{
    $fh = @fopen($path, 'rb');
    if (!$fh) return ['No activity log exists yet.'];
    $size = (int)fstat($fh)['size']; $chunk = min($size,262144);
    fseek($fh, -$chunk, SEEK_END); $content = (string)stream_get_contents($fh); fclose($fh);
    $lines = preg_split('/\r?\n/', $content) ?: [];
    if ($chunk<$size) array_shift($lines);
    return array_map('pv_server_console_redact_message', array_slice(array_values(array_filter($lines,static fn($line)=>$line!=='')),-$count));
}

function pv_admin_diagnostics(): array
{
    $results=[]; $failed=0;
    $check = static function(string $label, bool $ok) use (&$results,&$failed): void { $results[]=($ok?'PASS ':'FAIL ') . $label; if (!$ok) $failed++; };
    $check('PHP 8.1+ runtime',version_compare(PHP_VERSION,'8.1.0','>='));
    $check('mysqli with mysqlnd results',extension_loaded('mysqli') && function_exists('mysqli_stmt_get_result'));
    foreach (['members','pokemon','pokemon_stats','items','console_settings','console_audit','console_player_state'] as $table) {
        $row=pv_admin_row('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
        $check($table . ' transactional storage',$row && strtoupper((string)$row['ENGINE'])==='INNODB');
    }
    $check('Pokémon catalog populated',(int)(pv_admin_row('SELECT COUNT(*) AS n FROM pguide')['n']??0)>0);
    $check('Move catalog populated',(int)(pv_admin_row('SELECT COUNT(*) AS n FROM attacks')['n']??0)>0);
    $registry=pv_admin_registry();
    foreach ($registry as $name=>$spec) if (isset($spec['alias'])) pv_admin_resolve($name,$registry);
    $check('Aliases resolve without cycles',true);
    $chat=['say','shout','global','whisper','w','reply','r','ignore','unignore','mute','unmute','announce','broadcast','emote'];
    $check('No chat command registered',!array_intersect($chat,array_keys($registry)));
    $check('Quoted command parser',pv_admin_tokenize('/givepokemon "Example Trainer" "Shiny Pikachu"')===['givepokemon','Example Trainer','Shiny Pikachu']);
    $results[]=$failed ? $failed . ' diagnostic check(s) failed.' : 'All read-only installation checks passed.';
    return $results;
}

function pv_admin_system_handle(string $command, array $args, array &$context): array
{
    switch ($command) {
        case 'help': return pv_admin_help($args[0]??null,$context);
        case 'commands': return pv_admin_help(null,$context);
        case 'select':
            if (strtolower($args[0])==='none') { $context['selected']=0; $context['pending']=null; return ['Selected trainer cleared.']; }
            $player=pv_admin_player($args[0],$context); $context['selected']=(int)$player['id']; $context['pending']=null;
            return ['Selected ' . $player['username'] . ' (#' . $player['id'] . ').'];
        case 'who': case 'online':
            $rows=pv_admin_rows('SELECT DISTINCT m.id,m.username,o.time FROM online o JOIN members m ON m.id=o.id LEFT JOIN bot_trainers b ON b.user_id=m.id WHERE o.time>=? AND b.user_id IS NULL ORDER BY m.username LIMIT 1001',[time()-300]);
            if ($command==='online') return ['Recently active human trainers: ' . count($rows) . (count($rows)>1000?' (list capped)':'') . '. Activity window: five minutes.'];
            $lines=['Recently active trainers (five-minute window):'];
            foreach (array_slice($rows,0,1000) as $row) $lines[]='#'.$row['id'].' '.$row['username'].' | seen '.gmdate('H:i:s',(int)$row['time']).' UTC';
            if (!$rows) $lines[]='None.';
            return $lines;
        case 'version': return ['Pokemon Vortex ' . pv_config('asset_version','unknown') . ' — local command console v33.0.0.'];
        case 'uptime': return ['Console uptime: ' . (time()-(int)$context['started_at']) . ' seconds. The website runs as individual PHP requests.'];
        case 'time': return [gmdate('Y-m-d H:i:s') . ' UTC'];
        case 'motd': return [(string)pv_config('admin_console.motd','Welcome to the local Pokemon Vortex server console.')];
        case 'rules': return array_map('strval',(array)pv_config('admin_console.rules',['Respect other trainers. Protect account details. Use administrative changes carefully.']));
        case 'serverinfo':
            $db=pv_admin_db(); $state=pv_admin_maintenance_state($db);
            return ['Application: '.pv_config('app_name').' '.pv_config('asset_version'), 'PHP: '.PHP_VERSION.' ('.PHP_OS_FAMILY.')', 'Database: '.$db->server_info, 'Console PID: '.getmypid().' | uptime '.(time()-(int)$context['started_at']).' seconds', 'Game requests: '.(!empty($state['active'])?'maintenance':'open'), 'Scheduled maintenance: '.json_encode(pv_admin_setting('maintenance_schedule',null)), 'Last web script reload: '.json_encode(pv_admin_setting('script_reload_status',null)), 'Command access: local process only; no HTTP endpoint.'];
        case 'ping':
            $start=microtime(true); pv_admin_row('SELECT 1 AS ok');
            return ['Local database round trip: '.number_format((microtime(true)-$start)*1000,3).' ms. This is not a player network latency measurement.'];
        case 'debug':
            if ($args && !in_array(strtolower($args[0]),['on','off'],true)) throw new InvalidArgumentException('Usage: debug [on|off]');
            $context['debug']=$args?strtolower($args[0])==='on':!$context['debug'];
            return ['Local debug mode '.($context['debug']?'enabled':'disabled').'.'];
        case 'reload': case 'reloadconfig':
            $new=require dirname(__DIR__,2).'/config/app.php';
            if (!is_array($new)) throw new RuntimeException('Configuration must return an array.');
            // Keep the current DB connection until auditing and lock release complete.
            // Reopen worker to apply connection changes safely after this command.
            $context['restart_worker']=true;
            if ($command==='reload') pv_admin_set_setting('cache_revision',time());
            return ['Configuration validated. The console worker will reopen; the next web request reads the current configuration.'];
        case 'reloadscripts':
            pv_admin_set_setting('cache_revision',time());
            pv_admin_set_setting('script_revision',bin2hex(random_bytes(12)));
            $context['restart_worker']=true;
            return ['Script reload requested. On the next web request, this application’s cached PHP scripts are invalidated if OPcache is enabled and its API is available.', 'The console worker reopens to load its PHP files. Check serverinfo for the last web script-reload status.'];
        case 'saveall':
            $row=pv_admin_row('SELECT (SELECT COUNT(*) FROM members) AS trainers,(SELECT COUNT(*) FROM pokemon) AS pokemon');
            return ['Database reachable: '.$row['trainers'].' trainers and '.$row['pokemon'].' Pokémon persisted.', 'Gameplay and command mutations commit immediately; there is no unsaved in-memory world queue.'];
        case 'dbsave':
            $row=pv_admin_row('SELECT @@innodb_flush_log_at_trx_commit AS flush_mode,@@autocommit AS autocommit');
            return ['Database autocommit='.$row['autocommit'].'; InnoDB flush-on-commit mode='.$row['flush_mode'].'.', 'Commands commit their transactions immediately. No global table lock or shared MySQL restart was requested.'];
        case 'clearcache':
            pv_admin_set_setting('cache_revision',microtime(true));
            foreach (array_keys($GLOBALS) as $key) if (str_starts_with($key,'pv_') && str_contains($key,'cache') && !str_starts_with($key,'pv_admin_')) unset($GLOBALS[$key]);
            return ['Server cache revision advanced; session caches refresh on each trainer’s next request.'];
        case 'gc': return ['Collected '.gc_collect_cycles().' PHP cycle(s); console memory '.number_format(memory_get_usage(true)/1048576,2).' MiB.'];
        case 'memory': return ['Console memory: '.number_format(memory_get_usage(true)/1048576,2).' MiB; peak '.number_format(memory_get_peak_usage(true)/1048576,2).' MiB.'];
        case 'threads':
            $rows=pv_admin_rows("SHOW STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Threads_created')");
            $lines=['Console PHP worker: one thread, PID '.getmypid().'.'];
            foreach($rows as $r)$lines[]=$r['Variable_name'].': '.$r['Value'];
            return $lines;
        case 'connections':
            $rows=pv_admin_rows('SHOW PROCESSLIST'); $lines=['Database connections (SQL text omitted):'];
            foreach($rows as $r)$lines[]='#'.$r['Id'].' user='.$r['User'].' host='.$r['Host'].' db='.($r['db']??'none').' command='.$r['Command'].' seconds='.$r['Time'];
            return $lines;
        case 'logs': return pv_admin_recent_lines(pv_server_console_log_path(),isset($args[0])?pv_admin_int($args[0],1,100,'Count'):20);
        case 'audit':
            $count=isset($args[0])?pv_admin_int($args[0],1,100,'Count'):20;
            $rows=pv_admin_rows('SELECT created_at,operator_name,command_name,target_text,outcome FROM console_audit ORDER BY id DESC LIMIT '.$count);
            return array_map(static fn($r)=>gmdate('c',(int)$r['created_at']).' '.$r['operator_name'].' '.$r['command_name'].' '.$r['target_text'].' ['.$r['outcome'].']',$rows);
        case 'test': return pv_admin_diagnostics();
        case 'shutdown': case 'restart':
            $seconds=pv_admin_int($args[0],0,86400,'Delay'); $due=time()+$seconds;
            pv_admin_set_setting('maintenance_schedule',['kind'=>$command,'due'=>$due,'restart_until'=>$command==='restart'?$due+2:0]);
            if ($command==='restart') pv_admin_set_setting('cache_revision',$due);
            return [ucfirst($command).' scheduled for '.gmdate('Y-m-d H:i:s',$due).' UTC.', 'Scope: this Pokemon Vortex application. Apache, MySQL and other hosted games remain running.', 'Type resume to cancel or reopen the game.'];
        case 'resume':
            pv_admin_transaction(static function():void { pv_admin_set_setting('maintenance_schedule',null); pv_admin_set_setting('maintenance',['active'=>false]); });
            return ['Scheduled maintenance cancelled; Pokemon Vortex is open.'];
        case 'cancel': $context['pending']=null; return ['Pending confirmation cancelled.'];
        case 'exit': $context['exit']=true; return ['Console closed. The game continues running.'];
    }
    throw new LogicException('Unhandled system command.');
}
