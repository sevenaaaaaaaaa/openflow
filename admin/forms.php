<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('forms');

$formsFile = DATA_DIR . '/forms/index.json';
$forms = json_read($formsFile);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $data = [
            'title' => $_POST['title'] ?? '',
            'type' => $_POST['type'] ?? 'lead',
            'slug' => $_POST['slug'] ?? '',
            'success_message' => $_POST['success_message'] ?? '提交成功',
            'btn_text' => $_POST['btn_text'] ?? '提交',
            'newsletter_list_id' => $_POST['newsletter_list_id'] ?? '',
            'webhook_url' => $_POST['webhook_url'] ?? '',
            'fields' => [],
            'status' => $_POST['status'] ?? 'draft',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($data['slug'])) $data['slug'] = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $data['title']);

        $fieldKeys = $_POST['field_key'] ?? [];
        foreach ($fieldKeys as $i => $fk) {
            if (empty(trim($fk))) continue;
            $data['fields'][] = [
                'key' => $fk,
                'label' => $_POST['field_label'][$i] ?? $fk,
                'type' => $_POST['field_type'][$i] ?? 'text',
                'required' => isset($_POST['field_required'][$i]),
                'placeholder' => $_POST['field_placeholder'][$i] ?? '',
                'options' => $_POST['field_options'][$i] ?? '',
            ];
        }

        if (empty($id)) {
            $data['id'] = 'form_' . substr(bin2hex(random_bytes(6)), 0, 8);
            $data['created_at'] = date('Y-m-d H:i:s');
            $forms[] = $data;
        } else {
            foreach ($forms as &$f) { if ($f['id'] === $id) { $f = array_merge($f, $data); break; } }
        }
        json_write($formsFile, $forms);
        $message = '表单已保存';
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $forms = array_values(array_filter($forms, fn($f) => $f['id'] !== $_POST['id']));
        json_write($formsFile, $forms);
        $message = '表单已删除';
        header('Location: /xmp/forms');
        exit;
    }
    $forms = json_read($formsFile);
}

$editForm = null;
if (isset($_GET['edit'])) {
    foreach ($forms as $f) { if ($f['id'] === $_GET['edit']) { $editForm = $f; break; } }
}

$typeLabels = ['lead' => '预约/线索', 'download' => '资料下载', 'newsletter' => 'Newsletter 订阅'];
$bmConfig = json_read(DATA_DIR . '/billionmail.json');

admin_header('表单管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('forms'); ?>
  <div class="main">
    <h1>表单管理</h1>
    <p class="sub">管理全站表单：预约诊断、资料下载、Newsletter 订阅 · 字段自定义 · 自动关联</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>表单名称</th><th>类型</th><th>Slug</th><th>字段数</th><th>状态</th><th>嵌入代码</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($forms)): ?><tr><td colspan="7" class="empty">暂无表单</td></tr><?php endif; ?>
          <?php foreach ($forms as $f): ?>
          <tr>
            <td><strong><?=htmlspecialchars($f['title'])?></strong></td>
            <td><span class="badge badge-gray"><?=htmlspecialchars($typeLabels[$f['type']] ?? $f['type'])?></span></td>
            <td><code><?=htmlspecialchars($f['slug'])?></code></td>
            <td><?=count($f['fields'] ?? [])?></td>
            <td><span class="badge <?=($f['status']??'draft')==='published'?'badge-green':'badge-yellow'?>"><?=$f['status']??'draft'?></span></td>
            <td><code style="font-size:11px" onclick="copy(this)">[form slug="<?=htmlspecialchars($f['slug'])?>"]</code></td>
            <td><a href="?edit=<?=urlencode($f['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
              <form method="post" style="display:inline" data-confirm="删除表单「<?=htmlspecialchars($f['title'],ENT_QUOTES)?>」？已收集的提交记录会保留。">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=htmlspecialchars($f['id'])?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">删除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" id="editor">
      <h2><?=$editForm?'编辑表单':'创建新表单'?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?=htmlspecialchars($editForm['id'] ?? '')?>">
        <div class="field-row">
          <div class="field"><label>表单名称</label><input type="text" name="title" value="<?=htmlspecialchars($editForm['title'] ?? '')?>" required></div>
          <div class="field"><label>Slug</label><input type="text" name="slug" value="<?=htmlspecialchars($editForm['slug'] ?? '')?>" placeholder="自动生成"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>类型</label>
            <select name="type" id="formType" onchange="toggleType()">
              <option value="lead" <?=($editForm['type']??'')==='lead'?'selected':''?>>预约 / 线索收集</option>
              <option value="download" <?=($editForm['type']??'')==='download'?'selected':''?>>资料下载 (表单门控)</option>
              <option value="newsletter" <?=($editForm['type']??'')==='newsletter'?'selected':''?>>Newsletter 订阅</option>
            </select>
          </div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=($editForm['status']??'')==='draft'?'selected':''?>>草稿</option><option value="published" <?=($editForm['status']??'')==='published'?'selected':''?>>已发布</option></select></div>
        </div>
        <div class="field-row">
          <div class="field"><label>提交按钮文字</label><input type="text" name="btn_text" value="<?=htmlspecialchars($editForm['btn_text'] ?? '提交')?>"></div>
          <div class="field"><label>提交成功提示</label><input type="text" name="success_message" value="<?=htmlspecialchars($editForm['success_message'] ?? '提交成功')?>"></div>
        </div>

        <!-- Newsletter: BillionMail list sync -->
        <div id="newsletterConfig" style="display:<?=($editForm['type']??'')==='newsletter'?'block':'none'?>">
          <div class="field"><label>BillionMail 邮件列表 ID <span class="hint">订阅用户将自动同步到此列表</span></label>
            <input type="text" name="newsletter_list_id" value="<?=htmlspecialchars($editForm['newsletter_list_id'] ?? '')?>" placeholder="在 BillionMail 中创建的列表 ID">
          </div>
          <?php if (!($bmConfig['enabled'] ?? false)): ?>
          <div class="msg msg-warning" style="background:#fef08a;color:#854d0e;border:1px solid #fde047;padding:8px 12px;border-radius:8px;font-size:13px;margin-bottom:12px">⚠️ BillionMail 未启用，订阅数据将本地存储但不会同步到邮件列表</div>
          <?php endif; ?>
        </div>

        <!-- Webhook for download forms -->
        <div id="downloadConfig" style="display:<?=($editForm['type']??'')==='download'?'block':'none'?>">
          <div class="field"><label>资料 Slug <span class="hint">填写后提交即返回下载链接</span></label>
            <select name="download_slug">
              <option value="">— 不关联 —</option>
              <?php foreach (json_read(DATA_DIR . '/downloads.json') as $d): ?>
              <option value="<?=htmlspecialchars($d['slug']??'')?>" <?=($editForm['download_slug']??'')===$d['slug']?'selected':''?>><?=htmlspecialchars($d['title'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field"><label>Webhook URL <span class="hint">提交后推送数据（可选）</span></label><input type="url" name="webhook_url" value="<?=htmlspecialchars($editForm['webhook_url'] ?? '')?>" placeholder="https://hooks.example.com/form-data"></div>

        <!-- Fields Editor -->
        <div class="card" style="margin:16px 0;padding:16px">
          <h2>表单字段</h2>
          <p class="text-sm text-muted mb-4">添加、编辑或删除表单字段</p>
          <div id="fieldsList">
            <?php foreach (($editForm['fields'] ?? []) as $i => $fld): ?>
            <div class="field-row" style="align-items:end;padding:8px 0;border-bottom:1px solid var(--border)">
              <div class="field" style="margin-bottom:0;flex:1"><input type="text" name="field_key[]" value="<?=htmlspecialchars($fld['key'])?>" placeholder="字段名" style="font-family:var(--mono)"></div>
              <div class="field" style="margin-bottom:0;flex:1.5"><input type="text" name="field_label[]" value="<?=htmlspecialchars($fld['label'])?>" placeholder="显示名称"></div>
              <div class="field" style="margin-bottom:0;width:100px"><select name="field_type[]"><option value="text" <?=$fld['type']==='text'?'selected':''?>>文本</option><option value="email" <?=$fld['type']==='email'?'selected':''?>>邮箱</option><option value="tel" <?=$fld['type']==='tel'?'selected':''?>>电话</option><option value="select" <?=$fld['type']==='select'?'selected':''?>>下拉</option><option value="textarea" <?=$fld['type']==='textarea'?'selected':''?>>多行</option></select></div>
              <div class="field" style="margin-bottom:0;width:120px"><input type="text" name="field_placeholder[]" value="<?=htmlspecialchars($fld['placeholder']??'')?>" placeholder="占位文字"></div>
              <div class="field" style="margin-bottom:0;width:80px"><label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" name="field_required[<?=$i?>]" value="1" <?=($fld['required']??false)?'checked':''?>>必填</label></div>
              <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addField()">+ 添加字段</button>
          <div class="flex gap-2 mt-4" style="flex-wrap:wrap">
            <?php $presets = ['name'=>'姓名','email'=>'邮箱','phone'=>'电话','company'=>'企业名称','title'=>'职位','size'=>'企业规模']; ?>
            <?php foreach ($presets as $pk => $pl): ?>
            <span class="tag-item" style="cursor:pointer" onclick="addPresetField('<?=$pk?>','<?=htmlspecialchars($pl)?>')">+ <?=htmlspecialchars($pl)?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">保存表单</button>
        <a href="forms.php" class="btn btn-ghost">取消</a>
      </form>
    </div>
  </div>
</div>

<script>
function toggleType() {
  var t = document.getElementById('formType').value;
  document.getElementById('newsletterConfig').style.display = t === 'newsletter' ? 'block' : 'none';
  document.getElementById('downloadConfig').style.display = t === 'download' ? 'block' : 'none';
}
function addField() {
  var div = document.createElement('div');
  div.className = 'field-row';
  div.style.cssText = 'align-items:end;padding:8px 0;border-bottom:1px solid var(--border)';
  div.innerHTML =
    '<div class="field" style="margin-bottom:0;flex:1"><input type="text" name="field_key[]" placeholder="字段名" style="font-family:var(--mono)"></div>' +
    '<div class="field" style="margin-bottom:0;flex:1.5"><input type="text" name="field_label[]" placeholder="显示名称"></div>' +
    '<div class="field" style="margin-bottom:0;width:100px"><select name="field_type[]"><option value="text">文本</option><option value="email">邮箱</option><option value="tel">电话</option><option value="select">下拉</option><option value="textarea">多行</option></select></div>' +
    '<div class="field" style="margin-bottom:0;width:120px"><input type="text" name="field_placeholder[]" placeholder="占位文字"></div>' +
    '<div class="field" style="margin-bottom:0;width:80px"><label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" name="field_required[]" value="1">必填</label></div>' +
    '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('fieldsList').appendChild(div);
}
function addPresetField(key, label) {
  var div = document.createElement('div');
  div.className = 'field-row';
  div.style.cssText = 'align-items:end;padding:8px 0;border-bottom:1px solid var(--border)';
  var type = key === 'email' ? 'email' : key === 'phone' ? 'tel' : 'text';
  div.innerHTML =
    '<div class="field" style="margin-bottom:0;flex:1"><input type="text" name="field_key[]" value="' + key + '" style="font-family:var(--mono)"></div>' +
    '<div class="field" style="margin-bottom:0;flex:1.5"><input type="text" name="field_label[]" value="' + label + '"></div>' +
    '<div class="field" style="margin-bottom:0;width:100px"><select name="field_type[]"><option value="' + type + '" selected>' + (type === 'email' ? '邮箱' : type === 'tel' ? '电话' : '文本') + '</option></select></div>' +
    '<div class="field" style="margin-bottom:0;width:120px"><input type="text" name="field_placeholder[]" placeholder="占位文字"></div>' +
    '<div class="field" style="margin-bottom:0;width:80px"><label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer"><input type="checkbox" name="field_required[]" value="1" checked>必填</label></div>' +
    '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('fieldsList').appendChild(div);
}
function copy(el) {
  navigator.clipboard.writeText(el.textContent).then(function() { ofAlert('已复制嵌入代码'); });
}
</script>
<?php admin_footer(); ?>
