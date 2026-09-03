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
require_once __DIR__ . '/lib/McpGuard.php';   // P0-01：逐工具鉴权 + 审计留痕

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
    [
        'name' => 'contributions_list',
        'description' => '列出社区参与者贡献的、已开放为可调用能力的内容与工具（OIA 贡献三通）',
        'inputSchema' => ['type'=>'object','properties'=>['limit'=>['type'=>'integer']]],
    ],
    [
        'name' => 'contributions_recommend',
        'description' => '按兴趣标签推荐社区贡献物（平台分发：把对的东西送到对的人面前）',
        'inputSchema' => ['type'=>'object','properties'=>['interests'=>['type'=>'array','items'=>['type'=>'string']],'limit'=>['type'=>'integer']]],
    ],
    // ── 增长 OS 对外（BACKLOG T2-13）：整台增长引擎成为可被外部 Agent 调用的工具 ──
    [
        'name' => 'growth_next_best_action',
        'description' => '增长大脑：给出"现在最该动的人 + 下一最佳动作 + 理由"（读画像与成交真相，只提议不执行）',
        'inputSchema' => ['type'=>'object','properties'=>['limit'=>['type'=>'integer']]],
    ],
    [
        'name' => 'growth_goal_status',
        'description' => '当前增长目标与进度（指标/目标值/已完成/领先或落后）',
        'inputSchema' => ['type'=>'object','properties'=>[]],
    ],
    [
        'name' => 'growth_conversion_truth',
        'description' => '成交真相：哪个来源/分群真的转化成收入（按收入排，不是访问量）',
        'inputSchema' => ['type'=>'object','properties'=>[]],
    ],
    [
        'name' => 'growth_ask_data',
        'description' => '用一句自然语言问站点数据，基于已算好的真实指标作答（不编造）',
        'inputSchema' => ['type'=>'object','properties'=>['question'=>['type'=>'string']],'required'=>['question']],
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

        // 贡献三通第②腿：社区贡献物对外可被 Agent 调用（BACKLOG T1-16）
        case 'contributions_list':
            require_once __DIR__ . '/lib/ContributionPipeline.php';
            $out = contrib_mcp_list((int)($args['limit'] ?? 50));
            return ['content'=>[['type'=>'text','text'=>json_encode($out, JSON_UNESCAPED_UNICODE)]]];

        case 'contributions_recommend':
            require_once __DIR__ . '/lib/ContributionPipeline.php';
            $out = contrib_recommend((array)($args['interests'] ?? []), (int)($args['limit'] ?? 5));
            return ['content'=>[['type'=>'text','text'=>json_encode($out, JSON_UNESCAPED_UNICODE)]]];

        // ── 增长 OS 对外（BACKLOG T2-13）──
        case 'growth_next_best_action': {
            require_once __DIR__ . '/lib/CdpSync.php';
            require_once __DIR__ . '/lib/GrowthSignal.php';
            require_once __DIR__ . '/lib/GrowthGoal.php';
            require_once __DIR__ . '/lib/GrowthBrain.php';
            $rows = [];
            try { cdp_ensure_table(); $rows = Database::query("SELECT * FROM cdp_customers ORDER BY score DESC LIMIT 200"); }
            catch (\Throwable $e) { $rows = []; }
            $digest = growth_brain_digest(is_array($rows) ? $rows : [], growth_conversion_truth(),
                                          max(1, (int)($args['limit'] ?? 5)), growth_goal_current());
            $out = array_map(fn($r) => [
                'who' => $r['profile']['name'] ?? '', 'action' => $r['best']['action'] ?? '',
                'module' => $r['best']['module'] ?? '', 'reason' => $r['best']['reason'] ?? '',
                'priority' => $r['best']['priority'] ?? 0,
            ], $digest);
            return ['content'=>[['type'=>'text','text'=>json_encode($out, JSON_UNESCAPED_UNICODE)]]];
        }

        case 'growth_goal_status':
            require_once __DIR__ . '/lib/GrowthSignal.php';
            require_once __DIR__ . '/lib/GrowthGoal.php';
            return ['content'=>[['type'=>'text','text'=>json_encode(growth_goal_progress(), JSON_UNESCAPED_UNICODE)]]];

        case 'growth_conversion_truth':
            require_once __DIR__ . '/lib/GrowthSignal.php';
            return ['content'=>[['type'=>'text','text'=>json_encode(growth_conversion_truth(), JSON_UNESCAPED_UNICODE)]]];

        case 'growth_ask_data': {
            require_once __DIR__ . '/lib/CdpInsight.php';
            require_once __DIR__ . '/lib/GrowthSignal.php';
            require_once __DIR__ . '/lib/AskData.php';
            $r = askdata_answer((string)($args['question'] ?? ''));
            return ['content'=>[['type'=>'text','text'=>json_encode(
                ['ok'=>$r['ok'] ?? false, 'answer'=>$r['answer'] ?? ($r['error'] ?? '')], JSON_UNESCAPED_UNICODE)]]];
        }
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
            global $tools, $MCP_CTX;
            // 只列调用方有权调用的工具——无权的工具对它根本不存在
            return ['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>mcp_filter_tools($tools, $MCP_CTX ?? [])]];

        case 'tools/call':
            global $MCP_CTX;
            $name = $msg['params']['name'] ?? '';
            $args = $msg['params']['arguments'] ?? [];
            $ctx  = $MCP_CTX ?? ['transport'=>'stdio','permissions'=>['admin'],'name'=>'本机 stdio','key_id'=>''];
            $t0   = microtime(true);

            // 未登记的工具按写操作从严处理，防止新增工具漏登记就等于放开
            if (!isset(mcp_tool_meta()[$name])) {
                mcp_audit($name, $args, $ctx, false, '未在工具注册表中登记', (microtime(true)-$t0)*1000);
                return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>'未知工具：'.$name]];
            }
            if (!mcp_can($ctx, $name)) {
                mcp_audit($name, $args, $ctx, false, '权限不足（需要 '.mcp_tool_scope($name).'）', (microtime(true)-$t0)*1000);
                return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32002,
                    'message'=>'权限不足：'.mcp_tool_label($name).' 需要 '.mcp_tool_scope($name).' 权限']];
            }

            try {
                $res = mcp_call($name, $args);
                mcp_audit($name, $args, $ctx, true, '', (microtime(true)-$t0)*1000);
                return ['jsonrpc'=>'2.0','id'=>$id,'result'=>$res];
            } catch (Throwable $e) {
                mcp_audit($name, $args, $ctx, false, '执行失败：'.$e->getMessage(), (microtime(true)-$t0)*1000);
                return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32603,'message'=>'执行失败：'.$e->getMessage()]];
            }

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
    // HTTP SSE 模式 — 需 API Key 鉴权（Authorization: Bearer <key> 或 X-Api-Key）
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    // 身份识别走 McpGuard：正式 Key 可按权限 / 到期 / IP 分权，旧的单一 Key 仍兼容
    $auth = mcp_identify('http');
    if (!$auth['ok']) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        http_response_code(401);
        echo json_encode(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32001,'message'=>$auth['error']]], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $MCP_CTX = $auth['ctx'];
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, X-Api-Key, Content-Type');
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
    $MCP_CTX = mcp_identify('stdio')['ctx'];   // 本机子进程视同管理员，但调用同样留痕
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
