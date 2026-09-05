<?php
/**
 * 模块工厂 —— 可视化定义新模块（模块化页面生态的核心）
 *
 * 像 Elementor/Gutenberg 那样：后台定义一个模块，声明它的字段（schema）与样式，
 * 就能像内置块一样拖进落地页、到处复用，无需程序员写 PHP。
 *
 * - 字段类型：文本/富文本/图片/链接/颜色/数字/下拉/开关/重复列表/表单嵌入/引用模块
 * - 样式：背景/圆角/对齐/内边距（走 CSS 变量）
 * - 代码模式：高级开发者直接写 custom_html，用 {{字段}} 占位符映射
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/BlockSchema.php';
require_login();
require_perm('pages');

$msg = ''; $err = '';
$types = blockschema_field_types();
$mods  = blockschema_all();
$curKey = preg_replace('/[^a-z0-9_-]/', '', (string)($_GET['key'] ?? ''));
$edit = $curKey ? blockschema_get($curKey) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        $fields = [];
        foreach ((array)($_POST['f_key'] ?? []) as $i => $k) {
            if (trim((string)$k) === '') continue;
            $type = (string)($_POST['f_type'][$i] ?? 'text');
            $f = ['key' => trim((string)$k), 'label' => $_POST['f_label'][$i] ?? $k, 'type' => $type,
                  'required' => !empty($_POST['f_req'][$i]), 'placeholder' => $_POST['f_ph'][$i] ?? ''];
            if ($type === 'select') {
                $opts = trim((string)($_POST['f_opts'][$i] ?? ''));
                $f['options'] = $opts === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $opts))));
            }
            if ($type === 'repeat') {
                $sub = [];
                foreach ((array)($_POST['f_sub_key'][$i] ?? []) as $j => $sk) {
                    if (trim((string)$sk) === '') continue;
                    $sub[] = ['key' => trim((string)$sk), 'label' => $_POST['f_sub_label'][$i][$j] ?? $sk,
                              'type' => $_POST['f_sub_type'][$i][$j] ?? 'text'];
                }
                $f['children'] = $sub;
            }
            $fields[] = $f;
        }
        $style = [
            'bg'      => trim((string)($_POST['st_bg'] ?? '')),
            'radius'  => trim((string)($_POST['st_radius'] ?? '')),
            'align'   => in_array($_POST['st_align'] ?? '', ['left','center','right'], true) ? $_POST['st_align'] : '',
            'padding' => trim((string)($_POST['st_padding'] ?? '')),
        ];
        $mod = ['key' => trim((string)($_POST['key'] ?? '')), 'name' => trim((string)($_POST['name'] ?? '')),
                'status' => ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'draft',
                'fields' => $fields, 'style' => $style, 'custom_html' => (string)($_POST['custom_html'] ?? ''),
                'updated_at' => date('c')];
        $r = blockschema_save($mod);
        if ($r['ok']) {
            audit('保存模块 ' . $mod['key'], 'module');
            header('Location: /xmp/modules?key=' . urlencode($r['schema']['key']) . '&ok=1');
            exit;
        }
        $err = implode('；', $r['errors']);
    } elseif ($act === 'delete') {
        blockschema_delete((string)($_POST['key'] ?? ''));
        audit('删除模块 ' . ($_POST['key'] ?? ''), 'module');
        header('Location: /xmp/modules?deleted=1');
        exit;
    }
}

$mods = blockschema_all();
$fieldTypeLabels = array_map(fn($x) => $x['label'], $types);
admin_header('模块工厂');
?>
<div style="max-width:1080px">
  <h1 style="margin:0 0 4px">🧩 模块工厂</h1>
  <p class="v-sub" style="margin:0 0 16px">定义自己的页面模块（像内置块一样用）。写字段、定样式，无需写代码。</p>
  <?php if ($err): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#dc2626;border-left:3px solid #dc2626"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <?php if (isset($_GET['ok'])): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#16a34a;border-left:3px solid #16a34a">模块已保存。</div><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#64748b;border-left:3px solid #64748b">模块已删除。</div><?php endif; ?>

  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <!-- 左：模块列表 -->
    <div style="flex:1;min-width:220px">
      <div style="font-weight:700;margin-bottom:8px">模块（<?=count($mods)?>）</div>
      <?php if (!$mods): ?><div class="v-sub" style="font-size:13px;margin-bottom:8px">还没有。右侧新建一个。</div><?php endif; ?>
      <?php foreach ($mods as $m): $active = ($m['key'] === $curKey); ?>
      <a href="/xmp/modules?key=<?=urlencode($m['key'])?>" class="card" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;margin-bottom:6px;<?=$active?'border-left:3px solid var(--accent,#4f46e5)':''?>">
        <span><b><?=htmlspecialchars($m['name'])?></b><span class="text-sm text-muted" style="margin-left:8px"><?=htmlspecialchars($m['key'])?> · <?=count($m['fields'])?> 字段</span></span>
        <?php if ($m['custom_html']): ?><span class="st st-faint" title="代码模式">code</span><?php endif; ?>
      </a>
      <?php endforeach; ?>
      <a href="/xmp/modules?key=new" class="btn btn-primary btn-sm" style="margin-top:8px">+ 新建模块</a>
      <div class="text-xs text-muted" style="margin-top:10px;line-height:1.7">建好的模块会出现在「落地页构建器」的区块下拉里，和内置块一样可拖进页面、可复用。</div>
    </div>

    <!-- 右：编辑当前模块 -->
    <?php if ($curKey === 'new' || $edit): $m = $edit ?: ['key'=>'','name'=>'','status'=>'active','fields'=>[],'style'=>[],'custom_html'=>'']; ?>
    <div style="flex:2;min-width:420px">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="card" style="padding:16px;margin-bottom:12px">
          <h2 style="margin:0 0 12px;font-size:15px"><?=$edit?'编辑模块':'新建模块'?></h2>
          <div class="field-row">
            <div class="field"><label>模块名</label><input class="inp" type="text" name="name" value="<?=htmlspecialchars($m['name'])?>" required placeholder="如：学员评价"></div>
            <div class="field"><label>模块 key（唯一标识，英文）</label><input class="inp" type="text" name="key" value="<?=htmlspecialchars($m['key'])?>" placeholder="testimonial" <?=$edit?'disabled':''?>></div>
            <div class="field"><label>状态</label>
              <select class="inp" name="status"><option value="active" <?=($m['status']??'active')==='active'?'selected':''?>>启用</option><option value="draft" <?=($m['status']??'')==='draft'?'selected':''?>>停用</option></select>
            </div>
          </div>
          <?php if (!empty($_GET['ok'])): ?><div class="text-sm text-muted" style="margin-top:8px">修改 key 请删掉重建（已建模块被页面引用，改 key 会失联）。</div><?php endif; ?>

          <h3 style="font-size:13px;margin:18px 0 8px">字段定义</h3>
          <div id="fieldsList">
            <?php foreach (($m['fields'] ?? []) as $i => $f): ?>
            <div class="fld-row" style="border:1px solid var(--border);border-radius:8px;padding:10px;margin-bottom:8px">
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px;margin-bottom:6px">
                <input type="text" name="f_key[]" value="<?=htmlspecialchars($f['key'])?>" placeholder="字段 key" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">
                <input type="text" name="f_label[]" value="<?=htmlspecialchars($f['label']??'')?>" placeholder="字段标签" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">
                <select name="f_type[]" class="f-type" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">
                  <?php foreach ($fieldTypeLabels as $tk=>$tl): ?><option value="<?=$tk?>" <?=($f['type']??'text')===$tk?'selected':''?>><?=htmlspecialchars($tl)?></option><?php endforeach; ?>
                </select>
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--muted)"><input type="checkbox" name="f_req[]" value="1" <?=!empty($f['required'])?'checked':''?> style="width:auto">必填</label>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
                <input type="text" name="f_ph[]" value="<?=htmlspecialchars($f['placeholder']??'')?>" placeholder="占位提示" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">
                <input type="text" name="f_opts[]" value="<?=htmlspecialchars(implode(',', $f['options']??[]))?>" placeholder="下拉选项(逗号分隔)" class="f-opts" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">
              </div>
              <div class="f-repeat" style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--border)">
                <div class="text-xs text-muted" style="margin-bottom:4px">列表子字段（每条含这些）：</div>
                <?php foreach (($f['children'] ?? []) as $j => $sub): ?>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px;margin-bottom:4px">
                  <input type="text" name="f_sub_key[<?=$i?>][]" value="<?=htmlspecialchars($sub['key']??'')?>" placeholder="子字段 key" style="padding:5px 7px;border:1px solid var(--border);border-radius:6px;font-size:12px">
                  <input type="text" name="f_sub_label[<?=$i?>][]" value="<?=htmlspecialchars($sub['label']??'')?>" placeholder="子字段名" style="padding:5px 7px;border:1px solid var(--border);border-radius:6px;font-size:12px">
                  <select name="f_sub_type[<?=$i?>][]" style="padding:5px 7px;border:1px solid var(--border);border-radius:6px;font-size:12px">
                    <?php foreach ($fieldTypeLabels as $tk=>$tl): ?><option value="<?=$tk?>" <?=($sub['type']??'text')===$tk?'selected':''?>><?=htmlspecialchars($tl)?></option><?php endforeach; ?>
                  </select>
                  <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('.srow').remove()">✕</button>
                </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('.fld-row').remove()">移除字段</button>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-s btn-sm" onclick="addField()">+ 添加字段</button>

          <h3 style="font-size:13px;margin:18px 0 8px">样式定义</h3>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px">
            <div><label class="text-xs text-muted">背景色</label><input class="inp" type="text" name="st_bg" value="<?=htmlspecialchars($m['style']['bg']??'')?>" placeholder="#f4f3e9"></div>
            <div><label class="text-xs text-muted">圆角(px)</label><input class="inp" type="text" name="st_radius" value="<?=htmlspecialchars($m['style']['radius']??'')?>" placeholder="16"></div>
            <div><label class="text-xs text-muted">对齐</label><select class="inp" name="st_align"><option value="">默认</option><option value="left" <?=($m['style']['align']??'')==='left'?'selected':''?>>左</option><option value="center" <?=($m['style']['align']??'')==='center'?'selected':''?>>中</option><option value="right" <?=($m['style']['align']??'')==='right'?'selected':''?>>右</option></select></div>
            <div><label class="text-xs text-muted">内边距(px)</label><input class="inp" type="text" name="st_padding" value="<?=htmlspecialchars($m['style']['padding']??'')?>" placeholder="24"></div>
          </div>

          <h3 style="font-size:13px;margin:18px 0 8px">代码模式（可选，高级）</h3>
          <label class="text-xs text-muted" style="display:block;margin-bottom:4px">自定义 HTML 模板，用 <code>{{字段key}}</code> 映射值；<code>{{#items}}…{{/items}}</code> 循环列表。留空 = 自动渲染。</label>
          <textarea class="inp" name="custom_html" rows="3" placeholder="如：<div class=&quot;hd&quot;>{{title}}</div><div class=&quot;bd&quot;>{{body}}</div>" style="font-family:var(--mono);font-size:12.5px"><?=htmlspecialchars($m['custom_html']??'')?></textarea>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary">保存模块</button>
          <?php if ($edit): ?>
          <button type="submit" class="btn btn-ghost" formaction="?key=<?=urlencode($m['key'])?>" onclick="return false"></button>
          <?php endif; ?>
        </div>
      </form>
      <?php if ($edit): ?>
      <form method="post" style="margin-top:10px" data-confirm="删除模块「<?=htmlspecialchars($m['name']??'',ENT_QUOTES)?>」？">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="key" value="<?=htmlspecialchars($m['key'])?>">
        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">删除此模块</button>
      </form>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div style="flex:2;min-width:420px">
      <div class="card" style="padding:40px;text-align:center;color:var(--muted)">← 从左侧选一个模块，或「+ 新建模块」。</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
var fieldTypes = <?=json_encode($fieldTypeLabels, JSON_UNESCAPED_UNICODE)?>;
function addField(f) {
  f = f || {};
  var div = document.createElement('div');
  div.className = 'fld-row';
  div.style.cssText = 'border:1px solid var(--border);border-radius:8px;padding:10px;margin-bottom:8px';
  var optsHtml = Object.keys(fieldTypes).map(function(k){ return '<option value="'+k+'"'+(f.type===k?' selected':'')+'>'+fieldTypes[k]+'</option>'; }).join('');
  div.innerHTML =
    '<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px;margin-bottom:6px">' +
      '<input type="text" name="f_key[]" value="'+(f.key||'')+'" placeholder="字段 key" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">' +
      '<input type="text" name="f_label[]" value="'+(f.label||'')+'" placeholder="字段标签" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">' +
      '<select name="f_type[]" class="f-type" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">'+optsHtml+'</select>' +
      '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--muted)"><input type="checkbox" name="f_req[]" value="1" style="width:auto">必填</label>' +
    '</div>' +
    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">' +
      '<input type="text" name="f_ph[]" placeholder="占位提示" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">' +
      '<input type="text" name="f_opts[]" placeholder="下拉选项(逗号分隔)" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px">' +
    '</div>' +
    '<div class="f-repeat" style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--border)">' +
      '<div class="text-xs text-muted" style="margin-bottom:4px">列表子字段：</div><div class="sub-list"></div>' +
      '<button type="button" class="btn btn-ghost btn-sm" onclick="addSubField(this)">+ 子字段</button>' +
    '</div>' +
    '<button type="button" class="btn btn-ghost btn-sm" style="margin-top:6px" onclick="this.closest(\'.fld-row\').remove()">移除字段</button>';
  document.getElementById('fieldsList').appendChild(div);
  toggleRepeat(div);
  div.querySelector('.f-type').addEventListener('change', function(){ toggleRepeat(this.closest('.fld-row')); });
}
function toggleRepeat(row) {
  var t = row.querySelector('.f-type').value;
  var rep = row.querySelector('.f-repeat');
  if (rep) rep.style.display = (t === 'repeat') ? 'block' : 'none';
  var opts = row.querySelector('.f-opts');
  if (opts) opts.style.display = (t === 'select') ? 'block' : ((t==='repeat'?0:!!opts) ? '' : (t==='select'?'block':'none'));
}
function addSubField(btn) {
  var list = btn.closest('.f-repeat').querySelector('.sub-list');
  var div = document.createElement('div');
  div.className = 'srow';
  div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px;margin-bottom:4px';
  var optsHtml = Object.keys(fieldTypes).map(function(k){ return '<option value="'+k+'"'+(k==='text'?' selected':'')+'>'+fieldTypes[k]+'</option>'; }).join('');
  div.innerHTML =
    '<input type="text" name="f_sub_key[][]" placeholder="子字段 key" style="padding:5px 7px;border:1px solid var(--border);border-radius:6px;font-size:12px">' +
    '<input type="text" name="f_sub_label[][]" placeholder="子字段名" style="padding:5px 7px;border:1px solid var(--border);border-radius:6px;font-size:12px">' +
    '<select name="f_sub_type[][]" style="padding:5px 7px;border:1px solid var(--border);border-radius:6px;font-size:12px">'+optsHtml+'</select>' +
    '<button type="button" class="btn btn-ghost btn-sm" onclick="this.closest(\'.srow\').remove()">✕</button>';
  list.appendChild(div);
}
// 已有行的 repeat 可见性
document.querySelectorAll('.fld-row').forEach(function(row){ toggleRepeat(row); if (row.querySelector('.f-type')) row.querySelector('.f-type').addEventListener('change', function(){ toggleRepeat(row); }); });
</script>
<?php admin_footer(); ?>
