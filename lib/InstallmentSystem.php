<?php
/**
 * InstallmentSystem —— 订金/尾款结构化 + 简单合同电子签（AUDIT-04 / BACKLOG T2-6）
 *
 * 【为什么】项目制生意的刚需：先收订金、交付后收尾款。此前只能靠"待办"凑，
 * 金额、进度、该催谁都在人脑里。本模块把分期做成结构化数据，并给一份轻量
 * 合同确认（不是法律级电子签，但足以留下"谁在什么时候确认了什么"的可核验痕迹）。
 *
 * 【模型】一张收款单可拆多期：[{name,amount,due,status,paid_at}]
 *   status: pending(未付) / paid(已付) / void(作废)
 * 合同确认：记录确认人、时间、内容哈希与 IP —— 内容改了哈希就对不上，能发现。
 */

if (!function_exists('inst_plan_normalize')) {

    /**
     * 规范化分期计划：金额取正、比例自动补足、期数命名。
     * $total 总额；$parts 形如 [['name'=>'订金','ratio'=>0.3], ['name'=>'尾款','ratio'=>0.7]]
     * 或 [['name'=>'首付','amount'=>3000], ...]
     */
    function inst_plan_normalize(float $total, array $parts): array {
        $total = round(max(0, $total), 2);
        $out = []; $sum = 0.0;
        foreach ($parts as $i => $p) {
            $name = trim((string)($p['name'] ?? ('第' . ($i + 1) . '期')));
            if (isset($p['amount'])) $amt = round(max(0, (float)$p['amount']), 2);
            else $amt = round($total * max(0, min(1, (float)($p['ratio'] ?? 0))), 2);
            $out[] = [
                'id' => 'inst' . ($i + 1),
                'name' => $name !== '' ? $name : ('第' . ($i + 1) . '期'),
                'amount' => $amt,
                'due' => (string)($p['due'] ?? ''),
                'status' => in_array(($p['status'] ?? 'pending'), ['pending','paid','void'], true) ? ($p['status'] ?? 'pending') : 'pending',
                'paid_at' => (string)($p['paid_at'] ?? ''),
            ];
            $sum += $amt;
        }
        // 尾差补到最后一期（避免比例四舍五入后总额对不上）
        $diff = round($total - $sum, 2);
        if ($out && abs($diff) >= 0.01) {
            $last = count($out) - 1;
            $out[$last]['amount'] = round($out[$last]['amount'] + $diff, 2);
        }
        return $out;
    }

    /** 常用模板。 */
    function inst_templates(): array {
        return [
            'half'   => ['label' => '五五分（订金/尾款）', 'parts' => [['name'=>'订金','ratio'=>0.5],['name'=>'尾款','ratio'=>0.5]]],
            'three'  => ['label' => '三期（3/4/3）',      'parts' => [['name'=>'订金','ratio'=>0.3],['name'=>'中期款','ratio'=>0.4],['name'=>'尾款','ratio'=>0.3]]],
            'deposit'=> ['label' => '小订金（2/8）',      'parts' => [['name'=>'订金','ratio'=>0.2],['name'=>'尾款','ratio'=>0.8]]],
        ];
    }

    /** 汇总：已收/未收/进度/下一期。 */
    function inst_summary(array $plan): array {
        $paid = 0.0; $pending = 0.0; $next = null;
        foreach ($plan as $p) {
            if (($p['status'] ?? '') === 'void') continue;
            if (($p['status'] ?? '') === 'paid') { $paid += (float)$p['amount']; }
            else {
                $pending += (float)$p['amount'];
                if ($next === null) $next = $p;
            }
        }
        $total = round($paid + $pending, 2);
        return [
            'total' => $total, 'paid' => round($paid, 2), 'pending' => round($pending, 2),
            'pct' => $total > 0 ? (int)round($paid / $total * 100) : 0,
            'next' => $next, 'settled' => $pending <= 0.001 && $total > 0,
        ];
    }

    /** 标记某期已付（幂等：已付再标不变）。 */
    function inst_mark_paid(array $plan, string $instId, ?string $at = null): array {
        foreach ($plan as &$p) {
            if (($p['id'] ?? '') === $instId && ($p['status'] ?? '') !== 'paid') {
                $p['status'] = 'paid';
                $p['paid_at'] = $at ?: date('Y-m-d H:i:s');
            }
        }
        unset($p);
        return $plan;
    }

    /** 逾期未付的期次（用于催款提醒）。 */
    function inst_overdue(array $plan, ?string $today = null): array {
        $today = $today ?: date('Y-m-d');
        $out = [];
        foreach ($plan as $p) {
            if (($p['status'] ?? '') !== 'pending') continue;
            $due = trim((string)($p['due'] ?? ''));
            if ($due !== '' && $due < $today) $out[] = $p;
        }
        return $out;
    }

    /* ─────────── 轻量合同确认 ─────────── */

    /** 合同内容指纹：内容一改，指纹就变，能发现被改过。 */
    function contract_hash(string $content): string {
        return hash('sha256', trim(preg_replace('/\s+/u', ' ', $content)));
    }

    /**
     * 记录一次确认（谁、何时、确认了什么内容）。
     * 返回确认凭据（可存进订单，用于日后核验）。
     */
    function contract_sign(string $content, string $signer, string $ip = ''): array {
        return [
            'signer' => trim($signer),
            'signed_at' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'hash' => contract_hash($content),
            'len' => mb_strlen($content),
        ];
    }

    /**
     * 核验：把当前合同内容与当初的凭据比对。
     * 返回 ['ok'=>bool,'reason'=>string]
     */
    function contract_verify(string $content, array $record): array {
        if (empty($record['hash'])) return ['ok' => false, 'reason' => '没有签署记录'];
        if (contract_hash($content) !== $record['hash']) return ['ok' => false, 'reason' => '合同内容与签署时不一致（被修改过）'];
        return ['ok' => true, 'reason' => '内容与 ' . ($record['signer'] ?? '') . ' 于 ' . ($record['signed_at'] ?? '') . ' 确认的一致'];
    }
}
