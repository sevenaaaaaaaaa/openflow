<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('articles');

$secretFile = DATA_DIR . '/import_secret.json';
$secretData = json_read($secretFile);
$secret = $secretData['secret'] ?? '';
if (!$secret) {
    $secret = bin2hex(random_bytes(16));
    json_write($secretFile, ['secret' => $secret]);
}

$message = '';

admin_header('批量导入');
?>
<div class="admin-layout">
  <?php admin_sidebar('articles'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">批量导入文章</h1>
      <a href="articles.php" class="btn btn-ghost ml-auto">← 返回列表</a>
    </div>

    <div class="card">
      <h2>API 密钥</h2>
      <p class="text-sm text-muted mb-4">将此密钥用于外部系统调用批量导入接口</p>
      <div class="flex gap-2">
        <code style="flex:1;padding:12px 16px;font-size:14px" id="secretKey"><?=htmlspecialchars($secret)?></code>
        <button class="btn btn-ghost" onclick="copySecret()">复制</button>
        <button class="btn btn-ghost" onclick="refreshSecret()">刷新</button>
      </div>
    </div>

    <div class="card">
      <h2>API 调用方式</h2>
      <p class="text-sm text-muted mb-4">POST JSON 到 <code>/api/batch-import.php</code></p>
      <pre style="font-size:13px;line-height:1.6">curl -X POST https://你的域名/api/batch-import.php \
  -H "Content-Type: application/json" \
  -d '{
    "secret": "<?=htmlspecialchars($secret)?>",
    "articles": [
      {
        "title": "文章标题",
        "slug": "article-slug",
        "content": "&lt;p&gt;文章内容 HTML 或 Markdown&lt;/p&gt;",
        "editor_mode": "richtext",
        "category": "insight",
        "tags": ["标签1", "标签2"],
        "cover": "uploads/articles/cover.jpg",
        "author": "作者名",
        "status": "published",
        "seo_title": "SEO 标题",
        "seo_desc": "SEO 描述"
      }
    ]
  }'</pre>
    </div>

    <div class="card">
      <h2>本地批量导入</h2>
      <p class="text-sm text-muted mb-4">选择 JSON 文件导入（格式同上）</p>
      <div class="field">
        <input type="file" id="importFile" accept=".json">
      </div>
      <button class="btn btn-primary" onclick="localImport()">导入</button>
      <div id="importResult" style="margin-top:12px"></div>
    </div>
  </div>
</div>

<script>
function copySecret() {
  var el = document.getElementById('secretKey');
  navigator.clipboard.writeText(el.textContent).then(function() { alert('已复制'); });
}
function refreshSecret() {
  if (!confirm('刷新密钥后，旧的密钥将失效。确认刷新?')) return;
  var newSecret = 'sk_' + Array.from({length:24}, function(){ return 'abcdefghijklmnopqrstuvwxyz0123456789'[Math.floor(Math.random()*36)]; }).join('');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/batch-import.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() { location.reload(); };
  xhr.send(JSON.stringify({secret: document.getElementById('secretKey').textContent, articles: []}));
}
function localImport() {
  var file = document.getElementById('importFile').files[0];
  if (!file) { alert('请选择 JSON 文件'); return; }
  var box = document.getElementById('importResult');
  box.innerHTML = '<div class="msg" style="background:var(--surface-2)">⏳ 正在导入，请稍候...</div>';
  var reader = new FileReader();
  reader.onload = function(e) {
    try {
      var data = JSON.parse(e.target.result);
      var articles = data.articles || (Array.isArray(data) ? data : [data]);
      var secret = document.getElementById('secretKey').textContent;
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '../api/batch-import.php', true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onload = function() {
        try {
          var d = JSON.parse(xhr.responseText);
          renderImportResult(d, articles);
        } catch(e2) {
          box.innerHTML = '<div class="msg msg-error">导入响应异常：' + xhr.responseText + '</div>';
        }
      };
      xhr.send(JSON.stringify({secret: secret, articles: articles}));
    } catch(e) {
      box.innerHTML = '<div class="msg msg-error">JSON 格式错误: ' + e.message + '</div>';
    }
  };
  reader.readAsText(file);
}

var LAST_RESULT = { items: [], filename: '' };
function renderImportResult(d, articles) {
  var box = document.getElementById('importResult');
  var summary;
  if (d && d.ok) {
    var failed = (d.results || []).filter(function(r) { return r.status === 'error'; });
    var skipped = (d.results || []).filter(function(r) { return r.status === 'skipped'; });
    summary = '<div class="msg msg-success">✅ 成功 ' + d.imported + ' 篇' +
      (skipped.length ? ' · ⏭ 跳过 ' + skipped.length + ' 篇（重复）' : '') +
      (failed.length ? ' · ❌ 失败 ' + failed.length + ' 篇' : '') +
      ' · 共 ' + d.total + ' 篇</div>';
  } else {
    summary = '<div class="msg msg-error">❌ 导入失败：' + ((d && d.error) || '未知错误') + '</div>';
  }

  // 逐条结果列表
  var rows = '';
  var items = (d && d.results) || [];
  if (items.length) {
    rows = '<div style="max-height:400px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;margin-top:12px">' +
      '<table style="font-size:12px"><thead><tr><th style="width:60px">序号</th><th>标题</th><th style="width:70px">状态</th><th style="width:140px">详情</th></tr></thead><tbody>' +
      items.map(function(r) {
        var icon = r.status === 'success' ? '✅' : (r.status === 'skipped' ? '⏭️' : '❌');
        var color = r.status === 'success' ? 'var(--ok)' : (r.status === 'skipped' ? 'var(--warn)' : 'var(--danger)');
        var detail = r.status === 'success' ? (r.slug || '') : (r.reason || '');
        return '<tr>' +
          '<td class="text-muted">' + (r.index + 1) + '</td>' +
          '<td>' + escHtml(r.title || '（空标题）') + '</td>' +
          '<td><span style="color:' + color + ';font-weight:600">' + icon + ' ' + (r.status === 'success' ? '成功' : r.status === 'skipped' ? '跳过' : '失败') + '</span></td>' +
          '<td class="text-muted" style="word-break:break-all">' + escHtml(detail) + '</td>' +
          '</tr>';
      }).join('') +
      '</tbody></table></div>';
  }

  // 失败/跳过清单导出按钮
  var exportBtn = '';
  var issues = items.filter(function(r) { return r.status !== 'success'; });
  if (issues.length) {
    LAST_RESULT.items = issues;
    LAST_RESULT.filename = (document.getElementById('importFile').files[0] || {}).name || 'import.json';
    exportBtn = '<button class="btn btn-ghost btn-sm" style="margin-top:10px" onclick="exportFailedList()">📥 导出失败清单（' + issues.length + ' 条）</button>';
  }

  box.innerHTML = summary + rows + exportBtn;
  box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function escHtml(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
    return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
  });
}

function exportFailedList() {
  var lines = ['index,title,status,reason'];
  LAST_RESULT.items.forEach(function(r) {
    lines.push((r.index + 1) + ',"' + String(r.title || '').replace(/"/g, '""') + '",' + r.status + ',"' + String(r.reason || '').replace(/"/g, '""') + '"');
  });
  var blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = '导入失败清单-' + (LAST_RESULT.filename.replace(/\.json$/i, '') || 'import') + '.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}
</script>
<?php admin_footer(); ?>
