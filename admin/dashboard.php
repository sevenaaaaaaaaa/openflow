<?php
/**
 * 全站经营驾驶舱 — 大屏概览
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/DashboardSystem.php';
require_login();
require_perm('settings');

$kpis = dash_kpis();
$trend = dash_trend();
$channels = dash_channel_attribution();
$report = dash_revenue_report();
$nps = dash_nps();

admin_header('经营驾驶舱');
?>
<style>
.kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px;position:relative;overflow:hidden}
.kpi-card .ic{position:absolute;right:14px;top:14px;font-size:24px;opacity:.5}
.kpi-card .val{font-size:30px;font-weight:800;margin-top:6px}
.kpi-card .lab{font-size:12px;color:var(--text-3)}
.kpi-card .sub{font-size:11px;color:var(--text-3);margin-top:2px}
.dash-trend{display:flex;align-items:flex-end;gap:4px;height:140px}
.dash-trend .col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.dash-trend .bar{width:100%;background:linear-gradient(180deg,#86efac,#ddff0e);border-radius:4px 4px 0 0;min-height:3px}
.channel-row{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.channel-row .bar{flex:1;height:22px;background:var(--surface-2);border-radius:6px;overflow:hidden}
.channel-row .bar i{display:block;height:100%;background:linear-gradient(90deg,#7dd3fc,#86efac);border-radius:6px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('dashboard'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0">🚀 经营驾驶舱</h1>
      <span class="text-sm text-muted ml-auto"><?=date('Y-m-d H:i')?> · 数据实时</span>
    </div>
    <p class="sub">全站访问 · 线索 · 订单 · 订阅 · 收入 · NPS 一览</p>

    <!-- KPI 卡 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px">
      <div class="kpi-card"><span class="ic">👀</span><div class="lab">近30天访客</div><div class="val"><?=number_format($kpis['uv'])?></div><div class="sub">PV <?=number_format($kpis['pv'])?> · 今日 <?=$kpis['today_uv']?></div></div>
      <div class="kpi-card"><span class="ic">📥</span><div class="lab">累计线索</div><div class="val"><?=$kpis['leads']?></div></div>
      <div class="kpi-card"><span class="ic">🛒</span><div class="lab">订单</div><div class="val"><?=$kpis['orders']?></div><div class="sub">已支付 <?=$kpis['paid_orders']?></div></div>
      <div class="kpi-card"><span class="ic">💰</span><div class="lab">累计收入</div><div class="val" style="color:#16a34a">¥<?=number_format($kpis['revenue'],0)?></div><div class="sub">近30天 ¥<?=number_format($kpis['revenue_30d'],0)?></div></div>
      <div class="kpi-card"><span class="ic">👥</span><div class="lab">会员数</div><div class="val"><?=$kpis['members']?></div></div>
      <div class="kpi-card"><span class="ic">⭐</span><div class="lab">活跃订阅</div><div class="val"><?=$kpis['active_subscribers']?></div></div>
      <div class="kpi-card"><span class="ic">📈</span><div class="lab">NPS</div><div class="val" style="color:<?=($nps['avg_nps']??0)>=0?'#16a34a':'#dc2626'?>"><?=$nps['avg_nps'] ?? '—'?></div><div class="sub"><?=$nps['total_responses']?> 份回收</div></div>
      <div class="kpi-card"><span class="ic">🤝</span><div class="lab">分销佣金</div><div class="val">¥<?=number_format($kpis['commission_paid'],0)?></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px;margin-bottom:20px" class="dash-grid">
      <!-- 访问趋势 -->
      <div class="card">
        <h2>📈 访问趋势（近 14 天 UV）</h2>
        <div class="dash-trend">
          <?php $maxT = max($trend) ?: 1; foreach ($trend as $d => $uv): ?>
          <div class="col">
            <span style="font-size:10px;color:var(--text-3)"><?=$uv?></span>
            <div class="bar" style="height:<?=$uv>0?max(8,round($uv/$maxT*130)):3?>px"></div>
            <span style="font-size:9px;color:var(--text-3)"><?=substr($d,5)?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 渠道归因 -->
      <div class="card">
        <h2>🎯 渠道归因 <span class="text-sm text-muted" style="font-weight:400">· 按支付订单</span></h2>
        <?php if (empty($channels)): ?><div class="empty" style="padding:24px">暂无支付订单，产生订单后自动归因</div>
        <?php else: $maxCh = max(array_column($channels,'orders')) ?: 1; ?>
        <?php foreach ($channels as $src => $c): ?>
        <div class="channel-row">
          <span style="font-size:12px;width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($src)?></span>
          <div class="bar"><i style="width:<?=round($c['orders']/$maxCh*100)?>%"></i></div>
          <span style="font-size:11px;width:70px;text-align:right"><?=$c['orders']?>单 ¥<?=$c['revenue']?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="dash-grid">
      <!-- 收入报表 -->
      <div class="card" style="padding:0;overflow:auto">
        <h2 style="padding:20px 20px 0">💰 收入报表（按月）</h2>
        <table>
          <thead><tr><th>月份</th><th>订单数</th><th>收入</th><th>分销佣金</th></tr></thead>
          <tbody>
            <?php if (empty($report['monthly'])): ?><tr><td colspan="4" class="empty">暂无收入数据</td></tr><?php endif; ?>
            <?php foreach ($report['monthly'] as $m => $r): ?>
            <tr>
              <td><strong><?=htmlspecialchars($m)?></strong></td>
              <td><?=$r['orders']?></td>
              <td style="color:#16a34a;font-weight:600">¥<?=number_format($r['revenue'],0)?></td>
              <td>¥<?=number_format($r['commission'],0)?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 分销佣金榜 -->
      <div class="card" style="padding:0;overflow:auto">
        <h2 style="padding:20px 20px 0">🤝 分销佣金榜</h2>
        <table>
          <thead><tr><th>大使</th><th>佣金</th></tr></thead>
          <tbody>
            <?php if (empty($report['commission'])): ?><tr><td colspan="2" class="empty">暂无分销佣金</td></tr><?php endif; ?>
            <?php foreach ($report['commission'] as $c): ?>
            <tr><td><strong><?=htmlspecialchars($c['name'])?></strong></td><td style="color:#16a34a">¥<?=number_format($c['commission'],2)?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:900px){.dash-grid{grid-template-columns:1fr!important}}</style>
<?php admin_footer(); ?>
