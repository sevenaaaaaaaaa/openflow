<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('cdp');

$message = '';
$stats = null;
$result = null;

// 手动触发同步
if (isset($_GET['sync'])) {
    csrf_verify();
    $result = DataConnector::syncAll();
    $message = '数据同步完成';
}

// 身份解析统计
$idStats = IdentityResolver::stats();

// 最近合并的画像
$identity = json_read(DATA_DIR . '/cdp/identity.json');
$profiles = $identity['profile'] ?? [];
arsort($profiles); // 按最近活跃排序
$recentMerged = array_slice($profiles, 0, 20);

admin_header('数据连接器');
?>
<div class="admin-layout">
  <?php admin_sidebar('data-connector'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"> 数据连接器</h1>
      <div class="flex gap-2 ml-auto">
        <a href="?sync=1&csrf_token=<?=csrf_token()?>" class="btn btn-primary">🔄 立即同步全部</a>
      </div>
    </div>
    <p class="sub">把 CRM 线索 · 商城订单 · 课程进度 · 会员资料 自动回填到 CDP 画像 · 跨设备身份合并</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 同步结果 -->
    <?php if ($result): ?>
    <div class="card" style="margin-bottom:24px;padding:20px">
      <h2 style="margin-bottom:12px">📊 本次同步结果</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
        <?php foreach (['crm'=>'CRM 线索','shop'=>'商城订单','courses'=>'课程进度','members'=>'会员资料'] as $k=>$label): ?>
        <div class="stat-card">
          <div class="num" style="color:var(--accent)"><?=$result[$k]['count'] ?? 0?></div>
          <div class="label"><?=$label?></div>
          <?php if (!empty($result[$k]['error'])): ?><div class="text-xs" style="color:var(--danger)"><?=htmlspecialchars($result[$k]['error'])?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- 身份解析统计 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px">
      <div class="stat-card"><div class="num"><?=$idStats['canonical_profiles']?></div><div class="label">统一用户（Canonical）</div></div>
      <div class="stat-card"><div class="num"><?=$idStats['known_identities']?></div><div class="label">已知身份标识</div></div>
      <div class="stat-card"><div class="num"><?=$idStats['with_member']?></div><div class="label">已关联会员</div></div>
      <div class="stat-card"><div class="num"><?=$idStats['merged_events']?></div><div class="label">累计合并次数</div></div>
    </div>

    <!-- 数据源说明 -->
    <div class="card" style="margin-bottom:24px;padding:20px">
      <h2 style="margin-bottom:12px">📦 数据源</h2>
      <table style="width:100%">
        <thead><tr><th>数据源</th><th>来源</th><th>同步内容</th><th>标签</th></tr></thead>
        <tbody>
          <tr><td><strong>CRM 线索</strong></td><td><code>data/crm.json</code></td><td>姓名/电话/公司/阶段/评分</td><td>线索</td></tr>
          <tr><td><strong>商城订单</strong></td><td><code>orders 表</code></td><td>订单数/总消费/首末次购买</td><td>已购 · 高价值(≥1000)</td></tr>
          <tr><td><strong>课程进度</strong></td><td><code>data/courses/progress.json</code></td><td>报名课程数/完成数/最近学习</td><td>课程学习者 · 活跃学员</td></tr>
          <tr><td><strong>会员资料</strong></td><td><code>data/members/</code></td><td>姓名/邮箱/电话/公司/城市/等级</td><td>—</td></tr>
        </tbody>
      </table>
    </div>

    <!-- 最近画像 -->
    <div class="card" style="padding:0;overflow:auto">
      <div style="padding:16px 20px;background:var(--surface-2);border-bottom:1px solid var(--border)">
        <h2 style="margin:0">👥 最近合并的用户画像</h2>
      </div>
      <table style="width:100%">
        <thead><tr><th>Canonical ID</th><th>会员</th><th>邮箱</th><th>标签</th><th>合并数</th><th>最近活跃</th></tr></thead>
        <tbody>
          <?php if (empty($recentMerged)): ?><tr><td colspan="6" class="empty">暂无画像 · 点击右上角「立即同步」拉取数据</td></tr><?php endif; ?>
          <?php foreach ($recentMerged as $cid => $p): ?>
          <tr>
            <td><code style="font-size:11px"><?=htmlspecialchars($cid)?></code></td>
            <td><?=htmlspecialchars($p['member_id'] ?? '—')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($p['email'] ?? '—')?></td>
            <td>
              <?php foreach (array_slice($p['tags'] ?? [], 0, 4) as $t): ?>
              <span class="badge badge-gray" style="font-size:11px"><?=htmlspecialchars($t)?></span>
              <?php endforeach; ?>
            </td>
            <td><span class="badge <?=($p['merge_count']??1)>1?'badge-green':'badge-gray'?>"><?=$p['merge_count']??1?></span></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($p['last_seen'] ?? '')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
