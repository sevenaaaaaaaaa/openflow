<?php
/**
 * GeoEntities — 实体图（GEO：让品牌/产品/人物/话题以一致的实体形式被 AI 识别）
 *
 * 从站内内容里抽出四类实体——品牌 / 产品 / 人物 / 话题——统计出现频次并记录
 * 同一内容里的共现关系（实体图）。AI 引擎更信任「实体一致、上下文明晰」的站点；
 * 这张图也是实体一致性检查与 schema 补全的依据。
 */

function geo_entities_file(): string { return DATA_DIR . '/geo/entities.json'; }

/** 构建实体图：扫描已发布文章 + 课程/商品 + 作者 + 分类/标签，算频次与共现 */
function geo_entities_build(): array {
    $nodes = [];   // id => ['id','type','name','mentions','sources']
    $edges = [];   // "a|b" => ['a','b','weight']
    $add = function (string $type, string $name, string $source) use (&$nodes) {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 40) return;
        $id = $type . ':' . mb_strtolower($name);
        if (!isset($nodes[$id])) $nodes[$id] = ['id' => $id, 'type' => $type, 'name' => $name, 'mentions' => 0, 'sources' => []];
        $nodes[$id]['mentions']++;
        if (count($nodes[$id]['sources']) < 20 && !in_array($source, $nodes[$id]['sources'], true)) $nodes[$id]['sources'][] = $source;
    };
    $link = function (array $ids) use (&$edges) {
        $ids = array_values(array_unique($ids));
        for ($i = 0; $i < count($ids); $i++) for ($j = $i + 1; $j < count($ids); $j++) {
            $k = $ids[$i] < $ids[$j] ? $ids[$i] . '|' . $ids[$j] : $ids[$j] . '|' . $ids[$i];
            $edges[$k] = $edges[$k] ?? ['a' => min($ids[$i], $ids[$j]), 'b' => max($ids[$i], $ids[$j]), 'weight' => 0];
            $edges[$k]['weight']++;
        }
    };

    // 品牌
    $brand = function_exists('site_config_get') ? (string)site_config_get('site_name') : 'OpenFlow';
    $add('brand', $brand, '/');

    // 文章：分类/标签/作者
    try {
        if (function_exists('get_articles_list')) {
            foreach (get_articles_list() as $a) {
                if (($a['status'] ?? '') !== 'published') continue;
                $src = '/article/' . (string)($a['slug'] ?? ($a['id'] ?? ''));
                $ids = ['brand:' . mb_strtolower($brand)];
                $cat = (string)($a['category'] ?? '');
                if ($cat !== '') { $add('topic', $cat, $src); $ids[] = 'topic:' . mb_strtolower($cat); }
                foreach ((array)($a['tags'] ?? []) as $t) { $add('topic', (string)$t, $src); $ids[] = 'topic:' . mb_strtolower((string)$t); }
                $author = (string)($a['author'] ?? ($a['author_name'] ?? ''));
                if ($author !== '') { $add('person', $author, $src); $ids[] = 'person:' . mb_strtolower($author); }
                if (count($ids) > 1) $link($ids);
            }
        }
    } catch (\Throwable $e) {}

    // 产品：课程 + 已上架商品
    try {
        foreach (json_read(DATA_DIR . '/courses/index.json') as $c) {
            if (($c['status'] ?? '') !== 'published') continue;
            $add('product', (string)($c['title'] ?? ''), '/courses/' . (string)($c['id'] ?? ''));
        }
    } catch (\Throwable $e) {}
    try {
        if (class_exists('CommerceSystem')) {
            foreach (CommerceSystem::allPublished() as $p) {
                $add('product', (string)($p['title'] ?? ''), '/' . (string)($p['type'] ?? 'skill') . '/' . (string)($p['asset_id'] ?? ''));
            }
        }
    } catch (\Throwable $e) {}

    $out = ['nodes' => array_values($nodes), 'edges' => array_values($edges), 'built_at' => date('Y-m-d H:i:s')];
    json_write(geo_entities_file(), $out);
    return $out;
}

function geo_entities(): array {
    $d = json_read(geo_entities_file());
    return ['nodes' => is_array($d['nodes'] ?? null) ? $d['nodes'] : [], 'edges' => is_array($d['edges'] ?? null) ? $d['edges'] : [], 'built_at' => (string)($d['built_at'] ?? '')];
}

/** 实体图统计：按类型计数、Top 实体、Top 关系 */
function geo_entities_report(): array {
    $g = geo_entities();
    $byType = []; $top = [];
    foreach ($g['nodes'] as $n) {
        $byType[$n['type']] = ($byType[$n['type']] ?? 0) + 1;
        $top[] = $n;
    }
    usort($top, fn($a, $b) => ($b['mentions'] ?? 0) <=> ($a['mentions'] ?? 0));
    $edges = $g['edges'];
    usort($edges, fn($a, $b) => ($b['weight'] ?? 0) <=> ($a['weight'] ?? 0));
    return ['by_type' => $byType, 'total' => count($g['nodes']), 'links' => count($g['edges']),
            'top_nodes' => array_slice($top, 0, 20), 'top_edges' => array_slice($edges, 0, 15), 'built_at' => $g['built_at']];
}
