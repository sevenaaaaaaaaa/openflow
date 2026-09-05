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
$activity = dash_activity();
$paths = dash_paths();
$prefs = dash_preferences();
$utmAttr = dash_utm_attribution();

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
      <div class="panel" style="width:100%">
        <div class="p-head"><h3>🤖 AI 洞察</h3><span class="p-sub mono">AI · 兜底解读 · 发现异常</span>
          <button type="button" class="btn btn-s btn-sm" onclick="ofLoadInsights(true)" style="margin-left:auto">✨ 生成洞察</button></div>
        <div class="p-body" id="ofAiInsight" style="min-height:64px">
          <div class="text-sm text-muted" style="padding:14px;text-align:center">点击「✨ 生成洞察」，AI 会解读当前关键指标、发现异常并给出建议。</div>
        </div>
      </div>
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
        <div class="p-head"><h3>来源归因</h3><span class="p-sub mono">UTM + Referrer · 近30天</span></div>
        <div class="p-body">
          <?php
          $medCat = ['SEO'=>'oklch(58% .17 152)','SEM'=>'oklch(55% .2 25)','信息流'=>'oklch(66% .15 75)','社媒'=>'oklch(60% .14 300)','邮件'=>'oklch(55% .16 290)','自定义'=>'oklch(52% .17 258)','直接'=>'oklch(46% .016 70)','其他'=>'oklch(46% .016 70)'];
          $medOrder = ['SEO','SEM','信息流','社媒','邮件','自定义','直接','其他'];
          ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px">
            <?php foreach ($medOrder as $m): if (($utmAttr['by_medium'][$m] ?? 0) > 0): ?>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;background:<?=$medCat[$m]?>18;color:<?=$medCat[$m]?>"><span style="width:7px;height:7px;border-radius:50%;background:<?=$medCat[$m]?>"></span><?=$m?> <?=$utmAttr['by_medium'][$m]?></span>
            <?php endif; endforeach; ?>
            <?php if ($utmAttr['total'] <= 0): ?><span style="font-size:11px;color:var(--faint)">暂无归因数据（带 UTM 的访问落地或 referrer 数据产生后显示）</span><?php endif; ?>
          </div>
          <?php if (!empty($utmAttr['groups'])): $maxG = max(array_column($utmAttr['groups'],'visits')) ?: 1; foreach (array_slice($utmAttr['groups'], 0, 6, true) as $key => $g): ?>
          <div class="channel-row">
            <span style="font-size:11px;width:50px;color:<?=$medCat[$g['medium']]?>;font-weight:700"><?=htmlspecialchars($g['medium'])?></span>
            <span style="font-size:12px;width:92px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars($g['source'])?></span>
            <div class="bar"><i style="width:<?=round($g['visits']/$maxG*100)?>%"></i></div>
            <span class="num" style="font-size:11px;width:36px;text-align:right"><?=$g['visits']?></span>
          </div>
          <?php endforeach; endif; ?>
          <?php if (!empty($channels)): ?>
          <div style="border-top:1px solid var(--border-soft);margin-top:12px;padding-top:12px">
            <div style="font-size:11px;font-weight:600;color:var(--faint);margin-bottom:8px">支付订单归因（按订单 UTM）</div>
            <?php $maxCh = max(array_column($channels,'orders')) ?: 1; foreach (array_slice($channels, 0, 4, true) as $src => $c): ?>
            <div class="channel-row">
              <span style="font-size:12px;width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars($src)?></span>
              <div class="bar"><i style="width:<?=round($c['orders']/$maxCh*100)?>%"></i></div>
              <span style="font-family:var(--font-mono);font-size:11px;width:70px;text-align:right;color:var(--muted)"><?=$c['orders']?>单 ¥<?=$c['revenue']?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="panels p2">
      <div class="panel">
        <div class="p-head"><h3>收入报表</h3><span class="p-sub mono">按月 · 含商品/课程</span></div>
        <div class="p-body" style="padding:0">
          <table class="p-table">
            <thead><tr><th>月份</th><th>订单</th><th>付费单</th><th>免费单</th><th>收入</th><th>分销佣金</th></tr></thead>
            <tbody>
              <?php if (empty($report['monthly'])): ?><tr><td colspan="6" style="color:var(--faint)">暂无收入数据</td></tr><?php endif; ?>
              <?php foreach ($report['monthly'] as $m => $r): ?>
              <tr>
                <td style="color:var(--fg);font-weight:600"><?=htmlspecialchars($m)?></td>
                <td class="num"><?=$r['orders']?></td>
                <td class="num"><?=$r['paid_orders']?></td>
                <td class="num" style="color:var(--muted)"><?=$r['free_orders']?></td>
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

    <!-- 活跃分析 -->
    <div class="panels">
      <div class="panel">
        <div class="p-head"><h3>活跃分析</h3><span class="p-sub mono">DAU / WAU / MAU</span></div>
        <div class="p-body">
          <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px">
            <div class="kpi" style="padding:14px 16px"><div class="k-label">DAU</div><div class="k-val mono" style="font-size:24px"><?=$activity['dau']?></div><div class="k-sub">今日</div></div>
            <div class="kpi" style="padding:14px 16px"><div class="k-label">WAU</div><div class="k-val mono" style="font-size:24px"><?=$activity['wau']?></div><div class="k-sub">近 7 天</div></div>
            <div class="kpi" style="padding:14px 16px"><div class="k-label">MAU</div><div class="k-val mono" style="font-size:24px"><?=$activity['mau']?></div><div class="k-sub">近 30 天</div></div>
          </div>
          <div style="font-size:12px;font-weight:600;color:var(--faint);margin-bottom:8px">活跃时段（近 7 天 · 按小时）</div>
          <div style="display:flex;gap:3px;align-items:flex-end;height:60px">
            <?php $maxH = max($activity['hours']) ?: 1; foreach ($activity['hours'] as $h => $cnt): ?>
            <div style="flex:1;text-align:center" title="<?=$h?> 时 · <?=$cnt?> 次访问">
              <div style="background:var(--accent);opacity:<?=max(0.15, $cnt/$maxH)?>;border-radius:3px 3px 0 0;height:<?=$cnt>0?max(4, round($cnt/$maxH*52)):2?>px"></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;justify-content:space-between;font-family:var(--font-mono);font-size:9px;color:var(--faint);margin-top:4px"><span>00时</span><span>06时</span><span>12时</span><span>18时</span><span>23时</span></div>
          <div style="display:flex;gap:16px;margin-top:14px;font-size:12px;color:var(--muted)">
            <span>新访客 <b class="num" style="color:var(--ok)"><?=$activity['new_visitors']?></b></span>
            <span>回头客 <b class="num" style="color:var(--accent)"><?=$activity['returning']?></b></span>
            <span style="margin-left:auto;color:var(--faint)">近 30 天</span>
          </div>
        </div>
      </div>

      <!-- 行为路径 -->
      <div class="panel">
        <div class="p-head"><h3>行为路径</h3><span class="p-sub mono">落地页 · 来源 · 转化</span></div>
        <div class="p-body">
          <div style="font-size:12px;font-weight:600;color:var(--faint);margin-bottom:8px">Top 落地页</div>
          <?php if (empty($paths['pages'])): ?><div class="empty" style="padding:10px 0;font-size:12px;color:var(--faint)">暂无访问数据</div>
          <?php else: $maxP = max(array_column($paths['pages'],'views')) ?: 1; foreach (array_slice($paths['pages'],0,6) as $p): ?>
          <div class="channel-row"><span style="font-size:12px;width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars($p['page'])?></span><div class="bar"><i style="width:<?=round($p['views']/$maxP*100)?>%"></i></div><span class="num" style="font-size:11px;width:44px;text-align:right"><?=$p['views']?></span></div>
          <?php endforeach; endif; ?>
          <div style="font-size:12px;font-weight:600;color:var(--faint);margin:14px 0 8px">Top 来源</div>
          <?php if (empty($paths['referrers'])): ?><div class="empty" style="padding:6px 0;font-size:12px;color:var(--faint)">暂无来源数据（需 referrer 埋点）</div>
          <?php else: foreach ($paths['referrers'] as $r): ?>
          <div class="channel-row"><span style="font-size:12px;width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars($r['source'])?></span><div class="bar" style="height:18px"><i style="width:<?=min(100, $r['count']*10)?>%"></i></div><span class="num" style="font-size:11px;width:44px;text-align:right"><?=$r['count']?></span></div>
          <?php endforeach; endif; ?>
          <div style="display:flex;align-items:center;gap:8px;margin-top:14px;padding:10px 12px;border-radius:10px;background:var(--ok-soft);font-size:12px;color:var(--ok)"><b>转化</b> 近 30 天 <?=$paths['conversions']?> 次（表单提交 / 注册 / 下载）</div>
        </div>
      </div>
    </div>

    <!-- 偏好洞察 -->
    <div class="panels">
      <div class="panel">
        <div class="p-head"><h3>偏好洞察 · 设备</h3><span class="p-sub mono">OS / 语言</span></div>
        <div class="p-body">
          <div style="font-size:12px;font-weight:600;color:var(--faint);margin-bottom:8px">设备系统</div>
          <?php if (empty($prefs['devices'])): ?><div class="empty" style="padding:8px 0;font-size:12px;color:var(--faint)">暂无设备数据</div>
          <?php else: $maxD = max(array_column($prefs['devices'],'count')) ?: 1; foreach ($prefs['devices'] as $d): ?>
          <div class="channel-row"><span style="font-size:12px;width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars($d['name'])?></span><div class="bar" style="height:18px"><i style="width:<?=round($d['count']/$maxD*100)?>%"></i></div><span class="num" style="font-size:11px;width:36px;text-align:right"><?=$d['count']?></span></div>
          <?php endforeach; endif; ?>
          <div style="font-size:12px;font-weight:600;color:var(--faint);margin:14px 0 8px">语言</div>
          <?php if (empty($prefs['languages'])): ?><div class="empty" style="padding:8px 0;font-size:12px;color:var(--faint)">暂无语言数据</div>
          <?php else: foreach ($prefs['languages'] as $l): ?>
          <div class="channel-row"><span style="font-size:12px;width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars($l['name'])?></span><div class="bar" style="height:18px"><i style="width:<?=min(100, $l['count']*20)?>%"></i></div><span class="num" style="font-size:11px;width:36px;text-align:right"><?=$l['count']?></span></div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <div class="panel">
        <div class="p-head"><h3>偏好洞察 · 内容</h3><span class="p-sub mono">用户爱看什么</span></div>
        <div class="p-body">
          <div style="font-size:12px;font-weight:600;color:var(--faint);margin-bottom:10px">内容分类偏好</div>
          <?php if (empty($prefs['content'])): ?><div class="empty" style="padding:12px 0;font-size:12px;color:var(--faint)">暂无内容浏览数据</div>
          <?php else: $maxC = max(array_column($prefs['content'],'count')) ?: 1; foreach ($prefs['content'] as $c): ?>
          <div class="channel-row"><span style="font-size:12px;width:70px;color:var(--muted)"><?=htmlspecialchars($c['name'])?></span><div class="bar" style="height:20px"><i style="width:<?=round($c['count']/$maxC*100)?>%"></i></div><span class="num" style="font-size:11px;width:36px;text-align:right"><?=$c['count']?></span></div>
          <?php endforeach; endif; ?>
          <p style="font-size:12px;color:var(--faint);margin-top:14px;line-height:1.7">偏好基于浏览行为聚合：设备/语言来自 CDP 画像，内容分类来自页面访问分布。数据量增长后洞察更准确。</p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
<script>
// AI 洞察兜底：读当前关键指标 → CdpInsight::generate 解读异常与建议
function ofLoadInsights(force) {
  var box = document.getElementById('ofAiInsight'); if (!box) return;
  if (force) box.innerHTML = '<div class="text-sm text-muted" style="padding:20px;text-align:center">AI 正在解读当前数据…</div>';
  fetch('/api/cdp-insight.php?action=insights&days=30', {credentials:'include'})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) { box.innerHTML = '<div class="text-sm text-muted">洞察生成失败</div>'; return; }
      var h = '';
      if (d.summary) h += '<div style="padding:12px 14px;background:var(--surface);border-radius:10px;margin-bottom:12px;font-size:13.5px;line-height:1.7">📌 ' + d.summary + '</div>';
      if (d.insights && d.insights.length) {
        h += '<div style="font-size:12px;font-weight:700;color:var(--text-3);margin:10px 0 6px">✨ 洞察</div>';
        d.insights.forEach(function(i){ h += '<div style="display:flex;gap:8px;padding:8px 12px;background:var(--surface);border-radius:8px;margin-bottom:6px;font-size:13px"><span>💡</span><div><strong>'+(i.title||'')+'</strong><div class="text-sm text-muted" style="font-size:12px">'+(i.detail||'')+'</div></div></div>'; });
      }
      if (d.anomalies && d.anomalies.length) {
        h += '<div style="font-size:12px;font-weight:700;color:var(--danger);margin:10px 0 6px">⚠️ 异常</div>';
        d.anomalies.forEach(function(a){ h += '<div style="display:flex;gap:8px;padding:8px 12px;background:var(--surface);border-radius:8px;margin-bottom:6px;font-size:13px"><span>🚨</span><div><strong>'+(a.title||'')+'</strong><div class="text-sm text-muted" style="font-size:12px">'+(a.detail||'')+'</div></div></div>'; });
      }
      if (d.actions && d.actions.length) {
        h += '<div style="font-size:12px;font-weight:700;color:var(--accent);margin:10px 0 6px">🎯 建议</div>';
        d.actions.forEach(function(a){ h += '<div style="display:flex;gap:8px;padding:8px 12px;background:var(--surface);border-radius:8px;margin-bottom:6px;font-size:13px"><span>→</span><div><strong>'+(a.title||'')+'</strong><div class="text-sm text-muted" style="font-size:12px">'+(a.detail||'')+'</div></div></div>'; });
      }
      if (!h) h = '<div class="text-sm text-muted">暂无洞察，先积累数据。</div>';
      box.innerHTML = h;
    })
    .catch(function(){ box.innerHTML = '<div class="text-sm text-muted">网络异常，稍后再试</div>'; });
}
</script>
