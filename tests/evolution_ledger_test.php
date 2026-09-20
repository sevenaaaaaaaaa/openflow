<?php
declare(strict_types=1);
/**
 * 自我进化台账 契约测试
 *   php tests/evolution_ledger_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-evo-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/EvolutionLedger.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}

echo "自我进化台账\n";

/* 指标定义与判级（方向敏感） */
check('指标定义齐全（10 项，含方向）', count(EvolutionLedger::metric_defs()) === 10 && EvolutionLedger::metric_defs()['tasks_overdue']['dir'] === 'down_good');
check('down_good：变小=改善', EvolutionLedger::judge('down_good', 10, 4) === 'improved');
check('down_good：变大=变差', EvolutionLedger::judge('down_good', 4, 10) === 'worse');
check('up_good：变大=改善', EvolutionLedger::judge('up_good', 3, 9) === 'improved');
check('持平判 flat', EvolutionLedger::judge('up_good', 5, 5) === 'flat');
check('微小波动判 flat（<5% 且 <1）', EvolutionLedger::judge('up_good', 100, 102) === 'flat');
check('大基数下 6% 也算改善', EvolutionLedger::judge('up_good', 100, 106) === 'improved');
check('零基数不炸（0 → 1 视为改善）', EvolutionLedger::judge('up_good', 0, 1) === 'improved');

/* 指标现算：真实数据源缺失时必须是 0/999，而不是报错 */
$m = EvolutionLedger::metrics();
check('指标能现算（无数据源也不报错）', is_array($m) && count($m) === 10, json_encode(array_keys($m)));
check('缺 cron 记录 → 停滞记为 999（而非 0）', (int) $m['cron_age_hours'] === 999, (string) $m['cron_age_hours']);
check('线索/订单缺失 → 0', (int) $m['leads_total'] === 0 && (int) $m['orders_paid'] === 0);

/* 造数：线索与已支付订单 */
json_write(DATA_DIR . '/leads.json', [['id' => 'l1'], ['id' => 'l2'], ['id' => 'l3']]);
json_write(DATA_DIR . '/shop/orders.json', [
    ['id' => 'o1', 'status' => 'paid', 'amount' => 100],
    ['id' => 'o2', 'status' => 'completed', 'total' => 250],
    ['id' => 'o3', 'status' => 'pending', 'amount' => 999],
]);
$m2 = EvolutionLedger::metrics();
check('线索数现算', (int) $m2['leads_total'] === 3, (string) $m2['leads_total']);
check('已支付订单只算 paid/completed', (int) $m2['orders_paid'] === 2, (string) $m2['orders_paid']);
check('金额只累计已支付', (int) $m2['revenue'] === 350, (string) $m2['revenue']);

/* 体检建议未解决数 */
json_write(DATA_DIR . '/evolution.json', ['suggestions' => [
    ['id' => 's1', 'status' => 'open'], ['id' => 's2', 'status' => 'resolved'], ['id' => 's3'],
]]);
check('未解决建议计数（缺 status 视为未解决）', (int) EvolutionLedger::metrics()['suggestions_open'] === 2, (string) EvolutionLedger::metrics()['suggestions_open']);

/* 快照：同日去重 + 保留策略 */
$snap1 = EvolutionLedger::snapshot();
check('快照落盘', (string) ($snap1['date'] ?? '') === date('Y-m-d'));
$again = EvolutionLedger::snapshot();
check('一小时内不重复写快照', (int) ($again['at'] ?? 0) === (int) ($snap1['at'] ?? -1));
check('snapshots() 可读', count(EvolutionLedger::snapshots()) === 1);

/* 无对比快照 → unknown（绝不假装改善） */
check('缺对比快照时趋势为 unknown', EvolutionLedger::trend('leads_total', 7)['verdict'] === 'unknown');

/* 伪造一条 10 天前的快照，验证趋势对比 */
$st = json_read(DATA_DIR . '/evolution-ledger.json');
$st['snapshots'][] = ['at' => time() - 10 * 86400, 'date' => date('Y-m-d', time() - 10 * 86400), 'metrics' => ['leads_total' => 1, 'revenue' => 100]];
json_write(DATA_DIR . '/evolution-ledger.json', $st);
$t = EvolutionLedger::trend('leads_total', 7);
check('有对比快照时给出方向判定', $t['verdict'] === 'improved' && (float) $t['delta'] === 2.0, json_encode($t));
$t2 = EvolutionLedger::trend('orders_paid', 7);
check('缺该指标的历史值时按 0 起算（不报错）', $t2['verdict'] === 'improved', json_encode($t2));

/* 动作记录 */
check('记录动作（human）', EvolutionLedger::record_action('s1', ['kind' => 'human', 'summary' => '补了 404 页面', 'by' => 'Seven']));
check('记录动作（needs_review）', EvolutionLedger::record_action('s3', ['kind' => 'needs_review', 'summary' => '需人审：涉及删除数据', 'guard' => 'blocked']));
check('非法 kind 回落 human 而不是丢弃', (static function (): bool {
    EvolutionLedger::record_action('s2', ['kind' => 'weird', 'summary' => 'x']);
    return (string) (EvolutionLedger::entry('s2')['action']['kind'] ?? '') === 'human';
})());
check('空 id 拒绝', EvolutionLedger::record_action('', ['summary' => 'x']) === false);
check('动作可读回', (string) (EvolutionLedger::entry('s1')['action']['summary'] ?? '') === '补了 404 页面');

/* 度量与结算 */
check('非法指标拒绝', EvolutionLedger::record_measure('s1', 'nope', 7) === false);

/* 到期结算：先挂度量（before=当前值），再改变数据，到期后应判 improved */
EvolutionLedger::record_measure('s1', 'leads_total', 7);
json_write(DATA_DIR . '/leads.json', [['id'=>'l1'],['id'=>'l2'],['id'=>'l3'],['id'=>'l4'],['id'=>'l5'],['id'=>'l6']]);
$r = EvolutionLedger::settle(date('Y-m-d', time() + 8 * 86400));
check('到期结算出 1 条', count($r) === 1 && (string) $r[0]['verdict'] === 'improved', json_encode($r));
check('结算写入 before/after', (float) (EvolutionLedger::entry('s1')['measure']['before'] ?? -1) === 3.0 && (float) (EvolutionLedger::entry('s1')['measure']['after'] ?? -1) === 6.0, json_encode(EvolutionLedger::entry('s1')['measure']));
check('结算幂等（再跑不重复）', EvolutionLedger::settle(date('Y-m-d', time() + 20 * 86400)) === []);

/* 变差的场景要如实判 worse */
EvolutionLedger::record_measure('s3', 'suggestions_open', 1);
json_write(DATA_DIR . '/evolution.json', ['suggestions' => [
    ['id'=>'s1','status'=>'open'],['id'=>'s2','status'=>'open'],['id'=>'s3','status'=>'open'],
    ['id'=>'s4','status'=>'open'],['id'=>'s5','status'=>'open'],
]]);
$r2 = EvolutionLedger::settle(date('Y-m-d', time() + 2 * 86400));
$v3 = (string) ($r2[0]['verdict'] ?? '');
check('未解决建议变多 → worse（如实）', $v3 === 'worse', json_encode($r2));

/* 统计 */
$st2 = EvolutionLedger::stats();
check('统计含各判级与缺度量计数', ($st2['improved'] ?? 0) === 1 && ($st2['worse'] ?? 0) === 1 && ($st2['no_measure'] ?? 0) >= 1, json_encode($st2));

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
