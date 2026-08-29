<?php
/**
 * 全局站点结构管理 — 导航菜单 + Footer + 自定义页面注册
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$navFile = DATA_DIR . '/site-structure.json';
$cfg = json_read($navFile);
$message = '';

// 保存导航菜单 + Footer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $cfg = [
        'nav' => [],
        'footer' => ['columns' => []],
        'custom_pages' => [],
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    // 导航菜单
    foreach (($_POST['nav_label'] ?? []) as $i => $nl) {
        if (empty(trim($nl))) continue;
        $cfg['nav'][] = ['label'=>trim($nl), 'url'=>trim($_POST['nav_url'][$i] ?? '')];
    }
    // Footer 列
    $footerCols = [];
    $colNames = $_POST['fcol_name'] ?? [];
    foreach ($colNames as $ci => $cn) {
        if (empty(trim($cn))) continue;
        $links = [];
        foreach (($_POST['fcol_label'][$ci] ?? []) as $li => $fl) {
            if (empty(trim($fl))) continue;
            $links[] = ['label'=>trim($fl), 'url'=>trim($_POST['fcol_url'][$ci][$li] ?? '')];
        }
        $footerCols[] = ['name'=>trim($cn), 'links'=>$links];
    }
    $cfg['footer']['columns'] = $footerCols;
    // 自定义页面注册
    $customPages = [];
    foreach (($_POST['cp_slug'] ?? []) as $i => $slug) {
        if (empty(trim($slug))) continue;
        $customPages[] = [
            'slug'=>trim($slug), 'name'=>trim($_POST['cp_name'][$i] ?? ''),
            'title'=>trim($_POST['cp_title'][$i] ?? ''),
            'seo_title'=>trim($_POST['cp_seo_title'][$i] ?? ''),
            'seo_desc'=>trim($_POST['cp_seo_desc'][$i] ?? ''),
        ];
    }
    $cfg['custom_pages'] = $customPages;
    json_write($navFile, $cfg);
    $message = '站点结构已保存';
}

// 生成自定义页面文件
if (isset($_GET['generate'])) {
    $slug = basename($_GET['generate']);
    $target = PAGES_DIR . '/' . $slug . '.json';
    if (!file_exists($target)) {
        $all = json_read($navFile);
        $found = null;
        foreach (($all['custom_pages'] ?? []) as $p) if ($p['slug'] === $slug) { $found = $p; break; }
        if ($found) {
            json_write($target, [
                'hero_title' => $found['name'] ?? $found['title'] ?? '',
                'hero_subtitle' => '',
                'hero_description' => '',
                'sections' => [],
            ]);
            flash('success', "页面「{$slug}」已创建，可在页面编辑器中编辑");
        }
    }
    header('Location: /xmp/site-builder');
    exit;
}

if (!defined('OF_EMBED')) admin_header('站点结构');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('site-builder'); ?>
  <div class="main">
<?php endif; ?>
<?php
// B3：浅 CRUD 页归并为本页的子 tab
require_once __DIR__ . '/_subtabs.php';
$SUBTABS = ['self' => ['站点结构', '', 'site-builder'],
            'foot' => ['页脚链接', 'footer-links.php', 'site-builder']];
$__sub = of_subtab_begin($SUBTABS);
if ($__sub === 'self'):
?>
    <h1> 全局站点结构</h1>
    <p class="sub">导航菜单 · Footer · 自定义页面注册（全局维护，所有页面生效）</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <!-- 导航菜单 -->
      <div class="card">
        <h2>🧭 导航菜单</h2>
        <p class="text-sm text-muted mb-4">全站顶部导航 · 保存后前端所有页面生效</p>
        <div id="navList">
          <?php foreach ($cfg['nav'] ?? [] as $ni => $n): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <span style="color:var(--text-3)">≡</span>
            <input type="text" name="nav_label[]" value="<?=htmlspecialchars($n['label'])?>" placeholder="菜单名" style="width:150px;padding:8px;border:1.5px solid var(--border);border-radius:8px">
            <input type="text" name="nav_url[]" value="<?=htmlspecialchars($n['url'])?>" placeholder="/about.html 或 /" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addNav()">+ 添加菜单项</button>
      </div>

      <!-- Footer -->
      <div class="card">
        <h2>🦶 Footer 管理</h2>
        <p class="text-sm text-muted mb-4">页脚栏目与链接</p>
        <div id="footerCols">
          <?php foreach ($cfg['footer']['columns'] ?? [] as $ci => $col): ?>
          <div style="border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;background:var(--surface-2)">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
              <input type="text" name="fcol_name[]" value="<?=htmlspecialchars($col['name'])?>" placeholder="栏目名" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px">
              <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div[style]').remove()">✕</button>
            </div>
            <?php foreach ($col['links'] as $li => $l): ?>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
              <input type="text" name="fcol_label[<?=$ci?>][]" value="<?=htmlspecialchars($l['label'])?>" placeholder="链接名" style="width:150px;padding:7px;border:1.5px solid var(--border);border-radius:8px">
              <input type="text" name="fcol_url[<?=$ci?>][]" value="<?=htmlspecialchars($l['url'])?>" placeholder="URL" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px">
              <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addFooterCol()">+ 添加栏目</button>
      </div>

      <!-- 自定义页面 -->
      <div class="card">
        <h2>📄 自定义页面注册</h2>
        <p class="text-sm text-muted mb-4">注册新页面后可在「CMS → 页面编辑器」中编辑（生成页面文件）</p>
        <div id="cpList">
          <?php foreach ($cfg['custom_pages'] ?? [] as $ci => $p): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;padding:8px;background:var(--surface-2);border-radius:10px">
            <input type="text" name="cp_slug[]" value="<?=htmlspecialchars($p['slug'])?>" placeholder="slug (如 pricing)" style="width:120px;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <input type="text" name="cp_name[]" value="<?=htmlspecialchars($p['name'])?>" placeholder="页面名" style="width:110px;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <input type="text" name="cp_title[]" value="<?=htmlspecialchars($p['title'])?>" placeholder="H1 标题" style="flex:1;min-width:120px;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
            <a href="?generate=<?=urlencode($p['slug'])?>" class="btn btn-ghost btn-sm">生成页面</a>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addCustomPage()">+ 注册页面</button>
      </div>

      <button type="submit" name="save" class="btn btn-primary">保存站点结构</button>
    </form>
  </div>
</div>
<script>
function addNav() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  d.innerHTML = '<span style="color:var(--text-3)">≡</span><input type="text" name="nav_label[]" placeholder="菜单名" style="width:150px;padding:8px;border:1.5px solid var(--border);border-radius:8px"><input type="text" name="nav_url[]" placeholder="/about.html 或 /" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('navList').appendChild(d);
}
var fcolIdx = document.querySelectorAll('#footerCols > div').length;
function addFooterCol() {
  var idx = fcolIdx++;
  var d = document.createElement('div');
  d.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;background:var(--surface-2)';
  d.innerHTML = '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px"><input type="text" name="fcol_name[]" placeholder="栏目名" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div[style]\').remove()">✕</button></div>' +
    '<div style="display:flex;gap:8px;align-items:center;margin-bottom:6px"><input type="text" name="fcol_label[' + idx + '][]" placeholder="链接名" style="width:150px;padding:7px;border:1.5px solid var(--border);border-radius:8px"><input type="text" name="fcol_url[' + idx + '][]" placeholder="URL" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button></div>' +
    '<button type="button" class="btn btn-ghost btn-sm" onclick="addFcolLink(this,' + idx + ')">+ 链接</button>';
  document.getElementById('footerCols').appendChild(d);
}
function addFcolLink(btn, idx) {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:6px';
  d.innerHTML = '<input type="text" name="fcol_label[' + idx + '][]" placeholder="链接名" style="width:150px;padding:7px;border:1.5px solid var(--border);border-radius:8px"><input type="text" name="fcol_url[' + idx + '][]" placeholder="URL" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  btn.parentNode.insertBefore(d, btn);
}
function addCustomPage() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;padding:8px;background:var(--surface-2);border-radius:10px';
  d.innerHTML = '<input type="text" name="cp_slug[]" placeholder="slug (如 pricing)" style="width:120px;padding:7px;border:1.5px solid var(--border);border-radius:8px"><input type="text" name="cp_name[]" placeholder="页面名" style="width:110px;padding:7px;border:1.5px solid var(--border);border-radius:8px"><input type="text" name="cp_title[]" placeholder="H1 标题" style="flex:1;min-width:120px;padding:7px;border:1.5px solid var(--border);border-radius:8px"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('cpList').appendChild(d);
}
</script>
<?php else: of_subtab_include($SUBTABS, $__sub); endif; ?>
<?php if (!defined('OF_EMBED')) admin_footer(); ?>
