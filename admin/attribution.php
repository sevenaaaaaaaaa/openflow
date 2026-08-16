<?php
/**
 * 增长归因看板 — 合并 UTM / 分享传播 / 二维码 三个来源
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ShareTrack.php';
require_once __DIR__ . '/../lib/QrTrack.php';
require_once __DIR__ . '/../lib/Database.php';

require_login();
if (!has_perm('analytics') && !has_perm('dashboard')) { http_response_code(403); exit('无权限'); }

$tab = $_GET['tab'] ?? 'utm';

// ─── UTM 归因数据 ───
$utmRows = [];
try {
    $utmRows = Database::query(
        "SELECT label, COUNT(*) AS visits FROM events WHERE event = 'utm_landing' GROUP BY label ORDER BY visits DESC LIMIT 20"
    ) ?: [];
} catch (Exception $e) {}

// ─── 分享传播汇总 ───
$kols = share_track_kols(15);
$hotArticles = share_track_hot_articles(15);

// ─── 二维码汇总 ───
$qrStats = qr_track_all();

admin_header('增长归因');
?>
<div class="admin-layout">
  <?php admin_sidebar('attribution'); ?>
  <div class="main">
<h1>增长归因看板</h1>
<p class="sub">统一查看 UTM 渠道归因、分享传播链、二维码扫描三个来源的增长数据</p>

<div class="tabs">
  <a href="?tab=utm" class="<?=$tab==='utm'?'active':''?>">UTM 渠道</a>
  <a href="?tab=share" class="<?=$tab==='share'?'active':''?>">分享传播</a>
  <a href="?tab=qr" class="<?=$tab==='qr'?'active':''?>">二维码扫描</a>
</div>

<?php if ($tab === 'utm'): ?>
<div class="card">
  <h2>📈 UTM 渠道归因</h2>
  <p class="sub" style="margin-bottom:12px">基于全站埋点捕获的 utm_source|utm_medium|utm_campaign 首次落地数据</p>
  <?php if (empty($utmRows)): ?>
  <div class="empty">暂无 UTM 数据。投放带 utm_source 参数的链接后，此处会显示各渠道带来的访问。</div>
  <?php else: ?>
  <div style="overflow:auto">
  <table>
    <thead><tr><th>#</th><th>来源|媒介|活动</th><th>访问数</th></tr></thead>
    <tbody>
      <?php foreach ($utmRows as $i => $r): ?>
      <tr><td><?=$i+1?></td><td><code><?=htmlspecialchars($r['label'] ?? '')?></code></td><td><?=$r['visits']?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'share'): ?>
<div class="card">
  <h2>🔗 分享传播归因</h2>
  <p class="sub" style="margin-bottom:12px">文章分享 → 传播访问 → 转化，识别潜在 KOL</p>
  <div class="stats">
    <div class="stat-card"><div class="num"><?=count($kols)?></div><div class="label">贡献者</div></div>
    <div class="stat-card"><div class="num"><?=array_sum(array_column($kols, 'visit_count'))?></div><div class="label">传播访问</div></div>
    <div class="stat-card"><div class="num"><?=array_sum(array_column($kols, 'conversion_count'))?></div><div class="label">传播转化</div></div>
  </div>
  <h3 style="margin:16px 0 8px;font-size:14px">潜在 KOL</h3>
  <?php if (empty($kols)): ?><div class="empty">暂无分享数据</div>
  <?php else: ?>
  <div style="overflow:auto"><table>
    <thead><tr><th>#</th><th>贡献者</th><th>分享</th><th>访问</th><th>转化</th><th>转化率</th></tr></thead>
    <tbody>
      <?php foreach ($kols as $i => $k): ?>
      <tr><td><?=$i+1?></td><td><strong><?=htmlspecialchars($k['name'])?></strong></td><td><?=$k['share_count']?></td><td><?=$k['visit_count']?></td><td><?=$k['conversion_count']?></td><td><?=$k['convert_rate']?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'qr'): ?>
<div class="card">
  <h2>📱 二维码扫描归因</h2>
  <p class="sub" style="margin-bottom:12px">每个二维码的扫描次数与扫码后注册数</p>
  <?php if (empty($qrStats)): ?>
  <div class="empty">暂无扫码数据。二维码管理页生成的二维码被扫描后即开始统计。</div>
  <?php else: ?>
  <div style="overflow:auto">
  <table>
    <thead><tr><th>二维码</th><th>扫描次数</th><th>扫码注册</th><th>注册率</th><th>最近扫描</th></tr></thead>
    <tbody>
      <?php foreach ($qrStats as $id => $s): ?>
      <tr>
        <td><code><?=htmlspecialchars($id)?></code></td>
        <td><?=$s['scans']?></td>
        <td><?=$s['registrations']?></td>
        <td><?=$s['scans'] > 0 ? round($s['registrations'] / $s['scans'] * 100, 1) . '%' : '—'?></td>
        <td class="text-sm text-muted"><?=htmlspecialchars(substr($s['last'] ?? '', 0, 16))?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

  </div>
</div>

<?php admin_footer(); ?>
