<?php
/** Read-only Observe -> TIPS Plan runtime acceptance tests. */
$GLOBALS['LR_WRITES']=0;
$GLOBALS['LR_CUSTOMERS']=['lead_1'=>['id'=>'lead_1','tags'=>'["浏览"]']];
function cdp_get_by_id(string $id):?array{return $GLOBALS['LR_CUSTOMERS'][$id]??null;}
function cdp_add_tag(string $id,string $tag):void{$GLOBALS['LR_WRITES']++;}
require_once __DIR__.'/../lib/LoopRuntime.php';
$p=0;$f=0;function lr($n,$ok){global$p,$f;if($ok){$p++;echo"  ✓ $n\n";}else{$f++;echo"  ✗ $n\n";}}

$created='2026-09-05T16:00:00+08:00';
$goal=['id'=>'goal_leads','status'=>'active','metric'=>'qualified_leads','target'=>10,'baseline'=>2,'created_at'=>$created];
$policy=['id'=>'policy_leads','status'=>'active','risk_level'=>'low','permissions'=>['cdp.write'],'daily_action_cap'=>10,'created_at'=>$created];
$definition=['id'=>'loop_leads','status'=>'active','goal_id'=>'goal_leads','policy_id'=>'policy_leads','allowed_flow_ids'=>[],'allowed_skill_ids'=>[],'budgets'=>['max_iterations'=>9],'stop_conditions'=>['goal_reached'=>true],'created_at'=>$created];
$action=domain_action_view(['id'=>'act_old','action'=>'旧建议','status'=>'pending','idempotency_key'=>'old:1','created_at'=>$created]);
$evidence=['objects'=>['ActionProposal'=>[$action]],'gaps'=>[['reason'=>'legacy_log_has_no_run_boundary']]];
$input=['definition'=>$definition,'goal'=>$goal,'policy'=>$policy,'evidence'=>$evidence,'idempotency_key'=>'cycle:1','created_at'=>$created,
    'candidate_action'=>['action_type'=>'add_tag','subject_id'=>'lead_1','params'=>['tag'=>'高意向']],
    'guard_config'=>['level'=>'guarded','daily_budget'=>0,'daily_action_cap'=>10,'quiet_days'=>0], 'usage'=>['actions'=>0,'spend'=>0]];

echo "\n── one read-only cycle ──\n";
$result=loop_runtime_readonly_cycle($input);
lr('cycle succeeds',$result['ok']);
lr('mode and side-effect boundary explicit',$result['mode']==='read_only' && !$result['side_effects']);
lr('no model or executor called',$result['model_calls']===0 && $result['executor_calls']===0);
lr('all TIPS stages present',array_keys($result['tips_plan'])===['Touch','Insight','Personalize','Sell']);
lr('only valid evidence becomes a reference',$result['observation']['validated_refs']===['ActionProposal:act_old']);
lr('evidence gaps remain visible',$result['observation']['gap_count']===1);
lr('candidate only reaches dry-run',$result['dry_run']['mode']==='dry_run' && $result['decision']==='proposal_ready_for_review');
lr('business data unchanged',$GLOBALS['LR_WRITES']===0 && $GLOBALS['LR_CUSTOMERS']['lead_1']['tags']==='["浏览"]');
lr('run ends after one iteration',$result['run']['status']==='succeeded' && $result['run']['iteration']===1 && $result['run']['max_iterations']===1);
lr('zero AI cost is auditable',$result['run']['budget_usage']['tokens']===0 && $result['run']['budget_usage']['cost']===0);
$repeat=loop_runtime_readonly_cycle($input);
lr('same input keeps run identity',$repeat['run']['id']===$result['run']['id']);

echo "\n── fail closed ──\n";
$bad=$input;$bad['definition']['goal_id']='another';
$failed=loop_runtime_readonly_cycle($bad);
lr('goal mismatch fails closed',!$failed['ok'] && !$failed['side_effects']);
$inactive=$input;$inactive['policy']['status']='disabled';
lr('disabled policy fails closed',!loop_runtime_readonly_cycle($inactive)['ok']);
$missing=$input;unset($missing['idempotency_key']);
lr('idempotency is mandatory',!loop_runtime_readonly_cycle($missing)['ok']);

echo "\n".($f===0?"✅ 全部通过（{$p}）\n":"❌ 失败 {$f} / 通过 {$p}\n");exit($f===0?0:1);
