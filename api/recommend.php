<?php
/**
 * 个性化推荐 API
 * GET ?type=articles     → 推荐文章
 * GET ?type=related&id=x → 相关文章
 * GET ?type=cta          → 个性化 CTA
 * 自动识别当前访问者（cookie + 会员）
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/Personalizer.php';
header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? 'articles';
$limit = max(1, min(20, (int)($_GET['limit'] ?? 6)));

$visitorId = $_COOKIE['fc_uid'] ?? '';
$memberId = $_COOKIE['member_id'] ?? '';
$email = $_COOKIE['member_email'] ?? ($_SESSION['member_email'] ?? '');
$pref = Personalizer::buildProfile($visitorId, $memberId, $email);

if ($type === 'articles') {
    $recs = Personalizer::recommendArticles($pref, $limit, $_GET['exclude'] ?? '');
    $out = [];
    foreach ($recs as $id => $score) {
        $a = get_article($id);
        if (!$a) continue;
        $out[] = [
            'id' => $id, 'title' => $a['title'], 'slug' => $a['slug'] ?? '',
            'excerpt' => $a['excerpt'] ?? '', 'category' => $a['category'] ?? '',
            'tags' => $a['tags'] ?? [], 'score' => $score,
            'url' => '/article/' . ($a['slug'] ?? $id),
        ];
    }
    echo json_encode(['ok' => true, 'recommendations' => $out], JSON_UNESCAPED_UNICODE);

} elseif ($type === 'related') {
    $id = $_GET['id'] ?? '';
    $rel = Personalizer::relatedArticles($id, $pref, $limit);
    $out = [];
    foreach ($rel as $a) {
        $out[] = [
            'id' => $a['id'], 'title' => $a['title'], 'slug' => $a['slug'] ?? '',
            'excerpt' => $a['excerpt'] ?? '', 'category' => $a['category'] ?? '',
            'url' => '/article/' . ($a['slug'] ?? $a['id']),
        ];
    }
    echo json_encode(['ok' => true, 'related' => $out], JSON_UNESCAPED_UNICODE);

} elseif ($type === 'cta') {
    $cta = Personalizer::personalizedCta($pref);
    echo json_encode(['ok' => true] + $cta, JSON_UNESCAPED_UNICODE);

} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '未知 type']);
}
