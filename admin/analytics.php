<?php
/**
 * 运营分析 — 转化漏斗 / RFM 分层 / 流失预警
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/AnalyticsSystem.php';
require_login();
require_perm('settings');

$funnel = analytics_funnel();
$rfm = analytics_rfm();
$atRisk = analytics_at_risk();

// 发送挽回邮件
$winbackMsg = '';
if (isset($_POST['winback'])) {
    $res = analytics_send_winback($_POST['winback']);
    $winbackMsg = $res['ok'] ? '✅ 挽回邮件已发送' : '❌ ' . ($res['error'] ?? '发送失败');
}

$segmentLabels = ['high_value'=>'高价值','potential'=>'潜力','new'=>'新客','at_risk'=>'流失风险','churned'=>'沉睡'];
$segmentColors = ['high_value'=>'var(--ok)','potential'=>'var(--accent)','new'=>'var(--accent)','at_risk'=>'var(--warn)','churned'=>'var(--faint)'];

admin_header('运营分析');
?>
<style>
.funnel-bar{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.funnel-step{min-width:90px;font-size:13px;font-weight:600}
.funnel-bar .bar{height:34px;border-radius:8px;background:linear-gradient(90deg,#7dd3fc,#38bdf8);display:flex;align-items:center;padding:0 12px;font-size:13px;font-weight:700;color:#1e1e1e;white-space:nowrap;transition:width .4s}
.seg-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;text-align:center}
.seg-card .num{font-size:26px;font-weight:800;margin-top:4px}
.rfm-table td,.rfm-table th{font-size:13px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('analytics'); ?>
  <div class="main">
    <h1>运营分析</h1>
    <p class="sub">转化漏斗 · RFM 用户分层 · 流失预警与挽回</p>
    <?php if ($winbackMsg): ?><?=msg('success', $winbackMsg)?><?php endif; ?>

    <!-- 转化漏斗 -->
    <div class="card">
      <h2>🔄 转化漏斗 <span class="text-sm text-muted" style="font-weight:400">· 近 30 天</span></h2>
      <?php $maxStep = max(array_column($funnel, 'count')) ?: 1; ?>
      <?php foreach ($funnel as $i => $s): $width = max(8, round($s['count']/$maxStep*100)); ?>
      <div class="funnel-bar">
        <div class="funnel-step"><?=$s['icon']?> <?=htmlspecialchars($s['name'])?></div>
        <div class="bar" style="width:<?=$width?>%"><?=$s['count']?></div>
        <span style="font-size:12px;color:var(--text-3);width:110px"><?=$s['rate']?>%<?=$i>0?' · 流失 '.$s['drop']:''?></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($funnel[0]['count'])): ?><p class="text-sm text-muted">暂无数据，产生行为事件后自动统计</p><?php endif; ?>
    </div>

    <!-- RFM 分层 -->
    <div class="card">
      <h2>🎯 RFM 用户分层</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px">
        <?php foreach ($segmentLabels as $key => $label): ?>
        <div class="seg-card">
          <div style="font-size:12px;color:var(--text-3)"><?=$label?></div>
          <div class="num" style="color:<?=$segmentColors[$key]?>"><?=$rfm[$key] ?? 0?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="text-sm text-muted mb-4">分层逻辑：累计消费≥¥500且≥2次=高价值 · 有消费=潜力 · 最近30天无互动=沉睡 · 14-30天无互动=流失风险</p>
      <?php if (empty($rfm['members'])): ?>
      <div class="empty" style="padding:24px">暂无会员数据</div>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table class="rfm-table">
          <thead><tr><th>会员</th><th>分层</th><th>最近互动(天)</th><th>购买次数</th><th>累计金额</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($rfm['members'], 0, 30) as $m): ?>
            <tr>
              <td><strong><?=htmlspecialchars($m['name'] ?: $m['email'])?></strong></td>
              <td><span class="badge" style="background:<?=$segmentColors[$m['segment']]?>;color:#fff;font-size:11px"><?=$segmentLabels[$m['segment']]??$m['segment']?></span></td>
              <td class="text-sm text-muted"><?=$m['r']?></td>
              <td><?=$m['f']?></td>
              <td><strong>¥<?=number_format($m['m'],2)?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- 流失预警 -->
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">🚨 流失预警（<?=count($atRisk)?> 位可挽回用户）</h2>
      <p class="text-sm text-muted" style="padding:0 20px 12px">7-30 天未互动但有消费记录的会员 · 一键发送挽回邮件</p>
      <table>
        <thead><tr><th>会员</th><th>邮箱</th><th>未互动(天)</th><th>消费</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($atRisk)): ?><tr><td colspan="5" class="empty">当前没有需要挽回的用户 🎉</td></tr><?php endif; ?>
          <?php foreach ($atRisk as $m): ?>
          <tr>
            <td><strong><?=htmlspecialchars($m['name'] ?: '—')?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($m['email'])?></td>
            <td><span class="badge <?=$m['r']>=21?'badge-red':'badge-yellow'?>" style="font-size:11px"><?=$m['r']?> 天</span></td>
            <td><strong>¥<?=number_format($m['m'],2)?></strong></td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
