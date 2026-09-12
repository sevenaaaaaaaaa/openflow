<?php
/**
 * Provisioner — 自动装配（压轴）
 *
 * 知识库先行：用户把项目介绍/知识库/内容一股脑交进来（文本 / 文件 / 站点·RSS 自抓），
 * 系统读知识库 + 全站现状，按 TIPS 框架与用户旅程产出**一整套就位的东西**
 * （计划 / 看板目标 / flow / 分群 / 内容草稿 / 落地页 / 转化目标 / 待办），
 * 每个都是**可执行对象**，用户只需逐个点确认——接受即真实创建。
 *
 * 安全：AI 只提议，绝不自动执行外部动作；每个 artifact 落库前过白名单校验。
 */

function prov_dir(): string { return DATA_DIR . '/provision'; }
function prov_brief_file(): string { return prov_dir() . '/brief.json'; }
function prov_plan_file(): string { return prov_dir() . '/plan.json'; }

function prov_brief(): array { $d = json_read(prov_brief_file()); return is_array($d) ? $d : ['title' => '', 'text' => '', 'urls' => [], 'files' => [], 'updated_at' => '']; }
function prov_save_brief(array $b): void { json_write(prov_brief_file(), $b); }

/** 投喂：把文本写进知识库 */
function prov_ingest_text(string $text, string $title = ''): void {
    $text = trim($text);
    if ($text === '') return;
    if (!function_exists('knowledge_add')) require_once __DIR__ . '/KnowledgeSystem.php';
    knowledge_add(['id' => 'brief_' . substr(md5($text), 0, 10), 'title' => $title ?: '项目介绍', 'content' => mb_substr($text, 0, 20000), 'source' => 'provision', 'source_id' => 'brief', 'created_at' => date('Y-m-d H:i:s')]);
}

/** 投喂：抓取站点/文章 URL 的正文（SSRF 防护：仅 http/https，禁内网） */
function prov_ingest_url(string $url): array {
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url)) return ['ok' => false, 'error' => '仅支持 http(s) URL'];
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    if (preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|0\.0\.0\.0)/i', $host)) return ['ok' => false, 'error' => '禁止内网地址'];
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'OpenFlow-Provisioner/1.0'], 'ssl' => ['verify_peer' => true]]);
        $html = @file_get_contents($url, false, $ctx);
        if ($html === false) return ['ok' => false, 'error' => '抓取失败'];
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags(preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html))));
        if (mb_strlen($text) < 50) return ['ok' => false, 'error' => '正文太短'];
        prov_ingest_text(mb_substr($text, 0, 20000), $host . $url);
        return ['ok' => true, 'chars' => mb_strlen($text)];
    } catch (\Throwable $e) { return ['ok' => false, 'error' => $e->getMessage()]; }
}

function prov_plan(): array { $d = json_read(prov_plan_file()); return is_array($d) ? $d : ['items' => [], 'generated_at' => '', 'summary' => '']; }
function prov_plan_save(array $p): void { json_write(prov_plan_file(), $p); }

/** 可用 artifact 类型与字段（既喂模型也做白名单） */
function prov_artifact_schema(): array {
    return [
        'flow'            => '自动化流程 {name,trigger,steps[]}，trigger/step 同自动化引擎白名单',
        'growth_goal'     => '本周增长目标 {metric,target,title}，metric ∈ revenue|won|members|leads',
        'conversion_goal' => '转化目标 {name,event,value,scope}',
        'segment'         => '用户分群 {name,description,operator:and|or,rules[]}',
        'landing'         => '落地页 {title,slug,cta_title,cta_text,cta_button,cta_url,blocks[]}（blocks 为区块数组）',
        'content'         => '内容草稿 {title,angle,category}（由系统按知识库生成正文）',
        'task'            => '人工待办 {title,description,assignee,priority}',
    ];
}

/** 生成装配计划：AI 读知识库 + 现状，按 TIPS 产出一组 artifact */
function prov_generate(bool $force = false): array {
    if (!$force) { $c = prov_plan(); if (!empty($c['items'])) return ['ok' => true, 'plan' => $c, 'cached' => true]; }
    if (!class_exists('AiCenter')) require_once __DIR__ . '/AiCenter.php';
    if (!AiCenter::isConfigured()) return ['ok' => false, 'error' => 'AI 未配置'];
    if (!function_exists('knowledge_build_context')) require_once __DIR__ . '/KnowledgeSystem.php';
    if (!function_exists('business_context_digest')) require_once __DIR__ . '/BusinessContext.php';

    $brief = prov_brief();
    $kb = knowledge_build_context(mb_substr(($brief['title'] ?? '') . ' ' . ($brief['text'] ?? ''), 0, 500), 6);
    $ctx = function_exists('business_context') ? business_context() : ['digest' => ''];
    $existingFlows = [];
    try { if (function_exists('automation_get')) foreach (automation_get() as $f) $existingFlows[] = (string)($f['name'] ?? ''); } catch (\Throwable $e) {}

    $schema = prov_artifact_schema();
    $schemaLines = [];
    foreach ($schema as $k => $v) $schemaLines[] = "- {$k}：{$v}";

    $system = "你是 OpenFlow 的增长装配师。读用户投喂的项目资料 + 站点现状，按 TIPS 框架与用户旅程，"
        . "为新用户产出**一整套开箱就位的东西**，让他只需确认。\n"
        . "TIPS 四段：touch(触达:内容/渠道/落地页/邮件) · insight(洞察:目标/转化目标/分群) · personalize(个性化:自动化flow/触发) · sell(成交:报价/交付/复购)。\n"
        . "可用 artifact 类型：\n" . implode("\n", $schemaLines) . "\n"
        . "规则：每段 2-4 项，共 8-14 项；宁缺毋滥；每个 artifact 必须能被上述类型表达；不编造用户没提供的具体数字。\n"
        . "严格输出 JSON：{\"summary\":\"一句话总结\",\"items\":[{\"tps\":\"touch|insight|personalize|sell\",\"category\":\"短标签\",\"title\":\"做什么\",\"why\":\"为什么（引用资料）\",\"artifact\":{\"type\":\"...\",...}}]}";

    $user = "【项目资料】\n" . mb_substr((string)($brief['text'] ?? ''), 0, 4000)
        . ($kb ? "\n\n【知识库】\n" . mb_substr($kb, 0, 3000) : '')
        . "\n\n【站点现状】\n" . (string)($ctx['digest'] ?? '')
        . ($existingFlows ? "\n\n【已有流程，别重复】" . implode('、', array_slice($existingFlows, 0, 15)) : '');

    try {
        $r = AiCenter::json($system, $user, ['max_tokens' => 4000, 'feature' => 'provision_plan', 'tier' => 'admin']);
    } catch (\Throwable $e) { return ['ok' => false, 'error' => $e->getMessage()]; }
    if (empty($r['ok']) || empty($r['data']['items'])) return ['ok' => false, 'error' => $r['error'] ?? 'AI 无有效返回'];

    $items = [];
    foreach ((array)$r['data']['items'] as $i => $it) {
        $v = prov_validate_item($it, $i);
        if ($v) $items[] = $v;
    }
    if (!$items) return ['ok' => false, 'error' => 'AI 返回未通过校验'];
    $plan = ['items' => $items, 'generated_at' => date('Y-m-d H:i:s'), 'summary' => mb_substr((string)($r['data']['summary'] ?? ''), 0, 200)];
    prov_plan_save($plan);
    return ['ok' => true, 'plan' => $plan, 'cached' => false];
}

/** 校验并清洗单条装配项 */
function prov_validate_item(array $it, int $i): ?array {
    $tps = in_array($it['tps'] ?? '', ['touch', 'insight', 'personalize', 'sell'], true) ? $it['tps'] : 'touch';
    $title = mb_substr(trim((string)($it['title'] ?? '')), 0, 60);
    $a = (array)($it['artifact'] ?? []);
    $type = (string)($a['type'] ?? '');
    if ($title === '' || !isset(prov_artifact_schema()[$type])) return null;
    $clean = null;
    switch ($type) {
        case 'flow':
            if (function_exists('copilot_validate_flow')) { $f = copilot_validate_flow($a); if ($f) $clean = ['type' => 'flow', 'flow' => $f]; }
            break;
        case 'growth_goal':
            $metrics = function_exists('growth_goal_metrics') ? array_keys(growth_goal_metrics()) : ['revenue', 'won', 'members', 'leads'];
            if (in_array($a['metric'] ?? '', $metrics, true) && (float)($a['target'] ?? 0) > 0) $clean = ['type' => 'growth_goal', 'goal' => ['metric' => $a['metric'], 'target' => (float)$a['target'], 'title' => mb_substr((string)($a['title'] ?? '增长目标'), 0, 40)]];
            break;
        case 'conversion_goal':
            if (trim((string)($a['name'] ?? '')) !== '' && preg_match('/^[a-z0-9_]+$/', (string)($a['event'] ?? ''))) $clean = ['type' => 'conversion_goal', 'goal' => ['name' => mb_substr((string)$a['name'], 0, 60), 'event' => $a['event'], 'value' => (float)($a['value'] ?? 0), 'scope' => mb_substr((string)($a['scope'] ?? ''), 0, 120)]];
            break;
        case 'segment':
            $rules = array_values(array_filter((array)($a['rules'] ?? []), fn($r) => is_array($r)));
            if (trim((string)($a['name'] ?? '')) !== '' && $rules) $clean = ['type' => 'segment', 'segment' => ['name' => mb_substr((string)$a['name'], 0, 50), 'description' => mb_substr((string)($a['description'] ?? ''), 0, 120), 'operator' => (($a['operator'] ?? 'and') === 'or' ? 'or' : 'and'), 'rules' => $rules]];
            break;
        case 'landing':
            if (trim((string)($a['title'] ?? '')) !== '') $clean = ['type' => 'landing', 'landing' => ['title' => mb_substr((string)$a['title'], 0, 60), 'slug' => preg_replace('/[^a-z0-9-]/', '', strtolower((string)($a['slug'] ?? ''))), 'cta_title' => mb_substr((string)($a['cta_title'] ?? ''), 0, 60), 'cta_text' => mb_substr((string)($a['cta_text'] ?? ''), 0, 120), 'cta_button' => mb_substr((string)($a['cta_button'] ?? ''), 0, 20), 'cta_url' => mb_substr((string)($a['cta_url'] ?? ''), 0, 200), 'blocks' => array_values(array_filter((array)($a['blocks'] ?? []), fn($b) => is_array($b)))]]; 
            break;
        case 'content':
            if (trim((string)($a['title'] ?? '')) !== '') $clean = ['type' => 'content', 'content' => ['title' => mb_substr((string)$a['title'], 0, 80), 'angle' => mb_substr((string)($a['angle'] ?? ''), 0, 200), 'category' => mb_substr((string)($a['category'] ?? 'insight'), 0, 20)]];
            break;
        case 'task':
            if (trim((string)($a['title'] ?? '')) !== '') $clean = ['type' => 'task', 'task' => ['title' => mb_substr((string)$a['title'], 0, 60), 'description' => mb_substr((string)($a['description'] ?? ''), 0, 200), 'assignee' => mb_substr((string)($a['assignee'] ?? 'admin'), 0, 30), 'priority' => in_array($a['priority'] ?? '', ['low', 'medium', 'high'], true) ? $a['priority'] : 'medium']];
            break;
    }
    if (!$clean) return null;
    return ['id' => 'pi_' . substr(md5($title . $i . $type), 0, 10), 'tps' => $tps,
            'category' => mb_substr((string)($it['category'] ?? ''), 0, 20), 'title' => $title,
            'why' => mb_substr((string)($it['why'] ?? ''), 0, 300), 'artifact' => $clean, 'status' => 'proposed', 'created' => null];
}

/** 接受并真实创建一条装配项 */
function prov_apply(string $itemId): array {
    $plan = prov_plan();
    $idx = -1;
    foreach ((array)$plan['items'] as $i => $it) if (($it['id'] ?? '') === $itemId) { $idx = $i; break; }
    if ($idx < 0) return ['ok' => false, 'error' => '装配项不存在'];
    $it = $plan['items'][$idx];
    if (($it['status'] ?? '') === 'accepted') return ['ok' => true, 'already' => true, 'created' => $it['created']];
    $a = $it['artifact'];
    $created = null;
    try {
        switch ($a['type']) {
            case 'flow':
                if (!class_exists('CopilotActions')) require_once __DIR__ . '/CopilotActions.php';
                $r = copilot_create_flow($a['flow']);
                if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? '创建失败'];
                $created = ['type' => 'flow', 'id' => $r['flow_id'], 'url' => '/xmp/automation'];
                break;
            case 'growth_goal':
                require_once __DIR__ . '/GrowthGoal.php';
                growth_goal_set($a['goal']);
                $created = ['type' => 'growth_goal', 'url' => '/xmp/brain'];
                break;
            case 'conversion_goal':
                require_once __DIR__ . '/ConversionGoal.php';
                $r = cg_save($a['goal']);
                if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? '创建失败'];
                $created = ['type' => 'conversion_goal', 'id' => $r['goal']['id'], 'url' => '/xmp/conversion-goals'];
                break;
            case 'segment':
                $r = prov_create_segment($a['segment']);
                if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? '创建失败'];
                $created = ['type' => 'segment', 'id' => $r['id'], 'url' => '/xmp/segments'];
                break;
            case 'landing':
                require_once __DIR__ . '/BuilderPages.php';
                $data = $a['landing'];
                $data['status'] = 'draft';
                $data['goal_id'] = '';
                $pid = save_builder_page('', $data);
                if ($pid === '') return ['ok' => false, 'error' => '落地页创建失败'];
                $created = ['type' => 'landing', 'id' => $pid, 'url' => '/xmp/page-builder?edit=' . urlencode($pid)];
                break;
            case 'content':
                $r = prov_create_content($a['content']);
                if (empty($r['ok'])) return ['ok' => false, 'error' => $r['error'] ?? '生成失败'];
                $created = ['type' => 'content', 'id' => $r['id'], 'url' => '/xmp/article-edit?id=' . urlencode($r['id'])];
                break;
            case 'task':
                $tasks = json_read(DATA_DIR . '/tasks.json'); if (!is_array($tasks)) $tasks = [];
                $tid = 'task_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
                $tasks[] = ['id' => $tid, 'title' => $a['task']['title'], 'description' => $a['task']['description'], 'assignee' => $a['task']['assignee'], 'assigner' => 'provisioner', 'priority' => $a['task']['priority'], 'status' => 'pending', 'progress' => 0, 'due_date' => '', 'comments' => [], 'created_at' => date('Y-m-d H:i:s'), 'completed_at' => ''];
                json_write(DATA_DIR . '/tasks.json', $tasks);
                $created = ['type' => 'task', 'id' => $tid, 'url' => '/xmp/tasks'];
                break;
            default:
                return ['ok' => false, 'error' => '未知装配类型'];
        }
    } catch (\Throwable $e) { return ['ok' => false, 'error' => $e->getMessage()]; }
    $plan['items'][$idx]['status'] = 'accepted';
    $plan['items'][$idx]['created'] = $created;
    prov_plan_save($plan);
    // 回流：记住哪些类型被接受
    try { require_once __DIR__ . '/GrowthLearning.php'; growth_learning_verdict('provision', (string)$it['title'], 'done'); } catch (\Throwable $e) {}
    return ['ok' => true, 'created' => $created];
}

function prov_skip(string $itemId): bool {
    $plan = prov_plan(); $hit = false;
    foreach ((array)$plan['items'] as $i => $it) if (($it['id'] ?? '') === $itemId) { $plan['items'][$i]['status'] = 'skipped'; $hit = true; break; }
    if ($hit) prov_plan_save($plan);
    return $hit;
}

/** 创建分群（写入现有分群存储） */
function prov_create_segment(array $seg): array {
    if (!class_exists('CdpSystem')) require_once __DIR__ . '/CdpSystem.php';
    $file = DATA_DIR . '/cdp/segments.json';
    $all = json_read($file); if (!is_array($all)) $all = [];
    $id = 'seg_' . preg_replace('/[^a-z0-9]/', '', strtolower(mb_substr($seg['name'], 0, 16))) . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
    $row = ['id' => $id, 'name' => $seg['name'], 'description' => $seg['description'] ?? '', 'operator' => $seg['operator'] ?? 'and', 'rules' => $seg['rules'], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
    $all[] = $row;
    json_write($file, $all);
    return ['ok' => true, 'id' => $id];
}

/** 生成内容草稿（AI 基于标题+知识库写正文），存为草稿文章 */
function prov_create_content(array $c): array {
    if (!class_exists('AiCenter')) require_once __DIR__ . '/AiCenter.php';
    if (!AiCenter::isConfigured()) return ['ok' => false, 'error' => 'AI 未配置'];
    if (!function_exists('knowledge_build_context')) require_once __DIR__ . '/KnowledgeSystem.php';
    $kb = knowledge_build_context($c['title'] . ' ' . $c['angle'], 4);
    $r = AiCenter::json(
        '你是资深专栏作者。按标题与角度写一篇 800-1500 字中文文章。只用资料中的事实，不编造。输出 JSON：'
        . '{"excerpt":"100字摘要","content":"HTML正文，含 h2 分节"}',
        "标题：{$c['title']}\n角度：{$c['angle']}\n" . ($kb ? "知识库：\n{$kb}" : ''),
        ['max_tokens' => 3000, 'feature' => 'provision_content', 'tier' => 'admin']
    );
    if (empty($r['ok']) || empty($r['data']['content'])) return ['ok' => false, 'error' => $r['error'] ?? '生成失败'];
    $articles = json_read(ARTICLES_DIR . '/index.json'); if (!is_array($articles)) $articles = [];
    $id = 'prov_' . date('Ymd_His') . substr(bin2hex(random_bytes(3)), 0, 5);
    $articles[] = ['id' => $id, 'title' => $c['title'], 'slug' => 'prov-' . date('YmdHis'), 'content' => (string)$r['data']['content'],
        'excerpt' => (string)($r['data']['excerpt'] ?? ''), 'status' => 'draft', 'author' => '装配', 'category' => $c['category'] ?: 'insight',
        'tags' => [], 'seo_title' => '', 'seo_desc' => '', 'seo_keywords' => '', 'source' => 'provision',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
    json_write(ARTICLES_DIR . '/index.json', $articles);
    return ['ok' => true, 'id' => $id];
}
