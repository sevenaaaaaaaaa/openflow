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

if (!function_exists('askdata_bi_answer')) {
    /**
     * 对话式 BI：AI 只负责「选数据集 + 说人话 + 建议追问」，数字全部来自真实数据集。
     * 返回 ['ok','answer','chart'=>{type,title,dataset,unit,points}|null,'followups'=>[],'data'=>[]]。
     */
    function askdata_bi_answer(string $question, ?array $datasets = null): array {
        $question = trim($question);
        if ($question === '') return ['ok' => false, 'error' => '请输入问题'];
        if (!function_exists('bi_catalog')) require_once __DIR__ . '/BiData.php';
        $sets = $datasets ?? bi_datasets();
        if (empty($sets)) return ['ok' => false, 'error' => '暂无可用数据', 'data' => []];

        // 注入式（测试）
        if (isset($GLOBALS['ASKDATA_BI_FN']) && is_callable($GLOBALS['ASKDATA_BI_FN'])) {
            $r = call_user_func($GLOBALS['ASKDATA_BI_FN'], $question, $sets);
            return is_array($r) ? $r : ['ok' => false, 'error' => 'bad inject'];
        }

        if (!class_exists('AiCenter') || !\AiCenter::isConfigured()) {
            return ['ok' => false, 'error' => 'AI 未配置：到「AI 配置」设置模型后即可用自然语言问数据。', 'data' => $sets];
        }

        $catalog = bi_catalog($sets);
        $system = "你是资深经营分析师。下面是本站**真实数据集目录**(key/label/type/unit + 最近样本)：\n"
            . json_encode($catalog, JSON_UNESCAPED_UNICODE) . "\n\n"
            . "根据用户问题，选择**最合适的一个数据集**来可视化，并用中文回答（引用数据里的真实数字）。\n"
            . "严格输出 JSON：{\"answer\":\"...\",\"dataset\":\"数据集key或空字符串\",\"chart_type\":\"line|bar|none\",\"title\":\"图表标题(短)\",\"followups\":[\"追问1\",\"追问2\"]}\n"
            . "规则：只能选目录里存在的 dataset key；问趋势用 timeseries→line；问分布/占比/对比用 breakdown→bar；"
            . "目录无法回答时 dataset 设为空、chart_type=none，并在 answer 里说明缺什么。不要编造任何数字。";

        try {
            $r = \AiCenter::json($system, "问题：{$question}", ['max_tokens' => 800, 'feature' => 'ask_data_bi', 'tier' => 'admin']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'data' => $sets];
        }
        if (empty($r['ok']) || empty($r['data'])) return ['ok' => false, 'error' => $r['error'] ?? 'AI 请求失败', 'data' => $sets];

        return askdata_bi_shape((array)$r['data'], $sets);
    }

    /** 校验 AI 返回并绑定真实数据 → 组装最终结果 */
    function askdata_bi_shape(array $d, array $sets): array {
        $byKey = [];
        foreach ($sets as $s) $byKey[(string)$s['key']] = $s;
        $key = (string)($d['dataset'] ?? '');
        $type = (string)($d['chart_type'] ?? 'none');
        if (!in_array($type, ['line', 'bar', 'none'], true)) $type = 'none';
        $chart = null;
        if ($key !== '' && isset($byKey[$key]) && $type !== 'none') {
            $s = $byKey[$key];
            if ($type === 'line' && ($s['type'] ?? '') !== 'timeseries') $type = 'bar';
            $chart = ['type' => $type, 'title' => mb_substr((string)($d['title'] ?? $s['label']), 0, 40),
                      'dataset' => $key, 'label' => (string)$s['label'], 'unit' => (string)$s['unit'], 'points' => array_values((array)$s['points'])];
        }
        $followups = array_values(array_filter(array_map('strval', array_slice((array)($d['followups'] ?? []), 0, 3))));
        return ['ok' => true, 'answer' => (string)($d['answer'] ?? ''), 'chart' => $chart, 'followups' => $followups, 'data' => $sets];
    }
    }

    /**
     * 回答一个自然语言问题（旧版：对预计算快照直接作答；页面已改用 BI）。$snapshot 可注入（测试）。
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
                ['max_tokens' => 600, 'feature' => 'ask_data', 'tier' => 'admin']
            );
            if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? 'AI 请求失败', 'data' => $snap];
            $text = trim((string)($r['text'] ?? $r['content'] ?? ''));
            return ['ok' => $text !== '', 'answer' => $text, 'data' => $snap];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'data' => $snap];
        }
    }
}
