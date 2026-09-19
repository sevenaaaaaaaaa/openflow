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

/**
 * 关联目标全集：内置对象 + 自定义内容类型的记录（键形如 cpt:<typeSlug>）。
 * 内置目标是稳定枚举；内容类型是用户自己建的，所以动态拼进来。
 */
function ps_ref_targets(): array
{
    $out = ps_ref_types();
    if (function_exists('cpt_types')) {
        foreach ((array) cpt_types() as $t) {
            $slug = (string) ($t['slug'] ?? '');
            if ($slug === '') continue;
            $out['cpt:' . $slug] = (string) ($t['name'] ?? $slug);
        }
    }
    return $out;
}

/** 关联类型 → 展示名（含 cpt:<slug>） */
function ps_ref_label(string $type): string
{
    return (string) (ps_ref_targets()[$type] ?? $type);
}

/** 关联是否指向自定义内容类型；是则返回类型 slug，否则空串 */
function ps_ref_cpt_slug(string $type): string
{
    return str_starts_with($type, 'cpt:') ? substr($type, 4) : '';
}

/**
 * 解析关联为可读信息：内容类型的标题**现取**（对方改名后任务卡片跟着变），
 * 目标已被删则标 missing，并用存下来的 label 兜底。
 */
function ps_ref_resolve(array $ref): array
{
    $type = (string) ($ref['type'] ?? '');
    $id = (string) ($ref['id'] ?? '');
    $stored = (string) ($ref['label'] ?? '');
    if ($type === '') return ['type' => '', 'id' => '', 'label' => '', 'missing' => false, 'type_label' => ''];
    $out = ['type' => $type, 'id' => $id, 'label' => $stored !== '' ? $stored : $id, 'missing' => false, 'type_label' => ps_ref_label($type)];
    $slug = ps_ref_cpt_slug($type);
    if ($slug !== '' && $id !== '' && function_exists('cpt_entry')) {
        $e = cpt_entry($slug, $id);
        if ($e === null) {
            $out['missing'] = true;
            $out['label'] = ($stored !== '' ? $stored : $id) . '（已删除）';
        } else {
            $out['label'] = (string) ($e['title'] ?? $out['label']);
        }
    }
    return $out;
}

/** 反向查询：哪些任务引用了这条记录（跨全部项目，含已归档） */
function ps_tasks_referencing(string $type, string $id): array
{
    $out = [];
    foreach (ps_projects(true) as $p) {
        $pid = ps_safe_id((string) ($p['id'] ?? ''));
        if ($pid === '') continue;
        foreach (ps_tasks($pid) as $t) {
            $ref = (array) ($t['ref'] ?? []);
            if ((string) ($ref['type'] ?? '') !== $type) continue;
            if ((string) ($ref['id'] ?? '') !== $id) continue;
            $out[] = [
                'project' => $pid,
                'project_name' => (string) ($p['name'] ?? $pid),
                'task' => $t,
            ];
        }
    }
    return $out;
}

function ps_dir(): string { return DATA_DIR . '/projects'; }
function ps_index_file(): string { return ps_dir() . '/index.json'; }
function ps_project_file(string $id): string { return ps_dir() . '/' . ps_safe_id($id) . '.json'; }

/** 任务 id 清洗（同安全 id 规则，语义单列） */
function ps_safe_task_id(string $id): string { return ps_safe_id($id); }

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
        $index[$i]['members'] = ps_members_normalize($data['members'] ?? ($p['members'] ?? []));
        // 只有显式传了 archived 才改它：否则「改个成员」会把归档项目顺手解档
        $index[$i]['archived'] = array_key_exists('archived', $data) ? !empty($data['archived']) : !empty($p['archived']);
        $index[$i]['updated_at'] = $now;
        $found = true;
        break;
    }
    if (!$found) {
        $index[] = [
            'id' => $id, 'name' => $name,
            'desc' => trim((string) ($data['desc'] ?? '')),
            'status' => (string) ($data['status'] ?? 'active'),
            'members' => ps_members_normalize($data['members'] ?? []),
            'archived' => false,
            'created_at' => $now, 'updated_at' => $now,
        ];
        if (!is_dir(ps_dir()) && !@mkdir(ps_dir(), 0775, true) && !is_dir(ps_dir())) {
            return ['ok' => false, 'error' => '无法创建数据目录', 'id' => ''];
        }
        // 创建者自动是 owner（有登录态时）；没登录态（CLI/脚本）就留空，留空=公共项目
        $creator = ps_current_user();
        if ($creator !== '' && $index[count($index) - 1]['members'] === []) {
            $index[count($index) - 1]['members'] = [$creator => 'owner'];
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
    $parent = ps_safe_task_id((string) ($t['parent'] ?? ''));
    if ($parent !== '' && $parent === ps_safe_task_id((string) ($t['id'] ?? ''))) $e[] = '父任务不能是自己';
    $ref = (array) ($t['ref'] ?? []);
    if ($ref !== [] && (string) ($ref['type'] ?? '') !== '') {
        $rt = (string) $ref['type'];
        if (!isset(ps_ref_targets()[$rt])) {
            $e[] = '关联对象类型不合法';
        } else {
            $rid = (string) ($ref['id'] ?? '');
            $slug = ps_ref_cpt_slug($rt);
            if ($slug !== '' && $rid !== '' && function_exists('cpt_entry') && cpt_entry($slug, $rid) === null) {
                $e[] = '关联的内容记录不存在';
            }
        }
    }
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

    // 只覆盖「显式传进来的字段」：更新时没传的键保留原值。
    // （此前缺字段会被清成默认值——改名顺手把截止日抹掉、把重复规则清空，都踩过。）
    $defaults = [
        'title' => '', 'note' => '', 'status' => 'todo', 'priority' => 'normal',
        'assignee' => '', 'start' => '', 'due' => '', 'ref' => [], 'parent' => '',
        'remind' => ['before_days' => 0, 'on_due' => true, 'channels' => ['im', 'email']],
    ];
    $existing = null;
    if ($id !== '') {
        foreach ((array) $p['tasks'] as $t) {
            if ((string) ($t['id'] ?? '') === $id) { $existing = (array) $t; break; }
        }
        if ($existing === null) return ['ok' => false, 'error' => '任务不存在', 'task' => []];
    }
    $base = $existing ?? $defaults;
    $incoming = [
        'title' => array_key_exists('title', $data) ? trim((string) $data['title']) : (string) ($base['title'] ?? ''),
        'note' => array_key_exists('note', $data) ? trim((string) $data['note']) : (string) ($base['note'] ?? ''),
        'status' => array_key_exists('status', $data) ? (string) $data['status'] : (string) ($base['status'] ?? 'todo'),
        'priority' => array_key_exists('priority', $data) ? (string) $data['priority'] : (string) ($base['priority'] ?? 'normal'),
        'assignee' => array_key_exists('assignee', $data) ? trim((string) $data['assignee']) : (string) ($base['assignee'] ?? ''),
        'start' => array_key_exists('start', $data) ? (string) $data['start'] : (string) ($base['start'] ?? ''),
        'due' => array_key_exists('due', $data) ? (string) $data['due'] : (string) ($base['due'] ?? ''),
        'ref' => array_key_exists('ref', $data)
            ? array_intersect_key((array) $data['ref'], array_flip(['type', 'id', 'label']))
            : (array) ($base['ref'] ?? []),
        'parent' => array_key_exists('parent', $data) ? ps_safe_task_id((string) $data['parent']) : ps_safe_task_id((string) ($base['parent'] ?? '')),
        'remind' => array_key_exists('remind', $data)
            ? array_intersect_key((array) $data['remind'], array_flip(['before_days', 'on_due', 'channels']))
            : (array) ($base['remind'] ?? $defaults['remind']),
    ];
    $errors = ps_task_errors($incoming + ['id' => $id, 'status' => $incoming['status'], 'priority' => $incoming['priority']]);
    // 层级校验：父任务必须在本项目内、不能挂到自己的子孙下（否则成环）、深度有上限
    $parent = (string) $incoming['parent'];
    if ($parent !== '' && $errors === []) {
        $ids = array_map(static fn(array $t): string => (string) ($t['id'] ?? ''), (array) $p['tasks']);
        if (!in_array($parent, $ids, true)) {
            $errors[] = '父任务不存在';
        } elseif ($id !== '' && in_array($parent, ps_task_subtree_ids($projectId, $id), true)) {
            $errors[] = '父任务不能是它自己的子任务';
        } else {
            $depth = count(ps_task_ancestors($projectId, $parent)) + 1;
            if ($depth >= 9) $errors[] = '层级太深（最多 8 层）';
        }
    }
    // 历史悬空关联：如果任务原本就指向这条已删记录、且这次没改关联，
    // 放行编辑——否则一条任务会因为「关联对象被删」而连标题都改不了。
    if ($errors !== []) {
        $dangling = '关联的内容记录不存在';
        if (in_array($dangling, $errors, true) && $id !== '' && count($errors) === 1) {
            foreach ((array) $p['tasks'] as $t) {
                if ((string) ($t['id'] ?? '') !== $id) continue;
                $oldRef = (array) ($t['ref'] ?? []);
                $newRef = (array) $incoming['ref'];
                if ((string) ($oldRef['type'] ?? '') === (string) ($newRef['type'] ?? '')
                    && (string) ($oldRef['id'] ?? '') === (string) ($newRef['id'] ?? '')) {
                    $errors = [];
                }
                break;
            }
        }
    }
    if ($errors !== []) return ['ok' => false, 'error' => implode('；', $errors), 'task' => []];

    $tasks = $p['tasks'];
    if ($id !== '') {
        foreach ($tasks as $i => $t) {
            if ((string) ($t['id'] ?? '') !== $id) continue;
            $patch = $incoming;
            if (array_key_exists('repeat', $data)) $patch['repeat'] = ps_repeat_normalize($data['repeat']);
            $tasks[$i] = array_merge($t, $patch, ['updated_at' => $now]);
            json_write(ps_project_file($projectId), ['tasks' => $tasks]);
            ps_touch_project($projectId);
            return ['ok' => true, 'error' => '', 'task' => $tasks[$i]];
        }
        return ['ok' => false, 'error' => '任务不存在', 'task' => []];
    }
    $incoming['repeat'] = ps_repeat_normalize($data['repeat'] ?? []);
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

/**
 * 删除任务及其全部子任务（返回删除条数，0 表示没找到）。
 * 子任务单独留着会变成悬空层级，所以级联删——UI 提示里会写明会带走几条。
 */
function ps_task_delete(string $projectId, string $taskId): int
{
    $p = ps_project_get($projectId);
    if ($p === null) return 0;
    $tasks = $p['tasks'];
    $has = false;
    foreach ($tasks as $t) if ((string) ($t['id'] ?? '') === $taskId) { $has = true; break; }
    if (!$has) return 0;
    $kill = ps_task_subtree_ids($projectId, $taskId);
    $tasks = array_values(array_filter($tasks, static fn(array $t): bool => !in_array((string) ($t['id'] ?? ''), $kill, true)));
    json_write(ps_project_file($projectId), ['tasks' => $tasks]);
    ps_touch_project($projectId);
    return count($kill);
}

/** 子树 id（含自己） */
function ps_task_subtree_ids(string $projectId, string $taskId): array
{
    $tasks = ps_tasks($projectId);
    $kids = [];
    foreach ($tasks as $t) $kids[(string) ($t['parent'] ?? '')][] = (string) ($t['id'] ?? '');
    $out = [];
    $stack = [$taskId];
    while ($stack !== []) {
        $cur = (string) array_pop($stack);
        if (in_array($cur, $out, true)) continue;   // 防环兜底
        $out[] = $cur;
        foreach ($kids[$cur] ?? [] as $k) $stack[] = $k;
    }
    return $out;
}

/** 祖先链（由近及远） */
function ps_task_ancestors(string $projectId, string $taskId): array
{
    $byId = [];
    foreach (ps_tasks($projectId) as $t) $byId[(string) ($t['id'] ?? '')] = $t;
    $out = [];
    $cur = (string) ($byId[$taskId]['parent'] ?? '');
    $guard = 0;
    while ($cur !== '' && isset($byId[$cur]) && $guard < 32) {
        $out[] = $cur;
        $cur = (string) ($byId[$cur]['parent'] ?? '');
        $guard++;
    }
    return $out;
}

/**
 * 树结构：[['task'=>..., 'depth'=>int, 'children'=>[...], 'rollup'=>[...]]]
 * 顶层是 parent 为空（或指向不存在的任务）的根。
 */
function ps_task_tree(string $projectId): array
{
    $tasks = ps_tasks($projectId);
    $byId = [];
    foreach ($tasks as $t) $byId[(string) ($t['id'] ?? '')] = $t;
    // 有效父：空 / 自指 / 指向不存在的任务 → 当根
    $parentOf = [];
    foreach ($byId as $id => $t) {
        $parent = (string) ($t['parent'] ?? '');
        $parentOf[$id] = ($parent === '' || $parent === $id || !isset($byId[$parent])) ? '' : $parent;
    }
    // 破环：沿父链走，走回自己就把该节点升为根（坏数据也不丢节点、不转不出来）
    foreach ($parentOf as $id => $pid) {
        if ($pid === '') continue;
        $seen = [$id => true];
        $cur = $pid;
        while ($cur !== '') {
            if (isset($seen[$cur])) { $parentOf[$id] = ''; break; }
            $seen[$cur] = true;
            $cur = $parentOf[$cur] ?? '';
        }
    }
    $kids = [];
    $roots = [];
    foreach ($parentOf as $id => $pid) {
        if ($pid === '') $roots[] = $id;
        else $kids[$pid][] = $id;
    }
    $build = static function (string $id, int $depth) use (&$build, $kids, $byId, $projectId): array {
        $children = [];
        foreach ($kids[$id] ?? [] as $k) $children[] = $build($k, $depth + 1);
        return ['task' => $byId[$id], 'depth' => $depth, 'children' => $children, 'rollup' => ps_task_rollup($projectId, $id)];
    };
    return array_map(static fn(string $id): array => $build($id, 0), $roots);
}

/** 树 → 渲染用的扁平行（DFS 顺序 + 深度），页面据此缩进，无需递归模板 */
function ps_task_tree_flat(string $projectId, string $today = ''): array
{
    $out = [];
    $walk = static function (array $nodes) use (&$walk, &$out, $today, $projectId): void {
        foreach ($nodes as $n) {
            $t = (array) ($n['task'] ?? []);
            $roll = (array) ($n['rollup'] ?? []);
            $out[] = [
                'task' => $t,
                'depth' => (int) ($n['depth'] ?? 0),
                'children' => count((array) ($n['children'] ?? [])),
                'rollup' => $roll,
                'progress' => ps_task_progress($projectId, $t, $roll, $today),
                'subtree_ids' => ps_task_subtree_ids($projectId, (string) ($t['id'] ?? '')),
            ];
            $walk((array) ($n['children'] ?? []));
        }
    };
    $walk(ps_task_tree($projectId));
    return $out;
}

/** 一条任务的“进度”：有子任务看子任务完成度，没有就看自己的状态 */
function ps_task_progress(string $projectId, array $task, array $rollup, string $today = ''): array
{
    $total = (int) ($rollup['total'] ?? 0);
    if ($total > 0) {
        return ['mode' => 'rollup', 'total' => $total, 'done' => (int) ($rollup['done'] ?? 0), 'pct' => (int) ($rollup['pct'] ?? 0), 'overdue' => (int) ($rollup['overdue'] ?? 0)];
    }
    $done = (string) ($task['status'] ?? 'todo') === 'done' ? 1 : 0;
    return ['mode' => 'self', 'total' => 1, 'done' => $done, 'pct' => $done * 100, 'overdue' => 0];
}

/**
 * 子任务汇总（含所有后代）：总数 / 已完成 / 逾期 / 进度%。
 * 自己算不算？不算——「3/7 完成」说的是这条任务下面的事。
 */
function ps_task_rollup(string $projectId, string $taskId, string $today = ''): array
{
    $today = $today !== '' ? $today : date('Y-m-d');
    $ids = ps_task_subtree_ids($projectId, $taskId);
    $ids = array_values(array_filter($ids, static fn(string $x): bool => $x !== $taskId));
    $byId = [];
    foreach (ps_tasks($projectId) as $t) $byId[(string) ($t['id'] ?? '')] = $t;
    $total = 0; $done = 0; $overdue = 0;
    foreach ($ids as $id) {
        $t = $byId[$id] ?? null;
        if ($t === null) continue;
        $total++;
        $st = (string) ($t['status'] ?? 'todo');
        if ($st === 'done') $done++;
        $due = (string) ($t['due'] ?? '');
        if ($st !== 'done' && $due !== '' && substr($due, 0, 10) < $today) $overdue++;
    }
    return ['total' => $total, 'done' => $done, 'overdue' => $overdue, 'pct' => $total > 0 ? (int) round($done / $total * 100) : 0];
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
    $roots = 0;
    $leafDone = 0; $leafTotal = 0;
    foreach (ps_tasks($projectId) as $t) {
        if ((string) ($t['parent'] ?? '') === '') $roots++;
        $sub = ps_task_subtree_ids($projectId, (string) ($t['id'] ?? ''));
        if (count($sub) === 1) { $leafTotal++; if ((string) ($t['status'] ?? '') === 'done') $leafDone++; }
    }
    return ['by_status' => $by, 'total' => $total, 'overdue' => $overdue, 'roots' => $roots, 'nodes' => $total, 'leaf_total' => $leafTotal, 'leaf_done' => $leafDone];
}

/* ────────────── 任务重复规则 ────────────── */
/**
 * 【为什么】每周一的周报、每月 1 号的账单核对、每两周的内容排期……这些事不该靠人记。
 * 规则挂在任务上：**完成当前这条时，才生成下一个实例**（Todoist 语义）。
 * 这样既有历史（已完成的那条还在），又不会因为没做而堆出一串重复任务。
 */

function ps_repeat_freqs(): array
{
    return ['none' => '不重复', 'daily' => '每天', 'weekly' => '每周', 'monthly' => '每月'];
}

/** 规范化 repeat 配置 */
function ps_repeat_normalize(mixed $v): array
{
    if (!is_array($v)) return ['freq' => 'none'];
    $freq = (string) ($v['freq'] ?? 'none');
    if (!isset(ps_repeat_freqs()[$freq])) $freq = 'none';
    $interval = (int) ($v['interval'] ?? 1);
    $interval = max(1, min(12, $interval));
    $until = (string) ($v['until'] ?? '');
    if ($until !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) !== 1) $until = '';
    $out = ['freq' => $freq, 'interval' => $interval, 'until' => $until];
    if (isset($v['spawned']) && $v['spawned'] !== '') $out['spawned'] = (string) $v['spawned'];
    if (!empty($v['ended'])) $out['ended'] = true;
    return $out;
}

/** 重复规则的可读描述 */
function ps_repeat_label(array $repeat): string
{
    $freq = (string) ($repeat['freq'] ?? 'none');
    if ($freq === 'none') return '';
    $n = max(1, (int) ($repeat['interval'] ?? 1));
    $base = ['daily' => '天', 'weekly' => '周', 'monthly' => '月'][$freq] ?? '';
    $s = $n === 1 ? ('每' . $base) : ('每 ' . $n . ' ' . $base);
    if ((string) ($repeat['until'] ?? '') !== '') $s .= '（至 ' . (string) $repeat['until'] . '）';
    if (!empty($repeat['ended'])) $s .= '（已结束）';
    return $s;
}

/**
 * 下一次日期（YYYY-MM-DD）；超出 until 或无法推进 → null
 * @param array $repeat 规则
 * @param string $from 基准日（通常是当前实例的截止日）
 */
function ps_repeat_next_date(array $repeat, string $from): ?string
{
    $freq = (string) ($repeat['freq'] ?? 'none');
    if ($freq === 'none') return null;
    $n = max(1, (int) ($repeat['interval'] ?? 1));
    $from = substr($from, 0, 10);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) return null;
    $ts = strtotime($from . ' 12:00:00');
    if ($ts === false) return null;
    if ($freq === 'daily') {
        $next = strtotime('+' . $n . ' day', $ts);
    } elseif ($freq === 'weekly') {
        $next = strtotime('+' . $n . ' week', $ts);
    } else { // monthly：按「同一个号」，月末不存在的号用当月最后一天（31 号 → 2 月 28/29）
        $day = (int) date('j', $ts);
        $y = (int) date('Y', $ts);
        $m = (int) date('n', $ts) + $n;
        while ($m > 12) { $m -= 12; $y++; }
        $last = (int) date('t', mktime(12, 0, 0, $m, 1, $y));
        $next = mktime(12, 0, 0, $m, min($day, $last), $y);
    }
    if ($next === false) return null;
    $nd = date('Y-m-d', $next);
    $until = (string) ($repeat['until'] ?? '');
    if ($until !== '' && strcmp($nd, $until) > 0) return null;
    return $nd;
}

/** 把日期按天数平移（用于 start/due 的相对关系） */
function ps_repeat_shift(string $date, int $days): string
{
    $ts = strtotime(substr($date, 0, 10) . ' 12:00:00');
    return $ts === false ? '' : date('Y-m-d', (int) strtotime(($days >= 0 ? '+' : '') . $days . ' day', $ts));
}

/**
 * 生成到期的下一实例（幂等：已完成的实例只会生成一次，用 repeat.spawned 记住）。
 * 规则：仅 status=done 且带重复规则的任务触发；生成的实例除状态外照抄模板（评论不复制）。
 * @return list<array{project:string,from:string,new:string,title:string}>
 */
function ps_repeat_spawn_due(string $projectId = '', string $today = ''): array
{
    $today = $today !== '' ? $today : date('Y-m-d');
    $out = [];
    $projects = $projectId !== '' ? [['id' => $projectId]] : ps_projects(true);
    foreach ($projects as $p) {
        $pid = ps_safe_id((string) ($p['id'] ?? ''));
        if ($pid === '') continue;
        $tasks = ps_tasks($pid);
        $changed = false;
        foreach ($tasks as $i => $t) {
            $rep = ps_repeat_normalize($t['repeat'] ?? []);
            $freq = (string) $rep['freq'];
            if ($freq === 'none' || !empty($rep['ended'])) continue;
            if ((string) ($t['status'] ?? '') !== 'done') continue;   // 只有完成的实例才推进序列
            $dueFull = (string) ($t['due'] ?? '');
            $base = substr($dueFull, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $base) !== 1) $base = $today;
            $next = ps_repeat_next_date($rep, $base);
            if ($next === null) {
                $rep['ended'] = true;            // 已过 until：标记结束，不再生成
                $tasks[$i]['repeat'] = $rep;
                $changed = true;
                continue;
            }
            if ((string) ($rep['spawned'] ?? '') === $next) continue;   // 幂等
            $timePart = substr($dueFull, 10);                            // 保留 HH:MM
            $start = (string) ($t['start'] ?? '');
            $offset = 0;
            if ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($start, 0, 10)) === 1) {
                $offset = (int) round(((int) strtotime($base . ' 12:00') - (int) strtotime(substr($start, 0, 10) . ' 12:00')) / 86400);
            }
            $newId = 't' . date('ymdHis') . substr((string) random_int(100, 999), 0, 3);
            $copy = $t;
            unset($copy['comments'], $copy['reminded'], $copy['history']);
            $copy['id'] = $newId;
            $copy['title'] = (string) ($t['title'] ?? '');
            $copy['status'] = 'todo';
            $copy['due'] = $next . $timePart;
            $copy['start'] = $start !== '' ? ps_repeat_shift($next, -$offset) : '';
            $copy['repeat'] = array_diff_key($rep, ['spawned' => 1]);    // 新实例继续沿用规则
            $copy['repeat_src'] = (string) (($t['repeat_src'] ?? '') !== '' ? $t['repeat_src'] : ($t['id'] ?? ''));
            $copy['repeat_from'] = (string) ($t['id'] ?? '');
            $copy['created_at'] = date('c');
            $copy['updated_at'] = date('c');
            $tasks[] = $copy;
            $rep['spawned'] = $next;             // 已完成实例：记住这一次，不会重复生成
            $tasks[$i]['repeat'] = $rep;
            $changed = true;
            $out[] = ['project' => $pid, 'from' => (string) ($t['id'] ?? ''), 'new' => $newId, 'title' => (string) $copy['title'], 'due' => $next];
        }
        if ($changed) {
            json_write(ps_project_file($pid), ['tasks' => $tasks]);
            ps_touch_project($pid);
        }
    }
    return $out;
}

/* ────────────── 任务评论与 @提及 ────────────── */
/**
 * 【为什么】任务只能改状态、不能讨论，团队协作就还是「派活」而不是「一起干」。
 * 评论存在任务里（同一个 JSON），@提及 解析成登录名后定向通知到人 + 邮件。
 */

function ps_comment_roles_ok(): array
{
    return ['owner' => '负责人', 'editor' => '可编辑', 'viewer' => '只读'];
}

/** 一条任务的评论（按时间正序） */
function ps_task_comments(string $projectId, string $taskId): array
{
    $p = ps_project_get($projectId);
    if ($p === null) return [];
    foreach ((array) $p['tasks'] as $t) {
        if ((string) ($t['id'] ?? '') !== $taskId) continue;
        $out = array_values(array_filter((array) ($t['comments'] ?? []), 'is_array'));
        usort($out, static fn(array $a, array $b): int => strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? '')));
        return $out;
    }
    return [];
}

/** 评论文本 → id 列表：匹配 @登录名 或 @显示名（整词，不被后续字母数字粘连） */
function ps_comment_mentions(string $text): array
{
    $out = [];
    foreach (ps_user_options() as $uk => $disp) {
        foreach (array_unique([(string) $uk, (string) $disp]) as $name) {
            if ($name === '') continue;
            $re = '/@' . preg_quote($name, '/') . '(?![\p{L}\p{N}_])/iu';
            if (preg_match($re, $text) === 1) { $out[] = (string) $uk; break; }
        }
    }
    return array_values(array_unique($out));
}

/** 评论渲染：转义 + 高亮 @提及（XSS 安全：先 htmlspecialchars） */
function ps_comment_html(string $text): string
{
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $names = [];
    foreach (ps_user_options() as $uk => $disp) {
        foreach (array_unique([(string) $uk, (string) $disp]) as $name) {
            if ($name !== '') $names[] = (string) $name;
        }
    }
    usort($names, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));   // 长名优先，避免截胡
    foreach ($names as $name) {
        $safe = preg_replace(
            '/' . preg_quote('@' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), '/') . '(?![\p{L}\p{N}_])/iu',
            '<span class="cmt-at">@' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>',
            $safe
        ) ?? $safe;
    }
    return nl2br($safe, false);
}

/** 新增评论：校验 + 落库 + 返回被提及的人 */
function ps_task_comment_add(string $projectId, string $taskId, string $text, string $by = ''): array
{
    $text = trim($text);
    if ($text === '') return ['ok' => false, 'error' => '评论不能为空', 'comment' => [], 'mentioned' => []];
    if (mb_strlen($text) > 2000) return ['ok' => false, 'error' => '评论太长（最多 2000 字）', 'comment' => [], 'mentioned' => []];
    $p = ps_project_get($projectId);
    if ($p === null) return ['ok' => false, 'error' => '项目不存在', 'comment' => [], 'mentioned' => []];
    if ($by === '') $by = ps_current_user();
    $mentions = array_values(array_filter(ps_comment_mentions($text), static fn(string $u): bool => $u !== $by));
    $cmt = [
        'id' => 'c' . date('ymdHis') . substr((string) random_int(100, 999), 0, 3),
        'by' => $by,
        'at' => date('Y-m-d H:i:s'),
        'text' => $text,
        'mentions' => $mentions,
    ];
    $tasks = $p['tasks'];
    $hit = false;
    foreach ($tasks as $i => $t) {
        if ((string) ($t['id'] ?? '') !== $taskId) continue;
        $list = (array) ($t['comments'] ?? []);
        $list[] = $cmt;
        if (count($list) > 200) $list = array_slice($list, -200);   // 单任务最多留 200 条
        $tasks[$i]['comments'] = array_values($list);
        $tasks[$i]['updated_at'] = date('c');
        $hit = true;
        break;
    }
    if (!$hit) return ['ok' => false, 'error' => '任务不存在', 'comment' => [], 'mentioned' => []];
    json_write(ps_project_file($projectId), ['tasks' => $tasks]);
    ps_touch_project($projectId);
    return ['ok' => true, 'error' => '', 'comment' => $cmt, 'mentioned' => $mentions];
}

/** 删评论：作者本人或站点管理员 */
function ps_task_comment_delete(string $projectId, string $taskId, string $commentId, string $by = '', bool $isAdmin = false): bool
{
    $p = ps_project_get($projectId);
    if ($p === null) return false;
    if ($by === '') $by = ps_current_user();
    $tasks = $p['tasks'];
    $ok = false;
    foreach ($tasks as $i => $t) {
        if ((string) ($t['id'] ?? '') !== $taskId) continue;
        $list = (array) ($t['comments'] ?? []);
        foreach ($list as $j => $c) {
            if ((string) ($c['id'] ?? '') !== $commentId) continue;
            if (!$isAdmin && (string) ($c['by'] ?? '') !== $by) return false;
            unset($list[$j]);
            $ok = true;
            break;
        }
        if (!$ok) return false;
        $tasks[$i]['comments'] = array_values($list);
        $tasks[$i]['updated_at'] = date('c');
        break;
    }
    if (!$ok) return false;
    json_write(ps_project_file($projectId), ['tasks' => $tasks]);
    ps_touch_project($projectId);
    return true;
}

/**
 * 评论通知：被 @ 的人在后台收到定向通知（audience=user:<登录名>），
 * 有邮箱且邮件渠道可用时再发一封邮件。返回实际通知到的人。
 * notify()/mail_send() 不存在时静默降级（CLI/测试环境）。
 */
function ps_comment_notify(string $projectId, array $task, array $comment): array
{
    $notified = [];
    $mentions = (array) ($comment['mentions'] ?? []);
    if ($mentions === []) return $notified;
    $p = ps_project_get($projectId);
    $projectName = (string) ($p['name'] ?? $projectId);
    $by = (string) ($comment['by'] ?? '');
    $title = '@' . ps_user_display($by) . ' 在任务里提到了你';
    $body = mb_substr((string) ($comment['text'] ?? ''), 0, 200);
    $link = '/xmp/today?view=team&project=' . urlencode($projectId);
    foreach ($mentions as $u) {
        $u = (string) $u;
        if ($u === '' || $u === $by) continue;
        if (function_exists('notify')) {
            notify('team', $title, "【{$projectName}】" . (string) ($task['title'] ?? '') . "\n" . $body, $link, ['user:' . $u]);
        }
        if (function_exists('mail_send')) {
            $em = ps_assignee_email($u);
            if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) !== false) {
                try {
                    mail_send($em, $title, '<p><b>' . htmlspecialchars($title) . '</b></p><p>任务：' . htmlspecialchars((string) ($task['title'] ?? '')) . '</p><p>' . nl2br(htmlspecialchars($body)) . '</p><p><a href="' . htmlspecialchars('https://nownexts.com' . $link) . '">打开团队视角</a></p>');
                } catch (\Throwable $e) {}
            }
        }
        $notified[] = $u;
    }
    return $notified;
}

/* ────────────── 项目级成员权限（owner / editor / viewer） ────────────── */
/**
 * 【为什么】此前只有站点级 tasks 权限：要么全站能改，要么全站不能。
 * 多个人一起用就会撞——「这个项目谁能改」是项目自己的事。
 *
 * 兼容策略：**没设成员的项目视为公共**（凡有 tasks 权限的人都能改）——
 * 这样老项目不会被权限上线瞬间锁死；一旦设了成员，就按成员角色来。
 */

function ps_project_roles(): array
{
    return ['owner' => '负责人', 'editor' => '可编辑', 'viewer' => '只读'];
}

/** 成员规范化：兼容旧的字符串列表（旧数据里的成员按「可编辑」理解） */
function ps_members_normalize(mixed $v): array
{
    if (!is_array($v)) return [];
    $out = [];
    foreach ($v as $k => $val) {
        if (is_int($k)) {                       // 旧格式：['Seven', 'marketing']
            $user = trim((string) $val);
            $role = 'editor';
        } else {                                // 新格式：['Seven' => 'owner']
            $user = trim((string) $k);
            $role = trim((string) $val);
        }
        if ($user === '') continue;
        if (!isset(ps_project_roles()[$role])) $role = 'viewer';   // 角色不认就按最小权限
        $out[$user] = $role;
    }
    return $out;
}

function ps_project_members(string $projectId): array
{
    $p = ps_project_get($projectId);
    return $p === null ? [] : ps_members_normalize($p['members'] ?? []);
}

/** 当前登录用户（后台用登录名，前后台一致口径） */
function ps_current_user(): string
{
    $u = $_SESSION['admin_user'] ?? '';
    if ($u === '' && function_exists('member_current')) {
        $u = (string) ((member_current()['name'] ?? '') ?: '');
    }
    return trim((string) $u);
}

function ps_is_site_admin(): bool
{
    return (string) ($_SESSION['admin_role'] ?? '') === 'admin';
}

function ps_project_role(string $projectId, string $user = ''): string
{
    if ($user === '') $user = ps_current_user();
    if ($user === '') return '';
    $members = ps_project_members($projectId);
    return (string) ($members[$user] ?? '');
}

/** 可选成员：登录名 => 显示名（成员用登录名做主键，展示用显示名） */
function ps_user_options(): array
{
    $out = [];
    foreach ((array) json_read(DATA_DIR . '/users.json') as $uk => $u) {
        if (!is_array($u)) continue;
        $out[(string) $uk] = (string) ($u['name'] ?? $uk);
    }
    return $out;
}

function ps_user_display(string $user): string
{
    $o = ps_user_options();
    return (string) ($o[$user] ?? $user);
}

function ps_member_set(string $projectId, string $user, string $role): array
{
    $user = trim($user);
    if ($user === '') return ['ok' => false, 'error' => '成员不能为空'];
    if (!isset(ps_project_roles()[$role])) return ['ok' => false, 'error' => '角色不合法'];
    $p = ps_project_get($projectId);
    if ($p === null) return ['ok' => false, 'error' => '项目不存在'];
    $members = ps_project_members($projectId);
    $members[$user] = $role;
    $r = ps_project_save(['id' => $projectId, 'name' => (string) ($p['name'] ?? $projectId), 'desc' => (string) ($p['desc'] ?? ''), 'members' => $members]);
    return $r['ok'] ? ['ok' => true, 'error' => '', 'members' => $members] : ['ok' => false, 'error' => (string) $r['error']];
}

function ps_member_remove(string $projectId, string $user): bool
{
    $members = ps_project_members($projectId);
    if (!isset($members[$user])) return false;
    unset($members[$user]);
    $p = ps_project_get($projectId);
    if ($p === null) return false;
    $r = ps_project_save(['id' => $projectId, 'name' => (string) ($p['name'] ?? $projectId), 'desc' => (string) ($p['desc'] ?? ''), 'members' => $members]);
    return (bool) ($r['ok'] ?? false);
}

/**
 * 能不能对这个项目做这件事。
 * @param string $action view|edit|manage（manage = 改项目信息 / 管成员 / 删项目）
 * @param string $user 空=当前用户
 * @param bool $siteAdmin 站点管理员（调用方传，便于测试）
 */
function ps_can(string $projectId, string $action, string $user = '', bool $siteAdmin = false): bool
{
    $p = ps_project_get($projectId);
    if ($p === null) return false;
    if ($siteAdmin) return true;
    if (($action !== 'view' && $action !== 'edit' && $action !== 'manage')) return false;
    $members = ps_members_normalize($p['members'] ?? []);
    $role = ps_project_role($projectId, $user);
    if ($members === []) {
        // 公共项目：能看能改（与权限上线前的行为一致），但管成员/删项目还是只有管理员
        return $action !== 'manage';
    }
    return match ($role) {
        'owner' => true,
        'editor' => $action === 'view' || $action === 'edit',
        'viewer' => $action === 'view',
        default => false,
    };
}

/** 我能看的项目（非成员的项目在列表里就不该出现） */
function ps_visible_projects(string $user = '', bool $siteAdmin = false): array
{
    $out = [];
    foreach (ps_projects() as $p) {
        $pid = ps_safe_id((string) ($p['id'] ?? ''));
        if ($pid !== '' && ps_can($pid, 'view', $user, $siteAdmin)) $out[] = $p;
    }
    return $out;
}

/* ────────────── 到期分桶（页内角标 / 待办聚合，不依赖外部渠道） ────────────── */

/**
 * 按到期时间分桶：逾期 / 今天 / 近 N 天。已完成跳过。
 * 与 ps_due_reminders 的区别：这里**不考虑是否已提醒过**——角标要一直显示，
 * 提醒只发一次是另一件事。
 *
 * @return array{overdue:list<array>,today:list<array>,soon:list<array>,counts:array{overdue:int,today:int,soon:int}}
 */
function ps_due_buckets(string $projectId = '', string $now = '', int $soonDays = 7): array
{
    $ts = $now !== '' ? (strtotime($now) ?: time()) : time();
    $today = date('Y-m-d', $ts);
    $limit = date('Y-m-d', $ts + $soonDays * 86400);
    $out = ['overdue' => [], 'today' => [], 'soon' => []];
    $projects = $projectId !== '' ? [['id' => $projectId, 'name' => (string) ((ps_project_get($projectId)['name'] ?? $projectId))]] : ps_projects(true);
    foreach ($projects as $p) {
        $pid = ps_safe_id((string) ($p['id'] ?? ''));
        if ($pid === '') continue;
        foreach (ps_tasks($pid) as $t) {
            if ((string) ($t['status'] ?? 'todo') === 'done') continue;
            $due = (string) ($t['due'] ?? '');
            if ($due === '') continue;
            $d = substr($due, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) !== 1) continue;
            $item = ['project' => $pid, 'project_name' => (string) ($p['name'] ?? $pid), 'task' => $t];
            if ($d < $today) $out['overdue'][] = $item;
            elseif ($d === $today) $out['today'][] = $item;
            elseif ($d <= $limit) $out['soon'][] = $item;
        }
    }
    usort($out['overdue'], static fn(array $a, array $b): int => strcmp((string) ($a['task']['due'] ?? ''), (string) ($b['task']['due'] ?? '')));
    usort($out['soon'], static fn(array $a, array $b): int => strcmp((string) ($a['task']['due'] ?? ''), (string) ($b['task']['due'] ?? '')));
    return $out + ['counts' => ['overdue' => count($out['overdue']), 'today' => count($out['today']), 'soon' => count($out['soon'])]];
}

/** 负责人邮箱：按显示名或登录名在 users.json 里找 */
function ps_assignee_email(string $assignee): string
{
    $needle = strtolower(trim($assignee));
    if ($needle === '') return '';
    foreach ((array) json_read(DATA_DIR . '/users.json') as $uk => $u) {
        if (!is_array($u)) continue;
        $name = strtolower(trim((string) ($u['name'] ?? '')));
        if ($name === $needle || strtolower(trim((string) $uk)) === $needle) {
            return trim((string) ($u['email'] ?? ''));
        }
    }
    return '';
}

/**
 * 一条任务该发给谁：负责人邮箱优先；另外始终带上配置里的备用收件人
 * （负责人没填邮箱时至少还有人收得到，避免提醒悄悄消失）。
 */
function ps_reminder_recipients(array $task): array
{
    $out = [];
    $who = trim((string) ($task['assignee'] ?? ''));
    if ($who !== '') {
        $em = ps_assignee_email($who);
        if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) !== false) $out[] = $em;
    }
    $extra = '';
    if (function_exists('notify_channels')) {
        $extra = (string) (notify_channels()['task_email']['to'] ?? '');
    }
    foreach (preg_split('/[,;\s]+/', $extra) ?: [] as $em) {
        $em = trim((string) $em);
        if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) !== false && !in_array($em, $out, true)) $out[] = $em;
    }
    return $out;
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

/**
 * 扫描到期任务并逐个通知（编排层，便于测试与复用）
 *
 * @param callable $notify 接收一条提醒数组；返回 false 表示未送达 → 不写幂等键，下次重试
 * @return array 已送达并落键的提醒列表
 */
function ps_notify_due_reminders(callable $notify, string $now = '', int $withinHours = 24): array
{
    $out = [];
    foreach (ps_due_reminders($now, $withinHours) as $r) {
        $task = (array) ($r['task'] ?? []);
        if ($notify($r) === false) continue;
        ps_mark_reminded((string) $r['project'], (string) ($task['id'] ?? ''), (string) $r['key']);
        $out[] = ['project' => (string) $r['project'], 'id' => (string) ($task['id'] ?? ''), 'kind' => (string) $r['kind'], 'key' => (string) $r['key']];
    }
    return $out;
}

/** 提醒文案（cron 与站内共用同一份，避免两处措辞漂移） */
function ps_reminder_text(array $r): array
{
    $t = (array) ($r['task'] ?? []);
    $p = ps_project_get((string) ($r['project'] ?? ''));
    $kind = (string) ($r['kind'] ?? 'due_soon');
    $lines = [(string) ($t['title'] ?? '')];
    $lines[] = '项目：' . (string) ($p['name'] ?? ($r['project'] ?? ''));
    if ((string) ($t['due'] ?? '') !== '') $lines[] = '截止：' . (string) $t['due'];
    if ((string) ($t['assignee'] ?? '') !== '') $lines[] = '负责人：' . (string) $t['assignee'];
    $refInfo = ps_ref_resolve((array) ($t['ref'] ?? []));
    if ($refInfo['type'] !== '' && $refInfo['label'] !== '') {
        $prefix = ($refInfo['type_label'] !== '' && !str_contains($refInfo['label'], $refInfo['type_label'])) ? $refInfo['type_label'] . ' · ' : '';
        $lines[] = '关联：' . $prefix . $refInfo['label'];
    }
    return ['title' => $kind === 'overdue' ? '⏰ 任务已逾期' : '🔔 任务即将到期', 'body' => implode("\n", $lines)];
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
