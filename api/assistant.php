<?php
/**
 * 全局 AI 小助手 API — 多轮对话
 * POST /api/assistant.php
 * Body: { "message": "用户消息", "session": "可选会话ID" }
 *
 * 通过 ai-config.json 配置的默认供应商调用；支持系统指令注入后台使用说明。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/KnowledgeSystem.php';
require_once __DIR__ . '/../lib/SkillSystem.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

// 重置会话（清空该用户的对话历史）
if (($_GET['action'] ?? '') === 'reset' || ($input['action'] ?? '') === 'reset') {
    $sessionKey = 'assistant_' . ($_SESSION['admin_user'] ?? session_id());
    $history = json_read(DATA_DIR . '/assistant-sessions.json');
    unset($history[$sessionKey]);
    json_write(DATA_DIR . '/assistant-sessions.json', $history);
    echo json_encode(['ok' => true, 'reset' => true]);
    exit;
}

$message = trim($input['message'] ?? '');
if (empty($message)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '消息不能为空']);
    exit;
}

$ai = json_read(DATA_DIR . '/ai-config.json');

// 找到启用中的默认供应商
$provider = null;
$defaultId = $ai['default_provider'] ?? '';
foreach (($ai['providers'] ?? []) as $p) {
    if ($p['id'] === $defaultId && $p['enabled'] && !empty($p['api_key'])) { $provider = $p; break; }
}
// 若默认未启用，用第一个启用且带 key 的
if (!$provider) {
    foreach (($ai['providers'] ?? []) as $p) {
        if ($p['enabled'] && !empty($p['api_key'])) { $provider = $p; break; }
    }
}
if (!$provider) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '请先在「AI Agent 配置」中启用一个供应商并填写 API Key'], JSON_UNESCAPED_UNICODE);
    exit;
}

$model = $provider['model'] ?? 'gpt-4o';
$temperature = $ai['temperature'] ?? 0.7;
$apiUrl = rtrim($provider['api_url'], '/');
$sessionKey = 'assistant_' . ($_SESSION['admin_user'] ?? session_id());

// 读取会话历史（最近 8 轮）
$history = json_read(DATA_DIR . '/assistant-sessions.json');
$conv = $history[$sessionKey] ?? [];
$conv[] = ['role' => 'user', 'content' => mb_substr($message, 0, 4000)];
$conv = array_slice($conv, -16); // 最多保留 16 条（8 轮）

$systemPrompt = <<<PROMPT
你是一个嵌入在 OpenFlow 网站后台的 AI 助手，名字叫「小福」，形象亲切可爱（二次元风格）。
你的职责是帮助运营人员使用这个后台系统，回答关于后台功能、操作步骤、内容建议等问题。

后台主要功能（供你参考回答）：
- 内容：页面管理、文章管理（分类/标签/批量导入）、专题、活动、课程、资料下载、聚合页、媒体管理、免费图库（Pexels/Unsplash/Pixabay 素材搜索）
- SEO 与技术：页面 SEO、SEO 工具、批量 SEO 策略、301 重定向、结构化数据、健康检测
- 营销：WeChat 微信、线索管理、表单管理、提交记录、Campaign、分发渠道、社交媒体、转化组件、邮件、短信、二维码、UTM 生成器
- 系统：系统设置、运维工具、插件管理、主题管理、AI Agent、操作日志、数据导出、权限管理
- 前端：/article/slug 文章详情、/community 门派社区、/academy 内容学院

回答要求：
1. 用中文、简洁、条理清晰回复，可用 emoji 让语气友好
2. 涉及操作步骤时给出具体菜单路径（如「营销 → 表单管理」）
 3. 不要编造不存在的功能；不确定时建议用户查看对应页面
 4. 如果用户询问内容创作（写标题/摘要/文案），直接给出可用的成品
PROMPT;

// 生态市场技能：告诉 AI 有哪些可复用的 Skill 可推荐
$publishedSkills = array_values(array_filter(skills_all(), fn($s) => ($s['status'] ?? '') === 'published'));
if ($publishedSkills) {
    $skillLines = [];
    foreach ($publishedSkills as $s) $skillLines[] = '- ' . ($s['title'] ?? '') . '（' . ($s['type'] ?? '') . '）：' . mb_substr($s['description'] ?? '', 0, 50);
    $systemPrompt .= "\n\n【生态市场可用技能，可推荐给用户使用】\n" . implode("\n", $skillLines) . "\n用户可在 /marketplace.php 安装技能。";
}

// 技能自动匹配：根据用户消息识别意图，执行相关 tool 技能，注入结果
$skillExecuted = null;
if ($publishedSkills) {
    foreach ($publishedSkills as $s) {
        if (($s['type'] ?? '') !== 'tool') continue;
        $kws = array_merge($s['tags'] ?? [], ['数据', '运营', '统计']);
        $hit = false;
        foreach ($kws as $k) {
            if ($k !== '' && mb_strpos(mb_strtolower($message), mb_strtolower($k)) !== false) { $hit = true; break; }
        }
        if ($hit) {
            $r = skill_execute($s['id'], ['q' => $message]);
            if ($r['ok'] && ($r['type'] ?? '') === 'tool') {
                $skillExecuted = ['id' => $s['id'], 'title' => $s['title'], 'data' => $r['data']];
                $systemPrompt .= "\n\n【已执行技能「{$s['title']}」，结果供回答参考】\n" . json_encode($r['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            break;
        }
    }
}

// RAG：注入知识库相关内容（语义检索 + 引用溯源）
$kbCited = knowledge_build_context_cited($message, 3);
if ($kbCited['context']) {
    $systemPrompt .= "\n\n【公司知识库参考】\n" . $kbCited['context'];
}

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($conv as $c) $messages[] = $c;

// 构建请求（兼容 OpenAI 格式与各供应商）
$payload = json_encode([
    'model' => $model,
    'messages' => $messages,
    'temperature' => $temperature,
    'max_tokens' => 2000,
]);

if ($provider['id'] === 'claude') {
    $headers = [
        'x-api-key: ' . $provider['api_key'],
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json',
    ];
    $endpoint = $apiUrl . '/messages';
    // Claude 格式不同：system 单独传
    $payload = json_encode([
        'model' => $model,
        'max_tokens' => 2000,
        'system' => $systemPrompt,
        'messages' => array_slice($conv, -10),
    ]);
} else {
    $headers = [
        'Authorization: Bearer ' . $provider['api_key'],
        'Content-Type: application/json',
    ];
    if ($provider['id'] === 'minimax') {
        $endpoint = $apiUrl . '/text/chatcompletion_v2';
    } else {
        $endpoint = $apiUrl . '/chat/completions';
    }
}

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if ($error) {
    echo json_encode(['ok' => false, 'error' => '请求失败: ' . $error]);
    exit;
}

$data = json_decode($resp, true);
if (!$data) {
    echo json_encode(['ok' => false, 'error' => '响应解析失败', 'raw' => mb_substr($resp, 0, 300)]);
    exit;
}

// 提取回复文本
$reply = '';
if ($provider['id'] === 'claude') {
    $reply = $data['content'][0]['text'] ?? '';
} elseif (isset($data['choices'][0]['message']['content'])) {
    $reply = $data['choices'][0]['message']['content'];
} elseif (isset($data['output_text'])) {
    $reply = $data['output_text'];
} elseif (isset($data['data'][0]['output_text'])) {
    $reply = $data['data'][0]['output_text'];
}

if ($reply !== '') {
    $conv[] = ['role' => 'assistant', 'content' => mb_substr($reply, 0, 4000)];
    $conv = array_slice($conv, -16);
    $history[$sessionKey] = $conv;
    json_write(DATA_DIR . '/assistant-sessions.json', $history);
}

// 意图识别 → 返回可执行的快捷操作（小福聊天里的「去执行」按钮）
$actions = [];
$m = mb_strtolower($message);
$want = [
    '发文章' => [['发文章', '文章'], '文章管理', 'articles.php', '📝'],
    '写文章' => [['写文章', '写一篇', '怎么写'], '写新文章', 'article-edit.php', '✍️'],
    '导入' => [['导入', '搬运', '迁移'], '批量导入', 'api-batch.php', '📦'],
    '课程' => [['课程', '课'], '课程管理', 'courses.php', '🎓'],
    '线索' => [['线索', 'leads'], '线索管理', 'leads.php', '👥'],
    '知识库' => [['知识库', '知识'], '知识库', 'knowledge.php', '📚'],
    '专题' => [['专题'], '专题管理', 'topics.php', '📚'],
    '表单' => [['表单'], '表单管理', 'forms.php', '🧾'],
    '问卷' => [['问卷', '调研'], '问卷管理', 'survey.php', '📋'],
    '微信' => [['微信', '公众号'], '公众号设置', 'wechat.php', '💬'],
    'seo' => [['seo', 'seo 标题', '搜索'], 'SEO 设置', 'seo.php', '🔍'],
    '健康' => [['健康', '检测', '体检'], '健康检测', 'health-check.php', '🩺'],
    '图库' => [['图库', '素材搜索'], '免费图库', 'stock-photos.php', '🌄'],
    '素材' => [['素材', '图片', '上传'], '媒体库', 'media.php', '🖼️'],
    '通知' => [['通知', '推送', '企微', '飞书'], '通知渠道', 'notify-channels.php', '📡'],
    '邮箱' => [['邮箱', '邮件'], '邮件营销', 'email.php', '📧'],
    '短信' => [['短信'], '短信营销', 'sms.php', '💬'],
];
foreach ($want as $kw => $act) {
    [$kws, $label, $file, $icon] = $act;
    foreach ($kws as $k) {
        if (mb_strpos($m, $k) !== false) {
            $permMap = ['articles.php'=>'articles','api-batch.php'=>'articles','article-edit.php'=>'articles','courses.php'=>'courses','leads.php'=>'leads','knowledge.php'=>'knowledge','topics.php'=>'articles','forms.php'=>'forms','survey.php'=>'survey','wechat.php'=>'wechat','seo.php'=>'settings','health-check.php'=>'settings','stock-photos.php'=>'media','media.php'=>'media','notify-channels.php'=>'settings','email.php'=>'settings','sms.php'=>'sms'];
            if (has_perm($permMap[$file] ?? '')) {
                $actions[] = ['label' => $label, 'url' => 'admin/' . $file, 'icon' => $icon];
            }
            break;
        }
    }
}
$actions = array_slice(array_unique(array_map('json_encode', $actions), SORT_STRING), 0, 3);
$actions = array_map('json_decode', $actions);

echo json_encode(['ok' => true, 'reply' => $reply, 'actions' => $actions, 'sources' => $kbCited['sources'] ?? [], 'skill' => $skillExecuted, 'provider' => $provider['id'], 'model' => $model], JSON_UNESCAPED_UNICODE);
