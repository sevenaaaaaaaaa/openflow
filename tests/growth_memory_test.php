<?php
/**
 * T1-17 验收：大脑共享记忆层（GrowthMemory）
 *   php tests/growth_memory_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-mem-' . getmypid());
@mkdir(DATA_DIR . '/db', 0777, true);
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/GrowthMemory.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 主体归一 ──\n";
check('邮箱转小写', gmem_subject('  Z@T.com ') === 'z@t.com');
check('非邮箱只去空白', gmem_subject('  m1 ') === 'm1');

echo "\n── 2. 记忆写入 ──\n";
$r = gmem_remember('z@t.com', '首次咨询报价', ['kind'=>'interaction','source'=>'inbox']);
check('写入成功', $r['ok'] === true && $r['dup'] === false);
check('必填校验', (gmem_remember('', 'x')['ok'] ?? true) === false);
check('空摘要被拒', (gmem_remember('a@t.com', '')['ok'] ?? true) === false);
check('非法 kind 回落 interaction', gmem_recall('z@t.com')[0]['kind'] === 'interaction');

echo "\n── 3. 幂等：同 dedupe_key 只记一次 ──\n";
gmem_remember('z@t.com', '成交 ¥1000', ['kind'=>'outcome','dedupe_key'=>'conv:abc']);
$dup = gmem_remember('z@t.com', '成交 ¥1000', ['kind'=>'outcome','dedupe_key'=>'conv:abc']);
check('第二次返回 dup', $dup['dup'] === true);
check('只有 2 条记忆', count(gmem_recall('z@t.com')) === 2, (string)count(gmem_recall('z@t.com')));
gmem_remember('z@t.com', '另一件事', ['dedupe_key'=>'']);
check('空 dedupe 不去重', count(gmem_recall('z@t.com')) === 3);

echo "\n── 4. 大小写不同的主体视为同一人 ──\n";
gmem_remember('Z@T.COM', '大写邮箱也算他', []);
check('归一到同一主体', count(gmem_recall('z@t.com')) === 4);

echo "\n── 5. 按类型召回 + 顺序 ──\n";
$out = gmem_recall('z@t.com', 20, 'outcome');
check('只取 outcome', count($out) === 1 && $out[0]['kind'] === 'outcome');
check('detail 解析成数组', is_array(gmem_recall('z@t.com')[0]['detail']));
check('limit 生效', count(gmem_recall('z@t.com', 2)) === 2);
check('别人的记忆不串', count(gmem_recall('other@t.com')) === 0);

echo "\n── 6. 记忆摘要（喂决策/话术）──\n";
$brief = gmem_brief('z@t.com', 4);
check('含日期', strpos($brief, date('Y-m-d')) !== false);
check('含类型标签', strpos($brief, '[成交') !== false || strpos($brief, '[接触') !== false, $brief);
check('含摘要内容', strpos($brief, '首次咨询报价') !== false);
check('无记忆返回空串', gmem_brief('nobody@t.com') === '');

echo "\n── 7. 最近是否接触过（防重复打扰）──\n";
check('刚记过→7天内接触过', gmem_touched('z@t.com', 7) === true);
check('陌生人→没接触过', gmem_touched('nobody@t.com', 7) === false);
check('0 天窗口仍算今天', gmem_touched('z@t.com', 0) === true);

echo "\n── 8. 统计 ──\n";
$s = gmem_stats();
check('总数 4', $s['total'] === 4, (string)$s['total']);
check('主体数 1', $s['subjects'] === 1);
check('按类型分布', ($s['by_kind']['outcome'] ?? 0) === 1);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@unlink(DATA_DIR.'/db/openflow.db'); @array_map('unlink', glob(DATA_DIR.'/db/*'));
@rmdir(DATA_DIR.'/db'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
