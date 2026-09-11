<?php
/**
 * 爬虫洞察 — AI 爬虫 / 搜索引擎爬虫 / 开发访问 的独立统计视图
 *
 * 数据来自 events_bot 表（track.php 按 TrafficFilter 分流写入），
 * 主 events 表只含真实访客，分析不再被测试浏览与抓取污染。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_login();
require_perm('settings');

$channels = [
    'bot_ai'     => [' AI 爬虫', 'var(--accent,#2563eb)'],
    'bot_search' => [' 搜索爬虫', '#16a34a'],
    'bot_other'  => ['  其他机器人', '#d97706'],
    'dev'        => ['  开发访问', '#6b7280'],
];

$days = 30;
$since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

// 各通道总量
$totals = [];
foreach (Database::query("SELECT channel, COUNT(*) c, COUNT(DISTINCT uid) u FROM events_bot WHERE created_at >= ? GROUP BY channel", [$since]) as $r) {
    $totals[$r['channel']] = $r;
}

// 近 14 天趋势（按天 × 通道）
$trend = Database::query("SELECT substr(created_at,1,10) d, channel, COUNT(*) c FROM events_bot WHERE created_at >= ? GROUP BY d, channel ORDER BY d", [date('Y-m-d H:i:s', strtotime('-14 days'))]);
$trendByDay = [];
foreach ($trend as $r) $trendByDay[$r['d']][$r['channel']] = (int)$r['c'];

// 爬虫明细：哪个 bot 抓最多
$botNames = Database::query("SELECT channel, ua, COUNT(*) c FROM events_bot WHERE created_at >= ? AND channel LIKE 'bot_%' GROUP BY channel, ua ORDER BY c DESC LIMIT 15", [$since]);

// 被抓最多的页面
$topPages = Database::query("SELECT channel, page, COUNT(*) c FROM events_bot WHERE created_at >= ? AND channel LIKE 'bot_%' GROUP BY channel, page ORDER BY c DESC LIMIT 30", [$since]);

// 开发访问明细（近期）
$devRows = Database::query("SELECT page, ip, ua, created_at FROM events_bot WHERE channel='dev' ORDER BY id DESC LIMIT 20");

if (!defined('OF_EMBED')) admin_header('爬虫洞察');
?>
<?php if (!defined('OF_EMBED')): ?>
<div class="admin-layout">
  <?php admin_sidebar('seo-center'); ?>
  <div class="main">
<?php endif; ?>
    <h1> 爬虫洞察</h1>
    <p class="sub">AI 爬虫与搜索引擎爬虫的抓取独立统计 · 开发访问自动隔离，不再污染用户数据（近 <?=$days?> 天）</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:16px 0 20px">
      <?php foreach ($channels as $k => [$label, $color]): ?>
      <div class="card" style="text-align:center;border-top:3px solid <?=$color?>">
        <div style="font-size:12px;color:var(--text-3)"><?=$label?></div>
        <div style="font-size:26px;font-weight:700"><?=number_format((int)($totals[$k]['c'] ?? 0))?></div>
        <div class="text-sm text-muted">事件 · <?=number_format((int)($totals[$k]['u'] ?? 0))?> 独立访客</div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <h2>  近 14 天趋势</h2>
      <?php if (!$trendByDay): ?>
      <div class="empty" style="padding:20px">暂无数据 —— 爬虫执行 JS 上报或开发访问产生事件后自动出现在这里</div>
      <?php else: ?>
      <div style="overflow-x:auto"><table>
        <thead><tr><th>日期</th><th> AI 爬虫</th><th> 搜索爬虫</th><th>  其他</th><th>  开发</th></tr></thead>
        <tbody>
          <?php foreach (array_reverse($trendByDay) as $d => $row): ?>
          <tr><td><?=$d?></td><td><?=(int)($row['bot_ai'] ?? 0)?></td><td><?=(int)($row['bot_search'] ?? 0)?></td><td><?=(int)($row['bot_other'] ?? 0)?></td><td><?=(int)($row['dev'] ?? 0)?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>  爬虫 UA 排行 <span class="hint">· 哪些 AI/搜索引擎在抓你</span></h2>
      <?php if (!$botNames): ?>
      <div class="empty" style="padding:20px">暂无爬虫事件</div>
      <?php else: ?>
      <div style="overflow-x:auto"><table>
        <thead><tr><th>类型</th><th>User-Agent</th><th>事件数</th></tr></thead>
        <tbody>
          <?php foreach ($botNames as $r): ?>
          <tr>
            <td><?=$channels[$r['channel']][0] ?? $r['channel']?></td>
            <td class="text-sm text-muted" style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['ua'])?></td>
            <td><b><?=number_format((int)$r['c'])?></b></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>  被抓取最多的页面 <span class="hint">· AI/搜索爬虫各取 Top 15</span></h2>
      <?php if (!$topPages): ?>
      <div class="empty" style="padding:20px">暂无数据</div>
      <?php else: ?>
      <div style="overflow-x:auto"><table>
        <thead><tr><th>类型</th><th>页面</th><th>抓取次数</th></tr></thead>
        <tbody>
          <?php foreach ($topPages as $r): ?>
          <tr>
            <td><?=$channels[$r['channel']][0] ?? $r['channel']?></td>
            <td class="text-sm" style="max-width:380px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['page'])?></td>
            <td><?=number_format((int)$r['c'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>  最近开发访问 <span class="hint">· 这些事件已从用户统计中隔离</span></h2>
      <?php if (!$devRows): ?>
      <div class="empty" style="padding:20px">暂无开发访问事件</div>
      <?php else: ?>
      <div style="overflow-x:auto"><table>
        <thead><tr><th>时间</th><th>页面</th><th>IP</th><th>UA</th></tr></thead>
        <tbody>
          <?php foreach ($devRows as $r): ?>
          <tr>
            <td class="text-sm text-muted"><?=htmlspecialchars($r['created_at'])?></td>
            <td class="text-sm"><?=htmlspecialchars($r['page'])?></td>
            <td class="text-sm text-muted"><?=htmlspecialchars($r['ip'])?></td>
            <td class="text-sm text-muted" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($r['ua'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if (!defined('OF_EMBED')) admin_footer(); ?>
