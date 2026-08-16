<?php
/**
 * 测试: Database SQLite (PHPUnit)
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase {
    public function testDatabaseConnectionWorks(): void {
        $pdo = Database::conn();
        $this->assertInstanceOf(\PDO::class, $pdo);
    }

    public function testDatabaseQueryReturnsResult(): void {
        $result = Database::query("SELECT 1 as num");
        $this->assertIsArray($result);
        $this->assertSame(1, (int)$result[0]['num']);
    }

    public function testDatabaseQueryWithParameters(): void {
        $result = Database::query("SELECT :val as num", [':val' => 42]);
        $this->assertSame(42, (int)$result[0]['num']);
    }

    public function testDatabaseTableExists(): void {
        $result = Database::query("SELECT name FROM sqlite_master WHERE type='table' AND name='leads'");
        $this->assertNotEmpty($result);
    }

    public function testDatabaseInsertAndQuery(): void {
        Database::execute("CREATE TEMPORARY TABLE test_items (id INTEGER PRIMARY KEY, name TEXT)");
        Database::execute("INSERT INTO test_items (name) VALUES (?)", ['test_item']);
        $result = Database::query("SELECT * FROM test_items WHERE name = ?", ['test_item']);
        $this->assertSame('test_item', $result[0]['name']);
        Database::execute("DROP TABLE test_items");
    }

    public function testDatabaseExecuteReturnsAffectedRows(): void {
        Database::execute("CREATE TEMPORARY TABLE test_rows (id INTEGER PRIMARY KEY, val INTEGER)");
        Database::execute("INSERT INTO test_rows (val) VALUES (1)");
        Database::execute("INSERT INTO test_rows (val) VALUES (2)");
        $affected = Database::execute("UPDATE test_rows SET val = 99 WHERE val = 1");
        $this->assertSame(1, $affected);
        Database::execute("DROP TABLE test_rows");
    }
}
