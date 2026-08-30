<?php
/**
 * 用户分群自动化规则引擎
 * 支持规则配置、定时评估、自动打标签
 */
require_once __DIR__ . '/../admin/config.php';

class SegmentEngine {
    private static string $rulesFile = DATA_DIR . '/segment_rules.json';
    private static string $segmentsFile = DATA_DIR . '/segments.json';

    /**
     * 获取所有分群规则
     */
    public static function getRules(): array {
        return json_read(self::$rulesFile);
    }

    /**
     * 获取所有分群
     */
    public static function getSegments(): array {
        return json_read(self::$segmentsFile);
    }

    /**
     * 添加分群
     */
    public static function addSegment(array $data): array {
        $segments = self::getSegments();
        $segment = [
            'id' => 'seg_' . bin2hex(random_bytes(6)),
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'color' => $data['color'] ?? '#6366f1',
            'rules' => $data['rules'] ?? [],
            'operator' => $data['operator'] ?? 'and',
            'member_count' => 0,
            'auto_update' => $data['auto_update'] ?? true,
            'last_evaluated' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $segments[] = $segment;
        json_write(self::$segmentsFile, $segments);
        return $segment;
    }

    /**
     * 更新分群
     */
    public static function updateSegment(string $id, array $data): ?array {
        $segments = self::getSegments();
        foreach ($segments as &$seg) {
            if ($seg['id'] === $id) {
                foreach ($data as $key => $value) {
                    if (in_array($key, ['name', 'description', 'color', 'rules', 'operator', 'auto_update'])) {
                        $seg[$key] = $value;
                    }
                }
                json_write(self::$segmentsFile, $segments);
                return $seg;
            }
        }
        return null;
    }

    /**
     * 删除分群
     */
    public static function deleteSegment(string $id): bool {
        $segments = self::getSegments();
        $before = count($segments);
        $segments = array_values(array_filter($segments, fn($s) => $s['id'] !== $id));
        if (count($segments) < $before) {
            json_write(self::$segmentsFile, $segments);
            return true;
        }
        return false;
    }

    /**
     * 评估单条规则是否匹配用户
     */
    public static function matchRule(array $rule, array $profile): bool {
        $field = $rule['field'] ?? '';
        $op = $rule['operator'] ?? 'eq';
        $value = $rule['value'] ?? '';
        $actual = $profile[$field] ?? null;

        return match ($op) {
            'eq' => $actual == $value,
            'neq' => $actual != $value,
            'gt' => (float)$actual > (float)$value,
            'gte' => (float)$actual >= (float)$value,
            'lt' => (float)$actual < (float)$value,
            'lte' => (float)$actual <= (float)$value,
            'contains' => str_contains((string)$actual, (string)$value),
            'not_contains' => !str_contains((string)$actual, (string)$value),
            'starts_with' => str_starts_with((string)$actual, (string)$value),
            'ends_with' => str_ends_with((string)$actual, (string)$value),
            'in' => in_array($actual, is_array($value) ? $value : explode(',', $value)),
            'not_in' => !in_array($actual, is_array($value) ? $value : explode(',', $value)),
            'empty' => empty($actual),
            'not_empty' => !empty($actual),
            'between' => (float)$actual >= (float)($value[0] ?? 0) && (float)$actual <= (float)($value[1] ?? 0),
            default => false,
        };
    }

    /**
     * 评估用户是否匹配分群
     */
    public static function matchSegment(array $segment, array $profile): bool {
        $rules = $segment['rules'] ?? [];
        $operator = $segment['operator'] ?? 'and';

        if (empty($rules)) return true;

        if ($operator === 'or') {
            foreach ($rules as $rule) {
                if (self::matchRule($rule, $profile)) return true;
            }
            return false;
        } else {
            foreach ($rules as $rule) {
                if (!self::matchRule($rule, $profile)) return false;
            }
            return true;
        }
    }

    /**
     * 批量评估所有用户，返回每个分群的匹配用户数
     */
    public static function evaluateAll(): array {
        $segments = self::getSegments();
        require_once __DIR__ . '/CdpProfileStore.php';
        $profiles = cdp_profile_all();
        $results = [];

        foreach ($segments as &$seg) {
            $count = 0;
            foreach ($profiles as $profile) {
                if (self::matchSegment($seg, $profile)) $count++;
            }
            $seg['member_count'] = $count;
            $seg['last_evaluated'] = date('Y-m-d H:i:s');
            $results[$seg['id']] = $count;
        }

        json_write(self::$segmentsFile, $segments);
        return $results;
    }

    /**
     * 获取用户所属分群
     */
    public static function getUserSegments(string $userId, array $profile): array {
        $segments = self::getSegments();
        $result = [];
        foreach ($segments as $seg) {
            if (self::matchSegment($seg, $profile)) {
                $result[] = ['id' => $seg['id'], 'name' => $seg['name'], 'color' => $seg['color']];
            }
        }
        return $result;
    }

    /**
     * 预设分群规则模板
     */
    public static function templates(): array {
        return [
            [
                'name' => '高价值用户',
                'description' => '消费金额 > 1000 或 课程完成数 > 3',
                'color' => '#ef4444',
                'rules' => [
                    ['field' => 'total_spent', 'operator' => 'gt', 'value' => '1000'],
                    ['field' => 'courses_completed', 'operator' => 'gt', 'value' => '3'],
                ],
                'operator' => 'or',
            ],
            [
                'name' => '活跃用户',
                'description' => '最近7天内有登录行为',
                'color' => '#10b981',
                'rules' => [
                    ['field' => 'last_active_days', 'operator' => 'lte', 'value' => '7'],
                ],
                'operator' => 'and',
            ],
            [
                'name' => '流失风险用户',
                'description' => '30天未活跃且曾是活跃用户',
                'color' => '#f59e0b',
                'rules' => [
                    ['field' => 'last_active_days', 'operator' => 'gt', 'value' => '30'],
                    ['field' => 'total_logins', 'operator' => 'gt', 'value' => '5'],
                ],
                'operator' => 'and',
            ],
            [
                'name' => '新注册用户',
                'description' => '注册不超过7天',
                'color' => '#6366f1',
                'rules' => [
                    ['field' => 'registered_days_ago', 'operator' => 'lte', 'value' => '7'],
                ],
                'operator' => 'and',
            ],
            [
                'name' => '课程学习者',
                'description' => '报名课程数 > 0',
                'color' => '#8b5cf6',
                'rules' => [
                    ['field' => 'courses_enrolled', 'operator' => 'gt', 'value' => '0'],
                ],
                'operator' => 'and',
            ],
            [
                'name' => '企业用户',
                'description' => '来源为 B2B 渠道或标签含企业',
                'color' => '#0ea5e9',
                'rules' => [
                    ['field' => 'source', 'operator' => 'eq', 'value' => 'b2b'],
                    ['field' => 'tags', 'operator' => 'contains', 'value' => '企业'],
                ],
                'operator' => 'or',
            ],
        ];
    }
}
