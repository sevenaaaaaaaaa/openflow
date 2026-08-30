<?php
/**
 * PlatformOps —— 平台运营 Agent · 选品驾驶舱（AUDIT-05 创新二 / BACKLOG T1-12）
 *
 * 【为什么】平台方常常就一个人，请不起运营团队："这周推谁上首页、哪个在掉要换位、
 * 这个新品能不能上架"——一件都没有工具支撑。App Store 靠成百上千审核员，中小平台
 * 学不起。这里用**已有的成交数据 + 画像 + AI**做一个轻量但可解释的运营 Agent。
 *
 * 【本版范围】① 选品建议（推谁/曝光谁/换谁，带理由）；② 新品上架质量初判
 * （描述完整性/定价异常/资产缺失 → 建议通过/需改/建议拒，人做最终关口）。
 * 纯规则内核可测；AI 可选增强。绝不自动上下架——只提议，人拍板。
 */

if (!function_exists('platops_curate')) {

    /**
     * 选品建议。$products: [{id,title,price,sales,views,created_at,featured,status}]
     * 返回最多 $limit 条 ['kind','product_id','title','reason','suggest']
     *   kind: promote(推首页) / spotlight(给曝光) / demote(换下来)
     */
    function platops_curate(array $products, int $limit = 6): array {
        $now = time();
        $scored = [];
        foreach ($products as $p) {
            if (!is_array($p)) continue;
            if (($p['status'] ?? 'active') !== 'active') continue;
            $sales  = (int)($p['sales'] ?? 0);
            $views  = max(1, (int)($p['views'] ?? 0));
            $price  = (float)($p['price'] ?? 0);
            $feat   = !empty($p['featured']);
            $ageDays = 9999;
            if (!empty($p['created_at'])) { $t = strtotime((string)$p['created_at']); if ($t) $ageDays = max(0, (int)floor(($now - $t) / 86400)); }
            $cvr = $sales / $views;   // 粗转化率
            $scored[] = compact('p', 'sales', 'views', 'price', 'feat', 'ageDays', 'cvr');
        }

        $out = [];

        // 1) 推首页：转化率高但没被推
        $cands = array_values(array_filter($scored, fn($x) => !$x['feat'] && $x['sales'] >= 1));
        usort($cands, fn($a, $b) => $b['cvr'] <=> $a['cvr']);
        foreach (array_slice($cands, 0, 2) as $x) {
            $out[] = [
                'kind' => 'promote', 'product_id' => (string)($x['p']['id'] ?? ''),
                'title' => (string)($x['p']['title'] ?? ''),
                'reason' => '转化率 ' . round($x['cvr'] * 100, 1) . '%（' . $x['sales'] . ' 单 / ' . $x['views'] . ' 浏览），还没上推荐位——这是最该被更多人看到的。',
                'suggest' => '推上首页',
            ];
        }

        // 2) 给曝光：新品还没被看见
        $newbies = array_values(array_filter($scored, fn($x) => $x['ageDays'] <= 30 && $x['views'] < 50));
        usort($newbies, fn($a, $b) => $a['ageDays'] <=> $b['ageDays']);
        foreach (array_slice($newbies, 0, 2) as $x) {
            $out[] = [
                'kind' => 'spotlight', 'product_id' => (string)($x['p']['id'] ?? ''),
                'title' => (string)($x['p']['title'] ?? ''),
                'reason' => '上架 ' . $x['ageDays'] . ' 天，只有 ' . $x['views'] . ' 次浏览——新品没曝光就没有数据，无法判断好坏。',
                'suggest' => '给一轮曝光',
            ];
        }

        // 3) 换下来：占着推荐位却不转化
        $bad = array_values(array_filter($scored, fn($x) => $x['feat'] && $x['views'] >= 100 && $x['cvr'] < 0.005));
        usort($bad, fn($a, $b) => $a['cvr'] <=> $b['cvr']);
        foreach (array_slice($bad, 0, 2) as $x) {
            $out[] = [
                'kind' => 'demote', 'product_id' => (string)($x['p']['id'] ?? ''),
                'title' => (string)($x['p']['title'] ?? ''),
                'reason' => '占着推荐位，' . $x['views'] . ' 次浏览只成交 ' . $x['sales'] . ' 单——位置该让给转化更好的。',
                'suggest' => '换下推荐位',
            ];
        }

        return array_slice($out, 0, $limit);
    }

    /**
     * 新品上架质量初判（人做最终关口）。
     * 返回 ['verdict'=>'pass|revise|reject','score'=>0-100,'issues'=>[],'notes'=>[]]
     */
    function platops_review(array $product, array $peers = []): array {
        $issues = []; $notes = [];
        $title = trim((string)($product['title'] ?? ''));
        $desc  = trim((string)($product['description'] ?? ''));
        $price = (float)($product['price'] ?? 0);
        $cover = trim((string)($product['cover'] ?? ''));
        $asset = trim((string)($product['asset_id'] ?? ''));

        if ($title === '' || mb_strlen($title) < 4) $issues[] = '标题太短或缺失，用户看不懂这是什么';
        if (mb_strlen($desc) < 30) $issues[] = '描述少于 30 字，说不清「解决什么问题、谁该买」';
        if ($cover === '') $issues[] = '缺封面图，列表里几乎不会被点开';
        if ($asset === '' && ($product['type'] ?? '') !== 'service') $issues[] = '没有关联可交付资产，付款后无法自动交付';

        // 夸大用语（合规风险）
        foreach (['最强', '第一', '100%', '保证赚', '稳赚', '包过'] as $w) {
            if (mb_strpos($title . $desc, $w) !== false) { $issues[] = "含夸大用语「{$w}」，有合规风险"; break; }
        }

        // 定价异常：与同类中位数偏离过大
        $prices = [];
        foreach ($peers as $pp) { $v = (float)($pp['price'] ?? 0); if ($v > 0) $prices[] = $v; }
        if ($price <= 0) {
            $issues[] = '价格为 0 或未设置';
        } elseif (count($prices) >= 3) {
            sort($prices);
            $mid = $prices[(int)floor(count($prices) / 2)];
            if ($mid > 0) {
                if ($price > $mid * 5)      $notes[] = '定价是同类中位数的 ' . round($price / $mid, 1) . ' 倍，偏高需理由';
                elseif ($price < $mid * 0.2) $notes[] = '定价明显低于同类，注意是否填错';
            }
        }

        $score = max(0, 100 - count($issues) * 25 - count($notes) * 5);
        $verdict = count($issues) === 0 ? 'pass' : (count($issues) >= 3 ? 'reject' : 'revise');
        return ['verdict' => $verdict, 'score' => $score, 'issues' => $issues, 'notes' => $notes];
    }

    function platops_verdict_label(string $v): string {
        return ['pass' => '建议通过', 'revise' => '需改后再上', 'reject' => '建议拒绝'][$v] ?? $v;
    }
}
