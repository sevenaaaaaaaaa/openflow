<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('tags');

$message = '';
$tags = get_tags();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $t = trim($_POST['tag'] ?? '');
        if ($t && !in_array($t, $tags)) {
            $tags[] = $t;
            sort($tags);
            save_tags($tags);
            $message = '标签已添加';
        }
    }
    if ($action === 'delete' && isset($_POST['tag'])) {
        $tags = array_values(array_filter($tags, fn($v) => $v !== $_POST['tag']));
        save_tags($tags);
        $message = '标签已删除';
    }
    $tags = get_tags();
}

admin_header('标签管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('tags'); ?>
  <div class="main">
    <h1>标签管理</h1>
    <p class="sub">管理文章标签</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card">
      <h2>当前标签 (<?=count($tags)?>)</h2>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php if (empty($tags)): ?><span class="text-sm text-muted">暂无标签</span><?php endif; ?>
        <?php foreach ($tags as $t): ?>
        <span class="tag-item"><?=htmlspecialchars($t)?>
          <form method="post" style="display:inline" onsubmit="return confirm('确认删除标签?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="tag" value="<?=htmlspecialchars($t)?>">
            <button type="submit" class="remove" style="border:none;background:none;cursor:pointer;padding:0">×</button>
          </form>
        </span>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <h2>添加标签</h2>
      <form method="post" class="flex gap-4 items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field" style="margin-bottom:0;flex:1"><label>标签名称</label><input type="text" name="tag" required placeholder="如：GEO 优化"></div>
        <button type="submit" class="btn btn-primary">添加</button>
      </form>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
