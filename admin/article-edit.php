<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/review-lib.php';
require_once __DIR__ . '/../lib/GeoSystem.php';
require_once __DIR__ . '/../lib/FlowSystem.php';
require_login();
require_perm('articles');

$id = $_GET['id'] ?? '';
$isNew = empty($id);
$isCopy = isset($_GET['copy']) && $_GET['copy'] === '1';

if (!$isNew) {
    $article = get_article($id);
    if (!$article) { flash('error', '文章不存在'); header('Location: articles.php'); exit; }
    // 合并默认字段，避免老文章缺字段导致 Undefined/Deprecated 警告
    $article = array_merge([
        'title' => '', 'slug' => '', 'content' => '', 'category' => '',
        'tags' => [], 'cover' => '', 'author' => '',
        'status' => 'draft',
        'seo_title' => '', 'seo_desc' => '', 'seo_keywords' => '', 'structured_data' => '',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ], $article);
    if ($isCopy) {
        $article['title'] .= ' (副本)';
        $article['slug'] = '';
        $article['status'] = 'draft';
        $isNew = true;
    }
} else {
    $article = [
        'title' => '', 'slug' => '', 'content' => '', 'category' => '',
        'tags' => [], 'cover' => '', 'author' => $_SESSION['admin_name'] ?? '',
        'status' => 'draft',
        'seo_title' => '', 'seo_desc' => '', 'seo_keywords' => '', 'structured_data' => '',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ];
}

$editorMode = $article['editor_mode'] ?? 'richtext'; // richtext | markdown

// Auto Save (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'auto_save') {
    header('Content-Type: application/json; charset=utf-8');
    $id = $_POST['id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $editorModePost = $_POST['editor_mode'] ?? 'richtext';
    
    if (empty($id)) {
        echo json_encode(['ok' => false, 'error' => '缺少文章 ID']);
        exit;
    }
    
    $article = get_article($id);
    $isNew = !$article;
    
    if ($isNew) {
        $article = [
            'id' => $id,
            'title' => $title ?: '未命名草稿',
            'slug' => '',
            'content' => $content,
            'editor_mode' => $editorModePost,
            'status' => 'draft',
            'category' => '',
            'tags' => [],
            'cover' => '',
            'author' => $_SESSION['admin_name'] ?? '',
            'seo_title' => '',
            'seo_desc' => '',
            'seo_keywords' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'auto_saved' => true,
        ];
    } else {
        $article['title'] = $title ?: ($article['title'] ?? '未命名草稿');
        $article['content'] = $content;
        $article['editor_mode'] = $editorModePost;
        $article['updated_at'] = date('Y-m-d H:i:s');
        $article['auto_saved'] = true;
    }
    
    save_article($id, $article);
    echo json_encode(['ok' => true, 'id' => $id, 'saved_at' => date('Y-m-d H:i:s')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $article['title'] = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if (empty($slug)) {
        $slug = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}-]/u', '-', $article['title']);
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        $slug = mb_substr($slug, 0, 80);
    }
    if (article_slug_exists($slug, $isNew ? null : $id)) {
        $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
    $article['slug'] = $slug;
    $article['content'] = $_POST['content'] ?? '';
    $article['editor_mode'] = $_POST['editor_mode'] ?? 'richtext';
    $article['category'] = $_POST['category'] ?? '';
    $article['tags'] = array_filter(explode(',', $_POST['tags'] ?? ''));
    $article['cover'] = $_POST['cover'] ?? '';
    $article['author'] = $_POST['author'] ?? $_SESSION['admin_name'] ?? '';
    $article['status'] = $_POST['status'] ?? 'draft';
    $article['member_only'] = isset($_POST['member_only']) ? true : false;
    $article['seo_title'] = $_POST['seo_title'] ?? '';
    $article['seo_desc'] = $_POST['seo_desc'] ?? '';
    $article['seo_keywords'] = $_POST['seo_keywords'] ?? '';
    $article['structured_data'] = $_POST['structured_data'] ?? '';
    $article['publish_at'] = $_POST['publish_at'] ?? '';
    $article['updated_at'] = date('Y-m-d H:i:s');

    // Version tracking: save revision if content changed
    if (!$isNew) {
        $old = get_article($article['id']);
        if ($old && ($old['content'] ?? '') !== ($article['content'] ?? '')) {
            $verDir = DATA_DIR . '/versions/articles';
            if (!is_dir($verDir)) mkdir($verDir, 0755, true);
            $verFile = $verDir . '/' . $article['id'] . '.json';
            $versions = json_read($verFile);
            $versions[] = [
                'content' => $old['content'],
                'seo_title' => $old['seo_title'] ?? '',
                'seo_desc' => $old['seo_desc'] ?? '',
                'saved_at' => date('Y-m-d H:i:s'),
                'version' => count($versions) + 1,
            ];
            // Keep max 20 versions
            if (count($versions) > 20) $versions = array_slice($versions, -20);
            json_write($verFile, $versions);
        }
    }

    if ($isNew) {
        $article['id'] = 'article_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $article['created_at'] = date('Y-m-d H:i:s');
    }

    $article = PluginSystem::apply_filters('article_save_before', $article);

    if (empty($article['title'])) {
        $message = '标题不能为空';
    } else {
        // ─── 内容审核：命中规则则进入待审核 ───
        $reviewResult = review_content($article['title'], $article['content'], 'article');
        $needReview = review_needed($reviewResult);
        if ($needReview) {
            $article['status'] = 'draft'; // 强制不发布，进入待审核
            $article['review_status'] = 'pending';
        } else {
            $article['review_status'] = 'approved';
        }
        $wasPublished = ($article['status'] === 'published');
        save_article($article['id'], $article);
        PluginSystem::do_action('article_saved', $article['id'], $article);

        // 内容流：发布后联动（IndexNow 收录等）
        if (($article['status'] ?? '') === 'published' && !$wasPublished) {
            try { flow_content_published($article); } catch (Exception $e) {}
        }

        // 审核命中：记录待审核 + 通知管理员/市场总监
        if ($needReview) {
            $review = review_apply('article', $article['id'], $reviewResult, [
                'title' => $article['title'],
            ]);
            $issueSummary = implode('；', array_column($reviewResult['issues'], 'desc'));
            notify('review', '文章需审核：' . mb_substr($article['title'], 0, 20), $issueSummary, 'admin/reviews.php?type=article', ['admin', 'marketing']);
            flash('warning', '内容命中审核规则，已进入待审核状态：' . $issueSummary);
        } else {
            // Trigger IndexNow on publish
            if ($wasPublished && !empty($article['slug'])) {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
                $url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/article/' . $article['slug'];
                indexnow_ping($url);
                geo_submit_url($url); // GEO：自动提交必应/百度
            }
            flash('success', $isNew ? '文章已创建' : '文章已保存');
        }
        if (isset($_POST['ajax_save'])) {
            echo $needReview ? '内容需审核' : '保存成功';
            exit;
        }
        header('Location: article-edit.php?id=' . urlencode($article['id']));
        exit;
    }
}

// Set default structured data if empty
if (empty($article['structured_data'])) {
    $article['structured_data'] = '';
}

$cats = get_categories();
$allTags = get_tags();
$coverUrl = $article['cover'] ? (substr($article['cover'], 0, 4) === 'http' ? $article['cover'] : SITE_URL . '/' . $article['cover']) : '';
$files = glob(UPLOAD_DIR . '/articles/*');
$files = array_filter($files, 'is_file');
usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

admin_header($isNew ? '写新文章' : '编辑文章');
?>
<style>
.editor-toolbar{display:flex;gap:6px;padding:8px 12px;background:var(--surface-2);border-radius:8px 8px 0 0;border:1px solid var(--border);border-bottom:0;flex-wrap:wrap}
.editor-toolbar button{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:5px 10px;font-size:13px;font-weight:500;cursor:pointer;transition:all .1s;color:var(--text)}
.editor-toolbar button:hover{background:var(--accent);border-color:var(--accent)}
.editor-toolbar .sep{width:1px;background:var(--border-2);margin:0 4px}
.mode-tabs{display:flex;gap:4px;margin-bottom:12px}
.mode-tab{padding:8px 20px;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surface);transition:all .15s;color:var(--text-2)}
.mode-tab:hover{color:var(--text)}
.mode-tab.active{background:var(--accent);border-color:var(--accent);color:var(--text)}
.md-split{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.md-split textarea{border:none;border-radius:0;resize:none;min-height:500px;font-family:var(--mono);font-size:14px;line-height:1.8;padding:16px;background:var(--surface)}
.md-preview{padding:16px;overflow-y:auto;min-height:500px;background:var(--surfaces);border-left:1px solid var(--border);font-size:15px;line-height:1.8}
.md-preview h1,.md-preview h2,.md-preview h3{margin-top:1.2em;margin-bottom:.4em;font-weight:700}
.md-preview h1{font-size:26px;border-bottom:2px solid var(--accent);padding-bottom:6px}
.md-preview h2{font-size:22px}
.md-preview h3{font-size:18px}
.md-preview p{margin-bottom:1em;color:var(--text)}
.md-preview code{background:var(--surface-2);padding:2px 6px;border-radius:4px;font-size:13px}
.md-preview pre{background:#1e1e1e;color:#fff;padding:16px;border-radius:8px;overflow-x:auto;margin-bottom:1em}
.md-preview pre code{background:none;color:#fff;padding:0}
.md-preview blockquote{border-left:4px solid var(--accent);padding:8px 16px;margin:1em 0;background:var(--surface-2);border-radius:0 8px 8px 0;color:var(--text-2)}
.md-preview ul,.md-preview ol{padding-left:24px;margin-bottom:1em}
.md-preview img{max-width:100%;border-radius:8px;margin:1em 0}
.md-preview a{color:#0284c7;text-decoration:underline}
.md-preview strong{font-weight:700}
.md-preview em{font-style:italic}
.rt-editor{border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.rt-editor .rt-content{min-height:500px;padding:16px;outline:none;font-size:15px;line-height:1.8}
.rt-editor .rt-content:empty::before{content:'在此输入内容...';color:var(--text-3)}
.rt-editor .rt-content img{max-width:100%;border-radius:8px;margin:1em 0}
.drop-overlay{position:fixed;inset:0;z-index:9999;display:none;pointer-events:none}
.drop-overlay.show{display:flex;align-items:center;justify-content:center}
.drop-overlay .hint{background:rgba(30,30,30,.92);color:#fff;padding:24px 40px;border-radius:16px;font-size:18px;font-weight:600;box-shadow:0 20px 60px rgba(0,0,0,.3);pointer-events:auto}
.upload-progress{position:fixed;bottom:24px;right:24px;z-index:9998;display:none;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 18px;box-shadow:var(--shadow-lg);max-width:300px}
.upload-progress.show{display:block}
.upload-progress .bar{height:4px;background:var(--surface-2);border-radius:2px;margin-top:8px;overflow:hidden}
.upload-progress .bar .fill{height:100%;background:var(--accent);width:0%;transition:width .2s}

/* ═══ Zen 沉浸写作模式 ═══ */
body.zen-mode{background:#faf9f4;overflow-x:hidden}
body.zen-mode .sidebar{transform:translateX(-110%);opacity:0;pointer-events:none;transition:transform .4s ease,opacity .3s}
body.zen-mode .main{max-width:900px;margin:0 auto;padding:20px 32px 80px;transition:all .4s ease}
body.zen-mode .main>h1{display:none}
body.zen-mode #article-form>div:not(#zenEditorCard){display:none}
body.zen-mode #zenEditorCard{box-shadow:0 24px 60px -24px rgba(0,0,0,.15)}
body.zen-mode #zenEditorCard .mode-tabs{position:sticky;top:70px;z-index:10;background:rgba(250,249,244,.92);backdrop-filter:blur(12px);padding:10px 0;border-radius:12px}
body.zen-mode .rt-editor{min-height:75vh;padding:32px 40px;font-size:17px;line-height:2}
body.zen-mode .md-split textarea,body.zen-mode .md-editor textarea{font-size:16.5px;line-height:2;padding:32px 40px;min-height:75vh}
body.zen-mode .mode-tabs .zen-exit{display:inline-flex}
#zenEditorCard{transition:box-shadow .3s}
</style>
<div class="admin-layout">
  <?php admin_sidebar('articles'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"><?=$isNew?'写新文章':'编辑文章'?></h1>
      <a href="articles.php" class="btn btn-ghost ml-auto">← 返回列表</a>
    </div>

    <?php if (!empty($message)): ?><?=msg('error', $message)?><?php endif; ?>

    <form method="post" id="article-form">
      <?= csrf_field() ?>
      <div class="card">
        <div class="field"><label>文章标题 <span class="hint">· 必填</span></label>
          <div style="display:flex;gap:8px">
            <input type="text" name="title" value="<?=htmlspecialchars($article['title'])?>" required oninput="autoSlug(this.value)" id="titleInput" style="flex:1">
            <button type="button" class="btn btn-ghost btn-sm" onclick="showImportDialog()" title="从网页导入">🌐 导入</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="showAIDialog()" title="AI 辅助">🤖 AI</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="saveAsTemplate()" title="存为模板">📋 存模板</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="loadTemplate()" title="从模板加载">📂 模板</button>
            <button type="button" class="btn btn-ghost btn-sm" id="zenBtn" onclick="toggleZen()" title="沉浸式写作，无干扰">🧘 Zen</button>
            <?php if (!$isNew): ?>
            <a href="../share-card.php?type=article&id=<?=urlencode($article['id'])?>" class="btn btn-ghost btn-sm" target="_blank" title="生成分享卡片">🖼 分享卡</a>
            <a href="qr.php?search=<?=urlencode(mb_substr($article['title'],0,30))?>" class="btn btn-ghost btn-sm" target="_blank" title="二维码">📱 QR</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>URL Slug <span class="hint">· 自动生成，可手动修改</span></label><input type="text" name="slug" id="slug" value="<?=htmlspecialchars($article['slug'])?>" placeholder="article-url-slug"></div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=$article['status']==='draft'?'selected':''?>>草稿</option><option value="published" <?=$article['status']==='published'?'selected':''?>>已发布</option></select></div>
          <div class="field"><label style="display:flex;align-items:center;gap:8px;margin-top:26px;white-space:nowrap"><input type="checkbox" name="member_only" value="1" <?=!empty($article['member_only'])?'checked':''?> style="width:16px;height:16px"> 💎 会员专享 <span class="hint">· 仅会员可读</span></label></div>
        </div>
        <div class="field-row">
          <div class="field"><label>定时发布 <span class="hint">· 留空则立即发布</span></label><input type="datetime-local" name="publish_at" value="<?=htmlspecialchars($article['publish_at'] ?? '')?>"></div>
          <div class="field">
            <?php if (!$isNew):
              $verFile = DATA_DIR . '/versions/articles/' . $article['id'] . '.json';
              $versions = json_read($verFile);
            ?>
            <label>版本历史 <span class="hint">· 共 <?=count($versions)?> 个版本</span></label>
            <select id="versionSelect" onchange="previewVersion(this.value)" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">
              <option value="">当前版本</option>
              <?php foreach (array_reverse($versions) as $v): ?>
              <option value="<?=$v['version']?>">v<?=$v['version']?> — <?=substr($v['saved_at']??'',0,16)?></option>
              <?php endforeach; ?>
            </select>
            <?php endif; ?>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>分类</label><select name="category"><option value="">未分类</option><?php foreach ($cats as $c): ?><option value="<?=htmlspecialchars($c['key'])?>" <?=$article['category']===$c['key']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select></div>
          <div class="field"><label>作者</label><input type="text" name="author" value="<?=htmlspecialchars($article['author'])?>"></div>
        </div>
        <div class="field">
          <label>标签 <span class="hint">· 逗号分隔</span></label>
          <input type="text" name="tags" value="<?=htmlspecialchars(implode(', ', $article['tags'] ?? []))?>" placeholder="标签1, 标签2, 标签3" id="tagsInput">
        </div>
        <?php if (!empty($allTags)): ?>
        <div style="margin-bottom:4px"><?php foreach ($allTags as $t): ?><span class="tag-item" style="cursor:pointer;margin:2px" onclick="addTag('<?=htmlspecialchars($t, ENT_QUOTES)?>')"><?=htmlspecialchars($t)?></span><?php endforeach; ?><span class="text-sm text-muted" style="margin-left:8px">点标签添加</span></div>
        <?php endif; ?>
      </div>

      <!-- Editor Mode Tabs -->
      <input type="hidden" name="editor_mode" id="editorModeInput" value="<?=htmlspecialchars($editorMode)?>">
      <div class="card" id="zenEditorCard" style="padding:16px">
        <div class="mode-tabs">
          <div class="mode-tab <?=$editorMode==='richtext'?'active':''?>" data-mode="richtext" onclick="switchMode('richtext')">📝 富文本</div>
          <div class="mode-tab <?=$editorMode==='markdown'?'active':''?>" data-mode="markdown" onclick="switchMode('markdown')">📄 Markdown <span class="text-sm text-muted">分屏预览</span></div>
          <button type="button" class="zen-exit" onclick="toggleZen()" style="display:none;margin-left:auto;padding:6px 14px;border-radius:99px;border:1px solid var(--border);background:var(--surface);font-size:12.5px;font-weight:600;cursor:pointer">✕ 退出 Zen</button>
          <span style="margin-left:auto;font-size:12px;color:var(--text-3);align-self:center">拖拽图片到编辑器任意位置即可上传并插入</span>
        </div>

        <!-- Rich Text Editor -->
        <div id="richtext-editor" class="rt-editor" style="display:<?=$editorMode==='richtext'?'block':'none'?>">
          <div class="editor-toolbar">
            <button type="button" onclick="rtCmd('bold')"><b>B</b></button>
            <button type="button" onclick="rtCmd('italic')"><i>I</i></button>
            <button type="button" onclick="rtCmd('underline')"><u>U</u></button>
            <span class="sep"></span>
            <button type="button" onclick="rtCmd('formatBlock','h2')">H2</button>
            <button type="button" onclick="rtCmd('formatBlock','h3')">H3</button>
            <span class="sep"></span>
            <button type="button" onclick="rtCmd('insertUnorderedList')">UL</button>
            <button type="button" onclick="rtCmd('insertOrderedList')">OL</button>
            <span class="sep"></span>
            <button type="button" onclick="rtInsertLink()">🔗 链接</button>
            <button type="button" onclick="document.getElementById('rtImgInput').click()">🖼 图片</button>
            <input type="file" id="rtImgInput" accept="image/*" style="display:none" onchange="rtUploadImg(this)">
            <button type="button" onclick="openMediaPicker()">📁 媒体库</button>
            <span class="sep"></span>
            <button type="button" onclick="rtCmd('removeFormat')">清除格式</button>
          </div>
          <div class="rt-content" contenteditable="true" id="rtContent"><?=$editorMode==='richtext'?$article['content']:''?></div>
        </div>

        <!-- Markdown Editor (split screen) -->
        <div id="markdown-editor" class="md-split" style="display:<?=$editorMode==='markdown'?'grid':'none'?>">
          <textarea id="mdInput" oninput="renderMD(this.value)" placeholder="在此输入 Markdown...&#10;&#10;# 标题&#10;内容..."><?=htmlspecialchars($article['content'])?></textarea>
          <div class="md-preview" id="mdPreview"></div>
        </div>

        <!-- Hidden content field for form submission -->
        <textarea name="content" id="contentHidden" style="display:none"><?=htmlspecialchars($article['content'])?></textarea>
      </div>

      <!-- Cover -->
      <div class="card">
        <h2>封面图</h2>
        <div class="field-row">
          <div class="field"><label>封面图片路径</label><input type="text" name="cover" id="cover" value="<?=htmlspecialchars($article['cover'])?>" placeholder="uploads/articles/文件名.jpg"></div>
          <div class="field"><label>或从媒体库选择</label><select onchange="pickCover(this)"><option value="">— 选择图片 —</option><?php foreach ($files as $fp): $n = basename($fp); $rel = 'uploads/articles/' . $n; ?><option value="<?=htmlspecialchars($rel)?>" <?=$article['cover']===$rel?'selected':''?>><?=htmlspecialchars($n)?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field" style="margin-bottom:0"><label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <button type="button" class="btn btn-ghost btn-sm" onclick="openStockPicker()">🖼 从免费图库搜索封面</button>
        </label></div>
        <?php if ($article['cover']): ?>
        <img id="coverPreview" src="<?=htmlspecialchars($coverUrl)?>" style="max-width:300px;max-height:180px;border-radius:8px;margin-top:8px;object-fit:cover">
        <?php endif; ?>
      </div>

      <!-- SEO -->
      <div class="card">
        <h2>SEO 设置</h2>
        <div class="field"><label>SEO 标题</label><input type="text" name="seo_title" value="<?=htmlspecialchars($article['seo_title'])?>" placeholder="留空则使用文章标题"></div>
        <div class="field"><label>SEO 描述</label><textarea name="seo_desc" rows="2"><?=htmlspecialchars($article['seo_desc'])?></textarea></div>
        <div class="field"><label>SEO 关键词 <span class="hint">· 逗号分隔</span></label><input type="text" name="seo_keywords" value="<?=htmlspecialchars($article['seo_keywords'])?>"></div>
      </div>

      <!-- Insertable Components -->
      <div class="card">
        <h2>📦 插入组件</h2>
        <p class="text-sm text-muted mb-4">在文章内插入 CTA / 活动报名 / 资料下载 / Newsletter 订阅组件</p>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <button type="button" class="btn btn-ghost btn-sm" onclick="insertComponent('cta')">📝 插入 CTA</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="insertComponent('event')">🎪 插入活动报名</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="insertComponent('download')">📥 插入资料下载</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="insertComponent('newsletter')">📬 插入订阅表单</button>
        </div>
        <div id="insertPreview" style="margin-top:12px;display:none;border:1px solid var(--border);border-radius:8px;padding:16px;background:var(--surface-2)"></div>
      </div>

      <!-- Structured Data -->
      <div class="card">
        <h2>结构化数据 (JSON-LD)</h2>
        <p class="text-sm text-muted mb-4">自定义 Schema 代码，将嵌入页面 <code>&lt;head&gt;</code></p>
        <textarea name="structured_data" rows="6" style="font-family:var(--mono);font-size:13px;line-height:1.6" placeholder='{"@context":"https://schema.org","@type":"Article","headline":"..."}'><?=htmlspecialchars($article['structured_data'] ?? '')?></textarea>
      </div>

      <!-- Push to Channels -->
      <?php if (!$isNew):
        $allCh = json_read(DATA_DIR . '/channels.json');
        $availCh = array_merge($allCh['domestic'] ?? [], $allCh['international'] ?? []);
        $hasCh = count(array_filter($availCh, fn($c) => $c['enabled']));
      ?>
      <?php if ($hasCh): ?>
      <div class="card">
        <h2>📡 推送到分发渠道</h2>
        <p class="text-sm text-muted mb-4">一键将文章推送到已配置的国内外平台草稿箱</p>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px" id="channelList">
          <?php foreach ($availCh as $ch): if (!$ch['enabled']) continue; ?>
          <label style="display:flex;align-items:center;gap:6px;padding:6px 14px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;background:var(--surface)">
            <input type="checkbox" value="<?=htmlspecialchars($ch['id'])?>" checked style="width:16px;height:16px">
            <?=htmlspecialchars($ch['name'])?>
          </label>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-primary" onclick="pushArticle()">🚀 推送到选中渠道</button>
        <div id="pushResult" style="margin-top:12px"></div>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Internal Links -->
      <?php if (!$isNew): ?>
      <div class="card">
        <h2>内链嗅探</h2>
        <p class="text-sm text-muted mb-4">扫描全站已发布文章，自动推荐可插入的内链</p>
        <button type="button" class="btn btn-ghost" onclick="scanInternalLinks('<?=htmlspecialchars($article['id'])?>')">🔍 扫描内链机会</button>
        <div id="internalLinks" style="margin-top:12px"></div>
      </div>
      <?php endif; ?>

      <div class="flex gap-4">
        <button type="submit" class="btn btn-primary" onclick="syncContent()">保存文章</button>
        <a href="articles.php" class="btn btn-ghost">取消</a>
      </div>
    </form>
  </div>
</div>

<!-- Import Dialog -->
<div style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);align-items:center;justify-content:center" id="importDialog" onclick="if(event.target===this)closeDialog()">
  <div style="background:var(--surface);border-radius:16px;padding:32px;width:600px;max-width:90vw;max-height:80vh;overflow-y:auto">
    <h2 style="margin-bottom:16px">🌐 从网页导入</h2>
    <div class="field"><label>文章 URL</label><input type="url" id="importUrl" placeholder="https://mp.weixin.qq.com/... 或任何网页链接"></div>
    <div class="field"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" id="importDownloadImages" checked style="width:18px;height:18px">自动下载图片到本地</label></div>
    <div class="flex gap-2">
      <button class="btn btn-primary" onclick="doImport()">导入</button>
      <button class="btn btn-ghost" onclick="closeDialog()">取消</button>
    </div>
    <div id="importResult" style="margin-top:16px"></div>
  </div>
</div>

<!-- Stock Picker Dialog -->
<div style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);align-items:center;justify-content:center" id="stockDialog" onclick="if(event.target===this)closeStockPicker()">
  <div style="background:var(--surface);border-radius:16px;padding:24px;width:760px;max-width:94vw;max-height:86vh;overflow-y:auto">
    <div class="flex items-center justify-between mb-4" style="display:flex;align-items:center;justify-content:space-between">
      <h2 style="margin-bottom:0">🖼 免费图库选封面</h2>
      <button class="btn btn-ghost btn-sm" onclick="closeStockPicker()">✕</button>
    </div>
    <div style="display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap">
      <button type="button" class="btn btn-sm <?=''?>" style="padding:6px 12px;border-radius:7px;font-size:12px;font-weight:600;background:var(--accent);border:none;cursor:pointer" id="spTabPexels" onclick="spSetPlat('pexels')">Pexels</button>
      <button type="button" class="btn btn-sm" style="padding:6px 12px;border-radius:7px;font-size:12px;font-weight:600;background:var(--surface-2);border:1px solid var(--border);cursor:pointer" id="spTabUnsplash" onclick="spSetPlat('unsplash')">Unsplash</button>
      <button type="button" class="btn btn-sm" style="padding:6px 12px;border-radius:7px;font-size:12px;font-weight:600;background:var(--surface-2);border:1px solid var(--border);cursor:pointer" id="spTabPixabay" onclick="spSetPlat('pixabay')">Pixabay</button>
    </div>
    <div style="display:flex;gap:8px;margin-bottom:12px">
      <input type="text" id="spQ" placeholder="搜索关键词，如 office / team / leadership" style="flex:1;padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--surface)" onkeydown="if(event.key==='Enter')spSearch(1)">
      <button class="btn btn-primary" onclick="spSearch(1)">搜索</button>
    </div>
    <div id="spResults" style="min-height:120px">
      <div style="text-align:center;padding:40px;color:var(--text-3);font-size:14px">输入关键词搜索，点击图片即可下载并设为封面</div>
    </div>
    <div id="spPager" style="display:none;margin-top:14px;gap:8px;justify-content:center;align-items:center">
      <button class="btn btn-ghost btn-sm" id="spPrev" onclick="spChange(-1)">‹ 上一页</button>
      <span class="text-sm text-muted" id="spPageInfo"></span>
      <button class="btn btn-ghost btn-sm" id="spNext" onclick="spChange(1)">下一页 ›</button>
    </div>
  </div>
</div>

<!-- AI Dialog -->
<div style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);align-items:center;justify-content:center" id="aiDialog" onclick="if(event.target===this)closeDialog()">
  <div style="background:var(--surface);border-radius:16px;padding:32px;width:600px;max-width:90vw;max-height:80vh;overflow-y:auto">
    <h2 style="margin-bottom:16px">🤖 AI 辅助</h2>
    <div class="field"><label>选择操作</label>
      <select id="aiPromptSelect" onchange="selectAIPrompt()">
        <?php
        $aiCfg = json_read(DATA_DIR . '/ai-config.json');
        foreach ($aiCfg['global_prompts'] ?? [] as $pr):
        ?>
        <option value="<?=htmlspecialchars($pr['id'])?>"><?=htmlspecialchars($pr['name'])?></option>
        <?php endforeach; ?>
        <option value="custom">自定义提示词</option>
      </select>
    </div>
    <div class="field"><label>自定义提示词</label><textarea id="aiCustomPrompt" rows="3" placeholder="输入你自己的提示词…" style="font-family:var(--mono)"></textarea></div>
    <div class="field"><label>供应商</label>
      <select id="aiProvider">
        <?php foreach ($aiCfg['providers'] ?? [] as $p): if (!$p['enabled']) continue; ?>
        <option value="<?=htmlspecialchars($p['id'])?>"><?=htmlspecialchars($p['name'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex gap-2">
      <button class="btn btn-primary" onclick="doAI()">生成</button>
      <button class="btn btn-ghost" onclick="closeDialog()">取消</button>
    </div>
    <div id="aiResult" style="margin-top:16px"></div>
  </div>
</div>

<!-- Drag overlay -->
<div class="drop-overlay" id="dropOverlay"><div class="hint">📁 松开以上传文件<br><small>支持图片、PDF、PPT、Word、Excel 等文件</small></div></div>
<div class="upload-progress" id="uploadProgress"><span id="uploadStatus">上传中...</span><div class="bar"><div class="fill" id="uploadFill"></div></div></div>

<script>
var currentMode = '<?=$editorMode?>';
var mdContent = '';

// ─── Zen 沉浸写作模式 ───
function toggleZen() {
  var zen = document.body.classList.toggle('zen-mode');
  document.getElementById('zenBtn').textContent = zen ? '🧘 退出 Zen' : '🧘 Zen';
  var exitBtn = document.querySelector('.zen-exit');
  if (exitBtn) exitBtn.style.display = zen ? 'inline-flex' : 'none';
  // Zen 模式下聚焦编辑器
  if (zen) {
    var t = document.getElementById('mdInput') || document.querySelector('#richtext-editor [contenteditable]');
    setTimeout(function(){ if (t) t.focus(); }, 350);
  }
  document.body.style.overflow = zen ? 'hidden' : '';
  window.scrollTo({top: 0, behavior: 'smooth'});
}
document.addEventListener('keydown', function(e) {
  // Ctrl/Cmd + Shift + Z 切换 Zen 模式
  if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key.toLowerCase() === 'z') {
    e.preventDefault(); toggleZen();
  }
});

// ─── Mode Switching ───
function switchMode(mode) {
  syncContent();
  currentMode = mode;
  document.getElementById('editorModeInput').value = mode;
  document.querySelectorAll('.mode-tab').forEach(function(t) { t.classList.toggle('active', t.dataset.mode === mode); });
  document.getElementById('richtext-editor').style.display = mode === 'richtext' ? 'block' : 'none';
  document.getElementById('markdown-editor').style.display = mode === 'markdown' ? 'grid' : 'none';

  if (mode === 'markdown') {
    var val = document.getElementById('contentHidden').value;
    document.getElementById('mdInput').value = val;
    renderMD(val);
  } else {
    document.getElementById('rtContent').innerHTML = document.getElementById('contentHidden').value;
  }
}

// ─── Sync Content Before Save ───
function syncContent() {
  if (currentMode === 'richtext') {
    document.getElementById('contentHidden').value = document.getElementById('rtContent').innerHTML;
  } else {
    document.getElementById('contentHidden').value = document.getElementById('mdInput').value;
  }
}
document.getElementById('article-form').addEventListener('submit', syncContent);

// ─── Markdown Renderer (Enhanced with tables, code highlight, TOC) ───
function renderMD(src) {
  var html = src;
  
  // Store code blocks to prevent processing inside them
  var codeBlocks = [];
  html = html.replace(/```(\w*)\n([\s\S]*?)```/g, function(match, lang, code) {
    var idx = codeBlocks.length;
    codeBlocks.push({ lang: lang, code: code });
    return '%%CODEBLOCK_' + idx + '%%';
  });
  
  // Inline code (protect from further processing)
  var inlineCodes = [];
  html = html.replace(/`([^`]+)`/g, function(match, code) {
    var idx = inlineCodes.length;
    inlineCodes.push(code);
    return '%%INLINECODE_' + idx + '%%';
  });
  
  // Tables
  html = html.replace(/^(\|.+\|)\n(\|[-:| ]+\|)\n((\|.+\|\n?)+)/gm, function(match, header, separator, body) {
    var headers = header.split('|').filter(function(c) { return c.trim(); });
    var aligns = separator.split('|').filter(function(c) { return c.trim(); }).map(function(c) {
      c = c.trim();
      if (c.startsWith(':') && c.endsWith(':')) return 'center';
      if (c.endsWith(':')) return 'right';
      return 'left';
    });
    var rows = body.trim().split('\n');
    var table = '<table><thead><tr>';
    headers.forEach(function(h, i) {
      table += '<th style="text-align:' + (aligns[i] || 'left') + '">' + h.trim() + '</th>';
    });
    table += '</tr></thead><tbody>';
    rows.forEach(function(row) {
      var cells = row.split('|').filter(function(c) { return c.trim(); });
      table += '<tr>';
      cells.forEach(function(c, i) {
        table += '<td style="text-align:' + (aligns[i] || 'left') + '">' + c.trim() + '</td>';
      });
      table += '</tr>';
    });
    table += '</tbody></table>';
    return table;
  });
  
  // Images (before links to avoid conflict)
  html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" loading="lazy" style="max-width:100%;border-radius:8px">');
  
  // Links
  html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
  
  // Bold + Italic
  html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
  // Bold
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');
  // Italic
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
  html = html.replace(/_(.+?)_/g, '<em>$1</em>');
  // Strikethrough
  html = html.replace(/~~(.+?)~~/g, '<del>$1</del>');
  
  // Headers with IDs for TOC
  html = html.replace(/^#### (.+)$/gm, function(m, text) {
    var id = text.toLowerCase().replace(/[^\w\u4e00-\u9fff]+/g, '-');
    return '<h4 id="' + id + '">' + text + '</h4>';
  });
  html = html.replace(/^### (.+)$/gm, function(m, text) {
    var id = text.toLowerCase().replace(/[^\w\u4e00-\u9fff]+/g, '-');
    return '<h3 id="' + id + '">' + text + '</h3>';
  });
  html = html.replace(/^## (.+)$/gm, function(m, text) {
    var id = text.toLowerCase().replace(/[^\w\u4e00-\u9fff]+/g, '-');
    return '<h2 id="' + id + '">' + text + '</h2>';
  });
  html = html.replace(/^# (.+)$/gm, function(m, text) {
    var id = text.toLowerCase().replace(/[^\w\u4e00-\u9fff]+/g, '-');
    return '<h1 id="' + id + '">' + text + '</h1>';
  });
  
  // Blockquotes
  html = html.replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>');
  // Merge consecutive blockquotes
  html = html.replace(/<\/blockquote>\n<blockquote>/g, '\n');
  
  // Unordered lists
  html = html.replace(/^[\*\-] (.+)$/gm, '<li>$1</li>');
  // Ordered lists
  html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
  // Wrap consecutive <li> in <ul>
  html = html.replace(/((<li>.*<\/li>\n?)+)/g, '<ul>$1</ul>');
  
  // Task lists
  html = html.replace(/<li>\[x\] (.+)<\/li>/g, '<li class="task"><input type="checkbox" checked disabled> $1</li>');
  html = html.replace(/<li>\[ \] (.+)<\/li>/g, '<li class="task"><input type="checkbox" disabled> $1</li>');
  
  // Horizontal rules
  html = html.replace(/^---$/gm, '<hr>');
  html = html.replace(/^\*\*\*$/gm, '<hr>');
  
  // Footnotes (simple implementation)
  html = html.replace(/\[\^(\d+)\]/g, '<sup class="footnote"><a href="#fn$1">[$1]</a></sup>');
  html = html.replace(/^(\d+)\. (.+)$/gm, '<p id="fn$1" class="footnote-item"><strong>$1.</strong> $2</p>');
  
  // Paragraphs (double newlines)
  html = html.replace(/\n\n/g, '</p><p>');
  html = '<p>' + html + '</p>';
  
  // Clean up empty paragraphs
  html = html.replace(/<p><\/p>/g, '');
  html = html.replace(/<p>(<h[1-6]>)/g, '$1');
  html = html.replace(/(<\/h[1-6]>)<\/p>/g, '$1');
  html = html.replace(/<p>(<table>)/g, '$1');
  html = html.replace(/(<\/table>)<\/p>/g, '$1');
  html = html.replace(/<p>(<ul>)/g, '$1');
  html = html.replace(/(<\/ul>)<\/p>/g, '$1');
  html = html.replace(/<p>(<blockquote>)/g, '$1');
  html = html.replace(/(<\/blockquote>)<\/p>/g, '$1');
  html = html.replace(/<p>(<hr>)/g, '$1');
  html = html.replace(/(<hr>)<\/p>/g, '$1');
  
  // Restore code blocks with syntax highlighting
  codeBlocks.forEach(function(block, idx) {
    var langClass = block.lang ? ' class="language-' + block.lang + '"' : '';
    var highlighted = block.code
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    // Basic syntax highlighting for common languages
    if (block.lang === 'php' || block.lang === 'javascript' || block.lang === 'js') {
      highlighted = highlighted
        .replace(/(\/\/.*$)/gm, '<span class="comment">$1</span>')
        .replace(/(\/\*[\s\S]*?\*\/)/g, '<span class="comment">$1</span>')
        .replace(/\b(function|return|if|else|foreach|class|echo|var|let|const)\b/g, '<span class="keyword">$1</span>')
        .replace(/('([^']*)'|"([^"]*)")/g, '<span class="string">$1</span>');
    }
    var pre = '<pre><code' + langClass + '>' + highlighted + '</code></pre>';
    html = html.replace('%%CODEBLOCK_' + idx + '%%', pre);
  });
  
  // Restore inline codes
  inlineCodes.forEach(function(code, idx) {
    html = html.replace('%%INLINECODE_' + idx + '%%', '<code>' + code + '</code>');
  });
  
  document.getElementById('mdPreview').innerHTML = html;
}
if (currentMode === 'markdown') renderMD(document.getElementById('mdInput').value);

// ─── Rich Text Commands ───
function rtCmd(cmd, val) {
  document.getElementById('rtContent').focus();
  document.execCommand(cmd, false, val || null);
}
function rtInsertLink() {
  var url = prompt('输入链接地址:', 'https://');
  if (url) rtCmd('createLink', url);
}
function rtUploadImg(input) {
  var file = input.files[0];
  if (!file) return;
  uploadImage(file, function(url) {
    rtCmd('insertImage', url);
    input.value = '';
  });
}

// ─── Image Upload via AJAX ───
function uploadImage(file, callback) {
  var fd = new FormData();
  fd.append('file', file);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'media-upload.php?dir=articles', true);
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        var resp = JSON.parse(xhr.responseText);
        if (resp.ok && callback) callback(resp.url);
      } catch(e) {}
    }
  };
  xhr.send(fd);
}

// ─── Drag & Drop Upload (Images + Documents) ───
var dropCounter = 0;
var editorContainer = document.querySelector('.card:has(.mode-tabs)') || document.querySelector('.main');

document.addEventListener('dragenter', function(e) {
  if (e.dataTransfer.types.indexOf('Files') >= 0) {
    dropCounter++;
    document.getElementById('dropOverlay').classList.add('show');
  }
});
document.addEventListener('dragleave', function(e) {
  dropCounter--;
  if (dropCounter <= 0) {
    dropCounter = 0;
    document.getElementById('dropOverlay').classList.remove('show');
  }
});
document.addEventListener('dragover', function(e) { e.preventDefault(); });
document.addEventListener('drop', function(e) {
  e.preventDefault();
  document.getElementById('dropOverlay').classList.remove('show');
  dropCounter = 0;
  var files = e.dataTransfer.files;
  for (var i = 0; i < files.length; i++) {
    var file = files[i];
    if (file.type.startsWith('image/')) {
      // 图片：上传并插入
      uploadImage(file, function(url) {
        if (currentMode === 'richtext') {
          rtCmd('insertImage', url);
        } else {
          var ta = document.getElementById('mdInput');
          var imgMd = '![](' + url + ')';
          var cursor = ta.selectionStart;
          if (cursor === undefined) {
            ta.value += '\n' + imgMd + '\n';
          } else {
            ta.value = ta.value.substring(0, cursor) + imgMd + ta.value.substring(ta.selectionEnd);
          }
          renderMD(ta.value);
        }
      });
    } else {
      // 文档文件（PDF/PPT/DOC/XLS 等）：上传并插入下载链接
      uploadDocument(file, function(url, name, type) {
        var linkHtml = '<a href="' + url + '" download class="doc-download">📥 下载 ' + name + ' (' + type + ')</a>';
        if (currentMode === 'richtext') {
          rtCmd('insertHTML', linkHtml);
        } else {
          var ta = document.getElementById('mdInput');
          var mdLink = '[📥 下载 ' + name + '](' + url + ' "' + name + '")';
          var cursor = ta.selectionStart;
          if (cursor === undefined) {
            ta.value += '\n' + mdLink + '\n';
          } else {
            ta.value = ta.value.substring(0, cursor) + mdLink + ta.value.substring(ta.selectionEnd);
          }
          renderMD(ta.value);
        }
      });
    }
  }
});

// ─── Document Upload ───
function uploadDocument(file, callback) {
  var fd = new FormData();
  fd.append('file', file);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'media-upload.php?dir=documents', true);
  xhr.upload.onprogress = function(e) {
    if (e.lengthComputable) {
      var pct = Math.round(e.loaded / e.total * 100);
      document.getElementById('uploadFill').style.width = pct + '%';
      document.getElementById('uploadStatus').textContent = '上传中... ' + pct + '%';
    }
  };
  xhr.onload = function() {
    document.getElementById('uploadProgress').classList.remove('show');
    if (xhr.status === 200) {
      try {
        var resp = JSON.parse(xhr.responseText);
        if (resp.ok && callback) {
          var ext = file.name.split('.').pop().toLowerCase();
          var typeLabels = { pdf: 'PDF', ppt: 'PPT', pptx: 'PPT', doc: 'Word', docx: 'Word', xls: 'Excel', xlsx: 'Excel', zip: 'ZIP', rar: 'RAR' };
          callback(resp.url, file.name, typeLabels[ext] || ext.toUpperCase());
        }
      } catch(e) {}
    }
  };
  document.getElementById('uploadProgress').classList.add('show');
  document.getElementById('uploadStatus').textContent = '上传中...';
  document.getElementById('uploadFill').style.width = '0%';
  xhr.send(fd);
}

// ─── Auto Save Draft ───
var autoSaveTimer = null;
var autoSaveInterval = 30000; // 30秒自动保存
var lastSavedContent = '';
var isDirty = false;

function markDirty() {
  isDirty = true;
}

function startAutoSave() {
  autoSaveTimer = setInterval(function() {
    if (isDirty) {
      saveDraft();
    }
  }, autoSaveInterval);
}

function saveDraft() {
  syncContent();
  var content = document.getElementById('contentHidden').value;
  var title = document.getElementById('titleInput').value;
  
  // 内容无变化则跳过
  if (content === lastSavedContent && title === lastSavedTitle) return;
  
  var articleId = '<?=$article['id'] ?? ''?>';
  if (!articleId) {
    // 新文章：先创建草稿
    articleId = 'article_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);
  }
  
  var fd = new FormData();
  fd.append('action', 'auto_save');
  fd.append('id', articleId);
  fd.append('title', title);
  fd.append('content', content);
  fd.append('editor_mode', currentMode);
  fd.append('status', 'draft');
  
  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'article-edit.php', true);
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        var resp = JSON.parse(xhr.responseText);
        if (resp.ok) {
          lastSavedContent = content;
          lastSavedTitle = title;
          isDirty = false;
          showAutoSaveStatus('✓ 已自动保存 ' + new Date().toLocaleTimeString());
          // 如果是新文章，更新 URL 以包含 ID
          if (!'<?=$article['id'] ?? ''?>' && resp.id) {
            history.replaceState(null, '', 'article-edit.php?id=' + resp.id);
          }
        }
      } catch(e) {}
    }
  };
  xhr.send(fd);
}

function showAutoSaveStatus(msg) {
  var el = document.getElementById('autoSaveStatus');
  if (!el) {
    el = document.createElement('div');
    el.id = 'autoSaveStatus';
    el.style.cssText = 'position:fixed;bottom:20px;right:20px;padding:8px 16px;border-radius:8px;background:var(--surface);border:1px solid var(--border);font-size:12px;color:var(--muted);z-index:9999;transition:opacity .3s';
    document.body.appendChild(el);
  }
  el.textContent = msg;
  el.style.opacity = '1';
  setTimeout(function() { el.style.opacity = '0'; }, 3000);
}

// 监听内容变化
document.addEventListener('input', function(e) {
  if (e.target.matches('#rtContent, #mdInput, #titleInput')) {
    markDirty();
  }
});

// 页面离开前保存
window.addEventListener('beforeunload', function(e) {
  if (isDirty) {
    saveDraft();
    e.returnValue = '有未保存的更改，确定要离开吗？';
  }
});

// 启动自动保存
startAutoSave();
var lastSavedTitle = '<?=$article['title'] ?? ''?>';
lastSavedContent = '<?=addslashes($article['content'] ?? '')?>';

// ─── Media Library Picker ───
function openMediaPicker() {
  var w = window.open('media.php?dir=articles&picker=1', 'mediaPicker', 'width=900,height=600');
}
// Listen for postMessage from media picker
window.addEventListener('message', function(e) {
  if (e.data && e.data.action === 'pickImage') {
    var url = e.data.url;
    if (currentMode === 'richtext') {
      rtCmd('insertImage', url);
    } else {
      var ta = document.getElementById('mdInput');
      ta.value += '\n![](' + url + ')\n';
      renderMD(ta.value);
    }
  }
});

// ─── Cover Image Picker ───
function pickCover(sel) {
  var val = sel.value;
  document.getElementById('cover').value = val;
  var preview = document.getElementById('coverPreview');
  if (val) {
    var url = val.indexOf('http') === 0 ? val : '<?=SITE_URL?>/' + val;
    if (!preview) {
      preview = document.createElement('img');
      preview.id = 'coverPreview';
      preview.style.cssText = 'max-width:300px;max-height:180px;border-radius:8px;margin-top:8px;object-fit:cover';
      sel.parentNode.parentNode.appendChild(preview);
    }
    preview.src = url;
  }
}

// ─── 免费图库选封面 ───
var SP = { platform: 'pexels', page: 1, query: '' };
function openStockPicker() { document.getElementById('stockDialog').style.display = 'flex'; spSetPlat('pexels'); }
function closeStockPicker() { document.getElementById('stockDialog').style.display = 'none'; }
function spSetPlat(p) {
  SP.platform = p;
  ['Pexels','Unsplash','Pixabay'].forEach(function(n, i) {
    var tab = document.getElementById('spTab' + n);
    if (!tab) return;
    var active = (['pexels','unsplash','pixabay'][i] === p);
    tab.style.background = active ? 'var(--accent)' : 'var(--surface-2)';
    tab.style.borderColor = active ? 'var(--accent)' : 'var(--border)';
  });
}
function spSearch(page) {
  SP.query = document.getElementById('spQ').value.trim();
  SP.page = page || 1;
  if (!SP.query) { alert('请输入关键词'); return; }
  var box = document.getElementById('spResults');
  box.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-3)">⏳ 搜索中...</div>';
  fetch('../api/stock.php?action=search&platform=' + SP.platform + '&q=' + encodeURIComponent(SP.query) + '&page=' + SP.page + '&per_page=12')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.ok) { box.innerHTML = '<div style="text-align:center;padding:40px;color:#dc2626">' + (d.error || '搜索失败') + '</div>'; return; }
      if (!d.photos.length) { box.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-3)">未找到相关图片</div>'; return; }
      window.SP_PHOTOS = d.photos;
      box.innerHTML = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px">' + d.photos.map(function(p, i) {
        return '<div style="position:relative;border-radius:8px;overflow:hidden;aspect-ratio:4/3;background:var(--surface-2);cursor:pointer;border:2px solid transparent" onmouseenter="this.style.borderColor=\'var(--accent)\'" onmouseleave="this.style.borderColor=\'transparent\'" onclick="spDownload(' + i + ')">' +
          '<img src="' + p.thumb + '" style="width:100%;height:100%;object-fit:cover" loading="lazy" alt="">' +
          '<span style="position:absolute;left:4px;top:4px;font-size:9px;font-weight:700;padding:2px 6px;border-radius:999px;background:rgba(0,0,0,.55);color:#fff">' + p.platform + '</span>' +
          '<span style="position:absolute;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);color:#fff;font-size:11px;font-weight:600;text-align:center;padding:4px">下载设为封面 ↓</span>' +
          '</div>';
      }).join('') + '</div>';
      document.getElementById('spPager').style.display = 'flex';
      document.getElementById('spPageInfo').textContent = '第 ' + SP.page + ' 页 · 共 ' + d.total + ' 张';
      document.getElementById('spPrev').disabled = SP.page <= 1;
      document.getElementById('spNext').disabled = SP.page * 12 >= d.total;
    })
    .catch(function() { box.innerHTML = '<div style="text-align:center;padding:40px;color:#dc2626">请求失败，请检查图库 API 配置</div>'; });
}
function spChange(delta) { spSearch(SP.page + delta); }
function spDownload(idx) {
  var p = window.SP_PHOTOS[idx];
  if (!p) return;
  fetch('../api/stock.php?action=download', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ url: p.full, dir: 'articles' })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.ok) {
      document.getElementById('cover').value = d.path;
      var preview = document.getElementById('coverPreview');
      if (preview) preview.src = '<?=SITE_URL?>/' + d.path;
      alert('✅ 已下载并设为封面\n来源：' + p.platform + ' · 作者：' + p.photographer);
      closeStockPicker();
    } else {
      alert('❌ ' + (d.error || '下载失败'));
    }
  })
  .catch(function() { alert('❌ 下载失败'); });
}

// ─── Slug ───
function autoSlug(title) {
  var slug = document.getElementById('slug');
  if (!slug.value || slug.dataset.auto !== 'false') {
    var s = title.toLowerCase().replace(/[^a-z0-9\u4e00-\u9fa5-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '').substring(0, 80);
    slug.value = s;
    slug.dataset.auto = 'true';
  }
}
document.getElementById('slug').addEventListener('input', function() { this.dataset.auto = 'false'; });

// ─── Insert Components ───
function insertComponent(type) {
  var templates = {
    cta: '<div style="background:#f4f3e9;border-radius:16px;padding:32px;text-align:center;margin:32px 0"><h3 style="font-size:20px;margin-bottom:8px">{{title}}</h3><p style="color:#6e6e6e;margin-bottom:16px">{{description}}</p><a href="{{url}}" style="display:inline-block;padding:12px 28px;background:#ddff0e;color:#000;border-radius:8px;font-weight:600;text-decoration:none">{{button}}</a></div>',
    event: '<div style="border:2px solid #ddff0e;border-radius:16px;padding:24px;margin:32px 0;background:#fff"><div style="font-size:14px;font-weight:700;margin-bottom:8px">🎪 活动预告</div><h3 style="font-size:18px;margin-bottom:4px">{{title}}</h3><p style="color:#6e6e6e;font-size:14px">{{date}} · {{location}}</p><a href="{{url}}" style="display:inline-block;margin-top:12px;padding:10px 24px;background:#1e1e1e;color:#fff;border-radius:8px;font-weight:600;text-decoration:none">立即报名 →</a></div>',
    download: '<div style="border:1px solid #e2dfd2;border-radius:16px;padding:20px;margin:32px 0;display:flex;align-items:center;gap:16px;background:#fff"><div style="font-size:40px">📥</div><div style="flex:1"><h3 style="font-size:16px;margin-bottom:4px">{{title}}</h3><p style="color:#6e6e6e;font-size:13px">{{description}}</p></div><a href="{{url}}" style="padding:10px 20px;background:#ddff0e;color:#000;border-radius:8px;font-weight:600;text-decoration:none;white-space:nowrap">免费下载</a></div>',
    newsletter: '<div style="background:linear-gradient(135deg,#f7fee7,#d9f99d);border-radius:16px;padding:28px;margin:32px 0;text-align:center"><h3 style="font-size:18px;margin-bottom:4px">📬 {{title}}</h3><p style="color:#6e6e6e;font-size:14px;margin-bottom:16px">{{description}}</p><div style="display:flex;max-width:400px;margin:0 auto;gap:8px"><input type="email" placeholder="your@email.com" style="flex:1;padding:10px 16px;border:1px solid #e2dfd2;border-radius:8px;font-size:14px"><button style="padding:10px 20px;background:#1e1e1e;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer">订阅</button></div></div>',
  };
  var html = templates[type] || '';
  if (currentMode === 'richtext') {
    rtCmd('insertHTML', html);
  } else {
    var ta = document.getElementById('mdInput');
    ta.value += '\n' + html + '\n';
    renderMD(ta.value);
  }
  var preview = document.getElementById('insertPreview');
  preview.style.display = 'block';
  preview.innerHTML = '<div style="font-size:12px;color:var(--text-3);margin-bottom:8px">已插入 ' + type + ' 组件：</div>' + html;
  setTimeout(function() { preview.style.display = 'none'; }, 3000);
}

// ─── Internal Link Scanner ───
function scanInternalLinks(excludeId) {
  var el = document.getElementById('internalLinks');
  el.innerHTML = '<p class="text-sm text-muted">扫描中...</p>';
  var content = '';
  if (currentMode === 'richtext') {
    content = document.getElementById('rtContent').innerHTML;
    // Strip HTML tags for matching
    var tmp = document.createElement('div');
    tmp.innerHTML = content;
    content = tmp.textContent || tmp.innerText || '';
  } else {
    content = document.getElementById('mdInput').value;
  }

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/internal-links.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        var resp = JSON.parse(xhr.responseText);
        if (resp.suggestions && resp.suggestions.length) {
          var html = '<div style="display:grid;gap:8px">';
          resp.suggestions.forEach(function(s) {
            html += '<div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--surface-2);border-radius:8px">';
            html += '<div style="flex:1"><strong style="font-size:14px">' + s.title + '</strong><br><code style="font-size:11px">' + s.url + '</code></div>';
            html += '<button type="button" class="btn btn-primary btn-sm" onclick="insertInternalLink(\'' + s.url + '\',\'' + s.title.replace(/'/g,"\\'") + '\')">插入链接</button>';
            html += '</div>';
          });
          html += '</div>';
          el.innerHTML = html;
        } else {
          el.innerHTML = '<p class="text-sm text-muted">未发现可插入的内链机会</p>';
        }
      } catch(e) {
        el.innerHTML = '<p class="msg msg-error">扫描出错</p>';
      }
    } else {
      el.innerHTML = '<p class="msg msg-error">请求失败</p>';
    }
  };
  xhr.send('id=' + encodeURIComponent(excludeId) + '&content=' + encodeURIComponent(content));
}
function insertInternalLink(url, title) {
  var link = '<a href="' + url + '">' + title + '</a>';
  if (currentMode === 'richtext') {
    rtCmd('insertHTML', link);
  } else {
    var ta = document.getElementById('mdInput');
    var mdLink = '[' + title + '](' + url + ')';
    var cursor = ta.selectionStart;
    if (cursor === undefined) { ta.value += mdLink; }
    else { ta.value = ta.value.substring(0, cursor) + mdLink + ta.value.substring(ta.selectionEnd); }
    renderMD(ta.value);
  }
}

// ─── Push to Channels ───
function pushArticle() {
  var checked = document.querySelectorAll('#channelList input:checked');
  var channels = Array.from(checked).map(function(cb) { return cb.value; });
  if (!channels.length) { alert('请选择至少一个渠道'); return; }
  var btn = document.querySelector('[onclick="pushArticle()"]');
  btn.textContent = '⏳ 推送中...'; btn.disabled = true;
  document.getElementById('pushResult').innerHTML = '';

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/push-article.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    btn.textContent = '🚀 推送到选中渠道'; btn.disabled = false;
    try {
      var resp = JSON.parse(xhr.responseText);
      var html = '';
      if (resp.results) {
        for (var id in resp.results) {
          var r = resp.results[id];
          var label = document.querySelector('#channelList input[value="' + id + '"]');
          var name = label ? label.parentElement.textContent.trim() : id;
          html += '<div style="display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:6px;margin-bottom:4px;background:' + (r.ok ? '#f0fdf4' : '#fef2f2') + '">';
          html += '<span>' + (r.ok ? '✅' : '❌') + '</span>';
          html += '<span style="flex:1">' + name + '</span>';
          html += '<span style="font-size:12px;color:var(--text-3)">' + (r.ok ? '推送成功' : (r.error || 'HTTP ' + r.http)) + '</span>';
          html += '</div>';
        }
      }
      document.getElementById('pushResult').innerHTML = html;
    } catch(e) {
      document.getElementById('pushResult').innerHTML = '<div class="msg msg-error">推送请求失败</div>';
    }
  };
  xhr.send(JSON.stringify({article_id: '<?=htmlspecialchars($article['id'] ?? '')?>', channels: channels, format: 'draft'}));
}

// ─── Version Preview ───
var versions = <?php
$verData = [];
if (!$isNew) {
    $verFile = DATA_DIR . '/versions/articles/' . $article['id'] . '.json';
    $verData = json_read($verFile);
}
echo json_encode($verData, JSON_UNESCAPED_UNICODE);
?>;

function previewVersion(ver) {
  if (!ver) return;
  var found = null;
  for (var i = 0; i < versions.length; i++) {
    if (versions[i].version == ver) { found = versions[i]; break; }
  }
  if (!found) return;
  if (confirm('要预览 v' + ver + ' 的内容吗？点击确定后内容将替换到编辑器（不保存）。如需恢复请手动保存。')) {
    if (currentMode === 'richtext') {
      document.getElementById('rtContent').innerHTML = found.content;
    } else {
      document.getElementById('mdInput').value = found.content;
      renderMD(found.content);
    }
  }
}

// ─── Import Dialog ───
var globalPrompts = <?=json_encode($aiCfg['global_prompts'] ?? [], JSON_UNESCAPED_UNICODE)?>;

function showImportDialog() { document.getElementById('importDialog').style.display = 'flex'; }
function showAIDialog() {
  document.getElementById('aiDialog').style.display = 'flex';
  selectAIPrompt();
}
function closeDialog() {
  document.querySelectorAll('[id$=Dialog]').forEach(function(d) { d.style.display = 'none'; });
}

function selectAIPrompt() {
  var sel = document.getElementById('aiPromptSelect');
  var custom = document.getElementById('aiCustomPrompt');
  if (sel.value === 'custom') {
    custom.style.display = 'block';
  } else {
    custom.style.display = 'none';
    for (var i = 0; i < globalPrompts.length; i++) {
      if (globalPrompts[i].id === sel.value) {
        custom.value = globalPrompts[i].prompt;
        break;
      }
    }
  }
}

function doImport() {
  var url = document.getElementById('importUrl').value.trim();
  if (!url) { alert('请输入 URL'); return; }
  var dl = document.getElementById('importDownloadImages').checked;
  var result = document.getElementById('importResult');
  result.innerHTML = '<p class="text-sm text-muted">⏳ 正在导入…</p>';

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/import-article.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    try {
      var resp = JSON.parse(xhr.responseText);
      if (resp.ok) {
        document.querySelector('input[name="title"]').value = resp.title;
        autoSlug(resp.title);
        if (currentMode === 'richtext') {
          document.getElementById('rtContent').innerHTML = resp.content;
        } else {
          document.getElementById('mdInput').value = resp.content;
        }
        result.innerHTML = '<div class="msg msg-success">✅ 导入成功！标题: ' + resp.title + '，已下载 ' + (resp.images_downloaded || 0) + ' 张图片</div>';
        setTimeout(closeDialog, 2000);
      } else {
        result.innerHTML = '<div class="msg msg-error">❌ ' + (resp.error || '导入失败') + '</div>';
      }
    } catch(e) { result.innerHTML = '<div class="msg msg-error">解析失败</div>'; }
  };
  xhr.onerror = function() { result.innerHTML = '<div class="msg msg-error">请求失败</div>'; };
  xhr.send(JSON.stringify({url: url, download_images: dl}));
}

function doAI() {
  syncContent();
  var content = document.getElementById('contentHidden').value;
  if (!content.trim()) { alert('请先撰写文章内容'); return; }
  var sel = document.getElementById('aiPromptSelect');
  var promptText = sel.value === 'custom' ? document.getElementById('aiCustomPrompt').value : document.getElementById('aiCustomPrompt').value;
  var provider = document.getElementById('aiProvider').value;
  var result = document.getElementById('aiResult');
  result.innerHTML = '<p class="text-sm text-muted">⏳ AI 生成中…</p>';

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/ai-generate.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    try {
      var resp = JSON.parse(xhr.responseText);
      if (resp.ok) {
        result.innerHTML = '<div class="msg msg-success">✅ 生成完成</div>' +
          '<div style="border:1px solid var(--border);border-radius:8px;padding:16px;margin-top:8px;max-height:300px;overflow-y:auto;font-size:14px;line-height:1.6">' +
          resp.result.replace(/\n/g, '<br>') + '</div>' +
          '<button class="btn btn-primary btn-sm mt-4" onclick="applyAIResult(\'' + resp.result.replace(/'/g,"\\'") + '\')">应用到编辑器</button>';
      } else {
        result.innerHTML = '<div class="msg msg-error">❌ ' + (resp.error || '生成失败') + '</div>';
      }
    } catch(e) { result.innerHTML = '<div class="msg msg-error">解析失败</div>'; }
  };
  xhr.onerror = function() { result.innerHTML = '<div class="msg msg-error">请求失败</div>'; };
  xhr.send(JSON.stringify({prompt: promptText, content: content, provider: provider}));
}

function applyAIResult(text) {
  if (currentMode === 'richtext') {
    rtCmd('insertHTML', text.replace(/\n/g, '<br>'));
  } else {
    var ta = document.getElementById('mdInput');
    ta.value += '\n' + text + '\n';
    renderMD(ta.value);
  }
  closeDialog();
}

// ─── Content Templates ───
var templates = <?php
$tplFile = DATA_DIR . '/templates.json';
$tpls = json_read($tplFile);
echo json_encode($tpls['templates'] ?? [], JSON_UNESCAPED_UNICODE);
?>;

function saveAsTemplate() {
  syncContent();
  var name = prompt('模板名称:', '');
  if (!name) return;
  var tpl = {
    id: 'tpl_' + Date.now(),
    name: name,
    title: document.querySelector('input[name="title"]').value,
    content: document.getElementById('contentHidden').value,
    editor_mode: currentMode,
    category: document.querySelector('select[name="category"]').value,
    tags: document.querySelector('input[name="tags"]').value,
    saved_at: new Date().toISOString()
  };
  var xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/templates.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() { alert('模板已保存: ' + name); };
  xhr.send(JSON.stringify({action: 'save', template: tpl}));
}

function loadTemplate() {
  if (!templates.length) { alert('暂无模板'); return; }
  var list = templates.map(function(t, i) { return (i+1) + '. ' + t.name; }).join('\n');
  var choice = prompt('选择模板编号:\n' + list + '\n\n输入编号(1-' + templates.length + '):');
  if (!choice) return;
  var idx = parseInt(choice) - 1;
  if (idx < 0 || idx >= templates.length) { alert('无效选择'); return; }
  var tpl = templates[idx];
  document.querySelector('input[name="title"]').value = tpl.title || '';
  autoSlug(tpl.title || '');
  document.getElementById('contentHidden').value = tpl.content || '';
  if (tpl.category) document.querySelector('select[name="category"]').value = tpl.category;
  if (tpl.tags) document.querySelector('input[name="tags"]').value = tpl.tags;
  if (tpl.editor_mode === 'markdown') { switchMode('markdown'); document.getElementById('mdInput').value = tpl.content || ''; renderMD(tpl.content || ''); }
  else { switchMode('richtext'); document.getElementById('rtContent').innerHTML = tpl.content || ''; }
  alert('已加载模板: ' + tpl.name);
}

// ─── Auto-save ───
var autoSaveTimer = null;
function initAutoSave() {
  var form = document.getElementById('article-form');
  if (!form) return;
  autoSaveTimer = setInterval(function() {
    var title = document.querySelector('input[name="title"]').value;
    if (!title.trim()) return; // Don't auto-save empty articles
    syncContent();
    var formData = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.onload = function() {
      if (xhr.status === 200) {
        var indicator = document.getElementById('autoSaveIndicator');
        if (!indicator) {
          indicator = document.createElement('div');
          indicator.id = 'autoSaveIndicator';
          indicator.style.cssText = 'position:fixed;bottom:80px;right:24px;background:#2e7d32;color:#fff;padding:8px 16px;border-radius:8px;font-size:13px;z-index:9999;opacity:0;transition:opacity .3s';
          document.body.appendChild(indicator);
        }
        indicator.textContent = '⏺ 已自动保存 ' + new Date().toLocaleTimeString();
        indicator.style.opacity = '1';
        setTimeout(function() { indicator.style.opacity = '0'; }, 2000);
      }
    };
    xhr.send(formData);
  }, 30000); // Every 30 seconds
}
<?php if ($article['id'] ?? false): ?>setTimeout(initAutoSave, 5000);<?php endif; ?>

// ─── Ctrl+S 手动 AJAX 保存（不刷新页面）───
function saveArticleAJAX() {
  var form = document.getElementById('article-form');
  if (!form) return;
  syncContent();
  var formData = new FormData(form);
  formData.append('ajax_save', '1');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', window.location.href, true);
  xhr.onload = function() {
    var indicator = document.getElementById('autoSaveIndicator');
    if (!indicator) {
      indicator = document.createElement('div');
      indicator.id = 'autoSaveIndicator';
      indicator.style.cssText = 'position:fixed;bottom:80px;right:24px;background:#2e7d32;color:#fff;padding:8px 16px;border-radius:8px;font-size:13px;z-index:9999;opacity:0;transition:opacity .3s';
      document.body.appendChild(indicator);
    }
    if (xhr.status === 200 && xhr.responseText.indexOf('保存成功') !== -1) {
      indicator.style.background = '#2e7d32';
      indicator.textContent = '✅ 已保存 ' + new Date().toLocaleTimeString();
    } else {
      indicator.style.background = '#c62828';
      indicator.textContent = '❌ 保存失败，请检查字段';
    }
    indicator.style.opacity = '1';
    setTimeout(function() { indicator.style.opacity = '0'; }, 2500);
  };
  xhr.send(formData);
}
document.addEventListener('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
    e.preventDefault();
    saveArticleAJAX();
  }
});

function addTag(tag) {
  var input = document.getElementById('tagsInput');
  var existing = input.value.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
  if (existing.indexOf(tag) === -1) { existing.push(tag); input.value = existing.join(', '); }
}
</script>
<?php admin_footer(); ?>
