<?php
/**
 * 后台导航 —— 唯一数据源（v1，2026-09-03）
 *
 * 之前的侧栏是 admin/config.php 里 600 行手写 HTML：5 个「视图」入口 + TIPS 四象限切换器 + 25 个小节 148 条扁平链接，
 * 三套组织原则打架，而且象限切换器记的是「上次点过的模块」而不是当前页所属模块——打开系统设置，侧栏仍显示 Touch。
 *
 * 现在：7 个区（area）→ 若干组（group）→ 条目（item）。当前页面决定展开哪个区，永远不记忆。
 * 不在树里的页面（写文章 / 课程编辑 / 客户详情…）通过 ADMIN_NAV_ALIAS 归到父条目，侧栏照常高亮。
 *
 *   admin_nav_tree()                 → 全部区（已按权限过滤）
 *   admin_nav_locate($current)       → ['area' => id, 'item' => href|null, 'label' => 当前条目名]
 *   admin_nav_render($current)       → 输出 .sidebar
 *
 * perm：字符串 = 需要该权限；数组 = 任一权限即可；null = 不限。
 */

const ADMIN_NAV_ALIAS = [
    // 编辑 / 详情页 → 所属列表
    'article-edit' => 'content-hub', 'articles' => 'content-hub', 'pages-list' => 'content-hub', 'downloads' => 'content-hub', 'download-edit' => 'content-hub',
    'podcasts' => 'content-hub', 'page-categories' => 'content-hub', 'tags' => 'content-hub', 'page-builder' => 'pages', 'page-editor-config' => 'pages',
    'course-edit' => 'courses', 'customer-detail' => 'crm', 'profile-detail' => 'profiling', 'user-profile' => 'profiling',
    'abtests-stats' => 'abtests', 'survey-agent' => 'survey', 'survey-org' => 'survey', 'sentiment-report' => 'sentiment',
    'media-upload' => 'media', 'export' => 'data-export', 'export-all' => 'data-export', 'onboarding' => 'workspace', 'debug' => 'devops',
    'notion-sync' => 'data-sync', 'languages' => 'settings', 'storage' => 'health-check', 'activity' => 'audit-log', 'footer-links' => 'site-builder',
    'mail-settings' => 'email', 'payment-settings' => 'shop-settings', 'seo' => 'seo-center', 'seo-tools' => 'seo-center', 'seo-batch' => 'seo-center',
    'seo-console' => 'seo-center', 'redirects' => 'seo-center', 'structured-data' => 'seo-center', 'structured' => 'seo-center', 'landing' => 'landing-pages', 'image-seo' => 'seo-center', 'index' => 'workspace',
];

function admin_nav_tree(): array {
    static $tree = null;
    if ($tree !== null) return $tree;
    $I = [
        'home'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        'doc'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-5-6.3"/>',
        'bolt'    => '<path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z"/>',
        'chart'   => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
        'cart'    => '<path d="M6 7h12l1.5 13.5a1 1 0 0 1-1 1.1H5.5a1 1 0 0 1-1-1.1L6 7Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
        'gear'    => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
    ];
    $all = [
        ['id' => 'overview', 'label' => '概览', 'icon' => $I['home'], 'groups' => [
            ['label' => '', 'items' => [
                ['id' => 'workspace', 'label' => '工作台', 'perm' => 'dashboard'],
                ['id' => 'dashboard', 'label' => '经营驾驶舱', 'perm' => 'dashboard'],
                ['id' => 'flow', 'label' => '运营主线', 'perm' => 'flow'],
                ['id' => 'driver', 'label' => '增长驱动', 'perm' => 'flow'],
                ['id' => 'content-calendar', 'label' => '内容日历', 'perm' => 'tasks'],
            ]],
        ]],
        ['id' => 'content', 'label' => '内容', 'icon' => $I['doc'], 'groups' => [
            ['label' => '创作与发布', 'items' => [
                ['id' => 'content-hub', 'label' => '内容中心', 'perm' => 'pages', 'hint' => '文章 · 页面 · 下载 · 播客'],
                ['id' => 'tasks', 'label' => '内容生产任务', 'perm' => 'tasks'],
                ['id' => 'publish', 'label' => '内容分发', 'perm' => 'tasks'],
                ['id' => 'channels', 'label' => '分发渠道', 'perm' => 'channels'],
                ['id' => 'version-diff', 'label' => '版本对比', 'perm' => 'version-diff'],
            ]],
            ['label' => '站点页面', 'items' => [
                ['id' => 'pages', 'href' => '/xmp/pages?page=index', 'label' => '页面编辑', 'perm' => 'pages'],
                ['id' => 'cluster', 'label' => 'Cluster 管理', 'perm' => 'pages'],
                ['id' => 'landing-pages', 'label' => '落地页', 'perm' => ['landing', 'conversion']],
                ['id' => 'page-modules', 'label' => '落地页模块', 'perm' => ['landing', 'conversion']],
                ['id' => 'conversion', 'label' => '转化组件', 'perm' => ['landing', 'conversion']],
                ['id' => 'featured', 'label' => '推荐位', 'perm' => 'featured'],
                ['id' => 'promos', 'label' => '站内投放', 'perm' => 'promos'],
            ]],
            ['label' => '结构', 'items' => [
                ['id' => 'categories', 'href' => '/xmp/categories?type=article', 'label' => '文章分类', 'perm' => ['community-config', 'articles']],
                ['id' => 'topics', 'label' => '专题', 'perm' => 'topics'],
                ['id' => 'authors', 'label' => '作者', 'perm' => 'authors'],
                ['id' => 'cpt', 'label' => '自定义内容类型', 'perm' => 'cpt'],
                ['id' => 'content-i18n', 'label' => '内容多语言', 'perm' => 'articles'],
                ['id' => 'community-config', 'label' => '学院首页配置', 'perm' => ['community-config', 'articles']],
            ]],
            ['label' => '素材', 'items' => [
                ['id' => 'dam', 'label' => '数字资产', 'perm' => ['dam', 'media']],
                ['id' => 'media', 'label' => '多媒体', 'perm' => ['dam', 'media']],
                ['id' => 'stock-photos', 'label' => '免费图库', 'perm' => ['dam', 'media']],
            ]],
        ]],
        ['id' => 'community', 'label' => '社区与学院', 'short' => '社区', 'icon' => $I['users'], 'groups' => [
            ['label' => '社区', 'items' => [
                ['id' => 'community-mod', 'label' => '社区管理', 'perm' => 'community-mod'],
                ['id' => 'comments', 'label' => '评论 / 点评', 'perm' => ['community-mod', 'moderation']],
                ['id' => 'moderation', 'label' => '风控中心', 'perm' => 'moderation'],
                ['id' => 'reports', 'label' => '举报', 'perm' => 'moderation'],
                ['id' => 'bookmarks', 'label' => '收藏', 'perm' => 'bookmarks'],
                ['id' => 'follows', 'label' => '关注', 'perm' => 'follows'],
            ]],
            ['label' => '学院与活动', 'items' => [
                ['id' => 'courses', 'label' => '课程', 'perm' => 'courses'],
                ['id' => 'events', 'label' => '活动', 'perm' => 'events'],
                ['id' => 'live', 'label' => '直播', 'perm' => 'live'],
                ['id' => 'consultation', 'label' => '1v1 咨询', 'perm' => 'consultation'],
                ['id' => 'navigation', 'label' => '增长导航', 'perm' => 'navigation'],
            ]],
            ['label' => '审核', 'items' => [
                ['id' => 'approvals', 'label' => '审核中心', 'perm' => 'approvals'],
                ['id' => 'reviews', 'label' => '内容审核', 'perm' => 'reviews'],
                ['id' => 'review-settings', 'label' => '审核规则', 'perm' => 'reviews'],
            ]],
        ]],
        ['id' => 'growth', 'label' => '增长', 'icon' => $I['bolt'], 'groups' => [
            ['label' => '自动化', 'items' => [
                ['id' => 'automation', 'label' => '营销自动化', 'perm' => 'automation'],
                ['id' => 'canvas', 'label' => '画布流程', 'perm' => 'canvas'],
                ['id' => 'campaigns', 'label' => '活动 / CRO', 'perm' => 'campaigns'],
                ['id' => 'dynamic-content', 'label' => 'Dynamic Engine', 'perm' => 'settings'],
                ['id' => 'ma-sync', 'label' => 'MA 融合同步', 'perm' => 'ma-sync'],
            ]],
            ['label' => '分群与画像', 'items' => [
                ['id' => 'segments', 'label' => '用户分群', 'perm' => 'segments'],
                ['id' => 'destinations', 'label' => '人群激活', 'perm' => 'segments'],
                ['id' => 'profiling', 'label' => '用户画像', 'perm' => 'profiling'],
                ['id' => 'frequency-cap', 'label' => '触达频控', 'perm' => 'cdp'],
            ]],
            ['label' => '触达渠道', 'items' => [
                ['id' => 'email', 'label' => '邮件', 'perm' => 'email'],
                ['id' => 'sms', 'label' => '短信', 'perm' => 'sms'],
                ['id' => 'forms', 'label' => '表单', 'perm' => 'forms'],
                ['id' => 'submissions', 'label' => '提交记录', 'perm' => 'submissions'],
                ['id' => 'qr', 'label' => '二维码', 'perm' => 'qr'],
                ['id' => 'utm-builder', 'label' => 'UTM 生成器', 'perm' => 'utm-builder'],
            ]],
            ['label' => '微信与社媒', 'items' => [
                ['id' => 'wechat-mp', 'label' => '公众号', 'perm' => 'wechat-mp'],
                ['id' => 'wechat-send', 'label' => '群发 & 私信', 'perm' => 'wechat-mp'],
                ['id' => 'wechat-tags', 'label' => '服务号标签', 'perm' => 'wechat-mp'],
                ['id' => 'wechat-messages', 'label' => '客服 / 模板消息', 'perm' => 'wechat-mp'],
                ['id' => 'wecom', 'label' => '企业微信', 'perm' => 'wechat-mp'],
                ['id' => 'social', 'label' => '社交媒体', 'perm' => 'social'],
            ]],
        ]],
        ['id' => 'data', 'label' => '数据', 'icon' => $I['chart'], 'groups' => [
            ['label' => '分析', 'items' => [
                ['id' => 'cdp', 'label' => 'CDP 数据中台', 'perm' => 'cdp'],
                ['id' => 'analytics', 'label' => '运营分析', 'perm' => 'analytics'],
                ['id' => 'realtime', 'label' => '实时数据', 'perm' => 'analytics'],
                ['id' => 'path-analysis', 'label' => '路径分析', 'perm' => 'analytics'],
                ['id' => 'attribution', 'label' => '增长归因', 'perm' => 'analytics'],
                ['id' => 'attribution-model', 'label' => '多触点归因', 'perm' => 'analytics'],
                ['id' => 'insights', 'label' => '营销洞察', 'perm' => 'insights'],
                ['id' => 'ask-data', 'label' => '问数据', 'perm' => 'insights'],
                ['id' => 'share-kols', 'label' => '分享传播', 'perm' => 'analytics'],
                ['id' => 'abtests', 'label' => 'A/B 测试', 'perm' => 'abtests'],
            ]],
            ['label' => '行为与巡检', 'items' => [
                ['id' => 'heatmap', 'label' => '点击热力图', 'perm' => 'cdp'],
                ['id' => 'session-replay', 'label' => '会话回放', 'perm' => 'cdp'],
                ['id' => 'funnel-guard', 'label' => '漏斗巡检', 'perm' => 'cdp'],
                ['id' => 'report-subscribe', 'label' => '报表订阅', 'perm' => 'cdp'],
            ]],
            ['label' => '采集与连接', 'items' => [
                ['id' => 'tracking', 'label' => '埋点', 'perm' => 'tracking'],
                ['id' => 'click-tracking', 'label' => '圈选埋点', 'perm' => 'tracking'],
                ['id' => 'scripts', 'label' => '脚本', 'perm' => 'scripts'],
                ['id' => 'event-dictionary', 'label' => '事件字典', 'perm' => 'cdp'],
                ['id' => 'data-connector', 'label' => '数据连接器', 'perm' => 'cdp'],
                ['id' => 'inbound', 'label' => '入站接收', 'perm' => 'cdp'],
                ['id' => 'data-sync', 'label' => '外部连接', 'perm' => 'cdp'],
            ]],
            ['label' => '调研', 'items' => [
                ['id' => 'survey', 'label' => '问卷调研', 'perm' => 'survey'],
                ['id' => 'survey-stats', 'label' => '问卷统计', 'perm' => 'survey'],
                ['id' => 'nps', 'label' => 'NPS', 'perm' => 'nps'],
            ]],
            ['label' => 'SEO 与舆情', 'items' => [
                ['id' => 'seo-center', 'label' => 'SEO 中心', 'perm' => ['seo', 'seo-tools', 'redirects']],
                ['id' => 'geo', 'label' => 'GEO 话题监控', 'perm' => 'geo'],
                ['id' => 'sentiment', 'label' => '舆情监测', 'perm' => 'sentiment'],
            ]],
        ]],
        ['id' => 'commerce', 'label' => '商业', 'icon' => $I['cart'], 'groups' => [
            ['label' => '客户与线索', 'items' => [
                ['id' => 'crm', 'label' => 'CRM 线索', 'perm' => 'crm'],
                ['id' => 'crm-raw', 'href' => '/xmp/crm?tab=raw', 'label' => '原始线索', 'perm' => 'crm'],
                ['id' => 'crm-pool', 'href' => '/xmp/crm?tab=pool', 'label' => '公海', 'perm' => 'crm'],
                ['id' => 'orgs', 'label' => '企业客户', 'perm' => 'crm'],
                ['id' => 'quotes', 'label' => '收款链接', 'perm' => 'quotes'],
                ['id' => 'brain', 'label' => '增长大脑', 'perm' => 'brain'],
                ['id' => 'decision-trace', 'label' => '决策轨道', 'perm' => 'brain'],
            ]],
            ['label' => '订单与商城', 'items' => [
                ['id' => 'orders', 'label' => '订单与退款', 'perm' => 'shop-settings'],
                ['id' => 'refunds', 'label' => '退款售后', 'perm' => 'commerce'],
                ['id' => 'mall', 'label' => '商城', 'perm' => 'shop-settings'],
                ['id' => 'catalog', 'label' => '商品目录', 'perm' => 'commerce'],
                ['id' => 'coupons', 'label' => '优惠券', 'perm' => 'commerce'],
                ['id' => 'activation', 'label' => '激活码', 'perm' => 'shop-settings'],
                ['id' => 'distribution', 'label' => '分销', 'perm' => 'shop-settings'],
                ['id' => 'commission', 'label' => '分成与结算', 'perm' => 'commerce'],
                ['id' => 'ecom-reports', 'label' => '电商报表', 'perm' => 'commerce'],
                ['id' => 'shop-settings', 'label' => '商城设置', 'perm' => 'shop-settings'],
            ]],
            ['label' => '会员', 'items' => [
                ['id' => 'membership', 'label' => '会员体系', 'perm' => 'membership'],
                ['id' => 'subscription', 'label' => '付费订阅', 'perm' => 'subscription'],
            ]],
            ['label' => '生态', 'items' => [
                ['id' => 'marketplace', 'label' => '生态市场', 'perm' => 'marketplace'],
                ['id' => 'developers', 'label' => '开发者审核', 'perm' => 'marketplace'],
                ['id' => 'dev-docs', 'label' => '开发者文档', 'perm' => 'marketplace'],
                ['id' => 'commerce', 'label' => '商业中心', 'perm' => 'commerce'],
                ['id' => 'platform-ops', 'label' => '平台运营', 'perm' => 'commerce'],
            ]],
        ]],
        ['id' => 'system', 'label' => '系统', 'icon' => $I['gear'], 'groups' => [
            ['label' => '站点与配置', 'items' => [
                ['id' => 'settings', 'label' => '系统设置', 'perm' => 'settings'],
                ['id' => 'site-builder', 'label' => '站点结构', 'perm' => 'site-builder'],
                ['id' => 'themes', 'label' => '主题', 'perm' => 'themes'],
                ['id' => 'plugins', 'label' => '插件', 'perm' => 'plugins'],
                ['id' => 'ai-config', 'label' => 'AI Agent', 'perm' => 'ai-config'],
                ['id' => 'ai-usage', 'label' => 'AI 用量与预算', 'perm' => 'ai-config'],
                ['id' => 'knowledge', 'label' => '知识库', 'perm' => 'knowledge'],
                ['id' => 'cloudflare', 'label' => 'Cloudflare', 'perm' => 'settings'],
                ['id' => 'sdk-versions', 'label' => 'SDK 版本', 'perm' => 'settings'],
            ]],
            ['label' => '用户与安全', 'items' => [
                ['id' => 'users', 'label' => '后台用户', 'perm' => 'users'],
                ['id' => 'roles', 'label' => '角色与权限', 'perm' => 'users'],
                ['id' => 'security', 'label' => '账号安全（2FA）', 'perm' => ['themes', 'plugins', 'users', 'settings']],
                ['id' => 'api-permissions', 'label' => 'API 权限矩阵', 'perm' => ['themes', 'plugins', 'users', 'settings']],
                ['id' => 'consent', 'label' => '同意与数据保留', 'perm' => 'settings'],
                ['id' => 'audit-log', 'label' => '审计日志', 'perm' => 'settings'],
            ]],
            ['label' => '集成与开放', 'items' => [
                ['id' => 'api-keys', 'label' => 'API Key', 'perm' => 'settings'],
                ['id' => 'webhooks', 'label' => 'Webhook', 'perm' => 'settings'],
                ['id' => 'api-docs', 'label' => 'API 文档', 'perm' => 'settings'],
                ['id' => 'api-affiliate', 'label' => 'API 分佣', 'perm' => 'settings'],
                ['id' => 'notify-channels', 'label' => '通知渠道', 'perm' => 'notify-channels'],
                ['id' => 'messages', 'label' => '站内信', 'perm' => 'messages'],
                ['id' => 'inbox', 'label' => '统一收件箱', 'perm' => 'messages'],
                ['id' => 'ads', 'label' => '广告位', 'perm' => 'settings'],
                ['id' => 'ad-campaigns', 'label' => '投放管理', 'perm' => 'settings'],
            ]],
            ['label' => '运维与数据', 'items' => [
                ['id' => 'devops', 'label' => '运维工具', 'perm' => 'settings'],
                ['id' => 'health-check', 'label' => '健康检测', 'perm' => 'settings'],
                ['id' => 'backup', 'label' => '备份', 'perm' => 'settings'],
                ['id' => 'migrate', 'label' => '数据迁移', 'perm' => 'settings'],
                ['id' => 'ingest', 'label' => '外部导入', 'perm' => 'ingest'],
                ['id' => 'api-batch', 'label' => '批量接口', 'perm' => 'articles'],
                ['id' => 'data-export', 'label' => '数据导出', 'perm' => 'export'],
                ['id' => 'evolution', 'label' => '自我进化', 'perm' => 'evolution'],
                ['id' => 'safefix', 'label' => '协同修复', 'perm' => 'evolution'],
            ]],
        ]],
    ];
    // 权限过滤：条目 → 组 → 区，空的整层去掉
    $allowed = function ($perm): bool {
        if ($perm === null) return true;
        foreach ((array)$perm as $p) if (has_perm($p)) return true;
        return false;
    };
    $tree = [];
    foreach ($all as $area) {
        $groups = [];
        foreach ($area['groups'] as $g) {
            $items = [];
            foreach ($g['items'] as $it) {
                if (!$allowed($it['perm'] ?? null)) continue;
                $it['href'] = $it['href'] ?? ('/xmp/' . $it['id']);
                $items[] = $it;
            }
            if ($items) { $g['items'] = $items; $groups[] = $g; }
        }
        if ($groups) { $area['groups'] = $groups; $tree[] = $area; }
    }
    return $tree;
}

/** 当前页 id（含别名）→ 所在区与条目 */
function admin_nav_locate(string $current, string $script = ''): array {
    $tree = admin_nav_tree();
    $find = function (string $id) use ($tree): ?array {
        if (isset(ADMIN_NAV_ALIAS[$id])) $id = ADMIN_NAV_ALIAS[$id];
        foreach ($tree as $area) foreach ($area['groups'] as $g) foreach ($g['items'] as $it) {
            if ($it['id'] === $id) return ['area' => $area['id'], 'item' => $it['id'], 'label' => $it['label'], 'areaLabel' => $area['label']];
        }
        return null;
    };
    // 1) 以真实脚本名为准（很多页面传给 admin_sidebar() 的 id 是历史遗留、指向别的页）
    if ($script === '') $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''), '.php');
    $tab = isset($_GET['tab']) ? preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['tab']) : '';
    if ($script !== '') {
        if ($tab !== '' && ($hit = $find($script . '-' . $tab))) return $hit;   // crm?tab=raw → crm-raw
        if ($hit = $find($script)) return $hit;
    }
    // 2) 页面显式传的 id（含 'crm-raw' 这类子 tab）
    if ($hit = $find($current)) return $hit;
    return ['area' => 'overview', 'item' => null, 'label' => '', 'areaLabel' => '概览'];
}

/** 渲染侧栏：区 rail（7 个）+ 当前区的分组条目；其它区折叠，点击展开（不记忆） */
function admin_nav_render(string $current, string $script = ''): void {
    $tree = admin_nav_tree();
    $loc = admin_nav_locate($current, $script);
    $svg = fn(string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    ?>
<nav class="sidebar" id="sidebar" aria-label="后台导航" data-area="<?=htmlspecialchars($loc['area'])?>">
  <div class="sb-areas" role="tablist" aria-label="模块">
    <?php foreach ($tree as $area): $on = $area['id'] === $loc['area']; ?>
    <button type="button" class="sb-area<?=$on ? ' on' : ''?>" role="tab" aria-selected="<?=$on ? 'true' : 'false'?>" data-area="<?=$area['id']?>" title="<?=htmlspecialchars($area['label'])?>"><?=$svg($area['icon'])?><span><?=htmlspecialchars($area['short'] ?? $area['label'])?></span></button>
    <?php endforeach; ?>
  </div>
  <div class="sb-panels">
    <?php foreach ($tree as $area): $on = $area['id'] === $loc['area']; ?>
    <div class="sb-panel<?=$on ? ' on' : ''?>" data-area="<?=$area['id']?>" role="tabpanel">
      <div class="sb-panel-h"><?=htmlspecialchars($area['label'])?><span class="sb-count"><?=array_sum(array_map(fn($g) => count($g['items']), $area['groups']))?></span></div>
      <?php foreach ($area['groups'] as $g): ?>
      <?php if ($g['label'] !== ''): ?><div class="sb-group"><?=htmlspecialchars($g['label'])?></div><?php endif; ?>
      <?php foreach ($g['items'] as $it): $act = $on && $it['id'] === $loc['item']; ?>
      <a href="<?=htmlspecialchars($it['href'])?>" class="sb-link<?=$act ? ' active' : ''?>" data-nav="<?=htmlspecialchars($it['id'])?>"<?=$act ? ' aria-current="page"' : ''?>><span><?=htmlspecialchars($it['label'])?></span><?php if (!empty($it['hint'])): ?><small><?=htmlspecialchars($it['hint'])?></small><?php endif; ?></a>
      <?php endforeach; endforeach; ?>
    </div>
    <?php endforeach; ?>
    <div class="sb-panel-h" style="margin-top:14px">最近打开</div>
    <div id="sbRecent" class="sb-recent" data-current="<?=htmlspecialchars($current)?>" data-current-label="<?=htmlspecialchars($loc['label'])?>"></div>
    <?php if (class_exists('PluginSystem')) PluginSystem::do_action('admin_sidebar_menu', $current); ?>
  </div>
  <div class="sb-foot mono">OpenFlow</div>
</nav>
<?php
}
