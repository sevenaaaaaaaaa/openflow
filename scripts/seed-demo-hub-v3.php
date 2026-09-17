<?php
/**
 * Demo 三件套 v3 —— 针对反馈的三项修正
 *   ① 首屏视觉：hero 带窗口化真实截图（不再纯文字）
 *   ② 模块密度：每张卡补要点清单（交付/适合/依赖），四力补模块清单+产出，42 模块补一句说明，对比表补列
 *   ③ 对齐：配合 modules.css 的等高/CTA 贴底修正
 * 用法: php scripts/seed-demo-hub-v3.php
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../lib/BuilderPages.php';

$k = 'block_new_key';
$SHOT = '/assets/images/product';
$SHOT_V = '?v=20260911';

function upsert_page(string $slug, array $page): void {
    $pages = builder_pages_all();
    foreach ($pages as $p) {
        if (($p['slug'] ?? '') === $slug) {
            save_builder_page($p['id'], ['blocks' => $page['blocks'], 'title' => $page['title'], 'seo_title' => $page['seo_title'], 'seo_desc' => $page['seo_desc']]);
            echo "♻ {$slug}（" . count($page['blocks']) . " 区块）\n";
            return;
        }
    }
    save_builder_page('', $page);
    echo "✅ {$slug}（" . count($page['blocks']) . " 区块）\n";
}
function screens(string $title, callable $k, string $SHOT, string $V, array $items): array {
    $html = '';
    foreach ($items as $it) { [$w,$f,$n,$d] = $it; $html .= "<div data-w=\"{$w}\"><img src=\"{$SHOT}/{$f}{$V}\" alt=\"{$n}\" loading=\"lazy\"><h3>{$n}</h3><p>{$d}</p></div>"; }
    return ['_type'=>'bento','_key'=>$k(),'title'=>$title,'subtitle'=>'真实界面 · 不是效果图','content'=>$html];
}
function deploy(callable $k): array {
    return ['_type'=>'tabs','_key'=>$k(),'title'=>'三种部署方式，你说了算','subtitle'=>'部署','content'=><<<HTML
<div data-tab="云端 SaaS"><h3>开箱即用，免费起步</h3><p>不用买服务器、不用运维：注册即用，功能随版本自动更新。</p><ul><li>适合：想当天就开工的人</li><li>成本：从 0 开始，按需升级</li><li>数据：可随时导出带走</li></ul></div>
<div data-tab="私有化部署"><h3>数据完全留在内网</h3><p>核心引擎自托管（本项目即开源底座，rsync + Apache 即可上线），数据、日志、模型调用全部在自己服务器上。</p><ul><li>适合：对数据敏感的企业与机构</li><li>前提：一台服务器 + PHP 8.3</li><li>数据：不出内网</li></ul></div>
<div data-tab="混合架构"><h3>核心私有 + 云端弹性</h3><p>核心引擎私有部署，峰值的采集/生成等重活走云端弹性扩缩容，兼顾合规与成本。</p><ul><li>适合：既要合规又要弹性</li><li>做法：核心私有 + 重活上云</li><li>数据：核心数据不动</li></ul></div>
HTML];
}
function open_(callable $k): array {
    return ['_type'=>'checklist','_key'=>$k(),'title'=>'开放，是默认值','subtitle'=>'生态','content'=><<<HTML
<li><b>开放 API</b>：116 个端点（公开内容 / 事件上报 / 分群服务 / 表单回传），Swagger 文档在 /api/v1/docs</li>
<li><b>Webhook 与连接器</b>：飞书 / Notion / Obsidian / 印象笔记内容导入；事件可推送到你的任何系统</li>
<li><b>Skill 与模板</b>：生态市场可安装技能、插件、主题；组件工厂用 AI 现场造新模块</li>
<li><b>永久开源（MIT）</b>：核心能力开源可自托管，数据与代码在你手里，随时可迁走</li>
HTML];
}
function integrations(callable $k): array {
    return ['_type'=>'marquee','_key'=>$k(),'title'=>'不用推翻你现在在用的东西','subtitle'=>'接入你现有的模型与工具','content'=><<<HTML
<div>OpenAI</div><div>DeepSeek</div><div>GLM</div><div>Kimi</div><div>Claude</div><div>Qwen</div>
<div>飞书</div><div>Notion</div><div>Obsidian</div><div>印象笔记</div><div>公众号</div><div>企业微信</div>
<div>Webhook</div><div>MCP</div><div>自托管模型</div>
HTML];
}
function runlog(callable $k): array {
    return ['_type'=>'runlog','_key'=>$k(),'anchor'=>'dayrun','title'=>'它一天在干什么：跑一遍','subtitle'=>'run','button_text'=>'▶ 跑一遍',
        'content'=>"$ openflow loop --today\n✓ 采集     GEO 全网抓取新话题（RSS + 搜索 API 双通道）\n✓ 选题     按你的方向打分去重，产出候选 3 个\n✓ 初稿     AI 出结构化草稿，落进草稿箱待你审\n✓ 发布     多端分发 + 收录推送（sitemap / IndexNow）\n✓ 回收     阅读·线索·转化回流，标出漏单的那一环\n✓ 体检     SelfEvolve 列出该修的清单（只给建议，不自动改）\n→ 结论     今天最该动的一件事，交给你判断"];
}
function day(callable $k): array {
    return ['_type'=>'tabs','_key'=>$k(),'title'=>'它一天在干什么','subtitle'=>'增长闭环','content'=><<<HTML
<div data-tab="09:00 采集"><h3>先把外部信号收进来</h3><p>GEO 全网采集（RSS + 搜索 API）抓回你所在方向的新话题与竞品动作，去重、打分、进选题库。</p><ul><li>产出：今日候选选题</li><li>依据：热度 + 与你的相关度</li><li>去哪看：后台「GEO 话题监控」</li></ul></div>
<div data-tab="11:00 生成"><h3>AI 出初稿，人只把关</h3><p>按选题结构化产出初稿，接你配置的模型（OpenAI / DeepSeek / GLM / Kimi / 自托管均可）。</p><ul><li>产出：草稿 + SEO 标题描述建议</li><li>可控：模型与提示词都在你自己后台</li><li>不白屏：AI 没配也能手动写</li></ul></div>
<div data-tab="14:00 分发"><h3>一次编辑，多端出去</h3><p>按渠道适配分发（站内 / 公众号 / Newsletter），自动排版与定时发布；收录推送同步。</p><ul><li>产出：已发布 + sitemap/IndexNow 已推送</li><li>渠道：站内 · 公众号 · 邮件 · RSS</li><li>多语言：一篇内容可出多语言版本</li></ul></div>
<div data-tab="18:00 复盘"><h3>把效果收回来</h3><p>阅读、停留、线索、转化逐环回流；漏斗哪一环漏单直接标出来。</p><ul><li>产出：明日动作 + 归因</li><li>依据：转化漏斗五阶段 + 来源归因</li><li>出口：增长引擎给出「最该动的那一件事」</li></ul></div>
HTML];
}
function form_block(callable $k): array {
    return ['_type'=>'form','_key'=>$k(),'anchor'=>'diagnose','title'=>'30 分钟，告诉你哪一段最该交给 Agent','subtitle'=>'免费 · 30 分钟 · 由人给出判断','content'=>'appointment'];
}
function matrix(string $self, callable $k): array {
    $S = [
        'openflow'=>['OpenFlow','全站增长操作系统','四力合一（内容·数据·触达·销售）','/product'],
        'mflow'=>['MFlow','内容生产与分发','生成 → 多平台分发 → 轻触达','/product/mflow'],
        'webs-flow'=>['Webs Flow','落地页专精','承接页以小时计，投放原生','/product/webs-flow'],
        'userloop'=>['UserLoop','全域营销数据中枢','独立运行，对接任何 MA','/product/userloop'],
        'inflow'=>['inFlow','情报增长站','趋势 / 舆情 / 竞品','/product/inflow'],
        'payflow'=>['PayFlow','商业变现引擎','一行嵌入收款，订阅 / 裂变 / 佣金','/product/payflow'],
        'learnflow'=>['LearnFlow','课程与训练营交付','上课 → 进度 → 测验 → 证书','/product/learnflow'],
    ];
    $html = '';
    foreach ($S as $key => $d) {
        if ($key === $self) continue;
        $html .= "<div><span class=\"tg-tag\">互补</span><h3>{$d[0]} · {$d[1]}</h3><p><b>负责</b>：{$d[2]}<br><span style=\"color:var(--muted)\">与本产品 API 互通——不是二选一</span></p><a href=\"{$d[3]}\" style=\"color:var(--accent);font-size:13px;font-weight:700\">了解 {$d[0]} →</a></div>";
    }
    $html .= '<div><span class="tg-tag">演示</span><h3>先看它一天干什么</h3><p>真实机制跑一遍：采集 → 选题 → 初稿 → 发布 → 回收 → 体检。</p><a href="/demo/home-v2#dayrun" style="color:var(--accent);font-size:13px;font-weight:700">看运行演示 →</a></div>';
    return ['_type'=>'tool-grid','_key'=>$k(),'title'=>'七个独立产品，API 互通','subtitle'=>'产品矩阵','content'=>$html];
}

$S1 = [[2,'workspace.png','控制台 · 今日主线','KPI、主线任务与 Agent 运行状态，一屏看完'],[2,'content-hub.png','内容中心','文章 · 课程 · 资料 · 播客 一处管理'],[2,'audience.png','CDP 用户画像','身份合并后的行为时间线与分群']];
$S2 = [[2,'automation.png','营销自动化','画布编排：触发 → 条件 → 动作'],[2,'studio.png','编排画布','可视化搭流程，人只做判断'],[2,'crm.png','CRM 管道','线索阶段与跟进记录']];

/* ═══════ A · 产品矩阵 ═══════ */
upsert_page('demo-products', [
    'slug'=>'demo-products','title'=>'产品矩阵总览（预览）','status'=>'published',
    'seo_title'=>'产品矩阵 — 七个独立产品，按需组合 | 芭乐派',
    'seo_desc'=>'不必为不需要的能力付费：OpenFlow 全家桶、MFlow 内容分发、Webs Flow 落地页、UserLoop 全域数据、inFlow 情报、PayFlow 收款、LearnFlow 课程交付。零强制绑定，API 互通。',
    'blocks'=>[
        ['_type'=>'hero','_key'=>$k(),'title'=>'七个独立产品，按需组合成一套系统','subtitle'=>'产品矩阵 · 总览','image'=>$SHOT.'/content-hub.png'.$SHOT_V,'image_caption'=>'app.openflow / content-hub','content'=>'不必为一个用不上的大系统付费。每件产品都能单独跑、单独见效；组合起来就是一套自转的增长系统。互通走 API——拔掉任何一个，其余照常运行。','button_text'=>'看七个产品','button_url'=>'#products','button_text2'=>'先看它一天干什么','button_url2'=>'#dayrun'],
        ['_type'=>'proof','_key'=>$k(),'content'=>"<div><b>7</b><span>独立产品</span></div><div><b>0</b><span>强制绑定</span></div><div><b>116</b><span>API 端点（互通）</span></div><div><b>502</b><span>篇真实内容在跑</span></div>"],
        ['_type'=>'journey','_key'=>$k(),'title'=>'三步找到你的入口','subtitle'=>'怎么选','content'=><<<HTML
<div><h3>你缺的是哪一环？</h3><p>整套系统？还是具体某环——收款、落地页、情报、内容分发、课程交付。</p><ul><li>结果：锁定 1-2 件产品</li><li>别为不需要的付费</li></ul></div>
<div><h3>你的依赖假设是什么？</h3><p>已有 CMS / 商城 / MA 的，选零依赖单点产品（PayFlow、Webs Flow、inFlow、UserLoop）。</p><ul><li>结果：确定是否需要 OpenFlow 底座</li><li>零依赖 = 今天就能用</li></ul></div>
<div><h3>谁在用、几个人用？</h3><p>一个人用单点产品更快见效；团队协作、要统一数据与权限，走 OpenFlow + 按需嵌入。</p><ul><li>结果：确定部署与协作方式</li><li>升级有通道，数据可平滑并入</li></ul></div>
HTML],
        ['_type'=>'tool-grid','_key'=>$k(),'anchor'=>'products','title'=>'七个独立产品','subtitle'=>'七件产品 · 逐个看','content'=><<<HTML
<div><span class="tg-tag">全家桶</span><h3>OpenFlow · 增长操作系统</h3><ul><li><b>交付</b>：内容引擎 + CDP + 自动化 + CRM + 商城</li><li><b>适合</b>：要一整套自转系统的团队</li><li><b>依赖</b>：深度（自家 CMS + CDP）</li></ul><a href="/product" style="color:var(--accent);font-size:13px;font-weight:700">进入 OpenFlow →</a></div>
<div><span class="tg-tag">轻量内容</span><h3>MFlow · 内容生产与分发</h3><ul><li><b>交付</b>：AI 生成 + 多平台分发 + 轻触达</li><li><b>适合</b>：内容创作者、电商卖家</li><li><b>依赖</b>：低（兼容现成 CMS，不锁模型）</li></ul><a href="/product/mflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 MFlow →</a></div>
<div><span class="tg-tag">落地页</span><h3>Webs Flow · 落地页专精</h3><ul><li><b>交付</b>：44 种展示区块 + 组件工厂 + 投放归因</li><li><b>适合</b>：投手、活动运营</li><li><b>依赖</b>：无（不需要 CMS）</li></ul><a href="/product/webs-flow" style="color:var(--accent);font-size:13px;font-weight:700">进入 Webs Flow →</a></div>
<div><span class="tg-tag">全域数据</span><h3>UserLoop · 全域营销数据中枢</h3><ul><li><b>交付</b>：埋点 + 身份合并 + 分群 + MA 对接</li><li><b>适合</b>：多平台运营团队、代理商</li><li><b>依赖</b>：无（对接你现有的任何 MA）</li></ul><a href="/product/userloop" style="color:var(--accent);font-size:13px;font-weight:700">进入 UserLoop →</a></div>
<div><span class="tg-tag">外部情报</span><h3>inFlow · 情报增长站</h3><ul><li><b>交付</b>：趋势 / 舆情 / 竞品 + 每日情报</li><li><b>适合</b>：品牌方、内容策划</li><li><b>依赖</b>：无（只依赖外部数据源）</li></ul><a href="/product/inflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 inFlow →</a></div>
<div><span class="tg-tag">收款变现</span><h3>PayFlow · 商业变现引擎</h3><ul><li><b>交付</b>：一行嵌入收款 + 订阅 + 裂变佣金</li><li><b>适合</b>：创作者、独立开发者</li><li><b>依赖</b>：无（任何网站都能嵌）</li></ul><a href="/product/payflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 PayFlow →</a></div>
<div><span class="tg-tag">课程交付</span><h3>LearnFlow · 课程与训练营</h3><ul><li><b>交付</b>：课程结构 + 进度 + 测验 + 结业证书</li><li><b>适合</b>：讲师、教练、训练营主理人</li><li><b>依赖</b>：轻（收款接 PayFlow）</li></ul><a href="/product/learnflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 LearnFlow →</a></div>
HTML],
        screens('这些界面现在就在跑，不是概念图', $k, $SHOT, $SHOT_V, $S1),
        ['_type'=>'bento','_key'=>$k(),'title'=>'常见组合：按场景拼装','subtitle'=>'组合方案','content'=><<<HTML
<div data-w="3"><h3>🎯 单点起步</h3><ul><li>只缺收款 → <b>PayFlow</b></li><li>只缺落地页 → <b>Webs Flow</b></li><li>只缺情报 → <b>inFlow</b></li></ul><p style="color:var(--muted);font-size:12.5px">零依赖，今天就能用</p></div>
<div data-w="3"><h3>🛒 电商增长组合</h3><ul><li>落地页：<b>Webs Flow</b></li><li>收款：<b>PayFlow</b></li><li>数据：<b>UserLoop</b></li></ul><p style="color:var(--muted);font-size:12.5px">投放 → 承接 → 收款 → 沉淀</p></div>
<div data-w="3"><h3>📣 内容营销组合</h3><ul><li>情报：<b>inFlow</b></li><li>生产分发：<b>MFlow</b></li><li>效果回流：<b>UserLoop</b></li></ul><p style="color:var(--muted);font-size:12.5px">选题到收录一条线</p></div>
<div data-w="3"><h3>🎓 知识变现组合</h3><ul><li>交付：<b>LearnFlow</b></li><li>收款：<b>PayFlow</b></li><li>触达：<b>MFlow</b></li></ul><p style="color:var(--muted);font-size:12.5px">开营到复购</p></div>
<div data-w="6"><h3>🏢 全家桶组合：OpenFlow</h3><ul><li>要一整套自转系统、数据要在一处、团队要统一后台 → OpenFlow 四力合一</li><li>其余六件按需嵌入；所有产品的数据都能平滑并入</li></ul></div>
HTML],
        ['_type'=>'comparison','_key'=>$k(),'title'=>'一眼看清差异','subtitle'=>'怎么区分','content'=><<<HTML
<table style="width:100%;border-collapse:collapse;min-width:900px">
<thead><tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">产品</th><th style="padding:10px">交付什么</th><th style="padding:10px">依赖</th><th style="padding:10px">适合谁</th><th style="padding:10px">上手</th></tr></thead>
<tbody>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>OpenFlow</b></td><td style="padding:12px 10px">全站运营底座：内容 / 数据 / 触达 / 销售</td><td style="padding:12px 10px">深度（自家 CMS + CDP）</td><td style="padding:12px 10px">要一整套系统的团队</td><td style="padding:12px 10px">30 分钟上线</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>MFlow</b></td><td style="padding:12px 10px">AI 生成 + 多平台分发 + 轻触达</td><td style="padding:12px 10px">低（兼容现成 CMS）</td><td style="padding:12px 10px">创作者 / 电商卖家</td><td style="padding:12px 10px">当天</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>Webs Flow</b></td><td style="padding:12px 10px">落地页 / 活动页（44 种区块）</td><td style="padding:12px 10px">无</td><td style="padding:12px 10px">投手 / 活动运营</td><td style="padding:12px 10px">小时级</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>UserLoop</b></td><td style="padding:12px 10px">全域埋点 + 分群 + 对接任何 MA</td><td style="padding:12px 10px">无</td><td style="padding:12px 10px">多平台运营团队</td><td style="padding:12px 10px">一行代码</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>inFlow</b></td><td style="padding:12px 10px">外部情报：趋势 / 舆情 / 竞品</td><td style="padding:12px 10px">无（依赖外部数据源）</td><td style="padding:12px 10px">品牌 / 内容策划</td><td style="padding:12px 10px">当天</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>PayFlow</b></td><td style="padding:12px 10px">收款 / 订阅 / 裂变佣金</td><td style="padding:12px 10px">无</td><td style="padding:12px 10px">创作者 / 独立开发者</td><td style="padding:12px 10px">一行嵌入</td></tr>
<tr><td style="padding:12px 10px"><b>LearnFlow</b></td><td style="padding:12px 10px">课程与训练营交付闭环</td><td style="padding:12px 10px">轻（收款接 PayFlow）</td><td style="padding:12px 10px">讲师 / 训练营主理人</td><td style="padding:12px 10px">当天</td></tr>
</tbody></table>
HTML],
        runlog($k),
        deploy($k),
        open_($k),
        integrations($k),
        ['_type'=>'quote-wall','_key'=>$k(),'title'=>'用过的人怎么说','subtitle'=>'真实反馈','content'=><<<HTML
<div>「以前每天 3 小时找选题改文章，现在爬完信号直接给草稿，我只管把关。」<br><span style="color:var(--muted);font-size:12.5px">— 陈默 · 内容工作室</span></div>
<div>「第一次一个人跑完整条内容流水线，从采集到收录没换过工具。」<br><span style="color:var(--muted);font-size:12.5px">— 独立开发者</span></div>
<div>「漏斗终于看得见了：哪个环节漏单，面板直接告诉你。」<br><span style="color:var(--muted);font-size:12.5px">— 增长负责人</span></div>
<div>「不用换我的商城，接上就收款。」<br><span style="color:var(--muted);font-size:12.5px">— DTC 卖家</span></div>
<div>「组件工厂太顺了——贴一段 HTML 就变成可复用模块。」<br><span style="color:var(--muted);font-size:12.5px">— 前端工程师</span></div>
<div>「课程播放器拖进度条很流畅，学员完课率肉眼可见在涨。」<br><span style="color:var(--muted);font-size:12.5px">— 训练营主理人</span></div>
HTML],
        ['_type'=>'faq','_key'=>$k(),'title'=>'常见问题','subtitle'=>'FAQ','content'=><<<HTML
<details><summary>可以只用其中一个产品吗？</summary><p>可以。七个产品都独立可用——单点产品零依赖，单独交付价值。</p></details>
<details><summary>以后想升级成整套怎么办？</summary><p>数据可平滑并入 OpenFlow：身份、内容、订单都有迁移通道，不用重建。</p></details>
<details><summary>数据放在你们那里安全吗？</summary><p>三种部署任选：云端 SaaS、私有化自托管、混合。核心开源，数据与代码都在你手里。</p></details>
<details><summary>组合起来会不会很复杂？</summary><p>不会。互通走 API 自动完成，产品之间不需要你手工搬运数据。</p></details>
HTML],
        form_block($k),
        ['_type'=>'cta','_key'=>$k(),'title'=>'从最缺的那一环开始','subtitle'=>'产品矩阵','content'=>'单点产品今天就能用；想要整套，OpenFlow 随时接住你。','button_text'=>'看能力全景','button_url'=>'/demo/capabilities'],
        matrix('demo-products', $k),
    ],
]);

/* ═══════ B · 能力全景 ═══════ */
upsert_page('demo-capabilities', [
    'slug'=>'demo-capabilities','title'=>'能力全景（预览）','status'=>'published',
    'seo_title'=>'能力全景 — TIPS 四力 + 三层支撑（42 个真实模块） | 芭乐派',
    'seo_desc'=>'TIPS 四力（触达/洞察/个性化/销售）+ 行动脊柱、增长引擎、永久开源。42 个模块全部可在代码与后台核验，每项能力标注承载产品。',
    'blocks'=>[
        ['_type'=>'hero','_key'=>$k(),'title'=>'四力加三层支撑，能力全部可核验','subtitle'=>'能力 · 全景','image'=>$SHOT.'/automation.png'.$SHOT_V,'image_caption'=>'app.openflow / automation','content'=>'TIPS 四力回答「增长靠什么」：触达、洞察、个性化、销售。行动脊柱、增长引擎与开源底座保证它们真的跑起来——每一项能力都标了由哪个产品承载、多少个真实模块。','button_text'=>'看 42 个模块','button_url'=>'#modules','button_text2'=>'先看它一天干什么','button_url2'=>'#dayrun'],
        ['_type'=>'proof','_key'=>$k(),'content'=>"<div><b>4</b><span>核心四力</span></div><div><b>42</b><span>真实模块（可核验）</span></div><div><b>44</b><span>展示区块</span></div><div><b>116</b><span>API 端点</span></div>"],
        ['_type'=>'tabs','_key'=>$k(),'anchor'=>'map','title'=>'四力：每一项能力解决什么','subtitle'=>'TIPS','content'=><<<HTML
<div data-tab="触达 Touch"><h3>把对的内容递到对的人面前</h3><ul><li><b>内容引擎</b> — 文章 / 课程 / 资料 / 播客一站式</li><li><b>创作台</b> — 深度专栏、口播脚本、品牌幻灯片</li><li><b>SEO / GEO</b> — 四大搜索引擎接入 + 全网话题采集</li><li><b>Newsletter</b> — 选文章自动排版定时发送</li><li><b>直播带货</b> — 弹幕、推品、下单闭环</li></ul><p style="color:var(--accent);font-weight:700">承载产品：MFlow（轻量）· OpenFlow（全家桶）· Webs Flow（承接页）</p></div>
<div data-tab="洞察 Insight"><h3>从几百个指标捞出该看的那 3-5 个</h3><ul><li><b>CDP 画像</b> — 身份合并、一人一册行为时间线</li><li><b>分群与 RFM</b> — 行为驱动动态标签</li><li><b>转化漏斗</b> — 访客→注册→加购→支付→复购</li><li><b>问数据</b> — 自然语言直接问经营数据</li><li><b>归因与 A/B</b> — 知道钱和人从哪来</li></ul><p style="color:var(--accent);font-weight:700">承载产品：inFlow（外部情报）· UserLoop（全域数据）· OpenFlow（自有数据）</p></div>
<div data-tab="个性化 Personality"><h3>同一套系统，给每个人不同的答案</h3><ul><li><b>人群定向</b> — 区块按登录态 / 分群 / UTM 显示不同内容</li><li><b>推荐引擎</b> — 按画像与行为推荐内容与商品</li><li><b>动态内容</b> — 规则驱动的个性化呈现</li><li><b>会员分层</b> — 等级与权益解锁</li></ul><p style="color:var(--accent);font-weight:700">承载产品：UserLoop（数据底座）· OpenFlow（全站个性化）</p></div>
<div data-tab="销售 Sales"><h3>把流量变成收入，把收入变成复购</h3><ul><li><b>CRM 与线索</b> — 线索池、管道、跟进记录</li><li><b>商业闭环</b> — 购物车、订阅计费、优惠券、推荐码、佣金结算</li><li><b>会员体系</b> — 等级与权益</li><li><b>集市</b> — 数字商品上架与交付</li></ul><p style="color:var(--accent);font-weight:700">承载产品：PayFlow（收款变现）· LearnFlow（课程交付）· OpenFlow（全站商业）</p></div>
HTML],
        screens('能力长什么样：真实后台截图', $k, $SHOT, $SHOT_V, array_merge($S1, $S2)),
        ['_type'=>'accordion','_key'=>$k(),'anchor'=>'modules','title'=>'42 个模块，全部真实存在','subtitle'=>'能力索引','content'=><<<HTML
<details open><summary>触达 Touch · 6 模块</summary><div class="hacc-body"><ul><li><b>内容引擎</b> — 文章 / 课程 / 资料 / 播客的管理与发布</li><li><b>创作台</b> — 专栏、口播脚本、品牌幻灯片的结构化产出</li><li><b>直播带货</b> — 横屏 / 竖屏 / 沉浸直播间与下单闭环</li><li><b>SEO / GEO</b> — 四引擎接入 + RSS/搜索 API 采集</li><li><b>多语言站群</b> — 一篇内容同步多语言版本</li><li><b>Newsletter</b> — 自动排版 → 定时发送到订阅者</li></ul><a href="/academy">去学院看内容形态 →</a></div></details>
<details><summary>洞察 Insight · 6 模块</summary><div class="hacc-body"><ul><li><b>CDP 画像</b> — 匿名与会员身份合并，一册行为时间线</li><li><b>分群与 RFM</b> — 行为驱动标签，生命周期自动流转</li><li><b>转化漏斗</b> — 五阶段转化率可视化</li><li><b>驾驶舱</b> — KPI 带月目标与 delta，AI 兜底解读</li><li><b>问数据 AskData</b> — 自然语言问经营数据</li><li><b>归因与 A/B</b> — 路径归因 + 实验评估</li></ul><a href="/product#demo">看数据怎么驱动决策 →</a></div></details>
<details><summary>个性化 Personality · 6 模块</summary><div class="hacc-body"><ul><li><b>人群定向</b> — 区块级登录态/分群/UTM 定向</li><li><b>推荐引擎</b> — 内容与商品个性化推荐</li><li><b>动态内容</b> — 规则驱动呈现</li><li><b>落地页变体</b> — 同页多版按人群分流</li><li><b>会员分层</b> — 等级权益</li><li><b>权益解锁</b> — 付费墙与解锁</li></ul></div></details>
<details><summary>销售 Sales · 6 模块</summary><div class="hacc-body"><ul><li><b>CRM 管道</b> — 阶段、评分、跟进</li><li><b>线索评分</b> — 按行为与属意度打分</li><li><b>报价与订单</b> — 报价单、订单状态机</li><li><b>订阅计费</b> — 续费、失败重试、提醒</li><li><b>优惠券与推荐码</b> — 促销与裂变</li><li><b>佣金结算</b> — 余额、提现、审计</li></ul></div></details>
<details><summary>行动脊柱 Agentic · 6 模块</summary><div class="hacc-body"><ul><li><b>Agent 运行时</b> — 受控执行与额度闸门</li><li><b>画布编排</b> — 可视化连节点</li><li><b>工作流</b> — 触发 → 条件 → 动作</li><li><b>定时任务</b> — cron 驱动</li><li><b>审批闸门</b> — 危险动作需人批</li><li><b>审计留痕</b> — 谁改了什么可回溯</li></ul></div></details>
<details><summary>增长引擎 · 6 模块</summary><div class="hacc-body"><ul><li><b>信号采集</b> — 前端访问与后台使用记录</li><li><b>规则引擎</b> — 条件命中即给建议</li><li><b>建议与动作</b> — 具体到「改哪里」</li><li><b>进度追踪</b> — 建议采纳与解决状态</li><li><b>归因回收</b> — 效果回到建议</li><li><b>体检与修复</b> — SelfEvolve 列出该修清单</li></ul></div></details>
<details><summary>永久开源 · 6 模块</summary><div class="hacc-body"><ul><li><b>开放 API</b> — 116 个端点 + Swagger</li><li><b>Webhook</b> — 事件出站</li><li><b>插件系统</b> — 钩子/路由/菜单/插槽/区块/定时六类扩展点</li><li><b>Skill 生态</b> — 可安装技能与模板</li><li><b>MCP 服务</b> — 23 个工具供 AI Agent 调用</li><li><b>主题与模板</b> — 视觉与布局可替换</li></ul></div></details>
HTML],
        ['_type'=>'feature-detail','_key'=>$k(),'title'=>'四力之下的三层支撑','subtitle'=>'支撑体系','content'=><<<HTML
<div><b>行动脊柱 Agentic</b><span>Agent 编排、工作流、自动化：让能力自己按流程跑，人只做判断。危险动作有审批闸门，全部留痕。</span></div>
<div><b>增长引擎</b><span>信号采集 → 规则判断 → 具体动作：系统自己发现机会，并告诉你先改哪里。</span></div>
<div><b>永久开源（MIT）</b><span>核心能力开源可自托管：数据主权与长期可维护性不打折；不想运维也能用托管版。</span></div>
HTML],
        ['_type'=>'comparison','_key'=>$k(),'title'=>'同一项能力，由哪个产品承载','subtitle'=>'能力 → 产品','content'=><<<HTML
<table style="width:100%;border-collapse:collapse;min-width:860px">
<thead><tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">能力</th><th style="padding:10px">全家桶 OpenFlow</th><th style="padding:10px">单点产品</th><th style="padding:10px">选择建议</th></tr></thead>
<tbody>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">内容生产与分发</td><td style="padding:12px 10px">内容引擎 + 创作台</td><td style="padding:12px 10px"><b>MFlow</b></td><td style="padding:12px 10px">已有 CMS，只缺生成与分发</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">落地页与投放承接</td><td style="padding:12px 10px">页面构建器（面向全站）</td><td style="padding:12px 10px"><b>Webs Flow</b></td><td style="padding:12px 10px">只为投放做承接页</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">用户数据与分群</td><td style="padding:12px 10px">CDP（自有数据）</td><td style="padding:12px 10px"><b>UserLoop</b></td><td style="padding:12px 10px">多平台数据要打通，MA 不想换</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">外部情报</td><td style="padding:12px 10px">GEO 采集（站点选题）</td><td style="padding:12px 10px"><b>inFlow</b></td><td style="padding:12px 10px">盯趋势 / 舆情 / 竞品</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">收款与变现</td><td style="padding:12px 10px">商业与订阅</td><td style="padding:12px 10px"><b>PayFlow</b></td><td style="padding:12px 10px">任何页面接一行就能收款</td></tr>
<tr><td style="padding:12px 10px">课程交付</td><td style="padding:12px 10px">课程系统</td><td style="padding:12px 10px"><b>LearnFlow</b></td><td style="padding:12px 10px">讲师要交付闭环与证书</td></tr>
</tbody></table>
HTML],
        ['_type'=>'journey','_key'=>$k(),'title'=>'四力合起来能跑出什么','subtitle'=>'应用场景','content'=><<<HTML
<div><h3>内容获客</h3><p>inFlow 找选题 → MFlow 产出与分发 → 收录与回流。</p><ul><li>产出：持续的内容流量</li></ul></div>
<div><h3>私域转化</h3><p>UserLoop 圈人群 → 个性化落地页承接 → CRM 跟进成交。</p><ul><li>产出：可归因的成交</li></ul></div>
<div><h3>新品验证</h3><p>Webs Flow 做承接页 → A/B 分流 → PayFlow 收款 → 数据回收判断。</p><ul><li>产出：值得放大或砍掉</li></ul></div>
<div><h3>复购与留存</h3><p>分群流转 + 自动化触达 + 会员权益。</p><ul><li>产出：长期关系</li></ul></div>
HTML],
        runlog($k),
        deploy($k),
        open_($k),
        integrations($k),
        form_block($k),
        ['_type'=>'cta','_key'=>$k(),'title'=>'能力是框架，产品是交付','subtitle'=>'能力全景','content'=>'想清楚要哪些能力，再去产品矩阵挑，组合成你自己的系统。','button_text'=>'去产品矩阵','button_url'=>'/demo/products'],
        matrix('demo-capabilities', $k),
    ],
]);

/* ═══════ C · 首页 v2 ═══════ */
upsert_page('demo-home-v2', [
    'slug'=>'demo-home-v2','title'=>'首页 v2（预览）','status'=>'published',
    'seo_title'=>'芭乐派 — 一人公司的增长系统矩阵',
    'seo_desc'=>'一个人做不了所有事。芭乐派把增长拆成七件可组合的产品：整套系统、内容分发、落地页、全域数据、外部情报、收款变现、课程交付。开源、可自托管、数据主权在你。',
    'blocks'=>[
        ['_type'=>'hero','_key'=>$k(),'title'=>'你不缺怎么做，你缺该做什么','subtitle'=>'芭乐派 · 给一人公司的增长系统','image'=>$SHOT.'/workspace.png'.$SHOT_V,'image_caption'=>'app.openflow / workspace','content'=>'市面上的增长工具都默认你有一支团队。芭乐派做的是另一套：它自己爬信号、自己出草稿、自己盯该跟进谁——你只做判断，不做事。核心开源，数据在你自己的服务器。','button_text'=>'免费开始（开源）','button_url'=>'/courses','button_text2'=>'先看它一天干什么','button_url2'=>'#dayrun'],
        ['_type'=>'ticker','_key'=>$k(),'title'=>'现在就在跑的数字','subtitle'=>'可核验','content'=>"<div data-n=\"502\"><b>0</b><span>已发布文章</span></div><div data-n=\"44\"><b>0</b><span>展示区块</span></div><div data-n=\"116\"><b>0</b><span>API 端点</span></div><div data-n=\"128\"><b>0</b><span>契约测试</span></div>"],
        ['_type'=>'cluster','_key'=>$k(),'title'=>'一个人做不了所有事——为什么','subtitle'=>'现实','content'=><<<HTML
<div><h4>时间被切碎</h4><ul><li>写内容、投广告、盯数据、回客户都要人</li><li>判断反而没时间做</li></ul></div>
<div><h4>工具互相割裂</h4><ul><li>内容在 A、数据在 B、收款在 C</li><li>接不上就等于没有</li></ul></div>
<div><h4>重型系统用不动</h4><ul><li>一体化系统很全，但对小团队太重</li><li>落地成本高于收益</li></ul></div>
<div><h4>轻量工具扛不住</h4><ul><li>单点工具快，但拼不出闭环</li><li>做到一半就撞墙</li></ul></div>
HTML],
        ['_type'=>'bento','_key'=>$k(),'title'=>'七件产品，按需组合','subtitle'=>'产品矩阵','content'=><<<HTML
<div data-w="2"><h3>OpenFlow</h3><p>全站增长操作系统 · 四力合一。</p><a href="/product" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="2"><h3>MFlow</h3><p>轻量内容生产与分发，不换 CMS。</p><a href="/product/mflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="2"><h3>Webs Flow</h3><p>落地页专精，以小时计上线。</p><a href="/product/webs-flow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>UserLoop</h3><p>全域营销数据中枢 · 对接任何 MA。</p><a href="/product/userloop" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>inFlow</h3><p>外部情报：趋势 / 舆情 / 竞品。</p><a href="/product/inflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>PayFlow</h3><p>一行嵌入收款 · 订阅 / 裂变 / 佣金。</p><a href="/product/payflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>LearnFlow</h3><p>课程与训练营交付闭环。</p><a href="/product/learnflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
HTML],
        screens('不是效果图，是正在跑的系统', $k, $SHOT, $SHOT_V, $S1),
        ['_type'=>'tabs','_key'=>$k(),'title'=>'你是谁，就从哪里开始','subtitle'=>'按人群','content'=><<<HTML
<div data-tab="内容创作者"><h3>内容流水线</h3><ul><li><b>inFlow</b> 给选题情报</li><li><b>MFlow</b> 生成并多平台分发</li><li><b>UserLoop</b> 回收效果</li></ul><p style="color:var(--muted)">一个人当编辑部</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="电商卖家"><h3>投放转化闭环</h3><ul><li><b>Webs Flow</b> 做承接页</li><li><b>PayFlow</b> 收款</li><li><b>UserLoop</b> 沉淀全域数据</li></ul><p style="color:var(--muted)">投放→承接→收款→复购</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="独立开发者"><h3>收钱 + 数据</h3><ul><li><b>PayFlow</b> 三分钟接上收款</li><li><b>UserLoop</b> 打通广告与产品数据</li></ul><p style="color:var(--muted)">专注做产品</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="讲师 / 教练"><h3>课程交付 + 变现</h3><ul><li><b>LearnFlow</b> 交付课程与训练营</li><li><b>PayFlow</b> 收款</li><li><b>MFlow</b> 触达学员</li></ul><p style="color:var(--muted)">开营到结业到复购</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="团队 / 企业"><h3>整套系统</h3><ul><li><b>OpenFlow</b> 四力合一，统一后台</li><li>数据在一处，权限可控</li><li>其余产品按需嵌入</li></ul><a href="/product" style="color:var(--accent);font-weight:700">看 OpenFlow →</a></div>
HTML],
        ['_type'=>'journey','_key'=>$k(),'title'=>'能力框架：TIPS 四力','subtitle'=>'怎么做到','content'=><<<HTML
<div><h3>触达</h3><p>内容、渠道、分发。</p><ul><li>把对的信息递到对的人面前</li></ul></div>
<div><h3>洞察</h3><p>数据、画像、情报。</p><ul><li>从噪声里捞出该看的那几个数</li></ul></div>
<div><h3>个性化</h3><p>分群、推荐、动态内容。</p><ul><li>同一套系统，给每个人不同的答案</li></ul></div>
<div><h3>销售</h3><p>CRM、商业、会员。</p><ul><li>把流量变成收入，把收入变成复购</li></ul></div>
HTML],
        day($k),
        runlog($k),
        ['_type'=>'comparison','_key'=>$k(),'title'=>'自己做 vs 用芭乐派','subtitle'=>'对比','content'=><<<HTML
<table style="width:100%;border-collapse:collapse;min-width:880px">
<thead><tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">环节</th><th style="padding:10px">自己拼工具</th><th style="padding:10px">用芭乐派</th><th style="padding:10px">省下什么</th></tr></thead>
<tbody>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">选题</td><td style="padding:12px 10px">刷平台靠感觉，慢半拍</td><td style="padding:12px 10px">inFlow 全网采集 + 每日情报</td><td style="padding:12px 10px">每天 1-2 小时</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">内容</td><td style="padding:12px 10px">写完再逐个平台发</td><td style="padding:12px 10px">MFlow 生成 + 多平台分发</td><td style="padding:12px 10px">重复排版与复制</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">承接</td><td style="padding:12px 10px">排设计排期，等一周</td><td style="padding:12px 10px">Webs Flow 落地页，小时级上线</td><td style="padding:12px 10px">排期与外包</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">收款</td><td style="padding:12px 10px">接支付要建站、要开发</td><td style="padding:12px 10px">PayFlow 一行嵌入</td><td style="padding:12px 10px">开发与对接</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">数据</td><td style="padding:12px 10px">各平台后台各看一段</td><td style="padding:12px 10px">UserLoop 一处看清，能对接任何 MA</td><td style="padding:12px 10px">导表与对账</td></tr>
<tr><td style="padding:12px 10px">交付</td><td style="padding:12px 10px">课件、录播、作业散在各处</td><td style="padding:12px 10px">LearnFlow 上课→进度→证书闭环</td><td style="padding:12px 10px">人工点名与发证</td></tr>
</tbody></table>
HTML],
        ['_type'=>'quote-wall','_key'=>$k(),'title'=>'用过的人怎么说','subtitle'=>'真实反馈','content'=><<<HTML
<div>「以前每天 3 小时找选题改文章，现在爬完信号直接给草稿，我只管把关。」<br><span style="color:var(--muted);font-size:12.5px">— 陈默 · 内容工作室</span></div>
<div>「第一次一个人跑完整条内容流水线，从采集到收录没换过工具。」<br><span style="color:var(--muted);font-size:12.5px">— 独立开发者</span></div>
<div>「漏斗终于看得见了：哪个环节漏单，面板直接告诉你。」<br><span style="color:var(--muted);font-size:12.5px">— 增长负责人</span></div>
<div>「组件工厂太顺了——贴一段 HTML 就变成可复用模块。」<br><span style="color:var(--muted);font-size:12.5px">— 前端工程师</span></div>
<div>「课程播放器拖进度条很流畅，学员完课率肉眼可见在涨。」<br><span style="color:var(--muted);font-size:12.5px">— 训练营主理人</span></div>
<div>「不用换我的商城，接上就收款。」<br><span style="color:var(--muted);font-size:12.5px">— DTC 卖家</span></div>
HTML],
        ['_type'=>'countdown','_key'=>$k(),'title'=>'R.B.E 训练营 · 第 4 期','subtitle'=>'限时','content'=>'8 周，从利润公式到 Agent 系统，亲手设计出你的增长系统。报名通道定期关闭。','end'=>'2026-12-31 23:59:59'],
        ['_type'=>'accordion','_key'=>$k(),'title'=>'三条路，通向同一个地方','subtitle'=>'怎么开始','content'=><<<HTML
<details open><summary>要一整套</summary><div class="hacc-body"><p>OpenFlow 四力合一，开箱即用，数据在一处，团队统一后台。</p><a href="/product">看 OpenFlow →</a></div></details>
<details><summary>要单点</summary><div class="hacc-body"><p>产品矩阵七件，哪件缺用哪件，零依赖今天就能跑。</p><a href="/demo/products">看产品矩阵 →</a></div></details>
<details><summary>要方法论</summary><div class="hacc-body"><p>学院讲清楚增长方法论，课程带你从利润公式到 Agent 系统。</p><a href="/academy">去学院 →</a></div></details>
<details><summary>要开源自托管</summary><div class="hacc-body"><p>核心能力开源，数据留在自己服务器，生态可扩展。</p><a href="/docs">看文档 →</a></div></details>
HTML],
        ['_type'=>'faq','_key'=>$k(),'title'=>'常见问题','subtitle'=>'FAQ','content'=><<<HTML
<details><summary>芭乐派卖的是软件还是方法？</summary><p>两者都卖：软件是增长系统（产品矩阵），方法是学院里的课程与文档。软件核心开源可自托管，方法论随时可学。</p></details>
<details><summary>一定要买全家桶吗？</summary><p>不必。七件产品独立可用，按需组合。从最缺的那一环开始就好。</p></details>
<details><summary>数据会不会被绑死？</summary><p>不会。互通走公开 API，数据主权在你；想换平台，档案可迁移。</p></details>
HTML],
        form_block($k),
        ['_type'=>'cta','_key'=>$k(),'title'=>'让系统替你跑，你只做判断','subtitle'=>'芭乐派','content'=>'看产品矩阵，从最缺的那一环开始；或者直接上整套 OpenFlow。','button_text'=>'看产品矩阵','button_url'=>'/demo/products'],
        matrix('demo-home-v2', $k),
    ],
]);

echo "全部完成\n";
