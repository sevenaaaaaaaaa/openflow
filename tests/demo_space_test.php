<?php
/**
 * 演示空间生命周期契约：开通/域名去重/续期/冻结/到期/归档删除
 */
require_once __DIR__ . '/../lib/DemoSpace.php';

$pass = 0; $fail = 0;
if (!function_exists('ok')) {
    function ok(bool $c, string $msg): void { global $pass, $fail; if ($c) { $pass++; } else { $fail++; echo "  ✗ {$msg}\n"; } }
}

$tmp = sys_get_temp_dir() . '/of-demo-test-' . bin2hex(random_bytes(4));
$svc = new DemoSpace($tmp . '/store.json', $tmp . '/spaces', 90);

$res = $svc->create('acme', 'ops@acme.test');
ok($res['space']['slug'] === 'acme', '创建成功');
ok(strlen($res['code']) >= 8, '邀请码有长度');
ok(is_dir($tmp . '/spaces/acme'), '空间目录已建');

try { $svc->create('acme2', 'other@acme.test'); ok(false, '同域名应拒绝'); }
catch (RuntimeException $e) { ok(true, '同域名被拒'); }

try { $svc->create('x', 'a@b.test'); ok(false, '短 slug 应拒绝'); }
catch (InvalidArgumentException $e) { ok(true, '短 slug 被拒'); }

$space = $svc->renew('acme', 10);
ok((int) $space['expires_at'] > time() + 9 * 86400, '续期生效');

$space = $svc->freeze('acme');
ok($svc->effectiveStatus($space) === 'frozen', '冻结状态');

$store = json_decode((string) file_get_contents($tmp . '/store.json'), true);
$store['acme']['status'] = 'active';
$store['acme']['expires_at'] = time() - 10;
file_put_contents($tmp . '/store.json', json_encode($store));
$svc2 = new DemoSpace($tmp . '/store.json', $tmp . '/spaces', 90);
ok($svc2->effectiveStatus($svc2->all()['acme']) === 'expired', '到期判定');

ok($svc2->delete('acme'), '删除返回 true');
ok(!is_dir($tmp . '/spaces/acme'), '目录已删');
ok(glob($tmp . '/demo-archive/acme-*.tar.gz') !== [], '归档文件存在');
ok($svc2->all() === [], '记录已清空');

echo "\n通过 {$pass}，失败 {$fail}\n";
exit($fail === 0 ? 0 : 1);
