<?php
/**
 * MCP 治理契约 —— php tests/mcp_contract_test.php
 *
 * 历史教训：mcp-server.php 从上线起就开放了 18 个工具、其中 4 个会改数据，
 * 却没有逐工具鉴权、没有一行审计。这套测试把治理层钉死：
 *
 *   1. tools 清单里的每个工具都必须在注册表里登记（漏登记 = 悄悄对外开放）
 *   2. 注册表里的 scope 只能是 read / write，写操作必须真的要求 write 权限
 *   3. 服务端必须在 tools/call 前调鉴权、调用后落审计
 *   4. tools/list 必须按权限过滤
 *   5. 未登记的工具一律按 write 从严
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  ✗ $msg\n"; } }

// McpGuard 会经 AuditLog / ApiKeyAuth 引到 admin/config.php，DATA_DIR 由它定义，这里不要抢
require_once "$root/lib/McpGuard.php";

// ── 1. 服务端声明的工具全部登记在案 ──
$src = file_get_contents("$root/mcp-server.php");
preg_match_all("/'name'\s*=>\s*'([a-z0-9_]+)'/", $src, $m);
$declared = array_values(array_unique($m[1]));
$meta = mcp_tool_meta();
ok(count($declared) > 0, '没能从 mcp-server.php 解析出工具清单（提取正则失效？）');
foreach ($declared as $t) {
    ok(isset($meta[$t]), "工具 {$t} 没在 mcp_tool_meta() 登记 —— 未登记等于没有权限校验就对外开放");
}
foreach (array_keys($meta) as $t) {
    ok(in_array($t, $declared, true), "注册表里的 {$t} 在 mcp-server.php 已不存在，注册表该同步删除");
}

// ── 2. scope 合法，写操作确实需要 write ──
foreach ($meta as $tool => $row) {
    ok(in_array($row[0], ['read', 'write'], true), "工具 {$tool} 的 scope「{$row[0]}」非法（只能 read / write）");
    ok(isset($row[2]) && $row[2] !== '', "工具 {$tool} 缺少中文名，审计日志会不可读");
    if ($row[0] === 'write') {
        ok(!mcp_can(['permissions' => ['read']], $tool), "写操作 {$tool} 竟然允许只读 Key 调用");
        ok(mcp_can(['permissions' => ['write']], $tool), "写操作 {$tool} 拒绝了 write Key");
    } else {
        ok(mcp_can(['permissions' => ['read']], $tool), "只读工具 {$tool} 拒绝了 read Key");
    }
    ok(mcp_can(['permissions' => ['admin']], $tool), "admin Key 被工具 {$tool} 拒绝");
}

// ── 3. 未登记工具从严 ──
ok(mcp_tool_scope('__never_registered__') === 'write', '未登记的工具没有按 write 从严处理');
ok(!mcp_can(['permissions' => ['read']], '__never_registered__'), '未登记的工具对只读 Key 放行了');

// ── 4. 服务端确实接了治理层 ──
ok(str_contains($src, "McpGuard.php"), 'mcp-server.php 没有引入 McpGuard');
ok(str_contains($src, 'mcp_identify('), 'mcp-server.php 没有做身份识别');
ok(str_contains($src, 'mcp_can('), 'mcp-server.php 的 tools/call 没有逐工具鉴权');
ok(str_contains($src, 'mcp_audit('), 'mcp-server.php 没有审计留痕');
ok(str_contains($src, 'mcp_filter_tools('), 'tools/list 没有按权限过滤');
ok(!preg_match('/hash_equals\(\$expected, \$apiKey\)/', $src), 'mcp-server.php 仍在用写死的单一 Key 校验，应走 McpGuard');

// ── 5. 参数脱敏 ──
$san = mcp_sanitize_args(['api_key' => 'sk-live-xxx', 'password' => 'p', 'token' => 't', 'title' => str_repeat('长', 400)]);
ok($san['api_key'] === '***' && $san['password'] === '***' && $san['token'] === '***', '审计参数没有对密钥类字段脱敏');
ok(mb_strlen($san['title']) < 300, '审计参数没有截断长文本，日志会被正文撑爆');

echo "\n通过 $pass · 失败 $fail\n";
exit($fail ? 1 : 0);
