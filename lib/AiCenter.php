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
     * 统一 AI 调用
     * @param string $system 系统提示词
     * @param string $user 用户内容
     * @param array $opts ['model'=>, 'json'=>bool(期望JSON), 'temperature'=>]
     * @return array ['ok'=>bool, 'text'=>string, 'json'=>?array, 'error'=>string]
     */
    public static function chat(string $system, string $user, array $opts = []): array {
        $provider = self::defaultProvider();
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

        // 构建 payload
        if ($providerId === 'claude') {
            $payload = json_encode([
                'model' => $model,
                'max_tokens' => $opts['max_tokens'] ?? 4096,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ], JSON_UNESCAPED_UNICODE);
            $headers = ['x-api-key: ' . $provider['api_key'], 'anthropic-version: 2023-06-01', 'Content-Type: application/json'];
            $endpoint = $apiUrl . '/messages';
        } elseif ($providerId === 'minimax') {
            $payload = json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => $temperature,
                'max_tokens' => 4096,
            ], JSON_UNESCAPED_UNICODE);
            $headers = ['Authorization: Bearer ' . $provider['api_key'], 'Content-Type: application/json'];
            $endpoint = $apiUrl . '/text/chatcompletion_v2';
        } else {
            // OpenAI 兼容（OpenAI/DeepSeek/Kimi/GLM/Qwen/Doubao/元宝/OpenRouter）
            $messages = [['role' => 'system', 'content' => $system]];
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

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $opts['timeout'] ?? 90,
        ]);
        $resp = curl_exec($ch);
        $error = curl_error($ch);

        if ($error) {
            return ['ok' => false, 'error' => '请求失败: ' . $error, 'text' => ''];
        }
        $data = json_decode($resp, true);
        if (!$data) {
            return ['ok' => false, 'error' => '响应解析失败', 'text' => mb_substr($resp, 0, 300)];
        }

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
            return ['ok' => false, 'error' => '无法解析响应格式', 'text' => mb_substr($resp, 0, 300)];
        }

        $result = ['ok' => true, 'text' => trim($text), 'json' => null, 'error' => ''];
        if ($wantJson) {
            $json = self::extractJson($text);
            if ($json !== null) $result['json'] = $json;
        }
        self::logCall($providerId, $model, $result['ok'], $result['error'] ?? '');
        return $result;
    }

    /**
     * 记录 AI 调用（用量看板）
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
        // 去掉 ```json ... ```
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) return $decoded;
        }
        // 提取第一个 {...}
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
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
