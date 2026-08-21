<?php
/**
 * 个性化推荐引擎 Personalizer
 * 基于用户画像（标签偏好/分类偏好/会员等级/消费行为/来源渠道）
 * 对内容（文章/课程/落地页）打分排序，输出个性化推荐
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/CdpSystem.php';
require_once __DIR__ . '/IdentityResolver.php';

class Personalizer {
    /**
     * 构建用户画像偏好（从 CDP + Identity 图谱 + 浏览历史推导）
     * @return array ['tags'=>[tag=>weight], 'categories'=>[cat=>weight], 'member_level'=>'', 'tags_negative'=>[]]
     */
    public static function buildProfile(string $visitorId = '', string $memberId = '', string $email = ''): array {
        $pref = ['tags' => [], 'categories' => [], 'member_level' => '', 'source' => '', 'total_spent' => 0];

        // 游客无任何标识：跳过 CDP 全量加载，避免每次请求 OOM/拖垮服务器
        if ($visitorId === '' && $memberId === '' && $email === '') return $pref;

        // 1. CDP 画像标签
        $canonical = IdentityResolver::resolve($visitorId, $memberId, $email);
        $profiles = CdpSystem::allProfiles();
        $cdp = $canonical ? ($profiles[$canonical] ?? null) : null;
        if (!$cdp && $visitorId) $cdp = $profiles[$visitorId] ?? null;

        if ($cdp) {
            foreach ($cdp['tags'] ?? [] as $t) {
                $pref['tags'][$t] = ($pref['tags'][$t] ?? 0) + 5;
            }
            $p = $cdp['properties'] ?? [];
            $pref['member_level'] = $p['member_level'] ?? '';
            $pref['source'] = $p['source'] ?? '';
            $pref['total_spent'] = (float)($p['total_spent'] ?? 0);
            // 属性里的分类/主题偏好
            foreach (['pref_category', 'category', 'interest'] as $k) {
                if (!empty($p[$k])) $pref['categories'][$p[$k]] = 5;
            }
        }

        // 2. 浏览历史（事件）加权
        try {
            $events = CdpSystem::allEvents();
            foreach (array_slice($events, -200) as $e) {
                $eid = $e['visitor_id'] ?? '';
                if ($canonical && $eid !== $canonical) continue;
                if (!$canonical && $visitorId && $eid !== $visitorId) continue;
                if (($e['event'] ?? '') !== 'article_view') continue;
                $props = $e['properties'] ?? [];
                foreach (($props['tags'] ?? []) as $t) $pref['tags'][$t] = ($pref['tags'][$t] ?? 0) + 3;
                if (!empty($props['category'])) $pref['categories'][$props['category']] = ($pref['categories'][$props['category']] ?? 0) + 3;
            }
        } catch (Exception $e) {}

        // 3. 会员等级加成
        if ($pref['member_level'] === 'vip') $pref['member_level'] = 'vip';
        return $pref;
    }

    /**
     * 文章推荐（按画像打分）
     * @return array [id => score]
     */
    public static function recommendArticles(array $pref, int $limit = 6, string $excludeId = ''): array {
        $articles = get_articles();
        $scored = [];
        foreach ($articles as $a) {
            if (($a['status'] ?? '') !== 'published') continue;
            if ($excludeId && $a['id'] === $excludeId) continue;
            $score = 0;

            // 标签匹配：用户偏好标签与文章标签重叠度
            $aTags = $a['tags'] ?? [];
            foreach ($aTags as $t) {
                if (isset($pref['tags'][$t])) $score += $pref['tags'][$t] * 2;
            }
            // 分类匹配
            $aCat = $a['category'] ?? '';
            if ($aCat && isset($pref['categories'][$aCat])) $score += $pref['categories'][$aCat] * 3;
            // 热度加成
            $score += min(10, (int)($a['views'] ?? 0) / 20);
            // 时效性加成（近30天加分）
            if (!empty($a['created_at']) && strtotime($a['created_at']) > time() - 30 * 86400) $score += 3;
            // 会员等级内容
            if (!empty($a['member_only']) && $pref['member_level'] !== 'vip') continue;

            if ($score > 0) $scored[$a['id']] = ['score' => $score, 'article' => $a];
        }

        uasort($scored, fn($x, $y) => $y['score'] <=> $x['score']);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as $id => $v) $out[$id] = $v['score'];
        return $out;
    }

    /**
     * 个性化商品推荐（基于画像标签/偏好 → 生态市场商品）
     */
    public static function recommendProducts(array $pref, int $limit = 4): array {
        if (!class_exists('CommerceSystem')) return [];
        try {
            $products = array_values(array_filter(CommerceSystem::allPublished(), fn($p) => (float)($p['pricing']['price'] ?? 0) > 0));
        } catch (Exception $e) { return []; }
        $scored = [];
        foreach ($products as $p) {
            $score = 0;
            foreach (($p['tags'] ?? []) as $t) {
                if (isset($pref['tags'][$t])) $score += $pref['tags'][$t] * 2;
            }
            $hay = ($p['title'] ?? '') . ' ' . ($p['description'] ?? '');
            foreach (array_keys($pref['tags'] ?? []) as $pt) {
                if (mb_strpos($hay, $pt) !== false) $score += 2;
            }
            if ($score > 0) $scored[$p['id']] = $score;
        }
        uasort($scored, fn($x, $y) => $y <=> $x);
        $ids = array_slice(array_keys($scored), 0, $limit);
        if (count($ids) < $limit) {
            foreach ($products as $p) {
                if (count($ids) >= $limit) break;
                if (!in_array($p['id'], $ids, true)) $ids[] = $p['id']; // 热门兜底
            }
        }
        $out = [];
        foreach ($ids as $pid) { $out[$pid] = $scored[$pid] ?? 0; }
        return $out;
    }

    /**
     * 个性化课程推荐（基于画像偏好 → 有价课程）
     */
    public static function recommendCourses(array $pref, int $limit = 3): array {
        try {
            $shopCfg = shop_settings();
            $courses = array_values(array_filter(json_read(DATA_DIR . '/courses/index.json'), fn($c) => ($c['status'] ?? '') === 'published' && (float)($shopCfg['course_prices'][$c['id']] ?? 0) > 0));
        } catch (Exception $e) { return []; }
        $scored = [];
        foreach ($courses as $c) {
            $score = 0;
            foreach (($c['tags'] ?? []) as $t) {
                if (isset($pref['tags'][$t])) $score += $pref['tags'][$t] * 2;
            }
            $cat = $c['category'] ?? $c['type'] ?? '';
            if ($cat && isset($pref['categories'][$cat])) $score += $pref['categories'][$cat] * 3;
            if ($score > 0) $scored[$c['id']] = $score;
        }
        uasort($scored, fn($x, $y) => $y <=> $x);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as $id => $s) $out[$id] = $s;
        return $out;
    }

    /**
     * 相关文章推荐（基于当前文章 + 用户偏好）
     */
    public static function relatedArticles(string $currentId, array $pref, int $limit = 4): array {
        $current = get_article($currentId);
        if (!$current) return [];
        $articles = get_articles();
        $scored = [];
        $curTags = $current['tags'] ?? [];
        $curCat = $current['category'] ?? '';

        foreach ($articles as $a) {
            if ($a['id'] === $currentId || ($a['status'] ?? '') !== 'published') continue;
            $score = 0;
            $aTags = $a['tags'] ?? [];
            // 与当前文章标签重叠
            $overlap = array_intersect($curTags, $aTags);
            $score += count($overlap) * 4;
            // 同分类
            if ($curCat && ($a['category'] ?? '') === $curCat) $score += 2;
            // 用户偏好加权
            foreach ($aTags as $t) if (isset($pref['tags'][$t])) $score += $pref['tags'][$t];
            $score += min(5, (int)($a['views'] ?? 0) / 50);
            if ($score > 0) $scored[$a['id']] = ['score' => $score, 'article' => $a];
        }
        uasort($scored, fn($x, $y) => $y['score'] <=> $x['score']);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as $id => $v) $out[$id] = $v['article'];
        return $out;
    }

    /**
     * 个性化 CTA（根据画像选择最合适的转化组件）
     * @return array 返回 CTA 配置
     */
    public static function personalizedCta(array $pref): array {
        $conversion = json_read(DATA_DIR . '/conversion.json');
        $inlines = $conversion['inline_cta'] ?? [];
        // 高价值/会员用户 → 高级转化
        if (($pref['total_spent'] ?? 0) >= 1000 || ($pref['member_level'] ?? '') === 'vip') {
            return ['title' => '解锁高级内容', 'desc' => '成为会员，获得专属深度内容', 'action' => 'upgrade'];
        }
        // 有消费意向
        if (($pref['tags']['已购'] ?? 0) > 0) {
            return ['title' => '继续学习', 'desc' => '查看你的课程进度', 'action' => 'continue'];
        }
        // 默认 CTA
        if (!empty($inlines['enabled'])) {
            return ['title' => $inlines['default_title'] ?? '预约增长诊断', 'desc' => $inlines['default_description'] ?? '', 'action' => 'form'];
        }
        return ['title' => '预约增长诊断', 'desc' => '30 分钟了解你的增长机会', 'action' => 'form'];
    }

    /**
     * 分群内容推荐（供聚合页个性化排序）
     */
    public static function rankForProfile(array $articles, array $pref): array {
        $scored = [];
        foreach ($articles as $a) {
            $score = 0;
            foreach (($a['tags'] ?? []) as $t) if (isset($pref['tags'][$t])) $score += $pref['tags'][$t];
            if (isset($pref['categories'][$a['category'] ?? ''])) $score += $pref['categories'][$a['category']];
            $scored[] = ['article' => $a, 'score' => $score];
        }
        uasort($scored, fn($x, $y) => $y['score'] <=> $x['score']);
        return array_column($scored, 'article');
    }
}
