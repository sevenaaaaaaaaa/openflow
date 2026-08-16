<?php
/**
 * 品牌数字资产管理 (DAM) — 独立于通用媒体库
 * 按资产类型细分：Logo / 字体 / 色彩 / 图标 / 插画 / 模板 / 视频 / 音频
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('media');

$damFile = DATA_DIR . '/dam.json';
$dam = json_read($damFile);
$message = '';

$assetTypes = [
    'logo' => ['name'=>'Logo 标识', 'icon'=>'🅛', 'color'=>'#ddff0e'],
    'font' => ['name'=>'字体', 'icon'=>'🔠', 'color'=>'#7dd3fc'],
    'color' => ['name'=>'品牌色彩', 'icon'=>'🎨', 'color'=>'#86efac'],
    'icon' => ['name'=>'图标', 'icon'=>'✦', 'color'=>'#f9a8d4'],
    'illustration' => ['name'=>'插画', 'icon'=>'🖼️', 'color'=>'#c4b5fd'],
    'template' => ['name'=>'模板', 'icon'=>'📐', 'color'=>'#fcd34d'],
    'video' => ['name'=>'视频', 'icon'=>'🎬', 'color'=>'#f87171'],
    'audio' => ['name'=>'音频', 'icon'=>'🎵', 'color'=>'#60a5fa'],
];

// 上传处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    csrf_verify();
    $type = $_POST['asset_type'] ?? 'logo';
    $f = $_FILES['file'];
    if ($f['error'] === UPLOAD_ERR_OK) {
        $dir = UPLOAD_DIR . '/dam/' . $type;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $name = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
        move_uploaded_file($f['tmp_name'], $dir . '/' . $name);
        $dam['assets'][$type][] = [
            'id' => 'da_' . time() . '_' . substr(bin2hex(random_bytes(3)), 0, 4),
            'name' => pathinfo($f['name'], PATHINFO_FILENAME),
            'file' => 'uploads/dam/' . $type . '/' . $name,
            'ext' => $ext, 'size' => $f['size'],
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];
        json_write($damFile, $dam);
        $message = '资产已上传';
    }
}

// 保存品牌色彩
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_colors'])) {
    csrf_verify();
    $dam['brand'] = ['colors' => array_filter(array_map('trim', explode("\n", $_POST['colors'] ?? '')))];
    json_write($damFile, $dam);
    $message = '品牌色彩已保存';
}

// 删除资产
if (isset($_GET['delete'])) {
    $type = $_GET['type'] ?? 'logo';
    $id = $_GET['delete'] ?? '';
    foreach (($dam['assets'][$type] ?? []) as $i => $a) {
        if ($a['id'] === $id) {
            $filePath = UPLOAD_DIR . '/' . str_replace('uploads/', '', $a['file']);
            if (file_exists($filePath)) unlink($filePath);
            array_splice($dam['assets'][$type], $i, 1);
            break;
        }
    }
    json_write($damFile, $dam);
    flash('success', '资产已删除');
    header('Location: dam.php?type=' . $type);
    exit;
}

$currentType = $_GET['type'] ?? 'logo';
$colors = $dam['brand']['colors'] ?? [];

admin_header('品牌数字资产');
?>
<div class="admin-layout">
  <?php admin_sidebar('dam'); ?>
  <div class="main">
    <h1>🅛 品牌数字资产</h1>
    <p class="sub">集中管理品牌 Logo · 字体 · 色彩 · 图标 · 插画 · 模板 · 视频 · 音频</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 资产类型导航 -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
      <?php foreach ($assetTypes as $k => $t): $count = count($dam['assets'][$k] ?? []); ?>
      <a href="?type=<?=$k?>" class="btn <?=$currentType===$k?'btn-primary':'btn-ghost'?>" style="gap:6px"><?=$t['icon']?> <?=htmlspecialchars($t['name'])?> <span class="badge badge-gray" style="font-size:10px"><?=$count?></span></a>
      <?php endforeach; ?>
    </div>

    <?php if ($currentType === 'color'): ?>
    <!-- 品牌色彩 -->
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🎨 品牌色彩</h2>
        <p class="text-sm text-muted mb-4">每行一个色值（HEX/RGB），用于前端主题与设计规范</p>
        <textarea name="colors" rows="6" style="width:100%;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--mono)"><?=htmlspecialchars(implode("\n", $colors))?></textarea>
        <button type="submit" name="save_colors" class="btn btn-primary" style="margin-top:10px">保存色彩</button>
      </div>
    </form>
    <!-- 色彩预览 -->
    <?php if ($colors): ?>
    <div class="card">
      <h2>👁 色彩预览</h2>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php foreach ($colors as $c): if (!$c || $c[0] !== '#') continue; ?>
        <div style="width:80px;height:60px;border-radius:10px;background:<?=htmlspecialchars($c)?>;display:flex;align-items:flex-end;justify-content:center;color:#fff;font-size:11px;text-shadow:0 1px 2px rgba(0,0,0,.5);padding-bottom:4px"><?=htmlspecialchars($c)?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: $typeInfo = $assetTypes[$currentType] ?? ['name'=>$currentType,'icon'=>'📄']; ?>
    <!-- 上传 -->
    <form method="post" enctype="multipart/form-data" class="card" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?>
      <input type="hidden" name="asset_type" value="<?=$currentType?>">
      <span style="font-size:24px"><?=$typeInfo['icon']?></span>
      <input type="file" name="file" accept="image/*,video/*,audio/*,.ttf,.otf,.woff,.woff2,.svg,.pdf" style="flex:1;padding:8px;border:1.5px solid var(--border);border-radius:8px">
      <button type="submit" class="btn btn-primary">上传资产</button>
    </form>

    <!-- 资产网格 -->
    <div class="card">
      <h2><?=$typeInfo['icon']?> <?=htmlspecialchars($typeInfo['name'])?> (<?=count($dam['assets'][$currentType] ?? [])?>)</h2>
      <?php if (empty($dam['assets'][$currentType] ?? [])): ?>
      <div class="empty" style="padding:32px">该类型暂无资产，先上传</div>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
        <?php foreach (array_reverse($dam['assets'][$currentType]) as $a): ?>
        <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--surface)">
          <div style="height:110px;background:var(--surface-2);display:grid;place-items:center;overflow:hidden">
            <?php if (in_array($a['ext'], ['jpg','jpeg','png','gif','webp','svg'])): ?>
            <img src="/<?=htmlspecialchars($a['file'])?>" alt="" style="max-width:100%;max-height:100%;object-fit:contain">
            <?php else: ?>
            <span style="font-size:40px"><?=$typeInfo['icon']?></span>
            <?php endif; ?>
          </div>
          <div style="padding:10px">
            <div style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($a['name'])?></div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
              <span style="font-size:11px;color:var(--text-3)"><?=round($a['size']/1024)?>KB</span>
              <span>
                <a href="javascript:copyDAM('<?=htmlspecialchars($a['file'])?>')" class="btn btn-ghost btn-sm">复制</a>
                <a href="?delete=<?=urlencode($a['id'])?>&type=<?=$currentType?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除?')">✕</a>
              </span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
function copyDAM(path) {
  var url = location.origin + '/' + path;
  navigator.clipboard.writeText(url).then(function() {
    if (window.fcToast) window.fcToast('已复制: ' + url, 'success'); else alert('已复制');
  });
}
</script>
<?php admin_footer(); ?>
