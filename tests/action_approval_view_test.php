<?php
require_once __DIR__.'/../lib/ActionApprovalView.php';
$p=0;$f=0;function av($n,$ok){global$p,$f;if($ok){$p++;echo"  ✓ $n\n";}else{$f++;echo"  ✗ $n\n";}}
$at='2026-09-05T20:00:00+08:00';
$action=domain_action_view(['id'=>'act_1','action'=>'添加高意向标签','status'=>'pending','profile_id'=>'c1','idempotency_key'=>'a:1','created_at'=>$at]);
$approval=domain_approval(['action_id'=>'act_1','decision'=>'approved','actor_type'=>'human','actor_id'=>'admin','decided_at'=>$at]);
$execution=domain_execution(['action_id'=>'act_1','approval_id'=>$approval['id'],'status'=>'succeeded','executor'=>'CdpSync','idempotency_key'=>'a:1:exec','result_ref'=>'cdp:c1','created_at'=>$at]);
$evaluation=domain_evaluation(['action_id'=>'act_1','execution_id'=>$execution['id'],'goal_id'=>'g1','metric'=>'qualified_leads','baseline'=>0,'observed'=>1,'sample_size'=>1,'source_type'=>'analytics','source_ref'=>'metric:1','measured_at'=>$at]);
echo "\n── action approval view ──\n";
$view=action_approval_view(['objects'=>['ActionProposal'=>[$action],'Approval'=>[$approval],'Execution'=>[$execution],'Evaluation'=>[$evaluation]],'gaps'=>[]]);
av('view is explicitly read-only',$view['mode']==='read_only'&&!$view['write_enabled']);
av('complete action is evaluated',$view['rows'][0]['state']==='evaluated');
av('chain parts remain visible',$view['rows'][0]['approval']['id']===$approval['id']&&$view['rows'][0]['execution']['id']===$execution['id']);
av('complete fixture has no orphan',$view['integrity_ok']);
av('business delta visible',$view['rows'][0]['evaluation']['delta']===1.0);
echo "\n── conservative gaps ──\n";
$orphan=action_approval_view(['objects'=>['ActionProposal'=>[],'Approval'=>[$approval],'Execution'=>[$execution],'Evaluation'=>[]],'gaps'=>[['reason'=>'legacy_log_has_no_run_boundary']]]);
av('orphan approval counted',$orphan['orphans']['approvals']===1);
av('orphan execution counted',$orphan['orphans']['executions']===1);
av('projection gap preserved',count($orphan['projection_gaps'])===1);
av('orphan never fabricates action',count($orphan['rows'])===0);
av('broken chain fails integrity',!$orphan['integrity_ok']);
$pending=action_approval_view(['objects'=>['ActionProposal'=>[$action]],'gaps'=>[]]);
av('action without approval stays proposed',$pending['rows'][0]['state']==='proposed');
echo "\n".($f===0?"✅ 全部通过（{$p}）\n":"❌ 失败 {$f} / 通过 {$p}\n");exit($f===0?0:1);
