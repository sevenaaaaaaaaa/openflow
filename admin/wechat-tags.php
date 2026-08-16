<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/WechatMp.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_login();
require_perm('wechat-mp');

$cfg = WechatMp::config();
$appid = $cfg['appid'] ?? '';
$message = '';
$error = '';

// 标签-微信openid 关联存储（本地缓存，用于 CDP 打通）
$linkFile = DATA_DIR . '/wechat-users.json';
$wxUsers = json_read($linkFile);

$action = $_POST['do'] ?? ($_GET['do'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // 创建标签
    if ($action === 'create_tag') {
        $name = trim($_POST['tag_name'] ?? '');
        if (!$name) $error = '请输入标签名';
        else {
            $r = WechatMp::createTag($name);
            if (($r['errcode'] ?? 1) === 0) $message = "标签「{$name}」已创建";
            else $error = '创建失败: ' . ($r['errmsg'] ?? '未知');
        }
    }

    // 删除标签
    if ($action === 'delete_tag') {
        $tagId = (int)($_POST['tag_id'] ?? 0);
        $r = WechatMp::deleteTag($tagId);
        if (($r['errcode'] ?? 1) === 0) $message = '标签已删除';
        else $error = '删除失败: ' . ($r['errmsg'] ?? '未知');
    }

    // 给用户打标签
    if ($action === 'tag_user') {
        $tagId = (int)($_POST['tag_id'] ?? 0);
        $openids = array_values(array_filter(array_map('trim', explode("\n", $_POST['openids'] ?? ''))));
        if (empty($openids)) $error = '请输入 openid';
        else {
            $r = WechatMp::tagUsers($tagId, $openids);
            if (($r['errcode'] ?? 1) === 0) $message = '已打标签 ' . count($openids) . ' 人';
            else $error = '打标失败: ' . ($r['errmsg'] ?? '未知');
        }
    }

    // 同步标签到 CDP（把微信用户与 CDP 画像打通）
    if ($action === 'sync_to_cdp') {
        $synced = 0;
        foreach ($wxUsers as $openid => $u) {
            $wxTags = WechatMp::userTags($openid);
            $tagIds = $wxTags['tagid_list'] ?? [];
            $tagNames = [];
            foreach ($tags as $t) if (in_array($t['id'], $tagIds)) $tagNames[] = $t['name'];
            // 用 openid 作为身份标识合并
            $canonical = IdentityResolver::merge('', '', $u['email'] ?? '', '', $openid);
            if ($canonical) {
                $profiles = CdpSystem::allProfiles();
                if (isset($profiles[$canonical])) {
                    foreach ($tagNames as $tn) {
                        if (!in_array('微信:'.$tn, $profiles[$canonical]['tags'], true)) $profiles[$canonical]['tags'][] = '微信:'.$tn;
                    }
                    if (!empty($u['nickname'])) $profiles[$canonical]['properties']['wx_nickname'] = $u['nickname'];
                    $profiles[$canonical]['properties']['wx_openid'] = $openid;
                    CdpSystem::saveProfiles($profiles);
                    $synced++;
                }
            }
        }
        $message = "已同步 {$synced} 个微信用户到 CDP";
    }
}

// 重新拉取标签
$tags = [];
if ($appid) {
    try { $r = WechatMp::listTags(); $tags = $r['tags'] ?? []; } catch (Exception $e) {}
}

admin_header('微信标签');
?>
<div class="admin-layout">
  <?php admin_sidebar('wechat-tags'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">🏷 微信标签管理</h1>
      <div class="flex gap-2 ml-auto">
        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="sync_to_cdp">
          <button type="submit" class="btn btn-ghost btn-sm">🔄 同步到 CDP</button>
        </form>
      </div>
    </div>
    <p class="sub">服务号用户标签 · 与 CDP 画像双向打通 · 标签用于定向群发</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>
    <?php if (!$appid): ?><?=msg('error', '请先在「公众号设置」配置 AppID/AppSecret')?><?php endif; ?>

    <!-- 创建标签 -->
    <div class="card" style="margin-bottom:24px">
      <h2>➕ 创建标签</h2>
      <form method="post" style="display:flex;gap:12px;align-items:end">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create_tag">
        <div class="field"><label>标签名</label><input type="text" name="tag_name" required placeholder="如：高意向客户"></div>
        <button type="submit" class="btn btn-primary">创建</button>
      </form>
    </div>

    <!-- 标签列表 -->
    <div class="card" style="margin-bottom:24px;padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2)"><h2 style="margin:0">📋 标签列表 (<?=count($tags)?>)</h2></div>
      <table>
        <thead><tr><th>标签 ID</th><th>名称</th><th>用户数</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($tags)): ?><tr><td colspan="4" class="empty">暂无标签（可能未配置公众号或未创建）</td></tr><?php endif; ?>
          <?php foreach ($tags as $t): ?>
          <tr>
            <td><code><?=$t['id']?></code></td>
            <td><strong><?=htmlspecialchars($t['name'])?></strong></td>
            <td><span class="badge badge-gray"><?=$t['count'] ?? 0?></span></td>
            <td>
              <form method="post" style="display:inline" onsubmit="return confirm('删除标签？')">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="delete_tag">
                <input type="hidden" name="tag_id" value="<?=$t['id']?>">
                <button class="btn btn-ghost btn-sm" style="color:#dc2626">删除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 打标签 -->
    <div class="card">
      <h2>🏷 给用户打标签</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="tag_user">
        <div class="field-row">
          <div class="field"><label>选择标签</label><select name="tag_id"><option value="0">— 选择 —</option><?php foreach ($tags as $t): ?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option><?php endforeach; ?></select></div>
          <div class="field"><label>用户 OpenID <span class="hint">· 每行一个</span></label><textarea name="openids" rows="4" placeholder="oxxxxxxxxxxxxxxxxxxx"></textarea></div>
        </div>
        <button type="submit" class="btn btn-primary">打标签</button>
      </form>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
