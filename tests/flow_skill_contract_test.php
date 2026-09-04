<?php
/** FlowDefinition and SkillInvocation shared contract acceptance tests. */
require_once __DIR__ . '/../lib/DomainContract.php';
$pass=0;$fail=0;
function check(string $name,bool $ok,string $detail=''):void{global$pass,$fail;if($ok){$pass++;echo"  ✓ {$name}\n";}else{$fail++;echo"  ✗ {$name}".($detail?" → {$detail}":'')."\n";}}

echo "\n── FlowDefinition compatibility ──\n";
$automation=['id'=>'flow_1','name'=>'访问打标','trigger'=>'page_view','steps'=>[['action'=>'add_tag','tag'=>'浏览']],'enabled'=>true,'created_at'=>'2026-09-05 10:00:00'];
$flow=domain_flow_definition($automation,'automation','flow');
$loop=domain_flow_definition($automation,'automation','loop');
check('existing flow id is shared across modes',$flow['id']===$loop['id'] && $flow['id']==='flow_1');
check('presentation mode does not change structure identity',$flow['structure_hash']===$loop['structure_hash']);
check('enabled automation maps to active',$flow['status']==='active');
check('automation owner remains explicit',$flow['source_ref']['owner']==='AutomationSystem');
check('automation definition validates',domain_contract_validate('FlowDefinition',$flow)['ok']);
$changed=$automation;$changed['steps'][0]['tag']='高意向';
check('business structure change changes fingerprint',domain_flow_definition($changed)['structure_hash']!==$flow['structure_hash']);

$canvas=['id'=>'canvas_1','name'=>'线索流程','nodes'=>[['id'=>'n1','type'=>'trigger','trigger'=>'form_submit'],['id'=>'n2','type'=>'action','action'=>'add_tag']],'edges'=>[['from'=>'n1','to'=>'n2']],'enabled'=>false,'created_at'=>'2026-09-05 11:00:00'];
$canvasDef=domain_flow_definition($canvas,'canvas');
check('canvas trigger is conservatively projected',$canvasDef['trigger']==='form_submit');
check('disabled canvas maps to paused',$canvasDef['status']==='paused');
check('canvas definition validates',domain_contract_validate('FlowDefinition',$canvasDef)['ok']);
$badRisk=$flow;$badRisk['risk_level']='unbounded';
check('unknown risk level is rejected',!domain_contract_validate('FlowDefinition',$badRisk)['ok']);

echo "\n── SkillInvocation evidence ──\n";
$source=['skill_id'=>'skill_tag','skill_version'=>'1.2.0','idempotency_key'=>'run_1:step_2','created_at'=>'2026-09-05 12:00:00'];
$invocation=domain_skill_invocation($source,'flow');
$loopInvocation=domain_skill_invocation($source,'loop');
check('invocation id is deterministic across modes',$invocation['id']===$loopInvocation['id'] && str_starts_with($invocation['id'],'ski_'));
check('queued invocation validates',domain_contract_validate('SkillInvocation',$invocation)['ok']);
$running=domain_skill_invocation_transition($invocation,'running');
check('queued invocation can start',$running['ok'] && $running['invocation']['id']===$invocation['id']);
check('queued invocation cannot skip to success',!domain_skill_invocation_transition($invocation,'succeeded',['result_ref'=>'result_1'])['ok']);
check('success without result evidence is rejected',!domain_skill_invocation_transition($running['invocation'],'succeeded')['ok']);
$done=domain_skill_invocation_transition($running['invocation'],'succeeded',['result_ref'=>'skill-result:1','cost'=>['tokens'=>120],'completed_at'=>'2026-09-05 12:00:01']);
check('verified invocation succeeds',$done['ok'] && $done['invocation']['status']==='succeeded');
check('cost and result remain traceable',$done['invocation']['result_ref']==='skill-result:1' && $done['invocation']['cost']['tokens']===120);
$failed=domain_skill_invocation(array_merge($source,['status'=>'failed']));
check('failed invocation requires error evidence',!domain_contract_validate('SkillInvocation',$failed)['ok']);

echo "\n".($fail===0?"✅ 全部通过（{$pass}）\n":"❌ 失败 {$fail} / 通过 {$pass}\n");
exit($fail===0?0:1);
