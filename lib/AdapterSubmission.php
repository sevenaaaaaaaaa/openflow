<?php
declare(strict_types=1);
/**
 * 开源工具自助入驻队列（Adapter Submission）
 *
 * 【为什么要有】适配流水线整条都通了，但**第三方没有入口**——
 * `api/developer.php` 只能提交 skill / product / course，适配只能由我们在 CLI 跑
 * `forge-adapter.php <repo>`。于是现状是"我们去适配"，不是"他们来入驻"，
 * 生态市场的供给上限就等于我们自己的手速（28 候选 → 队列 4 → 上架 1）。
 *
 * 【这一层解决什么】把"提交"和"跑流水线"解耦：
 *   提交（毫秒级，Web 请求里完成）→ 排队 → cron worker 逐个跑（分钟级，带预算与重试）
 * 绝不在 Web 请求里跑 intake/forge——那要联网抓仓库 + 调模型，几十秒起步，必然超时。
 *
 * 【状态机】
 *   queued ──claim──▶ running ──┬─ 闸门跑完 ─▶ done    （草稿保留，进人审）
 *                               └─ 异常 ────▶ failed   （attempts < max 时自动重回 queued）
 *   任何状态 ──人工──▶ rejected / requeue
 *
 * 数据：data/ecosystem/submissions.json（flock 串行化）
 * 配置：data/ecosystem/intake-config.json（限流与预算）
 */

require_once __DIR__ . '/AdapterIntake.php';
require_once __DIR__ . '/AdapterPipeline.php';

function adapter_sub_dir(): string
{
    return (defined('DATA_DIR') ? DATA_DIR : dirname(__DIR__) . '/data') . '/ecosystem';
}

function adapter_sub_file(): string { return adapter_sub_dir() . '/submissions.json'; }

/**
 * 限流与预算。默认值刻意保守：AI 额度是真金白银，入口一开就可能被刷。
 * @return array{enabled:bool, per_submitter_daily:int, global_daily:int, max_attempts:int, per_tick:int}
 */
function adapter_sub_config(): array
{
    $f = adapter_sub_dir() . '/intake-config.json';
    $d = [];
    if (is_file($f)) {
        $t = json_decode((string) @file_get_contents($f), true);
        if (is_array($t)) $d = $t;
    }
    return [
        'enabled'             => !array_key_exists('enabled', $d) || (bool) $d['enabled'],
        'per_submitter_daily' => max(1, min(50, (int) ($d['per_submitter_daily'] ?? 3))),
        'global_daily'        => max(1, min(500, (int) ($d['global_daily'] ?? 20))),
        'max_attempts'        => max(1, min(5, (int) ($d['max_attempts'] ?? 2))),
        'per_tick'            => max(1, min(5, (int) ($d['per_tick'] ?? 1))),
    ];
}

function adapter_sub_config_set(array $in): array
{
    $cur = adapter_sub_config();
    $next = [
        'enabled'             => array_key_exists('enabled', $in) ? !empty($in['enabled']) : $cur['enabled'],
        'per_submitter_daily' => max(1, min(50, (int) ($in['per_submitter_daily'] ?? $cur['per_submitter_daily']))),
        'global_daily'        => max(1, min(500, (int) ($in['global_daily'] ?? $cur['global_daily']))),
        'max_attempts'        => max(1, min(5, (int) ($in['max_attempts'] ?? $cur['max_attempts']))),
        'per_tick'            => max(1, min(5, (int) ($in['per_tick'] ?? $cur['per_tick']))),
    ];
    if (!is_dir(adapter_sub_dir())) @mkdir(adapter_sub_dir(), 0755, true);
    @file_put_contents(adapter_sub_dir() . '/intake-config.json',
        (string) json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $next;
}

/**
 * 带排他锁的读-改-写。所有写路径都经过这里，避免 cron worker 与 Web 提交互相覆盖。
 * @param callable(list<array<string,mixed>>): array{items:list<array<string,mixed>>, ret:mixed} $mutator
 * @return mixed mutator 的 ret
 */
function adapter_sub_transact(callable $mutator)
{
    $dir = adapter_sub_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return null;
    $fh = @fopen(adapter_sub_file(), 'c+');
    if ($fh === false) return null;
    try {
        if (!flock($fh, LOCK_EX)) return null;
        $raw = (string) stream_get_contents($fh);
        $d = $raw !== '' ? json_decode($raw, true) : null;
        $items = (is_array($d) && is_array($d['items'] ?? null)) ? $d['items'] : [];
        $r = $mutator($items);
        $next = is_array($r['items'] ?? null) ? $r['items'] : $items;
        $json = json_encode(['items' => array_values($next)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $json);
            fflush($fh);
        }
        return $r['ret'] ?? null;
    } finally {
        @flock($fh, LOCK_UN);
        @fclose($fh);
    }
}

/** @return list<array<string,mixed>> */
function adapter_sub_all(): array
{
    if (!is_file(adapter_sub_file())) return [];
    $d = json_decode((string) @file_get_contents(adapter_sub_file()), true);
    return (is_array($d) && is_array($d['items'] ?? null)) ? $d['items'] : [];
}

function adapter_sub_find(string $ticket): ?array
{
    foreach (adapter_sub_all() as $it) {
        if ((string) ($it['ticket'] ?? '') === $ticket) return $it;
    }
    return null;
}

/** 对外可见的字段（**不含提交者联系方式**——状态页凭 ticket 就能看，不能顺带泄露提交人） */
function adapter_sub_public(array $it): array
{
    $r = (array) ($it['result'] ?? []);
    return [
        'ticket'       => (string) ($it['ticket'] ?? ''),
        'source'       => (string) ($it['slug'] ?? ''),
        'url'          => (string) ($it['url'] ?? ''),
        'status'       => (string) ($it['status'] ?? ''),
        'stage'        => (string) ($it['stage'] ?? ''),
        'attempts'     => (int) ($it['attempts'] ?? 0),
        'submitted_by' => (string) ($it['submitter']['name'] ?? ''),
        'badge'        => (string) ($r['badge'] ?? ''),
        'gate_status'  => (string) ($r['gate_status'] ?? ''),
        'failed'       => array_values((array) ($r['failed'] ?? [])),
        'todos'        => (int) ($r['todos'] ?? 0),
        'draft_id'     => (string) ($r['draft_id'] ?? ''),
        'message'      => (string) ($it['message'] ?? ''),
        'created_at'   => (string) ($it['created_at'] ?? ''),
        'updated_at'   => (string) ($it['updated_at'] ?? ''),
    ];
}

/** 当天计数（限流用）。存量灌入不占用户配额。 */
function adapter_sub_count_today(array $items, string $submitterKey = ''): int
{
    $today = date('Y-m-d');
    $n = 0;
    foreach ($items as $it) {
        if (substr((string) ($it['created_at'] ?? ''), 0, 10) !== $today) continue;
        if ((string) ($it['origin'] ?? '') === 'seed') continue;
        if ($submitterKey !== '' && (string) ($it['submitter']['key'] ?? '') !== $submitterKey) continue;
        $n++;
    }
    return $n;
}

/**
 * 提交一个开源工具。**只做校验与入队，不跑流水线。**
 *
 * @param array{source:string, note?:string, origin?:string,
 *              submitter?:array{key?:string,name?:string,contact?:string}} $in
 * @param array{drafts_dir?:string, plugins_dir?:string} $env 用于查重（已有草稿 / 已上架）
 * @return array{ok:bool, ticket:string, status:string, error:string, duplicate_of:string}
 */
function adapter_submit(array $in, array $env = []): array
{
    $fail = static fn(string $e): array =>
        ['ok' => false, 'ticket' => '', 'status' => '', 'error' => $e, 'duplicate_of' => ''];

    $cfg = adapter_sub_config();
    if (!$cfg['enabled']) return $fail('自助提交当前已关闭');

    $src = adapter_parse_source(trim((string) ($in['source'] ?? '')));
    if (!($src['ok'] ?? false)) return $fail('来源无法解析：' . (string) $src['error']);
    if (($src['kind'] ?? '') !== 'github') return $fail('目前只支持 GitHub 仓库（收到 ' . (string) $src['kind'] . '）');
    $slug = (string) $src['slug'];

    $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 500);
    $origin = (($in['origin'] ?? 'self-serve') === 'seed') ? 'seed' : 'self-serve';
    $sub = (array) ($in['submitter'] ?? []);
    $submitter = [
        'key'     => mb_substr((string) ($sub['key'] ?? ''), 0, 64),
        'name'    => mb_substr((string) ($sub['name'] ?? ''), 0, 64),
        'contact' => mb_substr((string) ($sub['contact'] ?? ''), 0, 120),
    ];

    // 已经有草稿或已上架 → 不重复跑（白花 AI 额度，还会撞目录名）
    $draftsDir  = (string) ($env['drafts_dir'] ?? (dirname(__DIR__) . '/plugins/_drafts'));
    $pluginsDir = (string) ($env['plugins_dir'] ?? (dirname(__DIR__) . '/plugins'));
    $guessId = strtolower(str_replace('/', '-', $slug));
    if (is_dir(rtrim($pluginsDir, '/') . '/' . $guessId)) return $fail('这个工具已经在生态市场上架了');
    if (is_dir(rtrim($draftsDir, '/') . '/' . $guessId)) return $fail('这个工具已有适配草稿在人审队列里');

    $r = adapter_sub_transact(static function (array $items) use (
        $slug, $src, $note, $origin, $submitter, $cfg, $fail
    ): array {
        // 同一个仓库不排两次队
        foreach ($items as $it) {
            if ((string) ($it['slug'] ?? '') !== $slug) continue;
            if (in_array((string) ($it['status'] ?? ''), ['queued', 'running', 'done'], true)) {
                return ['items' => $items, 'ret' => [
                    'ok' => false, 'ticket' => (string) $it['ticket'], 'status' => (string) $it['status'],
                    'error' => '这个仓库已经提交过了，可以用受理编号查看进度',
                    'duplicate_of' => (string) $it['ticket'],
                ]];
            }
        }

        if ($origin !== 'seed') {
            if (adapter_sub_count_today($items) >= $cfg['global_daily']) {
                return ['items' => $items, 'ret' => $fail('今天的自助适配额度已用完，请明天再来')];
            }
            if ($submitter['key'] !== ''
                && adapter_sub_count_today($items, $submitter['key']) >= $cfg['per_submitter_daily']) {
                return ['items' => $items, 'ret' => $fail('你今天已提交 ' . $cfg['per_submitter_daily'] . ' 个，明天可以继续')];
            }
        }

        $now = date('Y-m-d H:i:s');
        $item = [
            'ticket'     => 'sub_' . bin2hex(random_bytes(5)),
            'slug'       => $slug,
            'url'        => (string) $src['url'],
            'note'       => $note,
            'origin'     => $origin,
            'submitter'  => $submitter,
            'status'     => 'queued',
            'stage'      => '',
            'attempts'   => 0,
            'message'    => '已受理，排队等待自动适配',
            'result'     => [],
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $items[] = $item;
        return ['items' => $items, 'ret' => [
            'ok' => true, 'ticket' => $item['ticket'], 'status' => 'queued', 'error' => '', 'duplicate_of' => '',
        ]];
    });

    return is_array($r) ? $r : $fail('队列写入失败');
}

/**
 * worker 取一个待处理项并原子置为 running（避免两个 cron 并发跑同一条）。
 * @return array<string,mixed>|null
 */
function adapter_sub_claim(): ?array
{
    $r = adapter_sub_transact(static function (array $items): array {
        foreach ($items as $i => $it) {
            if ((string) ($it['status'] ?? '') !== 'queued') continue;
            $now = date('Y-m-d H:i:s');
            $items[$i]['status']     = 'running';
            $items[$i]['stage']      = 'intake';
            $items[$i]['attempts']   = (int) ($it['attempts'] ?? 0) + 1;
            $items[$i]['message']    = '正在自动适配…';
            $items[$i]['started_at'] = $now;
            $items[$i]['updated_at'] = $now;
            return ['items' => $items, 'ret' => $items[$i]];
        }
        return ['items' => $items, 'ret' => null];
    });
    return is_array($r) ? $r : null;
}

/** 写回一条的最终状态 */
function adapter_sub_finish(string $ticket, array $patch): bool
{
    $r = adapter_sub_transact(static function (array $items) use ($ticket, $patch): array {
        foreach ($items as $i => $it) {
            if ((string) ($it['ticket'] ?? '') !== $ticket) continue;
            foreach ($patch as $k => $v) $items[$i][$k] = $v;
            $items[$i]['updated_at']  = date('Y-m-d H:i:s');
            $items[$i]['finished_at'] = date('Y-m-d H:i:s');
            return ['items' => $items, 'ret' => true];
        }
        return ['items' => $items, 'ret' => false];
    });
    return $r === true;
}

/** 人工动作：重排队 / 拒绝 */
function adapter_sub_act(string $ticket, string $action, string $message = ''): array
{
    $r = adapter_sub_transact(static function (array $items) use ($ticket, $action, $message): array {
        foreach ($items as $i => $it) {
            if ((string) ($it['ticket'] ?? '') !== $ticket) continue;
            if ($action === 'requeue') {
                $items[$i]['status']  = 'queued';
                $items[$i]['stage']   = '';
                // 人工重排 = 明确要求"再试一次"，必须给一次新的重试预算：
                // 否则一条已经用满 attempts 的记录会在下一轮 worker 里立刻又变回 failed，
                // 操作者看到的是"点了重排没反应"。
                $items[$i]['attempts'] = 0;
                $items[$i]['message'] = $message !== '' ? $message : '已重新排队（重试次数已重置）';
            } elseif ($action === 'reject') {
                $items[$i]['status']  = 'rejected';
                $items[$i]['message'] = $message !== '' ? $message : '人工拒绝';
            } else {
                return ['items' => $items, 'ret' => ['ok' => false, 'error' => '未知动作：' . $action]];
            }
            $items[$i]['updated_at'] = date('Y-m-d H:i:s');
            return ['items' => $items, 'ret' => ['ok' => true, 'error' => '', 'item' => $items[$i]]];
        }
        return ['items' => $items, 'ret' => ['ok' => false, 'error' => '找不到受理编号：' . $ticket]];
    });
    return is_array($r) ? $r : ['ok' => false, 'error' => '队列写入失败'];
}

/**
 * 跑一轮 worker：取队列里的待处理项，逐个走流水线。
 *
 * @param array{limit?:int, pipeline?:callable, out_dir?:string, ai?:callable|null,
 *              complete?:bool, fetch?:callable, skip_phpstan?:bool} $opts
 * @return array{processed:int, rows:list<array<string,mixed>>}
 */
function adapter_sub_tick(array $opts = []): array
{
    $cfg = adapter_sub_config();
    $limit = max(1, min(5, (int) ($opts['limit'] ?? $cfg['per_tick'])));
    $runner = $opts['pipeline'] ?? null;
    $rows = [];

    for ($n = 0; $n < $limit; $n++) {
        $item = adapter_sub_claim();
        if ($item === null) break;
        $ticket = (string) $item['ticket'];
        $slug = (string) $item['slug'];

        try {
            $res = is_callable($runner) ? $runner($slug, $opts) : adapter_pipeline_run($slug, $opts);
        } catch (\Throwable $e) {
            $res = ['ok' => false, 'error' => $e->getMessage(), 'stage' => 'exception'];
        }
        if (!is_array($res)) $res = ['ok' => false, 'error' => '流水线返回格式错误', 'stage' => 'exception'];

        // 闸门跑完就算"处理完成"——没过闸门不是异常，是结论，草稿保留给人审。
        // 只有连闸门都没跑到（解析/拉取/合成失败）才算 failed，且还有重试余额时自动重排队。
        $hasReport = (string) ($res['status'] ?? '') !== '';
        $patch = [
            'stage'  => (string) ($res['stage'] ?? ''),
            'result' => [
                'draft_id'     => (string) ($res['id'] ?? ''),
                'draft_dir'    => (string) ($res['dir'] ?? ''),
                'badge'        => (string) ($res['badge'] ?? ''),
                'gate_status'  => (string) ($res['status'] ?? ''),
                'failed'       => array_values((array) ($res['failed'] ?? [])),
                'todos'        => (int) ($res['todos'] ?? 0),
                'license'      => (string) ($res['license'] ?? ''),
                'class'        => (string) ($res['class'] ?? ''),
                'generated_by' => (string) ($res['generated_by'] ?? ''),
            ],
        ];
        if ($hasReport) {
            $patch['status'] = 'done';
            $patch['message'] = ((string) $res['status'] === 'passed')
                ? '适配草稿已生成并通过闸门，等待人工审核上架'
                : '适配草稿已生成，但未通过全部闸门（' . implode('、', (array) ($res['failed'] ?? [])) . '），已转人工审核';
        } elseif ((int) ($item['attempts'] ?? 1) < $cfg['max_attempts']) {
            $patch['status'] = 'queued';
            $patch['message'] = '本轮失败（' . (string) ($res['error'] ?? '') . '），稍后自动重试';
        } else {
            $patch['status'] = 'failed';
            $patch['message'] = '自动适配失败：' . (string) ($res['error'] ?? '') . '（已达重试上限，可人工处理）';
        }
        adapter_sub_finish($ticket, $patch);
        $rows[] = ['ticket' => $ticket, 'slug' => $slug, 'status' => (string) $patch['status'],
                   'gate' => (string) ($res['status'] ?? ''), 'error' => (string) ($res['error'] ?? '')];
    }

    return ['processed' => count($rows), 'rows' => $rows];
}

/**
 * 把存量候选池灌进同一条队列（origin=seed，不占用户配额）。
 * 这样"批量吞吐"与"自助入驻"共用一套流水线、一套状态、一套后台，而不是两条平行实现。
 *
 * @return array{added:int, skipped:int, tickets:list<string>}
 */
function adapter_sub_seed(int $max = 5, ?string $candidatesFile = null, array $env = []): array
{
    $file = $candidatesFile ?? (adapter_sub_dir() . '/candidates.json');
    $added = 0; $skipped = 0; $tickets = [];
    if (!is_file($file)) return ['added' => 0, 'skipped' => 0, 'tickets' => []];

    $d = json_decode((string) @file_get_contents($file), true);
    $list = [];
    if (is_array($d)) {
        if (is_array($d['candidates'] ?? null))      $list = $d['candidates'];
        elseif (is_array($d['items'] ?? null))       $list = $d['items'];
        elseif (array_is_list($d))                   $list = $d;
    }

    foreach ($list as $c) {
        if ($added >= $max) break;
        if (!is_array($c)) { $skipped++; continue; }
        $slug = (string) ($c['slug'] ?? ($c['repo'] ?? ($c['full_name'] ?? ($c['name'] ?? ''))));
        if ($slug === '') { $skipped++; continue; }
        $r = adapter_submit([
            'source' => $slug,
            'origin' => 'seed',
            'note'   => '候选池自动灌入：' . (string) ($c['class'] ?? ($c['category'] ?? '')),
            'submitter' => ['key' => 'seed', 'name' => '候选池'],
        ], $env);
        if (!empty($r['ok'])) { $added++; $tickets[] = (string) $r['ticket']; } else { $skipped++; }
    }
    return ['added' => $added, 'skipped' => $skipped, 'tickets' => $tickets];
}
