<?php
/**
 * Dynamic Content System — 基于 URL 参数的动态内容替换
 * 支持：卡片显隐、文字替换、UTM 追踪、A/B 测试
 */
require_once __DIR__ . '/../admin/config.php';

class DynamicContent {
    private static string $file = DATA_DIR . '/dynamic-rules.json';
    private static string $analyticsFile = DATA_DIR . '/dynamic-analytics.json';

    // ─── Rules CRUD ───────────────────────────────
    public static function all(): array {
        return json_read(self::$file);
    }

    public static function save(array $rules): bool {
        return json_write(self::$file, $rules);
    }

    public static function get(string $id): ?array {
        foreach (self::all() as $r) {
            if ($r['id'] === $id) return $r;
        }
        return null;
    }

    public static function create(array $data): array {
        $rules = self::all();
        $rule = [
            'id' => 'rule_' . bin2hex(random_bytes(6)),
            'name' => trim($data['name'] ?? '未命名规则'),
            'target' => [
                'type' => $data['target_type'] ?? 'global',
                'page' => $data['target_page'] ?? '',
                'article_id' => $data['target_article_id'] ?? '',
            ],
            'conditions' => self::parseConditions($data),
            'actions' => self::parseActions($data),
            'enabled' => !empty($data['enabled']),
            'priority' => (int)($data['priority'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $rules[] = $rule;
        self::save($rules);
        return $rule;
    }

    public static function update(string $id, array $data): ?array {
        $rules = self::all();
        foreach ($rules as &$r) {
            if ($r['id'] === $id) {
                $r['name'] = trim($data['name'] ?? $r['name']);
                $r['target'] = [
                    'type' => $data['target_type'] ?? $r['target']['type'],
                    'page' => $data['target_page'] ?? $r['target']['page'],
                    'article_id' => $data['target_article_id'] ?? $r['target']['article_id'],
                ];
                $r['conditions'] = self::parseConditions($data);
                $r['actions'] = self::parseActions($data);
                $r['enabled'] = !empty($data['enabled']);
                $r['priority'] = (int)($data['priority'] ?? $r['priority']);
                $r['updated_at'] = date('Y-m-d H:i:s');
                self::save($rules);
                return $r;
            }
        }
        return null;
    }

    public static function delete(string $id): bool {
        $rules = self::all();
        $rules = array_values(array_filter($rules, fn($r) => $r['id'] !== $id));
        return self::save($rules);
    }

    public static function toggle(string $id): bool {
        $rules = self::all();
        foreach ($rules as &$r) {
            if ($r['id'] === $id) {
                $r['enabled'] = !$r['enabled'];
                $r['updated_at'] = date('Y-m-d H:i:s');
                self::save($rules);
                return true;
            }
        }
        return false;
    }

    // ─── Rule Matching ────────────────────────────
    /**
     * 获取当前页面应应用的规则
     */
    public static function matchingRules(string $pageType, string $pageId = '', array $urlParams = []): array {
        $all = self::all();
        $matching = [];

        // 画像维度参数（会员等级/标签/来源/消费）
        $profileParams = self::profileParams();

        foreach ($all as $rule) {
            if (!$rule['enabled']) continue;
            if (!self::matchesTarget($rule['target'], $pageType, $pageId)) continue;
            if (!self::matchesConditions($rule['conditions'], array_merge($urlParams, $profileParams))) continue;
            $matching[] = $rule;
        }

        // 按优先级排序（高优先级先执行）
        usort($matching, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));
        return $matching;
    }

    /**
     * 画像维度参数：把当前访问者画像转换为可匹配参数
     * 支持：member_level / has_tag / total_spent / is_vip / source / is_member
     */
    public static function profileParams(): array {
        $visitorId = $_COOKIE['fc_uid'] ?? '';
        $memberId = $_COOKIE['member_id'] ?? '';
        $params = ['is_member' => $memberId ? '1' : '0'];

        try {
            require_once __DIR__ . '/IdentityResolver.php';
            require_once __DIR__ . '/CdpSystem.php';
            $canonical = IdentityResolver::resolve($visitorId, $memberId);
            $profiles = CdpSystem::allProfiles();
            $cdp = $canonical ? ($profiles[$canonical] ?? null) : null;
            if (!$cdp && $visitorId) $cdp = $profiles[$visitorId] ?? null;
            if (!$cdp) return $params;

            $p = $cdp['properties'] ?? [];
            $params['member_level'] = $p['member_level'] ?? '';
            $params['total_spent'] = (float)($p['total_spent'] ?? 0);
            $params['source'] = $p['source'] ?? '';
            $params['is_vip'] = ($p['member_level'] ?? '') === 'vip' ? '1' : '0';
            // 标签：合并为逗号分隔，支持 contains 匹配
            $params['tags'] = implode(',', $cdp['tags'] ?? []);
        } catch (Exception $e) {}

        return $params;
    }

    private static function matchesTarget(array $target, string $pageType, string $pageId): bool {
        $type = $target['type'] ?? 'global';
        if ($type === 'global') return true;
        if ($type === 'page' && ($target['page'] ?? '') === $pageType) return true;
        if ($type === 'article' && !empty($target['article_id'])) return true;
        return false;
    }

    private static function matchesConditions(array $conditions, array $params): bool {
        if (empty($conditions)) return true;

        foreach ($conditions as $cond) {
            $paramName = $cond['param'] ?? '';
            $operator = $cond['operator'] ?? 'equals';
            $expected = $cond['value'] ?? '';
            $actual = $params[$paramName] ?? '';

            switch ($operator) {
                case 'equals':
                    if (strtolower($actual) !== strtolower($expected)) return false;
                    break;
                case 'not_equals':
                    if (strtolower($actual) === strtolower($expected)) return false;
                    break;
                case 'contains':
                    if (stripos($actual, $expected) === false) return false;
                    break;
                case 'starts_with':
                    if (stripos($actual, $expected) !== 0) return false;
                    break;
                case 'ends_with':
                    if (substr(strtolower($actual), -strlen(strtolower($expected))) !== strtolower($expected)) return false;
                    break;
                case 'matches':
                    if (!preg_match($expected, $actual)) return false;
                    break;
                case 'exists':
                    if (!isset($params[$paramName]) || $actual === '') return false;
                    break;
                case 'not_exists':
                    if (isset($params[$paramName]) && $actual !== '') return false;
                    break;
            }
        }
        return true;
    }

    // ─── Parse Helpers ────────────────────────────
    private static function parseConditions(array $data): array {
        $conditions = [];
        $params = $data['cond_param'] ?? [];
        $operators = $data['cond_operator'] ?? [];
        $values = $data['cond_value'] ?? [];

        foreach ($params as $i => $param) {
            $param = trim($param);
            if (empty($param)) continue;
            $conditions[] = [
                'param' => $param,
                'operator' => $operators[$i] ?? 'equals',
                'value' => trim($values[$i] ?? ''),
            ];
        }
        return $conditions;
    }

    private static function parseActions(array $data): array {
        $actions = [];
        $types = $data['action_type'] ?? [];
        $selectors = $data['action_selector'] ?? [];
        $finds = $data['action_find'] ?? [];
        $replaces = $data['action_replace'] ?? [];

        foreach ($types as $i => $type) {
            if (empty($type)) continue;
            $action = [
                'type' => $type,
                'selector' => trim($selectors[$i] ?? ''),
            ];
            if ($type === 'replace_text') {
                $action['text_find'] = $finds[$i] ?? '';
                $action['text_replace'] = $replaces[$i] ?? '';
            }
            $actions[] = $action;
        }
        return $actions;
    }

    // ─── Analytics ────────────────────────────────
    public static function trackImpression(string $ruleId, string $pageType): void {
        $analytics = json_read(self::$analyticsFile);
        $today = date('Y-m-d');
        $key = "{$ruleId}:{$today}";
        $analytics[$key] = ($analytics[$key] ?? 0) + 1;
        json_write(self::$analyticsFile, $analytics);
    }

    public static function trackClick(string $ruleId, string $actionType, string $selector): void {
        $analytics = json_read(self::$analyticsFile);
        $today = date('Y-m-d');
        $key = "{$ruleId}:click:{$today}";
        $analytics[$key] = ($analytics[$key] ?? 0) + 1;
        json_write(self::$analyticsFile, $analytics);
    }

    public static function getAnalytics(string $ruleId, int $days = 7): array {
        $analytics = json_read(self::$analyticsFile);
        $result = ['impressions' => [], 'clicks' => []];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $impKey = "{$ruleId}:{$date}";
            $clickKey = "{$ruleId}:click:{$date}";
            $result['impressions'][$date] = $analytics[$impKey] ?? 0;
            $result['clicks'][$date] = $analytics[$clickKey] ?? 0;
        }

        return $result;
    }

    // ─── Export rules as JS config ────────────────
    public static function toJSConfig(string $pageType, string $pageId = ''): string {
        $params = self::getURLParams();
        $rules = self::matchingRules($pageType, $pageId, $params);

        if (empty($rules)) return '';

        // Track impressions
        foreach ($rules as $rule) {
            self::trackImpression($rule['id'], $pageType);
        }

        return json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /**
     * 从 URL 解析参数（UTM + 自定义）
     */
    public static function getURLParams(): array {
        $params = [];
        $query = $_SERVER['QUERY_STRING'] ?? '';
        if (empty($query)) return $params;

        parse_str($query, $parsed);
        return $parsed;
    }
}
