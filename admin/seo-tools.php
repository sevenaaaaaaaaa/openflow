<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('seo-tools');

$seoSettingsFile = DATA_DIR . '/seo-settings.json';
$seo = json_read($seoSettingsFile);

// Custom sitemap additions & robots.txt overrides
$sitemapExtraFile = DATA_DIR . '/sitemap-extra.txt';
$robotsExtraFile = DATA_DIR . '/robots-extra.txt';
$sitemapExtra = file_exists($sitemapExtraFile) ? file_get_contents($sitemapExtraFile) : '';
$robotsExtra = file_exists($robotsExtraFile) ? file_get_contents($robotsExtraFile) : '';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['save_extra'])) {
        file_put_contents($sitemapExtraFile, $_POST['sitemap_extra'] ?? '');
        file_put_contents($robotsExtraFile, $_POST['robots_extra'] ?? '');
        $message = 'sitemap.xml 和 robots.txt 附加内容已保存';
    }
    if (isset($_POST['save_settings'])) {
        foreach (['google_verify','baidu_verify','bing_verify','canonical_domain','hreflang','og_image','og_locale','twitter_handle','fb_app_id','meta_robots_index','meta_robots_follow','ga4_id','baidu_tongji_id'] as $k) {
            $seo[$k] = $_POST[$k] ?? '';
        }
        json_write($seoSettingsFile, $seo);
        // Generate verification files
        if ($seo['google_verify']) file_put_contents(__DIR__ . '/../' . $seo['google_verify'] . '.html', 'google-site-verification: ' . $seo['google_verify']);
        if ($seo['baidu_verify']) file_put_contents(__DIR__ . '/../' . $seo['baidu_verify'] . '.html', $seo['baidu_verify']);
        $message = 'SEO 设置已保存，验证文件已生成';
    }
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $protocol . '://' . $host;

if (!defined('OF_EMBED')) admin_header('SEO 工具');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('seo-tools'); ?>
  <div class="main">
<?php endif; ?>
    <h1>SEO 工具</h1>
    <p class="sub">Sitemap · robots.txt · 站点验证 · 一键提交搜索引擎</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="stats" style="grid-template-columns:repeat(4,1fr)">
      <div class="stat-card"><div class="num">✓</div><div class="label">Sitemap 已生成</div><div style="margin-top:4px"><a href="javascript:copy('<?=$base?>/sitemap.xml')" class="btn btn-ghost btn-xs">复制</a> <a href="https://www.google.com/ping?sitemap=<?=urlencode($base.'/sitemap.xml')?>" target="_blank" class="btn btn-ghost btn-xs">提交 Google</a> <a href="https://www.bing.com/ping?sitemap=<?=urlencode($base.'/sitemap.xml')?>" target="_blank" class="btn btn-ghost btn-xs">提交 Bing</a></div></div>
      <div class="stat-card"><div class="num">✓</div><div class="label">robots.txt 已生成</div><div style="margin-top:4px"><a href="javascript:copy('<?=$base?>/robots.txt')" class="btn btn-ghost btn-xs">复制</a> <a href="<?=$base?>/robots.txt" target="_blank" class="btn btn-ghost btn-xs">查看</a></div></div>
      <div class="stat-card"><div class="num">✓</div><div class="label">llms.txt 已生成</div><div style="margin-top:4px"><a href="javascript:copy('<?=$base?>/llms.txt')" class="btn btn-ghost btn-xs">复制</a> <a href="<?=$base?>/llms.txt" target="_blank" class="btn btn-ghost btn-xs">查看</a></div></div>
      <div class="stat-card"><div class="num"><?=!empty($seo['google_verify'])?'✓':'—'?></div><div class="label">站长验证</div></div>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <!-- Sitemap Extra -->
      <div class="card">
        <h2>🗺️ Sitemap XML 编辑</h2>
        <p class="text-sm text-muted mb-4">sitemap.xml 自动包含所有页面和已发布文章。在此添加额外 URL 或自定义条目。</p>
        <div class="flex gap-2 mb-4">
          <code style="flex:1;padding:8px 14px;background:var(--surface-2);border-radius:6px"><?=$base?>/sitemap.xml</code>
          <button type="button" class="btn btn-primary btn-sm" onclick="copy('<?=$base?>/sitemap.xml')">复制链接</button>
          <a href="https://www.google.com/ping?sitemap=<?=urlencode($base.'/sitemap.xml')?>" class="btn btn-ghost btn-sm" target="_blank">提交 Google</a>
          <a href="https://www.bing.com/ping?sitemap=<?=urlencode($base.'/sitemap.xml')?>" class="btn btn-ghost btn-sm" target="_blank">提交 Bing</a>
          <a href="https://search.google.com/search-console" class="btn btn-ghost btn-sm" target="_blank">Search Console</a>
        </div>
        <div class="field"><label>额外 URL（每行一个，格式: URL 优先级 更新频率）</label>
          <textarea name="sitemap_extra" rows="6" style="font-family:var(--mono);font-size:13px"><?=htmlspecialchars($sitemapExtra)?></textarea>
        </div>
      </div>

      <!-- Robots.txt -->
      <div class="card">
        <h2>🤖 robots.txt 编辑</h2>
        <p class="text-sm text-muted mb-4">robots.txt 自动生成。在此添加额外规则或覆盖默认配置。</p>
        <div class="flex gap-2 mb-4">
          <code style="flex:1;padding:8px 14px;background:var(--surface-2);border-radius:6px"><?=$base?>/robots.txt</code>
          <button type="button" class="btn btn-primary btn-sm" onclick="copy('<?=$base?>/robots.txt')">复制链接</button>
        </div>
        <div class="field"><label>附加规则</label>
          <textarea name="robots_extra" rows="6" style="font-family:var(--mono);font-size:13px"><?=htmlspecialchars($robotsExtra)?></textarea>
        </div>
      </div>

      <button type="submit" name="save_extra" class="btn btn-primary">保存 Sitemap & Robots 附加内容</button>
    </form>

    <!-- Site Verification -->
    <div class="card">
      <h2>🔑 站点验证 & 元标记</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_settings" value="1">
        <div class="field-row">
          <div class="field"><label>Google Search Console</label><input type="text" name="google_verify" value="<?=htmlspecialchars($seo['google_verify']??'')?>" placeholder="googleXXXXXXX"></div>
          <div class="field"><label>百度站长平台</label><input type="text" name="baidu_verify" value="<?=htmlspecialchars($seo['baidu_verify']??'')?>" placeholder="baidu_verify_XXXXX"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Bing Webmaster</label><input type="text" name="bing_verify" value="<?=htmlspecialchars($seo['bing_verify']??'')?>" placeholder="Bing 验证代码"></div>
          <div class="field"><label>Canonical 域名</label><input type="text" name="canonical_domain" value="<?=htmlspecialchars($seo['canonical_domain']??'')?>" placeholder="example.com"></div>
        </div>
        <button type="submit" class="btn btn-primary">保存验证设置</button>
      </form>
    </div>
  </div>
</div>

<script>
function copy(t) {
  navigator.clipboard.writeText(t).then(function() { alert('已复制: ' + t); });
}
</script>
<?php if (!defined('OF_EMBED')) admin_footer(); ?>
