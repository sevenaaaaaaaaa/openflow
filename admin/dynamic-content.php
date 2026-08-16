<?php
/**
 * Dynamic Content 管理 — 基于 URL 参数的动态内容规则
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/DynamicContent.php';
require_login();
require_perm('settings');

$rules = DynamicContent::all();
$message = '';

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $rule = DynamicContent::create($_POST);
        flash('success', '规则已创建：' . $rule['name']);
        header('Location: dynamic-content.php');
        exit;
    } elseif ($action === 'update') {
        $id = $_POST['rule_id'] ?? '';
        DynamicContent::update($id, $_POST);
        flash('success', '规则已更新');
        header('Location: dynamic-content.php');
        exit;
    } elseif ($action === 'delete') {
        $id = $_POST['rule_id'] ?? '';
        DynamicContent::delete($id);
        flash('success', '规则已删除');
        header('Location: dynamic-content.php');
        exit;
    } elseif ($action === 'toggle') {
        $id = $_POST['rule_id'] ?? '';
        DynamicContent::toggle($id);
        header('Location: dynamic-content.php');
        exit;
    }
}

// 查看详情
$viewId = $_GET['view'] ?? null;
$viewRule = $viewId ? DynamicContent::get($viewId) : null;
$analytics = null;
if ($viewRule) {
    $analytics = DynamicContent::getAnalytics($viewRule['id'], 14);
}

admin_header('Dynamic Content 动态内容');
?>
<style>
.rule-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;transition:.15s}
.rule-card:hover{border-color:var(--accent)}
.rule-header{display:flex;align-items:center;gap:12px;margin-bottom:8px}
.rule-toggle{width:40px;height:22px;border-radius:11px;background:var(--border);position:relative;cursor:pointer;transition:.2s}
.rule-toggle.on{background:var(--ok)}
.rule-toggle::after{content:'';position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.2s}
.rule-toggle.on::after{left:20px}
.rule-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.badge-sm{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600}
.condition-row{display:flex;gap:8px;margin-bottom:6px;align-items:center}
.condition-row select,.condition-row input{padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px}
.condition-row .del-btn{cursor:pointer;color:var(--text-3);font-size:16px;padding:2px 6px}
.condition-row .del-btn:hover{color:var(--danger)}
.action-row{display:flex;gap:8px;margin-bottom:6px;align-items:center;flex-wrap:wrap}
.action-row select,.action-row input{padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px}
.action-row .del-btn{cursor:pointer;color:var(--text-3);font-size:16px;padding:2px 6px}
.action-row .del-btn:hover{color:var(--danger)}
.chart-bar{display:inline-block;width:20px;background:var(--accent);border-radius:3px 3px 0 0;vertical-align:bottom;margin:0 1px}
.chart-label{font-size:9px;color:var(--muted);text-align:center;margin-top:2px}
.preview-box{background:var(--surface-2);border:1px dashed var(--border);border-radius:8px;padding:16px;margin-top:12px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0">🔀 Dynamic Content</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <span class="badge badge-gray"><?=count($rules)?> 条规则</span>
        <button onclick="document.getElementById('createDialog').style.display='flex'" class="btn btn-primary btn-sm">+ 创建规则</button>
      </div>
    </div>
    <p class="sub">根据 URL 参数（UTM、自定义参数）动态显示不同内容 · 支持卡片显隐与文字替换</p>

    <?php if ($viewRule): ?>
    <!-- ═══ 规则详情 + 数据分析 ═══ -->
    <div class="card">
      <div class="flex items-center gap-4 mb-4">
        <h2 style="margin-bottom:0"><?=htmlspecialchars($viewRule['name'])?></h2>
        <a href="dynamic-content.php" class="btn btn-ghost btn-sm ml-auto">← 返回列表</a>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div style="padding:12px;background:var(--surface-2);border-radius:8px">
          <div class="text-sm text-muted">目标</div>
          <div><strong><?=['global'=>'全站','page'=>'指定页面','article'=>$viewRule['target']['article_id'] ?: '指定文章'][$viewRule['target']['type']]?></strong></div>
          <?php if ($viewRule['target']['type'] === 'page'): ?><div class="text-sm text-muted"><?=htmlspecialchars($viewRule['target']['page'])?></div><?php endif; ?>
        </div>
        <div style="padding:12px;background:var(--surface-2);border-radius:8px">
          <div class="text-sm text-muted">匹配条件</div>
          <div><strong><?=count($viewRule['conditions'])?> 个条件</strong></div>
        </div>
      </div>

      <!-- 条件列表 -->
      <div class="mb-4">
        <div class="font-semibold mb-2">📋 匹配条件</div>
        <?php if (empty($viewRule['conditions'])): ?>
        <p class="text-sm text-muted">无条件 — 始终匹配</p>
        <?php else: foreach ($viewRule['conditions'] as $c): ?>
        <div class="badge-sm" style="background:var(--surface-2);margin:2px 4px">
          <span style="color:var(--accent)">URL参数</span>
          <code><?=htmlspecialchars($c['param'])?></code>
          <span style="color:var(--text-3)"><?=htmlspecialchars($c['operator'])?></span>
          <code><?=htmlspecialchars($c['value'] ?: '(空)')?></code>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- 动作列表 -->
      <div class="mb-4">
        <div class="font-semibold mb-2">⚡ 执行动作</div>
        <?php foreach ($viewRule['actions'] as $a): ?>
        <div class="badge-sm" style="background:var(--surface-2);margin:2px 4px">
          <span style="color:var(--accent)"><?=$a['type']?></span>
          <code><?=htmlspecialchars($a['selector'])?></code>
          <?php if ($a['type'] === 'replace_text'): ?>
          <span style="color:var(--text-3)">「<?=htmlspecialchars($a['text_find'])?>」→「<?=htmlspecialchars($a['text_replace'])?>」</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- 分析数据 -->
      <?php if ($analytics): ?>
      <div class="font-semibold mb-2">📊 近 14 天数据</div>
      <div style="display:flex;align-items:flex-end;gap:4px;height:80px;padding:8px 0">
        <?php
        $maxImp = max(array_merge([1], $analytics['impressions']));
        foreach (array_reverse($analytics['impressions']) as $date => $count):
            $h = $count > 0 ? max(4, round($count / $maxImp * 60)) : 0;
        ?>
        <div style="flex:1;text-align:center">
          <div class="chart-bar" style="height:<?=$h?>px;background:var(--accent)" title="<?=$count?> 次曝光"></div>
          <div class="chart-label"><?=date('d', strtotime($date))?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:16px;margin-top:8px">
        <div><span class="text-sm text-muted">总曝光:</span> <strong><?=array_sum($analytics['impressions'])?></strong></div>
        <div><span class="text-sm text-muted">总点击:</span> <strong><?=array_sum($analytics['clicks'])?></strong></div>
      </div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ═══ 规则列表 ═══ -->
    <?php if (empty($rules)): ?>
    <div class="card">
      <div class="empty" style="padding:40px">
        <div style="font-size:48px;margin-bottom:12px">🔀</div>
        <p>暂无动态内容规则</p>
        <p class="text-sm text-muted">创建规则后，访客根据 URL 参数（如 UTM）看到不同内容</p>
        <button onclick="document.getElementById('createDialog').style.display='flex'" class="btn btn-primary" style="margin-top:16px">创建第一条规则</button>
      </div>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px">
      <?php foreach ($rules as $r): ?>
      <div class="rule-card">
        <div class="rule-header">
          <div class="rule-toggle <?=!empty($r['enabled'])?'on':''?>" onclick="toggleRule('<?=htmlspecialchars($r['id'])?>')"></div>
          <strong style="flex:1"><?=htmlspecialchars($r['name'])?></strong>
          <a href="?view=<?=urlencode($r['id'])?>" class="btn btn-ghost btn-sm">📊</a>
          <form method="post" style="display:inline" onsubmit="return confirm('确认删除?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="rule_id" value="<?=htmlspecialchars($r['id'])?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">✕</button>
          </form>
        </div>
        <div class="rule-meta">
          <span class="badge-sm" style="background:var(--surface-2)"><?=['global'=>'🌐 全站','page'=>'📄 页面','article'=>'📝 文章'][$r['target']['type']]?></span>
          <?php if ($r['target']['type'] === 'page'): ?><span class="badge-sm" style="background:var(--surface-2)"><?=htmlspecialchars($r['target']['page'])?></span><?php endif; ?>
          <span class="badge-sm" style="background:var(--surface-2)"><?=count($r['conditions'])?> 条件</span>
          <span class="badge-sm" style="background:var(--surface-2)"><?=count($r['actions'])?> 动作</span>
        </div>
        <div style="margin-top:8px;font-size:12px;color:var(--muted)">
          <?php foreach (array_slice($r['conditions'], 0, 3) as $c): ?>
          <code style="background:var(--surface-2);padding:1px 4px;border-radius:3px;margin-right:4px"><?=$c['param']?> <?=$c['operator']?> <?=htmlspecialchars($c['value'])?></code>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:8px">
          <button onclick="editRule(<?=htmlspecialchars(json_encode($r, JSON_HEX_TAG | JSON_HEX_AMP))?>)" class="btn btn-ghost btn-sm">编辑</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ 使用示例 ═══ -->
    <div class="card" style="margin-top:20px">
      <h2>💡 使用示例</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-top:12px">
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <strong>UTM 来源差异化</strong>
          <p class="text-sm text-muted" style="margin-top:4px">来自 Google 的访客看到「搜索优化」方案，来自微信的看到「私域运营」方案</p>
          <code style="font-size:11px;color:var(--accent)">?utm_source=google</code>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <strong>A/B 测试</strong>
          <p class="text-sm text-muted" style="margin-top:4px">通过 <code>?variant=b</code> 显示不同标题和 CTA</p>
          <code style="font-size:11px;color:var(--accent)">?variant=a</code>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <strong>广告落地页</strong>
          <p class="text-sm text-muted" style="margin-top:4px">不同广告链接携带不同参数，页面自动匹配对应卡片</p>
          <code style="font-size:11px;color:var(--accent)">?ad=brand</code>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <strong>城市/地区定位</strong>
          <p class="text-sm text-muted" style="margin-top:4px">根据 <code>?city=beijing</code> 显示本地化内容</p>
          <code style="font-size:11px;color:var(--accent)">?city=shanghai</code>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══ 创建/编辑对话框 ═══ -->
<div id="createDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:90%;max-width:640px;max-height:85vh;overflow-y:auto">
    <div class="flex items-center gap-4 mb-4">
      <h2 id="dialogTitle" style="margin-bottom:0">创建动态规则</h2>
      <button onclick="closeDialog()" style="margin-left:auto;font-size:20px;cursor:pointer;background:none;border:none">✕</button>
    </div>
    <form method="post" id="ruleForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="rule_id" id="formRuleId" value="">

      <div class="field"><label>规则名称</label><input type="text" name="name" id="formName" placeholder="如：微信访客差异化" required></div>

      <div class="field-row">
        <div class="field">
          <label>应用范围</label>
          <select name="target_type" id="formTargetType" onchange="toggleTargetPage()">
            <option value="global">全站</option>
            <option value="page">指定页面</option>
            <option value="article">指定文章</option>
          </select>
        </div>
        <div class="field" id="targetPageField" style="display:none">
          <label>目标页面</label>
          <select name="target_page" id="formTargetPage">
            <option value="index">首页</option>
            <option value="about">关于我们</option>
            <option value="capability">核心能力</option>
            <option value="solutions">解决方案</option>
            <option value="cases">案例</option>
            <option value="consultation">咨询</option>
            <option value="courses">课程</option>
            <option value="live">直播</option>
          </select>
        </div>
        <div class="field" id="targetArticleField" style="display:none">
          <label>文章 ID</label>
          <input type="text" name="target_article_id" id="formArticleId" placeholder="article_xxx">
        </div>
      </div>

      <div class="field"><label>优先级 <span class="hint">· 数字越大越优先</span></label><input type="number" name="priority" id="formPriority" value="0" min="0" max="100"></div>

      <!-- 条件 -->
      <div class="field">
        <label>匹配条件 <span class="hint">· 所有条件必须同时满足 · 支持画像参数：is_member / member_level / is_vip / total_spent / source / tags(contains)</span></label>
        <div id="conditionsContainer">
          <div class="condition-row">
            <input type="text" name="cond_param[]" placeholder="URL参数名 (如 utm_source)" style="width:160px">
            <select name="cond_operator[]">
              <option value="equals">等于</option>
              <option value="not_equals">不等于</option>
              <option value="contains">包含</option>
              <option value="starts_with">开头是</option>
              <option value="ends_with">结尾是</option>
              <option value="matches">正则匹配</option>
              <option value="exists">存在</option>
              <option value="not_exists">不存在</option>
            </select>
            <input type="text" name="cond_value[]" placeholder="值" style="width:160px">
            <span class="del-btn" onclick="this.closest('.condition-row').remove()">✕</span>
          </div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addCondition()" style="margin-top:6px">+ 添加条件</button>
      </div>

      <!-- 动作 -->
      <div class="field">
        <label>执行动作</label>
        <div id="actionsContainer">
          <div class="action-row">
            <select name="action_type[]" onchange="toggleActionFields(this)" style="width:130px">
              <option value="show_card">显示元素</option>
              <option value="hide_card">隐藏元素</option>
              <option value="replace_text">替换文字</option>
              <option value="add_class">添加样式</option>
              <option value="change_bg">改变背景色</option>
            </select>
            <input type="text" name="action_selector[]" placeholder="CSS 选择器 (如 .card-3)" style="width:180px">
            <input type="text" name="action_find[]" placeholder="查找文本" style="width:120px;display:none" class="replace-fields">
            <input type="text" name="action_replace[]" placeholder="替换为" style="width:120px;display:none" class="replace-fields">
            <span class="del-btn" onclick="this.closest('.action-row').remove()">✕</span>
          </div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addAction()" style="margin-top:6px">+ 添加动作</button>
      </div>

      <div class="field">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="enabled" id="formEnabled" checked> 启用规则
        </label>
      </div>

      <div class="flex-between" style="margin-top:20px">
        <button type="button" class="btn btn-ghost" onclick="closeDialog()">取消</button>
        <button type="submit" class="btn btn-primary" id="formSubmitBtn">创建规则</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleTargetPage() {
  var t = document.querySelector('[name="target_type"]').value;
  document.getElementById('targetPageField').style.display = t === 'page' ? '' : 'none';
  document.getElementById('targetArticleField').style.display = t === 'article' ? '' : 'none';
}
function addCondition() {
  var c = document.getElementById('conditionsContainer');
  var row = document.createElement('div');
  row.className = 'condition-row';
  row.innerHTML =
    '<input type="text" name="cond_param[]" placeholder="URL参数名" style="width:160px">' +
    '<select name="cond_operator[]">' +
      '<option value="equals">等于</option><option value="not_equals">不等于</option>' +
      '<option value="contains">包含</option><option value="starts_with">开头是</option>' +
      '<option value="ends_with">结尾是</option><option value="matches">正则匹配</option>' +
      '<option value="exists">存在</option><option value="not_exists">不存在</option>' +
    '</select>' +
    '<input type="text" name="cond_value[]" placeholder="值" style="width:160px">' +
    '<span class="del-btn" onclick="this.closest(\'.condition-row\').remove()">✕</span>';
  c.appendChild(row);
}
function addAction() {
  var c = document.getElementById('actionsContainer');
  var row = document.createElement('div');
  row.className = 'action-row';
  row.innerHTML =
    '<select name="action_type[]" onchange="toggleActionFields(this)" style="width:130px">' +
      '<option value="show_card">显示元素</option><option value="hide_card">隐藏元素</option>' +
      '<option value="replace_text">替换文字</option><option value="add_class">添加样式</option>' +
      '<option value="change_bg">改变背景色</option>' +
    '</select>' +
    '<input type="text" name="action_selector[]" placeholder="CSS 选择器" style="width:180px">' +
    '<input type="text" name="action_find[]" placeholder="查找文本" style="width:120px;display:none" class="replace-fields">' +
    '<input type="text" name="action_replace[]" placeholder="替换为" style="width:120px;display:none" class="replace-fields">' +
    '<span class="del-btn" onclick="this.closest(\'.action-row\').remove()">✕</span>';
  c.appendChild(row);
}
function toggleActionFields(sel) {
  var row = sel.closest('.action-row');
  var show = sel.value === 'replace_text';
  row.querySelectorAll('.replace-fields').forEach(function(el) { el.style.display = show ? '' : 'none'; });
}
function toggleRule(id) {
  var fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('rule_id', id);
  fd.append('csrf_token', '<?=csrf_token()?>');
  fetch('dynamic-content.php', { method: 'POST', body: fd }).then(function() { location.reload(); });
}
function editRule(rule) {
  document.getElementById('dialogTitle').textContent = '编辑规则';
  document.getElementById('formSubmitBtn').textContent = '保存修改';
  document.getElementById('formRuleId').value = rule.id;
  document.getElementById('formName').value = rule.name;
  document.getElementById('formTargetType').value = rule.target.type;
  document.getElementById('formTargetPage').value = rule.target.page || '';
  document.getElementById('formArticleId').value = rule.target.article_id || '';
  document.getElementById('formPriority').value = rule.priority || 0;
  document.getElementById('formEnabled').checked = !!rule.enabled;
  toggleTargetPage();

  // 清空并填充条件
  var cc = document.getElementById('conditionsContainer');
  cc.innerHTML = '';
  (rule.conditions || []).forEach(function(c) {
    addCondition();
    var last = cc.lastElementChild;
    last.querySelector('[name="cond_param[]"]').value = c.param;
    last.querySelector('[name="cond_operator[]"]').value = c.operator;
    last.querySelector('[name="cond_value[]"]').value = c.value;
  });
  if (!rule.conditions || !rule.conditions.length) addCondition();

  // 清空并填充动作
  var ac = document.getElementById('actionsContainer');
  ac.innerHTML = '';
  (rule.actions || []).forEach(function(a) {
    addAction();
    var last = ac.lastElementChild;
    last.querySelector('[name="action_type[]"]').value = a.type;
    last.querySelector('[name="action_selector[]"]').value = a.selector;
    if (a.text_find !== undefined) last.querySelector('[name="action_find[]"]').value = a.text_find;
    if (a.text_replace !== undefined) last.querySelector('[name="action_replace[]"]').value = a.text_replace;
    toggleActionFields(last.querySelector('[name="action_type[]"]'));
  });
  if (!rule.actions || !rule.actions.length) addAction();

  document.getElementById('createDialog').style.display = 'flex';
}
function closeDialog() {
  document.getElementById('createDialog').style.display = 'none';
  document.getElementById('dialogTitle').textContent = '创建动态规则';
  document.getElementById('formSubmitBtn').textContent = '创建规则';
  document.getElementById('ruleForm').reset();
  document.getElementById('formRuleId').value = '';
  var cc = document.getElementById('conditionsContainer');
  cc.innerHTML = '';
  addCondition();
  var ac = document.getElementById('actionsContainer');
  ac.innerHTML = '';
  addAction();
}
// 初始化
addCondition();
addAction();
</script>
<?php admin_footer(); ?>
