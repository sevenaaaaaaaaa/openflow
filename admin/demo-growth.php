<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/DemoGrowthWorkspace.php';
require_login(); require_perm('dashboard');
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_verify(); $r=demo_growth_install(); flash($r['ok']?'success':'error',$r['ok']?'Demo 数据已恢复为标准场景':'Demo 数据写入失败'); header('Location: /xmp/demo-growth'); exit; }
$state=demo_growth_read(); $view=demo_growth_compare($state['data']); $m=$view['loop']['metrics'];
admin_header('Demo 陪跑');
?>
<div class="admin-layout"><?php admin_sidebar('demo-growth'); ?><div class="main">
  <div class="v-head"><div><h1>Demo 陪跑 <span class="st st-warn">隔离演示数据</span></h1><p class="v-sub">增长大脑与黄金 Loop 读取同一批画像、行为证据和模拟成交真相。用于跑通产品，不代表真实经营结果。</p></div><div class="v-actions"><form method="post"><?=csrf_field()?><button class="btn btn-primary btn-sm"><?=$state['installed']?'一键重置 Demo':'一键装载 Demo'?></button></form></div></div>
  <div class="panel" style="margin-bottom:16px"><div class="p-body"><strong>安全边界：</strong>仅写入 <span class="mono">data/demo/</span>；不写 CDP、不打标签、不触达、不创建订单、不进入生产报表。所有身份均为 <span class="mono">DEMO-*</span>，邮箱使用保留域 <span class="mono">.example</span>。</div></div>
  <div class="kpi-grid"><div class="kpi"><div class="k-label">场景状态</div><div class="k-val" style="font-size:20px"><?=$state['installed']?'已装载':'预览中'?></div><div class="k-sub">可随时恢复标准数据</div></div><div class="kpi"><div class="k-label">共享样本</div><div class="k-val mono"><?=count($view['rows'])?></div><div class="k-sub">两套引擎同源</div></div><div class="kpi"><div class="k-label">Loop 高意向</div><div class="k-val mono"><?=$m['true_positive']?></div><div class="k-sub">可解释证据评分</div></div><div class="kpi"><div class="k-label">生产写入</div><div class="k-val mono">0</div><div class="k-sub">硬隔离</div></div></div>
  <div class="panel"><div class="p-head"><h3>增长大脑 × 黄金 Loop 并排陪跑</h3><span class="p-sub">同一证据，不同决策方式</span></div><div class="p-body"><div class="table-wrap"><table><thead><tr><th>Demo 对象</th><th>增长大脑（规则建议）</th><th>黄金 Loop（TIPS 计划）</th><th>边界</th></tr></thead><tbody>
  <?php foreach($view['rows'] as $row): $l=$row['loop']; ?><tr><td><strong><?=htmlspecialchars($row['name'])?></strong><div class="text-xs mono"><?=htmlspecialchars($row['subject_id'])?></div></td><td><?=$row['brain']?htmlspecialchars($row['brain']['action']).'<div class="text-xs text-muted">'.htmlspecialchars($row['brain']['reason']).'</div>':'<span class="text-muted">暂无强建议</span>'?></td><td><strong>意向分 <?=$l['score']?></strong><div class="text-xs text-muted"><?=$l['predicted_high_intent']?'准备“高意向”待审核行动':'继续观察，不生成行动'?></div></td><td><span class="st <?=$l['blocked_reason']?'st-danger':'st-ok'?>"><?=$l['blocked_reason']?htmlspecialchars($l['blocked_reason']):'Demo only'?></span></td></tr><?php endforeach; ?>
  </tbody></table></div></div></div>
</div></div><?php admin_footer(); ?>
