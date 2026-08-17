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
.dash-trend{display:flex;align-items:flex-end;gap:5px;height:150px}
.dash-trend .col{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px}
.dash-trend .bar{width:100%;background:var(--accent);opacity:.8;border-radius:5px 5px 0 0;min-height:3px;transition:opacity .2s}
.dash-trend .col:hover .bar{opacity:1}
.channel-row{display:flex;align-items:center;gap:10px;margin-bottom:11px}
.channel-row:last-child{margin-bottom:0}
.channel-row .bar{flex:1;height:22px;background:var(--hover);border-radius:6px;overflow:hidden}
.channel-row .bar i{display:block;height:100%;background:var(--accent);border-radius:6px}
.p-table{width:100%;border-collapse:collapse;font-size:13px}
.p-table th{text-align:left;font-family:var(--font-mono);font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--faint);font-weight:600;padding:10px 20px;border-bottom:1px solid var(--border-soft)}
.p-table td{padding:12px 20px;border-bottom:1px solid var(--border-soft);color:var(--muted)}
.p-table tr:last-child td{border-bottom:none}
.p-table .num{font-family:var(--font-mono);color:var(--fg)}
.kpi .delta{display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;font-family:var(--font-mono);margin-top:6px}
.kpi .delta.up{color:var(--ok)}
.kpi .delta.down{color:var(--danger)}
.kpi .delta.flat{color:var(--faint)}
.target-track{height:5px;border-radius:99px;background:var(--hover);margin-top:9px;overflow:hidden}
.target-track i{display:block;height:100%;border-radius:99px;background:var(--accent);transition:width .6s var(--ease-out)}
</style>
<?php
function kpi_delta(float $cur, float $prev): array {
    if ($prev <= 0) return ['pct' => $cur > 0 ? 100 : 0, 'cls' => $cur > 0 ? 'up' : 'flat', 'label' => $cur > 0 ? '▲ 新增' : '—'];
    $pct = round(($cur - $prev) / $prev * 100);
    $cls = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat');
    $arrow = $pct > 0 ? '▲' : ($pct < 0 ? '▼' : '＝');
    return ['pct' => $pct, 'cls' => $cls, 'label' => $arrow . ' ' . ($pct > 0 ? '+' : '') . $pct . '%'];
}
$dUv = kpi_delta((float)$kpis['uv'], (float)$kpis['prev_uv']);
$dRev = kpi_delta((float)$kpis['revenue_30d'], (float)$kpis['prev_revenue_30d']);
$dLead = kpi_delta((float)$kpis['leads'], (float)$kpis['prev_leads']);
$settings = json_read(DATA_DIR . '/settings.json');
$revTarget = (float)($settings['monthly_revenue_target'] ?? 0);
$revProgress = $revTarget > 0 ? min(100, round($kpis['revenue_30d'] / $revTarget * 100)) : 0;
?>
<div class="admin-layout">
  <?php admin_sidebar('dashboard'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>经营驾驶舱</h1><p class="v-sub">全站访问 · 线索 · 订单 · 订阅 · 收入 · NPS 一览</p></div>
      <div class="v-actions"><span class="st st-ok">实时</span></div>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="k-label">近30天访客</div><div class="k-val mono"><?=number_format($kpis['uv'])?></div><div class="k-sub">PV <?=number_format($kpis['pv'])?> · 今日 <?=$kpis['today_uv']?></div><div class="delta <?=$dUv['cls']?>"><?=$dUv['label']?> <span style="font-weight:400;color:var(--faint)">vs 上期</span></div></div>
      <div class="kpi"><div class="k-label">累计线索</div><div class="k-val mono"><?=$kpis['leads']?></div><div class="k-sub">CRM 线索池</div><div class="delta <?=$dLead['cls']?>"><?=$dLead['label']?> <span style="font-weight:400;color:var(--faint)">vs 上期</span></div></div>
      <div class="kpi"><div class="k-label">订单</div><div class="k-val mono"><?=$kpis['orders']?></div><div class="k-sub">已支付 <?=$kpis['paid_orders']?></div></div>
      <div class="kpi"><div class="k-label">累计收入</div><div class="k-val mono" style="color:var(--ok)">¥<?=number_format($kpis['revenue'],0)?></div><div class="k-sub">近30天 ¥<?=number_format($kpis['revenue_30d'],0)?></div>
        <div class="delta <?=$dRev['cls']?>"><?=$dRev['label']?> <span style="font-weight:400;color:var(--faint)">vs 上期</span></div>
        <?php if ($revTarget > 0): ?><div class="target-track"><i style="width:<?=$revProgress?>%"></i></div><div style="font-size:10.5px;color:var(--faint);margin-top:4px">月目标 ¥<?=number_format($revTarget,0)?> · 完成 <?=$revProgress?>%</div><?php endif; ?>
      </div>
      <div class="kpi"><div class="k-label">会员数</div><div class="k-val mono"><?=$kpis['members']?></div><div class="k-sub">注册会员总量</div></div>
      <div class="kpi"><div class="k-label">活跃订阅</div><div class="k-val mono"><?=$kpis['active_subscribers']?></div><div class="k-sub">订阅中会员</div></div>
      <div class="kpi"><div class="k-label">NPS</div><div class="k-val mono" style="color:<?=($nps['avg_nps']??0)>=0?'var(--ok)':'var(--danger)'?>"><?=$nps['avg_nps'] ?? '—'?></div><div class="k-sub"><?=$nps['total_responses']?> 份回收</div></div>
      <div class="kpi"><div class="k-label">分销佣金</div><div class="k-val mono">¥<?=number_format($kpis['commission_paid'],0)?></div><div class="k-sub">累计支出</div></div>
    </div>

    <div class="panels">
      <div class="panel">
        <div class="p-head"><h3>访问趋势</h3><span class="p-sub mono">近 14 天 UV</span></div>
        <div class="p-body">
          <div class="dash-trend">
            <?php $maxT = max($trend) ?: 1; foreach ($trend as $d => $uv): ?>
            <div class="col">
              <span style="font-family:var(--font-mono);font-size:10px;color:var(--faint)"><?=$uv?></span>
              <div class="bar" style="height:<?=$uv>0?max(8,round($uv/$maxT*120)):3?>px"></div>
              <span style="font-family:var(--font-mono);font-size:9px;color:var(--faint)"><?=substr($d,5)?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="p-head"><h3>渠道归因</h3><span class="p-sub mono">按支付订单</span></div>
        <div class="p-body">
          <?php if (empty($channels)): ?><div class="empty" style="padding:16px 0">暂无支付订单，产生订单后自动归因</div>
          <?php else: $maxCh = max(array_column($channels,'orders')) ?: 1; ?>
          <?php foreach ($channels as $src => $c): ?>
          <div class="channel-row">
            <span style="font-size:12px;width:88px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars($src)?></span>
            <div class="bar"><i style="width:<?=round($c['orders']/$maxCh*100)?>%"></i></div>
            <span style="font-family:var(--font-mono);font-size:11px;width:74px;text-align:right;color:var(--muted)"><?=$c['orders']?>单 ¥<?=$c['revenue']?></span>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="panels p2">
      <div class="panel">
        <div class="p-head"><h3>收入报表</h3><span class="p-sub mono">按月</span></div>
        <div class="p-body" style="padding:0">
          <table class="p-table">
            <thead><tr><th>月份</th><th>订单数</th><th>收入</th><th>分销佣金</th></tr></thead>
            <tbody>
              <?php if (empty($report['monthly'])): ?><tr><td colspan="4" style="color:var(--faint)">暂无收入数据</td></tr><?php endif; ?>
              <?php foreach ($report['monthly'] as $m => $r): ?>
              <tr>
                <td style="color:var(--fg);font-weight:600"><?=htmlspecialchars($m)?></td>
                <td class="num"><?=$r['orders']?></td>
                <td class="num" style="color:var(--ok);font-weight:600">¥<?=number_format($r['revenue'],0)?></td>
                <td class="num">¥<?=number_format($r['commission'],0)?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <div class="p-head"><h3>分销佣金榜</h3><span class="p-sub mono">大使</span></div>
        <div class="p-body" style="padding:0">
          <table class="p-table">
            <thead><tr><th>大使</th><th>佣金</th></tr></thead>
            <tbody>
              <?php if (empty($report['commission'])): ?><tr><td colspan="2" style="color:var(--faint)">暂无分销佣金</td></tr><?php endif; ?>
              <?php foreach ($report['commission'] as $c): ?>
              <tr><td style="color:var(--fg);font-weight:600"><?=htmlspecialchars($c['name'])?></td><td class="num" style="color:var(--ok)">¥<?=number_format($c['commission'],2)?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
