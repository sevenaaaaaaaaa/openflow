<?php
/**
 * GEO 系统 — 话题监控 / AI 生成 / FAQ 结构化 / 自动提交
 */
require_once __DIR__ . '/KnowledgeSystem.php';

function geo_sources_file(): string { return DATA_DIR . '/geo/sources.json'; }
function geo_topics_file(): string { return DATA_DIR . '/geo/topics.json'; }
function geo_settings_file(): string { return DATA_DIR . '/geo/settings.json'; }

function geo_settings(): array {
    return array_merge([
        'enabled' => false,
        'rss_enabled' => true,
        'ai_enabled' => false,
        'trends_enabled' => false,    // 百度指数/Google Trends 选配
        'trends_provider' => '',      // baidu / google
        'trends_api_key' => '',
        'auto_submit' => false,       // 发布后自动提交搜索引擎
        'bing_api_key' => '',         // Bing Webmaster API key（选配）
        'baidu_token' => '',          // 百度站长 token（选配）
    ], json_read(geo_settings_file()));
}
function geo_save_settings(array $s): bool {
    if (!is_dir(dirname(geo_settings_file()))) mkdir(dirname(geo_settings_file()), 0755, true);
    return json_write(geo_settings_file(), $s);
}

// ─── RSS 源管理 ───
function geo_sources(): array {
    return json_read(geo_sources_file());
}
function geo_save_sources(array $src): bool {
    if (!is_dir(dirname(geo_sources_file()))) mkdir(dirname(geo_sources_file()), 0755, true);
    return json_write(geo_sources_file(), $src);
}

// ─── 抓取 RSS 源 ───
function geo_fetch_rss(string $url, int $limit = 20): array {
    $items = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OpenFlow-GEO)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $xml = curl_exec($ch);
    if (empty($xml)) return $items;

    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if (!$doc) return $items;

    $entries = [];
    foreach ($doc->channel->item as $item) $entries[] = $item;
    if (empty($entries)) foreach ($doc->entry as $entry) $entries[] = $entry; // Atom

    foreach (array_slice($entries, 0, $limit) as $e) {
        $items[] = [
            'title' => trim((string)($e->title ?? '')),
            'link' => trim((string)($e->link ?? '')),
            'description' => trim(strip_tags((string)($e->description ?? $e->summary ?? ''))),
            'pubDate' => trim((string)($e->pubDate ?? $e->updated ?? '')),
        ];
    }
    return $items;
}

// ─── 抓取所有启用的 RSS 源 ───
function geo_fetch_all(int $limit = 8): array {
    $all = [];
    foreach (geo_sources() as $src) {
        if (empty($src['enabled']) || empty($src['url'])) continue;
        $items = geo_fetch_rss($src['url'], $limit);
        foreach ($items as $it) $all[] = ['source' => $src['name'] ?? '', 'item' => $it];
    }
    // 按时间倒序
    usort($all, fn($a,$b) => strcmp($b['item']['pubDate'] ?? '', $a['item']['pubDate'] ?? ''));
    return $all;
}

// ─── AI 提炼热点话题 ───
function geo_ai_extract_topics(array $items): array {
    // 找到 AI 供应商
    $ai = json_read(DATA_DIR . '/ai-config.json');
    $provider = null;
    foreach (($ai['providers'] ?? []) as $p) if (($p['enabled'] ?? false) && !empty($p['api_key'])) { $provider = $p; break; }
    if (!$provider) return [];

    $snippet = '';
    foreach (array_slice($items, 0, 15) as $it) {
        $snippet .= '[' . ($it['source'] ?? '') . '] ' . ($it['item']['title'] ?? '') . "\n";
    }

    $prompt = "你是内容策略分析师。基于以下行业新闻标题，提炼出 5 个适合【网站增长/SEO/AI 运营】主题网站写作的话题。\n\n新闻：\n{$snippet}\n\n输出严格 JSON 数组：[{\"topic\":\"话题\",\"angle\":\"切入角度\",\"why\":\"为什么值得写\"}]";
    $text = geo_ai_call($provider, $prompt);
    if (empty($text)) return [];

    // 提取 JSON
    $json = $text;
    if (preg_match('/\[.*\]/s', $text, $m)) $json = $m[0];
    $topics = json_decode($json, true);
    return is_array($topics) ? array_slice($topics, 0, 5) : [];
}

// ─── AI 生成文章 ───
function geo_ai_generate_article(array $topic, string $category = 'insight'): ?array {
    $ai = json_read(DATA_DIR . '/ai-config.json');
    $provider = null;
    foreach (($ai['providers'] ?? []) as $p) if (($p['enabled'] ?? false) && !empty($p['api_key'])) { $provider = $p; break; }
    if (!$provider) return null;

    $prompt = "请为 OpenFlow 网站（主题：网站增长/SEO/AI 运营）写一篇 SEO 友好的文章。\n\n话题：{$topic['topic']}\n切入角度：{$topic['angle']}\n\n要求：\n1. 标题 20-30 字，含核心关键词\n2. 800-1200 字，5-7 个 h2 小标题\n3. 开头 100 字内给出可直接引用的核心观点（Answer 段落）\n4. 文末附 3 个 FAQ 问答\n5. 输出 HTML 格式正文（用 h2/p/ul）\n6. 不要输出标题行外的额外文字";

    // RAG：注入公司知识库
    $kb = knowledge_build_context($topic['topic'] . ' ' . ($topic['angle'] ?? ''), 3);
    if ($kb) $prompt .= "\n\n【公司知识库参考，请融入内容】\n" . $kb;
    $text = geo_ai_call($provider, $prompt);
    if (empty($text)) return null;
    return [
        'title' => $topic['topic'] ?? '未命名文章',
        'content' => $text,
        'category' => $category,
        'excerpt' => $topic['why'] ?? '',
    ];
}

// AI 调用（复用 OpenAI 兼容格式）
function geo_ai_call(array $provider, string $prompt): string {
    $apiUrl = rtrim($provider['api_url'], '/');
    $model = $provider['model'] ?? 'gpt-4o';
    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role'=>'system','content'=>'你是 OpenFlow 的专业内容作者，擅长网站增长与 AI 运营写作。'],
            ['role'=>'user','content'=>$prompt],
        ],
        'max_tokens' => 4000,
    ]);
    $endpoint = $provider['id'] === 'minimax' ? $apiUrl . '/text/chatcompletion_v2' : $apiUrl . '/chat/completions';
    $headers = ['Authorization: Bearer ' . $provider['api_key'], 'Content-Type: application/json'];
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>$headers, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>90]);
    $resp = curl_exec($ch);
    $data = json_decode($resp, true);
    return $data['choices'][0]['message']['content'] ?? ($data['output_text'] ?? ($data['data'][0]['output_text'] ?? ''));
}

// ─── 话题库 ───
function geo_get_topics(): array { return json_read(geo_topics_file()); }
function geo_save_topics(array $t): bool {
    if (!is_dir(dirname(geo_topics_file()))) mkdir(dirname(geo_topics_file()), 0755, true);
    return json_write(geo_topics_file(), $t);
}
function geo_add_topic(array $topic): void {
    $topics = geo_get_topics();
    $topic['id'] = 'topic_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $topic['created_at'] = date('Y-m-d H:i:s');
    $topics[] = $topic;
    geo_save_topics($topics);
}

// ─── 自动提交搜索引擎 ───
function geo_submit_url(string $url): void {
    $s = geo_settings();
    if (empty($s['auto_submit'])) return;
    $host = parse_url($url, PHP_URL_HOST) ?? '';

    // IndexNow（必应/微软，也支持 Yandex/Seznam）
    $indexnow = json_read(DATA_DIR . '/indexnow.json');
    if (!empty($indexnow['key'])) {
        $ch = curl_init('https://api.indexnow.org/indexnow');
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode([
            'host'=>$host, 'key'=>$indexnow['key'], 'keyLocation'=>"https://{$host}/{$indexnow['key']}.txt", 'urlList'=>[$url]
        ]), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
        curl_exec($ch);
    }

    // 必应 Webmaster API（选配）
    if (!empty($s['bing_api_key'])) {
        $ch = curl_init("https://ssl.bing.com/webmaster/api.svc/json/SubmitUrl?apikey=" . $s['bing_api_key']);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(['url'=>$url]), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
        curl_exec($ch);
    }

    // 百度站长（选配）
    if (!empty($s['baidu_token'])) {
        $site = $host;
        $ch = curl_init("http://data.zz.baidu.com/urls?site=https://{$site}&token={$s['baidu_token']}");
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$url, CURLOPT_HTTPHEADER=>['Content-Type: text/plain'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
        curl_exec($ch);
    }
}

// ─── FAQ 结构化（GEO）───
// 从文章内容提取 h2/h3 生成 FAQ 段落，追加到 JSON-LD
function geo_build_faq_jsonld(array $faqs): array {
    $mainEntity = [];
    foreach ($faqs as $f) {
        $mainEntity[] = [
            '@type' => 'Question',
            'name' => $f['q'],
            'acceptedAnswer' => ['@type'=>'Answer','text'=>$f['a']],
        ];
    }
    return ['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$mainEntity];
}
