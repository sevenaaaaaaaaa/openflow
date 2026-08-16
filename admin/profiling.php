<?php
/**
 * 用户画像 — 三维度
 *   1) 整体画像（Audience）: 全量客户 · 筛选 · 分群/分组/分层
 *   2) 个人画像（Persona）: 单用户标签/积分/等级/行为画像卡
 *   3) 详细资料（Profile）: 来源渠道 · 首次/末次访问 · 完整行为时间线
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/ProfilingSystem.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_login();
require_perm('settings');

// 手动打标/移除（persona 画像卡）
if (isset($_GET['tag']) && isset($_GET['focus'])) {
    prof_manual_tag($_GET['focus'], $_GET['t'], $_GET['act'] === 'add');
    flash('success', '标签已更新');
    header('Location: /xmp/profiling?tab=persona&focus=' . urlencode($_GET['focus']));
    exit;
}

$tab = $_GET['tab'] ?? 'audience';
$focus = $_GET['focus'] ?? '';
if ($focus && $tab === 'audience') $tab = 'profile';

$members = member_get_all();
$memberIndexById = [];
foreach ($members as $m) $memberIndexById[$m['id']] = $m;
$defs = prof_tag_defs();

// ─── 整体画像数据 ───
$f_identity = $_GET['identity'] ?? 'all';
$f_activity = $_GET['activity'] ?? 'all';
$f_value    = $_GET['value'] ?? 'all';
$f_segment  = $_GET['segment'] ?? '';
$f_tag      = $_GET['tag'] ?? '';
$f_search   = trim($_GET['search'] ?? '');
$audience = prof_audience([
    'identity' => $f_identity, 'activity' => $f_activity, 'value' => $f_value,
    'segment' => $f_segment, 'tag' => $f_tag, 'search' => $f_search,
]);

// ─── 详细资料 ───
$cdpDetail = null; $profile = null;
if ($tab === 'profile' && $focus) {
    try { cdp_ensure_table(); } catch (Exception $e) {}
    $cdpDetail = null;
    try {
        if (strpos($focus, 'm:') === 0 || strpos($focus, 'u:') === 0 || strpos($focus, 'e:') === 0 || strpos($focus, 'p:') === 0) {
            $rows = Database::query("SELECT * FROM cdp_customers WHERE id = ?", [$focus]);
            $cdpDetail = $rows[0] ?? null;
        } else {
            $cdpDetail = cdp_find('', $focus, '');
        }
    } catch (Exception $e) { $cdpDetail = null; }
    if ($cdpDetail) $profile = prof_profile_detail($cdpDetail);
}

// persona 画像卡数据
$tagCounts = [];
$profiles = [];
foreach ($members as $m) {
    $p = prof_build_profile($m);
    $profiles[$m['id']] = $p;
    foreach (array_keys($p['all']) as $t) $tagCounts[$t] = ($tagCounts[$t] ?? 0) + 1;
}
arsort($tagCounts);

$segLabels = ['high_value'=>'💎 高价值','potential'=>'⭐ 潜力','new'=>'🌱 新客','at_risk'=>'⚠️ 流失风险','churned'=>'😴 沉睡'];
$segColors = ['high_value'=>'#eab308','potential'=>'var(--accent)','new'=>'var(--accent)','at_risk'=>'var(--warn)','churned'=>'var(--faint)'];

admin_header('用户画像');
?>
<div class="admin-layout">
  <?php admin_sidebar('profiling'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">👤 用户画像</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <span class="badge badge-gray"><?=$audience['total']?> 全量客户</span>
        <span class="badge badge-gray"><?=$audience['active7']?> 近7天活跃</span>
      </div>
    </div>
    <p class="sub">三维度：整体画像（全量·分群分组分层） / 个人画像（标签·等级·行为） / 详细资料（来源·时间线）</p>

    <div class="tabs">
      <a href="?tab=audience" class="<?=$tab==='audience'?'active':''?>">📊 整体画像</a>
      <a href="?tab=persona" class="<?=$tab==='persona'?'active':''?>">🪪 个人画像</a>
      <?php if ($tab==='profile'): ?><a href="?tab=profile&focus=<?=urlencode($focus)?>" class="active">📋 详细资料</a><?php endif; ?>
    </div>

    <?php if ($tab === 'audience'): ?>
    <!-- 统计卡 -->
    <div class="stats">
      <div class="stat-card"><div class="num"><?=$audience['total']?></div><div class="label">全量客户</div></div>
      <div class="stat-card"><div class="num"><?=$audience['members']?></div><div class="label">注册会员</div></div>
      <div class="stat-card"><div class="num"><?=$audience['anon']?></div><div class="label">匿名访客</div></div>
      <div class="stat-card"><div class="num" style="color:var(--ok)"><?=$audience['active7']?></div><div class="label">近7天活跃</div></div>
      <div class="stat-card"><div class="num"><?=$audience['active30']?></div><div class="label">近30天活跃</div></div>
      <div class="stat-card"><div class="num" style="color:var(--warn)"><?=$audience['paid']?></div><div class="label">有消费</div></div>
      <div class="stat-card"><div class="num" style="color:#eab308"><?=$audience['high']?></div><div class="label">高价值≥¥500</div></div>
    </div>

    <!-- 筛选 -->
    <form method="get" class="card" style="margin-bottom:16px;padding:16px">
      <input type="hidden" name="tab" value="audience">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <select name="identity" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
          <option value="all" <?=$f_identity==='all'?'selected':''?>>全部身份</option>
          <option value="member" <?=$f_identity==='member'?'selected':''?>>仅会员</option>
          <option value="anon" <?=$f_identity==='anon'?'selected':''?>>仅匿名</option>
        </select>
        <select name="activity" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
          <option value="all" <?=$f_activity==='all'?'selected':''?>>全部活跃度</option>
          <option value="7" <?=$f_activity==='7'?'selected':''?>>7天内活跃</option>
          <option value="30" <?=$f_activity==='30'?'selected':''?>>30天内活跃</option>
          <option value="90" <?=$f_activity==='90'?'selected':''?>>90天内活跃</option>
        </select>
        <select name="value" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
          <option value="all" <?=$f_value==='all'?'selected':''?>>全部价值</option>
          <option value="high" <?=$f_value==='high'?'selected':''?>>高价值 ≥¥500</option>
          <option value="paid" <?=$f_value==='paid'?'selected':''?>>有消费</option>
        </select>
        <select name="segment" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
          <option value="">全部 RFM 分层</option>
          <?php foreach ($segLabels as $sk => $sl): ?>
          <option value="<?=$sk?>" <?=$f_segment===$sk?'selected':''?>><?=preg_replace('/^[^ ]+ /','',$sl)?> (<?=$audience['segments'][$sk]??0?>)</option>
          <?php endforeach; ?>
        </select>
        <select name="tag" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
          <option value="">全部标签</option>
          <?php foreach ($tagCounts as $t => $c): $def = $defs[$t] ?? ['name'=>$t]; ?>
          <option value="<?=htmlspecialchars($t)?>" <?=$f_tag===$t?'selected':''?>><?=htmlspecialchars($def['name'])?> (<?=$c?>)</option>
          <?php endforeach; ?>
        </select>
        <input type="search" name="search" value="<?=htmlspecialchars($f_search)?>" placeholder="搜索姓名/邮箱/ID…" style="flex:1;min-width:150px;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px">
        <button type="submit" class="btn btn-primary">筛选</button>
        <?php if ($f_identity!=='all'||$f_activity!=='all'||$f_value!=='all'||$f_segment||$f_tag||$f_search): ?><a href="?tab=audience" class="btn btn-ghost">清除</a><?php endif; ?>
      </div>
    </form>

    <!-- RFM 分层 -->
    <div class="card" style="margin-bottom:16px">
      <h2>🎯 RFM 分层分布</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
        <?php foreach ($segLabels as $sk => $sl): ?>
        <div class="card" style="text-align:center;padding:16px;border-color:<?=$segColors[$sk]?>">
          <div style="font-size:13px"><?=$sl?></div>
          <div style="font-size:24px;font-weight:800;color:<?=$segColors[$sk]?>;margin-top:4px"><?=$audience['segments'][$sk]??0?></div>
          <div class="text-sm text-muted" style="font-size:11px"><?=$audience['total']?round((($audience['segments'][$sk]??0)/max(1,$audience['total']))*100,1):0?>%</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 渠道分组 -->
    <div class="card" style="margin-bottom:16px">
      <h2>📡 来源渠道分组</h2>
      <?php if (empty($audience['channels'])): ?><div class="empty">暂无渠道数据（用户通过带 UTM/推荐参数的链接进入后自动归因）</div>
      <?php else: $maxC = max($audience['channels']) ?: 1; ?>
      <div style="display:grid;gap:8px">
        <?php foreach ($audience['channels'] as $ch => $cnt): ?>
        <div style="display:flex;align-items:center;gap:10px">
          <span style="width:90px;font-size:13px;font-weight:600"><?=htmlspecialchars($ch)?></span>
          <div style="height:20px;border-radius:6px;background:linear-gradient(90deg,#7dd3fc,#38bdf8);width:<?=max(4,round($cnt/$maxC*100))?>%"></div>
          <strong style="font-size:13px"><?=$cnt?></strong>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 分群客户表 -->
    <div class="card" style="padding:0;overflow:auto">
      <h2 style="padding:20px 20px 0">👥 客户分群（<?=count($audience['list'])?>）</h2>
      <table>
        <thead><tr><th>客户</th><th>身份</th><th>来源</th><th>分层</th><th>价值</th><th>标签</th><th>末次活跃</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($audience['list'])): ?><tr><td colspan="8" class="empty">暂无匹配客户</td></tr><?php endif; ?>
          <?php foreach (array_slice($audience['list'], 0, 200) as $c): ?>
          <tr>
            <td><strong><?=htmlspecialchars($c['name'] ?: ($c['email'] ?: substr($c['cdp_id'], 2)))?></strong>
              <?php if ($c['email']): ?><div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars($c['email'])?></div><?php endif; ?>
            </td>
            <td class="text-sm text-muted"><?=$c['is_member']?'<span class="badge badge-green" style="font-size:10px">会员</span>':'<span class="badge badge-gray" style="font-size:10px">匿名</span>'?></td>
            <td><span class="badge badge-gray" style="font-size:10px"><?=htmlspecialchars($c['channel'])?></span></td>
            <td><span class="badge" style="background:<?=$segColors[$c['segment']]??'#6b6580'?>;color:#fff;font-size:10px"><?=preg_replace('/^[^ ]+ /','',$segLabels[$c['segment']]??$c['segment'])?></span></td>
            <td><?=$c['ltv']>0?'<strong style="color:var(--ok)">¥'.number_format($c['ltv'],0).'</strong>':'<span class="text-muted">—</span>'?></td>
            <td>
              <?php foreach (array_slice($c['tags'],0,3) as $t): $def=$defs[$t]??['name'=>$t,'color'=>'#6b6580']; ?><span style="display:inline-flex;padding:1px 7px;border-radius:999px;font-size:10px;color:#fff;background:<?=$def['color']?>;margin:1px"><?=htmlspecialchars($def['name'])?></span><?php endforeach; ?>
            </td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($c['last_seen'],0,16))?></td>
            <td><a href="?tab=profile&focus=<?=urlencode($c['cdp_id'])?>" class="btn btn-ghost btn-sm">📋 资料</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab === 'persona'): ?>
    <!-- 标签筛选 -->
    <div class="flex gap-2 mb-4" style="flex-wrap:wrap;align-items:center">
      <a href="?tab=persona" class="btn btn-sm <?=!$f_tag?'btn-primary':'btn-ghost'?>">全部 (<?=count($members)?>)</a>
      <?php foreach ($tagCounts as $t => $c): $def = $defs[$t] ?? ['name'=>$t,'color'=>'#6b6580','icon'=>'🏷️']; ?>
      <a href="?tab=persona&tag=<?=urlencode($t)?>" class="btn btn-sm <?=$f_tag===$t?'btn-primary':'btn-ghost'?>" style="border-color:<?=$def['color']?>">
        <?=$def['icon']?> <?=htmlspecialchars($def['name'])?> (<?=$c?>)
      </a>
      <?php endforeach; ?>
      <?php if ($f_tag): ?><a href="?tab=persona" class="btn btn-ghost btn-sm">清除筛选</a><?php endif; ?>
    </div>

    <!-- 会员列表 -->
    <div class="card" style="padding:0;overflow:auto">
      <table>
        <thead><tr><th>用户</th><th>标签</th><th>积分</th><th>等级</th><th>最近活跃</th><th>操作</th></tr></thead>
        <tbody>
          <?php if (empty($members)): ?><tr><td colspan="6" class="empty">暂无会员</td></tr><?php endif; ?>
          <?php foreach ($members as $m): $p = $profiles[$m['id']]; if ($f_tag && !isset($p['all'][$f_tag])) continue; ?>
          <tr>
            <td>
              <strong><?=htmlspecialchars($m['name'] ?: '—')?></strong>
              <div class="text-sm text-muted" style="font-size:11px"><?=htmlspecialchars($m['email'] ?? '')?></div>
            </td>
            <td>
              <?php if (empty($p['all'])): ?><span class="text-sm text-muted">—</span>
              <?php else: foreach ($p['all'] as $tk => $td): ?>
              <span style="display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:999px;font-size:11px;color:#fff;background:<?=$td['color']?>;margin:1px"><?=$td['icon']?> <?=htmlspecialchars($td['name'])?></span>
              <?php endforeach; endif; ?>
            </td>
            <td><?=$m['points'] ?? 0?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($m['level'] ?? 'member')?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars(substr($m['last_active'] ?? '', 0, 10))?></td>
            <td style="white-space:nowrap">
              <a href="?tab=persona&focus=<?=urlencode($m['id'])?>" class="btn btn-ghost btn-sm">👁 画像</a>
              <a href="?tab=profile&focus=<?=urlencode($m['id'])?>" class="btn btn-ghost btn-sm">📋 资料</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 画像卡 -->
    <?php if ($focus): $fm = null; foreach ($members as $m) if ($m['id'] === $focus) { $fm = $m; break; }
      if ($fm): $fp = $profiles[$fm['id']]; ?>
    <div class="card" style="margin-top:20px">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#38bdf8,#7dd3fc);display:grid;place-items:center;font-size:26px;font-weight:700"><?=htmlspecialchars(mb_substr($fm['name'] ?: '?', 0, 1))?></div>
        <div style="flex:1">
          <h2 style="margin-bottom:4px"><?=htmlspecialchars($fm['name'] ?: '匿名')?></h2>
          <div class="text-sm text-muted"><?=htmlspecialchars($fm['email'] ?? '')?> · <?=htmlspecialchars($fm['phone'] ?? '')?> · 积分 <?=$fm['points'] ?? 0?></div>
        </div>
        <a href="?tab=persona" class="btn btn-ghost btn-sm">关闭</a>
      </div>

      <h3 style="font-size:14px;margin:20px 0 10px">🏷️ 标签管理</h3>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
        <?php foreach ($defs as $tk => $td): $has = isset($fp['all'][$tk]); ?>
        <a href="?tab=persona&tag=<?=urlencode($tk)?>&t=<?=urlencode($tk)?>&act=<?=$has?'remove':'add'?>&focus=<?=urlencode($fm['id'])?>"
           style="padding:6px 14px;border-radius:999px;font-size:13px;cursor:pointer;background:<?=$has?$td['color']:'#f4f3e9'?>;color:<?=$has?'#fff':'#5b5b52'?>;text-decoration:none">
          <?=$td['icon']?> <?=htmlspecialchars($td['name'])?> <?=$has?'✓':'＋'?>
        </a>
        <?php endforeach; ?>
      </div>

      <h3 style="font-size:14px;margin:16px 0 10px">📊 行为画像</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
        <?php
        $mid = $fm['id'];
        $orders = array_filter(json_read(DATA_DIR . '/shop/orders.json'), fn($o) => ($o['member_id'] ?? '') === $mid && ($o['status'] ?? '') === 'paid');
        $totalSpent = array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $orders));
        $posts = count(array_filter(json_read(DATA_DIR . '/community-posts.json'), fn($p) => ($p['author_id'] ?? '') === $mid));
        $comments = count(array_filter(json_read(DATA_DIR . '/community-comments.json'), fn($c) => ($c['author_id'] ?? '') === $mid));
        ?>
        <div class="card" style="text-align:center;padding:16px"><div style="font-size:22px;font-weight:700">¥<?=number_format($totalSpent,0)?></div><div class="text-sm text-muted">累计消费</div></div>
        <div class="card" style="text-align:center;padding:16px"><div style="font-size:22px;font-weight:700"><?=count($orders)?></div><div class="text-sm text-muted">付费订单</div></div>
        <div class="card" style="text-align:center;padding:16px"><div style="font-size:22px;font-weight:700"><?=$posts?></div><div class="text-sm text-muted">发帖</div></div>
        <div class="card" style="text-align:center;padding:16px"><div style="font-size:22px;font-weight:700"><?=$comments?></div><div class="text-sm text-muted">评论</div></div>
      </div>
    </div>
    <?php endif; endif; ?>

    <?php elseif ($tab === 'profile'): ?>
    <?php if (!$cdpDetail || !$profile): ?>
    <div class="card"><div class="empty" style="padding:40px">未找到该用户（<?=htmlspecialchars($focus)?>）。可尝试从「整体画像」或「个人画像」进入。</div></div>
    <?php else:
      $mid = $cdpDetail['member_id'] ?? '';
      $fm = $mid ? ($memberIndexById[$mid] ?? null) : null;
      $name = $fm['name'] ?? $cdpDetail['primary_email'] ?? substr($cdpDetail['id'], 2);
      $utmProps = $profile['utm'] ? json_decode($profile['utm']['props'] ?? '{}', true) ?: [] : [];
      $ch = prof_channel_of($cdpDetail);
    ?>
    <div class="card">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px">
        <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#38bdf8,#7dd3fc);display:grid;place-items:center;font-size:24px;font-weight:700"><?=htmlspecialchars(mb_substr($name,0,1))?></div>
        <div style="flex:1">
          <h2 style="margin-bottom:4px"><?=htmlspecialchars($name)?></h2>
          <div class="text-sm text-muted"><?=htmlspecialchars($cdpDetail['primary_email'] ?? '')?> · <?=$fm?'会员':'匿名访客'?> · 来源 <?=htmlspecialchars($ch)?></div>
        </div>
        <span class="badge <?=$mid?'badge-green':'badge-gray'?>"><?=$mid?'已注册会员':'匿名客户'?></span>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:18px">
        <div class="card" style="text-align:center;padding:12px"><div style="font-size:16px;font-weight:700"><?=htmlspecialchars(substr($profile['first_seen'],0,10))?></div><div class="text-sm text-muted" style="font-size:11px">首次访问</div></div>
        <div class="card" style="text-align:center;padding:12px"><div style="font-size:16px;font-weight:700"><?=htmlspecialchars(substr($profile['last_seen'],0,10))?></div><div class="text-sm text-muted" style="font-size:11px">末次访问</div></div>
        <div class="card" style="text-align:center;padding:12px"><div style="font-size:16px;font-weight:700"><?=$profile['event_count']?></div><div class="text-sm text-muted" style="font-size:11px">行为事件</div></div>
        <div class="card" style="text-align:center;padding:12px"><div style="font-size:16px;font-weight:700;color:var(--ok)">¥<?=number_format($profile['total_spent'],0)?></div><div class="text-sm text-muted" style="font-size:11px">累计消费</div></div>
        <div class="card" style="text-align:center;padding:12px"><div style="font-size:16px;font-weight:700"><?=$profile['orders']?></div><div class="text-sm text-muted" style="font-size:11px">付费订单</div></div>
        <div class="card" style="text-align:center;padding:12px"><div style="font-size:16px;font-weight:700"><?=$profile['submissions']?></div><div class="text-sm text-muted" style="font-size:11px">表单提交</div></div>
      </div>

      <!-- 来源信息 -->
      <h3 style="font-size:14px;margin:18px 0 10px">📍 来源信息</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;padding:14px;background:var(--surface-2);border-radius:12px;margin-bottom:18px">
        <?php if ($profile['utm']): $parts = array_filter(explode('|', $profile['utm']['label'] ?? '')); ?>
          <div><div class="text-sm text-muted" style="font-size:11px">渠道 Source</div><strong><?=htmlspecialchars($parts[0] ?? '—')?></strong></div>
          <div><div class="text-sm text-muted" style="font-size:11px">媒介 Medium</div><strong><?=htmlspecialchars($parts[1] ?? '—')?></strong></div>
          <div><div class="text-sm text-muted" style="font-size:11px">活动 Campaign</div><strong><?=htmlspecialchars($parts[2] ?? '—')?></strong></div>
          <div><div class="text-sm text-muted" style="font-size:11px">落地时间</div><strong style="font-size:12px"><?=htmlspecialchars(substr($profile['utm']['created_at']??'',0,16))?></strong></div>
        <?php else: ?>
          <div class="text-sm text-muted">未捕获到 UTM 来源（直访或未带追踪参数）</div>
        <?php endif; ?>
      </div>

      <!-- 行为时间线 -->
      <h3 style="font-size:14px;margin:18px 0 10px">🕐 行为时间线（<?=count($profile['timeline'])?>）</h3>
      <div style="max-height:420px;overflow-y:auto;border:1px solid var(--border);border-radius:12px">
        <?php if (empty($profile['timeline'])): ?><div class="empty" style="padding:24px">暂无行为数据</div>
        <?php else: foreach ($profile['timeline'] as $ev): ?>
        <div style="display:flex;gap:10px;padding:9px 14px;border-bottom:1px solid var(--border);font-size:13px;align-items:center">
          <span style="width:16px;text-align:center"><?=prof_event_label($ev['event'])[0]?></span>
          <span class="text-sm text-muted" style="width:120px;flex-shrink:0;font-size:11px"><?=htmlspecialchars(substr($ev['created_at']??'',0,16))?></span>
          <span class="badge badge-gray" style="font-size:10px;flex-shrink:0"><?=htmlspecialchars($ev['event']??'')?></span>
          <span class="text-muted" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($ev['label'] ?: $ev['page'] ?: '')?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
