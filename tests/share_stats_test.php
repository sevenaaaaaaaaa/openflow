<?php
declare(strict_types=1);
/**
 * 分享访问统计 契约测试
 *   php tests/share_stats_test.php
 */

define('DATA_DIR', sys_get_temp_dir() . '/of-sstat-' . getmypid());
@mkdir(DATA_DIR . '/projects', 0777, true);
function json_read(string $f): array { return is_file($f) ? ((array) json_decode((string) file_get_contents($f), true)) : []; }
function json_write(string $f, array $d): bool { @mkdir(dirname($f), 0777, true); return (bool) file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }

require_once __DIR__ . '/../lib/ProjectSystem.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✓ {$n}\n"; } else { $fail++; echo "  ✗ {$n}" . ($d !== '' ? " — {$d}" : "") . "\n"; }
}

echo "分享访问统计\n";
$_SESSION = ['admin_user' => 'Seven', 'admin_role' => 'admin'];
$pid = (string) (ps_project_save(['name' => '对外排期'])['id'] ?? '');
$tok = (string) (ps_share_create($pid, 'board', '', 'Seven', true)['token'] ?? '');

/* 零值 */
$zero = ps_share_stats($pid, $tok);
check('未打开时全为零', (int) $zero['opens'] === 0 && (int) $zero['visitors'] === 0 && (int) $zero['total_14d'] === 0);
check('未知 token 也是零值（不报错）', (int) (ps_share_stats($pid, str_repeat('0', 32))['opens'] ?? -1) === 0);

/* 追踪：同一访客两次、另一访客一次、直接打开一次 */
ps_share_track($tok, ['ip' => '1.1.1.1', 'ua' => 'UA-A', 'country' => 'cn', 'ref' => 'https://t.me/x/1']);
ps_share_track($tok, ['ip' => '1.1.1.1', 'ua' => 'UA-A', 'country' => 'CN', 'ref' => 'https://t.me/x/2']);
ps_share_track($tok, ['ip' => '2.2.2.2', 'ua' => 'UA-B', 'country' => 'US', 'ref' => '']);
$st = ps_share_stats($pid, $tok);
check('打开次数累加', (int) $st['opens'] === 3, json_encode($st['opens']));
check('独立访客按 IP+UA 去重', (int) $st['visitors'] === 2, json_encode($st['visitors']));
check('近 14 天桶合计正确', (int) $st['total_14d'] === 3, json_encode($st['total_14d']));
check('国家归一化为大写并聚合', ($st['countries']['CN'] ?? 0) === 2 && ($st['countries']['US'] ?? 0) === 1, json_encode($st['countries']));
check('来源只留主机名（去 www）', ($st['refs']['t.me'] ?? 0) === 2, json_encode($st['refs']));
check('无 Referer 归为直接打开', ($st['refs']['直接打开'] ?? 0) === 1, json_encode($st['refs']));
check('首次与最近打开时间已记录', $st['first_open'] !== '' && $st['last_open'] !== '');
check('近 14 天为定长窗口', count((array) $st['days']) === 14);

/* 隐私：不落原始 IP / UA */
$raw = json_encode(ps_shares($pid), JSON_UNESCAPED_UNICODE);
check('不存原始 IP', !str_contains($raw, '1.1.1.1') && !str_contains($raw, '2.2.2.2'));
check('不存原始 UA', !str_contains($raw, 'UA-A'));

/* 非法/缺失上下文不炸 */
ps_share_track($tok, []);
ps_share_track($tok, ['ip' => '', 'ua' => '', 'country' => 'CHINA', 'ref' => 'not a url']);
$st2 = ps_share_stats($pid, $tok);
check('非法/缺失国家归为未知（两次：空上下文 + CHINA）', ($st2['countries']['未知'] ?? 0) === 2, json_encode($st2['countries']));
check('非法来源归为直接打开', ($st2['refs']['直接打开'] ?? 0) >= 2, json_encode($st2['refs']));
check('坏 token 不写入', (static function () use ($pid, $st2): bool {
    ps_share_track('xyz', ['ip' => '9.9.9.9']);
    return (int) ps_share_stats($pid, (string) array_key_first(ps_shares($pid)))['opens'] >= 4;
})());

/* 日桶裁剪（保留 30 天） */
$p2 = ps_project_get($pid);
$shares = (array) ($p2['shares'] ?? []);
$shares[$tok]['days'] = [];
for ($i = 0; $i < 40; $i++) $shares[$tok]['days'][date('Y-m-d', strtotime('-' . $i . ' day'))] = 1;
ps_project_save(['id' => $pid, 'name' => '对外排期', 'shares' => $shares]);
ps_share_track($tok, ['ip' => '3.3.3.3', 'ua' => 'UA-C']);
$keys = array_keys((array) (ps_shares($pid)[$tok]['days'] ?? []));
check('日桶裁剪到 30 条', count($keys) <= 30, (string) count($keys));
check('裁剪保留最近日期', in_array(date('Y-m-d'), $keys, true), json_encode(array_slice($keys, -2)));

/* 访客指纹上限 200 */
$p3 = ps_project_get($pid);
$shares2 = (array) ($p3['shares'] ?? []);
$shares2[$tok]['visitors'] = array_fill(0, 200, 'zzzzzzzzzz');
ps_project_save(['id' => $pid, 'name' => '对外排期', 'shares' => $shares2]);
ps_share_track($tok, ['ip' => '4.4.4.4', 'ua' => 'UA-D']);
check('访客指纹上限 200', count((array) (ps_shares($pid)[$tok]['visitors'] ?? [])) === 200, (string) count((array) (ps_shares($pid)[$tok]['visitors'] ?? [])));

@exec('rm -rf ' . escapeshellarg(DATA_DIR));
echo "\n合计：{$pass} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
