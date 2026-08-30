<?php
/**
 * 描述即造 API（需登录会员 = 参与者）
 *   POST action=build description=...  → 生成 + 三道护栏审查，返回草稿或拦截原因
 *
 * 永不自动上架：只返回草稿供人确认。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/SkillSystem.php';
require_once __DIR__ . '/../lib/SkillGenerator.php';
require_once __DIR__ . '/../lib/SkillGuard.php';
require_once __DIR__ . '/../lib/BuilderWorkspace.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$member = function_exists('member_current') ? member_current() : null;
if (!builder_can_contribute($member)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => '请先登录']);
    exit;
}

$desc = trim((string)($_POST['description'] ?? ''));
$r = skillguard_build($desc, (string)($member['id'] ?? ''));

// 通过审查且用户选择保存 → 存为草稿（仍需人工发布）
if (!empty($r['ok']) && ($_POST['save'] ?? '') === '1' && function_exists('skill_publish')) {
    try {
        skill_publish(array_merge($r['skill'], [
            'submitter' => (string)($member['id'] ?? ''),
            'status' => 'draft',
        ]));
        $r['saved'] = true;
    } catch (\Throwable $e) { $r['saved'] = false; }
}

echo json_encode($r, JSON_UNESCAPED_UNICODE);
