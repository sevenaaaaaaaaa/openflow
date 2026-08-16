<?php
/**
 * Staging banner — include in frontend HTML files
 * Usage: <?php include 'staging-banner.php'; ?>
 */
$settings = json_read(__DIR__ . '/data/settings.json');
if (!empty($settings['staging_mode'])): ?>
<style>
.staging-banner{position:fixed;top:0;left:0;right:0;z-index:99999;background:#ff6b35;color:#fff;text-align:center;padding:8px 16px;font-size:14px;font-weight:600;font-family:-apple-system,sans-serif}
.staging-banner a{color:#fff;text-decoration:underline}
body{padding-top:36px!important}
.nav{top:52px!important}
</style>
<div class="staging-banner"><?=htmlspecialchars($settings['staging_banner'] ?? '🧪 测试环境')?></div>
<?php endif; ?>
