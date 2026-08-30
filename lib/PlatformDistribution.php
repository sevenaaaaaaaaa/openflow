<?php
/**
 * PlatformDistribution —— 平台级 AI 分发（AUDIT-05 创新三 / BACKLOG T2-7）
 *
 * 【为什么】有赞的营销中心是"给商户一堆工具自己配"，App Store 是"编辑人肉推荐"——
 * 都不是"平台的 AI 逐人决策把每个商品分发出去"。我们的 CDP+MA 本就在做逐人决策，
 * 把商品接进去是同一套大脑的延伸：**创作者什么都不用配，平台替他把货送到对的人面前。**
 *
 * 【本版】按访客画像给商品打分排序（兴趣标签 × 成交真相 × 复购信号 × 新品扶持），
 * 供推荐位/结账页/内容页调用。纯函数内核，数据可注入。
 */

if (!function_exists('pdist_score')) {

    /**
     * 给单个商品对单个访客打分（可解释：返回分数与命中原因）。
     * $profile: ['tags'=>[], 'segments'=>[], 'purchased'=>[商品id], 'source'=>'']
     * $truth:   成交真相账本（growth_conversion_truth 结果，可空）
     */
    function pdist_score(array $product, array $profile, ?array $truth = null): array {
        $score = 0; $why = [];

        // 1) 兴趣匹配：画像标签命中商品标签/标题
        $hay = mb_strtolower(($product['title'] ?? '') . ' ' . ($product['description'] ?? '') . ' ' . implode(' ', (array)($product['tags'] ?? [])));
        $hits = 0;
        foreach ((array)($profile['tags'] ?? []) as $t) {
            $t = mb_strtolower(trim((string)$t));
            if ($t !== '' && mb_strpos($hay, $t) !== false) $hits++;
        }
        if ($hits) { $score += $hits * 20; $why[] = "兴趣命中 {$hits} 个标签"; }

        // 2) 已买过 → 不重复推
        if (in_array((string)($product['id'] ?? ''), array_map('strval', (array)($profile['purchased'] ?? [])), true)) {
            return ['score' => -1, 'why' => ['已购买过，不重复推荐']];
        }

        // 3) 成交真相：这个来源真转化的人，推热销品更稳
        $sales = (int)($product['sales'] ?? 0);
        if ($sales > 0) { $score += min(25, $sales); $why[] = "已售 {$sales} 单"; }

        // 4) 复购信号：买过同作者的东西 → 更可能再买
        $author = (string)($product['author'] ?? '');
        if ($author !== '' && in_array($author, (array)($profile['bought_authors'] ?? []), true)) {
            $score += 30; $why[] = '买过该创作者的其它作品';
        }

        // 5) 新品扶持：没数据的新品给一点基础曝光，否则永无出头之日
        $ageDays = 9999;
        if (!empty($product['created_at'])) { $t = strtotime((string)$product['created_at']); if ($t) $ageDays = (int)floor((time() - $t) / 86400); }
        // 扶持力度刻意低于"已有成交"的权重：给新品出头机会，但不越过被验证过的商品
        if ($ageDays <= 14 && $sales === 0) { $score += 8; $why[] = '新品扶持曝光'; }

        // 6) 成交真相里的强来源加成
        if ($truth && !empty($profile['source'])) {
            foreach (($truth['sources'] ?? []) as $i => $s) {
                if ($i < 3 && (string)($s['key'] ?? '') === (string)$profile['source']) { $score += 8; $why[] = '来自高转化来源'; break; }
            }
        }

        return ['score' => $score, 'why' => $why];
    }

    /**
     * 为一个访客挑商品（平台替创作者分发）。
     * 返回 [['product'=>..., 'score'=>int, 'why'=>[]], ...]，已过滤不可推的。
     */
    function pdist_recommend(array $products, array $profile, int $limit = 4, ?array $truth = null): array {
        $out = [];
        foreach ($products as $p) {
            if (!is_array($p)) continue;
            if (($p['status'] ?? 'active') !== 'active') continue;
            if (isset($p['stock']) && $p['stock'] !== null && (int)$p['stock'] <= 0) continue;
            $r = pdist_score($p, $profile, $truth);
            if ($r['score'] < 0) continue;
            $out[] = ['product' => $p, 'score' => $r['score'], 'why' => $r['why']];
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, max(1, $limit));
    }

    /**
     * 曝光公平性：避免头部商品霸屏——同一批结果里每个创作者最多占 $maxPer 个位置。
     * 这是"平台运营"该有的克制：不然新创作者永远没机会。
     */
    function pdist_diversify(array $ranked, int $maxPer = 2): array {
        $seen = []; $out = [];
        foreach ($ranked as $r) {
            $a = (string)($r['product']['author'] ?? '');
            $k = $a !== '' ? $a : ('_' . count($out));
            $seen[$k] = ($seen[$k] ?? 0) + 1;
            if ($seen[$k] > $maxPer) continue;
            $out[] = $r;
        }
        return $out;
    }
}
