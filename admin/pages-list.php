<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('pages');

$pages = [
    'index' => ['name' => '首页', 'icon' => '🏠', 'desc' => '网站首页 Hero、产品、案例、CTA'],
    'about' => ['name' => '关于我们', 'icon' => '👤', 'desc' => '创始人寄语、使命愿景、专家团队'],
    'capability' => ['name' => '产品', 'icon' => '⚡', 'desc' => '内容引擎、AI Agent、营销自动化'],
    'courses' => ['name' => '解决方案', 'icon' => '📚', 'desc' => '四层课程体系、交付形态'],
    'flow-community' => ['name' => 'Flow社区', 'icon' => '🌐', 'desc' => '社区内容中心'],
];

$seoFile = DATA_DIR . '/seo.json';
$seo = json_read($seoFile);

// Copy page content
if (isset($_GET['copy']) && isset($pages[$_GET['copy']])) {
    $src = $_GET['copy'];
    $dst = $_GET['to'] ?? '';
    if ($dst && isset($pages[$dst]) && $src !== $dst) {
        $srcContent = page_content($src);
        save_page_content($dst, $srcContent);
        flash('success', "已从「{$pages[$src]['name']}」复制内容到「{$pages[$dst]['name']}」");
    }
    header('Location: pages-list.php');
    exit;
}

// Quick update (AJAX) for page content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update'])) {
    csrf_verify();
    $page = $_POST['page'] ?? '';
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    if (isset($pages[$page])) {
        $content = page_content($page);
        $content[$field] = $value;
        save_page_content($page, $content);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
    exit;
}

// Quick SEO update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_seo'])) {
    csrf_verify();
    $page = $_POST['page'] ?? '';
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    if (isset($pages[$page])) {
        if (!isset($seo[$page])) $seo[$page] = [];
        $seo[$page][$field] = $value;
        json_write($seoFile, $seo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }
    echo json_encode(['ok' => false]);
    exit;
}

$search = $_GET['search'] ?? '';
$filteredPages = $pages;
if ($search) {
    $filteredPages = array_filter($pages, function($p) use ($search) {
        $s = mb_strtolower($search);
        return mb_strpos(mb_strtolower($p['name']), $s) !== false || mb_strpos(mb_strtolower($p['desc']), $s) !== false;
    });
}

admin_header('页面列表');
?>
<style>
.page-row{display:grid;grid-template-columns:auto 1.5fr 1fr 2fr auto;gap:16px;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border);transition:background .1s}
.page-row:hover{background:var(--surface-2)}
.page-row:last-child{border-bottom:0}
.page-row .icon{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;background:var(--surface-2);font-size:18px}
.page-row .name{font-weight:600;font-size:15px}
.page-row .desc{font-size:13px;color:var(--text-3);margin-top:2px}
.page-row .fields{display:flex;flex-direction:column;gap:4px}
.page-row .fields .inline-edit{padding:3px 6px;border:1px solid transparent;border-radius:4px;cursor:text;transition:all .1s;font-size:13px;display:inline-block}
.page-row .fields .inline-edit:hover{background:var(--surface)}
.page-row .fields .inline-edit.editing{border-color:#2b5f7e;background:#fff}
.page-row .actions{display:flex;gap:6px}
.page-seo{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;padding:12px;background:var(--surface-2);border-radius:8px}
.page-seo .inline-edit{font-size:12px}
.seo-label{font-size:11px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('pages'); ?>
  <div class="main">
    <h1>页面列表</h1>
    <p class="sub">所有页面 · 点击字段直接编辑 · 支持快速复制和 SEO 设置</p>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap;align-items:center">
      <form method="get" style="display:flex;gap:8px;flex:1;max-width:400px">
        <div style="flex:1;display:flex;align-items:center;gap:8px;background:var(--surface);border:1.5px solid var(--border);border-radius:8px;padding:4px 4px 4px 14px">
          <input type="search" name="search" placeholder="搜索页面…" value="<?=htmlspecialchars($search)?>" style="flex:1;border:none;outline:none;font-size:14px;padding:6px 0;background:transparent">
          <button type="submit" style="padding:6px 16px;border:none;border-radius:6px;background:var(--accent);font-weight:600;cursor:pointer;font-size:13px">搜索</button>
        </div>
      </form>
      <span class="text-sm text-muted"><?=count($filteredPages)?>/<?=count($pages)?> 页</span>
      <?php if ($search): ?><a href="pages-list.php" class="btn btn-ghost btn-sm">清除</a><?php endif; ?>
      <div style="flex:1"></div>
      <!-- 新增页面 / 落地页入口 -->
      <a href="page-builder.php" class="btn btn-primary">🧱 新增页面</a>
      <a href="landing-pages.php" class="btn btn-ghost">🚀 落地页搭建</a>
      <a href="site-builder.php" class="btn btn-ghost">🗂 站点结构</a>
    </div>

    <div class="card" style="padding:0">
      <div style="display:grid;grid-template-columns:auto 1.5fr 1fr 2fr auto;gap:16px;padding:12px 20px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);border-bottom:1px solid var(--border-2)">
        <span></span><span>页面</span><span>关键内容</span><span>SEO 标题 / 描述</span><span>操作</span>
      </div>

      <?php foreach ($filteredPages as $pk => $pv):
        $pc = page_content($pk);
        $ps = $seo[$pk] ?? [];
        // Get first 3 content fields for preview
        $previewFields = array_slice(array_keys($pc), 0, 3);
      ?>
      <div class="page-row" data-page="<?=$pk?>">
        <div class="icon"><?=$pv['icon']?></div>
        <div>
          <div class="name"><?=htmlspecialchars($pv['name'])?></div>
          <div class="desc"><?=htmlspecialchars($pv['desc'])?></div>
          <a href="pages.php?page=<?=$pk?>" class="btn btn-ghost btn-sm" style="margin-top:6px">编辑全部</a>
        </div>
        <div class="fields">
          <?php foreach ($previewFields as $fk):
            $fv = $pc[$fk] ?? '';
            $display = mb_strlen($fv) > 30 ? mb_substr($fv, 0, 30) . '…' : $fv;
          ?>
          <div class="inline-edit" data-page="<?=$pk?>" data-field="<?=htmlspecialchars($fk)?>" title="点击编辑"><?=htmlspecialchars($display ?: '（空）')?></div>
          <?php endforeach; ?>
        </div>
        <div class="page-seo">
          <div>
            <div class="seo-label">SEO 标题</div>
            <div class="inline-edit" data-page="<?=$pk?>" data-field="seo_title" data-is-seo="1" title="点击编辑"><?=htmlspecialchars(mb_substr($ps['title']??'',0,40) ?: '（空）')?></div>
          </div>
          <div>
            <div class="seo-label">SEO 描述</div>
            <div class="inline-edit" data-page="<?=$pk?>" data-field="seo_desc" data-is-seo="1" title="点击编辑"><?=htmlspecialchars(mb_substr($ps['description']??'',0,40) ?: '（空）')?></div>
          </div>
        </div>
        <div class="actions">
          <a href="pages.php?page=<?=$pk?>" class="btn btn-ghost btn-sm">✏️</a>
          <div style="position:relative;display:inline-block">
            <button class="btn btn-ghost btn-sm" onclick="this.nextElementSibling.style.display='block'">📋</button>
            <div style="display:none;position:absolute;top:100%;right:0;z-index:10;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:8px;box-shadow:var(--shadow-lg);white-space:nowrap;min-width:180px">
              <div style="font-size:12px;font-weight:600;color:var(--text-3);padding:4px 8px;margin-bottom:4px">复制内容到…</div>
              <?php foreach ($pages as $tk => $tv): if ($tk === $pk) continue; ?>
              <a href="pages-list.php?copy=<?=$pk?>&to=<?=$tk?>" style="display:block;padding:6px 8px;font-size:13px;border-radius:4px;text-decoration:none;color:var(--text)"><?=htmlspecialchars($tv['name'])?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <a href="<?=SITE_URL?>/<?=$pk==='index'?'':$pk.'.html'?>" class="btn btn-ghost btn-sm" target="_blank">👁</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
// ─── Inline Edit for Page Content ───
document.querySelectorAll('.page-row .inline-edit').forEach(function(el) {
  el.addEventListener('click', function() {
    if (this.classList.contains('editing')) return;
    var isTextarea = (this.textContent.length > 30 || this.dataset.field === 'seo_desc');
    if (isTextarea) {
      var current = this.dataset.origText || this.textContent.replace('（空）', '').replace('…','');
      var newVal = prompt('编辑 ' + this.dataset.field, current);
      if (newVal !== null && newVal !== current) {
        this.textContent = newVal.length > 30 ? newVal.substring(0, 30) + '…' : newVal;
        this.dataset.origText = newVal;
        quickUpdatePage(this);
      }
      return;
    }
    this.contentEditable = true;
    this.classList.add('editing');
    this.focus();
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
    quickUpdatePage(this);
  });
  el.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
  });
});

function quickUpdatePage(el) {
  var page = el.dataset.page;
  var field = el.dataset.field;
  var isSeo = el.dataset.isSeo === '1';
  var value = el.textContent.trim().replace('（空）', '').replace('…','');
  // Use original text if stored
  if (el.dataset.origText) value = el.dataset.origText;

  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'pages-list.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  var param = isSeo ? 'quick_seo=1' : 'quick_update=1';
  xhr.send(param + '&page=' + encodeURIComponent(page) + '&field=' + encodeURIComponent(field) + '&value=' + encodeURIComponent(value));
}

// Close copy dropdowns on click outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('[onclick*="nextElementSibling.style.display"]')) {
    document.querySelectorAll('[style*="position:absolute"][style*="top:100%"]').forEach(function(el) {
      el.style.display = 'none';
    });
  }
});
</script>
<?php admin_footer(); ?>
