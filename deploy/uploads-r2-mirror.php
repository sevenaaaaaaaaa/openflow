<?php
declare(strict_types=1);
/**
 * uploads/ → R2 存量回填（在服务器上跑）：
 *
 *   php deploy/uploads-r2-mirror.php            # 扫 uploads/ 全部文件，逐个镜像
 *   php deploy/uploads-r2-mirror.php --verify=5 # 随机抽 N 个，CDN GET 校验内容一致
 *
 * 依赖 lib/MediaMirror.php 的配置（data/media-mirror.json）。
 * 幂等：重复跑只会重复 PUT 相同内容（R2 覆盖写），安全。
 */

require_once __DIR__ . '/../lib/MediaMirror.php';

$root = (defined('UPLOAD_DIR') ? UPLOAD_DIR : dirname(__DIR__) . '/uploads');

if (isset($argv[1]) && str_starts_with($argv[1], '--verify')) {
    $n = (int) (explode('=', $argv[1])[1] ?? 5);
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->isFile()) $files[] = $f->getPathname(); }
    shuffle($files);
    $files = array_slice($files, 0, $n);
    $site = rtrim((string) (getenv('SITE_URL') ?: 'https://nownexts.com'), '/');
    $ok = 0;
    foreach ($files as $f) {
        $rel = ltrim(substr($f, strlen($root) + 1), '/');
        $key = media_mirror_key($rel);
        if ($key === '') { echo "✗ 键无效: $rel\n"; continue; }
        $url = $site . '/assets/' . $key;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $same = ($code === 200 && md5($body) === md5_file($f));
        echo ($same ? '✓' : '✗') . " HTTP $code " . ($same ? '内容一致' : '内容不一致') . "  $rel\n";
        if ($same) $ok++;
    }
    echo $ok === count($files) ? "✓ 抽验 $ok/" . count($files) . " 全部一致\n" : "⚠ 抽验 $ok/" . count($files) . "\n";
    exit($ok === count($files) ? 0 : 1);
}

if (!is_dir($root)) { fwrite(STDERR, "✗ uploads 目录不存在: $root\n"); exit(2); }
$cfg = media_mirror_cfg();
if (!$cfg['enabled']) { fwrite(STDERR, "✗ 未启用：data/media-mirror.json 里 enabled=true 后再跑\n"); exit(2); }

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) { if ($f->isFile()) $files[] = $f->getPathname(); }
sort($files);
echo 'uploads 共 ' . count($files) . " 个文件，开始镜像…\n";
$ok = 0; $fail = [];
$t0 = microtime(true);
foreach ($files as $i => $f) {
    if (media_mirror_put($f)) { $ok++; }
    else { $fail[] = substr($f, strlen($root) + 1); }
    if (($i + 1) % 50 === 0) echo '  进度 ' . ($i + 1) . '/' . count($files) . "\n";
}
printf("✓ 成功 %d / %d（%.1fs）\n", $ok, count($files), microtime(true) - $t0);
if ($fail) {
    echo "✗ 失败 " . count($fail) . " 个：\n";
    foreach (array_slice($fail, 0, 10) as $r) echo "   - $r\n";
    exit(1);
}
