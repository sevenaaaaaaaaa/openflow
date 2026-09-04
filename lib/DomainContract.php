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
        return ['ok' => $errors === [], 'errors' => $errors];
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
