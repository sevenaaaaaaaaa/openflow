<?php
/**
 * GrowthLearning — 结果回流学习（自生长的最小闭环）
 *
 * 让系统记住"哪类建议被采纳后真的有效/无效"，并把它作为下一轮提议的加权依据。
 * 这是 观察→计划→执行→评估→记忆→影响下次计划 里最后两环的落点。
 *
 * 记分（action 级，跨人聚合）：
 *   采纳并完成 done +1 · 判定有效 effective +2 · 忽略 dismissed -1 · 判定无效 ineffective -2
 * boost = clamp(score * 2, -15, +15)，加到提议优先级上。
 */

function growth_learning_file(): string { return DATA_DIR . '/growth/learning.json'; }

function growth_learning_data(): array {
    if (isset($GLOBALS['__gl_cache']) && is_array($GLOBALS['__gl_cache'])) return $GLOBALS['__gl_cache'];
    $d = json_read(growth_learning_file());
    $d['actions'] = is_array($d['actions'] ?? null) ? $d['actions'] : [];
    $d['modules'] = is_array($d['modules'] ?? null) ? $d['modules'] : [];
    return $GLOBALS['__gl_cache'] = $d;
}

function growth_learning_key(string $module, string $action): string {
    return mb_strtolower(trim($module) . '|' . trim($action));
}

/** 记录一条结果回执（verdict: done|dismissed|effective|ineffective） */
function growth_learning_verdict(string $module, string $action, string $verdict): void {
    if (!in_array($verdict, ['done', 'dismissed', 'effective', 'ineffective'], true)) return;
    $action = trim($action);
    if ($action === '') return;
    $d = growth_learning_data();
    $k = growth_learning_key($module, $action);
    $row = $d['actions'][$k] ?? ['module' => $module, 'action' => $action, 'done' => 0, 'dismissed' => 0, 'effective' => 0, 'ineffective' => 0, 'score' => 0];
    $row[$verdict] = (int)($row[$verdict] ?? 0) + 1;
    $row['score'] = (int)$row['done'] + (int)$row['effective'] * 2 - (int)$row['dismissed'] - (int)$row['ineffective'] * 2;
    $row['updated_at'] = date('Y-m-d H:i:s');
    $d['actions'][$k] = $row;

    // 模块级聚合
    $m = $d['modules'][$module] ?? ['done' => 0, 'dismissed' => 0, 'effective' => 0, 'ineffective' => 0, 'score' => 0];
    $m[$verdict] = (int)($m[$verdict] ?? 0) + 1;
    $m['score'] = (int)$m['done'] + (int)$m['effective'] * 2 - (int)$m['dismissed'] - (int)$m['ineffective'] * 2;
    $d['modules'][$module] = $m;

    $d['updated_at'] = date('Y-m-d H:i:s');
    json_write(growth_learning_file(), $d);
    $GLOBALS['__gl_cache'] = $d;
}

/** 该动作/模块的历史得分带来的优先级加权（-15..+15） */
function growth_learning_boost(string $module, string $action = ''): int {
    $d = growth_learning_data();
    $score = 0;
    if ($action !== '') {
        $k = growth_learning_key($module, $action);
        if (isset($d['actions'][$k])) $score = (int)($d['actions'][$k]['score'] ?? 0);
    } else {
        $score = (int)($d['modules'][$module]['score'] ?? 0);
    }
    return max(-15, min(15, $score * 2));
}

/** 给模型看的紧凑快照：哪些打法被验证有效/无效 */
function growth_learning_snapshot(int $limit = 8): array {
    $d = growth_learning_data();
    $rows = array_values($d['actions']);
    usort($rows, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
    $effective = []; $ineffective = [];
    foreach ($rows as $r) {
        if (($r['score'] ?? 0) > 0 && count($effective) < $limit) $effective[] = $r['action'];
        if (($r['score'] ?? 0) < 0 && count($ineffective) < $limit) $ineffective[] = $r['action'];
    }
    return ['effective' => $effective, 'ineffective' => $ineffective];
}
