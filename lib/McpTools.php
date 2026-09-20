<?php
declare(strict_types=1);
/**
 * MCP 工具注册表（单一来源）
 *
 * 【为什么单独抽出来】MCP server（mcp-server.php）与对外「开发者」页面必须用同一份定义：
 * 否则文档会漂移——页面上列的工具与真实可调用的工具不一致，是最容易骗到人的一类错误。
 */
function mcp_tools(): array
{
    return [
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
    // ── 动作类工具（线B：agent 可执行） ──
    ['name'=>'flow_run', 'description'=>'触发一个流程事件（flow_handle），驱动自动化/画布',
     'inputSchema'=>['type'=>'object','properties'=>['event'=>['type'=>'string'],'context'=>['type'=>'object']],'required'=>['event']]],
    ['name'=>'automation_run', 'description'=>'按触发器手动运行一条自动化流程',
     'inputSchema'=>['type'=>'object','properties'=>['trigger'=>['type'=>'string'],'context'=>['type'=>'object']],'required'=>['trigger']]],
    ['name'=>'cdp_add_tag', 'description'=>'给 CDP 用户画像打一个标签',
     'inputSchema'=>['type'=>'object','properties'=>['visitor_id'=>['type'=>'string'],'tag'=>['type'=>'string']],'required'=>['visitor_id','tag']]],
    ['name'=>'email_send', 'description'=>'发送一封邮件（走已配置邮件渠道）',
     'inputSchema'=>['type'=>'object','properties'=>['email'=>['type'=>'string'],'subject'=>['type'=>'string'],'content'=>['type'=>'string']],'required'=>['email']]],
    ['name'=>'lead_create', 'description'=>'创建一条 CRM 线索（lead）',
     'inputSchema'=>['type'=>'object','properties'=>['email'=>['type'=>'string'],'name'=>['type'=>'string']],'required'=>['email']]],
];
}

/** 工具名 → 说明（给文档/校验用） */
function mcp_tool_names(): array
{
    $out = [];
    foreach (mcp_tools() as $t) {
        $n = (string) ($t['name'] ?? '');
        if ($n !== '') $out[$n] = (string) ($t['description'] ?? '');
    }
    return $out;
}
