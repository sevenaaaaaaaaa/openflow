<?php
/**
 * AskData —— 自然语言问数据（AUDIT-02 / BACKLOG T1-3）
 *
 * 【为什么】不请分析师的一个人，想知道"上周哪个来源转化最好""VIP 占比多少"，
 * 现在得自己翻好几个报表。本模块让他用一句话问，AI 读**已算好的真实指标快照**
 * 直接答——跳过自助拖拽分析那一代。
 *
 * 【安全】AI 只读预先算好的快照(不生成 SQL、不碰原始库)，并被要求"只用给定数据、
 * 不许编数字"。未配 AI → 优雅提示 + 附原始快照。发送可注入便于测试。
 */

if (!function_exists('askdata_gather')) {

    /** 汇集一份紧凑的真实指标快照喂给 AI。每块 try/catch，取不到就略过。 */
    function askdata_gather(int $days = 30): array {
        $snap = [];
        try { if (class_exists('CdpInsight')) $snap = CdpInsight::snapshot($days); } catch (\Throwable $e) {}
        // 成交真相（P0-2 账本）
        try { if (function_exists('growth_conversion_truth')) $snap['conversion_truth'] = growth_conversion_truth(); } catch (\Throwable $e) {}
        // 基础计数
        try {
            if (function_exists('json_read')) {
                $snap['counts'] = [
                    'members' => count(json_read(DATA_DIR . '/members/index.json')),
                    'leads'   => count(json_read(DATA_DIR . '/crm.json')),
                ];
            }
        } catch (\Throwable $e) {}
        return $snap;
    }

    /**
     * 回答一个自然语言问题。$snapshot 可注入（测试）。
     * 返回 ['ok'=>bool,'answer'=>string,'data'=>快照,'error'?]。
     */
    function askdata_answer(string $question, ?array $snapshot = null): array {
        $question = trim($question);
        if ($question === '') return ['ok' => false, 'error' => '请输入问题'];
        $snap = $snapshot ?? askdata_gather();

        // 注入式（测试）或 AiCenter
        $ctx = json_encode($snap, JSON_UNESCAPED_UNICODE);
        try {
            if (isset($GLOBALS['ASKDATA_FN']) && is_callable($GLOBALS['ASKDATA_FN'])) {
                $ans = (string)call_user_func($GLOBALS['ASKDATA_FN'], $question, $snap);
                return ['ok' => $ans !== '', 'answer' => $ans, 'data' => $snap];
            }
            if (!class_exists('AiCenter') || !\AiCenter::isConfigured()) {
                return ['ok' => false, 'error' => 'AI 未配置：到「AI 配置」设置模型后即可用自然语言问数据。', 'data' => $snap];
            }
            $r = \AiCenter::chat(
                '你是网站增长数据分析助手。下面给你一份**已算好的真实指标快照(JSON)**。'
                . '严格只用这份数据回答用户的问题，用中文、简洁、给出具体数字；'
                . '如果数据里没有相关指标，直说"当前快照没有这项数据"，绝不编造数字。',
                "问题：{$question}\n\n数据快照：\n{$ctx}",
                ['max_tokens' => 600]
            );
            if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? 'AI 请求失败', 'data' => $snap];
            $text = trim((string)($r['text'] ?? $r['content'] ?? ''));
            return ['ok' => $text !== '', 'answer' => $text, 'data' => $snap];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'data' => $snap];
        }
    }
}
