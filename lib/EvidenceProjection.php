<?php
/** Read-only projection from existing Flow facts into shared domain contracts. */

require_once __DIR__ . '/DomainContract.php';

if (!function_exists('evidence_project')) {
    function evidence_projection_gap(string $source, int $index, string $reason): array {
        return ['source' => $source, 'index' => $index, 'reason' => $reason];
    }

    /**
     * Pure projection. Unsupported legacy rows are reported as gaps, never guessed.
     * No persistence and no business executor are called here.
     */
    function evidence_project(array $actions, array $flowLogs, array $conversionLedger): array {
        $objects = ['ActionProposal'=>[], 'FlowRun'=>[], 'Approval'=>[], 'Execution'=>[], 'Evaluation'=>[]];
        $gaps = [];

        foreach (array_values($actions) as $index => $row) {
            $action = domain_action_view($row, 'flow');
            $validation = domain_contract_validate('ActionProposal', $action);
            if (!$validation['ok']) {
                $gaps[] = evidence_projection_gap('growth_action', $index, 'invalid_action:' . implode(',', $validation['errors']));
                continue;
            }
            $objects['ActionProposal'][] = $action;

            if (($row['status'] ?? '') === 'done' && empty($row['execution'])) {
                $gaps[] = evidence_projection_gap('growth_action', $index, 'done_without_execution_receipt');
            }
            if (!empty($row['approval']) && is_array($row['approval'])) {
                $approval = domain_approval($row['approval'] + ['action_id'=>$action['id'], 'subject_version'=>$action['version'], 'tenant_id'=>$action['tenant_id']]);
                if (domain_contract_validate('Approval', $approval)['ok']) $objects['Approval'][] = $approval;
                else $gaps[] = evidence_projection_gap('growth_action', $index, 'invalid_embedded_approval');
            }
            if (!empty($row['execution']) && is_array($row['execution'])) {
                $execution = domain_execution($row['execution'] + ['action_id'=>$action['id'], 'tenant_id'=>$action['tenant_id']]);
                if (domain_contract_validate('Execution', $execution)['ok']) $objects['Execution'][] = $execution;
                else $gaps[] = evidence_projection_gap('growth_action', $index, 'invalid_embedded_execution');
            }
        }

        $runsById = [];
        $approvalsById = [];
        $executionsById = [];
        foreach (array_values($flowLogs) as $index => $row) {
            $structured = !empty($row['run_id']) && !empty($row['flow']) && !empty($row['idempotency_key']) && !empty($row['status']);
            if (!$structured) {
                $gaps[] = evidence_projection_gap('automation_log', $index, 'legacy_log_has_no_run_boundary');
                continue;
            }
            $run = domain_flow_run([
                'id'=>$row['run_id'], 'flow_id'=>$row['flow'], 'trigger'=>$row['trigger'] ?? 'unknown',
                'status'=>$row['status'], 'idempotency_key'=>$row['idempotency_key'],
                'tenant_id'=>$row['tenant_id'] ?? 'default', 'created_at'=>$row['time'] ?? '',
                'result'=>$row['result'] ?? null,
            ], 'flow');
            $validation = domain_contract_validate('FlowRun', $run);
            if ($validation['ok']) $runsById[$run['id']] = $run;
            else $gaps[] = evidence_projection_gap('automation_log', $index, 'invalid_structured_run:' . implode(',', $validation['errors']));
            if (!empty($row['approval']) && is_array($row['approval'])) {
                $approval = domain_approval($row['approval']);
                $approvalValidation = domain_contract_validate('Approval', $approval);
                if ($approvalValidation['ok']) $approvalsById[$approval['id']] = $approval;
                else $gaps[] = evidence_projection_gap('automation_log', $index, 'invalid_shadow_approval:' . implode(',', $approvalValidation['errors']));
            }
            if (!empty($row['execution']) && is_array($row['execution'])) {
                $execution = domain_execution($row['execution']);
                $executionValidation = domain_contract_validate('Execution', $execution);
                if ($executionValidation['ok']) $executionsById[$execution['id']] = $execution;
                else $gaps[] = evidence_projection_gap('automation_log', $index, 'invalid_shadow_execution:' . implode(',', $executionValidation['errors']));
            }
        }
        $objects['FlowRun'] = array_values($runsById);
        $objects['Approval'] = array_values(array_replace(array_column($objects['Approval'], null, 'id'), $approvalsById));
        $objects['Execution'] = array_values(array_replace(array_column($objects['Execution'], null, 'id'), $executionsById));

        $events = is_array($conversionLedger['events'] ?? null) ? $conversionLedger['events'] : [];
        if (!$events && ((int)($conversionLedger['total']['count'] ?? 0) > 0)) {
            $gaps[] = evidence_projection_gap('conversion_ledger', 0, 'aggregate_ledger_has_no_action_attribution');
        }
        foreach (array_values($events) as $index => $row) {
            $evaluation = domain_evaluation($row + ['source_type'=>'conversion_ledger']);
            $validation = domain_contract_validate('Evaluation', $evaluation);
            if ($validation['ok']) $objects['Evaluation'][] = $evaluation;
            else $gaps[] = evidence_projection_gap('conversion_event', $index, 'invalid_evaluation:' . implode(',', $validation['errors']));
        }

        $projected = array_sum(array_map('count', $objects));
        return [
            'mode' => 'read_only', 'contract_version' => domain_contract_version(),
            'objects' => $objects,
            'summary' => ['projected'=>$projected, 'gaps'=>count($gaps), 'by_contract'=>array_map('count', $objects)],
            'gaps' => $gaps,
        ];
    }

    /** Read current stores without modifying them. */
    function evidence_project_current(): array {
        $actions = function_exists('growth_action_all') ? growth_action_all() : [];
        $logs = function_exists('json_read') && defined('DATA_DIR') ? json_read(DATA_DIR . '/automation-log.json') : [];
        $ledger = function_exists('growth_conv_read') ? growth_conv_read() : [];
        return evidence_project(is_array($actions) ? $actions : [], is_array($logs) ? $logs : [], is_array($ledger) ? $ledger : []);
    }
}
