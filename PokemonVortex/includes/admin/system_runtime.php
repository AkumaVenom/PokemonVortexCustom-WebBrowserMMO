<?php
declare(strict_types=1);
if (!defined('PV_BOOTSTRAPPED')) { http_response_code(404); exit; }

/** Read-only request-time evaluation: schedules still work after the console closes. */
function pv_admin_maintenance_state(mysqli $db): array
{
    $result = $db->query("SELECT setting_key,value_json FROM console_settings WHERE setting_key IN ('maintenance','maintenance_schedule')");
    if (!$result) return ['active'=>false]; // Unmigrated v32 installations remain playable until repair.
    $settings = [];
    while ($row = $result->fetch_assoc()) $settings[$row['setting_key']] = json_decode($row['value_json'], true) ?: [];
    $state = $settings['maintenance'] ?? ['active'=>false];
    if (!empty($state['until']) && time() >= (int)$state['until']) $state['active'] = false;
    $schedule = $settings['maintenance_schedule'] ?? [];
    if (!empty($schedule['due']) && time() >= (int)$schedule['due']) {
        if (($schedule['kind'] ?? '') === 'shutdown') return ['active'=>true, 'reason'=>'The local administrator has closed the game for maintenance.', 'until'=>0];
        if (($schedule['kind'] ?? '') === 'restart' && time() < (int)($schedule['restart_until'] ?? 0)) return ['active'=>true, 'reason'=>'The game is restarting. Please retry shortly.', 'until'=>(int)$schedule['restart_until']];
    }
    return $state;
}

/** Invalidate only this project's cached scripts, never other games on XAMPP. */
function pv_admin_apply_script_revision(mysqli $db): void
{
    if (PHP_SAPI === 'cli') return;
    $result = $db->query("SELECT value_json FROM console_settings WHERE setting_key='script_revision'");
    if (!$result || !($row=$result->fetch_assoc())) return;
    $revision = json_decode((string)$row['value_json'],true);
    if (!is_string($revision) || !preg_match('/^[a-f0-9]{24}$/D',$revision)) return;
    $directory = dirname(__DIR__,2) . '/storage/cache';
    if (!is_dir($directory) && !@mkdir($directory,0770,true)) return;
    $marker = $directory . '/script-reload-' . getmypid() . '.json';
    if (is_file($marker)) {
        $prior=json_decode((string)@file_get_contents($marker),true);
        if (($prior['revision']??'')===$revision) return;
    }
    $status='OPcache disabled; web requests load scripts normally.'; $count=0;
    if (function_exists('opcache_get_status') && filter_var(ini_get('opcache.enable'),FILTER_VALIDATE_BOOLEAN)) {
        $cache=@opcache_get_status(true);
        if (is_array($cache) && isset($cache['scripts'])) {
            $root=realpath(dirname(__DIR__,2));
            foreach (array_keys($cache['scripts']) as $file) {
                $real=realpath($file);
                if ($root && $real && str_starts_with($real,$root.DIRECTORY_SEPARATOR) && @opcache_invalidate($real,true)) $count++;
            }
            $status='Invalidated '.$count.' cached application script(s); changed code loads on subsequent requests.';
        } else $status='OPcache API is restricted. Restart Apache locally to reload cached PHP scripts.';
    }
    @file_put_contents($marker,json_encode(['revision'=>$revision,'status'=>$status,'at'=>time()]),LOCK_EX);
    $value=json_encode(['status'=>$status,'at'=>time()]);
    $stmt=$db->prepare("INSERT INTO console_settings(setting_key,value_json,updated_at) VALUES('script_reload_status',?,?) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json),updated_at=VALUES(updated_at)");
    if($stmt){$now=time();$stmt->bind_param('si',$value,$now);$stmt->execute();$stmt->close();}
}
