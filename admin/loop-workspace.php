<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/GoldenLeadLoopSandbox.php';
require_once __DIR__ . '/../lib/LoopLifecycle.php';
require_login();
require_perm('dashboard');
$command=(string)($_POST['command']??'');
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_verify();
  if($command==='start_sandbox'){
    loop_lifecycle_definition_save(['id'=>'loop_sandbox','status'=>'active','goal_id'=>'goal_sandbox','tips_stages'=>['Touch','Insight','Personalize','Sell'],'allowed_flow_ids'=>[],'allowed_skill_ids'=>[],'budgets'=>['max_iterations'=>1,'max_steps'=>8,'max_tokens'=>0,'max_cost'=>0],'stop_conditions'=>['approval_required'=>true],'created_at'=>date('c')]);
    $result=loop_lifecycle_start('loop_sandbox','sandbox:'.bin2hex(random_bytes(6)),['mode'=>'sandbox','production_write_enabled'=>false]);
  } elseif($command==='pause') $result=loop_lifecycle_pause((string)($_POST['run_id']??''),'operator_requested');
  elseif($command==='resume') $result=loop_lifecycle_resume((string)($_POST['run_id']??'')); else $result=['ok'=>false];
  flash($result['ok']?'success':'error',$result['ok']?'Loop 状态已更新':'Loop 操作未完成');header('Location: /xmp/loop-workspace');exit;
}
$lifecycle=loop_lifecycle_read(); $runtimeRuns=array_values($lifecycle['runs']); usort($runtimeRuns,fn($a,$b)=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));
$sandbox = golden_lead_sandbox_run();
$metrics = $sandbox['metrics'];
$selected = array_values(array_filter($sandbox['subjects'], fn($row) => !empty($row['predicted_high_intent'])));
admin_header('Loop 工作台 · 实验');
?>
<div class="admin-layout">
  <?php admin_sidebar('loop-workspace'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>Loop 工作台 <span class="st st-warn">实验 · 只读沙盘</span></h1><p class="v-sub">TIPS Agent 的受控验证入口。当前只运行合成数据，不调用模型、不执行生产动作、不计入真实经营指标。</p></div>
      <div class="v-actions"><a class="btn btn-primary btn-sm" href="/xmp/demo-growth">运行完整 Demo 闭环</a><a class="btn btn-s btn-sm" href="/xmp/flow-workspace">查看 Flow 工作台</a></div>
    </div>
    <div class="panel" style="margin-bottom:16px"><div class="p-head"><h3>受控运行时</h3><span class="p-sub mono">PERSISTENT · NO EXECUTOR</span></div><div class="p-body"><p class="text-sm text-muted">用于验证 Loop 的启动、暂停和恢复；该沙盘没有可调用生产 Flow、Skill 或写入执行器。</p><div class="v-actions"><form method="post"><?=csrf_field()?><input type="hidden" name="command" value="start_sandbox"><button class="btn btn-primary btn-sm">启动空白沙盘运行</button></form></div><?php if(!$runtimeRuns): ?><div class="of-empty" style="border:0;margin-top:12px">尚未启动持久化沙盘运行。</div><?php endif; ?><?php foreach(array_slice($runtimeRuns,0,5) as $run): ?><div class="todo-row" style="margin-top:10px"><span class="t-b"><span class="t-t mono"><?=htmlspecialchars($run['id'])?></span><span class="t-d"><?=htmlspecialchars($run['status'])?> · 更新于 <?=htmlspecialchars($run['updated_at'])?></span></span><span><?php if($run['status']==='observing'): ?><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="command" value="pause"><input type="hidden" name="run_id" value="<?=htmlspecialchars($run['id'])?>"><button class="btn btn-s btn-sm">暂停</button></form><?php elseif($run['status']==='paused'): ?><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="command" value="resume"><input type="hidden" name="run_id" value="<?=htmlspecialchars($run['id'])?>"><button class="btn btn-s btn-sm">恢复</button></form><?php endif; ?></span></div><?php endforeach; ?></div></div>
    <div class="kpi-grid">
      <div class="kpi"><div class="k-label">运行模式</div><div class="k-val" style="font-size:20px">只读</div><div class="k-sub">单轮 · 零生产写入</div></div>
      <div class="kpi"><div class="k-label">合成样本</div><div class="k-val mono"><?=$metrics['sample_size']?></div><div class="k-sub">不是真实客户</div></div>
      <div class="kpi"><div class="k-label">Precision</div><div class="k-val mono"><?=number_format((float)$metrics['precision']*100,0)?>%</div><div class="k-sub">沙盘识别准确率</div></div>
      <div class="kpi"><div class="k-label">错误触达率</div><div class="k-val mono"><?=number_format((float)$metrics['wrong_contact_rate']*100,0)?>%</div><div class="k-sub">沙盘指标</div></div>
    </div>
    <div class="panel" style="margin-bottom:16px">
      <div class="p-head"><h3>TIPS 单轮计划</h3><span class="p-sub mono">Observe → Plan → Dry-run → Stop</span></div>
      <div class="p-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px">
        <?php foreach ($sandbox['tips'] as $stage=>$item): ?><div class="card" style="margin:0"><div style="font-weight:800;margin-bottom:6px"><?=htmlspecialchars($stage)?></div><div class="text-sm text-muted"><?=htmlspecialchars($item['rule'])?></div></div><?php endforeach; ?>
      </div>
    </div>
    <div class="panel">
      <div class="p-head"><h3>高意向候选</h3><span class="p-sub mono">SYNTHETIC · REVIEW ONLY</span></div>
      <div class="p-body">
        <?php foreach ($selected as $row): ?><div class="todo-row"><span class="t-b"><span class="t-t"><?=htmlspecialchars($row['subject_id'])?> · 意向分 <?=$row['score']?></span><span class="t-d"><?=count($row['evidence'])?> 条可解释信号 · 建议添加“高意向”标签</span></span><span class="st st-warn">沙盘待审核</span></div><?php endforeach; ?>
        <p class="text-xs text-muted" style="margin-top:14px">当前没有“批准并执行”按钮。生产 Action Gateway 仍然关闭；真实数据陪跑和行动审批中心将作为后续独立阶段接入。</p>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
