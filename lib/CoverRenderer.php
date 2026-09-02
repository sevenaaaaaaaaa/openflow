<?php
/**
 * CoverRenderer — 生成式封面（无图文章 / 课程 / 资产用）
 *
 * v8（2026-09-02）：从「按分类硬编码 hex 渐变 + emoji」改为 token 驱动。
 * 输出只带 class，颜色全部来自 assets/modules.css 的 .gcov（用 --gc 色相变量 + color-mix 派生），
 * 亮暗两色自动成立，与站点其它零件同一套调色板。
 *
 *   CoverRenderer::renderCardCover($article)    列表卡封面（放进 .a-card .cov 或任何 16:9 容器）
 *   CoverRenderer::renderDetailCover($article)  正文页大图（有图出 <img>，无图出 .gcov.lg 横幅）
 *   CoverRenderer::usesCssCover($article)       是否走生成式封面
 *   CoverRenderer::palette($category)           ['hue','code','name','icon']
 */
class CoverRenderer {

    /** 分类 → 色相（accent / ok / warn / danger）、短代号、名称、线框图标 path */
    const PALETTE = [
        'ai-create'    => ['hue' => 'accent', 'code' => 'CREATE', 'name' => 'AI 创作',    'icon' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>'],
        'ai-marketing' => ['hue' => 'ok',     'code' => 'MKT',    'name' => 'AI 营销',    'icon' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1Z"/><path d="M15 9a3 3 0 0 1 0 6M18 6a7 7 0 0 1 0 12"/>'],
        'ai-build'     => ['hue' => 'ok',     'code' => 'BUILD',  'name' => 'AI 建站',    'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 20V9"/>'],
        'ai-code'      => ['hue' => 'accent', 'code' => 'CODE',   'name' => 'AI 编程',    'icon' => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 4l-4 16"/>'],
        'ai-ops'       => ['hue' => 'warn',   'code' => 'OPS',    'name' => 'AI 运营',    'icon' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>'],
        'ai-sell'      => ['hue' => 'ok',     'code' => 'SELL',   'name' => 'AI 销售',    'icon' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>'],
        'ai-data'      => ['hue' => 'accent', 'code' => 'DATA',   'name' => '数据分析',   'icon' => '<path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/>'],
        'ai-user'      => ['hue' => 'danger', 'code' => 'USER',   'name' => '用户运营',   'icon' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-5-6.3"/>'],
        'agent'        => ['hue' => 'accent', 'code' => 'AGENT',  'name' => 'Agent 生态', 'icon' => '<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M8 13h.01M16 13h.01M9 17h6"/>'],
        'trend'        => ['hue' => 'warn',   'code' => 'TREND',  'name' => '行业趋势',   'icon' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>'],
        // 旧数据里的分类 key
        'insight'      => ['hue' => 'warn',   'code' => 'INSIGHT','name' => '增长洞察',   'icon' => '<path d="M12 3a6 6 0 0 0-4 10.5V16h8v-2.5A6 6 0 0 0 12 3ZM10 20h4"/>'],
        'content'      => ['hue' => 'ok',     'code' => 'CONTENT','name' => '内容',       'icon' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>'],
        'ai-agent'     => ['hue' => 'accent', 'code' => 'AGENT',  'name' => 'Agent 实践', 'icon' => '<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M8 13h.01M16 13h.01M9 17h6"/>'],
    ];
    const FALLBACK = ['hue' => 'neutral', 'code' => 'NOTE', 'name' => '文章', 'icon' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/>'];

    /** 分类 key（含 a/b 子分类）→ 调色 */
    public static function palette(string $category): array {
        $cat = explode('/', $category)[0] ?? '';
        return self::PALETTE[$cat] ?? self::FALLBACK;
    }

    public static function svg(string $path): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    }

    /** 分类名：优先站点分类表里的名字，没有再用内置名 */
    private static function catName(array $item, array $p): string {
        $key = $item['category'] ?? '';
        if ($key !== '' && function_exists('get_categories')) {
            foreach (get_categories('article') as $c) if (($c['key'] ?? '') === $key) return (string)$c['name'];
        }
        return $p['name'];
    }

    /** 列表卡封面（16:9）。卡片正文已有标题，默认不重复；独立使用（无正文）时传 $withTitle = true */
    public static function renderCard(array $item, bool $withTitle = false): string {
        $p = self::palette($item['category'] ?? '');
        $title = htmlspecialchars(mb_substr($item['title'] ?? '', 0, 48));
        return '<div class="gcov h-' . $p['hue'] . '">'
            . '<span class="gc-code" aria-hidden="true">' . $p['code'] . '</span>'
            . '<span class="gc-k">' . self::svg($p['icon']) . htmlspecialchars(self::catName($item, $p)) . '</span>'
            . ($withTitle ? '<span class="gc-t">' . $title . '</span>' : '')
            . '</div>';
    }

    /** 正文页横幅（无图时） */
    public static function renderDetail(array $item): string {
        $p = self::palette($item['category'] ?? '');
        $title = htmlspecialchars($item['title'] ?? '');
        $excerpt = htmlspecialchars(mb_substr(strip_tags($item['excerpt'] ?? $item['content'] ?? ''), 0, 80));
        $author = htmlspecialchars($item['author'] ?? '');
        $date = htmlspecialchars(substr($item['created_at'] ?? '', 0, 10));
        return '<div class="gcov lg h-' . $p['hue'] . '">'
            . '<span class="gc-code" aria-hidden="true">' . $p['code'] . '</span>'
            . '<span class="gc-k">' . self::svg($p['icon']) . htmlspecialchars(self::catName($item, $p)) . '</span>'
            . '<h1 class="gc-t">' . $title . '</h1>'
            . ($excerpt ? '<p class="gc-d">' . $excerpt . '</p>' : '')
            . ($author || $date ? '<span class="gc-m">' . $author . ($author && $date ? ' · ' : '') . $date . '</span>' : '')
            . '</div>';
    }

    /** 是否走生成式封面（没图，或用的是循环分配的资产池占位图） */
    public static function usesCssCover(array $item): bool {
        $cover = $item['cover'] ?? '';
        if (empty($cover)) return true;
        if (strpos($cover, 'assets/images/') === 0) return true;
        return false;
    }

    private static function coverUrl(string $cover): string {
        return strpos($cover, 'http') === 0 ? $cover : (defined('SITE_URL') ? SITE_URL : '') . '/' . ltrim($cover, '/');
    }

    /** 正文封面：优先图片，无图出横幅 */
    public static function renderDetailCover(array $item): string {
        if (self::usesCssCover($item)) return self::renderDetail($item);
        return '<img class="art-cover" src="' . htmlspecialchars(self::coverUrl($item['cover'])) . '" alt="' . htmlspecialchars($item['title'] ?? '') . '" loading="lazy">';
    }

    /** 列表卡封面：优先图片，无图出生成式封面 */
    public static function renderCardCover(array $item): string {
        if (self::usesCssCover($item)) return self::renderCard($item);
        return '<img src="' . htmlspecialchars(self::coverUrl($item['cover'])) . '" alt="" loading="lazy">';
    }
}
