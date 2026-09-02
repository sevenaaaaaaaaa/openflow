<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('articles');

$message = '';
$showTrash = isset($_GET['trash']);

$page = max(1, (int)($_GET['page'] ?? 1));

if ($showTrash) {
    $articles = json_read(DATA_DIR . '/trash.json');
    $pag = paginate($articles, $page, 50);
    $articles = $pag['items'];
} else {
    $articles = get_articles();
    $pag = paginate($articles, $page, 50);
    $articles = $pag['items'];
}

// Quick copy
if (isset($_GET['copy'])) {
    $orig = get_article($_GET['copy']);
    if ($orig) {
        $orig['title'] .= ' (副本)';
        $orig['slug'] = '';
        $orig['status'] = 'draft';
        $orig['id'] = 'article_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $orig['created_at'] = date('Y-m-d H:i:s');
        $orig['updated_at'] = date('Y-m-d H:i:s');
        save_article($orig['id'], $orig);
        flash('success', '文章已复制');
    }
    header('Location: /xmp/content-hub?tab=articles');
    exit;
}

// Quick update (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update'])) {
    csrf_verify();
    $id = $_POST['id'] ?? '';
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $article = get_article($id);
    if ($article && in_array($field, ['title','slug','category','status','cover','seo_title','seo_desc','seo_keywords','is_pinned'])) {
        if ($field === 'tags') {
            $article['tags'] = array_filter(explode(',', $value));
        } elseif ($field === 'is_pinned') {
            $article['is_pinned'] = in_array((string)$value, ['1', 'true', 'on'], true);
        } else {
            $article[$field] = $value;
        }
        $article['updated_at'] = date('Y-m-d H:i:s');
        save_article($id, $article);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
    exit;
}

// Delete (soft: move to trash)
if (isset($_GET['delete'])) {
    $article = get_article($_GET['delete']);
    if ($article) {
        $trash = json_read(DATA_DIR . '/trash.json');
        $article['deleted_at'] = date('Y-m-d H:i:s');
        $trash[] = $article;
        json_write(DATA_DIR . '/trash.json', $trash);
        delete_article($_GET['delete']);
        flash('success', '文章已移至回收站');
    }
    header('Location: /xmp/content-hub?tab=articles');
    exit;
}

// Restore from trash
if (isset($_GET['restore'])) {
    $trash = json_read(DATA_DIR . '/trash.json');
    $found = null;
    foreach ($trash as $i => $t) {
        if ($t['id'] === $_GET['restore']) { $found = $t; array_splice($trash, $i, 1); break; }
    }
    if ($found) {
        json_write(DATA_DIR . '/trash.json', $trash);
        // Re-save with original ID
        save_article($found['id'], $found);
        flash('success', '文章已从回收站恢复');
    }
    header('Location: /xmp/content-hub?tab=articles&trash=1');
    exit;
}

// Permanently delete from trash
if (isset($_GET['permanent_delete'])) {
    $trash = json_read(DATA_DIR . '/trash.json');
    $trash = array_values(array_filter($trash, fn($t) => $t['id'] !== $_GET['permanent_delete']));
    json_write(DATA_DIR . '/trash.json', $trash);
    flash('success', '文章已永久删除');
    header('Location: /xmp/content-hub?tab=articles&trash=1');
    exit;
}

// Batch operations
if (isset($_POST['batch_action']) && isset($_POST['selected'])) {
    $ids = (array)$_POST['selected'];
    $action = $_POST['batch_action'];
    $count = 0;
    foreach ($ids as $id) {
        $a = get_article($id);
        if (!$a) continue;
        if ($action === 'delete') {
            $trash = json_read(DATA_DIR . '/trash.json');
            $a['deleted_at'] = date('Y-m-d H:i:s');
            $trash[] = $a;
            json_write(DATA_DIR . '/trash.json', $trash);
            delete_article($id);
            $count++;
        } elseif ($action === 'publish') { $a['status'] = 'published'; save_article($id, $a); $count++; }
        elseif ($action === 'draft') { $a['status'] = 'draft'; save_article($id, $a); $count++; }
        elseif ($action === 'category' && !empty($_POST['batch_category'])) { $a['category'] = $_POST['batch_category']; save_article($id, $a); $count++; }
    }
    flash('success', "批量操作完成：{$count} 篇文章已处理");
    header('Location: /xmp/content-hub?tab=articles');
    exit;
}

$cats = get_categories();
$catMap = [];
foreach ($cats as $c) $catMap[$c['key']] = $c['name'];
$allTags = get_tags();

// Search & filter
$search = $_GET['search'] ?? '';
$catFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
if ($search) $articles = array_values(array_filter($articles, fn($a) => mb_strpos(mb_strtolower($a['title']??''), mb_strtolower($search)) !== false));
if ($catFilter) $articles = array_values(array_filter($articles, fn($a) => ($a['category']??'') === $catFilter));
if ($statusFilter) $articles = array_values(array_filter($articles, fn($a) => ($a['status']??'') === $statusFilter));

if (!defined('OF_EMBED')) admin_header('文章管理');
?>
<style>
.inline-edit{padding:2px 6px;margin:-2px -6px;border:1px solid transparent;border-radius:6px;cursor:text;transition:all .1s;display:inline-block;min-width:30px}
.inline-edit:hover{background:var(--hover)}
.inline-edit.editing{border-color:var(--accent);background:var(--surface);outline:none;min-width:160px}
.inline-tag{display:inline-flex;align-items:center;gap:3px;padding:1px 8px;border-radius:999px;background:var(--hover);font-size:11.5px;font-weight:500;color:var(--muted)}
.inline-tag.more{background:none;border:1px dashed var(--border-strong)}
.inline-tag .remove{cursor:pointer;color:var(--text-3);font-size:14px;line-height:1}
.inline-tag .remove:hover{color:var(--danger)}
.inline-select{height:32px;padding:0 26px 0 10px;border:1px solid var(--border);border-radius:9px;font-size:12.5px;font-weight:600;background:var(--surface);color:var(--fg);max-width:150px}
.inline-select.st-published{color:var(--ok);border-color:color-mix(in oklab,var(--ok) 35%,transparent)}
.inline-select.st-scheduled{color:var(--warn);border-color:color-mix(in oklab,var(--warn) 45%,transparent)}
.inline-select.st-draft{color:var(--muted)}
.cover-thumb{width:56px;height:40px;border-radius:8px;object-fit:cover;background:var(--hover);display:block}
.cover-empty{display:grid;place-items:center;color:var(--faint)}
.cover-empty svg{width:18px;height:18px}
.cover-uploader{position:relative;flex:0 0 auto}
.cover-uploader input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%}
.lst-actions .btn.on{background:var(--accent-soft);border-color:transparent;color:var(--accent)}
.lst-pin{display:inline-flex;color:var(--accent);flex:0 0 auto}
.lst-pin svg{width:13px;height:13px}
.lst-sync{font-size:11px;color:var(--ok)}
</style>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('articles'); ?>
  <div class="main">
<?php endif; ?>
    <div class="flex items-center gap-4 mb-4 lst-head">
      <h1 style="margin-bottom:0">文章管理</h1>
      <div class="flex gap-2 ml-auto lst-actions">
        <?php if ($showTrash): ?><a href="<?=of_hub_url()?>" class="btn btn-ghost">← 返回文章列表</a><?php endif; ?>
        <a href="api-batch.php" class="btn btn-ghost">批量导入</a>
        <a href="<?=of_hub_url(['trash'=>1])?>" class="btn btn-ghost <?=$showTrash?'on':''?>">回收站</a>
        <a href="article-edit.php" class="btn btn-primary">写新文章</a>
      </div>
    </div>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 筛选行：搜索 + 分类 + 状态 + 计数，一行 -->
    <div class="lst-filter">
      <form method="get" class="lst-search" role="search">
        <?php if ($showTrash): ?><input type="hidden" name="trash" value="1"><?php endif; ?>
        <?php if ($catFilter): ?><input type="hidden" name="category" value="<?=htmlspecialchars($catFilter)?>"><?php endif; ?>
        <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?=htmlspecialchars($statusFilter)?>"><?php endif; ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg>
        <input type="search" name="search" placeholder="搜索标题、slug…" value="<?=htmlspecialchars($search)?>" aria-label="搜索文章">
      </form>
      <select class="lst-sel" onchange="location.href='?search=<?=urlencode($search)?>&category='+this.value+'&status=<?=$statusFilter?>'" aria-label="分类">
        <option value="">全部分类</option>
        <?php foreach ($cats as $c): ?><option value="<?=htmlspecialchars($c['key'])?>" <?=$catFilter===$c['key']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
      </select>
      <select class="lst-sel" onchange="location.href='?search=<?=urlencode($search)?>&category=<?=$catFilter?>&status='+this.value" aria-label="状态">
        <option value="">全部状态</option>
        <option value="published" <?=$statusFilter==='published'?'selected':''?>>已发布</option>
        <option value="scheduled" <?=$statusFilter==='scheduled'?'selected':''?>>定时</option>
        <option value="draft" <?=$statusFilter==='draft'?'selected':''?>>草稿</option>
      </select>
      <?php if ($search||$catFilter||$statusFilter): ?><a href="<?=of_hub_url($showTrash?['trash'=>1]:[])?>" class="btn btn-ghost btn-sm">清除筛选</a><?php endif; ?>
      <span class="lst-count"><?=count($articles)?> 篇</span>
    </div>

    <?php if (!$showTrash): ?>
    <form method="post" id="batchForm">
      <?= csrf_field() ?>
      <!-- 勾选后才出现的批量操作条（固定在底部） -->
      <div class="of-selbar" id="batchBar" aria-live="polite">
        <span class="sel-n">已选 <b id="batchN">0</b> 篇</span>
        <select name="batch_action" class="lst-sel">
          <option value="">选择操作…</option>
          <option value="publish">发布</option>
          <option value="draft">转为草稿</option>
          <option value="category">修改分类</option>
          <option value="delete">移至回收站</option>
        </select>
        <select name="batch_category" class="lst-sel" style="display:none" id="batchCategorySelect">
          <option value="">选择分类</option>
          <?php foreach ($cats as $c): ?>
          <option value="<?=htmlspecialchars($c['key'])?>"><?=htmlspecialchars($c['name'])?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm" data-confirm="对已选文章执行批量操作？">执行</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="batchClear()">取消选择</button>
      </div>
    <?php endif; ?>

    <div class="card lst-card">
      <?php if (empty($articles)): ?>
        <div class="empty"><?=$showTrash?'回收站为空':'暂无文章，点击「写新文章」开始'?></div>
      <?php else: ?>
      <table id="articleTable" class="lst-table" data-static>
        <thead>
          <tr>
            <?php if (!$showTrash): ?><th class="c-check"><input type="checkbox" id="selectAll" aria-label="全选"></th><?php endif; ?>
            <th class="c-title">文章</th>
            <th class="c-cat">分类</th>
            <th class="c-status">状态</th>
            <th class="c-meta">作者 / 日期</th>
            <th class="c-act">操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($articles as $a): $st = $a['status'] ?? 'draft'; ?>
          <tr data-id="<?=htmlspecialchars($a['id'])?>">
            <?php if (!$showTrash): ?><td class="c-check"><input type="checkbox" name="selected[]" value="<?=htmlspecialchars($a['id'])?>" class="batch-check" aria-label="选择"></td><?php endif; ?>
            <td class="c-title">
              <div class="lst-item">
                <div class="cover-uploader" title="点击更换封面">
                  <?php if (!empty($a['cover'])): $cu = substr($a['cover'],0,4)==='http'?$a['cover']:SITE_URL.'/'.$a['cover']; ?>
                  <img class="cover-thumb" src="<?=htmlspecialchars($cu)?>" alt="">
                  <?php else: ?>
                  <span class="cover-thumb cover-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 16 5-5 4 4 3-3 6 6"/><circle cx="16" cy="9" r="1.5"/></svg></span>
                  <?php endif; ?>
                  <input type="file" accept="image/*" onchange="uploadCover(this,'<?=htmlspecialchars($a['id'])?>')">
                </div>
                <div class="lst-body">
                  <div class="lst-title">
                    <?php if (!empty($a['is_pinned'])): ?><span class="lst-pin" title="已置顶"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5M9 3h6l-1 7 4 3H6l4-3-1-7Z"/></svg></span><?php endif; ?>
                    <span class="inline-edit" data-id="<?=htmlspecialchars($a['id'])?>" data-field="title" title="点击编辑标题"><?=htmlspecialchars($a['title']??'')?></span>
                  </div>
                  <div class="lst-sub">
                    <code class="inline-edit lst-slug" data-id="<?=htmlspecialchars($a['id'])?>" data-field="slug" title="点击编辑 slug"><?=htmlspecialchars($a['slug']??'')?></code>
                    <span class="tags-cell" data-id="<?=htmlspecialchars($a['id'])?>">
                      <?php foreach (array_slice($a['tags']??[], 0, 3) as $t): ?><span class="inline-tag"><?=htmlspecialchars($t)?></span><?php endforeach; ?>
                      <?php if (count($a['tags']??[]) > 3): ?><span class="inline-tag more">+<?=count($a['tags'])-3?></span><?php endif; ?>
                    </span>
                    <?php if (!empty($a['synced_to'])): $syncs = array_map(fn($p) => strtoupper($p), array_keys($a['synced_to'])); ?><span class="lst-sync">已同步 <?=htmlspecialchars(implode('/', $syncs))?></span><?php endif; ?>
                  </div>
                </div>
              </div>
            </td>
            <td class="c-cat">
              <select class="inline-select" data-id="<?=htmlspecialchars($a['id'])?>" data-field="category" onchange="quickUpdate(this)" aria-label="分类">
                <option value="">未分类</option>
                <?php foreach ($cats as $c): ?>
                <option value="<?=htmlspecialchars($c['key'])?>" <?=($a['category']??'')===$c['key']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="c-status">
              <select class="inline-select st-<?=htmlspecialchars($st)?>" data-id="<?=htmlspecialchars($a['id'])?>" data-field="status" onchange="this.className='inline-select st-'+this.value;quickUpdate(this)" aria-label="状态">
                <option value="draft" <?=$st==='draft'?'selected':''?>>草稿</option>
                <option value="published" <?=$st==='published'?'selected':''?>>已发布</option>
                <option value="scheduled" <?=$st==='scheduled'?'selected':''?>>定时</option>
              </select>
              <?php if ($st==='scheduled' && !empty($a['publish_at'])): ?><div class="lst-when"><?=htmlspecialchars(substr($a['publish_at'],0,16))?></div><?php endif; ?>
            </td>
            <td class="c-meta">
              <div><?=htmlspecialchars($a['author']??'')?></div>
              <div class="lst-when"><?=htmlspecialchars(substr($a['created_at']??'',0,10))?></div>
            </td>
            <td class="c-act">
              <?php if ($showTrash): ?>
              <a href="<?=of_hub_url(['trash'=>1,'restore'=>$a['id']])?>" class="btn btn-ghost btn-sm">恢复</a>
              <a href="<?=of_hub_url(['trash'=>1,'permanent_delete'=>$a['id']])?>" class="btn btn-danger btn-sm" data-confirm="永久删除「<?=htmlspecialchars($a['title']??'')?>」？无法恢复。">永久删除</a>
              <?php else: ?>
              <div class="lst-acts">
                <a href="article-edit.php?id=<?=urlencode($a['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
                <a href="../content-preview.php?type=article&id=<?=urlencode($a['id'])?>" class="ib" target="_blank" title="预览"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                <label class="ib" title="<?=($a['is_pinned']??false)?'取消置顶':'置顶'?>"><input type="checkbox" class="pin-toggle" data-id="<?=htmlspecialchars($a['id'])?>" data-field="is_pinned" onchange="quickUpdate(this);this.closest('tr').querySelector('.lst-title').classList.toggle('pinned',this.checked)" <?=($a['is_pinned']??false)?'checked':''?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5M9 3h6l-1 7 4 3H6l4-3-1-7Z"/></svg></label>
                <details class="of-menu">
                  <summary class="ib" title="更多"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg></summary>
                  <div class="of-menu-list">
                    <button type="button" onclick="openExport('<?=urlencode($a['id'])?>','<?=htmlspecialchars($a['title'], ENT_QUOTES)?>')">导出 / 分享</button>
                    <a href="articles.php?copy=<?=urlencode($a['id'])?>">复制一份</a>
                    <a href="article-edit.php?id=<?=urlencode($a['id'])?>#seo">SEO 设置</a>
                    <a href="articles.php?delete=<?=urlencode($a['id'])?>" class="danger" data-confirm="把「<?=htmlspecialchars($a['title']??'', ENT_QUOTES)?>」移至回收站？">移至回收站</a>
                  </div>
                </details>
              </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$showTrash): ?></form><?php endif; ?>
      <?php endif; ?>
      <?=pagination_html($pag, 'articles.php' . ($showTrash ? '?trash=1' : '?'))?>
    </div>

    <!-- Quick Tag Editor Modal -->
    <div class="card" id="tagEditor" style="display:none">
      <h2>编辑标签</h2>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px" id="tagCheckboxes"></div>
      <div class="flex gap-2">
        <button class="btn btn-primary" onclick="saveTags()">保存</button>
        <button class="btn btn-ghost" onclick="document.getElementById('tagEditor').style.display='none'">取消</button>
      </div>
    </div>
  </div>
</div>

<!-- 导出/分享 Modal -->
<div id="exportModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:24px;width:520px;max-width:92vw;max-height:88vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 id="exportTitle" style="margin:0">📤 导出文章</h3>
      <button class="btn btn-ghost btn-sm" onclick="closeExport()">✕</button>
    </div>
    <div id="exportTargets"></div>
    <div id="exportResult" style="display:none"></div>
  </div>
</div>

<script>
// ─── Inline Edit ───
document.querySelectorAll('.inline-edit').forEach(function(el) {
  el.addEventListener('click', function() {
    if (this.classList.contains('editing')) return;
    var val = this.textContent.trim();
    var isSlug = this.dataset.field === 'slug';
    this.contentEditable = true;
    this.classList.add('editing');
    this.focus();
    // Select all text
    var range = document.createRange();
    range.selectNodeContents(this);
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  });
  el.addEventListener('blur', function() {
    if (!this.classList.contains('editing')) return;
    this.contentEditable = false;
    this.classList.remove('editing');
    var newVal = this.textContent.trim();
    var oldVal = this.dataset.oldVal;
    if (newVal !== oldVal && newVal) {
      quickUpdate(this);
    }
  });
  el.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
    if (e.key === 'Escape') { this.textContent = this.dataset.oldVal || this.textContent; this.blur(); }
  });
  el.addEventListener('focus', function() { this.dataset.oldVal = this.textContent.trim(); });
});

// ─── Quick Update (AJAX) ───
function quickUpdate(el) {
  var id = el.dataset.id;
  var field = el.dataset.field;
  var value = el.tagName === 'SELECT' ? el.value : (el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.textContent.trim());
  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'articles.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  el.disabled = true;
  xhr.onload = function() {
    el.disabled = false;
    var ok = false; try { ok = xhr.status === 200 && JSON.parse(xhr.responseText).ok === true; } catch (e) {}
    if (!ok) { ofAlert('保存失败', 'error'); return; }
    var label = el.tagName === 'SELECT' ? (el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : el.value) : (el.checked ? '已置顶' : '已取消置顶');
    ofAlert('已更新：' + label, 'success');
  };
  xhr.onerror = function() { el.disabled = false; ofAlert('网络错误，未保存', 'error'); };
  xhr.send('quick_update=1&id=' + encodeURIComponent(id) + '&field=' + encodeURIComponent(field) + '&value=' + encodeURIComponent(value));
}

// ─── Tag Editor ───
function openTagEditor(id, currentTags) {
  document.getElementById('tagEditor').style.display = 'block';
  document.getElementById('tagEditor').dataset.articleId = id;
  var container = document.getElementById('tagCheckboxes');
  container.innerHTML = '';
  var tags = <?=json_encode($allTags)?>;
  var current = currentTags ? currentTags.split(',').map(function(s) { return s.trim(); }) : [];
  tags.forEach(function(t) {
    var label = document.createElement('label');
    label.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:999px;background:var(--surface-2);cursor:pointer;font-size:13px';
    var cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.value = t;
    cb.checked = current.indexOf(t) >= 0;
    label.appendChild(cb);
    label.appendChild(document.createTextNode(t));
    container.appendChild(label);
  });
}
function saveTags() {
  var id = document.getElementById('tagEditor').dataset.articleId;
  var checked = document.querySelectorAll('#tagCheckboxes input:checked');
  var tags = Array.from(checked).map(function(cb) { return cb.value; }).join(', ');
  var el = document.querySelector('.tags-cell[data-id="' + id + '"]');
  if (el) {
    el.innerHTML = tags ? tags.split(',').map(function(t) { return '<span class="inline-tag">' + t.trim() + '</span>'; }).join('') : '<span class="text-sm text-muted">—</span>';
  }
  quickUpdate({ dataset: { id: id, field: 'tags' }, value: tags });
  document.getElementById('tagEditor').style.display = 'none';
}

// ─── Cover Upload ───
function uploadCover(input, articleId) {
  var file = input.files[0];
  if (!file) return;
  var fd = new FormData();
  fd.append('file', file);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'media-upload.php?dir=articles', true);
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        var resp = JSON.parse(xhr.responseText);
        if (resp.ok) {
          var path = resp.path;
          // Update cover via quick update
          var xhr2 = new XMLHttpRequest();
          xhr2.open('POST', 'articles.php', true);
          xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
          xhr2.onload = function() { location.reload(); };
          xhr2.send('quick_update=1&id=' + encodeURIComponent(articleId) + '&field=cover&value=' + encodeURIComponent(path));
        }
      } catch(e) {}
    }
  };
  xhr.send(fd);
}

// ─── Batch operations ───
function batchSync() {
  var all = document.querySelectorAll('.batch-check'), n = document.querySelectorAll('.batch-check:checked').length;
  var bar = document.getElementById('batchBar'); if (!bar) return;
  bar.classList.toggle('show', n > 0); document.body.classList.toggle('has-selbar', n > 0);
  document.getElementById('batchN').textContent = n;
  var sa = document.getElementById('selectAll'); if (sa) { sa.checked = n > 0 && n === all.length; sa.indeterminate = n > 0 && n < all.length; }
  all.forEach(function (cb) { cb.closest('tr').classList.toggle('sel', cb.checked); });
}
document.getElementById('selectAll')?.addEventListener('change', function() {
  document.querySelectorAll('.batch-check').forEach(function(cb) { cb.checked = this.checked; }, this); batchSync();
});
document.addEventListener('change', function (e) { if (e.target.classList.contains('batch-check')) batchSync(); });
function batchClear() { document.querySelectorAll('.batch-check').forEach(function (cb) { cb.checked = false; }); batchSync(); }
document.querySelector('select[name="batch_action"]')?.addEventListener('change', function() {
  document.getElementById('batchCategorySelect').style.display = this.value === 'category' ? 'inline-block' : 'none';
});

// ─── Filter & Search (client-side) ───
document.addEventListener('keydown', function(e) {
  if (e.ctrlKey && e.key === 'f') {
    // simple Ctrl+F for table
  }
});

// ─── 文章导出/分享 ───
var EXPORT_TARGETS = <?=json_encode(ArticleExport::targets(), JSON_UNESCAPED_UNICODE)?>;
var currentExportId = '';
function openExport(id, title) {
  currentExportId = id;
  var box = document.getElementById('exportModal');
  var inner = document.getElementById('exportTargets');
  inner.innerHTML = '';
  Object.keys(EXPORT_TARGETS).forEach(function(k) {
    var t = EXPORT_TARGETS[k];
    var div = document.createElement('button');
    div.type = 'button';
    div.className = 'btn btn-ghost';
    div.style.cssText = 'display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;width:100%;margin-bottom:6px;text-align:left;font-size:13px';
    div.innerHTML = '<span style="font-size:16px">' + t.icon + '</span><span style="flex:1"><strong>' + t.name + '</strong><div style="font-size:11px;color:var(--text-3)">' + t.desc + '</div></span>';
    div.onclick = function(){ doExport(k); };
    inner.appendChild(div);
  });
  document.getElementById('exportTitle').textContent = '📤 导出：' + title;
  box.style.display = 'flex';
}
function doExport(target) {
  if (target === 'markdown') {
    var w = window.open('../api/article-export.php?action=download&id=' + encodeURIComponent(currentExportId), '_blank');
    closeExport();
    return;
  }
  if (target === 'notebooklm') {
    // 获取 markdown 文本，提示用户粘贴到 NotebookLM
    fetch('../api/article-export.php?action=md&id=' + encodeURIComponent(currentExportId)).then(function(r){return r.text();}).then(function(md){
      showPromptResult('📓 复制以下内容到 NotebookLM（notebooklm.google.com）新建笔记粘贴：', md);
    });
    return;
  }
  // AI 平台：获取 prompt + 打开平台
  fetch('../api/article-export.php?action=prompt&id=' + encodeURIComponent(currentExportId) + '&target=' + target).then(function(r){return r.json();}).then(function(d){
    if (d.ok) {
      var btn = '打开 ' + d.target_name + '';
      showPromptResult(d.target_name + ' 分享内容已生成：', d.prompt, d.url, d.target_name);
    }
  });
}
function showPromptResult(label, prompt, url, urlLabel) {
  document.getElementById('exportTargets').style.display = 'none';
  var res = document.getElementById('exportResult');
  res.style.display = 'block';
  res.innerHTML =
    '<div style="margin-bottom:10px;font-size:13px;font-weight:600">' + label + '</div>' +
    '<textarea id="exportPrompt" rows="12" readonly style="width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px;font-family:var(--mono)">' + prompt.replace(/</g,'&lt;') + '</textarea>' +
    '<div style="display:flex;gap:8px;margin-top:10px">' +
    '<button type="button" class="btn btn-primary btn-sm" onclick="copyPrompt()">📋 复制内容</button>' +
    (url ? '<a href="' + url + '" target="_blank" class="btn btn-ghost btn-sm">🚀 ' + (urlLabel||'打开平台') + '</a>' : '') +
    '<button type="button" class="btn btn-ghost btn-sm" onclick="backToTargets()">← 返回</button>' +
    '</div>';
}
function copyPrompt() {
  var ta = document.getElementById('exportPrompt');
  ta.select(); document.execCommand('copy');
  fcToast && fcToast('✅ 已复制', 'success');
}
function backToTargets() {
  document.getElementById('exportTargets').style.display = '';
  document.getElementById('exportResult').style.display = 'none';
}
function closeExport() {
  document.getElementById('exportModal').style.display = 'none';
  document.getElementById('exportResult').style.display = 'none';
  document.getElementById('exportTargets').style.display = '';
}
</script>
<?php if (!defined('OF_EMBED')) admin_footer(); ?>
