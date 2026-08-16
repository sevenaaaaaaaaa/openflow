<?php
/**
 * 生长引擎 GrowthEngine
 * 让每个部署实例从"出厂形态"开始，随着使用者的行为逐渐长出独一无二的形态。
 *
 * 核心理念：
 *  - 不是客制化（从预设选项里挑选），而是生长（从行为中涌现结果）
 *  - 部署后的第一天，系统就开始记录使用者的每一次使用、采纳、忽略、偏好
 *  - 每个实例因为使用方式不同，最终长成不同的形态
 *
 * 实现：
 *  1. 行为信号采集 —— 记录"使用指纹"（模块热度、建议采纳/忽略、前台行为）
 *  2. 个性权重 —— 用行为信号调整建议优先级（用得多的优先，被忽略的降权）
 *  3. 进化轨迹 —— 记录"出生→现在"的成长里程碑
 *  4. 形态画像 —— 根据行为总结实例"长成了什么样"
 */
require_once __DIR__ . '/../admin/config.php';

class GrowthEngine {
    private static string $file = DATA_DIR . '/growth.json';

    /* ─── 状态 ─── */

    public static function state(): array {
        $s = json_read(self::$file);
        if (empty($s)) {
            $s = [
                'born_at' => time(),           // 出生时间（首次记录）
                'signals' => [],                // 行为信号计数
                'weights' => [],                // 个性权重（模块→优先级加成）
                'ignored' => [],                // 被忽略的建议
                'milestones' => [],             // 进化里程碑
                'shape' => null,                // 形态画像（后置计算）
                'last_shaped' => 0,
            ];
            json_write(self::$file, $s);
        }
        return $s;
    }

    /* ── 行为信号采集 ── */

    /**
     * 记录一个行为信号
     * @param string $type signal 类型：view_page / resolve_suggestion / ignore_suggestion / use_module / visit
     * @param string $key  信号对象（页面/模块/建议 id）
     * @param int    $weight 权重（默认 1）
     */
    public static function signal(string $type, string $key, int $weight = 1): void {
        $s = self::state();
        $k = $type . ':' . $key;
        $s['signals'][$k] = ($s['signals'][$k] ?? 0) + $weight;
        // 限制信号数量（保留高频）
        if (count($s['signals']) > 800) {
            arsort($s['signals']);
            $s['signals'] = array_slice($s['signals'], 0, 600, true);
        }
        json_write(self::$file, $s);
    }

    /** 记录建议被采纳 */
    public static function suggestionResolved(string $id, string $category): void {
        self::signal('resolve', $category, 2);
        $s = self::state();
        $s['milestones'][] = [
            'ts' => time(), 'type' => 'resolved', 'key' => $id, 'category' => $category,
        ];
        if (count($s['milestones']) > 200) $s['milestones'] = array_slice($s['milestones'], -150);
        json_write(self::$file, $s);
    }

    /** 记录一次进化事件（统一时间线） */
    public static function timeline(string $type, string $title, string $detail = ''): void {
        $s = self::state();
        $s['milestones'][] = [
            'ts' => time(), 'type' => $type, 'key' => $title, 'detail' => $detail,
        ];
        if (count($s['milestones']) > 300) $s['milestones'] = array_slice($s['milestones'], -250);
        json_write(self::$file, $s);
    }

    /** 获取进化时间线（倒序） */
    public static function timelineGet(int $limit = 50): array {
        $s = self::state();
        return array_reverse(array_slice(array_reverse($s['milestones'] ?? []), 0, $limit));
    }

    /** 记录建议被忽略（降权） */
    public static function suggestionIgnored(string $id, string $category): void {
        $s = self::state();
        $s['ignored'][$id] = ($s['ignored'][$id] ?? 0) + 1;
        json_write(self::$file, $s);
    }

    /* ── 个性权重 ── */

    /**
     * 计算某类建议的个性权重加成（-1 ~ +2）
     * 基于：该类建议的采纳率、相关模块的使用热度、被忽略次数
     */
    public static function weightFor(string $category): float {
        $s = self::state();
        $signals = $s['signals'] ?? [];
        $w = 0.0;

        // 1. 采纳信号 → 该类更被看重
        $resolved = $signals['resolve:' . $category] ?? 0;
        if ($resolved > 0) $w += min(1.0, $resolved * 0.3);

        // 2. 相关模块使用热度（view_page:xxx）→ 用得多的优先
        $viewTotal = 0;
        foreach ($signals as $k => $v) {
            if (strpos($k, 'view_page:') === 0) $viewTotal += $v;
        }
        if ($viewTotal > 0) {
            $viewCat = 0;
            foreach ($signals as $k => $v) {
                if (strpos($k, 'view_page:') === 0 && self::pageBelongsTo($k, $category)) $viewCat += $v;
            }
            $w += min(1.0, ($viewCat / max(1, $viewTotal)) * 2);
        }

        // 3. 被忽略 → 降权
        $ignoredCount = 0;
        foreach (($s['ignored'] ?? []) as $id => $c) {
            // 忽略 id 前缀匹配 category
            if (strpos($id, $category) !== false || strpos($category, $id) !== false) $ignoredCount += $c;
        }
        if ($ignoredCount > 0) $w -= min(1.0, $ignoredCount * 0.5);

        return max(-1, min(2, $w));
    }

    /** 页面信号是否属于某类建议 */
    private static function pageBelongsTo(string $signalKey, string $category): bool {
        $map = [
            'content' => ['academy', 'articles', 'downloads', 'podcasts', 'courses', 'topic'],
            'bug' => ['article', 'course', 'download', 'community', 'marketplace'],
            'perf' => ['home', 'docs', 'tools'],
            'routing' => ['category', 'detail'],
        ];
        $pages = $map[$category] ?? [];
        foreach ($pages as $p) if (strpos($signalKey, $p) !== false) return true;
        return false;
    }

    /* ── 形态画像 ── */

    /**
     * 计算当前实例的"形态画像" —— 它长成了什么样
     * @return array ['type'=>, 'label'=>, 'strengths'=>[], 'advice'=>]
     */
    public static function shape(): array {
        $s = self::state();
        $now = time();
        // 每 6 小时重算一次
        if (($s['last_shaped'] ?? 0) > $now - 6 * 3600 && !empty($s['shape'])) return $s['shape'];

        $signals = $s['signals'] ?? [];
        $viewByCat = ['content' => 0, 'growth' => 0, 'sales' => 0, 'community' => 0, 'dev' => 0];
        foreach ($signals as $k => $v) {
            if (strpos($k, 'view_page:') !== 0) continue;
            $key = str_replace('view_page:', '', $k);
            if (preg_match('/academy|articles|downloads|podcasts|courses/', $key)) $viewByCat['content'] += $v;
            elseif (preg_match('/tools|docs|capability|product/', $key)) $viewByCat['growth'] += $v;
            elseif (preg_match('/marketplace|shop|member/', $key)) $viewByCat['sales'] += $v;
            elseif (preg_match('/community/', $key)) $viewByCat['community'] += $v;
            elseif (preg_match('/api|developer|md-docs/', $key)) $viewByCat['dev'] += $v;
        }
        $total = array_sum($viewByCat);
        if ($total < 5) {
            $shape = ['type' => 'seedling', 'label' => '🌱 新生', 'strengths' => [], 'advice' => '还在孕育形态，多使用各模块让系统认识你'];
        } else {
            arsort($viewByCat);
            $top = array_key_first($viewByCat);
            $labels = [
                'content' => '📚 内容中心型', 'growth' => '🚀 增长驱动型',
                'sales' => '💰 商业转化型', 'community' => '💬 社区运营型', 'dev' => '🔧 开发者友好型',
            ];
            $top2 = array_slice($viewByCat, 0, 2, true);
            $strengths = array_map(fn($k) => $labels[$k] ?? $k, array_keys($top2));
            $shape = [
                'type' => $top, 'label' => $labels[$top] ?? '综合型',
                'strengths' => $strengths,
                'advice' => '你的站正在向「' . ($labels[$top] ?? $top) . '」生长，建议重点完善相关模块',
            ];
        }
        $shape['born_at'] = $s['born_at'] ?? $now;
        $shape['days_alive'] = (int)(($now - ($s['born_at'] ?? $now)) / 86400);
        $s['shape'] = $shape;
        $s['last_shaped'] = $now;
        json_write(self::$file, $s);
        return $shape;
    }

    /** 出生以来的天数 */
    public static function daysAlive(): int {
        $s = self::state();
        return (int)((time() - ($s['born_at'] ?? time())) / 86400);
    }

    /**
     * 形态 → 推荐偏好（供前台"为你推荐"使用）
     * 根据当前站点形态，动态调整推荐的内容倾向
     */
    public static function recommendPreferences(): array {
        $shape = self::shape();
        $type = $shape['type'] ?? 'seedling';
        // 形态 → 偏好的分类/标签关键词
        $map = [
            'content' => ['categories' => ['insight', 'article', 'case'], 'tags' => ['内容', '文章', '方法论']],
            'growth' => ['categories' => ['growth', 'seo', 'tool'], 'tags' => ['增长', 'SEO', '获客']],
            'sales' => ['categories' => ['commerce', 'product'], 'tags' => ['商业', '转化', '付费']],
            'community' => ['categories' => ['community', 'event'], 'tags' => ['社区', '活动', '运营']],
            'dev' => ['categories' => ['dev', 'api'], 'tags' => ['开发', 'API', '技术']],
        ];
        return [
            'shape_type' => $type,
            'shape_label' => $shape['label'] ?? '综合',
            'prefs' => $map[$type] ?? ['categories' => [], 'tags' => []],
        ];
    }

    /**
     * 形态对比：默认形态（均衡）vs 当前形态
     * 展示这个实例从"出厂"到"现在"的差异化生长
     */
    public static function shapeCompare(): array {
        $shape = self::shape();
        $s = self::state();
        $signals = $s['signals'] ?? [];
        $dims = ['content' => '内容', 'growth' => '增长', 'sales' => '商业', 'community' => '社区', 'dev' => '开发'];
        $current = [];
        foreach ($signals as $k => $v) {
            if (strpos($k, 'view_page:') !== 0) continue;
            $key = str_replace('view_page:', '', $k);
            foreach ($dims as $dk => $dn) {
                if (preg_match(self::dimPattern($dk), $key)) $current[$dk] = ($current[$dk] ?? 0) + $v;
            }
        }
        $total = array_sum($current);
        $rows = [];
        foreach ($dims as $dk => $dn) {
            $pct = $total > 0 ? round(($current[$dk] ?? 0) / $total * 100) : 0;
            $rows[] = ['dim' => $dk, 'label' => $dn, 'pct' => $pct];
        }
        usort($rows, fn($a, $b) => $b['pct'] <=> $a['pct']);
        return ['shape' => $shape['label'] ?? '未知', 'distribution' => $rows];
    }

    private static function dimPattern(string $dim): string {
        return [
            'content' => '/academy|articles|downloads|podcasts|courses|topic/',
            'growth' => '/tools|docs|capability|product|category/',
            'sales' => '/marketplace|shop|member|commerce/',
            'community' => '/community/',
            'dev' => '/api|developer|md-docs/',
        ][$dim] ?? '/./';
    }

    /**
     * 脱敏打包：把本实例的"生长形态"打包成一个可分享的主题模板
     * 脱敏原则：只保留形态画像 + 主题偏好，绝不含任何用户/访客/内容数据
     * @return array ['theme_id'=>, 'name'=>, 'desc'=>, 'shape'=>, 'payload'=>]
     */
    public static function exportAnonymizedTemplate(): array {        $shape = self::shape();
        $s = self::state();

        // 只提取"形态特征"，脱敏（不含任何 PII）
        $fingerprint = [
            'shape_type' => $shape['type'] ?? 'seedling',
            'shape_label' => $shape['label'] ?? '综合',
            'distribution' => array_column(self::shapeCompare()['distribution'] ?? [], 'pct', 'dim'),
            'days_alive' => self::daysAlive(),
            'signal_count' => count($s['signals'] ?? []),
            'resolved_count' => count(array_filter($s['milestones'] ?? [], fn($m) => ($m['type'] ?? '') === 'resolved')),
            'active_hours' => self::activeHours(),
        ];

        // 形态 → 建议的默认主题名
        $themeSuggest = [
            'content' => 'notion', 'growth' => 'google', 'sales' => 'apple',
            'community' => 'default', 'dev' => 'linear', 'seedling' => 'default',
        ][$shape['type'] ?? 'seedling'] ?? 'default';

        return [
            'theme_id' => 'growth_' . substr(md5(($shape['type'] ?? 'x') . date('Ymd')), 0, 8),
            'name' => '生长形态 · ' . ($shape['label'] ?? '综合'),
            'desc' => '从真实使用中生长出来的形态模板（已脱敏），适合 ' . ($shape['type'] ?? '综合') . ' 型站点',
            'shape' => $shape,
            'fingerprint' => $fingerprint,
            'suggested_base_theme' => $themeSuggest,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /** 记录活跃时段（signal 时自动记录 hour） */
    public static function recordActivity(int $hour): void {
        $s = self::state();
        $s['hours'][$hour] = ($s['hours'][$hour] ?? 0) + 1;
        if (count($s['hours']) > 24) {
            arsort($s['hours']);
            $s['hours'] = array_slice($s['hours'], 0, 24, true);
        }
        json_write(self::$file, $s);
    }

    /** 获取活跃时段（前 N 个高峰小时） */
    public static function activeHours(int $top = 4): array {
        $s = self::state();
        $hours = $s['hours'] ?? [];
        arsort($hours);
        return array_slice(array_keys($hours), 0, $top);
    }

    /**
     * 生成周期报告（周报/月报摘要）
     * @return array {period, highlights, counts}
     */
    public static function report(int $days = 7): array {
        $s = self::state();
        $milestones = $s['milestones'] ?? [];
        $since = time() - $days * 86400;
        $recent = array_filter($milestones, fn($m) => ($m['ts'] ?? 0) >= $since);

        $byType = [];
        foreach ($recent as $m) $byType[$m['type'] ?? 'other'] = ($byType[$m['type'] ?? 'other'] ?? 0) + 1;

        // 形态变化
        $shape = self::shape();

        // 建议处理统计
        $evo = SelfEvolve::state();
        $resolvedRecent = array_filter($evo['history'] ?? [], fn($h) => strtotime($h['resolved_at'] ?? '') >= $since);

        $highlights = [];
        if (count($resolvedRecent) > 0) $highlights[] = "采纳了 " . count($resolvedRecent) . " 条迭代建议";
        if (($byType['scan'] ?? 0) > 0) $highlights[] = "完成 " . $byType['scan'] . " 次自我体检";
        if (($byType['resolve'] ?? 0) > 0) $highlights[] = "解决了 " . $byType['resolve'] . " 个问题";
        $openCritical = count(array_filter($evo['suggestions'] ?? [], fn($x) => ($x['status'] ?? 'open') === 'open' && ($x['severity'] ?? '') === 'critical'));
        if ($openCritical > 0) $highlights[] = "仍有 {$openCritical} 个严重问题待处理";

        return [
            'period' => $days . '天',
            'since' => date('Y-m-d', $since),
            'shape' => $shape['label'] ?? '未知',
            'highlights' => $highlights,
            'counts' => $byType,
            'active_hours' => self::activeHours(),
        ];
    }
}
