<?php
/**
 * T1-10 验收：统一会话收件箱 + 站点 Agent（UnifiedInbox / SiteAgent）
 *   php tests/inbox_agent_test.php
 */
define('DATA_DIR', sys_get_temp_dir() . '/of-inbox-' . getmypid());
@mkdir(DATA_DIR, 0777, true);
function json_read(string $f): array { if(!is_file($f)) return []; $d=json_decode((string)file_get_contents($f),true); return is_array($d)?$d:[]; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f),0777,true); return (bool)file_put_contents($f,json_encode($d,JSON_UNESCAPED_UNICODE)); }
$GLOBALS['LEADS'] = [];
function crm_ensure_lead($email,$n='',$p=''){ $GLOBALS['LEADS'][$email] = ['email'=>$email,'name'=>$n]; return $GLOBALS['LEADS'][$email]; }
function crm_update_lead($email,$u){ $GLOBALS['LEADS'][$email] = array_merge($GLOBALS['LEADS'][$email]??[], $u); }
require_once __DIR__ . '/../lib/UnifiedInbox.php';
require_once __DIR__ . '/../lib/SiteAgent.php';

$pass=0;$fail=0;
function check(string $n,bool $ok,string $d=''){ global $pass,$fail; if($ok){$pass++;echo "  ✓ {$n}\n";}else{$fail++;echo "  ✗ {$n}".($d?"  → {$d}":'')."\n";} }

$inject = [
    'form' => [['id'=>'f1','created_at'=>'2026-08-30 10:00:00','data'=>['name'=>'张三','email'=>'z@t.com','message'=>'想了解报价']]],
    'comment' => [['id'=>'c1','author'=>'李四','content'=>'这个功能怎么用？','created_at'=>'2026-08-30 12:00:00','target_type'=>'article','target_id'=>'a1']],
    'message' => [
        ['id'=>'m1','direction'=>'in','from_name'=>'王五','from_email'=>'w@t.com','content'=>'能开发票吗','created_at'=>'2026-08-30 09:00:00'],
        ['id'=>'m2','direction'=>'out','content'=>'系统通知','created_at'=>'2026-08-30 13:00:00'],
    ],
    'consultation' => [['id'=>'b1','name'=>'赵六','email'=>'zh@t.com','topic'=>'增长诊断','created_at'=>'2026-08-30 11:00:00']],
];

echo "\n── 1. 多来源聚合 + 时间倒序 ──\n";
$all = uinbox_all($inject);
check('聚合 4 条(出站站内信被排除)', count($all) === 4, '数量=' . count($all));
check('时间倒序：最新是评论', $all[0]['source'] === 'comment', $all[0]['source']);
check('uid 带来源前缀', $all[0]['uid'] === 'comment:c1');
check('表单字段映射', count(array_filter($all, fn($i)=>$i['source']==='form' && $i['email']==='z@t.com')) === 1);
check('评论带出处链接', $all[0]['link'] === '/article/a1', $all[0]['link']);
check('默认状态 open', $all[0]['status'] === 'open');

echo "\n── 2. 过滤与计数 ──\n";
check('按来源过滤', count(uinbox_filter($all, 'form')) === 1);
check('按状态过滤 open=4', count(uinbox_filter($all, '', 'open')) === 4);
$c = uinbox_counts($all);
check('计数 open=4', $c['open'] === 4);

echo "\n── 3. 状态标记 ──\n";
check('标记 done', uinbox_set_state('form:f1', 'done') === true);
check('非法状态被拒', uinbox_set_state('form:f1', 'bogus') === false);
$all2 = uinbox_all($inject);
$f1 = array_values(array_filter($all2, fn($i)=>$i['uid']==='form:f1'))[0];
check('状态已持久化', $f1['status'] === 'done');
check('open 计数降为 3', uinbox_counts($all2)['open'] === 3);

echo "\n── 4. 转 CRM 线索 ──\n";
$r = uinbox_to_lead(['uid'=>'comment:c1','email'=>'lisi@t.com','name'=>'李四','content'=>'咨询','source'=>'comment']);
check('转线索成功', $r['ok'] === true, $r['error'] ?? '');
check('线索已建', isset($GLOBALS['LEADS']['lisi@t.com']));
check('来源写入', strpos($GLOBALS['LEADS']['lisi@t.com']['source'] ?? '', '客服') === 0);
check('自动标记已处理', uinbox_state()['comment:c1']['status'] === 'done');
check('无邮箱→拒绝', (uinbox_to_lead(['uid'=>'x','email'=>''])['ok'] ?? true) === false);

echo "\n── 5. 站点 Agent：知识不足转人工 ──\n";
$GLOBALS['SITEAGENT_KB_FN'] = function($q,$l){ return []; };
$a1 = siteagent_answer('你们支持火星配送吗');
check('handoff=true', $a1['handoff'] === true);
check('不硬答', strpos($a1['answer'], '没找到') !== false);
check('带 CTA', !empty($a1['cta']));

echo "\n── 6. 有知识 + AI → 现答不转人工 ──\n";
$GLOBALS['SITEAGENT_KB_FN'] = function($q,$l){ return [['title'=>'退款政策','content'=>'7 天内可全额退款','url'=>'/article/refund']]; };
$GLOBALS['SITEAGENT_AI_FN'] = function($q,$kb){ return '7 天内可以全额退款。'; };
$a2 = siteagent_answer('能退款吗');
check('AI 现答', $a2['answer'] === '7 天内可以全额退款。');
check('不转人工', $a2['handoff'] === false);
check('带来源出处', ($a2['sources'][0]['title'] ?? '') === '退款政策');

echo "\n── 7. 有知识但无 AI → 给片段 + 转人工 ──\n";
unset($GLOBALS['SITEAGENT_AI_FN']);
$a3 = siteagent_answer('能退款吗');
check('给出片段', strpos($a3['answer'], '退款政策') !== false);
check('仍转人工(不假装能聊)', $a3['handoff'] === true);

echo "\n── 8. Agent 转人工落线索 ──\n";
check('非法邮箱被拒', (siteagent_handoff('q','not-an-email')['ok'] ?? true) === false);
$h = siteagent_handoff('能退款吗', 'agent@t.com', '访客');
check('转人工成功', $h['ok'] === true);
check('线索来源=站点Agent', ($GLOBALS['LEADS']['agent@t.com']['source'] ?? '') === '站点Agent');
check('空问题被拦', (siteagent_answer('')['ok'] ?? true) === false);

echo "\n";
echo $fail===0 ? "✅ 全部通过（{$pass}）\n" : "❌ 失败 {$fail} / 通过 {$pass}\n";
@unlink(DATA_DIR.'/inbox-state.json'); @rmdir(DATA_DIR);
exit($fail===0?0:1);
