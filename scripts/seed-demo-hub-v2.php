<?php
/**
 * Demo 三件套 v2 —— 按「逐模块/逐标题/逐图/逐交互」对照原页后的优化版
 *
 * 相对原页补齐的关键项：
 *   1. 真实界面截图（原 /product、/capability 有，demo 之前没有）
 *   2. 部署方式三档（SaaS / 私有化 / 混合）—— 原 /capability 有，是成单关键
 *   3. 开放生态（API / Webhook / Skill / 永久开源）
 *   4. 转化出口：预约诊断表单（原首页有 lead 表单，demo 之前只有链接）
 *   5. 可核验数字（43 区块 / 116 API / 42 模块 / 502 文章 / 128 测试）
 *   6. 交互密度：tabs / accordion / ticker / countdown / quote-wall / bento 截图墙
 *   7. 双 CTA hero（原首页是双 CTA，demo 之前只有单 CTA）
 *
 * 用法: php scripts/seed-demo-hub-v2.php
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../lib/BuilderPages.php';

$k = 'block_new_key';
$SHOT = '/assets/images/product';
$SHOT_V = '?v=20260911';   // 图片换版必须动这个（immutable 缓存最长 30 天，否则用户看到旧图）

function upsert_page(string $slug, array $page): void {
    $pages = builder_pages_all();
    foreach ($pages as $p) {
        if (($p['slug'] ?? '') === $slug) {
            save_builder_page($p['id'], ['blocks' => $page['blocks'], 'title' => $page['title'], 'seo_title' => $page['seo_title'], 'seo_desc' => $page['seo_desc']]);
            echo "♻ {$slug} 已重写（" . count($page['blocks']) . " 区块）\n";
            return;
        }
    }
    save_builder_page('', $page);
    echo "✅ {$slug} 已创建（" . count($page['blocks']) . " 区块）\n";
}

/** 真实界面截图墙（三个 demo 共用） */
function block_screens(string $title, callable $k, string $SHOT, string $SHOT_V, array $items): array {
    $html = '';
    foreach ($items as $it) {
        [$w, $file, $name, $desc] = $it;
        $html .= "<div data-w=\"{$w}\"><img src=\"{$SHOT}/{$file}{$SHOT_V}\" alt=\"{$name}\" loading=\"lazy\"><h3>{$name}</h3><p>{$desc}</p></div>";
    }
    return ['_type' => 'bento', '_key' => $k(), 'title' => $title, 'subtitle' => '真实界面 · 不是效果图', 'content' => $html];
}

/** 部署方式三档（SaaS / 私有化 / 混合） */
function block_deploy(callable $k): array {
    return ['_type' => 'tabs', '_key' => $k(), 'title' => '三种部署方式，你说了算', 'subtitle' => '部署', 'content' => <<<HTML
<div data-tab="云端 SaaS"><h3>开箱即用，免费起步</h3><p>不用买服务器、不用运维：注册即用，功能随版本自动更新。适合先跑起来、验证打法的一人公司与小团队。</p><p style="margin-top:8px;color:var(--accent);font-weight:700">适合：想当天就开工的人</p></div>
<div data-tab="私有化部署"><h3>数据完全留在内网</h3><p>核心引擎自托管（本项目即开源底座，rsync + Apache 即可上线），数据、日志、模型调用全部在自己服务器上。适合对数据敏感的企业与机构。</p><p style="margin-top:8px;color:var(--accent);font-weight:700">适合：数据不出内网的团队</p></div>
<div data-tab="混合架构"><h3>核心私有 + 云端弹性</h3><p>核心引擎私有部署，峰值的采集/生成等重活走云端弹性扩缩容。兼顾合规与成本。适合既有合规要求又需要弹性的业务。</p><p style="margin-top:8px;color:var(--accent);font-weight:700">适合：既要合规又要弹性</p></div>
HTML];
}

/** 开放生态 */
function block_open(callable $k): array {
    return ['_type' => 'checklist', '_key' => $k(), 'title' => '开放，是默认值', 'subtitle' => '生态', 'content' => <<<HTML
<li><b>开放 API</b>：116 个端点，公开内容、事件上报、分群服务都能对接（Swagger 文档在 /api/v1/docs）</li>
<li><b>Webhook 与连接器</b>：飞书 / Notion / Obsidian / 印象笔记内容导入；事件可推送到你的任何系统</li>
<li><b>Skill 与模板</b>：生态市场可安装技能、插件、主题；组件工厂还能用 AI 现场造新模块</li>
<li><b>永久开源</b>：核心能力开源可自托管，数据与代码都在你手里，随时可迁走</li>
HTML];
}

/** 预约诊断表单（转化出口） */
function block_form(callable $k): array {
    return ['_type' => 'form', '_key' => $k(), 'anchor' => 'diagnose',
        'title' => '30 分钟，告诉你哪一段最该交给 Agent',
        'subtitle' => '免费 · 30 分钟 · 由人给出判断',
        'content' => 'appointment'];
}


/** 接入信任条：你现有的模型与工具（均为已实现集成） */
function block_integrations(callable $k): array {
    return ['_type' => 'marquee', '_key' => $k(), 'title' => '不用推翻你现在在用的东西', 'subtitle' => '接入你现有的模型与工具', 'content' => <<<HTML
<div>OpenAI</div><div>DeepSeek</div><div>GLM</div><div>Kimi</div><div>Claude</div><div>Qwen</div>
<div>飞书</div><div>Notion</div><div>Obsidian</div><div>印象笔记</div><div>公众号</div><div>企业微信</div>
<div>Webhook</div><div>MCP</div><div>自托管模型</div>
HTML];
}

/** 「它一天干什么」走查（对标原页可点击闭环的叙事版） */
function block_day(callable $k): array {
    return ['_type' => 'tabs', '_key' => $k(), 'title' => '它一天在干什么', 'subtitle' => '增长闭环', 'content' => <<<HTML
<div data-tab="09:00 采集"><h3>先把外部信号收进来</h3><p>GEO 全网采集（RSS + 搜索 API）抓回你所在方向的新话题与竞品动作，去重、打分、进选题库。</p><p style="color:var(--muted);font-size:13.5px;margin-top:6px">产出：今日候选选题 + 热点热度</p></div>
<div data-tab="11:00 生成"><h3>AI 出初稿，人只把关</h3><p>按选题结构化产出初稿，接你配置的模型（OpenAI / DeepSeek / GLM / Kimi / 自托管均可），落进草稿箱等你审。</p><p style="color:var(--muted);font-size:13.5px;margin-top:6px">产出：草稿 + SEO 建议</p></div>
<div data-tab="14:00 分发"><h3>一次编辑，多端出去</h3><p>按渠道适配分发（站内 / 公众号 / Newsletter），自动排版与定时发布；收录推送与 sitemap 同步更新。</p><p style="color:var(--muted);font-size:13.5px;margin-top:6px">产出：已发布 + 收录</p></div>
<div data-tab="18:00 复盘"><h3>把效果收回来</h3><p>阅读、停留、线索、转化逐环回流；漏斗哪一环漏单直接标出来，增长引擎给出明天该动的那一件事。</p><p style="color:var(--muted);font-size:13.5px;margin-top:6px">产出：明日动作 + 归因</p></div>
HTML];
}


/** 「跑一遍」运行演示（输出为已实现的真实机制，不编造） */
function block_runlog(callable $k): array {
    return ['_type' => 'runlog', '_key' => $k(), 'title' => '它一天在干什么：跑一遍', 'subtitle' => 'run',
        'anchor' => 'dayrun', 'button_text' => '▶ 跑一遍',
        'content' => "$ openflow loop --today\n✓ 采集     GEO 全网抓取新话题（RSS + 搜索 API 双通道）\n✓ 选题     按你的方向打分去重，产出候选 3 个\n✓ 初稿     AI 出结构化草稿，落进草稿箱待你审\n✓ 发布     多端分发 + 收录推送（sitemap / IndexNow）\n✓ 回收     阅读·线索·转化回流，标出漏单的那一环\n✓ 体检     SelfEvolve 列出该修的清单（只给建议，不自动改）\n→ 结论     今天最该动的一件事，交给你判断"];
}

$SHOT_ITEMS = [
    [2, 'workspace.png', '控制台 · 今日主线', 'KPI、主线任务与 Agent 运行状态，一屏看完'],
    [2, 'content-hub.png', '内容中心', '文章 · 课程 · 资料 · 播客 一处管理'],
    [2, 'audience.png', 'CDP 用户画像', '身份合并后的行为时间线与分群'],
];
$SHOT_ITEMS2 = [
    [2, 'automation.png', '营销自动化', '画布编排：触发 → 条件 → 动作'],
    [2, 'studio.png', '编排画布', '可视化搭流程，人只做判断'],
    [2, 'crm.png', 'CRM 管道', '线索阶段与跟进记录'],
];

/* ═══════════════ A · 总产品聚合页 v2 ═══════════════ */
upsert_page('demo-products', [
    'slug' => 'demo-products', 'title' => '产品矩阵总览（预览）', 'status' => 'published',
    'seo_title' => '产品矩阵 — 七个独立产品，按需组合 | 芭乐派',
    'seo_desc' => '不必为不需要的能力付费：OpenFlow 全家桶、MFlow 内容分发、Webs Flow 落地页、UserLoop 全域数据、inFlow 情报、PayFlow 收款、LearnFlow 课程交付。零强制绑定，API 互通。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '七个独立产品，按需组合成一套系统', 'subtitle' => '产品矩阵 · 总览', 'content' => '不必为一个用不上的大系统付费。每件产品都能单独跑、单独见效；组合起来，就是一套自转的增长系统。互通走 API——拔掉任何一个，其余照常运行。', 'button_text' => '看七个产品', 'button_url' => '#products', 'button_text2' => '预约增长诊断', 'button_url2' => '#diagnose'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>7</b><span>独立产品</span></div>
<div><b>0</b><span>强制绑定</span></div>
<div><b>116</b><span>API 端点（互通）</span></div>
<div><b>502</b><span>篇真实内容在跑</span></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '三步找到你的入口', 'subtitle' => '怎么选', 'content' => <<<HTML
<div><h3>你缺的是哪一环？</h3><p>整套系统？还是具体某环——收款、落地页、情报、内容分发、课程交付。先定位缺口，别为不需要的付费。</p></div>
<div><h3>你的依赖假设是什么？</h3><p>已有 CMS / 商城 / MA 的，选零依赖单点产品（PayFlow、Webs Flow、inFlow、UserLoop）；想数据在一处的，走 OpenFlow 底座。</p></div>
<div><h3>谁在用、几个人用？</h3><p>一个人用单点产品更快见效；团队协作、要统一数据与权限，用 OpenFlow + 按需嵌入其余产品。</p></div>
HTML],
        ['_type' => 'tool-grid', '_key' => $k(), 'anchor' => 'products', 'title' => '七个独立产品', 'subtitle' => '产品矩阵', 'content' => <<<HTML
<div><span class="tg-tag">全家桶</span><h3>OpenFlow · 增长操作系统</h3><p>内容·数据·触达·销售四力合一。<b>依赖</b>：深度（自家 CMS+CDP）·<b>为谁</b>：要一整套系统的团队</p><a href="/product" style="color:var(--accent);font-size:13px;font-weight:700">进入 OpenFlow →</a></div>
<div><span class="tg-tag">轻量内容</span><h3>MFlow · 内容生产与分发</h3><p>不换 CMS、不建 CDP、不锁模型。<b>依赖</b>：低 ·<b>为谁</b>：内容创作者、电商卖家</p><a href="/product/mflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 MFlow →</a></div>
<div><span class="tg-tag">落地页</span><h3>Webs Flow · 落地页专精</h3><p>素材到上线以小时计，投放原生。<b>依赖</b>：无 ·<b>为谁</b>：投手、活动运营</p><a href="/product/webs-flow" style="color:var(--accent);font-size:13px;font-weight:700">进入 Webs Flow →</a></div>
<div><span class="tg-tag">全域数据</span><h3>UserLoop · 全域营销数据中枢</h3><p>独立运行，对接你现有的任何 MA。<b>依赖</b>：无 ·<b>为谁</b>：多平台运营团队</p><a href="/product/userloop" style="color:var(--accent);font-size:13px;font-weight:700">进入 UserLoop →</a></div>
<div><span class="tg-tag">外部情报</span><h3>inFlow · 情报增长站</h3><p>零 CMS/CDP 依赖，盯趋势、舆情、竞品。<b>依赖</b>：无 ·<b>为谁</b>：品牌、内容策划</p><a href="/product/inflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 inFlow →</a></div>
<div><span class="tg-tag">收款变现</span><h3>PayFlow · 商业变现引擎</h3><p>一行嵌入收款，订阅 / 裂变 / 佣金。<b>依赖</b>：无 ·<b>为谁</b>：创作者、独立开发者</p><a href="/product/payflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 PayFlow →</a></div>
<div><span class="tg-tag">课程交付</span><h3>LearnFlow · 课程与训练营</h3><p>上课→进度→测验→证书。<b>依赖</b>：轻（收款接 PayFlow）·<b>为谁</b>：讲师、训练营主理人</p><a href="/product/learnflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 LearnFlow →</a></div>
HTML],
        block_screens('这些界面现在就在跑，不是概念图', $k, $SHOT, $SHOT_V, $SHOT_ITEMS),
        ['_type' => 'bento', '_key' => $k(), 'title' => '常见组合：按场景拼装', 'subtitle' => '组合方案', 'content' => <<<HTML
<div data-w="3"><h3>🎯 单点起步</h3><p>只缺一件事：收款 → <b>PayFlow</b>；落地页 → <b>Webs Flow</b>；情报 → <b>inFlow</b>。零依赖，今天就能用。</p></div>
<div data-w="3"><h3>🛒 电商增长组合</h3><p><b>Webs Flow</b>（承接页）+ <b>PayFlow</b>（收款）+ <b>UserLoop</b>（全域数据）。投放→承接→收款→沉淀。</p></div>
<div data-w="3"><h3>📣 内容营销组合</h3><p><b>inFlow</b>（选题情报）+ <b>MFlow</b>（生产与分发）+ <b>UserLoop</b>（效果回流）。</p></div>
<div data-w="3"><h3>🎓 知识变现组合</h3><p><b>LearnFlow</b>（课程交付）+ <b>PayFlow</b>（收款）+ <b>MFlow</b>（学员触达）。</p></div>
<div data-w="6"><h3>🏢 全家桶组合：OpenFlow</h3><p>要一整套自转系统、数据要在一处、团队要统一后台——OpenFlow 四力合一，其余六件按需嵌入。所有产品的数据都能平滑并入。</p></div>
HTML],
        ['_type' => 'comparison', '_key' => $k(), 'title' => '一眼看清差异', 'subtitle' => '怎么区分', 'content' => <<<HTML
<table style="width:100%;border-collapse:collapse;min-width:760px">
<thead><tr style="text-align:left;border-bottom:1px solid var(--border)">
<th style="padding:10px">产品</th><th style="padding:10px">一句话</th><th style="padding:10px">依赖</th><th style="padding:10px">适合谁</th></tr></thead>
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
        block_runlog($k),
        block_deploy($k),
        block_open($k),
        block_integrations($k),
        ['_type' => 'quote-wall', '_key' => $k(), 'title' => '用过的人怎么说', 'subtitle' => '真实反馈', 'content' => <<<HTML
<div>「以前每天 3 小时找选题改文章，现在爬完信号直接给草稿，我只管把关。」— 陈默，内容工作室</div>
<div>「第一次一个人跑完整条内容流水线，从采集到收录没换过工具。」— 独立开发者</div>
<div>「漏斗终于看得见了：哪个环节漏单，面板直接告诉你。」— 增长负责人</div>
<div>「不用换我的商城，接上就收款。」— DTC 卖家</div>
<div>「组件工厂太顺了——贴一段 HTML 就变成可复用模块。」— 前端工程师</div>
<div>「课程播放器拖进度条很流畅，学员完课率肉眼可见在涨。」— 训练营主理人</div>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>可以只用其中一个产品吗？</summary><p>可以。七个产品都独立可用——单点产品零依赖，单独交付价值。</p></details>
<details><summary>以后想升级成整套怎么办？</summary><p>数据可平滑并入 OpenFlow：身份、内容、订单都有迁移通道，不用重建。</p></details>
<details><summary>数据放在你们那里安全吗？</summary><p>三种部署任选：云端 SaaS、私有化自托管、混合。核心开源，数据与代码都在你手里。</p></details>
<details><summary>组合起来会不会很复杂？</summary><p>不会。互通走 API 自动完成，产品之间不需要你手工搬运数据。</p></details>
HTML],
        block_form($k),
        ['_type' => 'cta', '_key' => $k(), 'title' => '从最缺的那一环开始', 'subtitle' => '产品矩阵', 'content' => '单点产品今天就能用；想要整套，OpenFlow 随时接住你。', 'button_text' => '看能力全景', 'button_url' => '/demo/capabilities'],
    ],
]);

/* ═══════════════ B · 总能力聚合页 v2 ═══════════════ */
upsert_page('demo-capabilities', [
    'slug' => 'demo-capabilities', 'title' => '能力全景（预览）', 'status' => 'published',
    'seo_title' => '能力全景 — TIPS 四力 + 三层支撑（42 个真实模块） | 芭乐派',
    'seo_desc' => 'TIPS 四力（触达/洞察/个性化/销售）+ 行动脊柱、增长引擎、永久开源。42 个模块全部可在代码与后台核验，每项能力标注承载产品。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '四力加三层支撑，能力全部可核验', 'subtitle' => '能力 · 全景', 'content' => 'TIPS 四力回答「增长靠什么」：触达、洞察、个性化、销售。行动脊柱、增长引擎与开源底座保证它们真的跑起来。每一项能力都标注了由哪个产品承载、多少个真实模块。', 'button_text' => '看 42 个模块', 'button_url' => '#modules', 'button_text2' => '去产品矩阵', 'button_url2' => '/demo/products'],
        ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>4</b><span>核心四力</span></div>
<div><b>42</b><span>真实模块（可核验）</span></div>
<div><b>43</b><span>展示区块</span></div>
<div><b>116</b><span>API 端点</span></div>
HTML],
        ['_type' => 'tabs', '_key' => $k(), 'anchor' => 'map', 'title' => '四力：每一项能力解决什么', 'subtitle' => 'TIPS', 'content' => <<<HTML
<div data-tab="触达 Touch"><h3>把对的内容递到对的人面前</h3><p><b>内容引擎</b>：文章/课程/资料/播客一站式生产管理。<b>创作台</b>：深度专栏、口播脚本、品牌幻灯片，AI 结构化产出。<b>SEO/GEO</b>：四大搜索引擎接入 + 全网话题采集。<b>Newsletter</b>：选文章自动排版定时发送。</p><p style="margin-top:8px;color:var(--accent);font-weight:700">承载产品：MFlow（轻量） · OpenFlow（全家桶） · Webs Flow（承接页）</p></div>
<div data-tab="洞察 Insight"><h3>从几百个指标捞出该看的那 3-5 个</h3><p><b>CDP 画像</b>：身份合并、一人一册行为时间线。<b>分群与 RFM</b>：行为驱动动态标签。<b>转化漏斗</b>：访客→注册→加购→支付→复购。<b>问数据</b>：自然语言直接问经营数据。<b>外部情报</b>：趋势/舆情/竞品。</p><p style="margin-top:8px;color:var(--accent);font-weight:700">承载产品：inFlow（外部情报） · UserLoop（全域数据） · OpenFlow（自有数据）</p></div>
<div data-tab="个性化 Personality"><h3>同一套系统，给每个人不同的答案</h3><p><b>人群定向</b>：任何页面区块按登录态/分群/UTM 显示不同内容。<b>推荐引擎</b>：按画像与行为推荐内容与商品。<b>动态内容</b>：规则驱动的个性化呈现。</p><p style="margin-top:8px;color:var(--accent);font-weight:700">承载产品：UserLoop（数据底座） · OpenFlow（全站个性化）</p></div>
<div data-tab="销售 Sales"><h3>把流量变成收入，把收入变成复购</h3><p><b>CRM 与线索</b>：线索池、管道、跟进。<b>商业闭环</b>：购物车、订阅计费、优惠券、推荐码、佣金结算。<b>会员体系</b>：等级与权益。<b>集市</b>：数字商品上架交付。</p><p style="margin-top:8px;color:var(--accent);font-weight:700">承载产品：PayFlow（收款变现） · LearnFlow（课程交付） · OpenFlow（全站商业）</p></div>
HTML],
        block_screens('能力长什么样：真实后台截图', $k, $SHOT, $SHOT_V, array_merge($SHOT_ITEMS, $SHOT_ITEMS2)),
        ['_type' => 'accordion', '_key' => $k(), 'anchor' => 'modules', 'title' => '42 个模块，全部真实存在', 'subtitle' => '能力索引', 'content' => <<<HTML
<details open><summary>触达 Touch · 6 模块</summary><div class="hacc-body"><p>内容引擎 · 创作台 · 直播带货 · SEO/GEO · 多语言站群 · Newsletter 分发</p><a href="/academy">去学院看内容形态 →</a></div></details>
<details><summary>洞察 Insight · 6 模块</summary><div class="hacc-body"><p>CDP 画像 · 分群与 RFM · 转化漏斗 · 驾驶舱 · 问数据 AskData · 归因与 A/B</p><a href="/product#demo">看数据怎么驱动决策 →</a></div></details>
<details><summary>个性化 Personality · 6 模块</summary><div class="hacc-body"><p>人群定向 · 推荐引擎 · 动态内容 · 落地页变体 · 会员分层 · 权益解锁</p></div></details>
<details><summary>销售 Sales · 6 模块</summary><div class="hacc-body"><p>CRM 管道 · 线索评分 · 报价与订单 · 订阅计费 · 优惠券与推荐码 · 佣金结算</p></div></details>
<details><summary>行动脊柱 Agentic · 6 模块</summary><div class="hacc-body"><p>Agent 运行时 · 画布编排 · 工作流 · 定时任务 · 审批闸门 · 审计留痕</p></div></details>
<details><summary>增长引擎 · 6 模块</summary><div class="hacc-body"><p>信号采集 · 规则引擎 · 建议与动作 · 进度追踪 · 归因回收 · 体检与修复</p></div></details>
<details><summary>永久开源 · 6 模块</summary><div class="hacc-body"><p>开放 API · Webhook · 插件系统 · Skill 生态 · MCP 服务 · 模板与主题</p></div></details>
HTML],
        ['_type' => 'feature-detail', '_key' => $k(), 'title' => '四力之下的三层支撑', 'subtitle' => '支撑体系', 'content' => <<<HTML
<div><b>行动脊柱 Agentic</b><span>Agent 编排、工作流、自动化：让能力自己按流程跑，人只做判断。</span></div>
<div><b>增长引擎</b><span>信号采集 → 规则判断 → 具体动作：系统自己发现机会并建议怎么用。</span></div>
<div><b>永久开源</b><span>核心能力开源可自托管：数据主权与长期可维护性不打折。</span></div>
HTML],
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
        ['_type' => 'journey', '_key' => $k(), 'title' => '四力合起来能跑出什么', 'subtitle' => '应用场景', 'content' => <<<HTML
<div><h3>内容获客</h3><p>inFlow 找选题 → MFlow 产出与分发 → 收录与回流，一条线跑完。</p></div>
<div><h3>私域转化</h3><p>UserLoop 圈人群 → 个性化落地页承接 → CRM 跟进成交。</p></div>
<div><h3>新品验证</h3><p>Webs Flow 做承接页 → A/B 分流 → PayFlow 收款 → 数据回收判断。</p></div>
<div><h3>复购与留存</h3><p>分群流转 + 自动化触达 + 会员权益，把一次成交变成长期关系。</p></div>
HTML],
        block_runlog($k),
        block_deploy($k),
        block_open($k),
        block_integrations($k),
        block_form($k),
        ['_type' => 'cta', '_key' => $k(), 'title' => '能力是框架，产品是交付', 'subtitle' => '能力全景', 'content' => '想清楚要哪些能力，再去产品矩阵挑，组合成你自己的系统。', 'button_text' => '去产品矩阵', 'button_url' => '/demo/products'],
    ],
]);

/* ═══════════════ C · v2 首页 ═══════════════ */
upsert_page('demo-home-v2', [
    'slug' => 'demo-home-v2', 'title' => '首页 v2（预览）', 'status' => 'published',
    'seo_title' => '芭乐派 — 一人公司的增长系统矩阵',
    'seo_desc' => '一个人做不了所有事。芭乐派把增长拆成七件可组合的产品：整套系统、内容分发、落地页、全域数据、外部情报、收款变现、课程交付。开源、可自托管、数据主权在你。',
    'blocks' => [
        ['_type' => 'hero', '_key' => $k(), 'title' => '你不缺怎么做，你缺该做什么', 'subtitle' => '芭乐派 · 给一人公司的增长系统', 'content' => '市面上的增长工具都默认你有一支团队。芭乐派做的是另一套：它自己爬信号、自己出草稿、自己盯该跟进谁——你只做判断，不做事。核心开源，数据在你自己的服务器。', 'button_text' => '免费开始（开源）', 'button_url' => '/courses', 'button_text2' => '先看它一天干什么', 'button_url2' => '/demo/home-v2#dayrun'],
        ['_type' => 'ticker', '_key' => $k(), 'title' => '现在就在跑的数字', 'subtitle' => '可核验', 'content' => <<<HTML
<div data-n="502"><b>0</b><span>已发布文章</span></div>
<div data-n="43"><b>0</b><span>展示区块</span></div>
<div data-n="116"><b>0</b><span>API 端点</span></div>
<div data-n="128"><b>0</b><span>契约测试</span></div>
HTML],
        ['_type' => 'cluster', '_key' => $k(), 'title' => '一个人做不了所有事——为什么', 'subtitle' => '现实', 'content' => <<<HTML
<div><h4>时间被切碎</h4><p>写内容、投广告、盯数据、回客户——每件事都要人，判断反而没时间做。</p></div>
<div><h4>工具互相割裂</h4><p>内容在 A、数据在 B、收款在 C，接不上就等于没有。</p></div>
<div><h4>重型系统用不动</h4><p>一体化系统很全，但对小团队太重，落地成本高于收益。</p></div>
<div><h4>轻量工具扛不住</h4><p>单点工具快，但拼不出闭环，做到一半就撞墙。</p></div>
HTML],
        ['_type' => 'bento', '_key' => $k(), 'title' => '七件产品，按需组合', 'subtitle' => '产品矩阵', 'content' => <<<HTML
<div data-w="2"><h3>OpenFlow</h3><p>全站增长操作系统 · 四力合一。</p><a href="/product" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="2"><h3>MFlow</h3><p>轻量内容生产与分发。</p><a href="/product/mflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="2"><h3>Webs Flow</h3><p>落地页专精，以小时计。</p><a href="/product/webs-flow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>UserLoop</h3><p>全域营销数据中枢 · 对接任何 MA。</p><a href="/product/userloop" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>inFlow</h3><p>外部情报：趋势 / 舆情 / 竞品。</p><a href="/product/inflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>PayFlow</h3><p>一行嵌入收款 · 订阅 / 裂变 / 佣金。</p><a href="/product/payflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
<div data-w="3"><h3>LearnFlow</h3><p>课程与训练营交付闭环。</p><a href="/product/learnflow" style="color:var(--accent);font-size:13px;font-weight:700">进入 →</a></div>
HTML],
        block_screens('不是效果图，是正在跑的系统', $k, $SHOT, $SHOT_V, $SHOT_ITEMS),
        ['_type' => 'tabs', '_key' => $k(), 'title' => '你是谁，就从哪里开始', 'subtitle' => '按人群', 'content' => <<<HTML
<div data-tab="内容创作者"><h3>内容流水线</h3><p><b>inFlow</b> 给选题情报 → <b>MFlow</b> 生成并多平台分发 → <b>UserLoop</b> 回收效果。一个人当编辑部。</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="电商卖家"><h3>投放转化闭环</h3><p><b>Webs Flow</b> 做承接页 → <b>PayFlow</b> 收款 → <b>UserLoop</b> 沉淀全域数据。</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="独立开发者"><h3>收钱 + 数据</h3><p><b>PayFlow</b> 三分钟接上收款；<b>UserLoop</b> 打通广告与产品数据。</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="讲师 / 教练"><h3>课程交付 + 变现</h3><p><b>LearnFlow</b> 交付课程与训练营；<b>PayFlow</b> 收款；<b>MFlow</b> 触达学员。</p><a href="/demo/products" style="color:var(--accent);font-weight:700">看这个组合 →</a></div>
<div data-tab="团队 / 企业"><h3>整套系统</h3><p><b>OpenFlow</b> 四力合一，团队统一后台，数据在一处，其余产品按需嵌入。</p><a href="/product" style="color:var(--accent);font-weight:700">看 OpenFlow →</a></div>
HTML],
        ['_type' => 'journey', '_key' => $k(), 'title' => '能力框架：TIPS 四力', 'subtitle' => '怎么做到', 'content' => <<<HTML
<div><h3>触达</h3><p>内容、渠道、分发：把对的信息递到对的人面前。</p></div>
<div><h3>洞察</h3><p>数据、画像、情报：从噪声里捞出该看的那几个数。</p></div>
<div><h3>个性化</h3><p>同一套系统，给每个人不同的答案。</p></div>
<div><h3>销售</h3><p>把流量变成收入，把收入变成复购。</p></div>
HTML],
        block_day($k),
        block_runlog($k),
        ['_type' => 'comparison', '_key' => $k(), 'title' => '自己做 vs 用芭乐派', 'subtitle' => '对比', 'content' => <<<HTML
<table style="width:100%;border-collapse:collapse;min-width:720px">
<thead><tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">环节</th><th style="padding:10px">自己拼工具</th><th style="padding:10px">用芭乐派</th></tr></thead>
<tbody>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">选题</td><td style="padding:12px 10px">刷平台靠感觉，慢半拍</td><td style="padding:12px 10px">inFlow 全网采集 + 每日情报</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">内容</td><td style="padding:12px 10px">写完再逐个平台发</td><td style="padding:12px 10px">MFlow 生成 + 多平台分发</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">承接</td><td style="padding:12px 10px">排设计排期，等一周</td><td style="padding:12px 10px">Webs Flow 落地页，小时级上线</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">收款</td><td style="padding:12px 10px">接支付要建站、要开发</td><td style="padding:12px 10px">PayFlow 一行嵌入</td></tr>
<tr style="border-bottom:1px solid var(--border-soft)"><td style="padding:12px 10px">数据</td><td style="padding:12px 10px">各平台后台各看一段</td><td style="padding:12px 10px">UserLoop 一处看清，能对接任何 MA</td></tr>
<tr><td style="padding:12px 10px">交付</td><td style="padding:12px 10px">课件、录播、作业散在各处</td><td style="padding:12px 10px">LearnFlow 上课→进度→证书闭环</td></tr>
</tbody></table>
HTML],
        ['_type' => 'quote-wall', '_key' => $k(), 'title' => '用过的人怎么说', 'subtitle' => '真实反馈', 'content' => <<<HTML
<div>「以前每天 3 小时找选题改文章，现在爬完信号直接给草稿，我只管把关。」— 陈默，内容工作室</div>
<div>「第一次一个人跑完整条内容流水线，从采集到收录没换过工具。」— 独立开发者</div>
<div>「漏斗终于看得见了：哪个环节漏单，面板直接告诉你。」— 增长负责人</div>
<div>「组件工厂太顺了——贴一段 HTML 就变成可复用模块。」— 前端工程师</div>
<div>「课程播放器拖进度条很流畅，学员完课率肉眼可见在涨。」— 训练营主理人</div>
<div>「不用换我的商城，接上就收款。」— DTC 卖家</div>
HTML],
        ['_type' => 'countdown', '_key' => $k(), 'title' => 'R.B.E 训练营 · 第 4 期', 'subtitle' => '限时', 'content' => '8 周，从利润公式到 Agent 系统，亲手设计出你的增长系统。报名通道定期关闭。', 'end' => '2026-12-31 23:59:59'],
        ['_type' => 'accordion', '_key' => $k(), 'title' => '三条路，通向同一个地方', 'subtitle' => '怎么开始', 'content' => <<<HTML
<details open><summary>要一整套</summary><div class="hacc-body"><p>OpenFlow 四力合一，开箱即用，数据在一处，团队统一后台。</p><a href="/product">看 OpenFlow →</a></div></details>
<details><summary>要单点</summary><div class="hacc-body"><p>产品矩阵七件，哪件缺用哪件，零依赖今天就能跑。</p><a href="/demo/products">看产品矩阵 →</a></div></details>
<details><summary>要方法论</summary><div class="hacc-body"><p>学院讲清楚增长方法论，课程带你从利润公式到 Agent 系统。</p><a href="/academy">去学院 →</a></div></details>
<details><summary>要开源自托管</summary><div class="hacc-body"><p>核心能力开源，数据留在自己服务器，生态可扩展。</p><a href="/docs">看文档 →</a></div></details>
HTML],
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>芭乐派卖的是软件还是方法？</summary><p>两者都卖：软件是增长系统（产品矩阵），方法是学院里的课程与文档。软件核心开源可自托管，方法论随时可学。</p></details>
<details><summary>一定要买全家桶吗？</summary><p>不必。七件产品独立可用，按需组合。从最缺的那一环开始就好。</p></details>
<details><summary>数据会不会被绑死？</summary><p>不会。互通走公开 API，数据主权在你；想换平台，档案可迁移。</p></details>
HTML],
        block_form($k),
        ['_type' => 'cta', '_key' => $k(), 'title' => '让系统替你跑，你只做判断', 'subtitle' => '芭乐派', 'content' => '看产品矩阵，从最缺的那一环开始；或者直接上整套 OpenFlow。', 'button_text' => '看产品矩阵', 'button_url' => '/demo/products'],
    ],
]);

echo "全部完成\n";
