<?php
/**
 * Skill 系统 — 可复用的 AI/Agent 能力包
 *
 * Skill = 一个可安装、可复用、可分享的「能力单元」，比插件更轻：
 *  - 插件：需要 PHP 代码（门槛高）
 *  - Skill：JSON 定义 + 可选 prompt/处理逻辑（门槛低，普通用户可创作）
 *
 * Skill 类型：
 *  - prompt  ：给 AI 的指令模板（写作/分析/文案等）
 *  - tool    ：可执行的能力（复用 MCP Server 的 tool 机制）
 *  - workflow：多步编排（步骤列表）
 *
 * 数据：data/skills/index.json（市场资产）+ plugins/skills/{id}/（可安装的实际文件）
 */
require_once __DIR__ . '/../admin/config.php';

function skill_file(): string { return DATA_DIR . '/skills/index.json'; }
function skill_dir(): string { return __DIR__ . '/../plugins/skills'; }

// ─── 资产列表 ───
function skills_all(): array { return json_read(skill_file()); }
function skills_save(array $list): void {
    if (!is_dir(dirname(skill_file()))) mkdir(dirname(skill_file()), 0755, true);
    json_write(skill_file(), $list);
}
function skill_get(string $id): ?array {
    foreach (skills_all() as $s) if ($s['id'] === $id) return $s;
    return null;
}

// 内置 skill 类型
function skill_types(): array {
    return [
        'prompt' => ['name' => 'AI 指令', 'icon' => '🤖', 'desc' => '可复用的 AI 提示词模板'],
        'tool' => ['name' => '能力工具', 'icon' => '🔧', 'desc' => '可执行的后台/数据能力'],
        'workflow' => ['name' => '工作流', 'icon' => '🔄', 'desc' => '多步编排流程'],
    ];
}

// 发布/更新 skill（市场资产）
function skill_publish(array $s): void {
    $list = skills_all();
    $found = false;
    foreach ($list as &$x) {
        if ($x['id'] === ($s['id'] ?? '')) { $x = array_merge($x, $s); $found = true; break; }
    }
    unset($x);
    if (!$found) $list[] = array_merge([
        'id' => 'skill_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'type' => 'prompt',
        'title' => '', 'description' => '',
        'author' => 'OpenFlow', 'author_type' => 'official',  // official / developer / user (PGC/UGC)
        'icon' => '🤖', 'tags' => [],
        'content' => '',       // prompt 内容 / 说明
        'steps' => [],         // workflow 步骤
        'status' => 'published',  // draft / published
        'installs' => 0, 'rating' => 0, 'rating_count' => 0,
        'version' => '1.0.0',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ], $s);
    skills_save($list);
}

// 删除
function skill_delete(string $id): void {
    skills_save(array_values(array_filter(skills_all(), fn($s) => $s['id'] !== $id)));
    // 同时删安装目录
    $dir = skill_dir() . '/' . $id;
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') as $f) unlink($f);
        @rmdir($dir);
    }
}

// ─── 执行 skill ───
// prompt 类型：返回 prompt 文本
// tool 类型：执行内置 handler
function skill_execute(string $id, array $params = []): array {
    $s = skill_get($id);
    if (!$s) return ['ok' => false, 'error' => 'Skill 不存在'];
    if (($s['status'] ?? '') !== 'published') return ['ok' => false, 'error' => 'Skill 未发布'];

    switch ($s['type']) {
        case 'prompt':
            // 替换 {param} 占位符
            $prompt = $s['content'] ?? '';
            foreach ($params as $k => $v) {
                if (is_scalar($v)) $prompt = str_replace('{' . $k . '}', (string)$v, $prompt);
            }
            return ['ok' => true, 'type' => 'prompt', 'prompt' => $prompt];

        case 'tool':
            return skill_exec_tool($s, $params);

        case 'workflow':
            $steps = $s['steps'] ?? [];
            $results = [];
            foreach ($steps as $st) {
                $stId = $st['id'] ?? '';
                $results[] = ['step' => $st['title'] ?? $stId, 'status' => 'defined'];
            }
            return ['ok' => true, 'type' => 'workflow', 'steps' => $results];

        default:
            return ['ok' => false, 'error' => '未知 Skill 类型'];
    }
}

// 内置 tool 处理器（可按需扩展）
function skill_exec_tool(array $s, array $params): array {
    $action = $s['tool_action'] ?? '';
    switch ($action) {
        case 'stats_overview':
            return ['ok' => true, 'type' => 'tool', 'data' => [
                'articles' => count(get_articles()),
                'members' => count(json_read(DATA_DIR . '/members/index.json')),
                'leads' => count(get_leads()),
                'revenue' => array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), array_filter(json_read(DATA_DIR . '/shop/orders.json'), fn($o) => ($o['status'] ?? '') === 'paid'))),
            ]];
        case 'search_content':
            $q = $params['q'] ?? '';
            $hits = [];
            foreach (get_articles() as $a) {
                if ($q && mb_strpos(($a['title'] ?? '') . ($a['content'] ?? ''), $q) !== false) {
                    $hits[] = ['title' => $a['title'], 'id' => $a['id'], 'type' => 'article'];
                }
            }
            return ['ok' => true, 'type' => 'tool', 'data' => array_slice($hits, 0, 10)];
        default:
            return ['ok' => false, 'error' => '未实现的工具行为'];
    }
}

// 打分
function skill_rate(string $id, int $rating): void {
    $list = skills_all();
    foreach ($list as &$s) {
        if ($s['id'] === $id) {
            $s['rating'] = (($s['rating'] ?? 0) * ($s['rating_count'] ?? 0) + $rating) / (($s['rating_count'] ?? 0) + 1);
            $s['rating_count'] = ($s['rating_count'] ?? 0) + 1;
            break;
        }
    }
    unset($s);
    skills_save($list);
}

// 安装数 +1
function skill_install_hit(string $id): void {
    $list = skills_all();
    foreach ($list as &$s) {
        if ($s['id'] === $id) { $s['installs'] = ($s['installs'] ?? 0) + 1; break; }
    }
    unset($s);
    skills_save($list);
}
