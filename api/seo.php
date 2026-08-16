<?php
/**
 * SEO 注入 API — 返回完整 SEO head HTML（供前端注入）
 * GET /api/seo.php?page=index&type=article&id=xxx
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/SeoHead.php';
require_once __DIR__ . '/../lib/SiteConfig.php';

header('Content-Type: application/json; charset=utf-8');
cors_headers();

$page = $_GET['page'] ?? '';
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

// 页面 → SEO 配置映射（含 canonical URL）
$siteUrl = site_config_get('site_url', '');
$pageSeoMap = [
    'index' => ['title' => '芭乐派 · OpenFlow 增长操作系统', 'desc' => '帮一人公司设计 Agent 能跑的增长系统：TIPS 框架（触达/洞察/个性化/销售）+ 自生长 AI Engine，主动爬取、洞察、优化、转化', 'keywords' => '一人公司, Agent 增长, 增长系统, 触达, 洞察, 销售自动化, 自生长', 'canonical' => $siteUrl . '/'],
    'about' => ['title' => '关于我们 | OpenFlow', 'desc' => 'OpenFlow 的使命、原则与团队', 'keywords' => 'OpenFlow, 关于我们, 增长团队', 'canonical' => $siteUrl . '/about'],
    'capability' => ['title' => '产品能力 | OpenFlow', 'desc' => 'CMS/CDP/MA/SEO 六大核心能力', 'keywords' => 'CMS, CDP, 营销自动化, SEO', 'canonical' => $siteUrl . '/capability'],
    'courses' => ['title' => '课程 | OpenFlow', 'desc' => '网站增长与自动化学习路径', 'keywords' => '课程, 增长, 自动化, 学习', 'canonical' => $siteUrl . '/courses'],
    'product' => ['title' => '产品 | OpenFlow', 'desc' => '网站增长操作系统 · 工作流引擎', 'keywords' => '产品, 工作流, 自动化', 'canonical' => $siteUrl . '/product'],
    'tools' => ['title' => '增长工具箱 | OpenFlow', 'desc' => '免费网站增长工具：SEO检查/Meta生成/LTV计算', 'keywords' => 'SEO工具, 增长工具, Meta生成', 'canonical' => $siteUrl . '/tools.php'],
    'community' => ['title' => '社区 | OpenFlow', 'desc' => '网站增长讨论社区 · 提问与分享', 'keywords' => '社区, 讨论, 增长交流, 提问', 'canonical' => $siteUrl . '/community.php'],
    'docs' => ['title' => '文档中心 | OpenFlow', 'desc' => '产品文档 · 使用指南 · 开发者文档', 'keywords' => '文档, 使用指南, API', 'canonical' => $siteUrl . '/docs.php'],
    'marketplace' => ['title' => '生态市场 | OpenFlow', 'desc' => '插件 · Skill · 主题 一站式扩展', 'keywords' => '插件, Skill, 生态, 主题', 'canonical' => $siteUrl . '/marketplace.php'],
    'academy' => ['title' => '内容学院 | OpenFlow', 'desc' => '网站增长实践文章 · 案例与方法论', 'keywords' => '网站增长, 文章, 案例, 方法论', 'canonical' => $siteUrl . '/academy.php'],
];

$opts = [
    'title' => 'OpenFlow · 网站增长操作系统',
    'description' => '',
    'keywords' => '',
];

if ($page && isset($pageSeoMap[$page])) {
    $opts = [
        'title' => $pageSeoMap[$page]['title'],
        'description' => $pageSeoMap[$page]['desc'],
        'keywords' => $pageSeoMap[$page]['keywords'],
        'canonical' => $pageSeoMap[$page]['canonical'],
    ];
}

// 动态页 SEO（文章/课程）
if ($type === 'article' && $id) {
    $article = get_article($id);
    if ($article) {
        $opts = [
            'title' => ($article['seo_title'] ?? $article['title'] ?? '') . ' | ' . site_config_get('site_name', 'OpenFlow'),
            'description' => $article['seo_desc'] ?? $article['excerpt'] ?? '',
            'keywords' => implode(', ', $article['tags'] ?? []),
            'canonical' => site_config_get('site_url', '') . '/article/' . ($article['slug'] ?? $id),
            'type' => 'article',
            'json_ld' => [
                '@context' => 'https://schema.org', '@type' => 'Article',
                'headline' => $article['title'] ?? '',
                'description' => $article['excerpt'] ?? '',
                'datePublished' => $article['created_at'] ?? '',
            ],
        ];
    }
}

// 生成 head HTML（用 buffering 捕获）
ob_start();
seo_head($opts);
$headHtml = ob_get_clean();

echo json_encode(['ok' => true, 'page' => $page, 'head' => $headHtml], JSON_UNESCAPED_UNICODE);
