<?php
/**
 * 写路径回归：埋点改成单行 INSERT 之后，语义有没有变。
 *   php tests/track_writepath_test.php
 *
 * 这个测试的存在理由：track() 是全系统最热也最不能出错的一条路径。
 * 把「先读一万条再整批写回」改成「直接加一行」，性能从 134ms 降到 0.4ms，
 * 但只有性能对是不够的——事件要真的落库、画像要跟着更新、身份合并要照旧、
 * 分群计数从内存扫描改成 SQL COUNT 后结果要一模一样。这里逐条钉住。
 *
 * 在临时 DATA_DIR 上跑，不碰任何真实数据。
 */
$tmp = sys_get_temp_dir().'/of-vfy-'.getmypid();
@mkdir($tmp.'/cdp',0777,true);
putenv('OF_DATA_DIR='.$tmp); putenv('OF_UPLOAD_DIR='.$tmp.'/uploads');
$_SERVER['REQUEST_URI']='/v'; $_SERVER['HTTP_HOST']='localhost'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
$p=0;$f=0; function ck($n,$ok,$d=''){global $p,$f; if($ok){$p++;echo "  ✓ $n\n";}else{$f++;echo "  ✗ $n".($d?" → $d":'')."\n";}}

echo "\n── 事件真的写进去了吗 ──\n";
// 注意：带 email 会触发身份合并，uid 变成 canonical id（既有语义，非本次改动）
CdpSystem::track('signup',['plan'=>'pro'],'v_1');
$r=Database::query("SELECT * FROM events WHERE uid='v_1'");
ck('单条已入库', count($r)===1, count($r).' 行');
ck('事件名正确', ($r[0]['event']??'')==='signup');
ck('属性完整', strpos($r[0]['props']??'','pro')!==false, $r[0]['props']??'');
ck('时间戳非空', !empty($r[0]['created_at']) && (int)($r[0]['ts']??0)>0);
ck('message_id 非空(去重键)', !empty($r[0]['message_id']));

echo "\n── 批量 ──\n";
$b=[]; for($i=0;$i<7;$i++) $b[]=['event'=>'click','properties'=>['n'=>$i],'visitor_id'=>'v_2'];
$n=CdpSystem::trackBatch($b);
ck('返回计数正确', $n===7, (string)$n);
ck('7 条都入库', count(Database::query("SELECT id FROM events WHERE uid='v_2'"))===7);

echo "\n── 画像同步更新了吗 ──\n";
require_once __DIR__ . '/../lib/CdpProfileStore.php';
$pr=cdp_profile_get('v_2');
ck('画像存在', is_array($pr));
ck('事件计数=7', (int)($pr['events_count']??0)===7, (string)($pr['events_count']??'null'));

echo "\n── 分群计数走 SQL 后结果对不对 ──\n";
$m=new ReflectionMethod('CdpSystem','countUserEvents'); $m->setAccessible(true);
ck('count(v_2,click)=7', $m->invoke(null,'v_2','click',0)===7, (string)$m->invoke(null,'v_2','click',0));
ck('count(v_2,signup)=0', $m->invoke(null,'v_2','signup',0)===0);
ck('count(v_1,signup)=1', $m->invoke(null,'v_1','signup',0)===1);
ck('时间窗内计数=7', $m->invoke(null,'v_2','click',7)===7);
ck('windowDays<=0 视为不限窗（与旧实现一致）', $m->invoke(null,'v_2','click',0)===7);

echo "\n── 身份合并后事件跟着走 canonical id ──\n";
CdpSystem::track('signup',['email'=>'a@t.com'],'v_3');
$canon=Database::query("SELECT uid FROM events WHERE event='signup' AND uid != 'v_1'");
ck('合并后另起 canonical uid', count($canon)===1 && strpos($canon[0]['uid'],'usr_')===0, json_encode($canon));

echo "\n── allEvents 读回来还是老格式吗 ──\n";
$ev=CdpSystem::allEvents(50);
ck('能读回', count($ev)>=8, (string)count($ev));
$last=end($ev);
ck('字段名兼容(visitor_id/properties/timestamp)', isset($last['visitor_id'],$last['properties'],$last['timestamp']));

echo "\n";
echo $f===0 ? "✅ 全部通过（{$p}）\n" : "❌ 失败 {$f} / 通过 {$p}\n";
exec('rm -rf '.escapeshellarg($tmp));
exit($f===0?0:1);
