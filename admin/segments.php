<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CrmSystem.php';
require_login();
require_perm('segments');

// ─── 分群 → CRM 批量建线索（A3）───
$importMsg = ''; $importErr = ''; $importStat = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'to_crm') {
    csrf_verify();
    if (!has_perm('crm')) {
        $importErr = '没有 CRM 权限，无法导入线索';
    } else {
        $stat = crm_leads_from_segment((string)($_POST['segment_id'] ?? ''), [
            'owner'           => trim($_POST['owner'] ?? ''),
            'stage'           => $_POST['stage'] ?? 'new',
            'update_existing' => !empty($_POST['update_existing']),
            'dry_run'         => !empty($_POST['dry_run']),
        ]);
        if (!empty($stat['error'])) {
            $importErr = $stat['error'];
        } else {
            $importStat = $stat;
            $prefix = !empty($_POST['dry_run']) ? '试运行（未写入）：' : '';
            $importMsg = $prefix . "分群「{$stat['segment']}」命中 {$stat['matched']} 人，"
                       . "新建 {$stat['created']} 条线索，补齐 {$stat['updated']} 条，"
                       . "跳过已存在 {$stat['skipped']} 条，无有效邮箱 {$stat['no_email']} 人。";
            if (empty($_POST['dry_run']) && $stat['created'] > 0 && class_exists('AuditLog')) {
                try { AuditLog::log("分群导入 CRM：{$stat['segment']} 新建 {$stat['created']} 条", 'crm', $stat); }
                catch (\Throwable $e) {}
            }
        }
    }
}

admin_header('用户分群');
$segments = SegmentEngine::getSegments();
$templates = SegmentEngine::templates();
$evaluating = isset($_GET['eval']);
if ($evaluating) {
    $results = SegmentEngine::evaluateAll();
}
$totalMembers = array_sum(array_column($segments, 'member_count'));
?>
<style>
.sg-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.sg-kpi{padding:16px 18px;background:var(--surface);border-radius:14px;border:1px solid var(--border)}
.sg-kpi .n{font-size:26px;font-weight:800;letter-spacing:-.02em;font-family:var(--font-mono)}
.sg-kpi .l{color:var(--muted);font-size:12.5px;margin-top:2px}
.sg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;margin-bottom:26px}
.sg-card{background:var(--surface);border-radius:14px;border:1px solid var(--border);overflow:hidden;display:flex;flex-direction:column}
.sg-card .bar{height:5px}
.sg-card .bd{padding:16px 18px;display:flex;flex-direction:column;gap:10px;flex:1}
.sg-card .hd{display:flex;align-items:flex-start;gap:10px}
.sg-card h3{margin:0;font-size:15px;font-weight:800;letter-spacing:-.01em}
.sg-card .desc{margin:2px 0 0;font-size:12.5px;color:var(--muted)}
.sg-card .cnt{margin-left:auto;font-family:var(--font-mono);font-size:20px;font-weight:800;color:var(--accent);flex:0 0 auto}
.sg-card .cnt small{font-size:11px;color:var(--faint);font-weight:500;margin-left:2px}
.sg-rules{display:flex;flex-wrap:wrap;gap:4px;align-items:center}
.sg-rule{padding:3px 9px;background:var(--hover);border-radius:7px;font-size:12px;font-family:var(--font-mono)}
.sg-op{font-size:11px;color:var(--faint);font-weight:700;letter-spacing:.06em}
.sg-meta{display:flex;justify-content:space-between;font-size:11.5px;color:var(--faint);margin-top:auto}
.sg-acts{display:flex;gap:6px;padding-top:6px;border-top:1px solid var(--border-soft)}
.sg-tpl{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px}
.sg-tpl-it{padding:14px 16px;background:var(--surface-strong);border-radius:12px;border:1px solid var(--border);display:flex;flex-direction:column;gap:6px}
.sg-tpl-it .nm{display:flex;align-items:center;gap:8px;font-weight:700;font-size:13.5px}
.sg-tpl-it .dot{width:10px;height:10px;border-radius:50%}
.sg-tpl-it p{margin:0;font-size:12.5px;color:var(--muted);flex:1}
.sg-modal{display:none;position:fixed;inset:0;background:oklch(12% 0 0/.42);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);z-index:1000;align-items:center;justify-content:center;padding:20px}
.sg-modal .box{background:var(--surface-strong);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--shadow);padding:24px 26px;width:560px;max-width:100%;max-height:90vh;overflow-y:auto}
.sg-modal h3{margin:0 0 4px;font-size:17px;font-weight:800}
.sg-modal .lead{margin:0 0 16px;font-size:13px;color:var(--muted);line-height:1.6}
.sg-modal .field label{font-size:12.5px}
.sg-modal input,.sg-modal select{width:100%;height:38px;padding:0 12px;border-radius:10px;border:1px solid var(--border);background:var(--surface);color:var(--fg);font-size:13.5px}
.sg-modal input[type=color]{width:44px;padding:2px;cursor:pointer}
.sg-modal input[type=checkbox]{width:16px;height:16px;margin:0}
.sg-row2{display:grid;grid-template-columns:1fr 120px;gap:12px}
.rb{display:flex;flex-direction:column;gap:8px}
.rb-row{display:grid;grid-template-columns:1.3fr 1fr 1fr 32px;gap:6px;align-items:center}
.rb-row .ib{width:30px;height:30px}
.rb-empty{font-size:12.5px;color:var(--faint);padding:8px 0}
.rb-join{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--muted)}
.rb-join select{width:auto;height:30px;padding:0 24px 0 8px;font-size:12.5px}
.sg-modal .acts{display:flex;gap:8px;justify-content:flex-end;margin-top:18px}
.sg-check{display:flex;gap:8px;align-items:center;font-size:13px;color:var(--muted);margin-bottom:8px;cursor:pointer}
</style>
<div class="admin-layout">
  <?php admin_sidebar('segments'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <div><h1 style="margin-bottom:0">用户分群</h1><p class="sub" style="margin:4px 0 0">按行为与属性把用户分成可触达的群，供自动化、触达与 CRM 使用</p></div>
      <div class="flex gap-2 ml-auto">
        <a href="?eval" class="btn btn-ghost" title="按规则重新计算每个分群的人数">重新评估</a>
        <button type="button" onclick="openAdd()" class="btn btn-primary">新建分群</button>
      </div>
    </div>

    <?php if ($importMsg): ?><?=msg('success', $importMsg)?><?php endif; ?>
    <?php if ($importErr): ?><?=msg('error', $importErr)?><?php endif; ?>

    <div class="sg-kpis">
      <div class="sg-kpi"><div class="n" style="color:var(--accent)"><?=count($segments)?></div><div class="l">分群总数</div></div>
      <div class="sg-kpi"><div class="n" style="color:var(--ok)"><?=count(array_filter($segments, fn($s) => $s['auto_update']))?></div><div class="l">自动更新</div></div>
      <div class="sg-kpi"><div class="n" style="color:var(--warn)"><?=$totalMembers?></div><div class="l">总覆盖人数</div></div>
      <div class="sg-kpi"><div class="n" style="color:var(--muted)"><?=count($templates)?></div><div class="l">预设模板</div></div>
    </div>

    <?php $__ops = ['eq'=>'=','neq'=>'≠','gt'=>'>','gte'=>'≥','lt'=>'<','lte'=>'≤','contains'=>'包含','in'=>'∈','not_in'=>'∉','between'=>'介于']; ?>
    <?php if (!empty($segments)): ?>
      <div class="sg-grid">
        <?php foreach ($segments as $seg): ?>
          <div class="sg-card">
            <div class="bar" style="background:<?=h($seg['color'] ?: 'var(--accent)')?>"></div>
            <div class="bd">
              <div class="hd">
                <div><h3><?=h($seg['name'])?></h3><p class="desc"><?=h($seg['description'] ?: '暂无描述')?></p></div>
                <span class="cnt"><?=$seg['member_count']?><small>人</small></span>
              </div>
              <div class="sg-rules">
                <?php $__first = true; foreach (($seg['rules'] ?? []) as $rule): if (!$__first): ?><span class="sg-op"><?=strtoupper($seg['operator'] ?? 'and')?></span><?php endif; $__first = false; ?>
                  <span class="sg-rule"><?=h($rule['field'] ?? '')?> <?=h($__ops[$rule['operator'] ?? 'eq'] ?? $rule['operator'])?> <?=h(is_array($rule['value']) ? implode(',', $rule['value']) : ($rule['value'] ?? ''))?></span>
                <?php endforeach; if (empty($seg['rules'])): ?><span class="text-muted" style="font-size:12px">无规则 · 匹配所有用户</span><?php endif; ?>
              </div>
              <div class="sg-meta"><span><?=$seg['auto_update'] ? '自动更新' : '手动评估'?></span><span><?=$seg['last_evaluated'] ? '上次评估 '.h(substr($seg['last_evaluated'],0,16)) : '未评估'?></span></div>
              <div class="sg-acts">
                <?php if (has_perm('crm')): ?><button type="button" onclick="openToCrm('<?=h($seg['id'])?>', '<?=h(addslashes($seg['name']))?>', <?=(int)$seg['member_count']?>)" class="btn btn-ghost btn-sm">导入 CRM</button><?php endif; ?>
                <a href="automation.php?edit=new" class="btn btn-ghost btn-sm" title="以「进入分群」为触发器新建自动化">建自动化</a>
                <button type="button" onclick="deleteSegment('<?=h($seg['id'])?>')" class="btn btn-ghost btn-sm" style="margin-left:auto;color:var(--danger)">删除</button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="of-empty" style="margin:0 0 26px;padding:34px">还没有分群。从下面的预设模板一键创建，或点右上角「新建分群」用条件组合出你要的人群。</div>
    <?php endif; ?>

    <div class="card">
      <h2 style="font-size:15px">预设模板 <span class="hint" style="font-weight:400;font-size:12px;color:var(--faint)">· 一键创建，创建后可继续改规则</span></h2>
      <div class="sg-tpl">
        <?php foreach ($templates as $tpl): ?>
          <div class="sg-tpl-it">
            <div class="nm"><span class="dot" style="background:<?=h($tpl['color'])?>"></span><?=h($tpl['name'])?></div>
            <p><?=h($tpl['description'])?></p>
            <div class="sg-rules"><?php foreach (($tpl['rules'] ?? []) as $rule): ?><span class="sg-rule"><?=h($rule['field'])?> <?=h($__ops[$rule['operator']] ?? $rule['operator'])?> <?=h(is_array($rule['value']) ? implode(',', $rule['value']) : $rule['value'])?></span><?php endforeach; ?></div>
            <div><button type="button" onclick="createFromTemplate(<?=htmlspecialchars(json_encode($tpl), ENT_QUOTES)?>)" class="btn btn-ghost btn-sm">使用模板</button></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- 分群 → CRM -->
<div id="toCrmModal" class="sg-modal" onclick="if(event.target===this)this.style.display='none'">
  <form method="post" class="box">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="to_crm">
    <input type="hidden" name="segment_id" id="crmSegId">
    <h3>导入 CRM</h3>
    <p class="lead">分群 <b id="crmSegName" style="color:var(--fg)"></b>，约 <b id="crmSegCount"></b> 人。已存在的线索默认跳过，不会覆盖销售填过的内容；没有有效邮箱的画像会被略过。</p>
    <div class="field"><label>初始阶段</label>
      <select name="stage">
        <?php foreach (crm_stages() as $k => $v): ?><option value="<?=h($k)?>"><?=h(is_array($v) ? ($v['label'] ?? $k) : $v)?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>负责人 <span class="hint">· 可留空</span></label><input type="text" name="owner" placeholder="销售同学的名字"></div>
    <label class="sg-check"><input type="checkbox" name="update_existing" value="1"> 已存在的线索补齐空字段（姓名 / 电话 / 公司 / 来源）</label>
    <label class="sg-check"><input type="checkbox" name="dry_run" value="1" checked> 先试运行：只看会导入多少，不写入</label>
    <div class="acts">
      <button type="button" onclick="document.getElementById('toCrmModal').style.display='none'" class="btn btn-ghost">取消</button>
      <button type="submit" class="btn btn-primary">开始导入</button>
    </div>
  </form>
</div>

<!-- 新建分群 -->
<div id="addModal" class="sg-modal" onclick="if(event.target===this)this.style.display='none'">
  <div class="box">
    <h3>新建分群</h3>
    <p class="lead">用条件组合出人群；条件之间按「全部满足」或「任一满足」组合。保存后系统会评估人数。</p>
    <form id="addForm" data-no-guard>
      <div class="sg-row2">
        <div class="field"><label>分群名称 <span class="hint">· 必填</span></label><input name="name" required placeholder="如：30 天未活跃的付费用户"></div>
        <div class="field"><label>颜色</label><input name="color" type="color" value="#6366f1"></div>
      </div>
      <div class="field"><label>描述 <span class="hint">· 可选</span></label><input name="description" placeholder="给同事看的一句话"></div>
      <div class="field">
        <label>条件</label>
        <div class="rb-join">当用户 <select name="operator"><option value="and">全部满足</option><option value="or">任一满足</option></select> 以下条件：</div>
        <div id="rb" class="rb" style="margin-top:8px"></div>
        <div style="margin-top:8px"><button type="button" class="btn btn-ghost btn-sm" onclick="rbAdd()">+ 添加条件</button></div>
      </div>
      <label class="sg-check"><input type="checkbox" name="auto_update" value="1" checked> 自动更新（用户行为变化时自动进出分群）</label>
      <div class="acts">
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-ghost">取消</button>
        <button type="submit" class="btn btn-primary">创建分群</button>
      </div>
    </form>
  </div>
</div>

<script>
var RB_FIELDS = {
  total_spent:{l:'累计消费（元）',t:'num'}, courses_completed:{l:'完成课程数',t:'num'}, courses_enrolled:{l:'报名课程数',t:'num'},
  last_active_days:{l:'距上次活跃（天）',t:'num'}, total_logins:{l:'登录次数',t:'num'}, registered_days_ago:{l:'注册至今（天）',t:'num'},
  source:{l:'来源',t:'str'}, tags:{l:'标签',t:'str'}
};
var RB_OPS = {num:{gt:'大于',gte:'大于等于',lt:'小于',lte:'小于等于',eq:'等于',between:'介于 a,b'}, str:{eq:'等于',neq:'不等于',contains:'包含',in:'属于（逗号分隔）',not_in:'不属于'}};
function rbRow(r) {
  r = r || {field:'total_spent', operator:'gt', value:''};
  var d = document.createElement('div'); d.className = 'rb-row';
  var f = document.createElement('select'); Object.keys(RB_FIELDS).forEach(function(k){ var o = new Option(RB_FIELDS[k].l, k); if (k === r.field) o.selected = true; f.appendChild(o); });
  var op = document.createElement('select'); var v = document.createElement('input'); v.type = 'text'; v.value = Array.isArray(r.value) ? r.value.join(',') : (r.value || '');
  function fillOps(){ var t = RB_FIELDS[f.value].t, cur = op.value || r.operator; op.innerHTML=''; Object.keys(RB_OPS[t]).forEach(function(k){ var o = new Option(RB_OPS[t][k], k); if (k === cur) o.selected = true; op.appendChild(o); }); v.placeholder = t === 'num' ? '数值' : '文本'; }
  f.addEventListener('change', fillOps); fillOps();
  var x = document.createElement('button'); x.type = 'button'; x.className = 'ib'; x.title = '删除条件'; x.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>'; x.onclick = function(){ d.remove(); rbEmpty(); };
  d.appendChild(f); d.appendChild(op); d.appendChild(v); d.appendChild(x);
  return d;
}
function rbAdd(r){ var rb = document.getElementById('rb'); var e = rb.querySelector('.rb-empty'); if (e) e.remove(); rb.appendChild(rbRow(r)); }
function rbEmpty(){ var rb = document.getElementById('rb'); if (!rb.querySelector('.rb-row')) rb.innerHTML = '<div class="rb-empty">没有条件 = 匹配所有用户</div>'; }
function rbRead(){ return Array.prototype.map.call(document.querySelectorAll('#rb .rb-row'), function(row){ var s = row.querySelectorAll('select'), v = row.querySelector('input').value.trim(); var r = {field:s[0].value, operator:s[1].value, value:v}; if (r.operator === 'between' || r.operator === 'in' || r.operator === 'not_in') r.value = v.split(',').map(function(x){return x.trim()}).filter(Boolean); return r; }); }
function openAdd(){ var m = document.getElementById('addModal'); m.style.display = 'flex'; if (!document.querySelector('#rb .rb-row')) { document.getElementById('rb').innerHTML=''; rbAdd(); } setTimeout(function(){ m.querySelector('input[name=name]').focus(); }, 30); }
document.getElementById('addForm').onsubmit = function(e) {
  e.preventDefault();
  var fd = new FormData(this), data = Object.fromEntries(fd);
  data.rules = rbRead(); data.auto_update = !!fd.get('auto_update');
  fetch('../api/segment-manage.php', { method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?=csrf_token()?>'}, body: JSON.stringify(data) })
    .then(r => r.json()).then(d => { if (d.ok) location.reload(); else ofAlert(d.error || '创建失败', 'error'); });
};
function openToCrm(id, name, count) {
  document.getElementById('crmSegId').value = id;
  document.getElementById('crmSegName').textContent = name;
  document.getElementById('crmSegCount').textContent = count;
  document.getElementById('toCrmModal').style.display = 'flex';
}
function createFromTemplate(tpl) {
  fetch('../api/segment-manage.php', { method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?=csrf_token()?>'},
    body: JSON.stringify({action: 'create', name: tpl.name, description: tpl.description, color: tpl.color, rules: tpl.rules, operator: tpl.operator, auto_update: true})
  }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else ofAlert(d.error || '创建失败', 'error'); });
}
async function deleteSegment(id) {
  if (!await ofConfirm({title:'删除分群', message:'分群规则和人数统计会被删除；引用它的自动化不会再触发。', okText:'删除'})) return;
  fetch('../api/segment-manage.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=delete&id=' + id + '&csrf_token=<?=csrf_token()?>'}).then(r => r.json()).then(d => { if (d.ok) location.reload(); else ofAlert(d.error || '删除失败', 'error'); });
}
</script>
<?php admin_footer(); ?>
