<?php
/**
 * 前端增长工具箱 WebTools
 * 免费工具集：SEO 检查 / Meta 生成 / 可读性分析 / LTV-CAC 计算 / 关键词难度估算
 */
require_once __DIR__ . '/../admin/config.php';

class WebTools {
    /**
     * 可读性分析（字数/阅读时间/标题/段落结构）
     */
    public static function readability(string $text): array {
        $text = trim($text);
        $chars = mb_strlen($text);
        $words = count(preg_split('/\s+/', trim(preg_replace('/[\x{4e00}-\x{9fa5}]/u', ' ' . '$0' . ' ', $text)))) ?: 0;
        $sentences = max(1, preg_match_all('/[。！？!?\.]+/u', $text));
        $paragraphs = max(1, count(array_filter(explode("\n", $text), fn($l) => trim($l))));
        // 中文按字数估阅读时间（约400字/分钟）
        $cnChars = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $text);
        $readMinutes = max(1, round($cnChars / 400, 1));
        // 标题检测
        preg_match_all('/^#{1,6}\s+(.+)$/m', $text, $headings);
        // 关键词密度（最常见2字词）
        $density = [];
        preg_match_all('/[\x{4e00}-\x{9fa5}]{2,4}/u', $text, $wordsAll);
        $wordCounts = array_count_values($wordsAll[0]);
        arsort($wordCounts);
        foreach (array_slice($wordCounts, 0, 5) as $w => $c) {
            $density[] = ['word' => $w, 'count' => $c, 'density' => round($c / max(1, $cnChars) * 100, 2)];
        }
        return [
            'chars' => $chars,
            'cn_chars' => $cnChars,
            'sentences' => $sentences,
            'paragraphs' => $paragraphs,
            'read_minutes' => $readMinutes,
            'headings' => count($headings[1]),
            'heading_list' => $headings[1] ?? [],
            'top_keywords' => $density,
        ];
    }

    /**
     * SEO 页面检查（基于 meta 标签信息）
     */
    public static function seoCheck(string $title, string $description, string $keywords = ''): array {
        $result = [];
        // 标题
        $titleLen = mb_strlen($title);
        $result['title'] = [
            'value' => $title, 'length' => $titleLen,
            'ok' => $titleLen >= 10 && $titleLen <= 60,
            'tip' => $titleLen < 10 ? '标题过短，建议 10-60 字' : ($titleLen > 60 ? '标题过长，建议 ≤60 字（可能被截断）' : '标题长度合适'),
        ];
        // 描述
        $descLen = mb_strlen($description);
        $result['description'] = [
            'value' => $description, 'length' => $descLen,
            'ok' => $descLen >= 50 && $descLen <= 160,
            'tip' => $descLen < 50 ? '描述过短，建议 50-160 字' : ($descLen > 160 ? '描述过长，建议 ≤160 字' : '描述长度合适'),
        ];
        // 关键词
        $kwArr = array_values(array_filter(array_map('trim', explode(',', $keywords))));
        $result['keywords'] = [
            'value' => $keywords, 'count' => count($kwArr),
            'ok' => count($kwArr) >= 3 && count($kwArr) <= 8,
            'tip' => count($kwArr) < 3 ? '建议 3-8 个关键词' : (count($kwArr) > 8 ? '关键词过多，建议 ≤8 个' : '关键词数量合适'),
            'list' => $kwArr,
        ];
        // 关键词在标题/描述中的覆盖
        $coverage = ['title' => 0, 'description' => 0];
        foreach ($kwArr as $kw) {
            if (mb_strpos($title, $kw) !== false) $coverage['title']++;
            if (mb_strpos($description, $kw) !== false) $coverage['description']++;
        }
        $result['coverage'] = [
            'title_hits' => $coverage['title'],
            'description_hits' => $coverage['description'],
            'tip' => $coverage['title'] === 0 ? '核心关键词未出现在标题中，建议优化' : '核心关键词已覆盖标题',
        ];
        // 综合评分
        $score = 50;
        if ($result['title']['ok']) $score += 15;
        if ($result['description']['ok']) $score += 15;
        if ($result['keywords']['ok']) $score += 10;
        if ($coverage['title'] > 0) $score += 10;
        $result['score'] = min(100, $score);
        $result['grade'] = $score >= 80 ? '优' : ($score >= 60 ? '良' : '需优化');
        return $result;
    }

    /**
     * Meta 标签生成器
     */
    public static function generateMeta(string $title, string $keywords, string $description = ''): array {
        $kw = array_values(array_filter(array_map('trim', explode(',', $keywords))));
        $primary = $kw[0] ?? '';
        $description = $description ?: $title . '。' . ($primary ? "聚焦{$primary}，提供专业内容与解决方案。" : '');
        $meta = [];
        $meta['title'] = $title . ($primary ? ' - ' . $primary : '');
        $meta['description'] = mb_substr($description, 0, 160);
        $meta['keywords'] = implode(', ', array_slice($kw, 0, 8));
        $meta['og_title'] = $title;
        $meta['og_description'] = mb_substr($description, 0, 160);
        $meta['twitter_title'] = $title;
        $meta['json_ld'] = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'description' => mb_substr($description, 0, 160),
            'keywords' => implode(', ', array_slice($kw, 0, 8)),
        ];
        // 生成 HTML 片段
        $meta['html'] = "<title>{$meta['title']}</title>\n"
            . "<meta name=\"description\" content=\"{$meta['description']}\">\n"
            . "<meta name=\"keywords\" content=\"{$meta['keywords']}\">\n"
            . "<meta property=\"og:title\" content=\"{$meta['og_title']}\">\n"
            . "<meta property=\"og:description\" content=\"{$meta['og_description']}\">";
        return $meta;
    }

    /**
     * LTV / CAC 计算器
     */
    public static function ltvCac(array $input): array {
        $arpu = (float)($input['arpu'] ?? 0);      // 月均客单价
        $churn = (float)($input['churn'] ?? 0) / 100; // 月流失率
        $cac = (float)($input['cac'] ?? 0);          // 获客成本
        $grossMargin = (float)($input['margin'] ?? 60) / 100; // 毛利率
        $lifeMonths = $churn > 0 ? round(1 / $churn, 1) : 99;
        $ltv = round($arpu * $lifeMonths * $grossMargin, 2);
        $ratio = $cac > 0 ? round($ltv / $cac, 2) : 0;
        return [
            'life_months' => $lifeMonths,
            'ltv' => $ltv,
            'ltv_cac_ratio' => $ratio,
            'payback_months' => $cac > 0 && $arpu * $grossMargin > 0 ? round($cac / ($arpu * $grossMargin), 1) : 0,
            'health' => $ratio >= 3 ? '健康' : ($ratio >= 1 ? '需改善' : '不健康'),
            'tip' => $ratio >= 3 ? 'LTV/CAC ≥3，商业模式健康' : ($ratio >= 1 ? 'LTV/CAC 介于1-3，建议优化留存或降本' : 'LTV/CAC <1，每单亏损，急需调整'),
        ];
    }

    /**
     * 转化漏斗计算器
     */
    public static function funnel(array $stages): array {
        $result = [];
        $conversion = [];
        $n = count($stages);
        for ($i = 0; $i < $n; $i++) {
            $count = (int)($stages[$i]['count'] ?? 0);
            $name = $stages[$i]['name'] ?? ('阶段' . ($i + 1));
            $conv = $i === 0 ? 100 : ($stages[$i-1]['count'] > 0 ? round($count / $stages[$i-1]['count'] * 100, 1) : 0);
            $totalConv = $stages[0]['count'] > 0 ? round($count / $stages[0]['count'] * 100, 1) : 0;
            $result[] = [
                'name' => $name, 'count' => $count,
                'step_conv' => $conv, 'total_conv' => $totalConv,
                'dropoff' => $i > 0 ? ($stages[$i-1]['count'] - $count) : 0,
            ];
        }
        return $result;
    }
}
