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
        ['id' => 'member', 'name' => '普通会员', 'icon' => '⭐', 'price' => 299, 'period' => 'year', 'benefits' => ['全部订阅邮件', '付费资料下载', '专享会员文章', '直播回放', '8 折购课']],
        ['id' => 'vip', 'name' => 'VIP 会员', 'icon' => '👑', 'price' => 999, 'period' => 'year', 'benefits' => ['普通会员全部权益', '1v1 咨询 85 折', '课程免费看', '专属客服', '优先审核']],
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

    // 计算等级
    $plans = mem_plans();
    $planMap = []; foreach ($plans as $p) $planMap[$p['id']] = $p;
    if ($grantedTier === 'vip') { $tier = 'vip'; }
    elseif ($grantedTier === 'member' || $subActive) { $tier = 'member'; }
    elseif ($points >= 1000) { $tier = 'member'; } // 高积分自动升级
    else { $tier = 'free'; }

    $plan = $planMap[$tier] ?? $planMap['free'];

    return [
        'tier' => $tier,
        'tier_name' => $plan['name'] ?? ($tier === 'free' ? '免费用户' : '会员'),
        'icon' => $plan['icon'] ?? '👤',
        'benefits' => $plan['benefits'] ?? [],
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
