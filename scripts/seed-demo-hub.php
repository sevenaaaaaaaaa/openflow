<?php
/**
 * Demo 三件套（预览版，不取代现有页面）
 *   /demo/products      总产品聚合页（矩阵分流）
 *   /demo/capabilities  总能力聚合页（能力全景）
 *   /demo/home-v2       v2 首页（矩阵叙事）
 * 用法: php scripts/seed-demo-hub.php
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../lib/BuilderPages.php';

$k = 'block_new_key';

function upsert_page(string $slug, array $page): void {
    $pages = builder_pages_all();
    foreach ($pages as $p) {
        if (($p['slug'] ?? '') === $slug) {
            save_builder_page($p['id'], ['blocks' => $page['blocks'], 'title' => $page['title'], 'seo_title' => $page['seo_title'], 'seo_desc' => $page['seo_desc']]);
            echo "♻ $slug 重写\n";
            return;
        }
    }
    save_builder_page('', $page);
    echo "✅ $slug 创建\n";
}

/* ═══════════════ A · 总产品聚合页 ═══════════════ */
$productCards = <<<HTML
<div><span class="tg-tag">全家桶</span><h3>OpenFlow · 增长操作系统</h3><p>内容·数据·触达·销售四力合一。要一整套系统的团队。</p><a href="/product" style="color:var(--accent);font-size:13px;font-weight:700">进入 OpenFlow →</a></div>
<div><span class="tg-tag">轻量内容</span><h3>MFlow · 内容生产与分发</h3><p>不换 CMS、不建 CDP、不锁模型。内容创作者与电商卖家。</p><a href="/product/mflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 MFlow →</a></div>
<div><span class="tg-tag">落地页</span><h3>Webs Flow · 落地页专精</h3><p>素材到上线以小时计，投放原生。投手与活动运营。</p><a href="/product/webs-flow" style="color:var(--accent);font-size:13px;font-weight:700">进入 Webs Flow →</a></div>
<div><span class="tg-tag">全域数据</span><h3>UserLoop · 全域营销数据中枢</h3><p>独立运行，对接任何 MA。多平台运营团队。</p><a href="/product/userloop" style="color:var(--accent);font-size:13px;font-weight:700">进入 UserLoop →</a></div>
<div><span class="tg-tag">外部情报</span><h3>inFlow · 情报增长站</h3><p>零 CMS/CDP 依赖，盯趋势舆情竞品。品牌与内容策划。</p><a href="/product/inflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 inFlow →</a></div>
<div><span class="tg-tag">收款变现</span><h3>PayFlow · 商业变现引擎</h3><p>一行嵌入收款，订阅/裂变/佣金。创作者与独立开发者。</p><a href="/product/payflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 PayFlow →</a></div>
<div><span class="tg-tag">课程交付</span><h3>LearnFlow · 课程与训练营</h3><p>上课→进度→测验→证书。讲师与训练营主理人。</p><a href="/product/learnflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 LearnFlow →</a></div>
HTML;

$comboCards = <<<HTML
<div data-w="3"><h3>🎯 单点起步</h3><p>只要一件事：收款 → PayFlow；落地页 → Webs Flow；情报 → inFlow。零依赖，今天就能用。</p></div>
<div data-w="3"><h3>🛒 电商增长组合</h3><p><b>Webs Flow</b>（承接页）+ <b>PayFlow</b>（收款）+ <b>UserLoop</b>（全域数据）。投放→承接→收款→沉淀，一条线跑通。</p></div>
<div data-w="3"><h3>📣 内容营销组合</h3><p><b>inFlow</b>（选题情报）+ <b>MFlow</b>（生产与分发）+ <b>UserLoop</b>（效果回流）。内容从哪来、发去哪、效果如何，闭环。</p></div>
<div data-w="3"><h3>🎓 知识变现组合</h3><p><b>LearnFlow</b>（课程交付）+ <b>PayFlow</b>（收款）+ <b>MFlow</b>（学员触达）。开营到复购。</p></div>
<div data-w="6"><h3>🏢 全家桶组合：OpenFlow</h3><p>要一整套自转系统、数据要在一处、团队要统一后台——OpenFlow 四力合一，其余六件按需嵌入。本文档所有产品的数据都能平滑并入。</p></div>
HTML;

upsert_page('demo-products', [
    'slug' => 'demo-products', 'title' => '产品矩阵总览（预览）', 'status' => 'published',
    'seo_title' => '产品矩阵 — 按需组合的增长系统 | 芭乐派',
    'seo_desc' => '七个独立产品按需组合：OpenFlow 全家桶、MFlow 轻量内容、Webs Flow 落地页、UserLoop 全域数据、inFlow 情报、PayFlow 收款、LearnFlow 课程交付。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '不必一次买全家桶，按需组合成你的增长系统', 'subtitle' => '产品矩阵 · 总览', 'content' => '芭乐派把增长拆成七件独立产品：每件都能单独跑，组合起来又是一整套系统。你要什么，就从哪里开始——互通走 API，没有人被绑死。', 'button_text' => '看七个产品', 'button_url' => '#products'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>7</b><span>独立产品</span></div>
<div><b>35</b><span>展示区块</span></div>
<div><b>0</b><span>强制绑定</span></div>
<div><b>API</b><span>互通增益</span></div>
HTML],
        // 核心：按需求分流（三步选型）
        ['_type' => 'journey', '_key' => $k(), 'title' => '三步找到你的入口', 'subtitle' => '怎么选', 'content' => <<<HTML
<div><h3>你缺什么？</h3><p>是缺一整套系统，还是缺某一个具体环节（收款/落地页/情报/交付）？先定位缺口。</p></div>
<div><h3>你的依赖假设？</h3><p>已经有 CMS/商城/MA 的，选零依赖的单点产品；想要一处的，选全家桶。</p></div>
<div><h3>谁在用？</h3><p>一个人用单点产品更快；团队协作、要统一数据，走 OpenFlow 底座 + 按需嵌入。</p></div>
HTML],
        // 七个产品
        ['_type' => 'tool-grid', '_key' => $k(), 'id' => 'products', 'title' => '七个独立产品', 'subtitle' => '产品矩阵', 'content' => $productCards],
        // 组合推荐
        ['_type' => 'bento', '_key' => $k(), 'title' => '常见组合：按场景拼装', 'subtitle' => '组合方案', 'content' => $comboCards],
        // 对比表
        ['_type' => 'comparison', '_key' => $k(), 'title' => '一眼看清差异', 'subtitle' => '怎么区分', 'content' => <<<HTML
<table style="width:100%;border-collapse:collapse;min-width:720px">
<thead><tr style="text-align:left;border-bottom:1px solid var(--border)">
<th style="padding:10px">产品</th><th style="padding:10px">一句话</th><th style="padding:10px">依赖</th><th style="padding:10px">为谁</th></tr></thead>
<tbody>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>OpenFlow</b></td><td style="padding:12px 10px">全站增长操作系统</td><td style="padding:12px 10px">深度（自家 CMS+CDP）</td><td style="padding:12px 10px">要一整套系统的团队</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>MFlow</b></td><td style="padding:12px 10px">内容生产 + 分发 + 轻触达</td><td style="padding:12px 10px">低（兼容现成 CMS）</td><td style="padding:12px 10px">创作者 / 电商卖家</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>Webs Flow</b></td><td style="padding:12px 10px">落地页专精</td><td style="padding:12px 10px">无</td><td style="padding:12px 10px">投手 / 活动运营</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>UserLoop</b></td><td style="padding:12px 10px">全域营销数据中枢</td><td style="padding:12px 10px">无（对接任何 MA）</td><td style="padding:12px 10px">多平台运营团队</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>inFlow</b></td><td style="padding:12px 10px">外部情报（趋势/舆情/竞品）</td><td style="padding:12px 10px">无（依赖外部数据源）</td><td style="padding:12px 10px">品牌 / 内容策划</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px"><b>PayFlow</b></td><td style="padding:12px 10px">收款 + 订阅 + 裂变佣金</td><td style="padding:12px 10px">无（一行嵌入）</td><td style="padding:12px 10px">创作者 / 独立开发者</td></tr>
<tr><td style="padding:12px 10px"><b>LearnFlow</b></td><td style="padding:12px 10px">课程与训练营交付</td><td style="padding:12px 10px">轻（收款接 PayFlow）</td><td style="padding:12px 10px">讲师 / 训练营主理人</td></tr>
</tbody></table>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '独立运行，API 互相增益', 'subtitle' => '互通原则', 'content' => <<<HTML
<div><b>不共享数据库</b><span>一切互通走公开 API（HMAC / API Key），各自的数据各自主权。</span></div>
<div><b>拔掉任何一个照常跑</b><span>互通是增益不是依赖——每个产品都能单独交付价值。</span></div>
<div><b>升级无缝</b><span>从单点产品进入后想升级全家桶，数据可平滑并入 OpenFlow。</span></div>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>可以只用其中一个产品吗？</summary><p>可以。七个产品都独立可用——单点产品零依赖，单独交付价值。</p></details>
<details><summary>组合起来会不会很复杂？</summary><p>不会。互通走 API 自动完成，产品之间不需要你手工搬运数据。</p></details>
<details><summary>分不清该选哪个怎么办？</summary><p>用上面的「三步找到你的入口」。或者从最常见的组合方案里挑一个开始。</p></details>
HTML],
        ['_type' => 'cta', '_key' => $k(), 'title' => '从最缺的那一环开始', 'subtitle' => '产品矩阵', 'content' => '单点产品今天就能用；想要整套，OpenFlow 随时接住你。', 'button_text' => '看能力全景', 'button_url' => '/demo/capabilities'],
    ],
]);

/* ═══════════════ B · 总能力聚合页 ═══════════════ */
upsert_page('demo-capabilities', [
    'slug' => 'demo-capabilities', 'title' => '能力全景（预览）', 'status' => 'published',
    'seo_title' => '能力全景 — 四力合一与支撑体系 | 芭乐派',
    'seo_desc' => 'TIPS 四力（触达/洞察/个性化/销售）+ 行动脊柱、增长引擎、开源底座，每项能力对应哪些产品、解决什么问题。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '一套能力框架，四个力加三层支撑', 'subtitle' => '能力 · 全景', 'content' => 'TIPS 四力回答「增长靠什么」：触达、洞察、个性化、销售。行动脊柱、增长引擎与开源底座，保证这些能力真的跑起来、越跑越准。', 'button_text' => '看能力地图', 'button_url' => '#map'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>4</b><span>核心四力</span></div>
<div><b>3</b><span>支撑层</span></div>
<div><b>42</b><span>真实模块</span></div>
<div><b>7</b><span>产品承载</span></div>
HTML],
        // 四力 tabs（按力看能力清单）
        ['_type' => 'tabs', '_key' => $k(), 'id' => 'map', 'title' => '四力：每一项能力解决什么', 'subtitle' => 'TIPS', 'content' => <<<HTML
<div data-tab="触达 Touch"><h3>把对的内容递到对的人面前</h3><p><b>内容引擎</b>：文章/课程/资料/播客一站式生产管理。<b>创作台</b>：深度专栏、口播脚本、品牌幻灯片，AI 结构化产出。<b>SEO/GEO</b>：四大搜索引擎接入 + 全网话题采集。<b>Newsletter</b>：选文章自动排版定时发送。</p><p style="margin-top:8px;color:var(--accent)">承载产品：MFlow（轻量） · OpenFlow（全家桶） · Webs Flow（承接页）</p></div>
<div data-tab="洞察 Insight"><h3>从几百个指标捞出该看的那 3-5 个</h3><p><b>CDP 画像</b>：身份合并、一人一册行为时间线。<b>分群与 RFM</b>：行为驱动动态标签。<b>转化漏斗</b>：访客→注册→加购→支付→复购。<b>问数据</b>：自然语言直接问经营数据。<b>外部情报</b>：趋势/舆情/竞品。</p><p style="margin-top:8px;color:var(--accent)">承载产品：inFlow（外部情报） · UserLoop（全域数据） · OpenFlow（自有数据）</p></div>
<div data-tab="个性化 Personality"><h3>同一套系统，给每个人不同的答案</h3><p><b>人群定向</b>：任何页面区块按登录态/分群/UTM 显示不同内容。<b>推荐引擎</b>：按画像与行为推荐内容与商品。<b>动态内容</b>：规则驱动的个性化呈现。</p><p style="margin-top:8px;color:var(--accent)">承载产品：UserLoop（数据底座） · OpenFlow（全站个性化）</p></div>
<div data-tab="销售 Sales"><h3>把流量变成收入，把收入变成复购</h3><p><b>CRM 与线索</b>：线索池、管道、跟进。<b>商业闭环</b>：购物车、订阅计费、优惠券、推荐码、佣金结算。<b>会员体系</b>：等级与权益。<b>集市</b>：数字商品上架交付。</p><p style="margin-top:8px;color:var(--accent)">承载产品：PayFlow（收款变现） · LearnFlow（课程交付） · OpenFlow（全站商业）</p></div>
HTML],
        // 三层支撑
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '四力之下的三层支撑', 'subtitle' => '支撑体系', 'content' => <<<HTML
<div><b>行动脊柱 Agentic</b><span>Agent 编排、工作流、自动化：让能力自己按流程跑，人只做判断。</span></div>
<div><b>增长引擎</b><span>信号采集 → 规则判断 → 具体动作：系统自己发现机会并建议怎么用。</span></div>
<div><b>永久开源</b><span>核心能力开源可自托管：数据主权与长期可维护性不打折。</span></div>
HTML],
        // 能力 → 产品映射
        ['_type' => 'comparison', '_key' => $k(), 'title' => '同一项能力，由哪个产品承载', 'subtitle' => '能力 → 产品', 'content' => <<<HTML
<table style="width:100%;border-collapse:collapse;min-width:720px">
<thead><tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">能力</th><th style="padding:10px">全家桶 OpenFlow</th><th style="padding:10px">单点产品</th></tr></thead>
<tbody>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">内容生产与分发</td><td style="padding:12px 10px">内容引擎 + 创作台</td><td style="padding:12px 10px"><b>MFlow</b></td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">落地页与投放承接</td><td style="padding:12px 10px">页面构建器（面向全站）</td><td style="padding:12px 10px"><b>Webs Flow</b>（专精落地页）</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">用户数据与分群</td><td style="padding:12px 10px">CDP（自有数据）</td><td style="padding:12px 10px"><b>UserLoop</b>（全域数据）</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">外部情报</td><td style="padding:12px 10px">GEO 采集（站点选题）</td><td style="padding:12px 10px"><b>inFlow</b>（趋势/舆情/竞品）</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">收款与变现</td><td style="padding:12px 10px">商业与订阅</td><td style="padding:12px 10px"><b>PayFlow</b>（一行嵌入）</td></tr>
<tr><td style="padding:12px 10px">课程交付</td><td style="padding:12px 10px">课程系统</td><td style="padding:12px 10px"><b>LearnFlow</b>（交付闭环）</td></tr>
</tbody></table>
HTML],
        ['_type' => 'accordion', '_key' => $k(), 'title' => '能力怎么落地：四条主路线', 'subtitle' => '落地方式', 'content' => <<<HTML
<details open><summary>自己组装</summary><div class="hacc-body"><p>按上面的映射，从产品矩阵里挑需要的能力，API 互通自动衔接。</p><a href="/demo/products">看产品矩阵 →</a></div></details>
<details><summary>整套采用</summary><div class="hacc-body"><p>OpenFlow 四力合一，开箱即用，无需自己组装。</p><a href="/product">看 OpenFlow →</a></div></details>
<details><summary>开源自托管</summary><div class="hacc-body"><p>核心能力开源，数据完全留在自己的服务器。</p><a href="/docs">看部署文档 →</a></div></details>
<details><summary>生态扩展</summary><div class="hacc-body"><p>插件、Skills、MCP 生态：能力不够时自己或第三方扩展。</p><a href="/marketplace">逛生态市场 →</a></div></details>
HTML],
        ['_type' => 'cta', '_key' => $k(), 'title' => '能力是框架，产品是交付', 'subtitle' => '能力全景', 'content' => '想清楚要哪些能力，再去产品矩阵挑，组合成你自己的系统。', 'button_text' => '去产品矩阵', 'button_url' => '/demo/products'],
    ],
]);

/* ═══════════════ C · v2 首页 ═══════════════ */
upsert_page('demo-home-v2', [
    'slug' => 'demo-home-v2', 'title' => '首页 v2（预览）', 'status' => 'published',
    'seo_title' => '芭乐派 — 一人公司的增长系统矩阵',
    'seo_desc' => '一个人做不了所有事。芭乐派把增长拆成七件可组合的产品：整套系统、内容分发、落地页、全域数据、外部情报、收款变现、课程交付。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '一个人做不了所有事，但可以让系统替你跑', 'subtitle' => '芭乐派 · 增长系统矩阵', 'content' => '我们不卖一个万能工具，而是一套按需组合的增长系统：从整套操作系统到单点能力，七件产品，各自独立，API 互通。你专注判断，其余交给系统。', 'button_text' => '看产品矩阵', 'button_url' => '/demo/products'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>7</b><span>独立产品</span></div>
<div><b>4</b><span>核心能力力</span></div>
<div><b>42</b><span>真实模块</span></div>
<div><b>1 人</b><span>也能驱动</span></div>
HTML],
        // 痛点：一个人做不了所有
        ['_type' => 'cluster', '_key' => $k(), 'title' => '为什么一个人跑不动一整套增长', 'subtitle' => '现实', 'content' => <<<HTML
<div><h4>时间被切碎</h4><p>写内容、投广告、盯数据、回客户——每件事都要人，判断反而没时间做。</p></div>
<div><h4>工具互相割裂</h4><p>内容在 A、数据在 B、收款在 C，接不上就等于没有。</p></div>
<div><h4>重型系统用不动</h4><p>一体化系统很全，但对小团队太重，落地成本高于收益。</p></div>
<div><h4>轻量工具扛不住</h4><p>单点工具快，但拼不出闭环，做到一半就撞墙。</p></div>
HTML],
        // 矩阵总览
        ['_type' => 'bento', '_key' => $k(), 'title' => '七件产品，按需组合', 'subtitle' => '产品矩阵', 'content' => <<<HTML
<div data-w="2"><h3>OpenFlow</h3><p>全站增长操作系统 · 四力合一。</p><a href="/product" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="2"><h3>MFlow</h3><p>轻量内容生产与分发。</p><a href="/product/mflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="2"><h3>Webs Flow</h3><p>落地页专精，以小时计。</p><a href="/product/webs-flow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>UserLoop</h3><p>全域营销数据中枢 · 对接任何 MA。</p><a href="/product/userloop" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>inFlow</h3><p>外部情报：趋势 / 舆情 / 竞品。</p><a href="/product/inflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>PayFlow</h3><p>一行嵌入收款 · 订阅 / 裂变 / 佣金。</p><a href="/product/payflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>LearnFlow</h3><p>课程与训练营交付闭环。</p><a href="/product/learnflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
HTML],
        // 按人群推荐
        ['_type' => 'tabs', '_key' => $k(), 'title' => '你是谁，就从哪里开始', 'subtitle' => '按人群', 'content' => <<<HTML
<div data-tab="内容创作者"><h3>内容流水线</h3><p><b>inFlow</b> 给选题情报 → <b>MFlow</b> 生成并多平台分发 → <b>UserLoop</b> 回收效果。一个人当编辑部。</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="电商卖家"><h3>投放转化闭环</h3><p><b>Webs Flow</b> 做承接页 → <b>PayFlow</b> 收款 → <b>UserLoop</b> 沉淀全域数据。投放→承接→收款→复购。</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="独立开发者"><h3>收钱 + 数据</h3><p><b>PayFlow</b> 三分钟接上收款；<b>UserLoop</b> 打通广告与产品数据。专注做产品。</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="讲师 / 教练"><h3>课程交付 + 变现</h3><p><b>LearnFlow</b> 交付课程与训练营；<b>PayFlow</b> 收款；<b>MFlow</b> 触达学员。</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="团队 / 企业"><h3>整套系统</h3><p><b>OpenFlow</b> 四力合一，团队统一后台，数据在一处，其余产品按需嵌入。</p><a href="/product" style="color:var(--accent);font-weight:700">看 OpenFlow →</a></div>
HTML],
        // 能力框架
        ['_type' => 'journey', '_key' => $k(), 'title' => '能力框架：TIPS 四力', 'subtitle' => '怎么做到', 'content' => <<<HTML
<div><h3>触达</h3><p>内容、渠道、分发：把对的信息递到对的人面前。</p></div>
<div><h3>洞察</h3><p>数据、画像、情报：从噪声里捞出该看的那几个数。</p></div>
<div><h3>个性化</h3><p>同一套系统，给每个人不同的答案。</p></div>
<div><h3>销售</h3><p>把流量变成收入，把收入变成复购。</p></div>
HTML],
        // 证据
        ['_type' => 'quote-wall', '_key' => $k(), 'title' => '用过的人怎么说', 'subtitle' => '真实反馈', 'content' => <<<HTML
<div>「以前每天 3 小时找选题改文章，现在爬完信号直接给草稿，我只管把关。」— 陈默，内容工作室</div>
<div>「第一次一个人跑完整条内容流水线，从采集到收录没换过工具。」— 独立开发者</div>
<div>「漏斗终于看得见了：哪个环节漏单，面板直接告诉你。」— 增长负责人</div>
<div>「组件工厂太顺了——贴一段 HTML 就变成可复用模块。」— 前端工程师</div>
<div>「课程播放器拖进度条很流畅，学员完课率肉眼可见在涨。」— 训练营主理人</div>
<div>「不用换我的商城，接上就收款。」— DTC 卖家</div>
HTML],
        // 三条路径
        ['_type' => 'accordion', '_key' => $k(), 'title' => '三条路，通向同一个地方', 'subtitle' => '怎么开始', 'content' => <<<HTML
<details open><summary>要一整套</summary><div class="hacc-body"><p>OpenFlow 四力合一，开箱即用，数据在一处，团队统一后台。</p><a href="/product">看 OpenFlow →</a></div></details>
<details><summary>要单点</summary><div class="hacc-body"><p>产品矩阵七件，哪件缺用哪件，零依赖今天就能跑。</p><a href="/demo/products">看产品矩阵 →</a></div></details>
<details><summary>要方法论</summary><div class="hacc-body"><p>学院讲清楚增长方法论，课程带你从利润公式到 Agent 系统。</p><a href="/academy">去学院 →</a></div></details>
<details><summary>要开源自托管</summary><div class="hacc-body"><p>核心能力开源，数据留在自己服务器，生态可扩展。</p><a href="/docs">看文档 →</a></div></details>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>芭乐派卖的是软件还是方法？</summary><p>两者都卖：软件是增长系统（产品矩阵），方法是学院里的课程与文档。软件开源可自托管，方法论随时可学。</p></details>
<details><summary>一定要买全家桶吗？</summary><p>不必。七件产品独立可用，按需组合。从最缺的那一环开始就好。</p></details>
<details><summary>数据会不会被绑死？</summary><p>不会。互通走公开 API，数据主权在你；想换平台，档案可迁移。</p></details>
HTML],
        ['_type' => 'cta', '_key' => $k(), 'title' => '让系统替你跑，你只做判断', 'subtitle' => '芭乐派', 'content' => '看产品矩阵，从最缺的那一环开始；或者直接上整套 OpenFlow。', 'button_text' => '看产品矩阵', 'button_url' => '/demo/products'],
    ],
]);

echo "全部完成\n";
