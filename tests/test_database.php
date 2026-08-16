<?php
/**
 * 测试: Database SQLite (PDO)
 */
require_once __DIR__ . '/../lib/Database.php';

test('Database connection works', function() {
    $pdo = Database::conn();
    assert_true($pdo instanceof PDO, 'Should return PDO instance');
});

test('Database query returns result', function() {
    $result = Database::query("SELECT 1 as num");
    assert_true(is_array($result), 'Should return array');
    assert_eq(1, (int)$result[0]['num'], 'Should return 1');
});

test('Database query with parameters', function() {
    $result = Database::query("SELECT :val as num", [':val' => 42]);
    assert_eq(42, (int)$result[0]['num'], 'Bound value should be 42');
});

test('Database table exists check', function() {
    $result = Database::query("SELECT name FROM sqlite_master WHERE type='table' AND name='leads'");
    assert_true(count($result) > 0, 'leads table should exist');
});

test('Database insert and query', function() {
    // Create temp test table
    Database::execute("CREATE TEMPORARY TABLE test_items (id INTEGER PRIMARY KEY, name TEXT)");
    Database::execute("INSERT INTO test_items (name) VALUES (?)", ['test_item']);
    $result = Database::query("SELECT * FROM test_items WHERE name = ?", ['test_item']);
    assert_eq('test_item', $result[0]['name'], 'Inserted item should be retrievable');
    Database::execute("DROP TABLE test_items");
});

test('Database execute returns affected rows', function() {
    Database::execute("CREATE TEMPORARY TABLE test_rows (id INTEGER PRIMARY KEY, val INTEGER)");
    Database::execute("INSERT INTO test_rows (val) VALUES (1)");
    Database::execute("INSERT INTO test_rows (val) VALUES (2)");
    $affected = Database::execute("UPDATE test_rows SET val = 99 WHERE val = 1");
    assert_eq(1, $affected, 'Should affect 1 row');
    Database::execute("DROP TABLE test_rows");
});
