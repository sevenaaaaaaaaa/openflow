<?php
/**
 * 上线体检 —— 安装/部署后的运行时前置检查（后台页）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/HealthCheck.php';
require_login();
require_perm('settings');

$rows = health_check_rows(dirname(__DIR__));
$errors = health_check_errors($rows);
if (!defined('OF_EMBED')) admin_header('上线体检');
?>
<div class="admin-layout">
  <?php admin_sidebar('health-check'); ?>
  <div class="main">
    <div class="panel">
      <div class="p-head"><strong>上线体检</strong><span class="hint">安装或升级后逐项确认；红色项处理完再对外</span></div>
      <div class="p-body">
        <?php if ($errors === 0): ?>
          <p class="msg msg-success">无错误项。可直接按上线验收清单继续。</p>
        <?php else: ?>
          <p class="msg msg-error">有 <?= (int) $errors ?> 项错误需要处理。</p>
        <?php endif; ?>
        <table class="tbl">
          <thead><tr><th>状态</th><th>检查项</th><th>结果</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= $row['level'] === 'ok' ? '✓' : ($row['level'] === 'warn' ? '!' : '✗') ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['detail']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php if (!defined('OF_EMBED')) admin_footer(); ?>
