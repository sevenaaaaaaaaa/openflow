<?php
/**
 * 内容导入连接器 — 飞书 / Notion / Obsidian / 印象笔记 → 文章草稿
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('articles');

$configFile = DATA_DIR . '/ingest-config.json';
$config = json_read($configFile);
$message = '';

// 保存配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $config = [
        'feishu_token' => trim($_POST['feishu_token'] ?? ''),
        'notion_token' => trim($_POST['notion_token'] ?? ''),
        'evernote_token' => trim($_POST['evernote_token'] ?? ''),
        'import_category' => trim($_POST['import_category'] ?? 'insight'),
        'import_status' => $_POST['import_status'] ?? 'draft',
    ];
    json_write($configFile, $config);
    $message = '连接器配置已保存';
}

// Obsidian 本地上传 .md 导入
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['md_file'])) {
    csrf_verify();
    $f = $_FILES['md_file'];
    if ($f['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($f['tmp_name']);
        $title = pathinfo($f['name'], PATHINFO_FILENAME);
        // 调导入 API
        $ch = curl_init((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST']??'') . '/api/ingest.php');
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(['platform'=>'obsidian','title'=>$title,'content'=>$content,'status'=>$config['import_status']??'draft']), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true]);
        $resp = json_decode(curl_exec($ch), true);
        $message = $resp['ok'] ? "✅ 已从 Obsidian 导入：{$title}" : "❌ 导入失败：" . ($resp['error'] ?? '');
    } else { $message = '文件上传失败'; }
}

admin_header('内容导入连接器');
?>
<div class="admin-layout">
  <?php admin_sidebar('ingest'); ?>
  <div class="main">
    <h1>🔗 内容导入连接器</h1>
    <p class="sub">从飞书 / Notion / Obsidian / 印象笔记发布文章到后台（自动进入审核）</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- Obsidian 本地上传 -->
    <div class="card" style="background:linear-gradient(135deg,var(--surface),rgba(125,211,252,.1))">
      <h2>📓 Obsidian 导入</h2>
      <p class="text-sm text-muted mb-4">直接上传 Obsidian 导出的 .md 文件，自动转为文章草稿</p>
      <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input type="file" name="md_file" accept=".md,.markdown,.txt" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px">
        <button type="submit" class="btn btn-primary">导入为草稿</button>
      </form>
    </div>

    <!-- 平台配置 -->
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>⚙️ 平台 API 配置</h2>
        <p class="text-sm text-muted mb-4">配置后可通过各平台 API 自动拉取文档</p>
        <div class="field-row">
          <div class="field"><label>飞书 OpenAPI Token</label><input type="password" name="feishu_token" value="<?=htmlspecialchars($config['feishu_token'] ?? '')?>" placeholder="飞书开放平台 tenant_access_token"></div>
          <div class="field"><label>Notion Integration Token</label><input type="password" name="notion_token" value="<?=htmlspecialchars($config['notion_token'] ?? '')?>" placeholder="Notion Integration Token (secret_..."></div>
        </div>
        <div class="field"><label>印象笔记 Token <span class="hint">· 可选</span></label><input type="password" name="evernote_token" value="<?=htmlspecialchars($config['evernote_token'] ?? '')?>" placeholder="印象笔记 API Token"></div>
        <div class="field-row">
          <div class="field"><label>默认分类</label><select name="import_category">
            <?php foreach (get_categories('article') as $c): ?>
            <option value="<?=htmlspecialchars($c['key'])?>" <?=($config['import_category']??'insight')===$c['key']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="field"><label>默认状态</label><select name="import_status"><option value="draft" <?=($config['import_status']??'draft')==='draft'?'selected':''?>>草稿</option><option value="pending" <?=($config['import_status']??'')==='pending'?'selected':''?>>待审核</option></select></div>
        </div>
        <button type="submit" name="save" class="btn btn-primary">保存配置</button>
      </div>
    </form>

    <!-- GitHub Pages 批量导入 -->
    <div class="card" style="background:linear-gradient(135deg,var(--surface),rgba(125,211,252,.1))">
      <h2>🐙 GitHub Pages / 仓库导入</h2>
      <p class="text-sm text-muted mb-4">从 GitHub Pages 或任意 GitHub 仓库批量导入 Markdown 文章（自动解析 front-matter，转为 HTML 草稿）</p>
      <div class="field-row">
        <div class="field"><label>仓库（owner/repo）</label><input type="text" id="ghRepo" class="inp" placeholder="yourname/your-blog" value=""></div>
        <div class="field"><label>分支</label><input type="text" id="ghBranch" class="inp" placeholder="main" value="main"></div>
      </div>
      <div class="field-row">
        <div class="field"><label>导入数量上限</label><input type="number" id="ghLimit" class="inp" value="20" min="1" max="100"></div>
        <div class="field"><label>分类</label>
          <select id="ghCat" class="inp">
            <?php foreach (get_categories('article') as $c): ?><option value="<?=htmlspecialchars($c['key'])?>" <?=($config['import_category']??'insight')===$c['key']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" onclick="githubImport()">📥 导入为草稿</button>
        <span id="ghResult" class="text-sm align-center" style="align-self:center"></span>
      </div>
    </div>

    <!-- Cloudflare Pages 批量导入 -->
    <div class="card">
      <h2>🌩️ Cloudflare Pages 导入</h2>
      <p class="text-sm text-muted mb-4">输入 Cloudflare Pages 站点地址，自动抓取 sitemap 或首页文章链接并导入为草稿</p>
      <div class="field-row">
        <div class="field"><label>站点地址</label><input type="text" id="cfSite" class="inp" placeholder="https://your-site.pages.dev"></div>
        <div class="field"><label>导入数量上限</label><input type="number" id="cfLimit" class="inp" value="20" min="1" max="100"></div>
      </div>
      <div class="field-row">
        <div class="field"><label>分类</label>
          <select id="cfCat" class="inp">
            <?php foreach (get_categories('article') as $c): ?><option value="<?=htmlspecialchars($c['key'])?>" <?=($config['import_category']??'insight')===$c['key']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" onclick="cfImport()">📥 导入为草稿</button>
        <span id="cfResult" class="text-sm align-center" style="align-self:center"></span>
      </div>
    </div>

    <!-- API 用法 -->
    <div class="card">
      <h2>🔌 API 接入说明</h2>
      <p class="text-sm text-muted mb-4">各平台/工具可通过 HTTP 调用本连接器发布文章：</p>
      <pre style="background:#1e1e1e;color:#fff;padding:14px;border-radius:8px;font-size:13px;overflow-x:auto">curl -X POST https://nownexts.com/api/ingest.php \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "obsidian",
    "title": "文章标题",
    "content": "文章内容（HTML 或 Markdown）",
    "tags": ["标签1", "标签2"]
  }'

# 从 URL 抓取（飞书/Notion/公众号文章链接）
curl -X POST https://nownexts.com/api/ingest.php \
  -H "Content-Type: application/json" \
  -d '{"platform": "feishu", "url": "https://xxx.feishu.cn/docx/xxx"}'</pre>
      <p class="text-sm text-muted mt-4">导入的文章自动进入「内容审核」队列，审核后发布。</p>
    </div>

    <!-- ═══ RSS 引入 ═══ -->
    <div class="card">
      <h2>📡 RSS 引入</h2>
      <p class="text-sm text-muted mb-4">从外部 RSS 源拉取文章，导入为草稿（支持 RSS 2.0 / Atom）</p>
      <div class="field-row">
        <div class="field"><label>RSS URL</label><input type="text" id="rssUrl" class="inp" placeholder="https://example.com/feed.xml"></div>
        <div class="field"><label>默认分类</label>
          <select id="rssCat" class="inp">
            <?php foreach (get_categories('article') as $c): ?><option value="<?=htmlspecialchars($c['key'])?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field-row">
        <div class="field"><label>作者</label><input type="text" id="rssAuthor" class="inp" placeholder="导入"></div>
        <div class="field"><label>标签（逗号分隔）</label><input type="text" id="rssTag" class="inp" placeholder="RSS, 资讯"></div>
      </div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-ghost" onclick="previewRss()">👁 预览</button>
        <button class="btn btn-primary" onclick="importRss()">📥 导入为草稿</button>
      </div>
      <div id="rssPreview" class="mt-4"></div>
    </div>
  </div>
</div>

<script>
function previewRss() {
  var url = document.getElementById('rssUrl').value.trim();
  var box = document.getElementById('rssPreview');
  if (!url) { box.innerHTML = '<div class="msg msg-error">请输入 RSS URL</div>'; return; }
  box.innerHTML = '<div class="msg msg-info">预览中…</div>';
  fetch('/api/rss-import.php?url=' + encodeURIComponent(url)).then(function(r){return r.json();}).then(function(d){
    if (!d.ok) { box.innerHTML = '<div class="msg msg-error">' + (d.error || '预览失败') + '</div>'; return; }
    var html = '<div class="msg msg-info">共发现 ' + d.count + ' 篇文章，前 5 条：</div><div style="border:1px solid var(--border);border-radius:12px;overflow:hidden">';
    d.items.slice(0, 5).forEach(function(it){
      html += '<div style="padding:10px 14px;border-bottom:1px solid var(--border);font-size:13px"><strong>' + it.title + '</strong><div class="text-sm text-muted">' + (it.link || '') + '</div></div>';
    });
    html += '</div>';
    box.innerHTML = html;
  }).catch(function(){ box.innerHTML = '<div class="msg msg-error">网络异常</div>'; });
}
function importRss() {
  var url = document.getElementById('rssUrl').value.trim();
  var cat = document.getElementById('rssCat').value;
  var author = document.getElementById('rssAuthor').value.trim() || '导入';
  var tag = document.getElementById('rssTag').value.trim();
  var box = document.getElementById('rssPreview');
  if (!url) { box.innerHTML = '<div class="msg msg-error">请输入 RSS URL</div>'; return; }
  var body = new FormData();
  body.append('rss_url', url); body.append('category', cat); body.append('author', author); body.append('tag', tag);
  box.innerHTML = '<div class="msg msg-info">导入中…</div>';
  fetch('/api/rss-import.php', {method:'POST', body: body}).then(function(r){return r.json();}).then(function(d){
    if (d.ok) box.innerHTML = '<div class="msg msg-success">✅ 已导入 ' + d.imported + ' 篇（共发现 ' + d.total_found + ' 篇，已去重）</div>';
    else box.innerHTML = '<div class="msg msg-error">' + (d.error || '导入失败') + '</div>';
  }).catch(function(){ box.innerHTML = '<div class="msg msg-error">网络异常</div>'; });
}
function githubImport() {
  var repo = document.getElementById('ghRepo').value.trim();
  var branch = document.getElementById('ghBranch').value.trim() || 'main';
  var limit = document.getElementById('ghLimit').value || 20;
  var cat = document.getElementById('ghCat').value;
  var box = document.getElementById('ghResult');
  if (!repo) { box.innerHTML = '<span class="msg msg-error">请输入仓库（owner/repo）</span>'; return; }
  box.innerHTML = '<span class="text-muted">⏳ 导入中…</span>';
  fetch('/api/ingest.php', {method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({platform:'github', repo:repo, branch:branch, limit:limit, category:cat})})
    .then(function(r){return r.json();}).then(function(d){
    if (d.ok) {
      var a = d.article || {};
      box.innerHTML = '<span class="msg msg-success">✅ 已导入 ' + (a.saved || 0) + ' 篇' + ((a.failed||0)>0?'（'+a.failed+' 失败）':'') + '</span>';
    } else box.innerHTML = '<span class="msg msg-error">' + (d.error || '导入失败') + '</span>';
  }).catch(function(){ box.innerHTML = '<span class="msg msg-error">网络异常</span>'; });
}
function cfImport() {
  var site = document.getElementById('cfSite').value.trim();
  var limit = document.getElementById('cfLimit').value || 20;
  var cat = document.getElementById('cfCat').value;
  var box = document.getElementById('cfResult');
  if (!site) { box.innerHTML = '<span class="msg msg-error">请输入站点地址</span>'; return; }
  box.innerHTML = '<span class="text-muted">⏳ 导入中…</span>';
  fetch('/api/ingest.php', {method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({platform:'cloudflare', site_url:site, limit:limit, category:cat})})
    .then(function(r){return r.json();}).then(function(d){
    if (d.ok) {
      var a = d.article || {};
      box.innerHTML = '<span class="msg msg-success">✅ 已导入 ' + (a.saved || 0) + ' 篇</span>';
    } else box.innerHTML = '<span class="msg msg-error">' + (d.error || '导入失败') + '</span>';
  }).catch(function(){ box.innerHTML = '<span class="msg msg-error">网络异常</span>'; });
}
</script>
<?php admin_footer(); ?>
