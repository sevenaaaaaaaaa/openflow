<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/RealtimeData.php';
require_once __DIR__ . '/../lib/CdpInsight.php';
require_once __DIR__ . '/../lib/CommerceSystem.php';
require_once __DIR__ . '/../lib/ArticleStats.php';
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
$reviewPending = 0;
try {
    $reviews = json_read(DATA_DIR . '/reviews.json');
    $reviewPending = count(array_filter($reviews, fn($r) => ($r['status'] ?? '') === 'pending'));
} catch (Exception $e) {}

// 待发货订单
$shipPending = 0;
try {
    $orders = json_read(DATA_DIR . '/commerce/orders.json');
    if (is_array($orders)) $shipPending = count(array_filter($orders, fn($o) => ($o['fulfillment'] ?? '') === 'pending'));
} catch (Exception $e) {}

// 内容资产
$artTotal = 0; $artPublished = 0;
try {
    $arts = json_read(ARTICLES_DIR . '/index.json');
    $artTotal = count($arts);
    $artPublished = count(array_filter($arts, fn($a) => ($a['status'] ?? '') === 'published'));
} catch (Exception $e) {}

// CDP 画像数
$cdpCount = 0;
try {
    $profiles = json_read(DATA_DIR . '/cdp/profiles.json');
    $cdpCount = is_array($profiles) ? count($profiles) : 0;
} catch (Exception $e) {}

admin_header('工作台');
?>
<div class="admin-layout">
  <?php admin_sidebar('workspace'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>工作台</h1><p class="v-sub">运营脉搏 · 待办 · 增长引擎。数据实时读取自 openflow.db（快照 <?=htmlspecialchars(date('Y-m-d', time()))?>）。</p></div>
      <div class="v-actions">
        <a href="dashboard.php" class="btn btn-s btn-sm">经营驾驶舱</a>
        <a href="article-edit.php" class="btn btn-p btn-sm">新建内容</a>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="k-label">行为事件</div><div class="k-val mono"><?=(int)($local['events_24h'] ?? 0)?></div><div class="k-sub">24h 采集入库</div></div>
      <div class="kpi"><div class="k-label">活跃访客</div><div class="k-val mono"><?=(int)($local['active_visitors_5min'] ?? 0)?></div><div class="k-sub">5min 实时在线</div></div>
      <div class="kpi"><div class="k-label">新会员</div><div class="k-val mono"><?=(int)($local['new_members_24h'] ?? 0)?></div><div class="k-sub">24h 新增会员</div></div>
      <div class="kpi"><div class="k-label">待办任务</div><div class="k-val mono"><?=$todoCount?></div><div class="k-sub">待处理队列</div></div>
      <div class="kpi"><div class="k-label">待审核</div><div class="k-val mono"><?=$reviewPending?></div><div class="k-sub">内容 / 社区审核</div></div>
      <div class="kpi"><div class="k-label">内容资产</div><div class="k-val mono"><?=$artTotal?></div><div class="k-sub"><?=$artPublished?> 已发布 + <?=$artTotal-$artPublished?> 草稿</div></div>
    </div>

    <div class="panels">
      <div class="panel">
        <div class="p-head"><h3>增长引擎</h3><span class="p-sub mono">自动增长闭环 · 热点→洞察→草稿</span></div>
        <div class="p-body">
          <div class="eng">
            <div class="eng-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20h10"/><path d="M12 20v-6"/><path d="M12 14c0-4 2.5-6 6-6 0 4-2.5 6-6 6Z"/><path d="M12 11c0-3 1.8-5 5-5 0 3-1.8 5-5 5Z"/></svg></div>
            <div><h4>增长引擎 <span class="st st-ok">运行中</span></h4><p class="eng-d"><?=htmlspecialchars($aiInsights['summary'] ?? '自动爬取热点 → AI 洞察 → 生成草稿。')?></p></div>
          </div>
          <div class="param-grid">
            <div class="param"><div class="p-v mono"><?=count($aiInsights['insights'] ?? [])?></div><div class="p-l">洞察产出</div></div>
            <div class="param"><div class="p-v mono"><?=count($aiInsights['anomalies'] ?? [])?></div><div class="p-l">异常发现</div></div>
            <div class="param"><div class="p-v mono"><?=count($aiInsights['actions'] ?? [])?></div><div class="p-l">行动建议</div></div>
            <div class="param"><div class="p-v mono"><?=$reviewPending?></div><div class="p-l">草稿待审</div></div>
            <div class="param"><div class="p-v mono"><?=$comStats['sales'] ?? 0?></div><div class="p-l">转化资产</div></div>
          </div>
          <div class="m-actions" style="margin-top:16px;display:flex;gap:10px">
            <a href="driver.php" class="btn btn-s btn-sm">查看日志</a>
            <a href="driver.php" class="btn btn-p btn-sm">立即运行</a>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="p-head"><h3>待办队列</h3><span class="p-sub mono">一键跳转处理</span></div>
        <div class="p-body">
          <a class="todo-row" href="reviews.php"><span class="t-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/></svg></span><span class="t-b"><span class="t-t">内容审核</span><span class="t-d"><?=$reviewPending?> 篇草稿待审核发布</span></span><span class="st <?=$reviewPending>0?'st-warn':'st-ok'?>"><?=$reviewPending>0?'待处理':'已清空'?></span></a>
          <a class="todo-row" href="commerce.php"><span class="t-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/></svg></span><span class="t-b"><span class="t-t">订单发货</span><span class="t-d"><?=$shipPending?> 笔已支付订单待发货</span></span><span class="st <?=$shipPending>0?'st-warn':'st-ok'?>"><?=$shipPending>0?'待处理':'已清空'?></span></a>
          <a class="todo-row" href="moderation.php"><span class="t-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span><span class="t-b"><span class="t-t">社区审核</span><span class="t-d">评论 / 帖子风控队列</span></span><span class="st st-faint">待检查</span></a>
        </div>
      </div>
    </div>

    <div class="panels p2">
      <div class="panel">
        <div class="p-head"><h3>最近动态</h3><span class="p-sub mono">系统事件流</span></div>
        <div class="p-body"><div class="tl">
          <?php $feed = []; foreach (array_slice($aiInsights['insights'] ?? [], 0, 2) as $i) { $feed[] = ['t'=>$i['title'] ?? '', 'd'=>$i['desc'] ?? '', 'c'=>'accent']; } foreach (array_slice($aiInsights['anomalies'] ?? [], 0, 2) as $a) { $feed[] = ['t'=>'异常：'.($a['title'] ?? ''), 'd'=>$a['desc'] ?? '', 'c'=>'warn']; } if (empty($feed)) { $feed[] = ['t'=>'CDP 采集', 'd'=>'行为事件 '.(int)($local['events_24h']??0).' 条入库 · '.($local['time'] ?? ''), 'c'=>'accent']; } foreach ($feed as $f): ?>
          <div class="tl-item <?=$f['c']?>"><div class="t-time mono"><?=htmlspecialchars(date('m-d H:i', time()))?></div><div class="t-title"><?=htmlspecialchars($f['t'])?></div><div class="t-desc"><?=htmlspecialchars($f['d'])?></div></div>
          <?php endforeach; ?>
          <div class="tl-item"><div class="t-time mono"><?=htmlspecialchars(date('m-d', strtotime('-1 day')))?></div><div class="t-desc" style="color:var(--faint)">增长引擎已自动执行 · 持续覆盖行业热点</div></div>
        </div></div>
      </div>
      <div class="panel">
        <div class="p-head"><h3>AI 运营洞察</h3><span class="p-sub mono"><?=$aiInsights['ai']?'AI 生成':'规则分析'?></span></div>
        <div class="p-body">
          <p style="font-size:13.5px;color:var(--muted);line-height:1.7;margin-bottom:14px"><?=htmlspecialchars($aiInsights['summary'] ?? '')?></p>
          <div class="chips">
            <?php foreach (array_slice($aiInsights['insights'] ?? [], 0, 6) as $i): ?><span class="chip"><?=htmlspecialchars(mb_substr($i['title'] ?? '', 0, 14))?></span><?php endforeach; ?>
            <?php if (empty($aiInsights['insights'])): ?><span class="chip" style="background:var(--hover);color:var(--muted)">暂无洞察</span><?php endif; ?>
          </div>
          <h4 style="font-size:13px;margin:20px 0 2px;font-family:var(--font-display)">待办分布</h4>
          <div class="bar-row"><span>内容审核</span><div class="b-track"><div class="b-fill" style="width:<?=min(100, max(4, $reviewPending*10))?>%"></div></div><span class="b-num"><?=$reviewPending?></span></div>
          <div class="bar-row"><span>待办任务</span><div class="b-track"><div class="b-fill ok" style="width:<?=min(100, max(4, $todoCount*8))?>%"></div></div><span class="b-num"><?=$todoCount?></span></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
