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
require_once __DIR__ . '/../lib/CptSystem.php';
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
    $siteAdmin = ps_is_site_admin();
    // 项目级授权：改任务要 edit，管成员/改项目要 manage。没权限就退回（不静默改数据）
    $need = in_array($act, ['task_save', 'task_move', 'task_reparent', 'task_delete'], true) ? 'edit'
        : (in_array($act, ['member_set', 'member_remove', 'project_save'], true) ? 'manage' : 'edit');
    if ($act !== 'project_save' && !ps_can($pid, $need, ps_current_user(), $siteAdmin)) {
        header('Location: ' . $back . '&denied=1');
        exit;
    }
    $by = function_exists('member_current') ? (string) ((member_current()['name'] ?? '') ?: '') : '';
    if ($act === 'task_save' && $pid !== '') {
        // 只传 POST 里真正出现的字段：没传的键 ps_task_save 会保留原值
        $payload = [];
        foreach (['title', 'note', 'status', 'priority', 'assignee', 'due', 'parent'] as $k) {
            if (array_key_exists($k, $_POST)) $payload[$k] = (string) $_POST[$k];
        }
        if (array_key_exists('ref_type', $_POST)) {
            $payload['ref'] = ['type' => (string) $_POST['ref_type'], 'id' => (string) ($_POST['ref_id'] ?? ''), 'label' => (string) ($_POST['ref_label'] ?? '')];
        }
        if ((string) ($_POST['id'] ?? '') !== '') $payload['id'] = (string) $_POST['id'];
        if (array_key_exists('repeat_pick', $_POST)) {
            $pick = (string) $_POST['repeat_pick'];
            if ($pick === '' || $pick === 'none') {
                $payload['repeat'] = ['freq' => 'none'];
            } else {
                $parts = explode(':', $pick);
                $payload['repeat'] = ['freq' => (string) ($parts[0] ?? 'none'), 'interval' => (int) ($parts[1] ?? 1), 'until' => trim((string) ($_POST['repeat_until'] ?? ''))];
            }
        }
        ps_task_save($pid, $payload);
    } elseif ($act === 'task_reparent' && $pid !== '') {
        // 只改层级：把原任务整条读出来，换 parent 后存回（其余字段原样）
        $cur = null;
        foreach (ps_tasks($pid) as $x) if ((string) ($x['id'] ?? '') === (string) ($_POST['id'] ?? '')) $cur = $x;
        if ($cur !== null) {
            ps_task_save($pid, [
                'id' => (string) $cur['id'], 'title' => (string) ($cur['title'] ?? ''), 'note' => (string) ($cur['note'] ?? ''),
                'status' => (string) ($cur['status'] ?? 'todo'), 'priority' => (string) ($cur['priority'] ?? 'normal'),
                'assignee' => (string) ($cur['assignee'] ?? ''), 'start' => (string) ($cur['start'] ?? ''), 'due' => (string) ($cur['due'] ?? ''),
                'ref' => (array) ($cur['ref'] ?? []), 'parent' => (string) ($_POST['parent'] ?? ''),
            ]);
        }
    } elseif ($act === 'project_save' && $pid === '') {
        // 新建项目：创建者自动成为 owner
        $r = ps_project_save(['name' => (string) ($_POST['name'] ?? ''), 'desc' => (string) ($_POST['desc'] ?? '')]);
        if ($r['ok'] ?? false) $back = '/xmp/today?view=team&project=' . urlencode((string) $r['id']);
    } elseif ($act === 'comment_add' && $pid !== '') {
        $r = ps_task_comment_add($pid, (string) ($_POST['id'] ?? ''), (string) ($_POST['text'] ?? ''), ps_current_user());
        if (($r['ok'] ?? false) === true) {
            $task = [];
            foreach (ps_tasks($pid) as $x) if ((string) ($x['id'] ?? '') === (string) ($_POST['id'] ?? '')) $task = $x;
            ps_comment_notify($pid, $task, (array) $r['comment']);
        }
    } elseif ($act === 'comment_delete' && $pid !== '') {
        ps_task_comment_delete($pid, (string) ($_POST['id'] ?? ''), (string) ($_POST['cid'] ?? ''), ps_current_user(), $siteAdmin);
    } elseif ($act === 'member_set' && $pid !== '') {
        ps_member_set($pid, (string) ($_POST['user'] ?? ''), (string) ($_POST['role'] ?? 'editor'));
    } elseif ($act === 'member_remove' && $pid !== '') {
        ps_member_remove($pid, (string) ($_POST['user'] ?? ''));
    } elseif ($act === 'task_move' && $pid !== '') {
        ps_task_move($pid, (string) ($_POST['id'] ?? ''), (string) ($_POST['status'] ?? 'todo'), $by);
        // 完成带重复规则的任务 → 立刻生成下一个实例（cron 只是兜底）
        if ((string) ($_POST['status'] ?? '') === 'done') ps_repeat_spawn_due($pid);
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
$siteAdmin = ps_is_site_admin();
$myUser = ps_current_user();
$projects = $viewMode === 'team' ? ps_visible_projects($myUser, $siteAdmin) : [];
$scopeAll = ($viewMode === 'team' && (string) ($_GET['project'] ?? '') === 'all');   // 跨项目汇总
$curId = $scopeAll ? 'all' : ps_safe_id((string) ($_GET['project'] ?? ''));
if ($viewMode === 'team' && !$scopeAll) {
    if ($curId === '' || ps_project_get($curId) === null) $curId = (string) ($projects[0]['id'] ?? '');
}
$curProject = ($viewMode === 'team' && !$scopeAll && $curId !== '' && ps_can($curId, 'view', $myUser, $siteAdmin)) ? ps_project_get($curId) : null;
if ($viewMode === 'team' && !$scopeAll && $curId !== '' && $curProject === null) $curId = (string) ($projects[0]['id'] ?? '');
$canEdit = $curProject !== null && ps_can($curId, 'edit', $myUser, $siteAdmin);
$canManage = $curProject !== null && ps_can($curId, 'manage', $myUser, $siteAdmin);
$myRole = $curProject !== null ? ps_project_role($curId, $myUser) : '';
$members = $curProject !== null ? ps_project_members($curId) : [];

// 汇总模式：把可见项目的任务并到一起，每条带上它所属项目（表单/评论都要按各自项目走）
$curTasks = [];
if ($scopeAll) {
    foreach ($projects as $pj) {
        $pjId = ps_safe_id((string) ($pj['id'] ?? ''));
        if ($pjId === '') continue;
        foreach (ps_tasks($pjId) as $t) {
            $t['_project'] = $pjId;
            $t['_project_name'] = (string) ($pj['name'] ?? $pjId);
            $curTasks[] = $t;
        }
    }
    $ord = array_flip(array_keys(ps_task_statuses()));
    usort($curTasks, static function (array $a, array $b) use ($ord): int {
        $sa = $ord[(string) ($a['status'] ?? 'todo')] ?? 99;
        $sb = $ord[(string) ($b['status'] ?? 'todo')] ?? 99;
        if ($sa !== $sb) return $sa <=> $sb;
        return strcmp((string) ($a['due'] ?? '9999'), (string) ($b['due'] ?? '9999'));
    });
} elseif ($curProject !== null) {
    $curTasks = ps_tasks($curId);
}
$kbStats = $curProject !== null ? ps_stats($curId) : ['by_status' => [], 'total' => 0, 'overdue' => 0];
if ($scopeAll) {
    $kbStats = ['by_status' => array_fill_keys(array_keys(ps_task_statuses()), 0), 'total' => 0, 'overdue' => 0, 'roots' => 0, 'nodes' => 0];
    foreach ($curTasks as $t) {
        $stt = (string) ($t['status'] ?? 'todo');
        $kbStats['by_status'][$stt] = ($kbStats['by_status'][$stt] ?? 0) + 1;
        if ((string) ($t['parent'] ?? '') === '') $kbStats['roots']++;
        $dueT = (string) ($t['due'] ?? '');
        if ($stt !== 'done' && $dueT !== '' && substr($dueT, 0, 10) < date('Y-m-d')) $kbStats['overdue']++;
        $kbStats['total']++;
    }
}
/** 这条任务属于哪个项目（单项目模式=当前项目） */
$taskProj = static fn(array $t): string => (string) ($t['_project'] ?? $curId);
/** 权限也按任务自己的项目算（汇总模式下可能有的能改有的只能看） */
$canEditTask = static fn(array $t): bool => ps_can($taskProj($t), 'edit', $myUser, $siteAdmin);
$assignees = $viewMode === 'team' ? ps_assignees() : [];
$teamViews = tv_views();   // 表格/看板/日历/甘特/树
$teamView = (string) ($_GET['vt'] ?? 'board');
if (!isset($teamViews[$teamView])) $teamView = 'board';
$tvParent = ps_safe_id((string) ($_GET['parent'] ?? ''));   // 「+ 子任务」预填的父任务
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
    $ri = ps_ref_resolve((array) ($t['ref'] ?? []));
    return $ri['type'] === '' ? '' : $ri['type_label'] . '：' . $ri['label'];
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
    $tvRows[] = ['id' => (string) ($t['id'] ?? ''), 'title' => (string) ($t['title'] ?? ''), 'slug' => '', 'status' => (string) ($t['status'] ?? 'todo'), 'values' => $values, 'resolved' => [], 'cells' => $cells, 'proj' => (string) ($t['_project_name'] ?? ''), 'proj_id' => $taskProj($t)];
}
$subCounts = [];
$cardRoll = [];
foreach ($curTasks as $ct) {
    $cid = (string) ($ct['id'] ?? '');
    $subCounts[$cid] = count(ps_task_subtree_ids($taskProj($ct), $cid)) - 1;
    $cardRoll[$cid] = ps_task_rollup($taskProj($ct), $cid);
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
        <?php if (count($projects) > 1): ?>
        <a href="/xmp/today?view=team&project=all" class="btn btn-s btn-sm<?=$scopeAll ? ' btn-p' : ''?>" title="把可见项目的任务并到一起看">所有项目 <span class="note">· <?=array_sum(array_map(static fn(array $x): int => (int) (ps_stats((string) $x['id'])['total'] ?? 0), $projects))?></span></a>
        <?php endif; ?>
        <form method="post" style="margin-left:auto;display:flex;gap:6px">
          <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
          <input type="hidden" name="ps_action" value="project_save">
          <input class="inp sm" name="name" placeholder="新项目名" style="min-width:150px" required>
          <button class="btn btn-s btn-sm">+ 建项目</button>
        </form>
      </div>
      <?php if ($curProject !== null): ?>
      <div class="p-body" style="border-top:1px solid var(--border-soft,var(--border));display:flex;gap:16px;flex-wrap:wrap;align-items:center">
        <span class="note">共 <b><?=$kbStats['total']?></b> 条<?=((int) ($kbStats['roots'] ?? 0)) > 0 && (int) $kbStats['roots'] !== (int) $kbStats['total'] ? '（' . (int) $kbStats['roots'] . ' 条顶层）' : ''?></span>
        <span class="note">逾期 <b style="color:<?=$kbStats['overdue'] > 0 ? 'var(--danger,#dc2626)' : 'inherit'?>"><?=$kbStats['overdue']?></b></span>
        <?php $kbDue = ps_due_buckets($scopeAll ? '' : $curId); ?>
        <span class="note">今天 <b<?=$kbDue['counts']['today'] > 0 ? ' style="color:var(--danger,#dc2626)"' : ''?>><?=$kbDue['counts']['today']?></b></span>
        <span class="note">近 7 天 <b><?=$kbDue['counts']['soon']?></b></span>
        <?php foreach (ps_task_statuses() as $sk => $sl): ?>
        <span class="note"><?=htmlspecialchars($sl)?> <b><?=$kbStats['by_status'][$sk] ?? 0?></b></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if (isset($_GET['denied'])): ?>
      <div class="panel" style="margin-bottom:12px;border-left:3px solid var(--danger,#dc2626)"><div class="p-body" style="font-size:12.5px">这个动作需要更高的项目权限，已拦下（没有改任何数据）。<?=$myRole !== '' ? '你在本项目的角色：' . htmlspecialchars(ps_project_roles()[$myRole] ?? $myRole) : '你不是本项目成员。'?></div></div>
    <?php endif; ?>

    <?php if ($curProject !== null): $roster = ps_user_options(); ?>
    <div class="panel" style="margin-bottom:12px">
      <div class="p-body" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <b style="font-size:13px">成员</b>
        <?php if ($members === []): ?>
        <span class="note">未设成员 → 公共项目：凡有「任务」权限的人都能改（设了成员后就按成员角色来）</span>
        <?php endif; ?>
        <?php foreach ($members as $mu => $mr): ?>
        <span class="pill" title="<?=htmlspecialchars((string) $mu)?>"><?=htmlspecialchars(ps_user_display((string) $mu))?> · <?=htmlspecialchars(ps_project_roles()[$mr] ?? $mr)?></span>
        <?php if ($canManage): ?>
        <form method="post" style="margin:0">
          <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
          <input type="hidden" name="ps_action" value="member_remove">
          <input type="hidden" name="project" value="<?=htmlspecialchars($curId)?>">
          <input type="hidden" name="user" value="<?=htmlspecialchars((string) $mu)?>">
          <button class="btn btn-s btn-sm" title="移除成员" style="padding:0 6px">×</button>
        </form>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($myRole !== '' && !$siteAdmin): ?><span class="note">我：<?=htmlspecialchars(ps_project_roles()[$myRole] ?? $myRole)?></span><?php endif; ?>
        <?php if (!$canEdit): ?><span class="pill hl">只读</span><?php endif; ?>
        <?php if ($canManage): ?>
        <form method="post" style="margin-left:auto;display:flex;gap:6px">
          <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
          <input type="hidden" name="ps_action" value="member_set">
          <input type="hidden" name="project" value="<?=htmlspecialchars($curId)?>">
          <select name="user" class="inp sm" style="width:130px">
            <?php foreach ($roster as $uk => $uname): if (isset($members[(string) $uk])) continue; ?><option value="<?=htmlspecialchars((string) $uk)?>"><?=htmlspecialchars($uname)?></option><?php endforeach; ?>
          </select>
          <select name="role" class="inp sm" style="width:96px"><?php foreach (ps_project_roles() as $rk => $rl): ?><option value="<?=$rk?>"<?=$rk === 'editor' ? ' selected' : ''?>><?=htmlspecialchars($rl)?></option><?php endforeach; ?></select>
          <button class="btn btn-s btn-sm">+ 加成员</button>
        </form>
        <?php endif; ?>
      </div>
      <div class="p-body" style="border-top:1px solid var(--border);font-size:11.5px;color:var(--muted)">
        注意区分：<b>负责人</b>是「谁在做这件事」（任务上的名字，可以是外部同事）；<b>成员</b>是「谁的登录账号能改这个项目」。
      </div>
    </div>
    <?php endif; ?>

    <?php if ($curProject === null && !$scopeAll): ?>
      <div class="empty"><?=$projects === [] ? '你没有可见的项目（不是任何项目的成员）。可以新建一个，你就是它的负责人。' : '还没有项目。用上面的「+ 建项目」新建一个；或运行 <code>php scripts/seed-projects.php</code> 种入示例。'?></div>
    <?php else: ?>
    <?php if ($canEdit && !$scopeAll): ?>
    <div class="panel" style="margin-bottom:12px" id="teamAdd">
      <form method="post" class="p-body" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
        <input type="hidden" name="ps_action" value="task_save">
        <input type="hidden" name="project" value="<?=htmlspecialchars($curId)?>">
        <?php $preTask = null; foreach ($curTasks as $ct) if ((string) ($ct['id'] ?? '') === $tvParent) $preTask = $ct; ?>
        <?php if ($preTask !== null): ?>
        <input type="hidden" name="parent" value="<?=htmlspecialchars($tvParent)?>">
        <span class="pill" style="align-self:center">父任务：<?=htmlspecialchars((string) ($preTask['title'] ?? ''))?> <a href="<?=htmlspecialchars($tvQ(['vt' => 'tree']))?>" style="text-decoration:none">×</a></span>
        <?php endif; ?>
        <label class="kb-f">标题<input class="inp sm" name="title" style="min-width:230px" required placeholder="要做什么"></label>
        <label class="kb-f">负责人<input class="inp sm" name="assignee" list="kbWho" placeholder="谁做" style="width:110px"><datalist id="kbWho"><?php foreach ($assignees as $u): ?><option value="<?=htmlspecialchars((string) $u['name'])?>"><?php endforeach; ?></datalist></label>
        <label class="kb-f">截止<input class="inp sm" type="date" name="due"></label>
        <label class="kb-f">优先级<select class="inp sm" name="priority"><?php foreach (ps_priorities() as $pk => $pl): ?><option value="<?=$pk?>"><?=htmlspecialchars($pl)?></option><?php endforeach; ?></select></label>
        <?php $builtinTargets = ps_ref_types(); $cptTargets = array_diff_key(ps_ref_targets(), $builtinTargets); ?>
        <label class="kb-f">关联<select class="inp sm" name="ref_type"><option value="">不关联</option><optgroup label="内置对象"><?php foreach ($builtinTargets as $rk => $rl): ?><option value="<?=htmlspecialchars((string) $rk)?>"><?=htmlspecialchars((string) $rl)?></option><?php endforeach; ?></optgroup><?php if ($cptTargets): ?><optgroup label="内容类型"><?php foreach ($cptTargets as $rk => $rl): ?><option value="<?=htmlspecialchars((string) $rk)?>"><?=htmlspecialchars((string) $rl)?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label>
        <label class="kb-f">对象 ID<input class="inp sm" name="ref_id" placeholder="如 lead_123 / cpt_…" list="kbRefIds" style="width:120px"></label>
        <label class="kb-f">重复<select class="inp sm" name="repeat_pick" style="width:96px">
          <option value="none">不重复</option>
          <option value="daily:1">每天</option>
          <option value="daily:2">每 2 天</option>
          <option value="weekly:1">每周</option>
          <option value="weekly:2">每 2 周</option>
          <option value="monthly:1">每月</option>
          <option value="monthly:3">每季度</option>
        </select></label>
        <label class="kb-f">重复至<input class="inp sm" type="date" name="repeat_until" style="width:132px" title="留空=一直重复"></label>
        <datalist id="kbRefIds"><?php foreach (ps_ref_targets() as $rk => $rl): $rslug = ps_ref_cpt_slug((string) $rk); if ($rslug === '' || !function_exists('cpt_entries')) continue; foreach (cpt_entries($rslug) as $ce): ?><option value="<?=htmlspecialchars((string) ($ce['id'] ?? ''))?>"><?=htmlspecialchars((string) $rl . ' · ' . (string) ($ce['title'] ?? ''))?></option><?php endforeach; endforeach; ?></datalist>
        <button class="btn btn-p btn-sm">+ 加任务</button>
      </form>
    </div>

    <?php elseif ($scopeAll): ?>
    <div class="panel" style="margin-bottom:12px"><div class="p-body" style="font-size:12.5px;color:var(--muted)">这是<b>所有项目</b>的汇总视图：可以看、改状态、评论；新建任务请切到具体项目。</div></div>
    <?php endif; ?>

    <div class="tv-bar" style="margin-bottom:10px">
      <span class="note" style="font-size:12px;color:var(--muted)">同一批任务：</span>
      <?php foreach ($teamViews as $vk => $vl): ?>
      <a href="<?=htmlspecialchars($tvQ(['vt' => $vk]))?>" class="btn btn-s btn-sm<?=$vk === $teamView ? ' btn-p' : ''?>"><?=htmlspecialchars($vl)?></a>
      <?php endforeach; ?>
      <?php if ($teamView === 'board' && $canEdit): ?><span class="note" style="font-size:12px;color:var(--muted)">拖拽卡片到别的列即可改状态</span><?php endif; ?>
      <?php if ($teamView === 'calendar'): ?>
      <a href="<?=htmlspecialchars($tvQ(['vt' => 'calendar', 'm' => tv_month_shift($tvYm, -1)]))?>" class="btn btn-s btn-sm">←</a>
      <b style="font-size:12.5px"><?=htmlspecialchars($tvYm)?></b>
      <a href="<?=htmlspecialchars($tvQ(['vt' => 'calendar', 'm' => tv_month_shift($tvYm, 1)]))?>" class="btn btn-s btn-sm">→</a>
      <?php endif; ?>
    </div>

    <?php if ($teamView === 'grid'): ?>
    <table class="tv-grid">
      <thead><tr><th>任务</th><?php if ($scopeAll): ?><th>项目</th><?php endif; ?><th>状态</th><th>重复</th><?php foreach ($tvFields as $f): ?><th><?=htmlspecialchars((string) $f['label'])?></th><?php endforeach; ?><th>评论</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($curTasks as $t): $status = (string) ($t['status'] ?? 'todo'); $due = (string) ($t['due'] ?? ''); ?>
        <tr>
          <td><b><?=htmlspecialchars((string) ($t['title'] ?? ''))?></b><?php $cr = $cardRoll[(string) ($t['id'] ?? '')] ?? []; if ((int) ($cr['total'] ?? 0) > 0): ?><span class="text-xs text-muted"> · 子任务 <?=(int) $cr['done']?>/<?=(int) $cr['total']?></span><?php endif; ?><?php if ((string) ($t['note'] ?? '') !== ''): ?><div class="text-xs text-muted"><?=htmlspecialchars(mb_substr((string) $t['note'], 0, 70))?></div><?php endif; ?></td>
          <?php if ($scopeAll): ?><td><span class="kb-proj"><?=htmlspecialchars((string) ($t['_project_name'] ?? ''))?></span></td><?php endif; ?>
          <td><?=htmlspecialchars(ps_task_statuses()[$status] ?? $status)?></td>
          <td>
            <?php if ($canEditTask($t)): $rp = ps_repeat_normalize($t['repeat'] ?? []);
              $rpVal = (string) $rp['freq'] === 'none' ? 'none' : ((string) $rp['freq'] . ':' . (int) $rp['interval']); ?>
            <form method="post" class="tw-inline">
              <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
              <input type="hidden" name="ps_action" value="task_save">
              <input type="hidden" name="project" value="<?=htmlspecialchars($taskProj($t))?>">
              <input type="hidden" name="id" value="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>">
              <select name="repeat_pick" class="inp sm" style="width:88px">
                <?php foreach (['none' => '不重复', 'daily:1' => '每天', 'daily:2' => '每 2 天', 'weekly:1' => '每周', 'weekly:2' => '每 2 周', 'monthly:1' => '每月', 'monthly:3' => '每季度'] as $rv => $rlab): ?>
                <option value="<?=$rv?>" <?=$rv === $rpVal ? 'selected' : ''?>><?=htmlspecialchars($rlab)?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-s btn-sm" title="保存重复规则">✓</button>
            </form>
            <?php else: ?><span class="text-xs text-muted"><?=htmlspecialchars(ps_repeat_label(ps_repeat_normalize($t['repeat'] ?? [])) ?: '—')?></span><?php endif; ?>
          </td>
          <td><?=htmlspecialchars((string) ($t['assignee'] ?? ''))?></td>
          <td><?=htmlspecialchars((string) (ps_priorities()[(string) ($t['priority'] ?? 'normal')] ?? ''))?></td>
          <td><?=htmlspecialchars((string) ($t['start'] ?? ''))?></td>
          <td<?=$due !== '' && substr($due, 0, 10) < date('Y-m-d') ? ' style="color:var(--danger,#dc2626);font-weight:700"' : ''?>><?=htmlspecialchars($due !== '' ? substr($due, 0, 16) : '')?></td>
          <td><?=htmlspecialchars($tvRefLabel($t))?></td>
          <td style="min-width:180px">
            <?php $cmts = ps_task_comments($taskProj($t), (string) ($t['id'] ?? '')); ?>
            <details class="kb-cmts" style="margin:0;border:none;padding:0">
              <summary><?=count($cmts) > 0 ? ('评论 ' . count($cmts)) : '写评论'?></summary>
              <?php foreach ($cmts as $c): ?>
              <div class="kb-cmt"><div class="kb-cmt-h"><b><?=htmlspecialchars(ps_user_display((string) ($c['by'] ?? '')))?></b><span><?=htmlspecialchars(substr((string) ($c['at'] ?? ''), 5, 11))?></span></div><div class="kb-cmt-t"><?=ps_comment_html((string) ($c['text'] ?? ''))?></div></div>
              <?php endforeach; ?>
              <?php if ($canEditTask($t)): ?>
              <form method="post" class="kb-cmt-form">
                <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                <input type="hidden" name="ps_action" value="comment_add">
                <input type="hidden" name="project" value="<?=htmlspecialchars($taskProj($t))?>">
                <input type="hidden" name="id" value="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>">
                <input class="inp sm" name="text" placeholder="@谁 说点什么…" maxlength="2000" required>
                <button class="btn btn-s btn-sm">发送</button>
              </form>
              <?php endif; ?>
            </details>
          </td>
          <td>
            <?php if ($canEditTask($t)): ?>
            <form method="post" data-confirm="删除「<?=htmlspecialchars(mb_substr((string) ($t['title'] ?? ''), 0, 20))?>」<?=((int) ($subCounts[(string) ($t['id'] ?? '')] ?? 0)) > 0 ? '及其 ' . (int) $subCounts[(string) ($t['id'] ?? '')] . ' 个子任务' : ''?>？" style="margin:0">
              <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
              <input type="hidden" name="ps_action" value="task_delete">
              <input type="hidden" name="project" value="<?=htmlspecialchars($taskProj($t))?>">
              <input type="hidden" name="id" value="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>">
              <button class="btn btn-s btn-sm" title="删除">×</button>
            </form>
            <?php endif; ?>
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
        <div class="tv-cal-ev"><b><?=htmlspecialchars($r['title'])?></b><?php if (($r['proj'] ?? '') !== ''): ?><span class="kb-proj"><?=htmlspecialchars((string) $r['proj'])?></span><?php endif; ?><?php if (($r['cells']['assignee'] ?? '') !== ''): ?><div class="text-xs text-muted">@<?=htmlspecialchars((string) $r['cells']['assignee'])?></div><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($cal['undated']): ?>
    <div style="margin-top:12px"><b style="font-size:12.5px">未排期（<?=count($cal['undated'])?>）</b>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px"><?php foreach ($cal['undated'] as $r): ?><span class="tv-cal-ev"><?=htmlspecialchars($r['title'])?></span><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <?php elseif ($teamView === 'gantt'): $g = tv_gantt_of($tvFields, $tvRows, ['start', 'due']); $todayPct = tv_gantt_today($g);
      $projById = [];
      foreach ($tvRows as $rr) $projById[(string) $rr['id']] = (string) $rr['proj']; ?>
      <?php if ($g['bars'] === []): ?>
        <div class="empty">还没有带「开始 / 截止」的任务，填上日期后这里会按时间跨度画出来。</div>
      <?php else: ?>
      <div class="tv-bar"><span class="note" style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($g['start'])?> → <?=htmlspecialchars($g['end'])?>（共 <?=$g['days']?> 天）· 条=开始→截止，只有一个日期的按里程碑画</span></div>
      <div class="tv-gantt">
        <div class="tv-gantt-scale"><div></div><div class="ticks"><span><?=htmlspecialchars($g['start'])?></span><span><?=htmlspecialchars($g['end'])?></span></div></div>
        <?php foreach ($g['bars'] as $b): ?>
        <div class="tv-gantt-row">
          <div class="tv-gantt-label"><?=htmlspecialchars($b['title'])?><?php if (($projById[(string) $b['id']] ?? '') !== ''): ?><span class="kb-proj"><?=htmlspecialchars((string) $projById[(string) $b['id']])?></span><?php endif; ?><div class="text-xs text-muted"><?=htmlspecialchars($b['start'])?><?=$b['milestone'] ? '' : ' → ' . htmlspecialchars($b['end'])?></div></div>
          <div class="tv-gantt-track"><div class="tv-gantt-bar<?=$b['milestone'] ? ' ms' : ''?>" style="left:<?=round($b['offset'] / max(1, $g['days']) * 100, 3)?>%;width:<?=round($b['span'] / max(1, $g['days']) * 100, 3)?>%"></div></div>
        </div>
        <?php endforeach; ?>
        <?php if ($todayPct !== null): ?><div class="tv-gantt-today" style="left:calc(200px + (100% - 200px) * <?=round($todayPct / 100, 4)?>)"></div><?php endif; ?>
      </div>
      <?php endif; ?>

    <?php elseif ($teamView === 'tree'): $treeRows = [];
      if ($scopeAll) {
          // 汇总模式：按项目拼接各自的树（子树结构保持，顶层按项目分组）
          foreach ($projects as $pj) {
              $pjId = ps_safe_id((string) ($pj['id'] ?? ''));
              if ($pjId === '') continue;
              foreach (ps_task_tree_flat($pjId) as $rr) {
                  $rr['task']['_project'] = $pjId;
                  $rr['task']['_project_name'] = (string) ($pj['name'] ?? $pjId);
                  $treeRows[] = $rr;
              }
          }
      } else {
          $treeRows = ps_task_tree_flat($curId);
      }
    ?>
      <?php if ($treeRows === []): ?>
        <div class="empty">还没有任务。</div>
      <?php else: ?>
      <div class="tv-bar" style="font-size:12px;color:var(--muted)">
        <span class="note"><?=count($treeRows)?> 条任务 · <?= (int) ($kbStats['roots'] ?? 0) ?> 条顶层 · 有子任务的会显示进度汇总（只看它的后代）</span>
        <button type="button" class="btn btn-s btn-sm" id="twFoldAll" style="margin-left:auto">全部折叠</button>
        <button type="button" class="btn btn-s btn-sm" id="twUnfoldAll">全部展开</button>
      </div>
      <div class="tw" id="twTree">
        <?php foreach ($treeRows as $r): $t = (array) $r['task']; $tk = (string) ($t['id'] ?? ''); $pr = (array) $r['progress'];
          $status = (string) ($t['status'] ?? 'todo'); $prio = (string) ($t['priority'] ?? 'normal');
          $due = (string) ($t['due'] ?? ''); $overdue = $due !== '' && substr($due, 0, 10) < date('Y-m-d') && $status !== 'done';
          $kids = (int) $r['children']; $subCount = count((array) $r['subtree_ids']) - 1;
          $ri = ps_ref_resolve((array) ($t['ref'] ?? [])); ?>
        <div class="tw-row" data-id="<?=htmlspecialchars($tk)?>" data-depth="<?=(int) $r['depth']?>" style="padding-left:<?=8 + (int) $r['depth'] * 22?>px">
          <?php if ($kids > 0): ?>
          <button type="button" class="tw-tog" data-id="<?=htmlspecialchars($tk)?>" data-closed="0" title="折叠/展开">▾</button>
          <?php else: ?><span class="tw-tog ph">·</span><?php endif; ?>
          <b class="tw-t"><?=htmlspecialchars((string) ($t['title'] ?? ''))?></b>
          <?php if ($scopeAll): ?><span class="kb-proj"><?=htmlspecialchars((string) ($t['_project_name'] ?? ''))?></span><?php endif; ?>
          <span class="st<?=$status !== 'todo' ? ' st-' . htmlspecialchars($status) : ''?>"><?=htmlspecialchars(ps_task_statuses()[$status] ?? $status)?></span>
          <?php if ($prio !== 'normal'): ?><span class="pill<?=$prio === 'urgent' ? ' hl' : ''?>"><?=htmlspecialchars(ps_priorities()[$prio] ?? $prio)?></span><?php endif; ?>
          <?php if ((string) ($t['assignee'] ?? '') !== ''): ?><span class="note">@<?=htmlspecialchars((string) $t['assignee'])?></span><?php endif; ?>
          <?php if ($due !== ''): ?><span class="note"<?=$overdue ? ' style="color:var(--danger,#dc2626);font-weight:700"' : ''?>><?=htmlspecialchars(substr($due, 0, 16))?><?=$overdue ? ' · 逾期' : ''?></span><?php endif; ?>
          <?php if ($ri['type'] !== ''): ?><span class="kb-ref"><?=htmlspecialchars($ri['type_label'])?>：<?=htmlspecialchars($ri['label'])?></span><?php endif; ?>
          <?php if ($kids > 0): ?>
          <span class="tw-prog" title="<?=$pr['done']?>/<?=$pr['total']?> 子任务已完成">
            <span class="tw-bar" style="width:<?=(int) $pr['pct']?>%"></span>
            <span class="tw-pct"><?=$pr['done']?>/<?=$pr['total']?> · <?=(int) $pr['pct']?>%<?=((int) $pr['overdue']) > 0 ? ' · 逾期 ' . (int) $pr['overdue'] : ''?></span>
          </span>
          <?php endif; ?>
          <span class="tw-acts">
            <?php if (($canEditTask($t) && !$scopeAll)): ?>
            <?php $rlab2 = ps_repeat_label(ps_repeat_normalize($t['repeat'] ?? [])); if ($rlab2 !== ''): ?><span class="tw-a" title="重复规则">🔁 <?=htmlspecialchars($rlab2)?></span><?php endif; ?>
            <?php $cc = count(ps_task_comments($taskProj($t), $tk)); ?>
            <a class="tw-a" href="<?=htmlspecialchars($tvQ(['vt' => 'board']))?>" title="到看板里评论">💬 <?=$cc?></a>
            <a class="tw-a" href="<?=htmlspecialchars($tvQ(['vt' => 'tree', 'parent' => $tk]))?>#teamAdd">+ 子任务</a>
            <form method="post" class="tw-inline">
              <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
              <input type="hidden" name="ps_action" value="task_reparent">
              <input type="hidden" name="project" value="<?=htmlspecialchars($taskProj($t))?>">
              <input type="hidden" name="id" value="<?=htmlspecialchars($tk)?>">
              <select name="parent" class="inp sm" style="width:142px">
                <option value="">→ 顶层</option>
                <?php foreach ($curTasks as $c2): $cid = (string) ($c2['id'] ?? ''); if (in_array($cid, (array) $r['subtree_ids'], true)) continue; ?>
                <option value="<?=htmlspecialchars($cid)?>" <?=((string) ($t['parent'] ?? '') === $cid) ? 'selected' : ''?>>→ <?=htmlspecialchars(mb_substr((string) ($c2['title'] ?? ''), 0, 12))?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-s btn-sm">移动</button>
            </form>
            <form method="post" class="tw-inline" data-confirm="删除「<?=htmlspecialchars(mb_substr((string) ($t['title'] ?? ''), 0, 20))?>」<?=$subCount > 0 ? '及其 ' . $subCount . ' 个子任务' : ''?>？">
              <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
              <input type="hidden" name="ps_action" value="task_delete">
              <input type="hidden" name="project" value="<?=htmlspecialchars($taskProj($t))?>">
              <input type="hidden" name="id" value="<?=htmlspecialchars($tk)?>">
              <button class="btn btn-s btn-sm" title="删除">×</button>
            </form>
            <?php endif; ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    <?php else: ?>
    <div class="kanban" id="kbBoard">
      <?php foreach (ps_task_statuses() as $sk => $sl): ?>
      <div class="kanban-col" data-status="<?=$sk?>">
        <div class="kb-h"><span class="dot"></span><b><?=htmlspecialchars($sl)?></b><span class="note"><?=$kbStats['by_status'][$sk] ?? 0?></span></div>
        <?php if (($kbStats['by_status'][$sk] ?? 0) === 0): ?><div class="kb-empty"><?=$canEdit ? '拖到这里' : '暂无'?></div><?php endif; ?>
        <?php foreach ($curTasks as $t): if ((string) ($t['status'] ?? 'todo') !== $sk) continue;
          $due = (string) ($t['due'] ?? ''); $overdue = $due !== '' && substr($due, 0, 10) < date('Y-m-d');
          $prio = (string) ($t['priority'] ?? 'normal'); $ref = (array) ($t['ref'] ?? []); ?>
        <div class="kanban-card<?=$sk === 'done' ? ' kb-done' : ''?>" draggable="<?=$canEditTask($t) ? 'true' : 'false'?>" data-id="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>" data-project="<?=htmlspecialchars($taskProj($t))?>">
          <div class="kb-t"><?=htmlspecialchars((string) ($t['title'] ?? ''))?></div>
          <?php if ($scopeAll): ?><span class="kb-proj"><?=htmlspecialchars((string) ($t['_project_name'] ?? ''))?></span><?php endif; ?>
          <?php $rl = ps_repeat_label(ps_repeat_normalize($t['repeat'] ?? [])); if ($rl !== ''): ?><div class="kb-rep" title="完成这条后自动生成下一实例">🔁 <?=htmlspecialchars($rl)?></div><?php endif; ?>
          <div class="kb-m">
            <?php $prioCls = ['urgent' => 'prio-urgent', 'high' => 'prio-high', 'low' => 'prio-low'][$prio] ?? ''; ?>
            <?php if ($prio !== 'normal'): ?><span class="pill <?=$prioCls?>"><?=htmlspecialchars(ps_priorities()[$prio] ?? $prio)?></span><?php endif; ?>
            <?php if ((string) ($t['assignee'] ?? '') !== ''): ?><span class="note">@<?=htmlspecialchars((string) $t['assignee'])?></span><?php endif; ?>
            <?php if ($due !== ''): ?><span class="note due<?=$overdue ? ' over' : ''?>"><?=htmlspecialchars(substr($due, 0, 16))?><?=$overdue ? ' · 逾期' : ''?></span><?php endif; ?>
          </div>
          <?php if ($ref !== [] && (string) ($ref['type'] ?? '') !== ''): $ri = ps_ref_resolve($ref); ?>
          <span class="kb-ref"<?=$ri['missing'] ? ' style="color:var(--muted)"' : ''?>><?=htmlspecialchars($ri['type_label'])?>：<?=htmlspecialchars($ri['label'])?></span>
          <?php endif; ?>
          <?php if ((string) ($t['note'] ?? '') !== ''): ?><p class="kb-note"><?=htmlspecialchars(mb_substr((string) $t['note'], 0, 90))?></p><?php endif; ?>
          <?php $cr = $cardRoll[(string) ($t['id'] ?? '')] ?? []; if ((int) ($cr['total'] ?? 0) > 0): ?>
          <div class="kb-prog<?=((int) ($cr['overdue'] ?? 0)) > 0 ? ' over' : ''?>">
            <div class="bar"><span class="fill" style="width:<?=(int) $cr['pct']?>%"></span></div>
            <div class="txt">子任务 <?=(int) $cr['done']?>/<?=(int) $cr['total']?> · <?=(int) $cr['pct']?>%<?=((int) ($cr['overdue'] ?? 0)) > 0 ? ' · 逾期 ' . (int) $cr['overdue'] : ''?></div>
          </div>
          <?php endif; ?>
          <?php $cmts = ps_task_comments($taskProj($t), (string) ($t['id'] ?? '')); ?>
          <details class="kb-cmts">
            <summary><?=count($cmts) > 0 ? ('评论 ' . count($cmts) . ' · ' . htmlspecialchars(mb_substr((string) ($cmts[count($cmts) - 1]['text'] ?? ''), 0, 22))) : '写评论'?></summary>
            <?php foreach ($cmts as $c): ?>
            <div class="kb-cmt">
              <div class="kb-cmt-h"><b><?=htmlspecialchars(ps_user_display((string) ($c['by'] ?? '')))?></b><span><?=htmlspecialchars(substr((string) ($c['at'] ?? ''), 5, 11))?></span>
                <?php if ($canEditTask($t) && ($siteAdmin || ps_current_user() === (string) ($c['by'] ?? ''))): ?>
                <form method="post" class="tw-inline" style="margin-left:auto" data-confirm="删除这条评论？">
                  <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
                  <input type="hidden" name="ps_action" value="comment_delete">
                  <input type="hidden" name="project" value="<?=htmlspecialchars($taskProj($t))?>">
                  <input type="hidden" name="id" value="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>">
                  <input type="hidden" name="cid" value="<?=htmlspecialchars((string) ($c['id'] ?? ''))?>">
                  <button class="btn btn-s btn-sm" title="删除评论" style="padding:0 5px">×</button>
                </form>
                <?php endif; ?>
              </div>
              <div class="kb-cmt-t"><?=ps_comment_html((string) ($c['text'] ?? ''))?></div>
            </div>
            <?php endforeach; ?>
            <?php if ($canEditTask($t)): ?>
            <form method="post" class="kb-cmt-form">
              <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
              <input type="hidden" name="ps_action" value="comment_add">
              <input type="hidden" name="project" value="<?=htmlspecialchars($taskProj($t))?>">
              <input type="hidden" name="id" value="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>">
              <input class="inp sm" name="text" placeholder="@谁 说点什么…" maxlength="2000" required>
              <button class="btn btn-s btn-sm">发送</button>
            </form>
            <?php endif; ?>
          </details>
          <?php if ($canEditTask($t)): ?>
          <form method="post" class="kb-del" data-confirm="删除「<?=htmlspecialchars(mb_substr((string) ($t['title'] ?? ''), 0, 20))?>」<?=((int) ($subCounts[(string) ($t['id'] ?? '')] ?? 0)) > 0 ? '及其 ' . (int) $subCounts[(string) ($t['id'] ?? '')] . ' 个子任务' : ''?>？">
            <input type="hidden" name="_csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
            <input type="hidden" name="ps_action" value="task_delete">
            <input type="hidden" name="project" value="<?=htmlspecialchars($taskProj($t))?>">
            <input type="hidden" name="id" value="<?=htmlspecialchars((string) ($t['id'] ?? ''))?>">
            <button class="btn btn-s btn-sm" title="删除">×</button>
          </form>
          <?php endif; ?>
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
          <div><span class="pill">已就绪</span><ul><li>项目 / 任务 / 看板（拖拽改状态）</li><li>任务关联既有对象（发布 · 分发 · CRM · 订单）</li><li>多用户登录与角色权限（复用 <code>tasks</code> 权限）</li><li>提醒引擎与 cron 扫描（飞书 / 企微 / Slack / WhatsApp + 负责人邮件，幂等，两条腿都不通就不误标）</li><li>页内到期提醒：主线页任务到期面板 + 团队视角「今天 / 近 7 天」角标</li><li>多视图：表格 / 看板 / 日历 / 甘特（与自定义内容类型共用布局层）</li><li>多维表格三件套：关联 / 汇总 / 查值（已可用于自定义内容类型）</li><li>父子任务 + 树视图（层级、逐层进度汇总、级联删除）</li><li>任务 ↔ 内容类型记录互相关联（双向可查）</li><li>内容类型记录也能分层级 / 看树视图（自关联即层级，删除只断链不删子记录）</li><li>项目级成员权限（owner / 可编辑 / 只读；未设成员的项目=公共）</li></ul></div>
          <div><span class="pill">进行中</span><ul><li>看板视觉与设计系统对齐</li></ul></div>
          <div><span class="pill">规划中</span><ul><li>视图级公开只读分享（把某个视图给外部看）</li><li>记录评论与 @提及</li><li>任务重复规则（每周/每月自动生成）</li></ul></div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php else: ?>

    <?php $dueBuckets = ps_due_buckets(); $dueTop = array_slice(array_merge($dueBuckets['overdue'], $dueBuckets['today']), 0, 5); ?>
    <?php if ($dueTop !== []): ?>
    <!-- 页内到期提醒：外部渠道之外，打开这一页也能看到（角标 + 待办聚合） -->
    <div class="panel" style="margin-bottom:12px;border-left:3px solid var(--danger,#dc2626)">
      <div class="p-body">
        <b style="font-size:13px">任务到期 · 逾期 <?=count($dueBuckets['overdue'])?> · 今天 <?=count($dueBuckets['today'])?> · 近 7 天 <?=count($dueBuckets['soon'])?></b>
        <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px">
          <?php foreach ($dueTop as $d): $dt = (array) $d['task']; $od = substr((string) ($dt['due'] ?? ''), 0, 10) < date('Y-m-d'); ?>
          <div style="display:flex;gap:8px;align-items:center;font-size:12.5px;flex-wrap:wrap">
            <span class="pill<?=$od ? ' hl' : ''?>"><?=$od ? '逾期' : '今天'?></span>
            <b><?=htmlspecialchars((string) ($dt['title'] ?? ''))?></b>
            <span class="text-muted">· <?=htmlspecialchars((string) $d['project_name'])?></span>
            <?php if ((string) ($dt['assignee'] ?? '') !== ''): ?><span class="text-muted">· @<?=htmlspecialchars((string) $dt['assignee'])?></span><?php endif; ?>
            <?php if ((string) ($dt['due'] ?? '') !== ''): ?><span class="text-muted">· <?=htmlspecialchars(substr((string) $dt['due'], 0, 16))?></span><?php endif; ?>
            <a href="/xmp/today?view=team&project=<?=urlencode((string) $d['project'])?>" style="margin-left:auto">去处理 →</a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($dueBuckets['overdue']) + count($dueBuckets['today']) > count($dueTop)): ?>
        <p class="text-xs text-muted" style="margin:8px 0 0">还有更多，去 <a href="/xmp/today?view=team">团队视角</a> 看全部。</p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

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
  const fallbackProj = <?=json_encode((string) ($scopeAll ? '' : $curId))?>;
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
      fd.append('project', dragEl.dataset.project || fallbackProj); fd.append('id', id); fd.append('status', status);
      col.appendChild(dragEl);
      fetch(location.pathname + location.search, { method: 'POST', body: fd, redirect: 'manual' })
        .then(function () { location.reload(); }).catch(function () { location.reload(); });
    });
  });
})();

/* ── 团队视角：树视图折叠/展开（按深度收起整棵子树）── */
(function () {
  const tree = document.getElementById('twTree');
  if (!tree) return;
  const rows = Array.from(tree.querySelectorAll('.tw-row'));

  function subtreeOf(row) {
    const depth = Number(row.dataset.depth);
    const out = [];
    let n = row.nextElementSibling;
    while (n && n.classList.contains('tw-row') && Number(n.dataset.depth) > depth) { out.push(n); n = n.nextElementSibling; }
    return out;
  }
  function apply(row, collapsed) {
    subtreeOf(row).forEach(function (h) { h.style.display = collapsed ? 'none' : ''; });
    const btn = row.querySelector('.tw-tog[data-id]');
    if (btn) { btn.dataset.closed = collapsed ? '1' : '0'; btn.textContent = collapsed ? '▸' : '▾'; }
  }
  // 展开后要把「子树里本来就被折叠」的规则重新套一遍，否则会连带漏出更深层
  function reapply() {
    rows.forEach(function (row) {
      const btn = row.querySelector('.tw-tog[data-id]');
      if (btn) apply(row, btn.dataset.closed === '1');
    });
  }
  tree.addEventListener('click', function (e) {
    const btn = e.target.closest('.tw-tog[data-id]');
    if (!btn) return;
    const row = btn.closest('.tw-row');
    if (!row) return;
    apply(row, btn.dataset.closed !== '1');
    reapply();
  });
  document.getElementById('twFoldAll')?.addEventListener('click', function () { rows.forEach(function (r) { if (r.querySelector('.tw-tog[data-id]')) apply(r, true); }); });
  document.getElementById('twUnfoldAll')?.addEventListener('click', function () { rows.forEach(function (r) { r.style.display = ''; const b = r.querySelector('.tw-tog[data-id]'); if (b) { b.dataset.closed = '0'; b.textContent = '▾'; } }); });
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
