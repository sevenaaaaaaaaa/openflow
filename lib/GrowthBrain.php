<?php
/**
 * GrowthBrain —— 中枢 NBA 提议器（大脑胚胎，AUDIT-07 P0-3）
 *
 * 【它是什么】前六个模块是器官，事件总线是传入神经，P0-2 接上了第一根传出神经。
 * 本类是那颗一直缺席的"大脑"的第一形态：读一个人的 CDP 画像（含 P0-2 写回的成交
 * 信号）+ 成交真相账本，跨模块产出"对这个人此刻的下一最佳动作 + 为什么"。
 *
 * 【边界/原则】
 *  - 先只读建议：只产出提议，不自动执行（人一键采纳是下一步）。
 *  - 规则优先、AI 增强可选：不配 AI 也能给出确定性建议+理由；配了 AI 只用来
 *    润色措辞（growth_brain_polish，默认不调用），绝不依赖外部服务才能工作。
 *  - 纯函数内核：growth_brain_propose($profile,$truth) 不做 IO，可独立测试。
 *  - 不是 GrowthFlywheel（内容自动线），不是 GrowthEngine（自进化/信号）；
 *    这是跨模块的逐人决策中枢。三者分工见各自文档块。
 */

if (!function_exists('growth_brain_normalize')) {
    /**
     * 把 cdp_customers 行 / CdpSystem 画像，归一成大脑消费的画像结构。
     */
    function growth_brain_normalize(array $row): array {
        $tags = $row['tags'] ?? [];
        if (is_string($tags))  $tags  = json_decode($tags, true) ?: [];
        $props = $row['props'] ?? [];
        if (is_string($props)) $props = json_decode($props, true) ?: [];

        $lastSeen = $row['last_seen'] ?? ($row['lastSeen'] ?? '');
        $days = 9999;
        if ($lastSeen) { $t = strtotime($lastSeen); if ($t) $days = max(0, (int)floor((time() - $t) / 86400)); }

        return [
            'id'         => (string)($row['id'] ?? ''),
            'name'       => (string)($row['name'] ?? ($row['email'] ?? '匿名')),
            'email'      => (string)($row['email'] ?? ''),
            'score'      => (int)($row['score'] ?? 0),
            'ltv'        => (float)($row['lifetime_value'] ?? ($props['won_value_total'] ?? 0)),
            'won_count'  => (int)($props['won_count'] ?? 0),
            'source'     => (string)($props['last_won_source'] ?? ($row['channel'] ?? '')),
            'segment'    => (string)($props['last_won_segment'] ?? ''),
            'tags'       => array_values((array)$tags),
            'days_idle'  => $days,
        ];
    }
}

if (!function_exists('growth_brain_propose')) {
    /**
     * 纯函数：对一个画像产出排好序的 NBA 提议。
     * $truth: growth_conversion_truth() 的结果（可空）——让"来源/分群真转化"影响优先级。
     * 返回：['best'=>提议|null, 'all'=>[提议...]]，每条提议 =
     *   ['module','action','reason','priority','cta']
     */
    function growth_brain_propose(array $p, ?array $truth = null, ?array $goal = null): array {
        $p = isset($p['days_idle']) && isset($p['won_count']) ? $p : growth_brain_normalize($p);
        $props = [];

        // 该来源/分群在成交真相里排第几（用于"放大赢面"）
        $topSources = [];
        foreach (($truth['sources'] ?? []) as $i => $s) { if ($i < 3 && ($s['revenue'] ?? 0) > 0) $topSources[$s['key']] = $i; }

        // ── 规则集（每条命中即产出一条提议）──

        // 1. 临门一脚：高互动、未成交 → 推成交/发报价
        if ($p['score'] >= 60 && $p['won_count'] === 0) {
            $props[] = [
                'module' => 'Sales', 'action' => '主动推成交 · 发报价单',
                'reason' => "互动分 {$p['score']}、尚未成交——是最该临门一脚的人。",
                'priority' => min(100, 55 + (int)($p['score'] * 0.4)), 'cta' => '建报价单',
            ];
        }

        // 2. 老客复购：成交过 + 沉默 → 复购召回
        if ($p['won_count'] >= 1 && $p['days_idle'] >= 30) {
            $ltv = (int)round($p['ltv']);
            $props[] = [
                'module' => 'MA', 'action' => '老客复购召回',
                'reason' => "已成交 {$p['won_count']} 单、累计 ¥{$ltv}，{$p['days_idle']} 天未回——复购窗口。",
                'priority' => min(100, 50 + min(30, (int)($p['ltv'] / 1000)) + min(15, (int)($p['days_idle'] / 10))),
                'cta' => '加入复购触达',
            ];
        }

        // 3. 放大赢面：来源属成交真相 Top → 对同来源人群定向放大
        if ($p['won_count'] >= 1 && $p['source'] !== '' && isset($topSources[$p['source']])) {
            $rank = $topSources[$p['source']] + 1;
            $props[] = [
                'module' => 'MA', 'action' => '同来源人群定向放大',
                'reason' => "来源「{$p['source']}」在成交真相里排第 {$rank}——把预算压到这个真转化的来源。",
                'priority' => 62, 'cta' => '建定向',
            ];
        }

        // 4. 沉默高意向挽回：曾有意向、长期沉默、未成交
        if ($p['won_count'] === 0 && $p['score'] >= 40 && $p['days_idle'] >= 60) {
            $props[] = [
                'module' => 'Sales', 'action' => '沉默高意向挽回',
                'reason' => "曾达互动分 {$p['score']}，已沉默 {$p['days_idle']} 天且未成交——挽回或判死。",
                'priority' => 48, 'cta' => '发挽回触达',
            ];
        }

        // 5. 高价值维护：LTV 靠前 → 人工维护
        if ($p['ltv'] >= 5000) {
            $props[] = [
                'module' => 'Sales', 'action' => '高价值客户人工维护',
                'reason' => "累计 ¥" . (int)round($p['ltv']) . " 的高价值客户——值得一次人工关怀。",
                'priority' => 58, 'cta' => '标记跟进',
            ];
        }

        // 6. 内容培育：信号还少 → 先用内容养
        if ($p['score'] < 30 && count($p['tags']) <= 1 && $p['won_count'] === 0) {
            $props[] = [
                'module' => 'Content', 'action' => '内容培育 · 补全画像',
                'reason' => "信号还少（分 {$p['score']}、标签 " . count($p['tags']) . "）——先用个性化内容养熟、补画像。",
                'priority' => 25, 'cta' => '推荐内容位',
            ];
        }

        // ── 目标加权：让离当前增长目标最近的动作浮上来（AUDIT-07 P1-5）──
        if ($goal && function_exists('growth_goal_boost_modules')) {
            $boost = growth_goal_boost_modules((string)($goal['metric'] ?? ''));
            if ($boost) foreach ($props as &$pr) {
                if (isset($boost[$pr['module']])) {
                    $pr['priority'] = min(100, $pr['priority'] + $boost[$pr['module']]);
                    $pr['goal_boosted'] = true;
                }
            }
            unset($pr);
        }

        usort($props, fn($a, $b) => $b['priority'] <=> $a['priority']);
        return ['best' => $props[0] ?? null, 'all' => $props];
    }
}

if (!function_exists('growth_brain_digest')) {
    /**
     * 跨画像批量：对每个人取其最佳提议，全局按优先级排序，返回前 $limit。
     * 这是"销售/增长驾驶舱"的种子——一眼看到"现在最该动的人和动作"。
     * $profiles: 归一前的行数组（cdp_customers 行或画像）。
     */
    function growth_brain_digest(array $profiles, ?array $truth = null, int $limit = 20, ?array $goal = null): array {
        $rows = [];
        foreach ($profiles as $row) {
            $prof = growth_brain_normalize($row);
            $prop = growth_brain_propose($prof, $truth, $goal);
            if (!$prop['best']) continue;                 // 没有强信号的人不占版面
            $rows[] = ['profile' => $prof, 'best' => $prop['best'], 'alts' => array_slice($prop['all'], 1, 2)];
        }
        usort($rows, fn($a, $b) => $b['best']['priority'] <=> $a['best']['priority']);
        return array_slice($rows, 0, $limit);
    }
}

if (!function_exists('growth_brain_polish')) {
    /**
     * 可选：用已配置的 AI 把 reason 润色得更像"人话"。不配 AI 原样返回。
     * 默认不被 propose 调用——避免任何"没有 AI 就不工作"的耦合。
     */
    function growth_brain_polish(string $reason, array $profile): string {
        if (!class_exists('AiCenter') || !\AiCenter::isConfigured()) return $reason;
        try {
            $r = \AiCenter::chat(
                '你是增长运营教练。把下面这条"下一最佳动作的理由"改写得更简洁、更有说服力，一句话，不超过40字，不要加引号。',
                $reason,
                ['max_tokens' => 120, 'feature' => 'growth_brain', 'tier' => 'batch']
            );
            $t = trim((string)($r['text'] ?? $r['content'] ?? ''));
            return $t !== '' ? $t : $reason;
        } catch (\Throwable $e) { return $reason; }
    }
}
