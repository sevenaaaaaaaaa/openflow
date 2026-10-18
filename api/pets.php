<?php
/**
 * 桌宠市场 API（松萝 Liana 纸偶形象）
 *
 *   GET  /api/pets                       公开目录（松萝「形象源」format 1，只含已上架）
 *   GET  /api/pets?action=preview&id=    预览图（已上架）
 *   GET  /api/pets?action=download&id=   下载 .lianapet（付费需已购买；计下载量）
 *   POST /api/pets?action=submit         投稿（认证开发者 + CSRF；multipart：file/price/license/description/tags）
 *   GET  /api/pets?action=mine           我的投稿
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/CommerceSystem.php';
require_once __DIR__ . '/../lib/PetMarket.php';

$action = (string) ($_GET['action'] ?? ($_POST['action'] ?? 'index'));

function pets_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

switch ($action) {
    case 'index':
        header('Cache-Control: public, max-age=300');
        pets_json(pet_index());

    case 'preview': {
        $it = pet_find((string) ($_GET['id'] ?? ''));
        $file = $it ? pet_preview_path($it) : '';
        if ($file === '' || !is_file($file)) { http_response_code(404); exit; }
        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    case 'download': {
        $it = pet_find((string) ($_GET['id'] ?? ''));
        if (!$it || !is_file(pet_file_path($it))) pets_json(['ok' => false, 'error' => '形象不存在或已下架'], 404);
        $member = member_current();
        if (!pet_can_download($it, $member)) {
            // 付费形象：浏览器里跳详情页购买，程序调用给 JSON
            if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html')) {
                header('Location: /pet/' . rawurlencode((string) $it['pack_id']));
                exit;
            }
            pets_json(['ok' => false, 'error' => '这是付费形象，请先购买', 'page' => '/pet/' . $it['pack_id']], 402);
        }
        pet_hit_download((string) $it['sub_id']);
        $file = pet_file_path($it);
        header('Content-Type: application/zip');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $it['pack_id'] . '.lianapet"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, no-store');
        readfile($file);
        exit;
    }

    case 'submit': {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') pets_json(['ok' => false, 'error' => '请用 POST'], 405);
        $member = member_current();
        if (!$member) pets_json(['ok' => false, 'error' => '请先登录'], 401);
        csrf_verify();
        if (($member['developer_status'] ?? '') !== 'approved') {
            pets_json(['ok' => false, 'error' => '需先通过开发者认证才能发布桌宠'], 403);
        }
        $up = $_FILES['file'] ?? null;
        if (!is_array($up) || ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $up['tmp_name'])) {
            $err = (int) ($up['error'] ?? UPLOAD_ERR_NO_FILE);
            pets_json(['ok' => false, 'error' => in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? '文件太大' : '请选择 .lianapet 文件'], 422);
        }
        if (strtolower(pathinfo((string) $up['name'], PATHINFO_EXTENSION)) !== 'lianapet') {
            pets_json(['ok' => false, 'error' => '只接受 .lianapet 文件'], 422);
        }
        $r = pet_submit($member, (string) $up['tmp_name'], [
            'price' => $_POST['price'] ?? 0,
            'license' => (string) ($_POST['license'] ?? 'CC-BY-4.0'),
            'description' => (string) ($_POST['description'] ?? ''),
            'tags' => (string) ($_POST['tags'] ?? ''),
        ]);
        if (!$r['ok']) pets_json(['ok' => false, 'error' => $r['error'] ?? '提交失败'], 422);
        try { notify('developer', '新桌宠待审核', ($member['name'] ?? '') . ' 提交了 ' . $r['pack_id'], '/xmp/pets'); } catch (Throwable $e) {}
        pets_json(['ok' => true, 'sub_id' => $r['sub_id'], 'message' => '已提交，审核通过后会出现在生态市场和松萝的官方形象源里']);
    }

    case 'mine': {
        $member = member_current();
        if (!$member) pets_json(['ok' => false, 'error' => '请先登录'], 401);
        $list = array_map(fn($it) => [
            'pack_id' => $it['pack_id'], 'name' => $it['name'], 'revision' => (int) ($it['revision'] ?? 1),
            'status' => $it['status'], 'review_note' => $it['review_note'] ?? '', 'price' => (float) ($it['price'] ?? 0),
            'downloads' => (int) ($it['downloads'] ?? 0), 'created_at' => $it['created_at'] ?? '',
        ], pet_by_author((string) $member['id']));
        pets_json(['ok' => true, 'items' => $list]);
    }

    default:
        pets_json(['ok' => false, 'error' => '未知操作'], 400);
}
