#!/usr/bin/env php
<?php
/**
 * OpenFlow 文章导入器 — 从 Obsidian Markdown 批量导入
 * 用法: php bin/import.php <源目录> [选项]
 *
 * 选项:
 *   --category=<key>    默认分类 (默认 insight)
 *   --author=<name>     默认作者 (默认 Seven)
 *   --tag=<tag>         附加标签 (可重复使用)
 *   --status=<status>   发布状态 published|draft (默认 draft)
 *   --dry-run           只预览，不写入
 *
 * 示例:
 *   php bin/import.php "/path/to/公开发布" --category=insight --status=published --tag="网站增长"
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../admin/config.php';

$sourceDir = $argv[1] ?? '';
if (!$sourceDir || !is_dir($sourceDir)) {
    echo "用法: php bin/import.php <源目录> [--category=xxx] [--author=xxx] [--tag=xxx] [--status=published|draft] [--dry-run]\n";
    exit(1);
}

// Parse options
$opts = ['category' => 'insight', 'author' => 'Seven', 'tags' => [], 'status' => 'draft', 'dry-run' => false];
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--category=')) $opts['category'] = substr($arg, 11);
    elseif (str_starts_with($arg, '--author=')) $opts['author'] = substr($arg, 9);
    elseif (str_starts_with($arg, '--tag=')) $opts['tags'][] = substr($arg, 6);
    elseif (str_starts_with($arg, '--status=')) $opts['status'] = substr($arg, 9);
    elseif ($arg === '--dry-run') $opts['dry-run'] = true;
}

echo "📂 扫描: $sourceDir\n";
echo "   分类: {$opts['category']} | 作者: {$opts['author']} | 状态: {$opts['status']}\n";
if ($opts['dry-run']) echo "   🔍 预览模式 — 不会写入\n";
echo str_repeat('─', 60) . "\n";

$files = glob($sourceDir . '/*.md');
if (empty($files)) {
    echo "❌ 目录中没有 .md 文件\n";
    exit(1);
}

// Load existing articles
$articles = json_read(ARTICLES_DIR . '/index.json');
$existingSlugs = array_column($articles, null, 'slug');

$imported = 0;
$skipped = 0;
$newArticles = [];

foreach ($files as $file) {
    $basename = basename($file);
    echo "\n📄 $basename\n";

    $raw = file_get_contents($file);
    $raw = preg_replace('/^\x{FEFF}/u', '', $raw); // BOM

    // ─── Parse metadata ───
    $title = '';
    $content = $raw;
    $frontTags = [];
    $frontCat = null;
    $frontAuthor = null;

    // Extract YAML front matter if present
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $raw, $m)) {
        $front = $m[1];
        $content = $m[2];
        foreach (explode("\n", $front) as $line) {
            if (preg_match('/^(\w+):\s*(.+)$/', trim($line), $kv)) {
                $k = strtolower($kv[1]);
                $v = trim($kv[2]);
                if ($k === 'title') $title = $v;
                elseif ($k === 'tags') $frontTags = array_map('trim', explode(',', $v));
                elseif ($k === 'category') $frontCat = $v;
                elseif ($k === 'author') $frontAuthor = $v;
            }
        }
    }

    // If no front matter title, extract from first h1
    if (empty($title) && preg_match('/^# (.+)$/m', $content, $m)) {
        $title = trim($m[1]);
    }
    if (empty($title)) {
        $title = pathinfo($basename, PATHINFO_FILENAME);
        // Clean up common prefixes
        $title = preg_replace('/^\d{2}[-\s]+/', '', $title);
        $title = preg_replace('/^(New-\d+)[\s·]+/', '', $title);
    }

    echo "   标题: $title\n";

    // ─── Convert markdown to HTML ───
    $html = md_to_html($content);

    // ─── Generate slug ───
    $slug = slugify($title);
    if (isset($existingSlugs[$slug])) {
        $slug .= '-' . substr(md5($title), 0, 6);
        echo "   ⚠️  slug 冲突, 使用: $slug\n";
    }

    // ─── Categories & tags ───
    $category = $frontCat ?: $opts['category'];
    $tags = array_merge($frontTags, $opts['tags']);
    $author = $frontAuthor ?: $opts['author'];

    // ─── SEO excerpt ───
    $plainText = trim(strip_tags(str_replace(['<h1>','<h2>','<h3>','<p>','<br>','<li>'], "\n", $html)));
    $plainText = preg_replace('/\n{3,}/', "\n\n", $plainText);
    $excerpt = mb_substr($plainText, 0, 160);
    $firstPara = '';
    if (preg_match('/<p>(.+?)<\/p>/s', $html, $m)) {
        $firstPara = mb_substr(strip_tags($m[1]), 0, 160);
    }

    // ─── Build article ───
    $article = [
        'id' => 'art-' . substr(md5($slug . time()), 0, 10),
        'title' => $title,
        'slug' => $slug,
        'content' => $html,
        'excerpt' => $excerpt ?: $firstPara,
        'seo_title' => $title . ' | OpenFlow',
        'seo_desc' => $firstPara ?: $excerpt,
        'status' => $opts['status'],
        'author' => $author,
        'category' => $category,
        'tags' => array_unique($tags),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    echo "   分类: $category | 标签: " . implode(', ', $tags) . "\n";
    echo "   slug: /article/$slug\n";

    if (!$opts['dry-run']) {
        $newArticles[] = $article;
        $existingSlugs[$slug] = true;
    }
    $imported++;
}

// ─── Write ───
if (!$opts['dry-run'] && !empty($newArticles)) {
    $all = array_merge($articles, $newArticles);
    $ok = json_write(ARTICLES_DIR . '/index.json', $all);
    echo "\n" . str_repeat('─', 60) . "\n";
    echo $ok ? "✅ 已写入 " . count($newArticles) . " 篇文章\n" : "❌ 写入失败\n";
    echo "   总计: " . count($all) . " 篇\n";
} elseif ($opts['dry-run']) {
    echo "\n" . str_repeat('─', 60) . "\n";
    echo "🔍 预览完成 — 共 $imported 篇 (未写入)\n";
}

// ─── Helpers ──────────────────────────────
function slugify(string $s): string {
    $s = mb_strtolower($s);
    $s = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $s);
    $s = preg_replace('/[\s-]+/', '-', $s);
    $s = trim($s, '-');
    return mb_substr($s, 0, 80);
}

function md_to_html(string $md): string {
    // Basic markdown → HTML
    $lines = explode("\n", $md);
    $out = [];
    $inCode = false;
    $inList = false;
    $inTable = false;
    $tableRows = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Code blocks
        if (str_starts_with($trimmed, '```')) {
            if ($inCode) { $out[] = '</code></pre>'; $inCode = false; }
            else { $out[] = '<pre><code>'; $inCode = true; }
            continue;
        }
        if ($inCode) { $out[] = htmlspecialchars($line); continue; }

        // Empty line — close open blocks
        if ($trimmed === '') {
            if ($inList) { $out[] = '</ul>'; $inList = false; }
            if ($inTable) {
                if (!empty($tableRows)) {
                    $out[] = '<table>';
                    foreach ($tableRows as $ri => $row) {
                        $tag = ($ri === 1) ? 'th' : 'td';
                        $cells = array_map(fn($c) => "<$tag>" . trim($c, '|') . "</$tag>", $row);
                        $out[] = '<tr>' . implode('', $cells) . '</tr>';
                    }
                    $out[] = '</table>';
                }
                $inTable = false; $tableRows = [];
            }
            $out[] = '';
            continue;
        }

        // Table
        if (preg_match('/^\|.+\|$/', $trimmed)) {
            if (!$inTable) { $inTable = true; $tableRows = []; }
            if (!str_contains($trimmed, '---')) {
                $tableRows[] = array_map('trim', explode('|', trim($trimmed, '|')));
            } else {
                $tableRows[] = []; // separator row, mark index
            }
            continue;
        }

        // Headings
        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
            if ($inList) { $out[] = '</ul>'; $inList = false; }
            $level = strlen($m[1]);
            $out[] = "<h$level>" . inline_md($m[2]) . "</h$level>";
            continue;
        }

        // Horizontal rule
        if (preg_match('/^[-*_]{3,}$/', $trimmed)) {
            if ($inList) { $out[] = '</ul>'; $inList = false; }
            $out[] = '<hr>';
            continue;
        }

        // Blockquote
        if (str_starts_with($trimmed, '> ')) {
            if ($inList) { $out[] = '</ul>'; $inList = false; }
            $text = inline_md(substr($trimmed, 2));
            $out[] = "<blockquote><p>$text</p></blockquote>";
            continue;
        }

        // Unordered list
        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
            if (!$inList) { $inList = true; $out[] = '<ul>'; }
            $out[] = '<li>' . inline_md($m[1]) . '</li>';
            continue;
        }

        // Ordered list
        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
            if (!$inList) { $inList = true; $out[] = '<ol>'; }
            $out[] = '<li>' . inline_md($m[1]) . '</li>';
            continue;
        }

        // Bold text (standalone line)
        if (preg_match('/^\*\*(.+)\*\*$/', $trimmed, $m)) {
            $out[] = '<p><strong>' . htmlspecialchars($m[1]) . '</strong></p>';
            continue;
        }

        // Default: paragraph
        if ($inList) { $out[] = '</ul>'; $inList = false; }
        $out[] = '<p>' . inline_md($trimmed) . '</p>';
    }

    if ($inCode) $out[] = '</code></pre>';
    if ($inList) { $out[] = '</ul>'; $inList = false; }

    return implode("\n", $out);
}

function inline_md(string $s): string {
    // Bold
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    // Italic
    $s = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $s);
    // Inline code
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
    // Links [text](url)
    $s = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $s);
    // Images
    $s = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1">', $s);
    // Strikethrough
    $s = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $s);
    return $s;
}

echo "\n完成。\n";
