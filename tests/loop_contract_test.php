<?php
require_once __DIR__.'/../lib/DomainContract.php';
$p=0;$f=0;function ck($n,$ok){global$p,$f;if($ok){$p++;echo"  ✓ $n\n";}else{$f++;echo"  ✗ $n\n";}}
echo "\n── Loop definition and policy ──\n";
$policy=domain_policy(['id'=>'policy_1','risk_level'=>'low','permissions'=>['cdp.write','cdp.write'],'daily_action_cap'=>1,'created_at'=>'2026-09-05 14:00:00']);
ck('Policy validates',domain_contract_validate('Policy',$policy)['ok']);ck('Policy permissions normalize',$policy['permissions']===['cdp.write']);
$def=domain_loop_definition(['id'=>'loop_def_1','goal_id'=>'goal_1','allowed_flow_ids'=>['flow_1'],'allowed_skill_ids'=>['skill_1'],'policy_id'=>'policy_1','budgets'=>['max_iterations'=>1],'stop_conditions'=>['goal_reached'=>true],'created_at'=>'2026-09-05 14:00:00']);
ck('TIPS LoopDefinition validates',domain_contract_validate('LoopDefinition',$def)['ok']);
$bad=$def;$bad['tips_stages']=['Plan','Execute'];ck('non-TIPS definition rejected',!domain_contract_validate('LoopDefinition',$bad)['ok']);
echo "\n── Loop run state ──\n";
$run=domain_loop_run(['definition_id'=>'loop_def_1','goal_id'=>'goal_1','idempotency_key'=>'cycle_1','max_iterations'=>1,'created_at'=>'2026-09-05 14:01:00']);
ck('LoopRun identity deterministic',$run['id']===domain_loop_run(['definition_id'=>'loop_def_1','idempotency_key'=>'cycle_1'])['id']);ck('queued LoopRun validates',domain_contract_validate('LoopRun',$run)['ok']);
$observe=domain_loop_run_transition($run,'observing',['evidence_refs'=>['event:1']]);ck('queued can observe',$observe['ok']);
$plan=domain_loop_run_transition($observe['run'],'planning',['tips_stage'=>'Insight']);ck('observing can plan',$plan['ok']);
ck('planning cannot execute without approval',!domain_loop_run_transition($plan['run'],'executing')['ok']);
$over=domain_loop_run(array_merge($run,['iteration'=>2]));ck('iteration budget enforced',!domain_contract_validate('LoopRun',$over)['ok']);
echo "\n── Traceable memory ──\n";
$memory=domain_memory(['subject_id'=>'c1','kind'=>'result','fact'=>'标签已存在','source_type'=>'execution','source_ref'=>'exe_1','created_at'=>'2026-09-05 14:02:00']);
ck('Memory identity deterministic',$memory['id']===domain_memory(['subject_id'=>'c1','source_type'=>'execution','source_ref'=>'exe_1'])['id']);ck('fact memory validates',domain_contract_validate('Memory',$memory)['ok']);
$model=$memory;$model['source_type']='model';ck('model cannot be fact source',!domain_contract_validate('Memory',$model)['ok']);
echo "\n".($f===0?"✅ 全部通过（{$p}）\n":"❌ 失败 {$f} / 通过 {$p}\n");exit($f===0?0:1);
