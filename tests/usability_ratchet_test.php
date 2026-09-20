<?php
declare(strict_types=1);
/**
 * 可用性棘轮（只许变好，不许变坏）
 *
 *   php tests/usability_ratchet_test.php
 *
 * 【为什么用棘轮而不是"检查"】可用性讨论容易停在感觉上；把可算的指标钉住，
 * 之后每次改动要么维持、要么改善，不允许悄悄退回去。
 *
 * 指标来自 scripts/usability-audit.php（同一份计算口径）：
 *   - 命令面板覆盖页数（越大越好）
 *   - 可疑孤岛页数（越小越好）
 *   - 空态只写「暂无」的页数（越小越好）
 *   - 无窄屏线索的页数（越小越好）
 */

$ROOT = dirname(__DIR__);
$json = shell_exec('php ' . escapeshellarg($ROOT . '/scripts/usability-audit.php') . ' --json 2>/dev/null');
$data = json_decode((string) $json, true);

$pass = 0; $fail = 0;
function ok(bool $c, string $n, string $d = ''): void
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "可用性棘轮\n";
if (!is_array($data) || !isset($data['entry'])) {
    ok(false, '可用性审计可运行', '脚本没产出 JSON（先跑 php scripts/usability-audit.php 看报错）');
    echo "\n合计：{$pass} 通过，{$fail} 失败\n";
    exit(1);
}

// 基线（2026-09-20 实测）；这些是"不许变坏"的下限/上限
// 口径：只统计"真实页面"（排除 301 别名与被 include 的片段），见 scripts/usability-audit.php
$base = [
    'palette_indexed' => 192,       // 下限：⌘K 覆盖全部真实页面（S1 前 41/222=18%，现在 192/192）
    'orphan_suspicious' => 0,       // 上限：可疑孤岛
    'empty_without_action' => 19,   // 上限：缺出路总量（S2 前 76；现在只剩 19 个行内说明）
    'mobile_without_hint' => 88,    // 上限：无窄屏线索
];

$e = $data['entry'];
$es = $data['empty_state'];
$m = $data['mobile_ready'];

ok((int) $e['palette_indexed'] >= $base['palette_indexed'],
    "命令面板覆盖 ≥ {$base['palette_indexed']} 页", "现在 {$e['palette_indexed']}（覆盖率下降）");
ok((int) $e['orphan_suspicious'] <= $base['orphan_suspicious'],
    "可疑孤岛 ≤ {$base['orphan_suspicious']} 个", "现在 {$e['orphan_suspicious']}");
ok((int) ($es['primary_without_action'] ?? 99) <= 0,
    '页面级空态缺出路 = 0（列表为空必须给出路）', '现在 ' . ($es['primary_without_action'] ?? '?'));
ok((int) $es['without_next_action'] <= $base['empty_without_action'],
    "空态缺出路总量 ≤ {$base['empty_without_action']}（只剩行内说明）", "现在 {$es['without_next_action']}");
ok((int) $m['without_hint'] <= $base['mobile_without_hint'],
    "无窄屏线索 ≤ {$base['mobile_without_hint']} 页", "现在 {$m['without_hint']}");

// 结构自洽性：页面总数与三类入口相加要能对上（防止审计口径被改坏）
$sum = (int) $e['with_entry'] + (int) $e['orphan'];
ok($sum === (int) $data['pages_total'], '入口统计自洽（有入口+孤岛=总页数）', "{$sum} vs {$data['pages_total']}");

echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
