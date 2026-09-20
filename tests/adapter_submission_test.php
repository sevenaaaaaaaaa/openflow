<?php
declare(strict_types=1);
/**
 * 自助入驻队列 + 流水线编排 契约测试
 *
 *   php tests/adapter_submission_test.php
 *
 * 全程离线（网络与 AI 都是注入的假实现），验证五件事：
 *   1) 提交校验：非 GitHub / 乱写 / 已上架 / 已有草稿 一律拒绝，且理由能看懂
 *   2) 防重与限流：同仓库不排两次队；每人每日与全站每日配额生效；候选池灌入不占用户配额
 *   3) 状态机：claim 原子取件、闸门有结论 = done、异常 = 重试到上限才 failed、人工 requeue/reject
 *   4) 隐私：公开状态里不能出现提交者联系方式
 *   5) 流水线编排：许可证 blocked 要短路（不白花 AI 额度）；正常来源能产出草稿并盖上闸门结论
 */

$tmp = sys_get_temp_dir() . '/of-adasub-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('OF_NO_AUTO_CSRF', true);
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AdapterSubmission.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $n, string $d = ''): void
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

$sandbox = $tmp . '/sandbox';
@mkdir($sandbox . '/plugins/_drafts', 0777, true);
$ENV = ['drafts_dir' => $sandbox . '/plugins/_drafts', 'plugins_dir' => $sandbox . '/plugins'];

echo "自助入驻队列\n";

// ── 1. 提交校验 ──
ok(adapter_submit(['source' => ''], $ENV)['ok'] === false, '空来源被拒');
ok(adapter_submit(['source' => '这不是仓库地址'], $ENV)['ok'] === false, '乱写被拒');
$notGh = adapter_submit(['source' => 'https://gitlab.com/foo/bar'], $ENV);
ok($notGh['ok'] === false && str_contains($notGh['error'], 'GitHub'), '非 GitHub 来源被拒且说明原因', $notGh['error']);

$r1 = adapter_submit(['source' => 'https://github.com/acme/tool-one',
                      'note' => '有官方 webhook',
                      'submitter' => ['key' => 'm1', 'name' => '小明', 'contact' => 'm1@example.com']], $ENV);
ok($r1['ok'] === true && str_starts_with($r1['ticket'], 'sub_'), '正常提交返回受理编号', $r1['error']);
$item = adapter_sub_find($r1['ticket']);
ok($item !== null && (string) $item['slug'] === 'acme/tool-one', 'URL 被规范化成 owner/repo');
ok((string) ($item['status'] ?? '') === 'queued', '初始状态是排队中');
ok((int) ($item['attempts'] ?? -1) === 0, '初始尝试次数为 0');

// ── 2. 防重 ──
$dup = adapter_submit(['source' => 'acme/tool-one', 'submitter' => ['key' => 'm2', 'name' => '小红']], $ENV);
ok($dup['ok'] === false && $dup['duplicate_of'] === $r1['ticket'], '同仓库二次提交返回原受理编号', $dup['error']);
ok(count(adapter_sub_all()) === 1, '重复提交没有产生第二条记录');

@mkdir($ENV['plugins_dir'] . '/acme-shipped', 0777, true);
ok(adapter_submit(['source' => 'acme/shipped'], $ENV)['ok'] === false, '已上架的工具不再受理');
@mkdir($ENV['drafts_dir'] . '/acme-drafted', 0777, true);
ok(adapter_submit(['source' => 'acme/drafted'], $ENV)['ok'] === false, '已有草稿的工具不再受理');

// ── 3. 限流 ──
adapter_sub_config_set(['per_submitter_daily' => 2, 'global_daily' => 4]);
$a = adapter_submit(['source' => 'acme/t2', 'submitter' => ['key' => 'm1', 'name' => '小明']], $ENV);
ok($a['ok'] === true, '同一人第 2 个仍可提交');
$b = adapter_submit(['source' => 'acme/t3', 'submitter' => ['key' => 'm1', 'name' => '小明']], $ENV);
ok($b['ok'] === false && str_contains($b['error'], '今天'), '超过每人每日配额被拦', $b['error']);
$c = adapter_submit(['source' => 'acme/t3', 'submitter' => ['key' => 'm9', 'name' => '别人']], $ENV);
ok($c['ok'] === true, '换一个人仍可提交（配额是按人算的）');

$seedFile = $tmp . '/cands.json';
file_put_contents($seedFile, (string) json_encode(['candidates' => [
    ['slug' => 'seed/one', 'class' => 'notify_reach'],
    ['slug' => 'seed/two', 'class' => 'data_ingest'],
    ['slug' => 'acme/tool-one'],            // 已在队列 → 应跳过
    ['noslug' => true],                      // 脏数据 → 应跳过
]]));
$seed = adapter_sub_seed(10, $seedFile, $ENV);
ok($seed['added'] === 2 && $seed['skipped'] === 2, '候选池灌入：有效 2 条、跳过 2 条', "added={$seed['added']} skipped={$seed['skipped']}");
$d = adapter_submit(['source' => 'acme/t8', 'submitter' => ['key' => 'm7', 'name' => 'x']], $ENV);
ok($d['ok'] === true, '候选池灌入不占用全站每日配额', $d['error']);

adapter_sub_config_set(['per_submitter_daily' => 3, 'global_daily' => 20]);

// ── 4. 隐私 ──
$pub = adapter_sub_public((array) adapter_sub_find($r1['ticket']));
ok(!str_contains((string) json_encode($pub), 'm1@example.com'), '公开状态里没有提交者邮箱');
ok(($pub['submitted_by'] ?? '') === '小明', '公开状态保留展示名');
ok(($pub['source'] ?? '') === 'acme/tool-one', '公开状态带仓库');

// ── 5. claim 与状态机 ──
$c1 = adapter_sub_claim();
$c2 = adapter_sub_claim();
ok($c1 !== null && $c2 !== null && $c1['ticket'] !== $c2['ticket'], 'claim 两次拿到不同的件（不会重复处理同一条）');
ok((string) $c1['status'] === 'running' && (int) $c1['attempts'] === 1, 'claim 把状态置为进行中并累加尝试次数');
foreach (adapter_sub_all() as $it) {
    if ((string) $it['status'] === 'queued') { adapter_sub_act((string) $it['ticket'], 'reject', '测试清场'); }
}
ok(adapter_sub_claim() === null, '没有排队项时 claim 返回 null');

// 把两条 running 收回排队，用假流水线跑
adapter_sub_act((string) $c1['ticket'], 'requeue');
adapter_sub_act((string) $c2['ticket'], 'requeue');

// 闸门跑完（即使没通过）= 已处理，草稿保留给人审
$tick = adapter_sub_tick(['limit' => 1, 'pipeline' => static fn(string $slug, array $o): array => [
    'ok' => false, 'stage' => 'done', 'id' => 'fake-id', 'dir' => '/tmp/fake',
    'status' => 'needs-review', 'badge' => 'needs-review', 'failed' => ['contract_test'], 'todos' => 2,
]]);
ok($tick['processed'] === 1, '一轮只处理 limit 条');
$done = adapter_sub_find((string) $tick['rows'][0]['ticket']);
ok((string) $done['status'] === 'done', '闸门有结论就算已处理（没通过不等于失败）', (string) $done['status']);
ok((string) $done['result']['gate_status'] === 'needs-review', '闸门结论被记录');
ok((array) $done['result']['failed'] === ['contract_test'], '失败项被记录，人审能看到');

// 异常：未达上限自动重排队，达上限才 failed
adapter_sub_config_set(['max_attempts' => 2]);
$boomTicket = (string) ($c1['ticket'] === $tick['rows'][0]['ticket'] ? $c2['ticket'] : $c1['ticket']);
$boom = static fn(string $slug, array $o): array => ['ok' => false, 'stage' => 'intake', 'error' => '拉取失败'];
adapter_sub_tick(['limit' => 1, 'pipeline' => $boom]);
$after1 = adapter_sub_find($boomTicket);
ok((string) $after1['status'] === 'queued' && (int) $after1['attempts'] === 1,
   '第一次异常自动重排队', (string) $after1['status'] . '/' . (int) $after1['attempts']);
ok((int) adapter_sub_find((string) $c1['ticket'])['attempts'] === 0
   || (string) adapter_sub_find((string) $c1['ticket'])['status'] !== 'queued',
   '人工重排会重置重试次数（否则点了重排会立刻又失败）');
adapter_sub_tick(['limit' => 1, 'pipeline' => $boom]);
$after2 = adapter_sub_find($boomTicket);
ok((string) $after2['status'] === 'failed', '达到重试上限后标记失败', (string) $after2['status']);
ok(str_contains((string) $after2['message'], '拉取失败'), '失败原因被保留给人看');

// 流水线抛异常不能把 worker 打挂
adapter_sub_act($boomTicket, 'requeue');
adapter_sub_config_set(['max_attempts' => 1]);
$thrown = adapter_sub_tick(['limit' => 1, 'pipeline' => static function (string $s, array $o): array {
    throw new RuntimeException('模拟崩溃');
}]);
ok($thrown['processed'] === 1, '流水线抛异常时 worker 仍完成本轮');
ok((string) adapter_sub_find($boomTicket)['status'] === 'failed', '异常被记成失败而不是卡在进行中');

// 人工动作
$rj = adapter_sub_act($boomTicket, 'reject', '上游不维护了');
ok(!empty($rj['ok']) && (string) adapter_sub_find($boomTicket)['status'] === 'rejected', '人工拒绝生效');
ok(adapter_sub_act('sub_nonexistent', 'requeue')['ok'] === false, '不存在的受理编号返回错误而不是静默成功');
ok(adapter_sub_act($boomTicket, 'nonsense')['ok'] === false, '未知动作被拒');

// 开关
adapter_sub_config_set(['enabled' => false]);
ok(adapter_submit(['source' => 'acme/when-closed'], $ENV)['ok'] === false, '关闭自助提交后不再受理');
adapter_sub_config_set(['enabled' => true]);

echo "\n流水线编排（离线）\n";

// ── 6. 编排：许可证 blocked 必须短路 ──
$fakeFetch = static function (string $license, string $readme = '') {
    return static function (string $slug) use ($license, $readme): array {
        return ['ok' => true, 'repo' => $slug, 'description' => 'A tool that sends notifications via HTTP API',
                'license' => $license, 'stars' => 1200, 'pushed_at' => '2026-09-01',
                'topics' => ['notifications'], 'homepage' => '', 'version' => 'v1.0.0',
                'readme' => $readme !== '' ? $readme : 'Publish messages with a simple HTTP POST to the official REST API. Webhook supported.'];
    };
};
$outDir = $tmp . '/drafts';
$noLicense = adapter_pipeline_run('acme/nolicense', ['out_dir' => $outDir, 'fetch' => $fakeFetch('')]);
ok($noLicense['status'] === 'blocked', '无许可证直接 blocked', $noLicense['status'] . '/' . $noLicense['error']);
ok($noLicense['stage'] === 'license', '在许可证阶段就短路（没有白跑合成）', $noLicense['stage']);
ok($noLicense['id'] === '' && $noLicense['dir'] === '', '短路时不落盘任何草稿');

// ── 7. 编排：正常来源能出草稿并得到闸门结论 ──
$good = adapter_pipeline_run('https://github.com/acme/ntfy-like',
                             ['out_dir' => $outDir, 'fetch' => $fakeFetch('apache-2.0'), 'skip_phpstan' => true]);
ok($good['slug'] === 'acme/ntfy-like', '来源被规范化');
ok($good['id'] !== '', '产出了插件 id', $good['id']);
ok(is_file($good['dir'] . '/plugin.json'), '落盘了 plugin.json');
ok(is_file($good['dir'] . '/plugin.php'), '落盘了 plugin.php');
ok(is_file($good['dir'] . '/verification-report.json'), '落盘了验证报告');
ok(in_array($good['status'], ['passed', 'needs-review', 'blocked'], true), '闸门给出了明确结论', $good['status']);
ok($good['generated_by'] !== '', '记录了生成方式（模板兜底 / AI）', $good['generated_by']);
ok($good['steps'] !== [], '编排留下了可读的步骤轨迹');

$manifest = json_decode((string) file_get_contents($good['dir'] . '/plugin.json'), true);
ok(is_array($manifest) && ($manifest['source']['repo'] ?? '') === 'acme/ntfy-like', 'manifest 记录了上游来源（可追溯）');
ok(is_array($manifest) && ($manifest['verification']['status'] ?? '') === $good['status'], '徽章来自闸门而不是 AI 自称');

// AI 不可用时必须能回落到模板，而不是整条挂掉
$aiDown = adapter_pipeline_run('acme/ai-down', ['out_dir' => $outDir, 'fetch' => $fakeFetch('mit'),
    'skip_phpstan' => true, 'ai' => static fn(string $s, string $u, array $o): array => ['ok' => false, 'error' => '额度用尽']]);
ok($aiDown['id'] !== '' && $aiDown['status'] !== '', 'AI 不可用时回落到模板，仍能产出草稿并过闸门', $aiDown['error']);

// 网络失败要报在 intake 阶段，且不产生草稿
$netDown = adapter_pipeline_run('acme/offline', ['out_dir' => $outDir,
    'fetch' => static fn(string $s): array => ['ok' => false, 'error' => 'HTTP 404']]);
ok($netDown['stage'] === 'intake' && $netDown['status'] === '', '拉取失败停在 intake 且没有闸门结论', $netDown['stage']);
ok(str_contains($netDown['error'], '404'), '拉取失败带上了原始错误');

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
@exec('rm -rf ' . escapeshellarg($tmp));
exit($fail === 0 ? 0 : 1);
