<?php
/**
 * 推荐大使 API — 申请成为大使 + 记录点击
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/MemberSystem.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// 记录推广点击（无需登录，通过 ref code）
if ($action === 'click') {
    $code = trim($_GET['code'] ?? '');
    if ($code) {
        foreach (member_get_all() as &$m) {
            if (($m['referral_code'] ?? '') === $code) {
                $m['ambassador_stats'] = ['clicks' => ($m['ambassador_stats']['clicks'] ?? 0) + 1, 'orders' => ($m['ambassador_stats']['orders'] ?? 0)];
                break;
            }
        }
        unset($m);
        json_write(member_file(), member_get_all());
    }
    echo json_encode(['ok'=>true]);
    exit;
}

$member = member_current();
if (!$member) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'请先登录']); exit; }

switch ($action) {
    // ─── 申请成为大使 ───
    case 'apply':
        $member['ambassador'] = true;
        if (empty($member['referral_code'])) $member['referral_code'] = 'of' . substr(bin2hex(random_bytes(4)), 0, 8);
        member_save($member);
        notify('分销', $member['name'] . ' 申请成为推荐大使', '', 'admin/distribution.php');
        echo json_encode(['ok'=>true, 'message'=>'恭喜，你已成为推荐大使！']);
        break;

    // ─── 申请提现 ───
    case 'withdraw':
        $amount = (float)($_POST['amount'] ?? 0);
        $balance = $member['balance'] ?? 0;
        $s = shop_settings();
        if ($amount <= 0 || $amount > $balance) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'提现金额无效']); exit; }
        if ($amount < ($s['min_withdraw'] ?? 100)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'低于最低提现金额']); exit; }
        // 收款账户
        $payMethod = trim($_POST['pay_method'] ?? '');
        $payAccount = trim($_POST['pay_account'] ?? '');
        if (!in_array($payMethod, ['wechat','alipay','bank'], true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请选择收款方式']); exit; }
        if ($payAccount === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'请填写收款账户']); exit; }
        $member['balance'] = round($balance - $amount, 2);
        member_save($member);
        $withdrawals = json_read(DATA_DIR . '/shop/withdrawals.json');
        $withdrawals[] = ['id'=>'w'.date('YmdHis').substr(bin2hex(random_bytes(3)),0,5),'member_id'=>$member['id'],'member_name'=>$member['name'],'amount'=>$amount,'pay_method'=>$payMethod,'pay_account'=>$payAccount,'status'=>'pending','created_at'=>date('Y-m-d H:i:s')];
        json_write(DATA_DIR . '/shop/withdrawals.json', $withdrawals);
        notify('分销', $member['name'] . ' 申请提现 ¥' . $amount, '', 'admin/distribution.php');
        echo json_encode(['ok'=>true, 'message'=>'提现申请已提交，审核通过后打款']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'未知操作']);
}
