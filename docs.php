<?php
/**
 * 文档中心 — 产品文档 / 模板库 / 开放 API
 *
 * v7（2026-09-01）：迁到共享 archetype。md_render 输出接到共享 .prose（.md-* 类保留做兼容），
 * 文档目录用 .g-main-aside.aside-left + .cat-nav，API 表用 hairline 行。数据逻辑原样保留。
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
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>文档中心 | <?=htmlspecialchars($siteName)?></title>
<meta name="description" content="产品文档 · 模板库 · 开放 API，快速上手 OpenFlow">
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 文档中心独有：目录侧栏、API 行。markdown 输出的 .md-* 类映射到 .prose 的排版。 */
.g-main-aside.aside-left{grid-template-columns:minmax(0,230px) minmax(0,1fr)}
.g-main-aside.aside-left>aside{position:sticky;top:calc(var(--chrome-h) + 24px);max-height:calc(100vh - 120px);overflow-y:auto}
.cat-nav{display:flex;flex-direction:column;gap:2px}
.cat-nav a{display:block;padding:8px 12px;border-radius:10px;font-size:13.5px;color:var(--muted);transition:background .15s,color .15s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cat-nav a:hover{background:var(--hover);color:var(--fg)}
.cat-nav a.active{background:var(--accent-soft);color:var(--accent-strong);font-weight:600}
.doc-meta{font-family:var(--font-mono);font-size:11.5px;color:var(--ok);font-weight:700;letter-spacing:.04em;margin-bottom:14px}
.prose .md-h{font-weight:800}
.prose h2.md-h{font-size:22px}.prose h3.md-h{font-size:18px}.prose h4.md-h{font-size:16px}
.prose .md-p{color:var(--muted)}
.prose .md-list{list-style:disc}
.prose .md-quote{border-left:3px solid var(--accent);background:var(--accent-soft);padding:12px 16px;border-radius:0 12px 12px 0;color:var(--muted)}
.prose .md-code{font-family:var(--font-mono);font-size:.92em;background:var(--hover);padding:2px 6px;border-radius:6px}
.prose pre.code{background:var(--surface-strong);border:1px solid var(--border);color:var(--fg)}
.api-row{display:grid;grid-template-columns:60px minmax(0,1fr);gap:16px;align-items:start;padding:16px 4px;border-bottom:1px solid var(--border-soft)}
.api-row:last-child{border-bottom:none}
.api-method{font-family:var(--font-mono);font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px;text-align:center}
.api-method.get{background:var(--ok-soft);color:var(--ok)}.api-method.post{background:var(--accent-soft);color:var(--accent)}
.api-row code{font-family:var(--font-mono);font-size:13.5px;font-weight:700}
.api-row .d{font-size:13.5px;color:var(--muted);margin-top:3px}
.api-row .p{font-family:var(--font-mono);font-size:11.5px;color:var(--faint);margin-top:3px}
.tpl{display:flex;flex-direction:column;gap:10px}
.tpl .hd{display:flex;align-items:center;gap:8px}
.tpl h3{font-size:15.5px;font-weight:800}
.tpl p{font-size:13.5px;color:var(--muted);line-height:1.7}
.tpl .use{font-family:var(--font-mono);font-size:12px;background:var(--hover);border-radius:var(--r-sm);padding:8px 10px;color:var(--muted);margin-top:auto}
@media (max-width:1080px){.g-main-aside.aside-left{grid-template-columns:1fr}.g-main-aside.aside-left>aside{position:static;max-height:none}.cat-nav{flex-direction:row;flex-wrap:wrap}}
</style>
<script src="/assets/inject.js?v=20260830b" defer></script>
</head>
<body data-of-main>
<?php of_shell('docs'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <section id="top" class="reveal in" data-od-anchor data-od-id="docs-hero">
    <div class="hero">
      <div class="hero-copy">
        <span class="kicker">DOCUMENTATION</span>
        <h1>文档中心<br><i class="si">查得到、看得懂</i></h1>
        <p class="lead">产品文档 · 模板库 · 开放 API。从快速上手到开发者扩展，一页页讲清楚。</p>
        <div class="cta-row"><a class="btn primary" href="#docs">产品文档</a><a class="btn ghost" href="#templates">模板库</a><a class="btn ghost" href="#api">开放 API</a></div>
      </div>
      <div class="hero-win">
        <div class="win-bar"><span class="light light-r"></span><span class="light light-y"></span><span class="light light-g"></span><div class="url">docs · <?=count($docIndex)?> 篇</div></div>
        <div class="win-flow">
          <?php $docCards = array_slice($docIndex, 0, 4); $k = 0; foreach ($docCards as $dn => $dv): if ($k++) echo '<div class="flow-link"></div>'; ?>
          <a class="flow-row" href="?doc=<?=htmlspecialchars($dn)?>"><span class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg></span><div><div class="ft"><?=htmlspecialchars($dv['title'])?></div><div class="fd"><?=htmlspecialchars($dv['modified'])?> · <?=round($dv['size']/1024, 1)?>KB</div></div></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section id="docs" class="sec reveal" data-od-anchor data-od-id="docs-body">
    <div class="g-main-aside aside-left">
      <aside>
        <div class="aside-box">
          <h3>文档目录</h3>
          <nav class="cat-nav" aria-label="文档目录">
            <?php foreach ($docIndex as $name => $meta): ?>
            <a href="docs?doc=<?=$name?>" class="<?=$name===$docName?'active':''?>" title="<?=htmlspecialchars($meta['title'])?>"><?=htmlspecialchars($meta['title'])?></a>
            <?php endforeach; ?>
          </nav>
        </div>
        <div class="aside-box">
          <h3>跳转</h3>
          <nav class="cat-nav" aria-label="跳转"><a href="#templates">模板库</a><a href="#api">开放 API</a></nav>
        </div>
      </aside>
      <article class="card" style="min-height:420px">
        <div class="doc-meta"><?=htmlspecialchars($docName)?>.md · <?=$docIndex[$docName]['modified'] ?? ''?></div>
        <?php if (trim($docMd) === ''): ?>
          <div class="empty">该文档暂无内容</div>
        <?php else: ?>
          <div class="prose"><?=md_render($docMd)?></div>
        <?php endif; ?>
      </article>
    </div>
  </section>

  <section id="templates" class="sec reveal" data-od-anchor data-od-id="docs-templates">
    <div class="sec-head row"><div><span class="kicker">模板库</span><h2>拿来就用的模板</h2></div></div>
    <div class="grid g3" style="gap:16px">
      <?php foreach ($templates as $t): ?>
      <div class="card tpl">
        <div class="hd"><span class="pill neutral" style="height:24px"><?=htmlspecialchars($t['type'])?></span><h3><?=htmlspecialchars($t['name'])?></h3></div>
        <p><?=htmlspecialchars($t['desc'])?></p>
        <div class="use"><?=htmlspecialchars($t['usage'])?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="api" class="sec reveal" data-od-anchor data-od-id="docs-api">
    <div class="sec-head row"><div><span class="kicker">开放 API</span><h2>统一 JSON 接口</h2></div></div>
    <p class="lead" style="color:var(--muted);font-size:14.5px">统一 JSON 接口，均支持跨域（Access-Control-Allow-Origin: *），可用于对接 CRM、数据分析工具等。</p>
    <div class="card">
      <?php foreach ($apiEndpoints as $ep): ?>
      <div class="api-row">
        <span class="api-method <?=strtoupper($ep['method'])==='GET'?'get':'post'?>"><?=htmlspecialchars($ep['method'])?></span>
        <div><code><?=htmlspecialchars($ep['path'])?></code><div class="d"><?=htmlspecialchars($ep['desc'])?></div><div class="p"><?=htmlspecialchars($ep['params'])?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="card prose">
      <h3>调用示例</h3>
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
  </section>

  <section class="reveal" data-od-anchor data-od-id="docs-cta">
    <div class="cta-band">
      <span class="kicker">芭乐派 · OpenFlow</span>
      <h2>文档看懂了，系统该动手设计了</h2>
      <p class="lead">工具在文档，方法论在课程，落地在你的增长系统。装完 OpenFlow，先从 New-1 开始。</p>
      <div class="cta-row"><a href="/courses" class="btn primary">开始学习 New-1</a><a href="/community" class="btn ghost">进门派社区</a></div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>
<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button>
</body>
</html>
<?php PageCache::end('docs', 1800); ?>
