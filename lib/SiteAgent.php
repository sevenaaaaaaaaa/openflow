<?php
/**
 * SiteAgent —— 会卖的站点 Agent（AUDIT-04 创新三 / BACKLOG T1-10）
 *
 * 【为什么】传统客服（工单/在线聊天）是成本中心；AI 时代的客服该是收入触点。
 * 本模块用站内知识库（T1-4 回流进去的文章）现答访客问题，答完顺势给行动（留资/
 * 收款/预约），搞不定就转人工并落一条 CRM 线索——客服、售前、成交在一个对话里闭环。
 *
 * 【接地】强制只用站内知识作答（knowledge_search 命中的片段），命中不足则不硬答，
 * 转人工。未配 AI → 直接给知识片段 + 转人工，不假装能聊。
 */

if (!function_exists('siteagent_answer')) {

    /** 检索站内知识片段。可注入 $GLOBALS['SITEAGENT_KB_FN'] 便于测试。 */
    function siteagent_retrieve(string $q, int $limit = 3): array {
        try {
            if (isset($GLOBALS['SITEAGENT_KB_FN']) && is_callable($GLOBALS['SITEAGENT_KB_FN'])) {
                return (array)call_user_func($GLOBALS['SITEAGENT_KB_FN'], $q, $limit);
            }
            if (function_exists('knowledge_search')) return knowledge_search($q, $limit);
        } catch (\Throwable $e) {}
        return [];
    }

    /**
     * 回答一个访客问题。
     * 返回 ['ok','answer','sources'=>[{title,url}],'handoff'=>bool,'cta'=>?]
     *  - handoff=true 表示建议转人工（知识不足或 AI 不可用）
     */
    function siteagent_answer(string $question, array $ctx = []): array {
        $question = trim($question);
        if ($question === '') return ['ok' => false, 'error' => '请输入问题', 'handoff' => false];

        $docs = siteagent_retrieve($question);
        $sources = [];
        foreach ($docs as $d) {
            $sources[] = ['title' => (string)($d['title'] ?? ''), 'url' => (string)($d['url'] ?? '')];
        }

        // 知识不足 → 不硬答，转人工
        if (!$docs) {
            return [
                'ok' => true, 'handoff' => true, 'sources' => [],
                'answer' => '这个问题我在站内资料里没找到可靠答案，帮你转给人工跟进——留个联系方式，我们尽快回你。',
                'cta' => siteagent_cta($ctx),
            ];
        }

        // 拼接接地上下文
        $kb = '';
        foreach ($docs as $d) {
            $kb .= "【" . (string)($d['title'] ?? '') . "】\n" . mb_substr((string)($d['content'] ?? ''), 0, 1200) . "\n\n";
        }

        // AI 现答（可注入）
        try {
            if (isset($GLOBALS['SITEAGENT_AI_FN']) && is_callable($GLOBALS['SITEAGENT_AI_FN'])) {
                $t = trim((string)call_user_func($GLOBALS['SITEAGENT_AI_FN'], $question, $kb));
                if ($t !== '') return ['ok' => true, 'handoff' => false, 'answer' => $t, 'sources' => $sources, 'cta' => siteagent_cta($ctx)];
            } elseif (class_exists('AiCenter') && \AiCenter::isConfigured()) {
                $r = \AiCenter::chat(
                    '你是这个网站的客服助手。**只能**根据下面提供的站内资料回答，'
                    . '资料里没有的就说"这个我不确定，帮你转人工"，绝不编造。中文、简洁、直接给结论。',
                    "访客问题：{$question}\n\n站内资料：\n{$kb}",
                    ['max_tokens' => 600, 'feature' => 'site_agent', 'tier' => 'public']
                );
                $t = trim((string)($r['text'] ?? $r['content'] ?? ''));
                if (!empty($r['ok']) && $t !== '') {
                    $handoff = (mb_strpos($t, '转人工') !== false || mb_strpos($t, '不确定') !== false);
                    return ['ok' => true, 'handoff' => $handoff, 'answer' => $t, 'sources' => $sources, 'cta' => siteagent_cta($ctx)];
                }
            }
        } catch (\Throwable $e) {}

        // 无 AI：给检索到的片段 + 转人工，不假装能聊
        $first = $docs[0] ?? [];
        return [
            'ok' => true, 'handoff' => true, 'sources' => $sources,
            'answer' => "站内找到相关资料：《" . (string)($first['title'] ?? '') . "》\n\n"
                      . mb_substr(trim((string)($first['content'] ?? '')), 0, 200) . '…',
            'cta' => siteagent_cta($ctx),
        ];
    }

    /** 答完顺势给的行动（会卖的关键）。 */
    function siteagent_cta(array $ctx = []): array {
        if (!empty($ctx['logged_in'])) {
            return ['text' => '需要我按你的情况给个方案吗？', 'action' => 'consult', 'url' => '/consultation'];
        }
        return ['text' => '留个邮箱，我把完整方案发给你', 'action' => 'lead', 'url' => ''];
    }

    /**
     * 转人工：把这条会话落成 CRM 线索 + 收件箱记录。
     */
    function siteagent_handoff(string $question, string $email, string $name = ''): array {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => '请填写有效邮箱'];
        try {
            if (function_exists('crm_ensure_lead')) {
                crm_ensure_lead($email, $name);
                if (function_exists('crm_update_lead')) {
                    crm_update_lead($email, ['source' => '站点Agent', 'note' => mb_substr($question, 0, 200)]);
                }
            }
            if (function_exists('flow_handle')) {
                @flow_handle('form_submit', ['email' => $email, 'label' => '站点Agent转人工', 'props' => ['question' => mb_substr($question, 0, 200)]]);
            }
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
