<?php
/**
 * 多步写入一致性契约 —— php tests/txn_contract_test.php
 *
 * 盯的是这样一类问题：一个操作要连着改好几处（订单状态、佣金余额、订阅权益、积分），
 * 中间任何一步失败，前面写下去的就留在那儿了。而且这些写入横跨两种存储——
 * SQLite 的表和 data/ 下的 JSON 文件——SQLite 的事务管不到文件。
 *
 * 这里用真 SQLite + 真文件跑，验证 txn_run() 两件事都做到：
 *   1. 失败时 SQLite 已写的行要消失
 *   2. 失败时被改过的 JSON 文件要按快照还原（本来没有的要删掉）
 */
declare(strict_types=1);

define('DATA_DIR', sys_get_temp_dir() . '/of-txn-test-' . getmypid());
@mkdir(DATA_DIR . '/db', 0777, true);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Txn.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✓ $msg\n"; } else { $fail++; echo "  ✗ $msg\n"; }
}
function bal(string $id): float {
    $r = Database::query("SELECT balance FROM acct WHERE id = ?", [$id]);
    return (float)($r[0]['balance'] ?? -1);
}

Database::execute("CREATE TABLE IF NOT EXISTS acct (id TEXT PRIMARY KEY, balance REAL)");
Database::execute("DELETE FROM acct");
Database::execute("INSERT INTO acct (id, balance) VALUES ('a', 100), ('b', 100)");

$jsonA = DATA_DIR . '/state.json';                 // 事务前就存在
$jsonB = DATA_DIR . '/created-during.json';        // 事务里才创建
file_put_contents($jsonA, json_encode(['status' => 'paid']));
@unlink($jsonB);

echo "\n── 1. 成功路径：SQLite 与 JSON 都要落下去 ──\n";
$ret = txn_run(function () use ($jsonA, $jsonB) {
    Database::execute("UPDATE acct SET balance = balance - 30 WHERE id = 'a'");
    Database::execute("UPDATE acct SET balance = balance + 30 WHERE id = 'b'");
    file_put_contents($jsonA, json_encode(['status' => 'refunded']));
    file_put_contents($jsonB, json_encode(['note' => 'new']));
    return '成交';
}, [$jsonA, $jsonB]);
ok($ret === '成交', 'txn_run 原样返回业务返回值');
ok(bal('a') === 70.0 && bal('b') === 130.0, '成功时 SQLite 写入已提交');
ok(json_decode((string)file_get_contents($jsonA), true)['status'] === 'refunded', '成功时 JSON 写入保留');
ok(is_file($jsonB), '成功时事务中新建的文件保留');

echo "\n── 2. 失败路径：一步都不能留下 ──\n";
$before = [bal('a'), bal('b')];
$threw = false;
try {
    txn_run(function () use ($jsonA, $jsonB) {
        Database::execute("UPDATE acct SET balance = balance - 50 WHERE id = 'a'");   // 已经写了
        file_put_contents($jsonA, json_encode(['status' => '半截']));                  // 也写了
        @unlink($jsonB);                                                              // 还删了个文件
        throw new RuntimeException('第三步炸了');                                       // 然后炸
    }, [$jsonA, $jsonB]);
} catch (Throwable $e) {
    $threw = true;
    ok($e->getMessage() === '第三步炸了', '原始异常原样抛出，没有被包装或吞掉');
}
ok($threw, '失败必须抛出，不能静默返回');
ok(bal('a') === $before[0], 'SQLite 已写的行被回滚（余额 ' . bal('a') . '，应为 ' . $before[0] . '）');
ok(json_decode((string)file_get_contents($jsonA), true)['status'] === 'refunded', 'JSON 按快照还原');
ok(is_file($jsonB), '事务中被删掉的文件也还原了');

echo "\n── 3. 事务里新建的文件，回滚时要删掉（不能留垃圾）──\n";
$jsonC = DATA_DIR . '/only-on-success.json';
@unlink($jsonC);
try {
    txn_run(function () use ($jsonC) {
        file_put_contents($jsonC, json_encode(['x' => 1]));
        throw new RuntimeException('炸');
    }, [$jsonC]);
} catch (Throwable $e) {}
ok(!is_file($jsonC), '回滚后不应留下事务中新建的文件');

echo "\n── 4. 嵌套：内层不得抢先提交 ──\n";
$before = bal('a');
try {
    txn_run(function () use ($jsonA) {
        Database::execute("UPDATE acct SET balance = balance - 10 WHERE id = 'a'");
        txn_run(function () {                       // 内层：不该自己开事务、更不该自己提交
            Database::execute("UPDATE acct SET balance = balance - 5 WHERE id = 'a'");
        }, []);
        throw new RuntimeException('外层炸');
    }, [$jsonA]);
} catch (Throwable $e) {}
ok(bal('a') === $before, '内层写入随外层一起回滚（余额 ' . bal('a') . '，应为 ' . $before . '）');
ok(txn_active() === false, '回滚后不应残留打开的事务');

echo "\n── 5. 拿不到数据库时要降级，而不是整个操作报错 ──\n";
// 只动 JSON 的调用方不该因为 SQLite 不可用就失败
$probe = DATA_DIR . '/degrade.json';
file_put_contents($probe, 'v1');
$r = txn_run(function () use ($probe) { file_put_contents($probe, 'v2'); return 'ok'; }, [$probe]);
ok($r === 'ok' && file_get_contents($probe) === 'v2', '纯 JSON 的多步写入照常工作');
ok(function_exists('txn_active') && txn_active() === false, 'txn_active() 在空闲时返回 false');

// 收尾
foreach (glob(DATA_DIR . '/db/*') ?: [] as $f) @unlink($f);
foreach (glob(DATA_DIR . '/*') ?: [] as $f) if (is_file($f)) @unlink($f);
@rmdir(DATA_DIR . '/db'); @rmdir(DATA_DIR);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
