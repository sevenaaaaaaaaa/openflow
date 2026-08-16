<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('downloads');

$downloadsFile = DATA_DIR . '/downloads.json';
$downloads = json_read($downloadsFile);

$id = $_GET['id'] ?? '';
$isNew = empty($id);
$download = ['id' => '', 'title' => '', 'slug' => '', 'description' => '', 'file' => '', 'category' => '', 'tags' => [], 'status' => 'draft', 'seo_title' => '', 'seo_desc' => '', 'form_fields' => ['name','company','email'], 'download_count' => 0, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
if (!$isNew) {
    foreach ($downloads as $d) { if ($d['id'] === $id) { $download = $d; break; } }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $download['title'] = $_POST['title'] ?? '';
    $download['slug'] = $_POST['slug'] ?? '';
    $download['description'] = $_POST['description'] ?? '';
    $download['file'] = $_POST['file'] ?? '';
    $download['category'] = $_POST['category'] ?? '';
    $download['tags'] = array_values(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))));
    $download['status'] = $_POST['status'] ?? 'draft';
    $download['seo_title'] = $_POST['seo_title'] ?? '';
    $download['seo_desc'] = $_POST['seo_desc'] ?? '';
    $download['form_fields'] = array_filter(explode(',', $_POST['form_fields'] ?? ''));
    $download['updated_at'] = date('Y-m-d H:i:s');
    if (empty($download['slug'])) { $download['slug'] = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $download['title']); }

    if ($isNew) {
        $download['id'] = 'dl_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $download['created_at'] = date('Y-m-d H:i:s');
        $downloads[] = $download;
    } else {
        foreach ($downloads as &$d) { if ($d['id'] === $id) { $d = $download; break; } }
    }
    json_write($downloadsFile, $downloads);
    flash('success', $isNew ? '资料已创建' : '资料已保存');
    header('Location: download-edit.php?id=' . urlencode($download['id']));
    exit;
}

$cats = get_categories('download');
$allFiles = [];
foreach (glob(UPLOAD_DIR . '/{articles,general,thumbs}/*', GLOB_BRACE) as $fp) {
    if (is_file($fp)) $allFiles[] = $fp;
}
// Also find PDFs
$pdfs = glob(UPLOAD_DIR . '/{articles,general,thumbs}/*.pdf', GLOB_BRACE);

admin_header($isNew ? '新增资料' : '编辑资料');
?>
<div class="admin-layout">
  <?php admin_sidebar('downloads'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"><?=$isNew?'新增资料':'编辑资料'?></h1>
      <a href="downloads" class="btn btn-ghost ml-auto">← 返回</a>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>基本信息</h2>
        <div class="field-row">
          <div class="field"><label>资料标题</label><input type="text" name="title" value="<?=htmlspecialchars($download['title'])?>" required></div>
          <div class="field"><label>Slug</label><input type="text" name="slug" value="<?=htmlspecialchars($download['slug'])?>" placeholder="自动生成"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>分类</label><select name="category"><option value="">未分类</option><?php foreach ($cats as $c): ?><option value="<?=htmlspecialchars($c['key'])?>" <?=$download['category']===$c['key']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select></div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=$download['status']==='draft'?'selected':''?>>草稿</option><option value="published" <?=$download['status']==='published'?'selected':''?>>已发布</option></select></div>
        </div>
        <div class="field"><label>标签 <span class="hint">· 逗号分隔</span></label><input type="text" name="tags" value="<?=htmlspecialchars(implode(', ', $download['tags'] ?? []))?>" placeholder="白皮书, 增长, SEO"></div>
        <div class="field"><label>描述</label><textarea name="description" rows="3"><?=htmlspecialchars($download['description'])?></textarea></div>
      </div>

      <div class="card">
        <h2>文件上传</h2>
        <?php if ($download['file']): ?><div class="msg msg-info">当前文件: <code><?=htmlspecialchars($download['file'])?></code></div><?php endif; ?>
        <div class="field-row">
          <div class="field"><label>文件路径</label><input type="text" name="file" id="filePath" value="<?=htmlspecialchars($download['file'])?>" placeholder="uploads/xxx.pdf"></div>
          <div class="field"><label>或上传新文件</label>
            <input type="file" accept=".pdf,.zip,.doc,.docx,.pptx,.xlsx" onchange="uploadDLFile(this)">
          </div>
        </div>
        <?php if (!empty($pdfs)): ?>
        <div class="mt-4"><label class="text-sm text-muted">已有 PDF 文件</label>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
            <?php foreach ($pdfs as $pf): $pn = basename($pf); $rel = str_replace(UPLOAD_DIR . '/', 'uploads/', $pf); ?>
            <span class="tag-item" style="cursor:pointer" onclick="document.getElementById('filePath').value='<?=htmlspecialchars($rel)?>'"><?=htmlspecialchars($pn)?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>下载表单设置</h2>
        <p class="text-sm text-muted mb-4">用户下载前需填写的字段（逗号分隔）</p>
        <div class="field"><label>必填字段</label><input type="text" name="form_fields" value="<?=htmlspecialchars(implode(',', $download['form_fields'] ?? ['name','company','email']))?>" placeholder="name, company, email, phone, title"></div>
        <div class="flex gap-2" style="flex-wrap:wrap">
          <?php foreach (['name'=>'姓名','company'=>'企业','email'=>'邮箱','phone'=>'电话','title'=>'职位'] as $fk => $fl): ?>
          <span class="tag-item" style="cursor:pointer" onclick="addFormField('<?=$fk?>')">+ <?=htmlspecialchars($fl)?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <h2>SEO</h2>
        <div class="field"><label>SEO 标题</label><input type="text" name="seo_title" value="<?=htmlspecialchars($download['seo_title'])?>"></div>
        <div class="field"><label>SEO 描述</label><textarea name="seo_desc" rows="2"><?=htmlspecialchars($download['seo_desc'])?></textarea></div>
      </div>

      <button type="submit" class="btn btn-primary">保存</button>
    </form>
  </div>
</div>

<script>
function uploadDLFile(input) {
  var file = input.files[0];
  if (!file) return;
  var fd = new FormData();
  fd.append('file', file);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'media-upload.php?dir=general', true);
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        var resp = JSON.parse(xhr.responseText);
        if (resp.ok) document.getElementById('filePath').value = resp.path;
        else alert(resp.error || '上传失败');
      } catch(e) { alert('上传失败'); }
    }
  };
  xhr.send(fd);
}
function addFormField(field) {
  var input = document.querySelector('input[name="form_fields"]');
  var existing = input.value.split(',').map(function(s){return s.trim()}).filter(Boolean);
  if (existing.indexOf(field) === -1) existing.push(field);
  input.value = existing.join(', ');
}
</script>
<?php admin_footer(); ?>
