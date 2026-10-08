<?php
declare(strict_types=1);
/**
 * MediaMirror（uploads → R2 双写）契约测试
 *
 *   php tests/media_mirror_test.php
 *
 * 守住的底线：
 *   1) 未配置/未启用时绝不发网络请求（put 一律 false、URL 前缀保持 /uploads）
 *   2) 对象键构造：uploads/ 前缀剥离、.. 逃逸拒绝、双斜杠归一
 *   3) put 只接受 UPLOAD_DIR 下的真实文件（越界/不存在拒绝）
 *   4) fail-soft：异常不外抛
 */

$tmp = sys_get_temp_dir() . '/of-mmirror-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OF_DATA_DIR=' . $tmp);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/../lib/MediaMirror.php';

$pass = 0; $failN = 0;
function check(string $name, bool $ok): void
{
    global $pass, $failN;
    if ($ok) { $pass++; echo "  ✓ $name\n"; }
    else { $failN++; echo "  ✗ $name\n"; }
}

// ── 1) 未配置：无网络、无副作用 ──
check('未配置时 base 保持 /uploads（源站直出）', media_mirror_base() === '/uploads');
check('未配置时 put 返回 false', media_mirror_put(__FILE__) === false);

// ── 2) 配置了但未启用 ──
file_put_contents($tmp . '/media-mirror.json', json_encode([
    'enabled' => false, 'endpoint' => 'https://x.r2.cloudflarestorage.com',
    'bucket' => 'b', 'access_key' => 'k', 'secret_key' => 's',
]));
check('enabled=false 时 base 仍是 /uploads', media_mirror_base() === '/uploads');
check('enabled=false 时 put 返回 false', media_mirror_put(__FILE__) === false);

// ── 3) 启用后 base 翻转 ──
file_put_contents($tmp . '/media-mirror.json', json_encode([
    'enabled' => true, 'endpoint' => 'https://x.r2.cloudflarestorage.com',
    'bucket' => 'b', 'access_key' => 'k', 'secret_key' => 's',
]));
check('启用后 base=/assets/uploads', media_mirror_base() === '/assets/uploads');

// ── 4) 对象键构造规则 ──
check('uploads/ 前缀剥离', media_mirror_key('uploads/articles/a.webp') === 'assets/uploads/articles/a.webp');
check('纯相对路径加前缀', media_mirror_key('articles/a.webp') === 'assets/uploads/articles/a.webp');
check('日期子目录保留', media_mirror_key('uploads/2025/11/x.jpg') === 'assets/uploads/2025/11/x.jpg');
check('前导斜杠归一', media_mirror_key('/uploads/x.png') === 'assets/uploads/x.png');
check('.. 逃逸拒绝', media_mirror_key('uploads/../../etc/passwd') === '');
check('空串拒绝', media_mirror_key('') === '');
check('null 拒绝', media_mirror_key(null) === '');

// ── 5) put 的路径安全：只收 UPLOAD_DIR 下真实文件 ──
$up = $tmp . '/uploads/articles';
@mkdir($up, 0777, true);
file_put_contents($up . '/t.webp', 'x');
// OF_DATA_DIR 被重定向，UPLOAD_DIR 未定义时 put 应拒绝（不猜路径）
check('UPLOAD_DIR 未定义时 put 拒绝', media_mirror_put($up . '/t.webp') === false);

define('UPLOAD_DIR', $tmp . '/uploads');
check('UPLOAD_DIR 下真实文件才尝试（此例无网络可达 R2，靠假 endpoint 失败返回 false）', media_mirror_put($up . '/t.webp') === false);
check('不存在的文件拒绝', media_mirror_put($up . '/nope.webp') === false);
check('UPLOAD_DIR 之外的路径拒绝', media_mirror_put($tmp . '/media-mirror.json') === false);
check('路径逃逸（..）拒绝', media_mirror_put($tmp . '/../' . basename($tmp) . '/uploads/articles/../t.webp') === false);

// ── 6) fail-soft：异常配置不抛异常 ──
file_put_contents($tmp . '/media-mirror.json', '{broken json');
check('坏配置 JSON 不抛异常', media_mirror_put($up . '/t.webp') === false);
check('坏配置时 base 回退 /uploads', media_mirror_base() === '/uploads');

echo "\n" . ($failN === 0 ? "✓ 全部通过（{$pass} 项）" : "✗ {$failN} 项失败") . "\n";
@unlink($up . '/t.webp');
@rmdir($up);
@unlink($tmp . '/media-mirror.json');
@rmdir($tmp);
exit($failN === 0 ? 0 : 1);
