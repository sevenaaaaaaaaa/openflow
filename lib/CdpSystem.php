<?php
/**
 * CDP System — 客户数据中台
 * 行为追踪 · 用户分群 · 用户画像 · 数据接入 · 数据回传
 *
 * ── CDP 三层架构：第 1 层「主系统」 ──
 * 本文件是 CDP 的核心：事件采集、画像计算、分群、数据接入/回传都在这里。
 * 上层关系：CdpInsight（洞察）读本层指标；CdpSync（存储）落本层画像。
 * 加代码指引：CDP 的核心能力（埋点处理、分群规则、画像字段）加这里，
 *            不要加到 CdpInsight（那是 AI 洞察）或 CdpSync（那是落库）。
 */
require_once __DIR__ . '/../admin/config.php';

class CdpSystem {
    private static string $eventsFile = DATA_DIR . '/cdp/events.json';
    private static string $profilesFile = DATA_DIR . '/cdp/profiles.json';
    private static string $segmentsFile = DATA_DIR . '/cdp/segments.json';
    private static string $propertiesFile = DATA_DIR . '/cdp/properties.json';

    // ─── 事件追踪 ──────────────────────────────────

    /**
     * 记录用户行为事件
     */
    public static function track(string $event, array $data = [], string $visitorId = ''): bool {
        // 事件开关过滤（停用的事件不采集）
        if (!class_exists('EventDictionary')) {
            require_once __DIR__ . '/EventDictionary.php';
        }
        if (class_exists('EventDictionary') && !EventDictionary::isEnabled($event)) return false;

        $events = self::allEvents();

        $visitorId = $visitorId ?: self::getVisitorId();
        $memberId = $_COOKIE['member_id'] ?? '';

        // 身份解析：事件数据里出现邮箱/手机号/会员ID时，自动合并身份
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        if ($memberId || $email || $phone) {
            require_once __DIR__ . '/IdentityResolver.php';
            $canonical = IdentityResolver::merge($visitorId, $memberId, $email, $phone);
            if ($canonical) $visitorId = $canonical; // 主身份作为画像 key
        }

        $entry = [
            'id' => 'evt_' . bin2hex(random_bytes(8)),
            'event' => $event,
            'visitor_id' => $visitorId,
            'member_id' => $memberId,
            'properties' => $data,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => self::getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $events[] = $entry;

        // 保持文件大小（最多 10000 条）
        if (count($events) > 10000) {
            $events = array_slice($events, -10000);
        }

        self::saveEvents($events);

        // 更新用户画像
        self::updateProfile($visitorId, $memberId, $event, $data);

        return true;
    }

    /**
     * 批量记录事件
     */
    public static function trackBatch(array $events): int {
        $count = 0;
        foreach ($events as $e) {
            if (self::track($e['event'], $e['properties'] ?? [], $e['visitor_id'] ?? '')) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 获取事件列表
     */
    public static function allEvents(): array {
        return json_read(self::$eventsFile);
    }

    private static function saveEvents(array $events): void {
        json_write(self::$eventsFile, $events);
    }

    // ─── 用户画像 ──────────────────────────────────

    /**
     * 获取用户画像
     */
    public static function getProfile(string $visitorId): ?array {
        $profiles = self::allProfiles();
        return $profiles[$visitorId] ?? null;
    }

    /**
     * 获取所有画像
     */
    public static function allProfiles(): array {
        return json_read(self::$profilesFile);
    }

    /**
     * 保存全量画像（供身份解析合并后回写）
     */
    public static function saveProfiles(array $profiles): void {
        json_write(self::$profilesFile, $profiles);
    }

    /**
     * 更新用户画像
     */
    private static function updateProfile(string $visitorId, string $memberId, string $event, array $data): void {
        $profiles = self::allProfiles();

        if (!isset($profiles[$visitorId])) {
            $profiles[$visitorId] = [
                'visitor_id' => $visitorId,
                'member_id' => $memberId,
                'first_seen' => date('Y-m-d H:i:s'),
                'properties' => [],
                'events_count' => 0,
                'tags' => [],
            ];
        }

        $profile = &$profiles[$visitorId];
        $profile['last_seen'] = date('Y-m-d H:i:s');
        $profile['events_count']++;

        if ($memberId && empty($profile['member_id'])) {
            $profile['member_id'] = $memberId;
        }

        // 合并属性（身份字段 + 设备环境 + 渠道归因 + 业务属性）
        $profileKeys = ['email', 'name', 'phone', 'company', 'city', 'member_level', 'total_spent', 'role'];
        $deviceKeys = ['os', 'os_version', 'browser', 'browser_version', 'device', 'screen_width', 'screen_height', 'language'];
        $channelKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'channel', 'is_ad_channel'];
        foreach ($data as $key => $value) {
            if (in_array($key, $profileKeys, true)) {
                $profile['properties'][$key] = $value;
            }
        }
        // 设备环境：首次记录
        foreach ($deviceKeys as $key) {
            if (isset($data[$key]) && !isset($profile['properties'][$key])) {
                $profile['properties'][$key] = $data[$key];
            }
        }
        // 渠道归因：首次记录（首触归因）
        foreach ($channelKeys as $key) {
            if (isset($data[$key]) && !isset($profile['properties'][$key])) {
                $profile['properties'][$key] = $data[$key];
            }
        }

        // 自动打标签
        self::autoTag($profile, $event, $data);

        json_write(self::$profilesFile, $profiles);
    }

    /**
     * 自动打标签
     */
    private static function autoTag(array &$profile, string $event, array $data): void {
        $tags = &$profile['tags'];

        // 访问频率标签
        if ($profile['events_count'] >= 100) {
            $tags[] = 'highly_engaged';
        } elseif ($profile['events_count'] >= 20) {
            $tags[] = 'engaged';
        }

        // 事件类型标签
        if ($event === 'purchase') {
            $tags[] = 'buyer';
        } elseif ($event === 'form_submit') {
            $tags[] = 'lead';
        } elseif ($event === 'course_complete') {
            $tags[] = 'learner';
        } elseif ($event === 'article_view') {
            $tags[] = 'content_reader';
        } elseif ($event === 'tool_use') {
            $tags[] = 'tool_user';
        } elseif ($event === 'user_register') {
            $tags[] = 'registered';
        } elseif ($event === 'role_selected') {
            if (!empty($data['role'])) $tags[] = 'role:' . $data['role'];
        }

        // 渠道标签
        if (!empty($data['channel'])) {
            $chTag = 'channel:' . $data['channel'];
            if (!in_array($chTag, $tags, true)) $tags[] = $chTag;
        }

        // 设备标签
        if (!empty($data['device'])) {
            if (!in_array('device:' . $data['device'], $tags, true)) $tags[] = 'device:' . $data['device'];
        }

        // 去重
        $tags = array_unique($tags);
    }

    // ─── 用户分群 ──────────────────────────────────

    /**
     * 获取所有分群
     */
    public static function allSegments(): array {
        return json_read(self::$segmentsFile);
    }

    /**
     * 创建分群
     */
    public static function createSegment(array $data): array {
        $segments = self::allSegments();
        $segment = [
            'id' => 'seg_' . bin2hex(random_bytes(6)),
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'rules' => $data['rules'] ?? [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $segments[] = $segment;
        json_write(self::$segmentsFile, $segments);
        return $segment;
    }

    /**
     * 计算分群用户数
     */
    public static function countSegment(array $rules): int {
        $profiles = self::allProfiles();
        $count = 0;

        foreach ($profiles as $profile) {
            if (self::matchRules($profile, $rules)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 获取分群用户列表
     */
    public static function getSegmentUsers(array $rules, int $limit = 100): array {
        $profiles = self::allProfiles();
        $users = [];

        foreach ($profiles as $profile) {
            if (self::matchRules($profile, $rules)) {
                $users[] = $profile;
                if (count($users) >= $limit) break;
            }
        }

        return $users;
    }

    /**
     * 匹配分群规则
     */
    private static function matchRules(array $profile, array $rules): bool {
        if (empty($rules)) return true;

        foreach ($rules as $rule) {
            $type = $rule['type'] ?? '';
            $field = $rule['field'] ?? '';
            $operator = $rule['operator'] ?? 'equals';
            $value = $rule['value'] ?? '';

            switch ($type) {
                case 'property':
                    $actual = $profile['properties'][$field] ?? '';
                    if (!self::compare($actual, $operator, $value)) return false;
                    break;

                case 'event':
                    $eventName = $rule['event'] ?? '';
                    $count = self::countUserEvents($profile['visitor_id'], $eventName);
                    if (!self::compare($count, $operator, (int)$value)) return false;
                    break;

                case 'tag':
                    if (!in_array($field, $profile['tags'] ?? [])) return false;
                    break;

                case 'last_seen':
                    $days = (int)$value;
                    $lastSeen = strtotime($profile['last_seen'] ?? '2000-01-01');
                    $diff = (time() - $lastSeen) / 86400;
                    if (!self::compare($diff, $operator, $days)) return false;
                    break;
            }
        }

        return true;
    }

    private static function compare($actual, string $operator, $expected): bool {
        switch ($operator) {
            case 'equals': return $actual == $expected;
            case 'not_equals': return $actual != $expected;
            case 'greater_than': return $actual > $expected;
            case 'less_than': return $actual < $expected;
            case 'contains': return stripos((string)$actual, (string)$expected) !== false;
            case 'starts_with': return stripos((string)$actual, (string)$expected) === 0;
            case 'in': return in_array($actual, (array)$expected);
            default: return false;
        }
    }

    private static function countUserEvents(string $visitorId, string $event): int {
        $events = self::allEvents();
        $count = 0;
        foreach ($events as $e) {
            if ($e['visitor_id'] === $visitorId && $e['event'] === $event) {
                $count++;
            }
        }
        return $count;
    }

    // ─── 统计分析 ──────────────────────────────────

    /**
     * 获取事件统计
     */
    public static function getEventStats(int $days = 7): array {
        $events = self::allEvents();
        $stats = [];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $stats[$date] = ['total' => 0, 'unique_visitors' => []];
        }

        foreach ($events as $e) {
            $date = substr($e['timestamp'], 0, 10);
            if (isset($stats[$date])) {
                $stats[$date]['total']++;
                $stats[$date]['unique_visitors'][$e['visitor_id']] = true;
            }
        }

        // 整理数据
        foreach ($stats as $date => &$data) {
            $data['unique_visitors'] = count($data['unique_visitors']);
        }

        return $stats;
    }

    /**
     * 获取热门事件
     */
    public static function getTopEvents(int $limit = 10): array {
        $events = self::allEvents();
        $counts = [];

        foreach ($events as $e) {
            $event = $e['event'];
            $counts[$event] = ($counts[$event] ?? 0) + 1;
        }

        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    /**
     * 获取漏斗数据
     */
    public static function getFunnel(array $steps, int $days = 30): array {
        $events = self::allEvents();
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));

        $funnel = [];
        $prevVisitors = null;

        foreach ($steps as $step) {
            $visitors = [];
            foreach ($events as $e) {
                if ($e['event'] === $step && $e['timestamp'] >= $cutoff) {
                    $visitors[$e['visitor_id']] = true;
                }
            }

            $count = count($visitors);
            $funnel[] = [
                'step' => $step,
                'count' => $count,
                'rate' => $prevVisitors ? round($count / $prevVisitors * 100, 1) : 100,
            ];

            $prevVisitors = $count;
        }

        return $funnel;
    }

    // ─── 数据资产概览 ────────────────────────────────

    /**
     * 用户增长趋势（按天/周/月）
     */
    public static function getUserGrowth(string $period = 'day', int $count = 30): array {
        $profiles = self::allProfiles();
        $growth = [];

        for ($i = 0; $i < $count; $i++) {
            if ($period === 'day') {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $growth[$date] = ['new_users' => 0, 'total_users' => 0, 'active_users' => 0];
            } elseif ($period === 'week') {
                $date = date('Y-m-d', strtotime("-{$i} weeks monday"));
                $growth[$date] = ['new_users' => 0, 'total_users' => 0, 'active_users' => 0];
            } else {
                $date = date('Y-m', strtotime("-{$i} months"));
                $growth[$date] = ['new_users' => 0, 'total_users' => 0, 'active_users' => 0];
            }
        }

        // 统计新用户和活跃用户
        foreach ($profiles as $p) {
            $firstSeen = date('Y-m-d', strtotime($p['first_seen']));
            $lastSeen = date('Y-m-d', strtotime($p['last_seen'] ?? $p['first_seen']));

            foreach ($growth as $date => &$data) {
                if ($period === 'day') {
                    if ($firstSeen === $date) $data['new_users']++;
                    if ($lastSeen === $date) $data['active_users']++;
                } elseif ($period === 'week') {
                    $weekStart = date('Y-m-d', strtotime($date));
                    $weekEnd = date('Y-m-d', strtotime($date . ' +6 days'));
                    if ($firstSeen >= $weekStart && $firstSeen <= $weekEnd) $data['new_users']++;
                    if ($lastSeen >= $weekStart && $lastSeen <= $weekEnd) $data['active_users']++;
                } else {
                    if (substr($firstSeen, 0, 7) === $date) $data['new_users']++;
                    if (substr($lastSeen, 0, 7) === $date) $data['active_users']++;
                }
            }
        }

        // 累计用户数
        $total = count($profiles);
        foreach (array_reverse($growth, true) as $date => &$data) {
            $total -= $data['new_users'];
            $data['total_users'] = $total + $data['new_users'];
        }

        return $growth;
    }

    /**
     * 用户来源分布
     */
    public static function getSourceDistribution(): array {
        $events = self::allEvents();
        $sources = [];

        foreach ($events as $e) {
            $referrer = $e['referrer'] ?? '';
            $source = self::parseSource($referrer);
            $sources[$source] = ($sources[$source] ?? 0) + 1;
        }

        arsort($sources);
        return $sources;
    }

    /**
     * 用户设备分布
     */
    public static function getDeviceDistribution(): array {
        $events = self::allEvents();
        $devices = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0];

        foreach ($events as $e) {
            $ua = strtolower($e['user_agent'] ?? '');
            if (preg_match('/tablet|ipad/', $ua)) {
                $devices['tablet']++;
            } elseif (preg_match('/mobile|android|iphone/', $ua)) {
                $devices['mobile']++;
            } else {
                $devices['desktop']++;
            }
        }

        return $devices;
    }

    /**
     * 通用维度分布（渠道/设备/浏览器/OS，从事件属性统计）
     * @param string $dimension channel | device | browser | os | language
     */
    public static function getDimensionDistribution(string $dimension, int $limit = 12): array {
        $events = self::allEvents();
        $counts = [];
        foreach ($events as $e) {
            $props = $e['properties'] ?? [];
            $val = $props[$dimension] ?? '';
            if ($val === '' || $val === null) continue;
            $counts[$val] = ($counts[$val] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    /**
     * 渠道归因分布（配合 getChannelAttribution）
     */
    public static function getChannelDistribution(int $limit = 12): array {
        return self::getDimensionDistribution('channel', $limit);
    }

    /**
     * 浏览器分布
     */
    public static function getBrowserDistribution(int $limit = 8): array {
        return self::getDimensionDistribution('browser', $limit);
    }

    /**
     * 操作系统分布
     */
    public static function getOsDistribution(int $limit = 8): array {
        return self::getDimensionDistribution('os', $limit);
    }

    /**
     * 事件维度交叉分析：某维度 × 事件类型（如 渠道×转化事件）
     * @return array [dimension_value => ['total'=>, 'event'=> count]]
     */
    public static function getDimensionEventCross(string $dimension, array $events = []): array {
        $all = $events ?: self::allEvents();
        $result = [];
        foreach ($all as $e) {
            $props = $e['properties'] ?? [];
            $dv = $props[$dimension] ?? '';
            if ($dv === '') continue;
            $ev = $e['event'] ?? '';
            if (!isset($result[$dv])) $result[$dv] = ['total' => 0, 'events' => []];
            $result[$dv]['total']++;
            $result[$dv]['events'][$ev] = ($result[$dv]['events'][$ev] ?? 0) + 1;
        }
        uasort($result, fn($a, $b) => $b['total'] <=> $a['total']);
        return $result;
    }

    /**
     * 页面访问排行
     */
    public static function getPageViews(int $limit = 20): array {
        $events = self::allEvents();
        $pages = [];

        foreach ($events as $e) {
            if ($e['event'] === 'page_view') {
                $url = $e['properties']['path'] ?? $e['url'] ?? '';
                $pages[$url] = ($pages[$url] ?? 0) + 1;
            }
        }

        arsort($pages);
        return array_slice($pages, 0, $limit, true);
    }

    // ─── 用户画像分析 ────────────────────────────────

    /**
     * 标签分布统计
     */
    public static function getTagDistribution(): array {
        $profiles = self::allProfiles();
        $tags = [];

        foreach ($profiles as $p) {
            foreach (($p['tags'] ?? []) as $tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
        }

        arsort($tags);
        return $tags;
    }

    /**
     * 用户属性分布
     */
    public static function getPropertyDistribution(string $property): array {
        $profiles = self::allProfiles();
        $dist = [];

        foreach ($profiles as $p) {
            $value = $p['properties'][$property] ?? '未设置';
            $dist[$value] = ($dist[$value] ?? 0) + 1;
        }

        arsort($dist);
        return $dist;
    }

    /**
     * 用户生命周期阶段
     */
    public static function getLifecycleDistribution(): array {
        $profiles = self::allProfiles();
        $lifecycle = [
            'new' => 0,      // 新用户（7天内）
            'active' => 0,   // 活跃用户
            'dormant' => 0,  // 沉睡用户（30天未访问）
            'churned' => 0,  // 流失用户（90天未访问）
        ];

        $now = time();
        foreach ($profiles as $p) {
            $firstSeen = strtotime($p['first_seen']);
            $lastSeen = strtotime($p['last_seen'] ?? $p['first_seen']);
            $daysSinceFirst = ($now - $firstSeen) / 86400;
            $daysSinceLast = ($now - $lastSeen) / 86400;

            if ($daysSinceFirst <= 7) {
                $lifecycle['new']++;
            } elseif ($daysSinceLast > 90) {
                $lifecycle['churned']++;
            } elseif ($daysSinceLast > 30) {
                $lifecycle['dormant']++;
            } else {
                $lifecycle['active']++;
            }
        }

        return $lifecycle;
    }

    /**
     * 用户健康评分
     */
    public static function getHealthScore(string $visitorId): int {
        $profile = self::getProfile($visitorId);
        if (!$profile) return 0;

        $score = 0;
        $now = time();

        // 活跃度（最近访问时间）
        $lastSeen = strtotime($profile['last_seen'] ?? '2000-01-01');
        $daysSinceLast = ($now - $lastSeen) / 86400;
        if ($daysSinceLast <= 1) $score += 40;
        elseif ($daysSinceLast <= 7) $score += 30;
        elseif ($daysSinceLast <= 30) $score += 20;
        elseif ($daysSinceLast <= 90) $score += 10;

        // 事件频率
        $eventsCount = $profile['events_count'] ?? 0;
        if ($eventsCount >= 100) $score += 30;
        elseif ($eventsCount >= 50) $score += 25;
        elseif ($eventsCount >= 20) $score += 20;
        elseif ($eventsCount >= 5) $score += 10;

        // 标签加成
        $tags = $profile['tags'] ?? [];
        if (in_array('buyer', $tags)) $score += 20;
        if (in_array('highly_engaged', $tags)) $score += 10;

        return min(100, $score);
    }

    // ─── 行为分析 ──────────────────────────────────

    /**
     * 事件小时分布热力图
     */
    public static function getHourlyHeatmap(int $days = 7): array {
        $events = self::allEvents();
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));
        $heatmap = array_fill(0, 24, 0);

        foreach ($events as $e) {
            if ($e['timestamp'] >= $cutoff) {
                $hour = (int)date('H', strtotime($e['timestamp']));
                $heatmap[$hour]++;
            }
        }

        return $heatmap;
    }

    /**
     * 事件类型分布（饼图数据）
     */
    public static function getEventTypeDistribution(): array {
        return self::getTopEvents(15);
    }

    /**
     * 会话深度分析（每个用户的事件数分布）
     */
    public static function getSessionDepth(): array {
        $profiles = self::allProfiles();
        $depth = [
            '1-5' => 0,
            '6-20' => 0,
            '21-50' => 0,
            '51-100' => 0,
            '100+' => 0,
        ];

        foreach ($profiles as $p) {
            $count = $p['events_count'] ?? 0;
            if ($count <= 5) $depth['1-5']++;
            elseif ($count <= 20) $depth['6-20']++;
            elseif ($count <= 50) $depth['21-50']++;
            elseif ($count <= 100) $depth['51-100']++;
            else $depth['100+']++;
        }

        return $depth;
    }

    // ─── 留存分析 ──────────────────────────────────

    /**
     * N日留存分析
     */
    public static function getRetention(int $days = 30): array {
        $events = self::allEvents();
        $profiles = self::allProfiles();

        // 按用户分组事件
        $userEvents = [];
        foreach ($events as $e) {
            $vid = $e['visitor_id'];
            if (!isset($userEvents[$vid])) $userEvents[$vid] = [];
            $userEvents[$vid][] = $e['timestamp'];
        }

        $retention = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $retention[$date] = ['cohort_size' => 0, 'retained' => []];
        }

        // 统计每日新增用户
        foreach ($profiles as $p) {
            $firstSeen = date('Y-m-d', strtotime($p['first_seen']));
            if (isset($retention[$firstSeen])) {
                $retention[$firstSeen]['cohort_size']++;
            }
        }

        // 计算留存
        foreach ($retention as $date => &$data) {
            if ($data['cohort_size'] === 0) continue;

            $cohortUsers = array_filter($profiles, fn($p) => date('Y-m-d', strtotime($p['first_seen'])) === $date);
            $cohortVids = array_column($cohortUsers, 'visitor_id');

            for ($day = 0; $day < $days; $day++) {
                $checkDate = date('Y-m-d', strtotime("{$date} +{$day} days"));
                if (!isset($retention[$checkDate])) continue;

                $retained = 0;
                foreach ($cohortVids as $vid) {
                    if (isset($userEvents[$vid])) {
                        foreach ($userEvents[$vid] as $ts) {
                            if (date('Y-m-d', strtotime($ts)) === $checkDate) {
                                $retained++;
                                break;
                            }
                        }
                    }
                }

                $data['retained'][$day] = [
                    'day' => $day,
                    'count' => $retained,
                    'rate' => round($retained / $data['cohort_size'] * 100, 1),
                ];
            }
        }

        return $retention;
    }

    // ─── RFM 分析 ──────────────────────────────────

    /**
     * RFM 评分
     */
    public static function getRFMAnalysis(): array {
        $events = self::allEvents();
        $profiles = self::allProfiles();

        // 按用户分组事件
        $userEvents = [];
        foreach ($events as $e) {
            $vid = $e['visitor_id'];
            if (!isset($userEvents[$vid])) $userEvents[$vid] = [];
            $userEvents[$vid][] = $e;
        }

        $rfm = [];
        $now = time();

        foreach ($profiles as $vid => $p) {
            $userEvts = $userEvents[$vid] ?? [];
            $lastSeen = strtotime($p['last_seen'] ?? $p['first_seen']);
            $recency = ($now - $lastSeen) / 86400;
            $frequency = count($userEvts);

            // 计算 Monetary（从 purchase 事件中提取金额）
            $monetary = 0;
            foreach ($userEvts as $e) {
                if ($e['event'] === 'purchase') {
                    $monetary += (float)($e['properties']['amount'] ?? 0);
                }
            }

            // 评分（1-5）
            $rScore = self::scoreRecency($recency);
            $fScore = self::scoreFrequency($frequency);
            $mScore = self::scoreMonetary($monetary);

            $rfm[$vid] = [
                'visitor_id' => $vid,
                'name' => $p['properties']['name'] ?? $vid,
                'recency' => round($recency, 1),
                'frequency' => $frequency,
                'monetary' => $monetary,
                'r_score' => $rScore,
                'f_score' => $fScore,
                'm_score' => $mScore,
                'segment' => self::getRFMSegment($rScore, $fScore, $mScore),
            ];
        }

        // 按总分排序
        usort($rfm, fn($a, $b) => ($b['r_score'] + $b['f_score'] + $b['m_score']) <=> ($a['r_score'] + $a['f_score'] + $a['m_score']));

        return $rfm;
    }

    private static function scoreRecency(float $days): int {
        if ($days <= 1) return 5;
        if ($days <= 7) return 4;
        if ($days <= 30) return 3;
        if ($days <= 90) return 2;
        return 1;
    }

    private static function scoreFrequency(int $count): int {
        if ($count >= 100) return 5;
        if ($count >= 50) return 4;
        if ($count >= 20) return 3;
        if ($count >= 5) return 2;
        return 1;
    }

    private static function scoreMonetary(float $amount): int {
        if ($amount >= 1000) return 5;
        if ($amount >= 500) return 4;
        if ($amount >= 100) return 3;
        if ($amount >= 10) return 2;
        return 1;
    }

    private static function getRFMSegment(int $r, int $f, int $m): string {
        if ($r >= 4 && $f >= 4 && $m >= 4) return 'VIP';
        if ($r >= 4 && $f >= 3) return '忠诚用户';
        if ($r >= 4 && $m >= 3) return '高价值新客';
        if ($r >= 3 && $f >= 3) return '活跃用户';
        if ($r <= 2 && $f >= 3) return '流失风险';
        if ($r <= 2 && $f <= 2) return '流失用户';
        if ($r >= 3 && $f <= 2) return '新用户';
        return '一般用户';
    }

    /**
     * RFM 分群分布
     */
    public static function getRFMDistribution(): array {
        $rfm = self::getRFMAnalysis();
        $dist = [];

        foreach ($rfm as $user) {
            $seg = $user['segment'];
            $dist[$seg] = ($dist[$seg] ?? 0) + 1;
        }

        arsort($dist);
        return $dist;
    }

    // ─── 路径分析 ──────────────────────────────────

    /**
     * 用户路径分析
     */
    public static function getPathAnalysis(int $limit = 20): array {
        $events = self::allEvents();
        $paths = [];

        // 按用户分组并排序事件
        $userEvents = [];
        foreach ($events as $e) {
            $vid = $e['visitor_id'];
            if (!isset($userEvents[$vid])) $userEvents[$vid] = [];
            $userEvents[$vid][] = $e;
        }

        // 计算路径转移
        $transitions = [];
        foreach ($userEvents as $vid => $evts) {
            usort($evts, fn($a, $b) => strcmp($a['timestamp'], $b['timestamp']));
            for ($i = 0; $i < count($evts) - 1; $i++) {
                $from = $evts[$i]['event'];
                $to = $evts[$i + 1]['event'];
                $key = "{$from} → {$to}";
                $transitions[$key] = ($transitions[$key] ?? 0) + 1;
            }
        }

        arsort($transitions);
        return array_slice($transitions, 0, $limit, true);
    }

    /**
     * 入口页面分析
     */
    public static function getEntryPages(int $limit = 10): array {
        $events = self::allEvents();
        $entries = [];

        // 按用户分组
        $userEvents = [];
        foreach ($events as $e) {
            $vid = $e['visitor_id'];
            if (!isset($userEvents[$vid])) $userEvents[$vid] = [];
            $userEvents[$vid][] = $e;
        }

        // 找到每个用户的第一个页面
        foreach ($userEvents as $vid => $evts) {
            usort($evts, fn($a, $b) => strcmp($a['timestamp'], $b['timestamp']));
            $first = $evts[0] ?? null;
            if ($first && $first['event'] === 'page_view') {
                $page = $first['properties']['path'] ?? $first['url'] ?? '/';
                $entries[$page] = ($entries[$page] ?? 0) + 1;
            }
        }

        arsort($entries);
        return array_slice($entries, 0, $limit, true);
    }

    // ─── 营收分析 ──────────────────────────────────

    /**
     * 营收趋势
     */
    public static function getRevenueTrend(int $days = 30): array {
        $events = self::allEvents();
        $revenue = [];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $revenue[$date] = ['total' => 0, 'count' => 0, 'avg' => 0];
        }

        foreach ($events as $e) {
            if ($e['event'] === 'purchase') {
                $date = substr($e['timestamp'], 0, 10);
                $amount = (float)($e['properties']['amount'] ?? 0);
                if (isset($revenue[$date])) {
                    $revenue[$date]['total'] += $amount;
                    $revenue[$date]['count']++;
                }
            }
        }

        foreach ($revenue as &$data) {
            $data['avg'] = $data['count'] > 0 ? round($data['total'] / $data['count'], 2) : 0;
        }

        return $revenue;
    }

    /**
     * 转化营收归因（按渠道/设备/活动维度）
     * @param string $dimension channel | device | browser | os | utm_campaign
     */
    public static function getRevenueAttribution(string $dimension, int $days = 90): array {
        $events = self::allEvents();
        $cut = date('Y-m-d H:i:s', time() - $days * 86400);
        $result = [];

        foreach ($events as $e) {
            if ($e['event'] !== 'purchase') continue;
            if (($e['timestamp'] ?? '') < $cut) continue;
            $props = $e['properties'] ?? [];
            $dv = $props[$dimension] ?? '';
            if ($dv === '') $dv = '未知';
            $amount = (float)($props['amount'] ?? 0);
            if (!isset($result[$dv])) $result[$dv] = ['revenue' => 0, 'orders' => 0, 'avg' => 0];
            $result[$dv]['revenue'] += $amount;
            $result[$dv]['orders']++;
        }

        foreach ($result as &$d) $d['avg'] = $d['orders'] > 0 ? round($d['revenue'] / $d['orders'], 2) : 0;
        uasort($result, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        return $result;
    }

    /**
     * 转化事件按维度归因（含转化率：访问→转化）
     * 返回每个维度的转化指标
     */
    public static function getConversionByDimension(string $dimension, array $conversionEvents = ['purchase', 'form_submit', 'subscribe']): array {
        $events = self::allEvents();
        $result = [];
        foreach ($events as $e) {
            $props = $e['properties'] ?? [];
            $dv = $props[$dimension] ?? '';
            if ($dv === '') continue;
            $ev = $e['event'] ?? '';
            if (!isset($result[$dv])) $result[$dv] = ['visits' => 0, 'conversions' => 0, 'rate' => 0];
            if ($ev === 'page_view') $result[$dv]['visits']++;
            if (in_array($ev, $conversionEvents, true)) $result[$dv]['conversions']++;
        }
        foreach ($result as &$d) $d['rate'] = $d['visits'] > 0 ? round($d['conversions'] / $d['visits'] * 100, 2) : 0;
        uasort($result, fn($a, $b) => $b['conversions'] <=> $a['conversions']);
        return $result;
    }

    /**
     * 客户终身价值（LTV）
     */
    public static function getLTVAnalysis(): array {
        $events = self::allEvents();
        $profiles = self::allProfiles();

        // 按用户计算总消费
        $userRevenue = [];
        foreach ($events as $e) {
            if ($e['event'] === 'purchase') {
                $vid = $e['visitor_id'];
                $amount = (float)($e['properties']['amount'] ?? 0);
                $userRevenue[$vid] = ($userRevenue[$vid] ?? 0) + $amount;
            }
        }

        $ltv = [
            'total_revenue' => array_sum($userRevenue),
            'paying_users' => count($userRevenue),
            'total_users' => count($profiles),
            'conversion_rate' => count($profiles) > 0 ? round(count($userRevenue) / count($profiles) * 100, 2) : 0,
            'arpu' => count($profiles) > 0 ? round(array_sum($userRevenue) / count($profiles), 2) : 0,
            'arppu' => count($userRevenue) > 0 ? round(array_sum($userRevenue) / count($userRevenue), 2) : 0,
        ];

        // LTV 分层
        $ltvTiers = [
            '0' => 0,
            '1-50' => 0,
            '51-200' => 0,
            '201-500' => 0,
            '500+' => 0,
        ];

        foreach ($userRevenue as $amount) {
            if ($amount <= 0) $ltvTiers['0']++;
            elseif ($amount <= 50) $ltvTiers['1-50']++;
            elseif ($amount <= 200) $ltvTiers['51-200']++;
            elseif ($amount <= 500) $ltvTiers['201-500']++;
            else $ltvTiers['500+']++;
        }

        $ltv['tiers'] = $ltvTiers;

        return $ltv;
    }

    /**
     * 客单价分布
     */
    public static function getAOVDistribution(): array {
        $events = self::allEvents();
        $orders = [];

        foreach ($events as $e) {
            if ($e['event'] === 'purchase') {
                $amount = (float)($e['properties']['amount'] ?? 0);
                $orders[] = $amount;
            }
        }

        if (empty($orders)) return ['avg' => 0, 'min' => 0, 'max' => 0, 'median' => 0, 'distribution' => []];

        sort($orders);
        $dist = [
            '0-50' => 0,
            '51-100' => 0,
            '101-200' => 0,
            '201-500' => 0,
            '500+' => 0,
        ];

        foreach ($orders as $amt) {
            if ($amt <= 50) $dist['0-50']++;
            elseif ($amt <= 100) $dist['51-100']++;
            elseif ($amt <= 200) $dist['101-200']++;
            elseif ($amt <= 500) $dist['201-500']++;
            else $dist['500+']++;
        }

        return [
            'avg' => round(array_sum($orders) / count($orders), 2),
            'min' => min($orders),
            'max' => max($orders),
            'median' => $orders[(int)(count($orders) / 2)],
            'distribution' => $dist,
        ];
    }

    // ─── 渠道归因 ──────────────────────────────────

    /**
     * 渠道归因分析
     */
    public static function getChannelAttribution(): array {
        $events = self::allEvents();
        $channels = [];

        foreach ($events as $e) {
            $source = self::parseSource($e['referrer'] ?? '');
            $event = $e['event'];

            if (!isset($channels[$source])) {
                $channels[$source] = [
                    'visits' => 0,
                    'leads' => 0,
                    'conversions' => 0,
                    'revenue' => 0,
                ];
            }

            $channels[$source]['visits']++;

            if ($event === 'form_submit') $channels[$source]['leads']++;
            if ($event === 'purchase') {
                $channels[$source]['conversions']++;
                $channels[$source]['revenue'] += (float)($e['properties']['amount'] ?? 0);
            }
        }

        // 计算转化率
        foreach ($channels as &$ch) {
            $ch['conversion_rate'] = $ch['visits'] > 0 ? round($ch['conversions'] / $ch['visits'] * 100, 2) : 0;
        }

        // 按访问量排序
        uasort($channels, fn($a, $b) => $b['visits'] <=> $a['visits']);

        return $channels;
    }

    /**
     * 解析来源
     */
    private static function parseSource(string $referrer): string {
        if (empty($referrer)) return '直接访问';

        $host = parse_url($referrer, PHP_URL_HOST) ?? '';
        $host = strtolower($host);

        $sourceMap = [
            'google' => 'Google',
            'baidu' => '百度',
            'bing' => 'Bing',
            'sogou' => '搜狗',
            'weibo' => '微博',
            'weixin' => '微信',
            'mp.weixin' => '微信公众号',
            'twitter' => 'Twitter',
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'zhihu' => '知乎',
            'douyin' => '抖音',
            'bilibili' => 'B站',
            'xiaohongshu' => '小红书',
        ];

        foreach ($sourceMap as $key => $name) {
            if (str_contains($host, $key)) return $name;
        }

        return $host ?: '其他';
    }

    // ─── 辅助函数 ──────────────────────────────────

    private static function getVisitorId(): string {
        if (isset($_COOKIE['cdp_vid'])) return $_COOKIE['cdp_vid'];

        $vid = 'vid_' . bin2hex(random_bytes(8));
        setcookie('cdp_vid', $vid, time() + 86400 * 365, '/');
        return $vid;
    }

    private static function getClientIp(): string {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                return trim($ip);
            }
        }
        return '127.0.0.1';
    }
}
