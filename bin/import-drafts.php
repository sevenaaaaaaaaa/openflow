<?php
/**
 * 导入草稿脚本 — 把 drafts/ 文件夹的 Markdown 同步回站点文章库
 *
 * 用法：
 *   php bin/import-drafts.php            # 导入所有已写正文的草稿
 *   php bin/import-drafts.php --force    # 强制导入（忽略正文长度检查）
 *   php bin/import-drafts.php --list     # 只列出草稿状态，不导入
 *
 * 草稿格式（drafts/xxx.md）：
 *   ---
 *   id: art-xxx
 *   title: "文章标题"
 *   slug: article-slug
 *   category: ai-tools
 *   author: Seven
 *   status: draft        # 改成 published 即发布
 *   tags:
 *     - AI工具
 *     - 效率
 *   ---
 *
 *   # 文章标题
 *
 *   正文内容，Markdown 格式...
 */

require_once __DIR__ . '/../admin/config.php';

$draftDir = dirname(__DIR__) . '/drafts';
$force = in_array('--force', $argv ?? []);
$listOnly = in_array('--list', $argv ?? []);

if (!is_dir($draftDir)) {
    echo "❌ 找不到 drafts 目录：{$draftDir}\n";
    exit(1);
}

$files = glob($draftDir . '/*.md');
if (empty($files)) {
    echo "drafts/ 目录里没有 .md 文件\n";
    exit(0);
}
sort($files);

/**
 * 解析 frontmatter + 正文
 */
function parse_md(string $content): array {
    $meta = [];
    $body = $content;
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $content, $m)) {
        $body = $m[2];
        $lines = explode("\n", $m[1]);
        $curKey = null;
        foreach ($lines as $line) {
            // tags 数组项
            if (preg_match('/^\s+-\s+(.*)$/', $line, $mm) && $curKey === 'tags') {
                $meta['tags'][] = trim($mm[1]);
                continue;
            }
            // 键值
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*):\s*(.*)$/', $line, $mm)) {
                $curKey = $mm[1];
                $val = trim($mm[2]);
                // 去掉引号
                $val = preg_replace('/^"(.*)"$/', '$1', $val);
                $val = preg_replace("/^'(.*)'$/", '$1', $val);
                if ($curKey === 'tags') {
                    $meta['tags'] = $val !== '' ? array_map('trim', explode(',', $val)) : [];
                } else {
                    $meta[$curKey] = $val;
                }
                continue;
            }
        }
    }
    return [$meta, $body];
}

/**
 * 简单 Markdown → HTML
 */
function md_to_html(string $md): string {
    $md = str_replace("\r\n", "\n", $md);
    $lines = explode("\n", $md);
    $html = '';
    $inList = false;
    $inCode = false;
    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) {
            if ($inCode) { $html .= '</code></pre>'; $inCode = false; }
            else { $html .= '<pre class="code"><code>'; $inCode = true; }
            continue;
        }
        if ($inCode) { $html .= htmlspecialchars($line) . "\n"; continue; }
        // 标题
        if (preg_match('/^(#{1,4})\s+(.*)$/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $lvl = strlen($m[1]);
            $html .= '<h' . min(4, $lvl + 1) . '>' . md_inline($m[2]) . '</h' . min(4, $lvl + 1) . '>';
            continue;
        }
        // 列表
        if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . md_inline($m[1]) . '</li>';
            continue;
        }
        if ($inList && trim($line) === '') { $html .= '</ul>'; $inList = false; }
        // 引用
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<blockquote>' . md_inline($m[1]) . '</blockquote>';
            continue;
        }
        // 分隔线
        if (preg_match('/^---+\s*$/', $line)) continue;
        // 段落
        $t = trim($line);
        if ($t !== '') $html .= '<p>' . md_inline($t) . '</p>';
    }
    if ($inList) $html .= '</ul>';
    if ($inCode) $html .= '</code></pre>';
    return $html;
}

function md_inline(string $s): string {
    $s = htmlspecialchars($s);
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    $s = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $s);
    return $s;
}

// 读取现有文章库
$articles = json_read(ARTICLES_DIR . '/index.json');
$index = [];
foreach ($articles as $a) $index[$a['id']] = $a;

$imported = 0;
$published = 0;
$skipped = 0;
$stats = [];

foreach ($files as $f) {
    $content = file_get_contents($f);
    [$meta, $body] = parse_md($content);
    $id = $meta['id'] ?? '';
    $title = $meta['title'] ?? pathinfo($f, PATHINFO_FILENAME);

    if ($listOnly) {
        $bodyLen = mb_strlen(trim($body));
        $hasPlaceholder = strpos($body, '请在这里补充正文') !== false;
        $state = $hasPlaceholder || $bodyLen < 50 ? '待写' : '已写(' . $bodyLen . '字)';
        $status = $meta['status'] ?? 'draft';
        echo sprintf("[%s] %-6s %s | %s\n", $state, $status, basename($f), $title);
        continue;
    }

    // 判断是否已写正文
    $bodyClean = preg_replace('/<!--.*?-->/s', '', $body);
    $bodyLen = mb_strlen(trim(strip_tags($bodyClean)));
    if (!$force && $bodyLen < 50) {
        $skipped++;
        continue;
    }

    // 去掉 body 开头的 "# 标题" 行（frontmatter 里已有 title，正文里重复标题会导致页面双标题）
    $body = preg_replace('/^#\s+.+\n?/', '', $body, 1);

    // 转 HTML
    $html = md_to_html($body);

    $status = $meta['status'] ?? 'draft';
    $tags = $meta['tags'] ?? [];

    $article = [
        'id' => $id ?: 'art-' . substr(md5($f), 0, 10),
        'title' => $title,
        'slug' => $meta['slug'] ?? (pathinfo($f, PATHINFO_FILENAME)),
        'content' => $html,
        'category' => $meta['category'] ?? 'general',
        'status' => $status,
        'author' => $meta['author'] ?? '',
        'tags' => $tags,
        'created_at' => $index[$id]['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    // 保留原有的 seo 字段
    if (isset($index[$id])) {
        foreach (['excerpt','seo_title','seo_desc','seo_keywords','structured_data','cover','views'] as $k) {
            if (isset($index[$id][$k])) $article[$k] = $index[$id][$k];
        }
    }

    $index[$id] = $article;
    $imported++;
    if ($status === 'published') $published++;
    $stats[] = sprintf("[%s] %s (%s)", $status === 'published' ? '发布' : '草稿', $title, basename($f));
}

if ($listOnly) exit(0);

if ($imported === 0) {
    echo "没有可导入的草稿（正文 < 50 字会跳过）。\n";
    echo "写正文后重跑 `php bin/import-drafts.php`，或用 `--force` 强制导入。\n";
    exit(0);
}

// 写回
$all = array_values($index);
usort($all, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
json_write(ARTICLES_DIR . '/index.json', $all);

echo "========== 导入完成 ==========\n";
echo "导入：{$imported} 篇（其中发布 {$published} 篇），跳过未写正文：{$skipped} 篇\n";
echo "文章库当前总数：" . count($all) . " 篇\n\n";
foreach ($stats as $s) echo "  " . $s . "\n";
echo "\n发布到站点需要重新部署：\n  ./deploy.sh\n";
