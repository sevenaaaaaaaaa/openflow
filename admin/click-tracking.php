<?php
/**
 * 圈选埋点 —— 点一下页面元素就完成埋点（BACKLOG T1-4）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ClickTracker.php';
require_login();
require_perm('tracking');

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        $r = clicktrack_save([
            'id' => $_POST['id'] ?? '', 'name' => $_POST['name'] ?? '', 'selector' => $_POST['selector'] ?? '',
            'event' => $_POST['event'] ?? '', 'page' => $_POST['page'] ?? '', 'enabled' => isset($_POST['enabled']),
        ]);
        if ($r['ok']) { audit('保存圈选埋点 ' . $r['track']['event'], 'tracking'); header('Location: /xmp/click-tracking?ok=1'); exit; }
        $err = $r['error'];
    } elseif ($act === 'delete') { clicktrack_delete($_POST['id'] ?? ''); header('Location: /xmp/click-tracking'); exit; }
    elseif ($act === 'toggle')   { clicktrack_toggle($_POST['id'] ?? ''); header('Location: /xmp/click-tracking'); exit; }
}

$tracks = clicktrack_all();
$edit = !empty($_GET['edit']) ? clicktrack_get($_GET['edit']) : null;
admin_header('圈选埋点');
?>
<div style="max-width:1000px">
  <h1 style="margin:0 0 4px">🎯 圈选埋点</h1>
  <p class="v-sub" style="margin:0 0 16px">想统计某个按钮被点了多少次？不用写代码——用下面的圈选器在页面上点一下，或直接填 CSS 选择器，起个事件名就完成埋点。数据进 CDP 事件流。</p>
  <?php if ($err): ?><div class="card" style="padding:10px 14px;margin-bottom:12px;color:#dc2626;border-left:3px solid #dc2626"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <div style="flex:1;min-width:300px">
      <div style="font-weight:700;margin-bottom:8px">已定义（<?=count($tracks)?>）</div>
      <?php if (!$tracks): ?><div class="v-sub" style="font-size:13px">还没有。右侧新建，或用圈选器点选。</div><?php endif; ?>
      <?php foreach ($tracks as $t): ?>
      <div class="card" style="padding:12px 14px;margin-bottom:8px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
          <div>
            <strong><?=htmlspecialchars($t['name'])?></strong>
            <span style="font-size:11px;padding:1px 6px;border-radius:999px;background:<?=!empty($t['enabled'])?'#dcfce7':'#f1f5f9'?>;color:<?=!empty($t['enabled'])?'#166534':'#64748b'?>"><?=!empty($t['enabled'])?'采集中':'停用'?></span>
            <span style="font-size:12px;color:var(--faint)">· <?=(int)($t['hits'] ?? 0)?> 次</span>
          </div>
          <div style="display:flex;gap:6px">
            <a href="/xmp/click-tracking?edit=<?=urlencode($t['id'])?>" class="btn btn-ghost btn-sm">编辑</a>
            <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=htmlspecialchars($t['id'])?>"><button class="btn btn-ghost btn-sm"><?=!empty($t['enabled'])?'停用':'启用'?></button></form>
            <form method="post" onsubmit="return confirm('删除?')" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=htmlspecialchars($t['id'])?>"><button class="btn btn-ghost btn-sm" style="color:#dc2626">×</button></form>
          </div>
        </div>
        <div style="font-size:12px;color:var(--faint);margin-top:4px;font-family:monospace"><?=htmlspecialchars($t['selector'])?> → <?=htmlspecialchars($t['event'])?><?=!empty($t['page'])?' @'.htmlspecialchars($t['page']):' @全站'?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="flex:1;min-width:320px">
      <div class="card" style="padding:16px">
        <div style="font-weight:700;margin-bottom:10px"><?=$edit?'编辑埋点':'新建埋点'?></div>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?=htmlspecialchars($edit['id'])?>"><?php endif; ?>
          <input name="name" placeholder="名称，如 首页报名按钮" value="<?=htmlspecialchars($edit['name'] ?? '')?>" required style="width:100%;margin-bottom:8px">
          <div style="display:flex;gap:6px;margin-bottom:8px">
            <input id="selInput" name="selector" placeholder="CSS 选择器，如 .btn-signup" value="<?=htmlspecialchars($edit['selector'] ?? '')?>" required style="flex:1;font-family:monospace">
            <button type="button" class="btn btn-ghost btn-sm" onclick="openPicker()">🎯 圈选</button>
          </div>
          <div style="display:flex;gap:8px;margin-bottom:8px">
            <input name="event" placeholder="事件名(留空自动生成)" value="<?=htmlspecialchars($edit['event'] ?? '')?>" style="flex:1;font-family:monospace">
            <input name="page" placeholder="限定路径前缀(选填)" value="<?=htmlspecialchars($edit['page'] ?? '')?>" style="flex:1">
          </div>
          <label style="font-size:13px;display:block;margin-bottom:10px"><input type="checkbox" name="enabled" <?=(!empty($edit['enabled'])||!$edit)?'checked':''?>> 启用采集</label>
          <button class="btn btn-primary btn-sm"><?=$edit?'更新':'创建'?></button>
          <?php if ($edit): ?><a href="/xmp/click-tracking" class="btn btn-ghost btn-sm">取消</a><?php endif; ?>
        </form>
      </div>
      <div class="card" style="padding:14px 16px;margin-top:12px">
        <div style="font-weight:700;margin-bottom:6px;font-size:14px">圈选器怎么用</div>
        <div class="v-sub" style="font-size:13px">点「🎯 圈选」打开本站首页，把鼠标移到想统计的元素上（会高亮），点一下就把它的选择器填回左边。</div>
      </div>
    </div>
  </div>

  <!-- 圈选器：同源 iframe + 注入选择逻辑 -->
  <div id="pickerOv" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;padding:24px">
    <div style="background:#fff;border-radius:12px;height:100%;display:flex;flex-direction:column;overflow:hidden">
      <div style="padding:10px 14px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:10px">
        <strong style="font-size:14px">🎯 圈选模式</strong>
        <span style="font-size:12px;color:#6b7280">移到元素上高亮，点击即选中</span>
        <input id="pickerUrl" value="/" style="margin-left:auto;width:200px;font-size:12px;padding:4px 8px;border:1px solid #e5e7eb;border-radius:6px">
        <button type="button" class="btn btn-ghost btn-sm" onclick="reloadPicker()">跳转</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('pickerOv').style.display='none'">关闭</button>
      </div>
      <iframe id="pickerFrame" src="" style="flex:1;border:0;width:100%"></iframe>
    </div>
  </div>
</div>
<script>
function openPicker() {
  document.getElementById('pickerOv').style.display = 'block';
  reloadPicker();
}
function reloadPicker() {
  var f = document.getElementById('pickerFrame');
  f.src = document.getElementById('pickerUrl').value || '/';
  f.onload = function () { injectPicker(f); };
}
// 生成尽量稳的选择器：优先 id，其次 class 组合，最后标签+nth-of-type
function buildSelector(el) {
  if (el.id) return '#' + el.id;
  var parts = [];
  var cur = el, depth = 0;
  while (cur && cur.nodeType === 1 && depth < 4) {
    var seg = cur.tagName.toLowerCase();
    var cls = (cur.className && typeof cur.className === 'string')
      ? cur.className.trim().split(/\s+/).filter(function (c) { return c && !/^(active|open|show|hover)$/.test(c); }).slice(0, 2)
      : [];
    if (cls.length) { seg += '.' + cls.join('.'); parts.unshift(seg); break; }
    var p = cur.parentElement;
    if (p) {
      var same = Array.prototype.filter.call(p.children, function (c) { return c.tagName === cur.tagName; });
      if (same.length > 1) seg += ':nth-of-type(' + (same.indexOf(cur) + 1) + ')';
    }
    parts.unshift(seg);
    cur = cur.parentElement; depth++;
  }
  return parts.join(' > ');
}
function injectPicker(f) {
  try {
    var doc = f.contentDocument;
    if (!doc) return;
    var hl = doc.createElement('div');
    hl.style.cssText = 'position:absolute;pointer-events:none;border:2px solid #4f46e5;background:rgba(79,70,229,.12);z-index:999999;border-radius:4px;transition:all .05s';
    doc.body.appendChild(hl);
    doc.addEventListener('mouseover', function (e) {
      var r = e.target.getBoundingClientRect();
      hl.style.top = (r.top + doc.documentElement.scrollTop) + 'px';
      hl.style.left = (r.left + doc.documentElement.scrollLeft) + 'px';
      hl.style.width = r.width + 'px'; hl.style.height = r.height + 'px';
    }, true);
    doc.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      document.getElementById('selInput').value = buildSelector(e.target);
      document.getElementById('pickerOv').style.display = 'none';
    }, true);
  } catch (err) { alert('圈选器需要同源页面，请确认地址是本站路径'); }
}
</script>
<?php admin_footer(); ?>
