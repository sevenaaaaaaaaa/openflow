<?php
declare(strict_types=1);
/**
 * 适配合成 + 验证闸门 契约测试
 *   php tests/adapter_forge_test.php
 * 规范：docs/ADAPTER-SPEC.md
 */

require_once __DIR__ . '/../lib/AdapterIntake.php';
require_once __DIR__ . '/../lib/AdapterForge.php';
require_once __DIR__ . '/../lib/AdapterVerify.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "适配合成 + 验证闸门\n";

$profile = adapter_intake_profile([
    'repo' => 'binwiederhier/ntfy',
    'description' => 'Send push notifications via a simple HTTP API with webhook support',
    'license' => 'apache-2.0', 'stars' => 34297, 'pushed_at' => '2026-09-15',
    'topics' => ['notifications', 'push', 'webhook'], 'version' => 'v2.28.0',
], 'ntfy: REST API + webhooks. Send push notifications.', '');

/* 1. 生成计划 */
$plan = adapter_forge_plan($profile);
check('计划 id 规范化', $plan['id'] === 'binwiederhier-ntfy', (string) $plan['id']);
check('计划含 publish 段落', in_array('publish', $plan['sections'], true), implode(',', $plan['sections']));
check('计划含 hook 段落', in_array('hook', $plan['sections'], true));

/* 2. 模板生成物 */
$out = adapter_forge_generate($profile, null);
check('模板兜底标记', $out['generated_by'] === 'template');
check('产出三个文件', count($out['files']) === 3, implode(',', array_keys($out['files'])));
$php = (string) $out['files']['plugin.php'];
check('生成物注册了 api_route', str_contains($php, 'register_api_route'));
check('生成物从 plugin_config 读配置', str_contains($php, 'plugin_config('));
check('生成物无硬编码密钥', !preg_match('/[0-9a-f]{32,}/i', $php));
check('生成物声明 strict_types', str_contains($php, 'declare(strict_types=1)'));
check('生成物有 TODO 供人审', str_contains($php, 'TODO'));

/* 3. manifest 结构 */
$m = (array) $out['manifest'];
$surfKeys = array_keys((array) $m['surfaces']);
check('surfaces 是结构化映射（键为落点名）', $surfKeys !== [] && array_filter($surfKeys, 'is_string') === $surfKeys, json_encode($m['surfaces']));
check('含 api_route 与 publish 落点', in_array('api_route', $surfKeys, true) && in_array('publish', $surfKeys, true), implode(',', $surfKeys));
check('权限覆盖落点', array_values(array_diff(adapter_required_permissions(array_keys((array) $m['surfaces'])), (array) $m['permissions'])) === []);
check('默认不启用', ($m['enabled_by_default'] ?? true) === false);
check('source 带版本 pin', ((array) $m['source'])['version'] === 'v2.28.0');

/* 4. AI 路径（注入桩，不真的调模型） */
$ai = static fn (string $sys, string $user, array $opts): array => ['ok' => true, 'text' => "说明文字\n```php\n<?php\n// AI 生成\nPluginSystem::register_api_route('x','GET','y',fn()=>['ok'=>true]);\n```"];
$outAi = adapter_forge_generate($profile, $ai);
check('AI 路径被采用', $outAi['generated_by'] === 'ai');
check('AI 代码块被抠出', str_contains((string) $outAi['files']['plugin.php'], 'AI 生成'));
$aiBad = static fn (string $s, string $u, array $o): array => ['ok' => false, 'text' => ''];
check('AI 失败时回退模板', adapter_forge_generate($profile, $aiBad)['generated_by'] === 'template');

/* 5. 清单校验（含负例） */
$v = adapter_verify_manifest($m);
check('合法清单通过', $v['ok'] === true, json_encode(array_values(array_filter($v['checks'], fn ($c) => !$c['ok']))));
$bad = $m;
$bad['permissions'] = ['config', 'log'];   // 缺 api/http/notify
$vb = adapter_verify_manifest($bad);
check('缺权限 → 不通过', $vb['ok'] === false);
check('失败项含 permissions:superset', in_array('permissions:superset', array_map(fn ($c) => $c['id'], array_filter($vb['checks'], fn ($c) => !$c['ok'])), true));
$self = $m;
$self['verification'] = ['badge' => 'verified'];
check('自称 verified → 不通过', adapter_verify_manifest($self)['ok'] === false);
$noVer = $m;
$noVer['source'] = ['repo' => 'a/b', 'license' => 'apache-2.0', 'version' => ''];
check('缺版本 pin → 不通过', adapter_verify_manifest($noVer)['ok'] === false);
$verBad = $m;
$verBad['source'] = ['repo' => 'a/b', 'license' => 'mit', 'version' => '@scope/pkg@1.0.0'];
check('非 tag 形式版本 → 不通过', adapter_verify_manifest($verBad)['ok'] === false);
$verTag = $m;
$verTag['source'] = ['repo' => 'a/b', 'license' => 'mit', 'version' => 'killbill-0.24.0'];
check('带前缀的 tag → 通过', adapter_verify_manifest($verTag)['ok'] === true);

/* 6. 落盘 + 完整闸门（跳 PHPStan 保持快速） */
$dir = sys_get_temp_dir() . '/of-adapter-' . getmypid() . '/' . $plan['id'];
$w = adapter_forge_write((array) $out['files'], $dir);
check('落盘成功', ($w['ok'] ?? false) === true && count((array) $w['written']) === 3, (string) ($w['error'] ?? ''));
$report = adapter_verify_gate($dir, $m, ['skip_phpstan' => true]);
check('良好草稿 → passed', $report['status'] === 'passed', json_encode($report['failed'] ?? []));
check('passed 才有 verified 徽章', $report['badge'] === 'verified');

/* 7. 篡改后闸门应拦截 */
$stampOk = adapter_verify_stamp($dir, $report);
check('stamp 写回成功', $stampOk === true);
$stamped = json_decode((string) file_get_contents($dir . '/plugin.json'), true);
check('plugin.json 已带上徽章', ($stamped['verification']['badge'] ?? '') === 'verified');
$reportBad = adapter_verify_gate($dir, $bad, ['skip_phpstan' => true]);
check('缺权限草稿 → blocked', $reportBad['status'] === 'blocked');
check('blocked 无徽章', $reportBad['badge'] === 'unverified');
check('blocked 明确指出失败项', in_array('permissions:superset', (array) ($reportBad['failed'] ?? []), true));

/* 8. copyleft 许可证 → needs-review（不是 blocked，但要人审） */
$copyleft = $m;
$copyleft['source'] = ['repo' => 'a/b', 'license' => 'agpl-3.0', 'version' => 'v1.0.0'];
$rc = adapter_verify_gate($dir, $copyleft, ['skip_phpstan' => true]);
check('AGPL → needs-review', $rc['status'] === 'needs-review', $rc['status']);

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
