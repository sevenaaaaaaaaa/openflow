<?php
/**
 * SalesPlaybook —— 销售话术 / 物料草稿（AUDIT-04 / BACKLOG T1-9）
 *
 * 【为什么】GrowthBrain 驾驶舱已经会说"这单下一步该干嘛"，但销售还得自己想话术、
 * 翻案例、写报价。AUDIT-04 创新二要的是"物料和话术都备好，拿来即用"。
 * 本模块按【动作类型 × 画像】产出可直接用的草稿：开场白/异议应对/跟进邮件/报价说明。
 *
 * 【原则】规则模板先行(不配 AI 也拿得到能用的草稿)，AI 可选增强(按画像润色)。
 * 纯函数内核，可独立测试；不发送、不落库——只产草稿，发不发由人决定。
 */

if (!function_exists('playbook_kinds')) {

    function playbook_kinds(): array {
        return [
            'opener'    => '开场白',
            'objection' => '异议应对',
            'followup'  => '跟进邮件',
            'quote'     => '报价说明',
        ];
    }

    /** 由大脑动作推断该配哪种物料。 */
    function playbook_kind_for_action(string $action): string {
        if (strpos($action, '报价') !== false || strpos($action, '推成交') !== false) return 'quote';
        if (strpos($action, '复购') !== false || strpos($action, '挽回') !== false) return 'followup';
        if (strpos($action, '培育') !== false) return 'opener';
        return 'opener';
    }

    /** 称呼：有名字用名字，否则用通称。 */
    function playbook_salutation(array $p): string {
        $n = trim((string)($p['name'] ?? ''));
        if ($n === '' || strpos($n, '@') !== false) return '您好';
        return $n . ' 您好';
    }

    /**
     * 生成话术草稿（纯函数）。
     * $profile: name/email/score/won_count/ltv/days_idle/source/segment（GrowthBrain 归一后的画像）
     * 返回 ['kind','title','body','tips'=>[]]
     */
    function playbook_draft(string $kind, array $profile, array $ctx = []): array {
        $kind = isset(playbook_kinds()[$kind]) ? $kind : 'opener';
        $hi   = playbook_salutation($profile);
        $won  = (int)($profile['won_count'] ?? 0);
        $idle = (int)($profile['days_idle'] ?? 0);
        $ltv  = (float)($profile['ltv'] ?? 0);
        $src  = trim((string)($profile['source'] ?? ''));
        $amount = $ctx['amount'] ?? null;

        switch ($kind) {
            case 'quote':
                $body = "{$hi}，\n\n"
                      . "根据我们前面聊的需求，我整理了一份方案与报价"
                      . ($amount !== null ? "（总价 ¥" . number_format((float)$amount, 2) . "）" : "")
                      . "，链接在下方，点开就能看明细，确认后可直接在线支付：\n\n[收款链接]\n\n"
                      . "如果范围或预算需要调整，直接回我，我按你的实际情况改一版。";
                $tips = ['把链接换成后台生成的收款单链接', '附一个同行业案例更容易推进', '给一个明确的有效期能提升成交速度'];
                break;

            case 'followup':
                if ($won > 0) {
                    $body = "{$hi}，\n\n"
                          . "上次合作到现在有 {$idle} 天了，最近这块进展怎么样？\n\n"
                          . "我们这边刚更新了一些能直接用上的东西，"
                          . ($ltv >= 5000 ? "考虑到你之前的投入，我给你留了优先支持的名额。" : "如果有需要我可以先发给你看看。")
                          . "\n\n方便的话回一句，我按你现在的情况给个建议。";
                    $tips = ['提一个他上次买过的东西的具体进展，比泛泛问候有效', '带一个可执行的下一步（试用/复购优惠/复盘会）'];
                } else {
                    $body = "{$hi}，\n\n"
                          . "之前你看过我们的方案，后来一直没打扰。\n\n"
                          . "想确认下：当时是时机不合适，还是有哪块没讲清楚？\n\n"
                          . "如果暂时不需要，回我一句我就不再跟进；如果还在看，我可以针对你关心的点单独发一份说明。";
                    $tips = ['给一个"明确说不"的出口，回复率反而更高', '别再重复介绍产品，问阻力在哪'];
                }
                break;

            case 'objection':
                $body = "常见异议与应对：\n\n"
                      . "· 「太贵了」→ 先问对比的是什么。把价格拆成每月/每次成本，再对齐他最在意的那个结果。\n"
                      . "· 「再考虑一下」→ 问清是哪一块还没定：预算、时间、还是内部有人要拍板。\n"
                      . "· 「和 XX 有什么区别」→ 只讲他用得上的两点差异，别列全表。\n"
                      . "· 「现在没时间」→ 约一个具体时间点，而不是「以后再说」。";
                $tips = ['异议不是拒绝，是信息不足', '每条异议记回 CRM，反复出现的就该做成内容'];
                break;

            case 'opener':
            default:
                $body = "{$hi}，\n\n"
                      . ($src !== '' ? "看到你是从「{$src}」过来的。\n\n" : "")
                      . "我是 [你的名字]。看你最近在关注这块，先不推销——\n"
                      . "如果你现在最想解决的是某个具体问题，直接告诉我，我给你一个能马上用的建议。\n\n"
                      . "如果只是随便看看，也完全没问题。";
                $tips = ['第一句别推销，先给价值', '问一个具体问题比"了解需求"有效'];
                break;
        }

        return [
            'kind'  => $kind,
            'title' => playbook_kinds()[$kind],
            'body'  => $body,
            'tips'  => $tips,
        ];
    }

    /**
     * AI 增强：按画像把草稿改写得更贴合这个人。未配 AI / 失败 → 原样返回。
     * 可注入 $GLOBALS['PLAYBOOK_AI_FN'] 便于测试。
     */
    function playbook_ai_polish(array $draft, array $profile): array {
        try {
            $ctx = json_encode(['draft' => $draft['body'], 'profile' => $profile], JSON_UNESCAPED_UNICODE);
            if (isset($GLOBALS['PLAYBOOK_AI_FN']) && is_callable($GLOBALS['PLAYBOOK_AI_FN'])) {
                $t = (string)call_user_func($GLOBALS['PLAYBOOK_AI_FN'], $ctx);
                if (trim($t) !== '') { $draft['body'] = $t; $draft['ai'] = true; }
                return $draft;
            }
            if (!class_exists('AiCenter') || !\AiCenter::isConfigured()) return $draft;
            $r = \AiCenter::chat(
                '你是资深销售教练。把给定的销售话术草稿，按这个客户的画像改写得更贴合、更自然，'
                . '保持中文、保持同样的结构与意图，不要加客套废话，不要编造事实。只输出改写后的正文。',
                $ctx, ['max_tokens' => 700, 'feature' => 'sales_playbook', 'tier' => 'admin']
            );
            $t = trim((string)($r['text'] ?? $r['content'] ?? ''));
            if (!empty($r['ok']) && $t !== '') { $draft['body'] = $t; $draft['ai'] = true; }
        } catch (\Throwable $e) {}
        return $draft;
    }

    /** 给一条大脑提议直接配好物料（驾驶舱用）。 */
    function playbook_for_proposal(array $proposal, array $profile, array $ctx = []): array {
        $kind = playbook_kind_for_action((string)($proposal['action'] ?? ''));
        return playbook_draft($kind, $profile, $ctx);
    }
}
