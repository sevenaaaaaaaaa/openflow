<?php
/**
 * T0-3 验收：内容多语言（ContentI18n）
 *
 *   php tests/content_i18n_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-ci18n-' . getmypid());
@mkdir(DATA_DIR, 0777, true);

// 桩：json 存储 + 文章存储（i18n_* 用真的 lib/I18n.php，默认 zh-CN / zh-CN,en,ja）
function json_read(string $f): array { if (!is_file($f)) return []; $d = json_decode((string)file_get_contents($f), true); return is_array($d) ? $d : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
$GLOBALS['STORE'] = [];
function get_article(string $id): ?array { return $GLOBALS['STORE'][$id] ?? null; }
function save_article(string $id, array $a): bool { $GLOBALS['STORE'][$id] = $a; return true; }

require_once __DIR__ . '/../lib/ContentI18n.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$art = ['id'=>'a1','slug'=>'hello','title'=>'你好世界','content'=>'<p>中文正文</p>','seo_title'=>'你好','author'=>'张三','cover'=>'/x.png'];
$GLOBALS['STORE']['a1'] = $art;

echo "\n── 1. 无译文时 locales 只有 base ──\n";
check('locales=[zh-CN]', ci18n_locales($art) === ['zh-CN']);
check('resolve base 原样', ci18n_resolve($art, 'zh-CN')['title'] === '你好世界');
check('resolve en 缺译→回落 base', ci18n_resolve($art, 'en')['title'] === '你好世界');

echo "\n── 2. 写入 en 译文（已发布）──\n";
$r = ci18n_set('a1', 'en', ['title'=>'Hello World','content'=>'<p>English body</p>','seo_title'=>'Hello','status'=>'published']);
check('写入成功', $r['ok'] === true, $r['error'] ?? '');
$art = get_article('a1');
check('locales 含 en', ci18n_locales($art) === ['zh-CN','en'], implode(',',ci18n_locales($art)));

echo "\n── 3. resolve 按 locale 覆盖字段、保留其它 ──\n";
$en = ci18n_resolve($art, 'en');
check('title 用译文', $en['title'] === 'Hello World');
check('content 用译文', $en['content'] === '<p>English body</p>');
check('seo_title 用译文', $en['seo_title'] === 'Hello');
check('cover 保持 base', $en['cover'] === '/x.png');
check('author 保持 base', $en['author'] === '张三');
check('_locale 标记', ($en['_locale'] ?? '') === 'en');

echo "\n── 4. 草稿译文不对前台生效 ──\n";
ci18n_set('a1', 'ja', ['title'=>'こんにちは','content'=>'x','status'=>'draft']);
$art = get_article('a1');
check('ja 有译文但草稿', isset(ci18n_translations($art)['ja']));
check('resolve ja 草稿→回落 base', ci18n_resolve($art, 'ja')['title'] === '你好世界');
check('locales 仍含 ja(有 title)', in_array('ja', ci18n_locales($art), true));

echo "\n── 5. hreflang 输出 ──\n";
$h = ci18n_hreflang($art, 'https://demo.test');
check('含 base zh-cn', strpos($h, 'hreflang="zh-cn"') !== false);
check('含 en 且带 /en/ 前缀', strpos($h, 'hreflang="en"') !== false && strpos($h, 'https://demo.test/en/article/hello') !== false);
check('含 x-default', strpos($h, 'hreflang="x-default"') !== false);
check('base 无前缀', strpos($h, 'https://demo.test/article/hello') !== false);

echo "\n── 6. AI 未配置时优雅失败 ──\n";
$r = ci18n_ai_translate($art, 'en');   // 无 AiCenter 类
check('AI 未配置返回 ok=false', ($r['ok'] ?? true) === false);

echo "\n── 7. 删除译文 + 不能给 base 建译文 ──\n";
check('删除 en 命中', ci18n_delete('a1', 'en') === true);
check('en 已删', !isset(ci18n_translations(get_article('a1'))['en']));
check('给 base 建译文被拒', (ci18n_set('a1', 'zh-CN', ['title'=>'x'])['ok'] ?? true) === false);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
