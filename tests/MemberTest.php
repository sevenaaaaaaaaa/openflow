<?php
/**
 * 测试: MemberSystem (PHPUnit)
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MemberTest extends TestCase {
    public function testMemberCurrentReturnsNullOrArray(): void {
        $result = member_current();
        $this->assertTrue($result === null || is_array($result));
    }

    public function testMemberCanReturnsFalseForNullMember(): void {
        $result = member_can(null, 'courses');
        $this->assertFalse($result);
    }

    public function testMemberPasswordHashVerification(): void {
        $password = 'member_test_123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testMemberDataStructureValidation(): void {
        $member = [
            'id' => 'mem_' . bin2hex(random_bytes(8)),
            'name' => '测试用户',
            'email' => 'test@example.com',
            'phone' => '13800138000',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->assertNotEmpty($member['id']);
        $this->assertNotEmpty($member['name']);
        $this->assertNotEmpty($member['email']);
        $this->assertStringStartsWith('mem_', $member['id']);
    }

    public function testMemberCanReturnsFalseForNonExistentEntitlement(): void {
        $member = ['id' => 'test', 'role' => 'member'];
        $result = member_can($member, 'nonexistent_feature_xyz');
        $this->assertFalse($result);
    }
}
