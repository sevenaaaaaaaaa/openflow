<?php
/**
 * 生成 Lovart 风格示例落地页（演示 7 种新扩展区块）
 * 用法: php scripts/seed-lovart-page.php
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../lib/BuilderPages.php';

// 已存在则跳过（可重复执行）
foreach (builder_pages_all() as $p) {
    if (($p['slug'] ?? '') === 'growth-os-tour') { echo "已存在: /b/growth-os-tour\n"; exit; }
}

$k = 'block_new_key';

$page = [
    'slug'      => 'growth-os-tour',
    'title'     => '增长操作系统 · 完整能力巡礼',
    'status'    => 'published',
    'seo_title' => '增长操作系统 · 完整能力巡礼 | 芭乐派',
    'seo_desc'  => '从一份产品简介到一条完整转化漏斗：触达、洞察、个性化、转化、复购，一个系统跑通。',
    'blocks'    => [
        // 1) Hero
        ['_type' => 'hero', '_key' => $k(), 'title' => '把一份产品简介，变成一条完整的转化漏斗', 'subtitle' => 'GROWTH OS · TOUR', 'content' => 'OpenFlow 把产品资料、品牌素材与增长目标，接进同一条 Agent 工作流：落地页、广告素材、邮件头图、社媒变体与视频概念，一次生成，处处一致。', 'button_text' => '开始搭建', 'button_url' => '/product'],
        // 2) 旅程步骤（5 步漏斗）
        ['_type' => 'journey', '_key' => $k(), 'title' => '为漏斗的每一步设计', 'subtitle' => '买家旅程地图', 'content' => <<<HTML
<div><h3>流量钩子</h3><p>Meta、TikTok、Google 与达人素材，都对着同一个报价设计。</p></div>
<div><h3>落地承接</h3><p>Hero、证明、对比、FAQ 与 CTA，出自同一份活动简报。</p></div>
<div><h3>产品证明</h3><p>产品图、卖点模块、场景样机、评价与用例，一个 SKU 上下文生成。</p></div>
<div><h3>下单信心</h3><p>信任徽章、优惠提醒、套装视觉与保障图形。</p></div>
<div><h3>留存循环</h3><p>邮件、短信、售后插页与再营销素材，同一套品牌系统延伸。</p></div>
HTML],
        // 3) 转化控制（4 个编辑能力）
        ['_type' => 'features', '_key' => $k(), 'title' => '不推倒重来，修掉创意漏点', 'subtitle' => '转化控制', 'content' => <<<HTML
<div><h3>点选即改</h3><p>点中产品、标签、CTA 徽章或背景，告诉系统改什么——其余画面保持不动。</p></div>
<div><h3>品牌套件</h3><p>Logo、配色、字体或品牌 PDF 上传一次，每个门店素材自动复用。</p></div>
<div><h3>样机场景</h3><p>把平面包装或标签稿放进照片级的商品与门店场景。</p></div>
<div><h3>文案改写</h3><p>促销话术、价格标注与本地化标题就地重写，版式不散。</p></div>
HTML],
        // 4) Bento 能力拼贴
        ['_type' => 'bento', '_key' => $k(), 'title' => '从任何一个入口开始，共享同一个 Agent 上下文', 'subtitle' => '生成入口', 'content' => <<<HTML
<div data-w="3" style="background:linear-gradient(160deg,oklch(52% .17 258/.22),transparent 60%)"><h3>产品图系统</h3><p>PDP 主图 · 白底图 · 场景图 · 市场裁切，一个 SKU 简报全部生成。</p></div>
<div data-w="3"><h3>视频广告概念</h3><p>5 秒钩子 · 关键帧 · 竖版构图，TikTok 与 Reels 直接可用。</p></div>
<div data-w="2"><h3>广告变体</h3><p>报价钩子与渠道裁切，按人群、季节、SKU 快速展开。</p></div>
<div data-w="2"><h3>品牌套件</h3><p>Logo · 色板 · 字体规则，锁定后全渠道不漂移。</p></div>
<div data-w="2"><h3>批发提案</h3><p>零售买手提案册，与 PDP 共用同一套视觉记忆。</p></div>
HTML],
        // 5) 工具网格（6 工具带评分）
        ['_type' => 'tool-grid', '_key' => $k(), 'title' => '工具映射到增长任务', 'subtitle' => '能力清单', 'content' => <<<HTML
<div><span class="tg-tag">PDP / 目录</span><h3>AI 产品图生成</h3><p>主图、场景图、市场图与 SKU 变体，一次成套。</p><em>★ 4.9</em></div>
<div><span class="tg-tag">产品视觉</span><h3>抠图 + 样机</h3><p>隔离产品、放进真实场景，搭建一致的商品图廊。</p><em>★ 4.8</em></div>
<div><span class="tg-tag">付费投放</span><h3>AI 视频广告</h3><p>脚本、关键帧、缩略图与竖版概念，TikTok/Reels/Shorts。</p><em>★ 4.8</em></div>
<div><span class="tg-tag">效果投放</span><h3>AI 广告创意</h3><p>报价视觉、钩子、横幅与再营销变体、测试看板。</p><em>★ 4.9</em></div>
<div><span class="tg-tag">治理</span><h3>品牌套件 + 风格一致性</h3><p>Logo、色彩、字体规则跨资产复用，全店一个声部。</p><em>★ 4.8</em></div>
<div><span class="tg-tag">留存</span><h3>AI 社媒设计</h3><p>品牌帖子、故事、活动封面与客户教育内容。</p><em>★ 4.7</em></div>
HTML],
        // 6) 手风琴（横向展开）
        ['_type' => 'accordion', '_key' => $k(), 'title' => '一条横向推进的落地路线', 'subtitle' => '全漏斗展开', 'content' => <<<HTML
<details open><summary>获客</summary><div class="hacc-body"><p>研究品类钩子，生成广告变体，让素材与落地页承诺对得上。</p><a href="/capability">规划获客创意 →</a></div></details>
<details><summary>承接</summary><div class="hacc-body"><p>PDP 与落地页视觉：卖点、证明、对比、信任模块与 CTA 方向。</p><a href="/capability">优化转化素材 →</a></div></details>
<details><summary>再营销</summary><div class="hacc-body"><p>把异议与卖点切成短视频、评价卡、报价图形与提醒素材。</p><a href="/product">生成再营销变体 →</a></div></details>
<details><summary>本地化</summary><div class="hacc-body"><p>为区域门店适配视觉、文案与季节符号，品牌系统保持一致。</p><a href="/about">本地化投放素材 →</a></div></details>
HTML],
        // 7) 滚动横幅（渐变占位卡）
        ['_type' => 'marquee', '_key' => $k(), 'title' => '一套创意系统，铺满每个触点', 'subtitle' => '创意光谱', 'content' => <<<HTML
<div style="background:linear-gradient(135deg,oklch(72% .12 262/.35),oklch(70% .13 305/.3))">pdp.hero</div>
<div style="background:linear-gradient(135deg,oklch(74% .11 200/.3),oklch(70% .13 305/.28))">ad.variant</div>
<div style="background:linear-gradient(135deg,oklch(70% .13 305/.3),oklch(66% .15 75/.3))">email.header</div>
<div style="background:linear-gradient(135deg,oklch(66% .15 75/.3),oklch(72% .12 262/.35))">story.frame</div>
<div style="background:linear-gradient(135deg,oklch(66% .13 152/.3),oklch(74% .11 200/.3))">bundle.offer</div>
<div style="background:linear-gradient(135deg,oklch(72% .12 262/.35),oklch(66% .13 152/.3))">review.proof</div>
HTML],
        // 8) 数据指标
        ['_type' => 'stats', '_key' => $k(), 'title' => '系统运行一年后的样子', 'subtitle' => '关键数字', 'content' => <<<HTML
<div><b>1</b><span>Agent 上下文</span></div>
<div><b>10+</b><span>素材触点</span></div>
<div><b>4</b><span>核心编辑控制</span></div>
<div><b>∞</b><span>变体循环</span></div>
HTML],
        // 9) 客户证言
        ['_type' => 'testimonials', '_key' => $k(), 'title' => '用 Agent 工作流之后', 'subtitle' => '团队反馈', 'content' => <<<HTML
<div><p>「第一次会话就产出了 PDP 图、广告变体和邮件头图。意外的是，后续修改全都连着上下文。」</p><b>Lena</b><span>创始人 · Glow Pantry</span></div>
<div><p>「可以在快模式里试钩子，再用深模式把赢的方向做成体系——测试频率完全变了。」</p><b>Noah</b><span>效果投放负责人</span></div>
<div><p>「改成品图里的促销文案而不用重做整张素材，这正是电商团队最需要的事。」</p><b>Miguel</b><span>生命周期运营</span></div>
HTML],
        // 10) FAQ
        ['_type' => 'faq', '_key' => $k(), 'title' => '常见问题', 'subtitle' => 'FAQ', 'content' => <<<HTML
<details><summary>这是建站工具还是设计工作流？</summary><p>都不是，也不止。它是一套增长操作系统：素材、落地页、数据与自动化在同一个系统里连成闭环。</p></details>
<details><summary>需要上传哪些素材？</summary><p>产品照片、品牌指南、SKU 表、现有广告或竞品链接。系统在生成前先理解业务。</p></details>
<details><summary>生成之后还能改吗？</summary><p>能。点选即改、文案改写、元素拆层——改细节不用推倒重来。</p></details>
<details><summary>怎么保证品牌一致性？</summary><p>品牌套件锁定色彩、字体与 Logo 规则，所有素材自动套用。</p></details>
HTML],
        // 11) CTA
        ['_type' => 'cta', '_key' => $k(), 'title' => '准备把一份简介，变成完整的上线体系了吗', 'subtitle' => 'CTA', 'content' => '三条路，最后都通向同一个地方：方法论在学院，工具在 OpenFlow，落地在训练营。', 'button_text' => '设计我的增长系统', 'button_url' => '/product'],
    ],
];

$id = save_builder_page('', $page);
echo $id !== '' ? "✅ 示例页已创建: /b/growth-os-tour (id=$id, " . count($page['blocks']) . " 个区块)\n" : "❌ 创建失败\n";
