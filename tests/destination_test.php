<?php
/**
 * T0-6 验收：人群激活 destinations（DestinationSystem）
 *
 *   php tests/destination_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-dest-' . getmypid());
@mkdir(DATA_DIR . '/cdp', 0777, true);
function json_read(string $f): array { if (!is_file($f)) return []; $d = json_decode((string)file_get_contents($f), true); return is_array($d) ? $d : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/DestinationSystem.php';

// 捕获发送
$GLOBALS['SENT'] = [];
$GLOBALS['DEST_SENDER'] = function($dest, $payload) { $GLOBALS['SENT'][] = ['dest' => $dest['id'], 'payload' => $payload]; return true; };

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 建目的地 + field_map 字符串解析 ──\n";
$r = dest_save(['name'=>'广告受众', 'type'=>'webhook', 'segment_id'=>'seg_vip', 'url'=>'https://x.test/hook',
    'trigger'=>'realtime', 'enabled'=>true, 'field_map'=>"email=properties.email\nname=properties.name"]);
check('创建成功', $r['ok'] === true, $r['error'] ?? '');
check('field_map 解析成 kv', ($r['dest']['field_map']['email'] ?? '') === 'properties.email');
$did = $r['dest']['id'];
check('dest_get 取回', dest_get($did) !== null);

echo "\n── 2. 载荷组装（点路径映射）──\n";
$profile = ['visitor_id'=>'v1','member_id'=>'m1','tags'=>['VIP'],'properties'=>['email'=>'a@t.com','name'=>'张三']];
$pl = dest_build_payload(dest_get($did), $profile);
check('email 映射', ($pl['email'] ?? '') === 'a@t.com');
check('name 映射', ($pl['name'] ?? '') === '张三');
check('只含映射字段', !isset($pl['visitor_id']));

echo "\n── 3. 无映射时的默认载荷 ──\n";
dest_save(['name'=>'默认', 'segment_id'=>'seg_vip', 'url'=>'https://x', 'enabled'=>true]);
$def = array_values(array_filter(dest_all(), fn($d)=>$d['name']==='默认'))[0];
$pl2 = dest_build_payload($def, $profile);
check('默认含 email/visitor_id/tags', isset($pl2['email']) && isset($pl2['visitor_id']) && isset($pl2['tags']));

echo "\n── 4. segment_enter 实时触发：只推匹配+enabled+realtime ──\n";
dest_save(['name'=>'别的群', 'segment_id'=>'seg_other', 'url'=>'https://y', 'trigger'=>'realtime', 'enabled'=>true]);
dest_save(['name'=>'停用的', 'segment_id'=>'seg_vip', 'url'=>'https://z', 'trigger'=>'realtime', 'enabled'=>false]);
dest_save(['name'=>'手动的', 'segment_id'=>'seg_vip', 'url'=>'https://w', 'trigger'=>'manual', 'enabled'=>true]);
$GLOBALS['SENT'] = [];
$n = dest_on_segment_enter('seg_vip', $profile);
check('seg_vip 进群派发 2 条(广告受众+默认)', $n === 2, "n={$n}");
check('停用/手动/别的群 都不派', count($GLOBALS['SENT']) === 2);

echo "\n── 5. 手动全量同步（注入成员）──\n";
$members = [$profile, ['visitor_id'=>'v2','properties'=>['email'=>'b@t.com']], ['visitor_id'=>'v3','properties'=>[]]];
$GLOBALS['SENT'] = [];
$r = dest_sync_full($did, $members);
check('同步 3 条', ($r['synced'] ?? 0) === 3, json_encode($r));
check('实际发送 3 次', count($GLOBALS['SENT']) === 3);
check('stats 落盘', (dest_get($did)['stats']['synced'] ?? 0) === 3);
check('last_run 记录', !empty(dest_get($did)['last_run']));

echo "\n── 6. 停用目的地不发送 ──\n";
$GLOBALS['SENT'] = [];
$disabled = array_values(array_filter(dest_all(), fn($d)=>$d['name']==='停用的'))[0];
check('dispatch 停用→false', dest_dispatch_profile($disabled, $profile) === false);
check('未发送', count($GLOBALS['SENT']) === 0);

echo "\n── 7. 删除 ──\n";
check('删除命中', dest_delete($did) === true);
check('已删', dest_get($did) === null);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/cdp/*')); @rmdir(DATA_DIR.'/cdp'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
