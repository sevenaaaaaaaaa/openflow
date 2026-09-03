<?php
/**
 * 后台导航 —— 唯一数据源（v2，2026-09-03）
 *
 * v1 把 148 条扁平链接整成 7 个区（概览/内容/社区/增长/数据/商业/系统）。区是齐了，
 * 但两件事做坏了：① 产品框架 TIPS + AI 在后台完全消失，对外讲的词和对内找功能的词对不上；
 * ② 区里仍旧是平铺——「数据」区一口气 27 条、「系统」33 条，比改之前更难扫。
 *
 * v2：
 *   置顶（4）      工作台 · 经营驾驶舱 · 内容日历 · 自我进化 —— 永远在最上面，不属于任何区
 *   区（6）        触达 Touch / 洞察 Insight / 个性化 Personalize / 销售 Sales / AI 引擎 / 系统
 *   聚合入口（52） 相邻功能合成一个入口，点进去顶部出现子 tab 条在兄弟页之间真跳转
 *
 * 数据结构：区 area → 组 group → 条目 item。条目要么是单页，要么带 subs（一簇兄弟页，
 * 第一条是默认落点）。侧栏只显示条目，subs 由 admin_nav_cluster_bar() 渲染成页内 tab 条。
 *
 *   admin_nav_tree()                 → 全部区（已按权限过滤）
 *   admin_nav_pinned()               → 置顶条目（已按权限过滤）
 *   admin_nav_locate($current)       → ['area','item','sub','label','areaLabel','subs']
 *   admin_nav_render($current)       → 输出 .sidebar
 *   admin_nav_cluster_bar($current)  → 输出当前簇的子 tab 条 HTML（不足 2 个兄弟则返回 ''）
 *
 * perm：字符串 = 需要该权限；数组 = 任一权限即可；null = 不限。
 */

const ADMIN_NAV_ALIAS = [
    // 编辑 / 详情 / 历史页 → 所属条目
    'article-edit' => 'content-hub', 'articles' => 'content-hub', 'pages-list' => 'content-hub', 'downloads' => 'content-hub', 'download-edit' => 'content-hub',
    'podcasts' => 'content-hub', 'page-categories' => 'content-hub', 'tags' => 'content-hub', 'page-builder' => 'pages', 'page-editor-config' => 'pages',
    'course-edit' => 'courses', 'customer-detail' => 'crm', 'profile-detail' => 'profiling', 'user-profile' => 'profiling',
    'abtests-stats' => 'abtests', 'survey-agent' => 'survey', 'survey-org' => 'survey', 'sentiment-report' => 'sentiment',
    'media-upload' => 'media', 'export' => 'data-export', 'export-all' => 'data-export', 'onboarding' => 'workspace', 'debug' => 'devops',
    'notion-sync' => 'data-sync', 'languages' => 'settings', 'storage' => 'health-check', 'activity' => 'audit-log', 'footer-links' => 'site-builder',
    'mail-settings' => 'email', 'payment-settings' => 'shop-settings', 'seo' => 'seo-center', 'seo-tools' => 'seo-center', 'seo-batch' => 'seo-center',
    'seo-console' => 'seo-center', 'redirects' => 'seo-center', 'structured-data' => 'seo-center', 'structured' => 'seo-center', 'landing' => 'landing-pages',
    'image-seo' => 'seo-center', 'index' => 'workspace',
];

function admin_nav_icons(): array {
    return [
        'home'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'gauge'  => '<path d="M22 7 13.5 15.5 8.5 10.5 2 17"/><path d="M16 7h6v6"/>',
        'cal'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/>',
        'eve'    => '<path d="M12 3v3M12 18v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M3 12h3M18 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/><circle cx="12" cy="12" r="4"/>',
        // 区图标：沿用产品页 TIPS 四力的图形语言
        'touch'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'insight'=> '<path d="M22 7 13.5 15.5 8.5 10.5 2 17"/><path d="M16 7h6v6"/>',
        'person' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/>',
        'sales'  => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'ai'     => '<path d="m12 3 1.8 4.9L18.7 9.7l-4.9 1.8L12 16.4l-1.8-4.9L5.3 9.7l4.9-1.8L12 3Z"/><path d="M18.5 15.5l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9.9-2.1Z"/>',
        'gear'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
    ];
}

/** 置顶：跨区常用入口，永远显示在侧栏最上面 */
function admin_nav_pinned(): array {
    static $pins = null;
    if ($pins !== null) return $pins;
    $I = admin_nav_icons();
    $all = [
        ['id' => 'workspace',        'label' => '工作台',    'tag' => '默认',   'perm' => 'dashboard', 'icon' => $I['home']],
        ['id' => 'dashboard',        'label' => '经营驾驶舱', 'tag' => '大屏',   'perm' => 'dashboard', 'icon' => $I['gauge']],
        ['id' => 'content-calendar', 'label' => '内容日历',   'tag' => '排期',   'perm' => 'tasks',     'icon' => $I['cal']],
        ['id' => 'evolution',        'label' => '自我进化',   'tag' => '自生长', 'perm' => 'evolution', 'icon' => $I['eve']],
    ];
    $pins = [];
    foreach ($all as $p) {
        if (!admin_nav_allowed($p['perm'] ?? null)) continue;
        $p['href'] = $p['href'] ?? ('/xmp/' . $p['id']);
        $pins[] = $p;
    }
    return $pins;
}

function admin_nav_allowed($perm): bool {
    if ($perm === null || $perm === '') return true;
    foreach ((array)$perm as $p) if (has_perm($p)) return true;
    return false;
}

function admin_nav_tree(): array {
    static $tree = null;
    if ($tree !== null) return $tree;
    $I = admin_nav_icons();
    $all = [
        ['id' => 'touch', 'label' => '触达 Touch', 'short' => '触达', 'icon' => $I['touch'], 'desc' => '内容 · 社区 · 学院：对外的每一个触点', 'groups' => [
            ['label' => '内容', 'items' => [
                ['id' => 'content-hub', 'label' => '内容中心', 'perm' => 'pages', 'hint' => '文章 · 页面 · 下载 · 播客'],
                ['label' => '内容生产', 'hint' => '任务 · 日历 · 分发', 'subs' => [
                    ['id' => 'tasks', 'label' => '生产任务', 'perm' => 'tasks'],
                    ['id' => 'content-calendar', 'label' => '内容日历', 'perm' => 'tasks'],
                    ['id' => 'publish', 'label' => '内容分发', 'perm' => 'tasks'],
                    ['id' => 'channels', 'label' => '分发渠道', 'perm' => 'channels'],
                    ['id' => 'version-diff', 'label' => '版本对比', 'perm' => 'version-diff'],
                ]],
                ['label' => '页面与落地页', 'hint' => '站点页 · 落地页 · 转化件', 'subs' => [
                    ['id' => 'pages', 'href' => '/xmp/pages?page=index', 'label' => '页面编辑', 'perm' => 'pages'],
                    ['id' => 'cluster', 'label' => 'Cluster 管理', 'perm' => 'pages'],
                    ['id' => 'landing-pages', 'label' => '落地页', 'perm' => ['landing', 'conversion']],
                    ['id' => 'page-modules', 'label' => '落地页模块', 'perm' => ['landing', 'conversion']],
                    ['id' => 'conversion', 'label' => '转化组件', 'perm' => ['landing', 'conversion']],
                ]],
                ['label' => '内容结构', 'hint' => '分类 · 专题 · 作者', 'subs' => [
                    ['id' => 'categories', 'href' => '/xmp/categories?type=article', 'label' => '分类', 'perm' => ['community-config', 'articles']],
                    ['id' => 'topics', 'label' => '专题', 'perm' => 'topics'],
                    ['id' => 'authors', 'label' => '作者', 'perm' => 'authors'],
                    ['id' => 'cpt', 'label' => '自定义内容类型', 'perm' => 'cpt'],
                    ['id' => 'content-i18n', 'label' => '内容多语言', 'perm' => 'articles'],
                    ['id' => 'community-config', 'label' => '学院首页配置', 'perm' => ['community-config', 'articles']],
                ]],
                ['label' => '素材库', 'hint' => '资产 · 多媒体 · 图库', 'subs' => [
                    ['id' => 'dam', 'label' => '数字资产', 'perm' => ['dam', 'media']],
                    ['id' => 'media', 'label' => '多媒体', 'perm' => ['dam', 'media']],
                    ['id' => 'stock-photos', 'label' => '免费图库', 'perm' => ['dam', 'media']],
                ]],
                ['label' => '推荐位与广告', 'hint' => '站内推荐 · 投放', 'subs' => [
                    ['id' => 'featured', 'label' => '推荐位', 'perm' => 'featured'],
                    ['id' => 'promos', 'label' => '站内投放', 'perm' => 'promos'],
                    ['id' => 'ads', 'label' => '广告位', 'perm' => 'settings'],
                    ['id' => 'ad-campaigns', 'label' => '投放管理', 'perm' => 'settings'],
                ]],
            ]],
            ['label' => '社区与学院', 'items' => [
                ['label' => '社区管理', 'hint' => '帖子 · 评论 · 风控', 'subs' => [
                    ['id' => 'community-mod', 'label' => '社区管理', 'perm' => 'community-mod'],
                    ['id' => 'comments', 'label' => '评论 / 点评', 'perm' => ['community-mod', 'moderation']],
                    ['id' => 'moderation', 'label' => '风控中心', 'perm' => 'moderation'],
                    ['id' => 'reports', 'label' => '举报', 'perm' => 'moderation'],
                    ['id' => 'bookmarks', 'label' => '收藏', 'perm' => 'bookmarks'],
                    ['id' => 'follows', 'label' => '关注', 'perm' => 'follows'],
                ]],
                ['label' => '学院与活动', 'hint' => '课程 · 活动 · 直播 · 咨询', 'subs' => [
                    ['id' => 'courses', 'label' => '课程', 'perm' => 'courses'],
                    ['id' => 'events', 'label' => '活动', 'perm' => 'events'],
                    ['id' => 'live', 'label' => '直播', 'perm' => 'live'],
                    ['id' => 'consultation', 'label' => '1v1 咨询', 'perm' => 'consultation'],
                    ['id' => 'navigation', 'label' => '增长导航', 'perm' => 'navigation'],
                ]],
                ['label' => '审核中心', 'hint' => '投稿 · 讲师 · 规则', 'subs' => [
                    ['id' => 'approvals', 'label' => '审核中心', 'perm' => 'approvals'],
                    ['id' => 'reviews', 'label' => '内容审核', 'perm' => 'reviews'],
                    ['id' => 'review-settings', 'label' => '审核规则', 'perm' => 'reviews'],
                ]],
            ]],
        ]],
        ['id' => 'insight', 'label' => '洞察 Insight', 'short' => '洞察', 'icon' => $I['insight'], 'desc' => '分析 · 数据 · SEO：看清发生了什么', 'groups' => [
            ['label' => '分析', 'items' => [
                ['label' => '运营分析', 'hint' => '实时 · 路径 · 传播 · 订阅', 'subs' => [
                    ['id' => 'analytics', 'label' => '运营分析', 'perm' => 'analytics'],
                    ['id' => 'realtime', 'label' => '实时数据', 'perm' => 'analytics'],
                    ['id' => 'path-analysis', 'label' => '路径分析', 'perm' => 'analytics'],
                    ['id' => 'share-kols', 'label' => '分享传播', 'perm' => 'analytics'],
                    ['id' => 'report-subscribe', 'label' => '报表订阅', 'perm' => 'cdp'],
                ]],
                ['label' => '增长归因', 'hint' => '单触点 · 多触点', 'subs' => [
                    ['id' => 'attribution', 'label' => '增长归因', 'perm' => 'analytics'],
                    ['id' => 'attribution-model', 'label' => '多触点归因', 'perm' => 'analytics'],
                ]],
                ['label' => '营销洞察', 'hint' => '洞察 · 问数据', 'subs' => [
                    ['id' => 'insights', 'label' => '营销洞察', 'perm' => 'insights'],
                    ['id' => 'ask-data', 'label' => '问数据', 'perm' => 'insights'],
                ]],
                ['id' => 'abtests', 'label' => 'A/B 测试', 'perm' => 'abtests'],
            ]],
            ['label' => '数据底座', 'items' => [
                ['label' => 'CDP 数据中台', 'hint' => '客户数据 · 事件字典 · 频控', 'subs' => [
                    ['id' => 'cdp', 'label' => 'CDP 数据中台', 'perm' => 'cdp'],
                    ['id' => 'event-dictionary', 'label' => '事件字典', 'perm' => 'cdp'],
                    ['id' => 'frequency-cap', 'label' => '触达频控', 'perm' => 'cdp'],
                ]],
                ['label' => '埋点与接入', 'hint' => '埋点 · 脚本 · 连接器', 'subs' => [
                    ['id' => 'tracking', 'label' => '埋点', 'perm' => 'tracking'],
                    ['id' => 'click-tracking', 'label' => '圈选埋点', 'perm' => 'tracking'],
                    ['id' => 'scripts', 'label' => '脚本', 'perm' => 'scripts'],
                    ['id' => 'data-connector', 'label' => '数据连接器', 'perm' => 'cdp'],
                    ['id' => 'inbound', 'label' => '入站接收', 'perm' => 'cdp'],
                    ['id' => 'data-sync', 'label' => '外部连接', 'perm' => 'cdp'],
                ]],
                ['label' => '行为与巡检', 'hint' => '热力图 · 回放 · 漏斗', 'subs' => [
                    ['id' => 'heatmap', 'label' => '点击热力图', 'perm' => 'cdp'],
                    ['id' => 'session-replay', 'label' => '会话回放', 'perm' => 'cdp'],
                    ['id' => 'funnel-guard', 'label' => '漏斗巡检', 'perm' => 'cdp'],
                ]],
            ]],
            ['label' => '声音与搜索', 'items' => [
                ['label' => '问卷与 NPS', 'hint' => '问卷 · 统计 · NPS', 'subs' => [
                    ['id' => 'survey', 'label' => '问卷调研', 'perm' => 'survey'],
                    ['id' => 'survey-stats', 'label' => '问卷统计', 'perm' => 'survey'],
                    ['id' => 'nps', 'label' => 'NPS', 'perm' => 'nps'],
                ]],
                ['id' => 'seo-center', 'label' => 'SEO 中心', 'perm' => ['seo', 'seo-tools', 'redirects'], 'hint' => '页面 · 工具 · 重定向'],
                ['label' => '舆情与 GEO', 'hint' => '口碑 · 话题监控', 'subs' => [
                    ['id' => 'sentiment', 'label' => '舆情监测', 'perm' => 'sentiment'],
                    ['id' => 'geo', 'label' => 'GEO 话题监控', 'perm' => 'geo'],
                ]],
            ]],
        ]],
        ['id' => 'personalize', 'label' => '个性化 Personalize', 'short' => '个性化', 'icon' => $I['person'], 'desc' => '自动化 · 分群 · 触达：对的人看对的东西', 'groups' => [
            ['label' => '自动化', 'items' => [
                ['label' => '营销自动化', 'hint' => '旅程 · 画布', 'subs' => [
                    ['id' => 'automation', 'label' => '营销自动化', 'perm' => 'automation'],
                    ['id' => 'canvas', 'label' => '画布流程', 'perm' => 'canvas'],
                ]],
                ['label' => '活动与 CRO', 'hint' => '活动 · 动态内容', 'subs' => [
                    ['id' => 'campaigns', 'label' => '活动 / CRO', 'perm' => 'campaigns'],
                    ['id' => 'dynamic-content', 'label' => 'Dynamic Engine', 'perm' => 'settings'],
                ]],
                ['id' => 'ma-sync', 'label' => 'MA 融合同步', 'perm' => 'ma-sync'],
            ]],
            ['label' => '人群', 'items' => [
                ['label' => '分群与画像', 'hint' => '分群 · 激活 · 画像', 'subs' => [
                    ['id' => 'segments', 'label' => '用户分群', 'perm' => 'segments'],
                    ['id' => 'destinations', 'label' => '人群激活', 'perm' => 'segments'],
                    ['id' => 'profiling', 'label' => '用户画像', 'perm' => 'profiling'],
                ]],
            ]],
            ['label' => '触达渠道', 'items' => [
                ['label' => '邮件与短信', 'hint' => 'EDM · SMTP · 短信', 'subs' => [
                    ['id' => 'email', 'label' => '邮件', 'perm' => 'email'],
                    ['id' => 'sms', 'label' => '短信', 'perm' => 'sms'],
                ]],
                ['label' => '表单与提交', 'hint' => '表单 · 提交记录', 'subs' => [
                    ['id' => 'forms', 'label' => '表单', 'perm' => 'forms'],
                    ['id' => 'submissions', 'label' => '提交记录', 'perm' => 'submissions'],
                ]],
                ['label' => '二维码与 UTM', 'hint' => '扫码 · 链接参数', 'subs' => [
                    ['id' => 'qr', 'label' => '二维码', 'perm' => 'qr'],
                    ['id' => 'utm-builder', 'label' => 'UTM 生成器', 'perm' => 'utm-builder'],
                ]],
                ['label' => '微信生态', 'hint' => '公众号 · 群发 · 企微', 'subs' => [
                    ['id' => 'wechat-mp', 'label' => '公众号', 'perm' => 'wechat-mp'],
                    ['id' => 'wechat-send', 'label' => '群发 & 私信', 'perm' => 'wechat-mp'],
                    ['id' => 'wechat-tags', 'label' => '服务号标签', 'perm' => 'wechat-mp'],
                    ['id' => 'wechat-messages', 'label' => '客服 / 模板消息', 'perm' => 'wechat-mp'],
                    ['id' => 'wecom', 'label' => '企业微信', 'perm' => 'wechat-mp'],
                ]],
                ['id' => 'social', 'label' => '社交媒体', 'perm' => 'social'],
            ]],
        ]],
        ['id' => 'sales', 'label' => '销售 Sales', 'short' => '销售', 'icon' => $I['sales'], 'desc' => '线索 · 订单 · 商城：把增长换成收入', 'groups' => [
            ['label' => '客户', 'items' => [
                ['label' => 'CRM 线索', 'hint' => '线索 · 公海 · 企业客户', 'subs' => [
                    ['id' => 'crm', 'label' => 'CRM 线索', 'perm' => 'crm'],
                    ['id' => 'orgs', 'label' => '企业客户', 'perm' => 'crm'],
                ]],
                ['label' => '收款与激活', 'hint' => '收款链接 · 激活码', 'subs' => [
                    ['id' => 'quotes', 'label' => '收款链接', 'perm' => 'quotes'],
                    ['id' => 'activation', 'label' => '激活码', 'perm' => 'shop-settings'],
                ]],
            ]],
            ['label' => '交易', 'items' => [
                ['label' => '订单与退款', 'hint' => '订单 · 退款售后', 'subs' => [
                    ['id' => 'orders', 'label' => '订单与退款', 'perm' => 'shop-settings'],
                    ['id' => 'refunds', 'label' => '退款售后', 'perm' => 'commerce'],
                ]],
                ['label' => '商城', 'hint' => '商品 · 优惠券 · 设置', 'subs' => [
                    ['id' => 'mall', 'label' => '商城', 'perm' => 'shop-settings'],
                    ['id' => 'catalog', 'label' => '商品目录', 'perm' => 'commerce'],
                    ['id' => 'coupons', 'label' => '优惠券', 'perm' => 'commerce'],
                    ['id' => 'shop-settings', 'label' => '商城设置', 'perm' => 'shop-settings'],
                ]],
                ['label' => '会员与订阅', 'hint' => '等级 · 付费订阅', 'subs' => [
                    ['id' => 'membership', 'label' => '会员体系', 'perm' => 'membership'],
                    ['id' => 'subscription', 'label' => '付费订阅', 'perm' => 'subscription'],
                ]],
                ['label' => '分销与结算', 'hint' => '分销 · 分成 · API 分佣', 'subs' => [
                    ['id' => 'distribution', 'label' => '分销', 'perm' => 'shop-settings'],
                    ['id' => 'commission', 'label' => '分成与结算', 'perm' => 'commerce'],
                    ['id' => 'api-affiliate', 'label' => 'API 分佣', 'perm' => 'settings'],
                ]],
                ['id' => 'ecom-reports', 'label' => '电商报表', 'perm' => 'commerce'],
            ]],
            ['label' => '生态', 'items' => [
                ['label' => '生态市场', 'hint' => '市场 · 开发者 · 文档', 'subs' => [
                    ['id' => 'marketplace', 'label' => '生态市场', 'perm' => 'marketplace'],
                    ['id' => 'developers', 'label' => '开发者审核', 'perm' => 'marketplace'],
                    ['id' => 'dev-docs', 'label' => '开发者文档', 'perm' => 'marketplace'],
                ]],
                ['label' => '商业中心', 'hint' => '商业化 · 平台运营', 'subs' => [
                    ['id' => 'commerce', 'label' => '商业中心', 'perm' => 'commerce'],
                    ['id' => 'platform-ops', 'label' => '平台运营', 'perm' => 'commerce'],
                ]],
            ]],
        ]],
        ['id' => 'ai', 'label' => 'AI 引擎', 'short' => 'AI', 'icon' => $I['ai'], 'desc' => '自生长引擎：主动驱动，不等你下指令', 'groups' => [
            ['label' => '主动增长', 'items' => [
                ['id' => 'flow', 'label' => '运营主线', 'perm' => 'flow', 'hint' => '三流联动'],
                ['id' => 'driver', 'label' => '增长驱动', 'perm' => 'flow', 'hint' => '主动引擎'],
                ['label' => '增长大脑', 'hint' => '决策 · 轨道回溯', 'subs' => [
                    ['id' => 'brain', 'label' => '增长大脑', 'perm' => 'brain'],
                    ['id' => 'decision-trace', 'label' => '决策轨道', 'perm' => 'brain'],
                ]],
                ['label' => '自我进化', 'hint' => '进化 · 协同修复', 'subs' => [
                    ['id' => 'evolution', 'label' => '自我进化', 'perm' => 'evolution'],
                    ['id' => 'safefix', 'label' => '协同修复', 'perm' => 'evolution'],
                ]],
            ]],
            ['label' => 'Agent 与知识', 'items' => [
                ['label' => 'AI Agent', 'hint' => '模型 · 用量与预算', 'subs' => [
                    ['id' => 'ai-config', 'label' => 'AI Agent', 'perm' => 'ai-config'],
                    ['id' => 'ai-usage', 'label' => 'AI 用量与预算', 'perm' => 'ai-config'],
                ]],
                ['id' => 'knowledge', 'label' => '知识库', 'perm' => 'knowledge'],
            ]],
        ]],
        ['id' => 'system', 'label' => '系统', 'short' => '系统', 'icon' => $I['gear'], 'desc' => '设置 · 用户 · 运维', 'groups' => [
            ['label' => '站点', 'items' => [
                ['id' => 'settings', 'label' => '系统设置', 'perm' => 'settings'],
                ['id' => 'site-builder', 'label' => '站点结构', 'perm' => 'site-builder'],
                ['label' => '主题与插件', 'hint' => '外观 · 扩展', 'subs' => [
                    ['id' => 'themes', 'label' => '主题', 'perm' => 'themes'],
                    ['id' => 'plugins', 'label' => '插件', 'perm' => 'plugins'],
                ]],
            ]],
            ['label' => '用户与安全', 'items' => [
                ['label' => '用户与权限', 'hint' => '账号 · 角色 · 2FA', 'subs' => [
                    ['id' => 'users', 'label' => '后台用户', 'perm' => 'users'],
                    ['id' => 'roles', 'label' => '角色与权限', 'perm' => 'users'],
                    ['id' => 'security', 'label' => '账号安全（2FA）', 'perm' => ['themes', 'plugins', 'users', 'settings']],
                    ['id' => 'api-permissions', 'label' => 'API 权限矩阵', 'perm' => ['themes', 'plugins', 'users', 'settings']],
                ]],
                ['label' => '合规与审计', 'hint' => '同意 · 保留期 · 日志', 'subs' => [
                    ['id' => 'consent', 'label' => '同意与数据保留', 'perm' => 'settings'],
                    ['id' => 'audit-log', 'label' => '审计日志', 'perm' => 'settings'],
                ]],
            ]],
            ['label' => '集成与运维', 'items' => [
                ['label' => '开放接口', 'hint' => 'Key · Webhook · 文档', 'subs' => [
                    ['id' => 'api-keys', 'label' => 'API Key', 'perm' => 'settings'],
                    ['id' => 'webhooks', 'label' => 'Webhook', 'perm' => 'settings'],
                    ['id' => 'api-docs', 'label' => 'API 文档', 'perm' => 'settings'],
                    ['id' => 'api-batch', 'label' => '批量接口', 'perm' => 'articles'],
                ]],
                ['label' => '通知与站内信', 'hint' => '渠道 · 站内信 · 收件箱', 'subs' => [
                    ['id' => 'notify-channels', 'label' => '通知渠道', 'perm' => 'notify-channels'],
                    ['id' => 'messages', 'label' => '站内信', 'perm' => 'messages'],
                    ['id' => 'inbox', 'label' => '统一收件箱', 'perm' => 'messages'],
                ]],
                ['label' => '运维与部署', 'hint' => '运维 · 健康 · 备份 · CDN', 'subs' => [
                    ['id' => 'devops', 'label' => '运维工具', 'perm' => 'settings'],
                    ['id' => 'health-check', 'label' => '健康检测', 'perm' => 'settings'],
                    ['id' => 'backup', 'label' => '备份', 'perm' => 'settings'],
                    ['id' => 'cloudflare', 'label' => 'Cloudflare', 'perm' => 'settings'],
                    ['id' => 'sdk-versions', 'label' => 'SDK 版本', 'perm' => 'settings'],
                ]],
                ['label' => '数据进出', 'hint' => '导入 · 导出 · 迁移', 'subs' => [
                    ['id' => 'ingest', 'label' => '外部导入', 'perm' => 'ingest'],
                    ['id' => 'data-export', 'label' => '数据导出', 'perm' => 'export'],
                    ['id' => 'migrate', 'label' => '数据迁移', 'perm' => 'settings'],
                ]],
            ]],
        ]],
    ];

    // 归一化 + 权限过滤：sub → item → group → area，空的整层去掉
    $tree = [];
    foreach ($all as $area) {
        $groups = [];
        foreach ($area['groups'] as $g) {
            $items = [];
            foreach ($g['items'] as $it) {
                if (isset($it['subs'])) {
                    $subs = [];
                    foreach ($it['subs'] as $s) {
                        if (!admin_nav_allowed($s['perm'] ?? null)) continue;
                        $s['href'] = $s['href'] ?? ('/xmp/' . $s['id']);
                        $subs[] = $s;
                    }
                    if (!$subs) continue;
                    $it['subs'] = $subs;
                    $it['id']   = $subs[0]['id'];      // 簇 id = 默认落点
                    $it['href'] = $subs[0]['href'];
                } else {
                    if (!admin_nav_allowed($it['perm'] ?? null)) continue;
                    $it['href'] = $it['href'] ?? ('/xmp/' . $it['id']);
                    $it['subs'] = [];
                }
                $items[] = $it;
            }
            if ($items) { $g['items'] = $items; $groups[] = $g; }
        }
        if ($groups) { $area['groups'] = $groups; $tree[] = $area; }
    }
    return $tree;
}

/** 当前页 id（含别名 / 簇内兄弟页）→ 所在区、条目、簇内位置 */
function admin_nav_locate(string $current, string $script = ''): array {
    $tree = admin_nav_tree();
    $miss = ['area' => 'touch', 'item' => null, 'sub' => null, 'pinned' => false, 'label' => '', 'areaLabel' => '', 'subs' => [], 'clusterLabel' => ''];
    $find = function (string $id) use ($tree, $miss): ?array {
        if ($id === '') return null;
        if (isset(ADMIN_NAV_ALIAS[$id])) $id = ADMIN_NAV_ALIAS[$id];
        // 置顶条目也能定位（工作台 / 驾驶舱 / 日历 / 进化）
        foreach ($tree as $area) foreach ($area['groups'] as $g) foreach ($g['items'] as $it) {
            if ($it['id'] === $id) {
                return ['area' => $area['id'], 'item' => $it['id'], 'sub' => $id, 'pinned' => false, 'label' => $it['subs'] ? ($it['subs'][0]['label'] ?? $it['label']) : $it['label'],
                        'areaLabel' => $area['label'], 'subs' => $it['subs'], 'clusterLabel' => $it['label']];
            }
            foreach ($it['subs'] as $s) if ($s['id'] === $id) {
                return ['area' => $area['id'], 'item' => $it['id'], 'sub' => $id, 'pinned' => false, 'label' => $s['label'],
                        'areaLabel' => $area['label'], 'subs' => $it['subs'], 'clusterLabel' => $it['label']];
            }
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
    // 2) 页面显式传的 id
    if ($hit = $find($current)) return $hit;
    // 3) 置顶页（工作台 / 驾驶舱不属于任何区）；别名先归一，index.php → workspace
    foreach ([$script, $current] as $cand) {
        if ($cand === '') continue;
        $id = ADMIN_NAV_ALIAS[$cand] ?? $cand;
        foreach (admin_nav_pinned() as $p) if ($p['id'] === $id) {
            return ['area' => 'touch', 'item' => null, 'sub' => $p['id'], 'pinned' => true, 'label' => $p['label'],
                    'areaLabel' => '', 'subs' => [], 'clusterLabel' => ''];
        }
    }
    return $miss;
}

/**
 * 当前簇的子 tab 条。兄弟页 ≥ 2 才输出；点击是真跳转（各子页仍可单独收藏 / 直达）。
 * 由 admin_wrap_body() 注入到 .main 的开头，所以 90 多个页面一个都不用改。
 */
function admin_nav_cluster_bar(string $current = '', string $script = ''): string {
    if (!function_exists('has_perm')) return '';
    $loc = admin_nav_locate($current, $script);
    if (count($loc['subs']) < 2) return '';
    $h = '<nav class="of-ctabs" aria-label="' . htmlspecialchars($loc['clusterLabel']) . ' 内的功能">';
    // 簇名和某个子页同名时（运营分析 › 运营分析）不再重复一遍
    $dup = false;
    foreach ($loc['subs'] as $s) if (($s['label'] ?? '') === $loc['clusterLabel']) $dup = true;
    if (!$dup) $h .= '<span class="of-ctabs-h">' . htmlspecialchars($loc['clusterLabel']) . '</span>';
    foreach ($loc['subs'] as $s) {
        $on = $s['id'] === $loc['sub'];
        $h .= '<a class="of-ctab' . ($on ? ' on' : '') . '" href="' . htmlspecialchars($s['href']) . '"'
            . ($on ? ' aria-current="page"' : '') . '>' . htmlspecialchars($s['label']) . '</a>';
    }
    return $h . '</nav>';
}

/** 渲染侧栏：置顶 + 区 rail（6 个）+ 当前区的条目；其它区折叠，点击展开（不记忆） */
function admin_nav_render(string $current, string $script = ''): void {
    $tree = admin_nav_tree();
    $loc = admin_nav_locate($current, $script);
    $pins = admin_nav_pinned();
    $svg = fn(string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    ?>
<nav class="sidebar" id="sidebar" aria-label="后台导航" data-area="<?=htmlspecialchars($loc['area'])?>">
  <div class="sb-areas" role="tablist" aria-label="模块">
    <?php foreach ($tree as $area): $on = $area['id'] === $loc['area']; ?>
    <button type="button" class="sb-area<?=$on ? ' on' : ''?>" role="tab" aria-selected="<?=$on ? 'true' : 'false'?>" data-area="<?=$area['id']?>" title="<?=htmlspecialchars($area['label'] . ' · ' . $area['desc'])?>"><?=$svg($area['icon'])?><span><?=htmlspecialchars($area['short'] ?? $area['label'])?></span></button>
    <?php endforeach; ?>
  </div>
  <div class="sb-panels">
    <?php if ($pins): ?>
    <div class="sb-pins">
      <?php foreach ($pins as $p): $act = $loc['sub'] === $p['id']; ?>
      <a href="<?=htmlspecialchars($p['href'])?>" class="sb-pin<?=$act ? ' active' : ''?>"<?=$act ? ' aria-current="page"' : ''?>><?=$svg($p['icon'])?><span><?=htmlspecialchars($p['label'])?></span><span class="tag"><?=htmlspecialchars($p['tag'])?></span></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php foreach ($tree as $area): $on = $area['id'] === $loc['area']; ?>
    <div class="sb-panel<?=$on ? ' on' : ''?>" data-area="<?=$area['id']?>" role="tabpanel">
      <div class="sb-panel-h"><?=htmlspecialchars($area['label'])?><span class="sb-count"><?=array_sum(array_map(fn($g) => count($g['items']), $area['groups']))?></span></div>
      <div class="sb-desc"><?=htmlspecialchars($area['desc'])?></div>
      <?php foreach ($area['groups'] as $g): ?>
      <?php if ($g['label'] !== ''): ?><div class="sb-group"><?=htmlspecialchars($g['label'])?></div><?php endif; ?>
      <?php foreach ($g['items'] as $it): $act = $on && $it['id'] === $loc['item']; ?>
      <a href="<?=htmlspecialchars($it['href'])?>" class="sb-link<?=$act ? ' active' : ''?>" data-nav="<?=htmlspecialchars($it['id'])?>"<?=$act ? ' aria-current="page"' : ''?>><span><?=htmlspecialchars($it['label'])?><?php if (count($it['subs']) > 1): ?><i class="sb-n"><?=count($it['subs'])?></i><?php endif; ?></span><?php if (!empty($it['hint'])): ?><small><?=htmlspecialchars($it['hint'])?></small><?php endif; ?></a>
      <?php endforeach; endforeach; ?>
    </div>
    <?php endforeach; ?>
    <div class="sb-panel-h" style="margin-top:14px">最近打开</div>
    <div id="sbRecent" class="sb-recent" data-current="<?=htmlspecialchars($current)?>" data-current-label="<?=htmlspecialchars($loc['label'])?>"></div>
    <?php if (class_exists('PluginSystem')): ob_start(); PluginSystem::do_action('admin_sidebar_menu', $current); $__plug = trim((string)ob_get_clean()); if ($__plug !== ''): ?>
    <div class="sb-panel-h" style="margin-top:14px">插件</div>
    <div class="sb-plugins"><?=$__plug?></div>
    <?php endif; endif; ?>
  </div>
  <div class="sb-foot mono">OpenFlow</div>
</nav>
<?php
}
