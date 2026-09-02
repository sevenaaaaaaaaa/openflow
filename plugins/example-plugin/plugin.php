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

// 4. 侧边栏菜单
PluginSystem::add_action('admin_sidebar_menu', function ($current) {
    $active = ($current === 'example-plugin') ? 'active' : '';
    echo '<a href="../plugins/example-plugin/view.php" class="' . $active . '">示例插件</a>';
});
