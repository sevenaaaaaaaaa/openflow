<?php
/**
 * AI 一键生成落地页 — 需求描述 → blocks → 落地页
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AiCenter.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
header('Content-Type: application/json; charset=utf-8');

$member = member_current();
if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

$desc = trim($_POST['desc'] ?? ($_GET['desc'] ?? ''));
if ($desc === '') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'请描述你的落地页需求']); exit; }

if (!AiCenter::isConfigured()) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'AI 未配置，请先在后台 ai-config 配置供应商']); exit;
}

// 用 AI 生成 blocks（结构化 JSON）
$system = "你是资深落地页设计师。根据用户需求，返回一个落地页区块数组（JSON），每个区块含 type 和内容字段。可用 type：hero(标题/副标题/CTA)、features(功能亮点，items数组)、testimonial(用户评价，items数组)、cta(行动号召)、pricing(价格表，items数组)、faq(常见问题，items数组)、form(表单，form_slug)。只输出 JSON 数组，不要解释。";
$user = "需求：{$desc}\n返回 JSON 数组格式：[{\"type\":\"hero\",\"title\":\"...\",\"subtitle\":\"...\",\"button_text\":\"...\"},...]";

$r = AiCenter::chat($system, $user, ['max_tokens' => 2500, 'feature' => 'ai_landing', 'tier' => 'admin']);
// AiCenter::chat() 返回的键是 text，没有 content——原来读 $r['content'] 永远为空，
// 也就是说这个接口一直只会返回「AI 生成失败」，从没成功过。
$aiText = (string)($r['text'] ?? '');
if (empty($r['ok']) || $aiText === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $r['error'] ?? 'AI 生成失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

$blocks = AiCenter::extractJson($aiText);
if (!is_array($blocks) || empty($blocks)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'AI 返回无法解析的区块']); exit; }

// 规范化 blocks
$allowed = ['hero','features','testimonial','cta','pricing','faq','form'];
$clean = [];
foreach ($blocks as $b) {
    $t = $b['type'] ?? '';
    if (!in_array($t, $allowed, true)) continue;
    $b['id'] = 'blk_' . bin2hex(random_bytes(4));
    $clean[] = $b;
}
if (empty($clean)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'AI 未生成有效区块']); exit; }

// 标题从需求提取
$title = trim($_POST['title'] ?? '');
if ($title === '') $title = mb_substr($desc, 0, 20);

// 存落地页
$builderFile = DATA_DIR . '/builder-pages.json';
$pages = json_read($builderFile);
$page = [
    'id' => 'lp_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8),
    'title' => $title,
    'slug' => preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $title) . '_' . substr(bin2hex(random_bytes(2)), 0, 4),
    'seo_title' => $title,
    'seo_desc' => mb_substr($desc, 0, 120),
    'status' => 'draft',
    'is_ad_landing' => true,
    'blocks' => $clean,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
];
$pages[] = $page;
json_write($builderFile, $pages);

echo json_encode(['ok' => true, 'message' => '落地页已生成', 'page_id' => $page['id'], 'blocks' => count($clean), 'edit_url' => '/xmp/page-builder?edit=' . $page['id']], JSON_UNESCAPED_UNICODE);
