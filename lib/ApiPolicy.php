<?php
/**
 * ApiPolicy —— API 端点的权限矩阵（docs/ROADMAP.md 阶段三）
 *
 * 【为什么需要】后台页面的权限做得不差（169/191 页有 require_perm），
 * 但 **92 个 API 端点里只有 1 个做权限判断**。这一轮已经从里面挖出三个
 * 「公开 + 免登录 + 每次花钱」的 AI 端点，说明这不是理论风险。
 *
 * 【为什么不是改 92 个文件】所有 api/*.php 的第一行都是
 * `require_once admin/config.php`——这是一道现成的统一关卡。
 * 在关卡里按脚本名查表即可，**92 个文件一个都不用动**，
 * 也就不会有"漏改了一个"这种事。这和当初 CSRF 收口到 require_login 是同一个套路。
 *
 * 【默认取常识，且默认不改变现有行为】
 * 只有**明确是后台工具**的端点才标 admin（逐个核过调用方，确认只有 admin/ 在调）。
 * 其余一律 public——也就是跟今天一模一样，升级不会把谁的站点弄坏。
 * 想收紧哪个，去后台「API 权限矩阵」页改，不用改代码。
 *
 * 【四档】
 *   public  访客可访问（前台渲染、埋点、订阅、退订这些本来就该开放）
 *   member  需要前台会员登录
 *   admin   需要后台登录（可再要求某个具体权限）
 *   token   机器对机器，端点自带密钥/签名校验——本层不重复拦，只做登记
 */

if (!function_exists('api_policy_defaults')) {

    /** 四个档位的含义（后台页面展示用）。 */
    function api_policy_tiers(): array {
        return [
            'public' => '公开 · 访客可访问',
            'member' => '会员 · 需前台登录',
            'admin'  => '后台 · 需登录（可要求具体权限）',
            'token'  => '机器 · 端点自带密钥/签名，本层不重复拦',
        ];
    }

    /**
     * 常识默认表。只列**需要非 public** 的端点；没列到的一律 public。
     *
     * admin 那一批的判定标准很死：**逐个 grep 过调用方，确认只有 admin/ 下的页面在调**。
     * 只要有一个前台页面在调，就不进这张表——宁可漏收，不可误伤。
     */
    function api_policy_defaults(): array {
        return [
            // ── 后台内容工具（调用方均为 admin/ 下页面，已逐个核过）──
            'assign-covers'  => ['tier' => 'admin', 'perm' => 'articles',     'note' => '批量改封面：一次性后台工具，无前台调用方'],
            'batch-import'   => ['tier' => 'admin', 'perm' => 'articles',     'note' => '批量导入文章，调用方 admin/api-batch.php'],
            'calendar'       => ['tier' => 'admin', 'perm' => 'articles',     'note' => '内容日历拖拽改发布日期，调用方 admin/content-calendar.php'],
            'import-article' => ['tier' => 'admin', 'perm' => 'articles',     'note' => '从 URL 导入（同时是 SSRF 面），调用方 admin/article-edit.php'],
            'rss-import'     => ['tier' => 'admin', 'perm' => 'articles',     'note' => '从 RSS 拉稿入草稿，调用方 admin/ingest.php'],
            'internal-links' => ['tier' => 'admin', 'perm' => 'articles',     'note' => '内链扫描，调用方 admin/article-edit.php'],
            'templates'      => ['tier' => 'admin', 'perm' => 'site-builder', 'note' => '模板存取删，调用方 admin/article-edit.php'],
            'survey-import'  => ['tier' => 'admin', 'perm' => 'survey',       'note' => '问卷文档解析（接收上传），调用方 admin/survey.php'],

            // ── 后台数据/运营工具 ──
            'cdp-insight'    => ['tier' => 'admin', 'perm' => 'cdp',       'note' => '客户数据洞察：直接对着客户数据做分析，不该公开'],
            'realtime'       => ['tier' => 'admin', 'perm' => 'sentiment', 'note' => 'SERP/舆情实时查询：走第三方付费接口'],
            'stock'          => ['tier' => 'admin', 'perm' => 'media',     'note' => '图库代理：用站长自己的 Pexels/Unsplash key，还能往本地写文件'],
            'notifications'  => ['tier' => 'admin', 'perm' => 'dashboard', 'note' => '后台通知铃铛的已读标记'],
            'site-health'    => ['tier' => 'admin', 'perm' => 'settings',  'note' => '全站 URL 巡检'],
            'ingest'         => ['tier' => 'admin', 'perm' => 'articles',  'note' => '飞书/Notion/Obsidian 内容导入'],
            'segment-manage' => ['tier' => 'admin', 'perm' => 'segments',  'note' => '分群管理'],
            'report-manage'  => ['tier' => 'admin', 'perm' => 'moderation','note' => '举报处理'],
            'featured-manage'=> ['tier' => 'admin', 'perm' => 'featured',  'note' => '精选位管理'],

            // ── 花钱的 AI 端点（本轮已逐个封过，这里登记进矩阵）──
            'ai-business'    => ['tier' => 'admin', 'perm' => 'ai-config', 'note' => 'AI 业务助手：每次调用都花钱'],
            'ai-generate'    => ['tier' => 'admin', 'perm' => 'ai-config', 'note' => 'AI 通用生成：每次调用都花钱'],
            'survey-ai'      => ['tier' => 'admin', 'perm' => 'survey',    'note' => 'AI 生成问卷：每次调用都花钱'],
            'assistant'      => ['tier' => 'admin', 'perm' => '',          'note' => '后台助手：花钱 + 注入公司知识库 + 能创建自动化流程'],

            // ── 机器对机器：端点自带密钥/签名，本层只登记不重复拦 ──
            'cron'           => ['tier' => 'token', 'perm' => '', 'note' => '定时发布，自带签名校验'],
            'data-export'    => ['tier' => 'token', 'perm' => '', 'note' => '数仓出向导出，自带 token'],
            'leads'          => ['tier' => 'token', 'perm' => '', 'note' => '线索拉取，自带 api_key'],
            'push-article'   => ['tier' => 'token', 'perm' => '', 'note' => '推送到分发渠道，自带 key'],
            'webhook'        => ['tier' => 'token', 'perm' => '', 'note' => '入站 webhook，自带校验'],
            'wechat'         => ['tier' => 'token', 'perm' => '', 'note' => '公众号服务器验证（微信要求可匿名访问）'],
            'wecom'          => ['tier' => 'token', 'perm' => '', 'note' => '企业微信服务器验证（同上）'],
        ];
    }

    /** 生效策略 = 默认表 + 后台的自定义覆盖。 */
    function api_policy_all(): array {
        $base = api_policy_defaults();
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $over = is_array($s['api_policy'] ?? null) ? $s['api_policy'] : [];
        foreach ($over as $slug => $cfg) {
            if (!is_array($cfg)) continue;
            $slug = (string)$slug;
            $tier = (string)($cfg['tier'] ?? 'public');
            if (!isset(api_policy_tiers()[$tier])) $tier = 'public';
            if ($tier === 'public' && !isset($base[$slug])) { unset($base[$slug]); continue; }
            $base[$slug] = ['tier' => $tier, 'perm' => (string)($cfg['perm'] ?? ''),
                            'note' => (string)($cfg['note'] ?? ($base[$slug]['note'] ?? '自定义'))];
        }
        ksort($base);
        return $base;
    }

    /** 某个端点的策略；没列到的就是 public。 */
    function api_policy_for(string $slug): array {
        $all = api_policy_all();
        return $all[$slug] ?? ['tier' => 'public', 'perm' => '', 'note' => ''];
    }

    function api_policy_save(string $slug, string $tier, string $perm = ''): void {
        $slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
        if ($slug === '') return;
        if (!isset(api_policy_tiers()[$tier])) return;
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        if (!is_array($s['api_policy'] ?? null)) $s['api_policy'] = [];
        $s['api_policy'][$slug] = ['tier' => $tier, 'perm' => preg_replace('/[^a-z0-9\-_]/', '', strtolower($perm))];
        if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
    }

    /**
     * 执行模式：
     *   enforce  真拦（默认）
     *   observe  只记录会拦谁、不真拦——**上线前先跑两天用这个**，
     *            确认没有误伤自己的前台，再切 enforce
     *   off      整个机制关闭
     */
    function api_policy_mode(): string {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $m = (string)($s['api_policy_mode'] ?? 'enforce');
        return in_array($m, ['enforce', 'observe', 'off'], true) ? $m : 'enforce';
    }

    function api_policy_set_mode(string $mode): void {
        if (!in_array($mode, ['enforce', 'observe', 'off'], true)) return;
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $s['api_policy_mode'] = $mode;
        if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
    }

    /** 观察模式的记录（也用于 enforce 下的拒绝留痕），保留最近 300 条。 */
    function api_policy_log_file(): string { return DATA_DIR . '/api-policy-log.json'; }

    function api_policy_log(string $slug, string $tier, string $reason, bool $blocked): void {
        try {
            $log = function_exists('json_read') ? json_read(api_policy_log_file()) : [];
            $log[] = [
                'at' => date('Y-m-d H:i:s'), 'endpoint' => $slug, 'tier' => $tier,
                'reason' => $reason, 'blocked' => $blocked,
                'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'ref' => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 120),
            ];
            if (function_exists('json_write')) json_write(api_policy_log_file(), array_slice($log, -300));
        } catch (\Throwable $e) {}
    }

    /**
     * 判定一次访问是否放行。纯函数，便于测试。
     * @param array $ctx ['admin'=>bool 后台已登录, 'perm'=>bool 具体权限已满足, 'member'=>bool 会员已登录]
     */
    function api_policy_check(array $policy, array $ctx): array {
        $tier = (string)($policy['tier'] ?? 'public');
        if ($tier === 'public' || $tier === 'token') return ['allowed' => true, 'reason' => ''];
        if ($tier === 'member') {
            return !empty($ctx['member'])
                ? ['allowed' => true, 'reason' => '']
                : ['allowed' => false, 'reason' => 'need_member'];
        }
        // admin
        if (empty($ctx['admin'])) return ['allowed' => false, 'reason' => 'need_login'];
        if (($policy['perm'] ?? '') !== '' && empty($ctx['perm'])) {
            return ['allowed' => false, 'reason' => 'need_perm'];
        }
        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * 在统一入口执行。由 admin/config.php 末尾调用——**不要在各端点里单独调**，
     * 那样又会退回"逐个文件打补丁、总会漏一个"的老路。
     */
    function api_policy_guard(string $scriptPath): void {
        if (PHP_SAPI === 'cli') return;                       // 测试/命令行不拦
        $mode = api_policy_mode();
        if ($mode === 'off') return;

        $slug = basename($scriptPath, '.php');
        $policy = api_policy_for($slug);
        $tier = (string)($policy['tier'] ?? 'public');
        if ($tier === 'public' || $tier === 'token') return;

        $isAdmin = !empty($_SESSION['admin_login']) || !empty($_SESSION['admin_user']);
        $hasPerm = ($policy['perm'] ?? '') === ''
            || (function_exists('has_perm') && has_perm((string)$policy['perm']));
        $isMember = false;
        if (function_exists('member_current')) {
            try { $isMember = (bool)member_current(); } catch (\Throwable $e) {}
        }

        $r = api_policy_check($policy, ['admin' => $isAdmin, 'perm' => $hasPerm, 'member' => $isMember]);
        if (!empty($r['allowed'])) return;

        api_policy_log($slug, $tier, $r['reason'], $mode === 'enforce');
        if ($mode === 'observe') return;                       // 只记录，不拦

        $msg = [
            'need_login'  => '需要登录后台',
            'need_perm'   => '当前角色没有此操作的权限',
            'need_member' => '需要登录',
        ][$r['reason']] ?? '无权访问';
        http_response_code($r['reason'] === 'need_perm' ? 403 : 401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $msg, 'code' => $r['reason']], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
