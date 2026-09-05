<?php
/**
 * OpenFlow Studio —— 增长操作系统统一工作台（web 形态）
 *
 * Studio 是 TIPS 视角的聚合编排台：把画布流程 / Flow 工作台 / Loop 工作台 /
 * 自动化流程漏斗 / 模块工厂 / 流程洞察 聚合成一个入口。做投影与编排，不新增存储、
 * 不复制执行器（遵循 ADR-001：Flow/Loop 用同一套领域身份与执行入口）。
 *
 * 后续：web（本页）为 Studio 唯一实现，mac/windows/linux 由 Tauri 载入本页，
 * CLI 用 bin/of。所有平台共享同一 PHP 后端与鉴权。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_once __DIR__ . '/../lib/CanvasSystem.php';
require_once __DIR__ . '/../lib/FlowWorkspace.php';
require_once __DIR__ . '/../lib/LoopLifecycle.php';
require_once __DIR__ . '/../lib/BlockSchema.php';
require_login();
require_perm('settings');

// 聚合数据
$canvasFlows = canvas_get();
$autoStats = function_exists('automation_flows_stats') ? automation_flows_stats() : [];
$flowWs = function_exists('flow_workspace_current') ? flow_workspace_current() : [];
$flowDefs = $flowWs['definitions'] ?? [];
$loopDefs = array_values((array)(function_exists('loop_lifecycle_read') ? loop_lifecycle_read()['definitions'] : []));
$modules = function_exists('blockschema_all') ? blockschema_all() : [];
$canvasCount = is_array($canvasFlows) ? count($canvasFlows) : 0;
$enabledCanvas = is_array($canvasFlows) ? count(array_filter($canvasFlows, fn($f) => !empty($f['enabled']))) : 0;

admin_header('OpenFlow Studio');
?>
<div class="admin-layout"><?php admin_sidebar('studio'); ?><div class="main">
  <div class="v-head">
    <div><h1>OpenFlow Studio <span class="st st-ok">增长操作系统</span></h1>
      <p class="v-sub">TIPS 视角的编排与洞察聚合台 · 画布/Flow/Loop/自动化/模块工厂 单一入口 · 全端复用（web / mac / win / linux / CLI）</p></div>
    <div class="v-actions"><button type="button" class="btn btn-s btn-sm" id="installBtn" onclick="ofInstall()" style="display:none">📲 安装到桌面</button><a class="btn btn-primary btn-sm" href="/xmp/canvas">打开画布</a></div>
  </div>

  <!-- KPI 概览 -->
  <div class="kpi-grid">
    <div class="kpi"><div class="k-label">画布流程</div><div class="k-val mono"><?=$canvasCount?></div><div class="k-sub"><?=$enabledCanvas?> 条运行中</div></div>
    <div class="kpi"><div class="k-label">Flow 定义</div><div class="k-val mono"><?=count($flowDefs)?></div><div class="k-sub">可被 Loop 调用</div></div>
    <div class="kpi"><div class="k-label">自动化流程</div><div class="k-val mono"><?=count($autoStats)?></div><div class="k-sub">含漏斗洞察</div></div>
    <div class="kpi"><div class="k-label">自定义模块</div><div class="k-val mono"><?=count($modules)?></div><div class="k-sub">模块工厂产物</div></div>
  </div>

  <!-- Studio 能力入口 -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin:18px 0">
    <a class="card" style="padding:18px;text-decoration:none;color:inherit" href="/xmp/canvas">
      <div style="font-size:22px">🔄</div><div style="font-weight:800;margin-top:8px">画布编排</div>
      <div class="text-sm text-muted" style="margin-top:4px">真流程图 · A/B 分流 · 灰度 · 条件分支 · 延时续流</div>
      <div class="text-sm" style="color:var(--accent);margin-top:8px">打开 →</div></a>
    <a class="card" style="padding:18px;text-decoration:none;color:inherit" href="/xmp/flow-workspace">
      <div style="font-size:22px">🌊</div><div style="font-weight:800;margin-top:8px">Flow 工作台</div>
      <div class="text-sm text-muted" style="margin-top:4px">确定性流程 · 定义/运行/结果 · 版本对比</div>
      <div class="text-sm" style="color:var(--accent);margin-top:8px">打开 →</div></a>
    <a class="card" style="padding:18px;text-decoration:none;color:inherit" href="/xmp/loop-workspace">
      <div style="font-size:22px">🔁</div><div style="font-weight:800;margin-top:8px">Loop 工作台</div>
      <div class="text-sm text-muted" style="margin-top:4px">TIPS Agent · 沙盘 · 受控运行时 · 固化 Flow</div>
      <div class="text-sm" style="color:var(--accent);margin-top:8px">打开 →</div></a>
    <a class="card" style="padding:18px;text-decoration:none;color:inherit" href="/xmp/modules">
      <div style="font-size:22px">🧩</div><div style="font-weight:800;margin-top:8px">模块工厂</div>
      <div class="text-sm text-muted" style="margin-top:4px">可视化定义新模块 · 字段/样式/代码模式</div>
      <div class="text-sm" style="color:var(--accent);margin-top:8px">打开 →</div></a>
    <a class="card" style="padding:18px;text-decoration:none;color:inherit" href="/xmp/automation">
      <div style="font-size:22px">⚙️</div><div style="font-weight:800;margin-top:8px">营销自动化</div>
      <div class="text-sm text-muted" style="margin-top:4px">触发器 → 多渠道动作（邮件/企微/公众号/券）</div>
      <div class="text-sm" style="color:var(--accent);margin-top:8px">打开 →</div></a>
    <a class="card" style="padding:18px;text-decoration:none;color:inherit" href="/xmp/ma-platforms">
      <div style="font-size:22px">📤</div><div style="font-weight:800;margin-top:8px">广告平台对接</div>
      <div class="text-sm text-muted" style="margin-top:4px">Meta CAPI · Google Ads · 巨量 转化回传</div>
      <div class="text-sm" style="color:var(--accent);margin-top:8px">打开 →</div></a>
    <a class="card" style="padding:18px;text-decoration:none;color:inherit" href="/xmp/action-approvals">
      <div style="font-size:22px">✅</div><div style="font-weight:800;margin-top:8px">行动审批中心</div>
      <div class="text-sm text-muted" style="margin-top:4px">ActionProposal→Approval→Execution→Evaluation</div>
      <div class="text-sm" style="color:var(--accent);margin-top:8px">打开 →</div></a>
    <a class="card" style="padding:18px;text-decoration:none;color:inherit" href="/xmp/decision-trace">
      <div style="font-size:22px">🧠</div><div style="font-weight:800;margin-top:8px">决策轨道</div>
      <div class="text-sm text-muted" style="margin-top:4px">Agent 决策可解释 · 理由/证据/结果</div>
      <div class="text-sm" style="color:var(--accent);margin-top:8px">打开 →</div></a>
  </div>

  <!-- 画布流程列表 -->
  <div class="panel" style="margin-bottom:16px">
    <div class="p-head"><h3>画布流程</h3><span class="p-sub mono">NODES + EDGES · A/B · 条件</span></div>
    <div class="p-body">
      <?php if (!$canvasFlows): ?><div class="of-empty" style="border:0">还没有画布流程。<a href="/xmp/canvas">新建第一条</a></div>
      <?php else: foreach (array_slice($canvasFlows,0,8) as $f): $eds = is_array($f['nodes'] ?? null) ? count($f['nodes']) : 0; ?>
      <div class="todo-row"><span class="t-b"><span class="t-t"><?=htmlspecialchars($f['name'] ?? $f['id'])?></span><span class="t-d"><?=$eds?> 节点 · <?=is_array($f['edges'] ?? null) ? count($f['edges']) : 0?> 连线 · 更新于 <?=htmlspecialchars($f['updated_at'] ?? '')?></span></span><span class="st <?=!empty($f['enabled'])?'st-ok':'st-faint'?>"><?=!empty($f['enabled'])?'运行中':'已停'?></span></div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- 自动化流程漏斗洞察 -->
  <div class="panel" style="margin-bottom:16px">
    <div class="p-head"><h3>自动化流程漏斗</h3><span class="p-sub mono">进入 · 触达 · 转化</span></div>
    <div class="p-body">
      <?php if (!$autoStats): ?><div class="of-empty" style="border:0">暂无自动化流程洞察。运行流程后这里显示漏斗。</div>
      <?php else: foreach (array_slice($autoStats,0,8) as $s): ?>
      <div class="todo-row"><span class="t-b"><span class="t-t"><?=htmlspecialchars($s['name'])?></span><span class="t-d">进入 <?=$s['entered']?> · 触达 <?=$s['sent']?> · 邮<?=$s['channels']['email']?> 企微<?=$s['channels']['wecom']?> 公<?=$s['channels']['wechat']?></span></span><span class="st st-ok"><?=$s['conversion']?>% 转化</span></div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Loop 定义 -->
  <div class="panel">
    <div class="p-head"><h3>Loop 定义</h3><span class="p-sub mono">TIPS AGENT · 受控循环</span></div>
    <div class="p-body">
      <?php if (!$loopDefs): ?><div class="of-empty" style="border:0">暂无 Loop 定义。到 Loop 工作台启动沙盘。</div>
      <?php else: foreach (array_slice($loopDefs,0,6) as $d): ?>
      <div class="todo-row"><span class="t-b"><span class="t-t"><?=htmlspecialchars($d['id'] ?? '')?></span><span class="t-d">目标 <?=htmlspecialchars($d['goal_id'] ?? '')?> · 状态 <?=htmlspecialchars($d['status'] ?? '')?></span></span><span class="st st-faint">Loop</span></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div></div>
<?php admin_footer(); ?>
<script>
// PWA 安装到桌面（mac/win/linux/iPad/安卓pad/鸿蒙：浏览器「添加到主屏幕/程序坞」）
var ofDeferred = null;
window.addEventListener('beforeinstallprompt', function(e) { e.preventDefault(); ofDeferred = e; document.getElementById('installBtn').style.display = 'inline-block'; });
function ofInstall() {
  if (ofDeferred) { ofDeferred.prompt(); ofDeferred.userChoice.then(function() { ofDeferred = null; document.getElementById('installBtn').style.display = 'none'; }); }
  else {
    var b = document.getElementById('installBtn');
    if (b) b.textContent = '用浏览器菜单「添加到主屏幕 / 安装应用」';
    setTimeout(function(){ if (b) { b.textContent = '📲 安装到桌面'; } }, 4000);
  }
}
if (window.matchMedia('(display-mode: standalone)').matches) { var b = document.getElementById('installBtn'); if (b) b.style.display = 'none'; }
</script>
