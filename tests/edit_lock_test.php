<?php
/**
 * T2-2 验收：轻量编辑锁（EditLock）
 *   php tests/edit_lock_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-lock-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/EditLock.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }
$T = 1000000;

echo "\n── 1. 取锁 ──\n";
$r = editlock_acquire('article:a1', 'u1', '张三', $T);
check('首次取到', $r['ok'] === true);
check('必填校验', (editlock_acquire('', 'u1')['ok'] ?? true) === false);

echo "\n── 2. 他人被挡 ──\n";
$r2 = editlock_acquire('article:a1', 'u2', '李四', $T + 10);
check('被挡', $r2['ok'] === false);
check('告知持有者', $r2['holder'] === '张三');
check('给出剩余秒数', $r2['remaining'] > 0 && $r2['remaining'] <= editlock_ttl());

echo "\n── 3. 同一人可重入并续期 ──\n";
$r3 = editlock_acquire('article:a1', 'u1', '张三', $T + 60);
check('自己再取仍 ok', $r3['ok'] === true);
check('续期成功', editlock_renew('article:a1', 'u1', $T + 120) === true);
check('他人不能续期', editlock_renew('article:a1', 'u2', $T + 130) === false);

echo "\n── 4. 不同资源互不影响 ──\n";
check('另一篇能取', editlock_acquire('article:a2', 'u2', '李四', $T + 10)['ok'] === true);
check('原锁仍在', editlock_status('article:a1', $T + 130)['user_id'] === 'u1');

echo "\n── 5. TTL 过期自动释放 ──\n";
$after = $T + 120 + editlock_ttl() + 1;
check('过期后他人可取', editlock_acquire('article:a1', 'u2', '李四', $after)['ok'] === true);
check('状态显示新持有者', editlock_status('article:a1', $after)['user_name'] === '李四');

echo "\n── 6. 释放 ──\n";
check('非持有者不能释放', editlock_release('article:a1', 'u1') === false);
check('持有者可释放', editlock_release('article:a1', 'u2') === true);
check('释放后无锁', editlock_status('article:a1', $after) === null);
check('释放不存在的锁返回 false', editlock_release('nope', 'u1') === false);

echo "\n── 7. 强制接管（记录来源）──\n";
editlock_acquire('article:a3', 'u1', '张三', $after);
$t = editlock_takeover('article:a3', 'u2', '李四', $after + 5);
check('接管成功', $t['ok'] === true);
check('记录被接管者', $t['from'] === '张三');
check('新持有者生效', editlock_status('article:a3', $after + 6)['user_id'] === 'u2');

echo "\n── 8. GC 清理过期 ──\n";
$far = $after + editlock_ttl() * 3;
$left = editlock_gc($far);
check('过期锁被清空', count($left) === 0, json_encode(array_keys($left)));

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@unlink(DATA_DIR.'/edit-locks.json'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
