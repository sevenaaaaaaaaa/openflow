<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AdSystem.php';

require_login();
if (!has_perm('settings')) { http_response_code(403); exit('无权限'); }

$ads = ads_get();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $newAds = [];
    $slotKeys = ['article_top', 'article_bottom', 'community_banner', 'feed_1', 'feed_2'];
    foreach ($slotKeys as $k) {
        $newAds[$k] = [
            'enabled' => isset($_POST['ads'][$k]['enabled']),
            'html'    => trim($_POST['ads'][$k]['html'] ?? ''),
            'image'   => trim($_POST['ads'][$k]['image'] ?? ''),
            'link'    => trim($_POST['ads'][$k]['link'] ?? ''),
            'title'   => trim($_POST['ads'][$k]['title'] ?? ''),
        ];
    }
    if (ads_save($newAds)) {
        $message = '广告位配置已保存';
        $ads = $newAds;
    } else {
        $message = '保存失败，请检查 data/ 目录权限';
    }
}

admin_header('广告位管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
<h1>广告位管理</h1>
<p class="sub">管理文章顶部/底部、社区 Banner、瀑布流信息流广告位。优先使用 HTML 代码，其次使用图片+链接。</p>

<?php if ($message): ?><div class="msg msg-success"><?=htmlspecialchars($message)?></div><?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($ads as $slot => $ad): ?>
  <div class="card">
    <h2 style="display:flex;align-items:center;gap:10px">
      <input type="checkbox" name="ads[<?=$slot?>][enabled]" value="1" <?=!empty($ad['enabled'])?'checked':''?> style="width:18px;height:18px">
      <?=htmlspecialchars($ad['title'] ?? $slot)?>
    </h2>
    <div class="fld"><label>广告标题</label><input type="text" name="ads[<?=$slot?>][title]" value="<?=htmlspecialchars($ad['title'] ?? '')?>" class="inp" placeholder="广告位名称"></div>
    <div class="fld-row">
      <div class="fld"><label>图片 URL</label><input type="text" name="ads[<?=$slot?>][image]" value="<?=htmlspecialchars($ad['image'] ?? '')?>" class="inp" placeholder="https://…/banner.png"></div>
      <div class="fld"><label>跳转链接</label><input type="text" name="ads[<?=$slot?>][link]" value="<?=htmlspecialchars($ad['link'] ?? '')?>" class="inp" placeholder="https://…"></div>
    </div>
    <div class="fld"><label>自定义 HTML（优先于图片）</label><textarea name="ads[<?=$slot?>][html]" class="inp" rows="3" placeholder="&lt;!-- 直接粘贴广告代码，如 AdSense / 联盟广告 --&gt;"><?=htmlspecialchars($ad['html'] ?? '')?></textarea></div>
  </div>
  <?php endforeach; ?>
  <button type="submit" class="btn primary">保存广告位</button>
</form>
  </div>
</div>

<?php admin_footer(); ?>
