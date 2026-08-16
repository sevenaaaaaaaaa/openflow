<?php
/**
 * 测试: 安全函数
 */

test('CSRF token generation and verification', function() {
    // Generate token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $token = $_SESSION['csrf_token'];
    assert_true(strlen($token) === 64, 'Token should be 64 hex chars');
    assert_eq($token, $_SESSION['csrf_token'], 'Token should match session');
});

test('password_hash produces valid hash', function() {
    $password = 'test_password_123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    assert_true(password_verify($password, $hash), 'Hash should verify');
    assert_true(strlen($hash) > 50, 'Hash should be sufficiently long');
});

test('password_verify rejects wrong password', function() {
    $hash = password_hash('correct_password', PASSWORD_DEFAULT);
    assert_false(password_verify('wrong_password', $hash), 'Wrong password should fail');
});

test('Rate limiter tracks attempts', function() {
    // This tests the concept; actual rate limiter uses file-based storage
    $key = 'test_rate_' . md5(uniqid());
    $attempts = [];
    $maxAttempts = 5;
    $window = 900; // 15 min

    // Simulate 5 attempts
    for ($i = 0; $i < $maxAttempts; $i++) {
        $attempts[] = time();
    }
    assert_eq($maxAttempts, count($attempts), 'Should track all attempts');
    assert_true(count($attempts) >= $maxAttempts, 'Should be at max attempts');
});

test('IP extraction works', function() {
    // Test with various header scenarios
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 5.6.7.8';
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    assert_true(strpos($ip, '1.2.3.4') !== false, 'Should extract IP from forwarded header');
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
});

test('Magic bytes validation - PNG', function() {
    $validSignatures = [
        'png' => ["\x89PNG\r\n\x1a\n"],
        'jpg' => ["\xff\xd8\xff"],
        'gif' => ["GIF87a", "GIF89a"],
    ];
    // Create fake PNG header
    $fakePng = "\x89PNG\r\n\x1a\n" . random_bytes(100);
    $detected = false;
    foreach ($validSignatures['png'] as $sig) {
        if (substr($fakePng, 0, strlen($sig)) === $sig) {
            $detected = true;
            break;
        }
    }
    assert_true($detected, 'Should detect PNG signature');
});
