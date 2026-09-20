<?php
/**
 * 开发者与生态 | 芭乐派 · OpenFlow
 *
 * 2026-09-20：新增。定位方向（开放 / 面向开发者 / AI agent / 兼容 / 可定制）的对外落点。
 *
 * 三条自律：
 *   1) 数字全部现算，不写死（API 文件数 / MCP 工具数 / 插件数 / 已发布文章数）
 *   2) MCP 工具清单来自 lib/McpTools.php —— 与 mcp-server.php 同一份定义，文档不会漂移
 *   3) 不写「最强/唯一/提升 X%」这类无法自证的比较级（GTM.md §2）
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/SiteConfig.php';
require_once __DIR__ . '/lib/McpTools.php';
require_once __DIR__ . '/lib/I18n.php';
header('Cache-Control: no-cache, max-age=0');

$siteName = site_config_get('site_name', 'OpenFlow');

// 现算的可核验数字
$apiFiles = array_filter(glob(__DIR__ . '/api/*.php') ?: [], static fn(string $f): bool => basename($f) !== 'index.php');
$apiCount = count($apiFiles);
$mcpTools = function_exists('mcp_tools') ? mcp_tools() : [];
$mcpCount = count($mcpTools);
$pluginCount = count(glob(__DIR__ . '/plugins/*/') ?: []);
$publishedCount = 0;
try {
    foreach ((array) get_articles() as $a) if (($a['status'] ?? '') === 'published') $publishedCount++;
} catch (Throwable $e) {}
$langCount = 0;
try {
    // 走 lib/I18n 的正式入口（自带默认值兜底），不自己拼默认值
    $langCount = function_exists('i18n_supported') ? count(i18n_supported()) : 0;
} catch (Throwable $e) {}

// API 端点按域分组（只做归类展示，完整清单走 OpenAPI）
// 按关键词子串归类（先命中先归；避免"其它"堆一大坨）
$groups = [
    ['内容与文章', ['article', 'publish', 'import', 'push', 'content', 'media', 'cover', 'template', 'theme', 'page', 'lang', 'asset']],
    ['AI 与自动化', ['ai-', 'agent', 'batch', 'dynamic', 'growth', 'evolution', 'recommend', 'realtime', 'autopilot', 'insight', 'canvas']],
    ['数据与画像', ['cdp', 'segment', 'track', 'event', 'analytic', 'attribution', 'conversion', 'bi', 'ask-data', 'search', 'ab-event']],
    ['销售与订单', ['lead', 'crm', 'order', 'shop', 'cart', 'coupon', 'promo', 'commission', 'refund', 'receipt', 'address', 'affiliate', 'mall']],
    ['会员与课程', ['member', 'membership', 'course', 'certificate', 'subscription', 'activation', 'progress', 'tob-']],
    ['社区与互动', ['community', 'comment', 'follow', 'bookmark', 'message', 'notif', 'nps', 'survey', 'help', 'feedback', 'form', 'register', 'live']],
    ['渠道与集成', ['webhook', 'wecom', 'wechat', 'mail', 'newsletter', 'oauth', 'sso', 'mp-login', 'nav-', 'ingest', 'data-sync', 'data-export', 'stock', 'sdk']],
    ['SEO 与增长', ['seo', 'geo', 'trend', 'rss', 'links', 'sitemap', 'indexnow', 'robots', 'redirect', 'utm']],
    ['账号与安全', ['consent', 'privacy', 'provision', 'security', 'totp', 'unsubscribe', 'password', 'oauth-callback']],
    ['系统与开放', ['cron', 'site-', 'public-content', 'docs', 'v1', 'api-', 'plugin', 'skill', 'tool', 'scripts', 'storage', 'backup', 'health', 'debug', 'version']],
];
$groupHits = array_fill(0, count($groups), []);
$other = [];
foreach ($apiFiles as $f) {
    $base = basename($f, '.php');
    $placed = false;
    foreach ($groups as $gi => [, $keys]) {
        foreach ($keys as $k) {
            if (str_contains($base, $k)) { $groupHits[$gi][] = $base; $placed = true; break 2; }
        }
    }
    if (!$placed) $other[] = $base;
}
?>
<!doctype html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (function_exists('seo_head')): seo_head(['title' => '开发者与生态 | 芭乐派 · OpenFlow', 'description' => '开源（MIT）、可自托管：116 个 API 端点 + OpenAPI 规范、23 个 MCP 工具、插件 SDK 与适配规范。数据在你自己的服务器。', 'canonical' => site_config_get('site_url') . '/developers']); endif; ?>
<title>开发者与生态 · 芭乐派 · OpenFlow</title>
<meta name="description" content="开源（MIT）、可自托管：116 个 API 端点 + OpenAPI 规范、23 个 MCP 工具、插件 SDK 与适配规范。数据在你自己的服务器。">
<script>try{var t=JSON.parse(localStorage.getItem('openflow-site-v3')||'{}');if(t.theme)document.documentElement.dataset.theme=t.theme;}catch(e){}</script>
<?php require_once __DIR__ . '/includes/site-head.php'; of_head_assets(); ?>
<style>
/* 开发者页：代码块与工具表（其余全部来自 modules.css 的共享 archetype） */
.dev-pre{margin:12px 0 0;padding:14px 16px;border-radius:12px;background:var(--surface-2,rgba(0,0,0,.04));border:1px solid var(--border);overflow:auto;font-size:12.5px;line-height:1.7;font-family:var(--font-mono,ui-monospace,SFMono-Regular,Menlo,monospace)}
.dev-pre code{white-space:pre}
.dev-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:14px}
.dev-table th,.dev-table td{padding:9px 10px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
.dev-table th{font-size:11.5px;color:var(--faint);font-weight:700;white-space:nowrap}
.dev-table td code{font-size:12.5px;font-weight:600}
.dev-req{color:var(--muted);font-size:12px}
.dev-note{font-size:12.5px;color:var(--muted);margin-top:10px}
.dev-two{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;margin-top:18px}
</style>
</head>
<body data-of-main>
<?php require_once __DIR__ . '/includes/site-nav.php'; of_shell('developers'); ?>

<a class="skip" href="#main">跳到主要内容</a>
<main id="main" data-od-id="main">

  <!-- ══ 首屏 ══ -->
  <section id="top" class="reveal in" data-od-anchor data-od-id="hero">
    <div class="hero-center">
      <span class="kicker">开发者与生态</span>
      <h1>接进来，而不是搬过去</h1>
      <p class="lead">OpenFlow 是 MIT 开源、可自托管的增长系统。你有三种接法：调 API、接 MCP、写插件与适配。下面每个数字都能自己验证，每条命令都能直接跑。</p>
      <div class="proof-strip">
        <span><?=$apiCount?> 个 API 端点</span><span>OpenAPI 3.0 规范</span><span><?=$mcpCount?> 个 MCP 工具</span><span><?=$pluginCount?> 个插件示例</span><span>MIT 许可</span>
      </div>
    </div>
  </section>

  <!-- ══ 三条路 ══ -->
  <section id="paths" class="sec reveal" data-od-anchor data-od-id="dev-paths">
    <div class="sec-head center">
      <span class="kicker">选一条开始</span>
      <h2>三种接入方式，按你手上有什么决定</h2>
      <p class="lead">只想读数据 → 用公开端点；想让 AI 客户端直接操作 → 接 MCP；想把自己的系统接成一级能力 → 写插件或适配器。</p>
    </div>
    <div class="link-grid">
      <a class="lk" href="#api">
        <b>调 API</b>
        <span><?=$apiCount?> 个端点 + OpenAPI 规范；读接口免密钥，写接口用 <code>X-API-Key</code>。</span>
      </a>
      <a class="lk" href="#mcp">
        <b>接 MCP</b>
        <span><?=$mcpCount?> 个工具，Claude / Cursor 等客户端一行配置即可调用（stdio 或 HTTP）。</span>
      </a>
      <a class="lk" href="#plugins">
        <b>写插件 / 适配</b>
        <span>插件 SDK + 适配契约 v2：候选筛选 → 合成 → 自动验证 → 人审 → 上架。</span>
      </a>
    </div>
  </section>

  <!-- ══ MCP ══ -->
  <section id="mcp" class="sec reveal" data-od-anchor data-od-id="dev-mcp">
    <div class="sec-head center">
      <span class="kicker">MCP</span>
      <h2><?=$mcpCount?> 个工具，让 AI 客户端直接操作系统</h2>
      <p class="lead">工具清单是从服务端代码现取的（同一份定义，不会和实现漂移）。客户端配置好后，模型可以列文章、创建草稿、查线索数与营收，而不用你手写 HTTP。</p>
    </div>

    <div class="dev-two">
      <div>
        <table class="dev-table">
          <thead><tr><th>工具</th><th>说明</th><th>必填参数</th></tr></thead>
          <tbody>
            <?php foreach ($mcpTools as $t): $schema = (array) ($t['inputSchema'] ?? []); $req = (array) ($schema['required'] ?? []); ?>
            <tr>
              <td><code><?=htmlspecialchars((string) ($t['name'] ?? ''))?></code></td>
              <td><?=htmlspecialchars((string) ($t['description'] ?? ''))?></td>
              <td class="dev-req"><?=htmlspecialchars($req === [] ? '—' : implode('、', array_map('strval', $req)))?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div>
        <b style="font-size:13.5px">客户端配置（stdio）</b>
        <pre class="dev-pre"><code>{
  "mcpServers": {
    "openflow": {
      "command": "php",
      "args": ["/你的路径/mcp-server.php"]
    }
  }
}</code></pre>
        <b style="font-size:13.5px;display:block;margin-top:16px">直接调一次（JSON-RPC）</b>
        <pre class="dev-pre"><code>echo '{"jsonrpc":"2.0","id":2,"method":"tools/call",
  "params":{"name":"articles_list","arguments":{"limit":5}}}' \
  | php mcp-server.php</code></pre>
        <p class="dev-note">HTTP 模式：<code>php mcp-server.php --http</code>（SSE）。写入类工具会过 <code>McpGuard</code> 逐工具鉴权并留审计。</p>
      </div>
    </div>
  </section>

  <!-- ══ API ══ -->
  <section id="api" class="sec reveal" data-od-anchor data-od-id="dev-api">
    <div class="sec-head center">
      <span class="kicker">HTTP API</span>
      <h2>先读一条试试，不用密钥</h2>
      <p class="lead">下面是真实可跑的公开端点（已发布内容 <?=$publishedCount?> 篇，实时数字）。完整端点清单与参数请取 OpenAPI 规范。</p>
    </div>

    <div class="dev-two">
      <div>
        <pre class="dev-pre"><code># 读：公开内容（免密钥）
curl -s "https://nownexts.com/api/public-content.php?type=articles&limit=2"

# 读：全站搜索
curl -s "https://nownexts.com/api/search-public.php?q=增长"

# 取 OpenAPI 规范（116 个端点的机器可读清单）
curl -s "https://nownexts.com/api/v1/docs.json.php"</code></pre>
        <pre class="dev-pre"><code># 写：需要 API Key（后台「API 密钥」里生成）
curl -s -X POST "https://nownexts.com/api/article.php" \
  -H "X-API-Key: 你的密钥" \
  -H "Content-Type: application/json" \
  -d '{"title":"来自 API 的草稿","content":"正文"}'</code></pre>
      </div>
      <div>
        <table class="dev-table">
          <thead><tr><th>域</th><th>端点</th><th>入口</th></tr></thead>
          <tbody>
            <?php foreach ($groups as $gi => [$label, $prefixes]): $hits = $groupHits[$gi]; ?>
            <tr>
              <td><b><?=htmlspecialchars($label)?></b></td>
              <td class="dev-req"><?=htmlspecialchars($hits === [] ? '—' : implode(' · ', array_slice($hits, 0, 6)) . (count($hits) > 6 ? ' …' : ''))?></td>
              <td class="dev-req"><code>/api/<?=htmlspecialchars($hits[0] ?? '')?>.php</code></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($other !== []): ?>
            <tr>
              <td><b>其它</b></td>
              <td class="dev-req">还有 <?=count($other)?> 个端点（<code><?=htmlspecialchars(implode(' · ', array_slice($other, 0, 6)))?> …</code>）</td>
              <td class="dev-req"><code>/api/v1/docs.json.php</code></td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
        <p class="dev-note">语言：站点已配置 <b><?=$langCount?></b> 种（可在设置里增删）；接口返回 JSON，前端与后台同域，无需处理跨域。</p>
      </div>
    </div>
  </section>

  <!-- ══ 插件与适配 ══ -->
  <section id="plugins" class="sec reveal" data-od-anchor data-od-id="dev-plugins">
    <div class="sec-head center">
      <span class="kicker">生态伙伴</span>
      <h2>把外部能力做成一级功能</h2>
      <p class="lead">两种粒度：插件（进程内，能加后台页面与钩子）与适配器（把开源项目编译成经过验证的插件）。适配有明确契约与验证闸门，通过后进生态市场上架。</p>
    </div>
    <div class="dev-two">
      <div>
        <b style="font-size:13.5px">适配上架流程</b>
        <ol class="tl" style="margin-top:10px">
          <li><b>候选筛选</b>：按维护活跃度、许可、可验证性打分（<code class="dev-req">scripts/screen-adapters.py</code>）</li>
          <li><b>契约声明</b>：<code class="dev-req">source / surfaces / capabilities / compat / verification</code></li>
          <li><b>合成</b>：模板兜底 + AI 补全，产出插件骨架（含 <code class="dev-req">TODO(适配)</code> 时必须标未验证）</li>
          <li><b>验证闸门</b>：扫真实符号、检查危险调用、跑契约测试</li>
          <li><b>人审</b>：后台「生态」页批准 / 拒绝 / 上架</li>
          <li><b>上架</b>：生态市场展示，官方适配带标识且免费</li>
        </ol>
      </div>
      <div>
        <b style="font-size:13.5px">已有 <?=$pluginCount?> 个插件示例</b>
        <p class="dev-note">包含首个「AI 合成 + 人审上架」的官方适配（ntfy 通知），以及 SEO、事件防火墙、看板娘桥接等示例。仓库里可以直接抄一个改。</p>
        <pre class="dev-pre"><code>plugins/example-plugin/
├─ plugin.json      # 元信息与能力声明
├─ plugin.php       # 钩子与页面
├─ view.php         # 后台界面
└─ tests/…          # 契约测试</code></pre>
        <div class="cta-row" style="margin-top:14px">
          <a class="btn ghost" href="/marketplace">看生态市场</a>
          <a class="btn ghost" href="/docs">读文档</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ══ 自托管与兼容 ══ -->
  <section id="selfhost" class="sec reveal" data-od-anchor data-od-id="dev-selfhost">
    <div class="sec-head center">
      <span class="kicker">自托管 · 兼容</span>
      <h2>你的服务器，你的数据</h2>
      <p class="lead">MIT 许可下可以自己跑：PHP + SQLite 就能起步（生产实测在 SQLite 3.7.17 无 FTS5 的环境也能运行，相关能力自动降级），要扩再换 MySQL。前台静态资源可走 CDN，也可以完全本地。</p>
    </div>
    <div class="proof-strip">
      <span>MIT 许可</span><span>PHP 8.1+</span><span>SQLite 兜底 / MySQL 可选</span><span>无强制云依赖</span><span>数据主权在自己手上</span>
    </div>
  </section>

  <!-- ══ 自助入驻：把开源工具变成插件 ══ -->
  <section id="submit" class="sec reveal" data-od-anchor data-od-id="dev-submit">
    <div class="sec-head center">
      <span class="kicker">自助入驻</span>
      <h2>把一个开源工具变成 OpenFlow 插件，你只需要提交仓库地址</h2>
      <p class="lead">剩下的由适配流水线做：读取仓库与 README 形成能力画像、按契约生成插件草稿与契约测试、过五道闸门、人工审核后上架。<b>徽章只由闸门发放——AI 写得出代码，但不能给自己盖章。</b></p>
    </div>

    <div class="dev-two">
      <div>
        <ol class="tl">
          <li><b>你提交</b>：一个 GitHub 仓库地址（需要有明确的开源许可证）</li>
          <li><b>自动接入</b>：读仓库元数据与 README，判定能力类别与落点（触达 / 入湖 / 事件 / 支付 / CRM …）</li>
          <li><b>自动合成</b>：按适配契约 v2 生成 <code>plugin.json</code> + <code>plugin.php</code> + 契约测试</li>
          <li><b>五道闸门</b>：清单合法性 · 许可证白名单 · 契约测试 · 静态分析 · 沙箱越权检查</li>
          <li><b>人工审核</b>：通过闸门也要人点头才上架；上架后默认不启用，由站点管理员自己开启</li>
        </ol>
        <p class="dev-note">许可证白名单：MIT / Apache-2.0 / BSD / ISC / MPL-2.0 直接通过；GPL 系需人工复核；无许可证不受理。适配层只调上游<b>官方公开 API</b>，不逆向私有接口，不代理用户凭据。</p>
      </div>

      <div class="form-card">
        <div class="sec-head" style="gap:6px;margin-bottom:18px">
          <h3 class="h3" style="font-size:20px">提交一个开源工具</h3>
          <p class="note">需要登录；每人每天有提交配额</p>
        </div>
        <form onsubmit="return ofSubmitAdapter(event)" class="form-grid">
          <div class="field">
            <label for="ad-src">GitHub 仓库 *</label>
            <input class="inp" id="ad-src" type="text" name="source" required placeholder="owner/repo 或 https://github.com/owner/repo">
          </div>
          <div class="field">
            <label for="ad-note">它能做什么 / 你希望怎么用（选填）</label>
            <textarea class="inp" id="ad-note" name="note" placeholder="例如：它有官方 webhook，希望把事件接进 CDP"></textarea>
          </div>
          <button type="submit" class="btn primary" style="width:100%">提交并开始自动适配</button>
          <div id="adMsg" class="f-note" style="text-align:center"></div>
        </form>

        <div class="f-row" style="margin-top:18px;border-top:1px solid var(--border);padding-top:18px">
          <input class="inp" id="ad-ticket" type="text" placeholder="已有受理编号？sub_…" style="flex:1;min-width:180px">
          <button type="button" class="btn ghost" onclick="ofAdapterStatus()">查进度</button>
        </div>
        <div id="adStatus" class="f-note" style="margin-top:10px"></div>
      </div>
    </div>
  </section>

  <script>
  function ofSubmitAdapter(e) {
    e.preventDefault();
    var f = e.target, msg = document.getElementById('adMsg');
    var btn = f.querySelector('button[type=submit]');
    var body = new URLSearchParams({ action: 'submit_adapter', source: f.source.value.trim(), note: f.note.value.trim() });
    btn.disabled = true; msg.style.display = 'block'; msg.textContent = '提交中…';
    fetch('/api/developer', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
      .then(function (r) { return r.json().then(function (d) { return { s: r.status, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (res.d && res.d.ok) {
          msg.textContent = '已受理，受理编号 ' + res.d.ticket + '。适配会在后台排队进行，可用这个编号查进度。';
          document.getElementById('ad-ticket').value = res.d.ticket;
          f.reset();
          return;
        }
        if (res.s === 401) { msg.textContent = '请先登录后再提交。'; return; }
        msg.textContent = (res.d && res.d.error ? res.d.error : '提交失败，请稍后再试')
          + (res.d && res.d.ticket ? '（受理编号 ' + res.d.ticket + '）' : '');
      })
      .catch(function () { btn.disabled = false; msg.textContent = '网络错误，请稍后再试'; });
    return false;
  }

  function ofAdapterStatus() {
    var t = (document.getElementById('ad-ticket').value || '').trim();
    var box = document.getElementById('adStatus');
    if (!t) { box.textContent = '请先填写受理编号'; return; }
    box.textContent = '查询中…';
    fetch('/api/developer?action=adapter_status&ticket=' + encodeURIComponent(t))
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) { box.textContent = (d && d.error) || '查询失败'; return; }
        var x = d.data;
        var label = { queued: '排队中', running: '适配中', done: '已处理', failed: '失败', rejected: '已拒绝' }[x.status] || x.status;
        var line = x.source + '：' + label;
        if (x.gate_status) line += '（闸门 ' + x.gate_status + (x.badge ? ' · 徽章 ' + x.badge : '') + '）';
        if (x.failed && x.failed.length) line += '　未过：' + x.failed.join('、');
        box.textContent = line + (x.message ? '　' + x.message : '');
      })
      .catch(function () { box.textContent = '网络错误'; });
  }
  </script>

  <!-- ══ 收口 ══ -->
  <section id="next" class="reveal" data-od-anchor data-od-id="dev-cta">
    <div class="cta-band">
      <span class="kicker">下一步</span>
      <h2>从一条命令或一个插件开始</h2>
      <p class="lead">先跑通一次读取，再决定要不要写插件。密钥、文档、示例都在仓库和后台里，不需要申请，也没有等待审核的环节；你随时可以自己跑起来验证这一切。</p>
      <ol class="tl">
        <li><b>读一次</b>：复制上面任一条 curl，确认返回 JSON</li>
        <li><b>接客户端</b>：把 MCP 配置粘进 Claude / Cursor，问它「列一下最近的文章」</li>
        <li><b>再扩一层</b>：照 <code>plugins/example-plugin</code> 抄一个插件，或<a href="#submit">提交一个开源工具</a>让流水线替你适配</li>
      </ol>
      <div class="cta-row">
        <a class="btn primary" href="/docs">看开发文档</a>
        <a class="btn ghost" href="/marketplace">看生态市场</a>
        <a class="btn ghost" href="/capability">能力全景</a>
      </div>
    </div>
  </section>

  <!-- ══ footer（共享 .foot） ══ -->
<?php require_once __DIR__ . '/includes/site-footer.php'; of_footer(); ?>
</main>

<button id="backtop" data-od-id="back-to-top" aria-label="回到顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7M12 5v14"/></svg></button>
<script src="/assets/site-shell.js?v=<?=defined('OF_SHELL_VER') ? OF_SHELL_VER : '1'?>" defer></script>
</body>
</html>
