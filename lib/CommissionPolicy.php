<?php
/**
 * CommissionPolicy —— 统一分成/结算政策层（AUDIT-05 / BACKLOG T0-5）
 *
 * 【为什么】平台抽成散在三套系统里各写各的、还不一致：
 *   平台费 0.1 在 Commerce×2 / Shop×1 硬编码；分销佣金 Mall/Shop 20% vs Commerce 30%；
 *   min_withdraw 埋在 Shop。一个真想经营平台的人连"我怎么抽成"都没处设。
 * 本层把平台经济学收成一处可配策略，各交易系统引用；默认值沿用现状，
 * 不配置则行为完全不变。
 *
 * 存储：data/commission.json（缺省用内置默认）。
 * 约定：platform_fee_rate 为分数(0.10=10%)；distribution_rate 为百分数(20=20%)。
 */

if (!function_exists('commission_policy')) {

    function commission_policy_file(): string { return DATA_DIR . '/commission.json'; }

    function commission_policy_defaults(): array {
        return [
            'platform_fee_rate' => 0.10,   // 平台抽成（覆盖支付手续费等）——分数
            'distribution_rate' => 20,     // 默认分销/联盟佣金——百分数
            'min_withdraw'      => 100,    // 最低提现额
        ];
    }

    /** 当前策略（配置覆盖默认，并做边界钳制）。 */
    function commission_policy(): array {
        $cfg = function_exists('json_read') ? json_read(commission_policy_file()) : [];
        $p = array_merge(commission_policy_defaults(), is_array($cfg) ? $cfg : []);
        // 边界：费率 0~0.9、分销 0~100、提现 >=0
        $p['platform_fee_rate'] = min(0.9, max(0.0, (float)$p['platform_fee_rate']));
        $p['distribution_rate'] = min(100.0, max(0.0, (float)$p['distribution_rate']));
        $p['min_withdraw']      = max(0.0, (float)$p['min_withdraw']);
        return $p;
    }

    function commission_policy_save(array $data): array {
        $p = commission_policy_defaults();
        if (isset($data['platform_fee_rate'])) $p['platform_fee_rate'] = min(0.9, max(0.0, (float)$data['platform_fee_rate']));
        if (isset($data['distribution_rate'])) $p['distribution_rate'] = min(100.0, max(0.0, (float)$data['distribution_rate']));
        if (isset($data['min_withdraw']))      $p['min_withdraw']      = max(0.0, (float)$data['min_withdraw']);
        $f = commission_policy_file();
        @mkdir(dirname($f), 0755, true);
        if (function_exists('json_write')) json_write($f, $p);
        else @file_put_contents($f, json_encode($p, JSON_UNESCAPED_UNICODE));
        return $p;
    }

    /** 平台费（分数制）。各系统统一用它，别再写 round($x*0.1,2)。 */
    function commission_platform_fee(float $amount): float {
        return round(max(0.0, $amount) * commission_policy()['platform_fee_rate'], 2);
    }

    /** 平台费率（分数）——给需要"率"而非"额"的旧接口。 */
    function commission_platform_rate(): float { return commission_policy()['platform_fee_rate']; }

    /** 默认分销佣金率（百分数）。 */
    function commission_distribution_rate(): float { return commission_policy()['distribution_rate']; }

    function commission_min_withdraw(): float { return commission_policy()['min_withdraw']; }

    /**
     * 统一分账：给定成交额、是否有推广人、可选的单品分销率(百分数)，
     * 算出 平台费 / 分销佣金 / 作者应得。三套系统共用同一套口径。
     * author_amount = amount - platform_fee - commission（钳到 >=0）。
     */
    function commission_split(float $amount, bool $hasReferrer = false, ?float $distRatePct = null): array {
        $amount = max(0.0, $amount);
        $platformFee = commission_platform_fee($amount);
        $rate = $distRatePct !== null ? max(0.0, min(100.0, $distRatePct)) : commission_distribution_rate();
        $commission = $hasReferrer ? round($amount * $rate / 100, 2) : 0.0;
        $author = round(max(0.0, $amount - $platformFee - $commission), 2);
        return [
            'amount' => round($amount, 2),
            'platform_fee' => $platformFee,
            'commission' => $commission,
            'commission_rate' => $rate,
            'author_amount' => $author,
        ];
    }
}
