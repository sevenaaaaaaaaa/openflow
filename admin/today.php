<?php
/**
 * 今日主线 — /xmp/today
 *
 * 加法层：不替换、不合并现有 index.php / workspace.php，仅新增一条「行动脊柱」。
 * 把全站可行动信号编排成 立即 / 今日 / 本周 三泳道，给出「今天先做这件事」，
 * 完成后回流写 mainline/events.json + DecisionTrace，供排序与后续 AI 决策使用。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/Mainline.php';
require_login();

// 处理回流：完成 / 稍后
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ml_action'])) {
    csrf_verify();
    $id = preg_replace('/[^a-z0-9:._-]/i', '', (string)($_POST['id'] ?? ''));
    $act = $_POST['ml_action'] === 'done' ? 'done' : 'snoozed';
    if ($id !== '') {
        mainline_log($id, $act, ['title' => mb_substr((string)($_POST['title'] ?? ''), 0, 120), 'source' => (string)($_POST['source'] ?? '')]);
        // 回流到决策轨迹：处理结果进入自生长回路
        try {
            $dt = __DIR__ . '/../lib/DecisionTrace.php';
            if (is_file($dt)) {
                require_once $dt;
                if (function_exists('dtrace_record')) {
                    $tid = dtrace_record([
                        'subject_type' => 'mainline', 'subject_id' => $id,
                        'action' => (string)($_POST['title'] ?? $id),
                        'reason' => '今日主线处理', 'module' => 'Mainline',
                    ]);
                    if ($tid && function_exists('dtrace_outcome')) dtrace_outcome($tid, $act === 'done' ? 'completed' : 'snoozed');
                }
            }
        } catch (\Throwable $e) {}
    }
    header('Location: /xmp/today');
    exit;
}

$items = mainline_items();
$summary = mainline_summary($items);
$lanes = mainline_lanes($items);

// 目标进度（若有）
$goal = null; $goalProg = null;
try {
    $gf = __DIR__ . '/../lib/GrowthGoal.php';
    if (is_file($gf)) {
        require_once $gf;
        if (function_exists('growth_goal_current')) {
            $goal = growth_goal_current();
            if ($goal && function_exists('growth_goal_progress')) $goalProg = growth_goal_progress($goal);
        }
    }
} catch (\Throwable $e) {}

$severityMeta = [
    'critical' => ['st' => 'st-danger', 'label' => '紧急'],
    'warn'     => ['st' => 'st-warn',   'label' => '重要'],
    'info'     => ['st' => 'st-faint',  'label' => '待办'],
    'good'     => ['st' => 'st-ok',     'label' => '机会'],
];
$laneMeta = [
    'now'   => ['立即处理', '钱 · 风险 · 时效', '🔥'],
    'today' => ['今天推进', '让增长往前一步', '☀️'],
    'week'  => ['本周判断', '策略与机会', '🧭'],
];
$top = $summary['top'];

admin_header('今日主线');
?>
<style>
.ml-hero{border-color:var(--accent);background:linear-gradient(135deg,var(--accent-soft,oklch(0.95 0.03 262)),transparent 62%)}
.ml-hero .p-body{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
.ml-hero .ml-hero-ic{font-size:26px;line-height:1;flex:none}
.ml-hero .ml-hero-body{flex:1;min-width:240px}
.ml-hero .ml-hero-k{font-size:11.5px;font-weight:700;letter-spacing:.05em;color:var(--accent);margin-bottom:6px}
.ml-hero .ml-hero-t{font-size:17px;font-weight:750;line-height:1.45}
.ml-hero .ml-hero-w{font-size:13px;color:var(--muted);margin-top:5px;line-height:1.65}
.ml-lane{margin-top:18px}
.ml-lane-h{display:flex;align-items:center;gap:10px;margin:0 0 10px}
.ml-lane-h h3{font-size:15px;font-weight:750}
.ml-lane-h .ml-lane-sub{font-size:12px;color:var(--faint,var(--muted))}
.ml-lane-h .ml-count{margin-left:auto;font-family:var(--font-mono,monospace);font-size:12px;color:var(--muted)}
.ml-item{display:flex;gap:12px;align-items:flex-start;padding:13px 14px;border:1px solid var(--border-soft,var(--border));border-radius:var(--r-sm,10px);background:var(--surface);margin-bottom:9px}
.ml-item .ml-ico{flex:none;font-size:18px;line-height:1.3;width:24px;text-align:center}
.ml-item .ml-body{flex:1;min-width:0}
.ml-item .ml-t{font-size:14px;font-weight:650;line-height:1.5;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.ml-item .ml-w{font-size:12.5px;color:var(--muted);margin-top:4px;line-height:1.6}
.ml-item .ml-act{flex:none;display:flex;gap:7px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.ml-item .ml-src{font-size:11px;color:var(--faint,var(--muted));border:1px solid var(--border);border-radius:999px;padding:1px 8px}
.ml-goal .p-body{display:flex;gap:20px;align-items:center;flex-wrap:wrap}
@media(max-width:720px){.ml-item .ml-act{width:100%;justify-content:flex-start;padding-left:36px}}
</style>
<div class="admin-layout">
  <?php admin_sidebar('today'); ?>
  <div class="main">
    <div class="v-head">
      <div>
        <h1>今日主线</h1>
        <p class="v-sub">把全站可行动信号收成一条行动脊柱：先处理钱与时效，再推进今日目标，最后看本周策略。完成会回流，主线会越来越懂你。</p>
      </div>
      <div class="v-actions">
        <a href="/xmp/today" class="btn btn-s btn-sm">↻ 刷新</a>
        <a href="/xmp/workspace" class="btn btn-s btn-sm">看旧工作台对比</a>
      </div>
    </div>

    <?php if ($top): ?>
    <div class="panel ml-hero" style="margin-bottom:16px">
      <div class="p-body">
        <div class="ml-hero-ic"><?=htmlspecialchars($top['icon'])?></div>
        <div class="ml-hero-body">
          <div class="ml-hero-k">今天先做这件事</div>
          <div class="ml-hero-t"><?=htmlspecialchars($top['title'])?></div>
          <?php if (!empty($top['why'])): ?><div class="ml-hero-w">为什么是现在：<?=htmlspecialchars($top['why'])?></div><?php endif; ?>
        </div>
        <div class="ml-act" style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if (!empty($top['action_url'])): ?><a class="btn btn-p btn-sm" href="<?=htmlspecialchars($top['action_url'])?>"><?=htmlspecialchars($top['action_label'])?></a><?php endif; ?>
          <form method="post" style="display:inline" data-no-guard>
            <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
            <input type="hidden" name="id" value="<?=htmlspecialchars($top['id'])?>">
            <input type="hidden" name="title" value="<?=htmlspecialchars($top['title'])?>">
            <input type="hidden" name="source" value="<?=htmlspecialchars($top['source'])?>">
            <button class="btn btn-s btn-sm" name="ml_action" value="done">标记完成</button>
          </form>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="panel" style="margin-bottom:16px;border-color:var(--ok)">
      <div class="p-body" style="display:flex;gap:12px;align-items:center">
        <span style="font-size:22px">✅</span>
        <div><b>主线已清空。</b><span class="text-muted" style="font-size:13px">没有需要立刻处理的信号——可以去创作台产出内容，或让系统体检发现新机会。</span></div>
        <a href="/xmp/create" class="btn btn-s btn-sm" style="margin-left:auto">去创作台</a>
      </div>
    </div>
    <?php endif; ?>

    <div class="kpi-grid">
      <div class="kpi"><div class="k-label">立即处理</div><div class="k-val mono"><?=$summary['now']?></div><div class="k-sub">钱 / 风险 / 时效</div></div>
      <div class="kpi"><div class="k-label">今天推进</div><div class="k-val mono"><?=$summary['today']?></div><div class="k-sub">目标相关</div></div>
      <div class="kpi"><div class="k-label">本周判断</div><div class="k-val mono"><?=$summary['week']?></div><div class="k-sub">策略与机会</div></div>
      <div class="kpi"><div class="k-label">预计耗时</div><div class="k-val mono"><?=$summary['est_min']?><span style="font-size:13px">min</span></div><div class="k-sub">清空立即+今天</div></div>
    </div>

    <?php if ($goal && $goalProg): ?>
    <div class="panel ml-goal" style="margin-top:16px">
      <div class="p-head"><h3>本周目标</h3><span class="p-sub mono"><?=htmlspecialchars((string)($goal['metric'] ?? ''))?></span></div>
      <div class="p-body">
        <div style="flex:1;min-width:220px">
          <div style="font-size:15px;font-weight:700"><?=htmlspecialchars((string)($goal['title'] ?? '增长目标'))?></div>
          <div style="font-size:12.5px;color:var(--muted);margin-top:4px">当前 <?=htmlspecialchars((string)round((float)($goalProg['current'] ?? 0)))?> / 目标 <?=htmlspecialchars((string)round((float)($goalProg['target'] ?? 0)))?></div>
        </div>
        <div style="flex:1;min-width:220px">
          <div style="height:9px;border-radius:999px;background:var(--hover);overflow:hidden">
            <div style="height:100%;width:<?=max(0, min(100, (float)($goalProg['pct'] ?? 0)))?>%;background:var(--accent)"></div>
          </div>
          <div style="font-size:12px;color:var(--muted);margin-top:6px">完成度 <?=round((float)($goalProg['pct'] ?? 0))?>% · <a href="/xmp/brain" style="color:var(--accent)">去增长大脑</a></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php foreach (['now', 'today', 'week'] as $laneKey): $list = $lanes[$laneKey]; [$lt, $ls, $li] = $laneMeta[$laneKey]; ?>
    <div class="ml-lane">
      <div class="ml-lane-h"><span style="font-size:16px"><?=$li?></span><h3><?=$lt?></h3><span class="ml-lane-sub"><?=$ls?></span><span class="ml-count"><?=count($list)?> 项</span></div>
      <?php if (!$list): ?>
      <div class="panel"><div class="p-body text-muted" style="font-size:13px">暂无——很好。</div></div>
      <?php else: foreach ($list as $it): $sm = $severityMeta[$it['severity']] ?? $severityMeta['info']; ?>
      <div class="ml-item">
        <span class="ml-ico"><?=htmlspecialchars($it['icon'])?></span>
        <div class="ml-body">
          <div class="ml-t">
            <span><?=htmlspecialchars($it['title'])?></span>
            <span class="st <?=$sm['st']?>" style="font-size:10.5px;padding:1px 7px;border-radius:999px"><?=$sm['label']?></span>
            <span class="ml-src"><?=htmlspecialchars($it['source'])?></span>
          </div>
          <?php if (!empty($it['why'])): ?><div class="ml-w"><?=htmlspecialchars($it['why'])?></div><?php endif; ?>
        </div>
        <div class="ml-act">
          <?php if (!empty($it['action_url'])): ?><a class="btn btn-p btn-sm" href="<?=htmlspecialchars($it['action_url'])?>"><?=htmlspecialchars($it['action_label'])?></a><?php endif; ?>
          <form method="post" style="display:inline" data-no-guard>
            <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
            <input type="hidden" name="id" value="<?=htmlspecialchars($it['id'])?>">
            <input type="hidden" name="title" value="<?=htmlspecialchars($it['title'])?>">
            <input type="hidden" name="source" value="<?=htmlspecialchars($it['source'])?>">
            <button class="btn btn-s btn-sm" name="ml_action" value="done">完成</button>
            <button class="btn btn-s btn-sm" name="ml_action" value="snooze" style="color:var(--muted)">稍后</button>
          </form>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
    <?php endforeach; ?>

    <p class="text-xs text-muted" style="margin-top:22px">主线只做编排：数据全部来自现有模块，不改动它们。完成/稍后写入 <code>data/mainline/events.json</code> 并回流决策轨迹。</p>
  </div>
</div>
<?php admin_footer(); ?>
