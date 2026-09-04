<?php
/** Stage 1 acceptance: Flow and Loop share action identity and lifecycle facts. */

define('DATA_DIR', sys_get_temp_dir() . '/of-domain-' . getmypid());
function json_read(string $file): array { return []; }
require_once __DIR__ . '/../lib/AutonomyGuard.php';
require_once __DIR__ . '/../lib/DomainContract.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$name}\n"; return; }
    $fail++; echo "  ✗ {$name}" . ($detail ? " → {$detail}" : '') . "\n";
}

$legacy = [
    'id' => 'act_001', 'profile_id' => 'customer_7', 'module' => 'Sales',
    'action' => '主动跟进高意向客户', 'status' => 'pending',
    'created_at' => '2026-09-05 10:00:00',
];
$flow = domain_action_view($legacy, 'flow');
$loop = domain_action_view($legacy, 'loop');

echo "\n── shared identity ──\n";
check('Flow and Loop keep the same action id', $flow['id'] === $loop['id'] && $flow['id'] === 'act_001');
check('presentation mode does not change idempotency', $flow['idempotency_key'] === $loop['idempotency_key']);
check('legacy pending maps to proposed', $flow['status'] === 'proposed');
check('contract validates', domain_contract_validate('ActionProposal', $flow)['ok'] === true);

echo "\n── lifecycle ──\n";
$approved = domain_action_transition($flow, 'approved');
check('proposed can be approved', $approved['ok'] && $approved['action']['id'] === $flow['id']);
$running = domain_action_transition($approved['action'], 'running');
check('approved can run', $running['ok']);
check('cannot skip from proposed to succeeded', domain_action_transition($flow, 'succeeded')['ok'] === false);

echo "\n── execution truth ──\n";
$unverified = domain_action_record_execution($running['action'], ['ok' => true]);
check('model-only success is rejected', $unverified['ok'] === false && $unverified['error'] === 'unverifiable_execution');
$executed = domain_action_record_execution($running['action'], [
    'ok' => true, 'executor' => 'crm.followup', 'result_ref' => 'crm_event_9', 'executed_at' => '2026-09-05T10:02:00+08:00',
]);
check('verified executor result succeeds', $executed['ok'] && $executed['action']['status'] === 'succeeded');
check('execution preserves action identity', $executed['action']['id'] === $flow['id']);
check('result has a traceable reference', $executed['action']['execution']['result_ref'] === 'crm_event_9');

echo "\n── existing guard delegation ──\n";
$policy = domain_action_policy($flow, ['level'=>'guarded','daily_budget'=>0,'daily_action_cap'=>10,'quiet_days'=>0], ['actions'=>0,'spend'=>0]);
check('non-whitelisted action still requires a human', !$policy['allow'] && $policy['requires_human']);
$lowRisk = $flow; $lowRisk['action'] = '打标签：高意向';
check('existing guard allows low-risk action', domain_action_policy($lowRisk, ['level'=>'guarded','daily_budget'=>0,'daily_action_cap'=>10,'quiet_days'=>0], ['actions'=>0,'spend'=>0])['allow']);

echo "\n" . ($fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n");
exit($fail === 0 ? 0 : 1);
