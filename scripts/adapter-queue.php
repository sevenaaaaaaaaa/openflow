#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * 自助入驻队列 CLI
 *
 *   php scripts/adapter-queue.php list [--status=queued]     # 看队列
 *   php scripts/adapter-queue.php add <owner/repo> [--note=] # 手动入队（等同于别人在前台提交）
 *   php scripts/adapter-queue.php seed [--max=5]             # 把候选池灌进同一条队列
 *   php scripts/adapter-queue.php run  [--limit=1] [--ai] [--complete]
 *   php scripts/adapter-queue.php requeue <ticket>
 *   php scripts/adapter-queue.php reject <ticket> [--note=]
 *   php scripts/adapter-queue.php config [--enabled=1] [--global-daily=20] [--per-submitter=3] [--per-tick=1]
 *
 * 队列与 cron worker、前台自助提交共用同一份数据与同一条流水线。
 */

$ROOT = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/admin/config.php';
require_once $ROOT . '/lib/AdapterSubmission.php';

$args = array_slice($argv, 1);
$cmd = $args[0] ?? 'list';
$pos = [];
$flag = static function (string $name, string $default = '') use ($args): string {
    foreach ($args as $a) if (str_starts_with($a, "--{$name}=")) return substr($a, strlen($name) + 3);
    return $default;
};
$has = static fn(string $f): bool => in_array("--{$f}", $args, true);
foreach (array_slice($args, 1) as $a) if (!str_starts_with($a, '--')) $pos[] = $a;

$STATUS_LABEL = ['queued' => '排队中', 'running' => '进行中', 'done' => '已处理',
                 'failed' => '失败', 'rejected' => '已拒绝'];

switch ($cmd) {
    case 'list': {
        $want = $flag('status');
        $items = adapter_sub_all();
        if ($want !== '') $items = array_values(array_filter($items, static fn(array $i): bool => (string) ($i['status'] ?? '') === $want));
        if ($items === []) { echo "队列为空。用 `add` 手动入队，或 `seed` 从候选池灌入。\n"; exit(0); }
        printf("%-16s %-34s %-8s %-12s %s\n", '受理编号', '仓库', '状态', '闸门', '说明');
        foreach ($items as $i) {
            $r = (array) ($i['result'] ?? []);
            printf("%-16s %-34s %-8s %-12s %s\n",
                (string) $i['ticket'], mb_substr((string) $i['slug'], 0, 32),
                $STATUS_LABEL[(string) $i['status']] ?? (string) $i['status'],
                (string) ($r['gate_status'] ?? '—'),
                mb_substr((string) ($i['message'] ?? ''), 0, 46));
        }
        $counts = [];
        foreach (adapter_sub_all() as $i) { $s = (string) $i['status']; $counts[$s] = ($counts[$s] ?? 0) + 1; }
        echo "\n合计：";
        foreach ($counts as $s => $n) echo ($STATUS_LABEL[$s] ?? $s) . " {$n}　";
        echo "\n";
        exit(0);
    }

    case 'add': {
        $src = $pos[0] ?? '';
        if ($src === '') { fwrite(STDERR, "用法：php scripts/adapter-queue.php add <owner/repo>\n"); exit(2); }
        $r = adapter_submit(['source' => $src, 'note' => $flag('note'),
                             'submitter' => ['key' => 'cli', 'name' => '运维手动']]);
        if (!empty($r['ok'])) { echo "✓ 已入队：{$r['ticket']}（{$src}）\n"; exit(0); }
        fwrite(STDERR, "✗ {$r['error']}" . ((string) $r['duplicate_of'] !== '' ? "（已有受理编号 {$r['duplicate_of']}）" : '') . "\n");
        exit(1);
    }

    case 'seed': {
        $max = (int) ($flag('max', '5'));
        $r = adapter_sub_seed(max(1, $max));
        echo "✓ 从候选池灌入 {$r['added']} 条，跳过 {$r['skipped']} 条（已提交过/已上架/无法解析）\n";
        foreach ($r['tickets'] as $t) echo "   {$t}\n";
        exit(0);
    }

    case 'run': {
        $limit = (int) ($flag('limit', '1'));
        $ai = null;
        if ($has('ai') && class_exists('AiCenter')) {
            $ai = static function (string $system, string $user, array $opts): array {
                $r = AiCenter::chat($system, $user, ['feature' => 'adapter_intake',
                                                     'max_tokens' => (int) ($opts['max_tokens'] ?? 2200)]);
                return ['ok' => (bool) ($r['ok'] ?? false), 'text' => (string) ($r['text'] ?? ''), 'error' => (string) ($r['error'] ?? '')];
            };
        } elseif ($has('ai')) {
            fwrite(STDERR, "⚠ 未找到 AiCenter，回落到模板兜底\n");
        }
        $res = adapter_sub_tick(['limit' => max(1, $limit), 'ai' => $ai, 'complete' => $has('complete')]);
        if ($res['processed'] === 0) { echo "没有待处理项。\n"; exit(0); }
        foreach ($res['rows'] as $row) {
            printf("%s %-16s %-30s → %s%s\n",
                $row['gate'] === 'passed' ? '✓' : '·', $row['ticket'], $row['slug'],
                $STATUS_LABEL[$row['status']] ?? $row['status'],
                (string) $row['gate'] !== '' ? "（闸门 {$row['gate']}）" : ((string) $row['error'] !== '' ? "：{$row['error']}" : ''));
        }
        echo "本轮处理 {$res['processed']} 条。\n";
        exit(0);
    }

    case 'requeue':
    case 'reject': {
        $ticket = $pos[0] ?? '';
        if ($ticket === '') { fwrite(STDERR, "用法：php scripts/adapter-queue.php {$cmd} <ticket>\n"); exit(2); }
        $r = adapter_sub_act($ticket, $cmd === 'requeue' ? 'requeue' : 'reject', $flag('note'));
        if (!empty($r['ok'])) { echo "✓ {$ticket} 已" . ($cmd === 'requeue' ? '重新排队' : '拒绝') . "\n"; exit(0); }
        fwrite(STDERR, "✗ {$r['error']}\n");
        exit(1);
    }

    case 'config': {
        $in = [];
        if ($flag('enabled') !== '')        $in['enabled'] = $flag('enabled') !== '0';
        if ($flag('global-daily') !== '')   $in['global_daily'] = (int) $flag('global-daily');
        if ($flag('per-submitter') !== '')  $in['per_submitter_daily'] = (int) $flag('per-submitter');
        if ($flag('per-tick') !== '')       $in['per_tick'] = (int) $flag('per-tick');
        if ($flag('max-attempts') !== '')   $in['max_attempts'] = (int) $flag('max-attempts');
        $cfg = $in === [] ? adapter_sub_config() : adapter_sub_config_set($in);
        echo "自助入驻配置\n";
        printf("  开启            %s\n", $cfg['enabled'] ? '是' : '否');
        printf("  每人每日上限    %d\n", $cfg['per_submitter_daily']);
        printf("  全站每日上限    %d\n", $cfg['global_daily']);
        printf("  单次 cron 处理  %d\n", $cfg['per_tick']);
        printf("  失败重试上限    %d\n", $cfg['max_attempts']);
        exit(0);
    }

    default:
        fwrite(STDERR, "未知命令：{$cmd}\n用法见文件头注释。\n");
        exit(2);
}
