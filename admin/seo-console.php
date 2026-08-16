<?php
/**
 * SEO 站长工具接入 — GSC/Bing/百度 + 公开看板 + 广告回传
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/SeoConsole.php';
require_login();
require_perm('settings');

$settings = seo_console_settings();
$cache = seo_cache();
$message = '';

// 保存配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $settings['gsc_email'] = trim($_POST['gsc_email'] ?? '');
    $settings['gsc_key'] = trim($_POST['gsc_key'] ?? '');
    $settings['gsc_property'] = trim($_POST['gsc_property'] ?? '');
    $settings['bing_api_key'] = trim($_POST['bing_api_key'] ?? '');
    $settings['bing_site'] = trim($_POST['bing_site'] ?? '');
    $settings['baidu_token'] = trim($_POST['baidu_token'] ?? '');
    $settings['baidu_site'] = trim($_POST['baidu_site'] ?? '');
    $settings['public_enabled'] = isset($_POST['public_enabled']);
    $settings['public_slug'] = trim($_POST['public_slug'] ?? '') ?: 'seo-board';
    // 广告平台
    $platforms = [];
    foreach (($_POST['ad_platform'] ?? []) as $i => $ap) {
        if (empty(trim($ap))) continue;
        $platforms[] = ['platform'=>trim($ap), 'endpoint'=>trim($_POST['ad_endpoint'][$i] ?? ''), 'token'=>trim($_POST['ad_token'][$i] ?? '')];
    }
    $settings['ad_platforms'] = $platforms;
    seo_console_save($settings);
    $message = '配置已保存';
}

// 拉取数据
if (isset($_GET['pull'])) {
    $data = seo_console_pull();
    $cache = $data;
    $message = '数据已拉取并缓存';
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$publicUrl = $baseUrl . '/' . ($settings['public_slug'] ?: 'seo-board');

admin_header('SEO 站长工具');
?>
<div class="admin-layout">
  <?php admin_sidebar('seo-console'); ?>
  <div class="main">
    <h1>🔍 SEO 站长工具</h1>
    <p class="sub">接入 Google Search Console / Bing / 百度 · 公开看板 · 广告回传</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 公开看板 -->
    <div class="card" style="background:linear-gradient(135deg,var(--surface),rgba(221,255,14,.08));display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <div style="font-size:32px">🌐</div>
      <div style="flex:1">
        <h2 style="margin-bottom:4px">公开 SEO 看板</h2>
        <p class="text-sm text-muted" style="margin-bottom:0">对外展示搜索表现（点击/曝光/排名），可嵌入官网或分享给团队</p>
      </div>
      <a href="<?=htmlspecialchars($publicUrl)?>" target="_blank" class="btn btn-primary">🌐 查看公开看板</a>
    </div>

    <!-- 配置 -->
    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>⚙️ 站长工具配置</h2>
        <h3 style="font-size:14px;margin:12px 0 8px;color:#2b5f7e">🇺🇸 Google Search Console</h3>
        <div class="field-row">
          <div class="field"><label>Service Account Email</label><input type="text" name="gsc_email" value="<?=htmlspecialchars($settings['gsc_email'])?>" placeholder="xxx@xxx.iam.gserviceaccount.com"></div>
          <div class="field"><label>GSC 属性</label><input type="text" name="gsc_property" value="<?=htmlspecialchars($settings['gsc_property'])?>" placeholder="sc-domain:nownexts.com"></div>
        </div>
        <div class="field"><label>Service Account 私钥 JSON</label><textarea name="gsc_key" rows="4" style="font-family:var(--mono);font-size:12px"><?=htmlspecialchars($settings['gsc_key'])?></textarea></div>

        <h3 style="font-size:14px;margin:16px 0 8px;color:#2b5f7e">🔵 Bing Webmaster</h3>
        <div class="field-row">
          <div class="field"><label>Bing API Key</label><input type="password" name="bing_api_key" value="<?=htmlspecialchars($settings['bing_api_key'])?>"></div>
          <div class="field"><label>Bing 站点</label><input type="text" name="bing_site" value="<?=htmlspecialchars($settings['bing_site'])?>" placeholder="https://nownexts.com/"></div>
        </div>

        <h3 style="font-size:14px;margin:16px 0 8px;color:#2b5f7e">🀄 百度站长</h3>
        <div class="field-row">
          <div class="field"><label>百度 Token</label><input type="password" name="baidu_token" value="<?=htmlspecialchars($settings['baidu_token'])?>"></div>
          <div class="field"><label>百度站点</label><input type="text" name="baidu_site" value="<?=htmlspecialchars($settings['baidu_site'])?>" placeholder="nownexts.com"></div>
        </div>

        <h3 style="font-size:14px;margin:16px 0 8px;color:#2b5f7e">🌍 公开看板</h3>
        <div class="field-row">
          <div class="field"><label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="public_enabled" value="1" <?=$settings['public_enabled']?'checked':''?> style="width:16px;height:16px"> 启用公开看板</label></div>
          <div class="field"><label>看板 Slug</label><input type="text" name="public_slug" value="<?=htmlspecialchars($settings['public_slug'] ?: 'seo-board')?>" placeholder="seo-board"></div>
        </div>

        <h3 style="font-size:14px;margin:16px 0 8px;color:#2b5f7e">📊 广告平台回传</h3>
        <div id="adList">
          <?php foreach ($settings['ad_platforms'] ?? [] as $ai => $ad): ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
            <input type="text" name="ad_platform[]" value="<?=htmlspecialchars($ad['platform'])?>" placeholder="平台名" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <input type="text" name="ad_endpoint[]" value="<?=htmlspecialchars($ad['endpoint'])?>" placeholder="回传 API 端点" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <input type="password" name="ad_token[]" value="<?=htmlspecialchars($ad['token'])?>" placeholder="Token" style="width:140px;padding:7px;border:1.5px solid var(--border);border-radius:8px">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div').remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addAd()">+ 添加回传平台</button>

        <div style="margin-top:16px">
          <button type="submit" name="save" class="btn btn-primary">保存配置</button>
          <a href="?pull=1" class="btn btn-ghost">⚡ 立即拉取数据</a>
        </div>
      </div>
    </form>

    <!-- 缓存数据概览 -->
    <div class="card">
      <h2>📈 最近拉取数据 <?php if (!empty($cache['fetched_at'])): ?><span class="text-sm text-muted">· <?=htmlspecialchars($cache['fetched_at'])?></span><?php endif; ?></h2>
      <?php if (empty($cache['gsc'])): ?>
      <div class="empty" style="padding:24px">暂无数据，配置好 Key 后点「立即拉取」</div>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px">
        <?php $sumClick = array_sum(array_column($cache['gsc'],'clicks')); $sumImp = array_sum(array_column($cache['gsc'],'impressions')); ?>
        <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">近28天点击</div><div style="font-size:24px;font-weight:700"><?=$sumClick?></div></div>
        <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">近28天曝光</div><div style="font-size:24px;font-weight:700"><?=$sumImp?></div></div>
        <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">平均 CTR</div><div style="font-size:24px;font-weight:700"><?=$sumImp>0?round($sumClick/$sumImp*100,2):0?>%</div></div>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead><tr><th>关键词</th><th>页面</th><th>点击</th><th>曝光</th><th>CTR</th><th>排名</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($cache['gsc'],0,20) as $r): ?>
            <tr>
              <td><strong><?=htmlspecialchars($r['query'])?></strong></td>
              <td class="text-sm text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['page'])?></td>
              <td><?=$r['clicks']?></td>
              <td><?=$r['impressions']?></td>
              <td><?=$r['ctr']?>%</td>
              <td><?=$r['position']?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
function addAd() {
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:6px';
  d.innerHTML = '<input type="text" name="ad_platform[]" placeholder="平台名" style="width:130px;padding:7px;border:1.5px solid var(--border);border-radius:8px"><input type="text" name="ad_endpoint[]" placeholder="回传 API 端点" style="flex:1;padding:7px;border:1.5px solid var(--border);border-radius:8px"><input type="password" name="ad_token[]" placeholder="Token" style="width:140px;padding:7px;border:1.5px solid var(--border);border-radius:8px"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div\').remove()">✕</button>';
  document.getElementById('adList').appendChild(d);
}
</script>
<?php admin_footer(); ?>
