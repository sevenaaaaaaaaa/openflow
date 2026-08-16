<?php
/**
 * 文章导出与分享
 * - 导出 Markdown / NotebookLM（支持网页导入的 markdown 或纯文本）
 * - 分享到 AI 平台：Codex / Claude / Gemini / 豆包 / 元宝 / ChatGPT
 * 提供 HTML → Markdown 转换 + 各平台分享文案/链接生成
 */
require_once __DIR__ . '/../admin/config.php';

class ArticleExport {
    /**
     * HTML → Markdown 转换
     */
    public static function htmlToMarkdown(string $html): string {
        $html = preg_replace('/\r\n/', "\n", $html);
        $html = preg_replace('/<br\s*\/?>\s*/i', "\n", $html);
        $html = preg_replace('/<\/p>\s*/i', "\n\n", $html);
        $html = preg_replace('/<\/div>\s*/i', "\n", $html);
        $html = preg_replace('/<\/li>\s*/i', "\n", $html);
        $html = preg_replace('/<\/tr>\s*/i', "\n", $html);
        $html = preg_replace('/<\/h([1-6])>\s*/i', "\n\n", $html);

        // 标题
        $html = preg_replace_callback('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', function ($m) {
            $level = (int)$m[1];
            $text = self::inlineToMd($m[2]);
            return str_repeat('#', $level) . ' ' . trim($text) . "\n";
        }, $html);

        // 引用
        $html = preg_replace_callback('/<blockquote[^>]*>(.*?)<\/blockquote>/is', function ($m) {
            $inner = trim(strip_tags($m[1]));
            $lines = explode("\n", $inner);
            foreach ($lines as &$l) $l = '> ' . trim($l);
            return implode("\n", $lines) . "\n\n";
        }, $html);

        // 列表
        $html = preg_replace_callback('/<(ul|ol)[^>]*>(.*?)<\/\1>/is', function ($m) {
            $tag = $m[1];
            preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $m[2], $items);
            $out = '';
            foreach ($items[1] as $i => $item) {
                $prefix = $tag === 'ol' ? ($i + 1) . '. ' : '- ';
                $out .= $prefix . trim(self::inlineToMd($item)) . "\n";
            }
            return $out . "\n";
        }, $html);

        // 表格
        $html = preg_replace_callback('/<table[^>]*>(.*?)<\/table>/is', function ($m) {
            $rows = [];
            if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $m[1], $trs)) {
                foreach ($trs[1] as $tr) {
                    if (preg_match_all('/<(td|th)[^>]*>(.*?)<\/\1>/is', $tr, $cells)) {
                        $rows[] = array_map([self::class, 'inlineToMd'], $cells[2]);
                    }
                }
            }
            if (empty($rows)) return '';
            $out = '';
            $colCount = count($rows[0]);
            $out .= '| ' . implode(' | ', array_map('trim', $rows[0])) . " |\n";
            $out .= '| ' . implode(' | ', array_fill(0, $colCount, '---')) . " |\n";
            for ($i = 1; $i < count($rows); $i++) {
                $row = array_pad($rows[$i], $colCount, '');
                $out .= '| ' . implode(' | ', array_map('trim', $row)) . " |\n";
            }
            return $out . "\n";
        }, $html);

        // 代码块
        $html = preg_replace_callback('/<pre[^>]*>(.*?)<\/pre>/is', function ($m) {
            $code = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            return "```\n" . $code . "\n```\n\n";
        }, $html);

        // 图片
        $html = preg_replace_callback('/<img[^>]*src=["\']([^"\']+)["\'][^>]*alt=["\']([^"\']*)["\']?[^>]*>/i', function ($m) {
            return '![' . $m[2] . '](' . $m[1] . ')' . "\n\n";
        }, $html);
        $html = preg_replace_callback('/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i', function ($m) {
            return '![](' . $m[1] . ')' . "\n\n";
        }, $html);

        // 链接
        $html = preg_replace_callback('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', function ($m) {
            $text = self::inlineToMd($m[2]);
            return '[' . trim($text) . '](' . $m[1] . ')';
        }, $html);

        // 粗体/斜体/删除线/行内代码
        $html = preg_replace('/<strong[^>]*>(.*?)<\/strong>/i', '**$1**', $html);
        $html = preg_replace('/<b[^>]*>(.*?)<\/b>/i', '**$1**', $html);
        $html = preg_replace('/<em[^>]*>(.*?)<\/em>/i', '*$1*', $html);
        $html = preg_replace('/<i[^>]*>(.*?)<\/i>/i', '*$1*', $html);
        $html = preg_replace('/<code[^>]*>(.*?)<\/code>/i', '`$1`', $html);
        $html = preg_replace('/<del[^>]*>(.*?)<\/del>/i', '~~$1~~', $html);

        // 剩余 HTML 标签清理
        $html = preg_replace('/<[^>]+>/', '', $html);
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        // 清理多余空行
        $html = preg_replace("/\n{3,}/", "\n\n", $html);
        return trim($html);
    }

    private static function inlineToMd(string $html): string {
        $html = preg_replace('/<strong[^>]*>(.*?)<\/strong>/i', '**$1**', $html);
        $html = preg_replace('/<b[^>]*>(.*?)<\/b>/i', '**$1**', $html);
        $html = preg_replace('/<em[^>]*>(.*?)<\/em>/i', '*$1*', $html);
        $html = preg_replace('/<i[^>]*>(.*?)<\/i>/i', '*$1*', $html);
        $html = preg_replace('/<code[^>]*>(.*?)<\/code>/i', '`$1`', $html);
        $html = preg_replace('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', '[$2]($1)', $html);
        return trim(strip_tags($html));
    }

    /**
     * 生成文章 Markdown 全文
     */
    public static function toMarkdown(array $article): string {
        $md = '# ' . ($article['title'] ?? '') . "\n\n";
        if (!empty($article['excerpt'])) $md .= '> ' . trim($article['excerpt']) . "\n\n";
        $md .= "---\n\n";
        $content = $article['content'] ?? '';
        // 已是 markdown 则直接追加（避免重复转换）
        $isHtml = str_contains($content, '<') && str_contains($content, '>');
        $md .= $isHtml ? self::htmlToMarkdown($content) : $content;
        return $md;
    }

    /**
     * 支持导出的目标
     */
    public static function targets(): array {
        return [
            'markdown' => ['name' => 'Markdown', 'icon' => '📝', 'desc' => '复制为 Markdown / 下载 .md 文件'],
            'notebooklm' => ['name' => 'NotebookLM', 'icon' => '📓', 'desc' => '导入 Google NotebookLM 研究'],
            'codex' => ['name' => 'Codex', 'icon' => '🤖', 'desc' => '分享到 OpenAI Codex'],
            'claude' => ['name' => 'Claude', 'icon' => '🟠', 'desc' => '分享到 Anthropic Claude'],
            'gemini' => ['name' => 'Gemini', 'icon' => '💎', 'desc' => '分享到 Google Gemini'],
            'doubao' => ['name' => '豆包', 'icon' => '🫘', 'desc' => '分享到字节豆包'],
            'yuanbao' => ['name' => '元宝', 'icon' => '🪙', 'desc' => '分享到腾讯元宝'],
            'chatgpt' => ['name' => 'ChatGPT', 'icon' => '💬', 'desc' => '分享到 ChatGPT'],
        ];
    }

    /**
     * 生成平台分享 prompt（带上下文说明 + 文章内容）
     */
    public static function sharePrompt(array $article, string $target): string {
        $md = self::toMarkdown($article);
        $context = [
            'codex' => '请阅读以下文章，并可以：总结要点、改写、生成代码示例或回答问题。这是我的文章：',
            'claude' => '请阅读以下文章，我需要你帮我分析/总结/改写它。文章内容：',
            'gemini' => '请阅读以下文章并提供你的见解或帮助我优化。文章内容：',
            'doubao' => '请阅读这篇文章，帮我提炼要点或回答问题。文章：',
            'yuanbao' => '请阅读以下文章，帮我总结重点或做进一步分析。文章：',
            'chatgpt' => '请阅读以下文章，帮我分析或总结。文章：',
            'notebooklm' => '这是要导入 NotebookLM 的文章内容（Markdown）：',
        ];
        $intro = $context[$target] ?? '文章内容：';
        return $intro . "\n\n" . $md;
    }

    /**
     * 生成各平台分享 URL（打开对应 AI 平台并预填提示词）
     * @return string 可点击的分享链接
     */
    public static function shareUrl(string $target, string $prompt): string {
        $encoded = urlencode($prompt);
        // 由于 prompt 很长，各平台 URL 长度有限，实际用「复制 prompt」+「打开平台」
        $map = [
            'codex' => 'https://chatgpt.com/codex',
            'claude' => 'https://claude.ai/new',
            'gemini' => 'https://gemini.google.com/app',
            'doubao' => 'https://www.doubao.com/chat/',
            'yuanbao' => 'https://yuanbao.tencent.com/chat',
            'chatgpt' => 'https://chatgpt.com/',
            'notebooklm' => 'https://notebooklm.google.com/',
            'markdown' => '',
        ];
        $base = $map[$target] ?? '';
        // NotebookLM 支持 ?q= 预填，ChatGPT 等部分支持
        if ($target === 'notebooklm') $base .= '?q=' . $encoded;
        return $base;
    }
}
