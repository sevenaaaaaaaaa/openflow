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

// GEO 回流归因：AI 爬虫抓了哪篇文章 → 按文章聚合（channel=bot_ai 时 page 为文章路径 /article/{slug}）
// props 里存的是 track.php 写入的 JSON（含页面路径），page 字段本身已含路径
$aiRefPages = Database::query("SELECT page, COUNT(*) c, COUNT(DISTINCT uid) u FROM events_bot WHERE created_at >= ? AND channel = 'bot_ai' AND page LIKE '/article/%' GROUP BY page ORDER BY c DESC LIMIT 15", [$since]);
// 引用源域名（props 里的 referrer，爬虫上报时带 referer 的场景）
$aiRefSources = Database::query("SELECT props, COUNT(*) c FROM events_bot WHERE created_at >= ? AND channel='bot_ai' AND props LIKE '%referrer%' GROUP BY props ORDER BY c DESC LIMIT 10", [$since]);
$aiRefCount = 0;
$aiRefArticles = [];   // slug => ['title'=>, 'c'=>, 'u'=>]
try {
    $slugs = [];
    foreach ((array)$aiRefPages as $r) {
        $aiRefCount += (int)$r['c'];
        if (preg_match('#^/article/([a-z0-9\x{4e00}-\x{9fff}-]+)#u', (string)$r['page'], $m)) {
            $slug = $m[1];
            $aiRefArticles[$slug] = ['c' => (int)$r['c'], 'u' => (int)$r['u']];
        }
    }
    if ($aiRefArticles) {
        $titles = [];
        foreach (get_articles() as $a) $titles[$a['slug']] = $a['title'] ?? '';
        foreach ($aiRefArticles as $slug => &$row) $row['title'] = $titles[$slug] ?? $slug;
        unset($row);
    }
} catch (Throwable $e) {}

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

    <!-- GEO 回流归因：AI 爬虫抓了哪篇文章 -->
    <div class="card">
      <h2>🎯 AI 引用归因 <span class="hint">· AI 爬虫抓了哪些文章 · 回流链路追踪</span></h2>
      <?php if (!$aiRefArticles): ?>
      <div class="empty" style="padding:20px">暂无 AI 爬虫抓取文章的记录——文章被 GPTBot/ClaudeBot/PerplexityBot 等抓取后自动出现在这里</div>
      <?php else: ?>
      <div style="overflow-x:auto"><table>
        <thead><tr><th>文章</th><th>被 AI 抓取次数</th><th>独立 AI</th><th>占比</th></tr></thead>
        <tbody>
          <?php foreach ($aiRefArticles as $slug => $row): ?>
          <tr>
            <td><a href="/article/<?=htmlspecialchars($slug)?>" style="color:var(--accent);text-decoration:none"><?=htmlspecialchars($row['title'])?></a></td>
            <td><b><?=number_format($row['c'])?></b></td>
            <td class="text-sm text-muted"><?=$row['u']?></td>
            <td class="text-sm text-muted"><?=($aiRefCount>0)?round($row['c']/$aiRefCount*100,1):0?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <p class="text-sm text-muted" style="margin-top:10px">共计 <b><?=number_format($aiRefCount)?></b> 次 AI 爬虫抓取命中文章页。回流归因显示"哪篇文章被哪个 AI 爬虫抓得最多"，帮助判断哪些内容更容易被 AI 引用。</p>
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
