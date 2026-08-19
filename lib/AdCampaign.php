<?php
/**
 * AdCampaign — 投放管理闭环
 * 投放计划 + 平台指标 + ROI 归因 + CAPI 转化打通
 * 素材可关联 DAM（admin/dam.php），转化数据来自 CAPI（ConversionApi）
 */

function adc_file(): string { return DATA_DIR . '/ad-campaigns.json'; }

function adc_all(): array {
    $list = json_read(adc_file());
    usort($list, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $list;
}
function adc_save(array $list): void { json_write(adc_file(), $list); }
function adc_get(string $id): ?array {
    foreach (adc_all() as $c) if (($c['id'] ?? '') === $id) return $c;
    return null;
}

function adc_platforms(): array {
    return ['巨量引擎', '腾讯广告', 'Google Ads', 'Meta Ads', '小红书', '其他'];
}

// 计算 ROI / ROAS：收入来自 CAPI 转化（conversion_events）按 campaign/utm 归属
function adc_compute_roi(array $camp): array {
    $cost = (float)($camp['metrics']['cost'] ?? 0);
    $conv = (int)($camp['metrics']['conversions'] ?? 0);
    $revenue = 0;
    // 关联 CAPI 转化：按 event_id/来源 统计本计划带来的转化价值（简化：按 utm 或手动 conversions×客单价）
    $aov = (float)($camp['aov'] ?? 0);
    if ($conv > 0 && $aov > 0) $revenue = $conv * $aov;
    $roi = $cost > 0 ? round(($revenue - $cost) / $cost * 100, 1) : null;
    $roas = $cost > 0 ? round($revenue / $cost, 2) : null;
    $cpc = (int)($camp['metrics']['clicks'] ?? 0) > 0 ? round($cost / (int)$camp['metrics']['clicks'], 2) : null;
    $cpa = $conv > 0 ? round($cost / $conv, 2) : null;
    return ['cost' => $cost, 'conversions' => $conv, 'revenue' => round($revenue, 2), 'roi' => $roi, 'roas' => $roas, 'cpc' => $cpc, 'cpa' => $cpa];
}
