<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('redirects');

$message = '';
$redirects = get_redirects();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['add'])) {
        $from = trim($_POST['from'] ?? '');
        $to = trim($_POST['to'] ?? '');
        if ($from && $to) {
            add_redirect($from, $to);
            $message = '重定向已添加';
        }
    }
    if (isset($_POST['remove'])) {
        remove_redirect($_POST['remove']);
        $message = '重定向已删除';
    }
    if (isset($_POST['bulk'])) {
        $lines = explode("\n", $_POST['bulk']);
        $count = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) === 2) {
                add_redirect(trim($parts[0]), trim($parts[1]));
                $count++;
            }
        }
        $message = "批量导入 {$count} 条重定向";
    }
    $redirects = get_redirects();
}

// Export .htaccess
if (isset($_GET['export'])) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="redirects.txt"');
    echo "# OpenFlow 301 Redirects\n";
    echo "# Generated: " . date('Y-m-d H:i:s') . "\n\n";
    echo "# Apache .htaccess rules:\n";
    echo "RewriteEngine On\n";
    foreach ($redirects as $r) {
        echo "RewriteRule ^" . preg_quote($r['from'], '/') . "$ " . $r['to'] . " [R=301,L]\n";
    }
    echo "\n# Nginx rules:\n";
    foreach ($redirects as $r) {
        echo "rewrite ^/" . preg_quote($r['from'], '/') . "$ " . $r['to'] . " permanent;\n";
    }
    exit;
}

if (!defined('OF_EMBED')) admin_header('301 重定向');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('redirects'); ?>
  <div class="main">
<?php endif; ?>
    <h1>301 重定向</h1>
    <p class="sub">管理已删除页面的 301 跳转 · 支持批量导入 · 可导出为服务器配置</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="stats">
      <div class="stat-card"><div class="num"><?=count($redirects)?></div><div class="label">重定向规则</div></div>
    </div>

    <div class="card">
      <h2>当前重定向规则</h2>
      <table>
        <thead><tr><th>来源路径</th><th>目标 URL</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($redirects)): ?><tr><td colspan="4" class="empty">暂无重定向规则</td></tr><?php endif; ?>
          <?php foreach ($redirects as $r): ?>
          <tr>
            <td><code>/<?=htmlspecialchars($r['from'])?></code></td>
            <td><?=htmlspecialchars($r['to'])?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($r['created'] ?? '')?></td>
            <td>
              <form method="post" style="display:inline" onsubmit="return confirm('确认删除?')">
                <?= csrf_field() ?>
                <input type="hidden" name="remove" value="<?=htmlspecialchars($r['from'])?>">
                <button type="submit" class="btn btn-danger btn-sm">删除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="flex gap-2 mt-4">
        <a href="<?=of_hub_url(['export'=>1])?>" class="btn btn-ghost btn-sm">导出为服务器规则</a>
      </div>
    </div>

    <div class="card">
      <h2>添加重定向</h2>
      <form method="post" class="flex gap-4 items-end">
        <?= csrf_field() ?>
        <div class="field" style="margin-bottom:0;flex:1"><label>来源路径 <span class="hint">如: old-page.html</span></label>
          <div style="display:flex;align-items:center;gap:4px"><code style="font-size:13px">/</code><input type="text" name="from" required placeholder="old-page.html"></div>
        </div>
        <div class="field" style="margin-bottom:0;flex:1"><label>目标 URL</label><input type="text" name="to" required placeholder="https://... 或 /new-page"></div>
        <button type="submit" name="add" class="btn btn-primary">添加</button>
      </form>
    </div>

    <div class="card">
      <h2>批量导入</h2>
      <p class="text-sm text-muted mb-4">每行一条，格式: <code>/来源路径 目标URL</code>（空格分隔）</p>
      <form method="post">
        <?= csrf_field() ?>
        <textarea name="bulk" rows="6" placeholder="/old-about.html  /about&#10;/old-product.html  https://example.com/product"></textarea>
        <button type="submit" name="bulk" class="btn btn-primary mt-4">批量导入</button>
      </form>
    </div>
<?php if (!defined('OF_EMBED')): ?>
  </div>
</div>
<?php admin_footer(); endif; ?>
