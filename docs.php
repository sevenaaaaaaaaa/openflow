<?php
/**
 * 文档中心 — 产品文档 / 模板库 / 开放 API
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';

// 页面缓存（5 分钟）
if (PageCache::begin('docs', 1800)) exit;

$siteName = site_config_get('site_name', 'OpenFlow');

// 轻量 markdown → HTML
function md_render(string $md): string {
    $md = preg_replace('/\r\n/', "\n", $md);
    $lines = explode("\n", $md);
    $html = '';
    $inList = false; $inCode = false; $code = [];
    foreach ($lines as $line) {
        // 代码块
        if (preg_match('/^```/', $line)) {
            if ($inCode) { $html .= '<pre class="code"><code>' . htmlspecialchars(implode("\n", $code)) . '</code></pre>'; $code = []; $inCode = false; }
            else $inCode = true;
            continue;
        }
        if ($inCode) { $code[] = $line; continue; }
        // 标题
        if (preg_match('/^(#{1,4})\s+(.*)$/', $line, $m)) {
            $lvl = strlen($m[1]);
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $anchor = md_anchor($m[2]);
            $html .= '<h' . min(4, $lvl + 1) . ' id="' . $anchor . '" class="md-h">' . htmlspecialchars($m[2]) . '</h' . min(4, $lvl + 1) . '>';
            continue;
        }
        // 无序列表
        if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
            if (!$inList) { $html .= '<ul class="md-list">'; $inList = true; }
            $html .= '<li>' . md_inline($m[1]) . '</li>';
            continue;
        }
        // 有序列表
        if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<div class="md-p" style="margin:2px 0;padding-left:18px">' . md_inline($m[1]) . '</div>';
            continue;
        }
        if ($inList && trim($line) === '') { $html .= '</ul>'; $inList = false; }
        // 分隔线
        if (preg_match('/^---+\s*$/', $line)) continue;
        // 引用块
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $html .= '<blockquote class="md-quote">' . md_inline($m[1]) . '</blockquote>';
            continue;
        }
        // 段落
        $t = trim($line);
        if ($t !== '') $html .= '<p class="md-p">' . md_inline($t) . '</p>';
    }
    if ($inList) $html .= '</ul>';
    if ($inCode) $html .= '<pre class="code"><code>' . htmlspecialchars(implode("\n", $code)) . '</code></pre>';
    return $html;
}

// 行内格式：粗体 / 行内代码
function md_inline(string $s): string {
    $s = htmlspecialchars($s);
    // 行内代码 `code`（先处理，避免被后续粗体正则影响）
    $s = preg_replace('/`([^`]+)`/', '<code class="md-code">$1</code>', $s);
    // 粗体 **text**
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    return $s;
}

// 标题锚点（生成英文 slug）
function md_anchor(string $title): string {
    $a = strtolower(trim($title));
    $a = preg_replace('/[^\w\-\x{4e00}-\x{9fa5}]+/u', '-', $a);
    $a = trim($a, '-');
    return $a ?: 'section';
}

// 文档索引：自动扫描 docs/*.md
function md_docs_index(): array {
    $dir = __DIR__ . '/md-docs';
    $index = [];
    if (!is_dir($dir)) return $index;
    foreach (glob($dir . '/*.md') as $f) {
        $name = basename($f, '.md');
        $title = $name;
        // 尝试从文件首个 # 标题提取更友好的标题
        $content = @file_get_contents($f) ?: '';
        if (preg_match('/^#\s+(.+)$/m', $content, $m)) $title = trim($m[1]);
        $index[$name] = ['title' => $title, 'size' => strlen($content), 'modified' => date('Y-m-d', filemtime($f))];
    }
    return $index;
}

$docIndex = md_docs_index();

// 读取当前文档（支持 ?doc=NAME 或 #anchor）
$requestedDoc = trim($_GET['doc'] ?? 'FEATURES');
if (isset($_GET['doc'])) $requestedDoc = basename($requestedDoc, '.md');
if (!isset($docIndex[$requestedDoc])) $requestedDoc = 'FEATURES';
$docName = $requestedDoc;
$docMd = @file_get_contents(__DIR__ . '/md-docs/' . $docName . '.md') ?: '';
$docTitle = $docIndex[$docName]['title'] ?? $docName;

// API 端点列表
$apiEndpoints = [
    ['path' => '/api/form-submit', 'method' => 'POST', 'desc' => '统一表单提交：线索入库 + CRM + 通知 + 数据流', 'params' => 'form_slug + 字段（或 slug + data JSON）'],
    ['path' => '/api/community', 'method' => 'GET/POST', 'desc' => '论坛：topics / posts 拉取，create_post / vote 操作', 'params' => 'action, topic, title, content'],
    ['path' => '/api/articles', 'method' => 'GET', 'desc' => '文章列表：type=list 按分类/标签筛选', 'params' => 'type, category, tag, limit'],
    ['path' => '/api/member', 'method' => 'POST', 'desc' => '会员：注册 / 登录 / 登出 / 申请讲师', 'params' => 'action, account, password'],
    ['path' => '/api/track', 'method' => 'POST', 'desc' => '统一行为埋点：page_view / button_click / form_submit 等', 'params' => 'event, props, label'],
    ['path' => '/api/newsletter', 'method' => 'POST', 'desc' => 'Newsletter 订阅', 'params' => 'email, source'],
    ['path' => '/api/download', 'method' => 'POST', 'desc' => '资料下载门禁：验证后返回下载链接', 'params' => 'download_id, name, email, company'],
    ['path' => '/api/conversion', 'method' => 'GET', 'desc' => '转化组件配置：top_bar / bottom_cta / popup', 'params' => '—'],
    ['path' => '/api/site-structure', 'method' => 'GET', 'desc' => '站点结构：全局导航 / 页脚 / 自定义页面', 'params' => '—'],
    ['path' => '/api/landing', 'method' => 'GET', 'desc' => '聚合页数据：slug → 页面 + 聚合文章', 'params' => 'slug'],
    ['path' => '/api/search', 'method' => 'GET', 'desc' => '站内搜索：文章 / 课程 / 资料', 'params' => 'q'],
];

// 模板库
$inlineCta = json_read(DATA_DIR . '/conversion.json')['inline_cta'] ?? [];
$templates = [
    ['name' => '预约诊断表单', 'type' => '线索转化', 'desc' => '姓名/企业/职位/联系方式/问题 → CRM 线索自动建档', 'usage' => '表单提交 /api/form-submit（form_slug=appointment）'],
    ['name' => 'Newsletter 订阅框', 'type' => '内容订阅', 'desc' => '邮箱订阅，自动写入订阅列表并触发欢迎邮件', 'usage' => 'POST /api/newsletter {email}'],
    ['name' => '资料下载门禁', 'type' => '线索转化', 'desc' => '白皮书/报告门禁，填表后返回下载链接', 'usage' => 'downloads.php 卡片 + POST /api/download'],
    ['name' => '顶部通知条', 'type' => '全局组件', 'desc' => '全站置顶通知，可关闭，可埋点', 'usage' => '后台「转化组件」启用 top_bar'],
    ['name' => '底部 CTA 区块', 'type' => '全局组件', 'desc' => '页面底部转化区块，标题+描述+按钮', 'usage' => '后台「转化组件」启用 bottom_cta'],
    ['name' => '弹窗（含内嵌表单）', 'type' => '全局组件', 'desc' => '定时/滚动/离开触发弹窗，可关联表单', 'usage' => '后台「转化组件」启用 popup'],
];
if ($inlineCta['enabled'] ?? false) {
    $templates[] = ['name' => '文中 CTA 模板', 'type' => '内容组件', 'desc' => ($inlineCta['default_title'] ?? '') . ' — ' . ($inlineCta['default_description'] ?? ''), 'usage' => '文章编辑器中插入 {{title}} {{button}} 模板'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>文档中心 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="产品文档 · 模板库 · 开放 API，快速上手 OpenFlow">
<link rel="stylesheet" href="/assets/tailwind-build.css?v=20260813ad">
<script src="/assets/inject.js?v=20260830b" defer></script>
<style>
  body{background:var(--bg);font-family:var(--font-body)}
  .doc-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:22px;transition:.15s}
  .doc-card:hover{box-shadow:var(--shadow-sm)}
  .acd-tab{padding:8px 18px;border-radius:999px;font-size:13.5px;font-weight:600;text-decoration:none;transition:.15s}
  .md-h{font-size:17px;font-weight:800;margin:18px 0 8px;color:var(--fg)}
  .md-p{font-size:13.5px;line-height:1.8;color:var(--muted);margin:6px 0}
  .md-list{margin:6px 0 6px 18px;font-size:13.5px;line-height:1.9;color:var(--muted);list-style:disc}
  .code{background:var(--accent);color:var(--on-accent);border-radius:var(--r-sm);padding:14px;font-size:12.5px;overflow-x:auto;line-height:1.7;margin:8px 0}
  .api-row{display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--bg-soft)}
  .api-method{flex-shrink:0;font-family:ui-monospace,Menlo,monospace;font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:6px}
  .get{background:var(--ok-soft);color:var(--ok)}.post{background:var(--accent-soft);color:var(--accent)}
  .md-code{background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:1px 6px;font-size:12px;font-family:ui-monospace,Menlo,monospace}
  .md-quote{border-left:3px solid var(--accent);background:var(--bg-soft);padding:8px 14px;margin:8px 0;font-size:13px;color:var(--muted);border-radius:0 8px 8px 0}
  .doc-side{position:sticky;top:90px;max-height:calc(100vh - 120px);overflow-y:auto}
  .doc-side a{display:block;padding:8px 12px;border-radius:var(--r-sm);font-size:13px;color:var(--muted);text-decoration:none;transition:.12s}
  .doc-side a:hover{background:var(--bg)}
  .doc-side a.active{background:var(--accent);color:var(--on-accent);font-weight:600}
  .doc-side .grp{font-size:10.5px;font-weight:800;letter-spacing:.04em;color:var(--faint);text-transform:uppercase;padding:14px 12px 4px}
  .doc-content h1{font-size:24px;font-weight:800;color:var(--fg);margin:4px 0 14px}
  .doc-content h2{font-size:18px;font-weight:800;color:var(--fg);margin:22px 0 8px}
  .doc-content h3{font-size:15px;font-weight:700;color:var(--fg);margin:16px 0 6px}

  /* 设计语言统一：token 语义工具类（终版契约） */
  .text-faint{color:var(--faint)}.text-muted{color:var(--muted)}.text-fg{color:var(--fg)}
  .text-ok{color:var(--ok)}.text-accent{color:var(--accent)}.text-danger{color:var(--danger)}
  .bg-surface{background:var(--surface)}
</style>
</head>
<body class="min-h-screen">
<script src="/assets/site-shell.js?v=20260901a" data-cfasync="false" data-page="docs"></script>

<section style="padding:clamp(20px,4vw,44px) 0 clamp(28px,4vw,48px)">
  <div class="mx-auto px-5" style="max-width:1120px">
    <div style="display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,48px);align-items:center">
      <div style="display:flex;flex-direction:column;gap:16px">
        <span class="kicker" style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;color:var(--accent);text-transform:uppercase">DOCUMENTATION</span>
        <h1 style="font-size:clamp(30px,4.5vw,46px);font-weight:800;letter-spacing:-.035em;line-height:1.1;color:var(--fg)">文档中心<span style="font-family:var(--font-display);font-style:italic">查得到、看得懂</span></h1>
        <p style="color:var(--muted);font-size:15px;line-height:1.8;max-width:540px">产品文档 · 模板库 · 开放 API。从快速上手到开发者扩展，一页页讲清楚。</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:4px">
          <a href="#docs" style="padding:12px 24px;border-radius:999px;font-weight:700;font-size:14px;background:var(--accent);color:var(--on-accent);text-decoration:none"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span> 产品文档</a>
          <a href="#templates" style="padding:12px 24px;border-radius:999px;font-weight:700;font-size:14px;background:var(--surface);color:var(--fg);border:1px solid var(--border);text-decoration:none"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></span> 模板库</a>
          <a href="#api" style="padding:12px 24px;border-radius:999px;font-weight:700;font-size:14px;background:var(--surface);color:var(--fg);border:1px solid var(--border);text-decoration:none"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3v6M15 3v6M7 9h10v4a5 5 0 0 1-10 0V9Z"/><path d="M12 18v3"/></svg></span> 开放 API</a>
        </div>
        <div style="display:flex;gap:18px;margin-top:8px;color:var(--faint);font-size:12.5px;flex-wrap:wrap">
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span> <b style="color:var(--fg)"><?=count($docIndex)?></b> 篇文档</span>
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3v6M15 3v6M7 9h10v4a5 5 0 0 1-10 0V9Z"/><path d="M12 18v3"/></svg></span> <b style="color:var(--fg)">REST</b> API</span>
          <span><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></span> <b style="color:var(--fg)">模板</b>库</span>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <?php
        $docCards = array_slice($docIndex, 0, 4);
        $docIcons = ['🗂️', '🚀', '🧩', '🔌'];
        $di = 0;
        foreach ($docCards as $dn => $dv): ?>
        <a href="?doc=<?=htmlspecialchars($dn)?>" style="display:flex;flex-direction:column;gap:10px;padding:18px;border-radius:var(--r-md);background:var(--surface);border:1px solid var(--border);backdrop-filter:blur(16px) saturate(150%);text-decoration:none;transition:transform .25s var(--ease-spring),box-shadow .25s,border-color .25s" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-sm)';this.style.borderColor='var(--border-strong)'" onmouseout="this.style.transform='none';this.style.boxShadow='none';this.style.borderColor='var(--border)'">
          <span style="width:38px;height:38px;border-radius:12px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:18px"><?=$docIcons[$di] ?? '📄'?></span>
          <b style="font-size:14.5px;color:var(--fg)"><?=htmlspecialchars($dv['title'])?></b>
          <span style="font-size:11.5px;color:var(--faint)"><?=htmlspecialchars($dv['modified'])?> · <?=round($dv['size']/1024, 1)?>KB</span>
        </a>
        <?php $di++; endforeach; ?>
      </div>
    </div>
  </div>
</section>

<div class="mx-auto px-5 py-10" style="max-width:1000px">
  <!-- 产品文档 -->
  <div id="docs">
    <h2 class="text-2xl font-extrabold text-fg mb-5"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-4"/></svg></span> 产品文档</h2>
    <div style="display:grid;gap:28px;grid-template-columns:220px 1fr;align-items:start">
      <!-- 文档索引 -->
      <aside class="doc-side doc-card" style="padding:14px">
        <div class="grp">文档目录</div>
        <?php foreach ($docIndex as $name => $meta): ?>
        <a href="docs?doc=<?=$name?>" class="<?=$name===$docName?'active':''?>" title="<?=htmlspecialchars($meta['title'])?>"><?=htmlspecialchars($meta['title'])?></a>
        <?php endforeach; ?>
        <div class="grp">跳转</div>
        <a href="#templates"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></span> 模板库</a>
        <a href="#api"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3v6M15 3v6M7 9h10v4a5 5 0 0 1-10 0V9Z"/><path d="M12 18v3"/></svg></span> 开放 API</a>
      </aside>

      <!-- 当前文档内容 -->
      <article class="doc-card doc-content" style="min-height:420px">
        <div class="text-[11px] font-bold tracking-wide mb-2" style="color:var(--ok)"><?=htmlspecialchars($docName)?>.md · <?=$docIndex[$docName]['modified'] ?? ''?></div>
        <?php if (trim($docMd) === ''): ?>
          <div class="md-p" style="padding:40px 0;text-align:center;color:var(--faint)">该文档暂无内容</div>
        <?php else: ?>
          <?=md_render($docMd)?>
        <?php endif; ?>
      </article>
    </div>
  </div>

  <!-- 模板库 -->
  <div id="templates" style="margin-top:44px">
    <h2 class="text-2xl font-extrabold text-fg mb-5"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4a2 2 0 0 1 4 0v1h3a1 1 0 0 1 1 1v3h1a2 2 0 0 1 0 4h-1v3a1 1 0 0 1-1 1h-3v1a2 2 0 0 1-4 0v-1H6a1 1 0 0 1-1-1v-3H4a2 2 0 0 1 0-4h1V6a1 1 0 0 1 1-1h3V4Z"/></svg></span> 模板库</h2>
    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
      <?php foreach ($templates as $t): ?>
      <div class="doc-card">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full" style="background:var(--bg);color:var(--muted)"><?=htmlspecialchars($t['type'])?></span>
          <h3 class="font-extrabold text-[15px]"><?=htmlspecialchars($t['name'])?></h3>
        </div>
        <p class="text-[13px] text-muted leading-relaxed mb-3"><?=htmlspecialchars($t['desc'])?></p>
        <div class="text-[12px] font-mono" style="background:var(--bg);border-radius:var(--r-sm);padding:8px 10px;color:var(--muted)"><?=htmlspecialchars($t['usage'])?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 开放 API -->
  <div id="api" style="margin-top:44px">
    <h2 class="text-2xl font-extrabold text-fg mb-2"><span class="ic emj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3v6M15 3v6M7 9h10v4a5 5 0 0 1-10 0V9Z"/><path d="M12 18v3"/></svg></span> 开放 API</h2>
    <p class="text-[13.5px] text-muted mb-5">统一 JSON 接口，均支持跨域（Access-Control-Allow-Origin: *），可用于对接 CRM、数据分析工具等。</p>
    <div class="doc-card" style="padding:18px 22px">
      <?php foreach ($apiEndpoints as $ep): ?>
      <div class="api-row">
        <span class="api-method <?=strtoupper($ep['method'])==='GET'?'get':'post'?>"><?=htmlspecialchars($ep['method'])?></span>
        <div class="min-w-0 flex-1">
          <code style="font-size:13px;font-weight:700;color:var(--fg)"><?=htmlspecialchars($ep['path'])?></code>
          <div class="text-[13px] text-muted mt-0.5"><?=htmlspecialchars($ep['desc'])?></div>
          <div class="text-[11.5px] text-faint mt-0.5 font-mono"><?=htmlspecialchars($ep['params'])?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="doc-card mt-4">
      <h3 class="font-extrabold mb-2">调用示例</h3>
      <pre class="code">// 提交预约线索
fetch('/api/form-submit', {
  method: 'POST',
  body: new URLSearchParams({
    form_slug: 'appointment',
    name: '张三', company: '示例公司',
    contact: '13800000000', note: '想了解增长诊断'
  })
});</pre>
    </div>
  </div>
</div>

  <!-- CTA band -->
  <div style="background:linear-gradient(135deg,var(--accent),oklch(58% .16 295));border-radius:var(--r-lg);padding:clamp(28px,4vw,48px);color:#fff;margin-top:44px;text-align:center">
    <div style="font-family:var(--font-mono);font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;opacity:.75">芭乐派 · OpenFlow</div>
    <h2 style="font-size:clamp(24px,3vw,32px);font-weight:800;letter-spacing:-.02em;margin:10px 0 8px">文档看懂了，系统该动手设计了</h2>
    <p style="opacity:.85;font-size:14.5px;line-height:1.7;max-width:560px;margin:0 auto 22px">工具在文档，方法论在课程，落地在你的增长系统。装完 OpenFlow，先从 New-1 开始。</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="/courses" style="padding:12px 26px;border-radius:999px;font-weight:700;font-size:14px;background:#fff;color:var(--accent);text-decoration:none">开始学习 New-1</a>
      <a href="/community" style="padding:12px 26px;border-radius:999px;font-weight:700;font-size:14px;background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.6);text-decoration:none">进门派问问</a>
    </div>
  </div>
</div>

<footer class="pt-10 pb-8 mt-10" style="background:var(--bg-soft);border-top:1px solid var(--border);color:var(--fg)">
  <div class="mx-auto px-5" style="max-width:1120px">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:28px;padding-bottom:22px;border-bottom:1px solid var(--border)">
      <div>
        <div style="font-weight:800;font-size:15px;color:var(--fg)">芭乐派 · OpenFlow</div>
        <p style="font-size:12.5px;color:var(--muted);line-height:1.7;margin-top:8px;max-width:320px">芭乐派增长操作系统的开源底座。TIPS 框架（触达/洞察/个性化/销售）四力合一，自生长 AI Engine 主动驱动增长。</p>
        <p style="font-size:12px;color:var(--faint);margin-top:6px">核心能力永久开源 · 鱼与渔相结合</p>
      </div>
      <div>
        <div style="font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:10px">站点导航</div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <a href="/product" style="color:var(--muted);text-decoration:none;font-size:13px">产品</a>
          <a href="/capability" style="color:var(--muted);text-decoration:none;font-size:13px">能力</a>
          <a href="/courses" style="color:var(--muted);text-decoration:none;font-size:13px">课程</a>
          <a href="/academy" style="color:var(--muted);text-decoration:none;font-size:13px">学院</a>
          <a href="/about" style="color:var(--muted);text-decoration:none;font-size:13px">关于我们</a>
        </div>
      </div>
      <div>
        <div style="font-family:var(--font-mono);font-size:10.5px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:10px">资源</div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <a href="/docs" style="color:var(--muted);text-decoration:none;font-size:13px">文档中心</a>
          <a href="/downloads" style="color:var(--muted);text-decoration:none;font-size:13px">资料下载</a>
          <a href="/podcasts" style="color:var(--muted);text-decoration:none;font-size:13px">播客</a>
          <a href="/marketplace" style="color:var(--muted);text-decoration:none;font-size:13px">生态市场</a>
          <a href="/community" style="color:var(--muted);text-decoration:none;font-size:13px">门派社区</a>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding-top:16px;flex-wrap:wrap">
      <div style="font-size:12px;color:var(--muted)">© 2026 芭乐派 · OpenFlow 增长操作系统</div>
      <div style="font-size:12px;color:var(--faint)">帮一人公司设计 Agent 能跑的增长系统</div>
    </div>
  </div>
</footer>
</body>
</html>
<?php PageCache::end('docs', 1800); ?>
