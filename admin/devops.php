<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

$message = '';
$output = '';

// ─── Backup ───
if (isset($_GET['backup'])) {
    $backupDir = DATA_DIR . '/backups';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    $name = 'openflow-backup-' . date('Ymd_His') . '.zip';
    $zipPath = $backupDir . '/' . $name;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
        $root = realpath(__DIR__ . '/..');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($files as $f) {
            if ($f->isDir()) continue;
            $path = $f->getRealPath();
            $rel = substr($path, strlen($root) + 1);
            if (preg_match('#^/(admin|data|uploads|assets|lib|api)/#', '/' . $rel) || preg_match('/\.(php|html|css|js|json|md|png|jpg|jpeg|gif|svg|ico|webp|pdf|zip|csv)$/', $rel)) {
                $zip->addFile($path, $rel);
            }
        }
        $zip->close();
        $size = filesize($zipPath);
        $message = "备份完成：{$name} (" . round($size / 1048576, 2) . " MB)";
    } else {
        $message = '备份失败：无法创建 ZIP 文件';
    }
}

// ─── Download backup ───
if (isset($_GET['dl'])) {
    $f = basename($_GET['dl']);
    $fp = DATA_DIR . '/backups/' . $f;
    if (file_exists($fp)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $f . '"');
        header('Content-Length: ' . filesize($fp));
        readfile($fp);
        exit;
    }
}

// ─── Delete backup ───
if (isset($_POST['delete_backup'])) {
    $f = basename($_POST['delete_backup']);
    $fp = DATA_DIR . '/backups/' . $f;
    if (file_exists($fp)) unlink($fp);
    $message = '已删除备份：' . $f;
    header('Location: devops.php');
    exit;
}

// ─── Clear cache ───
if (isset($_GET['clear_cache'])) {
    $cacheDirs = [
        __DIR__ . '/../cache',
        sys_get_temp_dir() . '/openflow-cache',
    ];
    $cleared = 0;
    foreach ($cacheDirs as $d) {
        if (is_dir($d)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $f) { if ($f->isFile()) { unlink($f->getRealPath()); $cleared++; } }
        }
    }
    $message = "缓存清理完成：清除 {$cleared} 个临时文件";
}

// ─── Draft cleanup ───
if (isset($_GET['clean_drafts'])) {
    $days = max(1, (int)($_GET['days'] ?? 90));
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    $all = get_articles();
    $deleted = 0;
    foreach ($all as $a) {
        if (($a['status'] ?? 'draft') === 'draft' && ($a['updated_at'] ?? '') < $cutoff) {
            delete_article($a['id']);
            $deleted++;
        }
    }
    $message = "草稿清理完成：删除 {$deleted} 篇超过 {$days} 天未更新的草稿";
}

// ─── 404 Check ───
$brokenLinks = [];
if (isset($_GET['check_404'])) {
    $articles = get_articles();
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = "{$protocol}://{$host}";

    // Check all published articles' content for broken internal links
    foreach ($articles as $a) {
        if (($a['status'] ?? 'draft') !== 'published') continue;
        preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $a['content'] ?? '', $matches);
        foreach ($matches[1] ?? [] as $link) {
            if (strpos($link, $host) === false) continue; // only check internal
            $ch = curl_init($link);
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
            curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http >= 400) {
                $brokenLinks[] = ['article' => $a['title'], 'url' => $link, 'http' => $http];
            }
        }
    }
    $message = "404 检查完成：发现 " . count($brokenLinks) . " 个损坏链接";
}

// ─── List backups ───
$backupDir = DATA_DIR . '/backups';
$backups = is_dir($backupDir) ? array_filter(glob($backupDir . '/*'), 'is_file') : [];
usort($backups, fn($a, $b) => filemtime($b) - filemtime($a));

admin_header('运维工具');
?>
<div class="admin-layout">
  <?php admin_sidebar('devops'); ?>
  <div class="main">
    <h1>运维工具</h1>
    <p class="sub">全站备份 · 缓存清理 · 草稿清理 · 404 检查 · 导出</p>

    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="stats" style="grid-template-columns:repeat(4,1fr)">
      <div class="stat-card"><div class="num"><?=count($backups)?></div><div class="label">备份文件</div></div>
      <div class="stat-card"><div class="num"><?=count(get_articles())?></div><div class="label">总文章数</div></div>
      <div class="stat-card"><div class="num"><?=count(array_filter(get_articles(), fn($a)=>($a['status']??'')==='draft'))?></div><div class="label">草稿数</div></div>
      <div class="stat-card"><div class="num"><?=count($brokenLinks)?></div><div class="label">损坏链接</div></div>
    </div>

    <!-- Backup -->
    <div class="card">
      <h2>📦 全站备份</h2>
      <p class="text-sm text-muted mb-4">打包核心目录（admin/data/uploads/assets/lib/api）为 ZIP</p>
      <div class="flex gap-2">
        <a href="?backup=1" class="btn btn-primary" onclick="return confirm('开始备份？可能需要几秒钟')">创建备份</a>
      </div>
      <?php if (!empty($backups)): ?>
      <table style="margin-top:12px">
        <thead><tr><th>文件名</th><th>大小</th><th>时间</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($backups as $bp): $bn = basename($bp); ?>
          <tr>
            <td><code><?=htmlspecialchars($bn)?></code></td>
            <td><?=round(filesize($bp)/1048576,2)?> MB</td>
            <td class="text-sm text-muted"><?=date('Y-m-d H:i', filemtime($bp))?></td>
            <td><a href="?dl=<?=urlencode($bn)?>" class="btn btn-ghost btn-sm">下载</a>
              <a href="?delete_backup=<?=urlencode($bn)?>" class="btn btn-danger btn-sm" onclick="return confirm('删除?')">删除</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- Cache & Cleanup -->
    <div class="card">
      <h2>🧹 缓存与清理</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
        <div style="padding:16px;background:var(--surface-2);border-radius:12px">
          <strong>🗑 清理缓存</strong>
          <p class="text-sm text-muted">清除临时文件和缓存</p>
          <a href="?clear_cache=1" class="btn btn-ghost btn-sm" style="margin-top:8px">执行清理</a>
        </div>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px">
          <strong>📄 清理草稿</strong>
          <p class="text-sm text-muted">删除超过 N 天未更新的草稿</p>
          <div class="flex gap-2" style="margin-top:8px">
            <input type="number" id="draftDays" value="90" style="width:60px;padding:4px 8px;border:1px solid var(--border);border-radius:4px">
            <a href="?clean_drafts=1&days=90" class="btn btn-ghost btn-sm" onclick="this.href='?clean_drafts=1&days='+document.getElementById('draftDays').value">执行</a>
          </div>
        </div>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px">
          <strong>🔍 404 检查</strong>
          <p class="text-sm text-muted">扫描文章中的损坏内部链接</p>
          <a href="?check_404=1" class="btn btn-ghost btn-sm" style="margin-top:8px">开始检查</a>
        </div>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px">
          <strong>📊 导出全部数据</strong>
          <p class="text-sm text-muted">导出所有内容为 JSON</p>
          <a href="export-all.php" class="btn btn-ghost btn-sm" style="margin-top:8px">导出</a>
        </div>
      </div>
    </div>

    <!-- 404 Results -->
    <?php if (!empty($brokenLinks)): ?>
    <div class="card">
      <h2>🔗 损坏链接 (<?=count($brokenLinks)?>)</h2>
      <table>
        <thead><tr><th>来源文章</th><th>损坏 URL</th><th>HTTP</th></tr></thead>
        <tbody>
          <?php foreach ($brokenLinks as $bl): ?>
          <tr><td><?=htmlspecialchars($bl['article'])?></td><td><code><?=htmlspecialchars($bl['url'])?></code></td><td><span style="color:var(--danger)"><?=$bl['http']?></span></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Env Config -->
    <div class="card">
      <h2>⚙️ 环境配置</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px">
        <div style="padding:16px;background:var(--surface-2);border-radius:12px">
          <strong>🧪 测试环境</strong>
          <p class="text-sm text-muted">启用后后台顶部显示测试环境标识</p>
          <a href="settings.php" class="btn btn-ghost btn-sm" style="margin-top:8px">前往设置</a>
        </div>
        <div style="padding:16px;background:var(--surface-2);border-radius:12px">
          <strong>🌐 多语言</strong>
          <p class="text-sm text-muted">配置站点多语言支持</p>
          <a href="settings.php" class="btn btn-ghost btn-sm" style="margin-top:8px">前往设置</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
