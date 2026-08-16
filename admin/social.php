<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$socialFile = DATA_DIR . '/social.json';
$social = json_read($socialFile);
if (empty($social['accounts']) || !is_array($social['accounts'])) {
    $social['accounts'] = [];
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $accounts = [];
    foreach (($_POST['platform'] ?? []) as $i => $p) {
        if (empty(trim($p))) continue;
        $accounts[] = [
            'platform' => $p,
            'name' => $_POST['display_name'][$i] ?? '',
            'icon' => $_POST['icon'][$i] ?? $p,
            'url' => $_POST['url'][$i] ?? '',
            'show_in_footer' => isset($_POST['footer'][$i]),
            'show_in_share' => isset($_POST['share'][$i]),
        ];
    }
    $social['accounts'] = $accounts;
    $social['share_default_image'] = $_POST['share_default_image'] ?? '';
    $social['share_default_text'] = $_POST['share_default_text'] ?? '';
    json_write($socialFile, $social);
    $message = '社交媒体配置已保存';
}

$platforms = ['wechat'=>'微信','linkedin'=>'LinkedIn','twitter'=>'X (Twitter)','facebook'=>'Facebook','xiaohongshu'=>'小红书','zhihu'=>'知乎','bilibili'=>'B站','douyin'=>'抖音','youtube'=>'YouTube','github'=>'GitHub','email'=>'邮箱','website'=>'官网'];

admin_header('社交媒体');
?>
<div class="admin-layout">
  <?php admin_sidebar('social'); ?>
  <div class="main">
    <h1>社交媒体</h1>
    <p class="sub">管理社交媒体账号 · 配置 Footer 显示 · 一键分享设置</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>📱 社交媒体账号</h2>
        <p class="text-sm text-muted mb-4">添加或删除社交媒体账号，设置是否在 Footer 显示 / 分享按钮中显示</p>
        <div id="socialList">
          <?php foreach ($social['accounts'] as $i => $acct): ?>
          <div class="social-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;padding:8px 0;border-bottom:1px solid var(--border)">
            <select name="platform[]" style="width:130px;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">
              <?php foreach ($platforms as $pk => $pv): ?>
              <option value="<?=$pk?>" <?=$acct['platform']===$pk?'selected':''?>><?=htmlspecialchars($pv)?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="display_name[]" value="<?=htmlspecialchars($acct['name'])?>" placeholder="名称" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">
            <input type="hidden" name="icon[]" value="<?=htmlspecialchars($acct['icon'])?>">
            <input type="text" name="url[]" value="<?=htmlspecialchars($acct['url'])?>" placeholder="链接 URL" style="flex:1.5;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="footer[<?=$i?>]" value="1" <?=($acct['show_in_footer']??false)?'checked':''?>>Footer</label>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="share[<?=$i?>]" value="1" <?=($acct['show_in_share']??false)?'checked':''?>>分享</label>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addSocialRow()">+ 添加账号</button>
      </div>

      <div class="card">
        <h2>🔗 一键分享设置</h2>
        <div class="field"><label>默认分享图片</label><input type="text" name="share_default_image" value="<?=htmlspecialchars($social['share_default_image'] ?? '')?>" placeholder="https://.../share-image.jpg"></div>
        <div class="field"><label>默认分享文案</label><textarea name="share_default_text" rows="2"><?=htmlspecialchars($social['share_default_text'] ?? '')?></textarea></div>
      </div>

      <button type="submit" name="save" class="btn btn-primary">保存配置</button>
    </form>
  </div>
</div>

<script>
function addSocialRow() {
  var div = document.createElement('div');
  div.className = 'social-row';
  div.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;padding:8px 0;border-bottom:1px solid var(--border)';
  var opts = '<?php foreach ($platforms as $pk => $pv): ?><option value="<?=$pk?>"><?=htmlspecialchars($pv)?></option><?php endforeach; ?>';
  var idx = document.querySelectorAll('.social-row').length;
  div.innerHTML =
    '<select name="platform[]" style="width:130px;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px">' + opts + '</select>' +
    '<input type="text" name="display_name[]" placeholder="名称" style="flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">' +
    '<input type="hidden" name="icon[]" value="">' +
    '<input type="text" name="url[]" placeholder="链接 URL" style="flex:1.5;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px">' +
    '<label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="footer[' + idx + ']" value="1" checked>Footer</label>' +
    '<label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap"><input type="checkbox" name="share[' + idx + ']" value="1" checked>分享</label>' +
    '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('socialList').appendChild(div);
}
</script>
<?php admin_footer(); ?>
