<?php
/**
 * 示例插件 — 演示 OpenFlow PluginSystem 用法
 *
 * 安装后（后台「系统 → 插件管理」启用）：
 * 1. 文章保存时自动添加标签
 * 2. 侧边栏添加「示例插件」入口
 * 3. 表单提交时记录额外信息
 */

// 1. 文章保存前：自动打标签
PluginSystem::add_filter('article_save_before', function ($article) {
    $content = $article['content'] ?? '';
    // 如果正文提到"增长"，自动加标签
    if (mb_strpos($content, '增长') !== false) {
        $tags = $article['tags'] ?? [];
        if (!in_array('增长主题', $tags)) $tags[] = '增长主题';
        $article['tags'] = $tags;
    }
    return $article;
});

// 2. 文章保存后：写入日志（可通过自定义动作）
PluginSystem::add_action('article_saved', function ($id, $article) {
    // 示例：把保存动作转发到外部 Webhook
    $webhook = json_read(DATA_DIR . '/plugins/example-plugin/webhook.json');
    if (!empty($webhook['url'])) {
        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['event' => 'article_saved', 'id' => $id, 'title' => $article['title']]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
});

// 3. 表单提交后：通知外部系统
PluginSystem::add_action('form_submitted', function ($formId, $formType, $formData, $submission) {
    // 示例：新线索推送到企业微信群（通过现有通知渠道）
    if (function_exists('notify_channels_send')) {
        notify_channels_send('新表单提交', ($formData['name'] ?? '匿名') . ' · ' . ($formData['email'] ?? ''), 'admin/submissions.php');
    }
});

// 4. 侧边栏菜单（旧式原始 HTML 钩子，仍兼容；新插件建议用下方 register_admin_menu）
PluginSystem::add_action('admin_sidebar_menu', function ($current) {
    $active = ($current === 'example-plugin') ? 'active' : '';
    echo '<a href="../plugins/example-plugin/view.php" class="' . $active . '">示例插件</a>';
});

// ═══════════ 以下为 v2 插件 API（推荐用法）═══════════

// 5. 注册 API 端点：GET /api/plugin/example-plugin/stats
//    此端点只读文章计数，显式声明公开（新安全默认：未声明 auth 的路由按需要登录从严处理）
PluginSystem::register_api_route('example-plugin', 'GET', 'stats', function () {
    return PluginApiResponse::json([
        'articles' => count(json_read(DATA_DIR . '/articles.json')['items'] ?? []),
        'time'     => date('c'),
    ]);
}, ['auth' => 'none']);

// 6. 注册后台菜单 + 设置页：/xmp/plugin/example-plugin
PluginSystem::register_admin_menu(['id' => 'example-plugin', 'label' => '示例插件', 'order' => 50]);
PluginSystem::register_admin_page('example-plugin', function ($pluginId) {
    echo '<div class="card"><h2>示例插件设置</h2>'
       . '<p class="hint">这是用 <code>register_admin_page()</code> 注册的设置页，外壳（登录/侧栏）由系统提供。</p></div>';
});

// 7. 前台插槽：文章正文后追加一行署名
PluginSystem::register_front_slot('article_after', function ($ctx) {
    echo '<p style="font-size:12px;color:var(--faint);margin-top:20px">— 由示例插件注入（article_after 插槽）</p>';
});

// 8. 定时任务：每小时写一行心跳日志（由 api/cron.php 驱动）
PluginSystem::register_schedule('example-heartbeat', 'hourly', function () {
    @file_put_contents(DATA_DIR . '/plugins/example-plugin/heartbeat.log', date('c') . "\n", FILE_APPEND);
});
