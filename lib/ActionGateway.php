<?php
/** Shared action planning boundary. V1 is dry-run only and executes nothing. */

require_once __DIR__ . '/DomainContract.php';
require_once __DIR__ . '/AutonomyGuard.php';

if (!function_exists('action_gateway_catalog')) {
    function action_gateway_catalog(): array {
        return [
            'add_tag'=>[
                'executor'=>'CdpSync::cdp_add_tag', 'risk_level'=>'low', 'cost'=>0.0,
                'permissions'=>['cdp.write'], 'reversible'=>true, 'production_enabled'=>false,
            ],
        ];
    }

    /** Pure planning except for existing read functions; never calls an executor. */
    function action_gateway_dry_run(array $request, ?array $guardConfig = null, ?array $usage = null): array {
        $type = trim((string)($request['action_type'] ?? ''));
        $capability = action_gateway_catalog()[$type] ?? null;
        if (!$capability) return ['ok'=>false, 'mode'=>'dry_run', 'error'=>'unsupported_action'];

        $subjectId = trim((string)($request['subject_id'] ?? ''));
        $tag = trim((string)($request['params']['tag'] ?? ''));
        $key = trim((string)($request['idempotency_key'] ?? ''));
        if ($subjectId === '' || $tag === '' || $key === '') {
            return ['ok'=>false, 'mode'=>'dry_run', 'error'=>'missing_required_input'];
        }
        $tenantId = trim((string)($request['tenant_id'] ?? 'default')) ?: 'default';
        $proposalId = 'act_' . substr(hash('sha256', $tenantId . '|' . $type . '|' . $subjectId . '|' . $key), 0, 20);
        $proposal = domain_action_view([
            'id'=>$proposalId, 'tenant_id'=>$tenantId, 'profile_id'=>$subjectId,
            'module'=>'CDP', 'action'=>'打标签：' . $tag, 'status'=>'pending',
            'idempotency_key'=>$key, 'created_at'=>(string)($request['created_at'] ?? date('c')),
        ], (string)($request['view_mode'] ?? 'flow'));

        $target = null;
        if (function_exists('cdp_get_by_id')) $target = cdp_get_by_id($subjectId);
        $exists = is_array($target);
        $beforeTags = $exists ? (json_decode((string)($target['tags'] ?? '[]'), true) ?: []) : [];
        $alreadyApplied = in_array($tag, $beforeTags, true);
        $guard = domain_action_policy($proposal, $guardConfig, $usage);
        $issues = [];
        if (!$exists) $issues[] = 'target_not_found';
        if (!$guard['allow']) $issues[] = 'approval_required';

        return [
            'ok'=>true, 'mode'=>'dry_run', 'would_execute'=>false,
            'proposal'=>$proposal, 'capability'=>$capability,
            'target'=>['type'=>'cdp_customer', 'id'=>$subjectId, 'exists'=>$exists],
            'expected_change'=>['field'=>'tags', 'value'=>$tag, 'already_applied'=>$alreadyApplied],
            'policy'=>$guard, 'issues'=>$issues,
            'ready'=>empty($issues),
        ];
    }
}
