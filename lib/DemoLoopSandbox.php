<?php
/** Persistent demo-only golden Loop. Its executor can mutate demo profiles only. */
require_once __DIR__ . '/DomainContract.php';

if (!function_exists('demo_loop_state')) {
    function demo_loop_state(array $data): array { return is_array($data['simulation'] ?? null) ? $data['simulation'] : []; }
    function demo_loop_now(): string { return date('c'); }

    function demo_loop_start(array $data): array {
        foreach ((array)$data['profiles'] as $profile) {
            if (!empty($profile['expected_high_intent']) && ($profile['consent'] ?? '') === 'granted' && empty($profile['suppressed'])) { $subject=$profile; break; }
        }
        if (empty($subject)) return ['ok'=>false,'error'=>'no_eligible_demo_subject','data'=>$data];
        $now=demo_loop_now(); $tenant='demo'; $key='demo-golden-v1:' . $subject['id'];
        $goal=domain_goal_view(['id'=>'goal_demo_conversion','tenant_id'=>$tenant,'status'=>'active','metric'=>'qualified_leads','target'=>1,'baseline'=>0,'window_days'=>7,'budget'=>0,'created_at'=>$now], 'loop');
        $policy=domain_policy(['id'=>'policy_demo_low_risk','tenant_id'=>$tenant,'status'=>'active','risk_level'=>'low','permissions'=>['demo.write'],'daily_budget'=>0,'daily_action_cap'=>1,'created_at'=>$now]);
        $definition=domain_loop_definition(['id'=>'loop_demo_golden','tenant_id'=>$tenant,'status'=>'active','goal_id'=>$goal['id'],'policy_id'=>$policy['id'],'allowed_flow_ids'=>['flow_demo_add_tag'],'allowed_skill_ids'=>['skill_demo_tag'],'budgets'=>['max_iterations'=>1],'stop_conditions'=>['approval_required'=>true],'created_at'=>$now]);
        $loop=domain_loop_run(['tenant_id'=>$tenant,'definition_id'=>$definition['id'],'goal_id'=>$goal['id'],'idempotency_key'=>$key,'max_iterations'=>1,'created_at'=>$now,'updated_at'=>$now]);
        $loop=domain_loop_run_transition($loop,'observing',['updated_at'=>$now])['run'];
        $loop=domain_loop_run_transition($loop,'planning',['updated_at'=>$now,'tips_plan'=>['Touch','Insight','Personalize','Sell']])['run'];
        $loop=domain_loop_run_transition($loop,'awaiting_approval',['updated_at'=>$now])['run'];
        $flow=domain_flow_run(['tenant_id'=>$tenant,'definition_id'=>'flow_demo_add_tag','trigger'=>'demo_high_intent','idempotency_key'=>$key,'created_at'=>$now], 'loop');
        $flow=domain_flow_run_transition($flow,'running')['run'];
        $action=domain_action_view(['id'=>'act_demo_' . substr(hash('sha256',$key),0,16),'tenant_id'=>$tenant,'profile_id'=>$subject['id'],'module'=>'Personalize','action'=>'打标签：高意向','status'=>'pending','idempotency_key'=>$key . ':add_tag','created_at'=>$now], 'loop');
        $data['simulation']=['goal'=>$goal,'policy'=>$policy,'definition'=>$definition,'loop_run'=>$loop,'flow_run'=>$flow,'action'=>$action,'approval'=>null,'execution'=>null,'evaluation'=>null,'memory'=>null,'events'=>[['at'=>$now,'type'=>'proposal_created']]];
        return ['ok'=>true,'data'=>$data,'state'=>'awaiting_approval'];
    }

    function demo_loop_approve(array $data): array {
        $s=demo_loop_state($data); if (($s['loop_run']['status'] ?? '') !== 'awaiting_approval') return ['ok'=>false,'error'=>'approval_not_available','data'=>$data];
        $now=demo_loop_now(); $action=domain_action_transition($s['action'],'approved')['action'];
        $approval=domain_approval(['tenant_id'=>'demo','action_id'=>$action['id'],'subject_version'=>$action['version'],'decision'=>'approved','actor_type'=>'human','actor_id'=>'demo_operator','reason'=>'仅批准隔离 Demo 对象的低风险标签','decided_at'=>$now]);
        $s['action']=$action;$s['approval']=$approval;$s['loop_run']=domain_loop_run_transition($s['loop_run'],'executing',['updated_at'=>$now])['run'];$s['events'][]=['at'=>$now,'type'=>'approval_approved'];$data['simulation']=$s;
        return ['ok'=>true,'data'=>$data,'state'=>'approved'];
    }

    function demo_loop_execute(array $data): array {
        $s=demo_loop_state($data); if (($s['loop_run']['status'] ?? '') !== 'executing' || empty($s['approval'])) return ['ok'=>false,'error'=>'execution_not_approved','data'=>$data];
        $subjectId=(string)($s['action']['subject_id'] ?? ''); if (!str_starts_with($subjectId,'DEMO-')) return ['ok'=>false,'error'=>'demo_identity_required','data'=>$data];
        $index=null; foreach ($data['profiles'] as $i=>$p) if (($p['id'] ?? '')===$subjectId) {$index=$i;break;} if($index===null)return ['ok'=>false,'error'=>'demo_subject_missing','data'=>$data];
        $now=demo_loop_now(); $running=domain_action_transition($s['action'],'running')['action'];
        $tags=array_values((array)($data['profiles'][$index]['tags'] ?? [])); if(!in_array('高意向',$tags,true))$tags[]='高意向'; $data['profiles'][$index]['tags']=$tags;
        $action=domain_action_record_execution($running,['ok'=>true,'executor'=>'DemoActionGateway::add_tag','result_ref'=>'demo-profile:' . $subjectId . ':tags','completed_at'=>$now])['action'];
        $execution=domain_execution(['tenant_id'=>'demo','action_id'=>$action['id'],'approval_id'=>$s['approval']['id'],'flow_run_id'=>$s['flow_run']['id'],'status'=>'succeeded','executor'=>'DemoActionGateway::add_tag','idempotency_key'=>$action['id'].':execute','result_ref'=>'demo-profile:' . $subjectId . ':tags','created_at'=>$now,'completed_at'=>$now]);
        $flow=domain_flow_run_record_result($s['flow_run'],['ok'=>true,'executor'=>'DemoActionGateway::add_tag','result_ref'=>$execution['result_ref'],'completed_at'=>$now])['run'];
        $evaluation=domain_evaluation(['tenant_id'=>'demo','action_id'=>$action['id'],'execution_id'=>$execution['id'],'goal_id'=>$s['goal']['id'],'metric'=>'high_intent_tag_verified','baseline'=>0,'observed'=>1,'sample_size'=>1,'source_type'=>'event','source_ref'=>$execution['result_ref'],'measured_at'=>$now]);
        $memory=domain_memory(['tenant_id'=>'demo','subject_id'=>$subjectId,'kind'=>'result','fact'=>'已在隔离 Demo 中验证高意向标签回读','source_type'=>'execution','source_ref'=>$execution['id'],'created_at'=>$now]);
        $loop=domain_loop_run_transition($s['loop_run'],'evaluating',['updated_at'=>$now])['run'];$loop=domain_loop_run_transition($loop,'succeeded',['updated_at'=>$now,'evaluation_id'=>$evaluation['id']])['run'];$loop['iteration']=1;$loop['budget_usage']=['steps'=>7,'tokens'=>0,'cost'=>0,'elapsed_seconds'=>0];
        $s['action']=$action;$s['execution']=$execution;$s['evaluation']=$evaluation;$s['memory']=$memory;$s['flow_run']=$flow;$s['loop_run']=$loop;$s['events'][]=['at'=>$now,'type'=>'execution_succeeded'];$data['simulation']=$s;
        return ['ok'=>true,'data'=>$data,'state'=>'succeeded'];
    }
}
