<?php
/**
 * 会员体系 — 全站统一的权益模型
 *
 * 把散落在各模块的"付费/免费"权益聚合到一套会员模型：
 *  - 会员等级（会员体系）：免费用户 / 普通会员 / VIP 会员（通过积分/订阅/购买升级）
 *  - 权益清单：文章、资料下载、课程、订阅邮件、直播、1v1 咨询、社区、等级虚拟权益
 *  - 统一鉴权入口 member_entitlements() / member_can()
 *
 * 数据来源：
 *  - 订阅状态   SubscriptionSystem (sub_get_member / sub_is_active)
 *  - 课程订单   ShopSystem (shop_orders_file)
 *  - 咨询预约   ConsultationSystem (con_bookings)
 *  - 积分等级   Gamification (gamification_level_of)
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/SubscriptionSystem.php';
require_once __DIR__ . '/ShopSystem.php';
require_once __DIR__ . '/Gamification.php';
require_once __DIR__ . '/ConsultationSystem.php';

function mem_plans_file(): string { return DATA_DIR . '/membership/plans.json'; }
function mem_state_file(): string { return DATA_DIR . '/membership/state.json'; }

// ─── 会员计划（后台可配置）───
function mem_plans(): array {
    return array_merge([
        ['id' => 'free', 'name' => '免费用户', 'icon' => '👤', 'price' => 0, 'period' => '', 'benefits' => ['阅读公开文章', '社区发帖与评论', '基础积分等级']],
        ['id' => 'annual', 'name' => '年度会员', 'icon' => '⭐', 'price' => 299, 'period' => 'year', 'quota_per_day' => 5, 'benefits' => ['365 天有效期', '每天免费下单 5 个任意商品', '商品仅限本人账号使用，禁止二次开发/打包/魔改']],
        ['id' => 'lifetime', 'name' => '永久会员', 'icon' => '👑', 'price' => 599, 'period' => 'lifetime', 'quota_per_day' => 10, 'benefits' => ['永久有效', '每天下载 10 个任意商品', '商品仅限本人账号使用，禁止二次开发/打包/魔改']],
    ], json_read(mem_plans_file()));
}

// 会员体系状态（member_id => 手动标记的会员等级，如管理员授予）
function mem_state(): array { return json_read(mem_state_file()); }
function mem_save_state(array $s): void {
    if (!is_dir(dirname(mem_state_file()))) mkdir(dirname(mem_state_file()), 0755, true);
    json_write(mem_state_file(), $s);
}

// ─── 核心：聚合某会员的全部权益 ───
function member_entitlements(?array $member): array {
    if (!$member) return ['tier' => 'guest', 'tier_name' => '游客', 'icon' => '👋', 'benefits' => [], 'points' => 0, 'level' => null, 'subscription' => false, 'owned_courses' => [], 'consultation_used' => 0, 'tags' => []];

    $id = $member['id'];
    $points = (int)($member['points'] ?? 0);
    $level = gamification_level_of($points);
    $subActive = sub_is_active($id);

    // 已购课程
    $ownedCourses = [];
    foreach (json_read(shop_orders_file()) as $o) {
        if (($o['member_id'] ?? '') === $id && ($o['status'] ?? '') === 'paid' && !empty($o['course_id']) && strpos($o['course_id'], 'subscription:') !== 0) {
            $ownedCourses[] = $o['course_id'];
        }
    }

    // 咨询使用情况
    $consultationUsed = 0;
    foreach (con_bookings() as $b) {
        if (($b['member_id'] ?? '') === $id && in_array($b['status'] ?? '', ['paid', 'confirmed', 'completed'])) $consultationUsed++;
    }

    // 手动授予等级 + 订阅叠加
    $st = mem_state();
    $grantedTier = $st[$id]['tier'] ?? '';
    if ($subActive && ($grantedTier === 'member' || $grantedTier === '')) $grantedTier = 'member';
    if ($grantedTier === 'vip') $subActive = true;

    // 商品会员计划（年度/永久）→ 计入会员身份
    $shopPlan = member_shop_plan($member);
    if ($shopPlan) {
        // 永久会员 = VIP 级，年度会员 = 普通会员级
        $grantedTier = ($shopPlan['id'] === 'lifetime') ? 'vip' : 'member';
    }

    // 计算等级
    $plans = mem_plans();
    $planMap = []; foreach ($plans as $p) $planMap[$p['id']] = $p;
    if ($grantedTier === 'vip') { $tier = 'vip'; }
    elseif ($grantedTier === 'member' || $subActive) { $tier = 'member'; }
    elseif ($points >= 1000) { $tier = 'member'; } // 高积分自动升级
    else { $tier = 'free'; }

    // tier 名称：优先显示商品会员名（年度/永久会员），否则用默认名称
    if ($shopPlan) {
        $tierName = $shopPlan['name'] ?? ($tier === 'free' ? '免费用户' : '会员');
        $tierIcon = $shopPlan['icon'] ?? '👑';
    } else {
        $plan = $planMap[$tier] ?? $planMap['free'];
        $tierName = $plan['name'] ?? ($tier === 'free' ? '免费用户' : '会员');
        $tierIcon = $plan['icon'] ?? '👤';
    }

    return [
        'tier' => $tier,
        'tier_name' => $tierName,
        'icon' => $tierIcon,
        'benefits' => ($shopPlan['benefits'] ?? ($plan['benefits'] ?? [])),
        'points' => $points,
        'level' => $level,
        'subscription' => $subActive,
        'owned_courses' => array_values(array_unique($ownedCourses)),
        'consultation_used' => $consultationUsed,
        'granted_tier' => $grantedTier,
    ];
}

// 统一鉴权：判断会员是否有某权益
function member_can(?array $member, string $entitlement, array $ctx = []): bool {
    $e = member_entitlements($member);
    switch ($entitlement) {
        case 'articles':            return true; // 公开文章
        case 'articles_member':     return in_array($e['tier'], ['member', 'vip']); // 会员专享文章
        case 'downloads':           return in_array($e['tier'], ['member', 'vip']); // 付费资料
        case 'downloads_free':      return true;
        case 'courses':             return $e['subscription'] || in_array($ctx['course_id'] ?? '', $e['owned_courses']) || $e['tier'] === 'vip';
        case 'newsletter':          return in_array($e['tier'], ['member', 'vip']);
        case 'live':                return true; // 直播免费看
        case 'live_replay':         return in_array($e['tier'], ['member', 'vip']);
        case 'consultation':        return true; // 可预约
        case 'consultation_discount': return $e['tier'] === 'vip';
        case 'community_post':      return true; // 登录即可
        case 'community_vip_badge': return in_array($e['tier'], ['member', 'vip']);
        case 'level_virtual':       return true; // 虚拟等级免费
        case 'subscription_email':  return in_array($e['tier'], ['member', 'vip']);
        case 'priority_review':     return $e['tier'] === 'vip';
        default: return false;
    }
}

// 汇总所有权益（给会员中心展示）
function member_benefit_list(?array $member): array {
    $e = member_entitlements($member);
    $lists = [
        '内容' => [
            ['权益' => '公开文章阅读', '状态' => '✅ 免费'],
            ['权益' => '会员专享文章', '状态' => member_can($member, 'articles_member') ? '✅ 已解锁' : '🔒 ' . mem_upgrade_hint('articles_member')],
            ['权益' => '资料下载', '状态' => member_can($member, 'downloads') ? '✅ 已解锁' : '🔒 ' . mem_upgrade_hint('downloads')],
            ['权益' => '订阅邮件 Newsletter', '状态' => member_can($member, 'newsletter') ? '✅ 已解锁' : '🔒 会员权益'],
        ],
        '学习' => [
            ['权益' => '课程购买观看', '状态' => count($e['owned_courses']) ? '✅ 已购 ' . count($e['owned_courses']) . ' 门' : '🛒 按需购买'],
            ['权益' => '会员课程免费看', '状态' => member_can($member, 'courses') ? '✅ VIP 权益' : '🔒 VIP 权益'],
            ['权益' => '直播观看', '状态' => '✅ 免费'],
            ['权益' => '直播回放', '状态' => member_can($member, 'live_replay') ? '✅ 已解锁' : '🔒 会员权益'],
            ['权益' => '1v1 咨询', '状态' => '✅ 可预约（已用 ' . $e['consultation_used'] . ' 次）'],
            ['权益' => '1v1 咨询折扣', '状态' => member_can($member, 'consultation_discount') ? '✅ VIP 85 折' : '🔒 VIP 权益'],
        ],
        '社区与等级' => [
            ['权益' => '社区发帖评论', '状态' => '✅ 免费'],
            ['权益' => '会员徽章', '状态' => member_can($member, 'community_vip_badge') ? '✅ 已点亮' : '🔒 会员权益'],
            ['权益' => '积分等级', '状态' => $e['level']['name'] ?? '—', 'extra' => ($e['points'] ?? 0) . ' 积分'],
            ['权益' => '投稿优先审核', '状态' => member_can($member, 'priority_review') ? '✅ VIP 权益' : '🔒 VIP 权益'],
        ],
    ];
    return $lists;
}

// 升级提示文案
function mem_upgrade_hint(string $ent): string {
    $map = [
        'articles_member' => '开通会员阅读',
        'downloads' => '会员专享',
        'newsletter' => '会员专享',
        'live_replay' => '会员专享',
        'courses' => 'VIP 全通',
        'consultation_discount' => 'VIP 85 折',
        'community_vip_badge' => '会员徽章',
        'priority_review' => 'VIP 优先',
    ];
    return $map[$ent] ?? '会员权益';
}

// ─── 商品会员（年度 5单/天 · 永久 10单/天） ───

// 商品会员状态：返回 plan(id/name/quota_per_day) 或 null
function member_shop_plan(?array $member): ?array {
    if (!$member) return null;
    $planId = $member['membership_plan'] ?? '';
    if (!$planId) return null;
    $plans = mem_plans();
    $plan = null;
    foreach ($plans as $p) if ($p['id'] === $planId) { $plan = $p; break; }
    if (!$plan || ($plan['id'] ?? '') === 'free') return null;
    // 年度会员检查有效期
    if ($plan['period'] === 'year') {
        $expires = $member['membership_expires'] ?? '';
        if (!$expires || strtotime($expires) < time()) return null; // 已过期
    }
    return $plan;
}

// 商品会员每日已用/剩余额度
function member_quota_usage(?array $member): array {
    $plan = member_shop_plan($member);
    $quota = $plan['quota_per_day'] ?? 0;
    if (!$plan || $quota <= 0) return ['plan' => null, 'used' => 0, 'left' => 0, 'daily' => 0];
    $file = DATA_DIR . '/membership/usage.json';
    $usage = json_read($file);
    $today = date('Y-m-d');
    $key = $member['id'];
    $used = (isset($usage[$key]) && $usage[$key]['date'] === $today) ? (int)$usage[$key]['count'] : 0;
    return ['plan' => $plan['id'], 'used' => $used, 'left' => max(0, $quota - $used), 'daily' => $quota];
}

// 消耗一个每日额度（返回是否成功）
function member_quota_consume(?array $member): bool {
    $u = member_quota_usage($member);
    if (($u['left'] ?? 0) <= 0) return false;
    $file = DATA_DIR . '/membership/usage.json';
    $usage = json_read($file);
    $today = date('Y-m-d');
    $key = $member['id'];
    $usage[$key] = ['date' => $today, 'count' => (isset($usage[$key]) && $usage[$key]['date'] === $today) ? (int)$usage[$key]['count'] + 1 : 1];
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
    json_write($file, $usage);
    return true;
}

// 开通商品会员（购买成功后调用）
function member_grant_shop_plan(string $memberId, string $planId): array {
    $plans = mem_plans();
    $plan = null;
    foreach ($plans as $p) if ($p['id'] === $planId) { $plan = $p; break; }
    if (!$plan || ($plan['id'] ?? '') === 'free') return ['ok' => false, 'error' => '会员计划不存在'];
    $member = member_get($memberId);
    if (!$member) return ['ok' => false, 'error' => '用户不存在'];
    $member['membership_plan'] = $planId;
    if ($plan['period'] === 'year') {
        // 从当前或到期日 +365 天
        $base = $member['membership_expires'] ?? '';
        $start = ($base && strtotime($base) > time()) ? $base : date('Y-m-d H:i:s');
        $member['membership_expires'] = date('Y-m-d H:i:s', strtotime($start . ' +365 days'));
    } else {
        $member['membership_expires'] = '9999-12-31 23:59:59'; // 永久
    }
    member_save($member);
    return ['ok' => true, 'plan' => $planId];
}
