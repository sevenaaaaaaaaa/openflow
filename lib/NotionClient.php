<?php
/**
 * NotionClient — Notion API 通用客户端（支持分页/速率限制/CRUD）
 */
class NotionClient {
    private string $token;
    private string $version = '2022-06-28';
    private float $lastRequestTime = 0;

    public function __construct(string $token) {
        $this->token = $token;
    }

    /**
     * 查询数据库（自动分页，返回所有结果）
     */
    public function queryDatabase(string $dbId, array $filter = [], array $sorts = [], int $limit = 0): array {
        $results = [];
        $startCursor = null;
        do {
            $body = ['page_size' => 100];
            if ($filter) $body['filter'] = $filter;
            if ($sorts) $body['sorts'] = $sorts;
            if ($startCursor) $body['start_cursor'] = $startCursor;
            $r = $this->request('POST', "/databases/{$dbId}/query", $body);
            if (!($r['ok'] ?? false)) return $r;
            $results = array_merge($results, $r['data']['results'] ?? []);
            if ($limit > 0 && count($results) >= $limit) { $results = array_slice($results, 0, $limit); break; }
            $hasMore = $r['data']['has_more'] ?? false;
            $startCursor = $r['data']['next_cursor'] ?? null;
        } while ($hasMore && $startCursor);
        return ['ok' => true, 'results' => $results, 'count' => count($results)];
    }

    /**
     * 创建页面
     */
    public function createPage(string $dbId, array $properties, array $children = []): array {
        $body = ['parent' => ['database_id' => $dbId], 'properties' => $properties];
        if ($children) $body['children'] = $children;
        return $this->request('POST', '/pages', $body);
    }

    /**
     * 更新页面属性
     */
    public function updatePage(string $pageId, array $properties): array {
        return $this->request('PATCH', "/pages/{$pageId}", ['properties' => $properties]);
    }

    /**
     * 获取页面
     */
    public function getPage(string $pageId): array {
        return $this->request('GET', "/pages/{$pageId}");
    }

    /**
     * 获取数据库结构
     */
    public function getDatabase(string $dbId): array {
        return $this->request('GET', "/databases/{$dbId}");
    }

    /**
     * 创建数据库
     */
    public function createDatabase(string $parentPageId, string $title, array $properties): array {
        $body = [
            'parent' => ['type' => 'page_id', 'page_id' => $parentPageId],
            'title' => [['type' => 'text', 'text' => ['content' => $title]]],
            'properties' => $properties,
        ];
        return $this->request('POST', '/databases', $body);
    }

    /**
     * 添加子块到页面
     */
    public function appendBlocks(string $pageId, array $blocks): array {
        return $this->request('PATCH', "/blocks/{$pageId}/children", ['children' => $blocks]);
    }

    /**
     * 搜索页面
     */
    public function search(string $query, string $filter = '', int $limit = 100): array {
        $body = ['query' => $query, 'page_size' => min(100, $limit)];
        if ($filter === 'database') $body['filter'] = ['value' => 'database', 'property' => 'object'];
        return $this->request('POST', '/search', $body);
    }

    // ─── Notion 属性构建辅助 ───

    public static function propTitle(string $text): array {
        return ['title' => [['text' => ['content' => mb_substr($text, 0, 2000)]]]];
    }
    public static function propRichText(string $text, int $maxLen = 2000): array {
        if ($text === '') return ['rich_text' => []];
        return ['rich_text' => [['text' => ['content' => mb_substr($text, 0, $maxLen)]]]];
    }
    public static function propSelect(string $name): array {
        return ['select' => ['name' => $name]];
    }
    public static function propMultiSelect(array $tags): array {
        return ['multi_select' => array_map(fn($t) => ['name' => mb_substr($t, 0, 100)], $tags)];
    }
    public static function propNumber($n): array {
        return ['number' => (float)$n];
    }
    public static function propCheckbox(bool $v): array {
        return ['checkbox' => $v];
    }
    public static function propUrl(string $url): array {
        return ['url' => $url ?: null];
    }
    public static function propDate(string $start, ?string $end = null): array {
        $d = ['start' => $start];
        if ($end) $d['end'] = $end;
        return ['date' => $d];
    }
    public static function propStatus(string $name): array {
        return ['status' => ['name' => $name]];
    }

    // ─── Notion Block 构建辅助 ───

    public static function blockHeading(string $text, int $level = 2): array {
        $type = "heading_{$level}";
        return ['object' => 'block', 'type' => $type, $type => ['rich_text' => [['text' => ['content' => $text]]]]];
    }
    public static function blockParagraph(string $text): array {
        return ['object' => 'block', 'type' => 'paragraph', 'paragraph' => ['rich_text' => [['text' => ['content' => mb_substr($text, 0, 2000)]]]]];
    }
    public static function blockBulleted(string $text): array {
        return ['object' => 'block', 'type' => 'bulleted_list_item', 'bulleted_list_item' => ['rich_text' => [['text' => ['content' => $text]]]]];
    }
    public static function blockDivider(): array {
        return ['object' => 'block', 'type' => 'divider', 'divider' => new \stdClass()];
    }

    // ─── Notion 属性提取辅助 ───

    public static function getTitle(array $page): string {
        $props = $page['properties'] ?? [];
        foreach ($props as $p) {
            if (($p['type'] ?? '') === 'title') {
                return implode('', array_map(fn($t) => $t['plain_text'] ?? '', $p['title'] ?? []));
            }
        }
        return '';
    }
    public static function getRichText(array $prop): string {
        return implode('', array_map(fn($t) => $t['plain_text'] ?? '', $prop['rich_text'] ?? []));
    }
    public static function getSelect(array $prop): string {
        return $prop['select']['name'] ?? '';
    }
    public static function getMultiSelect(array $prop): array {
        return array_map(fn($s) => $s['name'] ?? '', $prop['multi_select'] ?? []);
    }
    public static function getNumber(array $prop): float {
        return (float)($prop['number'] ?? 0);
    }
    public static function getCheckbox(array $prop): bool {
        return !empty($prop['checkbox']);
    }
    public static function getUrl(array $prop): string {
        return $prop['url'] ?? '';
    }
    public static function getDateStart(array $prop): string {
        return $prop['date']['start'] ?? '';
    }

    // ─── API 请求 ───

    private function request(string $method, string $path, ?array $body = null): array {
        // 速率限制：每秒最多 3 请求
        $now = microtime(true);
        $wait = 0.34 - ($now - $this->lastRequestTime);
        if ($wait > 0) usleep((int)($wait * 1e6));

        $url = 'https://api.notion.com/v1' . $path;
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Notion-Version: ' . $this->version,
            'Content-Type: application/json',
        ];
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers];
        if (in_array($method, ['POST', 'PATCH', 'PUT'])) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        if ($method === 'PATCH') $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        if ($method === 'DELETE') $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $this->lastRequestTime = microtime(true);

        if ($error) return ['ok' => false, 'error' => "cURL: {$error}"];
        $data = json_decode($response, true);
        if ($code >= 200 && $code < 300) return ['ok' => true, 'data' => $data, 'code' => $code];
        return ['ok' => false, 'error' => $data['message'] ?? "HTTP {$code}", 'code' => $code, 'response' => $data];
    }
}
