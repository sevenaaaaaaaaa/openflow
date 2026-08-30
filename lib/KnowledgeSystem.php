<?php
/**
 * 公司知识库 — 供 AI agent 检索（RAG）
 * 文档存储 + 关键词检索 + AI 增强
 */

function knowledge_file(): string { return DATA_DIR . '/knowledge/index.json'; }
function knowledge_get(): array { return json_read(knowledge_file()); }
function knowledge_save(array $docs): bool {
    if (!is_dir(dirname(knowledge_file()))) mkdir(dirname(knowledge_file()), 0755, true);
    return json_write(knowledge_file(), $docs);
}

// 添加文档
function knowledge_add(array $doc): void {
    $docs = knowledge_get();
    $docs[] = array_merge([
        'id' => 'k_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'title' => '', 'content' => '', 'category' => 'general',
        'tags' => [], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ], $doc);
    knowledge_save($docs);
}

/**
 * 幂等 upsert：按 (source, source_id) 去重。已存在则原地更新（保留 id/created_at），
 * 否则新增。用于"内容发布→知识库"这类会反复触发的回流，避免重复堆积。
 * 返回文档 id。
 */
function knowledge_upsert(array $doc): string {
    $source   = (string)($doc['source'] ?? '');
    $sourceId = (string)($doc['source_id'] ?? '');
    $docs = knowledge_get();
    if ($source !== '' && $sourceId !== '') {
        foreach ($docs as $i => $d) {
            if (($d['source'] ?? '') === $source && (string)($d['source_id'] ?? '') === $sourceId) {
                $docs[$i] = array_merge($d, $doc, [
                    'id'         => $d['id'],
                    'created_at' => $d['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                knowledge_save($docs);
                return (string)$d['id'];
            }
        }
    }
    $id = 'k_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $docs[] = array_merge([
        'id' => $id, 'title' => '', 'content' => '', 'category' => 'general',
        'tags' => [], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ], $doc);
    knowledge_save($docs);
    return $id;
}

/** 按来源移除文档（如文章下架/删除时把它从知识库撤下）。返回移除条数。 */
function knowledge_remove_source(string $source, string $sourceId): int {
    $docs = knowledge_get();
    $before = count($docs);
    $docs = array_values(array_filter($docs, fn($d) =>
        !(($d['source'] ?? '') === $source && (string)($d['source_id'] ?? '') === (string)$sourceId)));
    if (count($docs) !== $before) knowledge_save($docs);
    return $before - count($docs);
}

/**
 * 内部知识回流：把一篇文章同步进站内知识库（喂站点 Agent / MCP）。
 * 已发布 → upsert；非发布（草稿/下架/回收站）→ 从知识库撤下。
 * 幂等、可反复调用；纯读写本地 JSON，无外部依赖。
 */
function knowledge_ingest_article(array $article): array {
    $id = (string)($article['id'] ?? '');
    if ($id === '') return ['ok' => false, 'reason' => 'no_id'];
    $status = (string)($article['status'] ?? '');
    $trashed = !empty($article['trashed']) || !empty($article['deleted_at']);

    if ($status !== 'published' || $trashed) {
        $n = knowledge_remove_source('article', $id);
        return ['ok' => true, 'action' => 'removed', 'removed' => $n];
    }

    // HTML → 纯文本：标签换成空格（保留段落边界、避免相邻块的词粘连），再压空白、限长
    $text = (string)($article['content'] ?? '');
    $text = trim(preg_replace('/\s+/u', ' ', preg_replace('/<[^>]+>/', ' ', $text)));
    if (function_exists('mb_substr') && mb_strlen($text) > 4000) $text = mb_substr($text, 0, 4000);

    $tags = $article['tags'] ?? [];
    if (is_string($tags)) $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));

    $kid = knowledge_upsert([
        'source'    => 'article',
        'source_id' => $id,
        'title'     => (string)($article['title'] ?? ''),
        'content'   => $text,
        'category'  => (string)($article['category'] ?? 'article'),
        'tags'      => array_values((array)$tags),
        'url'       => '/' . ltrim((string)($article['slug'] ?? ('article/' . $id)), '/'),
    ]);
    return ['ok' => true, 'action' => 'upserted', 'knowledge_id' => $kid];
}

// 检索相关知识（简单关键词匹配，按命中数排序）
function knowledge_search(string $query, int $limit = 5): array {
    $docs = knowledge_get();
    $q = mb_strtolower(trim($query));
    // 提取中文关键词（2-4字词）
    $keywords = [];
    preg_match_all('/[\x{4e00}-\x{9fa5}]{2,4}/u', $q, $m);
    $keywords = array_slice(array_values(array_unique($m[0])), 0, 8);

    $results = [];
    foreach ($docs as $d) {
        $hay = mb_strtolower(($d['title'] ?? '') . ' ' . ($d['content'] ?? '') . ' ' . implode(' ', $d['tags'] ?? []));
        $score = 0;
        // 全词匹配（英文/数字）
        if ($q && mb_strpos($hay, $q) !== false) $score += 10;
        // 关键词命中
        foreach ($keywords as $kw) {
            if (mb_strpos($hay, $kw) !== false) $score += 1;
        }
        // 标题命中加权
        $titleLower = mb_strtolower($d['title'] ?? '');
        foreach ($keywords as $kw) if (mb_strpos($titleLower, $kw) !== false) $score += 3;
        if ($score > 0) {
            $results[] = ['doc' => $d, 'score' => $score];
        }
    }
    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_map(fn($r) => $r['doc'], array_slice($results, 0, $limit));
}

// 构建 RAG 上下文（给 AI 的补充知识）
function knowledge_build_context(string $query, int $limit = 3): string {
    $docs = knowledge_search($query, $limit);
    if (empty($docs)) return '';
    $ctx = "以下是相关知识库内容，请优先参考这些信息回答：\n";
    foreach ($docs as $i => $d) {
        $snippet = mb_substr(strip_tags($d['content'] ?? ''), 0, 500);
        $n = $i + 1;
        $ctx .= "\n【{$n}】{$d['title']}：" . $snippet . "\n";
    }
    return $ctx;
}

// ═══ 语义检索升级（embedding）═══
// 缓存文件：data/knowledge/embeddings.json
function knowledge_emb_file(): string { return DATA_DIR . '/knowledge/embeddings.json'; }
function knowledge_emb_cache(): array { return json_read(knowledge_emb_file()); }

// 获取 AI 供应商的 embedding 能力（从 ai-config 读取）
function knowledge_emb_provider(): ?array {
    $ai = json_read(DATA_DIR . '/ai-config.json');
    foreach (($ai['providers'] ?? []) as $p) {
        if (!empty($p['enabled']) && !empty($p['api_key'])) return $p;
    }
    return null;
}

// 调用 provider 获取文本向量
function knowledge_embed_text(string $text): ?array {
    $p = knowledge_emb_provider();
    if (!$p) return null;
    $apiUrl = rtrim($p['api_url'], '/');
    $model = $p['embedding_model'] ?? 'text-embedding-ada-002';
    $payload = json_encode(['model' => $model, 'input' => mb_substr($text, 0, 8000)]);

    if ($p['id'] === 'claude' || $p['id'] === 'openclaude') {
        // Anthropic 无 embeddings API，走兼容（降级 null）
        return null;
    }
    $headers = ['Authorization: Bearer ' . $p['api_key'], 'Content-Type: application/json'];
    $endpoint = $apiUrl . '/embeddings';
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $resp = curl_exec($ch);
    if (!$resp) return null;
    $data = json_decode($resp, true);
    if (isset($data['data'][0]['embedding'])) return $data['data'][0]['embedding'];
    // deepseek 等无 embedding → 降级
    return null;
}

// 余弦相似度
function knowledge_cos_sim(array $a, array $b): float {
    if (count($a) !== count($b) || count($a) === 0) return 0;
    $dot = 0; $na = 0; $nb = 0;
    foreach ($a as $i => $v) {
        $dot += $v * $b[$i];
        $na += $v * $v;
        $nb += $b[$i] * $b[$i];
    }
    if ($na === 0 || $nb === 0) return 0;
    return $dot / (sqrt($na) * sqrt($nb));
}

// 语义检索：优先 embedding，失败回退关键词
function knowledge_search_semantic(string $query, int $limit = 5): array {
    $docs = knowledge_get();
    if (empty($docs)) return [];

    // 尝试 embedding
    $qVec = knowledge_embed_text($query);
    if ($qVec !== null) {
        $cache = knowledge_emb_cache();
        $results = [];
        foreach ($docs as $d) {
            $did = $d['id'];
            // 缓存命中则复用
            $vec = $cache[$did] ?? null;
            if ($vec === null) {
                $vec = knowledge_embed_text(($d['title'] ?? '') . ' ' . mb_substr(strip_tags($d['content'] ?? ''), 0, 2000));
                if ($vec !== null) { $cache[$did] = $vec; }
            }
            if ($vec !== null) {
                $results[] = ['doc' => $d, 'score' => knowledge_cos_sim($qVec, $vec)];
            }
        }
        if (!empty($results)) {
            // 保存缓存
            if (!is_dir(dirname(knowledge_emb_file()))) mkdir(dirname(knowledge_emb_file()), 0755, true);
            json_write(knowledge_emb_file(), $cache);
            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
            $out = array_slice(array_map(fn($r) => $r['doc'], $results), 0, $limit);
            // 过滤过低相似度
            $out = array_values(array_filter($out, function ($d, $i) use ($results) { return ($results[$i]['score'] ?? 0) > 0.3; }, ARRAY_FILTER_USE_BOTH));
            return $out ?: knowledge_search($query, $limit);
        }
    }
    return knowledge_search($query, $limit);
}

// 带引用的 RAG 上下文（AI 回复可标注来源）
function knowledge_build_context_cited(string $query, int $limit = 3): array {
    $docs = knowledge_search_semantic($query, $limit);
    if (empty($docs)) return ['context' => '', 'sources' => []];
    $ctx = "以下是相关知识库内容，请优先参考这些信息回答，并在合适处标注来源编号 [1][2]…：\n";
    $sources = [];
    foreach ($docs as $i => $d) {
        $snippet = mb_substr(strip_tags($d['content'] ?? ''), 0, 500);
        $n = $i + 1;
        $ctx .= "\n[{$n}] {$d['title']}：{$snippet}\n";
        $sources[] = ['id' => $d['id'], 'title' => $d['title'], 'category' => $d['category'] ?? ''];
    }
    return ['context' => $ctx, 'sources' => $sources];
}
