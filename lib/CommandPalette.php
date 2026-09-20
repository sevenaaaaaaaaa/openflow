<?php
declare(strict_types=1);
/**
 * 后台命令面板 — 统一的导航/快捷操作注册表（权限感知）
 * 供 Ctrl+K 全局搜索、工作台快捷入口、小助手快捷操作共用
 */

/**
 * 后台页面自动索引（⌘K 覆盖全部页面）
 *
 * 【为什么】原来面板是手工登记的 41 条，222 个后台页里绝大多数搜不到；
 * 而其中 96 个页面**只有侧栏入口**——不记得在哪 = 找不到。
 * 这里把"已经存在的页面"全部纳入索引（不新增入口、不改导航），标题取各页自己的
 * admin_header()，避免另写一份标题造成漂移。
 *
 * 缓存键含 admin/*.php 的数量与最新 mtime：文件一改就自动重建，不需要手工清缓存。
 */
function cp_page_index(): array
{
    $root = dirname(__DIR__);
    $files = glob($root . '/admin/*.php') ?: [];
    $sig = count($files) . ':' . max(array_map(static fn(string $f): int => (int) @filemtime($f), $files ?: [0]));
    $key = 'palette_pages_' . md5($sig);
    if (class_exists('Cache')) {
        return Cache::remember($key, 86400, static fn(): array => cp_page_index_build($files, $root));
    }
    return cp_page_index_build($files, $root);
}

function cp_page_index_build(array $files, string $root): array
{
    // 301 别名页不索引（⌘K 不该给出会跳走的地址）
    $aliases = [];
    $ht = (string) @file_get_contents($root . '/.htaccess');
    if ($ht !== '' && preg_match_all('#^RewriteRule\s+\^xmp/([a-z0-9-]+)/\?\$\s+\S+\s+\[R=301#mi', $ht, $m)) {
        foreach ($m[1] as $a) $aliases[(string) $a] = true;
    }

    // 导航区名（拿不到就统一放"全部页面"，不猜）
    $navArea = [];
    if (function_exists('admin_nav_build')) {
        foreach (admin_nav_build(true) as $area) {
            $label = (string) ($area['label'] ?? '');
            foreach ((array) ($area['groups'] ?? []) as $group) {
                foreach ((array) ($group['items'] ?? []) as $item) {
                    if (($item['id'] ?? '') !== '') $navArea[(string) $item['id']] = $label;
                    foreach ((array) ($item['subs'] ?? []) as $sub) if (($sub['id'] ?? '') !== '') $navArea[(string) $sub['id']] = $label;
                }
            }
        }
    }

    $out = [];
    foreach ($files as $f) {
        $base = basename($f, '.php');
        if (str_starts_with($base, '_')) continue;
        if (in_array($base, ['config', 'login', 'logout'], true)) continue;
        if (isset($aliases[$base])) continue;
        $src = (string) @file_get_contents($f);
        if ($src === '') continue;
        // 只索引真正的页面（会渲染外壳），片段/工具不算
        if (!str_contains($src, 'admin_header(') && !str_contains($src, 'admin_footer(')) continue;

        $label = '';
        if (preg_match('/admin_header\(\s*[\'"]([^\'"]{2,40})[\'"]/u', $src, $mm)) $label = trim((string) $mm[1]);
        $label = trim((string) preg_replace('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\s]+/u', '', $label));
        if ($label === '') $label = str_replace(['-', '_'], ' ', $base);

        // 权限跟随页面自身声明：没有权限的人不该在面板里看到别人的功能名
        $perm = '';
        if (preg_match('/require_perm\(\s*[\'"]([a-z0-9_-]+)[\'"]/i', $src, $pm)) $perm = (string) $pm[1];

        $section = $navArea[$base] ?? '📚 全部页面';
        $out['/xmp/' . ($base === 'index' ? 'dashboard' : $base)] = [
            'label' => $label,
            'url' => '/xmp/' . ($base === 'index' ? 'dashboard' : $base),
            'icon' => '📄',
            'section' => $section,
            'keywords' => $base . ' ' . str_replace(['-', '_'], ' ', $base) . ' 后台 页面 ' . $label,
            'action' => '',
            'weight' => 10,          // 自动索引用低优先级：命中同样内容时策展项排前面
            'source' => 'page',
            'perm' => $perm,          // 空 = 无权限门槛
        ];
    }
    return $out;
}

function cp_items(): array {
    $items = [];
    $add = function (string $perm, string $label, string $url, string $icon = '📄', string $section = '通用', string $keywords = '', string $action = '') use (&$items) {
        if ($perm === '' || has_perm($perm)) $items[] = [
            'label' => $label, 'url' => $url, 'icon' => $icon,
            'section' => $section, 'keywords' => $keywords . ' ' . $label,
            'action' => $action,
        ];
    };

    $addIdx = function (array $it) use (&$items): void {
        $it['weight'] = $it['weight'] ?? 0;      // 策展项权重 0
        $it['source'] = $it['source'] ?? 'curated';
        $items[] = $it;
    };

    // ── 快捷动作（命令栏 2.0：直接执行，不只是跳转）──
    $sec = '⚡ 快捷动作';
    $add('articles', '生成 PPT 幻灯片', '/xmp/create?tab=slides', '📊', $sec, 'ppt 幻灯片 演示 slides deck 做ppt');
    $add('articles', '写深度专栏（AI 大纲→成文）', '/xmp/create?tab=column', '✍️', $sec, '专栏 深度 文章 大纲 写作');
    $add('articles', '写口播视频脚本', '/xmp/create?tab=script', '🎬', $sec, '脚本 视频 口播 短视频 script');
    $add('live', '开播 / 直播间管理', '/xmp/live', '📡', $sec, '开播 直播 live obs');
    $add('', '切换深色 / 浅色主题', '', '🌓', $sec, '主题 深色 浅色 暗黑 dark theme', 'theme');
    $add('', '复制本站网址', '', '🔗', $sec, '复制 网址 url 链接 site', 'copy-site');
    $add('settings', '清理 Cloudflare 缓存', '', '☁️', $sec, '缓存 清理 cdn cloudflare purge', 'purge-cache');

    // ── CMS 内容 ──
    $sec = 'CMS 内容';
    $add('pages', '页面管理', '/xmp/content-hub?tab=pages', '📄', $sec, 'page 页面 首页 编辑 pages-list');
    $add('articles', '文章管理', '/xmp/content-hub?tab=articles', '📝', $sec, 'article 文章 发布');
    $add('articles', '写一篇新文章', '/xmp/article-edit', '✍️', $sec, '新文章 创建 编辑');
    $add('articles', '产品发现 Loop', '/xmp/product-scout', '🔭', $sec, 'scout 产品发现 每日 新产品');
    $add('articles', '创作台', '/xmp/create', '🎨', $sec, '创作 专栏 脚本 幻灯片 ppt 演示 slides create deck');
    $add('ingest', '外部内容导入（飞书/Notion/Obsidian）', '/xmp/ingest', '🔌', $sec, 'ingest 导入 飞书 notion obsidian');
    $add('articles', '批量导入文章', '/xmp/api-batch', '📦', $sec, '批量 导入 api');
    $add('articles', '文章分类', '/xmp/categories', '🗂️', $sec, 'category 分类');
    $add('articles', '文章标签', '/xmp/content-hub?tab=pages&sub=tags', '🏷️', $sec, 'tag 标签');
    $add('articles', '专题聚合', '/xmp/topics', '📚', $sec, 'topic 专题');
    $add('articles', '活动管理', '/xmp/events', '🎉', $sec, 'event 活动');
    $add('courses', '课程管理', '/xmp/courses', '🎓', $sec, 'course 课程 专栏 系列课');
    $add('consultation', '1v1 咨询（咨询师/预约/回放）', '/xmp/consultation', '🤝', $sec, 'consult 咨询 预约 1v1 导师');
    $add('live', '直播管理（OBS 推流）', '/xmp/live', '📡', $sec, 'live 直播 obs 推流 rtmp');
    $add('membership', '会员体系（等级/权益）', '/xmp/membership', '💎', $sec, 'membership 会员 vip 权益');
    $add('marketplace', '生态市场（插件/技能/主题）', '/xmp/marketplace', '🧩', $sec, 'marketplace 市场 插件 技能 主题 生态');
    $add('media', '媒体资源', '/xmp/media', '🖼️', $sec, 'media 图片 素材 上传');
    $add('media', '免费图库（Pexels/Unsplash）', '/xmp/stock-photos', '🌄', $sec, '图库 免版权 素材');

    // ── 知识与 AI ──
    $sec = '知识与 AI';
    $add('knowledge', '公司知识库（AI 检索）', '/xmp/knowledge', '📚', $sec, 'knowledge 知识库 rag ai');
    $add('ai-config', 'AI Agent 配置', '/xmp/ai-config', '🤖', $sec, 'ai gpt claude 模型 供应商');

    // ── 营销获客 ──
    $sec = '营销获客';
    $add('leads', '线索管理', '/xmp/crm?tab=raw', '👥', $sec, 'lead 线索 潜客');
    $add('survey', '调研问卷', '/xmp/survey', '📋', $sec, 'survey 问卷 调研');
    $add('nps', 'NPS 调研', '/xmp/nps', '📈', $sec, 'nps 满意度');
    $add('forms', '表单管理', '/xmp/forms', '🧾', $sec, 'form 表单');
    $add('wechat', '微信公众号', '/xmp/wechat-mp', '💬', $sec, 'wechat 微信 公众号');
    $add('marketing', 'Campaign 活动营销', '/xmp/campaigns', '🚀', $sec, 'campaign 活动 营销');
    $add('community-mod', '评论 / 点评管理', '/xmp/comments', '💬', $sec, 'comment 评论 点评 审核');
    $add('moderation', '风控中心（AI 审核/扫描）', '/xmp/moderation', '🛡️', $sec, 'moderation 风控 审核 扫描 ai');
    $add('settings', '存储与性能（体检/清理）', '/xmp/health-check?sub=stor', '🗄️', $sec, 'storage 存储 性能 清理 数据库');
    $add('settings', '导航站（大众点评）', '/xmp/navigation', '🧭', $sec, 'navigation 导航 点评 收录');

    // ── 增长与分析 ──
    $sec = '增长与分析';
    $add('flow', '运营主线（三流联动总览）', '/xmp/flow', '🔄', $sec, 'flow 主线 联动 数据流 内容流 价值流');
    $add('settings', '经营驾驶舱', '/xmp/dashboard', '📊', $sec, 'dashboard 驾驶舱 经营');
    $add('analytics', '访问统计', '/xmp/analytics', '📉', $sec, 'analytics 统计 访问');

    // ── 系统 ──
    $sec = '系统设置';
    $add('settings', '系统设置', '/xmp/settings', '⚙️', $sec, 'settings 设置 站点');
    $add('settings', 'SEO 设置', '/xmp/seo-center?tab=pages', '🔍', $sec, 'seo 搜索引擎 页面');
    $add('settings', 'SEO 重定向', '/xmp/seo-center?tab=redirects', '↪️', $sec, 'redirect 301 重定向 跳转');
    $add('settings', '结构化数据', '/xmp/seo-center?tab=structured', '🧬', $sec, 'structured data schema 结构化');
    $add('settings', '图片 SEO', '/xmp/seo-center?tab=images', '🖼️', $sec, 'image 图片 alt seo');
    $add('settings', '机器人分析', '/xmp/seo-center?tab=bots', '🤖', $sec, 'bot 爬虫 机器人 日志');
    $add('settings', '健康检测', '/xmp/health-check', '🩺', $sec, 'health 健康 检测 体检');
    $add('settings', '主题管理', '/xmp/themes', '🎨', $sec, 'theme 主题 前端');
    $add('settings', '数据导出', '/xmp/export', '📤', $sec, 'export 导出');
    $add('settings', '操作日志', '/xmp/audit-log?sub=act', '🧾', $sec, 'log 日志 activity');
    $add('settings', '权限管理', '/xmp/users', '🔐', $sec, 'user 权限 用户');
    $add('settings', '通知渠道（企微/飞书/WhatsApp）', '/xmp/notify-channels', '📡', $sec, '通知 企微 飞书 whatsapp');
    $add('messages', '站内信（广播/个人发送）', '/xmp/messages', '🔔', $sec, 'message 站内信 消息 广播');

    // ── 团队协作 / 生态（此前只能靠顶栏图标或侧栏小项到达）──
    $sec = '🧑‍🤝‍🧑 团队与生态';
    $add('tasks', 'Teams+（项目 · 任务 · 看板）', '/xmp/today?view=team', '🧑‍🤝‍🧑', $sec, 'team teams 团队 项目 任务 看板 kanban 协作');
    $add('tasks', '多维表格视图（表格/看板/日历/甘特/树）', '/xmp/table-views', '🗂️', $sec, 'table views 表格 视图 日历 甘特 树 gantt calendar');
    $add('tasks', '自定义内容类型（字段 · 关联 · 层级）', '/xmp/cpt', '🧩', $sec, 'cpt 内容类型 字段 relation rollup lookup 层级');
    $add('cpt', '生态适配人审（批准/上架）', '/xmp/ecosystem', '🛒', $sec, 'ecosystem 生态 适配 上架 审核 插件');

    // ── 后台页面自动索引：把"已经存在的页面"全部纳入（策展项同名地址优先）──
    $known = [];
    foreach ($items as $it) {
        $p2 = (string) parse_url((string) ($it['url'] ?? ''), PHP_URL_PATH);
        if ($p2 !== '') $known[$p2] = true;
        if (!isset($it['weight'])) $it['weight'] = 0;
    }
    foreach ($items as $i => $it) {
        $items[$i]['weight'] = (int) ($it['weight'] ?? 0);
        $items[$i]['source'] = (string) ($it['source'] ?? 'curated');
    }
    foreach (cp_page_index() as $path => $it) {
        if (isset($known[$path])) continue;
        $perm = (string) ($it['perm'] ?? '');
        if ($perm !== '' && !has_perm($perm)) continue;   // 权限感知：看不到自己没权限的页面
        $items[] = $it;
    }

    return $items;
}

function cp_search(string $q, int $limit = 12): array {
    $q = mb_strtolower(trim($q));
    if ($q === '') return [];
    $items = cp_items();
    $out = [];
    foreach ($items as $it) {
        $hay = mb_strtolower($it['label'] . ' ' . $it['keywords'] . ' ' . $it['section']);
        $score = 0;
        // 全词匹配
        if ($q !== '' && mb_strpos($hay, $q) !== false) $score += 5;
        // 逐字匹配
        if ($score === 0 && mb_strlen($q) >= 2) {
            $all = true;
            foreach (preg_split('//u', $q, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                if (mb_strpos($hay, $ch) === false) { $all = false; break; }
            }
            if ($all) $score += 2;
        }
        if ($score > 0) $out[] = ['item' => $it, 'score' => $score];
    }
    // 同分时策展项（weight=0）排在自动索引页（weight=10）之前
    usort($out, static function (array $a, array $b): int {
        if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
        return (int) ($a['item']['weight'] ?? 0) <=> (int) ($b['item']['weight'] ?? 0);
    });
    return array_map(fn($r) => $r['item'], array_slice($out, 0, $limit));
}
