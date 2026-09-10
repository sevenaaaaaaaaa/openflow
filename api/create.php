<?php
/**
 * 创作台 AI 端点（E2）— POST only，需登录
 *
 * action:
 *   outline  专栏大纲   {topic, angle}
 *   column   专栏成文   {topic, angle, outline}        → 存文章草稿
 *   script   口播脚本   {topic, duration, style}       → 存 scripts.json
 *   slides   幻灯片     {topic, pages, audience}       → 存 slide-decks.json（前台 /deck/{id} 放映）
 *   calendar_fill 日历补内容 {date, topic?}            → AI 选题+大纲 → 定时草稿（publish_at=date）
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

if ($topic === '' && $action !== 'calendar_fill') { echo json_encode(['ok' => false, 'error' => '主题不能为空']); exit; }

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
        $theme    = preg_replace('/[^a-z]/', '', (string)($_POST['theme'] ?? 'stage'));
        $brandMd  = trim((string)($_POST['brand_md'] ?? '')) ?: DeckThemes::globalBrand();
        $brandCtx = DeckThemes::brandPrompt($brandMd);
        $r = AiCenter::json(
            "你是顶级发布会 keynote 设计师。做一套 {$pages} 页幻灯片，每页只讲一件事，版式要多样。"
            . '输出 JSON：{"title":"整套标题","subtitle":"副标题","slides":[{"kind":"cover|agenda|point|list|split|stat|chart|quote|image|end",'
            . '"heading":"本页标题(短)","body":"本页正文(60字内)","points":["要点"],"num":"大数字(仅stat页,如 87%)",'
            . '"quote":"金句(仅quote页)","image":"图片URL(仅image页,可留空)"}]}'
            . '规则：第1页cover，第2页agenda(议程)，最后1页end(行动号召)；至少1页stat(大数字)和1页split(左右分栏)；'
            . 'chart页points格式["标签|数值",...]；list页给points；quote页给quote；image页image留空即可。'
            . $brandCtx,
            "主题：{$topic}" . ($audience ? "\n观众：{$audience}" : ''),
            ['max_tokens' => 3200, 'feature' => 'create_slides', 'tier' => 'standard']
        );
        if (empty($r['ok']) || empty($r['data']['slides'])) throw new RuntimeException($r['error'] ?? 'AI 生成失败');

        $decks = json_read(DATA_DIR . '/slide-decks.json');
        $id = 'deck_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
        $decks[$id] = [
            'id' => $id, 'topic' => $topic, 'audience' => $audience,
            'title' => (string)($r['data']['title'] ?? $topic),
            'subtitle' => (string)($r['data']['subtitle'] ?? ''),
            'slides' => array_values((array)$r['data']['slides']),
            'theme' => $theme, 'brand_md' => $brandMd,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        json_write(DATA_DIR . '/slide-decks.json', $decks);
        echo json_encode(['ok' => true, 'id' => $id, 'title' => $decks[$id]['title'], 'pages' => count($decks[$id]['slides'])], JSON_UNESCAPED_UNICODE);
        break;
    }

    /* ── 4. 全局品牌契约（design.md）── */
    case 'save_brand': {
        $md = trim((string)($_POST['brand_md'] ?? ''));
        if (strlen($md) > 8000) throw new RuntimeException('品牌契约过长（≤8000 字符）');
        file_put_contents(DeckThemes::brandFile(), $md);
        echo json_encode(['ok' => true]);
        break;
    }

    /* ── 5. 内容日历「AI 补一天」：选题（可空→AI 自选）→ 大纲 → 定时草稿 ── */
    case 'calendar_fill': {
        $date = trim((string)($_POST['date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException('日期格式不正确');

        // 近期文章标题作语境，避免选题重复
        $recent = array_slice(array_reverse(json_read(ARTICLES_DIR . '/index.json')), 0, 12);
        $recentTitles = implode('、', array_filter(array_map(fn($a) => $a['title'] ?? '', $recent)));

        if ($topic === '') {
            $r0 = AiCenter::json(
                '你是内容主编。根据近期已发文章，提出一个尚未覆盖、有观点的专栏选题。输出 JSON：{"topic":"选题(20字内)"}',
                "近期已发文章：{$recentTitles}",
                ['max_tokens' => 300, 'feature' => 'calendar_topic', 'tier' => 'light']
            );
            $topic = trim((string)($r0['data']['topic'] ?? '')) ?: '增长方法论';
        }

        $r = AiCenter::json(
            '你是资深专栏主编。输出 JSON：'
            . '{"title":"专栏标题(有观点)","outline":["小节1","小节2",...5-7节],"thesis":"核心论点(50字内)","tags":["3个"]}',
            "选题：{$topic}",
            ['max_tokens' => 1000, 'feature' => 'calendar_fill', 'tier' => 'light']
        );
        if (empty($r['ok']) || empty($r['data']['title'])) throw new RuntimeException($r['error'] ?? 'AI 生成失败');
        $d = $r['data'];

        $articles = json_read(ARTICLES_DIR . '/index.json');
        $id = 'calfill_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
        $outlineText = implode("\n", array_map(fn($s) => '## ' . $s, (array)($d['outline'] ?? [])));
        $articles[] = [
            'id' => $id, 'title' => (string)$d['title'], 'slug' => 'cal-' . date('YmdHis'),
            'content' => "<p><em>核心论点：" . htmlspecialchars((string)($d['thesis'] ?? '')) . "</em></p>\n" . $outlineText . "\n<p>（AI 已备大纲，待成文）</p>",
            'excerpt' => (string)($d['thesis'] ?? ''), 'status' => 'draft', 'author' => '日历 AI',
            'category' => 'insight', 'tags' => array_values(array_filter((array)($d['tags'] ?? []))),
            'seo_title' => '', 'seo_desc' => '', 'seo_keywords' => '', 'source' => 'calendar_ai',
            'publish_at' => $date . ' 09:00:00',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ];
        json_write(ARTICLES_DIR . '/index.json', $articles);
        echo json_encode(['ok' => true, 'id' => $id, 'title' => $d['title'], 'topic' => $topic, 'date' => $date], JSON_UNESCAPED_UNICODE);
        break;
    }

    default:
        echo json_encode(['ok' => false, 'error' => '未知 action']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
