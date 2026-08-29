<?php
/**
 * 积分 / 会员等级体系
 * 积分来源：注册/发帖/评论/投票被收/调研/课程购买/每日登录
 * 等级：普通 → 铜牌 → 银牌 → 金牌 → 大使
 */
require_once __DIR__ . '/MessageSystem.php';

function gamification_levels(): array {
    return [
        ['key' => 'member', 'name' => '普通会员', 'icon' => '🔹', 'min_points' => 0, 'perms' => ['post'], 'post_limit' => 10],
        ['key' => 'bronze', 'name' => '铜牌会员', 'icon' => '🥉', 'min_points' => 100, 'perms' => ['post', 'comment'], 'post_limit' => 20],
        ['key' => 'silver', 'name' => '银牌会员', 'icon' => '🥈', 'min_points' => 500, 'perms' => ['post', 'comment', 'vote'], 'post_limit' => 50],
        ['key' => 'gold', 'name' => '金牌会员', 'icon' => '🥇', 'min_points' => 1500, 'perms' => ['post', 'comment', 'vote', 'no_review'], 'post_limit' => 100],
        ['key' => 'ambassador', 'name' => '推荐大使', 'icon' => '🏅', 'min_points' => 5000, 'perms' => ['post', 'comment', 'vote', 'no_review', 'featured'], 'post_limit' => 0],
    ];
}

// 根据积分得到等级
function gamification_level_of(int $points): array {
    $level = gamification_levels()[0];
    foreach (gamification_levels() as $l) if ($points >= $l['min_points']) $level = $l;
    return $level;
}

// 给用户加分并返回新等级
// $points 可为负（退款回收积分），此时总分不会被扣成负值，通知文案也会切换成「扣除」。
function gamification_award(string $memberId, int $points, string $reason): ?array {
    $member = null;
    $members = json_read(DATA_DIR . '/members/index.json');
    foreach ($members as &$m) {
        if ($m['id'] === $memberId) {
            $m['points'] = max(0, (int)($m['points'] ?? 0) + $points);
            $m['level'] = gamification_level_of($m['points'])['key'];
            // 积分日志
            $m['points_log'] = $m['points_log'] ?? [];
            $m['points_log'][] = ['points' => $points, 'reason' => $reason, 'time' => date('Y-m-d H:i:s')];
            $m['points_log'] = array_slice($m['points_log'], -50);
            $member = $m;
            break;
        }
    }
    unset($m);
    if ($member) {
        json_write(DATA_DIR . '/members/index.json', $members);
        inbox_notify_event($points >= 0 ? 'points_awarded' : 'points_deducted',
            ['member_id' => $memberId, 'points' => abs($points), 'reason' => $reason]);
    }
    return $member;
}

// 检查会员是否有某权限
function gamification_has_perm(?array $member, string $perm): bool {
    if (!$member) return false;
    $level = gamification_level_of($member['points'] ?? 0);
    return in_array($perm, $level['perms'] ?? []);
}

// 检查发帖频率限制
function gamification_post_allowed(?array $member, int $existingToday): bool {
    if (!$member) return false;
    $level = gamification_level_of($member['points'] ?? 0);
    if (($level['post_limit'] ?? 0) === 0) return true; // 0 = 不限
    return $existingToday < $level['post_limit'];
}
