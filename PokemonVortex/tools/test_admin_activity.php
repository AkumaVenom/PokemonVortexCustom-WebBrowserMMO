<?php
declare(strict_types=1);
/** Pure restriction-boundary checks. No database, credentials or game state is used. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('PV_BOOTSTRAPPED', true);
require_once dirname(__DIR__).'/includes/admin/activity_runtime.php';

$now = 2000000000;
$cases = [
    'unrestricted trainer' => [[], false, true],
    'legacy ban' => [['banned'=>'1'], false, false],
    'permanent console ban' => [['ban_until'=>-1], false, false],
    'active timed ban' => [['banned'=>'1','ban_until'=>$now+1], false, false],
    'ban expires at deadline' => [['banned'=>'1','ban_until'=>$now], false, true],
    'expired ban' => [['banned'=>'1','ban_until'=>$now-1], false, true],
    'locked account' => [['locked'=>1], false, false],
    'frozen account' => [['frozen'=>1], false, false],
    'permanent jail' => [['jail_until'=>-1], false, false],
    'active timed jail' => [['jail_until'=>$now+1], false, false],
    'jail expires at deadline' => [['jail_until'=>$now], false, true],
    'expired ban does not remove freeze' => [['banned'=>'1','ban_until'=>$now-1,'frozen'=>1], false, false],
    'expired jail does not remove legacy ban' => [['banned'=>'1','jail_until'=>$now-1], false, false],
    'invisible trainer can act' => [['invisible'=>1], false, true],
    'invisible trainer cannot be targeted' => [['invisible'=>1], true, false],
    'visible trainer can be targeted' => [['invisible'=>0], true, true],
];
foreach ($cases as $label => [$row, $visible, $expected]) {
    if (pv_admin_activity_state_allowed($row, $now, $visible) !== $expected) {
        fwrite(STDERR, 'FAIL '.$label.PHP_EOL); exit(1);
    }
}
echo 'PASS '.count($cases).' administrative activity boundary checks'.PHP_EOL;
