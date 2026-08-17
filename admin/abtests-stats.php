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
    return [
        'A' => ['impression' => $aImp, 'conversion' => $aConv, 'rate' => $aImp > 0 ? round($aConv / $aImp * 100, 2) : 0, 'all' => $A],
        'B' => ['impression' => $bImp, 'conversion' => $bConv, 'rate' => $bImp > 0 ? round($bConv / $bImp * 100, 2) : 0, 'all' => $B],
    ];
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

    <!-- 结论 -->
    <div class="card" style="background:<?=$lift>=0?'linear-gradient(135deg,var(--surface),rgba(134,239,172,.12))':'linear-gradient(135deg,var(--surface),rgba(252,165,165,.12))'?>">
      <?php if ($result['A']['impression'] + $result['B']['impression'] === 0): ?>
      <p class="text-muted" style="margin-bottom:0">还没有统计数据。启用实验后，访问页面的用户会自动记录曝光；在页面关键转化处调用 <code>fcTrackAB('<?=htmlspecialchars($current['id'])?>', '<?=htmlspecialchars($current['traffic_b'] ?? 50) > 50 ? 'B' : 'A'?>', 'conversion')</code> 即可统计转化。</p>
      <?php else: ?>
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="font-size:44px"><?=$lift>=0?'📈':'📉'?></div>
        <div>
          <div style="font-size:20px;font-weight:700;color:<?=$lift>=0?'var(--ok)':'var(--danger)'?>"><?=($lift>=0?'+':'')?><?=$lift?>% 转化提升</div>
          <div class="text-sm text-muted" style="margin-top:4px">
            B 相对 A：转化率 <?=$aRate?>% → <?=$bRate?>%
            <?php if ($lift >= 5): ?>（B 显著更优，建议采用）<?php elseif ($lift <= -5): ?>（B 效果较差，建议保留 A）<?php else: ?>（差异不明显，建议继续观察）<?php endif; ?>
          </div>
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
