<?php
/**
 * SearchIndex —— 站内搜索 FTS5 索引（AUDIT-01 P0 / BACKLOG T0-4）
 *
 * 【为什么】原 SearchEngine 每次搜索都线性 mb_stripos 扫全部文章——数据一多就废。
 * 本层用 SQLite FTS5（trigram 分词，支持中文子串）建索引，搜索走索引 + bm25 排序 +
 * 高亮片段。零外部依赖（PHP 内置 SQLite FTS5）。
 *
 * 【策略】索引成本放到写入侧（文章保存时重建，写少）而非搜索侧（读多）；
 * trigram 需 ≥3 字才走索引，2 字（中文双字词很常见）回落 LIKE 扫索引表（compact，够快）。
 * 依赖仅 Database；rebuild 可注入文章列表，便于隔离测试。
 */

require_once __DIR__ . '/Database.php';

if (!function_exists('search_index_ensure')) {

    function search_index_ensure(): void {
        static $ready = false;
        if ($ready) return;
        try {
            Database::conn()->exec("CREATE VIRTUAL TABLE IF NOT EXISTS search_articles USING fts5(
                slug UNINDEXED, title, body, cover UNINDEXED, category UNINDEXED, date UNINDEXED,
                tokenize='trigram'
            )");
            $ready = true;
        } catch (\Throwable $e) { /* FTS5 不可用则留给调用方回落 */ }
    }

    function search_index_available(): bool {
        search_index_ensure();
        try { Database::query("SELECT 1 FROM search_articles LIMIT 1"); return true; }
        catch (\Throwable $e) { return false; }
    }

    /** 组装一篇文章的可搜索 body（标题外的正文/摘要/标签，正文去标签限长）。 */
    function _search_article_body(array $a): string {
        $parts = [
            (string)($a['seo_desc'] ?? ''), (string)($a['excerpt'] ?? ''),
            implode(' ', (array)($a['tags'] ?? [])),
            trim(preg_replace('/\s+/u', ' ', preg_replace('/<[^>]+>/', ' ', (string)($a['content'] ?? '')))),
        ];
        $body = trim(implode(' ', array_filter($parts)));
        if (function_exists('mb_substr') && mb_strlen($body) > 6000) $body = mb_substr($body, 0, 6000);
        return $body;
    }

    /**
     * 全量重建文章索引。$articles 为 null 时用 get_articles()/ARTICLES_DIR 读取。
     * 只索引已发布文章。写入侧调用（文章保存/发布时）。
     */
    function search_index_rebuild(?array $articles = null): int {
        search_index_ensure();
        if ($articles === null) {
            if (function_exists('get_articles')) $articles = get_articles();
            elseif (defined('ARTICLES_DIR') && function_exists('json_read')) $articles = json_read(ARTICLES_DIR . '/index.json');
            else $articles = [];
        }
        $conn = Database::conn();
        $own = !$conn->inTransaction();
        if ($own) $conn->beginTransaction();
        try {
            Database::execute("DELETE FROM search_articles");
            $n = 0;
            foreach ($articles as $a) {
                if (($a['status'] ?? 'draft') !== 'published') continue;
                Database::execute(
                    "INSERT INTO search_articles (slug, title, body, cover, category, date) VALUES (?,?,?,?,?,?)",
                    [(string)($a['slug'] ?? ''), (string)($a['title'] ?? ''), _search_article_body($a),
                     (string)($a['cover'] ?? ''), (string)($a['category'] ?? ''), (string)($a['created_at'] ?? '')]
                );
                $n++;
            }
            if ($own) $conn->commit();
            return $n;
        } catch (\Throwable $e) {
            if ($own && $conn->inTransaction()) $conn->rollBack();
            return 0;
        }
    }

    /** FTS5 查询字符串转义：整体作短语，双引号翻倍。 */
    function _search_fts_phrase(string $q): string {
        return '"' . str_replace('"', '""', $q) . '"';
    }

    /**
     * 查询已索引文章。返回 [{slug,title,cover,category,date,snippet}]。
     * ≥3 字走 FTS5 MATCH + bm25 排序 + 高亮；<3 字回落 LIKE。索引空则先冷建。
     */
    function search_index_query(string $q, int $limit = 12): array {
        $q = trim($q);
        if ($q === '' || !search_index_available()) return [];
        // 冷启动：空则用现有文章建一次
        $cnt = Database::query("SELECT count(*) c FROM search_articles");
        if ((int)($cnt[0]['c'] ?? 0) === 0) search_index_rebuild();

        try {
            if (mb_strlen($q) >= 3) {
                $rows = Database::query(
                    "SELECT slug, title, cover, category, date,
                            snippet(search_articles, 2, '<mark>', '</mark>', '…', 12) AS snippet
                     FROM search_articles WHERE search_articles MATCH ?
                     ORDER BY bm25(search_articles) LIMIT ?",
                    [_search_fts_phrase($q), $limit]
                );
            } else {
                $like = '%' . $q . '%';
                $rows = Database::query(
                    "SELECT slug, title, cover, category, date, '' AS snippet
                     FROM search_articles WHERE title LIKE ? OR body LIKE ? LIMIT ?",
                    [$like, $like, $limit]
                );
            }
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
