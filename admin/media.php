<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('media');

$subdirs = ['all' => '所有文件', 'logo' => 'Logo', 'qrcode' => '二维码', 'cases' => '案例', 'experts' => '专家头像', 'logos' => '客户 Logo', 'articles' => '文章封面', 'thumbs' => '缩略图', 'general' => '通用'];
$currentDir = $_GET['dir'] ?? 'all';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    csrf_verify();
    $targetDir = $currentDir === 'all' ? 'general' : $currentDir;
    $d = UPLOAD_DIR . '/' . $targetDir;
    if (!is_dir($d)) mkdir($d, 0755, true);
    $f = $_FILES['file'];
    if ($f['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg','ico','pdf','mp3','wav','ogg','m4a','mp4','webm','mov'])) {
            $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($f['tmp_name'], $d . '/' . $name);
            $message = '上传成功';
        } else { $message = '不支持的格式'; }
    } else { $message = '上传失败'; }
}

if (isset($_GET['delete'])) {
    $f = basename($_GET['delete']);
    $p = UPLOAD_DIR . '/' . $currentDir . '/' . $f;
    if ($currentDir === 'all') { foreach ($subdirs as $sk => $sv) { if ($sk === 'all') continue; $pp = UPLOAD_DIR . '/' . $sk . '/' . $f; if (file_exists($pp)) { unlink($pp); break; } } }
    elseif (file_exists($p)) { unlink($p); }
    $message = '已删除';
    header('Location: media.php?dir=' . $currentDir);
    exit;
}

// Gather files
$allFiles = [];
if ($currentDir === 'all') {
    foreach ($subdirs as $sk => $sv) {
        if ($sk === 'all') continue;
        foreach (glob(UPLOAD_DIR . '/' . $sk . '/*') as $fp) {
            if (is_file($fp)) $allFiles[] = ['path' => $fp, 'dir' => $sk, 'name' => basename($fp), 'mtime' => filemtime($fp), 'size' => filesize($fp)];
        }
    }
} else {
    foreach (glob(UPLOAD_DIR . '/' . $currentDir . '/*') as $fp) {
        if (is_file($fp)) $allFiles[] = ['path' => $fp, 'dir' => $currentDir, 'name' => basename($fp), 'mtime' => filemtime($fp), 'size' => filesize($fp)];
    }
}
usort($allFiles, fn($a, $b) => $b['mtime'] - $a['mtime']);

// Search filter
$search = $_GET['search'] ?? '';
if ($search) {
    $allFiles = array_values(array_filter($allFiles, fn($f) => mb_strpos(mb_strtolower($f['name']), mb_strtolower($search)) !== false));
}

admin_header('媒体管理');
?>
<style>
.media-toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.media-toolbar .search-box{flex:1;min-width:200px;display:flex;align-items:center;gap:8px;background:var(--surface);border:1.5px solid var(--border);border-radius:8px;padding:4px 4px 4px 14px}
.media-toolbar .search-box input{flex:1;border:none;outline:none;font-size:14px;padding:6px 0;background:transparent}
.media-toolbar .search-box button{padding:6px 16px;border:none;border-radius:6px;background:var(--accent);font-weight:600;cursor:pointer;font-size:13px}
.dir-nav{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap}
.dir-nav a{padding:6px 16px;border-radius:999px;font-size:13px;font-weight:500;text-decoration:none;transition:all .15s;background:var(--surface);border:1px solid var(--border);color:var(--text-2)}
.dir-nav a:hover{border-color:var(--accent);color:var(--text)}
.dir-nav a.active{background:var(--accent);border-color:var(--accent);color:var(--text);font-weight:600}
.dir-nav a .count{font-family:var(--mono);font-size:11px;opacity:.6}
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px}
.media-item{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);transition:transform .15s;position:relative}
.media-item:hover{transform:translateY(-2px)}
.media-item .preview{aspect-ratio:16/10;overflow:hidden;background:var(--surface-2);display:grid;place-items:center}
.media-item .preview img{width:100%;height:100%;object-fit:cover}
.media-item .preview .icon{font-size:36px;color:var(--text-3)}
.media-item .info{padding:10px 12px}
.media-item .info .name{font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.media-item .info .meta{font-size:11px;color:var(--text-3);margin-top:2px}
.media-item .info .dir-tag{font-size:10px;padding:1px 6px;border-radius:999px;background:var(--surface-2);display:inline-block;margin-top:2px}
.media-item .actions{display:flex;gap:4px;padding:0 12px 10px;opacity:0;transition:opacity .15s}
.media-item:hover .actions{opacity:1}
.drop-zone{border:2px dashed var(--border-2);border-radius:var(--radius-lg);padding:36px;text-align:center;cursor:pointer;transition:all .2s;background:var(--surface);margin-bottom:20px}
.drop-zone:hover,.drop-zone.dragover{border-color:var(--accent);background:rgba(221,255,14,.05)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('media'); ?>
  <div class="main">
    <h1>数字资产管理</h1>
    <p class="sub">共 <?=count($allFiles)?> 个文件 · 拖拽上传 · 按目录分类管理</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- Upload -->
    <div class="drop-zone" id="dropzone">
      <div style="font-size:36px;margin-bottom:8px">📁</div>
      <p style="font-weight:600;font-size:15px;color:var(--text-2)">拖拽文件到此处上传</p>
      <p style="font-size:13px;color:var(--text-3)">或 <a href="#" onclick="document.getElementById('fileInput').click();return false" style="color:var(--accent)">点击选择文件</a> · 支持 JPG/PNG/GIF/WebP/SVG/PDF</p>
      <form method="post" enctype="multipart/form-data" style="display:none">
        <?= csrf_field() ?>
        <input type="file" name="file" id="fileInput" accept="image/*,.pdf" onchange="previewAndUpload(this)">
      </form>
      <div id="uploadPreview" style="display:none;margin-top:12px;padding:12px;background:var(--surface-2);border-radius:12px;flex-direction:column;align-items:center;gap:8px">
        <img id="uploadPreviewImg" style="max-width:160px;max-height:120px;border-radius:8px;object-fit:contain;background:#fff" alt="预览">
        <div id="uploadPreviewMeta" style="font-size:13px;color:var(--text-3)"></div>
        <button type="button" class="btn btn-primary btn-sm" onclick="confirmUpload()">上传</button>
      </div>
    </div>

    <!-- Directory Navigation -->
    <div class="dir-nav">
      <?php foreach ($subdirs as $dk => $dl):
        $cnt = 0;
        if ($dk === 'all') $cnt = count($allFiles);
        else $cnt = count(array_filter(glob(UPLOAD_DIR . '/' . $dk . '/*'), 'is_file'));
      ?>
      <a href="?dir=<?=$dk?>&search=<?=urlencode($search)?>" class="<?=$dk===$currentDir?'active':''?>"><?=htmlspecialchars($dl)?> <span class="count"><?=$cnt?></span></a>
      <?php endforeach; ?>
    </div>

    <!-- Search & Toolbar -->
    <div class="media-toolbar">
      <form method="get" class="search-box">
        <input type="hidden" name="dir" value="<?=htmlspecialchars($currentDir)?>">
        <input type="search" name="search" placeholder="搜索文件名…" value="<?=htmlspecialchars($search)?>">
        <button type="submit">搜索</button>
        <?php if ($search): ?><a href="?dir=<?=$currentDir?>" style="font-size:13px;color:var(--text-3);padding:0 4px">✕</a><?php endif; ?>
      </form>
      <span class="text-sm text-muted"><?=count($allFiles)?> 个文件</span>
    </div>

    <!-- Grid -->
    <div class="media-grid" id="mediaGrid">
      <?php if (empty($allFiles)): ?><div class="empty" style="grid-column:1/-1">暂无文件</div><?php endif; ?>
      <?php foreach ($allFiles as $f):
        $url = SITE_URL . '/uploads/' . $f['dir'] . '/' . $f['name'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp','svg']);
        $sizeStr = $f['size'] > 1048576 ? round($f['size']/1048576,1).'MB' : round($f['size']/1024,1).'KB';
      ?>
      <div class="media-item" onclick="pickImage('<?=htmlspecialchars($url)?>')">
        <div class="preview">
          <?php if ($isImg): ?><img src="<?=htmlspecialchars($url)?>" alt="" loading="lazy">
          <?php else: ?><span class="icon">📄</span><?php endif; ?>
        </div>
        <div class="info">
          <div class="name" title="<?=htmlspecialchars($f['name'])?>"><?=htmlspecialchars($f['name'])?></div>
          <div class="meta"><?=$sizeStr?> · <?=date('m-d H:i', $f['mtime'])?></div>
          <span class="dir-tag"><?=htmlspecialchars($subdirs[$f['dir']] ?? $f['dir'])?></span>
        </div>
        <div class="actions">
          <button class="btn btn-ghost btn-sm" onclick="event.stopPropagation();copyUrl('<?=htmlspecialchars($url)?>')">复制</button>
          <a href="?dir=<?=$currentDir?>&delete=<?=urlencode($f['name'])?>" class="btn btn-danger btn-sm" onclick="event.stopPropagation();return confirm('确认删除?')">删除</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function copyUrl(url){navigator.clipboard.writeText(url).then(()=>alert('已复制'))}
function pickImage(url){
  <?php if (isset($_GET['picker'])): ?>
  if(window.opener){window.opener.postMessage({action:'pickImage',url:url},'*');window.close();}
  else copyUrl(url);
  <?php else: ?>
  copyUrl(url);
  <?php endif; ?>
}
// Drag & drop
var dz=document.getElementById('dropzone');
if(dz){
  dz.addEventListener('dragover',function(e){e.preventDefault();this.classList.add('dragover')});
  dz.addEventListener('dragleave',function(){this.classList.remove('dragover')});
  dz.addEventListener('drop',function(e){
    e.preventDefault();this.classList.remove('dragover');
    if(e.dataTransfer.files.length){
      var fd=new FormData();fd.append('file',e.dataTransfer.files[0]);
      fetch('?dir=<?=$currentDir?>',{method:'POST',body:fd}).then(()=>location.reload());
    }
  });
}
// Upload preview
var pendingFile = null;
function previewAndUpload(input) {
  var file = input.files[0];
  if (!file) return;
  pendingFile = file;
  var previewBox = document.getElementById('uploadPreview');
  var img = document.getElementById('uploadPreviewImg');
  var meta = document.getElementById('uploadPreviewMeta');
  if (file.type.indexOf('image/') === 0) {
    img.style.display = 'block';
    img.src = URL.createObjectURL(file);
    var isPDF = file.type === 'application/pdf';
    meta.textContent = file.name + ' · ' + (file.size / 1024).toFixed(1) + ' KB' + (isPDF ? '' : '');
  } else {
    img.style.display = 'none';
    meta.textContent = file.name + ' · ' + (file.size / 1024).toFixed(1) + ' KB';
  }
  previewBox.style.display = 'flex';
}
function confirmUpload() {
  if (!pendingFile) return;
  var fd = new FormData();
  fd.append('file', pendingFile);
  fetch('?dir=<?=$currentDir?>', { method: 'POST', body: fd })
    .then(function(r) { location.reload(); })
    .catch(function(e) { alert('上传失败'); });
}
</script>
<?php admin_footer(); ?>
