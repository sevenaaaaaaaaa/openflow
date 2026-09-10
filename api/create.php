<?php
/**
 * 创作台 AI 端点（E2）— POST only，需登录
 *
 * action:
 *   outline  专栏大纲   {topic, angle}
 *   column   专栏成文   {topic, angle, outline}        → 存文章草稿
 *   script   口播脚本   {topic, duration, style}       → 存 scripts.json
 *   slides   幻灯片     {topic, pages, audience}       → 存 slide-decks.json（前台 /deck/{id} 放映）
 */
require_once __DIR__ . '/../admin/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) { http_response_code(401); echo json_encode(['ok' => false, 'error' => '需要登录']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'POST only']); exit; }
csrf_verify();

if (!AiCenter::isConfigured()) {
    echo json_encode(['ok' => false, 'error' => 'AI 未配置，请先到 设置 → AI Agent 配置模型 Key']); exit;
}

$action = (string)($_POST['action'] ?? '');
$topic  = trim((string)($_POST['topic'] ?? ''));

if ($topic === '') { echo json_encode(['ok' => false, 'error' => '主题不能为空']); exit; }

try {
    switch ($action) {

    /* ── 1a. 专栏大纲 ── */
    case 'outline': {
        $angle = trim((string)($_POST['angle'] ?? ''));
        $r = AiCenter::json(
            '你是资深专栏主编，擅长写有观点、有信息密度的深度专栏。输出 JSON：'
            . '{"title":"专栏标题(有观点,不是说明书)","outline":["小节标题1","小节标题2",...5-7个小节],"thesis":"核心论点(50字内)"}',
            "主题：{$topic}" . ($angle ? "\n作者观点/角度：{$angle}" : ''),
            ['max_tokens' => 1200, 'feature' => 'create_outline', 'tier' => 'standard']
        );
        if (empty($r['ok'])) throw new RuntimeException($r['error'] ?? 'AI 生成失败');
        echo json_encode(['ok' => true, 'data' => $r['data']], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ── 1b. 专栏成文 → 存文章草稿 ── */
    case 'column': {
        $outline = (array)json_decode((string)($_POST['outline'] ?? '[]'), true);
        $title   = trim((string)($_POST['title'] ?? $topic));
        $angle   = trim((string)($_POST['angle'] ?? ''));
        $r = AiCenter::json(
            '你是资深专栏作者。按大纲写一篇 1500-2500 字深度专栏，中文。要求：每节有明确论点与论据；'
            . '有具体例子或数据感；不堆术语；结尾给出可执行建议。输出 JSON：'
            . '{"title":"最终标题","slug":"英文slug","excerpt":"100字内摘要","seo_title":"30字内","seo_desc":"80字内",'
            . '"tags":["3-5个"],"content":"HTML正文,<h2>分节,<p>段落,适当<strong>与列表"}',
            "标题：{$title}\n主题：{$topic}\n观点：{$angle}\n大纲：\n- " . implode("\n- ", array_map('strval', $outline)),
            ['max_tokens' => 4000, 'feature' => 'create_column', 'tier' => 'standard']
        );
        if (empty($r['ok']) || empty($r['data']['content'])) throw new RuntimeException($r['error'] ?? 'AI 生成失败');
        $d = $r['data'];
        if (mb_strlen(strip_tags((string)$d['content'])) < 400) throw new RuntimeException('正文过短，请重试');

        $articles = json_read(ARTICLES_DIR . '/index.json');
        $id = 'create_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($d['slug'] ?? ''))) ?: ('column-' . date('YmdHis'));
        $articles[] = [
            'id' => $id, 'title' => $d['title'] ?: $title, 'slug' => $slug,
            'content' => (string)$d['content'], 'excerpt' => (string)($d['excerpt'] ?? ''),
            'status' => 'draft', 'author' => '创作台', 'category' => 'insight',
            'tags' => array_values(array_filter((array)($d['tags'] ?? []))),
            'seo_title' => (string)($d['seo_title'] ?? ''), 'seo_desc' => (string)($d['seo_desc'] ?? ''),
            'seo_keywords' => '', 'source' => 'creation_studio',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ];
        json_write(ARTICLES_DIR . '/index.json', $articles);
        echo json_encode(['ok' => true, 'id' => $id, 'title' => $d['title'] ?: $title], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ── 2. 口播视频脚本 → 存 scripts.json ── */
    case 'script': {
        $duration = in_array($_POST['duration'] ?? '', ['60s', '3min', '10min'], true) ? $_POST['duration'] : '3min';
        $style    = in_array($_POST['style'] ?? '', ['犀利', '温和', '教程'], true) ? $_POST['style'] : '教程';
        $words    = ['60s' => '约220字', '3min' => '约700字', '10min' => '约2200字'][$duration];
        $r = AiCenter::json(
            "你是百万粉知识区口播编导。写一条 {$duration}（{$words}）的口播视频脚本，风格：{$style}。"
            . '要求：前3秒必须有钩子；口语化短句；给出镜头/情绪提示。输出 JSON：'
            . '{"title":"视频标题(适合B站/视频号)","hook":"开场钩子原文","beats":[{"t":"0:00","say":"台词","note":"镜头/情绪提示"}],'
            . '"cta":"结尾行动号召","caption":"发布文案(含话题标签)","shot_list":["拍摄物料清单"]}',
            "主题：{$topic}",
            ['max_tokens' => 3000, 'feature' => 'create_script', 'tier' => 'standard']
        );
        if (empty($r['ok']) || empty($r['data'])) throw new RuntimeException($r['error'] ?? 'AI 生成失败');

        $scripts = json_read(DATA_DIR . '/scripts.json');
        $id = 'script_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
        $scripts[$id] = [
            'id' => $id, 'topic' => $topic, 'duration' => $duration, 'style' => $style,
            'data' => $r['data'], 'created_at' => date('Y-m-d H:i:s'),
        ];
        json_write(DATA_DIR . '/scripts.json', $scripts);
        echo json_encode(['ok' => true, 'id' => $id, 'data' => $r['data']], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ── 3. 幻灯片 → 存 slide-decks.json（前台 /deck/{id} 放映） ── */
    case 'slides': {
        $pages    = max(5, min(15, (int)($_POST['pages'] ?? 8)));
        $audience = trim((string)($_POST['audience'] ?? ''));
        $r = AiCenter::json(
            "你是顶级发布会 keynote 设计师。做一套 {$pages} 页幻灯片，每页只讲一件事。"
            . '输出 JSON：{"title":"整套标题","subtitle":"副标题","slides":[{"kind":"cover|point|list|quote|end",'
            . '"heading":"本页标题(短)","body":"本页正文(60字内)","points":["要点1","要点2"],"quote":"金句(仅quote页)"}]}'
            . '规则：第1页cover，最后1页end(行动号召)；list页给points；quote页给quote；其余point页。',
            "主题：{$topic}" . ($audience ? "\n观众：{$audience}" : ''),
            ['max_tokens' => 3000, 'feature' => 'create_slides', 'tier' => 'standard']
        );
        if (empty($r['ok']) || empty($r['data']['slides'])) throw new RuntimeException($r['error'] ?? 'AI 生成失败');

        $decks = json_read(DATA_DIR . '/slide-decks.json');
        $id = 'deck_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
        $decks[$id] = [
            'id' => $id, 'topic' => $topic, 'audience' => $audience,
            'title' => (string)($r['data']['title'] ?? $topic),
            'subtitle' => (string)($r['data']['subtitle'] ?? ''),
            'slides' => array_values((array)$r['data']['slides']),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        json_write(DATA_DIR . '/slide-decks.json', $decks);
        echo json_encode(['ok' => true, 'id' => $id, 'title' => $decks[$id]['title'], 'pages' => count($decks[$id]['slides'])], JSON_UNESCAPED_UNICODE);
        break;
    }

    default:
        echo json_encode(['ok' => false, 'error' => '未知 action']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
