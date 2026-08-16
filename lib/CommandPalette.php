<?php
/**
 * 后台命令面板 — 统一的导航/快捷操作注册表（权限感知）
 * 供 Ctrl+K 全局搜索、工作台快捷入口、小助手快捷操作共用
 */

function cp_items(): array {
    $items = [];
    $add = function (string $perm, string $label, string $url, string $icon = '📄', string $section = '通用', string $keywords = '') use (&$items) {
        if (has_perm($perm)) $items[] = [
            'label' => $label, 'url' => $url, 'icon' => $icon,
            'section' => $section, 'keywords' => $keywords . ' ' . $label,
        ];
    };

    // ── CMS 内容 ──
    $sec = 'CMS 内容';
    $add('pages', '页面管理', 'pages.php', '📄', $sec, 'page 页面 首页 编辑');
    $add('articles', '文章管理', 'articles.php', '📝', $sec, 'article 文章 发布');
    $add('articles', '写一篇新文章', 'article-edit.php', '✍️', $sec, '新文章 创建 编辑');
    $add('ingest', '外部内容导入（飞书/Notion/Obsidian）', 'ingest.php', '🔌', $sec, 'ingest 导入 飞书 notion obsidian');
    $add('articles', '批量导入文章', 'api-batch.php', '📦', $sec, '批量 导入 api');
    $add('articles', '文章分类', 'categories.php', '🗂️', $sec, 'category 分类');
    $add('articles', '文章标签', 'tags.php', '🏷️', $sec, 'tag 标签');
    $add('articles', '专题聚合', 'topics.php', '📚', $sec, 'topic 专题');
    $add('articles', '活动管理', 'events.php', '🎉', $sec, 'event 活动');
    $add('courses', '课程管理', 'courses.php', '🎓', $sec, 'course 课程 专栏 系列课');
    $add('consultation', '1v1 咨询（咨询师/预约/回放）', 'consultation.php', '🤝', $sec, 'consult 咨询 预约 1v1 导师');
    $add('live', '直播管理（OBS 推流）', 'live.php', '📡', $sec, 'live 直播 obs 推流 rtmp');
    $add('membership', '会员体系（等级/权益）', 'membership.php', '💎', $sec, 'membership 会员 vip 权益');
    $add('marketplace', '生态市场（插件/技能/主题）', 'marketplace.php', '🧩', $sec, 'marketplace 市场 插件 技能 主题 生态');
    $add('media', '媒体资源', 'media.php', '🖼️', $sec, 'media 图片 素材 上传');
    $add('media', '免费图库（Pexels/Unsplash）', 'stock-photos.php', '🌄', $sec, '图库 免版权 素材');

    // ── 知识与 AI ──
    $sec = '知识与 AI';
    $add('knowledge', '公司知识库（AI 检索）', 'knowledge.php', '📚', $sec, 'knowledge 知识库 rag ai');
    $add('ai-config', 'AI Agent 配置', 'ai-config.php', '🤖', $sec, 'ai gpt claude 模型 供应商');

    // ── 营销获客 ──
    $sec = '营销获客';
    $add('leads', '线索管理', 'leads.php', '👥', $sec, 'lead 线索 潜客');
    $add('survey', '调研问卷', 'survey.php', '📋', $sec, 'survey 问卷 调研');
    $add('nps', 'NPS 调研', 'nps.php', '📈', $sec, 'nps 满意度');
    $add('forms', '表单管理', 'forms.php', '🧾', $sec, 'form 表单');
    $add('wechat', '微信公众号', 'wechat.php', '💬', $sec, 'wechat 微信 公众号');
    $add('marketing', 'Campaign 活动营销', 'campaigns.php', '🚀', $sec, 'campaign 活动 营销');
    $add('community-mod', '评论 / 点评管理', 'comments.php', '💬', $sec, 'comment 评论 点评 审核');
    $add('moderation', '风控中心（AI 审核/扫描）', 'moderation.php', '🛡️', $sec, 'moderation 风控 审核 扫描 ai');
    $add('settings', '存储与性能（体检/清理）', 'storage.php', '🗄️', $sec, 'storage 存储 性能 清理 数据库');
    $add('settings', '导航站（大众点评）', 'navigation.php', '🧭', $sec, 'navigation 导航 点评 收录');

    // ── 增长与分析 ──
    $sec = '增长与分析';
    $add('flow', '运营主线（三流联动总览）', 'flow.php', '🔄', $sec, 'flow 主线 联动 数据流 内容流 价值流');
    $add('settings', '经营驾驶舱', 'dashboard.php', '📊', $sec, 'dashboard 驾驶舱 经营');
    $add('analytics', '访问统计', 'analytics.php', '📉', $sec, 'analytics 统计 访问');

    // ── 系统 ──
    $sec = '系统设置';
    $add('settings', '系统设置', 'settings.php', '⚙️', $sec, 'settings 设置 站点');
    $add('settings', 'SEO 设置', 'seo.php', '🔍', $sec, 'seo 搜索引擎');
    $add('settings', '健康检测', 'health-check.php', '🩺', $sec, 'health 健康 检测 体检');
    $add('settings', '主题管理', 'themes.php', '🎨', $sec, 'theme 主题 前端');
    $add('settings', '数据导出', 'export.php', '📤', $sec, 'export 导出');
    $add('settings', '操作日志', 'logs.php', '🧾', $sec, 'log 日志');
    $add('settings', '权限管理', 'users.php', '🔐', $sec, 'user 权限 用户');
    $add('settings', '通知渠道（企微/飞书/WhatsApp）', 'notify-channels.php', '📡', $sec, '通知 企微 飞书 whatsapp');
    $add('messages', '站内信（广播/个人发送）', 'messages.php', '🔔', $sec, 'message 站内信 消息 广播');

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
    usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_map(fn($r) => $r['item'], array_slice($out, 0, $limit));
}
