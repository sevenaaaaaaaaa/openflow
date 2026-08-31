<?php
/**
 * 舆情监测系统 — 复刻 BettaFish(微舆) 多 Agent 深度搜索思路
 *
 * 工作流：
 *   监控主题 → 关键词优化(AI) → 多源搜索(Bing/百度/RSS) → 反思改进查询(多轮)
 *   → 情感分析(AI/词典) → 聚类去重 → 趋势/热词/风险 → 舆情报告
 */

function sent_file(): string { return DATA_DIR . '/sentiment/data.json'; }
function sent_db_file(): string { return DATA_DIR . '/db/openflow.db'; }

// ─── 监控主题管理 ───
function sent_get(): array {
    return json_read(sent_file());
}
function sent_save(array $data): bool {
    if (!is_dir(dirname(sent_file()))) mkdir(dirname(sent_file()), 0755, true);
    return json_write(sent_file(), $data);
}
function sent_topics(): array {
    $d = sent_get();
    return $d['topics'] ?? [];
}

// 添加监控主题
function sent_add_topic(string $name, array $keywords = []): void {
    $d = sent_get();
    $d['topics'][] = [
        'id' => 'st_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'name' => $name,
        'keywords' => $keywords ?: [$name],
        'created_at' => date('Y-m-d H:i:s'),
        'last_scan' => '',
    ];
    sent_save($d);
}

// ─── 多源搜索聚合 ───
// Bing Web Search / 百度 / RSS 并发抓取
function sent_search(string $query, array $sources = ['bing','baidu','rss']): array {
    $results = [];
    foreach ($sources as $src) {
        switch ($src) {
            case 'bing':
                $results = array_merge($results, sent_search_bing($query));
                break;
            case 'baidu':
                $results = array_merge($results, sent_search_baidu($query));
                break;
            case 'rss':
                $results = array_merge($results, sent_search_rss($query));
                break;
        }
    }
    // 去重（按标题）
    $seen = [];
    $unique = [];
    foreach ($results as $r) {
        $key = md5($r['title'] ?? '');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $unique[] = $r;
    }
    return $unique;
}

// Bing Web Search（免费额度）
function sent_search_bing(string $query): array {
    $key = sent_settings()['bing_key'] ?? '';
    if (empty($key)) return [];
    $url = 'https://api.bing.microsoft.com/v7.0/search?q=' . urlencode($query) . '&count=20&mkt=zh-CN';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Ocp-Apim-Subscription-Key: '.$key], CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false]);
    $resp = json_decode(curl_exec($ch), true);
    $results = [];
    foreach (($resp['webPages']['value'] ?? []) as $r) {
        $results[] = [
            'source' => 'bing', 'title' => $r['name'] ?? '', 'url' => $r['url'] ?? '',
            'snippet' => strip_tags($r['snippet'] ?? ''), 'date' => $r['dateLastCrawled'] ?? '',
        ];
    }
    return $results;
}

// 百度搜索（HTML 抓取标题链接）
function sent_search_baidu(string $query): array {
    $url = 'https://www.baidu.com/s?wd=' . urlencode($query) . '&rn=20';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; OpenFlow-Sentiment)', CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false]);
    $html = curl_exec($ch);
    if (empty($html)) return [];
    $results = [];
    if (preg_match_all('/<h3[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>.*?<\/h3>\s*<div[^>]*class="[^"]*c-abstract[^"]*"[^>]*>(.*?)<\/div>/s', $html, $m, PREG_SET_ORDER)) {
        foreach (array_slice($m, 0, 15) as $r) {
            $results[] = ['source'=>'baidu', 'title'=>strip_tags($r[2]), 'url'=>html_entity_decode($r[1]), 'snippet'=>strip_tags($r[3]), 'date'=>''];
        }
    }
    return $results;
}

// RSS 源搜索（按关键词过滤）
function sent_search_rss(string $query): array {
    require_once __DIR__ . '/GeoSystem.php';
    $items = geo_fetch_all(20);
    $results = [];
    foreach ($items as $it) {
        $hay = ($it['item']['title'] ?? '') . ' ' . ($it['item']['description'] ?? '');
        if (mb_strpos($hay, $query) !== false) {
            $results[] = ['source'=>($it['source'] ?? 'rss'), 'title'=>$it['item']['title'] ?? '', 'url'=>$it['item']['link'] ?? '', 'snippet'=>mb_substr($it['item']['description'] ?? '', 0, 200), 'date'=>$it['item']['pubDate'] ?? ''];
        }
    }
    return $results;
}

// ─── 关键词优化（AI，复刻 keyword_optimizer）───
function sent_optimize_keywords(string $topic): array {
    $text = sent_ai_call("你是舆情分析专家。针对主题「{$topic}」，生成 6 个高相关的中文搜索关键词（覆盖正面/负面/中性角度），输出严格 JSON 数组：[\"关键词1\",\"关键词2\"]");
    $json = $text;
    if (preg_match('/\[.*\]/s', $text, $m)) $json = $m[0];
    $keywords = json_decode($json, true);
    return is_array($keywords) ? array_slice(array_filter($keywords), 0, 6) : [$topic];
}

// ─── 反思改进查询（复刻 ReflectionNode：多轮搜索）───
function sent_refine_query(string $topic, array $initialResults): string {
    if (empty($initialResults)) return $topic;
    $snippets = '';
    foreach (array_slice($initialResults, 0, 8) as $r) $snippets .= "· " . mb_substr($r['title'] ?? '', 0, 60) . "\n";
    $text = sent_ai_call("基于以下初步搜索结果，生成一个更聚焦的二次搜索查询，用于深挖「{$topic}」的舆情细节（如争议点/最新进展/负面信息）。\n\n初步结果：\n{$snippets}\n\n只输出一个搜索查询词，不要其他文字。");
    return trim($text) ?: $topic;
}

// ─── 情感分析（AI + 词典混合，复刻 multilingual_sentiment_analyzer）───
function sent_sentiment(string $text): array {
    // 中文情感词典快速判断
    $pos = ['好评','满意','赞','优秀','推荐','喜欢','利好','增长','突破','认可','积极']; 
    $neg = ['差评','投诉','失望','愤怒','曝光','负面','下滑','亏损','风险','质疑','危机','裁员','爆料'];
    $posCount = 0; $negCount = 0;
    foreach ($pos as $w) if (mb_strpos($text, $w) !== false) $posCount++;
    foreach ($neg as $w) if (mb_strpos($text, $w) !== false) $negCount++;

    if ($posCount > $negCount) return ['label'=>'正面','score'=>1];
    if ($negCount > $posCount) return ['label'=>'负面','score'=>-1];
    return ['label'=>'中性','score'=>0];
}

/**
 * AI 调用 —— 统一走 AiCenter（记账 + 额度闸门 + 分档超时）。
 * 原来自建 curl：绕过电表、漏掉 Claude 分支、固定 60 秒超时。
 * 返回空串表示不可用，调用方据此回落到本地关键词情感判断。
 */
function sent_ai_call(string $prompt): string {
    require_once __DIR__ . '/AiCenter.php';
    if (!AiCenter::isConfigured()) return '';
    $r = AiCenter::chat('你是专业的舆情分析师。', $prompt, [
        'max_tokens' => 2000, 'feature' => 'sentiment_scan', 'tier' => 'batch',
    ]);
    return !empty($r['ok']) ? (string)($r['text'] ?? '') : '';
}

// ─── 设置 ───
function sent_settings(): array {
    $d = sent_get();
    return $d['settings'] ?? [];
}
function sent_save_settings(array $settings): void {
    $d = sent_get();
    $d['settings'] = $settings;
    sent_save($d);
}

// ─── 采集执行：对主题做多轮搜索 + 情感分析，存入 SQLite ───
function sent_run_scan(string $topicId): array {
    $topics = sent_topics();
    $topic = null;
    foreach ($topics as $t) if ($t['id'] === $topicId) { $topic = $t; break; }
    if (!$topic) return ['ok'=>false,'error'=>'主题不存在'];
    $name = $topic['name'];

    // 关键词优化
    $keywords = sent_optimize_keywords($name);
    $allResults = [];
    foreach (array_slice($keywords, 0, 3) as $kw) {
        $allResults = array_merge($allResults, sent_search($kw));
    }
    // 反思改进：二次搜索
    if (!empty($allResults)) {
        $refined = sent_refine_query($name, $allResults);
        if ($refined && $refined !== $name) $allResults = array_merge($allResults, sent_search($refined));
    }

    // 写入 SQLite + 情感分析
    $db = Database::conn();
    $inserted = 0; $pos=0; $neg=0; $neu=0;
    foreach ($allResults as $r) {
        $title = mb_substr($r['title'] ?? '', 0, 200);
        $snippet = mb_substr($r['snippet'] ?? '', 0, 500);
        $sent = sent_sentiment($title . ' ' . $snippet);
        if ($sent['label'] === '正面') $pos++; elseif ($sent['label'] === '负面') $neg++; else $neu++;
        try {
            $stmt = $db->prepare("INSERT OR IGNORE INTO sentiment_results (topic_id, source, title, url, snippet, sentiment, created_at) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$topicId, $r['source'] ?? '', $title, $r['url'] ?? '', $snippet, $sent['label'], date('Y-m-d H:i:s')]);
            $inserted++;
        } catch (Exception $e) {}
    }

    // 更新主题扫描时间
    $d = sent_get();
    foreach ($d['topics'] as &$t) if ($t['id'] === $topicId) { $t['last_scan'] = date('Y-m-d H:i:s'); break; }
    unset($t);
    sent_save($d);

    return ['ok'=>true, 'results'=>$inserted, 'positive'=>$pos, 'negative'=>$neg, 'neutral'=>$neu, 'keywords'=>$keywords];
}
