<?php
/**
 * MCP 治理层 —— 让 Agent 的每一次调用都可授权、可追责（P0-01，2026-09-03）
 *
 * 背景：mcp-server.php 从上线起就对外开放了 18 个工具，其中 4 个是写操作
 * （建文章 / 发布 / 执行技能 / 舆情扫描），而鉴权只有一把写死在
 * data/api_key.json 里的共享 Key，**没有逐工具权限校验，也没有任何审计留痕**。
 * 对照 WordPress 的 Abilities API：它能力比我们少，但每次调用都做完整权限校验、
 * 用显式开关决定暴露什么。能力领先而治理落后，是最危险的组合。
 *
 * 这一层补三件事：
 *   1. 每个工具声明 scope（read / write）与所需权限，tools/list 只列调用方有权用的
 *   2. HTTP 传输走已有的 ApiKeyAuth（支持按 Key 分权限、到期、启停、IP 白名单），
 *      旧的单一 Key 继续可用但被标记为 legacy，避免升级即断线
 *   3. 每次调用落审计日志；写操作额外落决策轨道，可回溯「Agent 为什么动了这一下」
 *
 * 参数在入库前会脱敏与截断——审计日志不该变成密钥和长正文的仓库。
 */

require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/ApiKeyAuth.php';

/**
 * 工具注册表：name => [scope, perm, label]
 *   scope：read 只读 / write 会改数据
 *   perm ：对应后台权限键（人的权限体系），空串表示只要通过鉴权即可
 * 不在表里的工具一律按 write + 需要 admin 处理——新增工具忘了登记时是收紧而不是放开。
 */
function mcp_tool_meta(): array {
    return [
        // ── 只读 ──
        'articles_list'            => ['read',  'articles',  '列出文章'],
        'article_get'              => ['read',  'articles',  '读取文章'],
        'members_list'             => ['read',  'users',     '列出会员'],
        'leads_count'              => ['read',  'crm',       '线索统计'],
        'orders_revenue'           => ['read',  'commerce',  '营收统计'],
        'search'                   => ['read',  '',          '站内搜索'],
        'sentiment_topics'         => ['read',  'sentiment', '舆情话题'],
        'skills_list'              => ['read',  'marketplace','技能列表'],
        'contributions_list'       => ['read',  'marketplace','贡献列表'],
        'contributions_recommend'  => ['read',  'marketplace','贡献推荐'],
        'growth_next_best_action'  => ['read',  'flow',      '下一步建议'],
        'growth_goal_status'       => ['read',  'flow',      '目标进度'],
        'growth_conversion_truth'  => ['read',  'analytics', '转化真相'],
        'growth_ask_data'          => ['read',  'insights',  '问数据'],
        // ── 写操作：会改站点数据，必须有 write 权限，且逐次留痕 ──
        'article_create'           => ['write', 'articles',  '创建文章'],
        'article_publish'          => ['write', 'articles',  '发布文章'],
        'skill_execute'            => ['write', 'marketplace','执行技能'],
        'sentiment_scan'           => ['write', 'sentiment', '发起舆情扫描'],
    ];
}

function mcp_tool_scope(string $tool): string {
    return mcp_tool_meta()[$tool][0] ?? 'write';   // 未登记 → 按写操作从严
}

function mcp_tool_perm(string $tool): string {
    $m = mcp_tool_meta();
    return isset($m[$tool]) ? $m[$tool][1] : 'settings';
}

function mcp_tool_label(string $tool): string {
    return mcp_tool_meta()[$tool][2] ?? $tool;
}

/**
 * 建立调用方身份。
 *   stdio  → 本机子进程，由运营者自己启动，视同管理员（但仍然审计）
 *   http   → 先试 ApiKeyAuth（可分权限 / 可到期 / 可停用），再回落到旧的单一 Key
 * @return array{ok:bool,ctx:array,error:string}
 */
function mcp_identify(string $transport): array {
    if ($transport === 'stdio') {
        return ['ok' => true, 'error' => '', 'ctx' => [
            'transport' => 'stdio', 'key_id' => '', 'name' => '本机 stdio',
            'permissions' => ['admin'], 'legacy' => false,
        ]];
    }

    // 1) 正式 Key 体系：支持按 Key 授权、到期、停用、IP 白名单
    try {
        $k = ApiKeyAuth::authenticate();
        if ($k) {
            return ['ok' => true, 'error' => '', 'ctx' => [
                'transport' => 'http', 'key_id' => (string)($k['id'] ?? ''),
                'name' => (string)($k['name'] ?? 'API Key'),
                'permissions' => (array)($k['permissions'] ?? ['read']),
                'legacy' => false,
            ]];
        }
    } catch (Throwable $e) { /* 落到 legacy 判断 */ }

    // 2) 旧的单一 Key：保持兼容，不让升级把在用的客户端打断，但标记 legacy 并照样审计
    try {
        $keys = json_read(DATA_DIR . '/api_key.json');
        $expected = (string)($keys['key'] ?? '');
        $presented = ApiKeyAuth::extractKey() ?? '';
        if ($expected !== '' && $presented !== '' && hash_equals($expected, $presented)) {
            return ['ok' => true, 'error' => '', 'ctx' => [
                'transport' => 'http', 'key_id' => 'legacy', 'name' => '旧版共享 Key',
                'permissions' => ['admin'], 'legacy' => true,
            ]];
        }
    } catch (Throwable $e) { /* 视为未通过 */ }

    return ['ok' => false, 'ctx' => [], 'error' => '缺少或无效的 API Key（Authorization: Bearer <key>）'];
}

/** 调用方能否使用某个工具 */
function mcp_can(array $ctx, string $tool): bool {
    $perms = (array)($ctx['permissions'] ?? []);
    if (in_array('admin', $perms, true)) return true;
    // 写操作要求 write 权限；只读要求 read 或 write
    $scope = mcp_tool_scope($tool);
    if ($scope === 'write') return in_array('write', $perms, true);
    return in_array('read', $perms, true) || in_array('write', $perms, true);
}

/** 过滤工具清单：调用方看不到自己无权调用的工具（等价于 WP 的 show_in_abilities + 权限校验） */
function mcp_filter_tools(array $tools, array $ctx): array {
    return array_values(array_filter($tools, fn($t) => mcp_can($ctx, (string)($t['name'] ?? ''))));
}

/** 参数脱敏 + 截断：审计日志不该变成密钥仓库或长正文仓库 */
function mcp_sanitize_args(array $args): array {
    $out = [];
    foreach ($args as $k => $v) {
        if (preg_match('/(pass|secret|token|key|credential)/i', (string)$k)) { $out[$k] = '***'; continue; }
        if (is_string($v)) {
            $out[$k] = mb_strlen($v) > 200 ? mb_substr($v, 0, 200) . '…(' . mb_strlen($v) . ' 字)' : $v;
        } elseif (is_scalar($v) || $v === null) {
            $out[$k] = $v;
        } else {
            $out[$k] = '[' . gettype($v) . ']';
        }
    }
    return $out;
}

/**
 * 记录一次调用。写操作额外落决策轨道——「Agent 为什么动了这一下」要能查。
 */
function mcp_audit(string $tool, array $args, array $ctx, bool $ok, string $note, float $ms): void {
    $scope = mcp_tool_scope($tool);
    $detail = [
        'tool'      => $tool,
        'scope'     => $scope,
        'args'      => mcp_sanitize_args($args),
        'transport' => $ctx['transport'] ?? '',
        'key_id'    => $ctx['key_id'] ?? '',
        'caller'    => $ctx['name'] ?? '',
        'legacy'    => !empty($ctx['legacy']),
        'ok'        => $ok,
        'note'      => mb_substr($note, 0, 300),
        'ms'        => round($ms),
    ];
    try {
        AuditLog::log('MCP ' . ($ok ? '' : '拒绝 ') . mcp_tool_label($tool), 'mcp', $detail);
    } catch (Throwable $e) {}

    if ($scope === 'write' && $ok) {
        try {
            require_once __DIR__ . '/Database.php';
            require_once __DIR__ . '/DecisionTrace.php';
            dtrace_record([
                'subject'   => 'MCP · ' . mcp_tool_label($tool),
                'decision'  => mcp_tool_label($tool) . '（' . ($ctx['name'] ?? 'Agent') . '）',
                'module'    => 'mcp',
                'trigger'   => 'AI 客户端通过 MCP 调用 ' . $tool,
                'evidence'  => ['参数：' . json_encode(mcp_sanitize_args($args), JSON_UNESCAPED_UNICODE)],
                'candidates'=> [$tool],
                'guard'     => '已通过 ' . $scope . ' 权限校验（调用方：' . ($ctx['name'] ?? '') . '）',
            ]);
        } catch (Throwable $e) {}
    }
}
