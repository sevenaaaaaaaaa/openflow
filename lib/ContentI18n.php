<?php
/**
 * ContentI18n —— 内容多语言（AUDIT-01 P0 / BACKLOG T0-3）
 *
 * 【为什么】站点已有 I18n（界面语言包 + /en//zh-CN/ 前缀 locale 检测），但**内容**
 * 仍是单语：一篇文章只有一份 title/content。对宣称出海/GEO 的定位是硬伤。
 * 本模块给文章加「语言版本」：同一篇文章挂多个 locale 的译文（关联组），
 * 前台按当前 locale 取译文并输出 hreflang，另配 AI 一键初译。
 *
 * 【存储】译文直接挂在文章记录里 $article['i18n'][locale] = {title,content,seo_title,
 * seo_desc,status}，随文章一起存，不另起文件。base locale = i18n 默认语言。
 */

require_once __DIR__ . '/I18n.php';

if (!function_exists('ci18n_base_locale')) {

    function ci18n_base_locale(): string {
        return function_exists('i18n_default_locale') ? i18n_default_locale() : 'zh-CN';
    }

    /** 该文章的译文表（已填了 title 的 locale 才算数）。 */
    function ci18n_translations(array $article): array {
        $t = $article['i18n'] ?? [];
        return is_array($t) ? $t : [];
    }

    /** 该文章拥有的全部 locale：base + 有译文的。 */
    function ci18n_locales(array $article): array {
        $out = [ci18n_base_locale()];
        foreach (ci18n_translations($article) as $loc => $tr) {
            if (is_array($tr) && trim((string)($tr['title'] ?? '')) !== '' && !in_array($loc, $out, true)) $out[] = $loc;
        }
        return $out;
    }

    /**
     * 按 locale 解析出用于展示的文章：base 或 base 之上覆盖译文字段。
     * 只覆盖 title/content/seo_title/seo_desc，其它字段(封面/作者/标签…)保持 base。
     * 译文缺失或未发布 → 回落 base（不因缺译而 404）。
     */
    function ci18n_resolve(array $article, ?string $locale = null): array {
        $locale = $locale ?: (function_exists('i18n_current') ? i18n_current() : ci18n_base_locale());
        if ($locale === ci18n_base_locale()) return $article;
        $tr = ci18n_translations($article)[$locale] ?? null;
        if (!is_array($tr) || trim((string)($tr['title'] ?? '')) === '') return $article;
        if (($tr['status'] ?? 'published') !== 'published') return $article;
        foreach (['title', 'content', 'seo_title', 'seo_desc'] as $k) {
            if (isset($tr[$k]) && $tr[$k] !== '') $article[$k] = $tr[$k];
        }
        $article['_locale'] = $locale;
        return $article;
    }

    /**
     * 生成 hreflang <link> 标签（base + 各译文 + x-default）。
     * $origin 形如 https://host；slug 为文章 slug。语言前缀走 /{locale}/。
     */
    function ci18n_hreflang(array $article, string $origin, string $pathTpl = '/article/'): string {
        $slug = (string)($article['slug'] ?? '');
        if ($slug === '') return '';
        $base = ci18n_base_locale();
        $out = [];
        foreach (ci18n_locales($article) as $loc) {
            $prefix = ($loc === $base) ? '' : '/' . $loc;
            $url = rtrim($origin, '/') . $prefix . $pathTpl . rawurlencode($slug);
            $hl = strtolower($loc);
            $out[] = '<link rel="alternate" hreflang="' . htmlspecialchars($hl) . '" href="' . htmlspecialchars($url) . '">';
        }
        // x-default 指向 base
        $out[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars(rtrim($origin, '/') . $pathTpl . rawurlencode($slug)) . '">';
        return implode("\n", $out);
    }

    /**
     * 写入某 locale 的译文（挂到文章 i18n 上并存盘）。
     * $fields: title/content/seo_title/seo_desc/status。返回 ['ok'=>bool]。
     */
    function ci18n_set(string $articleId, string $locale, array $fields): array {
        if (!function_exists('get_article') || !function_exists('save_article')) return ['ok' => false, 'error' => 'no_store'];
        $article = get_article($articleId);
        if (!$article) return ['ok' => false, 'error' => '文章不存在'];
        if ($locale === ci18n_base_locale()) return ['ok' => false, 'error' => '不能给基准语言建译文'];
        $i18n = ci18n_translations($article);
        $i18n[$locale] = [
            'title'      => trim((string)($fields['title'] ?? '')),
            'content'    => (string)($fields['content'] ?? ''),
            'seo_title'  => trim((string)($fields['seo_title'] ?? '')),
            'seo_desc'   => trim((string)($fields['seo_desc'] ?? '')),
            'status'     => in_array($fields['status'] ?? 'draft', ['draft', 'published'], true) ? $fields['status'] : 'draft',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $article['i18n'] = $i18n;
        save_article($articleId, $article);
        return ['ok' => true, 'translation' => $i18n[$locale]];
    }

    function ci18n_delete(string $articleId, string $locale): bool {
        if (!function_exists('get_article') || !function_exists('save_article')) return false;
        $article = get_article($articleId);
        if (!$article) return false;
        $i18n = ci18n_translations($article);
        if (!isset($i18n[$locale])) return false;
        unset($i18n[$locale]);
        $article['i18n'] = $i18n;
        save_article($articleId, $article);
        return true;
    }

    /**
     * AI 一键初译：把 base 的 title/content/seo 译到目标 locale，返回字段（不落盘）。
     * 未配置 AI → ['ok'=>false]。译文默认 status=draft，交人复核后发布。
     */
    function ci18n_ai_translate(array $article, string $locale): array {
        if (!class_exists('AiCenter') || !\AiCenter::isConfigured()) return ['ok' => false, 'error' => 'AI 未配置'];
        $target = function_exists('i18n_native') ? i18n_native($locale) : $locale;
        $payload = json_encode([
            'title' => (string)($article['title'] ?? ''),
            'seo_title' => (string)($article['seo_title'] ?? ''),
            'seo_desc' => (string)($article['seo_desc'] ?? ''),
            'content' => (string)($article['content'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);
        try {
            $r = \AiCenter::json(
                "你是专业本地化译者。把给定 JSON 里的网页文章字段翻译成【{$target}】，"
                . "保留 HTML 结构与标签不动，只译文字；输出同结构 JSON："
                . '{"title":"","seo_title":"","seo_desc":"","content":""}，不要多余文字。',
                $payload,
                ['max_tokens' => 4000, 'feature' => 'content_i18n', 'tier' => 'batch']
            );
            if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? 'AI 翻译失败'];
            $data = is_array($r['data'] ?? null) ? $r['data'] : [];
            if (empty($data['title']) && empty($data['content'])) return ['ok' => false, 'error' => 'AI 返回为空'];
            return ['ok' => true, 'fields' => [
                'title' => (string)($data['title'] ?? ''),
                'content' => (string)($data['content'] ?? ''),
                'seo_title' => (string)($data['seo_title'] ?? ''),
                'seo_desc' => (string)($data['seo_desc'] ?? ''),
                'status' => 'draft',
            ]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
