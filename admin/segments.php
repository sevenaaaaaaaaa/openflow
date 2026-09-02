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
<div class="admin-layout">
  <?php admin_sidebar('segments'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 用户分群</h1>
      <div class="flex gap-2 ml-auto">
        <a href="?eval" class="btn btn-ghost">重新评估</a>
        <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary">+ 新建分群</button>
      </div>
    </div>

    <?php if ($importMsg): ?><?=msg('success', $importMsg)?><?php endif; ?>
    <?php if ($importErr): ?><?=msg('error', $importErr)?><?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--accent)"><?=count($segments)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">分群总数</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--ok)"><?=count(array_filter($segments, fn($s) => $s['auto_update']))?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">自动更新</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--warn)"><?=$totalMembers?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">总覆盖人数</div>
      </div>
      <div style="padding:20px;background:var(--surface);border-radius:12px;border:1px solid var(--border)">
        <div style="font-size:28px;font-weight:700;color:var(--muted)"><?=count($templates)?></div>
        <div style="color:var(--muted);font-size:13px;margin-top:4px">预设模板</div>
      </div>
    </div>

    <?php if (!empty($segments)): ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;margin-bottom:32px">
        <?php foreach ($segments as $seg): ?>
          <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);overflow:hidden">
            <div style="height:6px;background:<?=h($seg['color'] ?: 'var(--accent)')?>"></div>
            <div style="padding:20px">
              <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
                <div>
                  <h3 style="margin:0;font-size:16px;color:var(--text)"><?=h($seg['name'])?></h3>
                  <p style="margin:4px 0 0;font-size:13px;color:var(--muted)"><?=h($seg['description'] ?: '暂无描述')?></p>
                </div>
                <span style="padding:4px 12px;border-radius:20px;font-size:20px;font-weight:700;background:var(--surface-2);color:var(--accent)"><?=$seg['member_count']?></span>
              </div>
              <div style="margin-bottom:12px">
                <div style="font-size:12px;color:var(--muted);margin-bottom:6px">规则 (<?=ucfirst($seg['operator'] ?? 'and')?> 匹配)</div>
                <?php foreach (($seg['rules'] ?? []) as $rule): ?>
                  <div style="padding:4px 10px;background:var(--surface-2);border-radius:6px;font-size:12px;color:var(--text);margin-bottom:4px;display:inline-block;margin-right:4px">
                    <?=h($rule['field'] ?? '')?> <?=h($rule['operator'] ?? '')?> <?=h(is_array($rule['value']) ? implode(',', $rule['value']) : $rule['value'] ?? '')?>
                  </div>
                <?php endforeach; ?>
                <?php if (empty($seg['rules'])): ?>
                  <span style="font-size:12px;color:var(--muted)">无规则（匹配所有）</span>
                <?php endif; ?>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--muted)">
                <span><?=$seg['auto_update'] ? '🔄 自动更新' : '⏸️ 手动'?></span>
                <span><?=$seg['last_evaluated'] ? '上次评估: '.h($seg['last_evaluated']) : '未评估'?></span>
              </div>
              <div style="display:flex;gap:8px;margin-top:12px">
                <?php if (has_perm('crm')): ?>
                <button onclick="openToCrm('<?=h($seg['id'])?>', '<?=h(addslashes($seg['name']))?>', <?=(int)$seg['member_count']?>)" style="padding:4px 12px;border-radius:6px;border:1px solid var(--accent);color:var(--accent);background:none;cursor:pointer;font-size:12px">导入 CRM</button>
                <?php endif; ?>
                <button onclick="deleteSegment('<?=h($seg['id'])?>')" style="padding:4px 12px;border-radius:6px;border:1px solid var(--danger);color:var(--danger);background:none;cursor:pointer;font-size:12px">删除</button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="background:var(--surface);border-radius:12px;border:1px solid var(--border);padding:20px">
      <h3 style="margin:0 0 16px;font-size:16px">📦 预设模板</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px">
        <?php foreach ($templates as $tpl): ?>
          <div style="padding:16px;background:var(--surface-2);border-radius:10px;border:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
              <div style="width:12px;height:12px;border-radius:50%;background:<?=h($tpl['color'])?>"></div>
              <span style="font-weight:600;color:var(--text)"><?=h($tpl['name'])?></span>
            </div>
            <p style="margin:0 0 12px;font-size:13px;color:var(--muted)"><?=h($tpl['description'])?></p>
            <button onclick="createFromTemplate(<?=htmlspecialchars(json_encode($tpl), ENT_QUOTES)?>)" style="padding:4px 12px;border-radius:6px;border:1px solid var(--accent);color:var(--accent);background:none;cursor:pointer;font-size:12px">使用模板</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- 分群 → CRM -->
<div id="toCrmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <form method="post" style="background:var(--surface);border-radius:16px;padding:28px;width:460px;max-width:90vw">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="to_crm">
    <input type="hidden" name="segment_id" id="crmSegId">
    <h3 style="margin:0 0 6px;font-size:17px">导入 CRM</h3>
    <p style="margin:0 0 18px;font-size:13px;color:var(--muted)">
      分群 <b id="crmSegName" style="color:var(--text)"></b>，约 <b id="crmSegCount"></b> 人。
      已存在的线索默认跳过，不会覆盖销售填过的内容。没有有效邮箱的画像会被略过。
    </p>
    <div style="margin-bottom:14px">
      <label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">初始阶段</label>
      <select name="stage" style="width:100%;padding:9px;border-radius:8px;border:1px solid var(--border);background:var(--surface-2);color:var(--text)">
        <?php foreach (crm_stages() as $k => $v): ?>
          <option value="<?=h($k)?>"><?=h(is_array($v) ? ($v['label'] ?? $k) : $v)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="margin-bottom:14px">
      <label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">负责人（可留空）</label>
      <input type="text" name="owner" placeholder="销售同学的名字" style="width:100%;padding:9px;border-radius:8px;border:1px solid var(--border);background:var(--surface-2);color:var(--text)">
    </div>
    <label style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--muted);margin-bottom:8px">
      <input type="checkbox" name="update_existing" value="1"> 已存在的线索补齐空字段（姓名/电话/公司/来源）
    </label>
    <label style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--muted);margin-bottom:18px">
      <input type="checkbox" name="dry_run" value="1" checked> 先试运行，只看会导入多少，不写入
    </label>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn btn-primary" style="padding:8px 18px;border-radius:8px;border:none;background:var(--accent);color:#fff;cursor:pointer">开始导入</button>
      <button type="button" onclick="document.getElementById('toCrmModal').style.display='none'" style="padding:8px 18px;border-radius:8px;border:1px solid var(--border);background:none;color:var(--text);cursor:pointer">取消</button>
    </div>
  </form>
</div>

<script>
function openToCrm(id, name, count) {
  document.getElementById('crmSegId').value = id;
  document.getElementById('crmSegName').textContent = name;
  document.getElementById('crmSegCount').textContent = count;
  document.getElementById('toCrmModal').style.display = 'flex';
}
</script>

<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:520px;max-width:90vw;max-height:90vh;overflow-y:auto">
    <h3 style="margin:0 0 20px">新建分群</h3>
    <form id="addForm">
      <div style="margin-bottom:16px"><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">分群名称</label><input name="name" required style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"></div>
      <div style="margin-bottom:16px"><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">描述</label><input name="description" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)"></div>
      <div style="margin-bottom:16px"><label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">颜色</label><input name="color" type="color" value="#6366f1" style="width:60px;height:36px;border:none;cursor:pointer"></div>
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">匹配方式</label>
        <select name="operator" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text)">
          <option value="and">全部匹配 (AND)</option>
          <option value="or">任一匹配 (OR)</option>
        </select>
      </div>
      <div style="margin-bottom:20px">
        <label style="display:block;font-size:13px;color:var(--muted);margin-bottom:6px">规则 JSON</label>
        <textarea name="rules" rows="4" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-family:var(--font-mono);font-size:13px" placeholder='[{"field":"total_spent","operator":"gt","value":"100"}]'></textarea>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">可用字段: total_spent, courses_completed, courses_enrolled, last_active_days, total_logins, source, tags, registered_days_ago</div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-ghost">取消</button>
        <button type="submit" class="btn btn-primary">创建</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('addForm').onsubmit = function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const data = Object.fromEntries(fd);
  data.rules = data.rules ? JSON.parse(data.rules) : [];
  fetch('../api/segment-manage.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?=csrf_token()?>'},
    body: JSON.stringify(data)
  }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else ofAlert(d.error || '创建失败'); });
};
function createFromTemplate(tpl) {
  fetch('../api/segment-manage.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?=csrf_token()?>'},
    body: JSON.stringify({action: 'create', name: tpl.name, description: tpl.description, color: tpl.color, rules: tpl.rules, operator: tpl.operator, auto_update: true})
  }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else ofAlert(d.error || '创建失败'); });
}
async function deleteSegment(id) {
  if (!await ofConfirm('确定删除？')) return;
  fetch('../api/segment-manage.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=delete&id=' + id + '&csrf_token=<?=csrf_token()?>'}).then(r => r.json()).then(d => { if (d.ok) location.reload(); else ofAlert(d.error || '删除失败'); });
}
</script>
<?php admin_footer(); ?>
