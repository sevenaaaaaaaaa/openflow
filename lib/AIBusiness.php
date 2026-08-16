<?php
/**
 * AI 业务助手 AIBusiness
 * 内容生产：标题优化 / 摘要生成 / 标签推荐 / 多语言
 * 线索运营：线索评分 / 跟进建议
 * 全部基于 AiCenter 统一调用，无 AI 配置时优雅降级（规则回退）
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/AiCenter.php';

class AIBusiness {
    /**
     * 内容优化：批量生成标题/摘要/标签/分类
     * @return array ['ok'=>, 'title'=>, 'excerpt'=>, 'tags'=>[], 'category'=>, 'ai'=>bool]
     */
    public static function optimizeArticle(array $article): array {
        if (!AiCenter::isConfigured()) {
            return self::ruleOptimize($article);
        }
        $content = mb_substr($article['content'] ?? '', 0, 4000);
        $system = '你是一位资深内容编辑与 SEO 专家。根据文章内容输出结构化 JSON。';
        $user = "文章标题：{$article['title']}\n\n文章内容：\n{$content}\n\n请输出 JSON（不要其他文字）：{\"title\":\"优化后的标题(≤30字)\",\"excerpt\":\"120字内的摘要\",\"tags\":[\"3-5个标签\"],\"category\":\"最合适的分类key(英文)\"}";
        $r = AiCenter::json($system, $user, ['temperature' => 0.4]);
        if ($r['ok']) {
            $d = $r['data'];
            return [
                'ok' => true,
                'title' => $d['title'] ?? $article['title'],
                'excerpt' => $d['excerpt'] ?? $article['excerpt'] ?? '',
                'tags' => $d['tags'] ?? [],
                'category' => $d['category'] ?? $article['category'] ?? '',
                'ai' => true,
            ];
        }
        return self::ruleOptimize($article);
    }

    private static function ruleOptimize(array $article): array {
        $content = strip_tags($article['content'] ?? '');
        return [
            'ok' => true,
            'title' => $article['title'] ?? '',
            'excerpt' => mb_substr($content, 0, 120),
            'tags' => $article['tags'] ?? [],
            'category' => $article['category'] ?? '',
            'ai' => false,
        ];
    }

    /**
     * AI 线索评分 + 跟进建议
     * @param array $lead 线索数据（email/name/company/source/score 等）
     */
    public static function scoreLead(array $lead): array {
        // 规则基础分
        $base = (int)($lead['score'] ?? 0);
        $signals = 0;
        $company = $lead['company'] ?? '';
        $name = $lead['name'] ?? '';
        $source = $lead['source'] ?? '';
        if ($company) $signals += 15;
        if ($name) $signals += 10;
        if (in_array($source, ['organic', 'referral', 'linkedin'], true)) $signals += 10;
        $score = min(100, $base + $signals);

        // AI 建议（可选）
        $advice = '';
        $ai = false;
        if (AiCenter::isConfigured()) {
            $system = '你是 B2B 销售线索分析师。基于线索信息给出跟进建议。';
            $user = "线索信息：\n" . json_encode($lead, JSON_UNESCAPED_UNICODE) . "\n\n请输出 JSON：{\"advice\":\"2-3句跟进建议\",\"priority\":\"high|medium|low\"}";
            $r = AiCenter::json($system, $user, ['temperature' => 0.3]);
            if ($r['ok']) {
                $advice = $r['data']['advice'] ?? '';
                $priority = $r['data']['priority'] ?? 'medium';
                $ai = true;
            }
        }

        $priority = $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');
        return [
            'ok' => true,
            'score' => $score,
            'priority' => $priority,
            'advice' => $advice,
            'signals' => $signals,
            'ai' => $ai,
        ];
    }

    /**
     * 舆情 AI 分析（整合到 SentimentSystem）
     */
    public static function analyzeSentiment(string $topic, array $results): array {
        if (empty($results)) return ['summary' => '暂无数据', 'ai' => false];
        if (!AiCenter::isConfigured()) {
            $titles = array_slice(array_column($results, 'title'), 0, 5);
            return ['summary' => '采集到 ' . count($results) . ' 条，主要提及：' . implode('；', array_slice($titles, 0, 3)), 'tone' => '中性', 'hot_points' => [], 'ai' => false];
        }
        $items = [];
        foreach (array_slice($results, 0, 15) as $r) $items[] = "- {$r['title']} ({$r['url']})";
        $user = "关于「{$topic}」的舆情信息：\n" . implode("\n", $items) . "\n\n输出 JSON：{\"summary\":\"150字内概括\",\"tone\":\"正面|中性|负面\",\"hot_points\":[\"热点\"]}";
        $r = AiCenter::json('你是舆情分析师，输出简洁专业分析。', $user, ['temperature' => 0.3]);
        if ($r['ok']) {
            return ['summary' => $r['data']['summary'] ?? '', 'tone' => $r['data']['tone'] ?? '中性', 'hot_points' => $r['data']['hot_points'] ?? [], 'ai' => true];
        }
        return ['summary' => 'AI 分析暂不可用', 'ai' => false];
    }
}
