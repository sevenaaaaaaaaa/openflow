<?php
declare(strict_types=1);
/**
 * 适配合成（Adapter Forge）
 *
 * 输入：AdapterIntake 产出的**能力画像**（capability profile）
 * 输出：插件草稿 {plugin.json, plugin.php, tests/plugin_<id>_contract_test.php}
 *
 * 设计要点：
 *  - **AI 可选、模板兜底**：$ai 传入 callable(system,user,opts):array 时才调模型；
 *    否则用确定性模板生成「可校验的骨架」，保证离线/额度用尽也能出草稿。
 *  - 本文件**不写盘**：产出在内存里返回，落盘由 adapter_forge_write() 负责（便于单测）。
 *  - 生成的草稿一律 `verification.status=pending`——徽章只能由 AdapterVerify 发放。
 *
 * 规范：docs/ADAPTER-SPEC.md
 */

require_once __DIR__ . '/AdapterIntake.php';

/**
 * 生成计划：按能力类与落点决定要生成哪些片段
 * @param array<string,mixed> $profile
 * @return array{id:string,class:string,sections:list<string>,notes:list<string>}
 */
function adapter_forge_plan(array $profile): array
{
    $manifest = adapter_manifest_skeleton($profile);
    $id = (string) ($manifest['id'] ?? 'adapter');
    $sections = [];
    $notes = [];

    $surfaces = (array) ($profile['surface_hits'] ?? []);
    if (isset($surfaces['publish'])) $sections[] = 'publish';
    if (isset($surfaces['hook'])) $sections[] = 'hook';
    if (isset($surfaces['schedule']) || isset($surfaces['ingest'])) $sections[] = 'sync';
    if (isset($surfaces['api_route'])) $sections[] = 'client';
    if (isset($surfaces['mcp_tool'])) $sections[] = 'mcp';
    if ($sections === []) {
        $sections[] = 'client';
        $notes[] = '未识别到明确落点，默认按出站调用生成骨架';
    }
    $gate = (array) ($profile['license_gate'] ?? []);
    if (($gate['level'] ?? '') !== 'permissive') {
        $notes[] = '许可证未通过白名单（' . (string) ($gate['note'] ?? '') . '）→ 可生成草稿，但不得上架';
    }
    return ['id' => $id, 'class' => (string) ($profile['class'] ?? ''), 'sections' => $sections, 'notes' => $notes];
}

/** 生成给 AI 的系统提示（人可读、可审） */
function adapter_forge_system_prompt(): string
{
    return "你是 OpenFlow 适配工程师，只输出 PHP 代码。"
        . "基座扩展点：PluginSystem::add_action/add_filter（钩子）、register_api_route(出站/入站 API)、"
        . "register_schedule（定时）、register_block（前台区块）、register_mcp_tool（Agent 工具）。"
        . "硬约束：只用官方 API；凭据只从 plugin_config() 读取；不得硬编码密钥；"
        . "每个网络调用必须有超时与失败分支；不得声明未在 manifest 里的权限。";
}

/**
 * 生成插件草稿（内存）
 *
 * @param array<string,mixed> $profile AdapterIntake::adapter_intake_profile 的产物
 * @param callable|null $ai 可选：fn(string $system, string $user, array $opts): array{ok:bool,text?:string,error?:string}
 * @return array{manifest:array<string,mixed>,files:array<string,string>,plan:array<string,mixed>,generated_by:string,error:string}
 */
function adapter_forge_generate(array $profile, ?callable $ai = null): array
{
    $plan = adapter_forge_plan($profile);
    $manifest = adapter_manifest_skeleton($profile);
    $id = (string) $plan['id'];

    $pluginPhp = '';
    $generatedBy = 'template';
    if ($ai !== null) {
        $prompt = adapter_ai_prompt($profile) . "\n\n生成计划：" . implode('、', (array) $plan['sections']);
        $res = $ai(adapter_forge_system_prompt(), $prompt, ['feature' => 'adapter_forge', 'json' => false]);
        if (is_array($res) && ($res['ok'] ?? false) && is_string($res['text'] ?? null) && trim((string) $res['text']) !== '') {
            $pluginPhp = adapter_forge_extract_code((string) $res['text']);
            $generatedBy = 'ai';
        }
    }
    if (trim($pluginPhp) === '') {
        $pluginPhp = adapter_forge_template_plugin($manifest, (array) ($profile['surface_hits'] ?? []));
    }

    return [
        'manifest' => $manifest,
        'files' => [
            'plugin.json' => adapter_forge_json_pretty($manifest),
            'plugin.php' => $pluginPhp,
            'tests/plugin_' . str_replace('-', '_', $id) . '_contract_test.php' => adapter_forge_template_contract_test($manifest, (array) $profile),
        ],
        'plan' => $plan,
        'generated_by' => $generatedBy,
        'error' => '',
    ];
}

/** 从 AI 输出里抠出 PHP 代码块（容忍 ```php 包裹与说明文字） */
function adapter_forge_extract_code(string $text): string
{
    if (preg_match('/```(?:php)?\s*\n(.*?)```/s', $text, $m) === 1) {
        $text = (string) $m[1];
    }
    $pos = strpos($text, '<?php');
    if ($pos === false) return '';
    return substr($text, (int) $pos);
}

/** 确定性模板：可校验的最小骨架（离线兜底，人审后再补真实调用） */
function adapter_forge_template_plugin(array $manifest, array $surfaceHits): string
{
    $id = (string) ($manifest['id'] ?? 'adapter');
    $name = (string) ($manifest['name'] ?? '适配器');
    $repo = (string) ($manifest['source']['repo'] ?? '');
    $code = "<?php\ndeclare(strict_types=1);\n"
        . "/**\n * {$name}（适配草稿）\n"
        . " * 上游：{$repo}\n"
        . " * 由 AdapterForge 生成（模板兜底）：\n"
        . " *  - 只声明 manifest 里的权限对应的能力；真实接口调用需人工/AI 补齐 TODO\n"
        . " *  - 凭据一律从 plugin_config() 读，绝不硬编码\n"
        . " */\n\n";

    $code .= "function " . str_replace('-', '_', $id) . "_config(): array {\n"
        . "    if (function_exists('plugin_config')) return (array) plugin_config('{$id}');\n"
        . "    return [];\n}\n\n";

    if (isset($surfaceHits['publish'])) {
        $code .= "/** 出站：把消息推到上游（TODO：替换为上游官方接口） */\n"
            . "PluginSystem::register_api_route('{$id}', 'POST', 'send', function (array \$req): array {\n"
            . "    \$cfg = " . str_replace('-', '_', $id) . "_config();\n"
            . "    \$text = (string) (\$req['body']['text'] ?? '');\n"
            . "    if (\$text === '') return ['ok' => false, 'error' => 'text 不能为空'];\n"
            . "    if (empty(\$cfg['endpoint'])) return ['ok' => false, 'error' => '未配置 endpoint'];\n"
            . "    // TODO(适配): 调用上游官方 API；必须设超时并处理失败\n"
            . "    return ['ok' => true, 'queued' => true];\n"
            . "}, ['auth' => 'token']);\n\n";
    }
    if (isset($surfaceHits['hook'])) {
        $code .= "/** 入站：把站内事件转发到上游（TODO：加签名校验与幂等键） */\n"
            . "PluginSystem::register_api_route('{$id}', 'POST', 'webhook', function (array \$req): array {\n"
            . "    \$event = (string) (\$req['body']['event'] ?? '');\n"
            . "    if (\$event === '') return ['ok' => false, 'error' => 'event 不能为空'];\n"
            . "    return ['ok' => true, 'received' => \$event];\n"
            . "}, ['auth' => 'hmac']);\n\n";
    }
    if (isset($surfaceHits['schedule']) || isset($surfaceHits['ingest'])) {
        $code .= "/** 定时同步（TODO：增量游标 + 限速退避） */\n"
            . "PluginSystem::register_schedule('{$id}_sync', 'hourly', function (): void {\n"
            . "    \$cfg = " . str_replace('-', '_', $id) . "_config();\n"
            . "    if (empty(\$cfg['endpoint'])) return;\n"
            . "    // TODO(适配): 拉取上游数据并写入基座\n"
            . "});\n\n";
    }
    if (!isset($surfaceHits['publish']) && !isset($surfaceHits['hook']) && !isset($surfaceHits['schedule'])
        && !isset($surfaceHits['ingest']) && !isset($surfaceHits['mcp_tool'])) {
        $code .= "/** 出站调用骨架（TODO：替换为上游官方接口） */\n"
            . "PluginSystem::register_api_route('{$id}', 'GET', 'status', function (array \$req): array {\n"
            . "    return ['ok' => true, 'configured' => (bool) " . str_replace('-', '_', $id) . "_config()];\n"
            . "}, ['auth' => 'token']);\n\n";
    }
    if (isset($surfaceHits['mcp_tool'])) {
        $code .= "/** 暴露为 MCP 工具，供 Agent 调用 */\n"
            . "PluginSystem::register_mcp_tool('{$id}', '{$id}_query', '查询上游数据（适配草稿）',\n"
            . "    ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]],\n"
            . "    function (array \$args): array {\n"
            . "        return ['ok' => true, 'q' => (string) (\$args['q'] ?? '')];\n"
            . "    });\n";
    }
    return $code;
}

/** 确定性模板：契约测试（验证闸门会执行它） */
function adapter_forge_template_contract_test(array $manifest, array $profile): string
{
    $id = (string) ($manifest['id'] ?? 'adapter');
    $surfaces = array_keys((array) ($profile['surface_hits'] ?? []));
    $surfacesPhp = "['" . implode("', '", array_map('strval', $surfaces)) . "']";
    $json = var_export($manifest, true);
    return "<?php\ndeclare(strict_types=1);\n"
        . "/**\n * {$id} 插件契约测试（AdapterForge 生成）\n *   php tests/plugin_"
        . str_replace('-', '_', $id) . "_contract_test.php\n */\n\n"
        . "/** @var array<string,mixed> \$manifest */\n"
        . "\$manifest = " . $json . ";\n"
        . "/** @var list<string> \$surfaces */\n"
        . "\$surfaces = array_keys((array) \$manifest['surfaces']);\n\n"
        . "function check(string \$n, bool \$ok): bool { if (!\$ok) echo \"  ✗ {\$n}\\n\"; return \$ok; }\n"
        . "\$results = [];\n\n"
        . "// 1. 清单字段齐全\n"
        . "foreach (['id','name','version','description','author','permissions','source','surfaces','verification'] as \$k) {\n"
        . "    \$results[] = check(\"manifest 含 {\$k}\", array_key_exists(\$k, \$manifest));\n}\n\n"
        . "// 2. 权限 ⊇ 落点所需（规范 docs/ADAPTER-SPEC.md §二）\n"
        . "\$surfacePerms = ['hook' => ['hooks'], 'api_route' => ['api','http'], 'schedule' => ['schedule'],\n"
        . "    'block' => ['block'], 'ingest' => ['data.write','http'], 'publish' => ['notify','http'], 'mcp_tool' => ['mcp']];\n"
        . "\$need = ['config','log'];\n"
        . "foreach (\$surfaces as \$s) { foreach ((array) (\$surfacePerms[\$s] ?? []) as \$p) \$need[] = \$p; }\n"
        . "\$missing = array_values(array_diff(array_unique(\$need), (array) \$manifest['permissions']));\n"
        . "\$results[] = check('权限覆盖所有落点', \$missing === []);\n\n"
        . "// 3. 不越权：不得声明白名单外的权限\n"
        . "\$known = ['hooks','config','log','data.read','data.write','http','cdp','email','notify','schedule','api','menu','page','slot','asset','block','cli','mcp'];\n"
        . "\$results[] = check('无未知权限', array_values(array_diff((array) \$manifest['permissions'], \$known)) === []);\n\n"
        . "// 4. 来源可追溯 + 默认不启用\n"
        . "\$results[] = check('source.repo 已 pin', (string) (((array) \$manifest['source'])['repo'] ?? '') !== '');\n"
        . "\$results[] = check('默认不启用', (bool) (((array) \$manifest)['enabled_by_default'] ?? true) === false);\n\n"
        . "// 5. 徽章不得自证\n"
        . "\$results[] = check('verification 未自称通过', (string) (((array) \$manifest['verification'])['badge'] ?? '') !== 'verified');\n\n"
        . "\$failed = count(array_filter(\$results, static fn (bool \$r): bool => !\$r));\n"
        . "echo \"\\n合计：\" . (count(\$results) - \$failed) . \" 通过，{\$failed} 失败\\n\";\n"
        . "exit(\$failed === 0 ? 0 : 1);\n";
}

/** JSON 美化（保证中文可读、末尾换行） */
function adapter_forge_json_pretty(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return ($json === false ? '{}' : $json) . "\n";
}

/**
 * 落盘（写文件 + 目录）
 * @param array<string,string> $files 相对路径 => 内容
 * @return array{ok:bool,dir:string,written:list<string>,error:string}
 */
function adapter_forge_write(array $files, string $dir): array
{
    $base = rtrim($dir, '/');
    $written = [];
    if (!is_dir($base) && !@mkdir($base, 0775, true) && !is_dir($base)) {
        return ['ok' => false, 'dir' => $base, 'written' => [], 'error' => '无法创建目录'];
    }
    foreach ($files as $rel => $content) {
        $path = $base . '/' . ltrim((string) $rel, '/');
        $parent = dirname($path);
        if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
            return ['ok' => false, 'dir' => $base, 'written' => $written, 'error' => "无法创建目录 {$parent}"];
        }
        if (@file_put_contents($path, $content) === false) {
            return ['ok' => false, 'dir' => $base, 'written' => $written, 'error' => "写入失败 {$rel}"];
        }
        $written[] = $rel;
    }
    return ['ok' => true, 'dir' => $base, 'written' => $written, 'error' => ''];
}

/* ────────────────────────── 第二轮：AI 补全真实调用 ────────────────────────── */

/** 生成物安全栅栏：拒绝新增未声明落点、拒绝硬编码密钥、拒绝删掉已有注册 */
function adapter_forge_guard(string $before, string $after, array $manifest): array
{
    $errors = [];
    if (!str_contains($after, '<?php') || !str_contains($after, 'declare(strict_types=1)')) {
        $errors[] = '缺少 <?php 或 declare(strict_types=1)';
    }
    // 硬编码密钥（常见形态）
    if (preg_match('/\b(sk-[A-Za-z0-9]{16,}|AKIA[0-9A-Z]{16}|gh[pousr]_[A-Za-z0-9]{20,})\b/', $after) === 1
        || preg_match('#https?://[^"\'\s]*:[^"\'\s]*@#', $after) === 1) {
        $errors[] = '疑似硬编码密钥/带凭据的 URL';
    }
    // 落点守卫：新出现的 register_* 必须都在 manifest.surfaces 里
    $map = ['register_api_route' => 'api_route', 'register_schedule' => 'schedule',
            'register_block' => 'block', 'register_mcp_tool' => 'mcp_tool', 'add_action' => 'hook', 'add_filter' => 'hook'];
    $declared = array_keys((array) ($manifest['surfaces'] ?? []));
    foreach ($map as $fn => $surface) {
        $nAfter = substr_count($after, $fn);
        $nBefore = substr_count($before, $fn);
        if ($nAfter > $nBefore && !in_array($surface, $declared, true)) {
            $errors[] = "新增了未声明落点的调用：{$fn}（{$surface}）";
        }
        if ($nAfter < $nBefore) {
            $errors[] = "删除了既有注册：{$fn}";
        }
    }
    return ['ok' => $errors === [], 'errors' => $errors];
}

/**
 * 第二轮：让 AI 依据上游信息把 TODO(适配) 换成真实调用
 *
 * 安全设计：先备份模板版本 → 写入新代码 → 跑闸门；**闸门不过就回滚**，绝不让坏代码留在草稿里。
 *
 * @param array<string,mixed> $profile
 * @param callable $ai fn(string $system, string $user, array $opts): array{ok:bool,text?:string,error?:string}
 * @return array{ok:bool,applied:bool,note:string,bytes:int,report:array<string,mixed>|null}
 */
function adapter_forge_complete(array $profile, string $draftDir, callable $ai): array
{
    $dir = rtrim($draftDir, '/');
    $pluginPath = $dir . '/plugin.php';
    if (!is_file($pluginPath)) {
        return ['ok' => false, 'applied' => false, 'note' => '草稿缺少 plugin.php', 'bytes' => 0, 'report' => null];
    }
    $manifest = [];
    if (is_file($dir . '/plugin.json')) {
        $decoded = json_decode((string) file_get_contents($dir . '/plugin.json'), true);
        if (is_array($decoded)) $manifest = $decoded;
    }
    $before = (string) file_get_contents($pluginPath);

    $user = "上游能力画像：\n" . json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        . "\n\n当前骨架（含 TODO(适配)）：\n```php\n" . mb_substr($before, 0, 6000) . "\n```\n\n"
        . "任务：用该上游的**官方 API** 补全所有 TODO(适配)，产出可直接运行的完整 PHP 文件。\n"
        . "硬约束：\n"
        . "1) 不得新增落点（注册函数只能出现在骨架已有的地方）\n"
        . "2) 凭据只从 plugin_config() 读；不得出现任何明文密钥\n"
        . "3) 每个网络请求必须有超时、非 2xx 分支与重试/退避说明\n"
        . "4) 保留 declare(strict_types=1) 与既有函数名；只输出完整 PHP 文件\n";

    $res = $ai(adapter_forge_system_prompt(), $user, ['feature' => 'adapter_forge_complete', 'max_tokens' => 3000]);
    if (!is_array($res) || !($res['ok'] ?? false)) {
        return ['ok' => false, 'applied' => false, 'note' => 'AI 调用失败：' . (string) ($res['error'] ?? ''), 'bytes' => 0, 'report' => null];
    }
    $after = adapter_forge_extract_code((string) ($res['text'] ?? ''));
    if (trim($after) === '') {
        return ['ok' => false, 'applied' => false, 'note' => 'AI 未返回可解析的 PHP', 'bytes' => 0, 'report' => null];
    }

    $guard = adapter_forge_guard($before, $after, $manifest);
    if (!($guard['ok'] ?? false)) {
        return ['ok' => false, 'applied' => false, 'note' => '安全栅栏拦截：' . implode('；', (array) $guard['errors']), 'bytes' => strlen($after), 'report' => null];
    }

    // 备份：① 模板版本留档（只备一次，供对比）② 本次调用前的快照（回滚用，必须每次覆盖）
    $templateBak = $dir . '/plugin.template.php.bak';
    if (!is_file($templateBak)) @copy($pluginPath, $templateBak);
    $prevBak = $dir . '/plugin.php.prev.bak';
    @copy($pluginPath, $prevBak);
    @file_put_contents($dir . '/plugin.ai.php', $after);
    @file_put_contents($pluginPath, $after);

    // 重新验证前必须撤章：徽章只能由闸门发放，旧章会让 no-self-claim 误判
    $unstamped = $manifest;
    $unstamped['verification'] = ['status' => 'pending', 'badge' => 'unverified', 'tests' => '', 'checked_at' => ''];
    @file_put_contents($dir . '/plugin.json', adapter_forge_json_pretty($unstamped));

    require_once __DIR__ . '/AdapterVerify.php';
    $report = adapter_verify_gate($dir, $unstamped);
    if (($report['status'] ?? 'blocked') === 'blocked') {
        if (is_file($prevBak)) @copy($prevBak, $pluginPath);   // 回滚到本次调用前（AI 产物已在 plugin.ai.php 留档）
        @file_put_contents($dir . '/plugin.json', adapter_forge_json_pretty($manifest));
        $ps = '';
        foreach ((array) ($report['checks'] ?? []) as $c) {
            if (!$c['ok'] && $c['id'] === 'phpstan') $ps = ' · ' . (string) $c['note'];
        }
        return ['ok' => false, 'applied' => false,
            'note' => '补全后未过闸门，已回滚：' . implode(',', (array) ($report['failed'] ?? [])) . $ps,
            'bytes' => strlen($after), 'report' => $report];
    }
    adapter_verify_stamp($dir, $report);
    return ['ok' => true, 'applied' => true, 'note' => '已补全并通过闸门', 'bytes' => strlen($after), 'report' => $report];
}
