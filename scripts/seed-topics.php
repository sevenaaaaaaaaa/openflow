<?php
/**
 * 专题聚合（topic hub）数据生成 —— 从「已发布文章」聚合出真实专题
 *
 * 设计原则（不编内容）：
 *  - 专题题目/描述直接取自 data/article-categories.json 的分类名与描述（自己站点既有文案）
 *  - article_ids 取该分类下最新的已发布文章（时间倒序）
 *  - cover 取该分类最新一篇的封面（真实资产），没有就留空
 *  - 每个分类 ≥ 阈值才建专题，避免空专题
 *
 * 用法：
 *   php scripts/seed-topics.php              # 预览（不写入）
 *   php scripts/seed-topics.php --write      # 写入 data/topics.json
 *   php scripts/seed-topics.php --write --min-top=8 --min-sub=10
 */

require_once dirname(__DIR__) . '/admin/config.php';

$write = in_array('--write', $argv, true);
function arg(string $k, int $d): int {
    foreach ($GLOBALS['argv'] as $a) if (str_starts_with($a, "--$k=")) return (int) substr($a, strlen($k) + 3);
    return $d;
}
$minTop = arg('min-top', 8);
$minSub = arg('min-sub', 12);

$categories = json_read(DATA_DIR . '/article-categories.json');
$articles = json_read(ARTICLES_DIR . '/index.json');
$articles = array_values(array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published'));
usort($articles, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$byCat = [];
foreach ($articles as $a) {
    $c = (string) ($a['category'] ?? '');
    if ($c === '') continue;
    $byCat[$c][] = $a;
}

$topics = [];
$seen = [];

/** 建一个专题 */
$make = function (string $slug, string $title, string $desc, string $cat, array $arts) {
    $arts = array_slice($arts, 0, 12);
    return [
        'slug' => $slug,
        'title' => $title,
        'description' => $desc,
        'category' => $cat,
        'cover' => (string) ($arts[0]['cover'] ?? ''),
        'article_ids' => array_map(fn($a) => $a['id'], $arts),
        'status' => 'published',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
};

// 1) 顶层分类（用分类自己的名称/描述）
foreach ($categories as $key => $c) {
    $arts = [];
    foreach ($byCat as $cat => $list) {
        if ($cat === $key || str_starts_with($cat, $key . '/')) $arts = array_merge($arts, $list);
    }
    usort($arts, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    if (count($arts) < $minTop) continue;
    $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($key));
    $topics[] = $make($slug, (string) ($c['name'] ?? $key), (string) ($c['description'] ?? ''), $key, $arts);
    $seen[$key] = true;
}

// 2) 子分类（够大才单独成专题）
foreach ($categories as $key => $c) {
    foreach (($c['sub'] ?? []) as $sub => $s) {
        $cat = $key . '/' . $sub;
        $arts = $byCat[$cat] ?? [];
        if (count($arts) < $minSub) continue;
        $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($key . '-' . $sub));
        $topics[] = $make(
            $slug,
            (string) ($c['name'] ?? $key) . ' · ' . (string) ($s['name'] ?? $sub),
            (string) ($s['description'] ?? ($c['description'] ?? '')),
            $cat,
            $arts
        );
    }
}

if ($write) {
    // 覆盖前备份（保护人工策划过的专题）
    $dst = DATA_DIR . '/topics.json';
    if (is_file($dst) && filesize($dst) > 4) {
        $bak = DATA_DIR . '/topics.json.bak-' . date('Ymd-His');
        copy($dst, $bak);
        echo "（已备份原文件 → " . basename($bak) . "）\n";
    }
    json_write($dst, $topics);
    echo "✅ 写入 data/topics.json：" . count($topics) . " 个专题\n";
} else {
    echo "（预览，未写入）将生成 " . count($topics) . " 个专题：\n";
}

foreach ($topics as $t) {
    printf("  %-22s %-18s %2d 篇  %s\n", $t['slug'], $t['title'], count($t['article_ids']), mb_substr($t['description'], 0, 40));
}
