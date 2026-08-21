<?php
/**
 * 补充种子：AI_TOOL_INVENTORY 清单（198+ 创作工具）→ 导航站
 * 运行：php bin/seed-navigation-inventory.php
 * 读取用户本地 AI Tool List 目录的 3 份 inventory，提取工具+URL，按内容创作子分类收录
 */
require_once __DIR__ . '/../admin/config.php';

$dir = '/Users/seveno/Knowledge/Obsidian/MindRe/1-Project/Lovart MFlow/1-3 GenFlow/Content Distribution/Drafts/00-选题规划/AI Tool List';
$files = ['AI_TOOL_INVENTORY_V2.md', 'AI_TOOL_INVENTORY_COMPLETE.md', 'AI_TOOL_INVENTORY_FULL.md'];

// 原始分类 → 导航站子分类 + 大分类 映射
$catMap = [
    'AI 视频' => ['content', 'AI视频'], 'AI视频' => ['content', 'AI视频'],
    'AI 音频' => ['content', 'AI音频'], 'AI音频' => ['content', 'AI音频'], 'AI音乐' => ['content', 'AI音频'],
    'AI 图像' => ['content', 'AI图像'], 'AI图像' => ['content', 'AI图像'], 'AI 设计' => ['content', 'AI图像'],
    'AI 写作' => ['content', '写作与PPT'], 'AI写作' => ['content', '写作与PPT'], 'AI PPT' => ['content', '写作与PPT'],
    'AI 字幕' => ['content', '字幕翻译'], '字幕' => ['content', '字幕翻译'], 'AI 翻译' => ['content', '字幕翻译'],
    'AI 剪辑' => ['content', '录屏剪辑'], '剪辑' => ['content', '录屏剪辑'],
    'AI 播客' => ['content', 'AI音频'], 'AI 数字人' => ['agent', 'Agent 应用'],
    'AI 本地' => ['agent', '本地 AI'], '模型训练' => ['open', '开源 AI 应用'],
    '趋势' => ['content', '效率工具'], '素材' => ['content', '效率工具'],
];
$defaultCat = ['content', 'AI创作'];

$tools = [];
foreach ($files as $fn) {
    $path = $dir . '/' . $fn;
    if (!is_file($path)) continue;
    $curCat = '';
    foreach (file($path) as $line) {
        $line = trim($line);
        if (preg_match('/^##\s*[一二三四五六七八九十]+、(.+)/u', $line, $m)) { $curCat = $m[1]; continue; }
        if (preg_match('/^\|\s*\*\*([^*]+)\*\*\s*\|/', $line, $m)) {
            $name = trim($m[1]);
            $parts = array_map('trim', explode('|', $line));
            $url = '';
            foreach ($parts as $p) {
                if (strpos($p, 'github.com/') !== false) { $url = $p; break; }
                if (preg_match('#^[a-z0-9-]+\.(com|cn|ai|io|dev|app|art|so)$#i', $p)) { $url = 'https://' . $p; }
            }
            if ($name === '' || $url === '' || strpos($url, '搜索') !== false || strpos($url, 'N/A') !== false) continue;
            // 归一化 github URL（有时带 "）和多余文本）
            $url = trim($url, ' )）');
            if (strpos($url, 'github.com/') !== false && strpos($url, 'http') !== 0) $url = 'https://' . $url;
            if (!isset($tools[$name])) $tools[$name] = ['url' => $url, 'cat' => $curCat];
        }
    }
}

// 载入现有导航站，去重
$navFile = DATA_DIR . '/navigation.json';
$nav = json_read($navFile);
$exists = [];
foreach (($nav['sites'] ?? []) as $s) $exists[$s['url']] = true;

$added = 0;
foreach ($tools as $name => $t) {
    if (isset($exists[$t['url']])) continue;
    $exists[$t['url']] = true;
    [$cat, $sub] = $catMap[$t['cat']] ?? $defaultCat;
    // 从名称推断简单描述
    $desc = 'AI 创作工具' . ($t['cat'] ? '（' . $t['cat'] . '）' : '');
    $nav['sites'][] = [
        'id' => 'site_' . substr(md5($t['url']), 0, 8),
        'name' => $name, 'name_en' => '', 'url' => $t['url'],
        'description' => $desc, 'category' => $cat, 'sub' => $sub,
        'tags' => ['AI', '创作', strpos($t['url'], 'github.com') !== false ? '开源' : ''],
        'featured' => false,
        'region' => strpos($t['url'], 'github.com') !== false ? 'intl' : 'cn',
        'logo' => '', 'reason' => '', 'weight' => 3,
        'status' => 'published', 'hits' => 0, 'created_at' => date('Y-m-d H:i:s'),
    ];
    $added++;
}
$nav['updated_at'] = date('Y-m-d H:i:s');
json_write($navFile, $nav);
echo "解析工具: " . count($tools) . " · 新增收录: {$added} · 总计 " . count($nav['sites']) . " 站\n";
