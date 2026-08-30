<?php
/**
 * T1-3 验收：自然语言问数据（AskData）
 *
 *   php tests/askdata_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-ask-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { if (!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
require_once __DIR__ . '/../lib/AskData.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$snap = ['conversion_truth' => ['sources'=>[['key'=>'自然搜索','revenue'=>9000,'count'=>3]], 'total'=>['revenue'=>9000,'count'=>3]], 'counts'=>['members'=>120,'leads'=>45]];

echo "\n── 1. 空问题被拦 ──\n";
check('空问题 ok=false', (askdata_answer('', $snap)['ok'] ?? true) === false);

echo "\n── 2. 注入 AI：拿到问题+数据并作答 ──\n";
$GLOBALS['ASKDATA_FN'] = function($q, $data) {
    // 断言 AI 拿到了真实快照
    $rev = $data['conversion_truth']['total']['revenue'] ?? 0;
    return "针对「{$q}」：成交额 ¥{$rev}，会员 {$data['counts']['members']}。";
};
$r = askdata_answer('哪个来源最赚钱', $snap);
check('ok=true', $r['ok'] === true);
check('答案含问题', strpos($r['answer'], '哪个来源最赚钱') !== false);
check('答案含真实数字 9000', strpos($r['answer'], '9000') !== false);
check('答案含会员数 120', strpos($r['answer'], '120') !== false);
check('返回 data 快照', isset($r['data']['counts']));

echo "\n── 3. 未配 AI（无注入、无 AiCenter）→ 优雅提示 + 附快照 ──\n";
unset($GLOBALS['ASKDATA_FN']);
$r2 = askdata_answer('随便问', $snap);
check('ok=false', $r2['ok'] === false);
check('给出配置提示', strpos($r2['error'] ?? '', 'AI') !== false);
check('仍附快照', isset($r2['data']));

echo "\n── 4. gather 容错（无数据源不炸）──\n";
$g = askdata_gather();
check('gather 返回数组', is_array($g));

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
