<?php
/**
 * Flow / Loop shared domain contracts.
 *
 * This layer normalizes existing records without owning persistence or executing
 * business side effects. Flow remains the source of truth; Loop may reference
 * the same objects through these contracts.
 */

if (!function_exists('domain_contract_version')) {
    function domain_contract_version(): int { return 1; }

    /** Machine-readable ownership and lifecycle rules for the first shared slice. */
    function domain_contract_catalog(): array {
        return [
            'Approval' => [
                'owner' => 'AuditLog',
                'required' => ['id', 'version', 'tenant_id', 'subject_type', 'subject_id', 'subject_version', 'decision', 'actor_type', 'actor_id', 'decided_at'],
                'decisions' => ['approved', 'rejected', 'revoked'],
                'actor_types' => ['human', 'policy'],
                'permission' => 'existing admin, tenant and autonomy guards',
                'idempotency' => 'tenant_id + subject_id + subject_version + decision',
                'audit_events' => ['approval.approved', 'approval.rejected', 'approval.revoked'],
            ],
            'Execution' => [
                'owner' => 'domain executor',
                'required' => ['id', 'version', 'tenant_id', 'action_id', 'approval_id', 'status', 'executor', 'idempotency_key', 'created_at'],
                'statuses' => ['queued', 'running', 'succeeded', 'failed', 'cancelled'],
                'transitions' => [
                    'queued' => ['running', 'cancelled'],
                    'running' => ['succeeded', 'failed', 'cancelled'],
                    'failed' => ['queued', 'cancelled'],
                    'succeeded' => [],
                    'cancelled' => [],
                ],
                'permission' => 'approved action plus existing domain guards',
                'idempotency' => 'tenant_id + idempotency_key',
                'audit_events' => ['execution.queued', 'execution.started', 'execution.succeeded', 'execution.failed', 'execution.cancelled'],
            ],
            'Evaluation' => [
                'owner' => 'GrowthSignal and analytics',
                'required' => ['id', 'version', 'tenant_id', 'action_id', 'execution_id', 'goal_id', 'metric', 'baseline', 'observed', 'delta', 'sample_size', 'source_type', 'source_ref', 'measured_at'],
                'source_types' => ['event', 'order', 'conversion_ledger', 'analytics'],
                'permission' => 'read-only analytics and tenant guards',
                'idempotency' => 'tenant_id + execution_id + metric + source_ref',
                'audit_events' => ['evaluation.recorded'],
            ],
            'Goal' => [
                'owner' => 'GrowthGoal',
                'required' => ['id', 'version', 'tenant_id', 'status', 'metric', 'target', 'baseline', 'created_at'],
                'statuses' => ['active', 'achieved', 'archived'],
                'permission' => 'existing admin and tenant guards',
                'idempotency' => 'tenant_id + id',
                'audit_events' => ['goal.activated', 'goal.achieved', 'goal.archived'],
            ],
            'SkillDefinition' => [
                'owner' => 'SkillSystem',
                'required' => ['id', 'version', 'tenant_id', 'status', 'type', 'title', 'permissions'],
                'statuses' => ['draft', 'review', 'published', 'rejected', 'archived'],
                'types' => ['prompt', 'tool', 'workflow'],
                'permission' => 'SkillGuard declaration and review',
                'idempotency' => 'tenant_id + id + version',
                'audit_events' => ['skill.drafted', 'skill.reviewed', 'skill.published', 'skill.rejected', 'skill.archived'],
            ],
            'SkillInvocation' => [
                'owner' => 'SkillSystem',
                'required' => ['id', 'version', 'tenant_id', 'skill_id', 'skill_version', 'status', 'executor', 'idempotency_key', 'created_at'],
                'statuses' => ['queued', 'running', 'succeeded', 'failed', 'cancelled'],
                'transitions' => [
                    'queued' => ['running', 'cancelled'],
                    'running' => ['succeeded', 'failed', 'cancelled'],
                    'failed' => ['queued', 'cancelled'],
                    'succeeded' => [],
                    'cancelled' => [],
                ],
                'permission' => 'SkillGuard plus declared SkillDefinition permissions',
                'idempotency' => 'tenant_id + skill_id + skill_version + idempotency_key',
                'audit_events' => ['skill.queued', 'skill.started', 'skill.succeeded', 'skill.failed', 'skill.cancelled'],
            ],
            'FlowDefinition' => [
                'owner' => 'AutomationSystem or CanvasSystem',
                'required' => ['id', 'version', 'tenant_id', 'status', 'source_type', 'name', 'trigger', 'structure_hash', 'input_schema', 'output_schema', 'risk_level', 'permissions', 'created_at'],
                'statuses' => ['draft', 'active', 'paused', 'archived'],
                'source_types' => ['automation', 'canvas'],
                'risk_levels' => ['low', 'medium', 'high', 'critical'],
                'permission' => 'existing automation/canvas admin and domain guards',
                'idempotency' => 'tenant_id + id + version + structure_hash',
                'audit_events' => ['flow.defined', 'flow.activated', 'flow.paused', 'flow.archived'],
            ],
            'FlowRun' => [
                'owner' => 'FlowSystem',
                'required' => ['id', 'version', 'tenant_id', 'definition_id', 'status', 'trigger', 'idempotency_key', 'created_at'],
                'statuses' => ['queued', 'running', 'succeeded', 'failed', 'cancelled'],
                'transitions' => [
                    'queued' => ['running', 'cancelled'],
                    'running' => ['succeeded', 'failed', 'cancelled'],
                    'failed' => ['queued', 'cancelled'],
                    'succeeded' => [],
                    'cancelled' => [],
                ],
                'permission' => 'existing trigger, channel and domain guards',
                'idempotency' => 'tenant_id + idempotency_key',
                'audit_events' => ['flow.queued', 'flow.started', 'flow.succeeded', 'flow.failed', 'flow.cancelled'],
            ],
            'ActionProposal' => [
                'owner' => 'GrowthAction',
                'required' => ['id', 'version', 'tenant_id', 'status', 'action', 'idempotency_key', 'created_at'],
                'statuses' => ['proposed', 'approved', 'running', 'succeeded', 'failed', 'cancelled'],
                'transitions' => [
                    'proposed' => ['approved', 'cancelled'],
                    'approved' => ['running', 'cancelled'],
                    'running' => ['succeeded', 'failed'],
                    'failed' => ['approved', 'cancelled'],
                    'succeeded' => [],
                    'cancelled' => [],
                ],
                'permission' => 'existing policy and domain guards',
                'idempotency' => 'tenant_id + idempotency_key',
                'audit_events' => ['action.proposed', 'action.approved', 'action.started', 'action.succeeded', 'action.failed', 'action.cancelled'],
            ],
        ];
    }

    function domain_approval(array $source): array {
        $tenantId = trim((string)($source['tenant_id'] ?? 'default')) ?: 'default';
        $subjectId = trim((string)($source['subject_id'] ?? ($source['action_id'] ?? '')));
        $subjectVersion = max(1, (int)($source['subject_version'] ?? 1));
        $decision = (string)($source['decision'] ?? '');
        $id = trim((string)($source['id'] ?? ''));
        if ($id === '' && $subjectId !== '' && $decision !== '') {
            $id = 'apr_' . substr(hash('sha256', $tenantId . '|' . $subjectId . '|' . $subjectVersion . '|' . $decision), 0, 20);
        }
        return [
            'contract' => 'Approval', 'contract_version' => domain_contract_version(),
            'id' => $id, 'version' => max(1, (int)($source['version'] ?? 1)), 'tenant_id' => $tenantId,
            'subject_type' => (string)($source['subject_type'] ?? 'ActionProposal'),
            'subject_id' => $subjectId, 'subject_version' => $subjectVersion,
            'decision' => $decision, 'actor_type' => (string)($source['actor_type'] ?? 'human'),
            'actor_id' => trim((string)($source['actor_id'] ?? '')),
            'policy_ref' => trim((string)($source['policy_ref'] ?? '')),
            'reason' => (string)($source['reason'] ?? ''),
            'decided_at' => (string)($source['decided_at'] ?? ''),
        ];
    }

    function domain_execution(array $source): array {
        $tenantId = trim((string)($source['tenant_id'] ?? 'default')) ?: 'default';
        $actionId = trim((string)($source['action_id'] ?? ''));
        $key = trim((string)($source['idempotency_key'] ?? ''));
        $id = trim((string)($source['id'] ?? ''));
        if ($id === '' && $actionId !== '' && $key !== '') {
            $id = 'exe_' . substr(hash('sha256', $tenantId . '|' . $actionId . '|' . $key), 0, 20);
        }
        return [
            'contract' => 'Execution', 'contract_version' => domain_contract_version(),
            'id' => $id, 'version' => max(1, (int)($source['version'] ?? 1)), 'tenant_id' => $tenantId,
            'action_id' => $actionId, 'approval_id' => trim((string)($source['approval_id'] ?? '')),
            'flow_run_id' => trim((string)($source['flow_run_id'] ?? '')),
            'status' => (string)($source['status'] ?? 'queued'),
            'executor' => trim((string)($source['executor'] ?? '')),
            'idempotency_key' => $key, 'request_ref' => (string)($source['request_ref'] ?? ''),
            'result_ref' => (string)($source['result_ref'] ?? ''), 'error' => (string)($source['error'] ?? ''),
            'created_at' => (string)($source['created_at'] ?? ''),
            'completed_at' => (string)($source['completed_at'] ?? ''),
        ];
    }

    function domain_evaluation(array $source): array {
        $tenantId = trim((string)($source['tenant_id'] ?? 'default')) ?: 'default';
        $baseline = (float)($source['baseline'] ?? 0);
        $observed = (float)($source['observed'] ?? 0);
        $parts = [$tenantId, (string)($source['execution_id'] ?? ''), (string)($source['metric'] ?? ''), (string)($source['source_ref'] ?? '')];
        $id = trim((string)($source['id'] ?? ''));
        if ($id === '' && $parts[1] !== '' && $parts[2] !== '' && $parts[3] !== '') {
            $id = 'eval_' . substr(hash('sha256', implode('|', $parts)), 0, 20);
        }
        return [
            'contract' => 'Evaluation', 'contract_version' => domain_contract_version(),
            'id' => $id, 'version' => max(1, (int)($source['version'] ?? 1)), 'tenant_id' => $tenantId,
            'action_id' => trim((string)($source['action_id'] ?? '')),
            'execution_id' => trim((string)($source['execution_id'] ?? '')),
            'goal_id' => trim((string)($source['goal_id'] ?? '')),
            'metric' => trim((string)($source['metric'] ?? '')), 'baseline' => $baseline,
            'observed' => $observed, 'delta' => round($observed - $baseline, 4),
            'sample_size' => max(0, (int)($source['sample_size'] ?? 0)),
            'source_type' => (string)($source['source_type'] ?? ''),
            'source_ref' => trim((string)($source['source_ref'] ?? '')),
            'attribution' => (string)($source['attribution'] ?? 'observed'),
            'measured_at' => (string)($source['measured_at'] ?? ''),
        ];
    }

    function domain_goal_view(array $source, string $viewMode = 'flow'): array {
        return [
            'contract' => 'Goal',
            'contract_version' => domain_contract_version(),
            'id' => trim((string)($source['id'] ?? '')),
            'version' => max(1, (int)($source['version'] ?? 1)),
            'tenant_id' => trim((string)($source['tenant_id'] ?? 'default')) ?: 'default',
            'status' => (string)($source['status'] ?? 'active'),
            'title' => (string)($source['title'] ?? ''),
            'metric' => (string)($source['metric'] ?? ''),
            'target' => (float)($source['target'] ?? 0),
            'baseline' => (float)($source['baseline'] ?? 0),
            'window_days' => max(0, (int)($source['window_days'] ?? 0)),
            'budget' => max(0, (float)($source['budget'] ?? 0)),
            'created_at' => (string)($source['created_at'] ?? ''),
            'source_ref' => ['owner' => 'GrowthGoal', 'id' => (string)($source['id'] ?? '')],
            'view_mode' => in_array($viewMode, ['flow', 'loop'], true) ? $viewMode : 'flow',
        ];
    }

    function domain_skill_view(array $source, string $viewMode = 'flow'): array {
        $permissions = array_values(array_unique(array_map('strval', (array)($source['permissions'] ?? []))));
        sort($permissions);
        return [
            'contract' => 'SkillDefinition',
            'contract_version' => domain_contract_version(),
            'id' => trim((string)($source['id'] ?? '')),
            'version' => trim((string)($source['version'] ?? '1.0.0')) ?: '1.0.0',
            'tenant_id' => trim((string)($source['tenant_id'] ?? 'default')) ?: 'default',
            'status' => (string)($source['status'] ?? 'draft'),
            'type' => (string)($source['type'] ?? 'prompt'),
            'title' => trim((string)($source['title'] ?? '')),
            'description' => (string)($source['description'] ?? ''),
            'permissions' => $permissions,
            'tips_stage' => (string)($source['tips_stage'] ?? ''),
            'source_ref' => ['owner' => 'SkillSystem', 'id' => (string)($source['id'] ?? '')],
            'view_mode' => in_array($viewMode, ['flow', 'loop'], true) ? $viewMode : 'flow',
        ];
    }

    /** Normalize an existing Automation or Canvas flow without owning its storage. */
    function domain_flow_definition(array $source, string $sourceType = 'automation', string $viewMode = 'flow'): array {
        $sourceType = in_array($sourceType, ['automation','canvas'], true) ? $sourceType : 'automation';
        $structure = $sourceType === 'canvas'
            ? ['nodes'=>array_values((array)($source['nodes'] ?? [])), 'edges'=>array_values((array)($source['edges'] ?? []))]
            : ['steps'=>array_values((array)($source['steps'] ?? []))];
        $permissions = array_values(array_unique(array_map('strval', (array)($source['permissions'] ?? []))));
        sort($permissions);
        $status = (string)($source['status'] ?? (!empty($source['enabled']) ? 'active' : 'paused'));
        $trigger = trim((string)($source['trigger'] ?? ''));
        if ($sourceType === 'canvas' && $trigger === '') {
            foreach ($structure['nodes'] as $node) {
                if (($node['type'] ?? '') === 'trigger') { $trigger = trim((string)($node['trigger'] ?? '')); break; }
            }
        }
        return [
            'contract'=>'FlowDefinition', 'contract_version'=>domain_contract_version(),
            'id'=>trim((string)($source['id'] ?? '')), 'version'=>max(1, (int)($source['version'] ?? 1)),
            'tenant_id'=>trim((string)($source['tenant_id'] ?? 'default')) ?: 'default',
            'status'=>$status, 'source_type'=>$sourceType, 'name'=>trim((string)($source['name'] ?? '')),
            'trigger'=>$trigger, 'structure_hash'=>hash('sha256', json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'input_schema'=>is_array($source['input_schema'] ?? null) ? $source['input_schema'] : ['type'=>'object','additionalProperties'=>true],
            'output_schema'=>is_array($source['output_schema'] ?? null) ? $source['output_schema'] : ['type'=>'object'],
            'risk_level'=>(string)($source['risk_level'] ?? 'medium'), 'permissions'=>$permissions,
            'structure'=>$structure, 'created_at'=>(string)($source['created_at'] ?? ($source['updated_at'] ?? '')),
            'updated_at'=>(string)($source['updated_at'] ?? ''),
            'source_ref'=>['owner'=>$sourceType === 'canvas' ? 'CanvasSystem' : 'AutomationSystem', 'id'=>(string)($source['id'] ?? '')],
            'view_mode'=>in_array($viewMode, ['flow','loop'], true) ? $viewMode : 'flow',
        ];
    }

    /** Build an invocation envelope; execution remains owned by SkillSystem. */
    function domain_skill_invocation(array $source, string $viewMode = 'flow'): array {
        $tenantId = trim((string)($source['tenant_id'] ?? 'default')) ?: 'default';
        $skillId = trim((string)($source['skill_id'] ?? ''));
        $skillVersion = trim((string)($source['skill_version'] ?? '1.0.0')) ?: '1.0.0';
        $key = trim((string)($source['idempotency_key'] ?? ''));
        $id = trim((string)($source['id'] ?? ''));
        if ($id === '' && $skillId !== '' && $key !== '') {
            $id = 'ski_' . substr(hash('sha256', $tenantId . '|' . $skillId . '|' . $skillVersion . '|' . $key), 0, 20);
        }
        return [
            'contract'=>'SkillInvocation', 'contract_version'=>domain_contract_version(),
            'id'=>$id, 'version'=>max(1, (int)($source['version'] ?? 1)), 'tenant_id'=>$tenantId,
            'skill_id'=>$skillId, 'skill_version'=>$skillVersion, 'status'=>(string)($source['status'] ?? 'queued'),
            'executor'=>trim((string)($source['executor'] ?? 'SkillSystem::skill_execute')),
            'idempotency_key'=>$key, 'request_ref'=>(string)($source['request_ref'] ?? ''),
            'result_ref'=>(string)($source['result_ref'] ?? ''), 'error'=>(string)($source['error'] ?? ''),
            'cost'=>is_array($source['cost'] ?? null) ? $source['cost'] : [],
            'created_at'=>(string)($source['created_at'] ?? ''), 'completed_at'=>(string)($source['completed_at'] ?? ''),
            'source_ref'=>['owner'=>'SkillSystem', 'skill_id'=>$skillId],
            'view_mode'=>in_array($viewMode, ['flow','loop'], true) ? $viewMode : 'flow',
        ];
    }

    /** Create a deterministic run envelope; it does not execute or persist a flow. */
    function domain_flow_run(array $source, string $viewMode = 'flow'): array {
        $tenantId = trim((string)($source['tenant_id'] ?? 'default')) ?: 'default';
        $definitionId = trim((string)($source['definition_id'] ?? ($source['flow_id'] ?? '')));
        $trigger = trim((string)($source['trigger'] ?? 'manual')) ?: 'manual';
        $createdAt = (string)($source['created_at'] ?? '');
        $idempotencyKey = trim((string)($source['idempotency_key'] ?? ''));
        $id = trim((string)($source['id'] ?? ''));
        if ($idempotencyKey === '' && $id !== '') $idempotencyKey = 'flow-run:' . $id;
        if ($id === '' && $definitionId !== '' && $idempotencyKey !== '') {
            $id = 'run_' . substr(hash('sha256', $tenantId . '|' . $definitionId . '|' . $idempotencyKey), 0, 20);
        }

        return [
            'contract' => 'FlowRun',
            'contract_version' => domain_contract_version(),
            'id' => $id,
            'version' => max(1, (int)($source['version'] ?? 1)),
            'tenant_id' => $tenantId,
            'definition_id' => $definitionId,
            'status' => (string)($source['status'] ?? 'queued'),
            'trigger' => $trigger,
            'subject_id' => (string)($source['subject_id'] ?? ''),
            'idempotency_key' => $idempotencyKey,
            'created_at' => $createdAt,
            'result' => is_array($source['result'] ?? null) ? $source['result'] : null,
            'source_ref' => ['owner' => 'FlowSystem', 'definition_id' => $definitionId],
            'view_mode' => in_array($viewMode, ['flow', 'loop'], true) ? $viewMode : 'flow',
        ];
    }

    function domain_action_status(string $legacyStatus): string {
        return [
            'pending' => 'proposed',
            'done' => 'succeeded',
            'dismissed' => 'cancelled',
        ][$legacyStatus] ?? $legacyStatus;
    }

    /**
     * Normalize an existing action for either product mode.
     * view_mode is presentation context only and never participates in identity.
     */
    function domain_action_view(array $source, string $viewMode = 'flow'): array {
        $id = trim((string)($source['id'] ?? ''));
        $tenantId = trim((string)($source['tenant_id'] ?? 'default')) ?: 'default';
        $idempotencyKey = trim((string)($source['idempotency_key'] ?? ($source['dedupe_key'] ?? '')));
        if ($idempotencyKey === '' && $id !== '') $idempotencyKey = 'action:' . $id;

        return [
            'contract' => 'ActionProposal',
            'contract_version' => domain_contract_version(),
            'id' => $id,
            'version' => max(1, (int)($source['version'] ?? 1)),
            'tenant_id' => $tenantId,
            'status' => domain_action_status((string)($source['status'] ?? 'pending')),
            'action' => trim((string)($source['action'] ?? '')),
            'module' => (string)($source['module'] ?? ''),
            'subject_id' => (string)($source['profile_id'] ?? ($source['subject_id'] ?? '')),
            'goal_id' => (string)($source['goal_id'] ?? ''),
            'idempotency_key' => $idempotencyKey,
            'created_at' => (string)($source['created_at'] ?? ''),
            'updated_at' => (string)($source['updated_at'] ?? ($source['done_at'] ?? '')),
            'execution' => is_array($source['execution'] ?? null) ? $source['execution'] : null,
            'source_ref' => ['owner' => 'GrowthAction', 'id' => $id],
            'view_mode' => in_array($viewMode, ['flow', 'loop'], true) ? $viewMode : 'flow',
        ];
    }

    function domain_contract_validate(string $type, array $object): array {
        $definition = domain_contract_catalog()[$type] ?? null;
        if (!$definition) return ['ok' => false, 'errors' => ['unknown_contract']];

        $errors = [];
        foreach ($definition['required'] as $field) {
            if (!array_key_exists($field, $object) || $object[$field] === '') $errors[] = 'missing:' . $field;
        }
        if (isset($object['status']) && !in_array($object['status'], $definition['statuses'], true)) {
            $errors[] = 'invalid_status:' . $object['status'];
        }
        if (isset($definition['types'], $object['type']) && !in_array($object['type'], $definition['types'], true)) {
            $errors[] = 'invalid_type:' . $object['type'];
        }
        if (isset($definition['decisions'], $object['decision']) && !in_array($object['decision'], $definition['decisions'], true)) {
            $errors[] = 'invalid_decision:' . $object['decision'];
        }
        if (isset($definition['actor_types'], $object['actor_type']) && !in_array($object['actor_type'], $definition['actor_types'], true)) {
            $errors[] = 'invalid_actor_type:' . $object['actor_type'];
        }
        if (($type === 'Approval') && ($object['actor_type'] ?? '') === 'policy' && empty($object['policy_ref'])) {
            $errors[] = 'missing:policy_ref';
        }
        if ($type === 'Execution' && ($object['status'] ?? '') === 'succeeded' && empty($object['result_ref'])) {
            $errors[] = 'missing:result_ref';
        }
        if ($type === 'Execution' && ($object['status'] ?? '') === 'failed' && empty($object['error'])) {
            $errors[] = 'missing:error';
        }
        if (isset($definition['source_types'], $object['source_type']) && !in_array($object['source_type'], $definition['source_types'], true)) {
            $errors[] = 'invalid_source_type:' . $object['source_type'];
        }
        if (isset($definition['risk_levels'], $object['risk_level']) && !in_array($object['risk_level'], $definition['risk_levels'], true)) {
            $errors[] = 'invalid_risk_level:' . $object['risk_level'];
        }
        if ($type === 'FlowDefinition' && (!is_array($object['input_schema'] ?? null) || !is_array($object['output_schema'] ?? null))) {
            $errors[] = 'invalid_io_schema';
        }
        if ($type === 'SkillInvocation' && ($object['status'] ?? '') === 'succeeded' && empty($object['result_ref'])) {
            $errors[] = 'missing:result_ref';
        }
        if ($type === 'SkillInvocation' && ($object['status'] ?? '') === 'failed' && empty($object['error'])) {
            $errors[] = 'missing:error';
        }
        if ($type === 'Evaluation' && (int)($object['sample_size'] ?? 0) < 1) $errors[] = 'invalid_sample_size';
        return ['ok' => $errors === [], 'errors' => $errors];
    }

    function domain_skill_invocation_transition(array $invocation, string $nextStatus, array $evidence = []): array {
        $current = (string)($invocation['status'] ?? '');
        $allowed = domain_contract_catalog()['SkillInvocation']['transitions'][$current] ?? [];
        if (!in_array($nextStatus, $allowed, true)) {
            return ['ok'=>false, 'error'=>'invalid_transition', 'from'=>$current, 'to'=>$nextStatus, 'invocation'=>$invocation];
        }
        if ($nextStatus === 'succeeded' && empty($evidence['result_ref'])) return ['ok'=>false, 'error'=>'missing_result_ref', 'invocation'=>$invocation];
        if ($nextStatus === 'failed' && empty($evidence['error'])) return ['ok'=>false, 'error'=>'missing_error', 'invocation'=>$invocation];
        $next = $invocation;
        $next['status'] = $nextStatus;
        $next['version'] = max(1, (int)($invocation['version'] ?? 1)) + 1;
        foreach (['result_ref','error','completed_at'] as $field) if (array_key_exists($field, $evidence)) $next[$field] = $evidence[$field];
        if (isset($evidence['cost']) && is_array($evidence['cost'])) $next['cost'] = $evidence['cost'];
        if (in_array($nextStatus, ['succeeded','failed','cancelled'], true) && empty($next['completed_at'])) $next['completed_at'] = date('c');
        return ['ok'=>true, 'event'=>'skill.' . $nextStatus, 'invocation'=>$next];
    }

    function domain_execution_transition(array $execution, string $nextStatus, array $evidence = []): array {
        $current = (string)($execution['status'] ?? '');
        $allowed = domain_contract_catalog()['Execution']['transitions'][$current] ?? [];
        if (!in_array($nextStatus, $allowed, true)) {
            return ['ok' => false, 'error' => 'invalid_transition', 'from' => $current, 'to' => $nextStatus, 'execution' => $execution];
        }
        if ($nextStatus === 'succeeded' && empty($evidence['result_ref'])) {
            return ['ok' => false, 'error' => 'missing_result_ref', 'execution' => $execution];
        }
        if ($nextStatus === 'failed' && empty($evidence['error'])) {
            return ['ok' => false, 'error' => 'missing_error', 'execution' => $execution];
        }
        $next = $execution;
        $next['status'] = $nextStatus;
        $next['version'] = max(1, (int)($execution['version'] ?? 1)) + 1;
        if (isset($evidence['result_ref'])) $next['result_ref'] = (string)$evidence['result_ref'];
        if (isset($evidence['error'])) $next['error'] = (string)$evidence['error'];
        if (in_array($nextStatus, ['succeeded','failed','cancelled'], true)) {
            $next['completed_at'] = (string)($evidence['completed_at'] ?? date('c'));
        }
        return ['ok' => true, 'event' => 'execution.' . $nextStatus, 'execution' => $next];
    }

    /** Verify that approval, execution and evaluation describe one evidence chain. */
    function domain_evidence_chain(array $action, array $approval, array $execution, array $evaluation): array {
        $errors = [];
        foreach ([['Approval',$approval], ['Execution',$execution], ['Evaluation',$evaluation]] as [$type, $object]) {
            $validation = domain_contract_validate($type, $object);
            foreach ($validation['errors'] as $error) $errors[] = $type . ':' . $error;
        }
        if (($approval['subject_type'] ?? '') !== 'ActionProposal' || ($approval['subject_id'] ?? '') !== ($action['id'] ?? '')) $errors[] = 'approval_subject_mismatch';
        if (($approval['decision'] ?? '') !== 'approved') $errors[] = 'action_not_approved';
        if (($execution['action_id'] ?? '') !== ($action['id'] ?? '')) $errors[] = 'execution_action_mismatch';
        if (($execution['approval_id'] ?? '') !== ($approval['id'] ?? '')) $errors[] = 'execution_approval_mismatch';
        if (($evaluation['action_id'] ?? '') !== ($action['id'] ?? '')) $errors[] = 'evaluation_action_mismatch';
        if (($evaluation['execution_id'] ?? '') !== ($execution['id'] ?? '')) $errors[] = 'evaluation_execution_mismatch';
        if (($evaluation['tenant_id'] ?? '') !== ($action['tenant_id'] ?? '') || ($execution['tenant_id'] ?? '') !== ($action['tenant_id'] ?? '')) $errors[] = 'tenant_mismatch';
        return ['ok' => $errors === [], 'errors' => array_values(array_unique($errors))];
    }

    function domain_flow_run_transition(array $run, string $nextStatus): array {
        $current = (string)($run['status'] ?? '');
        $allowed = domain_contract_catalog()['FlowRun']['transitions'][$current] ?? [];
        if (!in_array($nextStatus, $allowed, true)) {
            return ['ok' => false, 'error' => 'invalid_transition', 'from' => $current, 'to' => $nextStatus, 'run' => $run];
        }
        $next = $run;
        $next['status'] = $nextStatus;
        $next['version'] = max(1, (int)($run['version'] ?? 1)) + 1;
        return ['ok' => true, 'event' => 'flow.' . $nextStatus, 'run' => $next];
    }

    function domain_flow_run_record_result(array $run, array $result): array {
        if (($run['status'] ?? '') !== 'running') return ['ok' => false, 'error' => 'run_not_running', 'run' => $run];
        if (!array_key_exists('ok', $result) || empty($result['executor'])) {
            return ['ok' => false, 'error' => 'unverifiable_result', 'run' => $run];
        }
        $transition = domain_flow_run_transition($run, $result['ok'] ? 'succeeded' : 'failed');
        $transition['run']['result'] = [
            'executor' => (string)$result['executor'],
            'ok' => (bool)$result['ok'],
            'result_ref' => (string)($result['result_ref'] ?? ''),
            'error' => (string)($result['error'] ?? ''),
            'completed_at' => (string)($result['completed_at'] ?? date('c')),
        ];
        return $transition;
    }

    /** Pure state transition; callers remain responsible for persistence and audit. */
    function domain_action_transition(array $action, string $nextStatus): array {
        $current = (string)($action['status'] ?? '');
        $allowed = domain_contract_catalog()['ActionProposal']['transitions'][$current] ?? [];
        if (!in_array($nextStatus, $allowed, true)) {
            return ['ok' => false, 'error' => 'invalid_transition', 'from' => $current, 'to' => $nextStatus, 'action' => $action];
        }
        $next = $action;
        $next['status'] = $nextStatus;
        $next['version'] = max(1, (int)($action['version'] ?? 1)) + 1;
        return ['ok' => true, 'event' => 'action.' . $nextStatus, 'action' => $next];
    }

    /** Attach a real executor result; model text alone is never accepted as success. */
    function domain_action_record_execution(array $action, array $result): array {
        if (($action['status'] ?? '') !== 'running') {
            return ['ok' => false, 'error' => 'action_not_running', 'action' => $action];
        }
        if (!array_key_exists('ok', $result) || empty($result['executor'])) {
            return ['ok' => false, 'error' => 'unverifiable_execution', 'action' => $action];
        }

        $nextStatus = $result['ok'] ? 'succeeded' : 'failed';
        $transition = domain_action_transition($action, $nextStatus);
        $transition['action']['execution'] = [
            'executor' => (string)$result['executor'],
            'ok' => (bool)$result['ok'],
            'result_ref' => (string)($result['result_ref'] ?? ''),
            'error' => (string)($result['error'] ?? ''),
            'executed_at' => (string)($result['executed_at'] ?? date('c')),
        ];
        return $transition;
    }

    /** Delegate automatic-execution decisions to the existing autonomy guard. */
    function domain_action_policy(array $action, ?array $config = null, ?array $usage = null): array {
        if (!function_exists('autonomy_can_auto')) {
            return ['allow' => false, 'requires_human' => true, 'reason' => '自治守卫未加载'];
        }
        return autonomy_can_auto([
            'action' => (string)($action['action'] ?? ''),
            'module' => (string)($action['module'] ?? ''),
            'subject' => (string)($action['subject_id'] ?? ''),
            'cost' => (float)($action['cost'] ?? 0),
        ], $config, $usage);
    }
}
