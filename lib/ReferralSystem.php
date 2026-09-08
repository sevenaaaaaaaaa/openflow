<?php
/**
 * 推荐码体系 — B1 商业化核心
 *
 * 能力：推荐码生成 · 点击埋点归因 · 注册/成交自动归因 · 佣金结算 · 提现申请
 * 存储：JSON 文件（referral/codes.json + referrals.json + settlements.json）
 * 依赖：ShopSystem（订单归因）、MembershipSystem（分销商等级）、CouponSystem（优惠码）
 */

function ref_codes_file(): string { return DATA_DIR . '/referral/codes.json'; }
function ref_referrals_file(): string { return DATA_DIR . '/referral/referrals.json'; }
function ref_settlements_file(): string { return DATA_DIR . '/referral/settlements.json'; }
function ref_settings_file(): string { return DATA_DIR . '/referral/settings.json'; }

// ─── 设置 ───
function ref_settings(): array {
    return array_merge([
        'enabled' => true,
        'commission_rate' => 0.10,       // 默认佣金比例 10%
        'settlement_threshold' => 100,    // 提现门槛 100 元
        'settlement_currency' => 'CNY',
        'cookie_days' => 30,              // 推荐 cookie 有效期
    ], json_read(ref_settings_file()));
}

// ─── 推荐码管理 ───

/**
 * 为分销商生成推荐码
 * @param string $memberId   分销商会员ID
 * @param string $name       推荐码名称（可选）
 */
function ref_create_code(string $memberId, string $name = ''): array {
    $codes = json_read(ref_codes_file());
    // 每人最多5个推荐码
    $memberCodes = array_filter($codes, fn($c) => ($c['member_id'] ?? '') === $memberId);
    if (count($memberCodes) >= 5) return ['ok' => false, 'error' => '每人最多5个推荐码'];

    $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $entry = [
        'id' => 'ref_' . bin2hex(random_bytes(6)),
        'member_id' => $memberId,
        'code' => $code,
        'name' => $name ?: ('推荐码-' . $code),
        'url' => '', // 延迟生成
        'enabled' => true,
        'clicks' => 0,
        'conversions' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $settings = ref_settings();
    $entry['url'] = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'nownexts.com') . '?ref=' . $code;
    $codes[] = $entry;
    if (!is_dir(dirname(ref_codes_file()))) @mkdir(dirname(ref_codes_file()), 0755, true);
    json_write(ref_codes_file(), $codes);
    return ['ok' => true, 'code' => $entry];
}

/**
 * 获取分销商的所有推荐码
 */
function ref_codes_for(string $memberId): array {
    return array_values(array_filter(json_read(ref_codes_file()), fn($c) => ($c['member_id'] ?? '') === $memberId));
}

/**
 * 通过推荐码查找
 */
function ref_find_code(string $code): ?array {
    $code = strtoupper(trim($code));
    foreach (json_read(ref_codes_file()) as $c) {
        if (($c['code'] ?? '') === $code && !empty($c['enabled'])) return $c;
    }
    return null;
}

// ─── 点击归因 ───

/**
 * 记录推荐点击（前端 JS / 页面加载时调用）
 */
function ref_track_click(string $code, array $extra = []): array {
    $ref = ref_find_code($code);
    if (!$ref) return ['ok' => false, 'error' => '推荐码无效'];
    if (empty($ref['enabled'])) return ['ok' => false, 'error' => '推荐码已禁用'];

    // 更新点击计数
    $codes = json_read(ref_codes_file());
    foreach ($codes as &$c) {
        if (($c['code'] ?? '') === strtoupper($code)) {
            $c['clicks'] = ($c['clicks'] ?? 0) + 1;
            break;
        }
    }
    unset($c);
    json_write(ref_codes_file(), $codes);

    // 设置推荐 cookie（浏览器端 30 天）
    if (!headers_sent()) {
        $days = ref_settings()['cookie_days'] ?? 30;
        setcookie('of_ref', $code, time() + 86400 * $days, '/', '', true, false);
    }

    // 记录点击事件
    $referrals = json_read(ref_referrals_file());
    $referrals[] = [
        'id' => 'ref_click_' . bin2hex(random_bytes(6)),
        'code' => $code,
        'member_id' => $ref['member_id'],
        'type' => 'click',
        'visitor_id' => $_COOKIE['cdp_vid'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if (!is_dir(dirname(ref_referrals_file()))) @mkdir(dirname(ref_referrals_file()), 0755, true);
    json_write(ref_referrals_file(), $referrals);
    return ['ok' => true, 'member_id' => $ref['member_id']];
}

/**
 * 获取当前推荐码（从 cookie）
 */
function ref_current_code(): string {
    return $_COOKIE['of_ref'] ?? '';
}

// ─── 注册归因 ───

/**
 * 用户注册时，归因到推荐人
 */
function ref_attribute_registration(string $newMemberId, string $code = ''): array {
    if (empty($code)) $code = ref_current_code();
    if (empty($code)) return ['ok' => false, 'reason' => '无推荐码'];

    $ref = ref_find_code($code);
    if (!$ref) return ['ok' => false, 'reason' => '推荐码无效'];

    $referrals = json_read(ref_referrals_file());
    $referrals[] = [
        'id' => 'ref_reg_' . bin2hex(random_bytes(6)),
        'code' => $code,
        'member_id' => $ref['member_id'],
        'new_member_id' => $newMemberId,
        'type' => 'registration',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_write(ref_referrals_file(), $referrals);
    return ['ok' => true, 'referrer' => $ref['member_id']];
}

// ─── 成交归因 + 佣金计算 ───

/**
 * 订单成交时，自动归因到推荐人 + 计算佣金
 */
function ref_attribute_order(string $orderId, float $amount, string $code = ''): array {
    if (empty($code)) $code = ref_current_code();
    if (empty($code)) return ['ok' => false, 'reason' => '无推荐码'];

    $ref = ref_find_code($code);
    if (!$ref) return ['ok' => false, 'reason' => '推荐码无效'];

    $settings = ref_settings();
    $rate = $ref['commission_rate'] ?? $settings['commission_rate'];
    $commission = round($amount * $rate, 2);
    if ($commission <= 0) return ['ok' => false, 'reason' => '佣金金额异常'];

    // 更新推荐码转化计数
    $codes = json_read(ref_codes_file());
    foreach ($codes as &$c) {
        if (($c['code'] ?? '') === strtoupper($code)) {
            $c['conversions'] = ($c['conversions'] ?? 0) + 1;
            break;
        }
    }
    unset($c);
    json_write(ref_codes_file(), $codes);

    // 记录成交归因
    $referrals = json_read(ref_referrals_file());
    $referrals[] = [
        'id' => 'ref_order_' . bin2hex(random_bytes(6)),
        'code' => $code,
        'member_id' => $ref['member_id'],
        'order_id' => $orderId,
        'type' => 'conversion',
        'amount' => $amount,
        'commission' => $commission,
        'status' => 'pending', // pending → settled → paid
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_write(ref_referrals_file(), $referrals);

    return ['ok' => true, 'commission' => $commission, 'referrer' => $ref['member_id']];
}

// ─── 佣金结算 ───

/**
 * 结算分销商佣金（累计待结算 → 可提现）
 */
function ref_settle(string $memberId): array {
    $referrals = json_read(ref_referrals_file());
    $pending = [];
    foreach ($referrals as $r) {
        if (($r['member_id'] ?? '') === $memberId && ($r['status'] ?? '') === 'pending') {
            $pending[] = $r;
        }
    }
    if (empty($pending)) return ['ok' => false, 'error' => '无待结算佣金'];

    $total = array_sum(array_column($pending, 'commission'));
    // 更新为 settled
    foreach ($referrals as &$r) {
        if (($r['member_id'] ?? '') === $memberId && ($r['status'] ?? '') === 'pending') {
            $r['status'] = 'settled';
            $r['settled_at'] = date('Y-m-d H:i:s');
        }
    }
    unset($r);
    json_write(ref_referrals_file(), $referrals);

    // 记录结算单
    $settlements = json_read(ref_settlements_file());
    $settlements[] = [
        'id' => 'settle_' . bin2hex(random_bytes(6)),
        'member_id' => $memberId,
        'amount' => $total,
        'items' => count($pending),
        'status' => 'settled',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if (!is_dir(dirname(ref_settlements_file()))) @mkdir(dirname(ref_settlements_file()), 0755, true);
    json_write(ref_settlements_file(), $settlements);

    return ['ok' => true, 'settled' => $total, 'items' => count($pending)];
}

/**
 * 提现申请
 */
function ref_request_payout(string $memberId, float $amount, string $method = '', string $account = ''): array {
    $settings = ref_settings();
    $threshold = $settings['settlement_threshold'] ?? 100;

    // 检查可提现余额
    $balance = ref_balance($memberId);
    if ($balance < $amount) return ['ok' => false, 'error' => '余额不足（可提现: ¥' . $balance . '）'];
    if ($amount < $threshold) return ['ok' => false, 'error' => '最低提现 ¥' . $threshold];

    $settlements = json_read(ref_settlements_file());
    $settlements[] = [
        'id' => 'payout_' . bin2hex(random_bytes(6)),
        'member_id' => $memberId,
        'amount' => $amount,
        'method' => $method ?: 'manual',
        'account' => $account,
        'status' => 'requested', // requested → approved → paid
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_write(ref_settlements_file(), $settlements);
    return ['ok' => true, 'payout_id' => $settlements[count($settlements) - 1]['id']];
}

// ─── 查询 ───

function ref_balance(string $memberId): float {
    $settled = 0; $payouts = 0;
    foreach (json_read(ref_settlements_file()) as $s) {
        if (($s['member_id'] ?? '') !== $memberId) continue;
        $st = $s['status'] ?? '';
        if ($st === 'settled') $settled += (float)$s['amount'];
        // requested/approved 都算已扣减
        if (in_array($st, ['requested','approved','paid'])) $payouts += (float)$s['amount'];
    }
    return round(max(0, $settled - $payouts), 2);
}

function ref_stats(string $memberId): array {
    $codes = ref_codes_for($memberId);
    $totalClicks = array_sum(array_column($codes, 'clicks'));
    $totalConversions = array_sum(array_column($codes, 'conversions'));
    $pending = 0;
    foreach (json_read(ref_referrals_file()) as $r) {
        if (($r['member_id'] ?? '') === $memberId && ($r['status'] ?? '') === 'pending') {
            $pending += (float)($r['commission'] ?? 0);
        }
    }
    return [
        'codes' => count($codes),
        'clicks' => $totalClicks,
        'conversions' => $totalConversions,
        'pending_commission' => round($pending, 2),
        'settled_balance' => ref_balance($memberId),
    ];
}

function ref_settlements_for(string $memberId): array {
    return array_values(array_filter(json_read(ref_settlements_file()), fn($s) => ($s['member_id'] ?? '') === $memberId));
}
