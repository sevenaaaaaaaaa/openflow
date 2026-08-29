<?php
/**
 * 底部外链管理
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$footerFile = DATA_DIR . '/footer-links.json';
$links = json_read($footerFile);

// 保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $links = [];
    foreach (($_POST['group_name'] ?? []) as $gi => $gn) {
        $gn = trim($gn);
        if (empty($gn)) continue;
        $items = [];
        foreach (($_POST['link_label'][$gi] ?? []) as $li => $ll) {
            $ll = trim($ll);
            if (empty($ll)) continue;
            $items[] = [
                'label' => $ll,
                'url' => trim($_POST['link_url'][$gi][$li] ?? ''),
                'target' => $_POST['link_target'][$gi][$li] ?? '_blank',
                'rel' => !empty($_POST['link_nofollow'][$gi][$li]) ? 'nofollow' : '',
            ];
        }
        $links[] = [
            'name' => $gn,
            'links' => $items,
        ];
    }
    json_write($footerFile, $links);
    flash('success', '底部外链已保存');
    header('Location: /xmp/footer-links');
    exit;
}

if (!defined('OF_EMBED')) admin_header('底部外链管理');
?>
<style>
.group-box{border:1px solid var(--border);border-radius:12px;margin-bottom:16px;overflow:hidden}
.group-header{display:flex;align-items:center;gap:8px;padding:12px 16px;background:var(--surface-2);border-bottom:1px solid var(--border)}
.group-header input{flex:1;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:14px;background:var(--surface)}
.link-row{display:flex;gap:8px;padding:8px 16px;align-items:center;border-bottom:1px solid var(--border)}
.link-row:last-child{border:none}
.link-row input[type=text]{flex:1;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px}
.link-row input[type=url]{width:240px;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px}
</style>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
<?php endif; ?>
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0"> 底部外链</h1>
      <span class="badge badge-gray"><?=count($links)?> 个分组</span>
    </div>
    <p class="sub">管理网站底部的外链，支持分组、nofollow、新窗口打开</p>

    <form method="post" id="linksForm">
      <?= csrf_field() ?>

      <div id="groupsContainer">
        <?php foreach ($links as $gi => $group): ?>
        <div class="group-box" data-index="<?=$gi?>">
          <div class="group-header">
            <span style="font-weight:600">📁</span>
            <input type="text" name="group_name[<?=$gi?>]" value="<?=htmlspecialchars($group['name'])?>" placeholder="分组名称">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.group-box').remove()">删除分组</button>
          </div>
          <?php foreach (($group['links'] ?? []) as $li => $link): ?>
          <div class="link-row">
            <input type="text" name="link_label[<?=$gi?>][]" value="<?=htmlspecialchars($link['label'])?>" placeholder="显示文字">
            <input type="url" name="link_url[<?=$gi?>][]" value="<?=htmlspecialchars($link['url'])?>" placeholder="https://...">
            <select name="link_target[<?=$gi?>][]">
              <option value="_blank" <?=($link['target'] ?? '_blank') === '_blank' ? 'selected' : ''?>>新窗口</option>
              <option value="_self" <?=($link['target'] ?? '') === '_self' ? 'selected' : ''?>>当前窗口</option>
            </select>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px">
              <input type="checkbox" name="link_nofollow[<?=$gi?>][]" value="1" <?=!empty($link['rel']) ? 'checked' : ''?>> nofollow
            </label>
            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="this.closest('.link-row').remove()">✕</button>
          </div>
          <?php endforeach; ?>
          <div style="padding:8px 16px">
            <button type="button" class="btn btn-ghost btn-sm" onclick="addLink(<?=$gi?>)">+ 添加链接</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:8px;margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="addGroup()">+ 添加分组</button>
        <div style="margin-left:auto">
          <button type="submit" class="btn btn-primary">💾 保存</button>
        </div>
      </div>
    </form>

    <!-- 预览 -->
    <div class="card" style="margin-top:20px">
      <h2>👁 底部预览</h2>
      <div style="background:#1e1e1e;color:#fff;padding:24px;border-radius:8px;margin-top:12px">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:20px">
          <?php foreach ($links as $group): ?>
          <div>
            <div style="font-weight:600;margin-bottom:8px;color:#fff"><?=htmlspecialchars($group['name'])?></div>
            <?php foreach (($group['links'] ?? []) as $link): ?>
            <div style="margin-bottom:4px">
              <a href="<?=htmlspecialchars($link['url'])?>" style="color:var(--faint);text-decoration:none;font-size:13px" target="<?=$link['target'] ?? '_blank'?>" rel="<?=htmlspecialchars($link['rel'] ?? '')?>"><?=htmlspecialchars($link['label'])?></a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
var groupIndex = <?=count($links)?>;
function addGroup() {
  var div = document.createElement('div');
  div.className = 'group-box';
  div.innerHTML =
    '<div class="group-header">' +
      '<span style="font-weight:600">📁</span>' +
      '<input type="text" name="group_name[' + groupIndex + ']" placeholder="分组名称">' +
      '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.group-box\').remove()">删除分组</button>' +
    '</div>' +
    '<div class="link-rows"></div>' +
    '<div style="padding:8px 16px">' +
      '<button type="button" class="btn btn-ghost btn-sm" onclick="addLink(' + groupIndex + ')">+ 添加链接</button>' +
    '</div>';
  document.getElementById('groupsContainer').appendChild(div);
  groupIndex++;
}
function addLink(gi) {
  var group = document.querySelector('.group-box[data-index="' + gi + '"]');
  if (!group) {
    // 新建的 group 没有 data-index，找最后一个
    var boxes = document.querySelectorAll('.group-box');
    group = boxes[boxes.length - 1];
    gi = groupIndex - 1;
  }
  var row = document.createElement('div');
  row.className = 'link-row';
  row.innerHTML =
    '<input type="text" name="link_label[' + gi + '][]" placeholder="显示文字">' +
    '<input type="url" name="link_url[' + gi + '][]" placeholder="https://...">' +
    '<select name="link_target[' + gi + '][]"><option value="_blank">新窗口</option><option value="_self">当前窗口</option></select>' +
    '<label style="font-size:12px;display:flex;align-items:center;gap:4px"><input type="checkbox" name="link_nofollow[' + gi + '][]" value="1"> nofollow</label>' +
    '<button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="this.closest(\'.link-row\').remove()">✕</button>';
  var container = group.querySelector('.link-rows') || group;
  container.appendChild(row);
}
</script>
<?php if (!defined('OF_EMBED')) admin_footer(); ?>
