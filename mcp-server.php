<?php
/**
 * OpenFlow MCP Server — 让 AI 客户端（Claude/Codex 等）操作 CMS
 *
 * 支持两种传输：
 *   - stdio: php mcp-server.php  （AI 客户端通过子进程调用）
 *   - HTTP:  php mcp-server.php --http  (SSE 流式)
 *
 * MCP 协议：JSON-RPC 2.0
 *   initialize → tools/list → tools/call
 */

// CLI 模式
if (PHP_SAPI === 'cli') {
    $_SERVER['HTTP_HOST'] = 'mcp.local';
}

require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/lib/MemberSystem.php';
require_once __DIR__ . '/lib/SentimentSystem.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/SkillSystem.php';

// HTTP 模式：web 请求或显式 --http 参数
$httpMode = PHP_SAPI !== 'cli' || in_array('--http', $argv ?? []);

// ─── 工具定义 ───
$tools = [
    [
        'name' => 'articles_list',
        'description' => '列出文章（支持 category/status 过滤）',
        'inputSchema' => ['type'=>'object','properties'=>['category'=>['type'=>'string'],'status'=>['type'=>'string']]],
    ],
    [
        'name' => 'article_get',
        'description' => '获取单篇文章详情',
        'inputSchema' => ['type'=>'object','properties'=>['id'=>['type'=>'string']],'required'=>['id']],
    ],
    [
        'name' => 'article_create',
        'description' => '创建文章',
        'inputSchema' => ['type'=>'object','properties'=>['title'=>['type'=>'string'],'content'=>['type'=>'string'],'category'=>['type'=>'string'],'status'=>['type'=>'string']],'required'=>['title','content']],
    ],
    [
        'name' => 'article_publish',
        'description' => '发布文章',
        'inputSchema' => ['type'=>'object','properties'=>['id'=>['type'=>'string']],'required'=>['id']],
    ],
    [
        'name' => 'members_list',
        'description' => '列出会员',
        'inputSchema' => ['type'=>'object','properties'=>[]],
    ],
    [
        'name' => 'leads_count',
        'description' => '统计表单线索',
        'inputSchema' => ['type'=>'object','properties'=>[]],
    ],
    [
        'name' => 'orders_revenue',
        'description' => '统计订单收入',
        'inputSchema' => ['type'=>'object','properties'=>[]],
    ],
    [
        'name' => 'search',
        'description' => '全局搜索文章/课程/页面',
        'inputSchema' => ['type'=>'object','properties'=>['q'=>['type'=>'string']],'required'=>['q']],
    ],
    [
        'name' => 'sentiment_scan',
        'description' => '舆情采集指定主题',
        'inputSchema' => ['type'=>'object','properties'=>['topicId'=>['type'=>'string']],'required'=>['topicId']],
    ],
    [
        'name' => 'sentiment_topics',
        'description' => '列出舆情监控主题',
        'inputSchema' => ['type'=>'object','properties'=>[]],
    ],
    [
        'name' => 'skills_list',
        'description' => '列出生态市场中可用的技能（Skill）',
        'inputSchema' => ['type'=>'object','properties'=>['type'=>['type'=>'string']]],
    ],
    [
        'name' => 'skill_execute',
        'description' => '执行一个技能（Skill）：prompt 返回提示词，tool 返回数据',
        'inputSchema' => ['type'=>'object','properties'=>['id'=>['type'=>'string'],'params'=>['type'=>'object']],'required'=>['id']],
    ],
];

// ─── 工具执行 ───
function mcp_call(string $name, array $args): array {
    switch ($name) {
        case 'articles_list':
            $arts = get_articles();
            if (!empty($args['category'])) $arts = array_values(array_filter($arts, fn($a) => ($a['category'] ?? '') === $args['category']));
            if (!empty($args['status'])) $arts = array_values(array_filter($arts, fn($a) => ($a['status'] ?? '') === $args['status']));
            return ['content'=>[['type'=>'text','text'=>json_encode(array_slice($arts,0,20), JSON_UNESCAPED_UNICODE)]]];

        case 'article_get':
            $a = get_article($args['id'] ?? '');
            return ['content'=>[['type'=>'text','text'=>json_encode($a ?: ['error'=>'not found'], JSON_UNESCAPED_UNICODE)]]];

        case 'article_create':
            $id = 'article_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
            $article = [
                'id'=>$id, 'title'=>$args['title'], 'slug'=>preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u','-',$args['title']),
                'content'=>$args['content'], 'editor_mode'=>'richtext', 'category'=>$args['category'] ?? 'insight',
                'tags'=>[], 'cover'=>'', 'author'=>'AI Assistant', 'status'=>$args['status'] ?? 'draft',
                'seo_title'=>$args['title'], 'seo_desc'=>'', 'seo_keywords'=>'',
                'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s'),
            ];
            $all = get_articles(); $all[] = $article; json_write(ARTICLES_DIR . '/index.json', $all);
            return ['content'=>[['type'=>'text','text'=>json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE)]]];

        case 'article_publish':
            $a = get_article($args['id'] ?? '');
            if ($a) { $a['status']='published'; save_article($a['id'], $a); return ['content'=>[['type'=>'text','text'=>json_encode(['ok'=>true,'title'=>$a['title']], JSON_UNESCAPED_UNICODE)]]]; }
            return ['content'=>[['type'=>'text','text'=>json_encode(['error'=>'not found'])]]];

        case 'members_list':
            return ['content'=>[['type'=>'text','text'=>json_encode(json_read(DATA_DIR.'/members/index.json'), JSON_UNESCAPED_UNICODE)]]];

        case 'leads_count':
            return ['content'=>[['type'=>'text','text'=>json_encode(['total'=>count(json_read(DATA_DIR.'/submissions/index.json'))])]]];

        case 'orders_revenue':
            $orders = shop_all_orders();
            $paid = array_filter($orders, fn($o)=>($o['status']??'')==='paid');
            return ['content'=>[['type'=>'text','text'=>json_encode(['paid'=>count($paid),'revenue'=>round(array_sum(array_map(fn($o)=>(float)($o['amount']??0),$paid)),2)])]]];

        case 'search':
            $q = $args['q'] ?? ''; $results = [];
            foreach (get_articles() as $a) if (mb_strpos($a['title'] ?? '', $q) !== false) $results[] = ['type'=>'article','id'=>$a['id'],'title'=>$a['title']];
            foreach (json_read(DATA_DIR.'/courses/index.json') as $c) if (mb_strpos($c['title'] ?? '', $q) !== false) $results[] = ['type'=>'course','id'=>$c['id'],'title'=>$c['title']];
            return ['content'=>[['type'=>'text','text'=>json_encode($results, JSON_UNESCAPED_UNICODE)]]];

        case 'sentiment_scan':
            $r = sent_run_scan($args['topicId'] ?? '');
            return ['content'=>[['type'=>'text','text'=>json_encode($r, JSON_UNESCAPED_UNICODE)]]];

        case 'sentiment_topics':
            return ['content'=>[['type'=>'text','text'=>json_encode(sent_topics(), JSON_UNESCAPED_UNICODE)]]];

        case 'skills_list':
            $list = array_values(array_filter(skills_all(), fn($s) => ($s['status'] ?? '') === 'published'));
            if (!empty($args['type'])) $list = array_values(array_filter($list, fn($s) => $s['type'] === $args['type']));
            $out = array_map(fn($s) => ['id'=>$s['id'],'title'=>$s['title'],'type'=>$s['type'],'description'=>$s['description']], $list);
            return ['content'=>[['type'=>'text','text'=>json_encode($out, JSON_UNESCAPED_UNICODE)]]];

        case 'skill_execute':
            $r = skill_execute($args['id'] ?? '', $args['params'] ?? []);
            return ['content'=>[['type'=>'text','text'=>json_encode($r, JSON_UNESCAPED_UNICODE)]]];
    }
    return ['content'=>[['type'=>'text','text'=>json_encode(['error'=>'unknown tool'])]]];
}

// ─── 协议处理 ───
function mcp_handle(array $msg): ?array {
    $method = $msg['method'] ?? '';
    $id = $msg['id'] ?? null;

    switch ($method) {
        case 'initialize':
            return ['jsonrpc'=>'2.0','id'=>$id,'result'=>[
                'protocolVersion'=>'2024-11-05',
                'capabilities'=>['tools'=>['listChanged'=>false]],
                'serverInfo'=>['name'=>'openflow-mcp','version'=>'1.0.0'],
            ]];

        case 'tools/list':
            global $tools;
            return ['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>$tools]];

        case 'tools/call':
            $name = $msg['params']['name'] ?? '';
            $args = $msg['params']['arguments'] ?? [];
            return ['jsonrpc'=>'2.0','id'=>$id,'result'=>mcp_call($name, $args)];

        case 'ping':
            return ['jsonrpc'=>'2.0','id'=>$id,'result'=>[]];

        case 'notifications/initialized':
            return null; // 无响应

        default:
            return ['jsonrpc'=>'2.0','id'=>$id,'result'=>[]];
    }
}

// ─── 传输模式 ───
if ($httpMode) {
    // HTTP SSE 模式
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Access-Control-Allow-Origin: *');
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $resp = mcp_handle($input);
        if ($resp) {
            echo 'data: ' . json_encode($resp) . "\n\n";
        }
    }
    exit;
}

// stdio 模式：读一行 JSON-RPC，回一行
if (PHP_SAPI === 'cli' && !$httpMode) {
    while (($line = fgets(STDIN)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        $msg = json_decode($line, true);
        if (!$msg) continue;
        $resp = mcp_handle($msg);
        if ($resp) {
            fwrite(STDOUT, json_encode($resp) . "\n");
            fflush(STDOUT);
        }
    }
}
