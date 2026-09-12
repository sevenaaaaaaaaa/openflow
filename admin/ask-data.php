<?php
/**
 * 对话式 BI — 一句话问全站经营数据，AI 选数据集 + 回答 + 出图 + 追问（数字来自真实数据）
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('insights');

$samples = ['最近30天营收趋势如何？', '哪个来源带来的营收最高？', '线索都卡在哪个阶段？', '最近内容产出节奏怎么样？', '转化漏斗哪一步流失最多？', '现在有多少会员和线索？'];
admin_header('问数据 · BI');
?>
<style>
.bi-wrap{max-width:900px}
.bi-ask{display:flex;gap:8px;margin:14px 0 10px}
.bi-ask input{flex:1}
.bi-chips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px}
.bi-card{padding:18px;border:1px solid var(--border);border-radius:14px;background:var(--surface)}
.bi-ans{font-size:14.5px;line-height:1.9;white-space:pre-wrap}
.bi-chart{margin-top:14px}
.bi-chart svg{width:100%;height:auto;display:block}
.bi-x{display:flex;gap:10px;font-size:11px;fill:var(--faint);color:var(--faint)}
.bi-foot{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;align-items:center}
.bi-skel{display:flex;gap:8px;align-items:center;color:var(--muted);font-size:13px}
.bi-dot{width:8px;height:8px;border-radius:50%;background:var(--accent);animation:bip 1s infinite alternate}
@keyframes bip{from{opacity:.3}to{opacity:1}}
</style>
<div class="bi-wrap">
  <h1 style="margin:0 0 4px">💬 问数据 · BI</h1>
  <p class="v-sub" style="margin:0">一句话问全站经营数据（订单/线索/内容/流量/漏斗/来源）——AI 选对数据集、说人话、自动出图。数字全部来自真实数据，AI 不编造。</p>

  <div class="bi-ask">
    <input id="biQ" class="inp" placeholder="例如：上个月哪个来源带来的营收最高？" autofocus onkeydown="if(event.key==='Enter')biAsk()">
    <button class="btn btn-p" id="biBtn" onclick="biAsk()">问</button>
  </div>
  <div class="bi-chips">
    <?php foreach ($samples as $s): ?>
    <button class="btn btn-s btn-sm" style="font-size:12px" onclick="biAsk(<?=htmlspecialchars(json_encode($s), ENT_QUOTES)?>)"><?=htmlspecialchars($s)?></button>
    <?php endforeach; ?>
  </div>

  <div id="biOut"></div>
</div>
<script>
const BI_CSRF = <?=json_encode(csrf_token())?>;
const BI_DRILL = {
  revenue_by_day: '/xmp/orders', orders_by_day: '/xmp/orders', revenue_by_source: '/xmp/orders',
  leads_by_stage: '/xmp/crm', leads_by_source: '/xmp/crm',
  content_by_day: '/xmp/content-hub', traffic_by_day: '/xmp/cdp',
  funnel: '/xmp/cdp?tab=funnel', kpis: '/xmp/dashboard'
};
const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

function biSvg(chart) {
  const pts = chart.points || [];
  if (!pts.length) return '';
  const W = 760, H = 220, P = 34;
  const vals = pts.map(p => Number(p.value) || 0);
  const max = Math.max(1, ...vals);
  const n = pts.length;
  const x = i => P + (W - 2 * P) * (n <= 1 ? 0.5 : i / (n - 1));
  const y = v => H - P - (H - 2 * P) * (v / max);
  let g = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img">';
  // 基线
  g += '<line x1="' + P + '" y1="' + (H - P) + '" x2="' + (W - P) + '" y2="' + (H - P) + '" stroke="var(--border)"/>';
  if (chart.type === 'line') {
    let d = '';
    pts.forEach((p, i) => { d += (i ? ' L' : 'M') + x(i).toFixed(1) + ' ' + y(vals[i]).toFixed(1); });
    g += '<path d="' + d + '" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linejoin="round"/>';
    pts.forEach((p, i) => { if (n <= 40) g += '<circle cx="' + x(i).toFixed(1) + '" cy="' + y(vals[i]).toFixed(1) + '" r="2.6" fill="var(--accent)"/>'; });
  } else {
    const bw = (W - 2 * P) / n * 0.6;
    pts.forEach((p, i) => {
      const cx = P + (W - 2 * P) * (i + 0.5) / n;
      const h = (H - 2 * P) * (vals[i] / max);
      g += '<rect x="' + (cx - bw / 2).toFixed(1) + '" y="' + (H - P - h).toFixed(1) + '" width="' + bw.toFixed(1) + '" height="' + h.toFixed(1) + '" rx="3" fill="var(--accent)"/>';
      if (n <= 12) g += '<text x="' + cx.toFixed(1) + '" y="' + (H - P + 14) + '" text-anchor="middle" font-size="10" fill="var(--faint)">' + esc(String(p.label).slice(-6)) + '</text>';
    });
  }
  // y 轴最大刻度
  g += '<text x="' + (P - 6) + '" y="' + (P - 8) + '" text-anchor="end" font-size="10" fill="var(--faint)">' + esc(chart.unit || '') + max + '</text>';
  g += '</svg>';
  return g;
}

function biRender(r) {
  const out = document.getElementById('biOut');
  if (!r.ok) {
    out.innerHTML = '<div class="bi-card" style="border-left:3px solid var(--warn)">' + esc(r.error || '未能回答') + '</div>';
    return;
  }
  let h = '<div class="bi-card"><div class="bi-ans">' + esc(r.answer) + '</div>';
  if (r.chart) {
    h += '<div class="bi-chart"><div style="font-size:13px;font-weight:700;margin-bottom:6px">' + esc(r.chart.title) + ' <span style="color:var(--faint);font-weight:400">· ' + esc(r.chart.label) + '</span></div>' + biSvg(r.chart) + '</div>';
  }
  h += '<div class="bi-foot">';
  const drill = r.chart && BI_DRILL[r.chart.dataset];
  if (drill) h += '<a class="btn btn-s btn-sm" href="' + drill + '">下钻明细 →</a>';
  (r.followups || []).forEach(f => { h += '<button class="btn btn-s btn-sm" style="font-size:12px" onclick="biAsk(' + JSON.stringify(f).replace(/"/g, '&quot;') + ')">' + esc(f) + '</button>'; });
  h += '</div></div>';
  out.innerHTML = h;
}

async function biAsk(q) {
  const inp = document.getElementById('biQ');
  const text = (typeof q === 'string' && q) ? q : (inp.value || '').trim();
  if (!text) return;
  inp.value = text;
  const btn = document.getElementById('biBtn');
  btn.disabled = true;
  document.getElementById('biOut').innerHTML = '<div class="bi-card"><div class="bi-skel"><span class="bi-dot"></span>小福正在读全站数据…</div></div>';
  try {
    const r = await fetch('/api/ask-data-bi.php', {
      method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': BI_CSRF},
      body: JSON.stringify({q: text})
    });
    biRender(await r.json());
  } catch (e) {
    biRender({ok: false, error: '网络错误'});
  } finally {
    btn.disabled = false;
  }
}
</script>
<?php admin_footer(); ?>
