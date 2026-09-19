<?php
declare(strict_types=1);
/**
 * 项目与任务（ProjectSystem）—— 团队视角的数据层
 *
 * 定位：给「今日主线」（个人行动脊柱）加一层**团队协作**：
 *   项目 → 任务（负责人 / 截止 / 优先级 / 状态 / 关联对象 / 提醒规则）
 * 关联：任务用 ref{type,id,label} 指向既有实体（CRM 线索、订单、发布任务、课程、文章、直播），
 *       所以「发布任务 / 分发任务 / CRM 状态」天然打通，不另造一套。
 * 提醒：ps_due_reminders() 供 api/cron.php 扫描 → notify_channels_send()（含 Slack/飞书/企微）+ 邮件；
 *       以 reminded 键做**幂等**，同一任务同一提醒只发一次。
 *
 * 数据：data/projects/index.json（项目清单）+ data/projects/<id>.json（任务）
 * 规范：docs/ENGINEERING-AUDIT.md §8（内容契约）、docs/DEPLOY-CONVENTIONS.md（数据不入库）
 */

/** 任务状态（顺序即看板列顺序） */
function ps_task_statuses(): array
{
    return ['todo' => '待办', 'doing' => '进行中', 'review' => '待审', 'done' => '已完成'];
}

/** 优先级 */
function ps_priorities(): array
{
    return ['low' => '低', 'normal' => '普通', 'high' => '高', 'urgent' => '紧急'];
}

/** 任务可关联的既有对象类型（打通发布 / 分发 / CRM / 订单 / 课程 / 文章 / 直播） */
function ps_ref_types(): array
{
    return [
        'lead' => 'CRM 线索', 'deal' => 'CRM 商机', 'order' => '订单', 'member' => '会员',
        'publish' => '发布任务', 'distribute' => '分发任务', 'article' => '文章', 'course' => '课程',
        'live' => '直播', 'form' => '表单回传', 'other' => '其它',
    ];
}

function ps_dir(): string { return DATA_DIR . '/projects'; }
function ps_index_file(): string { return ps_dir() . '/index.json'; }
function ps_project_file(string $id): string { return ps_dir() . '/' . ps_safe_id($id) . '.json'; }

/** 只允许安全 id（防目录穿越） */
function ps_safe_id(string $id): string
{
    return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower($id));
}

/* ────────────── 项目 ────────────── */

function ps_projects(bool $withArchived = false): array
{
    $list = json_read(ps_index_file());
    $out = [];
    foreach ($list as $p) {
        if (!is_array($p)) continue;
        if (!$withArchived && !empty($p['archived'])) continue;
        $out[] = $p;
    }
    usort($out, static fn(array $a, array $b): int => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));
    return $out;
}

function ps_project_get(string $id): ?array
{
    $id = ps_safe_id($id);
    if ($id === '') return null;
    // 元信息在 index.json，任务在 <id>.json —— 合并后返回
    $meta = null;
    foreach ((array) json_read(ps_index_file()) as $p) {
        if (ps_safe_id((string) ($p['id'] ?? '')) === $id) { $meta = (array) $p; break; }
    }
    $data = json_read(ps_project_file($id));
    if ($meta === null && $data === []) return null;
    $out = ($meta ?? ['id' => $id, 'name' => $id]);
    $out['tasks'] = array_values(array_filter((array) ($data['tasks'] ?? []), 'is_array'));
    return $out;
}

/** 创建/更新项目（id 为空则新建） */
function ps_project_save(array $data): array
{
    $id = ps_safe_id((string) ($data['id'] ?? ''));
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') return ['ok' => false, 'error' => '项目名不能为空', 'id' => ''];
    if ($id === '') $id = 'p' . date('ymdHis') . substr((string) random_int(100, 999), 0, 3);
    $now = date('c');

    $index = (array) json_read(ps_index_file());
    $found = false;
    foreach ($index as $i => $p) {
        if (ps_safe_id((string) ($p['id'] ?? '')) !== $id) continue;
        $index[$i]['name'] = $name;
        $index[$i]['desc'] = trim((string) ($data['desc'] ?? ($p['desc'] ?? '')));
        $index[$i]['status'] = (string) ($data['status'] ?? ($p['status'] ?? 'active'));
        $index[$i]['members'] = array_values(array_unique(array_map('strval', (array) ($data['members'] ?? ($p['members'] ?? [])))));
        $index[$i]['archived'] = !empty($data['archived']);
        $index[$i]['updated_at'] = $now;
        $found = true;
        break;
    }
    if (!$found) {
        $index[] = [
            'id' => $id, 'name' => $name,
            'desc' => trim((string) ($data['desc'] ?? '')),
            'status' => (string) ($data['status'] ?? 'active'),
            'members' => array_values(array_unique(array_map('strval', (array) ($data['members'] ?? [])))),
            'archived' => false,
            'created_at' => $now, 'updated_at' => $now,
        ];
        if (!is_dir(ps_dir()) && !@mkdir(ps_dir(), 0775, true) && !is_dir(ps_dir())) {
            return ['ok' => false, 'error' => '无法创建数据目录', 'id' => ''];
        }
        if (!is_file(ps_project_file($id))) json_write(ps_project_file($id), ['tasks' => []]);
    }
    json_write(ps_index_file(), $index);
    return ['ok' => true, 'error' => '', 'id' => $id];
}

function ps_project_delete(string $id): bool
{
    $id = ps_safe_id($id);
    if ($id === '') return false;
    $index = array_values(array_filter((array) json_read(ps_index_file()), static fn($p): bool => ps_safe_id((string) ($p['id'] ?? '')) !== $id));
    json_write(ps_index_file(), $index);
    @unlink(ps_project_file($id));
    return true;
}

/* ────────────── 任务 ────────────── */

function ps_tasks(string $projectId): array
{
    $p = ps_project_get($projectId);
    if ($p === null) return [];
    $tasks = $p['tasks'];
    $order = array_flip(array_keys(ps_task_statuses()));
    usort($tasks, static function (array $a, array $b) use ($order): int {
        $sa = $order[(string) ($a['status'] ?? 'todo')] ?? 99;
        $sb = $order[(string) ($b['status'] ?? 'todo')] ?? 99;
        if ($sa !== $sb) return $sa <=> $sb;
        return strcmp((string) ($a['due'] ?? '9999'), (string) ($b['due'] ?? '9999'));
    });
    return $tasks;
}

/** 任务字段校验 → 错误列表 */
function ps_task_errors(array $t): array
{
    $e = [];
    if (trim((string) ($t['title'] ?? '')) === '') $e[] = '标题不能为空';
    if (!isset(ps_task_statuses()[(string) ($t['status'] ?? 'todo')])) $e[] = '状态不合法';
    if (!isset(ps_priorities()[(string) ($t['priority'] ?? 'normal')])) $e[] = '优先级不合法';
    $due = (string) ($t['due'] ?? '');
    if ($due !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $due) !== 1) $e[] = '截止时间格式应为 YYYY-MM-DD 或 YYYY-MM-DD HH:MM';
    $ref = (array) ($t['ref'] ?? []);
    if ($ref !== [] && !isset(ps_ref_types()[(string) ($ref['type'] ?? '')])) $e[] = '关联对象类型不合法';
    return $e;
}

/**
 * 新建/更新任务（id 为空则新建）；返回 [ok, error, task]
 */
function ps_task_save(string $projectId, array $data): array
{
    $p = ps_project_get($projectId);
    if ($p === null) return ['ok' => false, 'error' => '项目不存在', 'task' => []];
    $id = (string) ($data['id'] ?? '');
    $now = date('c');
    $incoming = [
        'title' => trim((string) ($data['title'] ?? '')),
        'note' => trim((string) ($data['note'] ?? '')),
        'status' => (string) ($data['status'] ?? 'todo'),
        'priority' => (string) ($data['priority'] ?? 'normal'),
        'assignee' => trim((string) ($data['assignee'] ?? '')),
        'start' => (string) ($data['start'] ?? ''),
        'due' => (string) ($data['due'] ?? ''),
        'ref' => array_intersect_key((array) ($data['ref'] ?? []), array_flip(['type', 'id', 'label'])),
        'remind' => array_intersect_key((array) ($data['remind'] ?? []), array_flip(['before_days', 'on_due', 'channels'])),
    ];
    $errors = ps_task_errors($incoming + ['status' => $incoming['status'], 'priority' => $incoming['priority']]);
    if ($errors !== []) return ['ok' => false, 'error' => implode('；', $errors), 'task' => []];

    $tasks = $p['tasks'];
    if ($id !== '') {
        foreach ($tasks as $i => $t) {
            if ((string) ($t['id'] ?? '') !== $id) continue;
            $tasks[$i] = array_merge($t, $incoming, ['updated_at' => $now]);
            json_write(ps_project_file($projectId), ['tasks' => $tasks]);
            ps_touch_project($projectId);
            return ['ok' => true, 'error' => '', 'task' => $tasks[$i]];
        }
        return ['ok' => false, 'error' => '任务不存在', 'task' => []];
    }
    $task = $incoming + [
        'id' => 't' . date('ymdHis') . substr((string) random_int(100, 999), 0, 3),
        'created_at' => $now, 'updated_at' => $now,
        'created_by' => function_exists('member_current') ? (string) ((member_current()['name'] ?? '') ?: '') : '',
        'history' => [], 'reminded' => [],
    ];
    $tasks[] = $task;
    json_write(ps_project_file($projectId), ['tasks' => $tasks]);
    ps_touch_project($projectId);
    return ['ok' => true, 'error' => '', 'task' => $task];
}

function ps_touch_project(string $projectId): void
{
    $index = (array) json_read(ps_index_file());
    foreach ($index as $i => $p) {
        if (ps_safe_id((string) ($p['id'] ?? '')) === ps_safe_id($projectId)) {
            $index[$i]['updated_at'] = date('c');
            break;
        }
    }
    json_write(ps_index_file(), $index);
}

/** 拖拽/切换状态：校验目标状态合法，并写入 history（可回溯谁在什么时候挪过） */
function ps_task_move(string $projectId, string $taskId, string $toStatus, string $by = ''): array
{
    if (!isset(ps_task_statuses()[$toStatus])) return ['ok' => false, 'error' => '目标状态不合法'];
    $p = ps_project_get($projectId);
    if ($p === null) return ['ok' => false, 'error' => '项目不存在'];
    $tasks = $p['tasks'];
    foreach ($tasks as $i => $t) {
        if ((string) ($t['id'] ?? '') !== $taskId) continue;
        $from = (string) ($t['status'] ?? 'todo');
        if ($from === $toStatus) return ['ok' => true, 'error' => '', 'task' => $t];
        $tasks[$i]['status'] = $toStatus;
        $tasks[$i]['updated_at'] = date('c');
        $hist = (array) ($tasks[$i]['history'] ?? []);
        $hist[] = ['at' => date('c'), 'from' => $from, 'to' => $toStatus, 'by' => $by];
        $tasks[$i]['history'] = array_slice($hist, -50);
        json_write(ps_project_file($projectId), ['tasks' => $tasks]);
        ps_touch_project($projectId);
        return ['ok' => true, 'error' => '', 'task' => $tasks[$i]];
    }
    return ['ok' => false, 'error' => '任务不存在'];
}

function ps_task_delete(string $projectId, string $taskId): bool
{
    $p = ps_project_get($projectId);
    if ($p === null) return false;
    $tasks = array_values(array_filter($p['tasks'], static fn(array $t): bool => (string) ($t['id'] ?? '') !== $taskId));
    json_write(ps_project_file($projectId), ['tasks' => $tasks]);
    ps_touch_project($projectId);
    return true;
}

/** 看板统计：每列数量 + 逾期数 */
function ps_stats(string $projectId, string $today = ''): array
{
    $today = $today !== '' ? $today : date('Y-m-d');
    $by = array_fill_keys(array_keys(ps_task_statuses()), 0);
    $overdue = 0; $total = 0;
    foreach (ps_tasks($projectId) as $t) {
        $st = (string) ($t['status'] ?? 'todo');
        $by[$st] = ($by[$st] ?? 0) + 1;
        $total++;
        $due = (string) ($t['due'] ?? '');
        if ($st !== 'done' && $due !== '' && substr($due, 0, 10) < $today) $overdue++;
    }
    return ['by_status' => $by, 'total' => $total, 'overdue' => $overdue];
}

/* ────────────── 提醒（幂等） ────────────── */

/**
 * 到期/逾期提醒扫描
 * @param string $now 参考时间（默认现在）
 * @param int $withinHours 提前窗口（默认 24 小时）
 * @return list<array{project:string,task:array,kind:string,key:string}>
 */
function ps_due_reminders(string $now = '', int $withinHours = 24): array
{
    $ts = $now !== '' ? strtotime($now) : time();
    if ($ts === false) $ts = time();
    $out = [];
    foreach (ps_projects() as $p) {
        $pid = ps_safe_id((string) ($p['id'] ?? ''));
        foreach (ps_tasks($pid) as $t) {
            if ((string) ($t['status'] ?? 'todo') === 'done') continue;
            $due = (string) ($t['due'] ?? '');
            if ($due === '') continue;
            $dueTs = strtotime(strlen($due) === 10 ? $due . ' 23:59' : $due);
            if ($dueTs === false) continue;
            $reminded = array_map('strval', (array) ($t['reminded'] ?? []));
            $day = date('Y-m-d', $dueTs);
            if ($dueTs < $ts) {
                $key = 'overdue:' . $day;
                if (!in_array($key, $reminded, true)) $out[] = ['project' => $pid, 'task' => $t, 'kind' => 'overdue', 'key' => $key];
            } elseif ($dueTs - $ts <= $withinHours * 3600) {
                $key = 'due:' . $day;
                if (!in_array($key, $reminded, true)) $out[] = ['project' => $pid, 'task' => $t, 'kind' => 'due_soon', 'key' => $key];
            }
        }
    }
    return $out;
}

/** 标记已提醒（幂等键写入任务） */
function ps_mark_reminded(string $projectId, string $taskId, string $key): bool
{
    $p = ps_project_get($projectId);
    if ($p === null) return false;
    $tasks = $p['tasks'];
    foreach ($tasks as $i => $t) {
        if ((string) ($t['id'] ?? '') !== $taskId) continue;
        $r = array_map('strval', (array) ($t['reminded'] ?? []));
        if (!in_array($key, $r, true)) $r[] = $key;
        $tasks[$i]['reminded'] = array_slice($r, -20);
        json_write(ps_project_file($projectId), ['tasks' => $tasks]);
        return true;
    }
    return false;
}

/** 团队成员候选（后台用户），供指派下拉 */
function ps_assignees(): array
{
    $users = (array) json_read(DATA_DIR . '/users.json');
    $out = [];
    foreach ($users as $u) {
        if (!is_array($u)) continue;
        $name = trim((string) ($u['name'] ?? ''));
        if ($name === '') continue;
        $out[] = ['name' => $name, 'email' => (string) ($u['email'] ?? ''), 'role' => (string) ($u['role'] ?? '')];
    }
    return $out;
}

/* ────────────── 种子：把「我们正在做的事」建成第一个项目 ────────────── */

/**
 * 幂等创建示例项目（已存在任一项目则跳过）
 * 任务内容取自当前真实工作（demo 内容契约 / 适配流水线 / 质量门禁），不是编造。
 */
function ps_seed_default(): array
{
    if (ps_projects(true) !== []) return ['ok' => true, 'created' => false, 'error' => ''];
    $r = ps_project_save([
        'name' => '产品迭代 · 团队视角试点',
        'desc' => '把「今日主线」扩成团队协作：任务 / 看板 / 关联对象 / 到期提醒。先跑通一条，再补多维表格与甘特。',
    ]);
    if (!($r['ok'] ?? false)) return ['ok' => false, 'created' => false, 'error' => (string) ($r['error'] ?? '')];
    $pid = (string) $r['id'];
    $day = static fn(int $d): string => date('Y-m-d', time() + $d * 86400);
    $tasks = [
        ['title' => '收尾 demo 内容契约遗留项（FAQ/42 模块/首页编者按）', 'status' => 'done', 'priority' => 'high', 'assignee' => 'Seven', 'ref' => ['type' => 'article', 'id' => 'content-contract', 'label' => '内容契约']],
        ['title' => '修首页文章行遮挡（长分类撑破 meta 列）', 'status' => 'done', 'priority' => 'urgent', 'assignee' => 'Seven', 'ref' => ['type' => 'other', 'id' => 'a-row-overflow', 'label' => '渲染缺陷']],
        ['title' => '适配流水线：批量跑候选并出闸门报告', 'status' => 'doing', 'priority' => 'high', 'assignee' => 'Seven', 'due' => $day(2), 'note' => '已跑通 ntfy 全链路（passed/verified），下一步批量 3~5 个。', 'ref' => ['type' => 'other', 'id' => 'adapter-forge', 'label' => '适配即服务']],
        ['title' => '团队视角：任务 + 看板 + 到期提醒（本条）', 'status' => 'doing', 'priority' => 'high', 'assignee' => 'Seven', 'due' => $day(1), 'ref' => ['type' => 'other', 'id' => 'project-view', 'label' => '今日主线扩展']],
        ['title' => '补 Slack 通知渠道（NotifyChannels）', 'status' => 'todo', 'priority' => 'normal', 'assignee' => '', 'due' => $day(3), 'ref' => ['type' => 'other', 'id' => 'notify-slack', 'label' => '提醒渠道']],
        ['title' => '多维表格：relation / rollup / lookup 字段', 'status' => 'todo', 'priority' => 'normal', 'due' => $day(14), 'note' => 'P1：把 ObjectGraph 的「按人聚合」泛化成通用 record↔record 关联。', 'ref' => ['type' => 'other', 'id' => 'relation-fields', 'label' => 'Airtable 能力']],
        ['title' => '甘特图 + 视图切换器（grid/board/calendar/gantt）', 'status' => 'todo', 'priority' => 'low', 'due' => $day(21), 'ref' => ['type' => 'other', 'id' => 'gantt', 'label' => '多维视图']],
        ['title' => '项目级成员权限（owner/editor/viewer）', 'status' => 'todo', 'priority' => 'low', 'due' => $day(28), 'ref' => ['type' => 'other', 'id' => 'project-perms', 'label' => '权限管理']],
        ['title' => 'PHPStan 基线分目录消减（admin/ 105 条）', 'status' => 'review', 'priority' => 'normal', 'due' => $day(5), 'ref' => ['type' => 'other', 'id' => 'phpstan-baseline', 'label' => '工程质量']],
    ];
    $n = 0;
    foreach ($tasks as $t) {
        if (ps_task_save($pid, $t)['ok'] ?? false) $n++;
    }
    return ['ok' => true, 'created' => true, 'error' => '', 'project' => $pid, 'tasks' => $n];
}
