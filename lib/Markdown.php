<?php
/**
 * Markdown → HTML 轻量转换器（无外部依赖）
 * 支持：标题 / 加粗 / 斜体 / 行内代码 / 代码块 / 链接 / 图片 / 列表 / 引用 / 水平线 / 表格
 */
require_once __DIR__ . '/../admin/config.php';

class Markdown {
    /** 转换 Markdown 为 HTML */
    public static function toHtml(string $md): string {
        $md = preg_replace("/\r\n|\r/", "\n", $md);
        $md = str_replace("\xC2\xA0", ' ', $md);

        // 提取代码块（fenced code）
        $blocks = [];
        $md = preg_replace_callback('/```[a-z0-9]*\n([\s\S]*?)```/i', function ($m) use (&$blocks) {
            $blocks[] = '<pre><code>' . htmlspecialchars(rtrim($m[1])) . "</code></pre>";
            return "\x00CODE" . (count($blocks) - 1) . "\x00";
        }, $md);

        $lines = preg_split('/\n/', $md);
        $html = '';
        $inList = null; // 'ul' | 'ol'
        $inQuote = false;
        $inTable = false;
        $tableRows = [];

        foreach ($lines as $rawLine) {
            $line = $rawLine;

            // 表格
            if (preg_match('/^\s*\|/', $line)) {
                $cells = array_map('trim', explode('|', trim($line, '| ')));
                if (isset($cells[0]) && preg_match('/^:?-{2,}:?$/', trim($cells[0])) && count($tableRows) === 1) {
                    $tableRows = [array_shift($tableRows)]; // 丢弃分隔行
                    continue;
                }
                if (!$inTable) { $inTable = true; $tableRows = []; }
                $tableRows[] = $cells;
                continue;
            }
            if ($inTable) {
                $html .= self::renderTable($tableRows);
                $tableRows = []; $inTable = false;
            }

            // 代码块占位符还原
            if (preg_match('/^\x00CODE(\d+)\x00$/', $line, $cm)) {
                $html .= self::closeList($inList) . $blocks[(int)$cm[1]] . "\n";
                $inList = null; $inQuote = false;
                continue;
            }

            $trimmed = ltrim($line);
            $indent = strlen($line) - strlen($trimmed);

            // 水平线
            if (preg_match('/^\s*([-*_])\1{2,}\s*$/', $line)) {
                $html .= self::closeList($inList) . "<hr>\n"; $inList = null; $inQuote = false;
                continue;
            }

            // 引用块
            if (strpos($trimmed, '>') === 0) {
                $html .= self::closeList($inList);
                if (!$inQuote) { $html .= "<blockquote>\n"; $inQuote = true; }
                $html .= '<p>' . self::inline(substr($trimmed, 1)) . "</p>\n";
                continue;
            }
            if ($inQuote) { $html .= "</blockquote>\n"; $inQuote = false; }

            // 列表项
            if (preg_match('/^\s*[-*+]\s+(.*)$/', $line, $lm)) {
                if ($inList !== 'ul') { $html .= self::closeList($inList); $html .= "<ul>\n"; $inList = 'ul'; }
                $html .= '<li>' . self::inline($lm[1]) . "</li>\n";
                continue;
            }
            if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $line, $om)) {
                if ($inList !== 'ol') { $html .= self::closeList($inList); $html .= "<ol>\n"; $inList = 'ol'; }
                $html .= '<li>' . self::inline($om[1]) . "</li>\n";
                continue;
            }
            $html .= self::closeList($inList); $inList = null;

            // 标题
            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $hm)) {
                $level = strlen($hm[1]);
                $html .= "<h{$level}>" . self::inline($hm[2]) . "</h{$level}>\n";
                continue;
            }

            // 空行
            if ($trimmed === '') { $html .= "\n"; continue; }

            // 普通段落
            $html .= '<p>' . self::inline($line) . "</p>\n";
        }

        if ($inTable) $html .= self::renderTable($tableRows);
        if ($inQuote) $html .= "</blockquote>\n";
        $html .= self::closeList($inList);

        return trim($html);
    }

    private static function closeList(?string $type): string {
        return $type === 'ul' ? "</ul>\n" : ($type === 'ol' ? "</ol>\n" : '');
    }

    private static function renderTable(array $rows): string {
        if (!$rows) return '';
        $out = "<table>\n<thead><tr>";
        foreach ($rows[0] as $c) $out .= '<th>' . self::inline($c) . '</th>';
        $out .= "</tr></thead>\n<tbody>\n";
        foreach (array_slice($rows, 1) as $r) {
            $out .= '<tr>';
            foreach ($r as $c) $out .= '<td>' . self::inline($c) . '</td>';
            $out .= "</tr>\n";
        }
        return $out . "</tbody></table>\n";
    }

    /** 行内语法：图片 / 链接 / 行内代码 / 加粗 / 斜体 */
    public static function inline(string $text): string {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        // 图片
        $text = preg_replace('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', '<img src="$2" alt="$1" loading="lazy">', $text);
        // 链接
        $text = preg_replace('/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
        // 行内代码
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        // 加粗
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text);
        // 斜体
        $text = preg_replace('/(?<!\*)\*([^*\s][^*]*)\*(?!\*)/', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!_)_([^_\s][^_]*)_(?!_)/', '<em>$1</em>', $text);
        return $text;
    }

    /** 从 Markdown 提取 front-matter（---\ntitle:...\n---），返回 [meta, body] */
    public static function extractFrontMatter(string $md): array {
        $meta = [];
        if (preg_match('/^---\s*\n([\s\S]*?)\n---\s*\n/', $md, $m)) {
            foreach (explode("\n", $m[1]) as $kv) {
                if (preg_match('/^([a-zA-Z_-]+)\s*:\s*(.*)$/', trim($kv), $km)) {
                    $meta[strtolower($km[1])] = trim($km[2], " \"'");
                }
            }
            $md = substr($md, strlen($m[0]));
        }
        return [$meta, $md];
    }
}
