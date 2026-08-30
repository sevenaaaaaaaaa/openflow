<?php
/**
 * T1-16 验收：贡献自动三通（ContributionPipeline）
 *   php tests/contribution_pipeline_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-cp-' . getmypid());
@mkdir(DATA_DIR . '/ecosystem', 0777, true);
@mkdir(DATA_DIR . '/knowledge', 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
require_once __DIR__ . '/../lib/KnowledgeSystem.php';
require_once __DIR__ . '/../lib/ContributionPipeline.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$item = ['kind'=>'skill','id'=>'s1','title'=>'标题优化器','summary'=>'把标题改写得更吸引点击','author'=>'m1','tags'=>['SEO','写作'],'url'=>'/marketplace?skill=s1'];

echo "\n── 1. 默认三腿全开 ──\n";
$ch = contrib_channels($item);
check('知识库开', $ch['knowledge'] === true);
check('MCP 开', $ch['mcp'] === true);
check('分发开', $ch['distribute'] === true);

echo "\n── 2. 一次发布，三腿同时接上 ──\n";
$r = contrib_publish($item);
check('返回 ok', $r['ok'] === true);
check('①进知识库', $r['channels']['knowledge'] === true);
check('②暴露 MCP', $r['channels']['mcp'] === true);
check('③纳入分发', $r['channels']['distribute'] === true);
$kb = knowledge_get();
check('知识库真有记录', count(array_filter($kb, fn($d)=>($d['source']??'')==='contribution')) === 1);
check('分发池真有记录', count(contrib_dist_all()) === 1);

echo "\n── 3. 幂等：再发布不重复 ──\n";
contrib_publish($item);
check('分发池仍 1 条', count(contrib_dist_all()) === 1);
check('知识库仍 1 条', count(array_filter(knowledge_get(), fn($d)=>($d['source']??'')==='contribution')) === 1);
$upd = array_merge($item, ['title'=>'标题优化器 v2']);
contrib_publish($upd);
check('原地更新标题', contrib_dist_all()[0]['title'] === '标题优化器 v2');
check('保留 created_at', !empty(contrib_dist_all()[0]['created_at']));

echo "\n── 4. 可关不可缺：关掉某一腿 ──\n";
$noMcp = array_merge($item, ['id'=>'s2','channels'=>['mcp'=>false]]);
$r2 = contrib_publish($noMcp);
check('MCP 关掉', $r2['channels']['mcp'] === false);
check('其它腿仍开', $r2['channels']['knowledge'] === true && $r2['channels']['distribute'] === true);
check('MCP 清单不含它', count(array_filter(contrib_mcp_list(), fn($x)=>$x['id']==='s2')) === 0);
check('MCP 清单含 s1', count(array_filter(contrib_mcp_list(), fn($x)=>$x['id']==='s1')) === 1);

echo "\n── 5. 关掉分发 → 从池移除 ──\n";
$off = array_merge($item, ['channels'=>['distribute'=>false]]);
$r3 = contrib_distribute($off);
check('返回 removed', ($r3['removed'] ?? false) === true);
check('池中已无 s1', count(array_filter(contrib_dist_all(), fn($x)=>$x['id']==='s1')) === 0);
check('缺 kind/id 被拒', (contrib_distribute(['title'=>'x'])['ok'] ?? true) === false);

echo "\n── 6. 平台分发：按兴趣挑对的东西 ──\n";
$pool = [
    ['uid'=>'skill:a','kind'=>'skill','id'=>'a','title'=>'SEO 关键词工具','summary'=>'做关键词研究','tags'=>['SEO'],'updated_at'=>'2026-08-01'],
    ['uid'=>'skill:b','kind'=>'skill','id'=>'b','title'=>'配色助手','summary'=>'挑颜色','tags'=>['设计'],'updated_at'=>'2026-08-30'],
    ['uid'=>'skill:c','kind'=>'skill','id'=>'c','title'=>'写作提纲','summary'=>'SEO 友好的提纲','tags'=>['写作'],'updated_at'=>'2026-08-10'],
];
$rec = contrib_recommend(['SEO'], 5, $pool);
check('SEO 兴趣命中 2 条排前', in_array($rec[0]['id'], ['a','c'], true) && in_array($rec[1]['id'], ['a','c'], true), json_encode(array_column($rec,'id')));
check('无关的排后', $rec[2]['id'] === 'b');
$recNone = contrib_recommend([], 2, $pool);
check('无兴趣→按最新回落', $recNone[0]['id'] === 'b', json_encode(array_column($recNone,'id')));
check('limit 生效', count(contrib_recommend(['SEO'], 1, $pool)) === 1);
check('空池返回空', contrib_recommend(['x'], 5, []) === []);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/ecosystem/*')); @array_map('unlink', glob(DATA_DIR.'/knowledge/*'));
@rmdir(DATA_DIR.'/ecosystem'); @rmdir(DATA_DIR.'/knowledge'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
