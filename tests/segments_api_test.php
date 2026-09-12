<?php
/**
 * 分群即服务契约 —— 计数/成员脱敏/限量 + 令牌闸门
 *   php tests/segments_api_test.php
 */

require_once __DIR__ . '/../lib/SegmentApi.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "分群即服务\n";

check('邮箱脱敏', segapi_mask_email('alice@example.com') === 'a***@example.com');
check('空邮箱脱敏为空', segapi_mask_email('') === '');

$profiles = [
    ['visitor_id' => 'v1', 'score' => 80, 'last_seen' => '2026-09-01 10:00:00', 'properties' => ['email' => 'alice@example.com', 'name' => 'Alice'], 'segment_memberships' => ['seg_high' => ['joined_at' => '2026-08-01 00:00:00'], 'seg_active' => []]],
    ['visitor_id' => 'v2', 'score' => 30, 'last_seen' => '2026-09-02 10:00:00', 'properties' => ['email' => 'bob@example.com', 'name' => 'Bob'], 'segment_memberships' => ['seg_high' => []]],
    ['visitor_id' => 'v3', 'score' => 10, 'properties' => ['email' => ''], 'segment_memberships' => []],
];

$counts = segapi_counts($profiles);
check('分群计数正确', ($counts['seg_high'] ?? 0) === 2 && ($counts['seg_active'] ?? 0) === 1);
check('无分群画像不计入', !isset($counts['seg_x']));

$members = segapi_members($profiles, 'seg_high', 100);
check('成员列表只含该分群', count($members) === 2 && $members[0]['visitor_id'] === 'v1' && $members[1]['visitor_id'] === 'v2');
check('成员邮箱已脱敏', $members[0]['email'] === 'a***@example.com');
check('成员保留 joined_at', $members[0]['joined_at'] === '2026-08-01 00:00:00');
check('limit 生效', count(segapi_members($profiles, 'seg_high', 1)) === 1);
check('空分群返回空数组', segapi_members($profiles, '', 100) === []);

// 结构性守卫：端点必须有令牌闸门
$ep = file_get_contents(__DIR__ . '/../api/segments.php');
check('端点用 hash_equals 校验令牌', strpos($ep, 'hash_equals(') !== false);
check('端点拒绝无效令牌', strpos($ep, 'Invalid API key') !== false);
check('端点支持 Bearer', stripos($ep, 'Bearer ') !== false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
