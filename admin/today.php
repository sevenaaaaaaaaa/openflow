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
require_once __DIR__ . '/../lib/AiCenter.php';
require_once __DIR__ . '/../lib/MainlineAi.php';
require_once __DIR__ . '/../lib/ProjectSystem.php';
require_once __DIR__ . '/../lib/TableView.php';
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

/* ── 团队视角：写操作（项目 / 任务 / 拖拽改状态）── */
$viewMode = (($_GET['view'] ?? '') === 'team') ? 'team' : 'mine';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ps_action'])) {
    csrf_verify();
    require_perm('tasks');
    $act = (string) $_POST['ps_action'];
    $pid = ps_safe_id((string) ($_POST['project'] ?? ''));
    $back = '/xmp/today?view=team' . ($pid !== '' ? '&project=' . urlencode($pid) : '');
    $by = function_exists('member_current') ? (string) ((member_current()['name'] ?? '') ?: '') : '';
    if ($act === 'project_save') {
        $r = ps_project_save(['name' => (string) ($_POST['name'] ?? ''), 'desc' => (string) ($_POST['desc'] ?? '')]);
        if ($r['ok'] ?? false) $back = '/xmp/today?view=team&project=' . urlencode((string) $r['id']);
    } elseif ($act === 'task_save' && $pid !== '') {
        ps_task_save($pid, [
            'title' => (string) ($_POST['title'] ?? ''),
            'note' => (string) ($_POST['note'] ?? ''),
            'status' => (string) ($_POST['status'] ?? 'todo'),
            'priority' => (string) ($_POST['priority'] ?? 'normal'),
            'assignee' => (string) ($_POST['assignee'] ?? ''),
            'due' => (string) ($_POST['due'] ?? ''),
            'ref' => ['type' => (string) ($_POST['ref_type'] ?? ''), 'id' => (string) ($_POST['ref_id'] ?? ''), 'label' => (string) ($_POST['ref_label'] ?? '')],
        ]);
    } elseif ($act === 'task_move' && $pid !== '') {
        ps_task_move($pid, (string) ($_POST['id'] ?? ''), (string) ($_POST['status'] ?? 'todo'), $by);
    } elseif ($act === 'task_delete' && $pid !== '') {
        ps_task_delete($pid, (string) ($_POST['id'] ?? ''));
    }
    header('Location: ' . $back);
    exit;
}

$items = mainline_items();
$summary = mainline_summary($items);
$lanes = mainline_lanes($items);
$aiReady = AiCenter::isConfigured();
$judgeCache = mainline_ai_cache();
$receipts = mainline_ai_receipts(6);

// 团队视角数据
$projects = $viewMode === 'team' ? ps_projects() : [];
$curId = ps_safe_id((string) ($_GET['project'] ?? ''));
if ($viewMode === 'team') {
    if ($curId === '' || ps_project_get($curId) === null) $curId = (string) ($projects[0]['id'] ?? '');
}
$curProject = ($viewMode === 'team' && $curId !== '') ? ps_project_get($curId) : null;
$curTasks = $curProject !== null ? ps_tasks($curId) : [];
$kbStats = $curProject !== null ? ps_stats($curId) : ['by_status' => [], 'total' => 0, 'overdue' => 0];
$assignees = $viewMode === 'team' ? ps_assignees() : [];
$teamView = (string) ($_GET['vt'] ?? 'board');
if (!isset(tv_views()[$teamView])) $teamView = 'board';
$tvYm = (string) ($_GET['m'] ?? date('Y-m'));
if (preg_match('/^\d{4}-\d{2}$/', $tvYm) !== 1) $tvYm = date('Y-m');
// 任务 → 多视图（与自定义内容类型共用 lib/TableView.php 的布局函数）
$tvFields = [
    ['key' => 'assignee', 'label' => '负责人', 'type' => 'text'],
    ['key' => 'priority', 'label' => '优先级', 'type' => 'select', 'options' => array_values(ps_priorities())],
    ['key' => 'start', 'label' => '开始', 'type' => 'date'],
    ['key' => 'due', 'label' => '截止', 'type' => 'date'],
    ['key' => 'ref', 'label' => '关联', 'type' => 'text'],
];
$tvRefLabel = static function (array $t): string {
    $ref = (array) ($t['ref'] ?? []);
    if ($ref === [] || (string) ($ref['type'] ?? '') === '') return '';
    return (string) (ps_ref_types()[(string) $ref['type']] ?? $ref['type']) . '：' . (string) ($ref['label'] ?: $ref['id']);
};
$tvRows = [];
foreach ($curTasks as $t) {
    $values = [
        'assignee' => (string) ($t['assignee'] ?? ''),
        'priority' => (string) (ps_priorities()[(string) ($t['priority'] ?? 'normal')] ?? ''),
        'start' => (string) ($t['start'] ?? ''),
        'due' => (string) ($t['due'] ?? ''),
        'ref' => $tvRefLabel($t),
        'status' => (string) ($t['status'] ?? 'todo'),
    ];
    $cells = [];
    foreach ($tvFields as $f) $cells[(string) $f['key']] = tv_display($f, $values[(string) $f['key']]);
    $tvRows[] = ['id' => (string) ($t['id'] ?? ''), 'title' => (string) ($t['title'] ?? ''), 'slug' => '', 'status' => (string) ($t['status'] ?? 'todo'), 'values' => $values, 'resolved' => [], 'cells' => $cells];
}
$tvQ = static fn(array $extra = []): string => '/xmp/today?' . http_build_query(array_merge(['view' => 'team', 'project' => $curId, 'vt' => $teamView], $extra));

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

/* ── 团队视角：看板与表单（本页局部样式）── */
.kb-f{display:flex;flex-direction:column;gap:3px;font-size:11px;color:var(--muted);font-weight:600}
.kanban{display:flex;gap:12px;overflow-x:auto;padding-bottom:12px;align-items:flex-start}
.kanban-col{min-width:238px;max-width:280px;flex:1;background:var(--surface-2);border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:8px}
.kanban-col.kb-over{outline:2px dashed var(--accent);outline-offset:-2px}
.kb-h{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:700;padding:2px 4px}
.kb-h .note{margin-left:auto;background:var(--border);border-radius:99px;padding:1px 8px;font-size:11px}
.kanban-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:10px 11px;cursor:grab;position:relative}
.kanban-card:hover{border-color:var(--accent)}
.kanban-card.kb-dragging{opacity:.45}
.kb-t{font-size:13px;font-weight:650;line-height:1.45;overflow-wrap:anywhere;padding-right:16px}
.kb-m{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px;font-size:11.5px}
.kb-ref{display:inline-block;margin-top:6px;font-size:11.5px;color:var(--accent);text-decoration:none;background:rgba(37,99,235,.09);border-radius:6px;padding:1px 6px}
.kb-note{margin:6px 0 0;font-size:11.5px;color:var(--muted);line-height:1.5;overflow-wrap:anywhere}
.kb-del{position:absolute;top:6px;right:6px;margin:0;opacity:0;transition:opacity .15s}
.kanban-card:hover .kb-del{opacity:1}
.kb-del .btn{padding:1px 7px;line-height:1.5}
.kb-road{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-top:12px}
.kb-road ul{margin:8px 0 0;padding-left:18px;font-size:12.5px;line-height:1.75;color:var(--muted)}
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
        <a href="/xmp/today" class="btn btn-s btn-sm<?=$viewMode === 'mine' ? ' btn-p' : ''?>">我的主线</a>
        <a href="/xmp/today?view=team" class="btn btn-s btn-sm<?=$viewMode === 'team' ? ' btn-p' : ''?>">团队视角</a>
        <a href="/xmp/today<?=$viewMode === 'team' ? '?view=team' : ''?>" class="btn btn-s btn-sm">↻ 刷新</a>
        <a href="/xmp/workspace" class="btn btn-s btn-sm">看旧工作台对比</a>
      </div>
    </div>

    <?php if ($viewMode === 'team'): ?>
    <!-- ═══ 团队视角：项目 / 看板 / 关联 / 到期提醒（默认仍是「我的主线」）═══ -->
    <div class="panel" style="margin-bottom:12px">
      <div class="p-body" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <b style="font-size:13px">项目</b>
        <?php foreach ($projects as $pj): $pjId = ps_safe_id((string) ($pj['id'] ?? '')); $st = ps_stats($pjId); ?>
        <a href="/xmp/today?view=team&project=<?=urlencode($pjId)?>" class="btn btn-s btn-sm<?=$pjId === $curId ? ' btn-p' : ''?>">
          <?=htmlspecialchars((string) ($pj['name'] ?? $pjId))?> <span class="note">· <?=$st['total']?><?=$st['overdue'] > 0 ? ' ⚠' . $st['overdue'] : ''?></span>
        </a>
        <?php endforeach; ?>
        <form method="post" style="margin-left:auto;display:flex;gap:6px">
          <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
          <input type="hidden" name="ps_action" value="project_save">
          <input class="inp sm" name="name" placeholder="新项目名" style="min-width:150px" required>
          <button class="btn btn-s btn-sm">+ 建项目</button>
        </form>
      </div>
      <?php if ($curProject !== null): ?>
      <div class="p-body" style="border-top:1px solid var(--border-soft,var(--border));display:flex;gap:16px;flex-wrap:wrap;align-items:center">
        <span class="note">共 <b><?=$kbStats['total']?></b> 条</span>
        <span class="note">逾期 <b style="color:<?=$kbStats['overdue'] > 0 ? 'var(--danger,#dc2626)' : 'inherit'?>"><?=$kbStats['overdue']?></b></span>
        <?php foreach (ps_task_statuses() as $sk => $sl): ?>
        <span class="note"><?=htmlspecialchars($sl)?> <b><?=$kbStats['by_status'][$sk] ?? 0?></b></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($curProject === null): ?>
      <div class="empty">还没有项目。用上面的「+ 建项目」新建一个；或运行 <code>php scripts/seed-projects.php</code> 种入示例。</div>
    <?php else: ?>
    <div class="panel" style="margin-bottom:12px">
      <form method="post" class="p-body" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
        <input type="hidden" name="ps_action" value="task_save">
        <input type="hidden" name="project" value="<?=htmlspecialchars($curId)?>">
        <label class="kb-f">标题<input class="inp sm" name="title" style="min-width:230px" required placeholder="要做什么"></label>
        <label class="kb-f">负责人<input class="inp sm" name="assignee" list="kbWho" placeholder="谁做" style="width:110px"><datalist id="kbWho"><?php foreach ($assignees as $u): ?><option value="<?=htmlspecialchars((string) $u['name'])?>"><?php endforeach; ?></datalist></label>
        <label class="kb-f">截止<input class="inp sm" type="date" name="due"></label>
        <label class="kb-f">优先级<select class="inp sm" name="priority"><?php foreach (ps_priorities() as $pk => $pl): ?><option value="<?=$pk?>"><?=htmlspecialchars($pl)?></option><?php endforeach; ?></select></label>
        <label class="kb-f">关联<select class="inp sm" name="ref_type"><option value="">不关联</option><?php foreach (ps_ref_types() as $rk => $rl): ?><option value="<?=$rk?>"><?=htmlspecialchars($rl)?></option><?php endforeach; ?></select></label>
        <label class="kb-f">对象 ID<input class="inp sm" name="ref_id" placeholder="如 lead_123" style="width:110px"></label>
        <button class="btn btn-p btn-sm">+ 加任务</button>
      </form>
    </div>

    <div class="tv-bar" style="margin-bottom:10px">
      <span class="note" style="font-size:12px;color:var(--muted)">同一批任务：</span>
      <?php foreach (tv_views() as $vk => $vl): ?>
      <a href="<?=htmlspecialchars($tvQ(['vt' => $vk]))?>" class="btn btn-s btn-sm<?=$vk === $teamView ? ' btn-p' : ''?>"><?=htmlspecialchars($vl)?></a>
      <?php endforeach; ?>
      <?php if ($teamView === 'board'): ?><span class="note" style="font-size:12px;color:var(--muted)">拖拽卡片到别的列即可改状态</span><?php endif; ?>
      <?php if ($teamView === 'calendar'): ?>
      <a href="<?=htmlspecialchars($tvQ(['vt' => 'calendar', 'm' => tv_month_shift($tvYm, -1)]))?>" class="btn btn-s btn-sm">←</a>
      <b style="font-size:12.5px"><?=htmlspecialchars($tvYm)?></b>
      <a href="<?=htmlspecialchars($tvQ(['vt' => 'calendar', 'm' => tv_month_shift($tvYm, 1)]))?>" class="btn btn-s btn-sm">→</a>
      <?php endif; ?>
    </div>

    <?php if ($teamView === 'grid'): ?>
    <table class="tv-grid">
      <thead><tr><th>任务</th><th>状态</th><?php foreach ($tvFields as $f): ?><th><?=htmlspecialchars((string) $f['label'])?></th><?php endforeach; ?><th></th></tr></thead>
      <tbody>
        <?php foreach ($curTasks as $t): $status = (string) ($t['status'] ?? 'todo'); $due = (string) ($t['due'] ?? ''); ?>
        <tr>
          <td><b><?=htmlspecialchars((string) ($t['title'] ?? ''))?></b><?php if ((string) ($t['note'] ?? '') !== ''): ?><div class="text-xs text-muted"><?=htmlspecialchars(mb_substr((string) $t['note'], 0, 70))?></div><?php endif; ?></td>
          <td><?=htmlspecialchars(ps_task_statuses()[$status] ?? $status)?></td>
          <td><?=htmlspecialchars((string) ($t['assignee'] ?? ''))?></td>
          <td><?=htmlspecialchars((string) (ps_priorities()[(string) ($t['priority'] ?? 'normal')] ?? ''))?></td>
          <td><?=htmlspecialchars((string) ($t['start'] ?? ''))?></td>
          <td<?=$due !== '' && substr($due, 0, 10) < date('Y-m-d') ? ' style="color:var(--danger,#dc2626);font-weight:700"' : ''?>><?=htmlspecialchars($due !== '' ? substr($due, 0, 16) : '')?></td>
          <td><?=htmlspecialchars($tvRefLabel($t))?></td>
          <td>
            <form method="post" data-confirm="删除这条任务？" style="margin:0">
              <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
              <input type="hidden" name="ps_action" value="task_delete">
              <input type="hidden" name="project" value="<?=htmlspecialchars($curId)?>">
              <input type="hidden" name="id" value="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>">
              <button class="btn btn-s btn-sm" title="删除">×</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php elseif ($teamView === 'calendar'): $cal = tv_calendar_of($tvFields, $tvRows, $tvYm, 'due'); ?>
    <div class="tv-cal">
      <?php foreach (['一', '二', '三', '四', '五', '六', '日'] as $d): ?><div class="tv-cal-head"><?=$d?></div><?php endforeach; ?>
      <?php for ($i = 0; $i < $cal['lead']; $i++): ?><div class="tv-cal-cell empty"></div><?php endfor; ?>
      <?php foreach ($cal['cells'] as $c): ?>
      <div class="tv-cal-cell<?=$c['date'] === date('Y-m-d') ? ' today' : ''?>">
        <div class="tv-cal-day"><?=$c['day']?></div>
        <?php foreach ($c['rows'] as $r): ?>
        <div class="tv-cal-ev"><b><?=htmlspecialchars($r['title'])?></b><?php if (($r['cells']['assignee'] ?? '') !== ''): ?><div class="text-xs text-muted">@<?=htmlspecialchars((string) $r['cells']['assignee'])?></div><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($cal['undated']): ?>
    <div style="margin-top:12px"><b style="font-size:12.5px">未排期（<?=count($cal['undated'])?>）</b>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px"><?php foreach ($cal['undated'] as $r): ?><span class="tv-cal-ev"><?=htmlspecialchars($r['title'])?></span><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <?php elseif ($teamView === 'gantt'): $g = tv_gantt_of($tvFields, $tvRows, ['start', 'due']); $todayPct = tv_gantt_today($g); ?>
      <?php if ($g['bars'] === []): ?>
        <div class="empty">还没有带「开始 / 截止」的任务，填上日期后这里会按时间跨度画出来。</div>
      <?php else: ?>
      <div class="tv-bar"><span class="note" style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($g['start'])?> → <?=htmlspecialchars($g['end'])?>（共 <?=$g['days']?> 天）· 条=开始→截止，只有一个日期的按里程碑画</span></div>
      <div class="tv-gantt">
        <div class="tv-gantt-scale"><div></div><div class="ticks"><span><?=htmlspecialchars($g['start'])?></span><span><?=htmlspecialchars($g['end'])?></span></div></div>
        <?php foreach ($g['bars'] as $b): ?>
        <div class="tv-gantt-row">
          <div class="tv-gantt-label"><?=htmlspecialchars($b['title'])?><div class="text-xs text-muted"><?=htmlspecialchars($b['start'])?><?=$b['milestone'] ? '' : ' → ' . htmlspecialchars($b['end'])?></div></div>
          <div class="tv-gantt-track"><div class="tv-gantt-bar<?=$b['milestone'] ? ' ms' : ''?>" style="left:<?=round($b['offset'] / max(1, $g['days']) * 100, 3)?>%;width:<?=round($b['span'] / max(1, $g['days']) * 100, 3)?>%"></div></div>
        </div>
        <?php endforeach; ?>
        <?php if ($todayPct !== null): ?><div class="tv-gantt-today" style="left:calc(200px + (100% - 200px) * <?=round($todayPct / 100, 4)?>)"></div><?php endif; ?>
      </div>
      <?php endif; ?>

    <?php else: ?>
    <div class="kanban" id="kbBoard">
      <?php foreach (ps_task_statuses() as $sk => $sl): ?>
      <div class="kanban-col" data-status="<?=$sk?>">
        <div class="kb-h"><b><?=htmlspecialchars($sl)?></b><span class="note"><?=$kbStats['by_status'][$sk] ?? 0?></span></div>
        <?php foreach ($curTasks as $t): if ((string) ($t['status'] ?? 'todo') !== $sk) continue;
          $due = (string) ($t['due'] ?? ''); $overdue = $due !== '' && substr($due, 0, 10) < date('Y-m-d');
          $prio = (string) ($t['priority'] ?? 'normal'); $ref = (array) ($t['ref'] ?? []); ?>
        <div class="kanban-card" draggable="true" data-id="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>">
          <div class="kb-t"><?=htmlspecialchars((string) ($t['title'] ?? ''))?></div>
          <div class="kb-m">
            <?php if ($prio !== 'normal'): ?><span class="pill <?=$prio === 'urgent' ? 'hl' : ''?>"><?=htmlspecialchars(ps_priorities()[$prio] ?? $prio)?></span><?php endif; ?>
            <?php if ((string) ($t['assignee'] ?? '') !== ''): ?><span class="note">@<?=htmlspecialchars((string) $t['assignee'])?></span><?php endif; ?>
            <?php if ($due !== ''): ?><span class="note"<?=$overdue ? ' style="color:var(--danger,#dc2626);font-weight:700"' : ''?>><?=htmlspecialchars(substr($due, 0, 16))?><?=$overdue ? ' · 逾期' : ''?></span><?php endif; ?>
          </div>
          <?php if ($ref !== [] && (string) ($ref['type'] ?? '') !== ''): ?>
          <span class="kb-ref"><?=htmlspecialchars(ps_ref_types()[(string) $ref['type']] ?? (string) $ref['type'])?>：<?=htmlspecialchars((string) ($ref['label'] ?: $ref['id']))?></span>
          <?php endif; ?>
          <?php if ((string) ($t['note'] ?? '') !== ''): ?><p class="kb-note"><?=htmlspecialchars(mb_substr((string) $t['note'], 0, 90))?></p><?php endif; ?>
          <form method="post" class="kb-del" data-confirm="删除这条任务？">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <input type="hidden" name="ps_action" value="task_delete">
            <input type="hidden" name="project" value="<?=htmlspecialchars($curId)?>">
            <input type="hidden" name="id" value="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>">
            <button class="btn btn-s btn-sm" title="删除">×</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="text-xs text-muted" style="margin-top:14px">任务可关联既有对象（线索/订单/发布任务/文章/课程…），所以「发布、分发、CRM 状态」都能挂在同一条任务上；到期与逾期由 cron 每 15 分钟扫描，走飞书 / 企微 / Slack / WhatsApp 外部渠道提醒（同一任务同一到期日只发一次；未配置渠道时不会静默标记，配好后照常提醒）。</p>

    <!-- 能力路线：这个视图还要长成什么样（诚实标注进度） -->
    <div class="panel" style="margin-top:16px">
      <div class="p-body">
        <b style="font-size:13px">这个视图接下来会长成什么</b>
        <div class="kb-road">
          <div><span class="pill">已就绪</span><ul><li>项目 / 任务 / 看板（拖拽改状态）</li><li>任务关联既有对象（发布 · 分发 · CRM · 订单）</li><li>多用户登录与角色权限（复用 <code>tasks</code> 权限）</li><li>提醒引擎与 cron 扫描（飞书 / 企微 / Slack / WhatsApp，幂等，未配渠道不误标）</li></ul></div>
          <div><span class="pill">进行中</span><ul><li>本页内到期提醒（角标 / 待办聚合，不只靠外部渠道）</li><li>任务负责人邮件提醒（复用现有邮件链路）</li><li>看板视觉与设计系统对齐</li></ul></div>
          <div><span class="pill">规划中</span><ul><li>多维表格：relation / rollup / lookup 字段（已可用于自定义内容类型）</li><li>多视图：表格 / 看板 / 日历 / 甘特（任务与内容类型通用）</li><li>记录级层级（父/子任务与树视图）</li><li>项目级成员权限（owner / editor / viewer）</li></ul></div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php else: ?>

    <!-- 每日晨会:岗位干活 → 小福汇报 的闭环入口 -->
    <div class="panel" style="margin-bottom:12px" id="briefPanel">
      <div class="p-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <span style="font-size:18px">☀️</span>
        <div style="flex:1;min-width:220px">
          <b style="font-size:14px">每日晨会</b>
          <span class="note" style="margin-left:8px" id="briefSub">小福的 3 分钟汇报 · 结果 → 重点 → 待拍板</span>
        </div>
        <button class="btn btn-p btn-sm" id="briefPlayBtn" onclick="briefToggle()">▶ 听晨会</button>
        <button class="btn btn-s btn-sm" onclick="briefExpand()">文字版</button>
      </div>
      <div class="p-body" id="briefBody" style="display:none;border-top:1px solid var(--border-soft,var(--border))">
        <div id="briefLoading" class="note" style="padding:4px 0">正在准备今天的晨会…</div>
        <div id="briefText" style="display:none;white-space:pre-wrap;line-height:1.9;font-size:13.5px"></div>
        <div id="briefFoot" style="display:none;margin-top:10px;gap:8px;flex-wrap:wrap;align-items:center">
          <span class="note mono" id="briefMode"></span>
          <button class="btn btn-s btn-sm" id="briefStopBtn" onclick="briefStop()" style="display:none">⏹ 停止朗读</button>
          <button class="btn btn-s btn-sm" onclick="briefToggle()">↻ 重听</button>
        </div>
      </div>
    </div>

    <div class="panel" style="margin-bottom:16px">
      <div class="p-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <span style="font-size:18px">⌘</span>
        <input id="mlCmd" class="inp sm" placeholder="告诉小福要做什么，例如：给 7 天没来的会员发一封召回邮件" style="flex:1;min-width:260px" onkeydown="if(event.key==='Enter')mlCommand()">
        <button class="btn btn-p btn-sm" id="mlCmdBtn" onclick="mlCommand()">指挥</button>
      </div>
      <div class="p-body" id="mlCmdOut" style="display:none;border-top:1px solid var(--border-soft,var(--border))"></div>
    </div>

    <div class="panel ml-hero" style="margin-bottom:16px">
      <div class="p-body" id="mlJudge">
        <div class="ml-hero-ic">🧠</div>
        <div class="ml-hero-body">
          <div class="ml-hero-k">小福的判断 <span class="ml-src" id="mlJudgeMeta">推理中…</span></div>
          <div class="ml-hero-t" id="mlJudgeHead">正在读今天的生意…</div>
          <div class="ml-hero-w" id="mlJudgeWhy"></div>
          <div id="mlJudgePlan" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"></div>
        </div>
        <div class="ml-act" style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-s btn-sm" id="mlJudgeBtn" onclick="mlJudge(true)">↻ 重新判断</button>
        </div>
      </div>
    </div>

    <?php if ($top && !$aiReady): ?>
    <div class="panel" style="margin-bottom:16px">
      <div class="p-body" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <span style="font-size:18px"><?=htmlspecialchars($top['icon'])?></span>
        <div style="flex:1;min-width:220px"><b><?=htmlspecialchars($top['title'])?></b><span class="text-muted" style="font-size:12.5px;display:block;margin-top:2px">规则判断（配置 AI 后可获得推理式判断）：<?=htmlspecialchars($top['why'])?></span></div>
        <?php if (!empty($top['action_url'])): ?><a class="btn btn-s btn-sm" href="<?=htmlspecialchars($top['action_url'])?>"><?=htmlspecialchars($top['action_label'])?></a><?php endif; ?>
        <a href="/xmp/ai-config" class="btn btn-s btn-sm">配置 AI</a>
      </div>
    </div>
    <?php elseif (!$top): ?>
    <div class="panel" style="margin-bottom:16px;border-color:var(--ok)">
      <div class="p-body" style="display:flex;gap:12px;align-items:center">
        <span style="font-size:22px">✅</span>
        <div><b>行动队列已清空。</b><span class="text-muted" style="font-size:13px">可以去创作台产出内容，或让系统体检发现新机会。</span></div>
        <a href="/xmp/create" class="btn btn-s btn-sm" style="margin-left:auto">去创作台</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($receipts): ?>
    <div class="panel" style="margin-bottom:16px">
      <div class="p-head"><h3>小福最近做的事</h3><span class="p-sub mono">留痕 · 可回溯</span></div>
      <div class="p-body">
        <?php foreach ($receipts as $rc): ?>
        <div style="display:flex;gap:10px;align-items:center;font-size:13px;padding:6px 0;border-bottom:1px solid var(--border-soft,var(--border))">
          <span class="st <?=$rc['ok'] ? 'st-ok' : 'st-danger'?>" style="font-size:10.5px;padding:1px 7px;border-radius:999px"><?=$rc['ok'] ? '已完成' : '失败'?></span>
          <span style="flex:1;min-width:0"><?=htmlspecialchars((string)$rc['label'])?></span>
          <?php if (!empty($rc['evaluated'])): ?>
            <span class="st <?=$rc['evaluated']==='effective'?'st-ok':'st-danger'?>" style="font-size:10.5px;padding:1px 7px;border-radius:999px"><?=$rc['evaluated']==='effective'?'有效':'无效'?></span>
          <?php elseif (!empty($rc['ok']) && !empty($rc['trace_id'])): ?>
            <button class="btn btn-s btn-sm" onclick="mlEvaluate(<?=(int)$rc['trace_id']?>, 'effective', this)">有效</button>
            <button class="btn btn-s btn-sm" onclick="mlEvaluate(<?=(int)$rc['trace_id']?>, 'ineffective', this)" style="color:var(--muted)">无效</button>
          <?php endif; ?>
          <span class="ml-src"><?=htmlspecialchars((string)$rc['type'])?></span>
          <span class="text-muted mono" style="font-size:11px"><?=htmlspecialchars(substr((string)$rc['at'], 5, 11))?></span>
        </div>
        <?php endforeach; ?>
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
            <input type="hidden" name="_csrf_token" value="<?=csrf_token()?>">
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
    <?php endif; ?>
  </div>
</div>
<script>
const ML_CSRF = <?=json_encode(csrf_token())?>;
window.__mlJudge = <?=json_encode($judgeCache['judge'] ?? null)?>;
window.__mlPlan = [];
window.__mlCmdPlan = [];
window.__mlAiReady = <?=$aiReady ? 'true' : 'false'?>;
window.__mlCachedAt = <?=json_encode($judgeCache['generated_at'] ?? '')?>;

/* ═══ 每日晨会:取简报 → 文字展示 → 浏览器 TTS 朗读(分句喂,规避中文长文截断) ═══ */
window.__brief = { text: '', playing: false, queue: [] };

function briefSplitSentences(t) {
  return String(t).split(/(?<=[。！？!?;\n])/).map(s => s.trim()).filter(Boolean);
}
function briefStop() {
  window.__brief.playing = false;
  try { speechSynthesis.cancel(); } catch (e) {}
  const b = document.getElementById('briefPlayBtn'); if (b) b.textContent = '▶ 听晨会';
  const s = document.getElementById('briefStopBtn'); if (s) s.style.display = 'none';
}
function briefSpeakNext() {
  if (!window.__brief.playing) return;
  const sent = window.__brief.queue.shift();
  if (sent === undefined) { briefStop(); return; }
  const u = new SpeechSynthesisUtterance(sent);
  u.lang = 'zh-CN';
  u.rate = 1.05;
  u.onend = () => briefSpeakNext();
  u.onerror = () => briefStop();
  try { speechSynthesis.speak(u); } catch (e) { briefStop(); }
}
async function briefFetch() {
  const r = await fetch('/api/morning-briefing.php?action=briefing', { headers: { 'X-CSRF-Token': ML_CSRF } });
  return r.json();
}
async function briefToggle() {
  if (window.__brief.playing) { briefStop(); return; }
  const body = document.getElementById('briefBody');
  const load = document.getElementById('briefLoading');
  const txt = document.getElementById('briefText');
  const foot = document.getElementById('briefFoot');
  body.style.display = 'block'; load.style.display = 'block'; txt.style.display = 'none'; foot.style.display = 'none';
  let data;
  try { data = await briefFetch(); } catch (e) {
    load.textContent = '晨会暂时开不了(网络异常),稍后再试。';
    return;
  }
  if (!data || !data.ok || !data.text) { load.textContent = '晨会数据还没准备好,稍后再试。'; return; }
  window.__brief.text = data.text;
  txt.textContent = data.text;
  load.style.display = 'none'; txt.style.display = 'block'; foot.style.display = 'flex';
  document.getElementById('briefMode').textContent = data.mode === 'ai' ? 'AI 口播稿 · 小福' : '模板简报(AI 未接入或未生效)';
  // 朗读
  if (!('speechSynthesis' in window)) { document.getElementById('briefPlayBtn').textContent = '✓ 已展示'; return; }
  window.__brief.playing = true;
  window.__brief.queue = briefSplitSentences(data.text);
  document.getElementById('briefPlayBtn').textContent = '⏸ 停止';
  document.getElementById('briefStopBtn').style.display = '';
  briefSpeakNext();
}
function briefExpand() {
  const body = document.getElementById('briefBody');
  if (body.style.display === 'none') {
    body.style.display = 'block';
    briefFetch().then(d => {
      if (d && d.ok && d.text) {
        document.getElementById('briefText').textContent = d.text;
        document.getElementById('briefLoading').style.display = 'none';
        document.getElementById('briefText').style.display = 'block';
        document.getElementById('briefFoot').style.display = 'flex';
        document.getElementById('briefMode').textContent = d.mode === 'ai' ? 'AI 口播稿 · 小福' : '模板简报(AI 未接入或未生效)';
      }
    }).catch(() => {});
  } else if (!window.__brief.playing) {
    body.style.display = 'none';
  }
}

function mlRenderPlan(plan) {
  window.__mlPlan = plan || [];
  const box = document.getElementById('mlJudgePlan');
  if (!box) return;
  box.innerHTML = '';
  window.__mlPlan.forEach((p, i) => {
    const a = p.action || {};
    const el = document.createElement(a.type === 'open' ? 'a' : 'button');
    el.className = 'btn ' + (i === 0 ? 'btn-p' : 'btn-s') + ' btn-sm';
    el.textContent = (a.label || p.step || '执行') + (a.type === 'open' ? ' →' : '');
    if (a.type === 'open') { el.href = a.url; }
    else { el.onclick = () => mlExecAction(a, el, p.step); }
    if (p.step) el.title = p.step;
    box.appendChild(el);
  });
}

function mlRenderCmdPlan(plan) {
  window.__mlCmdPlan = plan || [];
  const box = document.getElementById('mlCmdPlan');
  if (!box) return;
  box.innerHTML = '';
  window.__mlCmdPlan.forEach((p, i) => {
    const a = p.action || {};
    const el = document.createElement(a.type === 'open' ? 'a' : 'button');
    el.className = 'btn ' + (i === 0 ? 'btn-p' : 'btn-s') + ' btn-sm';
    el.textContent = (a.label || p.step || '执行') + (a.type === 'open' ? ' →' : '');
    if (a.type === 'open') { el.href = a.url; }
    else { el.onclick = () => mlExecAction(a, el, p.step); }
    if (p.step) el.title = p.step;
    box.appendChild(el);
  });
}

function mlRenderJudge(j, meta) {
  document.getElementById('mlJudgeHead').textContent = j ? j.headline : '还没有判断';
  document.getElementById('mlJudgeWhy').textContent = j ? (j.reasoning || '') : '';
  document.getElementById('mlJudgeMeta').textContent = meta || '';
  if (!j) {
    const box = document.getElementById('mlJudgePlan');
    box.innerHTML = window.__mlAiReady
      ? '<button class="btn btn-p btn-sm" onclick="mlJudge(true)">让小福看一眼今天的生意</button>'
      : '<a class="btn btn-s btn-sm" href="/xmp/ai-config">配置 AI 后开启推理判断</a>';
    return;
  }
  mlRenderPlan(j.plan);
}

async function mlJudge(force) {
  const btn = document.getElementById('mlJudgeBtn');
  if (btn) { btn.disabled = true; btn.textContent = '小福思考中…'; }
  document.getElementById('mlJudgeMeta').textContent = '正在读今天的生意…';
  try {
    const r = await fetch('/api/mainline-ai.php?action=judge' + (force ? '&force=1' : ''), {headers: {'X-Requested-With': 'fetch'}});
    const j = await r.json();
    if (!j.ok) {
      mlRenderJudge(null, j.error || '判断失败');
      if (window.ofAlert) ofAlert(j.error || '判断失败'); 
      return;
    }
    mlRenderJudge(j.judge, (j.cached ? '缓存 · ' : '刚生成 · ') + (j.generated_at || ''));
  } catch (e) {
    mlRenderJudge(null, '网络错误');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '↻ 重新判断'; }
  }
}

async function mlExecAction(a, btn, step) {
  if (!a) return;
  if (a.type === 'open') { location.href = a.url; return; }
  btn.disabled = true;
  const old = btn.textContent; btn.textContent = '执行中…';
  try {
    const r = await fetch('/api/mainline-ai.php?action=execute', {
      method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': ML_CSRF},
      body: JSON.stringify({action: 'execute', plan_action: a})
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.error || '执行失败');
    if (window.ofAlert) ofAlert('小福已完成：' + (a.label || step), 'success');
    if (j.kind === 'open' && j.url) { location.href = j.url; return; }
    setTimeout(() => location.reload(), 600);
  } catch (e) {
    if (window.ofAlert) ofAlert(e.message);
    btn.disabled = false; btn.textContent = old;
  }
}

async function mlCommand() {
  const inp = document.getElementById('mlCmd');
  const text = (inp.value || '').trim();
  if (!text) return;
  const btn = document.getElementById('mlCmdBtn');
  const out = document.getElementById('mlCmdOut');
  out.style.display = '';
  out.innerHTML = '<span class="text-muted" style="font-size:13px">小福正在拆解你的指令…</span>';
  btn.disabled = true;
  try {
    const r = await fetch('/api/mainline-ai.php?action=command', {
      method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': ML_CSRF},
      body: JSON.stringify({action: 'command', text: text})
    });
    const j = await r.json();
    if (!j.ok) { out.innerHTML = '<span style="color:var(--danger);font-size:13px">' + (j.error || '解析失败') + '</span>'; return; }
    const jd = j.judge;
    out.innerHTML = '<div style="font-size:14px;font-weight:650;margin-bottom:4px">' + jd.headline + '</div>'
      + '<div class="text-muted" style="font-size:12.5px;margin-bottom:10px">' + (jd.reasoning || '') + '</div>'
      + '<div id="mlCmdPlan" style="display:flex;gap:8px;flex-wrap:wrap"></div>';
    mlRenderCmdPlan(jd.plan || []);
  } catch (e) {
    out.innerHTML = '<span style="color:var(--danger);font-size:13px">网络错误</span>';
  } finally {
    btn.disabled = false;
  }
}

async function mlEvaluate(traceId, verdict, btn) {
  btn.disabled = true;
  try {
    const r = await fetch('/api/mainline-ai.php?action=evaluate', {
      method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': ML_CSRF},
      body: JSON.stringify({action: 'evaluate', trace_id: traceId, verdict: verdict})
    });
    const j = await r.json();
    if (!j.ok) throw new Error('评估失败');
    if (window.ofAlert) ofAlert(verdict === 'effective' ? '已记为有效，下次会优先复用' : '已记为无效，下次会避开', 'success');
    setTimeout(function () { location.reload(); }, 500);
  } catch (e) {
    if (window.ofAlert) ofAlert(e.message);
    btn.disabled = false;
  }
}

/* ── 团队视角：拖拽改状态（复用 CRM 看板的 HTML5 DnD 模式）── */
(function () {
  const board = document.getElementById('kbBoard');
  if (!board) return;
  const uid = <?=json_encode((string) $curId)?>;
  let dragEl = null;
  board.querySelectorAll('.kanban-card').forEach(function (card) {
    card.addEventListener('dragstart', function (e) {
      dragEl = card; card.classList.add('kb-dragging');
      e.dataTransfer.setData('text/plain', card.dataset.id);
      e.dataTransfer.effectAllowed = 'move';
    });
    card.addEventListener('dragend', function () { card.classList.remove('kb-dragging'); dragEl = null; });
  });
  board.querySelectorAll('.kanban-col').forEach(function (col) {
    col.addEventListener('dragover', function (e) {
      if (!dragEl) return;
      e.preventDefault(); e.dataTransfer.dropEffect = 'move'; col.classList.add('kb-over');
    });
    col.addEventListener('dragleave', function () { col.classList.remove('kb-over'); });
    col.addEventListener('drop', function (e) {
      e.preventDefault(); col.classList.remove('kb-over');
      if (!dragEl) return;
      const id = dragEl.dataset.id, status = col.dataset.status;
      if (!id || !status) return;
      if (dragEl.closest('.kanban-col') === col) return;
      const fd = new FormData();
      fd.append('csrf', ML_CSRF); fd.append('ps_action', 'task_move');
      fd.append('project', uid); fd.append('id', id); fd.append('status', status);
      col.appendChild(dragEl);
      fetch(location.pathname + location.search, { method: 'POST', body: fd, redirect: 'manual' })
        .then(function () { location.reload(); }).catch(function () { location.reload(); });
    });
  });
})();

document.addEventListener('DOMContentLoaded', function () {
  if (window.__mlJudge) mlRenderJudge(window.__mlJudge, '缓存 · ' + (window.__mlCachedAt || ''));
  else {
    document.getElementById('mlJudgeMeta').textContent = window.__mlAiReady ? '尚未判断' : '未配置 AI';
    mlRenderJudge(null, '');
    if (window.__mlAiReady) mlJudge(false);
  }
});
</script>
<?php admin_footer(); ?>
