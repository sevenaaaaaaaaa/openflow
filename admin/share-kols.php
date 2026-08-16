<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ShareTrack.php';

require_login();
if (!has_perm('analytics') && !has_perm('dashboard')) { http_response_code(403); exit('无权限'); }

$kols = share_track_kols(30);
$hotArticles = share_track_hot_articles(30);

admin_header('分享传播');
?>
<div class="admin-layout">
  <?php admin_sidebar('share-kols'); ?>
  <div class="main">
<div class="flex items-center gap-4 mb-4">
  <h1 style="margin-bottom:0">分享传播</h1>
  <div style="margin-left:auto;display:flex;gap:8px"><span class="badge badge-gray"><?=count($kols)?> 贡献者</span></div>
</div>
<p class="sub">查看文章分享传播数据，识别潜在分享贡献者与受欢迎的文章。分享链接带有 ?ref= 追踪参数。</p>

<div class="stats">
  <div class="stat-card"><div class="num"><?=count($kols)?></div><div class="label">分享贡献者</div></div>
  <div class="stat-card"><div class="num"><?=array_sum(array_column($kols, 'visit_count'))?></div><div class="label">分享带来访问</div></div>
  <div class="stat-card"><div class="num"><?=array_sum(array_column($kols, 'conversion_count'))?></div><div class="label">分享带来转化</div></div>
  <div class="stat-card"><div class="num"><?=count($hotArticles)?></div><div class="label">被分享文章</div></div>
</div>

<div class="card">
  <h2>🏆 分享贡献者排行（按带来的访问 / 转化排序）</h2>
  <?php if (empty($kols)): ?>
  <div class="empty">暂无分享数据。分享行为会在用户点击分享按钮后开始追踪。</div>
  <?php else: ?>
  <div style="overflow:auto">
  <table>
    <thead><tr><th>#</th><th>贡献者</th><th>分享次数</th><th>带来访问</th><th>带来转化</th><th>转化率</th></tr></thead>
    <tbody>
      <?php foreach ($kols as $i => $k): ?>
      <tr>
        <td><?=$i+1?></td>
        <td><strong><?=htmlspecialchars($k['name'])?></strong></td>
        <td><?=$k['share_count']?></td>
        <td><?=$k['visit_count']?></td>
        <td><?=$k['conversion_count']?></td>
        <td><span class="tag"><?=$k['convert_rate']?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>🔥 热门文章排行</h2>
  <?php if (empty($hotArticles)): ?>
  <div class="empty">暂无数据</div>
  <?php else: ?>
  <div style="overflow:auto">
  <table>
    <thead><tr><th>#</th><th>文章</th><th>分享次数</th><th>带来访问</th><th>带来转化</th></tr></thead>
    <tbody>
      <?php foreach ($hotArticles as $i => $h): ?>
      <tr>
        <td><?=$i+1?></td>
        <td><a href="/article/<?=htmlspecialchars($h['article_slug'])?>" target="_blank"><?=htmlspecialchars($h['article_slug'])?> ↗</a></td>
        <td><?=$h['share_count']?></td>
        <td><?=$h['visit_count']?></td>
        <td><?=$h['conversion_count']?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
  </div>
</div>

<?php admin_footer(); ?>
