<?php
/**
 * 测试: JSON helpers
 */

test('json_read returns empty array for non-existent file', function() {
    $result = json_read('/tmp/nonexistent_' . rand() . '.json');
    assert_eq([], $result, 'Should return empty array');
});

test('json_write and json_read round-trip', function() {
    $tmpFile = sys_get_temp_dir() . '/openflow_test_' . md5(uniqid()) . '.json';
    $data = ['name' => '测试', 'items' => [1, 2, 3], 'nested' => ['key' => '值']];
    json_write($tmpFile, $data);
    $read = json_read($tmpFile);
    assert_eq($data, $read, 'Round-trip should preserve data');
    @unlink($tmpFile);
});

test('json_write creates directory if needed', function() {
    $tmpDir = sys_get_temp_dir() . '/openflow_test_dir_' . md5(uniqid());
    $tmpFile = $tmpDir . '/data.json';
    json_write($tmpFile, ['test' => true]);
    assert_true(is_dir($tmpDir), 'Directory should be created');
    assert_true(file_exists($tmpFile), 'File should exist');
    @unlink($tmpFile);
    @rmdir($tmpDir);
});

test('json_read handles corrupt JSON gracefully', function() {
    $tmpFile = sys_get_temp_dir() . '/openflow_corrupt_' . md5(uniqid()) . '.json';
    file_put_contents($tmpFile, '{invalid json!!!');
    $result = json_read($tmpFile);
    assert_eq([], $result, 'Should return empty array for corrupt JSON');
    @unlink($tmpFile);
});
