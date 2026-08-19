<?php
/**
 * 点击热力图 — 页面元素级点击热区
 * 前端 element_click 事件带坐标(x/y/vw/vh/dh)，这里按页面聚合 + iframe 叠加渲染
 */
require_once __DIR__ . '/config.php';
require_login();
require_perm('analytics');

$days = max(1, (int)($_GET['days'] ?? 30));
$cutoff = date('Y-m-d', strtotime("-{$days} days"));
$page = trim($_GET['page'] ?? '');

// 拉取 element_click 事件（含坐标）
$events = [];
try {
    $rows = Database::query("SELECT props, page, created_at FROM events WHERE event='element_click' AND created_at >= ? ORDER BY id DESC LIMIT 5000", [$cutoff]);
    foreach ($rows as $r) {
        $props = json_decode($r['props'] ?? '[]', true);
        if (!is_array($props) || !isset($props['x'])) continue;
        $events[] = ['page' => $r['page'] ?? '', 'props' => $props, 'at' => $r['created_at'] ?? ''];
    }
} catch (Exception $e) {}

// 按页面聚合
$byPage = [];
foreach ($events as $e) {
    $p = $e['page'] !== '' ? $e['page'] : '/';
    $byPage[$p][] = $e;
}
arsort($byPage);

// 当前页点击点
$current = $page !== '' ? ($byPage[$page] ?? []) : [];
$points = array_slice($current, 0, 500);

admin_header('点击热力图');
?>
<div class="admin-layout">
  <?php admin_sidebar('heatmap'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>点击热力图</h1><p class="v-sub">页面元素级点击热区 · 近<?=$days?>天 · 前端自动采集</p></div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <span style="font-size:13px;color:var(--muted)">选择页面：</span>
        <?php foreach (array_slice(array_keys($byPage), 0, 15) as $p): $cnt = count($byPage[$p]); ?>
        <a href="heatmap.php?page=<?=urlencode($p)?>&days=<?=$days?>" style="padding:5px 12px;border-radius:999px;font-size:12px;border:1.5px solid <?=$page===$p?'var(--accent)':'var(--border)'?>;<?=$page===$p?'background:var(--accent);color:var(--on-accent)':''?>"><?=htmlspecialchars($p === '' ? '/' : $p)?> (<?=$cnt?>)</a>
        <?php endforeach; ?>
        <?php if (empty($byPage)): ?><span class="text-sm text-muted">暂无带坐标的点击数据，前端埋点会随访问自动采集。</span><?php endif; ?>
      </div>
    </div>

    <?php if ($page !== ''): ?>
    <div class="card">
      <h2 style="margin-bottom:4px"><?=htmlspecialchars($page)?></h2>
      <p class="sub"><?=count($points)?> 次带坐标点击 · 热区颜色越深点击越多</p>
      <div style="position:relative;border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--bg)">
        <iframe src="<?=htmlspecialchars('/' . ltrim($page, '/'))?>" style="width:100%;height:80vh;border:none;pointer-events:none" onload="this.contentWindow.document.body.style.background='transparent'"></iframe>
        <div style="position:absolute;inset:0;overflow:hidden;pointer-events:none" id="heatLayer"></div>
      </div>
    </div>
    <script>
    var POINTS = <?=json_encode(array_map(fn($e) => $e['props'], $points))?>;
    var layer = document.getElementById('heatLayer');
    POINTS.forEach(function(p) {
      if (p.x === undefined || p.y === undefined) return;
      var el = document.createElement('div');
      var heat = Math.min(1, Math.sqrt((p.click_count || 1) / 5));
      el.style.cssText = 'position:absolute;left:' + p.x + 'px;top:' + p.y + 'px;width:14px;height:14px;border-radius:50%;background:rgba(255,' + Math.round(150*(1-heat)) + ',' + Math.round(60*(1-heat)) + ',' + (0.25 + heat*0.55) + ');transform:translate(-50%,-50%)';
      layer.appendChild(el);
    });
    </script>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
