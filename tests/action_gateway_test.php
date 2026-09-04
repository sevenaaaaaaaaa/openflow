<?php
/** Action Gateway v1: deterministic, guarded, read-only add_tag planning. */
define('DATA_DIR',sys_get_temp_dir().'/of-gateway-'.getmypid());
function json_read(string $file):array{return[];}
$GLOBALS['GW_CUSTOMERS']=['c1'=>['id'=>'c1','tags'=>'["浏览"]']];
$GLOBALS['GW_WRITES']=0;
function cdp_get_by_id(string $id):?array{return $GLOBALS['GW_CUSTOMERS'][$id]??null;}
function cdp_add_tag(string $id,string $tag):void{$GLOBALS['GW_WRITES']++;}
require_once __DIR__.'/../lib/ActionGateway.php';
$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''):void{global$pass,$fail;if($ok){$pass++;echo"  ✓ {$n}\n";}else{$fail++;echo"  ✗ {$n}".($d?" → {$d}":'')."\n";}}

$request=['action_type'=>'add_tag','subject_id'=>'c1','params'=>['tag'=>'高意向'],'idempotency_key'=>'event_1:add_tag:0','created_at'=>'2026-09-05 13:00:00'];
$guard=['level'=>'guarded','daily_budget'=>0,'daily_action_cap'=>20,'quiet_days'=>0];
$usage=['actions'=>0,'spend'=>0];
$plan=action_gateway_dry_run($request,$guard,$usage);

echo "\n── deterministic dry-run ──\n";
check('supported action plans successfully',$plan['ok'] && $plan['mode']==='dry_run');
check('dry-run never claims execution',$plan['would_execute']===false);
check('target and expected mutation are visible',$plan['target']['exists'] && $plan['expected_change']['value']==='高意向');
check('low-risk action passes existing guarded policy',$plan['policy']['allow'] && $plan['ready']);
$repeat=action_gateway_dry_run($request,$guard,$usage);
check('same request keeps proposal identity',$repeat['proposal']['id']===$plan['proposal']['id']);
check('no CDP write occurs',$GLOBALS['GW_WRITES']===0 && $GLOBALS['GW_CUSTOMERS']['c1']['tags']==='["浏览"]');

echo "\n── safe failure ──\n";
$defaultPolicy=action_gateway_dry_run($request);
check('default propose mode requires approval',!$defaultPolicy['ready'] && in_array('approval_required',$defaultPolicy['issues'],true));
$missing=action_gateway_dry_run(array_merge($request,['subject_id'=>'missing']),$guard,$usage);
check('missing target is explicit',!$missing['ready'] && in_array('target_not_found',$missing['issues'],true));
check('unknown action is rejected',action_gateway_dry_run(array_merge($request,['action_type'=>'send_email']))['error']==='unsupported_action');
check('missing idempotency key is rejected',action_gateway_dry_run(array_merge($request,['idempotency_key'=>'']))['error']==='missing_required_input');
check('production execution remains disabled',action_gateway_catalog()['add_tag']['production_enabled']===false);

echo "\n".($fail===0?"✅ 全部通过（{$pass}）\n":"❌ 失败 {$fail} / 通过 {$pass}\n");
exit($fail===0?0:1);
