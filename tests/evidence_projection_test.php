<?php
/** Read-only evidence projection acceptance tests. */
require_once __DIR__ . '/../lib/EvidenceProjection.php';

$pass=0;$fail=0;
function check(string $name,bool $ok,string $detail=''):void{global $pass,$fail;if($ok){$pass++;echo "  ✓ {$name}\n";}else{$fail++;echo "  ✗ {$name}".($detail?" → {$detail}":'')."\n";}}

echo "\n── conservative legacy projection ──\n";
$legacy = evidence_project(
    [['id'=>'act_1','action'=>'跟进客户','module'=>'Sales','status'=>'done','created_at'=>'2026-09-05 10:00:00']],
    [['time'=>'2026-09-05 10:01:00','flow'=>'flow_1','level'=>'info','message'=>'邮件已发送']],
    ['total'=>['count'=>2,'revenue'=>3000],'updated_at'=>'2026-09-05 10:02:00']
);
check('legacy action is projected', count($legacy['objects']['ActionProposal'])===1);
check('done does not fabricate execution', count($legacy['objects']['Execution'])===0);
check('unbounded log does not fabricate FlowRun', count($legacy['objects']['FlowRun'])===0);
check('aggregate ledger does not fabricate Evaluation', count($legacy['objects']['Evaluation'])===0);
$reasons=array_column($legacy['gaps'],'reason');
check('missing execution receipt is visible', in_array('done_without_execution_receipt',$reasons,true));
check('missing run boundary is visible', in_array('legacy_log_has_no_run_boundary',$reasons,true));
check('missing attribution is visible', in_array('aggregate_ledger_has_no_action_attribution',$reasons,true));

echo "\n── structured evidence projection ──\n";
$structured = evidence_project(
    [[
        'id'=>'act_2','action'=>'发送跟进邮件','module'=>'MA','status'=>'done','created_at'=>'2026-09-05 11:00:00',
        'approval'=>['id'=>'apr_2','decision'=>'approved','actor_type'=>'human','actor_id'=>'admin','decided_at'=>'2026-09-05 11:01:00'],
        'execution'=>['id'=>'exe_2','approval_id'=>'apr_2','status'=>'succeeded','executor'=>'AutomationSystem','idempotency_key'=>'mail:2','result_ref'=>'mail_2','created_at'=>'2026-09-05 11:02:00'],
    ]],
    [['run_id'=>'run_2','flow'=>'flow_2','trigger'=>'form_submit','status'=>'succeeded','idempotency_key'=>'event_2','time'=>'2026-09-05 11:00:00','result'=>['ref'=>'log_2']]],
    ['events'=>[ ['action_id'=>'act_2','execution_id'=>'exe_2','goal_id'=>'g_2','metric'=>'revenue','baseline'=>0,'observed'=>800,'sample_size'=>1,'source_ref'=>'order_2','measured_at'=>'2026-09-05 12:00:00'] ]]
);
check('structured approval projects', count($structured['objects']['Approval'])===1);
check('structured execution projects', count($structured['objects']['Execution'])===1);
check('structured FlowRun projects', count($structured['objects']['FlowRun'])===1);
check('attributed conversion projects', count($structured['objects']['Evaluation'])===1);
check('projection is explicitly read-only', $structured['mode']==='read_only');

$action=$structured['objects']['ActionProposal'][0];$approval=$structured['objects']['Approval'][0];$execution=$structured['objects']['Execution'][0];$evaluation=$structured['objects']['Evaluation'][0];
check('projected evidence chain validates', domain_evidence_chain($action,$approval,$execution,$evaluation)['ok']);

echo "\n── optional shadow envelopes ──\n";
$shadowApproval=domain_approval(['action_id'=>'act_tag','decision'=>'approved','actor_type'=>'policy','actor_id'=>'enabled_flow_configuration','policy_ref'=>'flow-definition:f1:enabled','decided_at'=>'2026-09-05 12:00:00']);
$shadowRunning=domain_execution(['action_id'=>'act_tag','approval_id'=>$shadowApproval['id'],'flow_run_id'=>'run_tag','status'=>'running','executor'=>'CdpSync::cdp_add_tag','idempotency_key'=>'event_1:add_tag:0','created_at'=>'2026-09-05 12:00:00']);
$shadowDone=domain_execution(array_merge($shadowRunning,['status'=>'succeeded','result_ref'=>'cdp_customer:c1','completed_at'=>'2026-09-05 12:00:01']));
$shadowProjection=evidence_project([], [
    ['time'=>'2026-09-05 12:00:00','flow'=>'f1','message'=>'影子运行：running','run_id'=>'run_tag','trigger'=>'page_view','status'=>'running','idempotency_key'=>'event_1','approval'=>$shadowApproval,'execution'=>$shadowRunning],
    ['time'=>'2026-09-05 12:00:01','flow'=>'f1','message'=>'影子运行：succeeded','run_id'=>'run_tag','trigger'=>'page_view','status'=>'succeeded','idempotency_key'=>'event_1','approval'=>$shadowApproval,'execution'=>$shadowDone],
], []);
check('shadow approval coalesces by id',count($shadowProjection['objects']['Approval'])===1);
check('shadow execution latest status wins',count($shadowProjection['objects']['Execution'])===1 && $shadowProjection['objects']['Execution'][0]['status']==='succeeded');

echo "\n".($fail===0?"✅ 全部通过（{$pass}）\n":"❌ 失败 {$fail} / 通过 {$pass}\n");
exit($fail===0?0:1);
