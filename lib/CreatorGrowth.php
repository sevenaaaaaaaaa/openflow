<?php
/**
 * CreatorGrowth —— 创作者增长后台（AUDIT-05 创新一 / BACKLOG T1-11）
 *
 * 【为什么】平台对创作者现在只是"收银台 + 提现口"：能看到钱，得不到任何
 * "怎么把货卖得更好"的帮助。AUDIT-05 的核心机会是把整台增长引擎**多租户化**——
 * 让每个上架创作者都拿到属于他自己买家的画像与增长建议。
 * 这是"给每个入驻者配一支 AI 增长团队"的第一步。
 *
 * 【本版范围】v1：从订单切出「我的买家」画像（人数/复购/客单/来源），
 * 再按规则产出 3 条本周增长动作（选题 / 定价 / 复购），AI 可选润色。
 * 纯函数内核，订单可注入，便于测试。
 */

if (!function_exists('creator_stats')) {

    /**
     * 我的买家画像切片。$orders 可注入（否则按 author 查订单）。
     * 返回 buyers/orders/revenue/repeat_buyers/avg_order/top_source/last_sale_days
     */
    function creator_stats(string $creatorId, ?array $orders = null): array {
        if ($orders === null) {
            $orders = [];
            try {
                if (class_exists('Database')) {
                    $orders = Database::query("SELECT * FROM orders WHERE author = ? AND status = 'paid'", [$creatorId]);
                }
            } catch (\Throwable $e) { $orders = []; }
        }
        $byBuyer = []; $revenue = 0.0; $sources = []; $lastAt = '';
        foreach ($orders as $o) {
            $amt = (float)($o['amount'] ?? 0);
            $revenue += $amt;
            $b = (string)($o['member_id'] ?? ($o['email'] ?? ''));
            if ($b !== '') { $byBuyer[$b] = ($byBuyer[$b] ?? 0) + 1; }
            $src = trim((string)($o['source'] ?? ''));
            if ($src !== '') $sources[$src] = ($sources[$src] ?? 0) + 1;
            $at = (string)($o['created_at'] ?? ($o['paid_at'] ?? ''));
            if ($at > $lastAt) $lastAt = $at;
        }
        $repeat = count(array_filter($byBuyer, fn($n) => $n > 1));
        $cnt = count($orders);
        arsort($sources);
        $days = 9999;
        if ($lastAt !== '') { $t = strtotime($lastAt); if ($t) $days = max(0, (int)floor((time() - $t) / 86400)); }

        return [
            'buyers'        => count($byBuyer),
            'orders'        => $cnt,
            'revenue'       => round($revenue, 2),
            'repeat_buyers' => $repeat,
            'repeat_rate'   => count($byBuyer) ? (int)round($repeat / count($byBuyer) * 100) : 0,
            'avg_order'     => $cnt ? round($revenue / $cnt, 2) : 0.0,
            'top_source'    => (string)(array_key_first($sources) ?? ''),
            'last_sale_days'=> $days,
        ];
    }

    /**
     * 本周增长动作（纯规则，可解释）。返回最多 3 条：
     * ['kind','title','why','cta']
     */
    function creator_actions(array $s, array $ctx = []): array {
        $out = [];
        $hasSales = ($s['orders'] ?? 0) > 0;

        // 1) 复购：有买家但复购低
        if (($s['buyers'] ?? 0) >= 3 && ($s['repeat_rate'] ?? 0) < 30) {
            $out[] = ['kind' => 'repurchase', 'title' => '给老买家做一次复购触达',
                'why' => "你有 {$s['buyers']} 个买家，但只有 {$s['repeat_rate']}% 复购过——这批人最容易再买。",
                'cta' => '建复购触达'];
        }
        // 2) 定价：客单价偏低
        if ($hasSales && ($s['avg_order'] ?? 0) > 0 && ($s['avg_order'] ?? 0) < 100) {
            $out[] = ['kind' => 'pricing', 'title' => '试一版更高客单的组合包',
                'why' => '当前客单价 ¥' . number_format((float)$s['avg_order'], 2) . '，把关联内容打包能直接抬客单，不用多找一个人。',
                'cta' => '去建组合包'];
        }
        // 3) 沉默：很久没出单
        if ($hasSales && ($s['last_sale_days'] ?? 0) >= 14 && ($s['last_sale_days'] ?? 0) < 9999) {
            $out[] = ['kind' => 'revive', 'title' => '已经 ' . $s['last_sale_days'] . ' 天没出单，发一篇带货内容',
                'why' => '出单停滞通常不是产品问题，是曝光断了——一篇讲清「谁该买、解决什么」的内容最快见效。',
                'cta' => '写一篇'];
        }
        // 4) 冷启动：还没卖出
        if (!$hasSales) {
            $out[] = ['kind' => 'launch', 'title' => '先让第一批人看到你的东西',
                'why' => '还没有成交记录。先写一篇「它解决什么问题」的介绍内容，比继续打磨产品更快拿到反馈。',
                'cta' => '写介绍内容'];
        }
        // 5) 选题：有来源数据就顺着强来源做内容
        if (!empty($s['top_source'])) {
            $out[] = ['kind' => 'content', 'title' => "顺着「{$s['top_source']}」再做一篇",
                'why' => "你的买家最多来自「{$s['top_source']}」，在这个来源加内容的转化确定性最高。",
                'cta' => '去选题'];
        }
        // 6) 兜底：总要有一条能做的
        if (!$out) {
            $out[] = ['kind' => 'content', 'title' => '本周写一篇能带货的内容',
                'why' => '数据还太少，先把「谁该买、为什么值」讲清楚，后面才有可优化的东西。',
                'cta' => '去选题'];
        }
        return array_slice($out, 0, 3);
    }

    /** AI 润色（可选、可注入）。失败/未配 → 原样。 */
    function creator_actions_polish(array $actions, array $s): array {
        try {
            if (isset($GLOBALS['CREATOR_AI_FN']) && is_callable($GLOBALS['CREATOR_AI_FN'])) {
                $r = call_user_func($GLOBALS['CREATOR_AI_FN'], $actions, $s);
                return is_array($r) && $r ? $r : $actions;
            }
        } catch (\Throwable $e) {}
        return $actions;
    }

    /** 一次拿全：画像 + 动作。 */
    function creator_dashboard(string $creatorId, ?array $orders = null): array {
        $s = creator_stats($creatorId, $orders);
        return ['stats' => $s, 'actions' => creator_actions_polish(creator_actions($s), $s)];
    }
}
