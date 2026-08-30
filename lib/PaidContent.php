<?php
/**
 * PaidContent —— 付费 Newsletter / 会员专享内容（AUDIT-01 / BACKLOG T1-6）
 *
 * 【为什么】站点已有会员/订阅/支付体系，内容却只有"公开 / 仅会员"二值：
 * 做不了"年度会员才能看""这期通讯只发给付费订阅者"。这是内容 × 已有商业化的
 * 现成机会。本模块补分层门禁 + 付费通讯投递范围。
 *
 * 【模型】文章加 required_tier：
 *   ''        公开
 *   'member'  任意登录会员
 *   plan_id   指定套餐及以上（annual / lifetime …，按 mem_plans 顺序定高低）
 * 未达门槛 → 返回可读的预览片段 + 升级提示（不是 404，利于转化与 SEO）。
 */

if (!function_exists('paid_tier_order')) {

    /** 套餐等级顺序（越靠后越高）。免费在最前，其余按 mem_plans 顺序。 */
    function paid_tier_order(): array {
        $ids = ['', 'member'];
        if (function_exists('mem_plans')) {
            foreach (mem_plans() as $p) {
                $id = (string)($p['id'] ?? '');
                if ($id !== '' && $id !== 'free' && !in_array($id, $ids, true)) $ids[] = $id;
            }
        }
        return $ids;
    }

    /** 可选门槛（后台下拉数据源）。 */
    function paid_tier_options(): array {
        $out = ['' => '公开', 'member' => '仅登录会员'];
        if (function_exists('mem_plans')) {
            foreach (mem_plans() as $p) {
                $id = (string)($p['id'] ?? '');
                if ($id === '' || $id === 'free') continue;
                $out[$id] = ($p['icon'] ?? '') . ' ' . ($p['name'] ?? $id) . ' 及以上';
            }
        }
        return $out;
    }

    /** 会员当前等级 id（未登录=''，登录无套餐='member'）。 */
    function paid_member_tier(?array $member): string {
        if (!$member) return '';
        $plan = (string)($member['membership_plan'] ?? '');
        if ($plan !== '' && $plan !== 'free') return $plan;
        return 'member';
    }

    /**
     * 是否有权看完整内容。
     * 规则：门槛为空→都能看；否则会员等级在顺序表里的位置 >= 门槛位置。
     */
    function paid_can_view(?array $member, string $requiredTier): bool {
        $requiredTier = trim($requiredTier);
        if ($requiredTier === '') return true;
        $order = paid_tier_order();
        $mine  = paid_member_tier($member);
        $mi = array_search($mine, $order, true);
        $ri = array_search($requiredTier, $order, true);
        if ($ri === false) return true;              // 未知门槛不误伤
        if ($mi === false) return false;
        return $mi >= $ri;
    }

    /**
     * 生成付费墙预览：保留前 $chars 个可见字符的完整段落，其余截断。
     * 返回 ['preview'=>html, 'truncated'=>bool]。
     */
    function paid_preview(string $html, int $chars = 400): array {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
        if (mb_strlen($plain) <= $chars) return ['preview' => $html, 'truncated' => false];
        // 按段落累积，直到超过阈值
        if (preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $m) && $m[0]) {
            $acc = ''; $len = 0;
            foreach ($m[0] as $p) {
                $acc .= $p;
                $len += mb_strlen(trim(strip_tags($p)));
                if ($len >= $chars) break;
            }
            if ($acc !== '') return ['preview' => $acc, 'truncated' => true];
        }
        return ['preview' => '<p>' . htmlspecialchars(mb_substr($plain, 0, $chars)) . '…</p>', 'truncated' => true];
    }

    /** 升级提示文案（给付费墙用）。 */
    function paid_upgrade_hint(string $requiredTier): string {
        if ($requiredTier === 'member') return '登录会员即可阅读全文';
        $opts = paid_tier_options();
        $name = $opts[$requiredTier] ?? $requiredTier;
        return '升级为' . trim(str_replace('及以上', '', $name)) . '即可阅读全文';
    }

    /* ─────────── 付费 Newsletter ─────────── */

    function paid_news_file(): string { return DATA_DIR . '/newsletter/subscribers.json'; }

    /**
     * 一期通讯的投递名单。
     * $tier='' → 全部订阅者；否则只发给达到该门槛的会员订阅者。
     * $subs/$members 可注入（测试）。返回邮箱数组。
     */
    function paid_news_audience(string $tier = '', ?array $subs = null, ?array $members = null): array {
        $subs = $subs ?? (function_exists('json_read') ? json_read(paid_news_file()) : []);
        if (trim($tier) === '') {
            $out = [];
            foreach ($subs as $s) {
                $e = is_array($s) ? ($s['email'] ?? '') : (string)$s;
                if ($e !== '' && (!is_array($s) || ($s['status'] ?? 'active') !== 'unsubscribed')) $out[] = $e;
            }
            return array_values(array_unique($out));
        }
        $members = $members ?? (function_exists('json_read') ? json_read(DATA_DIR . '/members/index.json') : []);
        $byEmail = [];
        foreach ($members as $m) if (!empty($m['email'])) $byEmail[strtolower($m['email'])] = $m;

        $out = [];
        foreach ($subs as $s) {
            $e = is_array($s) ? ($s['email'] ?? '') : (string)$s;
            if ($e === '') continue;
            if (is_array($s) && ($s['status'] ?? 'active') === 'unsubscribed') continue;
            $m = $byEmail[strtolower($e)] ?? null;
            if ($m && paid_can_view($m, $tier)) $out[] = $e;
        }
        return array_values(array_unique($out));
    }
}
