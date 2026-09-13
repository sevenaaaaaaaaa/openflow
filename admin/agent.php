<?php
/**
 * Agent 运行时 — 给目标，Agent 自主链式调工具（工具库/运行时间线/审批）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AgentRuntime.php';
require_login();
require_perm('settings');

$tools = agent_tools();
$runs = agent_stored();
$riskLabel = ['safe' => '安全·自动', 'moderate' => '中风险·需批准', 'high' => '高风险·需批准'];
admin_header('Agent 运行时');
?>
<div class="admin-layout">
  <?php admin_sidebar('agent'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>Agent 运行时</h1><p class="v-sub">给一个目标，Agent 在白名单工具里自主选择并链式调用去完成它；失败会重试、可据错误换路；中高风险动作停在待批准。</p></div>
      <div class="v-actions"><span class="note mono" style="margin:0" id="agMsg"></span></div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="field"><label>目标 <span class="hint">· 用自然语言描述要达成什么</span></label>
        <textarea class="inp" id="agGoal" rows="3" placeholder="例如：把最近 30 天最后活跃的高价值客户打上 VIP 标签，并给其中未成交的建一条跟进任务"></textarea></div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <button class="btn btn-p btn-sm" id="agRun" onclick="agRun()">▶ 运行</button>
        <label style="font-size:13px;display:flex;align-items:center;gap:5px"><input type="checkbox" id="agAuto"> 允许按自治级别自动执行中高风险动作</label>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <h2 style="font-size:14px">🧰 工具库（<?=count($tools)?>）<span class="hint">· Agent 只能在这些工具里选</span></h2>
      <table>
        <thead><tr><th>工具</th><th>说明</th><th>参数</th><th>风险</th></tr></thead>
        <tbody>
          <?php foreach ($tools as $name => $t): ?>
          <tr><td class="mono"><?=htmlspecialchars($name)?></td><td class="text-sm"><?=htmlspecialchars($t['desc'])?></td><td class="text-sm text-muted"><?=htmlspecialchars(implode(', ', array_keys($t['params'])))?></td>
            <td><span class="badge <?=$t['risk'] === 'safe' ? 'badge-green' : ($t['risk'] === 'high' ? 'badge-red' : 'badge-yellow')?>"><?=htmlspecialchars($riskLabel[$t['risk']] ?? $t['risk'])?></span></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2 style="font-size:14px">🕓 运行记录（<?=count($runs)?>）</h2>
      <?php if (!$runs): ?><div class="empty" style="padding:20px">还没有运行。输入目标点「运行」。</div><?php endif; ?>
      <?php foreach (array_slice($runs, 0, 12) as $run): $st = $run['status'] ?? ''; ?>
      <div style="border:1px solid var(--border-soft,var(--border));border-radius:10px;padding:12px 14px;margin-bottom:10px">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <span class="badge <?=$st === 'completed' ? 'badge-green' : ($st === 'needs_approval' ? 'badge-yellow' : ($st === 'failed' ? 'badge-red' : 'badge-gray'))?>"><?=htmlspecialchars(['completed'=>'已完成','needs_approval'=>'待批准','failed'=>'失败','max_steps'=>'达最大步数'][$st] ?? $st)?></span>
          <b style="flex:1;min-width:0"><?=htmlspecialchars($run['goal'])?></b>
          <span class="text-xs text-muted mono"><?=htmlspecialchars($run['created_at'] ?? '')?></span>
        </div>
        <?php foreach ((array)($run['steps'] ?? []) as $si => $s): ?>
        <div style="display:flex;gap:8px;align-items:flex-start;padding:6px 0;border-top:1px solid var(--border-soft,var(--border));font-size:13px">
          <span class="mono" style="flex:none;width:22px;color:var(--faint)"><?=$si + 1?></span>
          <span class="mono" style="flex:none;min-width:90px"><?=htmlspecialchars($s['tool'] ?? '')?></span>
          <span style="flex:1;min-width:0"><?=htmlspecialchars($s['thought'] ?? '')?>
            <span class="text-sm text-muted"><?=htmlspecialchars(mb_substr(json_encode($s['args'] ?? [], JSON_UNESCAPED_UNICODE), 0, 80))?></span>
            <?php if (!empty($s['ok'])): ?><div class="text-sm" style="color:var(--ok)">✓ <?=htmlspecialchars(mb_substr((string)($s['result'] ?? ''), 0, 120))?></div>
            <?php else: ?><div class="text-sm" style="color:var(--danger)">✕ <?=htmlspecialchars((string)($s['error'] ?? '失败'))?></div><?php endif; ?>
          </span>
        </div>
        <?php endforeach; ?>
        <?php if (!empty($run['pending'])): ?>
        <div style="margin-top:8px;padding:8px 10px;background:var(--warn-soft,oklch(0.72 0.15 85 / 0.12));border-radius:8px;display:flex;gap:10px;align-items:center">
          <span class="text-sm">待批准：<b class="mono"><?=htmlspecialchars($run['pending']['tool'])?></b> <?=htmlspecialchars(mb_substr(json_encode($run['pending']['args'], JSON_UNESCAPED_UNICODE), 0, 100))?></span>
          <button class="btn btn-p btn-sm" style="margin-left:auto" onclick="agApprove('<?=htmlspecialchars($run['id'])?>')">批准执行</button>
        </div>
        <?php endif; ?>
        <?php if (!empty($run['summary'])): ?><div class="text-sm text-muted" style="margin-top:6px"><?=htmlspecialchars($run['summary'])?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
const AG_CSRF = <?=json_encode(csrf_token())?>;
function agMsg(t){ document.getElementById('agMsg').textContent = t; }
async function agPost(p){ return (await fetch('/api/agent.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':AG_CSRF},body:JSON.stringify(p)})).json(); }
async function agRun(){
  const goal = document.getElementById('agGoal').value.trim();
  if(!goal){ agMsg('先填目标'); return; }
  const b=document.getElementById('agRun'); b.disabled=true; b.textContent='运行中…'; agMsg('Agent 决策 + 执行中…');
  const r = await agPost({action:'run', goal, auto: document.getElementById('agAuto').checked?1:0});
  b.disabled=false; b.textContent='▶ 运行';
  if(!r.ok){ agMsg(r.error||'失败'); return; }
  agMsg('运行结束：' + (r.run.status||''));
  setTimeout(()=>location.reload(),500);
}
async function agApprove(id){
  agMsg('执行中…');
  const r = await agPost({action:'approve', run_id:id});
  if(!r.ok){ agMsg(r.error||'失败'); return; }
  agMsg('已批准执行'); setTimeout(()=>location.reload(),500);
}
</script>
<?php admin_footer(); ?>
