<?php
/** Stable event identity propagation without changing legacy Flow payloads. */
require_once __DIR__ . '/../lib/EventIdentity.php';

$trackSource = file_get_contents(__DIR__ . '/../api/track.php');
$cdpSource = file_get_contents(__DIR__ . '/../api/cdp.php');
$pass=0;$fail=0;
function check(string $name,bool $ok,string $detail=''):void{global$pass,$fail;if($ok){$pass++;echo"  ✓ {$name}\n";}else{$fail++;echo"  ✗ {$name}".($detail?" → {$detail}":'')."\n";}}

echo "\n── deterministic identity ──\n";
check('existing event id is preserved', event_identity(['event_id'=>'evt_known'])==='evt_known');
check('message id is accepted', event_identity(['message_id'=>'msg_known'])==='msg_known');
check('same event id remains identical', event_identity(['event_id'=>'evt_repeat'])===event_identity(['event_id'=>'evt_repeat']));
check('missing id generates a non-empty event id', str_starts_with(event_identity([]),'evt_'));

echo "\n── Flow compatibility ──\n";
$legacy=flow_trigger_context(['label'=>'阅读','page'=>'/a','props'=>['topic'=>'growth']], 'a@t.com','u1','m1');
check('missing identity keeps exact legacy payload', array_keys($legacy)===['email','uid','member_id','label','page','topic'], json_encode($legacy));
$identified=flow_trigger_context(['label'=>'阅读','event_id'=>'evt_1','props'=>[]], '','u1','');
check('event id is appended for automation and canvas', ($identified['event_id']??'')==='evt_1');
check('public track ingress passes identity', str_contains((string)$trackSource, "'event_id' => \$eventId"));
check('CDP ingress passes the same identity', str_contains((string)$cdpSource, "'event_id' => \$eventId"));

echo "\n".($fail===0?"✅ 全部通过（{$pass}）\n":"❌ 失败 {$fail} / 通过 {$pass}\n");
exit($fail===0?0:1);
