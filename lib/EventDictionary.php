<?php
/**
 * 事件字典 EventDictionary
 * 可视化维护事件定义：名称/分类/属性/启用状态/采集统计
 * 提供：全量字典、采集统计、事件开关
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/CdpSystem.php';

class EventDictionary {
    private static string $configFile = DATA_DIR . '/event-dictionary.json';

    /**
     * 内置事件字典（与 docs/TRACKING-PLAN.md 对齐）
     */
    public static function builtin(): array {
        return [
            // ── 自动采集 ──
            ['name' => 'page_view', 'category' => '自动采集', 'desc' => '页面浏览（自动）', 'props' => 'path,title,os,browser,device,channel,session_id'],
            ['name' => 'scroll_depth', 'category' => '自动采集', 'desc' => '滚动深度 25/50/75/100%（自动）', 'props' => 'percent,path'],
            ['name' => 'element_click', 'category' => '自动采集', 'desc' => '元素点击（自动）', 'props' => 'tag,text,href,selector,is_outbound'],
            ['name' => 'form_submit', 'category' => '自动采集', 'desc' => '表单提交（自动）', 'props' => 'form_id,form_name,fields'],
            ['name' => 'outbound_click', 'category' => '自动采集', 'desc' => '站外链接点击（自动）', 'props' => 'href,domain,text'],
            ['name' => 'js_error', 'category' => '自动采集', 'desc' => '前端JS错误（自动）', 'props' => 'message,source,lineno'],
            ['name' => 'site_search', 'category' => '自动采集', 'desc' => '站内搜索（自动）', 'props' => 'keyword,path'],
            ['name' => 'time_on_page', 'category' => '自动采集', 'desc' => '页面停留时长（自动）', 'props' => 'duration_sec,path'],
            ['name' => 'heartbeat', 'category' => '自动采集', 'desc' => '30s活跃心跳（自动）', 'props' => 'duration_sec'],
            ['name' => 'utm_landing', 'category' => '自动采集', 'desc' => '带UTM落地（自动）', 'props' => 'utm_* ,channel'],
            // ── 内容 ──
            ['name' => 'article_view', 'category' => '内容', 'desc' => '文章浏览', 'props' => 'article_id,slug,category,tags,author'],
            ['name' => 'article_share', 'category' => '内容', 'desc' => '文章分享', 'props' => 'article_id,platform,channel'],
            ['name' => 'article_like', 'category' => '内容', 'desc' => '文章点赞', 'props' => 'article_id'],
            ['name' => 'article_bookmark', 'category' => '内容', 'desc' => '文章收藏', 'props' => 'article_id,target_type'],
            ['name' => 'article_download', 'category' => '内容', 'desc' => '资料下载', 'props' => 'download_id,title,type'],
            // ── 课程 ──
            ['name' => 'course_view', 'category' => '课程', 'desc' => '课程浏览', 'props' => 'course_id,title,category'],
            ['name' => 'course_enroll', 'category' => '课程', 'desc' => '课程报名', 'props' => 'course_id,price'],
            ['name' => 'lesson_start', 'category' => '课程', 'desc' => '开始课时', 'props' => 'course_id,lesson_id'],
            ['name' => 'lesson_complete', 'category' => '课程', 'desc' => '完成课时', 'props' => 'course_id,lesson_id'],
            ['name' => 'course_complete', 'category' => '课程', 'desc' => '学完整课', 'props' => 'course_id,percent'],
            // ── 转化（conversion） ──
            ['name' => 'purchase', 'category' => '转化', 'desc' => '订单支付', 'props' => 'order_id,product_id,amount,currency', 'conversion' => true],
            ['name' => 'lead_created', 'category' => '转化', 'desc' => '线索生成', 'props' => 'lead_id,email,source', 'conversion' => true],
            ['name' => 'subscribe', 'category' => '转化', 'desc' => '订阅/升级', 'props' => 'plan_id,tier,amount,period', 'conversion' => true],
            ['name' => 'activation', 'category' => '转化', 'desc' => '激活码使用', 'props' => 'code,product', 'conversion' => true],
            ['name' => 'consultation_booked', 'category' => '转化', 'desc' => '预约咨询', 'props' => 'consultation_id,type', 'conversion' => true],
            // ── 社区 ──
            ['name' => 'post_view', 'category' => '社区', 'desc' => '帖子浏览', 'props' => 'post_id,topic'],
            ['name' => 'post_create', 'category' => '社区', 'desc' => '发帖', 'props' => 'post_id,topic'],
            ['name' => 'comment_create', 'category' => '社区', 'desc' => '评论', 'props' => 'post_id,content_len'],
            ['name' => 'post_like', 'category' => '社区', 'desc' => '点赞', 'props' => 'post_id'],
            // ── 用户 ──
            ['name' => 'user_register', 'category' => '用户', 'desc' => '注册', 'props' => 'method,referral'],
            ['name' => 'user_login', 'category' => '用户', 'desc' => '登录', 'props' => 'method'],
            ['name' => 'user_logout', 'category' => '用户', 'desc' => '登出', 'props' => ''],
            ['name' => 'role_selected', 'category' => '用户', 'desc' => '角色选择', 'props' => 'role,page'],
            ['name' => 'role_inferred', 'category' => '用户', 'desc' => '渠道推断角色', 'props' => 'role,page'],
            // ── 工具 ──
            ['name' => 'tool_use', 'category' => '工具', 'desc' => '使用工具箱', 'props' => 'tool_id,tool_name'],
            ['name' => 'seo_check', 'category' => '工具', 'desc' => 'SEO体检', 'props' => 'tool_id,score'],
            // ── 系统 ──
            ['name' => 'identify', 'category' => '系统', 'desc' => '用户识别', 'props' => 'email,name,phone'],
            ['name' => '$user_update', 'category' => '系统', 'desc' => '用户属性更新', 'props' => '自定义'],
        ];
    }

    /**
     * 获取事件配置（含用户自定义开关，与内置合并）
     */
    public static function config(): array {
        $cfg = json_read(self::$configFile);
        $builtin = self::builtin();
        foreach ($builtin as &$ev) {
            $ev['enabled'] = true;
            if (isset($cfg[$ev['name']])) {
                $ev['enabled'] = !empty($cfg[$ev['name']]['enabled']);
            }
        }
        return $builtin;
    }

    /**
     * 保存事件开关
     */
    public static function saveSwitches(array $switches): void {
        $cfg = [];
        foreach ($switches as $name => $enabled) {
            $cfg[$name] = ['enabled' => $enabled];
        }
        json_write(self::$configFile, $cfg);
        self::$switchCache = null;   // 配置变了，作废本请求的缓存
    }

    /** 本请求内的开关缓存（见 isEnabled 的说明）。 */
    private static ?array $switchCache = null;

    /**
     * 是否启用某事件（供采集侧过滤）
     *
     * 这是 CdpSystem::track() 的第一道检查，**每条事件都会走一次**。
     * 原来每次都 json_read 一遍开关文件——一次上报 20 条事件就是 20 次
     * 文件读 + JSON 解析，纯属重复劳动。开关在单次请求内不会变，缓存住即可。
     */
    public static function isEnabled(string $event): bool {
        if (self::$switchCache === null) {
            $cfg = json_read(self::$configFile);
            self::$switchCache = is_array($cfg) ? $cfg : [];
        }
        $cfg = self::$switchCache;
        if (isset($cfg[$event])) return !empty($cfg[$event]['enabled']);
        return true; // 未配置默认启用
    }

    /**
     * 采集统计（从事件流）
     */
    public static function stats(): array {
        $events = CdpSystem::allEvents();
        $byEvent = [];
        $byCategory = [];
        $total = 0;
        foreach ($events as $e) {
            $ev = $e['event'] ?? '';
            $byEvent[$ev] = ($byEvent[$ev] ?? 0) + 1;
            $total++;
        }
        foreach (self::builtin() as $b) {
            $byCategory[$b['category']] = ($byCategory[$b['category']] ?? 0) + ($byEvent[$b['name']] ?? 0);
        }
        return ['by_event' => $byEvent, 'by_category' => $byCategory, 'total' => $total];
    }

    /**
     * Tracking Plan：为内置事件生成 schema（prop => 'type:required?'）
     */
    public static function buildSchema(string $event): array {
        static $schemaCache = null;
        if ($schemaCache === null) {
            $schemaCache = [];
            foreach (self::builtin() as $b) {
                $sc = [];
                foreach (array_filter(array_map('trim', explode(',', $b['props'] ?? ''))) as $p) {
                    if ($p === 'utm_*') continue;
                    $sc[$p] = 'string';
                }
                $schemaCache[$b['name']] = $sc;
            }
        }
        return $schemaCache[$event] ?? [];
    }

    /**
     * 校验事件数据（Tracking Plan 合规）：返回质量问题列表
     * 仅记录不拦截（避免丢事件），质量进入 data-quality.json 供监控
     */
    public static function validate(string $event, array $data): array {
        $issues = [];
        $schema = self::buildSchema($event);
        if (empty($schema)) return $issues;
        $required = ['member_id', 'email']; // 关键身份字段，缺失算问题
        foreach ($required as $k) {
            if (empty($data[$k]) && ($event === 'purchase' || $event === 'form_submit')) $issues[] = "缺少身份字段 {$k}";
        }
        foreach ($schema as $prop => $type) {
            if (!array_key_exists($prop, $data)) {
                // 必填：page_view 的 path / form_submit 的 form_id 等
                if (in_array($prop, ['path', 'form_id', 'course_id', 'slug'], true)) $issues[] = "缺少关键属性 {$prop}";
                continue;
            }
            $v = $data[$prop];
            if ($type === 'string' && !is_scalar($v)) $issues[] = "属性 {$prop} 应为字符串";
            if ($type === 'number' && !is_numeric($v)) $issues[] = "属性 {$prop} 应为数字";
        }
        return $issues;
    }

    /**
     * 记录数据质量问题
     */
    public static function logQualityIssue(string $event, array $issues): void {
        $file = DATA_DIR . '/data-quality.json';
        $q = json_read($file);
        $q[] = ['event' => $event, 'issues' => $issues, 'at' => date('Y-m-d H:i:s'), 'count' => 1];
        json_write($file, array_slice($q, -500));
    }

    /**
     * 数据质量汇总（供监控面板）
     */
    public static function qualitySummary(): array {
        $q = json_read(DATA_DIR . '/data-quality.json');
        $byEvent = [];
        foreach ($q as $e) {
            $byEvent[$e['event'] ?? ''] = ($byEvent[$e['event'] ?? ''] ?? 0) + ($e['count'] ?? 1);
        }
        arsort($byEvent);
        return ['total' => count($q), 'by_event' => $byEvent, 'recent' => array_slice($q, 0, 20)];
    }
}
