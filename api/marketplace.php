<?php
/**
 * 生态市场 API — 安装技能 / 打分
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/SkillSystem.php';
require_once __DIR__ . '/../lib/SkillGenerator.php';
require_once __DIR__ . '/../lib/MemberSystem.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$member = member_current();

// 插件生成：管理员后台会话可用（免会员登录）
if ($action === 'ai_plugin') {
    if (!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true) {
        http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit;
    }
} else {
    if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }
}

switch ($action) {
    // 安装技能（用户"收藏/启用"到自己的技能库）
    case 'install':
        $id = trim($_POST['skill_id'] ?? '');
        $s = skill_get($id);
        if (!$s) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Skill 不存在']); exit; }
        // 付费技能校验：必须已购买才能安装
        $price = (float)($s['price'] ?? 0);
        if ($price > 0) {
            $purchased = $member['purchased_skills'] ?? [];
            if (!in_array($id, $purchased)) {
                echo json_encode(['ok'=>false,'error'=>'该技能为付费技能，请先购买','need_purchase'=>true,'skill_id'=>$id,'price'=>$price]); exit;
            }
        }
        skill_install_hit($id);
        // 记录到用户技能库
        $m = $member;
        $installed = $m['installed_skills'] ?? [];
        if (!in_array($id, $installed)) {
            $installed[] = $id;
            member_save(['id' => $member['id'], 'installed_skills' => $installed]);
        }
        echo json_encode(['ok'=>true, 'installed'=>true], JSON_UNESCAPED_UNICODE);
        break;

    // 购买付费技能/会员（走数字商品系统 + 虎皮椒支付 + 自动交付）
    case 'purchase':
        $id = trim($_POST['skill_id'] ?? ($_POST['id'] ?? ''));
        require_once __DIR__ . '/../lib/CommerceSystem.php';
        $product = null;
        $s = null;
        // 优先按 skill 找
        $s = skill_get($id);
        if ($s) {
            foreach (CommerceSystem::products() as $p) {
                if ($p['type'] === 'skill' && $p['asset_id'] === $id && $p['status'] === 'published') { $product = $p; break; }
            }
        } else {
            // 会员等非 skill 商品：按 asset_id 找
            foreach (CommerceSystem::products() as $p) {
                if ($p['asset_id'] === $id && $p['status'] === 'published') { $product = $p; break; }
            }
        }
        // 兼容旧逻辑：未发布为商品但有 price 的 skill
        if (!$product && $s) {
            $price = (float)($s['price'] ?? 0);
            if ($price > 0) {
                $product = CommerceSystem::publishSkill($id, ['mode' => 'one_time', 'price' => $price], $s['author'] ?? '', 0.7);
            }
        }
        if (!$product) { echo json_encode(['ok'=>false,'error'=>'该技能未上架或免费']); exit; }
        if (CommerceSystem::owns($member['id'], $product['id'])) { echo json_encode(['ok'=>true,'already_purchased'=>true]); exit; }

        // 创建数字商品订单（一级分销：ref 来自分享链接的分销码）
        $ref = trim($_POST['ref'] ?? ($_GET['ref'] ?? $_COOKIE['of_ref'] ?? ''));
        $r = CommerceSystem::purchase($member['id'], $product['id'], $ref);
        if (!$r['ok'] || empty($r['order'])) { echo json_encode(['ok'=>false,'error'=>$r['error'] ?? '下单失败']); exit; }
        $order = $r['order'];
        if (!empty($order['referrer_id'])) setcookie('of_ref', $order['referrer_id'], time() + 86400 * 30, '/');

        // 虎皮椒支付
        $pay = shop_xfpay_create($order, $member);
        if (!$pay['ok']) { echo json_encode(['ok'=>false,'error'=>$pay['error']]); exit; }
        echo json_encode(['ok'=>true, 'order' => $order, 'payment' => $pay], JSON_UNESCAPED_UNICODE);
        break;

    // 打分
    case 'rate':
        $id = trim($_POST['skill_id'] ?? '');
        $rating = (int)($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'评分需 1-5']); exit; }
        skill_rate($id, $rating);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
        break;

    // AI 生成技能草案
    case 'ai_generate':
        $desc = trim($_POST['description'] ?? '');
        if (empty($desc)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请描述你想创建的能力']); exit; }
        $r = skill_generate($desc, $member['name'] ?? '用户');
        if (!$r['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$r['error']]); exit; }
        echo json_encode(['ok'=>true, 'skill'=>$r['skill']], JSON_UNESCAPED_UNICODE);
        break;

    // AI 生成插件骨架（写文件，需 admin）
    case 'ai_plugin':
        if (($_SESSION['admin_role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'仅管理员可生成插件']); exit; }
        $desc = trim($_POST['description'] ?? '');
        if (empty($desc)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请描述插件功能']); exit; }
        $r = skill_generate_plugin($desc, $_SESSION['admin_name'] ?? 'OpenFlow');
        if (!$r['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$r['error']]); exit; }
        echo json_encode(['ok'=>true, 'plugin_id'=>$r['plugin_id'], 'manifest'=>$r['manifest']], JSON_UNESCAPED_UNICODE);
        break;

    // 执行技能（测试）
    case 'execute':
        $id = trim($_POST['skill_id'] ?? '');
        $r = skill_execute($id, $_POST['params'] ?? []);
        if (!$r['ok']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$r['error']]); exit; }
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
