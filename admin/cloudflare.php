<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CloudflareApi.php';
require_login();
require_perm('settings');

$cfg = CloudflareApi::config();
$message = '';
$error = '';

// 保存配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    csrf_verify();
    CloudflareApi::saveConfig([
        'email' => trim($_POST['email'] ?? ''),
        'api_key' => trim($_POST['api_key'] ?? ''),
        'token' => trim($_POST['token'] ?? ''),
        'zone_id' => trim($_POST['zone_id'] ?? ''),
        'zone_name' => trim($_POST['zone_name'] ?? ''),
    ]);
    $cfg = CloudflareApi::config();
    $message = 'Cloudflare 配置已保存';
}

// 验证连接
if (isset($_GET['verify'])) {
    csrf_verify();
    $r = CloudflareApi::verify();
    if (($r['success'] ?? false)) $message = '✅ 连接成功：' . ($r['result']['email'] ?? '账户');
    else $error = '连接失败：' . ($r['errors'][0]['message'] ?? '未知');
}

// 清缓存
if (isset($_GET['purge'])) {
    csrf_verify();
    $r = CloudflareApi::purgeCache();
    if (($r['success'] ?? false)) $message = '✅ 全站缓存已清理';
    else $error = '清理失败：' . ($r['errors'][0]['message'] ?? '未知');
}

// 添加 DNS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dns'])) {
    csrf_verify();
    $type = $_POST['dns_type'] ?? 'A';
    $name = trim($_POST['dns_name'] ?? '');
    $content = trim($_POST['dns_content'] ?? '');
    $zoneName = CloudflareApi::config()['zone_name'] ?? '';
    $fullName = $name === '@' ? $zoneName : ($name ? $name . ($zoneName && !str_ends_with($name, $zoneName) ? '.' . $zoneName : '') : '');
    if (!$name || !$content) { $error = '名称和内容必填'; }
    else {
        $r = CloudflareApi::addDnsRecord($type, $fullName ?: $name, $content);
        if (($r['success'] ?? false)) $message = '✅ DNS 记录已添加';
        else $error = '添加失败：' . ($r['errors'][0]['message'] ?? '未知');
        // 刷新列表
        try { $r2 = CloudflareApi::dnsRecords(); $dnsRecords = $r2['result'] ?? []; } catch (Exception $e) {}
    }
}

// 删除 DNS
if (isset($_GET['del_dns'])) {
    csrf_verify();
    $r = CloudflareApi::deleteDnsRecord($_GET['del_dns']);
    if (($r['success'] ?? false)) $message = '✅ DNS 记录已删除';
    else $error = '删除失败：' . ($r['errors'][0]['message'] ?? '未知');
}

// Zone 概览 / 分析
$zoneInfo = null;
$analytics = null;
$dnsRecords = [];
if (CloudflareApi::configured()) {
    try { $r = CloudflareApi::zoneOverview(); $zoneInfo = $r['result'] ?? null; } catch (Exception $e) {}
    try { $r = CloudflareApi::analytics('-1day'); $analytics = $r['result']['totals'] ?? null; } catch (Exception $e) {}
    try { $r = CloudflareApi::dnsRecords(); $dnsRecords = $r['result'] ?? []; } catch (Exception $e) {}
}

admin_header('Cloudflare');
?>
<div class="admin-layout">
  <?php admin_sidebar('cloudflare'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">☁️ Cloudflare</h1>
      <div class="flex gap-2 ml-auto">
        <a href="?verify=1&csrf_token=<?=csrf_token()?>" class="btn btn-ghost btn-sm">🔌 测试连接</a>
        <a href="?purge=1&csrf_token=<?=csrf_token()?>" class="btn btn-primary btn-sm" onclick="return confirm('清理全站 Cloudflare 缓存？')">🧹 清全站缓存</a>
      </div>
    </div>
    <p class="sub">CDN 缓存管理 · DNS 管理 · 站点性能监控 · 安全等级</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <!-- 配置 -->
    <div class="card" style="margin-bottom:24px">
      <h2>⚙️ 配置</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_config" value="1">
        <div class="field-row">
          <div class="field"><label>API Token <span class="hint">· 推荐（Zone.DNS + Zone.Cache Purge 权限）</span></label><input type="password" name="token" value="<?=htmlspecialchars($cfg['token'])?>" placeholder="Bearer Token"></div>
          <div class="field"><label>Zone ID</label><input type="text" name="zone_id" value="<?=htmlspecialchars($cfg['zone_id'])?>" placeholder="域名对应的 Zone ID"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Zone 域名</label><input type="text" name="zone_name" value="<?=htmlspecialchars($cfg['zone_name'])?>" placeholder="如 nownexts.com"></div>
        </div>
        <div class="field" style="border-top:1px dashed var(--border);padding-top:12px">
          <label style="color:var(--text-3)">或使用 Global API Key（Email + Key）</label>
          <div class="field-row">
            <div class="field"><label>邮箱</label><input type="text" name="email" value="<?=htmlspecialchars($cfg['email'])?>" placeholder="Cloudflare 登录邮箱"></div>
            <div class="field"><label>Global API Key</label><input type="password" name="api_key" value="<?=htmlspecialchars($cfg['api_key'])?>" placeholder="Global API Key"></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">保存配置</button>
      </form>
    </div>

    <!-- 状态 + 分析 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:24px">
      <div class="stat-card"><div class="num" style="color:var(--accent)"><?=$zoneInfo['status'] ?? '—'?></div><div class="label">Zone 状态</div></div>
      <div class="stat-card"><div class="num"><?=$analytics['requests']['all'] ?? 0?></div><div class="label">24h 请求</div></div>
      <div class="stat-card"><div class="num" style="color:var(--ok)"><?=isset($analytics['requests']['cached']) ? round(($analytics['requests']['cached'] ?? 0) / max(1, $analytics['requests']['all'] ?? 1) * 100) . '%' : '—'?></div><div class="label">缓存命中</div></div>
      <div class="stat-card"><div class="num" style="color:var(--danger)"><?=$analytics['requests']['threats'] ?? 0?></div><div class="label">拦截威胁</div></div>
      <div class="stat-card"><div class="num"><?=count($dnsRecords)?></div><div class="label">DNS 记录</div></div>
    </div>

    <!-- DNS 记录 -->
    <div class="card" style="margin-bottom:24px;padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2);display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">🌐 DNS 记录</h2>
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('dnsAdd').style.display='block'">+ 添加</button>
      </div>
      <table>
        <thead><tr><th>类型</th><th>名称</th><th>内容</th><th>代理</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($dnsRecords)): ?><tr><td colspan="5" class="empty">暂无 DNS 记录（未配置或未同步）</td></tr><?php endif; ?>
          <?php foreach ($dnsRecords as $d): ?>
          <tr>
            <td><span class="badge badge-gray"><?=htmlspecialchars($d['type'] ?? '')?></span></td>
            <td class="text-sm"><?=htmlspecialchars($d['name'] ?? '')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($d['content'] ?? '')?></td>
            <td><span class="badge <?=($d['proxied'] ?? false)?'badge-green':'badge-gray'?>"><?=($d['proxied'] ?? false)?'CF代理':'仅DNS'?></span></td>
            <td>
              <a href="?del_dns=<?=htmlspecialchars($d['id'] ?? '')?>&csrf_token=<?=csrf_token()?>" class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="return confirm('删除 DNS 记录？')">删除</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div id="dnsAdd" style="display:none;padding:16px 20px;border-top:1px solid var(--border)">
        <form method="post" style="display:flex;gap:8px;align-items:end">
          <?= csrf_field() ?>
          <input type="hidden" name="add_dns" value="1">
          <div class="field"><label>类型</label><select name="dns_type"><option value="A">A</option><option value="CNAME">CNAME</option><option value="TXT">TXT</option><option value="MX">MX</option></select></div>
          <div class="field"><label>名称</label><input type="text" name="dns_name" placeholder="subdomain 或 @"></div>
          <div class="field"><label>内容</label><input type="text" name="dns_content" placeholder="IP 或目标"></div>
          <button type="submit" class="btn btn-primary">添加</button>
        </form>
      </div>
    </div>

    <!-- 说明 -->
    <div class="card" style="padding:16px">
      <h2 style="margin-bottom:10px">💡 能力说明</h2>
      <table>
        <thead><tr><th>动作</th><th>说明</th></tr></thead>
        <tbody>
          <tr><td><strong>清全站缓存</strong></td><td>内容更新后立即清 CF 缓存，前端即时生效</td></tr>
          <tr><td><strong>DNS 管理</strong></td><td>添加/删除解析记录（A/CNAME/TXT/MX）</td></tr>
          <tr><td><strong>性能监控</strong></td><td>24h 请求量、缓存命中率、威胁拦截数</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
