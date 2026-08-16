<?php
require_once __DIR__ . '/Cache.php';

/**
 * 页面级输出缓存 — 大幅减少重复渲染开销
 *
 * 用法（页面顶部）：
 *   $__pc = PageCache::begin('docs', 300);   // 尝试命中缓存并输出，TTL 300s
 *   // ... 正常渲染页面 ...
 *   PageCache::end();                          // 末尾保存缓存
 *
 * 说明：
 *  - 命中缓存直接输出 HTML 并 exit
 *  - 未命中则开启 ob_start，页面末尾 end() 保存
 *  - 登录态/爬虫请求跳过缓存（保证动态与 SEO 新鲜度）
 */
class PageCache {
    private static bool $active = false;

    /**
     * 开始页面缓存。命中则输出并终止。
     * @return bool true=命中缓存已输出（调用方应结束），false=继续渲染
     */
    public static function begin(string $key, int $ttl = 300): bool {
        // 登录态/写请求/爬虫不缓存
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;
        if (!empty($_SESSION['admin_login']) || !empty($_SESSION['member_id'])) return false;
        if (class_exists('CrawlerDetect') && CrawlerDetect::isCrawler()) return false;
        // 禁用缓存的标记（调试）
        if (isset($_GET['nocache'])) return false;

        $cache = new FileCache();
        $html = $cache->get('page:' . $key);
        if ($html !== null) {
            echo $html;
            return true;
        }
        self::$active = true;
        ob_start();
        return false;
    }

    /**
     * 结束页面缓存：保存当前输出缓冲
     */
    public static function end(?string $key = null, int $ttl = 300): void {
        if (!self::$active) return;
        self::$active = false;
        $html = ob_get_clean();
        if ($html) {
            try {
                $cache = new FileCache();
                $cache->set('page:' . ($key ?? ''), $html, $ttl);
            } catch (\Throwable $e) {}
        }
        echo $html;
    }
}
