<?php
/**
 * T1-7 验收：图片自动 WebP + 多尺寸（ImageVariants）
 *   php tests/image_variants_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-img-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
require_once __DIR__ . '/../lib/ImageVariants.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

if (!imgvar_supported()) { echo "环境无 GD/webp，跳过\n"; exit(0); }

// 造一张 1200x600 的测试图
$src = DATA_DIR . '/test.jpg';
$im = imagecreatetruecolor(1200, 600);
imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 200));
imagejpeg($im, $src, 90);
imagedestroy($im);

echo "\n── 1. 生成多尺寸 webp ──\n";
$r = imgvar_generate($src, '/uploads/media');
check('ok=true', $r['ok'] === true, json_encode($r));
check('读到原尺寸 1200x600', $r['width'] === 1200 && $r['height'] === 600);
check('生成 480 档', isset($r['variants'][480]));
check('生成 960 档', isset($r['variants'][960]));
check('跳过 1920(不放大)', !isset($r['variants'][1920]), json_encode(array_keys($r['variants'])));
check('文件真的落盘', is_file(DATA_DIR . '/test-480.webp') && is_file(DATA_DIR . '/test-960.webp'));

echo "\n── 2. 产物确实是 webp 且尺寸正确 ──\n";
$info = @getimagesize(DATA_DIR . '/test-480.webp');
check('480 档宽=480', ($info[0] ?? 0) === 480, json_encode($info));
check('480 档高=240(等比)', ($info[1] ?? 0) === 240);
check('MIME=image/webp', ($info['mime'] ?? '') === 'image/webp', $info['mime'] ?? '');

echo "\n── 3. srcset 组装 ──\n";
check('srcset 含 480w', strpos($r['srcset'], '480w') !== false);
check('srcset 含 url 前缀', strpos($r['srcset'], '/uploads/media/test-480.webp') !== false, $r['srcset']);
check('升序排列', strpos($r['srcset'], '480w') < strpos($r['srcset'], '960w'));

echo "\n── 4. 小图：至少产一份 webp，不放大 ──\n";
$small = DATA_DIR . '/small.png';
$im2 = imagecreatetruecolor(200, 100); imagepng($im2, $small); imagedestroy($im2);
$r2 = imgvar_generate($small, '/u');
check('小图也 ok', $r2['ok'] === true);
check('只产原尺寸档 200', array_keys($r2['variants']) === [200], json_encode(array_keys($r2['variants'])));

echo "\n── 5. 容错 ──\n";
$r3 = imgvar_generate(DATA_DIR . '/nope.jpg');
check('文件不存在→ok=false 不抛', $r3['ok'] === false);
check('返回结构完整', isset($r3['variants']) && isset($r3['srcset']));

echo "\n── 6. img 标签渲染 ──\n";
$tag = imgvar_img_tag('/uploads/media/test.jpg', $r['variants'], '/uploads/media', '示例图');
check('含 srcset', strpos($tag, 'srcset=') !== false);
check('含 sizes', strpos($tag, 'sizes=') !== false);
check('含 lazy', strpos($tag, 'loading="lazy"') !== false);
check('alt 转义', strpos($tag, 'alt="示例图"') !== false);
$plain = imgvar_img_tag('/x.jpg', [], '', 'a"b');
check('无 variants 回落普通 img', strpos($plain, 'srcset') === false);
check('alt 引号被转义', strpos($plain, '&quot;') !== false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/*')); @rmdir(DATA_DIR);
exit($fail===0?0:1);
