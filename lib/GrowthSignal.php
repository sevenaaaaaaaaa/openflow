<?php
/**
 * GrowthSignal —— 传出神经第一环：成交反哺 CDP
 *
 * 【它解决什么】事件总线(flow_handle)长期"只进不出"：purchase / crm_stage_change(won)
 * 发上总线，却没人把"这单真成交、来自哪个来源/分群、值多少钱"写回 CDP。
 * 于是 CDP 只知道"谁访问多"，不知道"谁真值钱"——AUDIT-04 创新一点破的偏差。
 *
 * 【它做什么】成交发生时（旁路订阅，全程 try/catch，绝不影响成交本身）：
 *   1. 给 CDP 客户累加 LTV（cdp_add_ltv）——这条画像"真金白银"值多少；
 *   2. 写结构化成交信号到客户 props（成交次数/累计金额/最近来源/最近分群/时间）；
 *   3. 打成交来源/分群标签，便于分群回看；
 *   4. 追加到"成交真相账本"data/growth/conversions.json——按【来源】【分群】聚合
 *      成交次数与金额。这份账本回答"哪个来源/分群真的转化成收入"（而非只是访问多），
 *      是喂给 MA/Content 决策、以及 P0-3 中枢大脑的"真相源"。
 *
 * 账本聚合逻辑(growth_conv_apply / growth_conversion_truth)是纯函数，可独立测试。
 * 数据：data/growth/conversions.json（运行时生成）。
 */

if (!function_exists('growth_conv_ledger_file')) {
    function growth_conv_ledger_file(): string {
        return DATA_DIR . '/growth/conversions.json';
    }
}

if (!function_exists('growth_conv_blank')) {
    function growth_conv_blank(): array {
        return ['sources' => [], 'segments' => [], 'total' => ['count' => 0, 'revenue' => 0.0], 'updated_at' => ''];
    }
}

if (!function_exists('growth_conv_apply')) {
    /**
     * 纯函数：把一次成交并入账本，返回更新后的账本。不做 IO。
     * 空的来源/分群归一到占位键，保证"未归因"也可见、不丢数。
     */
    function growth_conv_apply(array $ledger, string $source, string $segment, float $amount, string $now = ''): array {
        if (empty($ledger['sources']))  $ledger = growth_conv_blank();
        $source  = trim($source)  !== '' ? trim($source)  : '(未归因来源)';
        $segment = trim($segment) !== '' ? trim($segment) : '(未分群)';
        $amount  = max(0.0, $amount);

        foreach ([['sources', $source], ['segments', $segment]] as [$dim, $key]) {
            $row = $ledger[$dim][$key] ?? ['count' => 0, 'revenue' => 0.0];
            $row['count']   = (int)$row['count'] + 1;
            $row['revenue'] = round((float)$row['revenue'] + $amount, 2);
            $ledger[$dim][$key] = $row;
        }
        $ledger['total']['count']   = (int)($ledger['total']['count'] ?? 0) + 1;
        $ledger['total']['revenue'] = round((float)($ledger['total']['revenue'] ?? 0) + $amount, 2);
        $ledger['updated_at'] = $now !== '' ? $now : date('Y-m-d H:i:s');
        return $ledger;
    }
}

if (!function_exists('growth_conv_read')) {
    function growth_conv_read(): array {
        $f = growth_conv_ledger_file();
        if (!is_file($f)) return growth_conv_blank();
        $d = json_decode((string)file_get_contents($f), true);
        if (is_array($d) && (isset($d['sources']) || isset($d['total']))) return $d;
        return growth_conv_blank();
    }
}

if (!function_exists('growth_conversion_truth')) {
    /**
     * 读账本并排名：每个维度按收入降序，附均单价。供 UI / 大脑消费。
     * 这就是"成交真相"——谁真的转化成收入，一目了然。
     */
    function growth_conversion_truth(?array $ledger = null): array {
        $ledger = $ledger ?? growth_conv_read();
        $rank = function (array $rows): array {
            $out = [];
            foreach ($rows as $key => $r) {
                $cnt = (int)($r['count'] ?? 0);
                $rev = (float)($r['revenue'] ?? 0);
                $out[] = ['key' => $key, 'count' => $cnt, 'revenue' => round($rev, 2), 'avg' => $cnt ? round($rev / $cnt, 2) : 0.0];
            }
            usort($out, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
            return $out;
        };
        return [
            'sources'    => $rank($ledger['sources'] ?? []),
            'segments'   => $rank($ledger['segments'] ?? []),
            'total'      => $ledger['total'] ?? ['count' => 0, 'revenue' => 0.0],
            'updated_at' => $ledger['updated_at'] ?? '',
        ];
    }
}

if (!function_exists('growth_signal_conversion')) {
    /**
     * 成交反哺入口（由 flow_handle 旁路调用）。
     * $ctx: ['email','member_id','uid','amount','source','segment','label','customer'(可选,已解析的CDP客户)]
     * 返回小结；任何异常吞掉，返回 ['ok'=>false]，绝不冒泡。
     */
    function growth_signal_conversion(array $ctx): array {
        try {
            $amount  = (float)($ctx['amount'] ?? 0);
            $source  = (string)($ctx['source'] ?? '');
            $segment = (string)($ctx['segment'] ?? '');

            // 1) 解析 CDP 客户（优先用调用方已解析的，省一次查询）
            $customer = $ctx['customer'] ?? null;
            if (!$customer && function_exists('cdp_find')) {
                $customer = cdp_find((string)($ctx['email'] ?? ''), (string)($ctx['member_id'] ?? ''), (string)($ctx['uid'] ?? ''));
            }

            if ($customer && !empty($customer['id'])) {
                $cid = (string)$customer['id'];
                // 来源缺省回落到获客渠道
                if ($source === '') $source = (string)($customer['channel'] ?? '');
                // 2) LTV：真金白银
                if ($amount > 0 && function_exists('cdp_add_ltv')) cdp_add_ltv($cid, $amount);
                // 3) 结构化成交信号写入 props
                if (function_exists('cdp_set_prop')) {
                    $prev = [];
                    if (function_exists('cdp_get_by_id')) {
                        $c = cdp_get_by_id($cid);
                        $prev = json_decode($c['props'] ?? '{}', true) ?: [];
                    }
                    $wonCount = (int)($prev['won_count'] ?? 0) + 1;
                    $wonTotal = round((float)($prev['won_value_total'] ?? 0) + $amount, 2);
                    cdp_set_prop($cid, 'won_count', $wonCount);
                    cdp_set_prop($cid, 'won_value_total', $wonTotal);
                    if ($source  !== '') cdp_set_prop($cid, 'last_won_source', $source);
                    if ($segment !== '') cdp_set_prop($cid, 'last_won_segment', $segment);
                    cdp_set_prop($cid, 'last_won_at', date('Y-m-d H:i:s'));
                }
                // 4) 成交来源/分群标签
                if (function_exists('cdp_add_tag')) {
                    if ($source  !== '') cdp_add_tag($cid, '成交来源:' . $source);
                    if ($segment !== '') cdp_add_tag($cid, '成交分群:' . $segment);
                }
            }

            // 5) 成交真相账本（即使没有 CDP 客户也记，保证总量真实）
            $ledger = growth_conv_read();
            $ledger = growth_conv_apply($ledger, $source, $segment, $amount);
            $f = growth_conv_ledger_file();
            @mkdir(dirname($f), 0777, true);
            @file_put_contents($f, json_encode($ledger, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return ['ok' => true, 'source' => $source, 'segment' => $segment, 'amount' => $amount];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
