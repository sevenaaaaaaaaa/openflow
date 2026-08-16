<?php
/**
 * 调研系统 — 问卷管理（创建/编辑/回收）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/survey-lib.php';
require_login();
require_perm('settings');

$surveys = survey_get_surveys();
$org = survey_get_org();
$message = '';
$error = '';

// 删除问卷
if (isset($_GET['delete'])) {
    $surveys = array_values(array_filter($surveys, fn($s) => $s['id'] !== $_GET['delete']));
    survey_save_surveys($surveys);
    flash('success', '问卷已删除');
    header('Location: /xmp/survey');
    exit;
}

// 保存问卷（新增或更新）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_survey'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    if (empty($title)) {
        $error = '问卷标题不能为空';
    } else {
        $questions = [];
        foreach (($_POST['q_title'] ?? []) as $i => $qt) {
            if (empty(trim($qt))) continue;
            $questions[] = [
                'id' => 'q' . $i,
                'title' => trim($qt),
                'type' => $_POST['q_type'][$i] ?? 'single',
                'required' => isset($_POST['q_required'][$i]),
                'options' => array_filter(array_map('trim', explode("\n", $_POST['q_options'][$i] ?? ''))),
                'scale' => (int)($_POST['q_scale'][$i] ?? 5),
            ];
        }
        if (empty($questions)) {
            $error = '请至少添加一个题目';
        } else {
            $data = [
                'title' => $title,
                'description' => trim($_POST['description'] ?? ''),
                'type' => $_POST['type'] ?? 'anonymous', // anonymous / named
                'status' => $_POST['status'] ?? 'draft', // draft / active / closed
                'company_scope' => $_POST['company_scope'] ?? 'all', // all / specific
                'template' => $_POST['template'] ?? 'classic', // classic / cards / gamified / immersive
                'company_ids' => array_filter($_POST['company_ids'] ?? []),
                'questions' => $questions,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if (empty($id)) {
                $data['id'] = 'survey_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['creator'] = $_SESSION['admin_user'] ?? '';
                $surveys[] = $data;
            } else {
                foreach ($surveys as &$s) if ($s['id'] === $id) { $s = array_merge($s, $data); break; }
                unset($s);
            }
            survey_save_surveys($surveys);
            flash('success', '问卷已保存');
            header('Location: /xmp/survey');
            exit;
        }
    }
}

$editSurvey = null;
if (isset($_GET['edit'])) {
    foreach ($surveys as $s) if ($s['id'] === $_GET['edit']) { $editSurvey = $s; break; }
}

// 当前用户角色（用于决定是否可创建/回收）
$me = survey_current_user();
$isAdmin = ($_SESSION['admin_role'] ?? '') === 'admin' || ($me['role'] ?? '') === 'company_admin';

$qTypes = ['single' => '单选', 'multi' => '多选', 'rating' => '评分', 'text' => '文本'];

admin_header('调研系统');
?>
<style>
.q-box{border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;background:var(--surface)}
.q-box .q-head{display:flex;gap:8px;align-items:center;margin-bottom:10px}
.status-pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;color:#fff}
</style>
<div class="admin-layout">
  <?php admin_sidebar('survey'); ?>
  <div class="main">
    <h1>📋 调研系统</h1>
    <p class="sub">创建问卷 · 回收结果 · 按角色查看统计 · 付费咨询官方 Agent</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap">
      <a href="survey" class="btn btn-primary">📋 问卷管理</a>
      <a href="survey-stats.php" class="btn btn-ghost">📊 统计查看</a>
      <a href="survey-org.php" class="btn btn-ghost">🏢 组织架构</a>
      <a href="survey-agent.php" class="btn btn-ghost" style="margin-left:auto">🤖 官方 Agent 咨询</a>
    </div>

    <?php if ($editSurvey): ?>
    <!-- 编辑/创建问卷 -->
    <form method="post" id="surveyForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?=htmlspecialchars($editSurvey['id'] ?? '')?>">
      <div class="card">
        <h2><?=empty($editSurvey['id'])?'➕ 创建新问卷':'✏️ 编辑问卷'?></h2>
        <div class="field-row">
          <div class="field"><label>问卷标题 <span class="hint">· 必填</span></label><input type="text" name="title" value="<?=htmlspecialchars($editSurvey['title'] ?? '')?>" required></div>
          <div class="field"><label>问卷类型</label><select name="type"><option value="anonymous" <?=($editSurvey['type']??'')==='anonymous'?'selected':''?>>匿名问卷</option><option value="named" <?=($editSurvey['type']??'')==='named'?'selected':''?>>实名问卷</option></select></div>
          <div class="field"><label>填写模板 <span class="hint">· 现代化表单体验</span></label><select name="template">
            <option value="classic" <?=($editSurvey['template']??'classic')==='classic'?'selected':''?>>📄 经典（一次显示全部）</option>
            <option value="cards" <?=($editSurvey['template']??'')==='cards'?'selected':''?>>🃏 卡片滑动（一题一卡）</option>
            <option value="gamified" <?=($editSurvey['template']??'')==='gamified'?'selected':''?>>🎮 游戏化（进度条+动效）</option>
            <option value="immersive" <?=($editSurvey['template']??'')==='immersive'?'selected':''?>>🌌 沉浸式（全屏渐变）</option>
          </select></div>
        </div>
        <div class="field"><label>问卷说明</label><textarea name="description" rows="2"><?=htmlspecialchars($editSurvey['description'] ?? '')?></textarea></div>
        <div class="field-row">
          <div class="field"><label>状态</label><select name="status">
            <option value="draft" <?=($editSurvey['status']??'')==='draft'?'selected':''?>>草稿</option>
            <option value="active" <?=($editSurvey['status']??'')==='active'?'selected':''?>>发布中</option>
            <option value="closed" <?=($editSurvey['status']??'')==='closed'?'selected':''?>>已结束</option>
          </select></div>
          <div class="field"><label>投放范围</label><select name="company_scope">
            <option value="all" <?=($editSurvey['company_scope']??'')==='all'?'selected':''?>>全部公司</option>
            <option value="specific" <?=($editSurvey['company_scope']??'')==='specific'?'selected':''?>>指定公司</option>
          </select></div>
        </div>
        <div class="field" id="companyScopeBox" style="display:<?=($editSurvey['company_scope']??'')==='specific'?'block':'none'?>">
          <label>目标公司</label>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
            <?php foreach ($org['companies'] ?? [] as $c): ?>
            <label style="display:inline-flex;align-items:center;gap:4px;font-size:13px;cursor:pointer"><input type="checkbox" name="company_ids[]" value="<?=htmlspecialchars($c['id'])?>" <?=in_array($c['id'], $editSurvey['company_ids'] ?? [])?'checked':''?> style="width:15px;height:15px"> <?=htmlspecialchars($c['name'])?></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
          <h2 style="margin-bottom:0">❓ 题目列表</h2>
          <span style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" class="btn btn-ghost btn-sm" onclick="openImportDialog()">📄 导入文档</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="openAIDialog()">✨ AI 生成</button>
          </span>
        </div>
        <div id="questionList">
          <?php foreach ($editSurvey['questions'] ?? [] as $qi => $q): ?>
          <div class="q-box" data-index="<?=$qi?>">
            <div class="q-head">
              <input type="text" name="q_title[]" value="<?=htmlspecialchars($q['title'])?>" placeholder="题目内容" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px">
              <select name="q_type[]" onchange="qTypeChange(this)" style="width:100px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
                <?php foreach ($qTypes as $tk => $tl): ?><option value="<?=$tk?>" <?=($q['type']??'')===$tk?'selected':''?>><?=$tl?></option><?php endforeach; ?>
              </select>
              <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="q_required[]" value="1" <?=($q['required']??false)?'checked':''?> style="width:15px;height:15px">必答</label>
              <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.q-box').remove()">✕</button>
            </div>
            <div class="q-options" style="display:<?=in_array($q['type']??'','single,multi')?'block':'none'?>">
              <textarea name="q_options[]" rows="3" placeholder="选项（每行一个）&#10;如：&#10;非常满意&#10;满意&#10;一般" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"><?=htmlspecialchars(implode("\n", $q['options'] ?? []))?></textarea>
            </div>
            <div class="q-scale" style="display:<?=($q['type']??'')==='rating'?'block':'none'?>">
              <label style="font-size:12px">评分等级：</label><input type="number" name="q_scale[]" value="<?=htmlspecialchars($q['scale'] ?? 5)?>" min="3" max="10" style="width:80px;padding:6px;border:1.5px solid var(--border);border-radius:6px">
            </div>
            <input type="hidden" name="q_text_hint[]" value="">
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addQuestion('single')">+ 单选</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addQuestion('multi')">+ 多选</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addQuestion('rating')">+ 评分</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addQuestion('text')">+ 文本</button>
      </div>

      <button type="submit" name="save_survey" class="btn btn-primary">保存问卷</button>
      <a href="survey" class="btn btn-ghost">取消</a>
    </form>

    <!-- 导入文档弹窗 -->
    <div style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);align-items:center;justify-content:center" id="importDialog" onclick="if(event.target===this)closeImportDialog()">
      <div style="background:var(--surface);border-radius:16px;padding:28px;width:620px;max-width:92vw;max-height:82vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <h2 style="margin-bottom:0">📄 从文档导入问卷</h2>
          <button class="btn btn-ghost btn-sm" onclick="closeImportDialog()">✕</button>
        </div>
        <p class="text-sm text-muted mb-4">支持 TXT / MD / DOCX / PDF · 每行一题，选项用 A/B/C 或 ①②③ · 解析后可预览再确认</p>
        <div class="field">
          <input type="file" id="importFile" accept=".txt,.md,.docx,.pdf,.text">
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-primary" onclick="doImport()">解析文档</button>
          <button class="btn btn-ghost" onclick="closeImportDialog()">取消</button>
        </div>
        <div id="importResult" style="margin-top:16px"></div>
        <div id="importPreview" style="display:none;margin-top:16px">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
            <strong style="font-size:14px" id="importCount"></strong>
            <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="confirmImport()">✅ 添加到题目列表</button>
          </div>
          <div id="importList" style="max-height:300px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;padding:8px"></div>
        </div>
      </div>
    </div>

    <!-- AI 生成弹窗 -->
    <div style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);align-items:center;justify-content:center" id="aiGenDialog" onclick="if(event.target===this)closeAIGenDialog()">
      <div style="background:var(--surface);border-radius:16px;padding:28px;width:560px;max-width:92vw">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <h2 style="margin-bottom:0">✨ AI 生成问卷</h2>
          <button class="btn btn-ghost btn-sm" onclick="closeAIGenDialog()">✕</button>
        </div>
        <div class="field"><label>调研主题</label><input type="text" id="aiTopic" placeholder="如：网站满意度、内容质量、客户反馈、新手引导体验..."></div>
        <div class="field-row">
          <div class="field"><label>题目数量</label><input type="number" id="aiCount" value="10" min="3" max="20"></div>
          <div class="field"><label style="display:flex;align-items:center;gap:6px;margin-top:26px;cursor:pointer"><input type="checkbox" id="aiRating" checked style="width:16px;height:16px"> 包含评分题</label></div>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-primary" onclick="doAIGenerate()">✨ 生成问卷</button>
          <button class="btn btn-ghost" onclick="closeAIGenDialog()">取消</button>
        </div>
        <div id="aiResult" style="margin-top:16px"></div>
        <div id="aiPreview" style="display:none;margin-top:16px">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
            <strong style="font-size:14px" id="aiCountLabel"></strong>
            <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="confirmAI()">✅ 添加到题目列表</button>
          </div>
          <div id="aiList" style="max-height:300px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;padding:8px"></div>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- 问卷列表 -->
    <div class="flex items-center gap-4 mb-4">
      <h2 style="margin-bottom:0">全部问卷</h2>
      <a href="survey.php?edit=new" class="btn btn-primary btn-sm ml-auto">➕ 创建问卷</a>
    </div>
    <div class="card" style="padding:0;overflow-x:auto">
      <table>
        <thead><tr><th>问卷</th><th>类型</th><th>状态</th><th>题目数</th><th>回收数</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($surveys)): ?>
          <tr><td colspan="7" class="empty">暂无问卷，点击右上角创建</td></tr>
          <?php endif; ?>
          <?php foreach ($surveys as $s):
            $responses = survey_get_responses($s['id']);
            $statusColor = ['draft' => 'var(--faint)', 'active' => 'var(--ok)', 'closed' => 'var(--warn)'][$s['status'] ?? 'draft'];
            $statusLabel = ['draft' => '草稿', 'active' => '发布中', 'closed' => '已结束'][$s['status'] ?? 'draft'];
          ?>
          <tr>
            <td><strong><?=htmlspecialchars($s['title'])?></strong><?php if (!empty($s['description'])): ?><div class="text-sm text-muted" style="font-size:12px"><?=htmlspecialchars(mb_substr($s['description'],0,50))?></div><?php endif; ?></td>
            <td class="text-sm text-muted"><?=($s['type']==='named'?'实名':'匿名')?></td>
            <td><span class="status-pill" style="background:<?=$statusColor?>"><?=$statusLabel?></span></td>
            <td><?=count($s['questions'])?></td>
            <td><strong><?=count($responses)?></strong></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($s['created_at']??'',0,10))?></td>
            <td style="white-space:nowrap">
              <?php if (($s['status'] ?? '') === 'active'): ?>
              <a href="../survey.php?id=<?=urlencode($s['id'])?>" class="btn btn-ghost btn-sm" target="_blank" title="打开填写页">✍️ 填写</a>
              <?php endif; ?>
              <a href="survey-stats.php?survey=<?=urlencode($s['id'])?>" class="btn btn-ghost btn-sm">📊 统计</a>
              <a href="survey.php?edit=<?=urlencode($s['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <a href="survey.php?delete=<?=urlencode($s['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除该问卷及全部回收数据?')">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function qTypeChange(sel) {
  var box = sel.closest('.q-box');
  var type = sel.value;
  box.querySelector('.q-options').style.display = (type === 'single' || type === 'multi') ? 'block' : 'none';
  box.querySelector('.q-scale').style.display = type === 'rating' ? 'block' : 'none';
}
function addQuestion(type) {
  var list = document.getElementById('questionList');
  var idx = list.querySelectorAll('.q-box').length;
  var d = document.createElement('div');
  d.className = 'q-box';
  d.dataset.index = idx;
  d.innerHTML =
    '<div class="q-head">' +
      '<input type="text" name="q_title[]" placeholder="题目内容" style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px">' +
      '<select name="q_type[]" onchange="qTypeChange(this)" style="width:100px;padding:8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">' +
        '<option value="single" ' + (type==='single'?'selected':'') + '>单选</option>' +
        '<option value="multi" ' + (type==='multi'?'selected':'') + '>多选</option>' +
        '<option value="rating" ' + (type==='rating'?'selected':'') + '>评分</option>' +
        '<option value="text" ' + (type==='text'?'selected':'') + '>文本</option>' +
      '</select>' +
      '<label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="q_required[]" value="1" style="width:15px;height:15px">必答</label>' +
      '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.q-box\').remove()">✕</button>' +
    '</div>' +
    '<div class="q-options" style="display:' + ((type==='single'||type==='multi')?'block':'none') + '">' +
      '<textarea name="q_options[]" rows="3" placeholder="选项（每行一个）&#10;如：&#10;非常满意&#10;满意&#10;一般" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px"></textarea>' +
    '</div>' +
    '<div class="q-scale" style="display:' + (type==='rating'?'block':'none') + '">' +
      '<label style="font-size:12px">评分等级：</label><input type="number" name="q_scale[]" value="5" min="3" max="10" style="width:80px;padding:6px;border:1.5px solid var(--border);border-radius:6px">' +
    '</div>' +
    '<input type="hidden" name="q_text_hint[]" value="">';
  list.appendChild(d);
}
// 投放范围联动
document.querySelector('select[name="company_scope"]')?.addEventListener('change', function() {
  document.getElementById('companyScopeBox').style.display = this.value === 'specific' ? 'block' : 'none';
});

// ─── 导入文档 ───
var IMPORTED_QUESTIONS = [];
function openImportDialog() { document.getElementById('importDialog').style.display = 'flex'; document.getElementById('importPreview').style.display = 'none'; document.getElementById('importResult').innerHTML = ''; }
function closeImportDialog() { document.getElementById('importDialog').style.display = 'none'; }
function doImport() {
  var fileInput = document.getElementById('importFile');
  var file = fileInput.files[0];
  if (!file) { alert('请选择文件'); return; }
  var result = document.getElementById('importResult');
  result.innerHTML = '<div class="spinner" style="text-align:center;padding:20px;color:var(--text-3)">⏳ 正在解析文档...</div>';
  var fd = new FormData();
  fd.append('file', file);
  fetch('../api/survey-import.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.ok) { result.innerHTML = '<div class="msg msg-error">' + d.error + '</div>'; return; }
      IMPORTED_QUESTIONS = d.questions;
      result.innerHTML = '<div class="msg msg-success">✅ 已解析出 ' + d.count + ' 道题目</div>';
      document.getElementById('importCount').textContent = '共解析出 ' + d.count + ' 道题目：';
      var list = document.getElementById('importList');
      list.innerHTML = d.questions.map(function(q, i) {
        var typeLabel = { single: '单选', multi: '多选', rating: '评分', text: '文本' }[q.type] || '文本';
        var opts = q.options && q.options.length ? '<div style="font-size:12px;color:var(--text-3);margin-top:4px">' + q.options.join(' / ') + '</div>' : '';
        return '<div style="padding:8px 12px;border-bottom:1px solid var(--border);font-size:13px">' +
          '<span style="color:var(--text-3);font-weight:700">' + (i+1) + '.</span> ' + q.title +
          ' <span class="badge badge-gray" style="font-size:10px">' + typeLabel + '</span>' + opts + '</div>';
      }).join('');
      document.getElementById('importPreview').style.display = 'block';
    })
    .catch(function() { result.innerHTML = '<div class="msg msg-error">请求失败，请重试</div>'; });
}
function confirmImport() {
  IMPORTED_QUESTIONS.forEach(function(q) {
    addQuestion(q.type || 'text');
    // 填充最后一个 q-box
    var boxes = document.querySelectorAll('#questionList .q-box');
    var box = boxes[boxes.length - 1];
    box.querySelector('input[name="q_title[]"]').value = q.title;
    if ((q.type === 'single' || q.type === 'multi') && q.options && q.options.length) {
      box.querySelector('textarea[name="q_options[]"]').value = q.options.join('\n');
    }
    if (q.required) box.querySelector('input[name="q_required[]"]').checked = true;
  });
  closeImportDialog();
  alert('✅ 已添加 ' + IMPORTED_QUESTIONS.length + ' 道题目到列表');
}

// ─── AI 生成 ───
var AI_QUESTIONS = [];
function openAIGenDialog() { document.getElementById('aiGenDialog').style.display = 'flex'; document.getElementById('aiPreview').style.display = 'none'; document.getElementById('aiResult').innerHTML = ''; }
function closeAIGenDialog() { document.getElementById('aiGenDialog').style.display = 'none'; }
function doAIGenerate() {
  var topic = document.getElementById('aiTopic').value.trim();
  if (!topic) { alert('请输入调研主题'); return; }
  var count = parseInt(document.getElementById('aiCount').value) || 10;
  var includeRating = document.getElementById('aiRating').checked;
  var result = document.getElementById('aiResult');
  result.innerHTML = '<div class="spinner" style="text-align:center;padding:20px;color:var(--text-3)">✨ AI 正在设计问卷，约需 10-30 秒...</div>';
  fetch('../api/survey-ai.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ topic: topic, question_count: count, include_rating: includeRating })
  })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.ok) { result.innerHTML = '<div class="msg msg-error">' + d.error + '</div>'; return; }
      AI_QUESTIONS = d.questions;
      result.innerHTML = '<div class="msg msg-success">✅ AI 生成了 ' + d.count + ' 道题目</div>';
      document.getElementById('aiCountLabel').textContent = '共生成 ' + d.count + ' 道题目：';
      var list = document.getElementById('aiList');
      list.innerHTML = d.questions.map(function(q, i) {
        var typeLabel = { single: '单选', multi: '多选', rating: '评分', text: '文本' }[q.type] || '文本';
        var opts = q.options && q.options.length ? '<div style="font-size:12px;color:var(--text-3);margin-top:4px">' + q.options.join(' / ') + '</div>' : '';
        return '<div style="padding:8px 12px;border-bottom:1px solid var(--border);font-size:13px">' +
          '<span style="color:var(--text-3);font-weight:700">' + (i+1) + '.</span> ' + q.title +
          ' <span class="badge badge-gray" style="font-size:10px">' + typeLabel + '</span>' + opts + '</div>';
      }).join('');
      document.getElementById('aiPreview').style.display = 'block';
    })
    .catch(function() { result.innerHTML = '<div class="msg msg-error">请求失败，请检查 AI 供应商配置</div>'; });
}
function confirmAI() {
  AI_QUESTIONS.forEach(function(q) {
    addQuestion(q.type || 'text');
    var boxes = document.querySelectorAll('#questionList .q-box');
    var box = boxes[boxes.length - 1];
    box.querySelector('input[name="q_title[]"]').value = q.title;
    if ((q.type === 'single' || q.type === 'multi') && q.options && q.options.length) {
      box.querySelector('textarea[name="q_options[]"]').value = q.options.join('\n');
    }
    if (q.required) box.querySelector('input[name="q_required[]"]').checked = true;
    if (q.type === 'rating') box.querySelector('input[name="q_scale[]"]').value = q.scale || 5;
  });
  closeAIGenDialog();
  alert('✅ 已添加 ' + AI_QUESTIONS.length + ' 道题目到列表');
}
</script>
<?php admin_footer(); ?>
