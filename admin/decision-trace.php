<?php
/**
 * 决策轨道 —— Agent 为什么这么建议，可看可纠可审计（BACKLOG T2-5）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/DecisionTrace.php';
require_login();
require_perm('brain');

$subject = trim((string)($_GET['subject'] ?? ''));
$traces = dtrace_list($subject, 60);
$stats = dtrace_stats();
$stageIcon = ['trigger'=>'⏱','evidence'=>'🔍','candidates'=>'🧩','decision'=>'🎯','guard'=>'🛡'];

admin_header('决策轨道');
?>
<div style="max-width:940px">
  <h1 style="margin:0 0 4px">🛤 决策轨道</h1>
  <p class="v-sub" style="margin:0 0 14px">Agent 的每次建议都留一条可读轨迹：为什么是现在、看了什么、想过哪些、最终选了什么、护栏怎么判。<strong>决策不是黑箱，你能看能纠。</strong></p>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
    <div class="card" style="padding:12px 16px"><strong style="font-size:20px"><?=$stats['total']?></strong> <span style="font-size:12px;color:var(--faint)">条决策</span></div>
    <div class="card" style="padding:12px 16px"><strong style="font-size:20px"><?=$stats['acted']?></strong> <span style="font-size:12px;color:var(--faint)">被执行</span></div>
    <div class="card" style="padding:12px 16px"><strong style="font-size:20px"><?=$stats['rate']?>%</strong> <span style="font-size:12px;color:var(--faint)">采纳率</span></div>
    <form method="get" style="margin-left:auto;display:flex;gap:6px">
      <input name="subject" value="<?=htmlspecialchars($subject)?>" placeholder="按联系人筛选" style="min-width:180px">
      <button class="btn btn-ghost btn-sm">筛选</button>
    </form>
  </div>

  <?php if (!$traces): ?>
    <div class="card" style="padding:34px;text-align:center;color:var(--faint)">
      还没有决策记录。到「增长大脑」采纳一条建议，这里就会留下它的完整推理轨迹。
    </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ($traces as $t): ?>
    <div class="card" style="padding:14px 16px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
        <strong style="font-size:14px"><?=htmlspecialchars($t['decision'] ?: '（未命名决策）')?></strong>
        <?php if (!empty($t['module'])): ?><span style="font-size:11px;padding:1px 8px;border-radius:999px;background:var(--accent-soft,#eef2ff);color:var(--accent,#4f46e5)"><?=htmlspecialchars($t['module'])?></span><?php endif; ?>
        <span style="font-size:12px;color:var(--faint)"><?=htmlspecialchars($t['subject'] ?: '—')?></span>
        <span style="font-size:12px;color:var(--faint);margin-left:auto"><?=htmlspecialchars($t['created_at'])?></span>
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;border-left:2px solid var(--border);padding-left:12px">
        <?php foreach ($t['steps'] as $s): if (trim((string)($s['detail'] ?? '')) === '') continue; ?>
        <div style="font-size:12.5px">
          <span style="color:var(--faint)"><?=$stageIcon[$s['stage']] ?? '·'?> <?=htmlspecialchars($s['label'] ?? '')?>：</span>
          <?=htmlspecialchars($s['detail'])?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (!empty($t['outcome'])): ?>
      <div style="font-size:12px;color:#16a34a;margin-top:6px">✔ 结果：<?=htmlspecialchars($t['outcome'])?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
