<?php
/**
 * 自定义内容类型 —— 定义类型（字段集）+ 管理各类型条目（BACKLOG T0-2）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CptSystem.php';
require_login();
require_perm('cpt');

$msg = ''; $err = '';
$curType = preg_replace('/[^a-z0-9-]/', '', $_GET['type'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'save_type') {
        $fields = [];
        foreach ((array)($_POST['f_key'] ?? []) as $i => $k) {
            if (trim((string)$k) === '') continue;
            $fields[] = ['key' => $k, 'label' => $_POST['f_label'][$i] ?? $k, 'type' => $_POST['f_type'][$i] ?? 'text',
                         'required' => !empty($_POST['f_req'][$i]), 'options' => $_POST['f_opts'][$i] ?? ''];
        }
        $r = cpt_type_save(['name' => $_POST['name'] ?? '', 'name_plural' => $_POST['name_plural'] ?? '',
            'slug' => $_POST['slug'] ?? '', 'icon' => $_POST['icon'] ?? '📄',
            'public' => isset($_POST['public']), 'menu' => isset($_POST['menu']), 'fields' => $fields]);
        if ($r['ok']) { audit('保存内容类型 ' . $r['type']['slug'], 'cpt'); header('Location: /xmp/cpt?type=' . $r['type']['slug'] . '&ok=1'); exit; }
        $err = $r['error'] ?? '保存失败';
    } elseif ($act === 'delete_type') {
        cpt_type_delete($_POST['slug'] ?? ''); audit('删除内容类型 ' . ($_POST['slug'] ?? ''), 'cpt');
        header('Location: /xmp/cpt?deleted=1'); exit;
    } elseif ($act === 'save_entry') {
        $r = cpt_entry_save($curType, ['id' => $_POST['id'] ?? '', 'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '', 'status' => $_POST['status'] ?? 'draft',
            'author' => $_SESSION['admin_name'] ?? '', 'fields' => $_POST['fields'] ?? []]);
        if ($r['ok']) { audit('保存条目 ' . $r['entry']['id'] . ' @' . $curType, 'cpt'); header('Location: /xmp/cpt?type=' . $curType . '&ok=1'); exit; }
        $err = !empty($r['errors']) ? implode('；', $r['errors']) : ($r['error'] ?? '保存失败');
    } elseif ($act === 'delete_entry') {
        cpt_entry_delete($curType, $_POST['id'] ?? ''); audit('删除条目 ' . ($_POST['id'] ?? '') . ' @' . $curType, 'cpt');
        header('Location: /xmp/cpt?type=' . $curType); exit;
    }
}

$types = cpt_types();
$type  = $curType ? cpt_type($curType) : null;
$editEntry = ($type && !empty($_GET['edit'])) ? cpt_entry($curType, $_GET['edit']) : null;

admin_header('自定义内容类型');
?>
<div style="max-width:1080px">
  <h1 style="margin:0 0 4px">🧩 自定义内容类型</h1>
  <p class="v-sub" style="margin:0 0 16px">给站点加一种自己的内容（案例/产品/FAQ/职位…）：定义字段，按类型管理条目。</p>
  <?php if ($err): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#dc2626;border-left:3px solid #dc2626"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <!-- 左：类型列表 -->
    <div style="flex:1;min-width:220px">
      <div style="font-weight:700;margin-bottom:8px">内容类型（<?=count($types)?>）</div>
      <?php if (!$types): ?><div class="v-sub" style="font-size:13px;margin-bottom:8px">还没有。右侧新建一个。</div><?php endif; ?>
      <?php foreach ($types as $t): $active = ($t['slug'] === $curType); ?>
      <a href="/xmp/cpt?type=<?=urlencode($t['slug'])?>" class="card" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;margin-bottom:6px;<?=$active?'border-left:3px solid var(--accent,#4f46e5)':''?>">
        <span><?=htmlspecialchars($t['icon'] ?? '📄')?> <strong><?=htmlspecialchars($t['name'])?></strong> <span style="color:var(--faint);font-size:12px">/c/<?=htmlspecialchars($t['slug'])?></span></span>
        <span style="font-size:12px;color:var(--faint)"><?=count(cpt_entries($t['slug']))?> 条<?=!empty($t['public'])?' · 公开':''?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- 右：新建/编辑类型 -->
    <div style="flex:1;min-width:300px">
      <div class="card" style="padding:14px 16px">
        <div style="font-weight:700;margin-bottom:10px"><?=$type?'编辑类型：'.htmlspecialchars($type['name']):'新建内容类型'?></div>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save_type">
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
            <input name="name" placeholder="类型名，如 客户案例" value="<?=htmlspecialchars($type['name'] ?? '')?>" required style="flex:2;min-width:140px">
            <input name="slug" placeholder="slug(英数)" value="<?=htmlspecialchars($type['slug'] ?? '')?>" <?=$type?'readonly':''?> style="flex:1;min-width:100px">
            <input name="icon" placeholder="图标emoji" value="<?=htmlspecialchars($type['icon'] ?? '📄')?>" style="width:70px">
          </div>
          <label style="font-size:13px;margin-right:14px"><input type="checkbox" name="public" <?=!empty($type['public'])?'checked':''?>> 前台公开(/c/slug)</label>
          <label style="font-size:13px"><input type="checkbox" name="menu" <?=!empty($type['menu'])?'checked':''?>> 显示入口</label>

          <div style="font-weight:700;margin:12px 0 6px;font-size:13px">字段</div>
          <div id="fields">
            <?php $defs = $type['fields'] ?? [['key'=>'','label'=>'','type'=>'text']]; foreach ($defs as $f): ?>
            <div class="frow" style="display:flex;gap:6px;margin-bottom:6px;flex-wrap:wrap">
              <input name="f_key[]" placeholder="key(英数)" value="<?=htmlspecialchars($f['key'] ?? '')?>" style="width:110px">
              <input name="f_label[]" placeholder="显示名" value="<?=htmlspecialchars($f['label'] ?? '')?>" style="width:110px">
              <select name="f_type[]" style="width:110px"><?php foreach (cpt_field_types() as $k=>$lab): ?><option value="<?=$k?>" <?=($f['type']??'')===$k?'selected':''?>><?=$lab?></option><?php endforeach; ?></select>
              <input name="f_opts[]" placeholder="选项(逗号分隔)" value="<?=htmlspecialchars(is_array($f['options']??'')?implode(',',$f['options']):'')?>" style="width:130px">
              <label style="font-size:12px"><input type="checkbox" name="f_req[]" value="1" <?=!empty($f['required'])?'checked':''?>>必填</label>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addField()">+ 加字段</button>
          <div style="margin-top:12px"><button class="btn btn-primary btn-sm">保存类型</button></div>
        </form>
        <?php if ($type): ?>
        <form method="post" data-confirm="删除类型「<?=htmlspecialchars($type['name'])?>」？条目文件会保留作备份。" style="margin-top:8px">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete_type"><input type="hidden" name="slug" value="<?=htmlspecialchars($type['slug'])?>">
          <button class="btn btn-ghost btn-sm" style="color:#dc2626">删除此类型</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($type): ?>
  <!-- 条目管理 -->
  <hr style="margin:24px 0;border:none;border-top:1px solid var(--border)">
  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <div style="flex:1;min-width:280px">
      <div style="font-weight:700;margin-bottom:8px"><?=htmlspecialchars($type['name'])?> · 条目（<?=count(cpt_entries($type['slug']))?>）</div>
      <?php foreach (cpt_entries($type['slug']) as $e): ?>
      <div class="card" style="padding:10px 12px;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;gap:8px">
        <div><strong><?=htmlspecialchars($e['title'])?></strong> <span style="font-size:11px;padding:1px 6px;border-radius:999px;background:<?=($e['status']??'')==='published'?'#dcfce7':'#f1f5f9'?>;color:<?=($e['status']??'')==='published'?'#166534':'#64748b'?>"><?=($e['status']??'')==='published'?'已发布':'草稿'?></span></div>
        <div style="display:flex;gap:6px">
          <a href="/xmp/cpt?type=<?=urlencode($type['slug'])?>&edit=<?=urlencode($e['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
          <form method="post" data-confirm="删除?" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="delete_entry"><input type="hidden" name="id" value="<?=htmlspecialchars($e['id'])?>"><button class="btn btn-ghost btn-sm" style="color:#dc2626">×</button></form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="flex:1;min-width:320px">
      <div class="card" style="padding:14px 16px">
        <div style="font-weight:700;margin-bottom:10px"><?=$editEntry?'编辑条目':'新建条目'?></div>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save_entry">
          <?php if ($editEntry): ?><input type="hidden" name="id" value="<?=htmlspecialchars($editEntry['id'])?>"><?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
            <input name="title" placeholder="标题" value="<?=htmlspecialchars($editEntry['title'] ?? '')?>" required style="flex:2;min-width:160px">
            <input name="slug" placeholder="slug(选填)" value="<?=htmlspecialchars($editEntry['slug'] ?? '')?>" style="flex:1;min-width:100px">
            <select name="status" style="width:100px"><option value="draft" <?=($editEntry['status']??'')==='draft'?'selected':''?>>草稿</option><option value="published" <?=($editEntry['status']??'')==='published'?'selected':''?>>发布</option></select>
          </div>
          <?php foreach ($type['fields'] ?? [] as $f): $val = $editEntry['fields'][$f['key']] ?? ''; ?>
          <div style="margin-bottom:8px">
            <label style="display:block;font-size:12px;color:var(--faint);margin-bottom:2px"><?=htmlspecialchars($f['label'])?><?=!empty($f['required'])?' *':''?></label>
            <?php if ($f['type']==='textarea' || $f['type']==='richtext'): ?>
              <textarea name="fields[<?=$f['key']?>]" rows="4" style="width:100%"><?=htmlspecialchars((string)$val)?></textarea>
            <?php elseif ($f['type']==='bool'): ?>
              <label><input type="checkbox" name="fields[<?=$f['key']?>]" value="1" <?=$val?'checked':''?>> 是</label>
            <?php elseif ($f['type']==='select'): ?>
              <select name="fields[<?=$f['key']?>]" style="width:100%"><option value="">—</option><?php foreach ($f['options'] ?? [] as $o): ?><option value="<?=htmlspecialchars($o)?>" <?=$val===$o?'selected':''?>><?=htmlspecialchars($o)?></option><?php endforeach; ?></select>
            <?php else: ?>
              <input type="<?=$f['type']==='number'?'number':($f['type']==='date'?'date':($f['type']==='url'?'url':'text'))?>" name="fields[<?=$f['key']?>]" value="<?=htmlspecialchars((string)$val)?>" style="width:100%" <?=$f['type']==='number'?'step=any':''?>>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <button class="btn btn-primary btn-sm"><?=$editEntry?'更新条目':'新建条目'?></button>
          <?php if ($editEntry): ?><a href="/xmp/cpt?type=<?=urlencode($type['slug'])?>" class="btn btn-ghost btn-sm">取消</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
function addField(){
  var w=document.getElementById('fields'), r=w.firstElementChild.cloneNode(true);
  r.querySelectorAll('input').forEach(function(i){ if(i.type==='checkbox')i.checked=false; else i.value=''; });
  r.querySelector('select').selectedIndex=0; w.appendChild(r);
}
</script>
<?php admin_footer(); ?>
