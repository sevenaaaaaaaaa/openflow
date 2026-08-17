<?php
/**
 * 运营主线 — 数据流 / 内容流 / 价值流 三线联动总览
 * 展示每条流从源头到结果的实时数据，并标出各环节的联动状态
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_once __DIR__ . '/../lib/FlowSystem.php';
require_once __DIR__ . '/../lib/MembershipSystem.php';
require_login();
require_perm('settings');

// ─── B. 数据流统计 ───
$events = Database::query("SELECT event, COUNT(*) c FROM events GROUP BY event ORDER BY c DESC");
$eventMap = [];
foreach ($events as $e) $eventMap[$e['event']] = (int)$e['c'];
$totalEvents = array_sum($eventMap);
$identifyCount = 0; // 已识别（member_id 关联）
try { $identifyCount = (int)Database::query("SELECT COUNT(*) c FROM events WHERE member_id != ''")[0]['c']; } catch (Exception $e) {}
$cdpCustomers = (int)Database::query("SELECT COUNT(*) c FROM cdp_customers")[0]['c'];
$cdpKnown = 0;
try { $cdpKnown = (int)Database::query("SELECT COUNT(*) c FROM cdp_customers WHERE member_id != ''")[0]['c']; } catch (Exception $e) {}

// 画像标签分布
$tagCount = [];
try {
    foreach (Database::query("SELECT tags FROM cdp_customers WHERE tags != '' AND tags != '[]'") as $row) {
        foreach (json_decode($row['tags'] ?? '[]', true) ?: [] as $t) $tagCount[$t] = ($tagCount[$t] ?? 0) + 1;
    }
    arsort($tagCount);
} catch (Exception $e) {}

// ─── A. 内容流统计 ───
$articleCount = count(get_articles());
$pubCount = count(array_filter(get_articles(), fn($a) => ($a['status'] ?? '') === 'published'));
$draftCount = $articleCount - $pubCount;
$courseCount = count(json_read(DATA_DIR . '/courses/index.json'));
$downloadCount = count(json_read(DATA_DIR . '/downloads.json'));
$campaignCount = count(json_read(DATA_DIR . '/campaigns.json'));
$commentCount = count(json_read(DATA_DIR . '/comments.json'));
$commentRate = $pubCount ? round($commentCount / $pubCount * 100) : 0;

// ─── C. 价值流统计 ───
$orders = json_read(DATA_DIR . '/shop/orders.json');
$paidOrders = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));
$revenue = array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $paidOrders));
$members = json_read(DATA_DIR . '/members/index.json');
$subActive = count(array_filter($members, function ($m) { try { return sub_is_active($m['id']); } catch (Exception $e) { return false; } }));
$consultations = json_read(DATA_DIR . '/consultation/bookings.json');
$conPaid = count(array_filter($consultations, fn($b) => in_array($b['status'] ?? '', ['paid','confirmed','completed'])));
$leads = get_leads();
$leadCount = count($leads);

// 自动化/画布
$automationCount = count(json_read(DATA_DIR . '/automation.json'));
$canvasCount = count(json_read(DATA_DIR . '/canvas-flows.json'));

// 线索→成交 转化率
$conversionRate = $leadCount ? round(count($paidOrders) / max(1, $leadCount) * 100) : 0;

admin_header('运营主线');
?>
<style>
  .flow-line{display:flex;align-items:stretch;gap:0;margin:10px 0 24px}
  .flow-step{flex:1;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px;position:relative;min-width:0}
  .flow-step+.flow-step{margin-left:10px}
  .flow-step::after{content:'→';position:absolute;right:-12px;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:16px;z-index:2}
  .flow-step:last-child::after{display:none}
  .flow-step .num{font-size:24px;font-weight:800}
  .flow-step .lbl{font-size:12px;color:var(--text-2);margin-top:2px}
  .flow-step .act{font-size:10.5px;color:var(--text-3);margin-top:4px}
  .flow-step .link{font-size:11px;color:#2b5f7e;margin-top:6px;display:inline-block;font-weight:600}
  .flow-title{display:flex;align-items:center;gap:8px;margin:28px 0 4px}
  .flow-title h2{font-size:16px;font-weight:700}
  .flow-title .tag{font-size:11px;padding:2px 8px;border-radius:999px;font-weight:600}
  .flow-hint{font-size:12px;color:var(--text-3);margin-bottom:14px}
</style>
<div class="admin-layout">
  <?php admin_sidebar('flow'); ?>
  <div class="main">
    <h1>🔄 运营主线</h1>
    <p class="sub">一条主线串起所有模块：内容吸引 → 数据识别 → 价值转化 · 各环节自动联动</p>

    <!-- ═══ B. 数据流 ═══ -->
    <div class="flow-title"><h2>📊 数据流</h2><span class="tag" style="background:var(--accent-soft)">匿名 → 识别 → 画像 → 线索</span></div>
    <p class="flow-hint">埋点自动建档、行为自动打标、表单自动入 CRM、登录自动合并</p>
    <div class="flow-line" style="flex-wrap:wrap;gap:10px">
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=number_format($totalEvents)?></div><div class="lbl">行为事件</div><div class="act"><?=count($eventMap)?> 种类型</div></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$cdpCustomers?></div><div class="lbl">CDP 客户</div><div class="act"><?=$cdpKnown?> 已识别</div></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=count($tagCount)?></div><div class="lbl">画像标签</div><div class="act">自动打标</div></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$leadCount?></div><div class="lbl">线索</div><div class="act">表单/下载自动生成</div><a class="link" href="crm.php">去管理 →</a></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$identifyCount?></div><div class="lbl">已识别事件</div><div class="act">登录/注册合并</div></div>
    </div>
    <?php if ($tagCount): ?>
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px">
      <?php foreach (array_slice($tagCount, 0, 10) as $t => $n): ?>
      <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(125,211,252,.15);color:#0369a1"># <?=htmlspecialchars($t)?> <b><?=$n?></b></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ A. 内容流 ═══ -->
    <div class="flow-title"><h2>📝 内容流</h2><span class="tag" style="background:var(--accent-soft)">创作 → 发布 → 分发 → 互动</span></div>
    <p class="flow-hint">知识库 → 文章/课程 → 多渠道分发 → 评论点评回收洞察</p>
    <div class="flow-line" style="flex-wrap:wrap;gap:10px">
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$pubCount?></div><div class="lbl">已发布文章</div><div class="act"><?=$draftCount?> 篇草稿</div><a class="link" href="articles.php">去管理 →</a></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$courseCount?></div><div class="lbl">课程</div><div class="act">含专栏/系列课</div><a class="link" href="courses.php">去管理 →</a></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$downloadCount?></div><div class="lbl">资料</div><div class="act">门禁表单收线索</div></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$campaignCount?></div><div class="lbl">Campaign</div><div class="act">分发触达</div><a class="link" href="campaigns.php">去管理 →</a></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$commentCount?></div><div class="lbl">评论/点评</div><div class="act">互动率 <?=$commentRate?>%</div><a class="link" href="comments.php">去审核 →</a></div>
    </div>

    <!-- ═══ C. 价值流 ═══ -->
    <div class="flow-title"><h2>💰 价值流</h2><span class="tag" style="background:var(--accent-soft)">免费 → 培育 → 付费 → 会员 → 复购</span></div>
    <p class="flow-hint">自动化/画布培育 → 课程/咨询/订阅转化 → 会员权益 → 推荐</p>
    <div class="flow-line" style="flex-wrap:wrap;gap:10px">
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$automationCount + $canvasCount?></div><div class="lbl">自动化流程</div><div class="act"><?=$automationCount?> 自动化 · <?=$canvasCount?> 画布</div><a class="link" href="automation.php">去配置 →</a></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=count($paidOrders)?></div><div class="lbl">付费订单</div><div class="act">转化率 <?=$conversionRate?>%</div></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num" style="color:var(--ok)">¥<?=number_format($revenue, 0)?></div><div class="lbl">累计收入</div><div class="act">课程+订阅</div></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$subActive?></div><div class="lbl">活跃订阅</div><div class="act">会员体系</div><a class="link" href="membership.php">去管理 →</a></div>
      <div class="flow-step" style="flex:1 1 150px"><div class="num"><?=$conPaid?></div><div class="lbl">1v1 咨询</div><div class="act">付费预约</div><a class="link" href="consultation">去管理 →</a></div>
    </div>

    <!-- 联动说明 -->
    <div class="card" style="margin-top:24px;background:linear-gradient(135deg,var(--surface),rgba(221,255,14,.07))">
      <h2 style="font-size:15px">🔗 全站联动（已自动打通）</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;margin-top:12px">
        <div style="padding:10px;background:var(--surface-2);border-radius:10px;font-size:12.5px">🕹️ <b>埋点 → CDP</b><br>行为自动建档+打标+评分</div>
        <div style="padding:10px;background:var(--surface-2);border-radius:10px;font-size:12.5px">📋 <b>表单 → 线索 → CRM</b><br>提交自动生成线索并打分</div>
        <div style="padding:10px;background:var(--surface-2);border-radius:10px;font-size:12.5px">🛒 <b>支付 → 会员价值</b><br>CDP 打标+LTV+积分+站内信</div>
        <div style="padding:10px;background:var(--surface-2);border-radius:10px;font-size:12.5px">🔔 <b>事件 → 通知</b><br>咨询/订单/直播/投稿自动站内信</div>
        <div style="padding:10px;background:var(--surface-2);border-radius:10px;font-size:12.5px">🏆 <b>行为 → 积分</b><br>下载/咨询/社区/购买自动奖励</div>
        <div style="padding:10px;background:var(--surface-2);border-radius:10px;font-size:12.5px">📈 <b>发布 → 收录</b><br>文章发布自动 IndexNow</div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
