<?php
/**
 * BlockTargeting —— 落地页/建站区块级人群定向（AUDIT-01 / BACKLOG T1-8）
 *
 * 【为什么】PromoSystem 已有一套成熟的人群定向（登录态/新老访客/CDP 分群/UTM），
 * 但只能用在"通知条/弹窗"这类投放位上；建站页面的区块还是所有人看到同一份。
 * 本模块把同一套定向下放到**区块级**：某个 Hero 只给老客看、某个 CTA 只给
 * 未登录访客看——把"建站"和"千人千面"焊起来。
 *
 * 【模型】区块上挂 audience：
 *   {login: any|in|out, visitor: any|new|return, segment: '分群id', utm: '来源'}
 * 全空 = 所有人可见（默认，行为不变）。
 * 判定复用 promo 的语义，保持两处一致；不依赖 PromoSystem 也能独立工作。
 */

if (!function_exists('blocktarget_context')) {

    /**
     * 构建当前访客上下文（与 api/promo.php 同源语义）。$server/$cookie 可注入便于测试。
     */
    function blocktarget_context(?array $cookie = null, ?array $get = null): array {
        $cookie = $cookie ?? $_COOKIE;
        $get    = $get ?? $_GET;

        $loggedIn = false;
        if (function_exists('member_current')) { try { $loggedIn = (bool)member_current(); } catch (\Throwable $e) {} }

        $segments = [];
        $uid = (string)($cookie['fc_uid'] ?? '');
        if ($uid !== '' && class_exists('CdpSystem')) {
            try {
                $prof = CdpSystem::getProfile($uid);
                if ($prof) foreach ((array)($prof['segment_memberships'] ?? []) as $sid => $on) if ($on) $segments[] = $sid;
            } catch (\Throwable $e) {}
        }

        return [
            'logged_in'  => $loggedIn,
            'visitor'    => (($cookie['fc_seen'] ?? '') !== '') ? 'return' : 'new',
            'segments'   => $segments,
            'utm_source' => (string)($get['utm_source'] ?? ($get['utm'] ?? '')),
        ];
    }

    /** 该区块是否配了定向（用于后台提示与统计）。 */
    function blocktarget_has_rules(array $block): bool {
        $a = $block['audience'] ?? [];
        if (!is_array($a)) return false;
        foreach (['login', 'visitor', 'segment', 'utm'] as $k) {
            $v = trim((string)($a[$k] ?? ''));
            if ($v !== '' && $v !== 'any') return true;
        }
        return false;
    }

    /**
     * 区块对当前访客是否可见。无规则 → true（默认全员可见）。
     */
    function blocktarget_visible(array $block, array $ctx): bool {
        $a = $block['audience'] ?? [];
        if (!is_array($a) || !$a) return true;

        $login = trim((string)($a['login'] ?? 'any'));
        if ($login === 'in'  && empty($ctx['logged_in'])) return false;
        if ($login === 'out' && !empty($ctx['logged_in'])) return false;

        $visitor = trim((string)($a['visitor'] ?? 'any'));
        if ($visitor !== '' && $visitor !== 'any' && ($ctx['visitor'] ?? '') !== $visitor) return false;

        $seg = trim((string)($a['segment'] ?? ''));
        if ($seg !== '' && !in_array($seg, (array)($ctx['segments'] ?? []), true)) return false;

        $utm = trim((string)($a['utm'] ?? ''));
        if ($utm !== '' && strcasecmp((string)($ctx['utm_source'] ?? ''), $utm) !== 0) return false;

        return true;
    }

    /** 过滤一组区块，只留当前访客可见的。 */
    function blocktarget_filter(array $blocks, ?array $ctx = null): array {
        $ctx = $ctx ?? blocktarget_context();
        $out = [];
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            if (blocktarget_visible($b, $ctx)) $out[] = $b;
        }
        return $out;
    }

    /** 后台下拉数据源（与 promo 保持一致的选项）。 */
    function blocktarget_options(): array {
        return [
            'login'   => ['any' => '不限', 'in' => '已登录', 'out' => '未登录'],
            'visitor' => ['any' => '不限', 'new' => '新访客', 'return' => '回访客'],
        ];
    }
}
