<?php
/**
 * HealthRecord — 系统体检的历史与趋势
 *
 * 每次体检把「总分 + 分布 + 分类快照」记一笔，形成"身体"随时间的变化曲线；
 * 这样能看出趋势（在变好还是变差），而不是只看当前一帧。
 */

function health_history_file(): string { return DATA_DIR . '/health-history.json'; }

/** 记录一次体检（同一小时内只记一次，避免刷屏） */
function health_record_add(array $scoreInfo, array $checks): void {
    $list = json_read(health_history_file());
    if (!is_array($list)) $list = [];
    $last = end($list);
    if (is_array($last) && (time() - (int)($last['ts'] ?? 0)) < 3600) {
        // 一小时内已记过 → 覆盖最后一条即可
        array_pop($list);
    }
    $catScores = [];
    foreach ($checks as $cat => $items) {
        $tw = 0; $sw = 0;
        foreach ($items as $it) { $w = $it['weight'] ?? 1; $tw += $w; if ($it['status'] === 'pass') $sw += $w; elseif ($it['status'] === 'warn') $sw += $w * 0.6; }
        $catScores[mb_substr((string)$cat, 0, 12)] = $tw > 0 ? (int)round($sw / $tw * 100) : 100;
    }
    $list[] = ['ts' => time(), 'at' => date('Y-m-d H:i:s'), 'score' => (int)($scoreInfo['score'] ?? 0), 'grade' => (string)($scoreInfo['grade'] ?? ''), 'stat' => (array)($scoreInfo['stat'] ?? []), 'cats' => $catScores];
    json_write(health_history_file(), array_slice($list, -180));   // 保留最近 ~180 次
}

function health_history(int $limit = 30): array {
    $list = json_read(health_history_file());
    return is_array($list) ? array_slice($list, -max(1, $limit)) : [];
}

/** 趋势：与上一次/上期对比 */
function health_trend(): array {
    $h = health_history(60);
    $n = count($h);
    if ($n === 0) return ['points' => [], 'delta' => 0, 'avg' => 0, 'last' => 0];
    $last = (int)($h[$n - 1]['score'] ?? 0);
    $prev = $n >= 2 ? (int)($h[$n - 2]['score'] ?? $last) : $last;
    $avg = (int)round(array_sum(array_column($h, 'score')) / $n);
    return ['points' => array_map(fn($r) => (int)$r['score'], $h), 'delta' => $last - $prev, 'avg' => $avg, 'last' => $last];
}

/** 生成内联 SVG 趋势折线 */
function health_trend_svg(array $points, int $w = 240, int $h = 48): string {
    $n = count($points);
    if ($n < 2) return '';
    $min = min($points); $max = max($points);
    if ($max - $min < 5) { $min = max(0, $min - 3); $max = min(100, $max + 3); }
    $dx = $w / ($n - 1);
    $d = '';
    foreach ($points as $i => $v) {
        $x = round($i * $dx, 1);
        $y = round($h - ($v - $min) / max(1, ($max - $min)) * $h, 1);
        $d .= ($i ? ' L' : 'M') . $x . ' ' . $y;
    }
    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" style="width:' . $w . 'px;height:' . $h . 'px"><path d="' . $d . '" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linejoin="round"/></svg>';
}
