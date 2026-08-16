<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ApiKeyAuth.php';
require_login();
require_perm('settings');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $key = ApiKeyAuth::create($_POST);
        $message = "API Key 已创建：{$key['key']}";
    } elseif ($action === 'delete') {
        ApiKeyAuth::delete($_POST['id']);
        $message = '已删除';
    } elseif ($action === 'toggle') {
        ApiKeyAuth::toggle($_POST['id'], isset($_POST['enabled']));
        $message = '已更新';
    }
    header('Location: /xmp/api-keys' . ($message ? '?msg=' . urlencode($message) : ''));
    exit;
}

if (!empty($_GET['msg'])) $message = $_GET['msg'];
$keys = ApiKeyAuth::allKeys();

admin_header('API Key 管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0">API Key 管理</h1>
      <button onclick="document.getElementById('createDialog').style.display='flex'" class="btn btn-primary btn-sm">+ 创建 API Key</button>
    </div>
    <p class="sub">管理 API 访问密钥，控制第三方接入权限</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="card">
      <table>
        <thead><tr><th>名称</th><th>API Key</th><th>权限</th><th>限流</th><th>请求次数</th><th>最后使用</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($keys as $k): ?>
        <tr>
          <td style="font-weight:600"><?=htmlspecialchars($k['name'])?></td>
          <td><code style="font-size:11px"><?=substr($k['key'], 0, 8)?>...<?=substr($k['key'], -4)?></code></td>
          <td><?=implode(', ', $k['permissions'])?></td>
          <td><?=$k['rate_limit']?>/min</td>
          <td><?=$k['request_count']?></td>
          <td style="font-size:12px;color:var(--muted)"><?=$k['last_used'] ?: '从未'?></td>
          <td><span class="pill <?=$k['enabled'] ? 'ok' : 'err'?>"><?=$k['enabled'] ? '启用' : '禁用'?></span></td>
          <td>
            <form method="post" style="display:inline">
              <?=csrf_field()?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?=$k['id']?>">
              <input type="hidden" name="enabled" value="<?=$k['enabled'] ? '' : '1'?>">
              <button class="btn btn-sm btn-ghost"><?=$k['enabled'] ? '禁用' : '启用'?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('确定删除？')">
              <?=csrf_field()?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?=$k['id']?>">
              <button class="btn btn-sm btn-danger">删除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($keys)): ?>
        <tr><td colspan="8" class="empty">暂无 API Key</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>接入说明</h2>
      <div style="font-size:13px;line-height:1.8">
        <p>使用 API Key 认证访问 OpenFlow REST API：</p>
        <pre style="background:var(--hover);padding:12px;border-radius:8px;margin:8px 0;overflow-x:auto"><code>curl -H "X-API-Key: YOUR_API_KEY" https://your-site.com/api/articles.php</code></pre>
        <p>支持的 Header：`X-API-Key` 或 `Authorization: Bearer YOUR_API_KEY`</p>
        <p>响应头包含限流信息：`X-RateLimit-Limit` / `X-RateLimit-Remaining` / `X-RateLimit-Reset`</p>
      </div>
    </div>
  </div>
</div>

<div id="createDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:90%;max-width:500px">
    <h2 style="margin-bottom:16px">创建 API Key</h2>
    <form method="post">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="create">
      <div class="field"><label>名称</label><input type="text" name="name" required placeholder="如：第三方集成"></div>
      <div class="field"><label>权限</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:4px;font-size:13px"><input type="checkbox" name="permissions[]" value="read" checked> 读取</label>
          <label style="display:flex;align-items:center;gap:4px;font-size:13px"><input type="checkbox" name="permissions[]" value="write"> 写入</label>
          <label style="display:flex;align-items:center;gap:4px;font-size:13px"><input type="checkbox" name="permissions[]" value="admin"> 管理</label>
        </div>
      </div>
      <div class="field"><label>限流（每分钟）</label><input type="number" name="rate_limit" value="60" min="1" max="1000"></div>
      <div class="field"><label>IP 白名单 <span class="hint">每行一个，留空不限制</span></label><textarea name="allowed_ips" rows="2" placeholder="192.168.1.1&#10;10.0.0.1"></textarea></div>
      <div class="field"><label>过期时间 <span class="hint">留空永不过期</span></label><input type="datetime-local" name="expires_at"></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="this.closest('[style]').style.display='none'">取消</button>
        <button type="submit" class="btn btn-primary">创建</button>
      </div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
