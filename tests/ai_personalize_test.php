<?php
/**
 * T1-1 验收：规则个性化 → AI 个性化（AiPersonalize）
 *
 *   php tests/ai_personalize_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-aip-' . getmypid());
@mkdir(DATA_DIR . '/cdp', 0777, true);
function json_read(string $f): array { if (!is_file($f)) return []; $d = json_decode((string)file_get_contents($f), true); return is_array($d) ? $d : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool)file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/AiPersonalize.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = '') { global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$base = ['title' => '预约增长诊断', 'desc' => '30 分钟了解你的增长机会', 'action' => 'form'];
$pref = ['tags' => ['SEO'=>8,'增长'=>5], 'member_level' => '', 'total_spent' => 0, 'source' => 'google'];

echo "\n── 1. 未开启 → 原样返回（行为不变）──\n";
check('返回 base 未变', ai_personalize_cta($base, $pref) === $base);

echo "\n── 2. 开启 + 注入 AI → 改写文案、保留 action ──\n";
json_write(DATA_DIR . '/settings.json', ['personalize' => ['ai_cta' => true]]);
$GLOBALS['CALLS'] = 0;
$GLOBALS['AI_PERSONALIZE_FN'] = function($ctx) { $GLOBALS['CALLS']++; return ['title' => 'SEO 增长诊断', 'desc' => '专为做 SEO 的你']; };
$r = ai_personalize_cta($base, $pref);
check('title 被 AI 改写', $r['title'] === 'SEO 增长诊断');
check('desc 被 AI 改写', $r['desc'] === '专为做 SEO 的你');
check('action 保留', $r['action'] === 'form');
check('标记 ai=true', ($r['ai'] ?? false) === true);
check('调用了 1 次 AI', $GLOBALS['CALLS'] === 1);

echo "\n── 3. 相同签名命中缓存，不再打 AI ──\n";
$r2 = ai_personalize_cta($base, $pref);
check('文案一致', $r2['title'] === 'SEO 增长诊断');
check('未再调 AI(仍 1 次)', $GLOBALS['CALLS'] === 1, 'calls=' . $GLOBALS['CALLS']);

echo "\n── 4. 不同画像 → 不同签名，重新生成 ──\n";
$pref2 = ['tags' => ['财务'=>8], 'member_level' => 'vip', 'total_spent' => 2000];
$base2 = ['title' => '解锁高级内容', 'desc' => 'x', 'action' => 'upgrade'];
ai_personalize_cta($base2, $pref2);
check('新签名触发新调用(2 次)', $GLOBALS['CALLS'] === 2);
check('签名随画像变化', ai_personalize_signature($base, $pref) !== ai_personalize_signature($base2, $pref2));

echo "\n── 5. AI 返回空 → 回落 base ──\n";
$GLOBALS['AI_PERSONALIZE_FN'] = function($ctx) { return []; };
$base3 = ['title' => 'T', 'desc' => 'D', 'action' => 'form'];
$pref3 = ['tags' => ['独特标签'=>9], 'total_spent' => 0];
check('空返回→原样', ai_personalize_cta($base3, $pref3) === $base3);

echo "\n── 6. 关闭开关 → 立即回落 ──\n";
json_write(DATA_DIR . '/settings.json', ['personalize' => ['ai_cta' => false]]);
check('关闭后原样', ai_personalize_cta($base, $pref) === $base);

echo "\n";
echo $fail === 0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/cdp/*')); @unlink(DATA_DIR.'/settings.json');
@rmdir(DATA_DIR.'/cdp'); @rmdir(DATA_DIR);
exit($fail === 0 ? 0 : 1);
