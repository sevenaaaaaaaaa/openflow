<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$cfgFile = DATA_DIR . '/sdk-versions.json';
$cfg = json_read($cfgFile);
$message = '';

// 保存版本配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_versions'])) {
    csrf_verify();
    $versions = [];
    foreach (($_POST['ver_version'] ?? []) as $i => $v) {
        if (empty(trim($v))) continue;
        $versions[] = [
            'version' => (int)$v,
            'file' => trim($_POST['ver_file'][$i] ?? ''),
            'note' => trim($_POST['ver_note'][$i] ?? ''),
            'enabled' => isset($_POST['ver_enabled'][$i]),
            'weight' => (int)($_POST['ver_weight'][$i] ?? 0),
        ];
    }
    $cfg['versions'] = $versions;
    $cfg['default'] = (int)($_POST['default_version'] ?? 2);
    json_write($cfgFile, $cfg);
    $message = 'SDK 版本配置已保存';
    $cfg = json_read($cfgFile);
}

$versions = $cfg['versions'] ?? [];
$default = (int)($cfg['default'] ?? 2);

admin_header('SDK 版本管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('sdk-versions'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">📦 SDK 版本管理</h1>
      <div class="flex gap-2 ml-auto">
        <a href="../api/sdk.php" target="_blank" class="btn btn-ghost btn-sm">查看当前 SDK</a>
      </div>
    </div>
    <p class="sub">埋点 SDK 版本化 · 灰度发布 · 秒级回滚 · 版本对比</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card" style="margin-bottom:20px;padding:16px">
      <h2 style="margin-bottom:10px">📈 灰度发布说明</h2>
      <p class="text-sm text-muted">权重 (weight) 决定新访客被分到各版本的比例，总和应为 100。例如 v2 权重 10 + v1 权重 90 = 10% 流量使用新版本。确认稳定后把 v2 权重调到 100 完成全量。</p>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="save_versions" value="1">
      <div class="card" style="padding:0;overflow:auto;margin-bottom:16px">
        <table>
          <thead><tr><th>版本</th><th>文件</th><th>说明</th><th>启用</th><th>灰度权重%</th></tr></thead>
          <tbody>
            <?php if (empty($versions)): ?><tr><td colspan="5" class="empty">暂无版本</td></tr><?php endif; ?>
            <?php foreach ($versions as $i => $v): ?>
            <tr>
              <td><input type="text" name="ver_version[]" value="<?=$v['version']?>" style="width:60px;padding:6px;border:1.5px solid var(--border);border-radius:6px"></td>
              <td><input type="text" name="ver_file[]" value="<?=htmlspecialchars($v['file'])?>" style="width:220px;padding:6px;border:1.5px solid var(--border);border-radius:6px"></td>
              <td><input type="text" name="ver_note[]" value="<?=htmlspecialchars($v['note'] ?? '')?>" style="width:200px;padding:6px;border:1.5px solid var(--border);border-radius:6px"></td>
              <td><input type="checkbox" name="ver_enabled[<?=$i?>]" value="1" <?=$v['enabled']?'checked':''?> style="width:16px;height:16px"></td>
              <td><input type="number" name="ver_weight[]" value="<?=$v['weight'] ?? 0?>" min="0" max="100" style="width:70px;padding:6px;border:1.5px solid var(--border);border-radius:6px"></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="field" style="max-width:200px"><label>默认版本</label><select name="default_version"><?php foreach ($versions as $v): ?><option value="<?=$v['version']?>" <?=$default===$v['version']?'selected':''?>>v<?=$v['version']?></option><?php endforeach; ?></select></div>
      <button type="submit" class="btn btn-primary" style="margin-top:12px">保存版本配置</button>
    </form>

    <!-- 测试入口 -->
    <div class="card" style="margin-top:20px;padding:16px">
      <h2 style="margin-bottom:10px">🧪 版本测试</h2>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($versions as $v): ?>
        <a href="../api/sdk.php?version=<?=$v['version']?>" target="_blank" class="btn btn-ghost btn-sm">查看 v<?=$v['version']?></a>
        <?php endforeach; ?>
        <a href="../api/sdk.php" target="_blank" class="btn btn-primary btn-sm">当前默认（灰度分流）</a>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
