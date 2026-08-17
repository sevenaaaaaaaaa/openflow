<?php
/**
 * 开发者生态 API — 入驻 / 提交产品 / 我的产品 / 删除
 *
 * 流程：注册用户 → 申请成为开发者（审核）→ 提交 Skill/主题（待审核）→ 管理员审核 → 上架市场
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/SkillSystem.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$member = member_current();

if ($action !== 'public_status' && !$member) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '请先登录'], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {
    // ─── 申请成为开发者 ───
    case 'apply_developer':
        $bio = trim($_POST['bio'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $website = trim($_POST['website'] ?? '');
        if (mb_strlen($bio) < 10) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'开发者简介至少 10 字']); exit; }
        $member['developer_status'] = 'pending';
        $member['developer_bio'] = $bio;
        $member['developer_skills'] = $skills;
        $member['developer_website'] = $website;
        $member['developer_applied_at'] = date('Y-m-d H:i:s');
        member_save($member);
        try { notify('developer', '开发者入驻申请', ($member['name'] ?? '') . ' 申请成为开发者', '/xmp/developers'); } catch (Throwable $e) {}
        echo json_encode(['ok' => true, 'message' => '申请已提交，管理员审核通过后即可上传产品'], JSON_UNESCAPED_UNICODE);
        break;

    // ─── 提交 Skill（待审核） ───
    case 'submit_skill':
        if (($member['developer_status'] ?? '') !== 'approved') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => '需先通过开发者审核才能提交产品'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['type'] ?? 'prompt');
        $content = trim($_POST['content'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));
        if ($title === '' || $content === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => '标题和内容为必填'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $types = skill_types();
        if (!isset($types[$type])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'无效的类型']); exit; }
        $id = 'skill_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($title)) . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
        skill_publish([
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'content' => $content,
            'tags' => $tags,
            'author' => $member['name'] ?? '开发者',
            'author_type' => 'developer',
            'icon' => trim($_POST['icon'] ?? '⚡'),
            'status' => 'pending',
            'submitted_by' => $member['id'],
            'submitted_at' => date('Y-m-d H:i:s'),
            // 分销配置（一级分销）
            'distribution_enabled' => !empty($_POST['distribution_enabled']),
            'distributor_rate' => min(80, max(5, (float)($_POST['distributor_rate'] ?? 30))),
            'price' => max(0, (float)($_POST['price'] ?? 0)),
        ]);
        try { notify('developer', '新产品待审核', $title . '（' . ($member['name'] ?? '') . ' 提交）', '/xmp/marketplace?status=pending'); } catch (Throwable $e) {}
        echo json_encode(['ok' => true, 'message' => '产品已提交，等待审核。审核通过后自动上架到市场'], JSON_UNESCAPED_UNICODE);
        break;

    // ─── 我的产品 ───
    case 'my_products':
        $mine = skill_by_author($member['id']);
        $list = array_map(fn($s) => [
            'id' => $s['id'], 'title' => $s['title'], 'type' => $s['type'],
            'status' => $s['status'] ?? 'draft', 'installs' => $s['installs'] ?? 0,
            'rating' => $s['rating'] ?? 0, 'submitted_at' => $s['submitted_at'] ?? '',
        ], $mine);
        echo json_encode(['ok' => true, 'products' => $list], JSON_UNESCAPED_UNICODE);
        break;

    // ─── 删除我的产品（仅未发布/被拒） ───
    case 'delete_product':
        $id = trim($_POST['id'] ?? '');
        $s = skill_get($id);
        if (!$s || ($s['submitted_by'] ?? '') !== $member['id']) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'无权操作']); exit; }
        if (($s['status'] ?? '') === 'published') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'已上架产品不能删除']); exit; }
        skill_delete($id);
        echo json_encode(['ok' => true, 'message' => '已删除']);
        break;

    // ─── 我的开发者状态 ───
    case 'public_status':
        $status = $member ? ($member['developer_status'] ?? 'none') : 'none';
        echo json_encode(['ok' => true, 'status' => $status, 'bio' => $member['developer_bio'] ?? '']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '未知操作'], JSON_UNESCAPED_UNICODE);
}
