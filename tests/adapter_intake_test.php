<?php
declare(strict_types=1);
/**
 * 适配源接入（Adapter Intake）契约测试
 *   php tests/adapter_intake_test.php
 * 规范：docs/ADAPTER-SPEC.md
 */

require_once __DIR__ . '/../lib/AdapterIntake.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "适配源接入\n";

/* 1. 来源解析 */
$r = adapter_parse_source('https://github.com/binwiederhier/ntfy');
check('解析 GitHub URL', $r['ok'] && $r['owner'] === 'binwiederhier' && $r['repo'] === 'ntfy' && $r['kind'] === 'github', json_encode($r));
check('解析 .git 后缀', adapter_parse_source('https://github.com/a/b.git')['repo'] === 'b');
check('解析 git@ 形式', adapter_parse_source('git@github.com:o/r.git')['slug'] === 'o/r');
check('解析短写 owner/repo', adapter_parse_source('n8n-io/n8n')['slug'] === 'n8n-io/n8n');
check('非 GitHub 归 generic', adapter_parse_source('https://gitlab.com/x/y')['kind'] === 'generic');
check('空串被拒', adapter_parse_source('   ')['ok'] === false);
check('非法串被拒', adapter_parse_source('not a path')['ok'] === false);

/* 2. 许可证闸门 */
check('MIT 通过', adapter_license_gate('MIT')['level'] === 'permissive' && adapter_license_gate('MIT')['ok'] === true);
check('AGPL → needs-review', adapter_license_gate('agpl-3.0')['level'] === 'copyleft' && adapter_license_gate('agpl-3.0')['ok'] === false);
check('无许可证 → blocked', adapter_license_gate('')['level'] === 'unknown' && adapter_license_gate('noassertion')['ok'] === false);
check('未在白名单 → other', adapter_license_gate('cc-by-nc-4.0')['level'] === 'other');

/* 3. 落点识别 */
$s = adapter_detect_surfaces('REST API with webhook callbacks and a nightly cron sync');
check('识别 api_route', isset($s['api_route']));
check('识别 hook', isset($s['hook']));
check('识别 schedule', isset($s['schedule']));
check('无信号返回空', adapter_detect_surfaces('a small note taking app') === []);

/* 4. 能力类 */
check('归入 notify（通知）', adapter_classify('ntfy', 'send push notifications to your phone') === 'notify_reach');
check('归入 intel（RSS）', adapter_classify('FreshRSS', 'a self-hosted RSS feed reader') === 'intel_source');
check('归入 payment', adapter_classify('lago', 'open source billing and subscription management') === 'payment_billing');
check('未命中返回空串', adapter_classify('foo', 'bar baz') === '');

/* 5. 权限最小集 ⊇ 落点所需 */
$perms = adapter_required_permissions(['publish', 'schedule']);
check('publish→notify/http', in_array('notify', $perms, true) && in_array('http', $perms, true), json_encode($perms));
check('schedule→schedule', in_array('schedule', $perms, true));
check('始终含基础集', in_array('config', $perms, true) && in_array('log', $perms, true));

/* 6. 能力画像 + manifest 草稿 */
$profile = adapter_intake_profile([
    'repo' => 'binwiederhier/ntfy',
    'description' => 'Send push notifications via a simple HTTP API',
    'license' => 'apache-2.0',
    'stars' => 34297,
    'pushed_at' => '2026-09-15',
    'topics' => ['notifications', 'push', 'webhook'],
], 'ntfy lets you send push notifications. REST API and webhooks supported.', '');
check('画像含 source.slug', ($profile['source']['slug'] ?? '') === 'binwiederhier/ntfy');
check('画像归入 notify_reach', ($profile['class'] ?? '') === 'notify_reach');
check('画像落点非空', is_array($profile['surfaces']) && $profile['surfaces'] !== []);
check('画像权限含 notify', in_array('notify', (array) $profile['permissions'], true));
check('许可证通过', ($profile['license_gate']['ok'] ?? false) === true);
check('验证状态为 pending（不得自证）', ($profile['verification']['status'] ?? '') === 'pending', json_encode($profile['verification']));

$m = adapter_manifest_skeleton($profile);
check('manifest id 规范化', ($m['id'] ?? '') === 'binwiederhier-ntfy', (string) ($m['id'] ?? ''));
check('manifest 默认不启用', ($m['enabled_by_default'] ?? true) === false);
check('manifest 带 source 追溯', ($m['source']['repo'] ?? '') === 'binwiederhier/ntfy');
check('manifest capacities 为空待填', ($m['capabilities']['network'] ?? ['x']) === []);
check('manifest 权限与画像一致', ($m['permissions'] ?? []) === $profile['permissions']);

/* 7. AI 提示词含硬约束 */
$p = adapter_ai_prompt($profile);
check('提示词含「官方 API」约束', str_contains($p, '官方 API'));
check('提示词含「不硬编码凭据」', str_contains($p, '绝不硬编码'));
check('提示词要求生成契约测试', str_contains($p, 'contract_test.php'));

/* 8. 无 OpenAPI 时证据等级不为 A */
$weak = adapter_intake_profile(['repo' => 'a/b', 'description' => 'todo app', 'license' => 'mit'], 'nothing here', '');
check('无 API 证据 → 状态 blocked', ($weak['verification']['status'] ?? '') === 'blocked');

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
