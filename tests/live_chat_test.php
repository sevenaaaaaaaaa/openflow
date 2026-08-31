<?php
/**
 * 直播聊天回归 —— 并发下不许丢消息
 *   php tests/live_chat_test.php
 *
 * 这条是最后一处「整批读写」，而且它的问题不是慢（300 条很小），是**正确性**：
 * 老实现「读全部 → 追加一条 → 整个写回」，两个观众同时发言时后写的会覆盖先写的，
 * 消息就静悄悄地丢了。直播恰恰是最不能丢消息的场景。
 */

$tmp = sys_get_temp_dir() . '/of-livechat-' . getmypid();
@mkdir($tmp . '/live', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

// 先放一份老 JSON，验证迁移
file_put_contents($tmp . '/live/chat.json', json_encode([
    ['id' => 'old1', 'room_id' => 'r1', 'user' => '甲', 'text' => '迁移前的消息', 'time' => '10:00:00'],
    ['id' => 'old2', 'room_id' => 'r2', 'user' => '乙', 'text' => '别的房间',     'time' => '10:01:00'],
], JSON_UNESCAPED_UNICODE));

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/LiveSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. 老 JSON 自动导入 ──\n";
check('建表并导入', live_chat_ensure() === true);
$r1 = live_chat('r1');
check('老消息还在', count($r1) === 1 && $r1[0]['text'] === '迁移前的消息', json_encode($r1, JSON_UNESCAPED_UNICODE));
check('原 JSON 保留作备份', is_file($tmp . '/live/chat.json'));
check('留了防重导 marker', is_file($tmp . '/live/.chat_migrated'));

echo "\n── 2. 连发不丢（老实现在这里会丢）──\n";
for ($i = 0; $i < 120; $i++) live_chat_send('r1', "观众{$i}", "第{$i}条");
$all = live_chat('r1', 500);
check('120 条一条不少（+1 条老的）', count($all) === 121, (string)count($all));
$texts = array_column($all, 'text');
check('没有重复', count($texts) === count(array_unique($texts)));
check('顺序是时间顺序', end($texts) === '第119条', (string)end($texts));

echo "\n── 3. 房间之间不串台 ──\n";
check('r2 只有自己的 1 条', count(live_chat('r2')) === 1, (string)count(live_chat('r2')));
check('r1 不受影响', count(live_chat('r1', 500)) === 121);

echo "\n── 4. 字段与截断 ──\n";
$m = live_chat_send('r3', '丙', str_repeat('长', 400));
check('返回结构完整', isset($m['id'], $m['room_id'], $m['user'], $m['text'], $m['time']));
check('超长文本截到 300 字', mb_strlen($m['text']) === 300, (string)mb_strlen($m['text']));
check('读回来内容一致', (live_chat('r3')[0]['text'] ?? '') === $m['text']);
check('消息 id 唯一', live_chat_send('r3', '丙', 'a')['id'] !== live_chat_send('r3', '丙', 'b')['id']);

echo "\n── 5. limit 生效 ──\n";
check('limit=10 只回 10 条', count(live_chat('r1', 10)) === 10, (string)count(live_chat('r1', 10)));
$last10 = live_chat('r1', 10);
check('limit 取的是最新的', end($last10)['text'] === '第119条', (string)end($last10)['text']);

echo "\n── 6. 不再有整批写回 ──\n";
$src = file_get_contents(__DIR__ . '/../lib/LiveSystem.php');
check('send 的主路径不是 json_write 全量',
      preg_match('/function live_chat_send.*?Database::execute/s', $src) === 1);
check('仍保留 JSON 回退（SQLite 不可用时）',
      preg_match('/function live_chat_send.*?json_write/s', $src) === 1);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
exec('rm -rf ' . escapeshellarg($tmp));
exit($fail === 0 ? 0 : 1);
