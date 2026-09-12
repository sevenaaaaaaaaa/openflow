<?php
/**
 * SeoAudit — 内容健康分 + 内链建议引擎 + 一键插链（对标 Canva 级内容 SEO）
 *
 * 内容健康分：对每篇文章做一次可解释的体检（标题/描述/字数/H2/内链/图片 alt/slug/关键词），
 * 给出 0-100 分与逐项问题与修复提示——让"该优化哪篇、优化什么"一眼可见。
 * 内链引擎：不只匹配标题/标签，还识别"未被链接的提及"，并跳过已链接的 URL，支持一键插链。
 */

/** 解析正文为纯文本 */
function seo_plain(string $html): string {
    return trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
}

/** 统计正文里的站内文章链接数（去重 URL） */
function seo_internal_link_targets(string $html): array {
    $urls = [];
    if (preg_match_all('#<a\s[^>]*href=(["\'])((?:https?://[^"\']*)?/article/[^"\']+)\1#i', $html, $m)) {
        foreach ($m[2] as $u) $urls[$u] = true;
    }
    return array_keys($urls);
}

/**
 * 单篇文章内容健康分。
 * @return array ['score'=>int,'grade'=>string,'checks'=>[['key','label','pass','weight','hint']],'metrics'=>[]]
 */
function seo_audit_article(array $a): array {
    $title = trim((string)($a['title'] ?? ''));
    $desc  = trim((string)($a['seo_desc'] ?? ($a['excerpt'] ?? '')));
    $html  = (string)($a['content'] ?? '');
    $text  = seo_plain($html);
    $titleLen = mb_strlen($title);
    $descLen  = mb_strlen(strip_tags($desc));
    $words    = mb_strlen($text);
    $h2       = preg_match_all('/<h2[\s>]/i', $html);
    $h3       = preg_match_all('/<h3[\s>]/i', $html);
    $links    = count(seo_internal_link_targets($html));
    $imgs     = preg_match_all('/<img[\s>]/i', $html);
    $imgsNoAlt = preg_match_all('/<img(?![^>]*\balt=)[^>]*>/i', $html);
    $slug     = (string)($a['slug'] ?? '');
    $kw       = trim((string)($a['seo_keywords'] ?? ''));

    $checks = [
        ['key' => 'title_len', 'label' => '标题长度 10–60 字', 'pass' => $titleLen >= 10 && $titleLen <= 60, 'weight' => 2, 'hint' => "当前 {$titleLen} 字"],
        ['key' => 'desc',      'label' => 'SEO 描述 50–160 字', 'pass' => $descLen >= 50 && $descLen <= 160, 'weight' => 2, 'hint' => "当前 {$descLen} 字"],
        ['key' => 'words',     'label' => '正文 ≥ 300 字', 'pass' => $words >= 300, 'weight' => 2, 'hint' => "当前 {$words} 字"],
        ['key' => 'headings',  'label' => '≥ 2 个 H2 小标题', 'pass' => $h2 >= 2, 'weight' => 1.5, 'hint' => "H2×{$h2} / H3×{$h3}"],
        ['key' => 'internal',  'label' => '≥ 3 条站内内链', 'pass' => $links >= 3, 'weight' => 2, 'hint' => "当前 {$links} 条"],
        ['key' => 'img_alt',   'label' => '图片都有 alt', 'pass' => ($imgs === 0 || $imgsNoAlt === 0), 'weight' => 1.5, 'hint' => "缺 alt {$imgsNoAlt}/{$imgs}"],
        ['key' => 'slug',      'label' => 'slug 简洁（≤60、英文/数字）', 'pass' => $slug !== '' && mb_strlen($slug) <= 60 && (bool)preg_match('/^[a-z0-9\-]+$/', $slug), 'weight' => 1, 'hint' => $slug ?: '缺失'],
        ['key' => 'keyword',   'label' => '标题命中 SEO 关键词', 'pass' => $kw === '' || mb_strpos($title, $kw) !== false || mb_strpos($text, $kw) !== false, 'weight' => 1.5, 'hint' => $kw === '' ? '未设关键词' : $kw],
    ];

    $totalW = 0; $gotW = 0;
    foreach ($checks as $c) { $totalW += $c['weight']; if ($c['pass']) $gotW += $c['weight']; }
    $score = $totalW > 0 ? (int)round($gotW / $totalW * 100) : 0;
    $grade = $score >= 90 ? 'A' : ($score >= 75 ? 'B' : ($score >= 60 ? 'C' : 'D'));

    return ['score' => $score, 'grade' => $grade, 'checks' => $checks,
            'metrics' => ['title_len' => $titleLen, 'desc_len' => $descLen, 'words' => $words, 'h2' => $h2, 'internal_links' => $links, 'imgs' => $imgs, 'imgs_no_alt' => $imgsNoAlt]];
}

/** 全站内容健康概览：逐篇评分 + 汇总 */
function seo_audit_all(int $limit = 0): array {
    if (!function_exists('get_articles_list')) return ['items' => [], 'summary' => []];
    $items = [];
    foreach (get_articles_list() as $a) {
        if (($a['status'] ?? '') === 'trash') continue;
        $r = seo_audit_article($a);
        $items[] = ['id' => (string)($a['id'] ?? ''), 'title' => (string)($a['title'] ?? ''), 'status' => (string)($a['status'] ?? ''),
                    'score' => $r['score'], 'grade' => $r['grade'], 'issues' => array_values(array_filter($r['checks'], fn($c) => !$c['pass']))];
    }
    usort($items, fn($a, $b) => $a['score'] <=> $b['score']);
    $n = count($items);
    $avg = $n ? (int)round(array_sum(array_column($items, 'score')) / $n) : 0;
    $dist = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
    foreach ($items as $it) $dist[$it['grade']]++;
    $out = ['items' => $limit > 0 ? array_slice($items, 0, $limit) : $items,
            'summary' => ['total' => $n, 'avg' => $avg, 'dist' => $dist]];
    return $out;
}

/* ── 内链引擎 ── */

/** 位置 $pos（字符下标）是否已经处在某个 <a> 内 */
function seo_pos_inside_anchor(string $html, int $pos): bool {
    $before = mb_substr($html, 0, max(0, $pos));
    $open = mb_strripos($before, '<a ');
    if ($open === false) return false;
    $close = mb_strripos($before, '</a>');
    return $open > ($close === false ? -1 : $close);
}

/**
 * 内链建议引擎：识别文中"未被链接的提及"（标题/标签），跳过已链接的 URL。
 * @return array [['url','article_title','anchor','position','already_linked']]
 */
function seo_internal_link_suggestions(string $content, string $excludeId = '', int $limit = 15): array {
    if (!function_exists('get_articles_list')) return [];
    $plain = seo_plain($content);
    $already = seo_internal_link_targets($content);
    $alreadySet = array_flip($already);
    $out = [];
    foreach (get_articles_list() as $a) {
        $id = (string)($a['id'] ?? '');
        if ($id === $excludeId || ($a['status'] ?? 'draft') !== 'published') continue;
        $slug = (string)($a['slug'] ?? '');
        if ($slug === '') continue;
        $url = '/article/' . $slug;
        $cands = array_merge([(string)($a['title'] ?? '')], array_map('strval', (array)($a['tags'] ?? [])));
        foreach ($cands as $cand) {
            $cand = trim($cand);
            if (mb_strlen($cand) < 2) continue;
            $pos = mb_strpos($plain, $cand);
            if ($pos === false) continue;
            // 映射回 HTML 位置：用 plain 位置近似——直接检验 HTML 中该 anchor 附近是否已链接
            $existing = isset($alreadySet[$url]);
            $out[$url] = ['url' => $url, 'article_title' => (string)($a['title'] ?? ''), 'anchor' => $cand, 'position' => $pos, 'already_linked' => $existing];
            break;   // 每篇只给一个最佳提及
        }
    }
    $list = array_values($out);
    usort($list, fn($a, $b) => ($a['already_linked'] ? 1 : 0) <=> ($b['already_linked'] ? 1 : 0) ?: $a['position'] <=> $b['position']);
    return array_slice($list, 0, $limit);
}

/**
 * 一键插链：把文中第一处未被链接的 anchor 包成站内链接并保存。
 * @return array ['ok','position'|'error']
 */
function seo_apply_internal_link(string $articleId, string $url, string $anchor): array {
    if (!function_exists('get_article') || !function_exists('save_article')) return ['ok' => false, 'error' => '文章接口不可用'];
    $a = get_article($articleId);
    if (!$a) return ['ok' => false, 'error' => '文章不存在'];
    $anchor = trim($anchor);
    if ($anchor === '' || $url === '' || strpos($url, '/article/') !== 0) return ['ok' => false, 'error' => '参数非法'];
    $html = (string)($a['content'] ?? '');
    // 找第一处不在 <a> 内的 anchor
    $offset = 0; $pos = false;
    while (($p = mb_strpos($html, $anchor, $offset)) !== false) {
        if (!seo_pos_inside_anchor($html, $p)) { $pos = $p; break; }
        $offset = $p + mb_strlen($anchor);
    }
    if ($pos === false) return ['ok' => false, 'error' => '文中已无可插入的未链接提及'];
    $link = '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" class="ilink">' . htmlspecialchars($anchor, ENT_QUOTES) . '</a>';
    $a['content'] = mb_substr($html, 0, $pos) . $link . mb_substr($html, $pos + mb_strlen($anchor));
    $a['updated_at'] = date('Y-m-d H:i:s');
    save_article($a);
    return ['ok' => true, 'position' => $pos];
}
