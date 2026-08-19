<?php
/**
 * KnowledgeSync — 知识平台双向同步（出站方向）
 * 站内文章发布/更新 → Notion 页面 / 通用 webhook（推送到外部知识库）
 * 入站方向由 admin/ingest.php 承担（飞书/Notion/Obsidian/印象 → 文章草稿）
 */

function ksync_config(): array {
    return json_read(DATA_DIR . '/ingest-config.json');
}

/**
 * 把文章同步到外部平台，记录同步状态到文章元数据
 * 返回：['ok'=>bool, 'platforms'=>['notion'=>true/false, 'webhook'=>bool]]
 */
function ksync_publish_article(array $article, string $action = 'publish'): array {
    $cfg = ksync_config();
    $result = ['ok' => true, 'platforms' => []];

    // 1) Notion 出站（创建/更新页面到数据库）
    if (!empty($cfg['sync_notion']) && !empty($cfg['notion_token'])) {
        $r = ksync_to_notion($article, $cfg, $action);
        $result['platforms']['notion'] = $r['ok'];
        if (!$r['ok']) $result['ok'] = false;
    }
    // 2) 通用出站 webhook
    if (!empty($cfg['sync_webhook'])) {
        $r = ksync_to_webhook($article, $cfg, $action);
        $result['platforms']['webhook'] = $r['ok'];
        if (!$r['ok']) $result['ok'] = false;
    }
    return $result;
}

// Notion API 创建页面到数据库
function ksync_to_notion(array $article, array $cfg, string $action): array {
    $token = $cfg['notion_token'];
    $dbId = $cfg['notion_db_id'] ?? '';
    $endpoint = 'https://api.notion.com/v1/pages';
    $title = $article['title'] ?? '未命名';
    $content = $article['content'] ?? '';
    $properties = [
        'title' => ['title' => [['text' => ['content' => $title]]]],
    ];
    if ($dbId !== '') $properties['数据库'] = ['relation' => [['id' => $dbId]]];
    $payload = ['parent' => ['database_id' => $dbId], 'properties' => $properties, 'children' => [['object' => 'block', 'type' => 'paragraph', 'paragraph' => ['rich_text' => [['text' => ['content' => mb_substr(strip_tags($content), 0, 1900)]]]]]]];
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Notion-Version: 2022-06-28', 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'response' => json_decode($body, true)];
}

// 通用出站 webhook（推送到外部知识库/自动化）
function ksync_to_webhook(array $article, array $cfg, string $action): array {
    $url = $cfg['sync_webhook'];
    $secret = $cfg['sync_secret'] ?? '';
    $payload = json_encode([
        'event' => 'article.' . $action,
        'article_id' => $article['id'] ?? '',
        'title' => $article['title'] ?? '',
        'slug' => $article['slug'] ?? '',
        'content' => mb_substr($article['content'] ?? '', 0, 100000),
        'url' => site_config_get('site_url') . '/article/' . ($article['slug'] ?? ''),
        'updated_at' => $article['updated_at'] ?? date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    $headers = ['Content-Type: application/json'];
    if ($secret !== '') $headers[] = 'X-Sync-Signature: ' . hash_hmac('sha256', $payload, $secret);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code];
}

// 记录同步状态到文章
function ksync_mark_synced(string $articleId, array $platforms, string $action): void {
    $article = get_article($articleId);
    if (!$article) return;
    $synced = (array)($article['synced_to'] ?? []);
    foreach ($platforms as $p => $ok) {
        if ($ok) $synced[$p] = $action . ':' . date('Y-m-d H:i:s');
    }
    save_article($articleId, ['synced_to' => $synced, 'updated_at' => date('Y-m-d H:i:s')]);
}
