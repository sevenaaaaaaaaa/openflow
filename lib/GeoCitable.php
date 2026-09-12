<?php
/**
 * GeoCitable — 可引用格式改写（GEO：让内容变成 AI 引擎愿意直接引用/摘录的结构）
 *
 * AI 引擎（Perplexity / ChatGPT Search / Google AIO）偏好：先给结论（Answer）、
 * 关键事实可摘录、Q&A 结构化、数据成表。这里把一篇文章重排成这种"可引用块"，
 * 并可一键写回正文（含 FAQ 结构化数据）。AI 不可用时退化为确定性抽取，绝不假成功。
 */

/** 确定性抽取可引用块（无 AI 兜底） */
function geo_citable_rules(array $article): array {
    $html = (string)($article['content'] ?? '');
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
    // 结论：首段
    $answer = '';
    if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $m)) $answer = trim(preg_replace('/\s+/u', ' ', strip_tags($m[1])));
    if ($answer === '') $answer = mb_substr($text, 0, 200);
    // 关键事实：含数字/百分号/金额的句子
    $facts = [];
    foreach (preg_split('/(?<=[。！？.!?])\s*/u', $text) as $s) {
        $s = trim($s);
        if ($s !== '' && preg_match('/\d|%|¥|\$|倍|成/', $s) && mb_strlen($s) <= 80) $facts[] = $s;
        if (count($facts) >= 4) break;
    }
    // Q&A：h2/h3 → 紧随段落
    $qa = [];
    if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>\s*(?:<p[^>]*>(.*?)<\/p>)?/is', $html, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $h) {
            $q = trim(strip_tags($h[1]));
            $a = trim(preg_replace('/\s+/u', ' ', strip_tags($h[2] ?? '')));
            if ($q !== '') $qa[] = ['q' => $q, 'a' => $a];
            if (count($qa) >= 4) break;
        }
    }
    return ['answer' => $answer, 'key_facts' => array_values($facts), 'qa' => $qa, 'table' => null];
}

/** 生成可引用块（优先 AI，失败/未配置退化到规则） */
function geo_citable_rewrite(array $article, bool $useAi = true): array {
    $base = geo_citable_rules($article);
    if (!$useAi) return ['ok' => true, 'blocks' => $base, 'source' => 'rules'];
    if (!class_exists('AiCenter')) { $p = __DIR__ . '/AiCenter.php'; if (is_file($p)) require_once $p; }
    if (!class_exists('AiCenter') || !AiCenter::isConfigured()) return ['ok' => true, 'blocks' => $base, 'source' => 'rules'];
    $text = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)($article['content'] ?? '')))), 0, 6000);
    $system = "你是 GEO（生成式引擎优化）编辑。把文章重排成 AI 引擎**最容易直接引用**的结构。\n"
        . "严格输出 JSON：{\"answer\":\"40-80字直接结论(可被原样引用)\",\"key_facts\":[\"含数字/事实的可摘录句(3-5条)\"],"
        . "\"qa\":[{\"q\":\"读者会问的问题\",\"a\":\"直接回答(1-2句)\"}],\"table\":{\"headers\":[\"指标\",\"值\"],\"rows\":[[\"...\",\"...\"]]}}\n"
        . "规则：只用原文事实，不得编造数字；没有表格就 table 设为 null；qa 3-5 条。";
    try {
        $r = AiCenter::json($system, "标题：{$article['title']}\n正文：{$text}", ['max_tokens' => 1200, 'feature' => 'geo_citable', 'tier' => 'admin']);
    } catch (\Throwable $e) { return ['ok' => true, 'blocks' => $base, 'source' => 'rules']; }
    if (empty($r['ok']) || empty($r['data']) || empty($r['data']['answer'])) return ['ok' => true, 'blocks' => $base, 'source' => 'rules'];
    $d = (array)$r['data'];
    $blocks = [
        'answer' => mb_substr((string)$d['answer'], 0, 300),
        'key_facts' => array_values(array_filter(array_map(fn($x) => mb_substr((string)$x, 0, 120), array_slice((array)($d['key_facts'] ?? []), 0, 6)))),
        'qa' => [],
        'table' => null,
    ];
    foreach (array_slice((array)($d['qa'] ?? []), 0, 5) as $qa) {
        if (is_array($qa) && !empty($qa['q'])) $blocks['qa'][] = ['q' => mb_substr((string)$qa['q'], 0, 100), 'a' => mb_substr((string)($qa['a'] ?? ''), 0, 300)];
    }
    if (is_array($d['table'] ?? null) && !empty($d['table']['headers'])) {
        $blocks['table'] = ['headers' => array_map('strval', (array)$d['table']['headers']), 'rows' => array_map(fn($r) => array_map('strval', (array)$r), (array)($d['table']['rows'] ?? []))];
    }
    return ['ok' => true, 'blocks' => $blocks, 'source' => 'ai'];
}

/** 渲染可引用块为 HTML */
function geo_citable_html(array $blocks): string {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $h = '<section class="geo-citable" style="border:1px solid var(--border);border-radius:12px;padding:18px 20px;margin:18px 0">';
    if (!empty($blocks['answer'])) {
        $h .= '<blockquote style="margin:0 0 14px;padding:10px 16px;border-left:3px solid var(--accent);font-size:15px;font-weight:600">' . $e($blocks['answer']) . '</blockquote>';
    }
    if (!empty($blocks['key_facts'])) {
        $h .= '<h3 style="font-size:14px;margin:10px 0 6px">关键事实</h3><ul>';
        foreach ($blocks['key_facts'] as $f) $h .= '<li>' . $e($f) . '</li>';
        $h .= '</ul>';
    }
    if (!empty($blocks['table']['headers'])) {
        $h .= '<table style="width:100%;border-collapse:collapse;margin:10px 0"><thead><tr>';
        foreach ($blocks['table']['headers'] as $th) $h .= '<th style="text-align:left;border-bottom:1px solid var(--border);padding:6px">' . $e($th) . '</th>';
        $h .= '</tr></thead><tbody>';
        foreach ($blocks['table']['rows'] as $row) { $h .= '<tr>'; foreach ($row as $td) $h .= '<td style="padding:6px;border-bottom:1px solid var(--border)">' . $e($td) . '</td>'; $h .= '</tr>'; }
        $h .= '</tbody></table>';
    }
    if (!empty($blocks['qa'])) {
        $h .= '<h3 style="font-size:14px;margin:12px 0 6px">常见问题</h3>';
        foreach ($blocks['qa'] as $qa) $h .= '<p style="margin:6px 0"><strong>' . $e($qa['q']) . '</strong><br>' . $e($qa['a']) . '</p>';
    }
    return $h . '</section>';
}

/** FAQ 结构化数据（供 SeoHead/结构化数据使用） */
function geo_citable_faq_jsonld(array $qa): array {
    $items = [];
    foreach ($qa as $x) if (!empty($x['q'])) $items[] = ['@type' => 'Question', 'name' => (string)$x['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string)($x['a'] ?? '')]];
    if (!$items) return [];
    return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items];
}

/**
 * 一键写回：把可引用块插到正文最前（幂等，已有标记则替换）。
 */
function geo_citable_apply(string $articleId, array $blocks): array {
    if (!function_exists('get_article') || !function_exists('save_article')) return ['ok' => false, 'error' => '文章接口不可用'];
    $a = get_article($articleId);
    if (!$a) return ['ok' => false, 'error' => '文章不存在'];
    $block = geo_citable_html($blocks);
    $html = (string)($a['content'] ?? '');
    // 移除旧的可引用块（幂等重写）
    $html = preg_replace('#<section class="geo-citable".*?</section>#is', '', $html, 1);
    $a['content'] = $block . "\n" . ltrim($html);
    $faq = geo_citable_faq_jsonld((array)($blocks['qa'] ?? []));
    if ($faq) $a['geo_faq'] = $faq;
    $a['geo_citable_at'] = date('Y-m-d H:i:s');
    $a['updated_at'] = date('Y-m-d H:i:s');
    save_article($a);
    return ['ok' => true, 'faq' => (bool)$faq];
}
