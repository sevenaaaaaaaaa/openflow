<?php
/**
 * T0-1 验收：CDP 画像迁 SQLite（CdpProfileStore）
 *
 *   php tests/cdp_profile_store_test.php
 *
 * 验：老 profiles.json 一次性导入、按行读、单行 upsert 不碰他人、
 *     save_all 精确对齐(集合内 upsert / 集合外删除)、marker 落盘、原 JSON 保留作备份。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-cdpstore-' . getmypid());
@mkdir(DATA_DIR . '/cdp', 0777, true);
@mkdir(DATA_DIR . '/db', 0777, true);

function json_read(string $f): array {
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function json_write(string $f, array $d): bool {
    @mkdir(dirname($f), 0777, true);
    return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE));
}

// 预置老 profiles.json（3 个画像）
$legacy = [
    'v1' => ['visitor_id'=>'v1','member_id'=>'m1','events_count'=>5,'tags'=>['VIP'],'properties'=>['email'=>'a@t.com']],
    'v2' => ['visitor_id'=>'v2','member_id'=>'','events_count'=>1,'tags'=>[],'properties'=>[]],
    'v3' => ['visitor_id'=>'v3','member_id'=>'m3','events_count'=>9,'tags'=>['老客'],'properties'=>[]],
];
json_write(DATA_DIR . '/cdp/profiles.json', $legacy);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/CdpProfileStore.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

echo "\n── 1. 一次性迁移：JSON → SQLite ──\n";
$all = cdp_profile_all();   // 触发 ensure→import
check('导入 3 条', count($all) === 3, '数量=' . count($all));
check('v1 数据完整(events=5)', ($all['v1']['events_count'] ?? 0) === 5);
check('v1 tags 完整', ($all['v1']['tags'] ?? []) === ['VIP']);
check('marker 已落盘', is_file(DATA_DIR . '/cdp/.profiles_migrated'));
check('原 profiles.json 保留(未删)', is_file(DATA_DIR . '/cdp/profiles.json'));

echo "\n── 2. 按行读 ──\n";
check('get v3', (cdp_profile_get('v3')['member_id'] ?? '') === 'm3');
check('get 不存在 → null', cdp_profile_get('nope') === null);

echo "\n── 3. 单行 upsert 不碰他人 ──\n";
$v2 = cdp_profile_get('v2');
$v2['events_count'] = 42; $v2['tags'] = ['已成交'];
cdp_profile_put('v2', $v2);
check('v2 已更新(events=42)', (cdp_profile_get('v2')['events_count'] ?? 0) === 42);
check('v1 不受影响', (cdp_profile_get('v1')['events_count'] ?? 0) === 5);
check('总数仍 3', count(cdp_profile_all()) === 3);

echo "\n── 4. member_id 抽成列(可按会员查) ──\n";
$rows = Database::query("SELECT visitor_id FROM cdp_profiles WHERE member_id = ?", ['m3']);
check('按 member_id 命中 v3', count($rows) === 1 && $rows[0]['visitor_id'] === 'v3');

echo "\n── 5. save_all 精确对齐：集合外删除 + 集合内 upsert ──\n";
// 模拟身份合并：把 v2 并入 v1 后回写（去掉 v2，改 v1）
$merged = cdp_profile_all();
$merged['v1']['events_count'] = 100;
unset($merged['v2']);
cdp_profile_save_all($merged);   // 应删 v2、留 v1(更新)/v3
$after = cdp_profile_all();
check('v2 被删除', !isset($after['v2']));
check('v1 已更新(events=100)', ($after['v1']['events_count'] ?? 0) === 100);
check('v3 保留', isset($after['v3']));
check('总数变 2', count($after) === 2, '数量=' . count($after));

echo "\n── 6. 新画像可 put 进来 ──\n";
cdp_profile_put('v9', ['visitor_id'=>'v9','member_id'=>'m9','events_count'=>1]);
check('v9 已入库', cdp_profile_get('v9') !== null);
check('总数 3', count(cdp_profile_all()) === 3);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
// 清理
@unlink(DATA_DIR . '/db/openflow.db');
@array_map('unlink', glob(DATA_DIR . '/db/*'));
@array_map('unlink', glob(DATA_DIR . '/cdp/*'));
@array_map('unlink', glob(DATA_DIR . '/cdp/.*'));
@rmdir(DATA_DIR . '/db'); @rmdir(DATA_DIR . '/cdp'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
