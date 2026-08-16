<?php
/**
 * OpenFlow 演示数据生成器
 * 为全新安装生成示例数据，便于体验完整功能
 *
 * 用法：php bin/demo-data.php
 * 安全：仅在 data/ 为空或指定 --force 时运行
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/SkillSystem.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_once __DIR__ . '/../lib/Database.php';

$force = in_array('--force', $argv ?? [], true);
$articleCount = count(json_read(ARTICLES_DIR . '/index.json'));

if ($articleCount > 0 && !$force) {
    echo "⚠️  data/ 已有内容（{$articleCount} 篇文章），跳过。\n";
    echo "   如需重置演示数据请运行：php bin/demo-data.php --force\n";
    exit(0);
}

echo "🎬 开始生成 OpenFlow 演示数据...\n";

// ─── 示例文章 ───
$demoArticles = [
    ['id' => 'demo_ai_growth', 'title' => 'AI 时代网站增长新范式', 'category' => 'insight', 'tags' => ['AI', '增长'], 'author' => 'Seven', 'slug' => 'demo-ai-growth', 'excerpt' => '当增长工具开始理解用户，网站运营进入新阶段。', 'content' => "<h2>增长的本质</h2><p>增长不是堆功能，而是理解用户每一步的意图与需求。</p><p>AI 让这件事变得可规模化。</p>"],
    ['id' => 'demo_seo_guide', 'title' => 'SEO 入门：从 0 到 1 建立搜索可见性', 'category' => 'seo', 'tags' => ['SEO', '内容'], 'author' => 'Seven', 'slug' => 'demo-seo-guide', 'excerpt' => '搜索引擎优化的完整入门路径。', 'content' => "<h2>搜索意图</h2><p>理解用户为什么搜索，比堆砌关键词更重要。</p>"],
    ['id' => 'demo_cdp_value', 'title' => 'CDP 是什么？为什么每个增长团队都需要', 'category' => 'insight', 'tags' => ['CDP', '数据'], 'author' => 'Seven', 'slug' => 'demo-cdp-value', 'excerpt' => '客户数据平台正在成为增长团队的基础设施。', 'content' => "<h2>数据驱动的增长</h2><p>统一画像、行为追踪、实时洞察，是增长决策的基础。</p>"],
];
foreach ($demoArticles as $a) {
    $a['status'] = 'published';
    $a['created_at'] = date('Y-m-d H:i:s', time() - rand(1, 20) * 86400);
    $a['updated_at'] = $a['created_at'];
    $a['views'] = rand(50, 500);
    save_article($a['id'], $a);
}
echo "✅ 已生成 " . count($demoArticles) . " 篇示例文章\n";

// ─── 示例 Skill ───
$demoSkills = [
    ['id' => 'skill_seo_title', 'title' => 'SEO 标题生成器', 'description' => '为文章主题生成高转化 SEO 标题', 'author' => 'OpenFlow', 'author_type' => 'official', 'steps' => [['title' => '提取主题', 'desc' => '识别核心关键词'], ['title' => '生成标题', 'desc' => '输出 5 个候选标题']], 'status' => 'published'],
    ['id' => 'skill_article_outline', 'title' => '文章大纲生成器', 'description' => '快速生成结构完整的文章大纲', 'author' => 'OpenFlow', 'author_type' => 'official', 'steps' => [['title' => '确定主题', 'desc' => ''], ['title' => '生成大纲', 'desc' => '标题+要点']], 'status' => 'published'],
    ['id' => 'skill_wechat_copy', 'title' => '公众号文案生成器', 'description' => '生成适合公众号传播的文案', 'author' => 'OpenFlow', 'author_type' => 'official', 'steps' => [['title' => '输入主题', 'desc' => ''], ['title' => '生成文案', 'desc' => '标题+正文+结尾引导']], 'status' => 'published'],
];
foreach ($demoSkills as $s) skill_publish($s);
echo "✅ 已生成 " . count($demoSkills) . " 个示例 Skill\n";

// ─── 示例 CDP 画像 + 事件 ───
$demoVisitors = [
    ['vid' => 'demo_visitor_1', 'tags' => ['content_reader', 'channel:搜索引擎'], 'props' => ['os' => 'macOS', 'browser' => 'Chrome', 'device' => 'Desktop', 'channel' => '搜索引擎'], 'events' => ['page_view', 'article_view', 'article_view', 'scroll_depth', 'tool_use']],
    ['vid' => 'demo_visitor_2', 'tags' => ['lead', 'channel:社媒'], 'props' => ['os' => 'iOS', 'browser' => 'Safari', 'device' => 'Mobile', 'channel' => '社媒'], 'events' => ['page_view', 'form_submit', 'page_view']],
    ['vid' => 'demo_visitor_3', 'tags' => ['buyer', 'channel:直接访问'], 'props' => ['os' => 'Windows', 'browser' => 'Edge', 'device' => 'Desktop', 'channel' => '直接访问'], 'events' => ['page_view', 'page_view', 'purchase']],
];
foreach ($demoVisitors as $v) {
    foreach ($v['events'] as $i => $ev) {
        $props = $v['props'];
        if ($ev === 'purchase') $props['amount'] = 199;
        CdpSystem::track($ev, $props, $v['vid']);
    }
}
echo "✅ 已生成 " . count($demoVisitors) . " 个示例访客画像\n";

// ─── 示例表单/下载 ───
$downloads = json_read(DATA_DIR . '/downloads.json');
if (empty($downloads)) {
    json_write(DATA_DIR . '/downloads.json', [
        ['id' => 'dl_demo_guide', 'title' => '网站增长白皮书', 'type' => 'pdf', 'download_count' => 0],
        ['id' => 'dl_demo_checklist', 'title' => 'SEO 自查清单', 'type' => 'xlsx', 'download_count' => 0],
    ]);
    echo "✅ 已生成示例资料\n";
}

// ─── 初始化数据库 ───
try { Database::migrate(); } catch (Exception $e) {}
echo "\n🎉 演示数据生成完成！\n";
echo "   访问 http://localhost:8080 查看前端效果\n";
echo "   访问 http://localhost:8080/admin 登录后台\n";
