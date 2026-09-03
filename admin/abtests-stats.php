<?php
/**
 * A/B 测试统计报告 — A/B 变体效果对比
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$tests = json_read(DATA_DIR . '/abtests.json');
$stats = json_read(DATA_DIR . '/abstats.json');

$currentId = $_GET['id'] ?? ($tests[0]['id'] ?? '');
$current = null;
foreach ($tests as $t) if ($t['id'] === $currentId) { $current = $t; break; }

if (!$current) {
    http_response_code(404);
    ?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>未找到实验</title></head>
    <body style="font-family:sans-serif;padding:40px;text-align:center">实验不存在<br><a href="abtests.php" style="color:var(--accent)">返回 A/B 测试</a></body></html><?php
    exit;
}

// 汇总统计
function ab_compute(array $stats, string $abId): array {
    $A = $stats[$abId]['A'] ?? [];
    $B = $stats[$abId]['B'] ?? [];
    $sum = function ($group, $event) {
        $total = 0;
        foreach (($group[$event] ?? []) as $n) $total += $n;
        return $total;
    };
    $aImp = $sum($A, 'impression');
    $bImp = $sum($B, 'impression');
    $aConv = $sum($A, 'conversion');
    $bConv = $sum($B, 'conversion');
    $aRate = $aImp > 0 ? $aConv / $aImp : 0;
    $bRate = $bImp > 0 ? $bConv / $bImp : 0;
    // 双样本比例 Z 检验
    $z = null; $p = null; $ciLow = null; $ciHigh = null; $minSample = null;
    if ($aImp > 0 && $bImp > 0) {
        $pooled = ($aConv + $bConv) / ($aImp + $bImp);
        $se = sqrt($pooled * (1 - $pooled) * (1 / $aImp + 1 / $bImp));
        $z = $se > 0 ? ($bRate - $aRate) / $se : 0;
        // p 值（双尾）：正态近似 Φ
        $p = 2 * (1 - normalCdf(abs($z)));
        // 差值 Wald 置信区间
        $diff = $bRate - $aRate;
        $seDiff = sqrt($bRate * (1 - $bRate) / $bImp + $aRate * (1 - $aRate) / $aImp);
        $ciLow = $diff - 1.96 * $seDiff;
        $ciHigh = $diff + 1.96 * $seDiff;
        // 最小样本量估算（power=0.8, alpha=0.05, 比例检验）
        if ($aRate > 0 && $aRate < 1 && $bRate > 0 && $bRate < 1) {
            $effect = abs($bRate - $aRate);
            $pAvg = ($aRate + $bRate) / 2;
            $minSample = $effect > 0 ? (int)ceil((1.96 + 0.84) ** 2 * 2 * $pAvg * (1 - $pAvg) / ($effect ** 2)) : null;
        }
    }
    return [
        'A' => ['impression' => $aImp, 'conversion' => $aConv, 'rate' => round($aRate * 100, 2), 'all' => $A],
        'B' => ['impression' => $bImp, 'conversion' => $bConv, 'rate' => round($bRate * 100, 2), 'all' => $B],
        'z' => $z === null ? null : round($z, 3),
        'p' => $p === null ? null : round($p, 4),
        'ci' => $ciLow === null ? null : [round($ciLow * 100, 2), round($ciHigh * 100, 2)],
        'min_sample' => $minSample,
        'significant' => $p !== null && $p < 0.05,
    ];
}
// 标准正态分布 CDF（近似）
function normalCdf(float $x): float {
    $t = 1 / (1 + 0.2316419 * abs($x));
    $d = 0.3989422804014327 * exp(-$x * $x / 2);
    $p = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));
    if ($x > 0) return 1 - $p;
    return $p;
}
$result = ab_compute($stats, $current['id']);

// 提升率
$aRate = $result['A']['rate'];
$bRate = $result['B']['rate'];
$lift = $aRate > 0 ? round(($bRate - $aRate) / $aRate * 100, 1) : ($bRate > 0 ? 100 : 0);

admin_header('A/B 测试统计');
?>
<style>
.metric{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;text-align:center}
.metric .val{font-size:30px;font-weight:800;margin-top:6px}
.metric .lab{font-size:12px;color:var(--text-3)}
.variant-tag{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;color:#fff}
</style>
<div class="admin-layout">
  <?php admin_sidebar('abtests'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4" style="align-items:center">
      <h1 style="margin-bottom:0"> A/B 测试统计</h1>
      <a href="abtests.php" class="btn btn-ghost btn-sm ml-auto">← 返回列表</a>
    </div>

    <!-- 实验切换 -->
    <div class="flex gap-2 mb-4" style="flex-wrap:wrap">
      <?php foreach ($tests as $t): ?>
      <a href="abtests-stats.php?id=<?=urlencode($t['id'])?>" class="btn btn-sm <?=$t['id']===$currentId?'btn-primary':'btn-ghost'?>"><?=htmlspecialchars($t['name'])?></a>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <h2 style="margin-bottom:0"><?=htmlspecialchars($current['name'])?></h2>
        <span class="variant-tag" style="background:var(--ok)"><?=htmlspecialchars($current['variant_a_label'] ?? '方案 A')?></span>
        <span class="variant-tag" style="background:var(--accent)"><?=htmlspecialchars($current['variant_b_label'] ?? '方案 B')?></span>
        <span class="text-sm text-muted">B 流量 <?=htmlspecialchars($current['traffic_b'] ?? 50)?>%</span>
        <?php if (($current['enabled'] ?? false)): ?><span class="badge badge-green">🟢 进行中</span><?php else: ?><span class="badge badge-gray">⏸ 已暂停</span><?php endif; ?>
      </div>
    </div>

    <!-- 指标对比 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px">
      <div class="metric"><div class="lab">A 曝光</div><div class="val" style="color:var(--ok)"><?=$result['A']['impression']?></div></div>
      <div class="metric"><div class="lab">A 转化</div><div class="val" style="color:var(--ok)"><?=$result['A']['conversion']?></div><div class="text-sm text-muted">转化率 <?=$result['A']['rate']?>%</div></div>
      <div class="metric"><div class="lab">B 曝光</div><div class="val" style="color:var(--accent)"><?=$result['B']['impression']?></div></div>
      <div class="metric"><div class="lab">B 转化</div><div class="val" style="color:var(--accent)"><?=$result['B']['conversion']?></div><div class="text-sm text-muted">转化率 <?=$result['B']['rate']?>%</div></div>
    </div>

    <!-- 统计显著性（Z 检验） -->
    <?php if ($result['p'] !== null): ?>
    <div class="card" style="margin-bottom:20px">
      <h2 style="margin-bottom:12px">📊 统计显著性</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
        <div class="metric"><div class="lab">Z 值</div><div class="val" style="font-size:22px"><?=$result['z']?></div></div>
        <div class="metric"><div class="lab">p 值</div><div class="val" style="font-size:22px;color:<?=$result['significant']?'var(--ok)':'var(--warn)'?>"><?=$result['p']?></div><div class="text-xs text-muted"><?=$result['significant']?'< 0.05 显著':'≥ 0.05 不显著'?></div></div>
        <div class="metric"><div class="lab">差值 95% 置信区间</div><div class="val" style="font-size:15px"><?=$result['ci'][0]?>% ~ <?=$result['ci'][1]?>%</div><div class="text-xs text-muted"><?=$result['ci'][0] > 0 ? '区间不含 0，B 显著更好' : ($result['ci'][1] < 0 ? '区间不含 0，A 显著更好' : '区间含 0，差异不显著')?></div></div>
        <?php if ($result['min_sample']): ?><div class="metric"><div class="lab">建议每组样本量</div><div class="val" style="font-size:15px"><?=$result['min_sample']?></div><div class="text-xs text-muted">当前 A=<?=$result['A']['impression']?> / B=<?=$result['B']['impression']?></div></div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- 结论 -->
    <?php
    // 结论的呈现必须跟着显著性走：样本不足时，把「+X% 提升」当结论展示，
    // 等于在教用户把噪声当信号。未显著一律中性呈现，并明确还差多少样本。
    $sig      = $result['significant'] === true;
    $hasData  = $result['A']['impression'] + $result['B']['impression'] > 0;
    $totalImp = $result['A']['impression'] + $result['B']['impression'];
    $needMore = $result['min_sample'] !== null ? max(0, $result['min_sample'] * 2 - $totalImp) : null;
    $tone     = !$sig ? 'neutral' : ($lift >= 0 ? 'good' : 'bad');
    $bg = ['neutral' => 'var(--surface)',
           'good' => 'linear-gradient(135deg,var(--surface),rgba(134,239,172,.12))',
           'bad'  => 'linear-gradient(135deg,var(--surface),rgba(252,165,165,.12))'][$tone];
    ?>
    <div class="card" style="background:<?=$bg?>">
      <?php if (!$hasData): ?>
      <p class="text-muted" style="margin-bottom:0">还没有统计数据。启用实验后，访问页面的用户会自动记录曝光；在页面关键转化处调用 <code>fcTrackAB('<?=htmlspecialchars($current['id'])?>', '<?=htmlspecialchars($current['traffic_b'] ?? 50) > 50 ? 'B' : 'A'?>', 'conversion')</code> 即可统计转化。</p>
      <?php else: ?>
      <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div>
          <?php if ($sig): ?>
            <div style="font-size:20px;font-weight:700;color:<?=$lift>=0?'var(--ok)':'var(--danger)'?>">
              <?=$lift >= 0 ? 'B 显著更优' : 'A 显著更优'?> · <?=($lift>=0?'+':'')?><?=$lift?>%
            </div>
            <div class="text-sm text-muted" style="margin-top:4px">
              转化率 <?=$aRate?>% → <?=$bRate?>%，p=<?=$result['p']?> 达到显著（&lt;0.05）。<?=$lift >= 0 ? '建议采用 B。' : '建议保留 A。'?>
            </div>
          <?php else: ?>
            <div style="font-size:20px;font-weight:700;color:var(--text-2)">还不能下结论</div>
            <div class="text-sm text-muted" style="margin-top:4px">
              当前差异 <?=($lift>=0?'+':'')?><?=$lift?>%（<?=$aRate?>% → <?=$bRate?>%）
              <?php if ($result['p'] !== null): ?>
                ，p=<?=$result['p']?> 未达显著，这个差异<b>还不能和随机波动区分开</b>。
              <?php else: ?>
                ，样本太少，无法计算显著性。
              <?php endif; ?>
              <?php if ($needMore !== null && $needMore > 0): ?>
                按当前效应量估算，两组合计还需约 <b><?=number_format($needMore)?></b> 次曝光才有把握。
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- 事件明细 -->
    <div class="card" style="padding:0;overflow-x:auto">
      <h2 style="padding:20px 20px 0">事件明细</h2>
      <table>
        <thead><tr><th>事件</th><th>变体 A</th><th>变体 B</th></tr></thead>
        <tbody>
          <?php
          $allEvents = [];
          foreach (['A','B'] as $v) foreach (($stats[$current['id']][$v] ?? []) as $ev => $labels) { if (!in_array($ev, $allEvents)) $allEvents[] = $ev; }
          if (empty($allEvents)): ?>
          <tr><td colspan="3" class="empty">暂无事件数据</td></tr>
          <?php else: ?>
          <?php foreach ($allEvents as $ev):
            $aSum = array_sum($result['A']['all'][$ev] ?? []);
            $bSum = array_sum($result['B']['all'][$ev] ?? []);
          ?>
          <tr>
            <td><strong><?=htmlspecialchars($ev)?></strong></td>
            <td><?=$aSum?></td>
            <td><?=$bSum?></td>
          </tr>
          <?php if (!empty($result['A']['all'][$ev]) || !empty($result['B']['all'][$ev])):
            $labels = array_unique(array_merge(array_keys($result['A']['all'][$ev] ?? []), array_keys($result['B']['all'][$ev] ?? [])));
            foreach ($labels as $lb): if ($lb === '') continue; ?>
          <tr style="color:var(--text-3)">
            <td class="text-sm" style="padding-left:30px">└ <?=htmlspecialchars($lb)?></td>
            <td class="text-sm"><?=$result['A']['all'][$ev][$lb] ?? 0?></td>
            <td class="text-sm"><?=$result['B']['all'][$ev][$lb] ?? 0?></td>
          </tr>
          <?php endforeach; endif; ?>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
