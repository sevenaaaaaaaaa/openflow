<?php
/**
 * 收货地址 API — 地址簿管理
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';
header('Content-Type: application/json; charset=utf-8');

$member = member_current();
if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$file = DATA_DIR . '/addresses.json';

switch ($action) {
    case 'list':
        $addr = json_read($file);
        $mine = array_values(array_filter((array)$addr, fn($a) => ($a['member_id'] ?? '') === $member['id']));
        echo json_encode(['ok'=>true, 'addresses'=>$mine], JSON_UNESCAPED_UNICODE);
        break;

    case 'save':
        $id = trim($_POST['id'] ?? '');
        $data = [
            'member_id' => $member['id'],
            'name' => trim($_POST['name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'province' => trim($_POST['province'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'district' => trim($_POST['district'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'is_default' => !empty($_POST['is_default']),
        ];
        if ($data['name']==='' || $data['phone']==='' || $data['address']==='') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'请填写姓名/电话/详细地址']); exit; }
        $addr = json_read($file);
        if ($data['is_default']) foreach ($addr as &$a) if (($a['member_id'] ?? '') === $member['id']) $a['is_default'] = false;
        unset($a);
        if ($id !== '') {
            foreach ($addr as &$a) if (($a['id'] ?? '') === $id && ($a['member_id'] ?? '') === $member['id']) { $a = array_merge($a, $data); break; }
            unset($a);
        } else {
            $data['id'] = 'addr' . date('YmdHis') . substr(bin2hex(random_bytes(3)),0,5);
            $data['created_at'] = date('Y-m-d H:i:s');
            $addr[] = $data;
        }
        json_write($file, $addr);
        echo json_encode(['ok'=>true, 'message'=>'地址已保存']);
        break;

    case 'delete':
        $id = trim($_POST['id'] ?? '');
        $addr = array_values(array_filter((array)json_read($file), fn($a) => !(($a['id'] ?? '') === $id && ($a['member_id'] ?? '') === $member['id'])));
        json_write($file, $addr);
        echo json_encode(['ok'=>true, 'message'=>'地址已删除']);
        break;

    default:
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'未知操作']);
}