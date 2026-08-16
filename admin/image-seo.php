<?php
/**
 * 图片 SEO 管理 — Alt 标签 / 元数据
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ImageMeta.php';
require_login();
require_perm('media');

$message = '';

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_alt') {
        $path = $_POST['path'] ?? '';
        $alt = $_POST['alt'] ?? '';
        if ($path) {
            ImageMeta::setAlt($path, $alt);
            flash('success', 'Alt 文本已更新');
        }
        header('Location: image-seo.php');
        exit;
    } elseif ($action === 'batch_update') {
        $alts = $_POST['alt'] ?? [];
        $count = 0;
        foreach ($alts as $path => $alt) {
            if ($path) {
                ImageMeta::setAlt($path, $alt);
                $count++;
            }
        }
        flash('success', "已更新 {$count} 张图片的 Alt 文本");
        header('Location: image-seo.php');
        exit;
    } elseif ($action === 'auto_generate') {
        $dir = $_POST['dir'] ?? '';
        $count = ImageMeta::autoGenerateAlts($dir);
        flash('success', "已为 {$count} 张图片自动生成 Alt 文本");
        header('Location: image-seo.php');
        exit;
    }
}

// 获取图片列表
$allMeta = ImageMeta::all();
$missingAlts = ImageMeta::getMissingAlts();

// 扫描上传目录获取所有图片
$allImages = [];
$dirs = ['articles', 'cases', 'experts', 'general', 'thumbs'];
foreach ($dirs as $dir) {
    $dirPath = UPLOAD_DIR . '/' . $dir;
    if (!is_dir($dirPath)) continue;
    $files = glob($dirPath . '/{*.jpg,*.jpeg,*.png,*.gif,*.webp}', GLOB_BRACE);
    foreach ($files as $file) {
        $relativePath = 'uploads/' . $dir . '/' . basename($file);
        $meta = $allMeta[$relativePath] ?? [];
        $allImages[] = [
            'path' => $relativePath,
            'url' => SITE_URL . '/' . $relativePath,
            'alt' => $meta['alt'] ?? '',
            'has_alt' => !empty($meta['alt']),
            'file' => $file,
        ];
    }
}

// 筛选
$filter = $_GET['filter'] ?? '';
if ($filter === 'missing') {
    $allImages = array_filter($allImages, fn($img) => !$img['has_alt']);
} elseif ($filter === 'has_alt') {
    $allImages = array_filter($allImages, fn($img) => $img['has_alt']);
}
$allImages = array_values($allImages);

$totalCount = count($allImages);
$withAltCount = count(array_filter($allImages, fn($img) => $img['has_alt']));
$missingAltCount = $totalCount - $withAltCount;

admin_header('图片 SEO 管理');
?>
<style>
.img-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:.15s}
.img-card:hover{border-color:var(--accent)}
.img-preview{width:100%;aspect-ratio:16/9;object-fit:cover;background:var(--surface-2)}
.img-info{padding:12px}
.img-path{font-size:11px;color:var(--muted);word-break:break-all;margin-bottom:6px}
.img-alt-input{width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;resize:none}
.img-alt-input:focus{border-color:var(--accent);outline:none}
.img-status{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600}
.img-status.ok{background:#dcfce7;color:#166534}
.img-status.missing{background:#fee2e2;color:#991b1b}
</style>
<div class="admin-layout">
  <?php admin_sidebar('media'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0">🖼️ 图片 SEO</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <span class="badge badge-gray"><?=$totalCount?> 张图片</span>
        <span class="badge badge-green"><?=$withAltCount?> 已设 Alt</span>
        <span class="badge badge-red"><?=$missingAltCount?> 缺少 Alt</span>
      </div>
    </div>
    <p class="sub">管理图片 Alt 标签，提升搜索引擎可访问性和 SEO 排名</p>

    <!-- 操作栏 -->
    <div class="card mb-4">
      <div class="flex items-center gap-4" style="flex-wrap:wrap">
        <div class="flex gap-2">
          <a href="?filter=" class="btn btn-sm <?=!$filter?'btn-primary':'btn-ghost'?>">全部 (<?=$totalCount?>)</a>
          <a href="?filter=missing" class="btn btn-sm <?=$filter==='missing'?'btn-primary':'btn-ghost'?>">缺少 Alt (<?=$missingAltCount?>)</a>
          <a href="?filter=has_alt" class="btn btn-sm <?=$filter==='has_alt'?'btn-primary':'btn-ghost'?>">已设 Alt (<?=$withAltCount?>)</a>
        </div>
        <div style="margin-left:auto">
          <form method="post" style="display:flex;gap:8px;align-items:center">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="auto_generate">
            <select name="dir" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px">
              <option value="">所有目录</option>
              <?php foreach ($dirs as $d): ?>
              <option value="<?=$d?>"><?=$d?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('将为没有 Alt 的图片自动生成默认 Alt，确认?')">⚡ 自动生成 Alt</button>
          </form>
        </div>
      </div>
    </div>

    <!-- 批量编辑表单 -->
    <form method="post" id="batchForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="batch_update">

      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
        <?php foreach ($allImages as $img): ?>
        <div class="img-card">
          <img src="<?=$img['url']?>" alt="<?=htmlspecialchars($img['alt'])?>" class="img-preview" onerror="this.style.display='none'">
          <div class="img-info">
            <div class="img-path">
              <?=htmlspecialchars($img['path'])?>
              <span class="img-status <?=$img['has_alt']?'ok':'missing'?>" style="float:right">
                <?=$img['has_alt']?'✓ 有 Alt':'✗ 缺少'?>
              </span>
            </div>
            <textarea name="alt[<?=htmlspecialchars($img['path'])?>]" class="img-alt-input" rows="2" placeholder="输入 Alt 文本..."><?=htmlspecialchars($img['alt'])?></textarea>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($allImages)): ?>
      <div style="margin-top:20px;text-align:center">
        <button type="submit" class="btn btn-primary">💾 保存所有修改</button>
      </div>
      <?php endif; ?>
    </form>

    <?php if (empty($allImages)): ?>
    <div class="card">
      <div class="empty" style="padding:40px">
        <div style="font-size:48px;margin-bottom:12px">🖼️</div>
        <p>暂无图片</p>
        <p class="text-sm text-muted">上传图片后，可在此管理 Alt 标签</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- SEO 提示 -->
    <div class="card" style="margin-top:20px">
      <h2>📝 Alt 标签 SEO 最佳实践</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-top:12px">
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <strong>✅ 好的 Alt</strong>
          <p class="text-sm text-muted" style="margin-top:4px"><code>OpenFlow 后台管理界面截图</code></p>
          <p class="text-sm text-muted">描述图片内容，包含关键词</p>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <strong>❌ 差的 Alt</strong>
          <p class="text-sm text-muted" style="margin-top:4px"><code>image.jpg</code> 或 <code>图片</code></p>
          <p class="text-sm text-muted">无意义或过于简短</p>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <strong>🎯 长度建议</strong>
          <p class="text-sm text-muted" style="margin-top:4px">50-125 个字符</p>
          <p class="text-sm text-muted">过长会被截断，过短缺少信息</p>
        </div>
        <div style="padding:14px;background:var(--surface-2);border-radius:8px">
          <strong>🔑 关键词</strong>
          <p class="text-sm text-muted" style="margin-top:4px">自然融入，不要堆砌</p>
          <p class="text-sm text-muted">搜索引擎会判断是否过度优化</p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
