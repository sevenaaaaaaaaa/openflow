<?php
/**
 * 爬虫 / AI 爬虫检测与友好响应工具
 *
 * 用途：
 *  1. 检测当前访客是标准爬虫还是 AI 爬虫
 *  2. 对爬虫提供友好的 HTML（SSR 首屏内容就绪、无 JS 依赖）
 *  3. 可跳过广告/弹窗等对爬虫无意义的部分
 *
 * 用法：
 *  ​$crawler = CrawlerDetect::detect();      // 返回 ['is_crawler'=>bool, 'type'=>'google|ai|bot|null', 'name'=>string]
 *  if ($crawler['is_crawler']) { /* 输出完整 SSR / 跳过懒加载 *​/ }
 */
class CrawlerDetect {
    /** AI 爬虫 UA 特征（抓取内容用于 LLM 训练/引用） */
    const AI_PATTERNS = [
        'GPTBot', 'ChatGPT-User', 'OAI-SearchBot',
        'ClaudeBot', 'anthropic-ai', 'Claude-Web',
        'PerplexityBot', 'Perplexity-User',
        'Google-Extended', 'Gemini', 'Google-CloudVertexBot',
        'cohere-ai', 'meta-externalagent', 'Amazonbot',
        'Applebot-Extended', 'CopilotBot', 'Bingbot-Extended',
        'Xbot', 'Bytespider', 'zhihu-crawler', 'Diffbot',
        'CCBot', 'KangarooBot', 'ImagesiftBot', 'YouBot', 'Applebot',
    ];

    /** 标准搜索引擎爬虫 */
    const SEARCH_PATTERNS = [
        'Googlebot', 'Bingbot', 'Baiduspider', 'Sogou', '360Spider',
        'YandexBot', 'DuckDuckBot', 'Yahoo', 'SeznamBot', 'facebookexternalhit',
        'Twitterbot', 'LinkedInBot', 'Pinterest', 'Slurp',
    ];

    /**
     * 检测当前请求的爬虫类型
     */
    public static function detect(): array {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '') {
            // 无 UA：可能是命令行/脚本，保守视为非爬虫
            return ['is_crawler' => false, 'type' => null, 'name' => null, 'ua' => $ua];
        }
        foreach (self::AI_PATTERNS as $p) {
            if (stripos($ua, $p) !== false) return ['is_crawler' => true, 'type' => 'ai', 'name' => $p, 'ua' => $ua];
        }
        foreach (self::SEARCH_PATTERNS as $p) {
            if (stripos($ua, $p) !== false) return ['is_crawler' => true, 'type' => 'search', 'name' => $p, 'ua' => $ua];
        }
        // 通用爬虫/机器人特征
        if (preg_match('/bot|crawler|spider|slurp|fetch|curl|wget|headless|python-requests/i', $ua)) {
            return ['is_crawler' => true, 'type' => 'bot', 'name' => 'generic', 'ua' => $ua];
        }
        return ['is_crawler' => false, 'type' => null, 'name' => null, 'ua' => $ua];
    }

    /**
     * 是否为 AI 爬虫（需要 SSR 完整内容）
     */
    public static function isAi(): bool {
        return (self::detect()['type'] ?? '') === 'ai';
    }

    /**
     * 是否为任意爬虫
     */
    public static function isCrawler(): bool {
        return self::detect()['is_crawler'] ?? false;
    }
}
