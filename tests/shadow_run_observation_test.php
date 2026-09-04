<?php
/** Read-only shadow-run observation acceptance tests. */
require_once __DIR__ . '/../lib/ShadowRunObservation.php';

$pass=0;$fail=0;
function check(string $name,bool $ok,string $detail=''):void{global $pass,$fail;if($ok){$pass++;echo "  ✓ {$name}\n";}else{$fail++;echo "  ✗ {$name}".($detail?" → {$detail}":'')."\n";}}

$logs = [
    ['time'=>'2026-09-05 09:00:00','flow'=>'legacy','level'=>'info','message'=>'旧日志'],
    ['time'=>'2026-09-05 10:00:00','flow'=>'f1','level'=>'info','message'=>'影子运行：running','run_id'=>'r1','trigger'=>'form_submit','status'=>'running','idempotency_key'=>'e1','tenant_id'=>'default'],
    ['time'=>'2026-09-05 10:00:01','flow'=>'f1','level'=>'info','message'=>'影子运行：succeeded','run_id'=>'r1','trigger'=>'form_submit','status'=>'succeeded','idempotency_key'=>'e1','tenant_id'=>'default','result'=>['result_ref'=>'c1']],
    ['time'=>'2026-09-05 10:10:00','flow'=>'f2','level'=>'info','message'=>'影子运行：running','run_id'=>'r2','trigger'=>'form_submit','status'=>'running','idempotency_key'=>'e2','tenant_id'=>'default'],
    ['time'=>'2026-09-05 10:20:00','flow'=>'f3','level'=>'info','message'=>'影子运行：succeeded','run_id'=>'r3','status'=>'succeeded','idempotency_key'=>'e3','tenant_id'=>'default'],
];
$report = shadow_run_observe($logs, strtotime('2026-09-05 12:00:00'), 3600);

echo "\n── observation metrics ──\n";
check('observer is explicitly read-only', $report['mode']==='read_only');
check('legacy row is outside candidate denominator', $report['counts']['shadow_candidate_rows']===4);
check('field completeness is reproducible', $report['rates']['structured_field_completeness']===0.75);
check('runs are grouped by run id', $report['counts']['runs']===2);
check('terminal rate is measured', $report['rates']['terminal_rate']===0.5);
check('lifecycle completeness is measured', $report['rates']['lifecycle_completeness']===0.5);
check('projection count is visible', $report['counts']['projected_runs']===2);

echo "\n── anomaly classification ──\n";
check('missing structured field is classified', ($report['anomalies_by_type']['incomplete_structured_row']??0)===1);
check('stale open run is classified', ($report['anomalies_by_type']['stale_running_run']??0)===1);
check('observation does not mutate input', count($logs)===5 && !isset($logs[0]['run_id']));

echo "\n".($fail===0?"✅ 全部通过（{$pass}）\n":"❌ 失败 {$fail} / 通过 {$pass}\n");
exit($fail===0?0:1);
