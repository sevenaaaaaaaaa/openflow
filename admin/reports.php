<?php
/**
 * 自定义报表 — 任意「维度 × 指标 × 筛选」+ 下钻 + 保存
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ReportEngine.php';
require_login();
require_perm('analytics');

$saved = report_saved();
admin_header('自定义报表');
?>
<div class="admin-layout">
  <?php admin_sidebar('reports'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>自定义报表</h1><p class="v-sub">选数据源、指标、维度、筛选，服务端聚合；每一行可下钻到原始记录。报表可保存复用。</p></div>
      <div class="v-actions"><span class="note mono" style="margin:0" id="rpMsg"></span></div>
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <div style="flex:1;min-width:300px">
        <div class="card" style="margin-bottom:14px">
          <h2 style="font-size:15px">构建报表</h2>
          <div class="field-row">
            <div class="field"><label>数据源</label><select class="inp" id="rpSource" onchange="rpSyncSelects()"><option value="events">行为事件</option><option value="orders">订单</option><option value="leads">线索</option></select></div>
            <div class="field"><label>指标</label><select class="inp" id="rpMetric"></select></div>
            <div class="field"><label>维度</label><select class="inp" id="rpDimension"></select></div>
            <div class="field"><label>时间窗口（天）</label><input class="inp" type="number" id="rpDays" value="30" min="1" max="365"></div>
          </div>
          <div id="rpFilters" style="margin:6px 0"></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
            <button class="btn btn-ghost btn-sm" onclick="rpAddFilter()">+ 添加筛选</button>
            <button class="btn btn-p btn-sm" id="rpRunBtn" onclick="rpRun()">运行</button>
            <input class="inp sm" id="rpName" placeholder="报表名（保存用）" style="width:180px">
            <button class="btn btn-s btn-sm" onclick="rpSave()">保存报表</button>
          </div>
        </div>

        <div class="card" id="rpResultCard" style="display:none">
          <h2 style="font-size:15px" id="rpResultTitle">结果</h2>
          <table><thead><tr><th>维度</th><th id="rpMetricHead">值</th><th></th></tr></thead><tbody id="rpRows"></tbody></table>
        </div>
        <div class="card" id="rpDrillCard" style="display:none;margin-top:14px">
          <h2 style="font-size:14px" id="rpDrillTitle">下钻</h2>
          <table><thead><tr><th>记录</th><th>补充</th><th></th></tr></thead><tbody id="rpDrillRows"></tbody></table>
        </div>
      </div>

      <div style="flex:0 0 240px">
        <div class="card">
          <h2 style="font-size:14px">已保存报表（<?=count($saved)?>）</h2>
          <?php if (!$saved): ?><div class="text-sm text-muted">还没有保存的报表。</div><?php endif; ?>
          <?php foreach ($saved as $r): ?>
          <div style="display:flex;gap:6px;align-items:center;padding:6px 0;border-bottom:1px solid var(--border-soft,var(--border))">
            <a href="#" onclick='rpLoad(<?=json_encode($r["spec"], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT)?>,<?=json_encode($r["name"], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT)?>);return false' style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['name'])?></a>
            <button class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="rpDelete('<?=htmlspecialchars($r['id'])?>')">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const RP_CSRF = <?=json_encode(csrf_token())?>;
const RP_META = <?=json_encode(['dimensions' => report_dimensions(), 'metrics' => report_metrics()], JSON_UNESCAPED_UNICODE)?>;
function rpMsg(t){ document.getElementById('rpMsg').textContent = t; }
function rpEsc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
function rpSyncSelects(){
  const src = document.getElementById('rpSource').value;
  const dims = RP_META.dimensions[src] || {}, mets = RP_META.metrics[src] || {};
  const dSel = document.getElementById('rpDimension'), mSel = document.getElementById('rpMetric');
  dSel.innerHTML = Object.entries(dims).map(([k,v])=>'<option value="'+k+'">'+rpEsc(v)+'</option>').join('');
  mSel.innerHTML = Object.entries(mets).map(([k,v])=>'<option value="'+k+'">'+rpEsc(v)+'</option>').join('');
}
function rpAddFilter(field, op, value){
  const wrap = document.getElementById('rpFilters');
  const dims = RP_META.dimensions[document.getElementById('rpSource').value] || {};
  const opts = Object.entries(dims).map(([k,v])=>'<option value="'+k+'"'+(k===field?' selected':'')+'>'+rpEsc(v)+'</option>').join('');
  const row = document.createElement('div');
  row.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px';
  row.className = 'rp-filter';
  row.innerHTML = '<select class="inp sm" data-f="field" style="flex:1">'+opts+'</select>'
    + '<select class="inp sm" data-f="op" style="width:100px"><option value="eq"'+((op||'eq')==='eq'?' selected':'')+'>等于</option><option value="neq">不等于</option><option value="contains">包含</option><option value="in">属于</option></select>'
    + '<input class="inp sm" data-f="value" placeholder="值" value="'+rpEsc(value||'')+'" style="flex:1">'
    + '<button class="btn btn-ghost btn-sm" onclick="this.parentNode.remove()">✕</button>';
  wrap.appendChild(row);
}
function rpSpec(){
  const filters = [];
  document.querySelectorAll('#rpFilters .rp-filter').forEach(function(r){
    const f = r.querySelector('[data-f=field]').value, op = r.querySelector('[data-f=op]').value, v = r.querySelector('[data-f=value]').value;
    if (v !== '') filters.push({field:f, op:op, value:v});
  });
  return {source:document.getElementById('rpSource').value, metric:document.getElementById('rpMetric').value, dimension:document.getElementById('rpDimension').value, days:document.getElementById('rpDays').value, filters:filters};
}
async function rpPost(p){ return (await fetch('/api/reports.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':RP_CSRF},body:JSON.stringify(p)})).json(); }
async function rpRun(spec){
  const b = document.getElementById('rpRunBtn'); b.disabled=true; b.textContent='计算中…'; rpMsg('');
  const r = await rpPost({action:'run', spec: spec || rpSpec()});
  b.disabled=false; b.textContent='运行';
  if(!r.ok){ rpMsg(r.error||'失败'); return; }
  const dims = RP_META.dimensions[r.source]||{}, mets = RP_META.metrics[r.source]||{};
  document.getElementById('rpResultTitle').textContent = (mets[r.metric]||r.metric) + ' · 按' + (dims[r.dimension]||r.dimension);
  document.getElementById('rpMetricHead').textContent = mets[r.metric]||'值';
  const max = Math.max(1, ...r.rows.map(x=>Number(x.value)||0));
  document.getElementById('rpRows').innerHTML = r.rows.map(x=>'<tr><td>'+rpEsc(x.label)+'</td><td class="mono">'+(r.metric==='revenue'||r.metric==='pipeline_value'?'¥':'')+x.value+'</td>'
    + '<td style="width:150px"><div style="background:var(--hover);border-radius:99px;height:8px;overflow:hidden"><div style="height:100%;width:'+Math.round((Number(x.value)||0)/max*100)+'%;background:var(--accent)"></div></div></td>'
    + '<td><button class="btn btn-ghost btn-sm" onclick="rpDrill('+JSON.stringify(JSON.stringify(x.label))+')">下钻</button></td></tr>').join('')
    || '<tr><td colspan="4" class="text-muted">无数据</td></tr>';
  document.getElementById('rpResultCard').style.display='';
  window.__rpSpec = r.spec; window.__rpMetric = r.metric;
}
async function rpDrill(labelJson){
  const label = JSON.parse(labelJson);
  const r = await rpPost({action:'drill', spec: window.__rpSpec, value: label});
  if(!r.ok){ rpMsg(r.error||'失败'); return; }
  document.getElementById('rpDrillTitle').textContent = '下钻：' + label + '（' + r.count + ' 条）';
  document.getElementById('rpDrillRows').innerHTML = r.rows.map(x=>'<tr><td>'+rpEsc(x.title||x.id)+'</td><td class="text-sm text-muted">'+rpEsc(x.extra||'')+'</td><td>'+(x.url?'<a class="btn btn-ghost btn-sm" href="'+rpEsc(x.url)+'">查看</a>':'')+'</td></tr>').join('') || '<tr><td colspan="3" class="text-muted">无记录</td></tr>';
  document.getElementById('rpDrillCard').style.display='';
}
async function rpSave(){
  const name = document.getElementById('rpName').value.trim();
  if(!name){ rpMsg('先填报表名'); return; }
  const r = await rpPost({action:'save', name:name, spec: window.__rpSpec || rpSpec()});
  rpMsg(r.ok?'已保存':'失败'); if(r.ok) setTimeout(()=>location.reload(),500);
}
async function rpDelete(id){ const ok = await ofConfirm({message:'删除该报表？', danger:true, okText:'删除'}); if(!ok) return; const r=await rpPost({action:'delete', id:id}); if(r.ok) location.reload(); }
function rpLoad(spec, name){
  document.getElementById('rpSource').value = spec.source||'events';
  rpSyncSelects();
  document.getElementById('rpMetric').value = spec.metric||'';
  document.getElementById('rpDimension').value = spec.dimension||'';
  document.getElementById('rpDays').value = spec.days||30;
  document.getElementById('rpFilters').innerHTML='';
  (spec.filters||[]).forEach(f=>rpAddFilter(f.field,f.op,f.value));
  document.getElementById('rpName').value = name||'';
  rpRun(spec);
}
document.addEventListener('DOMContentLoaded', function(){ rpSyncSelects(); rpRun(); });
</script>
<?php admin_footer(); ?>
