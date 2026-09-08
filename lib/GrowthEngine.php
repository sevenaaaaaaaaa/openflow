<?php
/**
 * GrowthEngine — 增长规则引擎（实用性重写）
 *
 * 旧设计：信号→形态→泛化建议（链条太长，建议空泛）
 * 新设计：触发条件 → 具体建议 → 一键执行
 *
 * 核心理念：每条规则 = WHEN（何时触发）× WHAT（建议什么）× HOW（怎么执行）
 *
 * 规则来源：绑定真实业务数据（CDP/CRM/订单/内容），而非页面访问计数
 */

class GrowthEngine {
    private static string $file = DATA_DIR . '/growth.json';

    /* ─── 状态 ─── */

    public static function state(): array {
        $s = json_read(self::$file);
        if (empty($s)) {
            $s = [
                'born_at' => time(),
                'signals' => [],
                'weights' => [],
                'milestones' => [],
                'shape' => null,
                'last_shaped' => 0,
            ];
        }
        return $s;
    }

    /* ─── 信号采集（保留兼容，但权重更新）─── */

    public static function signal(string $type, string $key, int $weight = 1): void {
        $s = self::state();
        $k = $type . ':' . $key;
        $s['signals'][$k] = ($s['signals'][$k] ?? 0) + $weight;
        if (count($s['signals']) > 800) {
            arsort($s['signals']);
            $s['signals'] = array_slice($s['signals'], 0, 600, true);
        }
        json_write(self::$file, $s);
    }

    public static function suggestionResolved(string $id, string $category): void {
        self::signal('resolve', $category, 2);
        $s = self::state();
        $s['milestones'][] = ['ts' => time(), 'type' => 'resolved', 'key' => $id, 'category' => $category];
        if (count($s['milestones']) > 200) $s['milestones'] = array_slice($s['milestones'], -150);
        json_write(self::$file, $s);
    }

    public static function timeline(string $type, string $title, string $detail = ''): void {
        $s = self::state();
        $s['milestones'][] = ['ts' => time(), 'type' => $type, 'title' => $title, 'detail' => $detail];
        if (count($s['milestones']) > 200) $s['milestones'] = array_slice($s['milestones'], -150);
        json_write(self::$file, $s);
    }

    public static function timelineGet(int $limit = 50): array {
        return array_slice(array_reverse(self::state()['milestones'] ?? []), 0, $limit);
    }

    public static function suggestionIgnored(string $id, string $category): void {
        self::signal('ignore', $category, 1);
    }

    public static function daysAlive(): int {
        $s = self::state();
        return (int)((time() - ($s['born_at'] ?? time())) / 86400);
    }

    /* ─── 规则引擎（核心改造：WHEN × WHAT × HOW）─── */

    /**
     * 扫描所有规则，返回触发的建议列表
     * 每条规则格式：['id'=>..., 'when'=>..., 'what'=>..., 'how'=>..., 'priority'=>...]
     */
    public static function scan(): array {
        $suggestions = [];

        foreach (self::allRules() as $rule) {
            $result = self::evaluate($rule['when']);
            if ($result['hit']) {
                $suggestions[] = [
                    'id'    => $rule['id'],
                    'when'  => $result['context'],   // 触发时的具体数据
                    'what'  => $rule['what'],         // 建议什么
                    'how'   => $rule['how'],          // 怎么执行
                    'priority' => $rule['priority'] ?? 'medium',
                ];
            }
        }

        // 按优先级排序：critical > high > medium > low
        $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($suggestions, fn($a, $b) => ($order[$a['priority']] ?? 9) <=> ($order[$b['priority']] ?? 9));

        return $suggestions;
    }

    /**
     * 评估单条规则的触发条件
     */
    private static function evaluate(array $when): array {
        $type = $when['type'] ?? '';
        $context = [];

        switch ($type) {
            case 'metric_above':
            case 'metric_below':
                // 直接查业务指标
                $val = self::metric($when['metric']);
                $threshold = $when['threshold'] ?? 0;
                $hit = ($type === 'metric_above') ? ($val > $threshold) : ($val < $threshold);
                return ['hit' => $hit, 'context' => ['metric' => $when['metric'], 'value' => $val, 'threshold' => $threshold]];

            case 'cdp_count_above':
                // CDP 事件计数
                $val = self::cdpCount($when['event'], $when['days'] ?? 7);
                $hit = $val > ($when['threshold'] ?? 0);
                return ['hit' => $hit, 'context' => ['event' => $when['event'], 'count' => $val, 'days' => $when['days'] ?? 7]];

            case 'crm_stage_stale':
                // CRM 阶段停滞
                $val = self::crmStaleCount($when['stage'], $when['days'] ?? 30);
                $hit = $val > ($when['threshold'] ?? 0);
                return ['hit' => $hit, 'context' => ['stage' => $when['stage'], 'stale_count' => $val, 'days' => $when['days'] ?? 30]];

            case 'content_gap':
                // 内容缺口（弱分类文章数）
                $val = self::weakCategoryCount($when['min_articles'] ?? 5);
                $hit = !empty($val);
                return ['hit' => $hit, 'context' => ['weak_categories' => $val]];

            case 'module_inactive':
                // 功能未启用
                $val = self::moduleActive($when['module']);
                $hit = !$val;
                return ['hit' => $hit, 'context' => ['module' => $when['module']]];

            default:
                return ['hit' => false, 'context' => []];
        }
    }

    /* ─── 规则清单（可扩展）─── */

    public static function allRules(): array {
        return [
            // 流失预警
            [
                'id' => 'churn_warning',
                'when' => ['type' => 'cdp_count_above', 'event' => 'page_view', 'days' => 7, 'threshold' => 0],
                'what' => '过去 7 天有访客但无注册转化，说明落地页或注册流程有卡点',
                'how' => '建议：在落地页加一个"30秒注册"入口，或优化注册流程减少字段',
                'priority' => 'high',
            ],
            // SEO 内容缺口
            [
                'id' => 'seo_content_gap',
                'when' => ['type' => 'content_gap', 'min_articles' => 3],
                'what' => '发现某些业务方向文章不足，存在内容缺口',
                'how' => '建议：查看"GEO 话题监控"生成的选题，优先为弱分类方向写内容',
                'priority' => 'high',
            ],
            // CRM 线索停滞
            [
                'id' => 'crm_stale_leads',
                'when' => ['type' => 'crm_stage_stale', 'stage' => 'new', 'days' => 7, 'threshold' => 1],
                'what' => '有新线索超过 7 天未跟进',
                'how' => '建议：查看 CRM 线索列表，跟进高意向线索',
                'priority' => 'critical',
            ],
            // CDP 数据采集不足
            [
                'id' => 'cdp_thin',
                'when' => ['type' => 'cdp_count_above', 'event' => 'page_view', 'days' => 30, 'threshold' => 0],
                'what' => '过去 30 天有访客但无 CDP 事件（埋点可能未生效）',
                'how' => '建议：检查前端埋点脚本是否正常加载（看 /xmp/cdp 有无事件）',
                'priority' => 'medium',
            ],
            // 营销自动化未启用
            [
                'id' => 'ma_inactive',
                'when' => ['type' => 'module_inactive', 'module' => 'automation'],
                'what' => '营销自动化功能未启用',
                'how' => '建议：在"自动化"模块创建一个"新用户欢迎序列"工作流',
                'priority' => 'medium',
            ],
            // 内容发布频率低
            [
                'id' => 'low_publish_rate',
                'when' => ['type' => 'cdp_count_above', 'event' => 'content_published', 'days' => 14, 'threshold' => 0],
                'what' => '过去 14 天无新内容发布',
                'how' => '建议：用 GEO 话题监控生成选题，写一篇 800 字的短文',
                'priority' => 'low',
            ],
        ];
    }

    /* ─── 业务指标查询（连接真实数据）─── */

    private static function metric(string $name): float {
        try {
            switch ($name) {
                case 'monthly_revenue':
                    $r = Database::query("SELECT SUM(amount) s FROM orders WHERE status='paid' AND paid_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
                    return (float)($r[0]['s'] ?? 0);
                case 'monthly_visitors':
                    $r = Database::query("SELECT COUNT(DISTINCT uid) c FROM events WHERE event='page_view' AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
                    return (float)($r[0]['c'] ?? 0);
                case 'open_ticket_count':
                    $r = Database::query("SELECT COUNT(*) c FROM leads WHERE stage IN ('new','contacted')");
                    return (float)($r[0]['c'] ?? 0);
                case 'published_articles':
                    $articles = json_read(DATA_DIR . '/articles/index.json');
                    return count(array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published'));
                default:
                    return 0;
            }
        } catch (Exception $e) { return 0; }
    }

    private static function cdpCount(string $event, int $days): int {
        try {
            $r = Database::query("SELECT COUNT(*) c FROM events WHERE event=? AND created_at >= ?", [$event, date('Y-m-d', strtotime("-{$days} days"))]);
            return (int)($r[0]['c'] ?? 0);
        } catch (Exception $e) { return 0; }
    }

    private static function crmStaleCount(string $stage, int $days): int {
        try {
            $r = Database::query("SELECT COUNT(*) c FROM leads WHERE stage=? AND updated_at < ?", [$stage, date('Y-m-d', strtotime("-{$days} days"))]);
            return (int)($r[0]['c'] ?? 0);
        } catch (Exception $e) { return 0; }
    }

    private static function weakCategoryCount(int $min): array {
        $articles = json_read(DATA_DIR . '/articles/index.json');
        $cats = [];
        foreach ($articles as $a) {
            $c = $a['category'] ?? '未分类';
            $cats[$c] = ($cats[$c] ?? 0) + 1;
        }
        $weak = [];
        foreach ($cats as $name => $cnt) {
            if ($cnt < $min) $weak[] = ['category' => $name, 'count' => $cnt];
        }
        usort($weak, fn($a, $b) => $a['count'] <=> $b['count']);
        return $weak;
    }

    private static function moduleActive(string $module): bool {
        switch ($module) {
            case 'automation':
                $flows = json_read(DATA_DIR . '/automation.json');
                return !empty($flows);
            case 'crm':
                $leads = json_read(DATA_DIR . '/crm.json');
                return !empty($leads['leads'] ?? []);
            case 'subscription':
                $state = json_read(DATA_DIR . '/subscription/state.json');
                return !empty($state);
            default:
                return true;
        }
    }
}
