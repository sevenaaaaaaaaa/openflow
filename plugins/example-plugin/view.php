<?php
/**
 * 示例插件 — 配置页
 */
require_once __DIR__ . '/../../admin/config.php';
require_login();
require_perm('settings');

$configFile = DATA_DIR . '/plugins/example-plugin/webhook.json';
$config = json_read($configFile);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!is_dir(dirname($configFile))) mkdir(dirname($configFile), 0755, true);
    json_write($configFile, ['url' => trim($_POST['webhook_url'] ?? '')]);
    $message = '配置已保存';
}

admin_header('示例插件');
?>
<div class="admin-layout">
  <?php admin_sidebar('example-plugin'); ?>
  <div class="main">
    <h1>📌 示例插件</h1>
    <p class="sub">展示如何扩展 OpenFlow XMP 平台</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card">
      <h2>🔧 功能演示</h2>
      <table>
        <tr><td><strong>自动打标签</strong></td><td class="text-sm text-muted">文章正文提到「增长」时自动加「增长主题」标签</td></tr>
        <tr><td><strong>保存通知</strong></td><td class="text-sm text-muted">文章保存后转发到下方 Webhook</td></tr>
        <tr><td><strong>表单通知</strong></td><td class="text-sm text-muted">表单提交时通过通知渠道推送</td></tr>
      </table>
    </div>

    <form method="post">
      <div class="card">
        <h2>🔗 Webhook 配置</h2>
        <p class="text-sm text-muted mb-4">文章保存时 POST 到该地址（用于外部系统联动）</p>
        <div class="field"><label>Webhook URL</label><input type="text" name="webhook_url" value="<?=htmlspecialchars($config['url'] ?? '')?>" placeholder="https://your-system.example.com/webhook"></div>
        <button type="submit" name="save" class="btn btn-primary">保存</button>
      </div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
