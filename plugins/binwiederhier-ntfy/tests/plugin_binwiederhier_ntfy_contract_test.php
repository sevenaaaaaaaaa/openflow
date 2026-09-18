<?php
declare(strict_types=1);
/**
 * binwiederhier-ntfy 插件契约测试（AdapterForge 生成）
 *   php tests/plugin_binwiederhier_ntfy_contract_test.php
 */

/** @var array<string,mixed> $manifest */
$manifest = array (
  'id' => 'binwiederhier-ntfy',
  'name' => '触达/通知通道适配器',
  'version' => '0.1.0',
  'description' => '由适配代理生成的草稿：来源 binwiederhier/ntfy',
  'author' => 'OpenFlow Adapter Agent',
  'enabled_by_default' => false,
  'permissions' => 
  array (
    0 => 'api',
    1 => 'config',
    2 => 'http',
    3 => 'log',
    4 => 'notify',
  ),
  'source' => 
  array (
    'kind' => 'github',
    'repo' => 'binwiederhier/ntfy',
    'version' => 'v2.28.0',
    'license' => 'apache-2.0',
  ),
  'surfaces' => 
  array (
    'api_route' => 
    array (
      0 => 'api',
      1 => 'rest',
    ),
    'publish' => 
    array (
      0 => 'publish',
      1 => 'post',
      2 => 'send',
      3 => 'notify',
    ),
  ),
  'capabilities' => 
  array (
    'network' => 
    array (
    ),
    'secrets' => 
    array (
    ),
    'data' => 
    array (
    ),
  ),
  'compat' => 
  array (
    'openflow' => '>=2.1 <3',
  ),
  'verification' => 
  array (
    'status' => 'blocked',
    'badge' => 'unverified',
    'tests' => '',
    'checked_at' => '',
  ),
);
/** @var list<string> $surfaces */
$surfaces = array_keys((array) $manifest['surfaces']);

function check(string $n, bool $ok): bool { if (!$ok) echo "  ✗ {$n}\n"; return $ok; }
$results = [];

// 1. 清单字段齐全
foreach (['id','name','version','description','author','permissions','source','surfaces','verification'] as $k) {
    $results[] = check("manifest 含 {$k}", array_key_exists($k, $manifest));
}

// 2. 权限 ⊇ 落点所需（规范 docs/ADAPTER-SPEC.md §二）
$surfacePerms = ['hook' => ['hooks'], 'api_route' => ['api','http'], 'schedule' => ['schedule'],
    'block' => ['block'], 'ingest' => ['data.write','http'], 'publish' => ['notify','http'], 'mcp_tool' => ['mcp']];
$need = ['config','log'];
foreach ($surfaces as $s) { foreach ((array) ($surfacePerms[$s] ?? []) as $p) $need[] = $p; }
$missing = array_values(array_diff(array_unique($need), (array) $manifest['permissions']));
$results[] = check('权限覆盖所有落点', $missing === []);

// 3. 不越权：不得声明白名单外的权限
$known = ['hooks','config','log','data.read','data.write','http','cdp','email','notify','schedule','api','menu','page','slot','asset','block','cli','mcp'];
$results[] = check('无未知权限', array_values(array_diff((array) $manifest['permissions'], $known)) === []);

// 4. 来源可追溯 + 默认不启用
$results[] = check('source.repo 已 pin', (string) (((array) $manifest['source'])['repo'] ?? '') !== '');
$results[] = check('默认不启用', (bool) (((array) $manifest)['enabled_by_default'] ?? true) === false);

// 5. 徽章不得自证
$results[] = check('verification 未自称通过', (string) (((array) $manifest['verification'])['badge'] ?? '') !== 'verified');

$failed = count(array_filter($results, static fn (bool $r): bool => !$r));
echo "\n合计：" . (count($results) - $failed) . " 通过，{$failed} 失败\n";
exit($failed === 0 ? 0 : 1);
