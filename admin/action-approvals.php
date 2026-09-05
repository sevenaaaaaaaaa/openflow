<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/../lib/GrowthAction.php';
require_once __DIR__.'/../lib/GrowthSignal.php';
require_once __DIR__.'/../lib/ActionApprovalView.php';
require_login();
require_perm('brain');
$view=action_approval_view(evidence_project_current());
$labels=['proposed'=>'待提交审批','approved'=>'已批准待执行','executing'=>'执行中','completed'=>'执行已结束','evaluated'=>'业务结果已评估','rejected'=>'已拒绝'];
admin_header('行动审批中心');
?>
<div class="admin-layout">
  <?php admin_sidebar('action-approvals'); ?>
  <div class="main">
    <div class="v-head"><div><h1>行动审批中心 <span class="st st-warn">只读</span></h1><p class="v-sub">查看 ActionProposal → Approval → Execution → Evaluation 审计链。这里不处理投稿或资质，也暂不开放批准和生产执行。</p></div><div class="v-actions"><a class="btn btn-s btn-sm" href="/xmp/loop-workspace">返回 Loop 工作台</a></div></div>
    <div class="kpi-grid">
      <div class="kpi"><div class="k-label">行动建议</div><div class="k-val mono"><?=$view['counts']['total']?></div><div class="k-sub">共享 ActionProposal</div></div>
      <div class="kpi"><div class="k-label">待审批</div><div class="k-val mono"><?=$view['counts']['proposed']?></div><div class="k-sub">尚无 Approval</div></div>
      <div class="kpi"><div class="k-label">已评估</div><div class="k-val mono"><?=$view['counts']['evaluated']?></div><div class="k-sub">有业务结果证据</div></div>
      <div class="kpi"><div class="k-label">证据断链</div><div class="k-val mono"><?=array_sum($view['orphans'])+count($view['projection_gaps'])?></div><div class="k-sub">不猜测、不补造</div></div>
    </div>
    <?php if(array_sum($view['orphans'])>0): ?><div class="panel" style="padding:14px;margin-bottom:16px;border-color:var(--warn)"><strong>影子证据尚未形成完整行动链</strong><div class="text-sm text-muted" style="margin-top:4px">孤立 Approval <?=$view['orphans']['approvals']?> · Execution <?=$view['orphans']['executions']?> · Evaluation <?=$view['orphans']['evaluations']?>。这些记录不会被显示为已批准或已执行的完整行动。</div></div><?php endif; ?>
    <div class="panel">
      <div class="p-head"><h3>行动审计队列</h3><span class="p-sub mono">READ ONLY · PRODUCTION EXECUTION OFF</span></div>
      <div class="p-body">
        <?php if(!$view['rows']): ?><div class="of-empty" style="border:0">暂无可验证的行动建议。增长大脑采纳建议或结构化行动进入共享投影后会显示在这里。</div><?php endif; ?>
        <?php foreach($view['rows'] as $row): $a=$row['action'];$ap=$row['approval'];$ex=$row['execution'];$ev=$row['evaluation'];
          $approvalText=$ap ? (string)($ap['actor_type']??'').' / '.(string)($ap['actor_id']??'') : '尚无';
          $executionText=$ex ? (string)($ex['executor']??'').' / '.(string)($ex['status']??'') : '尚无';
          $evaluationText=$ev ? (string)($ev['metric']??'').' Δ'.(string)($ev['delta']??0) : '尚未评估'; ?>
        <div class="todo-row" style="align-items:flex-start">
          <span class="t-b"><span class="t-t"><?=htmlspecialchars($a['action'])?></span><span class="t-d">对象 <?=htmlspecialchars($a['subject_id']?:'未指定')?> · 来源 <?=htmlspecialchars($a['module']?:'未标注')?> · <?=htmlspecialchars($a['created_at'])?></span>
            <span class="t-d">审批：<?=htmlspecialchars($approvalText)?> · 执行：<?=htmlspecialchars($executionText)?> · 结果：<?=htmlspecialchars($evaluationText)?></span>
          </span><span class="st <?=$row['state']==='proposed'?'st-warn':'st-faint'?>"><?=htmlspecialchars($labels[$row['state']]??$row['state'])?></span>
        </div>
        <?php endforeach; ?>
        <p class="text-xs text-muted" style="margin-top:14px">后续只有在独立 Approval 写入、现有领域权限复核和 Action Gateway 生产开关均通过后，才会增加批准按钮。</p>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
