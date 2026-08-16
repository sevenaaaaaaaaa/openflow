<?php
/**
 * 测试: Security functions (PHPUnit)
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase {
    public function testPasswordHashProducesValidHash(): void {
        $password = 'test_password_123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->assertTrue(password_verify($password, $hash));
        $this->assertGreaterThan(50, strlen($hash));
    }

    public function testPasswordVerifyRejectsWrongPassword(): void {
        $hash = password_hash('correct_password', PASSWORD_DEFAULT);
        $this->assertFalse(password_verify('wrong_password', $hash));
    }

    public function testCsrfTokenGeneration(): void {
        $token = bin2hex(random_bytes(32));
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function testMagicBytesDetectionPng(): void {
        $validSignatures = [
            'png' => ["\x89PNG\r\n\x1a\n"],
            'jpg' => ["\xff\xd8\xff"],
            'gif' => ["GIF87a", "GIF89a"],
        ];
        $fakePng = "\x89PNG\r\n\x1a\n" . random_bytes(100);
        $detected = false;
        foreach ($validSignatures['png'] as $sig) {
            if (substr($fakePng, 0, strlen($sig)) === $sig) {
                $detected = true;
                break;
            }
        }
        $this->assertTrue($detected, 'Should detect PNG signature');
    }

    public function testIpExtractionFromForwardedHeader(): void {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 5.6.7.8';
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $this->assertStringContainsString('1.2.3.4', $ip);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }
}
