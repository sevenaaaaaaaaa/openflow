<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('categories');

$type = $_GET['type'] ?? 'article';
$allowedTypes = ['article', 'download', 'course'];
if (!in_array($type, $allowedTypes)) $type = 'article';

$typeLabels = ['article' => '文章', 'download' => '资料', 'course' => '课程'];

$message = '';
$cats = get_categories($type);

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
                save_categories($type, $cats);
                $message = '分类已添加';
            } else { $message = '分类 key 已存在'; }
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
        save_categories($type, $cats);
        $message = '分类已更新';
    }
    if ($action === 'delete' && isset($_POST['key'])) {
        $cats = array_values(array_filter($cats, fn($c) => $c['key'] !== $_POST['key']));
        save_categories($type, $cats);
        $message = '分类已删除';
    }
    $cats = get_categories($type);
}

// Build parent options
$parentOpts = ['' => '— 顶级分类 —'];
foreach ($cats as $c) {
    if (empty($c['parent'])) $parentOpts[$c['key']] = $c['name'];
}

admin_header('分类管理 - ' . $typeLabels[$type]);
?>
<div class="admin-layout">
  <?php admin_sidebar('categories'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">分类管理</h1>
      <div class="flex gap-2 ml-auto">
        <?php foreach ($allowedTypes as $t): ?>
        <a href="?type=<?=$t?>" class="btn <?=$t===$type?'btn-primary':'btn-ghost'?> btn-sm"><?=htmlspecialchars($typeLabels[$t])?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <p class="sub">当前：<?=$typeLabels[$type]?>分类 · 支持一级/二级层级</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>Key</th><th>名称</th><th>上级分类</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($cats)): ?><tr><td colspan="4" class="empty">暂无分类</td></tr><?php endif; ?>
          <?php foreach ($cats as $c):
            $prefix = empty($c['parent']) ? '' : '— ';
            $pName = '';
            foreach ($cats as $p) { if ($p['key'] === $c['parent']) { $pName = $p['name']; break; } }
          ?>
          <tr>
            <td><code><?=htmlspecialchars($c['key'])?></code></td>
            <td><?=$prefix ? '<span style="color:var(--text-3)">— </span>' : ''?><?=htmlspecialchars($c['name'])?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($pName ?: '顶级')?></td>
            <td>
              <button class="btn btn-ghost btn-sm" onclick="editCat('<?=htmlspecialchars($c['key'],ENT_QUOTES)?>','<?=htmlspecialchars($c['name'],ENT_QUOTES)?>','<?=htmlspecialchars($c['parent']??'',ENT_QUOTES)?>')">编辑</button>
              <form method="post" style="display:inline" data-confirm="确认删除?">
                <?= csrf_field() ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" id="editForm" style="display:none">
      <h2>编辑分类</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="key" id="edit_key">
        <div class="field-row">
          <div class="field"><label>名称</label><input type="text" name="name" id="edit_name" required></div>
          <div class="field"><label>上级分类</label><select name="parent" id="edit_parent"><?php foreach ($parentOpts as $pk => $pv): ?><option value="<?=htmlspecialchars($pk)?>"><?=htmlspecialchars($pv)?></option><?php endforeach; ?></select></div>
        </div>
        <button type="submit" class="btn btn-primary">保存</button>
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('editForm').style.display='none'">取消</button>
      </form>
    </div>

    <div class="card">
      <h2>添加分类</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field-row">
          <div class="field"><label>Key <span class="hint">· 英文标识</span></label><input type="text" name="key" required placeholder="example-key"></div>
          <div class="field"><label>名称</label><input type="text" name="name" required placeholder="分类名称"></div>
        </div>
        <div class="field"><label>上级分类 <span class="hint">· 留空为一级</span></label>
          <select name="parent"><option value="">— 顶级分类 —</option><?php foreach ($parentOpts as $pk => $pv): if ($pk === '') continue; ?><option value="<?=htmlspecialchars($pk)?>"><?=htmlspecialchars($pv)?></option><?php endforeach; ?></select>
        </div>
        <button type="submit" name="action" value="add" class="btn btn-primary">添加</button>
      </form>
    </div>
  </div>
</div>
<script>
function editCat(key, name, parent) {
  document.getElementById('edit_key').value = key;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_parent').value = parent;
  document.getElementById('editForm').style.display = 'block';
  document.getElementById('editForm').scrollIntoView({behavior:'smooth'});
}
</script>
<?php admin_footer(); ?>
