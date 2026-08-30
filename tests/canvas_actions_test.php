<?php
/**
 * T1-2 验收：画布关键动作（tag/score/stage/webhook）
 *
 *   php tests/canvas_actions_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-canvas-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { if (!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
function notify(...$a) {}

// 桩：CDP + CRM
$GLOBALS['CUST'] = ['c1' => ['id'=>'c1','score'=>10]];
$GLOBALS['TAGS'] = []; $GLOBALS['SCORE'] = null; $GLOBALS['LEADS'] = [];
function cdp_find($email,$m='',$u=''){ return $email==='a@t.com' ? $GLOBALS['CUST']['c1'] : null; }
function cdp_get_by_id($id){ return $GLOBALS['CUST'][$id] ?? null; }
function cdp_add_tag($id,$tag){ $GLOBALS['TAGS'][] = "$id:$tag"; }
function cdp_set_score($id,$s){ $GLOBALS['SCORE'] = $s; $GLOBALS['CUST'][$id]['score']=$s; }
function crm_ensure_lead($email,$n='',$p=''){ if(!isset($GLOBALS['LEADS'][$email])) $GLOBALS['LEADS'][$email]=['email'=>$email,'tags'=>[],'stage'=>'new']; return $GLOBALS['LEADS'][$email]; }
function crm_update_lead($email,$u){ $GLOBALS['LEADS'][$email] = array_merge($GLOBALS['LEADS'][$email]??[], $u); }

require_once __DIR__ . '/../lib/CanvasSystem.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }
$ctx = ['email'=>'a@t.com','member_id'=>'m1','uid'=>'v1'];

echo "\n── 1. 动作类型清单含新动作 ──\n";
$types = canvas_action_types();
foreach (['tag','score','stage','webhook'] as $t) check("含 {$t}", isset($types[$t]));

echo "\n── 2. 打标签：CDP + CRM 都打 ──\n";
canvas_action_tag(['tag'=>'高意向'], $ctx);
check('CDP 打标 c1:高意向', in_array('c1:高意向', $GLOBALS['TAGS'], true));
check('CRM 线索也打标', in_array('高意向', $GLOBALS['LEADS']['a@t.com']['tags'] ?? []));
canvas_action_tag(['tag'=>''], $ctx);
check('空标签不动作', count($GLOBALS['TAGS']) === 1);

echo "\n── 3. 加分：CDP score 累加 ──\n";
canvas_action_score(['score'=>15], $ctx);   // 10 + 15
check('score 累加到 25', $GLOBALS['SCORE'] === 25, (string)$GLOBALS['SCORE']);
canvas_action_score(['score'=>0], $ctx);
check('加 0 分不动作', $GLOBALS['SCORE'] === 25);

echo "\n── 4. 改 CRM 阶段 ──\n";
canvas_action_stage(['stage'=>'won'], $ctx);
check('阶段改为 won', ($GLOBALS['LEADS']['a@t.com']['stage'] ?? '') === 'won');
$GLOBALS['LEADS'] = [];
canvas_action_stage(['stage'=>'won'], ['email'=>'']);
check('无邮箱不动作', empty($GLOBALS['LEADS']));

echo "\n── 5. Webhook 守卫（非法 URL 不炸）──\n";
canvas_action_webhook(['url'=>'not-a-url'], $ctx);
check('非法 URL 安全跳过', true);   // 不抛异常即通过
canvas_action_webhook(['url'=>''], $ctx);
check('空 URL 安全跳过', true);

echo "\n── 6. 无匹配客户时打标不炸 ──\n";
canvas_action_tag(['tag'=>'x'], ['email'=>'nobody@t.com']);
check('无 CDP 客户仍安全(未新增 CDP 标)', count(array_filter($GLOBALS['TAGS'], fn($t)=>strpos($t,'x')!==false)) === 0);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@rmdir(DATA_DIR);
exit($fail===0?0:1);
