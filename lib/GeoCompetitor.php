<?php
/**
 * GeoCompetitor — 竞品引用分析（GEO：看清"同一话题里谁被更多地提及/引用"）
 *
 * 配置竞品（域名或品牌名），扫描站内文章与 GEO 选题库，统计竞品与本品牌的
 * 提及量，算出话题份额（share of voice）。用于判断在目标话题上谁更占声量。
 */

function geo_competitors_file(): string { return DATA_DIR . '/geo/competitors.json'; }

function geo_competitors(): array {
    $d = json_read(geo_competitors_file());
    return is_array($d) ? array_values($d) : [];
}

function geo_competitor_add(string $domain, string $name = ''): array {
    $domain = mb_strtolower(trim(preg_replace('#^https?://#i', '', $domain)));
    $domain = trim(explode('/', $domain)[0]);
    if ($domain === '') return ['ok' => false, 'error' => '域名不能为空'];
    $list = geo_competitors();
    foreach ($list as $c) if (($c['domain'] ?? '') === $domain) return ['ok' => false, 'error' => '已存在'];
    $list[] = ['domain' => $domain, 'name' => trim($name) !== '' ? trim($name) : $domain];
    json_write(geo_competitors_file(), $list);
    return ['ok' => true];
}

function geo_competitor_remove(string $domain): bool {
    $list = array_values(array_filter(geo_competitors(), fn($c) => ($c['domain'] ?? '') !== $domain));
    return json_write(geo_competitors_file(), $list);
}

/** 统计一段文本里某关键词出现的次数 */
function geo_count_mentions(string $haystack, string $needle): int {
    $needle = mb_strtolower(trim($needle));
    if ($needle === '') return 0;
    return substr_count(mb_strtolower($haystack), $needle);
}

/**
 * 扫描站内文章 + GEO 选题库，统计竞品与本品牌提及量。
 * @return array [['name','domain','mentions','share','self'=>bool]]
 */
function geo_competitor_scan(): array {
    $sources = [];
    try {
        if (function_exists('get_articles_list')) {
            foreach (get_articles_list() as $a) {
                $sources[] = (string)($a['title'] ?? '') . ' ' . strip_tags((string)($a['content'] ?? '')) . ' ' . implode(' ', (array)($a['tags'] ?? []));
            }
        }
    } catch (\Throwable $e) {}
    try {
        if (function_exists('geo_get_topics')) {
            foreach (geo_get_topics() as $t) $sources[] = (string)($t['topic'] ?? '') . ' ' . (string)($t['angle'] ?? '') . ' ' . (string)($t['why'] ?? '');
        }
    } catch (\Throwable $e) {}
    $corpus = implode("\n", $sources);

    $rows = [];
    $brand = function_exists('site_config_get') ? (string)site_config_get('site_name') : 'OpenFlow';
    $brandMentions = geo_count_mentions($corpus, $brand);
    $rows[] = ['name' => $brand, 'domain' => '', 'mentions' => $brandMentions, 'self' => true];
    foreach (geo_competitors() as $c) {
        $m = geo_count_mentions($corpus, (string)($c['name'] ?? '')) + geo_count_mentions($corpus, (string)($c['domain'] ?? ''));
        $rows[] = ['name' => (string)($c['name'] ?? ''), 'domain' => (string)($c['domain'] ?? ''), 'mentions' => $m, 'self' => false];
    }
    $total = array_sum(array_column($rows, 'mentions'));
    foreach ($rows as &$r) $r['share'] = $total > 0 ? round($r['mentions'] / $total * 100, 1) : 0;
    unset($r);
    usort($rows, fn($a, $b) => $b['mentions'] <=> $a['mentions']);
    return $rows;
}
