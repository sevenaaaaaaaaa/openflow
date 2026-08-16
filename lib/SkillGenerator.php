<?php
/**
 * Skill 生成器 — 用 AI 从一句描述生成可发布的 Skill 骨架
 * 降低创作门槛：用户只需描述想要什么能力，AI 生成 prompt/工作流/元信息
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/SkillSystem.php';

// 调用 AI 生成 skill 草案
function skill_generate(string $description, string $author = 'OpenFlow'): array {
    $ai = json_read(DATA_DIR . '/ai-config.json');
    $provider = null;
    foreach (($ai['providers'] ?? []) as $p) {
        if (!empty($p['enabled']) && !empty($p['api_key'])) { $provider = $p; break; }
    }
    if (!$provider) return ['ok' => false, 'error' => 'AI 供应商未配置，请先在「AI Agent 配置」中设置'];

    $model = $provider['model'] ?? 'gpt-4o';
    $apiUrl = rtrim($provider['api_url'], '/');
    $prompt = "你是 OpenFlow 生态市场的 Skill 设计师。根据用户的描述，设计一个可复用的 Skill。\n"
        . "只输出 JSON，格式：{\"title\":\"技能名\",\"type\":\"prompt|tool|workflow\",\"icon\":\"emoji\",\"description\":\"一句话描述\",\"tags\":[\"标签\"],\"content\":\"提示词内容（用 {变量} 做占位符）\",\"steps\":[{\"title\":\"步骤名\",\"desc\":\"说明\"}]}\n"
        . "注意：type 为 prompt 时 content 必填、steps 为空；type 为 workflow 时 steps 必填、content 可空。\n"
        . "用户需求：{$description}";

    $payload = json_encode([
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.5,
        'max_tokens' => 800,
    ]);
    if ($provider['id'] === 'claude') {
        $headers = ['x-api-key: ' . $provider['api_key'], 'anthropic-version: 2023-06-01', 'Content-Type: application/json'];
        $payload = json_encode(['model' => $model, 'max_tokens' => 800, 'messages' => [['role' => 'user', 'content' => $prompt]]]);
        $endpoint = $apiUrl . '/messages';
    } else {
        $headers = ['Authorization: Bearer ' . $provider['api_key'], 'Content-Type: application/json'];
        $endpoint = $apiUrl . ($provider['id'] === 'minimax' ? '/text/chatcompletion_v2' : '/chat/completions');
    }
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45]);
    $resp = curl_exec($ch);
    if (!$resp) return ['ok' => false, 'error' => 'AI 请求失败'];

    $data = json_decode($resp, true);
    $content = $data['content'][0]['text'] ?? $data['choices'][0]['message']['content'] ?? $data['output_text'] ?? '';
    // 提取 JSON
    preg_match('/\{[\s\S]*\}/', $content, $m);
    if (empty($m[0])) return ['ok' => false, 'error' => 'AI 返回格式异常'];

    $skill = json_decode($m[0], true);
    if (!$skill) return ['ok' => false, 'error' => 'AI 返回无法解析'];

    // 规范化
    return [
        'ok' => true,
        'skill' => [
            'title' => $skill['title'] ?? 'AI 生成的技能',
            'type' => in_array($skill['type'] ?? '', ['prompt', 'tool', 'workflow']) ? $skill['type'] : 'prompt',
            'icon' => $skill['icon'] ?? '⚡',
            'description' => $skill['description'] ?? '',
            'tags' => array_slice(array_map('strval', $skill['tags'] ?? []), 0, 5),
            'content' => $skill['content'] ?? '',
            'steps' => array_slice($skill['steps'] ?? [], 0, 8),
            'author' => $author,
        ],
    ];
}

// 从描述一键生成并发布（草稿）
function skill_generate_and_save(string $description, string $author = 'OpenFlow'): array {
    $r = skill_generate($description, $author);
    if (!$r['ok']) return $r;
    $data = array_merge($r['skill'], ['status' => 'draft']);
    skill_publish($data);
    // 找回刚发布的（按标题+草稿）
    $found = null;
    foreach (skills_all() as $s) {
        if (($s['title'] ?? '') === ($data['title'] ?? '') && ($s['status'] ?? '') === 'draft') { $found = $s; break; }
    }
    return ['ok' => true, 'skill' => $found ?: $data];
}

// ═══ 插件骨架生成 ═══
// 从描述生成一个带 hooks 的插件（plugin.json + plugin.php），直接写入 plugins/{id}/
function skill_generate_plugin(string $description, string $author = 'OpenFlow'): array {
    $ai = json_read(DATA_DIR . '/ai-config.json');
    $provider = null;
    foreach (($ai['providers'] ?? []) as $p) {
        if (!empty($p['enabled']) && !empty($p['api_key'])) { $provider = $p; break; }
    }
    if (!$provider) return ['ok' => false, 'error' => 'AI 供应商未配置'];

    $model = $provider['model'] ?? 'gpt-4o';
    $apiUrl = rtrim($provider['api_url'], '/');
    $prompt = "你是一名 OpenFlow CMS 插件开发者。根据需求生成插件。插件使用 PluginSystem hooks：\n"
        . "- add_action('admin_sidebar_menu', fn) 增加后台侧边栏入口\n"
        . "- add_action('article_saved', fn) 文章保存时触发\n"
        . "- add_filter('article_output_before', fn) 文章渲染前\n"
        . "只输出 JSON：{\"id\":\"插件id\",\"name\":\"名称\",\"description\":\"描述\",\"hooks\":[{\"hook\":\"hook名\",\"code\":\"PHP代码（不含<?php）\"}]}\n"
        . "用户需求：{$description}";

    $payload = json_encode(['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.4, 'max_tokens' => 1200]);
    if ($provider['id'] === 'claude') {
        $headers = ['x-api-key: ' . $provider['api_key'], 'anthropic-version: 2023-06-01', 'Content-Type: application/json'];
        $payload = json_encode(['model' => $model, 'max_tokens' => 1200, 'messages' => [['role' => 'user', 'content' => $prompt]]]);
        $endpoint = $apiUrl . '/messages';
    } else {
        $headers = ['Authorization: Bearer ' . $provider['api_key'], 'Content-Type: application/json'];
        $endpoint = $apiUrl . ($provider['id'] === 'minimax' ? '/text/chatcompletion_v2' : '/chat/completions');
    }
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45]);
    $resp = curl_exec($ch);
    if (!$resp) return ['ok' => false, 'error' => 'AI 请求失败'];
    $data = json_decode($resp, true);
    $content = $data['content'][0]['text'] ?? $data['choices'][0]['message']['content'] ?? $data['output_text'] ?? '';
    preg_match('/\{[\s\S]*\}/', $content, $m);
    if (empty($m[0])) return ['ok' => false, 'error' => 'AI 返回格式异常'];
    $spec = json_decode($m[0], true);
    if (!$spec) return ['ok' => false, 'error' => 'AI 返回无法解析'];

    // 写文件
    $id = preg_replace('/[^a-z0-9\-_]/', '', strtolower($spec['id'] ?? 'my-plugin'));
    $id = $id ?: 'my-plugin';
    $dir = __DIR__ . '/../plugins/' . $id;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // plugin.json
    $manifest = [
        'id' => $id, 'name' => $spec['name'] ?? 'My Plugin', 'version' => '1.0.0',
        'description' => $spec['description'] ?? '', 'author' => $author,
    ];
    file_put_contents($dir . '/plugin.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // plugin.php
    $php = "<?php\n/**\n * {$manifest['name']} — AI 生成插件\n * 由 OpenFlow 生态市场 AI 生成\n */\n";
    $seen = [];
    foreach ($spec['hooks'] ?? [] as $h) {
        $hook = $h['hook'] ?? 'admin_sidebar_menu';
        if (isset($seen[$hook])) continue;
        $seen[$hook] = true;
        $code = trim($h['code'] ?? '');
        if ($code === '') continue;
        if (strpos($hook, 'filter') !== false) {
            $php .= "PluginSystem::add_filter('{$hook}', function(\$value) {\n    {$code}\n    return \$value;\n});\n\n";
        } else {
            $php .= "PluginSystem::add_action('{$hook}', function() {\n    {$code}\n});\n\n";
        }
    }
    $php .= "// 后台侧边栏入口（示例）\nPluginSystem::add_action('admin_sidebar_menu', function() { echo '<a href=\"#\">{$manifest['name']}</a>'; });\n";
    file_put_contents($dir . '/plugin.php', $php);

    return ['ok' => true, 'plugin_id' => $id, 'manifest' => $manifest, 'dir' => $dir];
}
