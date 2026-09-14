<?php
/**
 * AI 岗位 — 有目标、有工具边界、有交班、有考核的常驻数字员工
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AgentPost.php';
require_login();
require_perm('settings');

$posts = agent_posts();
foreach ($posts as &$p) {
    $p['kpi_now'] = agent_post_evaluate((string)$p['id']);
    $shifts = agent_post_shifts((string)$p['id']);
    $p['shift_count'] = count($shifts);
    $p['last_shift'] = $shifts ? end($shifts) : null;
}
unset($p);
admin_header('AI 岗位');
?>
<div class="admin-layout">
  <?php admin_sidebar('agent-posts'); ?>
  <div class="main">
    <div class="v-head">
      <div><h1>AI 岗位</h1><p class="v-sub">不是跑一次的任务,是有 KPI、有工具边界、每天交班的数字员工。草稿永远待审,发布永远由人拍板;连续两个周期不达标自动降级为「仅建议」。</p></div>
      <div class="v-actions"><span class="note mono" style="margin:0" id="apMsg"></span></div>
    </div>

    <?php foreach ($posts as $p): $kpi = $p['kpi_now'] ?? []; $last = $p['last_shift']; ?>
    <div class="card" style="margin-bottom:16px" id="post-<?=htmlspecialchars($p['id'])?>">
      <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap">
        <div style="font-size:34px;line-height:1"><?=htmlspecialchars($p['emoji'] ?? '🤖')?></div>
        <div style="flex:1;min-width:240px">
          <h2 style="font-size:16px;margin:0 0 4px">
            <?=htmlspecialchars($p['name'])?>
            <?php $st = $p['status'] ?? 'active'; ?>
            <span class="badge <?=$st === 'active' ? 'ok' : ($st === 'suggest_only' ? 'warn' : '')?>" style="margin-left:8px"><?=$st === 'active' ? '在岗' : ($st === 'suggest_only' ? '仅建议(考核降级)' : '已停用')?></span>
          </h2>
          <p class="note" style="margin:0 0 6px"><?=htmlspecialchars($p['motto'] ?? '')?></p>
          <p style="margin:0;font-size:13px"><b>目标:</b> <?=htmlspecialchars($p['goal'] ?? '')?></p>
          <?php if (!empty($p['note'])): ?><p class="note" style="margin:4px 0 0;color:var(--warn)"><?=htmlspecialchars($p['note'])?></p><?php endif; ?>
        </div>
        <div style="text-align:right">
          <div style="font-size:13px;margin-bottom:8px">
            本周期出稿 <b><?=$kpi['count'] ?? 0?></b> / 目标 <?=htmlspecialchars((string)($p['kpi']['target'] ?? '?'))?>
            <?php if (($kpi['met'] ?? false)): ?><span class="badge ok">达标</span>
            <?php elseif (isset($kpi['ratio'])): ?><span class="badge <?=$kpi['ratio'] >= 0.5 ? '' : 'warn'?>"><?=round(($kpi['ratio'] ?? 0) * 100)?>%</span><?php endif; ?>
          </div>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-p btn-sm" onclick="apRun('<?=htmlspecialchars($p['id'])?>', this)">▶ 立即交班</button>
            <?php if ($st === 'suggest_only'): ?>
              <button class="btn btn-s btn-sm" onclick="apReactivate('<?=htmlspecialchars($p['id'])?>')">恢复在岗</button>
            <?php elseif ($st === 'active'): ?>
              <button class="btn btn-s btn-sm" onclick="apToggle('<?=htmlspecialchars($p['id'])?>')">停用</button>
            <?php else: ?>
              <button class="btn btn-s btn-sm" onclick="apToggle('<?=htmlspecialchars($p['id'])?>')">启用</button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px" class="tags">
        <span class="badge">工具白名单:<?=htmlspecialchars(implode(', ', (array)($p['tools'] ?? [])))?></span>
        <span class="badge">风险策略:<?=htmlspecialchars($p['risk_policy'] ?? '')?></span>
        <span class="badge">节奏:<?=htmlspecialchars($p['schedule'] ?? 'daily')?></span>
        <span class="badge">已交班 <?=$p['shift_count']?> 次</span>
      </div>

      <?php if ($last): ?>
      <div style="margin-top:14px;padding:12px 14px;background:var(--surface-2);border-radius:10px">
        <div style="font-size:12.5px;color:var(--faint);margin-bottom:6px">最近交班 · <?=htmlspecialchars((string)($last['at'] ?? ''))?></div>
        <div style="font-size:14px"><?=htmlspecialchars((string)($last['summary'] ?? ''))?></div>
        <?php if (!empty($last['steps'])): ?>
        <details style="margin-top:8px"><summary style="font-size:12.5px;color:var(--faint);cursor:pointer">执行明细</summary>
          <table class="tbl" style="margin-top:8px"><thead><tr><th>步骤</th><th>结果</th><th>说明</th><th>耗时</th></tr></thead><tbody>
          <?php foreach ((array)$last['steps'] as $s): ?>
            <tr><td class="mono"><?=htmlspecialchars((string)($s['step'] ?? ''))?></td>
                <td><?=$s['ok'] ?? false ? '✅' : '➖'?></td>
                <td><?=htmlspecialchars((string)($s['detail'] ?? ''))?></td>
                <td class="mono"><?=(int)($s['ms'] ?? 0)?>ms</td></tr>
          <?php endforeach; ?>
          </tbody></table>
        </details>
        <?php endif; ?>
        <?php if (!empty($last['article_id'])): ?>
          <div style="margin-top:8px"><a class="btn btn-s btn-sm" href="/xmp/article-edit?id=<?=htmlspecialchars((string)$last['article_id'])?>">查看草稿 →</a></div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <p class="note" style="margin-top:12px">还没有交班记录——点「立即交班」或等今天的心跳。</p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="card">
      <h2 style="font-size:15px">岗位是什么</h2>
      <p style="font-size:13.5px;line-height:1.8;color:var(--muted)">
        岗位 = AgentRuntime 的受控封装:<b>工具白名单</b>(只授 safe 级能力,<code>publish_content</code> 等高风险动作永不授予)、
        <b>每日交班</b>(cron 心跳驱动,过程落盘)、<b>KPI 考核</b>(周期出稿数 vs 目标)、<b>自动降级</b>(连续两周期 &lt;50% 转为仅建议,人工确认后恢复)。
        每天的交班会出现在「今日主线」,点开就能看到选题来源、草稿链接与健康分。
      </p>
    </div>
  </div>
</div>

<script>
function apMsg(t, ok) { var el = document.getElementById('apMsg'); if (el) { el.textContent = t; el.style.color = ok ? '' : 'var(--danger)'; } }
async function apApi(action, id, extra) {
  var body = new FormData(); body.append('action', action); if (id) body.append('id', id);
  if (extra) for (var k in extra) body.append(k, extra[k]);
  var csrf = (document.cookie.match(/(^|;\s*)of_csrf=([^;]+)/) || [])[2];
  if (csrf) body.append('csrf_token', decodeURIComponent(csrf));
  var r = await fetch('/api/agent-posts.php', { method: 'POST', body: body });
  return r.json();
}
async function apRun(id, btn) {
  if (btn) { btn.disabled = true; btn.textContent = '交班中…(AI 写稿约需 30-90 秒)'; }
  apMsg('');
  try {
    var r = await apApi('run', id, { force: '1' });
    if (r.ok) { apMsg('已交班,页面即将刷新', true); setTimeout(function () { location.reload(); }, 800); }
    else apMsg(r.error || '交班失败', false);
  } catch (e) { apMsg('请求异常: ' + e.message, false); }
  if (btn) { btn.disabled = false; btn.textContent = '▶ 立即交班'; }
}
async function apToggle(id) { var r = await apApi('toggle', id); if (r.ok) location.reload(); else apMsg(r.error || '操作失败', false); }
async function apReactivate(id) { var r = await apApi('reactivate', id); if (r.ok) location.reload(); else apMsg(r.error || '操作失败', false); }
</script>
<?php admin_footer(); ?>
