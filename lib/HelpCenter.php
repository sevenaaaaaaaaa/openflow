<?php
/**
 * HelpCenter — 帮助中心数据层
 * 数据：data/help-center.json {categories:[], articles:[]}
 * 反馈：data/help-feedback.json {slug: {up:n, down:n}}
 */
class HelpCenter
{
    private static ?array $cache = null;

    public static function file(): string { return DATA_DIR . '/help-center.json'; }

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;
        $d = json_read(self::file());
        self::$cache = [
            'categories' => array_values((array)($d['categories'] ?? [])),
            'articles'   => array_values((array)($d['articles'] ?? [])),
        ];
        return self::$cache;
    }

    public static function save(array $data): void
    {
        json_write(self::file(), $data);
        self::$cache = null;
    }

    public static function categories(): array { return self::all()['categories']; }

    public static function category(string $id): ?array
    {
        foreach (self::categories() as $c) if (($c['id'] ?? '') === $id) return $c;
        return null;
    }

    /** 已发布文章（可按分类过滤，按 order/updated_at 排序） */
    public static function articles(string $catId = ''): array
    {
        $list = array_values(array_filter(self::all()['articles'], fn($a) =>
            ($a['status'] ?? 'published') === 'published'
            && ($catId === '' || ($a['cat'] ?? '') === $catId)
        ));
        usort($list, fn($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99) ?: strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
        return $list;
    }

    public static function get(string $slug): ?array
    {
        foreach (self::all()['articles'] as $a) {
            if (($a['slug'] ?? '') === $slug && ($a['status'] ?? 'published') === 'published') return $a;
        }
        return null;
    }

    /** 后台用：含草稿 */
    public static function getRaw(string $id): ?array
    {
        foreach (self::all()['articles'] as $a) if (($a['id'] ?? '') === $id) return $a;
        return null;
    }

    public static function hot(int $limit = 6): array
    {
        $list = array_values(array_filter(self::articles(), fn($a) => !empty($a['hot'])));
        return array_slice($list, 0, $limit);
    }

    /** 客户端即时搜索的数据包（精简字段） */
    public static function searchIndex(): array
    {
        return array_map(fn($a) => [
            'slug' => $a['slug'], 'title' => $a['title'],
            'excerpt' => (string)($a['excerpt'] ?? ''), 'cat' => $a['cat'] ?? '',
        ], self::articles());
    }

    public static function related(array $article, int $limit = 3): array
    {
        $same = array_values(array_filter(self::articles((string)($article['cat'] ?? '')), fn($a) => $a['slug'] !== $article['slug']));
        return array_slice($same, 0, $limit);
    }

    /* ── 反馈 ── */
    public static function feedbackFile(): string { return DATA_DIR . '/help-feedback.json'; }

    public static function feedback(string $slug, string $vote): array
    {
        $all = json_read(self::feedbackFile());
        $all[$slug] = $all[$slug] ?? ['up' => 0, 'down' => 0];
        if ($vote === 'up') $all[$slug]['up']++;
        elseif ($vote === 'down') $all[$slug]['down']++;
        json_write(self::feedbackFile(), $all);
        return $all[$slug];
    }

    public static function feedbackGet(string $slug): array
    {
        $all = json_read(self::feedbackFile());
        return $all[$slug] ?? ['up' => 0, 'down' => 0];
    }
}
