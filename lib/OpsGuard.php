<?php
/**
 * OpsGuard —— 平台运营的渐进自治护栏（AUDIT-05 / BACKLOG T2-8）
 *
 * 【为什么】T1-12 让 Agent 会给选品建议、会做质量初判，但"谁能自动生效、
 * 什么必须人拍板、出了异常怎么办"没有规矩。平台运营一旦放权，错一次就是
 * 用户看到不该看到的东西、或者把好创作者误伤下架。
 *
 * 【三件事】
 *   ① 审核关口：哪些运营动作可自动、哪些必须人工（与 T2-4 同一套分级思想）
 *   ② 异常下架：检测明显异常（退款率飙升/大量投诉/交付失败）→ 建议下架，
 *      但**默认只建议不执行**，且永远保留申诉记录
 *   ③ 可解释排序：给出排序因子明细，让创作者知道"我为什么排在这"
 */

if (!function_exists('opsguard_settings')) {

    function opsguard_settings(): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $o = is_array($s['ops_guard'] ?? null) ? $s['ops_guard'] : [];
        return array_merge([
            'auto_approve' => false,      // 质量初判 pass 的新品是否自动上架
            'auto_takedown' => false,     // 异常是否自动下架（默认否：只建议）
            'refund_rate_limit' => 30,    // 退款率超过 % 视为异常
            'complaint_limit' => 5,       // 投诉数超过即异常
        ], $o);
    }

    function opsguard_save(array $d): array {
        $s = function_exists('json_read') ? json_read(DATA_DIR . '/settings.json') : [];
        $s['ops_guard'] = [
            'auto_approve' => !empty($d['auto_approve']),
            'auto_takedown' => !empty($d['auto_takedown']),
            'refund_rate_limit' => max(1, min(100, (int)($d['refund_rate_limit'] ?? 30))),
            'complaint_limit' => max(1, (int)($d['complaint_limit'] ?? 5)),
        ];
        if (function_exists('json_write')) json_write(DATA_DIR . '/settings.json', $s);
        return $s['ops_guard'];
    }

    /**
     * ① 审核关口：一个运营动作能否自动生效。
     * $verdict 来自 platops_review 的 pass/revise/reject。
     */
    function opsguard_can_auto_approve(string $verdict, ?array $cfg = null): array {
        $cfg = $cfg ?? opsguard_settings();
        if ($verdict !== 'pass') {
            return ['allow' => false, 'reason' => '质量初判非「建议通过」，必须人工确认'];
        }
        if (empty($cfg['auto_approve'])) {
            return ['allow' => false, 'reason' => '未开启自动上架，交人确认'];
        }
        return ['allow' => true, 'reason' => '质量初判通过且已开启自动上架'];
    }

    /**
     * ② 异常检测：返回异常项与处置建议。
     * $m: ['sales'=>int,'refunds'=>int,'complaints'=>int,'delivery_failures'=>int]
     * 返回 ['abnormal'=>bool,'issues'=>[],'suggest'=>'none|watch|takedown','auto'=>bool]
     */
    function opsguard_detect(array $m, ?array $cfg = null): array {
        $cfg = $cfg ?? opsguard_settings();
        $issues = [];
        $sales = max(0, (int)($m['sales'] ?? 0));
        $refunds = max(0, (int)($m['refunds'] ?? 0));
        $complaints = max(0, (int)($m['complaints'] ?? 0));
        $fails = max(0, (int)($m['delivery_failures'] ?? 0));

        // 样本太少不下结论——避免误伤新创作者
        $enough = $sales >= 5;
        $rate = $sales > 0 ? (int)round($refunds / $sales * 100) : 0;
        if ($enough && $rate >= (int)$cfg['refund_rate_limit']) $issues[] = "退款率 {$rate}%（超过 {$cfg['refund_rate_limit']}%）";
        if ($complaints >= (int)$cfg['complaint_limit']) $issues[] = "投诉 {$complaints} 起";
        if ($fails >= 3) $issues[] = "交付失败 {$fails} 次";

        $suggest = 'none';
        if ($issues) $suggest = (count($issues) >= 2) ? 'takedown' : 'watch';

        return [
            'abnormal' => !empty($issues),
            'issues' => $issues,
            'suggest' => $suggest,
            // 只有开了自动下架、且建议是下架，才允许自动执行
            'auto' => ($suggest === 'takedown') && !empty($cfg['auto_takedown']),
            'sample_ok' => $enough,
        ];
    }

    /** 处置记录（含申诉入口所需信息）——永远留痕，便于纠错。 */
    function opsguard_log_file(): string { return DATA_DIR . '/ecosystem/ops-actions.json'; }
    function opsguard_log(string $productId, string $action, string $reason, string $operator = 'system'): array {
        $all = function_exists('json_read') ? json_read(opsguard_log_file()) : [];
        if (!is_array($all)) $all = [];
        $row = ['id' => 'ops_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4),
                'product_id' => $productId, 'action' => $action, 'reason' => $reason,
                'operator' => $operator, 'appealed' => false, 'at' => date('Y-m-d H:i:s')];
        $all[] = $row;
        if (count($all) > 500) $all = array_slice($all, -500);
        if (function_exists('json_write')) { @mkdir(dirname(opsguard_log_file()), 0755, true); json_write(opsguard_log_file(), $all); }
        return $row;
    }
    function opsguard_logs(string $productId = '', int $limit = 100): array {
        $all = function_exists('json_read') ? json_read(opsguard_log_file()) : [];
        if (!is_array($all)) return [];
        if ($productId !== '') $all = array_values(array_filter($all, fn($r) => ($r['product_id'] ?? '') === $productId));
        return array_slice(array_reverse($all), 0, $limit);
    }
    /** 创作者申诉：只标记，不自动恢复——人来判。 */
    function opsguard_appeal(string $logId, string $note = ''): bool {
        $all = function_exists('json_read') ? json_read(opsguard_log_file()) : [];
        if (!is_array($all)) return false;
        $hit = false;
        foreach ($all as &$r) if (($r['id'] ?? '') === $logId) { $r['appealed'] = true; $r['appeal_note'] = mb_substr($note, 0, 300); $hit = true; break; }
        unset($r);
        if ($hit && function_exists('json_write')) json_write(opsguard_log_file(), $all);
        return $hit;
    }

    /**
     * ③ 可解释排序：把排序因子摊开，让创作者知道自己为什么排这个位置。
     */
    function opsguard_explain_rank(array $product, array $factors): array {
        $lines = []; $total = 0;
        foreach ($factors as $name => $val) {
            $v = (float)$val;
            $total += $v;
            $lines[] = ['factor' => (string)$name, 'value' => round($v, 1)];
        }
        usort($lines, fn($a, $b) => abs($b['value']) <=> abs($a['value']));
        return [
            'product_id' => (string)($product['id'] ?? ''),
            'total' => round($total, 1),
            'factors' => $lines,
            'top_reason' => $lines ? ($lines[0]['factor'] . '（' . ($lines[0]['value'] >= 0 ? '+' : '') . $lines[0]['value'] . '）') : '',
        ];
    }
}
