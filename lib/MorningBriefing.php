<?php
/**
 * MorningBriefing — 每日增长晨会 v0
 *
 * 把驾驶舱 KPI + 今日主线 + AI 岗位交班收口成一段 3 分钟「合伙人式汇报」:
 *   昨日结果 → 今天最重要的三件事 → 需要你拍板的决策。
 *
 * v0 输出结构化文本(AI 可选增强:有模型时生成口播稿,无模型时模板直出,不假成功)。
 * 数据全部现算,不落盘;TTS/推送是后续版本的事,先把「汇报内容」做对。
 *
 * 依赖:DashboardSystem(只读) · Mainline(只读) · AiCenter(可选)
 */
require_once __DIR__ . '/Mainline.php';

function morning_briefing_data(): array {
    $kpis = [];
    try { if (!function_exists('dash_kpis')) require_once __DIR__ . '/DashboardSystem.php'; $kpis = dash_kpis(); }
    catch (Throwable $e) {}

    $items = [];
    try { $items = mainline_items(); } catch (Throwable $e) {}
    $now  = array_values(array_filter($items, fn($i) => ($i['lane'] ?? '') === 'now'));
    $today = array_values(array_filter($items, fn($i) => ($i['lane'] ?? '') === 'today'));

    return [
        'date'   => date('Y-m-d'),
        'kpis'   => $kpis,
        'now'    => array_slice($now, 0, 3),
        'today'  => array_slice($today, 0, 3),
        'load'   => count($now) + count($today),
    ];
}

/** 模板版汇报(无 AI 依赖,永远可用) */
function morning_briefing_text(array $d = []): string {
    $d = $d ?: morning_briefing_data();
    $lines = [];
    $lines[] = '早上好。今天是 ' . $d['date'] . ',花 3 分钟听完今天的晨会。';
    $lines[] = '';

    $k = $d['kpis'] ?? [];
    $kpiParts = [];
    foreach ([['today_uv', '今日访问'], ['uv', '30天访客'], ['leads', '30天线索'], ['revenue', '收入']] as [$key, $label]) {
        if (isset($k[$key]) && (float)$k[$key] > 0) $kpiParts[] = $label . ' ' . $k[$key];
    }
    if ($kpiParts) { $lines[] = '昨天到现在:' . implode(' · ', $kpiParts) . '。'; $lines[] = ''; }

    if ($d['now']) {
        $lines[] = '第一,必须现在处理的:';
        foreach ($d['now'] as $i => $it) $lines[] = ($i + 1) . '. ' . ($it['icon'] ?? '•') . ' ' . ($it['title'] ?? '') . '——' . ($it['why'] ?? '时间敏感');
        $lines[] = '';
    }
    if ($d['today']) {
        $lines[] = '第二,今天要推进的:';
        foreach ($d['today'] as $i => $it) $lines[] = ($i + 1) . '. ' . ($it['icon'] ?? '•') . ' ' . ($it['title'] ?? '');
        $lines[] = '';
    }
    if (!$d['now'] && !$d['today']) { $lines[] = '今天主线是空的——没有等着你的事。适合做一件「重要不紧急」的事:写一篇内容,或梳理一条流程。'; $lines[] = ''; }

    $lines[] = '汇报完毕。数据在驾驶舱,待办在主线,我随时在。';
    return implode("\n", $lines);
}

/** AI 增强版口播稿(模型可用时;失败回退模板版,绝不报假成功) */
function morning_briefing_ai(): array {
    $d = morning_briefing_data();
    $tpl = morning_briefing_text($d);
    try {
        if (!class_exists('AiCenter')) require_once __DIR__ . '/AiCenter.php';
        if (!AiCenter::isConfigured()) return ['ok' => true, 'text' => $tpl, 'mode' => 'template', 'data' => $d];
        $r = AiCenter::chat(
            '你是芭乐派 OpenFlow 的增长合伙人「小福」,每天早上向老板(一人公司创始人)做 3 分钟晨会汇报。口吻:直接、具体、不客套,像真合伙人;每件事都说清「为什么现在做」;结尾给一个明确的判断。',
            "以下是今天的数据,把它讲成一段 250 字以内的口播稿(纯文本,不要标题和列表符号):\n" . $tpl,
            ['max_tokens' => 600, 'feature' => 'morning_briefing', 'tier' => 'admin']
        );
        if (!empty($r['ok']) && mb_strlen((string)($r['text'] ?? '')) > 60) {
            return ['ok' => true, 'text' => (string)$r['text'], 'mode' => 'ai', 'data' => $d];
        }
    } catch (Throwable $e) { /* 回退模板 */ }
    return ['ok' => true, 'text' => $tpl, 'mode' => 'template', 'data' => $d];
}
