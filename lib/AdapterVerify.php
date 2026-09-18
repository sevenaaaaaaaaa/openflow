<?php
declare(strict_types=1);
/**
 * 适配验证闸门（Adapter Verify）
 *
 * 规范：docs/ADAPTER-SPEC.md §四（上架前 5 项必过）
 *   1. manifest 合法（字段齐全、permissions ⊇ surfaces、permissions ⊆ 基座白名单）
 *   2. 许可证白名单
 *   3. 契约测试通过（php <draft>/tests/plugin_<id>_contract_test.php）
 *   4. 静态分析（PHPStan，可选：装了才跑，未装则记 skipped）
 *   5. 沙箱能力试跑（草稿若声明 artifact 级能力则校验白名单，否则 n/a）
 *
 * **原则：AI 可以写代码，但不能给自己发徽章。** 徽章只由 adapter_verify_stamp() 依据报告写入。
 */

require_once __DIR__ . '/AdapterIntake.php';

/** 基座权限白名单（与 PluginSystem::normalize_permissions 保持一致） */
const ADAPTER_KNOWN_PERMISSIONS = [
    'hooks', 'config', 'log', 'data.read', 'data.write', 'http', 'cdp', 'email', 'notify',
    'schedule', 'api', 'menu', 'page', 'slot', 'asset', 'block', 'cli', 'mcp',
];

/**
 * 清单校验
 * @param array<string,mixed> $manifest
 * @return array{ok:bool,checks:list<array{id:string,ok:bool,note:string}>}
 */
function adapter_verify_manifest(array $manifest): array
{
    $checks = [];
    $add = static function (string $id, bool $ok, string $note = '') use (&$checks): void {
        $checks[] = ['id' => $id, 'ok' => $ok, 'note' => $note];
    };

    foreach (['id', 'name', 'version', 'description', 'author', 'permissions', 'source', 'surfaces', 'verification'] as $k) {
        $add("field:$k", isset($manifest[$k]), $k . ' 缺失');
    }
    // 说明：note 仅在失败时有意义（CLI 也只打印失败项）
    $id = (string) ($manifest['id'] ?? '');
    $add('id:format', $id !== '' && preg_match('/^[a-z0-9][a-z0-9-]*$/', $id) === 1, $id);

    $perms = array_map('strval', (array) ($manifest['permissions'] ?? []));
    $unknown = array_values(array_diff($perms, ADAPTER_KNOWN_PERMISSIONS));
    $add('permissions:known', $unknown === [], '未知权限：' . implode(',', $unknown));

    // surfaces → 所需权限
    $surfaces = (array) ($manifest['surfaces'] ?? []);
    $surfaceKeys = array_keys($surfaces);
    $need = adapter_required_permissions($surfaceKeys);
    $missing = array_values(array_diff($need, $perms));
    $add('permissions:superset', $missing === [], '缺少：' . implode(',', $missing));

    $src = (array) ($manifest['source'] ?? []);
    $add('source:repo', (string) ($src['repo'] ?? '') !== '', 'source.repo 必填（版本可追溯）');
    $ver = (string) ($src['version'] ?? '');
    $verOk = $ver !== '' && (preg_match('/^[A-Za-z-]*v?\d+(\.\d+)*/', $ver) === 1 || preg_match('/^[0-9a-f]{7,40}$/i', $ver) === 1);
    $add('source:version', $verOk, $ver === '' ? 'source.version 必填（pin tag/commit）' : "看起来不是 tag/commit：{$ver}");
    $license = (string) ($src['license'] ?? '');
    $gate = adapter_license_gate($license);
    // 未知/无证 = 硬失败；copyleft 或白名单外 = 可生成但需人审（由状态判定降为 needs-review）
    $add('source:license', $gate['level'] !== 'unknown', $license . ' → ' . $gate['level']);

    $verif = (array) ($manifest['verification'] ?? []);
    $add('verification:no-self-claim', ($verif['badge'] ?? '') !== 'verified', '草稿不得自称 verified');

    $ok = !in_array(false, array_map(static fn ($c) => $c['ok'], $checks), true);
    return ['ok' => $ok, 'checks' => $checks];
}

/**
 * 跑草稿自带的契约测试
 * @return array{ok:bool,note:string,output:string}
 */
function adapter_verify_contract_test(string $testPath, int $timeout = 30): array
{
    if (!is_file($testPath)) {
        return ['ok' => false, 'note' => '契约测试文件不存在', 'output' => ''];
    }
    if (!function_exists('exec')) {
        return ['ok' => true, 'note' => 'exec 不可用，已跳过', 'output' => ''];
    }
    $cmd = 'php ' . escapeshellarg($testPath) . ' 2>&1';
    $out = [];
    $code = 0;
    @exec($cmd, $out, $code);
    $text = implode("\n", array_slice($out, -12));
    return ['ok' => $code === 0, 'note' => $code === 0 ? '通过' : "退出码 {$code}", 'output' => $text];
}

/**
 * 静态分析（可选）：对草稿目录按 level 5 跑 PHPStan
 * @return array{ok:bool,note:string}
 */
function adapter_verify_phpstan(string $draftDir, string $repoRoot, int $level = 5): array
{
    $bin = $repoRoot . '/vendor/bin/phpstan';
    if (!is_file($bin)) {
        return ['ok' => true, 'note' => '未安装 PHPStan，已跳过'];
    }
    // 绝对路径：PHPStan 的 paths 相对配置文件解析，传相对路径会找不到
    $absDraft = str_starts_with($draftDir, '/') ? $draftDir : $repoRoot . '/' . ltrim($draftDir, '/');
    $dir = $repoRoot . '/.deploy';
    @mkdir($dir, 0777, true);

    // 用真实基座符号（scanFiles 只注册符号、不分析）：比手写桩更准，避免"冤枉"AI 代码
    // 注意：PHPStan 2.x 的 scanFiles 只接受**文件**，目录需展开
    $scanFiles = [];
    foreach ([$repoRoot . '/lib/*.php', $repoRoot . '/includes/*.php'] as $glob) {
        foreach ((array) glob($glob) as $f) $scanFiles[] = $f;
    }
    if (is_file($repoRoot . '/admin/config.php')) $scanFiles[] = $repoRoot . '/admin/config.php';
    $scanYaml = '';
    foreach ($scanFiles as $f) $scanYaml .= "        - " . $f . "\n";

    $neon = $dir . '/phpstan-draft-' . substr(md5($absDraft), 0, 8) . '.neon';
    @file_put_contents($neon, "parameters:\n    level: {$level}\n    phpVersion: 80300\n"
        . "    scanFiles:\n" . $scanYaml
        . "    paths:\n        - " . $absDraft . "\n"
        . "    treatPhpDocTypesAsCertain: false\n    reportUnmatchedIgnoredErrors: false\n"
        . "    tmpDir: /tmp/phpstan-adapter-draft\n");

    $cmd = 'php ' . escapeshellarg($bin) . ' analyse -c ' . escapeshellarg($neon) . ' --no-progress --memory-limit=512M 2>&1';
    $out = [];
    $code = 0;
    @exec($cmd, $out, $code);
    @unlink($neon);
    // 只保留「文件:行 错误信息」，便于回喂给修复轮
    $lines = [];
    foreach ($out as $ln) {
        if (preg_match('/\.php:\d+/', (string) $ln) === 1) $lines[] = trim((string) $ln);
        if (count($lines) >= 6) break;
    }
    $tail = $lines !== [] ? implode(' | ', $lines) : trim(implode(' | ', array_slice($out, -3)));
    return ['ok' => $code === 0, 'note' => $code === 0 ? '无静态错误' : 'PHPStan：' . mb_substr($tail, 0, 900)];
}

/** 沙箱能力校验（草稿若声明 artifact 级能力） */
function adapter_verify_sandbox(array $manifest): array
{
    $caps = (array) ($manifest['capabilities'] ?? []);
    $declared = array_map('strval', (array) ($caps['sandbox'] ?? []));
    if ($declared === []) {
        return ['ok' => true, 'note' => '未声明 artifact 能力，n/a'];
    }
    require_once __DIR__ . '/ArtifactSandbox.php';
    if (!function_exists('sandbox_check_permissions')) {
        return ['ok' => true, 'note' => '沙箱不可用，已跳过'];
    }
    $r = sandbox_check_permissions($declared);
    return ['ok' => (bool) ($r['ok'] ?? false), 'note' => ($r['ok'] ?? false) ? '权限在白名单内' : '越权：' . implode(',', (array) ($r['unknown'] ?? []))];
}

/**
 * 跑完整闸门
 * @param array<string,mixed> $manifest
 * @param array<string,mixed> $opts ['skip_phpstan'=>bool, 'tests'=>string]
 * @return array{status:string,badge:string,checks:list<array{id:string,ok:bool,note:string}>,checked_at:string}
 */
function adapter_verify_gate(string $draftDir, array $manifest, array $opts = []): array
{
    $repoRoot = dirname(__DIR__);
    $checks = [];

    $m = adapter_verify_manifest($manifest);
    foreach ($m['checks'] as $c) $checks[] = $c;

    // 契约测试
    $testRel = (string) ($opts['tests'] ?? '');
    if ($testRel === '') {
        $testRel = 'tests/plugin_' . str_replace('-', '_', (string) ($manifest['id'] ?? '')) . '_contract_test.php';
    }
    $ct = adapter_verify_contract_test(rtrim($draftDir, '/') . '/' . $testRel);
    $checks[] = ['id' => 'contract_test', 'ok' => $ct['ok'], 'note' => $ct['note'] . ($ct['output'] !== '' ? ' · ' . mb_substr(str_replace("\n", ' ', $ct['output']), -120) : '')];

    // 静态分析
    if (empty($opts['skip_phpstan'])) {
        $ps = adapter_verify_phpstan($draftDir, $repoRoot);
        $checks[] = ['id' => 'phpstan', 'ok' => $ps['ok'], 'note' => $ps['note']];
    } else {
        $checks[] = ['id' => 'phpstan', 'ok' => true, 'note' => '按参数跳过'];
    }

    // 沙箱
    $sb = adapter_verify_sandbox($manifest);
    $checks[] = ['id' => 'sandbox', 'ok' => $sb['ok'], 'note' => $sb['note']];

    // 状态判定
    $failed = array_values(array_filter($checks, static fn ($c) => !$c['ok']));
    $licenseGate = adapter_license_gate((string) (((array) ($manifest['source'] ?? []))['license'] ?? ''));
    if ($failed !== []) {
        $status = 'blocked';
    } elseif ($licenseGate['level'] !== 'permissive') {
        $status = 'needs-review';
    } else {
        $status = 'passed';
    }
    return [
        'status' => $status,
        'badge' => $status === 'passed' ? 'verified' : 'unverified',
        'failed' => array_map(static fn ($c) => (string) $c['id'], $failed),
        'checks' => $checks,
        'checked_at' => date('c'),
    ];
}

/**
 * 把验证结果写回草稿：verification-report.json + plugin.json 的 verification 块
 * **这是徽章唯一合法的来源路径。**
 * @param array<string,mixed> $report
 */
function adapter_verify_stamp(string $draftDir, array $report): bool
{
    $dir = rtrim($draftDir, '/');
    $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    @file_put_contents($dir . '/verification-report.json', ($json === false ? '{}' : $json) . "\n");

    $pluginJson = $dir . '/plugin.json';
    if (!is_file($pluginJson)) return false;
    $manifest = json_decode((string) @file_get_contents($pluginJson), true);
    if (!is_array($manifest)) return false;
    $manifest['verification'] = [
        'status' => (string) ($report['status'] ?? 'pending'),
        'badge' => (string) ($report['badge'] ?? 'unverified'),
        'tests' => 'tests/plugin_' . str_replace('-', '_', (string) ($manifest['id'] ?? '')) . '_contract_test.php',
        'checked_at' => (string) ($report['checked_at'] ?? ''),
    ];
    $out = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return @file_put_contents($pluginJson, ($out === false ? '{}' : $out) . "\n") !== false;
}
