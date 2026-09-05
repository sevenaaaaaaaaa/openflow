<?php
$root=sys_get_temp_dir().'/openflow-demo-'.bin2hex(random_bytes(4)); define('DATA_DIR',$root);
require_once __DIR__.'/../lib/DemoGrowthWorkspace.php';
$p=0;$f=0;function dg($n,$ok){global$p,$f;if($ok){$p++;echo"  ✓ $n\n";}else{$f++;echo"  ✗ $n\n";}}
echo "\n── isolated demo growth workspace ──\n";
$d=demo_growth_default_dataset(); $a=demo_growth_compare($d); $b=demo_growth_compare($d);
dg('default dataset is explicitly non-production',demo_growth_validate($d)['ok'] && $d['dataset']['production_data']===false);
dg('both engines run on every same subject',$a['ok'] && count($a['rows'])===count($d['profiles']) && count($a['loop']['subjects'])===count($d['profiles']));
dg('high intent path produces review-only action',$a['rows'][0]['brain']!==null && $a['rows'][0]['loop']['proposed_action']['status']==='sandbox_only');
dg('suppression remains effective',$a['rows'][2]['loop']['blocked_reason']==='suppressed' && !$a['rows'][2]['loop']['predicted_high_intent']);
dg('comparison is deterministic',$a===$b);
dg('runtime reports zero production writes',!$a['side_effects'] && $a['production_write_attempts']===0);
$install=demo_growth_install(); dg('one-click install writes isolated file',$install['ok'] && is_file($root.'/demo/growth-workspace.json'));
dg('install does not create production stores',!is_file($root.'/growth/conversions.json') && !is_file($root.'/growth/actions.json'));
$bad=$d;$bad['dataset']['kind']='production';dg('production dataset rejected',demo_growth_compare($bad)['error']==='demo_dataset_required');
$bad=$d;$bad['profiles'][0]['email']='real@example.com';dg('non-reserved email rejected',demo_growth_compare($bad)['error']==='demo_email_required');
$bad=$d;$bad['profiles'][0]['id']='customer-1';dg('non-demo identity rejected',demo_growth_compare($bad)['error']==='demo_identity_required');
echo "\n".($f===0?"✅ 全部通过（{$p}）\n":"❌ 失败 {$f} / 通过 {$p}\n");exit($f?1:0);
