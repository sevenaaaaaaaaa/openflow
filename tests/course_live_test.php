<?php
/**
 * 课程↔直播 契约 —— 直播课上下文解析 / 房间绑定 / 回放章节
 *   php tests/course_live_test.php
 *
 * 课程里的「直播课」不是加个字段，而是同一堂课：房间侧绑定课程课时，
 * 播放页解析出 直播/预约/回放 + 章节 + 进入直播间入口。
 */

$tmp = sys_get_temp_dir() . '/of-courselive-' . getmypid();
@mkdir($tmp . '/live', 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
putenv('OF_UPLOAD_DIR=' . $tmp . '/uploads');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/t';

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/LiveSystem.php';

$future = date('Y-m-d H:i:s', time() + 86400);
$past = date('Y-m-d H:i:s', time() - 86400);

json_write($tmp . '/live/index.json', [
    [
        'id' => 'room_live', 'title' => '增长直播课 · 第1讲', 'is_live' => true,
        'bind_course_id' => 'c1', 'bind_lesson_id' => 'l1',
        'hls_url' => 'https://cdn.example/live.m3u8', 'start_at' => $past, 'end_at' => $future,
        'replay_url' => '', 'products' => [], 'push' => null, 'chapters' => [],
    ],
    [
        'id' => 'room_replay', 'title' => '增长直播课 · 回放', 'is_live' => false,
        'bind_course_id' => 'c1', 'bind_lesson_id' => 'l2',
        'hls_url' => '', 'start_at' => $past, 'end_at' => $past,
        'replay_url' => 'https://cdn.example/replay.mp4', 'products' => ['商品A|/a|99', '商品B|/b|199'],
    ],
    [
        'id' => 'room_sched', 'title' => '预告', 'is_live' => false,
        'bind_course_id' => 'c1', 'bind_lesson_id' => 'l3',
        'start_at' => $future, 'end_at' => $future, 'replay_url' => '',
    ],
]);

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d ? " — {$d}" : '') . "\n"; } }

echo "课程↔直播\n";

check('非直播课时 → null', live_lesson_context(['type' => 'video', 'id' => 'l1'], 'c1') === null);

$r = live_room_for_lesson('c1', 'l1');
check('按课程+课时找到绑定房间', ($r['id'] ?? '') === 'room_live');

$ctx = live_lesson_context(['type' => 'live', 'id' => 'l1'], 'c1');
check('直播中：状态 live', ($ctx['status'] ?? '') === 'live');
check('直播中：带 HLS 与进入直播间入口', ($ctx['hls'] ?? '') !== '' && str_contains($ctx['room_url'] ?? '', '/live?room=room_live'));

$ctx2 = live_lesson_context(['type' => 'live', 'id' => 'l2'], 'c1');
check('回放：状态 replay 且带回放地址', ($ctx2['status'] ?? '') === 'replay' && ($ctx2['replay'] ?? '') !== '');
check('回放：自动从推品生成章节', !empty($ctx2['chapters']) && count($ctx2['chapters']) >= 2);
check('回放：章节含时间与标题', isset($ctx2['chapters'][0]['t'], $ctx2['chapters'][0]['title']));

$ctx3 = live_lesson_context(['type' => 'live', 'id' => 'l3'], 'c1');
check('未开播：状态 scheduled 且带开播时间', ($ctx3['status'] ?? '') === 'scheduled' && ($ctx3['start_at'] ?? '') !== '');

$unbound = live_lesson_context(['type' => 'live', 'id' => 'l9'], 'c1');
check('未绑定房间：bound=false，不报错', ($unbound['bound'] ?? true) === false);

echo ($fail ? "❌ {$fail} 失败 / {$pass} 通过\n" : "✅ 全部通过（{$pass}）\n");
exit($fail ? 1 : 0);
