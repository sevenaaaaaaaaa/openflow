<?php
/**
 * 页面分类管理 — 给页面分组，支持二级层级，可按分类筛选页面列表
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('pages');

$catsFile = DATA_DIR . '/page-categories.json';
$cats = json_read($catsFile);

// 页面清单：硬编码页 + 构建器页
$staticPages = [
    'index' => ['name' => '首页', 'icon' => '🏠'],
    'about' => ['name' => '关于我们', 'icon' => '👤'],
    'capability' => ['name' => '产品', 'icon' => '⚡'],
    'courses' => ['name' => '解决方案', 'icon' => '📚'],
    'flow-community' => ['name' => 'Flow社区', 'icon' => '🌐'],
];
$builderPages = json_read(DATA_DIR . '/builder-pages.json');
foreach ($builderPages as $bp) {
    $staticPages[$bp['id']] = ['name' => $bp['title'] ?? $bp['id'], 'icon' => '🧱'];
}
// 聚合页
$landingPages = get_landing_pages();
foreach ($landingPages as $lp) {
    $staticPages[$lp['id']] = ['name' => $lp['title'] ?? $lp['id'], 'icon' => '🚀'];
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $key = trim($_POST['key'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $parent = trim($_POST['parent'] ?? '');
        if ($key && $name) {
            $exists = false;
            foreach ($cats as $c) { if ($c['key'] === $key) $exists = true; }
            if (!$exists) {
                $cats[] = ['key' => $key, 'name' => $name, 'parent' => $parent];
                json_write($catsFile, $cats);
                $message = '页面分类已添加';
            } else $message = '分类 key 已存在';
        }
    }
    if ($action === 'update') {
        foreach ($cats as &$c) {
            if ($c['key'] === $_POST['key']) {
                $c['name'] = $_POST['name'] ?? $c['name'];
                $c['parent'] = $_POST['parent'] ?? $c['parent'];
                break;
            }
        }
        json_write($catsFile, $cats);
        $message = '分类已更新';
    }
    if ($action === 'delete' && isset($_POST['key'])) {
        $cats = array_values(array_filter($cats, fn($c) => $c['key'] !== $_POST['key']));
        json_write($catsFile, $cats);
        $message = '分类已删除';
    }
    if ($action === 'assign') {
        // 把页面分配到分类
        $assignments = $_POST['assignments'] ?? [];
        json_write(DATA_DIR . '/page-assignments.json', $assignments);
        $message = '页面分类已保存';
    }
    $cats = json_read($catsFile);
}

$assignments = json_read(DATA_DIR . '/page-assignments.json');
$parentOpts = ['' => '— 顶级分类 —'];
foreach ($cats as $c) if (empty($c['parent'])) $parentOpts[$c['key']] = $c['name'];

admin_header('页面分类');
?>
<style>
.cat-table{width:100%;border-collapse:collapse}
.cat-table th{padding:10px 14px;text-align:left;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3)}
.cat-table td{padding:10px 14px;border-top:1px solid var(--border)}
.assign-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.assign-page{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--surface-2);border:1px solid var(--border);border-radius:10px}
.assign-page .picon{width:32px;height:32px;border-radius:8px;background:var(--surface);display:grid;place-items:center;font-size:16px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('page-categories'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">页面分类</h1>
      <div class="flex gap-2 ml-auto">
        <a href="pages-list.php" class="btn btn-ghost btn-sm">← 页面列表</a>
      </div>
    </div>
    <p class="sub">给页面建分类 · 二级层级 · 可按分类组织导航与运营</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="padding:0;overflow:auto;margin-bottom:24px">
      <table class="cat-table">
        <thead><tr><th>Key</th><th>名称</th><th>上级</th><th>页面数</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($cats)): ?><tr><td colspan="5" class="empty">暂无分类，先添加一个</td></tr><?php endif; ?>
          <?php foreach ($cats as $c):
            $pageCount = count(array_filter($assignments, fn($a) => $a === $c['key']));
            $parentName = $c['parent'] ? (collect_cat_name($cats, $c['parent'])) : '—';
          ?>
          <tr>
            <td><code><?=htmlspecialchars($c['key'])?></code></td>
            <td>
              <span class="inline-edit-cat" data-key="<?=htmlspecialchars($c['key'])?>" data-field="name" style="font-weight:600;cursor:pointer"><?=htmlspecialchars($c['name'])?></span>
            </td>
            <td><span class="text-sm text-muted"><?=htmlspecialchars($parentName)?></span></td>
            <td><span class="badge badge-gray"><?=$pageCount?></span></td>
            <td>
              <button class="btn btn-ghost btn-sm" onclick="deleteCat('<?=htmlspecialchars($c['key'])?>')">删除</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="margin-bottom:24px">
      <h2>➕ 添加页面分类</h2>
      <form method="post" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field"><label>Key（英文标识）</label><input type="text" name="key" required placeholder="e.g. product"></div>
        <div class="field"><label>名称</label><input type="text" name="name" required placeholder="e.g. 产品页"></div>
        <div class="field"><label>上级分类</label><select name="parent"><?php foreach ($parentOpts as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?></select></div>
        <button type="submit" class="btn btn-primary">添加</button>
      </form>
    </div>

    <div class="card">
      <h2>📌 页面分配</h2>
      <p class="sub">把每个页面归到分类下（每页一个主分类）</p>
      <form method="post" onsubmit="return confirm('保存页面分类分配？')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign">
        <div class="assign-grid">
          <?php foreach ($staticPages as $pid => $pv): ?>
          <div class="assign-page">
            <span class="picon"><?=$pv['icon']?></span>
            <div style="flex:1">
              <div style="font-size:14px;font-weight:600"><?=htmlspecialchars($pv['name'])?></div>
              <code style="font-size:11px;color:var(--text-3)"><?=htmlspecialchars($pid)?></code>
            </div>
            <select name="assignments[<?=htmlspecialchars($pid)?>]" style="padding:5px 8px;border:1px solid var(--border);border-radius:6px;background:var(--surface);font-size:12px">
              <option value="">未分类</option>
              <?php foreach ($cats as $c): ?>
              <option value="<?=htmlspecialchars($c['key'])?>" <?=($assignments[$pid] ?? '')===$c['key']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:16px">保存分配</button>
      </form>
    </div>
  </div>
</div>

<?php
function collect_cat_name(array $cats, string $key): string {
    foreach ($cats as $c) if ($c['key'] === $key) return $c['name'];
    return $key;
}
?>

<script>
function deleteCat(key) {
  if (!confirm('确定删除分类？')) return;
  var fd = new FormData();
  fd.append('action', 'delete');
  fd.append('key', key);
  fetch('page-categories.php', {method: 'POST', body: fd, headers: {'X-CSRF-Token': '<?=csrf_token()?>'}}).then(function(){ location.reload(); });
}
</script>
<?php admin_footer(); ?>
