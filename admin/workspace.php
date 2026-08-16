<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/RealtimeData.php';
require_once __DIR__ . '/../lib/CdpInsight.php';
require_once __DIR__ . '/../lib/CommerceSystem.php';
require_login();

$local = RealtimeData::local();
$aiInsights = CdpInsight::generate(30);
$comStats = CommerceSystem::stats();

// 今日待办
$todoCount = 0;
try {
    $tasks = json_read(DATA_DIR . '/tasks.json');
    $todoCount = count(array_filter($tasks, fn($t) => ($t['status'] ?? '') !== 'done'));
} catch (Exception $e) {}
try {
    $reviews = json_read(DATA_DIR . '/reviews.json');
    $reviewPending = count(array_filter($reviews, fn($r) => ($r['status'] ?? '') === 'pending'));
} catch (Exception $e) { $reviewPending = 0; }

admin_header('工作台');
?>
<div class="admin-layout">
  <?php admin_sidebar('workspace'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">🚀 工作台</h1>
      <div class="flex gap-2 ml-auto">
        <span class="badge badge-gray" id="wsTime">—</span>
        <a href="dashboard.php" class="btn btn-ghost btn-sm">经营驾驶舱</a>
      </div>
    </div>
    <p class="sub">今日概览 · 实时指标 · AI 洞察 · 快捷任务</p>

    <!-- 实时指标 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px">
      <div class="stat-card"><div class="num"><?=$local['events_24h'] ?? 0?></div><div class="label">24h 行为事件</div></div>
      <div class="stat-card"><div class="num" style="color:#16a34a"><?=$local['active_visitors_5min'] ?? 0?></div><div class="label">5min 活跃访客</div></div>
      <div class="stat-card"><div class="num" style="color:var(--accent)"><?=$local['new_members_24h'] ?? 0?></div><div class="label">24h 新会员</div></div>
      <div class="stat-card"><div class="num" style="color:#d97706"><?=$todoCount?></div><div class="label">待办任务</div></div>
      <div class="stat-card"><div class="num" style="color:#dc2626"><?=$reviewPending?></div><div class="label">待审核</div></div>
      <div class="stat-card"><div class="num" style="color:#7c3aed"><?=$comStats['sales'] ?? 0?></div><div class="label">商品销量</div></div>
    </div>

    <!-- 快捷任务 -->
    <div class="card" style="margin-bottom:20px;padding:16px">
      <h2 style="margin-bottom:12px">⚡ 快捷任务</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px">
        <a href="article-edit.php" class="btn btn-ghost" style="justify-content:center">✍️ 写新文章</a>
        <a href="publish.php" class="btn btn-ghost" style="justify-content:center">📤 分发内容</a>
        <a href="crm.php" class="btn btn-ghost" style="justify-content:center">💼 跟进线索</a>
        <a href="realtime.php" class="btn btn-ghost" style="justify-content:center">⚡ 实时数据</a>
        <a href="commerce.php" class="btn btn-ghost" style="justify-content:center">💎 商业中心</a>
        <a href="content-calendar.php" class="btn btn-ghost" style="justify-content:center">🗓 内容日历</a>
      </div>
    </div>

    <!-- AI 洞察 + 商业 -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px" class="ws-grid">
      <div class="card" style="padding:20px;background:linear-gradient(135deg,var(--accent-soft),transparent)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <h2 style="margin:0">🤖 AI 运营洞察</h2>
          <span class="badge badge-gray" style="font-size:11px"><?=$aiInsights['ai']?'AI 生成':'规则分析'?></span>
        </div>
        <div style="font-size:13.5px;line-height:1.7;margin-bottom:10px">📌 <?=htmlspecialchars($aiInsights['summary'] ?? '')?></div>
        <?php foreach (array_slice($aiInsights['insights'] ?? [], 0, 3) as $i): ?>
        <div style="display:flex;gap:8px;padding:7px 10px;background:var(--surface);border-radius:8px;margin-bottom:6px;font-size:13px"><span>💡</span><strong><?=htmlspecialchars($i['title'] ?? '')?></strong></div>
        <?php endforeach; ?>
        <?php foreach (array_slice($aiInsights['anomalies'] ?? [], 0, 2) as $a): ?>
        <div style="display:flex;gap:8px;padding:7px 10px;background:var(--surface);border-radius:8px;margin-bottom:6px;font-size:13px;color:#dc2626"><span>🚨</span><?=htmlspecialchars($a['title'] ?? '')?></div>
        <?php endforeach; ?>
      </div>

      <div class="card" style="padding:20px">
        <h2 style="margin-bottom:12px">💎 商业概览</h2>
        <div style="font-size:13px;color:var(--text-3);line-height:2">
          <div>商品总数：<b><?=$comStats['total'] ?? 0?></b>（上架 <?=$comStats['published'] ?? 0?>）</div>
          <div>Skill：<b><?=$comStats['by_type']['skill'] ?? 0?></b> · 插件：<b><?=$comStats['by_type']['plugin'] ?? 0?></b> · 主题：<b><?=$comStats['by_type']['theme'] ?? 0?></b> · API套餐：<b><?=$comStats['by_type']['api_plan'] ?? 0?></b></div>
          <div>累计销量：<b><?=$comStats['sales'] ?? 0?></b></div>
        </div>
        <a href="commerce.php" class="btn btn-primary btn-sm" style="margin-top:12px">进入商业中心 →</a>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('wsTime').textContent = '🕐 ' + new Date().toLocaleString('zh-CN', {hour12:false});
setInterval(function(){ document.getElementById('wsTime').textContent = '🕐 ' + new Date().toLocaleString('zh-CN', {hour12:false}); }, 60000);
</script>
<?php admin_footer(); ?>
