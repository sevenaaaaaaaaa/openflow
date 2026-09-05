<?php
require_once __DIR__.'/../lib/FlowWorkspace.php';
$p=0;$f=0;function fw($n,$ok){global$p,$f;if($ok){$p++;echo"  ✓ $n\n";}else{$f++;echo"  ✗ $n\n";}}
echo "\n── Flow workspace projection ──\n";
$auto=[['id'=>'flow_tag','name'=>'标签 Flow','enabled'=>true,'trigger'=>'page_view','steps'=>[['action'=>'add_tag','tag'=>'高意向']]]];
$canvas=[['id'=>'canvas_mail','name'=>'画布 Flow','enabled'=>false,'nodes'=>[['id'=>'n1','type'=>'trigger','trigger'=>'form_submit'],['id'=>'n2','type'=>'send_email']],'edges'=>[]]];
$projection=['objects'=>['FlowRun'=>[['id'=>'run_1','flow_id'=>'flow_tag','trigger'=>'page_view','status'=>'succeeded','created_at'=>'2026-09-05 10:00:00']], 'Execution'=>[['id'=>'exe_1','action_id'=>'act_1','flow_run_id'=>'run_1','status'=>'succeeded']], 'ActionProposal'=>[['id'=>'act_1','action'=>'添加高意向标签','created_at'=>'2026-09-05 10:00:00']], 'Approval'=>[['id'=>'apr_1','subject_id'=>'act_1','decision'=>'approved']], 'Evaluation'=>[['id'=>'eva_1','action_id'=>'act_1','metric'=>'conversion','delta'=>1]]], 'gaps'=>[['source'=>'automation_log','reason'=>'legacy_log_has_no_run_boundary']]];
$view=flow_workspace_build($auto,$canvas,$projection,'flow_tag');
fw('normalizes both Flow types',$view['counts']['definitions']===2 && $view['definition']['source_type']==='automation');
fw('selects only matching FlowRun',$view['counts']['runs']===1 && $view['runs'][0]['id']==='run_1');
fw('joins approval execution and evaluation through run',$view['counts']['chains']===1 && $view['counts']['evaluated']===1 && $view['chains'][0]['approval']['id']==='apr_1');
$fallback=flow_workspace_build($auto,$canvas,$projection,'missing');
fw('unknown selected Flow safely falls back',in_array($fallback['selected_id'], ['flow_tag','canvas_mail'], true) && $fallback['definition']!==null);
fw('workspace is explicitly read only',$view['mode']==='read_only' && !$view['write_enabled']);
echo "\n".($f===0?"✅ 全部通过（{$p}）\n":"❌ 失败 {$f} / 通过 {$p}\n");exit($f?1:0);
