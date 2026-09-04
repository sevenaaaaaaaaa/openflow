<?php
/** Loop Runtime v1: one deterministic, read-only Observe -> TIPS Plan cycle. */

require_once __DIR__ . '/DomainContract.php';
require_once __DIR__ . '/ActionGateway.php';

if (!function_exists('loop_runtime_readonly_cycle')) {
    function loop_runtime_issue(string $code, string $field = ''): array {
        return ['code'=>$code, 'field'=>$field];
    }

    function loop_runtime_evidence_summary(array $projection): array {
        $objects = is_array($projection['objects'] ?? null) ? $projection['objects'] : [];
        $allowed = ['ActionProposal','FlowRun','Approval','Execution','Evaluation'];
        $counts = [];
        $refs = [];
        foreach ($allowed as $type) {
            $rows = is_array($objects[$type] ?? null) ? array_values($objects[$type]) : [];
            $counts[$type] = count($rows);
            foreach ($rows as $row) {
                if (!is_array($row) || !domain_contract_validate($type, $row)['ok']) continue;
                $refs[] = $type . ':' . $row['id'];
            }
        }
        sort($refs);
        return [
            'mode'=>'read_only', 'counts'=>$counts, 'validated_refs'=>$refs,
            'gap_count'=>count((array)($projection['gaps'] ?? [])),
        ];
    }

    /**
     * Runs exactly one local planning cycle. It does not persist, invoke a model,
     * call a Flow/Skill executor, approve an action, or write business data.
     */
    function loop_runtime_readonly_cycle(array $input): array {
        $definition = domain_loop_definition((array)($input['definition'] ?? []));
        $goal = domain_goal_view((array)($input['goal'] ?? []));
        $policy = domain_policy((array)($input['policy'] ?? []));
        $key = trim((string)($input['idempotency_key'] ?? ''));
        $createdAt = (string)($input['created_at'] ?? date('c'));
        $issues = [];

        foreach ([['LoopDefinition',$definition,'definition'],['Goal',$goal,'goal'],['Policy',$policy,'policy']] as $check) {
            $validation = domain_contract_validate($check[0], $check[1]);
            foreach ($validation['errors'] as $error) $issues[] = loop_runtime_issue('invalid_contract:' . $error, $check[2]);
        }
        if ($key === '') $issues[] = loop_runtime_issue('missing_idempotency_key', 'idempotency_key');
        if ($definition['goal_id'] !== $goal['id']) $issues[] = loop_runtime_issue('goal_mismatch', 'goal');
        if (($definition['policy_id'] ?? '') !== '' && $definition['policy_id'] !== $policy['id']) $issues[] = loop_runtime_issue('policy_mismatch', 'policy');
        if ($definition['status'] !== 'active') $issues[] = loop_runtime_issue('definition_not_active', 'definition.status');
        if ($goal['status'] !== 'active') $issues[] = loop_runtime_issue('goal_not_active', 'goal.status');
        if ($policy['status'] !== 'active') $issues[] = loop_runtime_issue('policy_not_active', 'policy.status');
        if ($issues) return ['ok'=>false, 'mode'=>'read_only', 'issues'=>$issues, 'side_effects'=>false];

        $maxIterations = max(1, min(1, (int)($definition['budgets']['max_iterations'] ?? 1)));
        $run = domain_loop_run([
            'tenant_id'=>$definition['tenant_id'], 'definition_id'=>$definition['id'], 'goal_id'=>$goal['id'],
            'idempotency_key'=>$key, 'max_iterations'=>$maxIterations, 'created_at'=>$createdAt, 'updated_at'=>$createdAt,
        ]);
        $observation = loop_runtime_evidence_summary((array)($input['evidence'] ?? []));
        $observing = domain_loop_run_transition($run, 'observing', ['updated_at'=>$createdAt, 'evidence_refs'=>$observation['validated_refs']]);
        $planning = domain_loop_run_transition($observing['run'], 'planning', ['updated_at'=>$createdAt, 'tips_stage'=>'Touch']);

        $dryRun = null;
        if (is_array($input['candidate_action'] ?? null)) {
            $request = $input['candidate_action'];
            $request['tenant_id'] = $definition['tenant_id'];
            $request['idempotency_key'] = $request['idempotency_key'] ?? ($run['id'] . ':action:0');
            $request['created_at'] = $request['created_at'] ?? $createdAt;
            $request['view_mode'] = 'loop';
            $dryRun = action_gateway_dry_run($request, (array)($input['guard_config'] ?? []), (array)($input['usage'] ?? []));
        }

        $target = (float)$goal['target'];
        $baseline = (float)$goal['baseline'];
        $plan = [
            'Touch'=>['objective'=>'识别与目标相关的可触达对象', 'evidence_refs'=>$observation['validated_refs']],
            'Insight'=>['objective'=>'仅依据可追溯业务证据形成判断', 'metric'=>$goal['metric'], 'baseline'=>$baseline, 'target'=>$target, 'evidence_gaps'=>$observation['gap_count']],
            'Personalize'=>['objective'=>'在策略边界内准备差异化方案', 'subject_id'=>(string)($input['candidate_action']['subject_id'] ?? ''), 'proposal_ref'=>$dryRun['proposal']['id'] ?? ''],
            'Sell'=>['objective'=>'准备可审批行动，不执行生产写入', 'action_type'=>(string)($input['candidate_action']['action_type'] ?? ''), 'dry_run_ready'=>(bool)($dryRun['ready'] ?? false)],
        ];
        $evaluating = domain_loop_run_transition($planning['run'], 'evaluating', ['updated_at'=>$createdAt, 'tips_plan_hash'=>hash('sha256', json_encode($plan, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))]);
        $finished = domain_loop_run_transition($evaluating['run'], 'succeeded', ['updated_at'=>$createdAt, 'read_only'=>true]);
        $finalRun = $finished['run'];
        $finalRun['iteration'] = 1;
        $finalRun['budget_usage'] = ['steps'=>4, 'tokens'=>0, 'cost'=>0, 'elapsed_seconds'=>0];

        return [
            'ok'=>true, 'mode'=>'read_only', 'side_effects'=>false, 'model_calls'=>0, 'executor_calls'=>0,
            'run'=>$finalRun, 'observation'=>$observation, 'tips_plan'=>$plan, 'dry_run'=>$dryRun,
            'decision'=>$dryRun && ($dryRun['ready'] ?? false) ? 'proposal_ready_for_review' : 'observe_only',
        ];
    }
}
