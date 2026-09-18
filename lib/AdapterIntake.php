<?php
declare(strict_types=1);
/**
 * 适配源接入（Adapter Intake）
 *
 * 职责：把「一个上游来源」变成结构化的**能力画像**（capability profile），供
 *       - AdapterForge（把能力编译成插件代码与契约测试）
 *       - AdapterVerify（上架前验证闸门）
 * 规范见 docs/ADAPTER-SPEC.md；候选池见 docs/ADAPTER-CANDIDATES.md。
 *
 * 本文件只做「解析/判定/组装」，不下载代码、不写文件（网络与落盘由调用方决定），
 * 因此可单测：tests/adapter_intake_test.php
 */

/** 基座落点（与 PluginSystem 的扩展点对齐） */
const ADAPTER_SURFACES = [
    'hook'      => '事件入站（webhook/回调）',
    'api_route' => '出站调用（读它的数据/触发它的动作）',
    'schedule'  => '定时同步',
    'block'     => '前台展示（区块/组件）',
    'ingest'    => '数据入湖（内容/线索/订单）',
    'publish'   => '内容/消息分发',
    'mcp_tool'  => '以 MCP 工具暴露给 Agent',
];

/** 功能能力类（与 scripts/screen-adapters.py 的 CAPABILITY_CLASSES 对齐） */
const ADAPTER_CLASSES = [
    'content_channel' => ['need' => '内容分发渠道', 'surface' => 'publish'],
    'notify_reach'    => ['need' => '触达/通知通道', 'surface' => 'publish'],
    'data_ingest'     => ['need' => '数据入湖', 'surface' => 'ingest'],
    'intel_source'    => ['need' => '情报源', 'surface' => 'ingest'],
    'crm_leads'       => ['need' => '线索/CRM 双向同步', 'surface' => 'api_route'],
    'payment_billing' => ['need' => '收款/订阅通道', 'surface' => 'api_route'],
    'workflow_agent'  => ['need' => '工作流/Agent 能力', 'surface' => 'api_route'],
];

/** 落点 → 需要的插件权限（与 PluginSystem::normalize_permissions 的白名单一致） */
const ADAPTER_SURFACE_PERMISSIONS = [
    'hook'      => ['hooks'],
    'api_route' => ['api', 'http'],
    'schedule'  => ['schedule'],
    'block'     => ['block'],
    'ingest'    => ['data.write', 'http'],
    'publish'   => ['notify', 'http'],
    'mcp_tool'  => ['mcp'],
];

const ADAPTER_LICENSE_PERMISSIVE = ['mit', 'apache-2.0', 'bsd-2-clause', 'bsd-3-clause', 'isc', 'mpl-2.0', 'unlicense', '0bsd'];
const ADAPTER_LICENSE_COPYLEFT  = ['gpl-2.0', 'gpl-3.0', 'agpl-3.0', 'lgpl-2.1', 'lgpl-3.0', 'sspl-1.0', 'bsl-1.1', 'osl-3.0'];

/**
 * 关键词命中判定：ASCII 关键词按词边界匹配（避免 "bi" 命中 "billing" 这类子串误判），
 * 中文等非 ASCII 关键词用子串匹配。
 */
function adapter_kw_hit(string $text, string $kw): bool
{
    if ($kw === '') return false;
    if (preg_match('/^[\x00-\x7F]+$/', $kw) === 1) {
        return preg_match('/(?<![a-z0-9])' . preg_quote(strtolower($kw), '/') . '(?![a-z0-9])/i', $text) === 1;
    }
    return str_contains($text, $kw);
}

/** 关键词 → 落点（与筛选脚本同一套语义） */
const ADAPTER_SURFACE_HINTS = [
    'api_route' => ['api', 'rest', 'graphql', 'sdk', 'openapi', 'swagger'],
    'hook'      => ['webhook', 'callback', 'events', 'subscribe', 'hooks'],
    'schedule'  => ['cron', 'schedule', 'sync', 'polling', '定时'],
    'block'     => ['widget', 'embed', 'component', 'iframe'],
    'ingest'    => ['import', 'export', 'csv', 'etl', 'connector', 'digest'],
    'publish'   => ['publish', 'post', 'send', 'notify', 'bot'],
    'mcp_tool'  => ['mcp', 'model context protocol', 'model-context-protocol'],
];

/* ────────────────────────── 解析 / 判定 ────────────────────────── */

/**
 * 解析来源串 → 结构化来源。
 * 支持：https://github.com/owner/repo(.git) · git@github.com:owner/repo.git · owner/repo · 其它 URL
 *
 * @return array{ok:bool,kind:string,host:string,owner:string,repo:string,slug:string,url:string,error:string}
 */
function adapter_parse_source(string $input): array
{
    $empty = ['ok' => false, 'kind' => '', 'host' => '', 'owner' => '', 'repo' => '', 'slug' => '', 'url' => '', 'error' => ''];
    $raw = trim($input);
    if ($raw === '') return $empty + ['error' => '来源为空'];

    // git@github.com:owner/repo.git
    if (preg_match('#^git@([^:]+):([^/]+)/(.+?)(?:\.git)?$#', $raw, $m)) {
        $host = strtolower($m[1]); $owner = $m[2]; $repo = $m[3];
    } elseif (preg_match('#^https?://([^/]+)/(.+?)/?$#', $raw, $m)) {
        $host = strtolower($m[1]);
        $parts = explode('/', trim($m[2], '/'));
        if (count($parts) < 2) return $empty + ['error' => 'URL 缺少 owner/repo'];
        $owner = $parts[0]; $repo = preg_replace('/\.git$/', '', $parts[1]) ?? $parts[1];
    } elseif (preg_match('#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$#', $raw, $m)) {
        $host = 'github.com'; $owner = $m[1]; $repo = $m[2];
    } else {
        return $empty + ['error' => '无法识别的来源格式'];
    }

    $kind = str_contains($host, 'github.com') ? 'github' : 'generic';
    $slug = strtolower($owner . '/' . $repo);
    return [
        'ok' => true, 'kind' => $kind, 'host' => $host, 'owner' => $owner, 'repo' => $repo,
        'slug' => $slug,
        'url' => $kind === 'github' ? "https://github.com/{$owner}/{$repo}" : "https://{$host}/{$owner}/{$repo}",
        'error' => '',
    ];
}

/**
 * 许可证闸门（按 docs/ADAPTER-SPEC.md §二）
 * @return array{level:string,ok:bool,note:string}
 */
function adapter_license_gate(string $spdx): array
{
    $lic = strtolower(trim($spdx));
    if ($lic === '' || $lic === 'noassertion' || $lic === 'unknown') {
        return ['level' => 'unknown', 'ok' => false, 'note' => '许可证未知 → blocked（需读取仓库 LICENSE 定案）'];
    }
    if (in_array($lic, ADAPTER_LICENSE_PERMISSIVE, true)) {
        return ['level' => 'permissive', 'ok' => true, 'note' => '宽松许可证，可直接适配'];
    }
    if (in_array($lic, ADAPTER_LICENSE_COPYLEFT, true)) {
        return ['level' => 'copyleft', 'ok' => false, 'note' => '传染性许可证 → needs-review（法务确认后方可）'];
    }
    return ['level' => 'other', 'ok' => false, 'note' => "许可证 {$lic} 不在白名单 → needs-review"];
}

/**
 * 文本 → 落点及命中依据
 * @return array<string,list<string>>
 */
function adapter_detect_surfaces(string $text): array
{
    $t = strtolower($text);
    $out = [];
    foreach (ADAPTER_SURFACE_HINTS as $surface => $kws) {
        $hits = [];
        foreach ($kws as $kw) {
            if (adapter_kw_hit($t, $kw)) $hits[] = $kw;
        }
        if ($hits) $out[$surface] = $hits;
    }
    return $out;
}

/** 文本 → 功能能力类（未命中返回 ''） */
function adapter_classify(string $name, string $desc): string
{
    $text = strtolower($name . ' ' . mb_substr($desc, 0, 160));
    $map = [
        'content_channel' => ['blog', 'publisher', 'cms', 'social', 'cross-post', 'newsletter', 'publish', '发帖', '分发'],
        'notify_reach'    => ['notification', 'notify', 'alert', 'push', 'chatops', 'relay', '通知', '提醒'],
        'data_ingest'     => ['analytics', 'event', 'telemetry', 'tracking', 'cdp', 'etl', 'collector', 'bi', '埋点'],
        'intel_source'    => ['rss', 'feed', 'reader', 'trend', 'crawler', 'scraping', 'news', 'monitor', '情报', '舆情'],
        'crm_leads'       => ['crm', 'sales', 'lead', 'contact', 'helpdesk', 'ticketing', '线索', '工单'],
        'payment_billing' => ['payment', 'billing', 'subscription', 'invoice', 'checkout', 'paywall', '收款', '订阅'],
        'workflow_agent'  => ['workflow', 'automation', 'agent', 'orchestration', 'mcp', 'rag', '编排', '自动化'],
    ];
    foreach ($map as $class => $kws) {
        foreach ($kws as $kw) {
            if (adapter_kw_hit($text, $kw)) return $class;
        }
    }
    return '';
}

/** 落点集合 → 需要的权限（去重、排序） */
function adapter_required_permissions(array $surfaces): array
{
    $perms = ['config', 'log'];
    foreach ($surfaces as $s) {
        foreach (ADAPTER_SURFACE_PERMISSIONS[$s] ?? [] as $p) $perms[] = $p;
    }
    $perms = array_values(array_unique($perms));
    sort($perms);
    return $perms;
}

/* ────────────────────────── 组装 ────────────────────────── */

/**
 * 组装能力画像（capability profile）
 *
 * @param array<string,mixed> $meta 来源元数据：repo/description/license/stars/pushed_at/topics/homepage
 */
function adapter_intake_profile(array $meta, string $readme = '', string $openapi = ''): array
{
    $source = adapter_parse_source((string) ($meta['repo'] ?? ($meta['url'] ?? '')));
    $desc = (string) ($meta['description'] ?? '');
    $readmeHead = mb_substr($readme, 0, 4000);
    $openapiHead = mb_substr($openapi, 0, 2000);
    $text = trim($desc . ' ' . implode(' ', (array) ($meta['topics'] ?? [])) . ' ' . $readmeHead . ' ' . $openapiHead);

    $surfaces = adapter_detect_surfaces($text);
    $class = adapter_classify((string) ($source['repo'] ?? ''), $text);
    if ($class === '' && isset(ADAPTER_CLASSES[(string) ($meta['category'] ?? '')])) {
        $class = (string) $meta['category'];
    }
    if ($class === '' || $surfaces === []) {
        $fallback = ADAPTER_CLASSES[$class]['surface'] ?? 'api_route';
        $surfaces[$fallback] = $surfaces[$fallback] ?? ['fallback'];
    }

    $license = adapter_license_gate((string) ($meta['license'] ?? ''));

    // 动态证据等级：README 明确写 API/webhook/MCP 才算 B；OpenAPI 出现算 A
    $grade = 'C';
    if ($openapi !== '') $grade = 'A';
    elseif (preg_match('/(api|webhook|mcp|rest|graphql)/i', $readmeHead) === 1) $grade = 'B';

    return [
        'source' => [
            'kind' => $source['kind'], 'slug' => $source['slug'], 'url' => $source['url'],
            'version' => (string) ($meta['version'] ?? ''), 'license' => strtolower((string) ($meta['license'] ?? '')),
        ],
        'class' => $class,
        'class_need' => ADAPTER_CLASSES[$class]['need'] ?? '',
        'surfaces' => array_map(static fn ($s) => ADAPTER_SURFACES[$s] ?? $s, array_keys($surfaces)),
        'surface_hits' => $surfaces,
        'permissions' => adapter_required_permissions(array_keys($surfaces)),
        'license_gate' => $license,
        'evidence_grade' => $grade,
        'activity' => ['stars' => (int) ($meta['stars'] ?? 0), 'pushed_at' => (string) ($meta['pushed_at'] ?? '')],
        'verification' => adapter_verification_stub($license, $grade),
    ];
}

/** 生成 verification 块（永远 pending：徽章只能由闸门发放） */
function adapter_verification_stub(array $licenseGate, string $grade): array
{
    $status = 'pending';
    if (($licenseGate['level'] ?? '') === 'unknown') $status = 'blocked';
    if ($grade === 'C') $status = 'blocked';
    return ['status' => $status, 'badge' => 'unverified', 'tests' => '', 'checked_at' => ''];
}

/**
 * 生成 plugin.json 草稿（manifest v2，见 docs/ADAPTER-SPEC.md §二）
 * @param array<string,mixed> $profile adapter_intake_profile() 的产物
 */
function adapter_manifest_skeleton(array $profile): array
{
    $src = (array) ($profile['source'] ?? []);
    $slug = (string) ($src['slug'] ?? '');
    $id = preg_replace('#[^a-z0-9-]+#', '-', str_replace('/', '-', $slug)) ?: 'adapter';
    return [
        'id' => $id,
        'name' => (string) ($profile['class_need'] ?? '') !== '' ? (string) $profile['class_need'] . '适配器' : '适配器',
        'version' => '0.1.0',
        'description' => '由适配代理生成的草稿：来源 ' . $slug,
        'author' => 'OpenFlow Adapter Agent',
        'enabled_by_default' => false,
        'permissions' => (array) ($profile['permissions'] ?? ['hooks', 'config', 'log']),
        'source' => [
            'kind' => (string) ($src['kind'] ?? ''), 'repo' => $slug,
            'version' => (string) ($src['version'] ?? ''), 'license' => (string) ($src['license'] ?? ''),
        ],
        'surfaces' => (array) ($profile['surface_hits'] ?? []),   // {api_route: [...], publish: [...]}
        'capabilities' => ['network' => [], 'secrets' => [], 'data' => []],
        'compat' => ['openflow' => '>=2.1 <3'],
        'verification' => (array) ($profile['verification'] ?? []),
    ];
}

/** 给 AI 的适配提示词（AdapterForge 阶段使用） */
function adapter_ai_prompt(array $profile): string
{
    $json = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return "你是 OpenFlow 的适配工程师。根据下面的「能力画像」，为一个上游产品生成插件草稿。\n"
        . "硬约束（违反即失败）：\n"
        . "1) 只用官方 API/SDK，遵守 rate limit；不得逆向私有接口\n"
        . "2) 凭据只读 plugin.json 声明的 secrets，绝不硬编码或写日志\n"
        . "3) 只实现画像里 surfaces 声明的能力，不额外扩权\n"
        . "4) 每个落点都要有错误处理与幂等键；网络失败要退避重试\n"
        . "5) 同时生成 tests/plugin_<id>_contract_test.php（清单合法/路由形状/错误处理/幂等）\n\n"
        . "输出：plugin.php + plugin.json + 契约测试；不要输出解释文字。\n\n能力画像：\n```json\n{$json}\n```\n";
}

/* ────────────────────────── 网络（可选，调用方自行决定是否使用） ────────────────────────── */

/**
 * 拉取 GitHub 仓库元数据（公开 API，无 token 亦可；限额 60/h）
 * @return array<string,mixed> 失败返回 ['ok'=>false,'error'=>...]
 */
function adapter_intake_github(string $slug, int $timeout = 12): array
{
    // 1) 优先用 gh（已登录时限额高、数据全）
    if (function_exists('exec')) {
        $cmd = 'gh api ' . escapeshellarg('repos/' . $slug) . ' 2>/dev/null';
        $out = [];
        $code = 0;
        @exec($cmd, $out, $code);
        if ($code === 0 && $out !== []) {
            $d = json_decode(implode('', $out), true);
            if (is_array($d)) {
                $meta = adapter_intake_normalize($d, $slug);
                $meta['version'] = adapter_intake_latest_version($slug);
                return $meta;
            }
        }
    }
    // 2) 回退公开 API（可带 GITHUB_TOKEN 提升限额）
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'curl 不可用'];
    $token = (string) (getenv('GITHUB_TOKEN') ?: '');
    $headers = ['Accept: application/vnd.github+json'];
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
    $ch = curl_init('https://api.github.com/repos/' . rawurlencode($slug));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'OpenFlow-Adapter-Intake',
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (!is_string($body) || $code !== 200) {
        $hint = $code === 403 ? '（匿名限额 60/h，可设 GITHUB_TOKEN 或安装 gh）' : '';
        return ['ok' => false, 'error' => "GitHub API {$code}{$hint}"];
    }
    $d = json_decode($body, true);
    if (!is_array($d)) return ['ok' => false, 'error' => '响应不是 JSON'];
    return adapter_intake_normalize($d, $slug);
}

/** 取最新 release tag（失败返回空串，不阻塞） */
function adapter_intake_latest_version(string $slug): string
{
    if (!function_exists('exec')) return '';
    $out = [];
    $code = 0;
    @exec('gh api ' . escapeshellarg('repos/' . $slug . '/releases/latest') . ' 2>/dev/null', $out, $code);
    if ($code === 0 && $out !== []) {
        $d = json_decode(implode('', $out), true);
        if (is_array($d) && !empty($d['tag_name'])) return (string) $d['tag_name'];
    }
    return '';
}

/** GitHub 响应 → 统一元数据结构 */
function adapter_intake_normalize(array $d, string $slug): array
{
    return [
        'ok' => true,
        'repo' => (string) ($d['full_name'] ?? $slug),
        'description' => (string) ($d['description'] ?? ''),
        'license' => strtolower((string) (($d['license']['spdx_id'] ?? '') ?: '')),
        'stars' => (int) ($d['stargazers_count'] ?? 0),
        'pushed_at' => substr((string) ($d['pushed_at'] ?? ''), 0, 10),
        'topics' => (array) ($d['topics'] ?? []),
        'homepage' => (string) ($d['homepage'] ?? ''),
        'version' => '',   // 需另查 releases/latest，由 Forge 阶段补
    ];
}
