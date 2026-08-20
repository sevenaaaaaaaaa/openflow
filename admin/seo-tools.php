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
    // IndexNow 配置
    $indexnowFile = DATA_DIR . '/indexnow.json';
    $indexnow = json_read($indexnowFile);
    if (isset($_POST['save_indexnow'])) {
        $key = trim($_POST['indexnow_key'] ?? '');
        $host = trim($_POST['indexnow_host'] ?? '');
        $indexnow = ['key' => $key, 'host' => $host, 'updated_at' => date('Y-m-d H:i:s')];
        json_write($indexnowFile, $indexnow);
        // 生成验证文件 {key}.txt（IndexNow 协议要求）
        if ($key !== '') file_put_contents(__DIR__ . '/../' . $key . '.txt', $key);
        $message = 'IndexNow 配置已保存' . ($key !== '' ? '，验证文件已生成 /' . $key . '.txt' : '');
    }
    if (isset($_POST['gen_indexnow_key'])) {
        $indexnow['key'] = bin2hex(random_bytes(16));
        $indexnow['host'] = trim($_POST['indexnow_host'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
        json_write($indexnowFile, $indexnow);
        if (!empty($indexnow['key'])) file_put_contents(__DIR__ . '/../' . $indexnow['key'] . '.txt', $indexnow['key']);
        $message = '已生成新 IndexNow Key 并写入验证文件';
    }
    if (isset($_POST['test_indexnow'])) {
        require_once __DIR__ . '/../lib/GeoSystem.php';
        $host = ($indexnow['host'] ?? '') ?: ($_SERVER['HTTP_HOST'] ?? '');
        geo_submit_url('https://' . $host . '/sitemap.xml');
        $message = 'IndexNow 测试提交已发出（POST ' . $host . ' → api.indexnow.org），如配置正确返回 200';
    }
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $protocol . '://' . $host;

admin_header('SEO 工具');
?>
<div class="admin-layout">
  <?php admin_sidebar('seo-tools'); ?>
  <div class="main">
    <h1>SEO 工具</h1>
    <p class="sub">Sitemap · robots.txt · 站点验证 · 一键提交搜索引擎</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="stats" style="grid-template-columns:repeat(4,1fr)">
      <div class="stat-card"><div class="num">✓</div><div class="label">Sitemap 已生成</div><div style="margin-top:4px"><a href="javascript:copy('<?=$base?>/sitemap.xml')" class="btn btn-ghost btn-xs">复制</a> <a href="https://www.google.com/ping?sitemap=<?=urlencode($base.'/sitemap.xml')?>" target="_blank" class="btn btn-ghost btn-xs">提交 Google</a> <a href="https://www.bing.com/ping?sitemap=<?=urlencode($base.'/sitemap.xml')?>" target="_blank" class="btn btn-ghost btn-xs">提交 Bing</a></div></div>
      <div class="stat-card"><div class="num">✓</div><div class="label">robots.txt 已生成</div><div style="margin-top:4px"><a href="javascript:copy('<?=$base?>/robots.txt')" class="btn btn-ghost btn-xs">复制</a> <a href="<?=$base?>/robots.txt" target="_blank" class="btn btn-ghost btn-xs">查看</a></div></div>
      <div class="stat-card"><div class="num">✓</div><div class="label">llms.txt 已生成</div><div style="margin-top:4px"><a href="javascript:copy('<?=$base?>/llms.txt')" class="btn btn-ghost btn-xs">复制</a> <a href="<?=$base?>/llms.txt" target="_blank" class="btn btn-ghost btn-xs">查看</a></div></div>
      <div class="stat-card"><div class="num"><?=$seo['google_verify']?'✓':'—'?></div><div class="label">站长验证</div></div>
    </div>

    <!-- IndexNow 配置 -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <h2 style="margin:0">⚡ IndexNow</h2>
        <span class="badge <?=!empty($indexnow['key'])?'badge-green':'badge-gray'?>"><?=!empty($indexnow['key'])?'已启用':'未配置'?></span>
      </div>
      <p class="text-sm text-muted mb-4">IndexNow 协议：内容发布/更新后即时通知 Bing / Yandex 等搜索引擎，加速收录。无需验证文件也可（key.txt 自动生成）。</p>
      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <?= csrf_field() ?>
        <div class="field" style="flex:1;min-width:200px;margin-bottom:0"><label>Key <span class="hint">· 16 字节随机串</span></label><input type="text" name="indexnow_key" value="<?=htmlspecialchars($indexnow['key'] ?? '')?>" placeholder="自动生成或粘贴"></div>
        <div class="field" style="flex:1;min-width:200px;margin-bottom:0"><label>Host <span class="hint">· 你的域名</span></label><input type="text" name="indexnow_host" value="<?=htmlspecialchars($indexnow['host'] ?? ($_SERVER['HTTP_HOST'] ?? ''))?>" placeholder="example.com"></div>
        <button type="submit" name="save_indexnow" class="btn btn-s btn-sm">保存配置</button>
        <button type="submit" name="gen_indexnow_key" class="btn btn-ghost btn-sm" onclick="return confirm('生成新 Key 将替换现有 Key（旧验证文件需删除）？')">🔄 生成新 Key</button>
        <button type="submit" name="test_indexnow" class="btn btn-ghost btn-sm" onclick="return confirm('立即提交一次 IndexNow（POST sitemap）？')">▶ 测试提交</button>
      </form>
      <?php if (!empty($indexnow['key'])): ?>
      <div class="text-sm text-muted" style="margin-top:12px">验证文件：<code>/<?=htmlspecialchars($indexnow['key'])?>.txt</code>（已自动生成于站点根目录）· 文章发布时自动通知 IndexNow</div>
      <?php endif; ?>
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
<?php admin_footer(); ?>
