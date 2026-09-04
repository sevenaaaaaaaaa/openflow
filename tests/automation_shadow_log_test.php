<?php
/** Low-risk add_tag shadow-write compatibility tests. */
define('DATA_DIR', sys_get_temp_dir() . '/of-shadow-' . getmypid());
@mkdir(DATA_DIR,0777,true);
function json_read(string $file):array{if(!is_file($file))return[];$v=json_decode((string)file_get_contents($file),true);return is_array($v)?$v:[];}
function json_write(string $file,array $value):bool{@mkdir(dirname($file),0777,true);return(bool)file_put_contents($file,json_encode($value,JSON_UNESCAPED_UNICODE));}
$GLOBALS['CUSTOMERS']=['c1'=>['id'=>'c1','tags'=>'[]']];
function cdp_add_tag(string $id,string $tag):void{$c=$GLOBALS['CUSTOMERS'][$id]??null;if(!$c)return;$tags=json_decode($c['tags'],true)?:[];if(!in_array($tag,$tags,true))$tags[]=$tag;$GLOBALS['CUSTOMERS'][$id]['tags']=json_encode($tags,JSON_UNESCAPED_UNICODE);}
function cdp_get_by_id(string $id):?array{return $GLOBALS['CUSTOMERS'][$id]??null;}
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_once __DIR__ . '/../lib/EvidenceProjection.php';

$pass=0;$fail=0;function check(string $n,bool $ok,string $d=''):void{global$pass,$fail;if($ok){$pass++;echo"  ✓ {$n}\n";}else{$fail++;echo"  ✗ {$n}".($d?" → {$d}":'')."\n";}}

echo "\n── legacy compatibility ──\n";
automation_log('legacy_1','旧日志','info');
$legacy=json_read(automation_log_file())[0];
check('legacy fields remain exact',array_keys($legacy)===['time','flow','level','message'],json_encode(array_keys($legacy)));

echo "\n── eligibility boundary ──\n";
$flow=['id'=>'flow_tag','trigger'=>'form_submit','steps'=>[['action'=>'add_tag','tag'=>'高意向']],'enabled'=>true];
check('stable event key is required',automation_shadow_add_tag($flow,['uid'=>'c1'])===null);
check('disabled flow cannot create approval evidence',automation_shadow_add_tag(array_merge($flow,['enabled'=>false]),['uid'=>'c1','event_id'=>'e0'])===null);
check('multiple steps are excluded',automation_shadow_add_tag(array_merge($flow,['steps'=>[['action'=>'add_tag','tag'=>'x'],['action'=>'notify']]]),['uid'=>'c1','event_id'=>'e1'])===null);
check('high-risk/non-tag action is excluded',automation_shadow_add_tag(array_merge($flow,['steps'=>[['action'=>'send_email']]]),['uid'=>'c1','event_id'=>'e1'])===null);
$repeatA=automation_shadow_add_tag($flow,['uid'=>'c1','event_id'=>'event_repeat']);
$repeatB=automation_shadow_add_tag($flow,['uid'=>'c1','event_id'=>'event_repeat']);
check('duplicate event id resolves to same run id',($repeatA['run_id']??'')!=='' && $repeatA['run_id']===$repeatB['run_id']);

echo "\n── verified shadow write ──\n";
automation_execute_flow($flow,['uid'=>'c1','event_id'=>'event_1']);
$logs=json_read(automation_log_file());
$shadow=array_values(array_filter($logs,fn($x)=>isset($x['run_id'])));
check('running and succeeded shadow rows written',count($shadow)===2,json_encode($shadow));
check('both rows share deterministic run id',$shadow[0]['run_id']===$shadow[1]['run_id']);
check('success has real executor',($shadow[1]['result']['executor']??'')==='CdpSync::cdp_add_tag');
check('enabled flow creates policy approval envelope',($shadow[1]['approval']['actor_id']??'')==='enabled_flow_configuration' && ($shadow[1]['approval']['decision']??'')==='approved');
check('approval has explicit policy reference',str_starts_with((string)($shadow[1]['approval']['policy_ref']??''),'flow-definition:flow_tag:enabled'));
check('execution references approval and FlowRun',($shadow[1]['execution']['approval_id']??'')===($shadow[1]['approval']['id']??'') && ($shadow[1]['execution']['flow_run_id']??'')===$shadow[1]['run_id']);
check('execution success has result evidence',($shadow[1]['execution']['status']??'')==='succeeded' && ($shadow[1]['execution']['result_ref']??'')==='cdp_customer:c1');
check('legacy tag log still exists',count(array_filter($logs,fn($x)=>($x['message']??'')==='打标签: 高意向'))===1);
$projection=evidence_project([], $logs, []);
check('structured rows coalesce to one FlowRun',count($projection['objects']['FlowRun'])===1);
check('latest verified status wins',($projection['objects']['FlowRun'][0]['status']??'')==='succeeded');
check('shadow approval projects once',count($projection['objects']['Approval'])===1);
check('shadow execution projects latest terminal state',count($projection['objects']['Execution'])===1 && ($projection['objects']['Execution'][0]['status']??'')==='succeeded');

echo "\n".($fail===0?"✅ 全部通过（{$pass}）\n":"❌ 失败 {$fail} / 通过 {$pass}\n");
foreach(glob(DATA_DIR.'/*')?:[]as$f)if(is_file($f))@unlink($f);@rmdir(DATA_DIR);exit($fail===0?0:1);
