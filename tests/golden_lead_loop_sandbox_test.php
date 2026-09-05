<?php
require_once __DIR__.'/../lib/GoldenLeadLoopSandbox.php';
$p=0;$f=0;function gl($n,$ok){global$p,$f;if($ok){$p++;echo"  ✓ $n\n";}else{$f++;echo"  ✗ $n\n";}}

echo "\n── golden lead Loop sandbox ──\n";
$r=golden_lead_sandbox_run();
gl('scenario succeeds',$r['ok']);
gl('dataset explicitly synthetic',$r['dataset']['kind']==='synthetic' && $r['dataset']['production_data']===false);
gl('sandbox has no side effects',!$r['side_effects'] && $r['production_write_attempts']===0);
gl('TIPS business stages preserved',array_keys($r['tips'])===['Touch','Insight','Personalize','Sell']);
gl('high-intent fixtures identified',$r['metrics']['true_positive']===2);
gl('suppressed lead never selected',!$r['subjects'][2]['predicted_high_intent'] && $r['subjects'][2]['blocked_reason']==='suppressed');
gl('every score remains explainable',count($r['subjects'][0]['evidence'])===3);
gl('selected action remains review-only',$r['subjects'][0]['proposed_action']['status']==='sandbox_only' && $r['subjects'][0]['proposed_action']['requires_review']);
gl('quality metrics are deterministic',(float)$r['metrics']['precision']===1.0 && (float)$r['metrics']['recall']===1.0 && (float)$r['metrics']['wrong_contact_rate']===0.0);
gl('single iteration stops explicitly',$r['stop']['reached'] && $r['stop']['reason']==='single_sandbox_iteration_complete');
$repeat=golden_lead_sandbox_run();
gl('same fixture produces same result',$repeat===$r);

echo "\n── dataset isolation ──\n";
$unsafe=golden_lead_sandbox_fixture();$unsafe['dataset']['kind']='production';$unsafe['dataset']['production_data']=true;
$rejected=golden_lead_sandbox_run($unsafe);
gl('production-labelled input rejected',!$rejected['ok'] && $rejected['error']==='isolated_dataset_required');
$missingConsent=golden_lead_sandbox_fixture();$missingConsent['leads'][0]['consent']='unknown';
$blocked=golden_lead_sandbox_run($missingConsent);
gl('missing consent prevents proposal',!$blocked['subjects'][0]['predicted_high_intent'] && $blocked['subjects'][0]['blocked_reason']==='consent_missing');
gl('threshold is bounded',golden_lead_sandbox_run(null,999)['threshold']===100);

echo "\n".($f===0?"✅ 全部通过（{$p}）\n":"❌ 失败 {$f} / 通过 {$p}\n");exit($f===0?0:1);
