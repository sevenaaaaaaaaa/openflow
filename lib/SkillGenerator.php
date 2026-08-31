<?php
/**
 * Skill 生成器 — 用 AI 从一句描述生成可发布的 Skill 骨架
 * 降低创作门槛：用户只需描述想要什么能力，AI 生成 prompt/工作流/元信息
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/SkillSystem.php';

// 调用 AI 生成 skill 草案
/**
 * 从自然语言描述生成一个 Skill 草稿。
 * 统一走 AiCenter（记账 + 额度闸门 + 分档超时）；原来自建 curl 绕过了电表。
 */
function skill_generate(string $description, string $author = 'OpenFlow'): array {
    require_once __DIR__ . '/AiCenter.php';
    if (!AiCenter::isConfigured()) {
        return ['ok' => false, 'error' => 'AI 供应商未配置，请先在「AI Agent 配置」中设置'];
    }
    $prompt = "你是 OpenFlow 生态市场的 Skill 设计师。根据用户的描述，设计一个可复用的 Skill。\n"
        . "只输出 JSON，格式：{\"title\":\"技能名\",\"type\":\"prompt|tool|workflow\",\"icon\":\"emoji\",\"description\":\"一句话描述\",\"tags\":[\"标签\"],\"content\":\"提示词内容（用 {变量} 做占位符）\",\"steps\":[{\"title\":\"步骤名\",\"desc\":\"说明\"}]}\n"
        . "注意：type 为 prompt 时 content 必填、steps 为空；type 为 workflow 时 steps 必填、content 可空。\n"
        . "用户需求：{$description}";

    $r = AiCenter::chat('', $prompt, [
        'max_tokens' => 800, 'temperature' => 0.5,
        'feature' => 'skill_generate', 'tier' => 'admin',
    ]);
    if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? 'AI 请求失败'];
    $content = (string)($r['text'] ?? '');
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
/**
 * 从自然语言描述生成一个插件骨架。同样统一走 AiCenter。
 */
function skill_generate_plugin(string $description, string $author = 'OpenFlow'): array {
    require_once __DIR__ . '/AiCenter.php';
    if (!AiCenter::isConfigured()) return ['ok' => false, 'error' => 'AI 供应商未配置'];
    $prompt = "你是一名 OpenFlow CMS 插件开发者。根据需求生成插件。插件使用 PluginSystem hooks：\n"
        . "- add_action('admin_sidebar_menu', fn) 增加后台侧边栏入口\n"
        . "- add_action('article_saved', fn) 文章保存时触发\n"
        . "- add_filter('article_output_before', fn) 文章渲染前\n"
        . "只输出 JSON：{\"id\":\"插件id\",\"name\":\"名称\",\"description\":\"描述\",\"hooks\":[{\"hook\":\"hook名\",\"code\":\"PHP代码（不含<?php）\"}]}\n"
        . "用户需求：{$description}";

    $r = AiCenter::chat('', $prompt, [
        'max_tokens' => 1200, 'temperature' => 0.4,
        'feature' => 'plugin_generate', 'tier' => 'admin',
    ]);
    if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? 'AI 请求失败'];
    $content = (string)($r['text'] ?? '');
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
