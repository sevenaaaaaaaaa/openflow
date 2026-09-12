<?php
/**
 * 实时人群看板 — 在线/今日新增/近7日活跃 + 各分群实时人数（自动刷新）
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('cdp');
admin_header('实时人群');
?>
<div class="admin-layout">
  <?php admin_sidebar('audience-live'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>实时人群</h1><p class="v-sub">在线 / 今日新增 / 近7日活跃 + 各分群实时人数，每 8 秒自动刷新。事件一进来，分群进出即时变化。</p></div>
      <div class="v-actions"><span class="note mono" style="margin:0" id="alTs">—</span><a class="btn btn-s btn-sm" href="/xmp/cdp?tab=segments">分群管理</a></div>
    </div>

    <div class="kpi-grid" style="margin-bottom:16px">
      <div class="kpi"><div class="k-label">当前在线</div><div class="k-val mono" id="alOnline">—</div><div class="k-sub">5 分钟内活跃</div></div>
      <div class="kpi"><div class="k-label">今日新增</div><div class="k-val mono" id="alNew">—</div><div class="k-sub">今日建档</div></div>
      <div class="kpi"><div class="k-label">近7日活跃</div><div class="k-val mono" id="alActive7">—</div><div class="k-sub">7 天内有行为</div></div>
      <div class="kpi"><div class="k-label">画像总量</div><div class="k-val mono" id="alTotal">—</div><div class="k-sub">累计建档</div></div>
    </div>

    <div class="card" style="padding:0;overflow:auto">
      <div style="padding:12px 16px;border-bottom:1px solid var(--border-soft,var(--border));font-weight:700">分群实时人数</div>
      <table>
        <thead><tr><th>分群</th><th>人数</th><th>占比</th></tr></thead>
        <tbody id="alSegs"><tr><td colspan="3" class="text-muted" style="padding:16px">加载中…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>
<script>
function alRender(d) {
  if (!d || !d.ok) return;
  document.getElementById('alOnline').textContent = d.online;
  document.getElementById('alNew').textContent = d.new_today;
  document.getElementById('alActive7').textContent = d.active_7d;
  document.getElementById('alTotal').textContent = d.total;
  document.getElementById('alTs').textContent = '更新于 ' + d.ts;
  var total = Math.max(1, d.total);
  var rows = (d.segments || []).map(function (s) {
    var pct = Math.round(s.count / total * 100);
    return '<tr><td>' + escH(s.name) + '</td><td class="mono">' + s.count + '</td>' +
      '<td><div style="background:var(--hover);border-radius:99px;height:8px;width:140px;overflow:hidden;display:inline-block;vertical-align:middle"><div style="height:100%;width:' + pct + '%;background:var(--accent)"></div></div> <span class="text-sm">' + pct + '%</span></td></tr>';
  }).join('');
  document.getElementById('alSegs').innerHTML = rows || '<tr><td colspan="3" class="text-muted" style="padding:16px">暂无分群</td></tr>';
}
function escH(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
function alLoad() { fetch('/api/cdp.php?action=live_audience').then(function (r) { return r.json(); }).then(alRender).catch(function () {}); }
alLoad(); setInterval(alLoad, 8000);
</script>
<?php admin_footer(); ?>
