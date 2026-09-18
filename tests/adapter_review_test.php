<?php
declare(strict_types=1);
/**
 * 适配人审队列 契约测试
 *   php tests/adapter_review_test.php
 */

require_once __DIR__ . '/../lib/AdapterIntake.php';
require_once __DIR__ . '/../lib/AdapterReview.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

$tmp = sys_get_temp_dir() . '/of-review-' . getmypid();
@exec('rm -rf ' . escapeshellarg($tmp));
$drafts = $tmp . '/drafts'; $plugins = $tmp . '/plugins'; $data = $tmp . '/data';
@mkdir($drafts . '/ok-adapter', 0777, true);
@mkdir($drafts . '/bad-adapter', 0777, true);

echo "适配人审队列\n";

/* 1. 扩权建议解析 */
$code = "<?php\n// @adapter-suggest: schedule 需要每 10 分钟轮询新条目\n// @adapter-suggest: block 想在文章页嵌一个榜单\n";
$sug = adapter_review_suggestions($code);
check('解析扩权建议 2 条', count($sug) === 2, json_encode($sug));
check('建议含 surface 与理由', ($sug[0]['surface'] ?? '') === 'schedule' && str_contains((string) ($sug[0]['reason'] ?? ''), '轮询'));
check('未知 surface 被标记', adapter_review_suggestions('// @adapter-suggest: quantum 未来落点')[0]['known'] === false);

/* 2. 造两条草稿（passed / blocked） */
$mk = static function (string $dir, string $id, string $status, string $license, bool $withTodo = true): void {
    @mkdir($dir . '/tests', 0777, true);
    file_put_contents($dir . '/plugin.json', json_encode([
        'id' => $id, 'name' => $id, 'version' => '0.1.0', 'description' => 'd', 'author' => 'a',
        'enabled_by_default' => false, 'permissions' => ['api', 'config', 'log', 'http'],
        'source' => ['kind' => 'github', 'repo' => 'o/r', 'version' => 'v1.0.0', 'license' => $license],
        'surfaces' => ['api_route' => ['api']], 'capabilities' => ['network' => [], 'secrets' => [], 'data' => []],
        'compat' => ['openflow' => '>=2.1 <3'],
        'verification' => ['status' => $status, 'badge' => $status === 'passed' ? 'verified' : 'unverified', 'tests' => '', 'checked_at' => ''],
    ], JSON_UNESCAPED_UNICODE));
    file_put_contents($dir . '/plugin.php', $withTodo ? "<?php\n// TODO(适配): 调真实接口\n" : "<?php\n// 已补完\n");
    file_put_contents($dir . '/plugin.ai.php', "<?php\n// artifact\n");
    file_put_contents($dir . '/plugin.php.prev.bak', "<?php\n// backup\n");
    file_put_contents($dir . '/verification-report.json', json_encode(['status' => $status, 'badge' => 'unverified', 'failed' => $status === 'blocked' ? ['source:license'] : [], 'checks' => [], 'checked_at' => '']));
};
$mk($drafts . '/ok-adapter', 'ok-adapter', 'passed', 'mit');              // 骨架（有 TODO）→ 会被降级
$mk($drafts . '/done-adapter', 'done-adapter', 'passed', 'mit', false);   // 已补完 → 保持 passed
$mk($drafts . '/bad-adapter', 'bad-adapter', 'blocked', 'noassertion');

$scan = adapter_review_scan($drafts);
check('扫描到 3 条', count($scan) === 3, (string) count($scan));
$byId = [];
foreach ($scan as $e) $byId[$e['id']] = $e;
$ok = $byId['ok-adapter'] ?? [];
$done = $byId['done-adapter'] ?? [];
$bad = $byId['bad-adapter'] ?? [];
check('骨架（有 TODO）被降级为 needs-review', ($ok['status'] ?? '') === 'needs-review', (string) ($ok['status'] ?? ''));
check('已补完保持 passed', ($done['status'] ?? '') === 'passed', (string) ($done['status'] ?? ''));
check('降级条目带说明', str_contains((string) ($ok['status_note'] ?? ''), '骨架未补完'));
check('排序：待审在前、拦截在后', ($scan[0]['status'] ?? '') === 'needs-review' && ($scan[2]['status'] ?? '') === 'blocked');
check('条目含 TODO 计数', ($ok['todos'] ?? 0) === 1);
check('条目含失败项', in_array('source:license', (array) $bad['failed'], true));

/* 3. 上架规则 */
check('已补完（passed）可上架', adapter_review_publishable($done)['ok'] === true);
check('骨架降级后不可直接上架', adapter_review_publishable($ok)['ok'] === false);
$approved = $ok; $approved['decision'] = 'approved';
check('骨架经人审批准 → 可上架且提醒 TODO', adapter_review_publishable($approved)['ok'] === true
    && str_contains((string) (adapter_review_publishable($approved)['warn'] ?? ''), 'TODO'));
check('blocked 不可上架', adapter_review_publishable($bad)['ok'] === false);
$rj = $done; $rj['decision'] = 'rejected';
check('已拒绝 → 不可上架', adapter_review_publishable($rj)['ok'] === false);

/* 4. 队列读写 + 嵌套自愈 */
$qf = $data . '/ecosystem/review-queue.json';
check('写入队列', adapter_review_queue_write($scan, $qf) === true);
check('读回条目数一致', count((array) (adapter_review_queue_read($qf)['items'] ?? [])) === 3);
file_put_contents($qf, json_encode(['generated_at' => 'x', 'count' => 1, 'items' => [[['id' => 'legacy', 'status' => 'passed']]]]));
check('历史嵌套结构自愈为列表', (adapter_review_queue_read($qf)['items'][0]['id'] ?? '') === 'legacy');
adapter_review_queue_write($scan, $qf);

/* 5. 人审决定 */
$d = adapter_review_decide('ok-adapter', 'approved', '法务已确认', $qf);
check('决定写入并返回条目', ($d['decision'] ?? '') === 'approved' && ($d['note'] ?? '') === '法务已确认');
check('决定后队列已更新', in_array('approved', array_map(static fn($x)=>$x['decision'] ?? '', (array) adapter_review_queue_read($qf)['items']), true));
check('未知 id 返回空', adapter_review_decide('nope', 'approve', '', $qf) === []);

/* 6. 上架（复制 + 注册表） */
$entry = adapter_review_entry($drafts . '/done-adapter');
$entry['decision'] = 'approved';
$reg = $data . '/plugins.json';
$pub = adapter_review_publish($entry, $plugins, $reg);
check('上架成功', ($pub['ok'] ?? false) === true, (string) ($pub['error'] ?? ''));
check('产物不含审阅工件', !is_file($plugins . '/done-adapter/plugin.ai.php') && !is_file($plugins . '/done-adapter/plugin.php.prev.bak'));
check('产物含 plugin.php 与验证报告', is_file($plugins . '/done-adapter/plugin.php') && is_file($plugins . '/done-adapter/verification-report.json'));
$registry = json_decode((string) file_get_contents($reg), true);
check('注册表写入 installed', isset($registry['installed']['done-adapter']));
check('注册表默认不启用', ($registry['enabled']['done-adapter'] ?? true) === false);
check('注册表记录来源与徽章', ($registry['installed']['done-adapter']['source'] ?? '') === 'o/r');
$dup = adapter_review_publish($entry, $plugins, $reg);
check('重复上架被拒', ($dup['ok'] ?? true) === false && str_contains((string) $dup['error'], '已存在'));
$badEntry = adapter_review_entry($drafts . '/bad-adapter');
$pubBad = adapter_review_publish($badEntry, $plugins, $reg);
check('blocked 草稿不可上架', ($pubBad['ok'] ?? true) === false && !is_dir($plugins . '/bad-adapter'));

@exec('rm -rf ' . escapeshellarg($tmp));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
