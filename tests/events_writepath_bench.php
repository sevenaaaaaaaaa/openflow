<?php
/**
 * 写放大实测：CdpSystem::track() 的热路径到底花多少（AUDIT-08 L0-4）
 *
 *   php tests/events_writepath_bench.php [行数]      默认 520000（生产量级）
 *
 * 为什么要有这个脚本：EVOLUTION.md 认为瓶颈是"数据访问散落各处"，开的药方是
 * 加一层 DataStore 抽象。但事件表**早就在 SQLite 上了**，性能并没有变好——
 * 因为病不在存储，在访问模式：track() 每记一条事件，先把最近一万条整批读进
 * 内存（含一万次 json_decode），再把末尾 200 条重复 INSERT 一遍。
 *
 * 抽象层不治这个病。所以先量，再决定建什么。
 *
 * 本脚本不启动完整应用，只用同构临时表复刻 lib/CdpSystem.php:51 与 :158 两个动作。
 * 灌 52 万行要几十秒，不进 qa_full 必跑集。
 */
$rows = (int)($argv[1] ?? 520000);
$f = sys_get_temp_dir().'/of-wp-'.getmypid().'.sqlite'; @unlink($f);
$db = new PDO('sqlite:'.$f); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, event TEXT, label TEXT, variant TEXT,
 page TEXT, uid TEXT, member_id TEXT, member_email TEXT, props TEXT, ip TEXT, created_at TEXT,
 session_id TEXT DEFAULT '', message_id TEXT DEFAULT '', ts INTEGER DEFAULT 0, event_category TEXT DEFAULT '')");
$db->exec("CREATE UNIQUE INDEX idx_events_message ON events(message_id) WHERE message_id != ''");
echo "灌 ".number_format($rows)." 行…\n";
$db->beginTransaction();
$s=$db->prepare("INSERT INTO events (event,page,uid,member_id,props,ip,created_at,session_id,message_id,ts) VALUES (?,?,?,?,?,?,?,?,?,?)");
$props = json_encode(['src'=>'organic','utm'=>'x','a'=>1,'b'=>'某个中文属性值'], JSON_UNESCAPED_UNICODE);
for($i=0;$i<$rows;$i++){ $s->execute(['pageview','/p/'.($i%500),'v_'.($i%20000),'',$props,'1.2.3.4',date('Y-m-d H:i:s'),'sess','evt_'.$i,time()*1000]); }
$db->commit();
echo "完成。\n\n=== 单次 track() 的两个动作各花多久 ===\n";

// 动作 A：allEvents() —— SELECT DESC LIMIT 10000 + json_decode ×10000 + array_reverse
$t=microtime(true);
$r=$db->query("SELECT id,event,uid,member_id,props,page,ip,created_at,session_id,message_id,ts,event_category FROM events ORDER BY id DESC LIMIT 10000")->fetchAll(PDO::FETCH_ASSOC);
$out=[]; foreach($r as $x){ $p=json_decode($x['props']??'[]',true); if(!is_array($p))$p=[];
  $out[]=['id'=>'evt_'.$x['id'],'event'=>$x['event'],'visitor_id'=>$x['uid'],'member_id'=>$x['member_id'],
          'properties'=>$p,'url'=>$x['page'],'ip'=>$x['ip'],'timestamp'=>$x['created_at'],
          'ts'=>(int)$x['ts'],'session_id'=>$x['session_id'],'message_id'=>$x['message_id']]; }
$events=array_reverse($out);
$a=(microtime(true)-$t)*1000;
printf("A. allEvents() 读 10000 行+解码+反转 : %7.1f ms   (内存 %s)\n",$a, number_format(memory_get_usage(true)/1048576,1).' MB');

// 动作 B：saveEvents() —— 末尾 200 条 INSERT OR IGNORE
$events[]=['id'=>'new','event'=>'pageview','visitor_id'=>'v_new','member_id'=>'','properties'=>['src'=>'x'],
           'url'=>'/n','ip'=>'1.1.1.1','timestamp'=>date('Y-m-d H:i:s'),'ts'=>time()*1000,
           'session_id'=>'s','message_id'=>'evt_new_'.getmypid()];
$t=microtime(true);
$db->beginTransaction();
$st=$db->prepare("INSERT OR IGNORE INTO events (event,label,variant,page,uid,member_id,member_email,props,ip,created_at,session_id,message_id,ts,event_category) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
foreach(array_slice($events,-200) as $e){ $st->execute([$e['event'],'','',$e['url'],$e['visitor_id'],$e['member_id'],'',
  json_encode($e['properties'],JSON_UNESCAPED_UNICODE),$e['ip'],$e['timestamp'],$e['session_id'],$e['message_id'],$e['ts'],'']); }
$db->commit();
$b=(microtime(true)-$t)*1000;
printf("B. saveEvents() 末尾 200 条重复 INSERT: %7.1f ms   (其中 199 条是 IGNORE 空转)\n",$b);

// 对照：只插 1 条
$t=microtime(true);
$st->execute(['pageview','','','/n2','v_n2','','',$props,'1.1.1.1',date('Y-m-d H:i:s'),'s','evt_only_'.getmypid(),time()*1000,'']);
$c=(microtime(true)-$t)*1000;
printf("对照 只 INSERT 1 条（应有的成本）    : %7.3f ms\n",$c);
printf("\n单事件总开销 %.1f ms  vs  应有 %.3f ms  →  放大 %.0f 倍\n",$a+$b,$c,($a+$b)/max($c,0.0001));
@unlink($f);
