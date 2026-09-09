<?php
/**
 * 插件设置页宿主 — /xmp/plugin/{插件ID}
 *
 * 插件用 PluginSystem::register_admin_page() 注册渲染回调，
 * 这里提供后台外壳（登录保护 + 侧栏 + 标题），回调只管内容。
 */
require_once __DIR__ . '/config.php';
require_login();

$pluginId = preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['plugin'] ?? ''));
$plugins = PluginSystem::get_plugins();
$meta = $plugins[$pluginId] ?? null;

admin_header(($meta['name'] ?? $pluginId ?: '插件') . ' · 插件');
?>
<div class="admin-layout">
  <?php admin_sidebar('plugins'); ?>
  <div class="main">
    <?php if (!$meta): ?>
    <div class="card"><h2>插件不存在</h2><p class="hint">该插件未安装。 <a href="/xmp/plugins">返回插件管理 →</a></p></div>
    <?php elseif (!PluginSystem::render_admin_page($pluginId)): ?>
    <div class="card">
      <h2><?=htmlspecialchars($meta['name'] ?? $pluginId)?></h2>
      <p class="hint"><?=htmlspecialchars($meta['description'] ?? '')?></p>
      <p class="hint">该插件没有注册设置页。版本 <?=htmlspecialchars($meta['version'] ?? '?')?> · 作者 <?=htmlspecialchars($meta['author'] ?? '?')?></p>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
