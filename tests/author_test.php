<?php
/**
 * 作者管理验收
 *
 *   php tests/author_test.php
 *
 * 重点不是"能存作者"，而是它真的把自由文本的乱账收干净：
 * 唯一性、别名、发现未登记作者、合并（改写文章署名 + 收别名）、按名统计。
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-author-' . getmypid());
@mkdir(DATA_DIR . '/articles', 0777, true);
@mkdir(DATA_DIR . '/courses', 0777, true);
@mkdir(DATA_DIR . '/skills', 0777, true);

function json_read(string $f): array { return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
function get_articles(): array { return json_read(DATA_DIR . '/articles/index.json'); }

// 抽取 AuthorSystem 函数（跳过顶部 require config）
$src = file_get_contents(__DIR__ . '/../lib/AuthorSystem.php');
function extract_fn(string $src, string $name): string {
    $at = strpos($src, "\nfunction {$name}(");
    if ($at === false) { fwrite(STDERR, "缺 {$name}\n"); exit(2); }
    $open = strpos($src, '{', $at); $depth = 0;
    for ($i = $open, $n = strlen($src); $i < $n; $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $at, $i - $at + 1); }
    }
    exit(2);
}
foreach (['authors_file','author_all','author_get','author_by_name','author_by_slug','author_slugify',
          'author_save','author_delete','author_content_counts','author_discover','author_merge'] as $fn) {
    eval(extract_fn($src, $fn));
}

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; }
    else     { $fail++; echo "  ✗ {$n}" . ($d ? "  → {$d}" : '') . "\n"; }
}

// 造内容：作者名有两种写法（张三 / 张 三）
json_write(DATA_DIR . '/articles/index.json', [
    ['id'=>'a1','author'=>'张三','status'=>'published'],
    ['id'=>'a2','author'=>'张三','status'=>'published'],
    ['id'=>'a3','author'=>'张 三','status'=>'published'],   // 拼写不一
    ['id'=>'a4','author'=>'李四','status'=>'published'],
    ['id'=>'a5','author'=>'','status'=>'published'],
]);
json_write(DATA_DIR . '/courses/index.json', [['id'=>'c1','author'=>'张三']]);
json_write(DATA_DIR . '/skills/index.json', [['id'=>'s1','author'=>'李四']]);
json_write(DATA_DIR . '/plugins.json', []);

echo "\n── 1. 建档与唯一性 ──\n";
$r = author_save(['name'=>'张三','title'=>'增长顾问','bio'=>'xx']);
check('创建成功', $r['ok'] === true);
check('生成 id 与 slug', !empty($r['author']['id']) && !empty($r['author']['slug']));
$dup = author_save(['name'=>'张三']);
check('重名被拒', $dup['ok'] === false, $dup['error'] ?? '');
check('空名被拒', author_save(['name'=>'  '])['ok'] === false);

echo "\n── 2. slug 生成 ──\n";
check('ASCII 名转 slug', author_slugify('John Doe') === 'john-doe');
check('中文名退化为短码', str_starts_with(author_slugify('张三'), 'au-'));

echo "\n── 3. 按名统计（连接既有内容）──\n";
$c = author_content_counts('张三');
check('文章数=2（只算精确同名，张 三 不算）', $c['articles'] === 2, (string)$c['articles']);
check('课程数=1', $c['courses'] === 1);
check('李四技能数=1', author_content_counts('李四')['skills'] === 1);

echo "\n── 4. 发现未登记作者 ──\n";
$disc = author_discover();
check('张三 已建档，不在发现列表', !isset($disc['张三']));
check('张 三 未建档，在列表', isset($disc['张 三']));
check('李四 未建档，在列表', isset($disc['李四']));
check('空作者名不算', !isset($disc['']));
check('发现项带统计', ($disc['李四']['skills'] ?? 0) === 1);

echo "\n── 5. 合并：改写署名 + 收别名（核心）──\n";
$zhang = author_by_name('张三');
$n = author_merge('张 三', $zhang['id']);
check('改写了 1 篇文章', $n === 1, (string)$n);
$arts = get_articles();
check('a3 的署名已改成 张三', ($arts[2]['author'] ?? '') === '张三');
check('合并后张三文章数=3', author_content_counts('张三')['articles'] === 3);
check('张 三 收作别名', in_array('张 三', author_by_name('张三')['aliases'] ?? [], true));
check('按别名也能定位到规范档案', (author_by_name('张 三')['id'] ?? '') === $zhang['id']);
check('合并后发现列表不再有 张 三', !isset(author_discover()['张 三']));

echo "\n── 6. 别名不与他人重名冲突/更新自身不算重名 ──\n";
$upd = author_save(array_merge($zhang, ['title'=>'首席增长官']));
check('更新自己不触发重名', $upd['ok'] === true);
check('职位已更新', author_get($zhang['id'])['title'] === '首席增长官');

echo "\n── 7. slug 解析 ──\n";
$bySlug = author_by_slug($zhang['slug']);
check('按 slug 取到', $bySlug && $bySlug['id'] === $zhang['id']);

echo "\n── 8. 删除档案不动内容 ──\n";
author_delete($zhang['id']);
check('档案已删', author_get($zhang['id']) === null);
check('文章署名仍在（内容不受影响）', count(array_filter(get_articles(), fn($a)=>($a['author']??'')==='张三')) === 3);

// 清理
$rm = function($d) use (&$rm){ foreach (glob($d.'/*')?:[] as $f){ is_dir($f)?$rm($f):@unlink($f);} @rmdir($d); };
$rm(DATA_DIR);

echo "\n" . str_repeat('─', 46) . "\n";
echo $fail === 0 ? "全部通过：{$pass} 项\n" : "通过 {$pass} 项，失败 {$fail} 项\n";
exit($fail === 0 ? 0 : 1);
