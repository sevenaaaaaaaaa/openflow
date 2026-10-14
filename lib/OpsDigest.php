<?php
declare(strict_types=1);
/**
 * OpsDigest —— 自动化运行观测与每日简报
 *
 * 三件事：
 *   ① 观测：正在跑的自动化任务（延时续流队列 + 今日执行流水）和 AI token 消耗
 *      的每日 / 每小时记录，供后台「运行与 Token 统计」页展示。
 *   ② 简报：每天清晨（默认 8 点）和傍晚（默认 18 点）各发一封邮件给管理员，
 *      内容：执行基本情况 · 流量情况 · 转化情况 · 当日任务执行 · token 消耗。
 *   ③ 幂等：cron 每分钟跑一次，靠 data/ops-digest-sent.json 记住当天两个时点
 *      发没发过，绝不重发。
 *
 * 依赖：admin/config.php（json_read/json_write/DATA_DIR）、Database、AiBudget。
 * 邮件走 MailChannel::mail_send（与报表订阅同一条腿）。
 */

// ─── 设置 ───────────────────────────────────────────────

/**
 * 简报设置。收件人默认取站点 admin_email；清晨/傍晚时点可配（0-23 时）。
 * 结构：['recipients' => string[], 'morning_hour' => int, 'evening_hour' => int]
 */
function ops_digest_settings(): array {
    $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
    $d = is_array($s['ops_digest'] ?? null) ? $s['ops_digest'] : [];
    $out = [
        'recipients'   => [],
        'morning_hour' => 8,
        'evening_hour' => 18,
    ];
    // 收件人：配置优先；没配则回落到站点管理员邮箱
    $rcpts = array_values(array_filter(array_map('trim', (array)($d['recipients'] ?? []))));
    if ($rcpts === [] && function_exists('site_config_get')) {
        $admin = trim(site_config_get('admin_email'));
        if ($admin !== '') $rcpts = [$admin];
    }
    $out['recipients'] = $rcpts;
    foreach (['morning_hour', 'evening_hour'] as $k) {
        $out[$k] = max(0, min(23, (int)($d[$k] ?? $out[$k])));
    }
    return $out;
}

function ops_digest_save(array $patch): array {
    $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
    if (!is_array($s)) $s = [];
    $cur = ops_digest_settings();
    if (isset($patch['recipients_raw'])) {
        // 逗号 / 分号 / 换行 / 空白都能分；只留长得像邮箱的
        $parts = preg_split('/[\s,;，；]+/u', (string)$patch['recipients_raw']) ?: [];
        $cur['recipients'] = array_values(array_unique(array_filter($parts, fn($e) => (bool)filter_var($e, FILTER_VALIDATE_EMAIL))));
    }
    foreach (['morning_hour', 'evening_hour'] as $k) if (isset($patch[$k])) $cur[$k] = max(0, min(23, (int)$patch[$k]));
    if ($cur['evening_hour'] <= $cur['morning_hour']) $cur['evening_hour'] = min(23, $cur['morning_hour'] + 1);
    $s['ops_digest'] = $cur;
    if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
    return $cur;
}

// ─── 观测：正在跑的任务 ─────────────────────────────────

/**
 * 正在执行 / 排队中的自动化任务。
 * 来源：automation-queue.json（delay 节点的延时续流队列）。
 * run_at 在未来 = 还在延时等待；已到期但 cron 未轮到 = 待执行。
 */
function ops_running_tasks(): array {
    $queue = function_exists('automation_file') ? json_read(DATA_DIR . '/automation-queue.json') : [];
    if (!is_array($queue)) $queue = [];
    $now = time();
    $pending = [];
    foreach ($queue as $q) {
        if (!is_array($q)) continue;
        $runAt = (string)($q['run_at'] ?? '');
        $pending[] = [
            'id'        => (string)($q['id'] ?? ''),
            'flow_id'   => (string)($q['flow_id'] ?? ''),
            'flow_name' => (string)($q['flow_name'] ?? ($q['flow_id'] ?? '')),
            'step'      => (int)($q['step_index'] ?? 0),
            'run_at'    => $runAt,
            'state'     => $runAt !== '' && strtotime($runAt) > $now ? 'delaying' : 'due',
            'created_at'=> (string)($q['created_at'] ?? ''),
        ];
    }
    usort($pending, fn($a, $b) => strcmp($a['run_at'], $b['run_at']));
    return $pending;
}

/**
 * 当日任务执行情况（按流程聚合今天的自动化日志）。
 * 返回：[['id','name','steps'=>执行步数,'errors'=>失败数,'last_at','last_msg'], ...]
 */
function ops_today_flow_stats(string $day = ''): array {
    $day = $day ?: date('Y-m-d');
    $log = function_exists('automation_log_file') ? json_read(automation_log_file()) : [];
    if (!is_array($log)) $log = [];
    $names = [];
    if (function_exists('automation_get')) {
        foreach (automation_get() as $f) {
            if (is_array($f)) $names[(string)($f['id'] ?? '')] = (string)($f['name'] ?? '');
        }
    }
    $out = [];
    foreach ($log as $e) {
        if (!is_array($e) || substr((string)($e['time'] ?? ''), 0, 10) !== $day) continue;
        $fid = (string)($e['flow'] ?? '');
        if (!isset($out[$fid])) {
            $out[$fid] = ['id' => $fid, 'name' => $names[$fid] ?? ($fid ?: '（未知流程）'),
                          'steps' => 0, 'errors' => 0, 'last_at' => '', 'last_msg' => ''];
        }
        $isErr = ($e['level'] ?? '') === 'error' || str_contains((string)($e['message'] ?? ''), '失败');
        if ($isErr) $out[$fid]['errors']++; else $out[$fid]['steps']++;
        if (strcmp((string)($e['time'] ?? ''), $out[$fid]['last_at']) > 0) {
            $out[$fid]['last_at'] = (string)($e['time'] ?? '');
            $out[$fid]['last_msg'] = mb_substr((string)($e['message'] ?? ''), 0, 80);
        }
    }
    usort($out, fn($a, $b) => strcmp($b['last_at'], $a['last_at']));
    return array_values($out);
}

// ─── 观测：token 消耗 ───────────────────────────────────

/** 某天逐小时 token 消耗（从 ai_usage 电表聚合；表不可用返回空数组）。 */
function ops_token_hourly(string $day = ''): array {
    $day = $day ?: date('Y-m-d');
    if (!function_exists('ai_usage_ensure') || !ai_usage_ensure()) return [];
    try {
        return \Database::query(
            "SELECT strftime('%H', at) AS h, COUNT(*) AS calls,
                    COALESCE(SUM(in_tokens),0) AS in_tokens, COALESCE(SUM(out_tokens),0) AS out_tokens,
                    COALESCE(SUM(cost),0) AS cost, COALESCE(SUM(CASE WHEN ok=0 THEN 1 ELSE 0 END),0) AS failed
             FROM ai_usage WHERE day = ? GROUP BY h ORDER BY h", [$day]);
    } catch (\Throwable $e) { return []; }
}

/** 近 N 天逐日 token 消耗（复用 AiBudget 的按天聚合）。 */
function ops_token_daily(int $days = 14): array {
    return function_exists('ai_spend_by') ? ai_spend_by('day', max(1, $days)) : [];
}

// ─── 观测：流量与转化（当日） ───────────────────────────

/** 当日流量与转化快照。查不动就给 0，不让简报发不出来。 */
function ops_traffic_today(): array {
    $out = ['uv' => 0, 'pv' => 0, 'leads' => 0, 'orders' => 0, 'revenue' => 0.0,
            'visit_to_lead' => 0.0, 'lead_to_order' => 0.0];
    try {
        $v = \Database::query("SELECT COUNT(DISTINCT uid) AS uv, COUNT(*) AS pv FROM events
                               WHERE event='page_view' AND created_at >= ?", [date('Y-m-d')]);
        $out['uv'] = (int)($v[0]['uv'] ?? 0);
        $out['pv'] = (int)($v[0]['pv'] ?? 0);
    } catch (\Throwable $e) {}
    // 当日线索（leads.csv / get_leads 的时间字段前 10 位是日期）
    try {
        if (function_exists('get_leads')) {
            foreach (get_leads() as $l) {
                if (substr((string)($l['时间'] ?? ''), 0, 10) === date('Y-m-d')) $out['leads']++;
            }
        }
    } catch (\Throwable $e) {}
    // 当日已支付订单与收入
    try {
        if (function_exists('shop_all_orders')) {
            foreach (shop_all_orders() as $o) {
                if (($o['status'] ?? '') !== 'paid') continue;
                if (strpos((string)($o['paid_at'] ?? $o['created_at'] ?? ''), date('Y-m-d')) === 0) {
                    $out['orders']++;
                    $out['revenue'] += (float)($o['amount'] ?? 0);
                }
            }
        }
    } catch (\Throwable $e) {}
    if ($out['uv'] > 0) $out['visit_to_lead'] = round($out['leads'] / $out['uv'] * 100, 1);
    if ($out['leads'] > 0) $out['lead_to_order'] = round($out['orders'] / $out['leads'] * 100, 1);
    return $out;
}

// ─── 简报：组装与发送 ───────────────────────────────────

/** 汇总一封简报所需的全部数据。 */
function ops_digest_build_data(): array {
    $running = ops_running_tasks();
    $todayFlows = ops_today_flow_stats();
    $tokens = function_exists('ai_spend') ? ai_spend() : ['calls'=>0,'tokens'=>0,'cost'=>0.0,'failed'=>0,'in_tokens'=>0,'out_tokens'=>0];
    return [
        'at'          => date('Y-m-d H:i'),
        'slot'        => '',
        'flows'       => function_exists('automation_get') ? automation_get() : [],
        'enabled'     => count(array_filter(function_exists('automation_get') ? automation_get() : [], fn($f) => !empty($f['enabled']))),
        'running'     => $running,
        'today_flows' => $todayFlows,
        'traffic'     => ops_traffic_today(),
        'tokens'      => $tokens,
        'hourly'      => ops_token_hourly(),
        'daily'       => ops_token_daily(7),
        'log_tail'    => array_slice(is_array(json_read(automation_log_file())) ? json_read(automation_log_file()) : [], -10),
    ];
}

/** 简报 HTML（邮件正文，内联样式）。 */
function ops_digest_build_html(array $d, string $slot = ''): string {
    $kn = fn($x) => number_format((float)$x);
    $period = $slot === 'e' ? '傍晚简报' : '清晨简报';
    $h = '<div style="font-family:-apple-system,\'PingFang SC\',\'Microsoft YaHei\',sans-serif;max-width:640px;margin:0 auto;color:#1f2937">';
    $h .= '<h2 style="font-size:18px;margin:0 0 4px">🤖 OpenFlow 运行简报 · ' . htmlspecialchars($period) . '</h2>';
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    if ($base === '' && function_exists('site_config_get')) $base = rtrim(site_config_get('site_url'), '/');
    $h .= '<p style="font-size:12px;color:#9ca3af;margin:0 0 16px">' . htmlspecialchars($d['at']) . ' 自动推送 · <a href="' . htmlspecialchars($base . '/xmp/ops-stats') . '">在后台查看详情</a></p>';

    // ① 执行基本情况
    $h .= '<h3 style="font-size:14px;margin:18px 0 8px">一、执行基本情况</h3>';
    $h .= '<table style="width:100%;border-collapse:collapse;font-size:13px"><tr>'
        . '<td style="padding:8px;background:#f1f5f9;border-radius:6px 0 0 6px">启用流程 <b>' . (int)$d['enabled'] . '</b> / ' . count($d['flows']) . '</td>'
        . '<td style="padding:8px;background:#f1f5f9">排队中（延时续流）<b>' . count($d['running']) . '</b></td>'
        . '<td style="padding:8px;background:#f1f5f9;border-radius:0 6px 6px 0">今日执行步数 <b>' . array_sum(array_map(fn($f) => (int)$f['steps'], $d['today_flows'])) . '</b> · 失败 <b style="color:' . (array_sum(array_map(fn($f) => (int)$f['errors'], $d['today_flows'])) > 0 ? '#dc2626' : 'inherit') . '">' . array_sum(array_map(fn($f) => (int)$f['errors'], $d['today_flows'])) . '</b></td>'
        . '</tr></table>';

    // ② 流量情况
    $t = $d['traffic'];
    $h .= '<h3 style="font-size:14px;margin:18px 0 8px">二、流量情况（今日）</h3>';
    $h .= '<table style="width:100%;border-collapse:collapse;font-size:13px"><tr>'
        . '<td style="padding:8px;background:#f1f5f9;border-radius:6px 0 0 6px">访客 UV <b>' . $kn($t['uv']) . '</b></td>'
        . '<td style="padding:8px;background:#f1f5f9">浏览 PV <b>' . $kn($t['pv']) . '</b></td>'
        . '<td style="padding:8px;background:#f1f5f9;border-radius:0 6px 6px 0">访访转化（UV→线索）<b>' . $t['visit_to_lead'] . '%</b></td>'
        . '</tr></table>';

    // ③ 转化情况
    $h .= '<h3 style="font-size:14px;margin:18px 0 8px">三、转化情况（今日）</h3>';
    $h .= '<table style="width:100%;border-collapse:collapse;font-size:13px"><tr>'
        . '<td style="padding:8px;background:#f1f5f9;border-radius:6px 0 0 6px">新增线索 <b>' . $kn($t['leads']) . '</b></td>'
        . '<td style="padding:8px;background:#f1f5f9">成交订单 <b>' . $kn($t['orders']) . '</b></td>'
        . '<td style="padding:8px;background:#f1f5f9;border-radius:0 6px 6px 0">收入 ¥<b>' . number_format($t['revenue'], 2) . '</b> · 线索→订单 <b>' . $t['lead_to_order'] . '%</b></td>'
        . '</tr></table>';

    // ④ 当日任务执行情况
    $h .= '<h3 style="font-size:14px;margin:18px 0 8px">四、当日任务执行</h3>';
    if (empty($d['today_flows'])) {
        $h .= '<p style="font-size:13px;color:#9ca3af;margin:0">今天还没有自动化流程被触发。</p>';
    } else {
        $h .= '<table style="width:100%;border-collapse:collapse;font-size:13px"><tr style="background:#f1f5f9"><th style="padding:8px;text-align:left">流程</th><th style="padding:8px;text-align:right">执行</th><th style="padding:8px;text-align:right">失败</th><th style="padding:8px;text-align:left">最近动作</th></tr>';
        foreach (array_slice($d['today_flows'], 0, 8) as $f) {
            $h .= '<tr><td style="padding:8px;border-bottom:1px solid #f1f5f9">' . htmlspecialchars($f['name']) . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right">' . (int)$f['steps'] . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;color:' . ($f['errors'] > 0 ? '#dc2626' : 'inherit') . '">' . (int)$f['errors'] . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #f1f5f9;font-size:12px;color:#6b7280">' . htmlspecialchars($f['last_msg']) . '</td></tr>';
        }
        $h .= '</table>';
    }

    // ⑤ token 消耗（当日逐小时 + 近 7 天逐日）
    $tokens = is_array($d['tokens'] ?? null) ? $d['tokens'] : [];
    $cur = function_exists('ai_budget_settings') ? ai_budget_settings()['currency'] : '¥';
    $h .= '<h3 style="font-size:14px;margin:18px 0 8px">五、Token 消耗</h3>';
    $h .= '<p style="font-size:13px;margin:0 0 8px">今日调用 <b>' . $kn($tokens['calls'] ?? 0) . '</b> 次 · '
        . '<b>' . $kn($tokens['tokens'] ?? 0) . '</b> token · 花费 <b>' . $cur . number_format((float)($tokens['cost'] ?? 0), 4) . '</b>'
        . (($tokens['failed'] ?? 0) > 0 ? ' · <span style="color:#dc2626">失败 ' . $kn($tokens['failed']) . '</span>' : '') . '</p>';
    if (!empty($d['hourly'])) {
        $h .= '<table style="width:100%;border-collapse:collapse;font-size:12px"><tr style="background:#f1f5f9"><th style="padding:6px;text-align:left">小时</th><th style="padding:6px;text-align:right">调用</th><th style="padding:6px;text-align:right">token</th><th style="padding:6px;text-align:right">花费</th></tr>';
        foreach ($d['hourly'] as $r) {
            $h .= '<tr><td style="padding:6px;border-bottom:1px solid #f1f5f9">' . htmlspecialchars((string)$r['h']) . ':00</td>'
                . '<td style="padding:6px;border-bottom:1px solid #f1f5f9;text-align:right">' . $kn($r['calls']) . '</td>'
                . '<td style="padding:6px;border-bottom:1px solid #f1f5f9;text-align:right">' . $kn((int)$r['in_tokens'] + (int)$r['out_tokens']) . '</td>'
                . '<td style="padding:6px;border-bottom:1px solid #f1f5f9;text-align:right">' . $cur . number_format((float)$r['cost'], 4) . '</td></tr>';
        }
        $h .= '</table>';
    }
    if (!empty($d['daily'])) {
        $h .= '<p style="font-size:12px;color:#6b7280;margin:10px 0 4px">近 7 天逐日：</p>';
        $h .= '<table style="width:100%;border-collapse:collapse;font-size:12px"><tr style="background:#f1f5f9"><th style="padding:6px;text-align:left">日期</th><th style="padding:6px;text-align:right">调用</th><th style="padding:6px;text-align:right">token</th><th style="padding:6px;text-align:right">花费</th></tr>';
        foreach ($d['daily'] as $r) {
            $h .= '<tr><td style="padding:6px;border-bottom:1px solid #f1f5f9">' . htmlspecialchars((string)$r['k']) . '</td>'
                . '<td style="padding:6px;border-bottom:1px solid #f1f5f9;text-align:right">' . $kn($r['calls']) . '</td>'
                . '<td style="padding:6px;border-bottom:1px solid #f1f5f9;text-align:right">' . $kn((int)$r['in_tokens'] + (int)$r['out_tokens']) . '</td>'
                . '<td style="padding:6px;border-bottom:1px solid #f1f5f9;text-align:right">' . $cur . number_format((float)$r['cost'], 4) . '</td></tr>';
        }
        $h .= '</table>';
    }
    $h .= '<p style="font-size:11px;color:#9ca3af;margin:20px 0 0">由 OpenFlow 自动推送 · 发送时点与收件人可在后台「运行与 Token 统计」页调整。</p></div>';
    return $h;
}

function ops_digest_subject(string $slot): string {
    $label = $slot === 'e' ? '傍晚' : '清晨';
    return '【运行简报·' . $label . '】' . date('m月d日') . ' 自动化执行 · 流量转化 · Token 消耗';
}

/** 立即发送一封（手动触发用）。返回 ['ok'=>bool, 'sent'=>int, 'error'=>string] */
function ops_digest_send(string $slot = 'm'): array {
    $cfg = ops_digest_settings();
    if ($cfg['recipients'] === []) return ['ok' => false, 'sent' => 0, 'error' => '未配置收件人邮箱'];
    require_once __DIR__ . '/MailChannel.php';
    $d = ops_digest_build_data();
    $d['slot'] = $slot;
    $html = ops_digest_build_html($d, $slot);
    $sent = 0;
    $err = '';
    foreach ($cfg['recipients'] as $to) {
        try { if (mail_send($to, ops_digest_subject($slot), $html)) $sent++; }
        catch (\Throwable $e) { $err = $e->getMessage(); }
    }
    if ($sent > 0) {
        $mark = json_read(DATA_DIR . '/ops-digest-sent.json');
        if (!is_array($mark)) $mark = [];
        $mark[$slot . ':' . date('Y-m-d')] = time();
        json_write(DATA_DIR . '/ops-digest-sent.json', array_slice($mark, -200));
        return ['ok' => true, 'sent' => $sent, 'error' => ''];
    }
    return ['ok' => false, 'sent' => 0, 'error' => $err !== '' ? $err : '邮件发送失败（检查邮件渠道配置）'];
}

/**
 * cron 入口：每天清晨 / 傍晚各发一封，当天发过就不重发。
 * 清晨简报窗口：morning_hour 点（含）之后、evening_hour 点之前都算清晨档 ——
 * cron 若停摆几小时，恢复后仍能补上当天没发的那一档。
 */
function ops_digest_maybe_send(): array {
    $cfg = ops_digest_settings();
    if ($cfg['recipients'] === []) return ['status' => 'skipped', 'detail' => '未配置收件人'];
    $hour = (int)date('G');
    $slot = '';
    if ($hour >= $cfg['morning_hour'] && $hour < $cfg['evening_hour']) $slot = 'm';
    elseif ($hour >= $cfg['evening_hour']) $slot = 'e';
    if ($slot === '') return ['status' => 'skipped', 'detail' => '未到发送时点'];

    $mark = json_read(DATA_DIR . '/ops-digest-sent.json');
    if (!is_array($mark)) $mark = [];
    $key = $slot . ':' . date('Y-m-d');
    if (!empty($mark[$key])) return ['status' => 'already_sent', 'slot' => $slot];

    $r = ops_digest_send($slot);
    return $r['ok'] ? ['status' => 'sent', 'slot' => $slot, 'sent' => $r['sent']]
                    : ['status' => 'error', 'slot' => $slot, 'detail' => $r['error']];
}
