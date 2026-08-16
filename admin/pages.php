<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/page-editor-config.php';
require_once __DIR__ . '/review-lib.php';
require_login();
require_perm('pages');

$page = $_GET['page'] ?? 'index';
$allowed = ['index', 'about', 'capability', 'courses', 'flow-community'];
$pageNames = [
    'index' => '首页', 'about' => '关于我们', 'capability' => '产品',
    'courses' => '解决方案', 'flow-community' => 'Flow社区',
];
// 加载注册的自定义页面
$siteStructure = json_read(DATA_DIR . '/site-structure.json');
foreach (($siteStructure['custom_pages'] ?? []) as $cp) {
    if (!empty($cp['slug'])) {
        $allowed[] = $cp['slug'];
        $pageNames[$cp['slug']] = $cp['name'] ?? $cp['title'] ?? $cp['slug'];
    }
}
if (!in_array($page, $allowed)) $page = 'index';

$pageFiles = ['index' => 'index.html', 'about' => 'about.html', 'capability' => 'capability.html', 'courses' => 'courses.html', 'flow-community' => 'flow-community.html'];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $data = $_POST['content'] ?? [];
    if (isset($_POST['structured_data'])) {
        save_structured_data('page', $page, ['jsonld' => $_POST['structured_data'], 'updated_at' => date('Y-m-d H:i:s')]);
    }
    $data = PluginSystem::apply_filters('page_save_before', $data, $page);
    save_page_content($page, $data);
    PluginSystem::do_action('page_saved', $page, $data);

    // ─── 页面内容审核 ───
    $pageText = $pageNames[$page];
    foreach ($data as $k => $v) {
        if (is_string($v)) $pageText .= ' ' . $v;
        elseif (is_array($v)) $pageText .= ' ' . json_encode($v, JSON_UNESCAPED_UNICODE);
    }
    $reviewResult = review_content($pageNames[$page], $pageText, 'page');
    $needReview = review_needed($reviewResult);

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $pageUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/' . ($page === 'index' ? '' : $page . '.html');
    indexnow_ping($pageUrl);
    $message = '内容已保存，已触发 IndexNow 推送';
    log_activity('update', 'page', $page, $pageNames[$page] . ' 内容已保存');

    if ($needReview) {
        $review = review_apply('page', $page, $reviewResult, ['title' => $pageNames[$page]]);
        $issueSummary = implode('；', array_column($reviewResult['issues'], 'desc'));
        notify('review', '页面需审核：' . $pageNames[$page], $issueSummary, 'admin/reviews.php?type=page', ['admin', 'marketing']);
        $message = '内容已保存，但命中审核规则：' . $issueSummary;
    } else {
        notify('system', '页面已更新', $pageNames[$page] . ' 内容已保存');
    }
    if (isset($_POST['ajax_save'])) { echo $needReview ? '内容需审核' : '保存成功'; exit; }
}

$content = page_content($page);
$groups = page_field_groups($page);
$sd = get_structured_data('page', $page);

admin_header('页面编辑器 - ' . $pageNames[$page]);
?>
<style>
.section-group{border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:16px;overflow:hidden}
.section-header{display:flex;align-items:center;gap:10px;padding:14px 20px;background:var(--surface-2);cursor:pointer;user-select:none;transition:background .1s;border-bottom:1px solid var(--border)}
.section-header:hover{background:var(--border)}
.section-header .icon{font-size:18px}
.section-header .title{font-weight:600;font-size:15px;flex:1}
.section-header .count{font-size:12px;color:var(--text-3);font-family:var(--mono)}
.section-header .arrow{transition:transform .2s;color:var(--text-3)}
.section-header .arrow.open{transform:rotate(90deg)}
.section-body{padding:20px;display:none}
.section-body.open{display:block}
.preview-bar{display:flex;align-items:center;gap:12px;padding:10px 16px;background:var(--surface-2);border-radius:8px;margin-bottom:16px}
.preview-bar .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;font-size:13px;font-weight:500;cursor:pointer;transition:all .1s}
.preview-bar .pill.active{background:var(--accent);color:var(--text)}
.preview-bar .pill:not(.active){background:var(--surface);color:var(--text-2);border:1px solid var(--border)}
.preview-frame{width:100%;height:600px;border:1px solid var(--border);border-radius:var(--radius-lg);background:#fff;display:none}
.preview-frame.show{display:block}
.preview-frame iframe{width:100%;height:100%;border:none;border-radius:var(--radius-lg)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('pages'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"><?=htmlspecialchars($pageNames[$page])?></h1>
      <a href="pages-list.php" class="btn btn-ghost ml-auto">← 页面列表</a>
    </div>
    <p class="sub">字段按页面模块分组 · 折叠展开快速定位</p>

    <div class="tabs">
      <?php foreach ($allowed as $p): ?>
      <a href="?page=<?=$p?>" class="<?=$p===$page?'active':''?>"><?=htmlspecialchars($pageNames[$p])?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- Preview Toggle -->
    <div class="preview-bar">
      <span class="pill active" data-mode="edit" onclick="togglePreview('edit')">✏️ 编辑模式</span>
      <span class="pill" data-mode="preview" onclick="togglePreview('preview')">👁 预览模式</span>
      <span style="margin-left:auto;font-size:12px;color:var(--text-3)">预览加载当前已保存的内容</span>
    </div>

    <!-- Preview Frame -->
    <div class="preview-frame" id="previewFrame">
      <iframe src="../page-preview.php?page=<?=$page?>" id="previewIframe"></iframe>
    </div>

    <!-- Editor -->
    <div id="editorArea">
    <form method="post" id="pageForm">
      <?= csrf_field() ?>
      <div style="display:flex;gap:8px;margin-bottom:12px">
        <button type="button" class="btn btn-ghost btn-sm" onclick="showPageAI()">🤖 AI 辅助</button>
      </div>
      <?php foreach ($groups as $gk => $gv):
        $hasFields = false;
        foreach ($gv['fields'] as $fk) { if (array_key_exists($fk, $content)) { $hasFields = true; break; } }
        if (!$hasFields) continue;
      ?>
      <div class="section-group">
        <div class="section-header" onclick="toggleSection(this)">
          <span class="icon"><?=$gv['icon']?></span>
          <span class="title"><?=htmlspecialchars($gv['label'])?></span>
          <span class="count"><?=count($gv['fields'])?> 个字段</span>
          <span class="arrow">▶</span>
        </div>
        <div class="section-body">
          <?php foreach ($gv['fields'] as $fk):
            if (!array_key_exists($fk, $content)) continue;
            $fv = $content[$fk] ?? '';
          ?>
          <div class="field">
            <label><?=htmlspecialchars($fk)?></label>
            <?php if (mb_strlen($fv ?? '') > 80 || str_contains($fv ?? '', '<')): ?>
            <textarea name="content[<?=htmlspecialchars($fk)?>]" rows="4"><?=htmlspecialchars($fv ?? '')?></textarea>
            <?php else: ?>
            <input type="text" name="content[<?=htmlspecialchars($fk)?>]" value="<?=htmlspecialchars($fv ?? '')?>">
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Unorganized fields (not in any group) -->
      <?php $grouped = []; foreach ($groups as $g) foreach ($g['fields'] as $f) $grouped[$f] = true;
      $ungrouped = array_diff_key($content, $grouped);
      if (!empty($ungrouped)): ?>
      <div class="section-group">
        <div class="section-header" onclick="toggleSection(this)">
          <span class="icon">📦</span><span class="title">其他字段</span><span class="count"><?=count($ungrouped)?> 个</span><span class="arrow">▶</span>
        </div>
        <div class="section-body">
          <?php foreach ($ungrouped as $fk => $fv): ?>
          <div class="field">
            <label><?=htmlspecialchars($fk)?></label>
            <?php if (mb_strlen($fv ?? '') > 80): ?>
            <textarea name="content[<?=htmlspecialchars($fk)?>]" rows="4"><?=htmlspecialchars($fv ?? '')?></textarea>
            <?php else: ?>
            <input type="text" name="content[<?=htmlspecialchars($fk)?>]" value="<?=htmlspecialchars($fv ?? '')?>">
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Structured Data -->
      <div class="section-group">
        <div class="section-header" onclick="toggleSection(this)">
          <span class="icon">🔧</span><span class="title">结构化数据 (JSON-LD)</span><span class="count">1 个</span><span class="arrow">▶</span>
        </div>
        <div class="section-body">
          <p class="text-sm text-muted mb-4">自定义 Schema 代码，嵌入页面 <code>head</code>。保存时自动 IndexNow 推送。</p>
          <textarea name="structured_data" rows="6" style="font-family:var(--mono);font-size:13px;line-height:1.6;width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px"><?=htmlspecialchars($sd['jsonld'] ?? '')?></textarea>
        </div>
      </div>

      <div class="flex gap-4" style="position:sticky;bottom:16px;background:var(--bg);padding:16px 0;z-index:10">
        <button type="submit" class="btn btn-primary" style="padding:12px 36px;font-size:15px">💾 保存所有更改</button>
        <a href="../<?=$pageFiles[$page]?>" class="btn btn-ghost" target="_blank">在新标签预览</a>
      </div>
    </form>
    </div>
  </div>
</div>

<script>
// ─── Section collapse ───
document.querySelectorAll('.section-header').forEach(function(h) {
  var body = h.nextElementSibling;
  var arrow = h.querySelector('.arrow');
  // Open first section by default
  if (h === document.querySelector('.section-header')) {
    body.classList.add('open');
    if (arrow) arrow.classList.add('open');
  }
});

function toggleSection(header) {
  var body = header.nextElementSibling;
  var arrow = header.querySelector('.arrow');
  body.classList.toggle('open');
  if (arrow) arrow.classList.toggle('open');
}

// ─── Preview toggle ───
function togglePreview(mode) {
  document.querySelectorAll('.preview-bar .pill').forEach(function(p) {
    p.classList.toggle('active', p.dataset.mode === mode);
  });
  document.getElementById('previewFrame').classList.toggle('show', mode === 'preview');
  document.getElementById('editorArea').style.display = mode === 'edit' ? 'block' : 'none';
  if (mode === 'preview') {
    var iframe = document.getElementById('previewIframe');
    iframe.src = '../page-preview.php?page=<?=$page?>&t=' + Date.now();
  }
}

// ─── Page AI ───
function showPageAI() {
  var content = '';
  document.querySelectorAll('textarea[name^="content["]').forEach(function(ta) { content += ta.value + '\n'; });
  if (!content.trim()) { alert('页面内容为空，无法使用 AI'); return; }
  var prompt = prompt('输入 AI 提示词（如：优化以下页面文案，使其更专业）:', '优化以下页面文案，使其更专业、简洁，适合企业官网风格。');
  if (!prompt) return;

  var div = document.createElement('div'); div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;background:#fff;border:1px solid #e2dfd2;border-radius:12px;padding:20px;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.15)';
  div.innerHTML = '<p style="font-size:14px;color:#6e6e6e">⏳ AI 处理中…</p>'; document.body.appendChild(div);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/ai-generate.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    try {
      var resp = JSON.parse(xhr.responseText);
      if (resp.ok) {
        div.innerHTML = '<p style="font-weight:600;margin-bottom:8px">✅ AI 建议</p><div style="font-size:13px;line-height:1.6;max-height:300px;overflow-y:auto;background:#f4f3e9;padding:12px;border-radius:8px;margin-bottom:12px">' + resp.result.replace(/\n/g,'<br>') + '</div><button class="btn btn-primary btn-sm" onclick="this.parentElement.remove()">关闭</button>';
      } else {
        div.innerHTML = '<p style="color:#dc2626">❌ ' + (resp.error || '失败') + '</p><button class="btn btn-ghost btn-sm mt-4" onclick="this.parentElement.remove()">关闭</button>';
      }
    } catch(e) { div.innerHTML = '<p style="color:#dc2626">解析失败</p><button class="btn btn-ghost btn-sm mt-4" onclick="this.parentElement.remove()">关闭</button>'; }
  };
  xhr.send(JSON.stringify({prompt: prompt, content: content}));
}

// ─── Ctrl+S AJAX 保存（不刷新页面）───
function savePageAJAX() {
  var form = document.getElementById('pageForm');
  if (!form) return;
  var fd = new FormData(form);
  fd.append('ajax_save', '1');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', window.location.href, true);
  xhr.onload = function() {
    var ind = document.getElementById('pageSaveIndicator');
    if (!ind) {
      ind = document.createElement('div');
      ind.id = 'pageSaveIndicator';
      ind.style.cssText = 'position:fixed;bottom:80px;right:24px;background:#2e7d32;color:#fff;padding:8px 16px;border-radius:8px;font-size:13px;z-index:9999;opacity:0;transition:opacity .3s';
      document.body.appendChild(ind);
    }
    ind.style.background = (xhr.status === 200 && xhr.responseText.indexOf('保存成功') !== -1) ? '#2e7d32' : '#c62828';
    ind.textContent = (xhr.status === 200 && xhr.responseText.indexOf('保存成功') !== -1) ? '✅ 已保存 ' + new Date().toLocaleTimeString() : '❌ 保存失败';
    ind.style.opacity = '1';
    setTimeout(function() { ind.style.opacity = '0'; }, 2500);
  };
  xhr.send(fd);
}
document.addEventListener('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
    e.preventDefault();
    savePageAJAX();
  }
});
</script>
<?php admin_footer(); ?>
