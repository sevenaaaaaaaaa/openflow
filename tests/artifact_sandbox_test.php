<?php
/**
 * T2-9 验收：生成物安全体系（ArtifactSandbox）
 *   php tests/artifact_sandbox_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-sbx-' . getmypid());
@mkdir(DATA_DIR . '/ecosystem', 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
function knowledge_search(string $q, int $n=3): array { return [['title'=>'退款政策','url'=>'/a/refund','content'=>'7天退款']]; }
require_once __DIR__ . '/../lib/ArtifactSandbox.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

echo "\n── 1. 权限模型：白名单 ──\n";
check('已知能力通过', sandbox_check_permissions(['text.template'])['ok'] === true);
$bad = sandbox_check_permissions(['text.template','system.exec']);
check('未知能力被拒', $bad['ok'] === false);
check('指出是哪个', $bad['unknown'] === ['system.exec']);

echo "\n── 2. 最小权限：未声明即拒 ──\n";
$art = ['id'=>'a1','permissions'=>['text.template'],'content'=>'你好 {name}'];
check('已声明能力可跑', sandbox_run($art, 'text.template', ['vars'=>['name'=>'张三']])['ok'] === true);
$deny = sandbox_run($art, 'content.search', ['q'=>'退款']);
check('未声明能力被拒', $deny['ok'] === false);
check('提示最小权限', strpos($deny['error'], '最小权限') !== false);
check('白名单外能力被拒', sandbox_run($art, 'system.exec')['ok'] === false);

echo "\n── 3. 模板渲染：变量替换 + 转义 ──\n";
$r = sandbox_run($art, 'text.template', ['vars'=>['name'=>'<script>x</script>']]);
check('渲染成功', $r['ok'] === true);
check('注入被转义', strpos($r['result'], '&lt;script&gt;') !== false, $r['result']);
$r2 = sandbox_run($art, 'text.template', ['vars'=>[]]);
check('缺变量置空不报错', $r2['ok'] === true && strpos($r2['result'], '{name}') === false);

echo "\n── 4. 受控检索 ──\n";
$art2 = ['id'=>'a2','permissions'=>['content.search']];
$s = sandbox_run($art2, 'content.search', ['q'=>'退款']);
check('检索成功', $s['ok'] === true);
check('只返回标题与链接', array_keys($s['result'][0]) === ['title','url']);
check('空查询被拒', sandbox_run($art2, 'content.search', ['q'=>''])['ok'] === false);

echo "\n── 5. 数值计算守卫 ──\n";
$art3 = ['id'=>'a3','permissions'=>['math.compute']];
check('加法', sandbox_run($art3,'math.compute',['fn'=>'add','a'=>2,'b'=>3])['result'] === 3.0+2.0);
check('除零被拦', sandbox_run($art3,'math.compute',['fn'=>'div','a'=>1,'b'=>0])['ok'] === false);
check('不支持的运算被拒', sandbox_run($art3,'math.compute',['fn'=>'pow'])['ok'] === false);

echo "\n── 6. 审核队列 ──\n";
$q1 = sandbox_enqueue(['id'=>'sk1','title'=>'导出工具','permissions'=>['content.search']], ['verdict'=>'review','notes'=>['申请了检索权限']], 'm1');
check('入队成功', $q1['ok'] === true && $q1['dup'] === false);
$dup = sandbox_enqueue(['id'=>'sk1','title'=>'导出工具'], ['verdict'=>'review'], 'm1');
check('同生成物不重复排队', $dup['dup'] === true);
check('待审 1 条', count(sandbox_pending()) === 1);
check('缺标识被拒', (sandbox_enqueue([], ['verdict'=>'review'])['ok'] ?? true) === false);

echo "\n── 7. 人工裁决留痕 ──\n";
$qid = $q1['item']['id'];
check('非法决定被拒', (sandbox_decide($qid, 'maybe')['ok'] ?? true) === false);
$d = sandbox_decide($qid, 'approve', '管理员A', '权限合理');
check('批准成功', $d['ok'] === true);
check('记录裁决人', $d['item']['decided_by'] === '管理员A');
check('记录备注', $d['item']['decision_note'] === '权限合理');
check('已处理不能重复裁决', (sandbox_decide($qid, 'reject')['ok'] ?? true) === false);
check('待审清零', count(sandbox_pending()) === 0);
$st = sandbox_queue_stats();
check('统计已批准 1', $st['approved'] === 1 && $st['pending'] === 0);
check('未知 id 裁决失败', (sandbox_decide('nope','approve')['ok'] ?? true) === false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@array_map('unlink', glob(DATA_DIR.'/ecosystem/*')); @rmdir(DATA_DIR.'/ecosystem'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
