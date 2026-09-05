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
// P0：深引擎开箱即用 —— 空/缺配置回退到内置默认（data/cdp/*.json 可覆盖）
require_once __DIR__ . '/CdpDefaults.php';

class CdpSystem {
    private static string $eventsFile = DATA_DIR . '/cdp/events.json';
    private static string $profilesFile = DATA_DIR . '/cdp/profiles.json';
    private static string $segmentsFile = DATA_DIR . '/cdp/segments.json';
    private static string $propertiesFile = DATA_DIR . '/cdp/properties.json';
    private static array $eventsCache = [];

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

        // 同意门（BACKLOG T1-5）：未同意则不采集、不建画像。默认 mode=off 行为不变。
        try {
            require_once __DIR__ . '/ConsentSystem.php';
            if (!consent_granted()) return false;
        } catch (\Throwable $e) {}

        // heartbeat 服务端限流（防前端bug/爬虫高频刷事件表）：同一访客 90 秒内只记 1 次
        if ($event === 'heartbeat') {
            $visitorId = $visitorId ?: self::getVisitorId();
            $hbKey = 'hb_limit:' . substr(md5($visitorId), 0, 16);
            try {
                $fc = new FileCache();
                $last = (int)$fc->get($hbKey);
                if ($last && (time() - $last) < 90) return true; // 静默丢弃，不写库
                $fc->set($hbKey, time(), 120);
            } catch (\Throwable $e) {}
        }

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

        require_once __DIR__ . '/EventIdentity.php';
        $eventId = event_identity($data);
        unset($data['_event_id']);
        $entry = [
            'id' => $eventId,
            'event' => $event,
            'visitor_id' => $visitorId,
            'member_id' => $memberId,
            'properties' => $data,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => self::getClientIp(),
            'timestamp' => date('Y-m-d H:i:s'),
            'message_id' => $eventId,
            'ts' => time() * 1000,
            'session_id' => self::currentSessionId(),
        ];

        // 事件入库前允许插件改写或丢弃（返回 null / false 即丢弃）
        if (class_exists('PluginSystem')) {
            $filtered = PluginSystem::apply_filters('cdp_event_received', $entry);
            if ($filtered === null || $filtered === false) return false;
            if (is_array($filtered)) $entry = $filtered;
        }

        self::appendEvent($entry);

        // 更新用户画像
        self::updateProfile($visitorId, $memberId, $event, $data);

        if (class_exists('PluginSystem')) {
            PluginSystem::do_action('cdp_profile_updated', $visitorId, $memberId, $event, $data);
        }

        return true;
    }

    /**
     * 批量记录事件
     */
    public static function trackBatch(array $events): int {
        // 逐条仍走 track()——同意门、事件字典、身份合并、插件过滤、画像更新
        // 这些每条事件的语义一条都不能省。这里只做一件事：把整批包进一个事务，
        // 让 N 条事件只落一次盘（否则前端一次上报 20 条就是 20 次 fsync）。
        $conn = null; $own = false;
        try {
            $conn = Database::conn();
            if (!$conn->inTransaction()) { $conn->beginTransaction(); $own = true; }
        } catch (Exception $e) { $conn = null; }

        $count = 0;
        try {
            foreach ($events as $e) {
                if (self::track($e['event'], $e['properties'] ?? [], $e['visitor_id'] ?? '')) {
                    $count++;
                }
            }
        } catch (Exception $e) {
            if ($own && $conn && $conn->inTransaction()) { try { $conn->rollBack(); } catch (Exception $e2) {} }
            throw $e;
        }
        if ($own && $conn && $conn->inTransaction()) { try { $conn->commit(); } catch (Exception $e) {} }
        return $count;
    }

    /**
     * 获取事件列表
     */
    public static function allEvents(int $limit = 10000): array {
        // 与 JSON 存储的 10,000 条保留上限一致，避免大库后台页面一次性
        // fetchAll 数十万行并耗尽 PHP 内存。需要更小窗口的调用方可显式传入。
        $limit = max(1, min(100000, $limit));
        if (isset(self::$eventsCache[$limit])) return self::$eventsCache[$limit];
        // 优先读事件表（EventStore 统一存储，MySQL/SQLite），空则回退 JSON（兼容旧数据）
        try {
            require_once __DIR__ . '/EventStore.php';
            EventStore::ensureTable();
            $rows = EventStore::query("SELECT id, event, uid, member_id, props, page, ip, created_at, session_id, message_id, ts, event_category FROM events ORDER BY id DESC LIMIT {$limit}");
            if (!empty($rows)) {
                $out = [];
                foreach ($rows as $r) {
                    $props = json_decode($r['props'] ?? '[]', true);
                    if (!is_array($props)) $props = [];
                    // 事件类别写入 props（供分析区分）
                    if (!empty($r['event_category']) && !isset($props['event_category'])) $props['event_category'] = $r['event_category'];
                    $out[] = [
                        'id' => 'evt_' . $r['id'],
                        'event' => $r['event'] ?? '',
                        'visitor_id' => $r['uid'] ?? '',
                        'member_id' => $r['member_id'] ?? '',
                        'properties' => $props,
                        'url' => $r['page'] ?? '',
                        'ip' => $r['ip'] ?? '',
                        'timestamp' => $r['created_at'] ?? '',
                        'ts' => (int)($r['ts'] ?? 0),
                        'session_id' => $r['session_id'] ?? '',
                        'message_id' => $r['message_id'] ?? '',
                    ];
                }
                return self::$eventsCache[$limit] = array_reverse($out);
            }
        } catch (Exception $e) {}
        return self::$eventsCache[$limit] = array_slice(json_read(self::$eventsFile), -$limit);
    }

    /**
     * 追加事件 —— 写路径的唯一入口。
     *
     * 【为什么长这样】这里是全系统最热的一条路径（每个访客的每次行为都走一遍），
     * 所以它只能做一件事：**往表里加行**。
     * 旧实现是「先把最近一万条读进内存 → 追加一条 → 再把末尾 200 条重写一遍」，
     * 实测单条 134 ms / 26 MB，其中 99.4% 是无用功（见 docs/ROADMAP.md、
     * tests/events_writepath_bench.php）。换存储治不了这个，只能换写法。
     *
     * SQLite 不可用时才回退到 JSON——回退路径不得不整批读写，但那是异常分支。
     */
    private static function appendEvent(array $entry): void {
        self::$eventsCache = [];
        if (self::insertEventRows([$entry]) === 0) self::appendEventsJson([$entry]);
    }

    /**
     * 批量写入 events 表；返回成功写入的行数，失败（表/连接不可用）返回 0。
     * 已在事务中则复用外层事务，避免 trackBatch 每条一次 fsync。
     */
    private static function insertEventRows(array $entries): int {
        if (empty($entries)) return 0;
        // 事件存储走统一 EventStore 层：配置了 MySQL 写 MySQL，否则写 SQLite。
        // SQLite 与 MySQL 都不行时返回 0，上层回退 JSON（保留一万条上限）。
        require_once __DIR__ . '/EventStore.php';
        try {
            EventStore::ensureTable();
            return EventStore::recordBatch($entries);
        } catch (Exception $e) {
            return 0;
        }
    }

    /** 回退分支：SQLite 写不进时追加到 JSON（保留一万条上限）。 */
    private static function appendEventsJson(array $entries): void {
        if (!function_exists('json_read') || !function_exists('json_write')) return;
        $events = json_read(self::$eventsFile);
        foreach ($entries as $e) $events[] = $e;
        json_write(self::$eventsFile, array_slice($events, -10000));
    }

    // 当前会话 ID（30 分钟滚动，会话 ID = 会话开始时间戳，对标 Amplitude）
    public static function currentSessionId(): string {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $now = time();
            $sid = $_SESSION['cdp_session_id'] ?? 0;
            $sstart = $_SESSION['cdp_session_start'] ?? 0;
            if ($sid && $sstart && ($now - $sstart) < 1800) return (string)$sid;
            $sid = $now * 1000;
            $_SESSION['cdp_session_id'] = $sid;
            $_SESSION['cdp_session_start'] = $now;
            return (string)$sid;
        }
        return '';
    }

    // ─── 用户画像 ──────────────────────────────────

    // 存储：画像已迁到 SQLite 表 cdp_profiles（一画像一行 + member_id 索引），
    // 读写按行，updateProfile 不再整存整取。历史 profiles.json 首次访问一次性导入
    // （保留原文件作回滚备份）。落库细节见 lib/CdpProfileStore.php。

    /**
     * 获取用户画像（按行读）
     */
    public static function getProfile(string $visitorId): ?array {
        require_once __DIR__ . '/CdpProfileStore.php';
        return cdp_profile_get($visitorId);
    }

    /**
     * 获取所有画像（{visitor_id => profile}，与原 JSON 同形）
     */
    public static function allProfiles(): array {
        require_once __DIR__ . '/CdpProfileStore.php';
        return cdp_profile_all();
    }

    /**
     * 保存全量画像（供身份解析合并后回写）——表精确等于给定集合。
     */
    public static function saveProfiles(array $profiles): void {
        require_once __DIR__ . '/CdpProfileStore.php';
        cdp_profile_save_all($profiles);
    }

    /**
     * 更新用户画像（读一条 → 改 → 写一条，无写放大）
     */
    private static function updateProfile(string $visitorId, string $memberId, string $event, array $data): void {
        require_once __DIR__ . '/CdpProfileStore.php';
        $profile = cdp_profile_get($visitorId) ?? self::blankProfile($visitorId, $memberId);
        if (!isset($profile['properties']) || !is_array($profile['properties'])) $profile['properties'] = [];

        $profile['last_seen'] = date('Y-m-d H:i:s');
        $profile['events_count'] = (int)($profile['events_count'] ?? 0) + 1;

        if ($memberId && empty($profile['member_id'])) {
            $profile['member_id'] = $memberId;
        }

        // 属性字典驱动（set / set_once / increment / append）
        self::applyProperties($profile['properties'], $data);

        // 行为聚合摘要（summaries）
        self::applySummaries($profile, $event, $data);

        // 生命周期更新
        self::applyLifecycle($profile);

        // 自动打标签
        self::autoTag($profile, $event, $data);

        // 增量分群评估（进出群触发 MA）
        self::evaluateUserSegments($profile);

        cdp_profile_put($visitorId, $profile);
    }

    // 空白画像（8 层结构）
    private static function blankProfile(string $visitorId, string $memberId): array {
        $now = date('Y-m-d H:i:s');
        return [
            'visitor_id' => $visitorId,
            'member_id' => $memberId,
            'first_seen' => $now,
            'last_seen' => $now,
            'properties' => [],
            'events_count' => 0,
            'tags' => [],
            'summaries' => [
                'page_views_7d' => 0, 'page_views_30d' => 0,
                'purchase_count' => 0, 'purchase_amount_total' => 0,
                'purchase_amount_30d' => 0,
                'form_submits' => 0, 'courses_completed' => 0,
                'sessions_count' => 0,
            ],
            'scores' => [],
            'lifecycle' => ['stage' => 'new', 'first_seen' => $now, 'last_seen' => $now],
            'segment_memberships' => [],
        ];
    }

    /**
     * 内置属性字典。
     *
     * applyProperties() 对不在字典里的 key 一律 continue，而全仓没有任何代码
     * 往 data/cdp/properties.json 写过东西——文件不存在时字典为空，等于
     * 采集到的属性全部被丢弃，画像里的 properties 永远是空的。
     * 这里给出一份内置默认字典兜底；data/cdp/properties.json 若存在则以它为准
     * （逐 key 覆盖），管理员仍可自定义。
     *
     * update 语义：set 覆盖 / set_once 首次写入 / increment 累加 / append 追加去重
     */
    private static function defaultPropDict(): array {
        $set  = fn(string $label) => ['label' => $label, 'update' => 'set'];
        $once = fn(string $label) => ['label' => $label, 'update' => 'set_once'];
        return [
            // 身份
            'email'        => $set('邮箱'),
            'phone'        => $set('手机号'),
            'name'         => $set('姓名'),
            'nickname'     => $set('昵称'),
            'company'      => $set('公司'),
            'job_title'    => $set('职位'),
            'industry'     => $set('行业'),
            'city'         => $set('城市'),
            'country'      => $set('国家'),
            // 归因（首次触点应当锁死，不被后续访问覆盖）
            'source'       => $once('来源'),
            'referrer'     => $once('引荐页'),
            'landing_page' => $once('落地页'),
            'utm_source'   => $once('UTM Source'),
            'utm_medium'   => $once('UTM Medium'),
            'utm_campaign' => $once('UTM Campaign'),
            'utm_content'  => $once('UTM Content'),
            'utm_term'     => $once('UTM Term'),
            // 环境
            'device'       => $set('设备'),
            'browser'      => $set('浏览器'),
            'os'           => $set('操作系统'),
            'channel'      => $set('渠道'),
            // 累计与集合
            'total_spent'  => ['label' => '累计消费', 'update' => 'increment'],
            'interests'    => ['label' => '兴趣标签', 'update' => 'append'],
            'viewed_categories' => ['label' => '浏览过的分类', 'update' => 'append'],
        ];
    }

    // 属性字典读取（内存缓存）
    private static $propDictCache = null;
    private static function propDict(): array {
        if (self::$propDictCache === null) {
            $file = json_read(DATA_DIR . '/cdp/properties.json');
            if (!is_array($file)) $file = [];
            // 文件里定义过的 key 以文件为准，其余用内置默认
            self::$propDictCache = array_merge(self::defaultPropDict(), $file);
        }
        return self::$propDictCache;
    }

    // 按字典应用属性（四语义）
    private static function applyProperties(array &$props, array $data): void {
        $dict = self::propDict();
        foreach ($data as $key => $value) {
            if (!isset($dict[$key]) || $value === null || $value === '') continue;
            $def = $dict[$key];
            $update = $def['update'] ?? 'set';
            switch ($update) {
                case 'set_once':
                    if (!array_key_exists($key, $props)) $props[$key] = $value;
                    break;
                case 'increment':
                    $props[$key] = round((float)($props[$key] ?? 0) + (float)$value, 2);
                    break;
                case 'append':
                    $arr = is_array($props[$key] ?? null) ? $props[$key] : [];
                    if (!in_array($value, $arr, true)) { $arr[] = $value; $props[$key] = array_values($arr); }
                    break;
                default:
                    $props[$key] = $value;
            }
        }
    }

    // 行为聚合摘要（对标 computed traits，增量更新）
    private static function applySummaries(array &$profile, string $event, array $data): void {
        $s = &$profile['summaries'];
        if ($event === 'page_view') {
            $s['page_views_7d'] = ($s['page_views_7d'] ?? 0) + 1;
            $s['page_views_30d'] = ($s['page_views_30d'] ?? 0) + 1;
        } elseif ($event === 'purchase') {
            $amount = (float)($data['amount'] ?? 0);
            $s['purchase_count'] = ($s['purchase_count'] ?? 0) + 1;
            $s['purchase_amount_total'] = ($s['purchase_amount_total'] ?? 0) + $amount;
            $s['purchase_amount_30d'] = ($s['purchase_amount_30d'] ?? 0) + $amount;
        } elseif ($event === 'form_submit') {
            $s['form_submits'] = ($s['form_submits'] ?? 0) + 1;
        } elseif ($event === 'course_complete') {
            $s['courses_completed'] = ($s['courses_completed'] ?? 0) + 1;
        } elseif ($event === 'session_start' || $event === 'vst') {
            $s['sessions_count'] = ($s['sessions_count'] ?? 0) + 1;
        }
    }

    // 生命周期：new/active/dormant/at_risk/churned（按首访/末访天数）
    private static function applyLifecycle(array &$profile): void {
        $now = time();
        $first = strtotime($profile['first_seen'] ?? $now);
        $last = strtotime($profile['last_seen'] ?? $now);
        $dFirst = ($now - $first) / 86400;
        $dLast = ($now - $last) / 86400;
        $cfg = json_read(DATA_DIR . '/cdp/lifecycle.json');
        if (empty($cfg) && function_exists('cdp_default_lifecycle')) $cfg = cdp_default_lifecycle();
        $newDays = (int)($cfg['new_days'] ?? 7);
        $activeDays = (int)($cfg['active_days'] ?? 7);
        $dormantDays = (int)($cfg['dormant_days'] ?? 30);
        $churnedDays = (int)($cfg['churned_days'] ?? 90);
        if ($dFirst <= $newDays) $stage = 'new';
        elseif ($dLast <= $activeDays) $stage = 'active';
        elseif ($dLast <= $dormantDays) $stage = 'dormant';
        elseif ($dLast <= $churnedDays) $stage = 'at_risk';
        else $stage = 'churned';
        $profile['lifecycle']['stage'] = $stage;
        $profile['lifecycle']['first_seen'] = $profile['first_seen'];
        $profile['lifecycle']['last_seen'] = $profile['last_seen'];
    }

    /**
     * 自动打标签
     */
    private static function autoTag(array &$profile, string $event, array $data): void {
        // 规则驱动的自动标签（tag_rules.json；空则回退内置默认，保证开箱即用）
        $rules = json_read(DATA_DIR . '/cdp/tag_rules.json');
        if (empty($rules) && function_exists('cdp_default_tag_rules')) $rules = cdp_default_tag_rules();
        if (is_array($rules)) {
            $tags = &$profile['tags'];
            foreach ($rules as $rid => $rule) {
                if (empty($rule['enabled']) || empty($rule['tag'])) continue;
                $match = self::matchTagRule($rule['when'], $profile, $event, $data);
                if ($match) {
                    if (!isset($tags[$rule['tag']])) $tags[$rule['tag']] = ['type'=>'auto','rule_id'=>$rid,'at'=>date('Y-m-d H:i:s')];
                }
            }
        }

        // 兼容：保留角色/渠道标签（历史行为）
        if ($event === 'role_selected' && !empty($data['role'])) {
            $k = 'role:' . $data['role'];
            if (!isset($profile['tags'][$k])) $profile['tags'][$k] = ['type'=>'auto','rule_id'=>'role','at'=>date('Y-m-d H:i:s')];
        }
        if (!empty($data['channel'])) {
            $k = 'channel:' . $data['channel'];
            if (!isset($profile['tags'][$k])) $profile['tags'][$k] = ['type'=>'auto','rule_id'=>'channel','at'=>date('Y-m-d H:i:s')];
        }
    }

    // 标签规则匹配（event/summary/lifecycle/property）
    private static function matchTagRule(array $cond, array $profile, string $event, array $data): bool {
        $type = $cond['type'] ?? 'event';
        switch ($type) {
            case 'event':
                if (($cond['event'] ?? '') !== $event) return false;
                return self::compare(1, $cond['operator'] ?? 'gte', $cond['value'] ?? 1);
            case 'summary':
                $field = $cond['field'] ?? '';
                $actual = $profile['summaries'][$field] ?? ($profile[$field] ?? 0);
                return self::compare($actual, $cond['operator'] ?? 'gte', $cond['value'] ?? 0);
            case 'lifecycle':
                $stage = $profile['lifecycle']['stage'] ?? '';
                return self::compare($stage, $cond['operator'] ?? 'eq', $cond['value'] ?? '');
            case 'property':
                $field = $cond['field'] ?? '';
                $actual = $profile['properties'][$field] ?? '';
                return self::compare($actual, $cond['operator'] ?? 'eq', $cond['value'] ?? '');
        }
        return false;
    }

    // ─── 用户分群 ──────────────────────────────────

    /**
     * 获取所有分群
     */
    public static function allSegments(): array {
        $segments = json_read(self::$segmentsFile);
        if (empty($segments) && function_exists('cdp_default_segments')) return cdp_default_segments();
        return $segments;
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
        return self::matchRuleGroup($rules, $profile);
    }

    // 规则组递归评估（and/or + 嵌套 group）
    private static function matchRuleGroup(array $group, array $profile): bool {
        $operator = $group['operator'] ?? 'and';
        $rules = $group['rules'] ?? $group; // 兼容纯列表（默认 and）
        if (isset($group['operator']) && isset($group['rules'])) {
            foreach ($rules as $rule) {
                if (($rule['type'] ?? '') === 'group') {
                    $matched = self::matchRuleGroup($rule, $profile);
                } else {
                    $matched = self::matchSingleRule($rule, $profile);
                }
                if ($operator === 'and' && !$matched) return false;
                if ($operator === 'or' && $matched) return true;
            }
            return $operator === 'and';
        }
        // 纯规则列表：AND
        foreach ($rules as $rule) {
            $matched = ($rule['type'] ?? '') === 'group' ? self::matchRuleGroup($rule, $profile) : self::matchSingleRule($rule, $profile);
            if (!$matched) return false;
        }
        return true;
    }

    // 单条规则评估
    private static function matchSingleRule(array $rule, array $profile): bool {
        $type = $rule['type'] ?? '';
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? '';

        switch ($type) {
            case 'property':
                $actual = $profile['properties'][$field] ?? '';
                return self::compare($actual, $operator, $value);

            case 'event':
                $eventName = $rule['event'] ?? '';
                $windowDays = (int)($rule['window'] ?? 0);
                $count = self::countUserEvents($profile['visitor_id'], $eventName, $windowDays);
                return self::compare($count, $operator, (int)$value);

            case 'summary':
                $actual = $profile['summaries'][$field] ?? ($profile[$field] ?? 0);
                return self::compare($actual, $operator, $value);

            case 'lifecycle':
                $stage = $profile['lifecycle']['stage'] ?? '';
                return self::compare($stage, $operator, $value);

            case 'tag':
                $tags = $profile['tags'] ?? [];
                if (is_array($tags)) return isset($tags[$field]) || in_array($field, $tags, true);
                return in_array($field, $tags, true);

            case 'last_seen':
                $days = (int)$value;
                $lastSeen = strtotime($profile['last_seen'] ?? '2000-01-01');
                $diff = (time() - $lastSeen) / 86400;
                return self::compare($diff, $operator, $days);

            case 'first_seen':
                $days = (int)$value;
                $first = strtotime($profile['first_seen'] ?? '2000-01-01');
                $diff = (time() - $first) / 86400;
                return self::compare($diff, $operator, $days);

            case 'segment':
                $segId = $value ?? '';
                return !empty($profile['segment_memberships'][$segId]);
        }
        return false;
    }

    private static function compare($actual, string $operator, $expected): bool {
        switch ($operator) {
            case 'equals': case 'eq': case '==': return $actual == $expected;
            case 'not_equals': case 'ne': return $actual != $expected;
            case 'greater_than': case 'gt': case '>': return $actual > $expected;
            case 'less_than': case 'lt': case '<': return $actual < $expected;
            case 'gte': case '>=': return $actual >= $expected;
            case 'lte': case '<=': return $actual <= $expected;
            case 'contains': return stripos((string)$actual, (string)$expected) !== false;
            case 'starts_with': return stripos((string)$actual, (string)$expected) === 0;
            case 'in': return in_array($actual, (array)$expected);
            default: return false;
        }
    }

    /**
     * 某访客某事件的发生次数（分群规则用）。
     *
     * 【为什么走 SQL】这个方法在每次埋点后的分群评估里被调用，是第二条热路径。
     * 旧实现是把最近一万条事件读进 PHP 再逐条比对——等于每条事件多扫一万行。
     * 改成带索引的 COUNT(*)（idx_events_uid_event），交给 SQLite 做它擅长的事。
     * SQLite 不可用时回退到原来的内存扫描，语义完全一致。
     */
    private static function countUserEvents(string $visitorId, string $event, int $windowDays = 0): int {
        $cutoff = $windowDays > 0 ? date('Y-m-d H:i:s', time() - $windowDays * 86400) : '';
        try {
            $sql = "SELECT COUNT(*) AS n FROM events WHERE uid = ? AND event = ?";
            $args = [$visitorId, $event];
            if ($cutoff !== '') { $sql .= " AND created_at >= ?"; $args[] = $cutoff; }
            $rows = Database::query($sql, $args);
            if (isset($rows[0]['n'])) return (int)$rows[0]['n'];
        } catch (Exception $e) {}

        // 回退：内存扫描（仅在 SQLite 不可用时）
        $count = 0;
        foreach (self::allEvents() as $e) {
            if (($e['visitor_id'] ?? '') === $visitorId && ($e['event'] ?? '') === $event) {
                if ($cutoff !== '' && ($e['timestamp'] ?? '') < $cutoff) continue;
                $count++;
            }
        }
        return $count;
    }

    // 单用户增量分群评估（事件后调用，对标 Segment 实时进出群）
    // 维护 segment_memberships + 触发 segment_enter/segment_exit 事件供 MA 消费
    private static function evaluateUserSegments(array &$profile): void {
        $segments = self::allSegments();
        if (empty($segments)) return;
        $memberships = &$profile['segment_memberships'];
        foreach ($segments as $seg) {
            $segId = $seg['id'] ?? '';
            if ($segId === '') continue;
            $rules = $seg['rules'] ?? [];
            $operator = $seg['operator'] ?? 'and';
            $in = self::matchRuleGroup(['operator'=>$operator, 'rules'=>$rules], $profile);
            $wasIn = isset($memberships[$segId]);
            if ($in && !$wasIn) {
                $memberships[$segId] = ['joined_at'=>date('Y-m-d H:i:s'), 'evaluated_at'=>date('Y-m-d H:i:s')];
                // 进出群事件（供 FlowSystem/AutomationSystem 消费）
                try {
                    if (function_exists('flow_handle')) flow_handle('segment_enter', ['profile_id'=>$profile['visitor_id'] ?? '', 'segment_id'=>$segId, 'props'=>['segment_name'=>$seg['name'] ?? $segId]]);
                } catch (Throwable $e) {}
                if (class_exists('PluginSystem')) PluginSystem::do_action('cdp_segment_enter', $segId, $profile, $seg);
                // 人群激活（BACKLOG T0-6）：进群实时推给该人群的 realtime 目的地。旁路。
                try { require_once __DIR__ . '/DestinationSystem.php'; dest_on_segment_enter($segId, $profile); } catch (Throwable $e) {}
            } elseif (!$in && $wasIn) {
                unset($memberships[$segId]);
                try {
                    if (function_exists('flow_handle')) flow_handle('segment_exit', ['profile_id'=>$profile['visitor_id'] ?? '', 'segment_id'=>$segId, 'props'=>['segment_name'=>$seg['name'] ?? $segId]]);
                } catch (Throwable $e) {}
                if (class_exists('PluginSystem')) PluginSystem::do_action('cdp_segment_exit', $segId, $profile, $seg);
            }
        }
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
    // 顺序漏斗（对标 Amplitude）：步骤 = 事件 + 过滤器，窗口内严格顺序，每用户每步只计一次
    // $steps: [['event'=>'x','filters'=>['field'=>['op','value']]], ...]
    // $opts: ['days'=>30, 'window_days'=>7, 'ordered'=>true, 'group_by'=>'channel']
    public static function getFunnel(array $steps, int $days = 30, array $opts = []): array {
        $events = self::allEvents();
        $windowDays = (int)($opts['window_days'] ?? 0); // 0 = 不限窗口
        $ordered = ($opts['ordered'] ?? true) !== false;
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));

        // 步骤解析：兼容字符串（老调用）和对象
        $stepDefs = [];
        foreach ($steps as $i => $s) {
            if (is_string($s)) $stepDefs[$i] = ['event' => $s, 'filters' => []];
            else $stepDefs[$i] = ['event' => $s['event'] ?? '', 'filters' => $s['filters'] ?? []];
        }

        // 按用户聚合事件（时间排序）
        $userEvents = [];
        foreach ($events as $e) {
            if (($e['timestamp'] ?? '') < $cutoff) continue;
            $uid = $e['visitor_id'] ?? '';
            if ($uid === '') continue;
            $userEvents[$uid][] = $e;
        }
        foreach ($userEvents as &$ue) usort($ue, fn($a, $b) => strcmp($a['timestamp'] ?? '', $b['timestamp'] ?? ''));
        unset($ue);

        $funnel = [];
        $prevMatches = null; // 进入上一布的用户集合
        foreach ($stepDefs as $i => $def) {
            $matches = [];
            foreach ($userEvents as $uid => $ue) {
                // 顺序漏斗：用户必须已通过前一步
                if ($prevMatches !== null && !isset($prevMatches[$uid])) continue;
                foreach ($ue as $e) {
                    if ($e['event'] !== $def['event']) continue;
                    if (!self::matchStepFilters($e, $def['filters'])) continue;
                    // 窗口约束（进入漏斗后 windowDays 内）
                    if ($windowDays > 0 && isset($firstEnter[$uid])) {
                        $enterTs = strtotime($firstEnter[$uid]);
                        if ($enterTs && strtotime($e['timestamp'] ?? '') > $enterTs + $windowDays * 86400) continue;
                    }
                    if ($i === 0) $firstEnter[$uid] = $e['timestamp'] ?? $firstEnter[$uid] ?? '';
                    $matches[$uid] = true;
                    break; // 每用户每步只计一次
                }
            }
            $count = count($matches);
            $funnel[] = [
                'step' => $def['event'],
                'count' => $count,
                'rate' => ($prevMatches && $prevMatches > 0) ? round($count / count($prevMatches) * 100, 1) : 100,
                'users' => array_keys($matches),
            ];
            $prevMatches = $matches;
            if ($count === 0) break; // 断流
        }
        return $funnel;
    }

    // 步骤过滤（事件属性匹配）
    private static function matchStepFilters(array $event, array $filters): bool {
        if (empty($filters)) return true;
        $props = $event['properties'] ?? [];
        foreach ($filters as $field => $cond) {
            $actual = $props[$field] ?? ($event[$field] ?? '');
            if (is_array($cond)) {
                $op = $cond[0] ?? 'eq';
                $expect = $cond[1] ?? '';
                if (!self::compare($actual, $op, $expect)) return false;
            } else {
                if ((string)$actual !== (string)$cond) return false;
            }
        }
        return true;
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
            foreach (($p['tags'] ?? []) as $tagKey => $tagVal) {
                $tagName = is_int($tagKey) ? $tagVal : $tagKey; // 兼容值列表/关联数组(带元数据)
                if (!is_string($tagName)) continue;
                $tags[$tagName] = ($tags[$tagName] ?? 0) + 1;
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
        $rules = json_read(DATA_DIR . '/cdp/scoring_rules.json');
        if (empty($rules) && function_exists('cdp_default_scoring_rules')) $rules = cdp_default_scoring_rules();
        $hcfg = $rules['health'] ?? [];
        if (empty($hcfg)) { // 回退默认
            $now = time();
            $lastSeen = strtotime($profile['last_seen'] ?? '2000-01-01');
            $daysSinceLast = ($now - $lastSeen) / 86400;
            $health = max(0, 100 - (int)min(100, $daysSinceLast * 2));
            self::persistScores($visitorId, $profile, $health);
            return $health;
        }

        $score = 0;
        $now = time();
        // 活跃度（recency buckets）
        $daysSinceLast = ($now - strtotime($profile['last_seen'] ?? '2000-01-01')) / 86400;
        foreach (($hcfg['recency']['buckets'] ?? []) as $b) {
            if (isset($b['lte_days']) && $daysSinceLast <= $b['lte_days']) { $score += $b['points']; break; }
            if (isset($b['else'])) $score += $b['else'];
        }
        // 频率（frequency buckets）
        $events = $profile['events_count'] ?? 0;
        foreach (($hcfg['frequency']['buckets'] ?? []) as $b) {
            if (isset($b['gte_events']) && $events >= $b['gte_events']) { $score += $b['points']; break; }
            if (isset($b['else'])) $score += $b['else'];
        }
        // 标签加成（tags）
        foreach (($hcfg['tags']['tags'] ?? []) as $tb) {
            if (isset($profile['tags'][$tb['tag']])) $score += $tb['points'];
        }
        $health = (int)min($hcfg['cap'] ?? 100, $score);
        self::persistScores($visitorId, $profile, $health);
        return $health;
    }

    /**
     * 计算活跃度（engagement）并随健康分一起写入画像 scores
     */
    private static function persistScores(string $visitorId, array $profile, int $health): void {
        try {
            $now = time();
            $lastSeen = strtotime($profile['last_seen'] ?? '2000-01-01');
            $days = max(0, ($now - $lastSeen) / 86400);
            // 活跃度：近 7 天有行为 + 行为频率 + 完整度
            $engagement = 0;
            $engagement += $days <= 1 ? 40 : ($days <= 7 ? 25 : ($days <= 30 ? 10 : 0));   // 近度
            $engagement += min(30, ($profile['events_count'] ?? 0) * 3);                    // 频率
            $engagement += count($profile['tags'] ?? []) >= 3 ? 20 : count($profile['tags'] ?? []) * 5; // 画像完整度
            $engagement = (int)min(100, $engagement);
            $scores = (array)($profile['scores'] ?? []);
            $scores['health'] = $health;
            $scores['engagement'] = $engagement;
            $profiles = self::allProfiles();
            foreach ($profiles as &$p) {
                if (($p['visitor_id'] ?? '') === $visitorId) { $p['scores'] = $scores; break; }
            }
            unset($p);
            self::saveProfiles($profiles);
        } catch (Exception $e) {}
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
    public static function getRetention(int $days = 30, array $opts = []): array {
        $events = self::allEvents();
        $profiles = self::allProfiles();
        $startEvent = $opts['start_event'] ?? ''; // '' = 任意首访（用 first_seen）
        $retEvent = $opts['ret_event'] ?? '';      // '' = 任意事件（回访）
        $granularity = $opts['granularity'] ?? 'day'; // day/week/month

        // cohort 归属：用户进入（首次 start_event 或 first_seen）
        $userCohort = []; // vid => cohort_date
        $userEventDates = []; // vid => 事件日期集合
        foreach ($events as $e) {
            $vid = $e['visitor_id'];
            if ($vid === '') continue;
            $ts = $e['timestamp'] ?? '';
            if ($startEvent === '' || $e['event'] === $startEvent) {
                if (!isset($userCohort[$vid])) $userCohort[$vid] = self::granularityDate($ts, $granularity);
            }
            if ($retEvent === '' || $e['event'] === $retEvent) {
                if (!isset($userEventDates[$vid])) $userEventDates[$vid] = [];
                $userEventDates[$vid][self::granularityDate($ts, $granularity)] = true;
            }
        }
        // start_event 为空时，用画像 first_seen 补 cohort
        if ($startEvent === '') {
            foreach ($profiles as $p) {
                $vid = $p['visitor_id'] ?? '';
                if ($vid !== '' && !isset($userCohort[$vid])) {
                    $userCohort[$vid] = self::granularityDate($p['first_seen'] ?? '', $granularity);
                }
            }
        }

        // 构建 cohort 矩阵
        $cohortDates = [];
        for ($i = 0; $i < $days; $i++) {
            $ts = strtotime("-{$i} day");
            $cohortDates[] = self::granularityDate(date('Y-m-d', $ts), $granularity);
        }
        $cohortDates = array_values(array_unique($cohortDates));

        $matrix = [];
        foreach ($cohortDates as $cd) {
            $users = array_keys(array_filter($userCohort, fn($d) => $d === $cd));
            $size = count($users);
            $offsets = [];
            $retained = [];
            foreach ($cohortDates as $od) {
                if ($od < $cd) continue; // 只看当期及之后
                $retainedUsers = 0;
                foreach ($users as $u) {
                    if (isset($userEventDates[$u][$od])) $retainedUsers++;
                }
                $rate = $size > 0 ? round($retainedUsers / $size * 100, 1) : null;
                $offsets[$od] = $rate;
                $dayDiff = (int)round((strtotime($od) - strtotime($cd)) / 86400);
                $retained[$dayDiff] = ['day' => $dayDiff, 'rate' => $rate];
            }
            $matrix[] = ['date'=>$cd, 'cohort_size'=>$size, 'retention'=>$offsets, 'retained'=>$retained];
        }

        return $matrix;
    }

    // 按粒度归一到周期起点（day/week/month）
    private static function granularityDate(string $ts, string $granularity): string {
        $t = strtotime($ts ?: '2000-01-01');
        if ($t === false) $t = time();
        if ($granularity === 'week') return date('Y-m-d', strtotime('monday this week', $t));
        if ($granularity === 'month') return date('Y-m-01', $t);
        return date('Y-m-d', $t);
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
        $rules = json_read(DATA_DIR . '/cdp/scoring_rules.json');
        foreach (($rules['rfm']['r'] ?? []) as $b) {
            if (isset($b['lte_days']) && $days <= $b['lte_days']) return $b['score'];
            if (isset($b['else'])) return $b['else'];
        }
        return 1;
    }

    private static function scoreFrequency(int $count): int {
        $rules = json_read(DATA_DIR . '/cdp/scoring_rules.json');
        foreach (($rules['rfm']['f'] ?? []) as $b) {
            if (isset($b['gte']) && $count >= $b['gte']) return $b['score'];
            if (isset($b['else'])) return $b['else'];
        }
        return 1;
    }

    private static function scoreMonetary(float $amount): int {
        $rules = json_read(DATA_DIR . '/cdp/scoring_rules.json');
        foreach (($rules['rfm']['m'] ?? []) as $b) {
            if (isset($b['gte']) && $amount >= $b['gte']) return $b['score'];
            if (isset($b['else'])) return $b['else'];
        }
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
