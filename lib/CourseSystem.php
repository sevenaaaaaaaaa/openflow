<?php
/**
 * CourseSystem — 课程领域层（类型单一真源 / 数据分析 / 先修与学习路径）
 *
 * 补课程体系的深度：类型不再三处各写一份；提供完课率/学员/营收/课时流失分析；
 * 支持先修课程（学习路径），让课程之间成为有序体系而非一堆孤立单课。
 */

/** 课程类型唯一真源（编辑/筛选/讲师提交共用） */
function course_types(): array {
    return ['课程' => '单课', '专栏' => '专栏', '认证课' => '认证课', '系列课' => '系列课', '直播课' => '直播课'];
}
function course_type_label(string $t): string { return course_types()[$t] ?? $t; }

function course_find(string $id): ?array {
    foreach (json_read(DATA_DIR . '/courses/index.json') as $c) if (($c['id'] ?? '') === $id) return $c;
    return null;
}

/** 先修课程 id 列表 */
function course_prereq(array $course): array {
    $p = $course['prerequisites'] ?? [];
    if (is_string($p)) $p = array_filter(array_map('trim', explode(',', $p)));
    return array_values(array_filter(array_map('strval', (array)$p)));
}

/** 某人缺少哪些先修（返回未完成的先修课程 id + 标题） */
function course_prereq_missing(string $memberId, array $course): array {
    if (!function_exists('progress_summary')) require_once __DIR__ . '/ProgressSystem.php';
    $missing = [];
    foreach (course_prereq($course) as $pid) {
        $pc = course_find($pid);
        if (!$pc) continue;
        $sum = progress_summary($memberId, $pid, $pc);
        if ($sum['total'] === 0 || $sum['done'] < $sum['total']) $missing[] = ['id' => $pid, 'title' => (string)($pc['title'] ?? $pid)];
    }
    return $missing;
}

/**
 * 单课程数据分析：学员/营收/完课率/平均进度/课时流失。
 */
function course_analytics(string $courseId): array {
    $course = course_find($courseId);
    if (!$course) return [];
    if (!function_exists('progress_all')) require_once __DIR__ . '/ProgressSystem.php';
    $revenue = 0.0; $orders = 0; $buyers = [];
    try {
        if (!function_exists('shop_all_orders')) require_once __DIR__ . '/ShopSystem.php';
        foreach (shop_all_orders() as $o) {
            if (($o['course_id'] ?? '') !== $courseId || ($o['status'] ?? '') !== 'paid') continue;
            $revenue += (float)($o['amount'] ?? 0); $orders++;
            $buyers[(string)($o['member_id'] ?? '')] = true;
        }
    } catch (\Throwable $e) {}

    // 进度：谁学过、谁完成
    $total = 0; foreach (($course['chapters'] ?? []) as $ch) $total += count($ch['lessons'] ?? []);
    $learners = 0; $completed = 0; $sumPct = 0;
    $lessonDone = [];
    $all = progress_all();
    foreach ($all as $mid => $courses) {
        if (!isset($courses[$courseId])) continue;
        $learners++;
        $done = 0;
        foreach ($courses[$courseId] as $lid => $st) { if (!empty($st['done'])) { $done++; $lessonDone[$lid] = ($lessonDone[$lid] ?? 0) + 1; } }
        $sumPct += $total > 0 ? $done / $total * 100 : 0;
        if ($total > 0 && $done >= $total) $completed++;
    }
    // 课时流失：按章节顺序列出完成人数
    $lessons = [];
    foreach (($course['chapters'] ?? []) as $ch) foreach (($ch['lessons'] ?? []) as $l) $lessons[] = ['id' => $l['id'], 'title' => $l['title'] ?? '', 'done' => (int)($lessonDone[$l['id']] ?? 0)];
    return [
        'course_id' => $courseId, 'title' => (string)($course['title'] ?? ''),
        'revenue' => round($revenue, 2), 'orders' => $orders, 'buyers' => count($buyers),
        'learners' => $learners, 'completed' => $completed,
        'completion_rate' => $learners > 0 ? round($completed / $learners * 100, 1) : 0,
        'avg_progress' => $learners > 0 ? round($sumPct / $learners, 1) : 0,
        'rating' => (float)($course['rating'] ?? 0), 'lessons_total' => $total,
        'lessons' => $lessons,
    ];
}

/** 全站课程分析汇总（按营收排序） */
function course_analytics_all(): array {
    $out = [];
    foreach (json_read(DATA_DIR . '/courses/index.json') as $c) $out[] = course_analytics((string)($c['id'] ?? ''));
    usort($out, fn($a, $b) => ($b['revenue'] ?? 0) <=> ($a['revenue'] ?? 0));
    return $out;
}
