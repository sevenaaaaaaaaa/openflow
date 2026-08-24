<?php
/**
 * CoverRenderer — CSS 渐变封面生成器
 * 按文章分类生成优雅的渐变背景 + 标题蒙版封面
 */
class CoverRenderer {

    // 分类 → 渐变色映射
    const PALETTE = [
        'ai-create'    => ['from' => '#7c3aed', 'to' => '#a78bfa', 'icon' => '🎨', 'name' => 'AI 创作'],
        'ai-marketing' => ['from' => '#059669', 'to' => '#34d399', 'icon' => '📣', 'name' => 'AI 营销'],
        'ai-build'     => ['from' => '#0891b2', 'to' => '#67e8f9', 'icon' => '🏗️', 'name' => 'AI 建站'],
        'ai-code'      => ['from' => '#2563eb', 'to' => '#60a5fa', 'icon' => '💻', 'name' => 'AI 编程'],
        'ai-ops'       => ['from' => '#7c3aed', 'to' => '#c084fc', 'icon' => '⚙️', 'name' => 'AI 运营'],
        'ai-sell'      => ['from' => '#d97706', 'to' => '#fbbf24', 'icon' => '💰', 'name' => 'AI 销售'],
        'ai-data'      => ['from' => '#0d9488', 'to' => '#5eead4', 'icon' => '📊', 'name' => '数据分析'],
        'ai-user'      => ['from' => '#e11d48', 'to' => '#fb7185', 'icon' => '👤', 'name' => '用户运营'],
        'agent'        => ['from' => '#4f46e5', 'to' => '#818cf8', 'icon' => '🤖', 'name' => 'Agent 生态'],
        'trend'        => ['from' => '#ea580c', 'to' => '#fb923c', 'icon' => '🔮', 'name' => '行业趋势'],
    ];

    /**
     * 获取文章分类对应的颜色配置
     */
    public static function palette(string $category): array {
        $parts = explode('/', $category);
        $cat = $parts[0] ?? 'trend';
        return self::PALETTE[$cat] ?? self::PALETTE['trend'];
    }

    /**
     * 生成 CSS 渐变封面 HTML（详情页大图）
     */
    public static function renderDetail(array $article): string {
        $cat = $article['category'] ?? 'trend';
        $p = self::palette($cat);
        $title = htmlspecialchars($article['title'] ?? '');
        $excerpt = htmlspecialchars(mb_substr(strip_tags($article['excerpt'] ?? $article['content'] ?? ''), 0, 80));
        $author = htmlspecialchars($article['author'] ?? '');
        $date = htmlspecialchars(substr($article['created_at'] ?? '', 0, 10));

        return '<div class="art-cover-gradient" style="width:100%;border-radius:var(--r-md);margin-bottom:36px;aspect-ratio:2.2/1;background:linear-gradient(135deg,' . $p['from'] . ',' . $p['to'] . ');position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:flex-end;padding:clamp(20px,4vw,40px);color:#fff">'
            . '<div style="position:absolute;inset:0;background-image:radial-gradient(circle at 20% 80%,rgba(255,255,255,.08) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(255,255,255,.06) 0%,transparent 50%)"></div>'
            . '<div style="position:absolute;top:20px;left:24px;display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;opacity:.85">'
            . '<span>' . $p['icon'] . '</span><span>' . htmlspecialchars($p['name']) . '</span>'
            . '</div>'
            . '<div style="position:relative">'
            . '<h1 style="font-size:clamp(22px,3vw,36px);font-weight:800;line-height:1.2;letter-spacing:-.02em;margin:0 0 10px">' . $title . '</h1>'
            . ($excerpt ? '<p style="font-size:14px;line-height:1.6;opacity:.75;margin:0 0 12px;max-width:600px">' . $excerpt . '</p>' : '')
            . ($author ? '<div style="font-size:12px;opacity:.6">' . $author . ($date ? ' · ' . $date : '') . '</div>' : '')
            . '</div></div>';
    }

    /**
     * 生成 CSS 渐变封面 HTML（列表页卡片封面）
     */
    public static function renderCard(array $article): string {
        $cat = $article['category'] ?? 'trend';
        $p = self::palette($cat);
        $title = htmlspecialchars(mb_substr($article['title'] ?? '', 0, 40));

        return '<div style="aspect-ratio:16/9;background:linear-gradient(135deg,' . $p['from'] . ',' . $p['to'] . ');position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:flex-end;padding:14px 16px">'
            . '<div style="position:absolute;inset:0;background-image:radial-gradient(circle at 30% 70%,rgba(255,255,255,.1) 0%,transparent 50%)"></div>'
            . '<div style="position:relative;font-size:clamp(13px,1.5vw,16px);font-weight:700;color:#fff;line-height:1.3">' . $title . '</div>'
            . '<div style="position:relative;font-size:11px;color:rgba(255,255,255,.7);margin-top:4px">' . $p['icon'] . ' ' . htmlspecialchars($p['name']) . '</div>'
            . '</div>';
    }

    /**
     * 判断文章是否使用 CSS 封面（无真实图片封面）
     */
    public static function usesCssCover(array $article): bool {
        $cover = $article['cover'] ?? '';
        if (empty($cover)) return true;
        // 资产池封面（循环分配的）也替换为 CSS 封面
        if (strpos($cover, 'assets/images/') === 0) return true;
        return false;
    }

    /**
     * 输出文章封面（优先图片，降级 CSS 渐变）
     */
    public static function renderDetailCover(array $article): string {
        if (self::usesCssCover($article)) {
            return self::renderDetail($article);
        }
        $cover = $article['cover'] ?? '';
        $coverUrl = strpos($cover, 'http') === 0 ? $cover : SITE_URL . '/' . ltrim($cover, '/');
        $alt = htmlspecialchars($article['title'] ?? '');
        return '<img class="art-cover" src="' . htmlspecialchars($coverUrl) . '" alt="' . $alt . '" loading="lazy">';
    }

    /**
     * 输出文章卡片封面（列表页用）
     */
    public static function renderCardCover(array $article): string {
        if (self::usesCssCover($article)) {
            return self::renderCard($article);
        }
        $cover = $article['cover'] ?? '';
        $coverUrl = strpos($cover, 'http') === 0 ? $cover : SITE_URL . '/' . ltrim($cover, '/');
        return '<img src="' . htmlspecialchars($coverUrl) . '" alt="" loading="lazy" style="width:100%;aspect-ratio:16/9;object-fit:cover">';
    }
}
