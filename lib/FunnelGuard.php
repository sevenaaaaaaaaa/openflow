<?php
/**
 * FunnelGuard — 转化漏斗级 AI 巡检
 * 对比近7天 vs 前7天：落地页访问→转化率、渠道转化效率，自动告警 + AI 根因建议
 * cron 调用，结果存 data/funnel-insights.json
 */

function funnel_guard_file(): string { return DATA_DIR . '/funnel-insights.json'; }

// 按页面/渠道聚合事件转化
function funnel_guard_collect(string $start, string $end): array {
    $agg = []; // page/渠道 => ['views'=>, 'submits'=>, 'purchases'=>]
    try {
        $rows = Database::query("SELECT event, page, props, created_at FROM events WHERE created_at >= ? AND created_at < ?", [$start, $end]);
        foreach ($rows as $r) {
            $ev = $r['event'];
            if (!in_array($ev, ['page_view', 'form_submit', 'purchase', 'element_click'], true)) continue;
            $props = json_decode($r['props'] ?? '[]', true);
            if (!is_array($props)) $props = [];
            $page = $r['page'] !== '' ? $r['page'] : '/';
            // 渠道
            $ch = $props['utm_source'] ?? '';
            if ($ev === 'page_view') {
                $agg[$page]['views'] = ($agg[$page]['views'] ?? 0) + 1;
                if ($ch) { $agg['@' . $ch]['views'] = ($agg['@' . $ch]['views'] ?? 0) + 1; }
            } elseif ($ev === 'form_submit') {
                $agg[$page]['submits'] = ($agg[$page]['submits'] ?? 0) + 1;
            } elseif ($ev === 'purchase') {
                $agg[$page]['purchases'] = ($agg[$page]['purchases'] ?? 0) + 1;
                if ($ch) { $agg['@' . $ch]['purchases'] = ($agg['@' . $ch]['purchases'] ?? 0) + 1; }
            }
        }
    } catch (Exception $e) {}
    return $agg;
}

// 巡检：环比转化率骤降检测
function funnel_guard_scan(): array {
    $now = time();
    $curStart = date('Y-m-d', $now - 7 * 86400);
    $curEnd = date('Y-m-d', $now + 86400);
    $prevStart = date('Y-m-d', $now - 14 * 86400);
    $prevEnd = $curStart;

    $cur = funnel_guard_collect($curStart, $curEnd);
    $prev = funnel_guard_collect($prevStart, $prevEnd);

    $insights = [];
    $alerts = 0;
    // 页面转化率
    $keys = array_unique(array_merge(array_keys($cur), array_keys($prev)));
    foreach ($keys as $k) {
        $isChannel = strpos($k, '@') === 0;
        $curViews = (int)($cur[$k]['views'] ?? 0);
        $curSub = (int)($cur[$k]['submits'] ?? 0);
        $curBuy = (int)($cur[$k]['purchases'] ?? 0);
        $prevViews = (int)($prev[$k]['views'] ?? 0);
        $prevBuy = (int)($prev[$k]['purchases'] ?? 0);
        if ($curViews < 50 && $prevViews < 50) continue; // 样本太小跳过
        $curRate = $curViews > 0 ? $curBuy / $curViews : 0;
        $prevRate = $prevViews > 0 ? $prevBuy / $prevViews : 0;
        $label = $isChannel ? '渠道 ' . substr($k, 1) : '落地页 ' . $k;
        if ($prevRate > 0.005 && $curRate < $prevRate * 0.5) {
            $drop = round((1 - $curRate / $prevRate) * 100);
            $alerts++;
            $insights[] = [
                'type' => $isChannel ? 'channel' : 'landing',
                'target' => $k, 'label' => $label,
                'cur_views' => $curViews, 'cur_conv' => round($curRate * 100, 2),
                'prev_conv' => round($prevRate * 100, 2), 'drop_pct' => $drop,
                'severity' => $drop > 60 ? 'high' : ($drop > 40 ? 'medium' : 'low'),
                'suggestion' => funnel_guard_suggest($isChannel ? 'channel' : 'landing', $label, $drop),
                'detected_at' => date('Y-m-d H:i:s'),
            ];
        } elseif ($curRate > 0.02 && $curRate > $prevRate * 1.5 && $prevRate > 0) {
            $insights[] = ['type' => $isChannel ? 'channel' : 'landing', 'target' => $k, 'label' => $label, 'cur_views' => $curViews, 'cur_conv' => round($curRate * 100, 2), 'prev_conv' => round($prevRate * 100, 2), 'drop_pct' => 0, 'severity' => 'good', 'suggestion' => '转化率显著提升，是有效打法，建议复盘并复制。', 'detected_at' => date('Y-m-d H:i:s')];
        }
    }
    usort($insights, fn($a, $b) => ($b['drop_pct'] ?? 0) <=> ($a['drop_pct'] ?? 0));
    $report = ['scanned_at' => date('Y-m-d H:i:s'), 'window' => '近7天 vs 前7天', 'alerts' => $alerts, 'insights' => array_slice($insights, 0, 15)];
    json_write(funnel_guard_file(), $report);
    return $report;
}

// 根因建议
function funnel_guard_suggest(string $type, string $label, int $drop): string {
    $suggestions = [
        'landing' => "「{$label}」转化率下降 {$drop}%：建议检查①落地页加载速度与移动端适配 ②CTA 与表单字段是否过重 ③广告创意与落地页相关性 ④近期是否有改版（可回溯 A/B 实验）。",
        'channel' => "「{$label}」转化效率下降 {$drop}%：建议检查①渠道人群定向是否漂移 ②落地页与渠道素材匹配 ③是否被竞品分流 ④结合 CAPI 回传核对归因。",
    ];
    return $suggestions[$type] ?? '建议结合热力图与录屏定位转化断点。';
}

// 最近巡检报告
function funnel_guard_report(): array {
    return json_read(funnel_guard_file());
}
