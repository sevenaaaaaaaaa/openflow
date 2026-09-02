<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

require_once __DIR__ . '/../lib/PluginSystem.php';
PluginSystem::load_plugins();

$message = '';
$registeredPlugins = PluginSystem::get_plugins();
$registry = json_read(DATA_DIR . '/plugins.json');

// Install —— 安装即执行第三方代码，必须校验 CSRF
if (isset($_POST['install'])) {
    csrf_verify();
    $source = trim($_POST['source'] ?? '');
    if ($source) {
        $result = PluginSystem::install_plugin($source);
        $message = $result['ok'] ? "✅ 插件已安装: {$result['name']}" : "❌ {$result['error']}";
    }
}

// Uninstall —— 原为裸 GET，一个 <img src> 就能触发卸载，强制校验 token
if (isset($_GET['uninstall'])) {
    csrf_verify();
    if (PluginSystem::uninstall_plugin($_GET['uninstall'])) {
        $message = '插件已卸载';
    }
    header('Location: /xmp/plugins');
    exit;
}

// Toggle —— 同上，启用/禁用插件是敏感操作，必须带 token
if (isset($_GET['toggle'])) {
    csrf_verify();
    $enabled = !($registry['enabled'][$_GET['toggle']] ?? false);
    PluginSystem::toggle_plugin($_GET['toggle'], $enabled);
    $message = $enabled ? '插件已启用' : '插件已禁用';
    header('Location: /xmp/plugins');
    exit;
}

// Discover from GitHub
$discovered = [];
if (isset($_GET['discover'])) {
    $ch = curl_init('https://api.github.com/search/repositories?q=topic:openflow-plugin&sort=updated&per_page=20');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => 'OpenFlow-CMS', CURLOPT_TIMEOUT => 15]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http === 200) {
        $data = json_decode($resp, true);
        foreach ($data['items'] ?? [] as $repo) {
            $discovered[] = [
                'full_name' => $repo['full_name'],
                'name' => $repo['name'],
                'description' => $repo['description'] ?? '',
                'stars' => $repo['stargazers_count'] ?? 0,
                'url' => $repo['html_url'],
                'updated_at' => $repo['updated_at'] ?? '',
            ];
        }
    }
}

admin_header('插件管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('plugins'); ?>
  <div class="main">
    <h1>插件管理</h1>
    <p class="sub">发现 · 安装 · 管理 · 扩展 CMS 功能</p>
    <?php if ($message): ?><?=msg(str_starts_with($message,'✅')?'success':'error', $message)?><?php endif; ?>

    <div class="stats" style="grid-template-columns:repeat(3,1fr)">
      <div class="stat-card"><div class="num"><?=count($registeredPlugins)?></div><div class="label">已注册插件</div></div>
      <div class="stat-card"><div class="num"><?=count(array_filter($registeredPlugins, fn($p) => $registry['enabled'][$p['id']] ?? true))?></div><div class="label">已启用</div></div>
      <div class="stat-card"><div class="num"><?=count($registry['installed'] ?? [])?></div><div class="label">已安装</div></div>
    </div>

    <!-- Install from GitHub -->
    <div class="card">
      <h2>📦 安装插件</h2>
      <form method="post" class="flex gap-4 items-end">
        <?= csrf_field() ?>
        <div class="field" style="margin-bottom:0;flex:1">
          <label>GitHub 地址 <span class="hint">支持 user/repo 或 ZIP URL</span></label>
          <input type="text" name="source" placeholder="username/openflow-plugin-name" required>
        </div>
        <button type="submit" name="install" class="btn btn-primary">安装</button>
      </form>
      <div style="margin-top:8px">
        <a href="?discover=1" class="btn btn-ghost btn-sm">🔍 发现 GitHub 插件</a>
      </div>
    </div>

    <!-- Installed Plugins -->
    <div class="card">
      <h2>已安装插件</h2>
      <?php if (empty($registry['installed'] ?? [])): ?>
      <div class="empty">暂无已安装的插件</div>
      <?php else: ?>
      <table>
        <thead><tr><th>名称</th><th>版本</th><th>安装时间</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($registry['installed'] as $pid => $p): ?>
          <tr>
            <td><strong><?=htmlspecialchars($p['name'] ?? $pid)?></strong><br><code style="font-size:11px"><?=htmlspecialchars($pid)?></code></td>
            <td><?=htmlspecialchars($p['version'] ?? '1.0')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($p['installed_at'] ?? '')?></td>
            <td><span class="badge <?=($registry['enabled'][$pid] ?? false)?'badge-green':'badge-gray'?>"><?=($registry['enabled'][$pid] ?? false)?'已启用':'已禁用'?></span></td>
            <td>
              <a href="?toggle=<?=urlencode($pid)?>&csrf_token=<?=urlencode(csrf_token())?>" class="btn btn-ghost btn-sm"><?=($registry['enabled'][$pid] ?? false)?'禁用':'启用'?></a>
              <a href="?uninstall=<?=urlencode($pid)?>&csrf_token=<?=urlencode(csrf_token())?>" class="btn btn-danger btn-sm" data-confirm="确认卸载?">卸载</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- Discovered Plugins -->
    <?php if (!empty($discovered)): ?>
    <div class="card">
      <h2>🔍 GitHub 发现 (topic: openflow-plugin)</h2>
      <div style="display:grid;gap:8px">
        <?php foreach ($discovered as $repo): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface-2);border-radius:8px">
          <div style="flex:1">
            <strong><a href="<?=htmlspecialchars($repo['url'])?>" target="_blank" style="color:var(--accent)"><?=htmlspecialchars($repo['full_name'])?></a></strong>
            <p class="text-sm text-muted"><?=htmlspecialchars($repo['description'])?></p>
          </div>
          <span class="text-sm text-muted">⭐ <?=$repo['stars']?></span>
          <form method="post" data-confirm="从 GitHub 安装 <?=htmlspecialchars($repo['full_name'], ENT_QUOTES)?>？安装后需在列表里手动启用。">
            <?= csrf_field() ?>
            <input type="hidden" name="source" value="<?=htmlspecialchars($repo['full_name'])?>">
            <button type="submit" name="install" value="1" class="btn btn-ghost btn-sm">安装</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Plugin Dev Guide -->
    <div class="card">
      <h2>📖 插件开发指南</h2>
      <p class="text-sm text-muted">创建一个插件只需 3 步：</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin-top:12px">
        <div style="padding:16px;background:var(--surface-2);border-radius:12px">
          <strong>1. plugin.json</strong>
          <pre style="font-size:12px;margin-top:4px;background:#1e1e1e;color:#fff;padding:12px;border-radius:6px">{
  "id": "my-plugin",
  "name": "My Plugin",
  "version": "1.0.0",
  "hooks": ["admin_sidebar"]
}</pre>
        </div>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px">
          <strong>2. plugin.php</strong>
          <pre style="font-size:12px;margin-top:4px;background:#1e1e1e;color:#fff;padding:12px;border-radius:6px">PluginSystem::add_action(
  'admin_sidebar_menu',
  function() {
    echo '&lt;a href="..."&gt;My Plugin&lt;/a&gt;';
  }
);</pre>
        </div>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px">
          <strong>3. 可用钩子</strong>
          <div style="font-size:12px;margin-top:4px;line-height:1.8">
            <code>admin_sidebar_menu</code> — 侧边栏菜单<br>
            <code>article_save</code> — 文章保存时<br>
            <code>article_render</code> — 文章渲染时<br>
            <code>admin_header</code> — 后台头部<br>
            <code>plugin_loaded</code> — 插件加载时
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
