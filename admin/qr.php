<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/QrTrack.php';
require_login();
require_perm('media');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host;

$articles = get_articles();
$events = json_read(DATA_DIR . '/events/index.json');
$downloads = json_read(DATA_DIR . '/downloads.json');
$landingPages = get_landing_pages();

// 生成带追踪的二维码 URL
function tracked_qr_url(string $qrId, string $target): string {
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $base . '/qr.php?t=' . urlencode($qrId) . '&url=' . urlencode($target);
}

// 所有二维码的扫描统计
$qrStats = qr_track_all();

$pageUrls = [
    'index' => ['name' => '首页', 'url' => $baseUrl . '/'],
    'about' => ['name' => '关于我们', 'url' => $baseUrl . '/about.html'],
    'capability' => ['name' => '产品', 'url' => $baseUrl . '/capability.html'],
    'courses' => ['name' => '解决方案', 'url' => $baseUrl . '/courses.html'],
    'flow-community' => ['name' => 'Flow社区', 'url' => $baseUrl . '/flow-community.html'],
];

$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';

admin_header('二维码管理');
?>
<div class="admin-layout">
  <?php admin_sidebar('qr'); ?>
  <div class="main">
    <h1>二维码管理</h1>
    <p class="sub">二维码默认走本站追踪链接，可统计扫描次数与扫码后注册数</p>

    <div class="card">
      <h2>📊 扫码统计</h2>
      <?php if (empty($qrStats)): ?>
      <div class="empty">暂无扫码数据。二维码生成后，用户扫码即开始计数。</div>
      <?php else: ?>
      <div style="overflow:auto">
      <table>
        <thead><tr><th>二维码 ID</th><th>扫描次数</th><th>扫码注册</th><th>最近扫描</th></tr></thead>
        <tbody>
          <?php foreach ($qrStats as $id => $s): ?>
          <tr><td><code><?=htmlspecialchars($id)?></code></td><td><?=$s['scans']?></td><td><?=$s['registrations']?></td><td class="text-sm text-muted"><?=htmlspecialchars(substr($s['last'] ?? '', 0, 16))?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="flex gap-3 mb-4" style="flex-wrap:wrap;align-items:center">
      <div class="field" style="margin-bottom:0;flex:1;max-width:400px">
        <input type="text" placeholder="搜索标题…" id="qrSearch" oninput="filterQRTables(this.value)" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;width:100%">
      </div>
      <div class="field" style="margin-bottom:0">
        <select onchange="filterQRType(this.value)" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px">
          <option value="">全部类型</option>
          <option value="page">页面</option>
          <option value="article">文章</option>
          <option value="event">活动</option>
          <option value="download">资料</option>
          <option value="landing">聚合页</option>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;flex:1;max-width:400px">
        <label class="text-sm text-muted">或自定义 URL 生成二维码</label>
        <div style="display:flex;gap:8px;margin-top:4px">
          <input type="url" id="customUrl" placeholder="https://..." style="flex:1;padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px">
          <button class="btn btn-primary btn-sm" onclick="generateCustomQR()">生成</button>
        </div>
      </div>
    </div>

    <!-- Page QR Codes -->
    <div class="card qr-group" data-type="page">
      <h2>📄 页面二维码</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-top:12px">
        <?php foreach ($pageUrls as $pk => $pv): $qrData = tracked_qr_url('qr_' . $pk, $pv['url']); $st = $qrStats['qr_' . $pk] ?? ['scans'=>0,'registrations'=>0]; ?>
        <div style="text-align:center;padding:12px;background:var(--surface-2);border-radius:12px">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?=urlencode($qrData)?>" alt="QR" style="width:120px;height:120px;border-radius:8px;cursor:pointer" onclick="downloadQR('<?=urlencode($qrData)?>','<?=htmlspecialchars($pk)?>')" title="点击下载">
          <div style="font-size:12px;font-weight:600;margin-top:6px"><?=htmlspecialchars($pv['name'])?></div>
          <div style="font-size:11px;color:var(--text-3);margin-top:2px">扫描 <?=$st['scans']?> · 注册 <?=$st['registrations']?></div>
          <button class="btn btn-ghost btn-sm" style="margin-top:4px" onclick="downloadQR('<?=urlencode($qrData)?>','<?=htmlspecialchars($pk)?>')">📥 下载</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Article QR Codes -->
    <div class="card qr-group" data-type="article">
      <h2>📝 文章二维码</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-top:12px">
        <?php foreach (array_slice($articles, 0, 20) as $a): $url = $baseUrl . '/article/' . ($a['slug'] ?? $a['id']); $qrData = tracked_qr_url('qr_art_' . ($a['slug'] ?? $a['id']), $url); $st = $qrStats['qr_art_' . ($a['slug'] ?? $a['id'])] ?? ['scans'=>0,'registrations'=>0]; ?>
        <div style="text-align:center;padding:12px;background:var(--surface-2);border-radius:12px">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?=urlencode($qrData)?>" alt="QR" style="width:120px;height:120px;border-radius:8px;cursor:pointer" onclick="downloadQR('<?=urlencode($qrData)?>','<?=htmlspecialchars($a['slug']?:$a['id'])?>')" title="点击下载">
          <div style="font-size:11px;font-weight:600;margin-top:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($a['title'])?>"><?=htmlspecialchars(mb_substr($a['title'],0,20))?></div>
          <div style="font-size:10px;color:var(--text-3)">扫描 <?=$st['scans']?></div>
          <button class="btn btn-ghost btn-sm" style="margin-top:4px" onclick="downloadQR('<?=urlencode($qrData)?>','<?=htmlspecialchars($a['slug']?:$a['id'])?>')">📥 下载</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Event QR Codes -->
    <div class="card qr-group" data-type="event">
      <h2>🎪 活动二维码</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-top:12px">
        <?php foreach (array_slice($events, 0, 10) as $e): if (($e['status']??'') !== 'published') continue; $url = $baseUrl . '/event/' . $e['slug']; ?>
        <div style="text-align:center;padding:12px;background:var(--surface-2);border-radius:12px">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?=urlencode($url)?>" alt="QR" style="width:120px;height:120px;border-radius:8px;cursor:pointer" onclick="downloadQR('<?=urlencode($url)?>','<?=htmlspecialchars($e['slug'])?>')" title="点击下载">
          <div style="font-size:11px;font-weight:600;margin-top:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(mb_substr($e['title'],0,20))?></div>
          <button class="btn btn-ghost btn-sm" style="margin-top:4px" onclick="downloadQR('<?=urlencode($url)?>','<?=htmlspecialchars($e['slug'])?>')">📥 下载</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Download QR Codes -->
    <div class="card qr-group" data-type="download">
      <h2>📥 资料二维码</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-top:12px">
        <?php foreach (array_slice($downloads, 0, 10) as $d): $url = $baseUrl . '/download/' . ($d['slug'] ?? $d['id']); ?>
        <div style="text-align:center;padding:12px;background:var(--surface-2);border-radius:12px">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?=urlencode($url)?>" alt="QR" style="width:120px;height:120px;border-radius:8px;cursor:pointer" onclick="downloadQR('<?=urlencode($url)?>','<?=htmlspecialchars($d['slug']?:$d['id'])?>')" title="点击下载">
          <div style="font-size:11px;font-weight:600;margin-top:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(mb_substr($d['title'],0,20))?></div>
          <button class="btn btn-ghost btn-sm" style="margin-top:4px" onclick="downloadQR('<?=urlencode($url)?>','<?=htmlspecialchars($d['slug']?:$d['id'])?>')">📥 下载</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Landing Page QR Codes -->
    <div class="card qr-group" data-type="landing">
      <h2>📋 聚合页二维码</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-top:12px">
        <?php foreach ($landingPages as $lp): $url = $baseUrl . '/lp/' . $lp['slug']; ?>
        <div style="text-align:center;padding:12px;background:var(--surface-2);border-radius:12px">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?=urlencode($url)?>" alt="QR" style="width:120px;height:120px;border-radius:8px;cursor:pointer" onclick="downloadQR('<?=urlencode($url)?>','<?=htmlspecialchars($lp['slug'])?>')" title="点击下载">
          <div style="font-size:11px;font-weight:600;margin-top:6px"><?=htmlspecialchars(mb_substr($lp['title'],0,20))?></div>
          <button class="btn btn-ghost btn-sm" style="margin-top:4px" onclick="downloadQR('<?=urlencode($url)?>','<?=htmlspecialchars($lp['slug'])?>')">📥 下载</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
function filterQRTables(val) {
  var q = val.toLowerCase();
  document.querySelectorAll('.qr-group').forEach(function(g) {
    g.querySelectorAll('[style*="text-align:center"]').forEach(function(item) {
      var title = item.querySelector('div:nth-child(2)')?.textContent?.toLowerCase() || '';
      item.style.display = title.includes(q) || !q ? '' : 'none';
    });
  });
}
function filterQRType(type) {
  document.querySelectorAll('.qr-group').forEach(function(g) {
    g.style.display = !type || g.dataset.type === type ? '' : 'none';
  });
}
function downloadQR(data, name) {
  var url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(decodeURIComponent(data));
  var a = document.createElement('a');
  a.href = url;
  a.download = 'qr-' + name + '-' + Date.now() + '.png';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}
function generateCustomQR() {
  var url = document.getElementById('customUrl').value.trim();
  if (!url) { alert('请输入 URL'); return; }
  if (!url.match(/^https?:\/\//)) { alert('URL 需以 http:// 或 https:// 开头'); return; }
  var qrData = '/qr.php?t=qr_custom_' + Date.now() + '&url=' + encodeURIComponent(url);
  window.open('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(location.origin + qrData), '_blank');
}
</script>
<?php admin_footer(); ?>
