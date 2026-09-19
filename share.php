<?php
/**
 * 只读分享页 —— /s/{token}
 *
 * 无需登录：按 token 找到「项目 + 视图」，只渲染白名单字段。
 * 安全约定：noindex + no-store + no-referrer（token 不能进搜索引擎、缓存、referrer）；
 * 数据全部来自 ps_share_payload() 的白名单模型，本页不读原始任务数组。
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/ProjectSystem.php';
require_once __DIR__ . '/lib/SiteConfig.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
// 分享页绝不进任何缓存：no-store 之外还要 private + Vary + Set-Cookie——
// 实测仅 no-store 时 Cloudflare 仍会缓存（cf-cache-status: HIT），撤销后链接还能被打开。
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Vary: Cookie, Authorization');
header('Set-Cookie: of_share_view=1; Path=/; SameSite=Lax; HttpOnly');

$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['t'] ?? '')) ?? '';
$data = $token !== '' ? ps_share_payload($token) : null;

if ($data === null) {
    http_response_code(404);
    ?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>分享链接无效 · 芭乐派</title>
<link rel="stylesheet" href="/assets/fonts/fonts.css">
<link rel="stylesheet" href="/assets/tokens.css?v=<?=defined('OF_SHELL_VER') ? OF_SHELL_VER : '1'?>">
<style>body{font-family:var(--font-sans,system-ui);background:var(--bg,#f7f6f3);color:var(--text,#1b1b1b);margin:0}
.sh-fail{max-width:560px;margin:14vh auto;padding:0 24px;text-align:center}
.sh-fail h1{font-size:22px;margin:0 0 10px}
.sh-fail p{color:var(--muted,#666);line-height:1.8;font-size:14px}
.sh-fail a{color:var(--accent,#2f6fed)}</style>
</head>
<body>
<div class="sh-fail">
  <h1>这个分享链接已经不可用</h1>
  <p>链接可能被创建者撤销，或者已过有效期。如果你确实需要查看，请向对方要一个新的链接。</p>
  <p><a href="/">前往芭乐派首页</a></p>
</div>
</body>
</html>
    <?php
    exit;
}

$prioCls = ['urgent' => 'p-urgent', 'high' => 'p-high', 'low' => 'p-low'];
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=htmlspecialchars($data['project'])?> · <?=htmlspecialchars($data['view_label'])?>（只读分享）· 芭乐派</title>
<meta name="description" content="<?=htmlspecialchars($data['project'])?> 的<?=htmlspecialchars($data['view_label'])?>视图（只读分享）。">
<link rel="stylesheet" href="/assets/fonts/fonts.css">
<link rel="stylesheet" href="/assets/tokens.css?v=<?=defined('OF_SHELL_VER') ? OF_SHELL_VER : '1'?>">
<style>
:root{--sh-r:12px}
body{margin:0;background:var(--bg);color:var(--text);font-family:var(--font-sans,system-ui);-webkit-font-smoothing:antialiased}
.sh-wrap{max-width:1180px;margin:0 auto;padding:26px 22px 60px}
.sh-head{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start;padding-bottom:16px;border-bottom:1px solid var(--border);margin-bottom:22px}
.sh-head h1{margin:0;font-size:20px;letter-spacing:-.01em}
.sh-sub{margin:6px 0 0;font-size:12.5px;color:var(--muted)}
.sh-badge{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:99px;border:1px solid var(--border);color:var(--muted)}
.sh-stats{margin-left:auto;display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;color:var(--muted)}
.sh-stats b{color:var(--text)}
.sh-stats b.over{color:#c0392b}
/* 看板 */
.sh-board{display:flex;gap:14px;overflow-x:auto;padding-bottom:12px;align-items:stretch}
.sh-col{min-width:240px;max-width:300px;flex:1;min-height:150px;background:var(--surface-2,rgba(0,0,0,.03));border:1px solid var(--border);border-radius:14px;padding:11px}
.sh-col h2{margin:2px 0 10px;font-size:12.5px;display:flex;align-items:center;gap:8px}
.sh-col h2 span{margin-left:auto;font-size:11px;color:var(--muted);font-weight:600}
.sh-card{background:var(--surface,#fff);border:1px solid var(--border);border-radius:var(--sh-r);padding:10px 11px;margin-bottom:8px}
.sh-card b{font-size:13px;font-weight:650;line-height:1.45;display:block}
.sh-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:7px;font-size:11.5px;color:var(--muted)}
.sh-due.over{color:#c0392b;font-weight:700}
.sh-pill{font-size:10.5px;font-weight:700;padding:1px 8px;border-radius:99px;background:rgba(0,0,0,.06);color:var(--muted)}
.sh-pill.p-urgent{background:rgba(192,57,43,.14);color:#a5301f}
.sh-pill.p-high{background:rgba(214,137,16,.18);color:#9a6410}
.sh-prog{margin-top:7px}
.sh-prog .bar{height:5px;border-radius:99px;background:rgba(0,0,0,.08);overflow:hidden}
.sh-prog .fill{display:block;height:100%;border-radius:99px;background:var(--accent)}
.sh-prog .txt{margin-top:4px;font-size:11px;color:var(--muted)}
/* 表格 / 日历 / 甘特 / 树 */
.sh-table{width:100%;border-collapse:collapse;font-size:13px}
.sh-table th,.sh-table td{padding:9px 10px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
.sh-table th{font-size:11.5px;color:var(--muted);font-weight:700;white-space:nowrap}
.sh-cal{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
.sh-cal .h{text-align:center;font-size:11.5px;color:var(--muted);font-weight:700;padding:2px 0}
.sh-cal .c{min-height:92px;background:var(--surface-2,rgba(0,0,0,.03));border-radius:8px;padding:6px 7px}
.sh-cal .c.empty{background:transparent}
.sh-cal .d{font-size:11.5px;color:var(--muted);margin-bottom:4px}
.sh-cal .e{background:var(--surface,#fff);border:1px solid var(--border);border-radius:6px;padding:3px 6px;font-size:11.5px;margin-bottom:4px}
.sh-gantt{border:1px solid var(--border);border-radius:var(--sh-r);overflow:hidden}
.sh-gantt .row{display:grid;grid-template-columns:230px 1fr;align-items:center;border-bottom:1px solid var(--border);min-height:38px}
.sh-gantt .row:last-child{border-bottom:none}
.sh-gantt .lab{font-size:12.5px;padding:6px 10px;border-right:1px solid var(--border)}
.sh-gantt .lab i{display:block;font-size:11px;color:var(--muted);font-style:normal;margin-top:2px}
.sh-gantt .track{position:relative;height:34px}
.sh-gantt .bar{position:absolute;top:10px;height:14px;border-radius:7px;background:var(--accent);opacity:.85}
.sh-gantt .bar.ms{width:12px;height:12px;border-radius:50%;top:11px}
.sh-gantt .scale{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);padding:6px 10px 0}
.sh-tree .node{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:7px 10px;border:1px solid var(--border);border-radius:10px;background:var(--surface,#fff);margin-bottom:5px;font-size:12.5px}
.sh-foot{margin-top:28px;padding-top:14px;border-top:1px solid var(--border);font-size:12px;color:var(--muted);display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.sh-foot a{color:var(--accent)}
@media (max-width:720px){.sh-gantt .row{grid-template-columns:1fr}.sh-gantt .lab{border-right:none;border-bottom:1px solid var(--border)}}
</style>
</head>
<body>
<div class="sh-wrap">
  <div class="sh-head">
    <div>
      <h1><?=htmlspecialchars($data['project'])?></h1>
      <p class="sh-sub"><?=htmlspecialchars($data['view_label'])?>视图 · 生成于 <?=htmlspecialchars($data['generated_at'])?><?=$data['expires'] !== '' ? ' · 链接有效期至 ' . htmlspecialchars($data['expires']) : ''?></p>
    </div>
    <span class="sh-badge">🔒 只读分享</span>
    <div class="sh-stats">
      <span>任务 <b><?=$data['stats']['total']?></b></span>
      <span>已完成 <b><?=$data['stats']['done']?></b></span>
      <span>逾期 <b class="<?=$data['stats']['overdue'] > 0 ? 'over' : ''?>"><?=$data['stats']['overdue']?></b></span>
    </div>
  </div>

  <?php if ($data['view'] === 'board'): ?>
  <div class="sh-board">
    <?php foreach ($data['columns'] as $col): ?>
    <div class="sh-col">
      <h2><?=htmlspecialchars($col['label'])?><span><?=count($col['tasks'])?></span></h2>
      <?php foreach ($col['tasks'] as $t): ?>
      <div class="sh-card">
        <b><?=htmlspecialchars($t['title'])?></b>
        <div class="sh-meta">
          <?php if ($t['priority_label'] !== ''): ?><span class="sh-pill <?=$prioCls[$t['priority']] ?? ''?>"><?=htmlspecialchars($t['priority_label'])?></span><?php endif; ?>
          <?php if ($t['assignee'] !== ''): ?><span>@<?=htmlspecialchars($t['assignee'])?></span><?php endif; ?>
          <?php if ($t['due_has_date']): ?><span class="sh-due<?=$t['overdue'] ? ' over' : ''?>"><?=htmlspecialchars($t['due'])?></span><?php endif; ?>
        </div>
        <?php if ($t['sub_total'] > 0): ?>
        <div class="sh-prog"><div class="bar"><span class="fill" style="width:<?=$t['sub_pct']?>%"></span></div><div class="txt">子任务 <?=$t['sub_done']?>/<?=$t['sub_total']?> · <?=$t['sub_pct']?>%</div></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php elseif ($data['view'] === 'grid'): ?>
  <table class="sh-table">
    <thead><tr><th>任务</th><th>状态</th><th>负责人</th><th>截止</th><th>优先级</th><th>子任务</th></tr></thead>
    <tbody>
      <?php foreach ($data['rows'] as $t): ?>
      <tr>
        <td><b><?=htmlspecialchars($t['title'])?></b></td>
        <td><?=htmlspecialchars($t['status_label'])?></td>
        <td><?=htmlspecialchars($t['assignee'])?></td>
        <td class="sh-due<?=$t['overdue'] ? ' over' : ''?>"><?=htmlspecialchars($t['due'])?></td>
        <td><?php if ($t['priority_label'] !== ''): ?><span class="sh-pill <?=$prioCls[$t['priority']] ?? ''?>"><?=htmlspecialchars($t['priority_label'])?></span><?php endif; ?></td>
        <td><?=$t['sub_total'] > 0 ? ($t['sub_done'] . '/' . $t['sub_total'] . ' · ' . $t['sub_pct'] . '%') : ''?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php elseif ($data['view'] === 'calendar' && !empty($data['calendar'])): $cal = $data['calendar']; ?>
  <div class="sh-cal">
    <?php foreach (['一', '二', '三', '四', '五', '六', '日'] as $d): ?><div class="h"><?=$d?></div><?php endforeach; ?>
    <?php for ($i = 0; $i < $cal['lead']; $i++): ?><div class="c empty"></div><?php endfor; ?>
    <?php foreach ($cal['cells'] as $c): ?>
    <div class="c">
      <div class="d"><?=$c['day']?></div>
      <?php foreach ($c['tasks'] as $t): ?><div class="e"><?=htmlspecialchars($t['title'])?></div><?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($cal['undated'] > 0): ?><p class="sh-sub" style="margin-top:12px">另有 <?=$cal['undated']?> 条任务未排期（未填写截止日）。</p><?php endif; ?>

  <?php elseif ($data['view'] === 'gantt' && !empty($data['gantt'])): $g = $data['gantt']; ?>
  <div class="sh-gantt">
    <div class="scale"><span><?=htmlspecialchars($g['start'])?></span><span><?=htmlspecialchars($g['end'])?>（共 <?=$g['days']?> 天）</span></div>
    <?php foreach ($g['bars'] as $b): ?>
    <div class="row">
      <div class="lab"><?=htmlspecialchars($b['title'])?><i><?=htmlspecialchars($b['status_label'])?></i></div>
      <div class="track"><div class="bar<?=$b['milestone'] ? ' ms' : ''?>" style="left:<?=round($b['offset'] / max(1, $g['days']) * 100, 3)?>%;width:<?=round($b['span'] / max(1, $g['days']) * 100, 3)?>%"></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php elseif ($data['view'] === 'tree' && !empty($data['tree'])): ?>
  <div class="sh-tree">
    <?php foreach ($data['tree'] as $t): ?>
    <div class="node" style="margin-left:<?=(int) $t['depth'] * 22?>px">
      <b><?=htmlspecialchars($t['title'])?></b>
      <span class="sh-pill"><?=htmlspecialchars($t['status_label'])?></span>
      <?php if ($t['priority_label'] !== ''): ?><span class="sh-pill <?=$prioCls[$t['priority']] ?? ''?>"><?=htmlspecialchars($t['priority_label'])?></span><?php endif; ?>
      <?php if ($t['assignee'] !== ''): ?><span style="font-size:11.5px;color:var(--muted)">@<?=htmlspecialchars($t['assignee'])?></span><?php endif; ?>
      <?php if ($t['due_has_date']): ?><span class="sh-due sh-pill<?=$t['overdue'] ? ' over' : ''?>"><?=htmlspecialchars($t['due'])?></span><?php endif; ?>
      <?php if ($t['sub_total'] > 0): ?><span style="font-size:11.5px;color:var(--muted)">子任务 <?=$t['sub_done']?>/<?=$t['sub_total']?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <p class="sh-sub">这个视图暂时没有可展示的内容。</p>
  <?php endif; ?>

  <div class="sh-foot">
    <span>这是只读分享页：内容由项目负责人分享，随时可能被撤销；如需修改请找对方。</span>
    <span style="margin-left:auto">由 <a href="/" rel="noopener">芭乐派 · OpenFlow</a> 生成</span>
  </div>
</div>
</body>
</html>
