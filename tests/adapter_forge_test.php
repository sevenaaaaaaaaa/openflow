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
// 模板骨架仍含 TODO(适配) → 按诚信规则只能是 needs-review（不得 verified）
check('骨架（有 TODO）→ needs-review', $report['status'] === 'needs-review', (string) $report['status']);
check('骨架不拿 verified 徽章', $report['badge'] === 'unverified');
check('报告含 TODO 计数', ($report['todos'] ?? 0) > 0);
// 把 TODO 去掉后应升级为 passed
$donePhp = str_replace(['TODO(适配)', 'TODO'], 'done', (string) file_get_contents($dir . '/plugin.php'));
file_put_contents($dir . '/plugin.php', $donePhp);
$report2 = adapter_verify_gate($dir, $m, ['skip_phpstan' => true]);
check('补完后 → passed + verified', $report2['status'] === 'passed' && $report2['badge'] === 'verified', (string) $report2['status']);
file_put_contents($dir . '/plugin.php', (string) $out['files']['plugin.php']);
$report = $report2;

/* 7. 篡改后闸门应拦截 */
$stampOk = adapter_verify_stamp($dir, $report2);
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

/* 9. 第二轮 AI 补全（注入桩，不调模型） */
$dir2 = sys_get_temp_dir() . '/of-adapter-c-' . getmypid() . '/' . $plan['id'];
adapter_forge_write((array) $out['files'], $dir2);
$templatePhp = (string) file_get_contents($dir2 . '/plugin.php');

// 桩代码必须保留骨架里的全部注册（否则栅栏会（正确地）拦下"删除既有注册"）
$goodCode = "<?php\ndeclare(strict_types=1);\n"
    . "function binwiederhier_ntfy_config(): array { return []; }\n"
    . "PluginSystem::register_api_route('binwiederhier-ntfy', 'POST', 'send', function (array \$req): array {\n"
    . "    \$cfg = binwiederhier_ntfy_config(); return ['ok' => true]; }, ['auth' => 'token']);\n"
    . "PluginSystem::register_api_route('binwiederhier-ntfy', 'POST', 'webhook', function (array \$req): array {\n"
    . "    return ['ok' => true, 'event' => (string) (\$req['body']['event'] ?? '')]; }, ['auth' => 'hmac']);\n";
$aiGood = static fn (string $s2, string $u, array $o): array => ['ok' => true, 'text' => "```php\n" . $goodCode . "```"];
$c1 = adapter_forge_complete($profile, $dir2, $aiGood);
check('补全：合法补全被应用', $c1['applied'] === true, (string) $c1['note']);
check('补全：AI 产物留档 plugin.ai.php', is_file($dir2 . '/plugin.ai.php'));
check('补全：模板与调用前快照都有留档', is_file($dir2 . '/plugin.template.php.bak') && is_file($dir2 . '/plugin.php.prev.bak'));
check('补全：应用后 TODO 归零', substr_count((string) file_get_contents($dir2 . '/plugin.php'), 'TODO') === 0);
$applied = (string) file_get_contents($dir2 . '/plugin.php');

$aiFail = static fn (string $s2, string $u, array $o): array => ['ok' => false, 'text' => '', 'error' => 'quota'];
$c2 = adapter_forge_complete($profile, $dir2, $aiFail);
check('补全：AI 失败 → 不应用且给出原因', $c2['applied'] === false && str_contains((string) $c2['note'], 'quota'));

$aiSecret = static fn (string $s2, string $u, array $o): array => ['ok' => true, 'text' => "<?php\ndeclare(strict_types=1);\n\$k = 'sk-abcdefghijklmnopqrstuvwx';\n"];
$c3 = adapter_forge_complete($profile, $dir2, $aiSecret);
check('补全：硬编码密钥被栅栏拦下', $c3['applied'] === false && str_contains((string) $c3['note'], '栅栏'), (string) $c3['note']);

$aiExtra = static fn (string $s2, string $u, array $o): array => ['ok' => true, 'text' => "<?php\ndeclare(strict_types=1);\nPluginSystem::register_block('x', ['type' => 'y']);\n"];
$c4 = adapter_forge_complete($profile, $dir2, $aiExtra);
check('补全：新增未声明落点被拦下', $c4['applied'] === false && str_contains((string) $c4['note'], '未声明落点'), (string) $c4['note']);

// 过得了栅栏、但过不了闸门（引用未定义函数）→ 必须回滚到调用前的版本
$badCode = "<?php\ndeclare(strict_types=1);\n"
    . "function binwiederhier_ntfy_config(): array { return []; }\n"
    . "PluginSystem::register_api_route('binwiederhier-ntfy', 'POST', 'send', function (array \$req): array { return undefined_helper_xyz(); }, ['auth' => 'token']);\n"
    . "PluginSystem::register_api_route('binwiederhier-ntfy', 'POST', 'webhook', function (array \$req): array { return ['ok' => true]; }, ['auth' => 'hmac']);\n";
$aiBad = static fn (string $s2, string $u, array $o): array => ['ok' => true, 'text' => $badCode];
$c5 = adapter_forge_complete($profile, $dir2, $aiBad);
check('补全：闸门不过 → 回滚且不应用', $c5['applied'] === false && str_contains((string) $c5['note'], '回滚'), (string) $c5['note']);
check('补全：回滚后内容与调用前一致', (string) file_get_contents($dir2 . '/plugin.php') === $applied);
check('补全：被拦下的 AI 代码仍留档（供人审）', str_contains((string) file_get_contents($dir2 . '/plugin.ai.php'), 'undefined_helper_xyz'));

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
