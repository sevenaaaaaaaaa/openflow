<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_once __DIR__ . '/../lib/Cache.php';
require_login();
require_perm('leads');

$tab = $_GET['tab'] ?? 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'create_segment') {
        // P0：创建分群支持深规则（type: property/event/summary/lifecycle/tag/last_seen + AND/OR）
        $seg = ['name' => $_POST['name'] ?? '', 'description' => $_POST['description'] ?? '', 'rules' => []];
        $rules = $_POST['rule_type'] ?? [];
        foreach ($rules as $i => $rt) {
            if (trim((string)$rt) === '') continue;
            $rule = ['type' => trim((string)$rt), 'field' => $_POST['rule_field'][$i] ?? '',
                     'operator' => $_POST['rule_op'][$i] ?? 'equals', 'value' => $_POST['rule_value'][$i] ?? ''];
            if ($rt === 'event') {
                // 事件规则：event 名用「事件名:值」或单独 event 字段；简单起见 field 存事件名
                $rule['event'] = trim((string)($_POST['rule_event'][$i] ?? ($_POST['rule_field'][$i] ?? '')));
                $rule['window'] = (int)($_POST['rule_window'][$i] ?? 0);
                $rule['value'] = (int)($_POST['rule_value'][$i] ?? 1);
                if ($rule['event'] === '') continue;
            }
            if ($rt === 'last_seen' || $rt === 'first_seen') $rule['value'] = (int)($_POST['rule_value'][$i] ?? 0);
            if (trim((string)($rule['value'] ?? '')) === '' && $rt !== 'tag') continue;
            $seg['rules'][] = $rule;
        }
        $seg['operator'] = ($_POST['rule_operator_top'] ?? 'and') === 'or' ? 'or' : 'and';
        $seg['id'] = 'seg_' . bin2hex(random_bytes(6));
        $seg['created_at'] = $seg['updated_at'] = date('Y-m-d H:i:s');
        CdpSystem::createSegment($seg);
        header('Location: /xmp/cdp?tab=segments');
        exit;
    }
}

$profiles = CdpSystem::allProfiles();
$events = array_reverse(CdpSystem::allEvents());
$totalVisitors = count($profiles);
$totalEvents = count($events);
$todayEvents = count(array_filter($events, fn($e) => substr($e['timestamp'], 0, 10) === date('Y-m-d')));
$avgEventsPerUser = $totalVisitors > 0 ? round($totalEvents / $totalVisitors, 1) : 0;

admin_header('CDP 客户数据中台');
?>
<style>
.cdp-tabs{display:flex;gap:4px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:0;overflow-x:auto;flex-wrap:nowrap;scrollbar-width:none;max-width:100%}
.cdp-tabs a{padding:10px 16px;font-size:13px;font-weight:600;color:var(--muted);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .15s;white-space:nowrap}
.cdp-tabs a:hover{color:var(--fg)}
.cdp-tabs a.active{color:var(--accent);border-bottom-color:var(--accent)}
@media(max-width:840px){.cdp-g{grid-template-columns:1fr!important}.main{overflow-x:auto}.card{overflow-x:auto}}
@media(min-width:841px) and (max-width:1180px){.cdp-g{grid-template-columns:1fr 1fr!important}}
.cdp-av{width:34px;height:34px;border-radius:50%;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-weight:800;font-size:12px;flex:0 0 auto}
.cdp-health{font-family:var(--font-mono);font-weight:800;font-size:13px;padding:2px 8px;border-radius:999px;background:var(--hover)}
.cdp-health.ok{color:var(--ok)}.cdp-health.warn{color:var(--warn)}.cdp-health.danger{color:var(--danger)}
.inline-tag{display:inline-flex;align-items:center;padding:1px 8px;border-radius:999px;background:var(--hover);font-size:11.5px;font-weight:500;color:var(--muted)}
.inline-tag.more{background:none;border:1px dashed var(--border-strong)}
.cdp-stat{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center}
.cdp-stat .num{font-size:28px;font-weight:800}
.cdp-stat .lab{font-size:12px;color:var(--muted);margin-top:4px}
.profile-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;transition:.15s}
.profile-card:hover{border-color:var(--accent)}
.segment-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px}
.event-row{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px}
.event-row:last-child{border:none}
.event-name{font-weight:600;min-width:120px}
.event-time{color:var(--muted);min-width:140px}
.event-props{color:var(--muted);flex:1}
.funnel-step{text-align:center;padding:16px;background:var(--surface);border:1px solid var(--border);border-radius:8px}
.funnel-step .count{font-size:24px;font-weight:800;color:var(--accent)}
.funnel-step .rate{font-size:12px;color:var(--muted)}
.chart-bar{display:flex;align-items:flex-end;gap:2px;height:120px;padding:16px 0}
.chart-bar .bar{flex:1;background:linear-gradient(180deg,var(--accent),var(--accent-soft));border-radius:3px 3px 0 0;min-height:2px;transition:height .3s}
.heatmap-grid{display:grid;grid-template-columns:repeat(24,1fr);gap:2px}
.heatmap-cell{aspect-ratio:1;border-radius:3px;display:grid;place-items:center;font-size:9px;color:var(--muted)}
.rfm-segment{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:99px;font-size:12px;font-weight:600}
.rfm-vip{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#000}
.rfm-loyal{background:var(--ok-soft);color:var(--ok)}
.rfm-active{background:var(--accent-soft);color:var(--accent)}
.rfm-risk{background:var(--warn-soft);color:var(--warn)}
.rfm-churned{background:var(--danger-soft);color:var(--danger)}
.rfm-new{background:var(--hover);color:var(--muted)}
.retention-cell{padding:6px;text-align:center;font-size:11px;border-radius:4px}
.health-bar{height:6px;border-radius:3px;background:var(--border);overflow:hidden}
.health-bar .fill{height:100%;border-radius:3px;transition:width .3s}
.path-item{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px}
.path-rank{font-family:var(--font-mono);font-size:11px;color:var(--faint);min-width:24px}
.path-bar{flex:1;height:20px;border-radius:4px;background:var(--hover);overflow:hidden}
.path-bar .fill{height:100%;border-radius:4px;background:var(--grad)}
.revenue-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px}
.revenue-card .big-num{font-size:36px;font-weight:800;background:var(--grad);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.ltv-tier{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)}
.ltv-tier:last-child{border:none}
.ltv-label{min-width:80px;font-weight:600;font-size:13px}
.ltv-bar{flex:1;height:24px;border-radius:6px;background:var(--hover);overflow:hidden}
.ltv-bar .fill{height:100%;border-radius:6px;background:var(--grad);display:flex;align-items:center;padding:0 8px;font-size:11px;color:var(--on-accent);font-weight:600}
.redis-status{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:99px;font-size:12px;font-weight:600}
.redis-ok{background:var(--ok-soft);color:var(--ok)}
.redis-fail{background:var(--danger-soft);color:var(--danger)}
</style>

<div class="admin-layout">
  <?php admin_sidebar('cdp'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0">CDP 数据中台</h1>
      <?php $redis = Cache::testRedis(); ?>
      <span class="redis-status <?=$redis['ok'] ? 'redis-ok' : 'redis-fail'?>">
        <?= $redis['ok'] ? 'Redis ' . ($redis['version'] ?? '') . ' OK' : 'Redis 未连接' ?>
      </span>
    </div>
    <p class="sub">行为追踪 · 用户画像 · 留存分析 · RFM分层 · 路径分析 · 营收分析</p>

    <div class="cdp-tabs">
      <a href="cdp.php?tab=overview" class="<?=$tab==='overview'?'active':''?>">数据概览</a>
      <a href="cdp.php?tab=behavior" class="<?=$tab==='behavior'?'active':''?>">行为分析</a>
      <a href="cdp.php?tab=profiles" class="<?=$tab==='profiles'?'active':''?>">用户画像</a>
      <a href="cdp.php?tab=retention" class="<?=$tab==='retention'?'active':''?>">留存分析</a>
      <a href="cdp.php?tab=rfm" class="<?=$tab==='rfm'?'active':''?>">RFM分析</a>
      <a href="cdp.php?tab=path" class="<?=$tab==='path'?'active':''?>">路径分析</a>
      <a href="cdp.php?tab=revenue" class="<?=$tab==='revenue'?'active':''?>">营收分析</a>
      <a href="cdp.php?tab=segments" class="<?=$tab==='segments'?'active':''?>">分群管理</a>
      <a href="cdp.php?tab=events" class="<?=$tab==='events'?'active':''?>">事件流</a>
      <a href="cdp.php?tab=funnel" class="<?=$tab==='funnel'?'active':''?>">漏斗分析</a>
      <a href="cdp.php?tab=dimension" class="<?=$tab==='dimension'?'active':''?>">维度分析</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
      <div class="cdp-stat"><div class="num" style="color:var(--accent)"><?=$totalVisitors?></div><div class="lab">总用户数</div></div>
      <div class="cdp-stat"><div class="num" style="color:var(--ok)"><?=$totalEvents?></div><div class="lab">总事件数</div></div>
      <div class="cdp-stat"><div class="num" style="color:var(--accent)"><?=$todayEvents?></div><div class="lab">今日事件</div></div>
      <div class="cdp-stat"><div class="num" style="color:var(--warn)"><?=$avgEventsPerUser?></div><div class="lab">人均事件</div></div>
    </div>

    <?php if ($tab === 'overview'): ?>
    <?php
    $growth = CdpSystem::getUserGrowth('day', 14);
    $sources = CdpSystem::getSourceDistribution();
    $devices = CdpSystem::getDeviceDistribution();
    $lifecycle = CdpSystem::getLifecycleDistribution();
    $pages = CdpSystem::getPageViews(10);
    ?>

    <!-- AI 运营洞察 -->
    <div class="card" style="margin-bottom:20px;padding:20px;background:linear-gradient(135deg,var(--accent-soft),transparent)">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2 style="margin:0">🤖 AI 运营洞察</h2>
        <div style="display:flex;gap:8px;align-items:center">
          <span id="aiInsightBadge" class="badge badge-gray" style="font-size:11px"><?=AiCenter::isConfigured()?'AI 生成':'规则分析'?></span>
          <button type="button" class="btn btn-ghost btn-sm" onclick="loadAiInsights(true)">🔄 重新生成</button>
        </div>
      </div>
      <div id="aiInsightBox">
        <div class="text-sm text-muted" style="padding:20px;text-align:center">加载洞察中…</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
      <div class="card">
        <h2>用户增长趋势（近14天）</h2>
        <div class="chart-bar" style="height:140px">
          <?php
          $maxGrowth = max(array_map(fn($g) => $g['new_users'], $growth)) ?: 1;
          foreach (array_reverse($growth) as $date => $data):
              $h = max(2, round($data['new_users'] / $maxGrowth * 120));
          ?>
          <div class="bar" style="height:<?=$h?>px" title="<?=$date?>: +<?=$data['new_users']?> 新用户"></div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted);padding:0 2px">
          <span><?=date('m/d', strtotime('-13 days'))?></span><span>今天</span>
        </div>
      </div>
      <div class="card">
        <h2>用户来源分布</h2>
        <?php $maxSource = !empty($sources) ? max($sources) : 1; foreach (array_slice($sources, 0, 8, true) as $src => $count): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:80px;font-size:12px;font-weight:600"><?=htmlspecialchars($src)?></span>
          <div style="flex:1;height:16px;border-radius:4px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=round($count/$maxSource*100)?>%;background:var(--grad);border-radius:4px"></div>
          </div>
          <span style="font-size:12px;color:var(--accent);min-width:40px;text-align:right"><?=$count?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="cdp-g" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px">
      <div class="card">
        <h2>设备分布</h2>
        <?php $maxDevice = max($devices) ?: 1; foreach ($devices as $device => $count): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:8px 0">
          <span style="min-width:60px;font-size:13px;font-weight:600"><?=['desktop'=>'电脑','mobile'=>'手机','tablet'=>'平板'][$device]?></span>
          <div style="flex:1;height:20px;border-radius:6px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=round($count/$maxDevice*100)?>%;background:var(--grad);border-radius:6px;display:flex;align-items:center;padding:0 8px;font-size:11px;color:var(--on-accent);font-weight:600"><?=$count?></div>
          </div>
          <span style="font-size:12px;color:var(--muted)"><?=round($count/max(array_sum($devices),1)*100,1)?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card">
        <h2>用户生命周期</h2>
        <?php
        $lcColors = ['new'=>'#3b82f6','active'=>'var(--ok)','dormant'=>'var(--warn)','churned'=>'var(--danger)'];
        $lcLabels = ['new'=>'新用户','active'=>'活跃用户','dormant'=>'沉睡用户','churned'=>'流失用户'];
        $totalLC = max(array_sum($lifecycle), 1);
        foreach ($lifecycle as $stage => $count): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:8px 0">
          <div style="width:10px;height:10px;border-radius:50%;background:<?=$lcColors[$stage]?>"></div>
          <span style="min-width:70px;font-size:12px;font-weight:600"><?=$lcLabels[$stage]?></span>
          <div style="flex:1;height:16px;border-radius:4px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=round($count/$totalLC*100)?>%;background:<?=$lcColors[$stage]?>;border-radius:4px"></div>
          </div>
          <span style="font-size:12px;color:var(--muted)"><?=$count?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card">
        <h2>热门页面 TOP10</h2>
        <?php foreach ($pages as $page => $count): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)">
          <span style="font-size:12px;color:var(--muted);min-width:30px;text-align:right"><?=$count?></span>
          <span style="font-size:12px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1" title="<?=htmlspecialchars($page)?>"><?=htmlspecialchars($page)?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($tab === 'behavior'): ?>
    <?php
    $eventStats = CdpSystem::getEventStats(14);
    $topEvents = CdpSystem::getTopEvents(10);
    $heatmap = CdpSystem::getHourlyHeatmap(7);
    $sessionDepth = CdpSystem::getSessionDepth();
    ?>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
      <div class="card">
        <h2>事件趋势（近14天）</h2>
        <div class="chart-bar" style="height:140px">
          <?php
          $maxEvt = max(array_map(fn($s) => $s['total'], $eventStats)) ?: 1;
          foreach (array_reverse($eventStats) as $date => $data):
              $h = max(2, round($data['total'] / $maxEvt * 120));
          ?>
          <div class="bar" style="height:<?=$h?>px" title="<?=$date?>: <?=$data['total']?> 事件"></div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted);padding:0 2px">
          <span><?=date('m/d', strtotime('-13 days'))?></span><span>今天</span>
        </div>
      </div>
      <div class="card">
        <h2>热门事件 TOP10</h2>
        <?php $maxEvtName = max($topEvents) ?: 1; foreach ($topEvents as $event => $count): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:120px;font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($event)?>"><?=htmlspecialchars($event)?></span>
          <div style="flex:1;height:14px;border-radius:3px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=round($count/$maxEvtName*100)?>%;background:var(--grad);border-radius:3px"></div>
          </div>
          <span style="font-size:12px;color:var(--accent);min-width:36px;text-align:right"><?=$count?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="cdp-g" style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div class="card">
        <h2>活跃时段热力图（近7天）</h2>
        <?php $maxHeat = max($heatmap) ?: 1; ?>
        <div class="heatmap-grid">
          <?php foreach ($heatmap as $hour => $count):
              $intensity = $count > 0 ? round($count / $maxHeat * 100) : 0;
              $bg = $count > 0 ? "rgba(99,102,241," . ($intensity / 100 * 0.8 + 0.2) . ")" : "var(--hover)";
          ?>
          <div class="heatmap-cell" style="background:<?=$bg?>;color:<?= $intensity > 50 ? '#fff' : 'var(--muted)'?>" title="<?=$hour?>:00 - <?=$count?> 事件"><?=$hour?></div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-top:8px">
          <span>0:00</span><span>6:00</span><span>12:00</span><span>18:00</span><span>23:00</span>
        </div>
      </div>
      <div class="card">
        <h2>会话深度分布</h2>
        <?php $maxDepth = max($sessionDepth) ?: 1; foreach ($sessionDepth as $range => $count): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
          <span style="min-width:60px;font-size:13px;font-weight:600"><?=$range?> 次</span>
          <div style="flex:1;height:24px;border-radius:6px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=round($count/$maxDepth*100)?>%;background:var(--grad);border-radius:6px;display:flex;align-items:center;padding:0 8px;font-size:11px;color:var(--on-accent);font-weight:600"><?=$count?></div>
          </div>
          <span style="font-size:12px;color:var(--muted);min-width:40px;text-align:right"><?=round($count/max(array_sum($sessionDepth),1)*100,1)?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($tab === 'profiles'): ?>
    <?php $tags = CdpSystem::getTagDistribution(); ?>
    <div class="cdp-g" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
      <div class="card">
        <h2>标签分布</h2>
        <?php if (empty($tags)): ?>
        <div class="empty">暂无标签数据</div>
        <?php else: ?>
        <?php $maxTag = max($tags) ?: 1; foreach ($tags as $tag => $count): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:8px 0">
          <span style="min-width:100px;font-size:12px;font-weight:600"><?=htmlspecialchars($tag)?></span>
          <div style="flex:1;height:18px;border-radius:4px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=round($count/$maxTag*100)?>%;background:var(--grad);border-radius:4px;display:flex;align-items:center;padding:0 6px;font-size:11px;color:var(--on-accent);font-weight:600"><?=$count?></div>
          </div>
          <span style="font-size:12px;color:var(--muted)"><?=$count?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="card">
        <h2>用户属性分布</h2>
        <?php $cityDist = CdpSystem::getPropertyDistribution('city');
        if (!empty($cityDist)):
            $maxProp = max($cityDist) ?: 1;
            foreach (array_slice($cityDist, 0, 8, true) as $val => $count): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:80px;font-size:12px;font-weight:600"><?=htmlspecialchars($val)?></span>
          <div style="flex:1;height:14px;border-radius:3px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=round($count/$maxProp*100)?>%;background:var(--grad);border-radius:3px"></div>
          </div>
          <span style="font-size:12px;color:var(--muted)"><?=$count?></span>
        </div>
        <?php endforeach; else: ?>
        <div class="empty">暂无属性数据</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card lst-card">
      <div style="display:flex;align-items:center;gap:10px;padding:16px 18px 4px"><h2 style="margin:0">用户</h2><span class="hint" style="font-size:12px;color:var(--faint)">· 按最近活跃排序，前 200 个；筛选框可按 ID / 姓名 / 邮箱 / 标签搜</span></div>
      <table class="lst-table">
        <thead><tr><th class="c-title">用户</th><th style="width:220px">标签</th><th style="width:90px">健康分</th><th style="width:80px">事件</th><th style="width:90px">首访</th><th style="width:90px">最近</th><th class="c-act" style="width:90px"></th></tr></thead>
        <tbody>
        <?php foreach (array_slice($profiles, 0, 200, true) as $vid => $p):
            $health = CdpSystem::getHealthScore($vid);
            $hc = $health >= 70 ? 'ok' : ($health >= 40 ? 'warn' : 'danger');
            $tagLabels = []; foreach (($p['tags'] ?? []) as $tk => $tv) { $tl = is_int($tk) ? $tv : $tk; if (is_string($tl)) $tagLabels[] = $tl; }
        ?>
          <tr>
            <td class="c-title"><div class="lst-item"><span class="cdp-av"><?= strtoupper(substr($vid, -2)) ?></span><div class="lst-body"><div class="lst-title"><a href="profile-detail.php?v=<?=urlencode($vid)?>" style="color:inherit;text-decoration:none"><?=htmlspecialchars($p['properties']['name'] ?? $vid)?></a></div><div class="lst-sub"><span class="lst-slug"><?=htmlspecialchars($p['properties']['email'] ?? $vid)?></span></div></div></div></td>
            <td><?php foreach (array_slice($tagLabels, 0, 3) as $tl): ?><span class="inline-tag"><?=htmlspecialchars($tl)?></span> <?php endforeach; if (count($tagLabels) > 3): ?><span class="inline-tag more">+<?=count($tagLabels)-3?></span><?php endif; if (!$tagLabels): ?><span class="text-muted">—</span><?php endif; ?></td>
            <td><span class="cdp-health <?=$hc?>"><?=$health?></span></td>
            <td class="text-sm text-muted"><?=$p['events_count']?></td>
            <td class="lst-when"><?=date('m/d', strtotime($p['first_seen']))?></td>
            <td class="lst-when"><?=date('m/d', strtotime($p['last_seen'] ?? $p['first_seen']))?></td>
            <td class="c-act"><a href="profile-detail.php?v=<?=urlencode($vid)?>" class="btn btn-ghost btn-sm">详情</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab === 'retention'): ?>
    <?php $retention = CdpSystem::getRetention(14); $cohorts = array_slice($retention, 0, 7, true); ?>
    <div class="card">
      <h2>N日留存分析</h2>
      <p class="text-sm text-muted mb-4">追踪用户在首次访问后的回访情况</p>
      <div style="overflow-x:auto">
        <table style="font-size:12px">
          <thead><tr><th style="min-width:100px">队列日期</th><th style="min-width:60px">用户数</th>
          <?php for ($d = 0; $d < 14; $d++): ?><th style="min-width:50px">D<?=$d?></th><?php endfor; ?>
          </tr></thead>
          <tbody>
          <?php foreach ($cohorts as $date => $data): ?>
          <tr>
            <td style="font-weight:600"><?=date('m/d', strtotime($date))?></td>
            <td style="font-weight:700;color:var(--accent)"><?=$data['cohort_size']?></td>
            <?php for ($d = 0; $d < 14; $d++):
                $ret = $data['retained'][$d] ?? null;
                $rate = $ret ? $ret['rate'] : 0;
                $bg = $rate > 0 ? "rgba(99,102,241," . ($rate / 100 * 0.6 + 0.1) . ")" : "transparent";
            ?>
            <td class="retention-cell" style="background:<?=$bg?>;color:<?= $rate > 30 ? '#fff' : 'var(--muted)'?>"><?=$rate > 0 ? $rate . '%' : '-'?></td>
            <?php endfor; ?>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card" style="margin-top:20px">
      <h2>留存曲线（平均）</h2>
      <div style="display:flex;align-items:flex-end;gap:4px;height:160px;padding:20px 0">
        <?php
        $avgRetention = [];
        for ($d = 0; $d < 14; $d++) {
            $total = 0; $cnt = 0;
            foreach ($cohorts as $data) { if (isset($data['retained'][$d])) { $total += $data['retained'][$d]['rate']; $cnt++; } }
            $avgRetention[$d] = $cnt > 0 ? round($total / $cnt, 1) : 0;
        }
        $maxRet = max($avgRetention) ?: 1;
        foreach ($avgRetention as $day => $rate):
            $h = max(2, round($rate / $maxRet * 140));
        ?>
        <div style="flex:1;text-align:center">
          <div style="font-size:10px;color:var(--accent);margin-bottom:4px"><?=$rate?>%</div>
          <div style="height:<?=$h?>px;background:var(--grad);border-radius:4px 4px 0 0"></div>
          <div style="font-size:10px;color:var(--muted);margin-top:4px">D<?=$day?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($tab === 'rfm'): ?>
    <?php $rfmData = CdpSystem::getRFMAnalysis(); $rfmDist = CdpSystem::getRFMDistribution(); ?>
    <div class="cdp-g" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
      <div class="card">
        <h2>RFM 客户分群</h2>
        <p class="text-sm text-muted mb-4">基于最近访问时间(R)、访问频率(F)、消费金额(M)</p>
        <?php
        $segStyles = ['VIP'=>'rfm-vip','忠诚用户'=>'rfm-loyal','高价值新客'=>'rfm-active','活跃用户'=>'rfm-active','流失风险'=>'rfm-risk','流失用户'=>'rfm-churned','新用户'=>'rfm-new','一般用户'=>'rfm-new'];
        $totalRFM = max(array_sum($rfmDist), 1);
        foreach ($rfmDist as $seg => $count): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
          <span class="rfm-segment <?=$segStyles[$seg] ?? 'rfm-new'?>"><?=$seg?></span>
          <div style="flex:1;height:20px;border-radius:6px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=round($count/$totalRFM*100)?>%;background:var(--grad);border-radius:6px"></div>
          </div>
          <span style="font-size:13px;font-weight:700;min-width:40px;text-align:right"><?=$count?></span>
          <span style="font-size:11px;color:var(--muted);min-width:40px"><?=round($count/$totalRFM*100,1)?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card">
        <h2>RFM 评分说明</h2>
        <div style="font-size:13px;line-height:2">
          <div><strong>R (Recency)</strong> — 最近一次访问距今天数：1天内=5分，7天内=4分，30天内=3分，90天内=2分，90天以上=1分</div>
          <div><strong>F (Frequency)</strong> — 访问频率：100次+=5分，50次+=4分，20次+=3分，5次+=2分，5次以下=1分</div>
          <div><strong>M (Monetary)</strong> — 消费金额：1000+=5分，500+=4分，100+=3分，10+=2分，10以下=1分</div>
          <div style="margin-top:12px;padding:12px;background:var(--hover);border-radius:8px">
            <div><strong>VIP</strong>：R>=4, F>=4, M>=4 — 核心高价值客户</div>
            <div><strong>忠诚用户</strong>：R>=4, F>=3 — 频繁回访</div>
            <div><strong>高价值新客</strong>：R>=4, M>=3 — 最近活跃且消费高</div>
            <div><strong>流失风险</strong>：R<=2, F>=3 — 曾经活跃但近期沉默</div>
            <div><strong>流失用户</strong>：R<=2, F<=2 — 需要召回</div>
          </div>
        </div>
      </div>
    </div>
    <div class="card">
      <h2>RFM 用户明细（前30）</h2>
      <table>
        <thead><tr><th>用户</th><th>最近访问</th><th>访问次数</th><th>消费金额</th><th>R</th><th>F</th><th>M</th><th>分群</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($rfmData, 0, 30) as $u): ?>
        <tr>
          <td style="font-weight:600"><?=htmlspecialchars($u['name'])?></td>
          <td><?=round($u['recency'])?>天前</td>
          <td><?=$u['frequency']?></td>
          <td><?=$u['monetary'] > 0 ? '¥' . number_format($u['monetary'], 2) : '-'?></td>
          <td><span style="color:var(--accent)"><?=$u['r_score']?></span></td>
          <td><span style="color:var(--accent)"><?=$u['f_score']?></span></td>
          <td><span style="color:var(--accent)"><?=$u['m_score']?></span></td>
          <td><span class="rfm-segment <?=$segStyles[$u['segment']] ?? 'rfm-new'?>" style="font-size:11px"><?=$u['segment']?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab === 'path'): ?>
    <?php $paths = CdpSystem::getPathAnalysis(15); $entries = CdpSystem::getEntryPages(10); ?>
    <div class="cdp-g" style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div class="card">
        <h2>用户路径 TOP15</h2>
        <p class="text-sm text-muted mb-4">最常用的事件转移路径</p>
        <?php $maxPath = max($paths) ?: 1; $rank = 1; foreach ($paths as $path => $count): ?>
        <div class="path-item">
          <span class="path-rank">#<?=$rank++?></span>
          <div class="path-bar"><div class="fill" style="width:<?=round($count/$maxPath*100)?>%"></div></div>
          <span style="font-size:12px;font-weight:600;min-width:140px"><?=htmlspecialchars($path)?></span>
          <span style="font-size:12px;color:var(--accent);min-width:36px;text-align:right"><?=$count?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card">
        <h2>入口页面 TOP10</h2>
        <p class="text-sm text-muted mb-4">用户首次访问的页面</p>
        <?php $maxEntry = max($entries) ?: 1; $rank = 1; foreach ($entries as $page => $count): ?>
        <div class="path-item">
          <span class="path-rank">#<?=$rank++?></span>
          <div class="path-bar"><div class="fill" style="width:<?=round($count/$maxEntry*100)?>%"></div></div>
          <span style="font-size:12px;font-weight:500;min-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($page)?>"><?=htmlspecialchars($page)?></span>
          <span style="font-size:12px;color:var(--accent);min-width:36px;text-align:right"><?=$count?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($tab === 'revenue'): ?>
    <?php
    $revenueTrend = CdpSystem::getRevenueTrend(30);
    $ltv = CdpSystem::getLTVAnalysis();
    $aov = CdpSystem::getAOVDistribution();
    ?>
    <div class="cdp-g" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
      <div class="revenue-card"><div class="big-num">¥<?=number_format($ltv['total_revenue'], 0)?></div><div style="font-size:13px;color:var(--muted);margin-top:4px">总收入</div></div>
      <div class="revenue-card"><div class="big-num"><?=$ltv['paying_users']?></div><div style="font-size:13px;color:var(--muted);margin-top:4px">付费用户</div></div>
      <div class="revenue-card"><div class="big-num">¥<?=$ltv['arpu']?></div><div style="font-size:13px;color:var(--muted);margin-top:4px">ARPU (人均)</div></div>
      <div class="revenue-card"><div class="big-num">¥<?=$ltv['arppu']?></div><div style="font-size:13px;color:var(--muted);margin-top:4px">ARPPU (付费人均)</div></div>
    </div>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
      <div class="card">
        <h2>营收趋势（近30天）</h2>
        <div class="chart-bar" style="height:140px">
          <?php
          $maxRev = max(array_map(fn($r) => $r['total'], $revenueTrend)) ?: 1;
          foreach (array_reverse($revenueTrend) as $date => $data):
              $h = $data['total'] > 0 ? max(4, round($data['total'] / $maxRev * 120)) : 0;
          ?>
          <div class="bar" style="height:<?=$h?>px" title="<?=$date?>: ¥<?=number_format($data['total'],0)?> (<?=$data['count']?>单)"></div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted);padding:0 2px">
          <span><?=date('m/d', strtotime('-29 days'))?></span><span>今天</span>
        </div>
      </div>
      <div class="card">
        <h2>客单价分布</h2>
        <?php
        $distLabels = ['0-50'=>'¥0-50','51-100'=>'¥51-100','101-200'=>'¥101-200','201-500'=>'¥201-500','500+'=>'¥500+'];
        $totalOrders = max(array_sum($aov['distribution']), 1);
        foreach ($aov['distribution'] as $range => $count): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:70px;font-size:12px;font-weight:600"><?=$distLabels[$range]?></span>
          <div style="flex:1;height:16px;border-radius:4px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=round($count/$totalOrders*100)?>%;background:var(--grad);border-radius:4px"></div>
          </div>
          <span style="font-size:12px;color:var(--muted)"><?=$count?></span>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:12px;padding:12px;background:var(--hover);border-radius:8px;font-size:12px">
          <div>平均客单价: <strong>¥<?=$aov['avg']?></strong></div>
          <div>中位数: <strong>¥<?=$aov['median']?></strong></div>
          <div>范围: ¥<?=$aov['min']?> ~ ¥<?=$aov['max']?></div>
        </div>
      </div>
    </div>
    <div class="card">
      <h2>LTV 客户价值分层</h2>
      <?php
      $tierLabels = ['0'=>'未消费','1-50'=>'¥1-50','51-200'=>'¥51-200','201-500'=>'¥201-500','500+'=>'¥500+'];
      $totalUsers = max(array_sum($ltv['tiers']), 1);
      foreach ($ltv['tiers'] as $tier => $count): ?>
      <div class="ltv-tier">
        <span class="ltv-label"><?=$tierLabels[$tier]?></span>
        <div class="ltv-bar"><div class="fill" style="width:<?=max(1, round($count/$totalUsers*100))?>%"><?=$count?></div></div>
        <span style="font-size:12px;color:var(--muted);min-width:40px"><?=round($count/$totalUsers*100,1)?>%</span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- 营收归因（按渠道/设备） -->
    <?php
    $revChannel = CdpSystem::getRevenueAttribution('channel');
    $revDevice = CdpSystem::getRevenueAttribution('device');
    $convChannel = CdpSystem::getConversionByDimension('channel');
    ?>
    <div class="cdp-g" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px" class="attr-grid">
      <div class="card">
        <h2>📡 营收归因 · 渠道</h2>
        <?php if (empty($revChannel)): ?><p class="text-sm text-muted" style="padding:20px;text-align:center">暂无渠道订单数据</p><?php endif; ?>
        <?php foreach ($revChannel as $ch => $d): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:70px;font-size:12px;font-weight:600"><?=htmlspecialchars($ch)?></span>
          <div style="flex:1;height:14px;background:var(--hover);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?=round($d['revenue'] / max(1, array_sum(array_column($revChannel, 'revenue'))) * 100)?>%;background:var(--grad);border-radius:4px"></div>
          </div>
          <span style="font-size:12px;color:var(--muted);min-width:90px;text-align:right">¥<?=number_format($d['revenue'],0)?> / <?=$d['orders']?>单</span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card">
        <h2>📱 营收归因 · 设备</h2>
        <?php if (empty($revDevice)): ?><p class="text-sm text-muted" style="padding:20px;text-align:center">暂无设备订单数据</p><?php endif; ?>
        <?php foreach ($revDevice as $dv => $d): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:70px;font-size:12px;font-weight:600"><?=htmlspecialchars($dv)?></span>
          <div style="flex:1;height:14px;background:var(--hover);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?=round($d['revenue'] / max(1, array_sum(array_column($revDevice, 'revenue'))) * 100)?>%;background:var(--ok);border-radius:4px"></div>
          </div>
          <span style="font-size:12px;color:var(--muted);min-width:90px;text-align:right">¥<?=number_format($d['revenue'],0)?> / <?=$d['orders']?>单</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 渠道转化效率 -->
    <div class="card" style="margin-top:20px;padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2)"><h2 style="margin:0">📈 渠道转化效率（访问 → 转化）</h2></div>
      <table>
        <thead><tr><th>渠道</th><th>访问</th><th>转化</th><th>转化率</th></tr></thead>
        <tbody>
          <?php if (empty($convChannel)): ?><tr><td colspan="4" class="empty">暂无数据</td></tr><?php endif; ?>
          <?php foreach ($convChannel as $ch => $d): ?>
          <tr>
            <td><strong><?=htmlspecialchars($ch)?></strong></td>
            <td><?=$d['visits']?></td>
            <td><?=$d['conversions']?></td>
            <td><span class="badge <?=$d['rate'] >= 5 ? 'badge-green' : ($d['rate'] >= 2 ? 'badge-yellow' : 'badge-gray')?>"><?=$d['rate']?>%</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab === 'segments'): ?>
    <?php $segments = CdpSystem::allSegments(); ?>
    <div class="flex items-center gap-4 mb-4">
      <h2 style="margin-bottom:0">分群管理</h2>
      <button onclick="document.getElementById('segmentDialog').style.display='flex'" class="btn btn-primary btn-sm">+ 创建分群</button>
    </div>
    <?php
    // 导出分群成员
    if (isset($_GET['export_segment'])) {
        $seg = null; foreach ($segments as $s) if (($s['id'] ?? '') === $_GET['export_segment']) { $seg = $s; break; }
        if ($seg) {
            $members = CdpSystem::getSegmentUsers($seg['rules'] ?? [], 5000);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="segment-' . $seg['id'] . '.csv"');
            echo "\xEF\xBB\xBFvisitor_id,email,tags,last_seen\n";
            foreach ($members as $m) {
                echo implode(',', [($m['visitor_id'] ?? ''), mb_convert_encoding(($m['email'] ?? ''), 'GBK', 'UTF-8'), mb_convert_encoding(implode('|', ($m['tags'] ?? [])), 'GBK', 'UTF-8'), ($m['last_seen'] ?? '')]) . "\n";
            }
            exit;
        }
    }
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px">
      <?php foreach ($segments as $seg): $memberList = CdpSystem::getSegmentUsers($seg['rules'] ?? [], 50); ?>
      <div class="segment-card">
        <div style="font-weight:600;margin-bottom:4px"><?=htmlspecialchars($seg['name'])?></div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:8px"><?=htmlspecialchars($seg['description'] ?? '')?></div>
        <div style="font-size:12px"><span style="font-weight:700;color:var(--accent)"><?=CdpSystem::countSegment($seg['rules'] ?? [])?></span> 用户匹配</div>
        <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
          <a href="cdp.php?tab=segments&export_segment=<?=urlencode($seg['id'])?>" class="btn btn-ghost btn-sm">⬇ 导出</a>
          <button class="btn btn-ghost btn-sm" onclick="var d=this.nextElementSibling;d.style.display=d.style.display==='none'?'':'none'">成员</button>
          <div style="display:none;flex:1;min-width:0;font-size:11px;color:var(--muted)"><?=count($memberList)?> 人：<?=htmlspecialchars(implode('、', array_slice(array_map(function($m){ $lab = ($m['email'] ?? '') ?: ($m['visitor_id'] ?? ''); return is_string($lab) ? $lab : ''; }, $memberList), 0, 20)))?><?=count($memberList) > 20 ? ' …' : ''?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="card" style="margin-top:20px">
      <h2>预设分群建议</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:12px">
        <div style="padding:14px;background:var(--hover);border-radius:8px"><strong>高活跃用户</strong><p class="text-sm text-muted" style="margin-top:4px">事件数 >= 100</p></div>
        <div style="padding:14px;background:var(--hover);border-radius:8px"><strong>已购买用户</strong><p class="text-sm text-muted" style="margin-top:4px">有购买事件</p></div>
        <div style="padding:14px;background:var(--hover);border-radius:8px"><strong>已留资用户</strong><p class="text-sm text-muted" style="margin-top:4px">有表单提交</p></div>
        <div style="padding:14px;background:var(--hover);border-radius:8px"><strong>沉睡用户</strong><p class="text-sm text-muted" style="margin-top:4px">30天未访问</p></div>
        <div style="padding:14px;background:var(--hover);border-radius:8px"><strong>学习者</strong><p class="text-sm text-muted" style="margin-top:4px">完成过课程</p></div>
        <div style="padding:14px;background:var(--hover);border-radius:8px"><strong>VIP 用户</strong><p class="text-sm text-muted" style="margin-top:4px">高价值 + 高活跃</p></div>
      </div>
    </div>

    <?php elseif ($tab === 'events'): ?>
    <?php
    // 事件筛选 + 导出
    $evFilter = trim($_GET['ev'] ?? '');
    $evUid = trim($_GET['ev_uid'] ?? '');
    $evDays = max(1, (int)($_GET['ev_days'] ?? 7));
    $evCut = date('Y-m-d', strtotime("-{$evDays} days"));
    $filteredEvents = array_values(array_filter($events, function ($e) use ($evFilter, $evUid, $evCut) {
        if ($evFilter !== '' && ($e['event'] ?? '') !== $evFilter) return false;
        if ($evUid !== '' && strpos(($e['visitor_id'] ?? ''), $evUid) === false) return false;
        if (($e['timestamp'] ?? '') < $evCut) return false;
        return true;
    }));
    if (isset($_GET['export_events'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="events-' . date('Ymd') . '.csv"');
        echo "\xEF\xBB\xBF时间,事件,用户,属性\n";
        foreach (array_slice($filteredEvents, 0, 5000) as $e) {
            echo implode(',', [($e['timestamp'] ?? ''), ($e['event'] ?? ''), ($e['visitor_id'] ?? ''), mb_convert_encoding(json_encode($e['properties'] ?? [], JSON_UNESCAPED_UNICODE), 'GBK', 'UTF-8')]) . "\n";
        }
        exit;
    }
    $eventNames = array_values(array_unique(array_column($events, 'event')));
    sort($eventNames);
    ?>
    <div class="card">
      <h2>事件明细</h2>
      <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:12px 0">
        <input type="hidden" name="tab" value="events">
        <select name="ev" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px"><option value="">全部事件</option><?php foreach ($eventNames as $en): ?><option value="<?=htmlspecialchars($en)?>" <?=$evFilter===$en?'selected':''?>><?=htmlspecialchars($en)?></option><?php endforeach; ?></select>
        <input type="text" name="ev_uid" value="<?=htmlspecialchars($evUid)?>" placeholder="按用户ID筛选" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px;width:180px">
        <select name="ev_days" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12.5px"><?php foreach ([1,7,14,30] as $dd): ?><option value="<?=$dd?>" <?=$evDays===$dd?'selected':''?>>近<?=$dd?>天</option><?php endforeach; ?></select>
        <button class="btn btn-s btn-sm">筛选</button>
        <a href="cdp.php?tab=events&export_events=1&ev=<?=urlencode($evFilter)?>&ev_uid=<?=urlencode($evUid)?>&ev_days=<?=$evDays?>" class="btn btn-ghost btn-sm">⬇ 导出</a>
      </form>
      <div style="max-height:600px;overflow-y:auto">
        <?php if (empty($filteredEvents)): ?><p class="text-sm text-muted">无匹配事件</p><?php endif; ?>
        <?php foreach (array_slice($filteredEvents, 0, 200) as $e): ?>
        <div class="event-row">
          <span class="event-name"><?=htmlspecialchars($e['event'])?></span>
          <span class="event-time"><?=htmlspecialchars($e['timestamp'])?></span>
          <span class="event-props"><?=htmlspecialchars(json_encode($e['properties'] ?? [], JSON_UNESCAPED_UNICODE))?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($tab === 'funnel'): ?>
    <?php
    $funnelSteps = ['page_view', 'form_view', 'form_submit', 'purchase'];
    $funnel = CdpSystem::getFunnel($funnelSteps, 30);
    ?>
    <div class="card">
      <h2>漏斗分析</h2>
      <p class="text-sm text-muted mb-4">分析用户从访问到转化的全流程</p>
      <div class="cdp-g" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
        <?php foreach ($funnel as $i => $step): ?>
        <div class="funnel-step">
          <div class="count"><?=$step['count']?></div>
          <div class="lab"><?=htmlspecialchars($step['step'])?></div>
          <div class="rate"><?=$step['rate']?>%</div>
        </div>
        <?php if ($i < count($funnel) - 1): ?><div style="text-align:center;color:var(--muted);padding:4px;display:flex;align-items:center">→</div><?php endif; ?>
        <?php endforeach; ?>
      </div>
      <div style="padding:16px;background:var(--hover);border-radius:8px;text-align:center">
        <div style="font-size:14px;color:var(--muted)">总体转化率</div>
        <div style="font-size:32px;font-weight:800;color:var(--accent)"><?=$funnel[0]['count'] > 0 ? round(end($funnel)['count'] / $funnel[0]['count'] * 100, 1) : 0?>%</div>
      </div>
    </div>

    <?php elseif ($tab === 'dimension'): ?>
    <?php
    $channelDist = CdpSystem::getChannelDistribution();
    $deviceDist = CdpSystem::getDimensionDistribution('device');
    $browserDist = CdpSystem::getBrowserDistribution();
    $osDist = CdpSystem::getOsDistribution();
    $langDist = CdpSystem::getDimensionDistribution('language');
    $channelCross = CdpSystem::getDimensionEventCross('channel');
    ?>
    <div class="cdp-g" style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="dim-grid">
      <div class="card">
        <h2>📡 渠道分布</h2>
        <?php foreach ($channelDist as $k => $v): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:70px;font-size:12px;font-weight:600"><?=htmlspecialchars($k)?></span>
          <div style="flex:1;height:12px;background:var(--hover);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?=round($v / max(1, array_sum($channelDist)) * 100)?>%;background:var(--accent);border-radius:4px"></div>
          </div>
          <span style="font-size:12px;color:var(--muted);min-width:40px;text-align:right"><?=$v?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($channelDist)): ?><p class="text-sm text-muted" style="padding:20px;text-align:center">暂无渠道数据</p><?php endif; ?>
      </div>
      <div class="card">
        <h2>📱 设备分布</h2>
        <?php foreach ($deviceDist as $k => $v): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:70px;font-size:12px;font-weight:600"><?=htmlspecialchars($k)?></span>
          <div style="flex:1;height:12px;background:var(--hover);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?=round($v / max(1, array_sum($deviceDist)) * 100)?>%;background:var(--ok);border-radius:4px"></div>
          </div>
          <span style="font-size:12px;color:var(--muted);min-width:40px;text-align:right"><?=$v?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($deviceDist)): ?><p class="text-sm text-muted" style="padding:20px;text-align:center">暂无设备数据</p><?php endif; ?>
      </div>
      <div class="card">
        <h2>🌐 浏览器分布</h2>
        <?php foreach ($browserDist as $k => $v): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:70px;font-size:12px;font-weight:600"><?=htmlspecialchars($k)?></span>
          <div style="flex:1;height:12px;background:var(--hover);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?=round($v / max(1, array_sum($browserDist)) * 100)?>%;background:var(--accent);border-radius:4px"></div>
          </div>
          <span style="font-size:12px;color:var(--muted);min-width:40px;text-align:right"><?=$v?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($browserDist)): ?><p class="text-sm text-muted" style="padding:20px;text-align:center">暂无浏览器数据</p><?php endif; ?>
      </div>
      <div class="card">
        <h2>💻 操作系统分布</h2>
        <?php foreach ($osDist as $k => $v): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
          <span style="min-width:70px;font-size:12px;font-weight:600"><?=htmlspecialchars($k)?></span>
          <div style="flex:1;height:12px;background:var(--hover);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?=round($v / max(1, array_sum($osDist)) * 100)?>%;background:var(--warn);border-radius:4px"></div>
          </div>
          <span style="font-size:12px;color:var(--muted);min-width:40px;text-align:right"><?=$v?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($osDist)): ?><p class="text-sm text-muted" style="padding:20px;text-align:center">暂无系统数据</p><?php endif; ?>
      </div>
    </div>

    <!-- 渠道 × 事件交叉 -->
    <div class="card" style="margin-top:20px;padding:0;overflow:auto">
      <div style="padding:14px 20px;background:var(--surface-2)"><h2 style="margin:0">🔀 渠道 × 事件交叉分析</h2></div>
      <table>
        <thead><tr><th>渠道</th><th>总事件</th><th>主要事件</th></tr></thead>
        <tbody>
          <?php if (empty($channelCross)): ?><tr><td colspan="3" class="empty">暂无交叉数据</td></tr><?php endif; ?>
          <?php foreach ($channelCross as $ch => $data): ?>
          <tr>
            <td><strong><?=htmlspecialchars($ch)?></strong></td>
            <td><span class="badge badge-green"><?=$data['total']?></span></td>
            <td class="text-sm text-muted">
              <?php
              arsort($data['events']);
              $top = array_slice($data['events'], 0, 4, true);
              foreach ($top as $ev => $cnt): ?>
              <span class="badge badge-gray" style="font-size:11px"><?=htmlspecialchars($ev)?>:<?=$cnt?></span>
              <?php endforeach; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php endif; ?>
  </div>
</div>

<?php if ($tab === 'segments'): ?>
<div id="segmentDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:92%;max-width:640px">
    <h2 style="margin-bottom:16px">创建分群</h2>
    <form method="post" id="segmentForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_segment">
      <div class="field"><label>分群名称</label><input type="text" name="name" required placeholder="如：高价值用户"></div>
      <div class="field"><label>描述</label><textarea name="description" rows="2" placeholder="可选"></textarea></div>
      <div class="field" style="margin-top:10px"><label>规则组合方式</label>
        <select name="rule_operator_top" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px">
          <option value="and">满足全部条件（AND）</option><option value="or">满足任一条件（OR）</option>
        </select>
      </div>
      <div class="field"><label>规则</label></div>
      <div id="segRules" style="display:flex;flex-direction:column;gap:8px">
        <div class="seg-rule" style="display:grid;grid-template-columns:1fr 90px 90px 1fr;gap:6px">
          <select name="rule_type[]" class="rule-type" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">
            <option value="property">属性</option><option value="event">事件</option><option value="summary">行为摘要</option>
            <option value="lifecycle">生命周期</option><option value="tag">标签</option>
            <option value="last_seen">最近活跃(天)</option><option value="first_seen">首次活跃(天)</option>
          </select>
          <input type="text" name="rule_field[]" class="rule-field" placeholder="字段" value="" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">
          <input type="text" name="rule_op[]" class="rule-op" placeholder="操作符" value="gte" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">
          <input type="text" name="rule_value[]" class="rule-value" placeholder="值" value="1" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">
        </div>
      </div>
      <div style="margin-top:8px"><button type="button" class="btn btn-ghost btn-sm" onclick="addSegRule()">+ 添加规则</button>
        <span class="text-xs text-muted" style="margin-left:6px">属性：channel/role · 摘要：page_views_30d/purchase_amount_total · 事件选 event+window(天)</span></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="this.closest('[style]').style.display='none'">取消</button>
        <button type="submit" class="btn btn-primary">创建</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<script>
function addSegRule() {
  var row = document.createElement('div');
  row.className = 'seg-rule';
  row.style.cssText = 'display:grid;grid-template-columns:1fr 90px 90px 1fr;gap:6px';
  row.innerHTML =
    '<select name="rule_type[]" class="rule-type" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">' +
      '<option value="property">属性</option><option value="event">事件</option><option value="summary">行为摘要</option>' +
      '<option value="lifecycle">生命周期</option><option value="tag">标签</option>' +
      '<option value="last_seen">最近活跃(天)</option><option value="first_seen">首次活跃(天)</option>' +
    '</select>' +
    '<input type="text" name="rule_field[]" class="rule-field" placeholder="字段/事件名" value="" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">' +
    '<input type="text" name="rule_op[]" class="rule-op" placeholder="操作符" value="gte" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">' +
    '<input type="text" name="rule_value[]" class="rule-value" placeholder="值(事件选次数/天)" value="1" style="padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">' +
    '<button type="button" class="btn btn-ghost btn-sm" style="grid-column:1/-1;justify-self:end" onclick="this.closest(\'.seg-rule\').remove()">移除</button>';
  document.getElementById('segRules').appendChild(row);
}
function loadAiInsights(force) {
  var box = document.getElementById('aiInsightBox');
  if (!box) return;
  if (force) { box.innerHTML = '<div class="text-sm text-muted" style="padding:20px;text-align:center">AI 生成中…</div>'; }
  fetch('../api/cdp-insight.php?action=insights&days=30', {credentials:'include'})
    .then(function(r){return r.json();})
    .then(function(d){
      if (!d.ok) { box.innerHTML = '<div class="text-sm text-muted">洞察生成失败</div>'; return; }
      var h = '';
      if (d.summary) h += '<div style="padding:12px 14px;background:var(--surface);border-radius:10px;margin-bottom:12px;font-size:13.5px;line-height:1.7">📌 ' + d.summary + '</div>';
      if (d.insights && d.insights.length) {
        h += '<div style="font-size:12px;font-weight:700;color:var(--text-3);margin:10px 0 6px">✨ 洞察</div>';
        d.insights.forEach(function(i){
          h += '<div style="display:flex;gap:8px;padding:8px 12px;background:var(--surface);border-radius:8px;margin-bottom:6px;font-size:13px"><span>💡</span><div><strong>' + (i.title||'') + '</strong><div class="text-sm text-muted" style="font-size:12px">' + (i.detail||'') + '</div></div></div>';
        });
      }
      if (d.anomalies && d.anomalies.length) {
        h += '<div style="font-size:12px;font-weight:700;color:var(--danger);margin:10px 0 6px">⚠️ 异常</div>';
        d.anomalies.forEach(function(a){
          h += '<div style="display:flex;gap:8px;padding:8px 12px;background:var(--surface);border-radius:8px;margin-bottom:6px;font-size:13px"><span>🚨</span><div><strong>' + (a.title||'') + '</strong><div class="text-sm text-muted" style="font-size:12px">' + (a.detail||'') + '</div></div></div>';
        });
      }
      if (d.actions && d.actions.length) {
        h += '<div style="font-size:12px;font-weight:700;color:var(--accent);margin:10px 0 6px">🎯 行动建议</div>';
        d.actions.forEach(function(a){
          h += '<div style="display:flex;gap:8px;padding:8px 12px;background:var(--surface);border-radius:8px;margin-bottom:6px;font-size:13px"><span>→</span><div><strong>' + (a.title||'') + '</strong><div class="text-sm text-muted" style="font-size:12px">' + (a.detail||'') + '</div></div></div>';
        });
      }
      if (!h) h = '<div class="text-sm text-muted">暂无洞察数据</div>';
      box.innerHTML = h;
    });
}
document.addEventListener('DOMContentLoaded', function(){ loadAiInsights(false); });
</script>
<?php admin_footer(); ?>
