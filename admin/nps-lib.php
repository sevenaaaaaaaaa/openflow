<?php
/**
 * NPS 调研系统共享库 — 数据模型 + 统计
 */

function nps_file(): string { return DATA_DIR . '/nps/projects.json'; }
function nps_responses_dir(): string { return DATA_DIR . '/nps/responses'; }

// ─── 项目 ───
function nps_get_projects(): array {
    return json_read(nps_file());
}
function nps_save_projects(array $projects): bool {
    if (!is_dir(dirname(nps_file()))) mkdir(dirname(nps_file()), 0755, true);
    return json_write(nps_file(), $projects);
}
function nps_get_project(string $id): ?array {
    foreach (nps_get_projects() as $p) if ($p['id'] === $id) return $p;
    return null;
}

// ─── 回收 ───
function nps_get_responses(string $projectId): array {
    return json_read(nps_responses_dir() . '/' . $projectId . '.json');
}
function nps_add_response(string $projectId, array $resp): bool {
    if (!is_dir(nps_responses_dir())) mkdir(nps_responses_dir(), 0755, true);
    $all = nps_get_responses($projectId);
    $all[] = $resp;
    return json_write(nps_responses_dir() . '/' . $projectId . '.json', $all);
}

// ─── NPS 计算 ───
// score: 0-10
// promoter: 9-10, passive: 7-8, detractor: 0-6
// NPS = (promoters/total - detractors/total) * 100
function nps_compute(array $responses): array {
    $total = count($responses);
    $promoters = 0; $passives = 0; $detractors = 0;
    $scores = [];
    foreach ($responses as $r) {
        $s = (int)($r['score'] ?? 0);
        $scores[] = $s;
        if ($s >= 9) $promoters++;
        elseif ($s >= 7) $passives++;
        else $detractors++;
    }
    if ($total === 0) {
        return ['total' => 0, 'nps' => null, 'promoters' => 0, 'passives' => 0, 'detractors' => 0,
            'promoter_pct' => 0, 'passive_pct' => 0, 'detractor_pct' => 0, 'avg' => null,
            'distribution' => array_fill(0, 11, 0)];
    }
    $promoterPct = $promoters / $total * 100;
    $detractorPct = $detractors / $total * 100;
    $nps = round($promoterPct - $detractorPct);

    $distribution = array_fill(0, 11, 0);
    foreach ($scores as $s) $distribution[$s]++;

    return [
        'total' => $total,
        'nps' => $nps,
        'promoters' => $promoters, 'passives' => $passives, 'detractors' => $detractors,
        'promoter_pct' => round($promoterPct, 1), 'passive_pct' => round($passives / $total * 100, 1), 'detractor_pct' => round($detractorPct, 1),
        'avg' => round(array_sum($scores) / $total, 2),
        'distribution' => $distribution,
    ];
}

// NPS 等级标签
function nps_grade(int $nps): array {
    if ($nps >= 70) return ['极佳', '#16a34a'];
    if ($nps >= 50) return ['优秀', '#65a30d'];
    if ($nps >= 30) return ['良好', '#d97706'];
    if ($nps >= 0) return ['一般', '#ea580c'];
    return ['需改善', '#dc2626'];
}
