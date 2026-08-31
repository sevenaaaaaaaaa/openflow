<?php
/**
 * AI 中心 - 统一 AI 调用封装
 * 支持多 provider（OpenAI 兼容 / Claude / MiniMax 等）
 * 提供：文本生成、结构化 JSON 输出、供应商回退、错误处理
 */
require_once __DIR__ . '/../admin/config.php';

class AiCenter {
    /**
     * 获取配置的 AI 供应商
     */
    public static function providers(): array {
        $ai = json_read(DATA_DIR . '/ai-config.json');
        return $ai['providers'] ?? [];
    }

    public static function defaultProvider(): array {
        $providers = self::providers();
        $ai = json_read(DATA_DIR . '/ai-config.json');
        $defaultId = $ai['default_provider'] ?? '';
        foreach ($providers as $p) {
            if ($p['id'] === $defaultId && $p['enabled']) return $p;
        }
        foreach ($providers as $p) {
            if (!empty($p['enabled']) && !empty($p['api_key'])) return $p;
        }
        return [];
    }

    public static function isConfigured(): bool {
        foreach (self::providers() as $p) {
            if (!empty($p['enabled']) && !empty($p['api_key'])) return true;
        }
        return false;
    }

    /**
     * 分档超时预算（秒）。docs/ROADMAP.md 阶段一。
     *
     * 【为什么要分档】原来所有调用统一等 90 秒。90 秒对后台批处理是合理的，
     * 但对一个**访客正在等的界面**是灾难：供应商一慢，一个请求就占住一个
     * PHP-FPM 处理位一分半，几个并发就能让整站打不开。
     * 所以按"谁在等"分档：访客等不了那么久，也不该让访客的等待拖垮站点。
     */
    public const TIER_TIMEOUTS = ['public' => 12, 'admin' => 30, 'batch' => 90];

    /** 归一化档位，未指定按 admin（后台交互）算。 */
    private static function tierOf(array $opts): string {
        $t = (string)($opts['tier'] ?? 'admin');
        return isset(self::TIER_TIMEOUTS[$t]) ? $t : 'admin';
    }

    /**
     * 统一 AI 调用
     * @param string $system 系统提示词
     * @param string $user 用户内容
     * @param array $opts ['model'=>, 'json'=>bool(期望JSON), 'temperature'=>,
     *                     'feature'=>调用方标识(记账用), 'tier'=>public|admin|batch,
     *                     'timeout'=>显式覆盖分档超时,
     *                     'history'=>[['role'=>'user|assistant','content'=>...], ...] 多轮上文]
     * @return array ['ok'=>bool, 'text'=>string, 'json'=>?array, 'error'=>string,
     *                'budget_exceeded'=>bool(额度用尽时为 true，调用方据此降级)]
     */
    public static function chat(string $system, string $user, array $opts = []): array {
        $tier = self::tierOf($opts);
        $feature = mb_substr((string)($opts['feature'] ?? 'unknown'), 0, 60);

        // ── 保险丝：额度用尽就别再烧了，让调用方降级 ──
        try {
            require_once __DIR__ . '/AiBudget.php';
            $gate = ai_budget_check($tier);
            if (!$gate['allowed']) {
                return ['ok' => false, 'error' => $gate['hint'], 'text' => '',
                        'budget_exceeded' => true, 'reason' => $gate['reason']];
            }
        } catch (\Throwable $e) {}

        // 允许指定供应商（连通性自检要测"选中的那一个"，不能测默认的那个——
        // 否则默认供应商是好的时候，坏的供应商也会报"连接成功"）。
        $provider = [];
        if (!empty($opts['provider_id'])) {
            foreach (self::providers() as $p) {
                if (($p['id'] ?? '') === $opts['provider_id']) { $provider = $p; break; }
            }
            if (empty($provider)) {
                return ['ok' => false, 'error' => '指定的 AI 供应商不存在', 'text' => ''];
            }
        } else {
            $provider = self::defaultProvider();
        }
        if (empty($provider) || empty($provider['api_key'])) {
            return ['ok' => false, 'error' => 'AI 供应商未配置，请在 AI Agent 配置中设置', 'text' => ''];
        }
        $model = $opts['model'] ?? ($provider['model'] ?? 'gpt-4o');
        $temperature = $opts['temperature'] ?? 0.7;
        $wantJson = $opts['json'] ?? false;
        $apiUrl = rtrim($provider['api_url'] ?? '', '/');
        $providerId = $provider['id'] ?? 'openai';

        if ($wantJson) {
            $system .= "\n\n请严格按照以下要求：只输出合法的 JSON，不要输出任何其他文字或 markdown 代码块。";
        }

        // 多轮上文：只接受 user/assistant 两种 role，内容强制转字符串。
        // 上文来自会话历史（可能是用户输入），所以在这里就清洗干净，
        // 不让脏数据带着 system role 混进 messages。
        $history = [];
        foreach ((array)($opts['history'] ?? []) as $h) {
            if (!is_array($h)) continue;
            $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = (string)($h['content'] ?? '');
            if ($content === '') continue;
            $history[] = ['role' => $role, 'content' => $content];
        }

        // 构建 payload
        if ($providerId === 'claude') {
            // Anthropic Messages API 的 system 是**顶层参数**，messages 里只接受
            // user / assistant 两种 role。原来把 system 塞进 messages 会被直接拒掉
            // （400 invalid_request_error），也就是说这个供应商一直是不能用的。
            $payload = json_encode(array_filter([
                'model' => $model,
                'max_tokens' => $opts['max_tokens'] ?? 4096,
                'system' => $system !== '' ? $system : null,
                'temperature' => $temperature,
                'messages' => array_merge($history, [
                    ['role' => 'user', 'content' => $user !== '' ? $user : $system],
                ]),
            ], fn($v) => $v !== null), JSON_UNESCAPED_UNICODE);
            $headers = ['x-api-key: ' . $provider['api_key'], 'anthropic-version: 2023-06-01', 'Content-Type: application/json'];
            $endpoint = $apiUrl . '/messages';
        } elseif ($providerId === 'minimax') {
            $payload = json_encode([
                'model' => $model,
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $system]],
                    $history,
                    [['role' => 'user', 'content' => $user]]
                ),
                'temperature' => $temperature,
                'max_tokens' => $opts['max_tokens'] ?? 4096,
            ], JSON_UNESCAPED_UNICODE);
            $headers = ['Authorization: Bearer ' . $provider['api_key'], 'Content-Type: application/json'];
            $endpoint = $apiUrl . '/text/chatcompletion_v2';
        } else {
            // OpenAI 兼容（OpenAI/DeepSeek/Kimi/GLM/Qwen/Doubao/元宝/OpenRouter）
            $messages = [['role' => 'system', 'content' => $system]];
            foreach ($history as $h) $messages[] = $h;
            if ($user) $messages[] = ['role' => 'user', 'content' => $user];
            $payload = json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $opts['max_tokens'] ?? 4096,
            ], JSON_UNESCAPED_UNICODE);
            $headers = ['Authorization: Bearer ' . $provider['api_key'], 'Content-Type: application/json'];
            $endpoint = $apiUrl . '/chat/completions';
        }

        $timeout = (int)($opts['timeout'] ?? self::TIER_TIMEOUTS[$tier]);
        $started = microtime(true);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            // 连不上就别耗着整个超时预算——握手最多给 5 秒
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        ]);
        $resp = curl_exec($ch);
        $error = curl_error($ch);
        $ms = (int)round((microtime(true) - $started) * 1000);

        if ($error) {
            self::meter($feature, $tier, $providerId, $model, 0, 0, $ms, false, $error);
            return ['ok' => false, 'error' => '请求失败: ' . $error, 'text' => ''];
        }
        $data = json_decode($resp, true);
        if (!$data) {
            self::meter($feature, $tier, $providerId, $model, 0, 0, $ms, false, '响应解析失败');
            return ['ok' => false, 'error' => '响应解析失败', 'text' => mb_substr($resp, 0, 300)];
        }
        // 供应商返回的结构化错误（额度不足、模型不存在、参数不合法……）
        if (isset($data['error'])) {
            $em = is_array($data['error'])
                ? (string)($data['error']['message'] ?? json_encode($data['error'], JSON_UNESCAPED_UNICODE))
                : (string)$data['error'];
            self::meter($feature, $tier, $providerId, $model, 0, 0, $ms, false, $em);
            return ['ok' => false, 'error' => '供应商返回错误: ' . mb_substr($em, 0, 200), 'text' => ''];
        }
        [$inTok, $outTok] = self::usageOf($data);

        // 提取文本（兼容多格式）
        $text = '';
        if ($providerId === 'claude') {
            $text = $data['content'][0]['text'] ?? ($data['content'] ?? '');
        } elseif (isset($data['choices'][0]['message']['content'])) {
            $text = $data['choices'][0]['message']['content'];
        } elseif (isset($data['choices'][0]['text'])) {
            $text = $data['choices'][0]['text'];
        } elseif (isset($data['output_text'])) {
            $text = $data['output_text'];
        } elseif (isset($data['data'][0]['output_text'])) {
            $text = $data['data'][0]['output_text'];
        } elseif (isset($data['output']['text'])) {
            $text = $data['output']['text'];
        } else {
            self::meter($feature, $tier, $providerId, $model, $inTok, $outTok, $ms, false, '无法解析响应格式');
            return ['ok' => false, 'error' => '无法解析响应格式', 'text' => mb_substr($resp, 0, 300)];
        }

        $result = ['ok' => true, 'text' => trim($text), 'json' => null, 'error' => ''];
        if ($wantJson) {
            $json = self::extractJson($text);
            if ($json !== null) $result['json'] = $json;
        }
        self::meter($feature, $tier, $providerId, $model, $inTok, $outTok, $ms, true, '');
        return $result;
    }

    /** 从各家响应里取 token 用量（各家字段名不同，统一成 [输入, 输出]）。 */
    private static function usageOf(array $data): array {
        try {
            require_once __DIR__ . '/AiBudget.php';
            return ai_extract_usage($data);
        } catch (\Throwable $e) { return [0, 0]; }
    }

    /**
     * 记一次调用：既写电表（ai_usage 表，带 token/成本/功能/耗时），
     * 也保留原来的 ai-log.json（后台旧看板还在读它）。
     * 记账失败绝不能影响 AI 调用本身，所以整段吞异常。
     */
    private static function meter(string $feature, string $tier, string $provider, string $model,
                                 int $in, int $out, int $ms, bool $ok, string $error = ''): void {
        try {
            require_once __DIR__ . '/AiBudget.php';
            ai_usage_record([
                'feature' => $feature, 'tier' => $tier, 'provider' => $provider, 'model' => $model,
                'in_tokens' => $in, 'out_tokens' => $out, 'ms' => $ms, 'ok' => $ok, 'error' => $error,
            ]);
        } catch (\Throwable $e) {}
        self::logCall($provider, $model, $ok, $error);
    }

    /**
     * 记录 AI 调用（旧看板 ai-log.json，保留兼容）
     */
    private static function logCall(string $provider, string $model, bool $ok, string $error = ''): void {
        try {
            $log = json_read(DATA_DIR . '/ai-log.json');
            $log[] = ['time' => date('Y-m-d H:i:s'), 'provider' => $provider, 'model' => $model, 'ok' => $ok, 'error' => $error ? mb_substr($error, 0, 100) : ''];
            json_write(DATA_DIR . '/ai-log.json', array_slice($log, -500));
        } catch (Exception $e) {}
    }

    /**
     * 从 AI 输出中提取 JSON（兼容 markdown 包裹 / 前后杂文）
     */
    public static function extractJson(string $text): ?array {
        // 尝试直接解析
        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;
        // 去掉 ```json ... ```（对象和数组都要认——有的提示词要的就是数组）
        if (preg_match('/```(?:json)?\s*([\{\[].*?[\}\]])\s*```/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) return $decoded;
        }
        // 裸提取。谁先出现就先试谁——否则 "结果：[{...}]" 会被 {…} 规则先抢走，
        // 只返回数组里的第一个对象，调用方拿到的结构就错了。
        $posObj = strpos($text, '{');
        $posArr = strpos($text, '[');
        $order = ($posArr !== false && ($posObj === false || $posArr < $posObj))
            ? ['/\[.*\]/s', '/\{.*\}/s']
            : ['/\{.*\}/s', '/\[.*\]/s'];
        foreach ($order as $re) {
            if (preg_match($re, $text, $m)) {
                $decoded = json_decode($m[0], true);
                if (is_array($decoded)) return $decoded;
            }
        }
        return null;
    }

    /**
     * 便捷：结构化 JSON 输出
     */
    public static function json(string $system, string $user, array $opts = []): array {
        $opts['json'] = true;
        $r = self::chat($system, $user, $opts);
        if ($r['ok'] && $r['json'] !== null) {
            return ['ok' => true, 'data' => $r['json'], 'raw' => $r['text']];
        }
        return ['ok' => false, 'error' => $r['error'] ?? 'JSON 解析失败', 'raw' => $r['text'] ?? ''];
    }
}
