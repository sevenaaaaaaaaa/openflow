<?php
/**
 * 测试: JSON helpers (PHPUnit)
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JsonTest extends TestCase {
    public function testJsonReadReturnsEmptyArrayForNonExistentFile(): void {
        $result = json_read('/tmp/nonexistent_' . rand() . '.json');
        $this->assertSame([], $result);
    }

    public function testJsonWriteAndReadRoundTrip(): void {
        $tmpFile = sys_get_temp_dir() . '/openflow_test_' . md5(uniqid()) . '.json';
        $data = ['name' => '测试', 'items' => [1, 2, 3], 'nested' => ['key' => '值']];
        json_write($tmpFile, $data);
        $read = json_read($tmpFile);
        $this->assertSame($data, $read);
        @unlink($tmpFile);
    }

    public function testJsonWriteCreatesDirectoryIfNeeded(): void {
        $tmpDir = sys_get_temp_dir() . '/openflow_test_dir_' . md5(uniqid());
        $tmpFile = $tmpDir . '/data.json';
        json_write($tmpFile, ['test' => true]);
        $this->assertDirectoryExists($tmpDir);
        $this->assertFileExists($tmpFile);
        @unlink($tmpFile);
        @rmdir($tmpDir);
    }

    public function testJsonReadHandlesCorruptJsonGracefully(): void {
        $tmpFile = sys_get_temp_dir() . '/openflow_corrupt_' . md5(uniqid()) . '.json';
        file_put_contents($tmpFile, '{invalid json!!!');
        $result = json_read($tmpFile);
        $this->assertSame([], $result);
        @unlink($tmpFile);
    }
}
