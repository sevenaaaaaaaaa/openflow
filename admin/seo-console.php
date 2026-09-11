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
$error = '';

// ── Google OAuth 回调/断开（独立访问时在此处理；seo-center 嵌入时宿主页已提前处理）───
$__g = seo_console_handle_google_actions();
if ($__g) { if ($__g[0] === 'error') $error = $__g[1]; else $message = $__g[1]; }

// 保存配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $settings['gsc_email'] = trim($_POST['gsc_email'] ?? '');
    $settings['gsc_key'] = trim($_POST['gsc_key'] ?? '');
    $settings['gsc_property'] = trim($_POST['gsc_property'] ?? '');
    $settings['google_client_id'] = trim($_POST['google_client_id'] ?? '');
    $settings['google_client_secret'] = trim($_POST['google_client_secret'] ?? '');
    $settings['ga4_property'] = trim($_POST['ga4_property'] ?? '');
    $settings['bing_api_key'] = trim($_POST['bing_api_key'] ?? '');
    $settings['bing_site'] = trim($_POST['bing_site'] ?? '');
    $settings['baidu_token'] = trim($_POST['baidu_token'] ?? '');
    $settings['baidu_site'] = trim($_POST['baidu_site'] ?? '');
    $settings['yandex_token'] = trim($_POST['yandex_token'] ?? '');
    $settings['yandex_host'] = trim($_POST['yandex_host'] ?? '');
    $settings['yandex_user_id'] = trim($_POST['yandex_user_id'] ?? 'self');
    $settings['public_enabled'] = isset($_POST['public_enabled']);
    $settings['public_slug'] = trim($_POST['public_slug'] ?? '') ?: 'seo-board';
    // GA4 属性选定后，自动把前台统计需要的 Measurement ID 写进站点设置（无需再手填 G-XXXX）
    if ($settings['ga4_property'] !== '' && function_exists('site_config_set')) {
        $token = seo_google_access_token();
        if ($token) {
            $streams = seo_http_get_json('https://analyticsadmin.googleapis.com/v1beta/properties/' . $settings['ga4_property'] . '/dataStreams', $token);
            foreach ($streams['dataStreams'] ?? [] as $ds) {
                if (($ds['type'] ?? '') === 'WEB_DATA_STREAM' && !empty($ds['webStreamData']['measurementId'])) {
                    site_config_set('ga_id', $ds['webStreamData']['measurementId']);
                    break;
                }
            }
        }
    }
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

// ── 快速收录：手动推送 URL ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['index_submit'])) {
    csrf_verify();
    require_once __DIR__ . '/seo-functions.php';
    $urls = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', (string)($_POST['index_urls'] ?? '')))));
    $urls = array_values(array_filter($urls, fn($u) => preg_match('#^https?://#i', $u)));
    if (!$urls) {
        $error = '请粘贴至少一条完整 URL（https:// 开头）';
    } else {
        $okIn = 0; $okBd = 0;
        foreach (array_slice($urls, 0, 20) as $u) {
            $r = seo_submit_url($u);
            if ($r['indexnow']) $okIn++;
            if ($r['baidu']) $okBd++;
        }
        $message = "推送完成：IndexNow 成功 {$okIn}/" . count($urls) . "，百度成功 {$okBd}/" . count($urls);
    }
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$publicUrl = $baseUrl . '/' . ($settings['public_slug'] ?: 'seo-board');

if (!defined('OF_EMBED')) admin_header('SEO 站长工具');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('seo-console'); ?>
  <div class="main">
<?php endif; ?>
    <h1> SEO 站长工具</h1>
    <p class="sub">接入 Google Search Console / GA4 / Bing / 百度 / Yandex · 公开看板 · 广告回传</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <!-- Google 一键授权（GSC + GA4 共用） -->
    <div class="card" style="background:linear-gradient(135deg,var(--surface),rgba(66,133,244,.08));border:1.5px solid rgba(66,133,244,.25)">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="font-size:32px">🔗</div>
        <div style="flex:1;min-width:260px">
          <h2 style="margin-bottom:4px">Google 账号授权</h2>
          <?php if (!empty($settings['google_refresh_token'])): ?>
          <p class="text-sm" style="margin-bottom:0;color:var(--ok,#16a34a)">✅ 已连接 <b><?=htmlspecialchars($settings['google_account'] ?: 'Google 账号')?></b> · Search Console 与 GA4 数据自动拉取，token 自动刷新</p>
          <?php else: ?>
          <p class="text-sm text-muted" style="margin-bottom:0">一次授权同时接通 <b>Search Console</b> 和 <b>GA4</b>：属性自动列出下拉即选，GA 统计代码自动注入前台，不用再手填 Service Account JSON 和 G-XXXX</p>
          <?php endif; ?>
        </div>
        <?php if (!empty($settings['google_refresh_token'])): ?>
        <a href="<?=of_hub_url(['google_disconnect'=>1])?>" class="btn btn-ghost" data-confirm="断开后 GSC/GA4 数据将停止自动拉取，确定？">断开连接</a>
        <?php elseif (!empty($settings['google_client_id']) && !empty($settings['google_client_secret'])): ?>
        <a href="<?=htmlspecialchars(seo_google_oauth_url())?>" class="btn btn-primary">🔑 连接 Google 账号</a>
        <?php endif; ?>
      </div>
      <?php if (empty($settings['google_refresh_token'])): ?>
      <details style="margin-top:12px">
        <summary class="text-sm" style="cursor:pointer;color:var(--accent,#2563eb)">⚙️ 首次使用：配置 Google OAuth 客户端（一次性，约 5 分钟）</summary>
        <div class="text-sm text-muted" style="margin-top:8px;line-height:1.9">
          1. 打开 <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console → 凭据</a>，创建「OAuth 客户端 ID」（类型：Web 应用）<br>
          2. 「已授权的重定向 URI」填：<code style="background:var(--hover,rgba(0,0,0,.06));padding:2px 8px;border-radius:6px;user-select:all"><?=htmlspecialchars(seo_google_callback_url())?></code><br>
          3. 启用 <a href="https://console.cloud.google.com/apis/library/webmasters.googleapis.com" target="_blank">Search Console API</a>、<a href="https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com" target="_blank">Analytics Admin API</a> 与 <a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank">Analytics Data API</a><br>
          4. 把 Client ID / Secret 填到下方表单保存，再点「连接 Google 账号」
        </div>
      </details>
      <div class="field-row" style="margin-top:10px">
        <div class="field"><label>Google OAuth Client ID</label><input type="text" name="google_client_id" form="seoCfg" value="<?=htmlspecialchars($settings['google_client_id'] ?? '')?>" placeholder="xxx.apps.googleusercontent.com"></div>
        <div class="field"><label>Client Secret</label><input type="password" name="google_client_secret" form="seoCfg" value="<?=htmlspecialchars($settings['google_client_secret'] ?? '')?>"></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- 快速收录 -->
    <?php
    require_once __DIR__ . '/seo-functions.php';
    $__inCfg = indexnow_config();
    $__keyUrl = 'https://' . ($__inCfg['host'] ?? '') . '/' . ($__inCfg['key'] ?? '') . '.txt';
    $__baiduReady = !empty($settings['baidu_token']) && !empty($settings['baidu_site']);
    $__indexLog = index_log_get();
    ?>
    <div class="card" style="border:1.5px solid rgba(22,163,74,.3);background:linear-gradient(135deg,var(--surface),rgba(22,163,74,.06))">
      <h2>⚡ 快速收录 <span class="hint" style="font-weight:400">· 发布文章时自动双路推送，也可手动推任意页面</span></h2>
      <div style="display:flex;gap:24px;flex-wrap:wrap;margin:10px 0 14px">
        <div class="text-sm">
          <b>IndexNow</b>（Bing / Yandex / Naver）<br>
          <?php if (!empty($__inCfg['key'])): ?>
          <span style="color:#16a34a">✅ 已自动就绪</span> · key 验证地址 <a href="<?=htmlspecialchars($__keyUrl)?>" target="_blank" class="text-sm"><?=htmlspecialchars($__keyUrl)?></a>
          <?php else: ?>
          <span style="color:#dc2626">❌ 未就绪</span>
          <?php endif; ?>
        </div>
        <div class="text-sm">
          <b>百度主动推送</b><br>
          <?php if ($__baiduReady): ?>
          <span style="color:#16a34a">✅ 已配置</span>（站点 <?=htmlspecialchars($settings['baidu_site'])?>）
          <?php else: ?>
          <span style="color:#d97706">⚠️ 未配置</span> —— 在下方「百度站长」填入 Token 与站点即可开通
          <?php endif; ?>
        </div>
        <div class="text-sm">
          <b>Google</b><br>
          <span class="text-muted">靠 sitemap + 上方 Google 授权后自动拉取收录状态；Google 不开放普通页面的主动推送 API</span>
        </div>
      </div>
      <form method="post" style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
        <?= csrf_field() ?>
        <textarea name="index_urls" rows="2" style="flex:1;min-width:320px;font-family:var(--mono);font-size:12px;padding:8px;border:1.5px solid var(--border);border-radius:8px" placeholder="https://nownexts.com/article/xxx&#10;每行一条，最多 20 条"></textarea>
        <button type="submit" name="index_submit" class="btn btn-primary">⚡ 立即推送收录</button>
      </form>
      <?php if ($__indexLog): ?>
      <details style="margin-top:12px">
        <summary class="text-sm" style="cursor:pointer;color:var(--accent,#2563eb)">  推送日志（最近 <?=count($__indexLog)?> 条）</summary>
        <div style="overflow-x:auto;margin-top:8px"><table>
          <thead><tr><th>时间</th><th>引擎</th><th>URL</th><th>结果</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($__indexLog, 0, 15) as $lg): ?>
            <tr>
              <td class="text-sm text-muted"><?=htmlspecialchars($lg['at'])?></td>
              <td><?=htmlspecialchars($lg['engine'])?></td>
              <td class="text-sm" style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($lg['url'])?></td>
              <td class="text-sm"><?=$lg['ok'] ? '<span style="color:#16a34a">✅</span>' : '<span style="color:#dc2626">❌</span>'?> <?=htmlspecialchars($lg['note'] ?? '')?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      </details>
      <?php endif; ?>
    </div>

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
    <form method="post" id="seoCfg">
      <?= csrf_field() ?>
      <div class="card">
        <h2>⚙️ 站长工具配置</h2>
        <h3 style="font-size:14px;margin:12px 0 8px;color:#2b5f7e">🇺🇸 Google Search Console</h3>
        <?php if (!empty($settings['google_refresh_token'])): ?>
        <?php $__gscSites = seo_gsc_list_sites(); ?>
        <div class="field-row">
          <div class="field"><label>GSC 属性（授权账号下自动列出）</label>
            <select name="gsc_property" style="width:100%;padding:8px;border:1.5px solid var(--border);border-radius:8px">
              <option value="">— 选择属性 —</option>
              <?php foreach ($__gscSites as $su): ?><option value="<?=htmlspecialchars($su)?>" <?=$settings['gsc_property']===$su?'selected':''?>><?=htmlspecialchars($su)?></option><?php endforeach; ?>
              <?php if ($settings['gsc_property'] && !in_array($settings['gsc_property'], $__gscSites, true)): ?><option value="<?=htmlspecialchars($settings['gsc_property'])?>" selected><?=htmlspecialchars($settings['gsc_property'])?>（当前）</option><?php endif; ?>
            </select>
          </div>
        </div>
        <?php else: ?>
        <p class="text-sm text-muted mb-2">👆 建议直接用上面的「连接 Google 账号」一键授权；以下为手动 Service Account 方式（留作回退）。</p>
        <div class="field-row">
          <div class="field"><label>Service Account Email</label><input type="text" name="gsc_email" value="<?=htmlspecialchars($settings['gsc_email'])?>" placeholder="xxx@xxx.iam.gserviceaccount.com"></div>
          <div class="field"><label>GSC 属性</label><input type="text" name="gsc_property" value="<?=htmlspecialchars($settings['gsc_property'])?>" placeholder="sc-domain:example.com"></div>
        </div>
        <div class="field"><label>Service Account 私钥 JSON</label><textarea name="gsc_key" rows="4" style="font-family:var(--mono);font-size:12px"><?=htmlspecialchars($settings['gsc_key'])?></textarea></div>
        <?php endif; ?>

        <h3 style="font-size:14px;margin:16px 0 8px;color:#2b5f7e">📈 Google Analytics 4</h3>
        <?php if (!empty($settings['google_refresh_token'])): ?>
        <?php $__ga4Props = seo_ga4_list_properties(); ?>
        <div class="field-row">
          <div class="field"><label>GA4 属性（选定后自动写入前台统计代码，无需手填 G-XXXX）</label>
            <select name="ga4_property" style="width:100%;padding:8px;border:1.5px solid var(--border);border-radius:8px">
              <option value="">— 选择属性 —</option>
              <?php foreach ($__ga4Props as $gp): ?><option value="<?=htmlspecialchars($gp['id'])?>" <?=$settings['ga4_property']===$gp['id']?'selected':''?>><?=htmlspecialchars($gp['label'])?>（ID: <?=htmlspecialchars($gp['id'])?>）</option><?php endforeach; ?>
              <?php if ($settings['ga4_property'] && !array_filter($__ga4Props, fn($p) => $p['id'] === $settings['ga4_property'])): ?><option value="<?=htmlspecialchars($settings['ga4_property'])?>" selected>当前：<?=htmlspecialchars($settings['ga4_property'])?></option><?php endif; ?>
            </select>
          </div>
        </div>
        <?php else: ?>
        <p class="text-sm text-muted mb-2">连接 Google 账号后此处自动列出 GA4 属性；当前可在「设置 → Google Analytics ID」手动填 G-XXXX（保存后前台自动注入统计代码）。</p>
        <?php endif; ?>

        <h3 style="font-size:14px;margin:16px 0 8px;color:#2b5f7e">🔵 Bing Webmaster</h3>
        <div class="field-row">
          <div class="field"><label>Bing API Key</label><input type="password" name="bing_api_key" value="<?=htmlspecialchars($settings['bing_api_key'])?>"></div>
          <div class="field"><label>Bing 站点</label><input type="text" name="bing_site" value="<?=htmlspecialchars($settings['bing_site'])?>" placeholder="https://example.com/"></div>
        </div>

        <h3 style="font-size:14px;margin:16px 0 8px;color:#2b5f7e">🀄 百度站长</h3>
        <div class="field-row">
          <div class="field"><label>百度 Token</label><input type="password" name="baidu_token" value="<?=htmlspecialchars($settings['baidu_token'])?>"></div>
          <div class="field"><label>百度站点</label><input type="text" name="baidu_site" value="<?=htmlspecialchars($settings['baidu_site'])?>" placeholder="example.com"></div>
        </div>

        <h3 style="font-size:14px;margin:16px 0 8px;color:#2b5f7e">  Yandex Webmaster</h3>
        <p class="text-sm text-muted mb-2">在 <a href="https://webmaster.yandex.com" target="_blank">Yandex Webmaster</a> 获取 OAuth Token，然后填写主机名（含协议）。</p>
        <div class="field-row">
          <div class="field"><label>OAuth Token</label><input type="password" name="yandex_token" value="<?=htmlspecialchars($settings['yandex_token'] ?? '')?>"></div>
          <div class="field"><label>主机名</label><input type="text" name="yandex_host" value="<?=htmlspecialchars($settings['yandex_host'] ?? '')?>" placeholder="https://yourdomain.com"></div>
        </div>
        <div class="field-row" style="margin-top:8px">
          <div class="field"><label>用户ID（默认 self）</label><input type="text" name="yandex_user_id" value="<?=htmlspecialchars($settings['yandex_user_id'] ?? 'self')?>" placeholder="self"></div>
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
          <a href="<?=of_hub_url(['pull'=>1])?>" class="btn btn-ghost">⚡ 立即拉取数据</a>
        </div>
      </div>
    </form>

    <!-- 缓存数据概览 -->
    <div class="card">
      <h2>📈 最近拉取数据 <?php if (!empty($cache['fetched_at'])): ?><span class="text-sm text-muted">· <?=htmlspecialchars($cache['fetched_at'])?></span><?php endif; ?></h2>
      <?php if (!empty($cache['ga4'])): ?>
      <h3 style="font-size:14px;margin:4px 0 10px;color:#2b5f7e">📈 GA4 · 近 28 天热门页面</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:12px">
        <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">会话</div><div style="font-size:24px;font-weight:700"><?=array_sum(array_column($cache['ga4'],'sessions'))?></div></div>
        <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">浏览量</div><div style="font-size:24px;font-weight:700"><?=array_sum(array_column($cache['ga4'],'views'))?></div></div>
        <div class="card" style="text-align:center"><div style="font-size:12px;color:var(--text-3)">用户</div><div style="font-size:24px;font-weight:700"><?=array_sum(array_column($cache['ga4'],'users'))?></div></div>
      </div>
      <div style="overflow-x:auto;margin-bottom:20px">
        <table>
          <thead><tr><th>页面</th><th>会话</th><th>浏览</th><th>用户</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($cache['ga4'],0,10) as $r): ?>
            <tr><td class="text-sm" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['page'])?></td><td><?=$r['sessions']?></td><td><?=$r['views']?></td><td><?=$r['users']?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <?php if (empty($cache['gsc'])): ?>
      <div class="empty" style="padding:24px">暂无 GSC 数据，连接 Google 账号并选择属性后点「立即拉取」</div>
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
              <td><strong><?=htmlspecialchars($r['query'] ?? '')?></strong></td>
              <td class="text-sm text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['page'] ?? '')?></td>
              <td><?=(int)($r['clicks'] ?? 0)?></td>
              <td><?=(int)($r['impressions'] ?? 0)?></td>
              <td><?=isset($r['ctr']) ? $r['ctr'] : (($r['impressions'] ?? 0) > 0 ? round(($r['clicks'] ?? 0) / $r['impressions'] * 100, 2) : 0)?>%</td>
              <td><?=htmlspecialchars((string)($r['position'] ?? '—'))?></td>
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
<?php if (!defined('OF_EMBED')) admin_footer(); ?>
