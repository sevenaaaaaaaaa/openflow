<?php
/**
 * 转化漏斗巡检 — 落地页/渠道转化率环比告警
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/FunnelGuard.php';
require_login();
require_perm('analytics');

$message = '';
if (isset($_GET['scan'])) {
    $r = funnel_guard_scan();
    $message = '巡检完成：发现 ' . $r['alerts'] . ' 个告警';
}
$report = funnel_guard_report();
$insights = $report['insights'] ?? [];

admin_header('漏斗巡检');
?>
<div class="admin-layout">
  <?php admin_sidebar('funnel-guard'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>🚨 转化漏斗巡检</h1><p class="v-sub">近 7 天 vs 前 7 天 · 落地页 / 渠道转化率环比 · cron 每 6 小时自动</p></div>
      <div class="v-actions"><a href="?scan=1" class="btn btn-s btn-sm" onclick="return confirm('立即执行一次巡检?')">▶ 立即巡检</a></div>
    </div>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
      <div style="padding:16px;border-radius:12px;background:var(--bg)"><div class="text-2xl font-bold" style="color:<?=($report['alerts'] ?? 0) > 0 ? 'var(--danger)' : 'var(--ok)'?>"><?=$report['alerts'] ?? 0?></div><div class="text-sm text-muted">当前告警</div></div>
      <div style="padding:16px;border-radius:12px;background:var(--bg)"><div class="text-2xl font-bold"><?=count($insights)?></div><div class="text-sm text-muted">巡检项</div></div>
      <div style="padding:16px;border-radius:12px;background:var(--bg)"><div class="text-lg font-bold mono" style="font-size:15px"><?=htmlspecialchars($report['scanned_at'] ?? '—')?></div><div class="text-sm text-muted">最近扫描</div></div>
      <div style="padding:16px;border-radius:12px;background:var(--bg)"><div class="text-sm font-bold" style="font-size:13px"><?=htmlspecialchars($report['window'] ?? '—')?></div><div class="text-sm text-muted">对比窗口</div></div>
    </div>

    <div class="card" style="padding:0;overflow:auto">
      <?php if (empty($insights)): ?><div style="padding:30px;text-align:center;color:var(--faint)">暂无巡检数据。点击右上角「立即巡检」或等 cron 自动执行。</div>
      <?php else: ?>
      <table>
        <thead><tr><th>对象</th><th>类型</th><th>近7天转化率</th><th>前7天转化率</th><th>变化</th><th>建议</th></tr></thead>
        <tbody>
          <?php foreach ($insights as $i): ?>
          <tr>
            <td style="max-width:180px"><b><?=htmlspecialchars($i['label'])?></b></td>
            <td><span class="badge <?=$i['type']==='channel'?'badge-gray':'badge-green'?>"><?=$i['type']==='channel'?'渠道':'落地页'?></span></td>
            <td class="mono"><?=$i['cur_conv']?>% <span class="text-xs text-muted">(<?=$i['cur_views']?>次)</span></td>
            <td class="mono"><?=$i['prev_conv']?>%</td>
            <td>
              <?php if (($i['severity'] ?? '') === 'good'): ?><span style="color:var(--ok);font-weight:700">📈 提升</span>
              <?php else: ?><span style="color:var(--danger);font-weight:700">▼ <?=$i['drop_pct']?>%</span>
              <span class="badge <?=['high'=>'badge-red','medium'=>'badge-yellow','low'=>'badge-gray'][$i['severity']] ?? 'badge-gray'?>"><?=$i['severity']?></span><?php endif; ?>
            </td>
            <td class="text-sm text-muted" style="max-width:320px"><?=htmlspecialchars($i['suggestion'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
