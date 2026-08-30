<?php
/**
 * 增长大脑 —— 中枢 NBA 提议器（只读驾驶舱，AUDIT-07 P0-3）
 *
 * 读 CDP 画像（含成交反哺信号）+ 成交真相账本，跨模块产出"现在最该动的人 +
 * 下一最佳动作 + 理由"。先只读建议：这里只提议，采纳按钮是去对应模块的快捷入口，
 * 不在本页执行任何写操作。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/CdpSync.php';
require_once __DIR__ . '/../lib/GrowthSignal.php';
require_once __DIR__ . '/../lib/GrowthGoal.php';
require_once __DIR__ . '/../lib/GrowthAction.php';
require_once __DIR__ . '/../lib/GrowthBrain.php';
require_once __DIR__ . '/../lib/SalesPlaybook.php';
require_once __DIR__ . '/../lib/GrowthMemory.php';
require_login();
require_perm('brain');

// ── 写操作（都非客户侧执行：配置目标 / 建待办 / 改待办状态）──
$act = $_POST['action'] ?? '';
if ($act === 'set_goal') {
    csrf_verify();
    growth_goal_set([
        'title'       => $_POST['title'] ?? '',
        'metric'      => $_POST['metric'] ?? 'revenue',
        'target'      => $_POST['target'] ?? 0,
        'window_days' => $_POST['window_days'] ?? 0,
    ]);
    header('Location: /xmp/brain?goal=set'); exit;
}
if ($act === 'clear_goal') {
    csrf_verify(); growth_goal_clear();
    header('Location: /xmp/brain?goal=cleared'); exit;
}
if ($act === 'adopt') {
    csrf_verify();
    growth_action_adopt([
        'profile_id'    => $_POST['profile_id'] ?? '',
        'profile_name'  => $_POST['profile_name'] ?? '',
        'profile_email' => $_POST['profile_email'] ?? '',
        'module'        => $_POST['module'] ?? '',
        'action'        => $_POST['nba_action'] ?? '',
        'reason'        => $_POST['reason'] ?? '',
        'cta'           => $_POST['cta'] ?? '',
        'goal_metric'   => $_POST['goal_metric'] ?? '',
    ]);
    header('Location: /xmp/brain?adopted=1#inbox'); exit;
}
if ($act === 'complete_action') { csrf_verify(); growth_action_complete((string)($_POST['id'] ?? '')); header('Location: /xmp/brain?done=1#inbox'); exit; }
if ($act === 'dismiss_action')  { csrf_verify(); growth_action_dismiss((string)($_POST['id'] ?? '')); header('Location: /xmp/brain#inbox'); exit; }

// ── 取数（只读、有界）──
$rows = [];
try {
    cdp_ensure_table();
    $rows = Database::query("SELECT * FROM cdp_customers ORDER BY score DESC, lifetime_value DESC LIMIT 200");
} catch (\Throwable $e) { $rows = []; }

$truth    = growth_conversion_truth();
$goal     = growth_goal_current();
$progress = growth_goal_progress($goal);
$digest   = growth_brain_digest(is_array($rows) ? $rows : [], $truth, 40, $goal);

// 已采纳（pending）的提议不再重复冒到建议里
$openKeys = growth_action_open_keys();
$digest = array_values(array_filter($digest, function ($r) use ($openKeys) {
    $p = $r['profile']; $who = $p['id'] ?: ($p['email'] ?: $p['name']);
    return !isset($openKeys[growth_action_key($who, $r['best']['action'] ?? '')]);
}));
$digest  = array_slice($digest, 0, 25);
$pending = growth_action_pending();
$stats   = growth_action_stats();

// 动作 → 对应模块入口（只读建议：仅快捷跳转，不代执行）
function brain_cta_link(array $best): string {
    $a = $best['action'] ?? ''; $m = $best['module'] ?? '';
    if (strpos($a, '报价') !== false || strpos($a, '推成交') !== false) return '/xmp/quotes';
    if ($m === 'MA')      return '/xmp/canvas';
    if ($m === 'Content') return '/xmp/promos';
    return '/xmp/crm';
}
function brain_badge(string $m): string {
    $map = ['Sales'=>'#2563eb','MA'=>'#7c3aed','Content'=>'#059669'];
    $c = $map[$m] ?? '#64748b';
    return '<span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:700;color:#fff;background:'.$c.'">'.htmlspecialchars($m).'</span>';
}

admin_header('增长大脑');
?>
<div style="max-width:1080px">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div>
      <h1 style="margin:0 0 4px">🧠 增长大脑 <span style="font-size:12px;font-weight:600;color:var(--faint);border:1px solid var(--border);padding:1px 8px;border-radius:999px;vertical-align:middle">胚胎 · 只读建议</span></h1>
      <p class="v-sub" style="margin:0">读画像 + 成交真相，跨模块告诉你「现在最该动的人和下一步动作」。采纳=去对应模块，本页不代执行。</p>
    </div>
  </div>

  <!-- 共享目标：大脑据此给"离目标最近的动作"加权（P1-5）-->
  <div class="card" style="padding:16px;margin:16px 0">
    <?php if ($progress['has'] ?? false): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <div style="font-size:12px;color:var(--faint)">当前目标 · 大脑正朝它加权</div>
          <div style="font-weight:800;font-size:16px"><?=htmlspecialchars($progress['title'])?>
            <span style="font-size:13px;font-weight:600;color:var(--faint)">（<?=htmlspecialchars($progress['label'])?>）</span>
            <?php if ($progress['pace_note']): ?><span style="font-size:12px;font-weight:700;color:<?=$progress['pace_note']==='落后进度'?'#dc2626':($progress['pace_note']==='领先进度'?'#059669':'#64748b')?>"> · <?=$progress['pace_note']?></span><?php endif; ?>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-weight:800;font-size:16px"><?=htmlspecialchars($progress['display'])?></div>
          <div style="font-size:12px;color:var(--faint)"><?=$progress['pct']?>%</div>
        </div>
      </div>
      <div style="height:8px;border-radius:999px;background:var(--border);margin-top:10px;overflow:hidden">
        <div style="height:100%;width:<?=$progress['pct']?>%;background:linear-gradient(90deg,#4f46e5,#7c3aed)"></div>
      </div>
      <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="clear_goal">
        <button class="btn btn-ghost btn-sm">清除目标</button>
        <span style="font-size:12px;color:var(--faint);margin-left:8px">换目标：在下方重新设定即可</span>
      </form>
    <?php else: ?>
      <div style="font-weight:700;margin-bottom:4px">还没有增长目标</div>
      <div class="v-sub" style="margin-bottom:10px">设一个目标，大脑会把"离它最近的动作"顶到前面（要钱→抬成交动作、要人→抬内容培育）。</div>
    <?php endif; ?>
    <form method="post" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <?= csrf_field() ?><input type="hidden" name="action" value="set_goal">
      <input name="title" placeholder="目标名，如 本季冲收入" style="flex:1;min-width:160px" required>
      <select name="metric" style="min-width:110px">
        <?php foreach (growth_goal_metrics() as $mk => $mm): ?>
        <option value="<?=$mk?>"><?=htmlspecialchars($mm['label'])?></option>
        <?php endforeach; ?>
      </select>
      <input name="target" type="number" min="0" step="1" placeholder="目标值" style="width:110px" required>
      <input name="window_days" type="number" min="0" step="1" placeholder="周期(天,可选)" style="width:120px">
      <button class="btn btn-sm">设为当前目标</button>
    </form>
  </div>

  <!-- 成交真相：谁真转化成收入（P0-2 账本）-->
  <div class="card" style="padding:16px;margin:16px 0">
    <div style="font-weight:700;margin-bottom:4px">成交真相 · 谁真转化成收入</div>
    <div class="v-sub" style="margin-bottom:12px">来自成交反哺账本——按<strong>收入</strong>而非访问量排。这是大脑判断"把力气投给谁"的真相源。</div>
    <?php if (($truth['total']['count'] ?? 0) === 0): ?>
      <div style="color:var(--faint);font-size:13px">还没有成交数据。等第一笔 purchase / 线索赢单落账后，这里会长出"哪个来源、哪个分群真赚钱"。</div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <?php foreach ([['来源','sources'],['分群','segments']] as [$label,$key]): ?>
        <div>
          <div style="font-size:12px;color:var(--faint);margin-bottom:6px"><?=$label?>（按收入）</div>
          <?php $items = array_slice($truth[$key] ?? [], 0, 5); if (!$items): ?>
            <div style="color:var(--faint);font-size:13px">—</div>
          <?php else: foreach ($items as $it): ?>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px dashed var(--border)">
              <span><?=htmlspecialchars($it['key'])?> <span style="color:var(--faint)">· <?=$it['count']?>单</span></span>
              <span style="font-weight:700">¥<?=number_format($it['revenue'])?> <span style="color:var(--faint);font-weight:400">均¥<?=number_format($it['avg'])?></span></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- 采纳箱：已采纳待处理的动作（判断→行动的闭环）-->
  <div id="inbox" style="display:flex;align-items:center;gap:10px;margin:22px 0 10px">
    <span style="font-weight:700">📥 采纳箱 · 待处理（<?=count($pending)?>）</span>
    <span style="font-size:12px;color:var(--faint)">已完成 <?=$stats['done']?> · 忽略 <?=$stats['dismissed']?></span>
  </div>
  <?php if (!$pending): ?>
    <div class="card" style="padding:16px;text-align:center;color:var(--faint);font-size:13px">
      还没采纳任何动作。在下面的建议里点「采纳」，它会进这里成为待办，并带上下文指向对应模块。
    </div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($pending as $a): ?>
      <div class="card" style="padding:12px 14px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;border-left:3px solid var(--accent,#4f46e5)">
        <div style="flex:1;min-width:220px">
          <div style="font-weight:700;font-size:14px"><?=brain_badge($a['module'] ?: '—')?> <?=htmlspecialchars($a['action'])?>
            <span style="font-size:12px;font-weight:600;color:var(--faint)">· <?=htmlspecialchars($a['profile_name'] ?: ($a['profile_email'] ?: '匿名'))?></span></div>
          <div style="font-size:12px;color:var(--faint)"><?=htmlspecialchars($a['reason'])?></div>
        </div>
        <a href="<?=htmlspecialchars($a['link'])?>" class="btn btn-sm btn-primary"><?=htmlspecialchars($a['cta'] ?: '去处理')?> →</a>
        <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="complete_action"><input type="hidden" name="id" value="<?=htmlspecialchars($a['id'])?>"><button class="btn btn-sm btn-ghost">完成</button></form>
        <form method="post" style="margin:0" onsubmit="return confirm('忽略这条建议?')"><?= csrf_field() ?><input type="hidden" name="action" value="dismiss_action"><input type="hidden" name="id" value="<?=htmlspecialchars($a['id'])?>"><button class="btn btn-sm btn-ghost" style="color:var(--faint)">忽略</button></form>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- 驾驶舱：现在最该动的人 -->
  <div style="font-weight:700;margin:22px 0 10px">现在最该动的人 · 下一最佳动作（<?=count($digest)?>）</div>
  <?php if (!$digest): ?>
    <div class="card" style="padding:30px;text-align:center;color:var(--faint)">
      还没有可提议的画像。<br>等 CDP 里积累了行为与成交信号（互动分 / 成交 / 沉默天数），大脑就会在这里排出"该找谁、做什么"。
    </div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach ($digest as $r): $p=$r['profile']; $b=$r['best']; ?>
      <div class="card" style="padding:14px 16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:34px;height:34px;border-radius:50%;background:var(--accent-soft,#eef2ff);color:var(--accent,#4f46e5);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px">
          <?=$b['priority']?>
        </div>
        <div style="min-width:150px">
          <div style="font-weight:700;font-size:14px"><?=htmlspecialchars($p['name'])?></div>
          <div style="font-size:12px;color:var(--faint)">分<?=$p['score']?> · <?=$p['won_count']?>单 · ¥<?=number_format($p['ltv'])?> · <?=$p['days_idle']>=9999?'—':$p['days_idle'].'天未回'?></div>
        </div>
        <div style="flex:1;min-width:220px">
          <div style="font-weight:700;font-size:14px;margin-bottom:2px"><?=brain_badge($b['module'])?> <?=htmlspecialchars($b['action'])?><?php if (!empty($b['goal_boosted'])): ?> <span title="离当前目标最近" style="font-size:11px">🎯</span><?php endif; ?></div>
          <div style="font-size:13px;color:var(--text-soft,#475569)"><?=htmlspecialchars($b['reason'])?></div>
          <?php if (!empty($r['alts'])): ?>
          <div style="font-size:12px;color:var(--faint);margin-top:4px">备选：<?php $al=array_map(fn($x)=>htmlspecialchars($x['action']),$r['alts']); echo implode(' · ',$al); ?></div>
          <?php endif; ?>
          <?php
            $mem = '';
            try { $subj = $p['email'] ?: ($p['id'] ?: $p['name']); if ($subj) $mem = gmem_brief($subj, 4); } catch (\Throwable $e) {}
          ?>
          <?php if ($mem !== ''): ?>
          <details style="margin-top:6px">
            <summary style="cursor:pointer;font-size:12px;color:var(--faint)">🧠 关于他的记忆</summary>
            <pre style="font-size:11.5px;line-height:1.7;color:var(--text-soft,#475569);margin:6px 0 0;white-space:pre-wrap;font-family:inherit"><?=htmlspecialchars($mem)?></pre>
          </details>
          <?php endif; ?>
          <?php $pb = playbook_for_proposal($b, $p); ?>
          <details style="margin-top:6px">
            <summary style="cursor:pointer;font-size:12px;color:var(--accent,#4f46e5)">📝 拿来即用的<?=htmlspecialchars($pb['title'])?></summary>
            <textarea readonly onclick="this.select()" style="width:100%;margin-top:6px;font-size:12px;line-height:1.7;padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--surface,#fafafa)" rows="7"><?=htmlspecialchars($pb['body'])?></textarea>
            <?php if (!empty($pb['tips'])): ?>
            <div style="font-size:11px;color:var(--faint);margin-top:4px">提示：<?=htmlspecialchars(implode('；', $pb['tips']))?></div>
            <?php endif; ?>
          </details>
        </div>
        <form method="post" style="margin:0">
          <?= csrf_field() ?><input type="hidden" name="action" value="adopt">
          <input type="hidden" name="profile_id" value="<?=htmlspecialchars($p['id'])?>">
          <input type="hidden" name="profile_name" value="<?=htmlspecialchars($p['name'])?>">
          <input type="hidden" name="profile_email" value="<?=htmlspecialchars($p['email'])?>">
          <input type="hidden" name="module" value="<?=htmlspecialchars($b['module'])?>">
          <input type="hidden" name="nba_action" value="<?=htmlspecialchars($b['action'])?>">
          <input type="hidden" name="reason" value="<?=htmlspecialchars($b['reason'])?>">
          <input type="hidden" name="cta" value="<?=htmlspecialchars($b['cta'] ?? '去处理')?>">
          <input type="hidden" name="goal_metric" value="<?=htmlspecialchars($goal['metric'] ?? '')?>">
          <button class="btn btn-sm btn-primary">采纳 →</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p style="font-size:12px;color:var(--faint);margin-top:18px">
    "增长大脑"胚胎：规则驱动、可解释。判断→行动的闭环已接上——采纳一条提议即进上方
    采纳箱，并带这个人的上下文去对应模块（报价单自动预填）；本页不代执行客户侧动作，
    发出由模块自己的确认步把关。下一步给它接上跨模块的共享记忆（AUDIT-07 P1）。
  </p>
</div>
<?php admin_footer(); ?>
