<?php
/**
 * 全局搜索 API
 * GET /api/search.php?q=关键词
 * 返回：跨文章/页面/课程/资料/线索/表单/活动的匹配结果
 */
require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}
$ql = mb_strtolower($q);
$results = [];

// 文章
foreach (get_articles() as $a) {
    $hay = mb_strtolower(($a['title'] ?? '') . ' ' . strip_tags($a['content'] ?? ''));
    if (mb_strpos($hay, $ql) !== false) {
        $results[] = ['type' => '文章', 'icon' => '📝', 'title' => $a['title'], 'link' => 'article-edit.php?id=' . urlencode($a['id']), 'hint' => '文章管理'];
    }
}

// 页面
$pageNames = ['index' => '首页', 'about' => '关于我们', 'capability' => '产品', 'courses' => '解决方案'];
foreach ($pageNames as $pk => $pn) {
    if (mb_strpos(mb_strtolower($pn), $ql) !== false || mb_strpos(mb_strtolower($pk), $ql) !== false) {
        $results[] = ['type' => '页面', 'icon' => '📄', 'title' => $pn, 'link' => 'pages.php?page=' . $pk, 'hint' => '页面管理'];
    }
}

// 课程
foreach (json_read(DATA_DIR . '/courses/index.json') as $c) {
    if (mb_strpos(mb_strtolower($c['title'] ?? ''), $ql) !== false) {
        $results[] = ['type' => '课程', 'icon' => '📚', 'title' => $c['title'], 'link' => 'course-edit.php?id=' . urlencode($c['id']), 'hint' => '课程管理'];
    }
}

// 资料
foreach (json_read(DATA_DIR . '/downloads.json') as $d) {
    if (mb_strpos(mb_strtolower($d['title'] ?? ''), $ql) !== false) {
        $results[] = ['type' => '资料', 'icon' => '📄', 'title' => $d['title'], 'link' => 'download-edit.php?id=' . urlencode($d['id']), 'hint' => '资料下载'];
    }
}

// 活动
foreach (json_read(DATA_DIR . '/events/index.json') as $e) {
    if (mb_strpos(mb_strtolower($e['title'] ?? ''), $ql) !== false) {
        $results[] = ['type' => '活动', 'icon' => '🎪', 'title' => $e['title'], 'link' => 'events.php?edit=' . urlencode($e['id']), 'hint' => '活动管理'];
    }
}

// 表单
foreach (json_read(DATA_DIR . '/forms/index.json') as $f) {
    if (mb_strpos(mb_strtolower($f['title'] ?? ''), $ql) !== false) {
        $results[] = ['type' => '表单', 'icon' => '📋', 'title' => $f['title'], 'link' => 'forms.php?edit=' . urlencode($f['id']), 'hint' => '表单管理'];
    }
}

// 线索（CSV）
if (file_exists(LEADS_CSV)) {
    $fp = fopen(LEADS_CSV, 'r');
    if ($fp) { fgetcsv($fp); $n = 0; while (($row = fgetcsv($fp)) && $n < 10) {
        $line = mb_strtolower(implode(' ', $row));
        if (mb_strpos($line, $ql) !== false) {
            $results[] = ['type' => '线索', 'icon' => '👥', 'title' => ($row[1] ?? '线索') . ' · ' . ($row[4] ?? ''), 'link' => 'leads.php', 'hint' => '线索管理'];
            $n++;
        }
    } fclose($fp); }
}

echo json_encode(['ok' => true, 'results' => array_slice($results, 0, 20)], JSON_UNESCAPED_UNICODE);
