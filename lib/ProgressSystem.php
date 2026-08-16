<?php
/**
 * 课程学习进度 — 章节打勾 / 断点续播 / 完成度
 * 数据：data/courses/progress.json  (member_id => course_id => lesson_id => state)
 */
require_once __DIR__ . '/../admin/config.php';

function progress_file(): string { return DATA_DIR . '/courses/progress.json'; }
function progress_all(): array { return json_read(progress_file()); }
function progress_save(array $p): void {
    if (!is_dir(dirname(progress_file()))) mkdir(dirname(progress_file()), 0755, true);
    json_write(progress_file(), $p);
}

// 获取某会员对某课程的全部进度
function progress_get(string $memberId, string $courseId): array {
    $all = progress_all();
    return $all[$memberId][$courseId] ?? [];
}
// 记录学习状态
function progress_set(string $memberId, string $courseId, string $lessonId, array $state): void {
    $all = progress_all();
    $all[$memberId][$courseId][$lessonId] = array_merge([
        'done' => false,       // 是否完成
        'position' => 0,       // 播放秒数
        'duration' => 0,       // 视频总长
        'last_at' => date('Y-m-d H:i:s'),
    ], $state);
    progress_save($all);
}
// 标记完成
function progress_done(string $memberId, string $courseId, string $lessonId): void {
    progress_set($memberId, $courseId, $lessonId, ['done' => true]);

    // 营销自动化：课程完成触发（整门课全部学完时触发 course_complete）
    try {
        $course = null;
        foreach (json_read(DATA_DIR . '/courses/index.json') as $c) if ($c['id'] === $courseId) { $course = $c; break; }
        if ($course) {
            $sum = progress_summary($memberId, $courseId, $course);
            if ($sum['total'] > 0 && $sum['done'] >= $sum['total'] && function_exists('flow_handle')) {
                flow_handle('course_complete', [
                    'member_id' => $memberId,
                    'course_id' => $courseId,
                    'course_title' => $course['title'] ?? '',
                    'label' => ($course['title'] ?? '') . ' 已学完',
                    'props' => ['percent' => 100, 'lessons' => $sum['total']],
                ]);
            }
        }
    } catch (Exception $e) {}
}
// 未完成
function progress_undone(string $memberId, string $courseId, string $lessonId): void {
    progress_set($memberId, $courseId, $lessonId, ['done' => false]);
}
// 续播：找上次未完成且位置>0 的节
function progress_resume(string $memberId, string $courseId, array $course): ?array {
    $pg = progress_get($memberId, $courseId);
    if (empty($pg)) return null;
    // 按 last_at 倒序找位置>0 且未完成的
    $candidates = [];
    foreach ($pg as $lid => $s) {
        if (!empty($s['position']) && empty($s['done'])) $candidates[$lid] = $s;
    }
    if ($candidates) {
        uasort($candidates, fn($a, $b) => strcmp($b['last_at'] ?? '', $a['last_at'] ?? ''));
        $lid = array_key_first($candidates);
        return ['lesson_id' => $lid, 'position' => (int)$candidates[$lid]['position'], 'state' => $candidates[$lid]];
    }
    return null;
}

// 课程完成度计算
function progress_summary(string $memberId, string $courseId, array $course): array {
    $pg = progress_get($memberId, $courseId);
    $total = 0; $done = 0; $inProgress = 0;
    foreach ($course['chapters'] ?? [] as $ch) {
        foreach ($ch['lessons'] ?? [] as $l) {
            $total++;
            $st = $pg[$l['id']] ?? null;
            if ($st && !empty($st['done'])) $done++;
            elseif ($st && !empty($st['position'])) $inProgress++;
        }
    }
    return ['total' => $total, 'done' => $done, 'in_progress' => $inProgress, 'percent' => $total ? round($done / $total * 100) : 0];
}
