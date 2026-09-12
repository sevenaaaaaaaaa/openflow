<?php
/**
 * FunnelDefinition — 转化漏斗的可配置定义
 *
 * 之前漏斗步骤硬编码且含 `form_view`（全站从未采集，导致漏斗恒缺一环、
 * 总体转化率被结构性拉低）。这里把步骤变成可配置，默认只用真实采集到的事件。
 */

function funnel_steps_file(): string { return DATA_DIR . '/cdp/funnel.json'; }

/** 默认步骤：全部是系统真实采集的事件 */
function funnel_steps_default(): array {
    return ['page_view', 'form_submit', 'purchase'];
}

/** 已注册的可用事件（供 UI 提示，避免再选到没采集的事件） */
function funnel_event_options(): array {
    return [
        'page_view' => '浏览页面',
        'article_view' => '阅读文章',
        'element_click' => '点击元素',
        'form_submit' => '提交表单',
        'download' => '下载资料',
        'share' => '分享',
        'purchase' => '完成购买',
        'member_register' => '注册会员',
        'login' => '登录',
    ];
}

function funnel_steps(): array {
    $s = json_read(funnel_steps_file());
    $steps = is_array($s['steps'] ?? null) ? array_values(array_filter(array_map('strval', $s['steps']))) : [];
    return $steps ?: funnel_steps_default();
}

function funnel_steps_save(array $steps): array {
    $valid = array_keys(funnel_event_options());
    $steps = array_values(array_unique(array_filter(array_map('trim', $steps), fn($s) => in_array($s, $valid, true))));
    if (count($steps) < 2) return ['ok' => false, 'error' => '至少保留 2 个漏斗步骤'];
    json_write(funnel_steps_file(), ['steps' => $steps, 'updated_at' => date('Y-m-d H:i:s')]);
    return ['ok' => true, 'steps' => $steps];
}
