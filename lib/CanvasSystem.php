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
            // 条件节点：根据条件决定走哪条边
            $ok = canvas_eval_condition($node, $context);
            foreach ($edges as $e) {
                if ($e['from'] === $nodeId && ($e['condition'] ?? 'true') === ($ok ? 'true' : 'false')) {
                    canvas_walk($e['to'], $byId, $edges, $context, $visited, $depth + 1);
                }
            }
            return;
        case 'notify':
            notify('画布流程', $node['title'] ?? '流程通知', $context['email'] ?? '', $node['link'] ?? '');
            break;
    }

    // 找下一个节点（普通顺序）
    foreach ($edges as $e) {
        if ($e['from'] === $nodeId && empty($e['condition'])) {
            canvas_walk($e['to'], $byId, $edges, $context, $visited, $depth + 1);
        }
    }
}

// 条件评估（简化：基于上下文字段）
function canvas_eval_condition(array $node, array $context): bool {
    $field = $node['field'] ?? '';
    $op = $node['op'] ?? 'eq';
    $value = $node['value'] ?? '';
    $actual = $context[$field] ?? '';
    switch ($op) {
        case 'eq': return $actual == $value;
        case 'neq': return $actual != $value;
        case 'gt': return (int)$actual > (int)$value;
        case 'lt': return (int)$actual < (int)$value;
        case 'contains': return strpos($actual, $value) !== false;
        case 'empty': return empty($actual);
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
