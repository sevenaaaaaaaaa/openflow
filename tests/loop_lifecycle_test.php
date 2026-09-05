<?php
$root=sys_get_temp_dir().'/of-loop-life-'.bin2hex(random_bytes(4)); define('DATA_DIR',$root);
function json_read($f){return is_file($f)?(json_decode(file_get_contents($f),true)?:[]):[];}
function json_write($f,$d){@mkdir(dirname($f),0777,true);return file_put_contents($f,json_encode($d))!==false;}
require_once __DIR__.'/../lib/LoopLifecycle.php'; $p=0;$f=0;
function ll($n,$ok){global$p,$f;if($ok){$p++;echo"  ✓ $n\n";}else{$f++;echo"  ✗ $n\n";}}
echo"\n── persistent Loop lifecycle ──\n";
$d=['id'=>'loop_1','status'=>'active','goal_id'=>'goal_1','tips_stages'=>['Touch','Insight','Personalize','Sell'],'allowed_flow_ids'=>[],'allowed_skill_ids'=>[],'budgets'=>['max_iterations'=>1],'stop_conditions'=>[],'created_at'=>'2026-09-05T00:00:00Z'];
$saved=loop_lifecycle_definition_save($d);ll('definition persists',$saved['ok']);$start=loop_lifecycle_start('loop_1','event:1');ll('run starts in observing',$start['ok']&&$start['run']['status']==='observing');$same=loop_lifecycle_start('loop_1','event:1');ll('same key is idempotent',$same['idempotent']&&$same['run']['id']===$start['run']['id']);$paused=loop_lifecycle_pause($start['run']['id'],'operator');ll('pause is stored',$paused['ok']&&$paused['run']['status']==='paused');$resumed=loop_lifecycle_resume($start['run']['id']);ll('resume returns to observing',$resumed['ok']&&$resumed['run']['status']==='observing');$bad=loop_lifecycle_cancel($start['run']['id'],'stop');ll('cancel from observing is rejected',!$bad['ok']);
echo "\n".($f?"❌ 失败 {$f} / 通过 {$p}\n":"✅ 全部通过（{$p}）\n");exit($f?1:0);
