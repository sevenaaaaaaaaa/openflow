<?php
/**
 * 画布流程引擎 — 可视化流程编排的执行端
 * 画布结构：nodes[]（触发器/动作/分支/延迟）+ edges[]（连线）
 * 支持：顺序、分支（条件）、并行
 *
 * ── 流程编排三件套：本文件是「可视化画布执行器」 ──
 * 关系：FlowSystem = 总事件总线（入口，接收事件并分发）；
 *       本文件 = 画布编排执行（用户拖拽配置的 nodes/edges 流程）；
 *       AutomationSystem = 营销自动化执行器（触发器+动作）。
 * 加代码指引：画布的节点类型、连线逻辑、执行顺序加这里，
 *             不要在这里加全局事件分发（归 FlowSystem）。
 */

function canvas_file(): string { return DATA_DIR . '/canvas-flows.json'; }
function canvas_get(): array { return json_read(canvas_file()); }
function canvas_save(array $flows): bool { return json_write(canvas_file(), $flows); }

// 从画布执行一个流程
function canvas_execute(array $flow, array $context): void {
    $nodes = $flow['nodes'] ?? [];
    $edges = $flow['edges'] ?? [];
    $byId = [];
    foreach ($nodes as $n) $byId[$n['id']] = $n;

    // 找到入口节点（触发器）
    $start = null;
    foreach ($nodes as $n) if (($n['type'] ?? '') === 'trigger') { $start = $n; break; }
    if (!$start) return;

    // 简单遍历：从起点沿边推进（支持条件分支）
    canvas_walk($start['id'], $byId, $edges, $context, [], 0);
}

function canvas_walk(string $nodeId, array $byId, array $edges, array $context, array $visited, int $depth): void {
    if ($depth > 20) return; // 防止死循环
    if (isset($visited[$nodeId])) return;
    $visited[$nodeId] = true;
    $node = $byId[$nodeId] ?? null;
    if (!$node) return;

    switch ($node['type']) {
        case 'trigger':
            break; // 触发器本身不做事
        case 'send_email':
            canvas_send_email($node, $context);
            break;
        case 'delay':
            canvas_schedule_delay($node, $context, $depth);
            break;
        case 'condition':
            // 条件节点：根据条件决定走哪条边（支持多条件 rules[] AND/OR）
            $ok = canvas_eval_condition($node, $context);
            foreach ($edges as $e) {
                if ($e['from'] === $nodeId && ($e['condition'] ?? 'true') === ($ok ? 'true' : 'false')) {
                    canvas_walk($e['to'], $byId, $edges, $context, $visited, $depth + 1);
                }
            }
            return;
        case 'split':
            // A/B 分流 + 灰度：按 rollout 比例放量，未放量走 default；放量内按 variant_a 比例分 A/B
            $path = canvas_split_next($node, $edges, $nodeId, $context);
            if ($path) canvas_walk($path, $byId, $edges, $context, $visited, $depth + 1);
            return;
        case 'notify':
            notify('画布流程', $node['title'] ?? '流程通知', $context['email'] ?? '', $node['link'] ?? '');
            break;
        case 'tag':      canvas_action_tag($node, $context); break;
        case 'score':    canvas_action_score($node, $context); break;
        case 'stage':    canvas_action_stage($node, $context); break;
        case 'webhook':  canvas_action_webhook($node, $context); break;
        case 'connection': canvas_action_connection($node, $context); break;
    }

    // 找下一个节点（普通顺序）
    foreach ($edges as $e) {
        if ($e['from'] === $nodeId && empty($e['condition'])) {
            canvas_walk($e['to'], $byId, $edges, $context, $visited, $depth + 1);
        }
    }
}

/**
 * 画布动作类型清单（UI 下拉/按钮的单一出处）。BACKLOG T1-2。
 * 这也是"大脑可调用的动作库"：每个 key 是 Agent 将来能直接调的一个动作。
 */
function canvas_action_types(): array {
    return [
        'send_email' => ['icon' => '📧', 'label' => '发送邮件'],
        'tag'        => ['icon' => '🏷', 'label' => '打标签'],
        'score'      => ['icon' => '⭐', 'label' => '加分'],
        'stage'      => ['icon' => '📊', 'label' => '改 CRM 阶段'],
        'webhook'    => ['icon' => '🔗', 'label' => 'Webhook'],
        'connection' => ['icon' => '🔌', 'label' => '连接动作'],
        'notify'     => ['icon' => '📢', 'label' => '通知'],
        'delay'      => ['icon' => '⏱', 'label' => '延迟'],
        'condition'  => ['icon' => '🔀', 'label' => '条件分支'],
        'split'      => ['icon' => '🧪', 'label' => 'A/B 分流'],
    ];
}

/** 从上下文解析 CDP 客户 id（email/member/uid）。 */
function canvas_ctx_customer_id(array $context): string {
    if (!function_exists('cdp_find')) return '';
    $c = cdp_find((string)($context['email'] ?? ''), (string)($context['member_id'] ?? ''), (string)($context['uid'] ?? ''));
    return $c['id'] ?? '';
}

/**
 * A/B 分流 + 灰度决策：返回下一个要走的节点 id（按边上的 variant/condition）。
 * 逻辑：
 *   - rollout_percent：整体放量比例（灰度）。0-100；context 里没有稳定 key 则比 hash。
 *   - variant_a_percent：在放量人群中，A 分支占比。
 *   未放量者走 variant='default' 的边；A 走 variant='a'，B 走 variant='b'。
 */
function canvas_split_next(array $node, array $edges, string $nodeId, array $context): ?string {
    $rollout = max(0, min(100, (int)($node['rollout_percent'] ?? 100)));
    $aPct = max(0, min(100, (int)($node['variant_a_percent'] ?? 50)));
    // 稳定 key：优先客户 id/邮箱/member/uid，否则随机（无稳定身份时不重复分流）
    $key = (string)($context['email'] ?? $context['member_id'] ?? $context['uid'] ?? $context['customer_id'] ?? '');
    $rolloutBucket = $key !== '' ? abs(crc32($key)) % 1000 : mt_rand(0, 999);
    $path = null;
    if ($rolloutBucket < $rollout * 10) {
        // 进入放量人群：A/B 用另一维度哈希，避免与放量判断同源（否则<rollout全落A）
        $abBucket = $key !== '' ? abs(crc32($key . ':ab')) % 1000 : mt_rand(0, 999);
        $path = $abBucket < $aPct * 10 ? 'a' : 'b';
    } else {
        $path = 'default';
    }
    // 找匹配的边；找不到则回退到默认顺序
    foreach ($edges as $e) {
        if ($e['from'] === $nodeId && ($e['variant'] ?? '') === $path) return $e['to'];
    }
    foreach ($edges as $e) {
        if ($e['from'] === $nodeId && empty($e['variant']) && empty($e['condition'])) return $e['to'];
    }
    return null;
}

/** 动作：打标签（CDP + 尽力同步 CRM 线索）。 */
function canvas_action_tag(array $node, array $context): void {
    $tag = trim((string)($node['tag'] ?? $node['value'] ?? ''));
    if ($tag === '') return;
    try {
        $cid = canvas_ctx_customer_id($context);
        if ($cid && function_exists('cdp_add_tag')) cdp_add_tag($cid, $tag);
        $email = (string)($context['email'] ?? '');
        if ($email !== '' && function_exists('crm_update_lead') && function_exists('crm_ensure_lead')) {
            $lead = crm_ensure_lead($email);
            $tags = array_values(array_unique(array_merge((array)($lead['tags'] ?? []), [$tag])));
            crm_update_lead($email, ['tags' => $tags]);
        }
    } catch (\Throwable $e) {}
}

/** 动作：加分（CDP score 累加）。 */
function canvas_action_score(array $node, array $context): void {
    $delta = (int)($node['score'] ?? $node['value'] ?? 0);
    if ($delta === 0) return;
    try {
        $cid = canvas_ctx_customer_id($context);
        if ($cid && function_exists('cdp_get_by_id') && function_exists('cdp_set_score')) {
            $cur = (int)((cdp_get_by_id($cid)['score'] ?? 0));
            cdp_set_score($cid, $cur + $delta);
        }
    } catch (\Throwable $e) {}
}

/** 动作：改 CRM 阶段。 */
function canvas_action_stage(array $node, array $context): void {
    $stage = trim((string)($node['stage'] ?? $node['value'] ?? ''));
    $email = (string)($context['email'] ?? '');
    if ($stage === '' || $email === '') return;
    try {
        if (function_exists('crm_ensure_lead') && function_exists('crm_update_lead')) {
            crm_ensure_lead($email);
            crm_update_lead($email, ['stage' => $stage]);
        }
    } catch (\Throwable $e) {}
}

/** 动作：Webhook（把上下文 POST 出去）。 */
/** 开放能力：走连接动作（鉴权、SSRF 防护、留痕都在连接层，这里只是转交） */
function canvas_action_connection(array $node, array $context): void {
    $aid = trim((string)($node['action_id'] ?? $node['value'] ?? ''));
    if ($aid === '') return;
    require_once __DIR__ . '/ConnectionActions.php';
    action_run_safe($aid, $context);
}

function canvas_action_webhook(array $node, array $context): void {
    $url = trim((string)($node['url'] ?? $node['value'] ?? ''));
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return;
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($context, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        curl_exec($ch); curl_close($ch);
    } catch (\Throwable $e) {}
}

/**
 * 条件节点可选字段清单（画布 UI 的下拉数据源，单一出处）
 *
 * 键为存进 node['field'] 的值，值为中文显示名。
 * crm.* 前缀的字段在评估时实时回查 CRM，而不是读事件上下文。
 */
function canvas_condition_fields(): array {
    return [
        '事件上下文' => [
            'email'      => '邮箱',
            'form_type'  => '表单类型',
            'score'      => '事件评分',
            'label'      => '事件标签',
            'page'       => '页面路径',
        ],
        'CRM 线索' => [
            'crm.stage'           => '线索阶段',
            'crm.score'           => '线索评分',
            'crm.owner'           => '负责销售',
            'crm.value'           => '预计金额',
            'crm.source'          => '线索来源',
            'crm.followup_count'  => '跟进次数',
            'crm.days_since_followup' => '距上次跟进天数',
            'crm.tags'            => '线索标签',
            'crm.exists'          => '是否已是线索',
        ],
    ];
}

/**
 * 解析条件字段的实际值。
 *
 * crm.* 走 CRM 实时数据（按 context['email'] 关联），其余读事件上下文。
 * CRM 不可用或线索不存在时统一返回 ''，让条件走「为空」分支而不是报错。
 *
 * @return string|int|float
 */
function canvas_resolve_field(string $field, array $context) {
    if (strpos($field, 'crm.') !== 0) {
        return $context[$field] ?? '';
    }
    $email = trim((string)($context['email'] ?? ''));
    if ($email === '' || !function_exists('crm_get')) return '';

    $lead = null;
    try {
        $data = crm_get();
        $lead = $data['leads'][mb_strtolower($email)] ?? null;
    } catch (\Throwable $e) { return ''; }

    $key = substr($field, 4);
    if ($key === 'exists') return $lead ? '1' : '';
    if (!$lead) return '';

    switch ($key) {
        case 'stage':  return (string)($lead['stage'] ?? '');
        case 'score':  return (int)($lead['score'] ?? 0);
        case 'owner':  return (string)($lead['owner'] ?? '');
        case 'value':  return (float)($lead['value'] ?? 0);
        case 'source': return (string)($lead['source'] ?? '');
        case 'tags':   return implode(',', (array)($lead['tags'] ?? []));
        case 'followup_count': return count((array)($lead['follow_ups'] ?? []));
        case 'days_since_followup':
            $ups = (array)($lead['follow_ups'] ?? []);
            if (!$ups) return '';                       // 从未跟进 → 空，走「为空」分支
            $last = end($ups);
            $t = strtotime((string)($last['time'] ?? ''));
            return $t ? (int)floor((time() - $t) / 86400) : '';
        default: return (string)($lead[$key] ?? '');
    }
}

// 条件评估（事件上下文 + CRM 实时字段）
function canvas_eval_condition(array $node, array $context): bool {
    // 线B：多条件分支 —— 支持 rules[] (AND/OR)；保持单函数自含（测试从源码抽取此函数）
    if (isset($node['rules']) && is_array($node['rules']) && $node['rules']) {
        $operator = ($node['rules_operator'] ?? 'and') === 'or' ? 'or' : 'and';
        foreach ($node['rules'] as $r) {
            $ok = canvas_eval_single_cond($r['field'] ?? '', $r['op'] ?? 'eq', $r['value'] ?? '', $context);
            if ($operator === 'and' && !$ok) return false;
            if ($operator === 'or' && $ok) return true;
        }
        return $operator === 'and';
    }
    $field = $node['field'] ?? '';
    $op = $node['op'] ?? 'eq';
    $value = $node['value'] ?? '';
    $actual = canvas_resolve_field($field, $context);
    switch ($op) {
        case 'eq': return $actual == $value;
        case 'neq': return $actual != $value;
        case 'gt': return (float)$actual > (float)$value;
        case 'gte': return (float)$actual >= (float)$value;
        case 'lt': return (float)$actual < (float)$value;
        case 'lte': return (float)$actual <= (float)$value;
        case 'contains': return $value !== '' && strpos((string)$actual, (string)$value) !== false;
        case 'in':
            $list = array_map('trim', explode(',', (string)$value));
            return in_array((string)$actual, $list, true);
        case 'empty': return $actual === '' || $actual === null;
        case 'not_empty': return !($actual === '' || $actual === null);
        default: return true;
    }
}

// 单条件判定（多条件 rules 用；保持独立以便复用）
function canvas_eval_single_cond(string $field, string $op, string $value, array $context): bool {
    $actual = canvas_resolve_field($field, $context);
    switch ($op) {
        case 'eq': return $actual == $value;
        case 'neq': return $actual != $value;
        case 'gt': return (float)$actual > (float)$value;
        case 'gte': return (float)$actual >= (float)$value;
        case 'lt': return (float)$actual < (float)$value;
        case 'lte': return (float)$actual <= (float)$value;
        case 'contains': return $value !== '' && strpos((string)$actual, (string)$value) !== false;
        case 'in':
            $list = array_map('trim', explode(',', (string)$value));
            return in_array((string)$actual, $list, true);
        case 'empty': return $actual === '' || $actual === null;
        case 'not_empty': return !($actual === '' || $actual === null);
        default: return true;
    }
}

// 发送邮件（复用自动化逻辑）
function canvas_send_email(array $node, array $context): void {
    $email = $context['email'] ?? '';
    if (empty($email)) return;
    $subject = $node['subject'] ?? '来自 OpenFlow';
    $content = $node['content'] ?? '';
    foreach ($context as $k => $v) if (is_string($v)) $content = str_replace('{' . $k . '}', $v, $content);
    $bm = BillionMail::fromConfig();
    if ($bm) { $bm->send($email, $subject, $content); return; }
    $mautic = Mautic::fromConfig();
    if ($mautic && !empty($node['mautic_email_id'])) {
        $res = $mautic->createContact($email, ['email'=>$email]);
        if ($res && isset($res['contact']['id'])) $mautic->sendEmail((int)$node['mautic_email_id'], $res['contact']['id']);
    }
}

// 延迟动作入队
function canvas_schedule_delay(array $node, array $context, int $depth): void {
    $delayMin = (int)($node['delay_minutes'] ?? 60);
    $queue = json_read(DATA_DIR . '/automation-queue.json');
    $queue[] = [
        'id' => 'cq_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'flow_id' => 'canvas',
        'step' => ['action'=>'send_email', 'subject'=>$node['subject'] ?? '', 'content'=>$node['content'] ?? ''],
        'context' => $context,
        'run_at' => date('Y-m-d H:i:s', time() + $delayMin * 60),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_write(DATA_DIR . '/automation-queue.json', $queue);
}

// 触发画布流程（按触发器类型匹配）
function canvas_trigger(string $trigger, array $context): void {
    if (str_starts_with($trigger, 'flow_')) $trigger = substr($trigger, 5);
    foreach (canvas_get() as $flow) {
        if (($flow['enabled'] ?? false) !== true) continue;
        $start = null;
        foreach (($flow['nodes'] ?? []) as $n) if (($n['type'] ?? '') === 'trigger' && ($n['trigger'] ?? '') === $trigger) { $start = $n; break; }
        if (!$start) continue;
        // 表单过滤
        if ($trigger === 'form_submit' && !empty($start['form_slug']) && ($context['form_slug'] ?? '') !== $start['form_slug']) continue;
        if ($trigger === 'nps_submit') {
            $threshold = (int)($start['threshold'] ?? 7);
            if ((int)($context['score'] ?? 0) < $threshold) continue;
        }
        // 行为类触发：匹配字段/值
        if (in_array($trigger, ['page_view','article_view','element_click','download','purchase','course_complete','course_enroll','lesson_complete','role_selected','tool_use'], true)) {
            $mf = $start['match_field'] ?? '';
            $mv = trim($start['match_value'] ?? '');
            if ($mf && $mv) {
                $actual = $context[$mf] ?? '';
                if (is_array($actual)) $actual = implode(',', $actual);
                if (mb_strpos((string)$actual, $mv) === false) continue;
            }
        }
        canvas_execute($flow, $context);
    }
}
