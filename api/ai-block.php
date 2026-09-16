<?php
/**
 * 组件工厂 — AI 快速生成可复用模块
 *
 * 四种输入 → 一个可插入任何页面的自定义模块（BlockSchema）：
 *   POST mode=describe    文字描述 → schema（字段 + custom_html 模板）
 *   POST mode=html        粘贴现有 HTML → AI 识别结构并参数化成模块
 *   POST mode=url         其他网站网址 → 抓取 → 学习版式结构 → 生成原创占位模块（不抄文案）
 *   POST mode=screenshot  上传截图 → 视觉模型读图 → 生成模块（需视觉模型支持）
 *
 * 生成结果存入模块工厂（data/block-types.json），立即出现在建站模块面板里。
 * 渲染走 builder_render_schema_module 的通用 schema 引擎——模板变量 {{字段}}。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/BlockSchema.php';
require_once __DIR__ . '/../lib/BlockRegistry.php';

header('Content-Type: application/json; charset=utf-8');

$member = member_current();
$isAdmin = function_exists('is_logged_in') && is_logged_in();
if (!$member && !$isAdmin) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

$mode = $_POST['mode'] ?? 'describe';

if (!AiCenter::isConfigured()) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'AI 未配置，请先在后台 AI 配置中设置供应商']); exit;
}

/* ── 模块 schema 的统一产出契约（AI 输出被约束到这里） ── */
const AIBLOCK_FIELDS_HINT = <<<TXT
模块 schema 的字段定义（fields 数组），每项：
  {"key":"字段英文标识","label":"字段中文名","type":"字段类型","children":[仅repeat]}
字段类型只允许：title/subtitle/text/richtext/image/url/color/number/select/bool/repeat/form/module。
repeat 列表的子项放 children 数组（同样只允许上述单值类型）。
custom_html 是完整可独立渲染的 HTML 片段：
  - 单值字段用 {{key}} 占位（richtext/html 类不转义、其余转义）
  - repeat 列表用 {{#key}}...{{/key}} 包裹，子字段同样 {{child_key}} 占位
  - 必须自带布局样式（内联 style 或 <style>），不依赖外部 CSS 框架
  - 图片统一 <img src="{{image字段}}">；颜色可加 style="background:{{color字段}}"
TXT;

/** AI 输出 → 保存为模块 schema */
function aiblock_persist(array $schema, string $mode): array {
    if (!is_array($schema)) return ['ok'=>false, 'errors'=>['AI 输出不是 JSON 对象']];
    // key 兜底生成，防撞
    if (empty($schema['key'])) {
        $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', (string)($schema['name'] ?? $mode))));
        $base = trim($base, '-') ?: 'mod';
        $schema['key'] = substr($base, 0, 24) . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
    }
    $schema['status'] = 'active';
    $schema['source'] = 'ai_' . $mode;
    $schema['origin'] = 'component-factory';
    $r = blockschema_save($schema);
    return $r;
}

$system = <<<SYS
你是资深前端组件工程师，为一个 PHP 服务端渲染的网站设计「可复用模块 schema」。
输出一个 JSON 对象（只输出 JSON，不要解释）：
{
  "name": "模块中文名（8字内）",
  "key": "英文短横线标识",
  "category": "layout|content|convert|media|social|commerce|other 之一",
  "fields": [...字段定义...],
  "custom_html": "完整 HTML 模板（使用 {{字段key}} 占位符 + {{#repeatkey}}{{/repeatkey}} 循环）"
}
要求：
- custom_html 必须是自包含的完整组件：自带内联布局样式、响应式（可用 @media 的 <style> 标签内嵌）、语义化标签
- 视觉基调：圆角 18-26px、1px 细边框、柔和背景、留白充足；颜色用 var(--accent)/var(--fg)/var(--muted) 等 CSS 变量以适配站点主题
- 字段数量 2-8 个，能参数化的都参数化（标题/文案/图片/列表）
- 禁止引入外部 JS/CSS 依赖；禁止 <script>（交互用纯 CSS 实现，如 details/summary、:checked）
SYS;

$ai = null; $modeInput = '';

switch ($mode) {
    case 'describe':
        $modeInput = trim($_POST['desc'] ?? '');
        if ($modeInput === '') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'请描述你要的模块（例：一个三步流程卡片，每步有图标和说明）']); exit; }
        $system .= "\n\n可用字段类型：" . implode('/', array_keys(blockschema_field_types())) .
                   "\nrepeat 用法示例：{\"key\":\"items\",\"type\":\"repeat\",\"label\":\"条目\",\"children\":[{\"key\":\"t\",\"type\":\"text\",\"label\":\"标题\"},{\"key\":\"d\",\"type\":\"text\",\"label\":\"描述\"}]}，HTML 里 {{#items}}<b>{{t}}</b>{{/items}} 循环。";
        $user = "用户需求：{$modeInput}";
        break;

    case 'html':
        $modeInput = trim($_POST['html'] ?? '');
        if ($modeInput === '') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'请粘贴 HTML 代码']); exit; }
        if (mb_strlen($modeInput) > 60000) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'HTML 太长（限 6 万字符）']); exit; }
        $system .= "\n\n用户会粘贴一段 HTML：识别其中的视觉结构与重复模式，把固定内容抽成字段（title/text/image/列表），保持版式还原。";
        $user = "把下面的 HTML 抽象成一个可复用模块 schema（内容参数化，样式保持）：\n```html\n" . $modeInput . "\n";
        break;

    case 'url':
        $modeInput = trim($_POST['url'] ?? '');
        if (!preg_match('#^https?://#i', $modeInput)) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'请输入有效的网址']); exit; }
        // 服务端抓取（10s 超时），只取 HTML 主体
        $ch = curl_init($modeInput);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OpenFlowBot/1.0)']);
        $fetched = (string)curl_exec($ch);
        curl_close($ch);
        if ($fetched === '') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'抓取失败（网站不可达或拒绝访问）']); exit; }
        // 只留 <body> 主体、剥 script/style，控制在 4 万字符内
        if (preg_match('#<body[^>]*>(.*)</body>#is', $fetched, $m)) $fetched = $m[1];
        $fetched = preg_replace('#<(script|style|svg|noscript)[^>]*>.*?</\1>#is', '', $fetched);
        $fetched = preg_replace('#\s+#', ' ', $fetched);
        $fetched = mb_substr($fetched, 0, 40000);
        $system .= "\n\n重要版权要求：只学习版式结构（布局/层级/组件形态），文案全部替换为通用占位内容（如「产品标题」「功能描述」），不复制原站受版权保护的文字与图片。";
        $user = "从下面这个网页片段提取一个值得复用的版式模式，做成模块 schema（内容用占位文案）：\n" . $fetched;
        break;

    case 'screenshot':
        $imgData = $_POST['image'] ?? '';
        if ($imgData === '' && !empty($_FILES['image']['tmp_name'])) {
            $bin = @file_get_contents($_FILES['image']['tmp_name']);
            $imgData = $bin !== false ? 'data:' . (mime_content_type($_FILES['image']['tmp_name']) ?: 'image/png') . ';base64,' . base64_encode($bin) : '';
        }
        if ($imgData === '') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'请上传截图（png/jpg/webp）']); exit; }
        if (strlen($imgData) > 8_000_000) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'图片过大（限 8MB）']); exit; }
        $user = "这是目标界面的截图。请把它还原成一个可复用模块 schema：布局、层级、卡片样式尽量还原，文字用 {{字段}} 占位。";
        $ai = AiCenter::chat($system, $user, [
            'max_tokens' => 4000, 'feature' => 'ai_block_screenshot', 'tier' => 'admin',
            'json' => true, 'image_dataurl' => $imgData,
        ]);
        break;

    default:
        http_response_code(422); echo json_encode(['ok'=>false,'error'=>'未知模式']); exit;
}

// 非 screenshot 模式现在才调 AI
if ($ai === null) {
    $ai = AiCenter::chat($system, $user, ['max_tokens' => 4000, 'feature' => 'ai_block_' . $mode, 'tier' => 'admin', 'json' => true]);
}

if (empty($ai['ok']) || ($ai['text'] ?? '') === '') {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$ai['error'] ?? 'AI 生成失败'], JSON_UNESCAPED_UNICODE); exit;
}

$schema = AiCenter::extractJson((string)$ai['text']);
$persist = aiblock_persist($schema, $mode);
if (empty($persist['ok'])) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'模块校验失败','errors'=>$persist['errors'] ?? []], JSON_UNESCAPED_UNICODE); exit;
}

// 校验产物：type 表里能不能认出来 + 渲染冒烟
$saved = $persist['schema'];
try {
    $html = builder_render_block(['_type' => $saved['key'], '_key' => 'smoke', 'title' => '示例标题', 'subtitle' => '示例副标题', 'content' => '示例内容']);
} catch (\Throwable $e) { $html = ''; }

echo json_encode([
    'ok' => true,
    'message' => '模块已创建，建站面板里可直接插入',
    'module' => $saved,
    'smoke_render_ok' => $html !== '' && stripos($html, 'empty') === false,
    'palette_label' => block_type_label($saved['key']),
], JSON_UNESCAPED_UNICODE);
