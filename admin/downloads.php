<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('downloads');

$downloadsFile = DATA_DIR . '/downloads.json';
$downloads = json_read($downloadsFile);

if (isset($_GET['delete'])) {
    $downloads = array_values(array_filter($downloads, fn($d) => $d['id'] !== $_GET['delete']));
    json_write($downloadsFile, $downloads);
    flash('success', '资料已删除');
    header('Location: downloads.php');
    exit;
}

$cats = get_categories('download');
$catMap = [];
foreach ($cats as $c) $catMap[$c['key']] = $c['name'];

admin_header('资料下载');
?>
<div class="admin-layout">
  <?php admin_sidebar('downloads'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">资料下载</h1>
      <a href="download-edit.php" class="btn btn-primary ml-auto">新增资料</a>
    </div>
    <p class="sub">管理可下载资源（PDF/报告/白皮书），前端需填写表单后下载</p>

    <div class="card" style="padding:0;overflow:auto">
      <?php if (empty($downloads)): ?>
      <div class="empty">
        <div style="font-size:48px;margin-bottom:12px">📥</div>
        <p>暂无资料，点击「新增资料」添加</p>
      </div>
      <?php else: ?>
      <table>
        <thead><tr><th>标题</th><th>文件</th><th>分类</th><th>下载次数</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($downloads as $d): ?>
          <tr>
            <td><strong><?=htmlspecialchars($d['title'])?></strong></td>
            <td><code style="font-size:12px"><?=htmlspecialchars(basename($d['file'] ?? ''))?></code></td>
            <td><span class="badge badge-gray"><?=htmlspecialchars($catMap[$d['category'] ?? ''] ?? $d['category'] ?? '')?></span></td>
            <td class="text-sm text-muted"><?=(int)($d['download_count'] ?? 0)?></td>
            <td><span class="badge <?=($d['status']??'draft')==='published'?'badge-green':'badge-yellow'?>"><?=$d['status']??'draft'?></span></td>
            <td><a href="download-edit.php?id=<?=urlencode($d['id'])?>" class="btn btn-ghost btn-sm">编辑</a><a href="../content-preview.php?type=download&id=<?=urlencode($d['id'])?>" class="btn btn-ghost btn-sm" target="_blank">👁</a><a href="downloads.php?delete=<?=urlencode($d['id'])?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除?')">删除</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
