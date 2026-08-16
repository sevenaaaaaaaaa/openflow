<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$channelsFile = DATA_DIR . '/channels.json';
$channels = json_read($channelsFile);

// 兜底默认结构（数据缺失时避免 null 访问）
if (empty($channels['domestic']) || !is_array($channels['domestic'])) {
    $channels['domestic'] = [
        ['id' => 'wechat_mp', 'name' => '微信公众号', 'api_url' => '', 'api_key' => '', 'enabled' => false],
        ['id' => 'wechat_work', 'name' => '企业微信', 'api_url' => '', 'api_key' => '', 'enabled' => false],
    ];
}
if (empty($channels['international']) || !is_array($channels['international'])) {
    $channels['international'] = [
        ['id' => 'email', 'name' => '邮件', 'api_url' => '', 'api_key' => '', 'enabled' => false],
        ['id' => 'webhook', 'name' => 'Webhook', 'api_url' => '', 'api_key' => '', 'enabled' => false],
    ];
}
if (!isset($channels['default_push_format'])) {
    $channels['default_push_format'] = 'draft';
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    csrf_verify();
    $domestic = $_POST['domestic'] ?? [];
    $international = $_POST['international'] ?? [];
    // Merge saved config back
    foreach ($channels['domestic'] as &$dc) {
        if (isset($domestic[$dc['id']])) {
            $dc['api_url'] = $domestic[$dc['id']]['api_url'] ?? '';
            $dc['api_key'] = $domestic[$dc['id']]['api_key'] ?? '';
            $dc['enabled'] = isset($domestic[$dc['id']]['enabled']);
        }
    }
    foreach ($channels['international'] as &$ic) {
        if (isset($international[$ic['id']])) {
            $ic['api_url'] = $international[$ic['id']]['api_url'] ?? '';
            $ic['api_key'] = $international[$ic['id']]['api_key'] ?? '';
            $ic['enabled'] = isset($international[$ic['id']]['enabled']);
        }
    }
    $channels['default_push_format'] = $_POST['default_push_format'] ?? 'draft';
    json_write($channelsFile, $channels);
    $message = '渠道配置已保存';
}

admin_header('内容分发渠道');
?>
<div class="admin-layout">
  <?php admin_sidebar('channels'); ?>
  <div class="main">
    <h1>内容分发渠道</h1>
    <p class="sub">配置国内外内容平台 · 文章编辑页可一键推送到各平台草稿箱</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card">
      <h2>📡 推送接口说明</h2>
      <p class="text-sm text-muted">文章编辑页底部将显示「推送」按钮，点击后通过各平台 API 将内容推送到目标平台的草稿箱。</p>
      <p class="text-sm text-muted">国内平台需自行部署各平台开发者对接；海外平台可通过 API 或 Webhook 桥接。</p>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <h2>🇨🇳 国内渠道</h2>
        <table>
          <thead><tr><th>启用</th><th>平台</th><th>API 地址</th><th>API Key / Token</th></tr></thead>
          <tbody>
            <?php foreach ($channels['domestic'] as $dc): ?>
            <tr>
              <td><input type="checkbox" name="domestic[<?=$dc['id']?>][enabled]" value="1" <?=$dc['enabled']?'checked':''?> style="width:18px;height:18px"></td>
              <td><strong><?=htmlspecialchars($dc['name'])?></strong></td>
              <td><input type="text" name="domestic[<?=$dc['id']?>][api_url]" value="<?=htmlspecialchars($dc['api_url'])?>" placeholder="API 端点" style="width:100%;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px"></td>
              <td><input type="password" name="domestic[<?=$dc['id']?>][api_key]" value="<?=htmlspecialchars($dc['api_key'])?>" placeholder="Token / Secret" style="width:100%;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px"></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2>🌍 海外渠道</h2>
        <table>
          <thead><tr><th>启用</th><th>平台</th><th>API 地址</th><th>API Key</th></tr></thead>
          <tbody>
            <?php foreach ($channels['international'] as $ic): ?>
            <tr>
              <td><input type="checkbox" name="international[<?=$ic['id']?>][enabled]" value="1" <?=$ic['enabled']?'checked':''?> style="width:18px;height:18px"></td>
              <td><strong><?=htmlspecialchars($ic['name'])?></strong></td>
              <td><input type="text" name="international[<?=$ic['id']?>][api_url]" value="<?=htmlspecialchars($ic['api_url'])?>" placeholder="API 端点" style="width:100%;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px"></td>
              <td><input type="password" name="international[<?=$ic['id']?>][api_key]" value="<?=htmlspecialchars($ic['api_key'])?>" placeholder="API Key" style="width:100%;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px"></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2>⚙️ 推送设置</h2>
        <div class="field"><label>默认推送格式</label><select name="default_push_format"><option value="draft" <?=$channels['default_push_format']==='draft'?'selected':''?>>草稿</option><option value="published" <?=$channels['default_push_format']==='published'?'selected':''?>>直接发布</option></select></div>
      </div>

      <button type="submit" name="save" class="btn btn-primary">保存配置</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
