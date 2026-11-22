#!/usr/bin/env php
<?php
/**
 * 演示空间运维 CLI
 *   php scripts/demo-space.php list
 *   php scripts/demo-space.php create <slug> <email> [days]
 *   php scripts/demo-space.php renew <slug> <days>
 *   php scripts/demo-space.php freeze <slug>
 *   php scripts/demo-space.php delete <slug>
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/lib/DemoSpace.php';
$svc = DemoSpace::defaults($root);
$cmd = $argv[1] ?? 'list';

try {
    switch ($cmd) {
        case 'list':
            foreach ($svc->all() as $slug => $space) {
                printf("%-20s %-10s %-28s %s
", $slug, $svc->effectiveStatus($space), (string) $space['email'], date('Y-m-d', (int) $space['expires_at']));
            }
            break;
        case 'create':
            $res = $svc->create((string) ($argv[2] ?? ''), (string) ($argv[3] ?? ''));
            echo "已开通 {$res['space']['slug']}，邀请码 {$res['code']}（只显示一次）
";
            break;
        case 'renew':
            $space = $svc->renew((string) ($argv[2] ?? ''), (int) ($argv[3] ?? 90));
            echo "已续期，到期 " . date('Y-m-d', (int) $space['expires_at']) . "
";
            break;
        case 'freeze':
            $svc->freeze((string) ($argv[2] ?? ''));
            echo "已冻结
";
            break;
        case 'delete':
            echo $svc->delete((string) ($argv[2] ?? '')) ? "已归档并删除
" : "空间不存在
";
            break;
        default:
            fwrite(STDERR, "未知命令：{$cmd}
");
            exit(2);
    }
} catch (Throwable $e) {
    fwrite(STDERR, '✗ ' . $e->getMessage() . "
");
    exit(1);
}
