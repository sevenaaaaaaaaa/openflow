<?php
/**
 * 给示例页追加第二批 8 种区块（tabs/portrait/cluster/prompt/proof/blog-grid/canvas-wall/feature-detail）
 * 用法: php scripts/seed-showcase-page-2.php
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../admin/config.php';
require __DIR__ . '/../lib/BuilderPages.php';

$k = 'block_new_key';
$pages = builder_pages_all();
$page = null;
foreach ($pages as $p) if (($p['slug'] ?? '') === 'growth-os-tour') { $page = $p; break; }
if (!$page) { echo "❌ 找不到示例页\n"; exit(1); }

// 已加过就跳过（幂等）
foreach (($page['blocks'] ?? []) as $b) {
    $t = $b['_type'] ?? '';
    if ($t === 'tabs' || $t === 'cluster' || $t === 'prompt') { echo "已追加过，跳过\n"; exit; }
}

$proof = ['_type' => 'proof', '_key' => $k(), 'content' => <<<HTML
<div><b>数百万</b><span>设计师与品牌在用</span></div>
<div><b>10+</b><span>素材触点</span></div>
<div><b>4</b><span>核心编辑控制</span></div>
<div><b>∞</b><span>变体循环</span></div>
HTML];

$cluster = ['_type' => 'cluster', '_key' => $k(), 'title' => '增长漏斗在哪里漏掉效果', 'subtitle' => '常见漏点', 'content' => <<<HTML
<div><h4>广告与页面不一致</h4><p>付费素材承诺一个角度，落地页和 PDP 视觉讲的却是另一个故事。</p></div>
<div><h4>产品图缺乏说服力</h4><p>SKU 照片技术上没问题，但制造不出欲望、用法清晰度与信任。</p></div>
<div><h4>A/B 测试太慢</h4><p>每换一个钩子、报价、背景或徽章，都是一张新的设计工单。</p></div>
<div><h4>品牌跨渠道漂移</h4><p>PDP、广告、邮件与社媒慢慢不再像一个品牌。</p></div>
<div><h4>本地化瓶颈</h4><p>新市场需要翻译文案、本地符号与尺寸变体，团队却手工重建。</p></div>
<div><h4>上线后没有循环</h4><p>上线之后，赢的创意无法快速刷新用于再营销与复购。</p></div>
HTML];

$tabs = ['_type' => 'tabs', '_key' => $k(), 'title' => '按角色看同一套系统', 'subtitle' => '角色视角', 'content' => <<<HTML
<div data-tab="增长负责人"><h3>看漏斗，不看单点</h3><p>访客→线索→成交→复购一条线看全，每个环节的素材和数据接在同一个系统里，而不是五个后台各看一段。</p></div>
<div data-tab="内容运营"><h3>选题到发布一口气</h3><p>热点采集、选题库、AI 初稿、SEO 检查、定时发布——从「今天写什么」到「已收录」不再换五个工具。</p></div>
<div data-tab="投放优化"><h3>素材快速变体</h3><p>一个报价展开成人群、季节、SKU 多路变体，赢的方向锁定视觉记忆继续展开。</p></div>
<div data-tab="独立开发者"><h3>一人跑完整团队</h3><p>内容、数据、自动化、触达四力接在一条工作流，Agent 跑流程，你只做判断。</p></div>
HTML];

$portrait = ['_type' => 'portrait', '_key' => $k(), 'title' => '访客犹豫的四个时刻', 'subtitle' => '转化卡点', 'content' => <<<HTML
<div><h3>价值不清楚</h3><p>用卖点导向的视觉和使用场景，让买家理解这个 SKU 为什么值得。</p><a href="/capability">修复清晰度 →</a></div>
<div><h3>证明不够</h3><p>把评价、保障与社会证明变成可读的信任模块。</p><a href="/capability">补上证明 →</a></div>
<div><h3>视觉不一致</h3><p>品牌套件让广告、PDP、邮件与社媒像一个店。</p><a href="/product">锁定品牌 →</a></div>
<div><h3>迭代太慢</h3><p>几分钟生成新钩子、新报价与新市场的创意变体。</p><a href="/product">创建变体 →</a></div>
HTML];

$fdetail = ['_type' => 'feature-detail', '_key' => $k(), 'title' => '生成之前，先理解业务', 'subtitle' => '上下文先行', 'content' => <<<HTML
<div><b>分析竞品页面</b><span>粘贴竞品 URL，提取品类视觉、页面模式与广告角度。</span></div>
<div><b>从 PDF 提取品牌规则</b><span>上传品牌指南，锁定色彩、字体与 Logo 用法。</span></div>
<div><b>产品规格进提示词</b><span>SKU 表、卖点与限制，作为生成前的业务上下文。</span></div>
HTML];

$bgrid = ['_type' => 'blog-grid', '_key' => $k(), 'title' => '增长方法论的内容库', 'subtitle' => '延伸阅读', 'content' => <<<HTML
<div><time>2026-09-12</time><h3>AI 设计 Agent 与产品图生成器，差在哪</h3><p>为什么团队需要的是上下文、编辑与品牌记忆，而不是一次性生成。</p></div>
<div><time>2026-08-28</time><h3>产品页的 7 天创意刷新计划</h3><p>产品图、卖点图、对比图与再营销素材的实用工作流。</p></div>
<div><time>2026-08-15</time><h3>用品牌套件止住渠道漂移</h3><p>快速测试中保持广告、邮件、社媒与 PDP 视觉一致。</p></div>
HTML];

$prompt = ['_type' => 'prompt', '_key' => $k(), 'title' => '我经营一家精品陶瓷咖啡滤杯的独立店。用我的产品图、品牌色与竞品参考，生成 PDP 图系统：主图、白底图、场景图、对比图、套装图与 4:5 社媒裁切。', 'subtitle' => '从真实简报开始', 'content' => <<<HTML
<span>PDP 图刷新</span><span>Meta/TikTok 广告变体</span><span>季节活动铺开</span><span>邮件头图</span><span>本地化素材</span>
HTML, 'button_text' => 'Generate'];

// 按目标位置插入
$insertAt = function (array $blocks, string $beforeType, array $blocksToInsert): array {
    $out = []; $inserted = false;
    foreach ($blocks as $b) {
        if (!$inserted && (($b['_type'] ?? '') === $beforeType)) {
            foreach ($blocksToInsert as $nb) $out[] = $nb;
            $inserted = true;
        }
        $out[] = $b;
    }
    if (!$inserted) foreach ($blocksToInsert as $nb) $out[] = $nb;
    return $out;
};

$blocks = $page['blocks'];
$blocks = $insertAt($blocks, 'journey', [$proof]);
$blocks = $insertAt($blocks, 'features', [$cluster]);
$blocks = $insertAt($blocks, 'tool-grid', [$tabs]);
$blocks = $insertAt($blocks, 'marquee', [$portrait, $fdetail]);
$blocks = $insertAt($blocks, 'cta', [$bgrid, $prompt]);

$id = save_builder_page($page['id'], ['blocks' => $blocks]);
echo $id !== '' ? "✅ 已追加，现共 " . count($blocks) . " 个区块\n" : "❌ 保存失败\n";
