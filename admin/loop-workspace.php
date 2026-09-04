<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/GoldenLeadLoopSandbox.php';
require_login();
require_perm('dashboard');
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
      <div class="v-actions"><a class="btn btn-s btn-sm" href="/xmp/flow">查看 Flow 业务链路</a></div>
    </div>
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
