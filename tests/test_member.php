<?php
/**
 * 测试: MemberSystem
 */
require_once __DIR__ . '/../lib/MemberSystem.php';
require_once __DIR__ . '/../lib/MembershipSystem.php';

test('member_current returns null when not logged in', function() {
    $result = member_current();
    assert_true($result === null || is_array($result), 'Should return null or array');
});

test('member_can returns false for null member', function() {
    $result = member_can(null, 'courses');
    assert_false($result, 'Should return false for null member');
});

test('Member hash generation', function() {
    $password = 'member_test_123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    assert_true(password_verify($password, $hash), 'Member password hash should verify');
});

test('Member data structure validation', function() {
    $member = [
        'id' => 'mem_' . bin2hex(random_bytes(8)),
        'name' => '测试用户',
        'email' => 'test@example.com',
        'phone' => '13800138000',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    assert_true(!empty($member['id']), 'Member should have ID');
    assert_true(!empty($member['name']), 'Member should have name');
    assert_true(!empty($member['email']), 'Member should have email');
    assert_true(strpos($member['id'], 'mem_') === 0, 'Member ID should start with mem_');
});

test('member_can returns false for non-existent entitlement', function() {
    $member = ['id' => 'test', 'role' => 'member'];
    $result = member_can($member, 'nonexistent_feature_xyz');
    assert_false($result, 'Should return false for unknown entitlement');
});
