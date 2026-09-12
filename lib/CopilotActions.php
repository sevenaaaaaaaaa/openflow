<?php
/**
 * CopilotActions — 小福增长 Copilot 可执行动作
 * 自然语言 → 创建自动化流程 / 查询数据 / 生成回复建议
 *
 * B7：从「纯模板意图识别」升级为**真 LLM 编排** ——
 *   - 模板作为快速路径（确定性、无额度消耗、永远可用）
 *   - 模板未命中且 AI 已配置 → LLM 解析任意自然语言为流程，再经**白名单校验**才落库
 *   - 数据问答的「数字」永远由确定性函数产出，LLM 只负责路由问题，绝不编造数字
 */

/** 流程白名单：触发器 + 动作 + 字段，既喂给 LLM 也用于落库前校验 */
function copilot_flow_schema(): array {
    return [
        'triggers' => [
            'purchase' => '购买成功', 'register' => '注册', 'member_register' => '注册成为会员',
            'login' => '登录', 'form_submit' => '提交表单', 'newsletter_subscribed' => '订阅newsletter',
            'course_complete' => '课程完课', 'course_start' => '开始课程', 'course_enroll' => '报名课程',
            'lesson_complete' => '学完一节', 'page_view' => '页面浏览', 'article_view' => '文章阅读',
            'element_click' => '元素点击', 'download' => '下载', 'share' => '分享',
            'segment_enter' => '进入分群', 'segment_exit' => '离开分群', 'refund' => '退款',
            'crm_stage_change' => 'CRM阶段变更', 'nps_submit' => 'NPS提交', 'tool_use' => '工具使用',
            'event_register' => '活动报名',
            'role_selected' => '选择角色', 'cron' => '定时',
        ],
        'actions' => [
            'send_email'   => ['subject' => 'string', 'content' => 'text', 'delay_minutes' => 'int'],
            'delay'        => ['delay_minutes' => 'int'],
            'notify'       => ['title' => 'string', 'link' => 'string'],
            'add_tag'      => ['tag' => 'string'],
            'award_points' => ['points' => 'int'],
            'inbox'        => ['title' => 'string', 'content' => 'text'],
            'send_coupon'  => ['coupon_name' => 'string', 'coupon_type' => 'fixed|percent|free', 'coupon_value' => 'number', 'coupon_min' => 'number'],
            'update_lead'  => ['stage' => 'string', 'value' => 'number', 'owner' => 'string', 'source' => 'string', 'followup' => 'text'],
            'create_task'  => ['title' => 'string', 'assignee' => 'string', 'priority' => 'string'],
            'add_segment'  => ['segment_id' => 'string'],
            'remove_segment' => ['segment_id' => 'string'],
            'publish_content' => ['article_id' => 'string'],
        ],
    ];
}

/**
 * 白名单校验 + 清洗 LLM（或任何来源）生成的流程。非法 → null。
 * 这是 AI 与自动化引擎之间的安全边界：AI 只能碰 schema 内的字段。
 */
function copilot_validate_flow(array $flow): ?array {
    $schema  = copilot_flow_schema();
    $triggers = array_keys($schema['triggers']);
    $actions  = $schema['actions'];

    $name = trim((string)($flow['name'] ?? ''));
    $trigger = (string)($flow['trigger'] ?? '');
    if ($name === '' || !in_array($trigger, $triggers, true)) return null;

    $stepsIn = is_array($flow['steps'] ?? null) ? array_values($flow['steps']) : [];
    if (count($stepsIn) < 1 || count($stepsIn) > 6) return null;

    $steps = [];
    foreach ($stepsIn as $step) {
        if (!is_array($step)) return null;
        $action = (string)($step['action'] ?? '');
        if (!isset($actions[$action])) return null;
        $clean = ['action' => $action];
        foreach ($actions[$action] as $field => $type) {
            if (!array_key_exists($field, $step)) continue;
            switch ($type) {
                case 'int':    $clean[$field] = max(0, min(100000, (int)$step[$field])); break;
                case 'number': $clean[$field] = max(0, min(100000, (float)$step[$field])); break;
                case 'text':   $clean[$field] = mb_substr(trim((string)$step[$field]), 0, 5000); break;
                case 'fixed|percent|free':
                    $clean[$field] = in_array($step[$field], ['fixed', 'percent', 'free'], true) ? $step[$field] : 'fixed';
                    break;
                default:       $clean[$field] = mb_substr(trim((string)$step[$field]), 0, 300);
            }
        }
        // 关键动作必须有内容，否则引擎跑了也是空转
        if ($action === 'send_email' && ($clean['subject'] ?? '') === '') return null;
        if ($action === 'add_tag' && ($clean['tag'] ?? '') === '') return null;
        if ($action === 'award_points' && (int)($clean['points'] ?? 0) <= 0) return null;
        if ($action === 'create_task' && ($clean['title'] ?? '') === '') return null;
        if (in_array($action, ['add_segment', 'remove_segment'], true) && ($clean['segment_id'] ?? '') === '') return null;
        if ($action === 'publish_content' && ($clean['article_id'] ?? '') === '') return null;
        $steps[] = $clean;
    }

    $out = ['name' => mb_substr($name, 0, 60), 'trigger' => $trigger, 'steps' => $steps];
    if (!empty($flow['match_field'])) $out['match_field'] = mb_substr(trim((string)$flow['match_field']), 0, 40);
    if (!empty($flow['match_value'])) $out['match_value'] = mb_substr(trim((string)$flow['match_value']), 0, 60);
    return $out;
}

/**
 * LLM 编排：把任意自然语言解析为一个自动化流程（经白名单校验）。
 * AI 未配置 / 调用失败 / 解析非法 → null，调用方回退到模板路径。
 */
function copilot_llm_parse_flow(string $message): ?array {
    if (!class_exists('AiCenter')) {
        $p = __DIR__ . '/AiCenter.php';
        if (!is_file($p)) return null;
        require_once $p;
    }
    if (!AiCenter::isConfigured()) return null;

    $schema = copilot_flow_schema();
    $trigLines = [];
    foreach ($schema['triggers'] as $k => $label) $trigLines[] = "{$k}={$label}";
    $actLines = [];
    foreach ($schema['actions'] as $k => $fields) $actLines[] = "{$k}(" . implode(',', array_keys($fields)) . ")";

    $system = "你是增长自动化流程编排器。把用户的中文需求转成一条自动化流程 JSON。\n"
        . "可用触发器 trigger（只能选其一）：" . implode('，', $trigLines) . "\n"
        . "可用动作 action（只能从这些里选）：" . implode('，', $actLines) . "\n"
        . "可用变量（写进邮件/站内信正文）：{name} 会员名，{site_name} 站点名，{recommend} 推荐内容。\n"
        . '严格输出 JSON，不要多余文字：{"matched":true,"flow":{"name":"流程名(短)","trigger":"触发器key",'
        . '"steps":[{"action":"动作key", ...该动作的字段}]}}；若需求与增长自动化无关则输出 {"matched":false}。'
        . '规则：步骤 1-6 个；send_email 必须有 subject 和 content；不要臆造不存在的字段。';

    try {
        $r = AiCenter::json($system, '需求：' . $message, ['max_tokens' => 1200, 'feature' => 'copilot_flow', 'tier' => 'admin']);
    } catch (Throwable $e) {
        return null;
    }
    if (empty($r['ok']) || empty($r['data']['matched']) || empty($r['data']['flow'])) return null;
    return copilot_validate_flow((array)$r['data']['flow']);
}

/**
 * 从自然语言解析出自动化流程。
 * 先走模板（确定性、零额度）；未命中且有 AI → 走 LLM 真编排。
 * 返回经白名单校验的 flow 数组或 null。
 */
function copilot_parse_flow(string $message): ?array {
    $m = mb_strtolower($message);
    $flows = [];
    // 欢迎流程
    if (mb_strpos($m, '欢迎') !== false && (mb_strpos($m, '邮件') !== false || mb_strpos($m, '邮箱') !== false)) {
        $flows[] = ['name' => '新用户欢迎邮件', 'trigger' => 'member_register', 'steps' => [
            ['action' => 'send_email', 'subject' => '欢迎加入 {name} 🎉', 'content' => "嗨 {name}，\n\n欢迎来到 {site_name}！这里有一份快速上手指南…\n\n为你推荐：\n{recommend}", 'delay_minutes' => 0],
        ]];
    }
    // 购买后感谢 + 发券
    if (mb_strpos($m, '购买') !== false && (mb_strpos($m, '券') !== false || mb_strpos($m, '优惠') !== false)) {
        $flows[] = ['name' => '购买后发感谢券', 'trigger' => 'purchase', 'steps' => [
            ['action' => 'send_email', 'subject' => '感谢你的购买 🎁', 'content' => "嗨 {name}，感谢你的支持！送你一张专属优惠券，下次下单立减。", 'delay_minutes' => 0],
            ['action' => 'send_coupon', 'coupon_name' => '复购感谢券', 'coupon_type' => 'fixed', 'coupon_value' => 20, 'coupon_min' => 100],
        ]];
    }
    // 线索跟进
    if (mb_strpos($m, '线索') !== false && (mb_strpos($m, '跟进') !== false || mb_strpos($m, '孵化') !== false)) {
        $flows[] = ['name' => '线索孵化跟进', 'trigger' => 'form_submit', 'steps' => [
            ['action' => 'delay', 'delay_minutes' => 1440],
            ['action' => 'send_email', 'subject' => '我们很想了解你的需求', 'content' => "嗨 {name}，上次你留了信息，想进一步了解你的场景…", 'delay_minutes' => 0],
        ]];
    }
    // 课程完课送券
    if (mb_strpos($m, '完课') !== false || mb_strpos($m, '课程') !== false && mb_strpos($m, '券') !== false) {
        $flows[] = ['name' => '完课奖励', 'trigger' => 'course_complete', 'steps' => [
            ['action' => 'award_points', 'points' => 100],
            ['action' => 'inbox', 'title' => '🎉 恭喜完课', 'content' => '太棒了，学完了一门课！已奖励 100 积分。'],
        ]];
    }
    // 会员升级欢迎
    if (mb_strpos($m, '会员') !== false && mb_strpos($m, '升级') !== false) {
        $flows[] = ['name' => '会员升级欢迎', 'trigger' => 'purchase', 'match_field' => 'label', 'match_value' => '会员', 'steps' => [
            ['action' => 'send_email', 'subject' => '欢迎成为会员 👑', 'content' => "嗨 {name}，你已解锁会员权益，尽情使用吧！", 'delay_minutes' => 0],
            ['action' => 'add_tag', 'tag' => '会员'],
        ]];
    }
    if (!empty($flows[0])) {
        // 模板命中也要过一遍校验，保持与 LLM 路径同一安全边界
        return copilot_validate_flow($flows[0]);
    }
    // 模板未命中：仅在消息像是「自动化诉求」时才花一次 LLM 调用，避免每条闲聊都多跑模型
    if (!copilot_looks_like_flow_command($message)) return null;
    return copilot_llm_parse_flow($message);
}

/** 低成本前置门：判断消息是否可能在描述一条自动化流程（省掉无意义的模型调用） */
function copilot_looks_like_flow_command(string $message): bool {
    $m = mb_strtolower($message);
    $signals = [
        '流程', '自动化', '自动', '触发', '规则', '当', '每当', '一旦', '只要', '如果', '就发', '就送', '就通知',
        '帮我', '请帮', '创建一个', '建一个', '设置一个', '做一个', '配置一个',
        '欢迎', '召回', '跟进', '回访', '唤醒', '流失', '沉睡', '生日', '周年',
        '发邮件', '发信', '发短信', '发券', '优惠券', '发通知', '站内信', '打标签', '加标签', '积分',
        '提醒', '通知他', '通知用户', '跟进客户',
    ];
    foreach ($signals as $s) {
        if (mb_strpos($m, $s) !== false) return true;
    }
    return false;
}

/**
 * 创建自动化流程（返回 ok + flow_id）。落库前再过白名单，双保险。
 */
function copilot_create_flow(array $flow): array {
    $clean = copilot_validate_flow($flow);
    if (!$clean) return ['ok' => false, 'error' => '流程未通过安全校验（触发器/动作不在白名单内）'];
    require_once __DIR__ . '/AutomationSystem.php';
    $flows = automation_get();
    $clean['id'] = 'flow_' . date('YmdHis') . substr(bin2hex(random_bytes(4)), 0, 6);
    $clean['enabled'] = true;
    $clean['created_at'] = date('Y-m-d H:i:s');
    $clean['updated_at'] = date('Y-m-d H:i:s');
    $clean['source'] = 'copilot';
    $flows[] = $clean;
    automation_save($flows);
    automation_log($clean['id'], '小福 Copilot 创建流程：' . ($clean['name'] ?? ''), 'info');
    return ['ok' => true, 'flow_id' => $clean['id'], 'flow' => $clean];
}

/**
 * 数据问答支持的问题键（数字一律来自确定性统计，不由模型生成）
 */
function copilot_data_keys(): array {
    return [
        'leads_count'     => '线索池总量与今日新增',
        'revenue'         => '已支付订单数与累计收入',
        'active_users'    => '近7天活跃访客与画像总量',
        'conversion_rate' => '近30天 page_view→purchase 转化率',
        'product_count'   => '已上架商品数量',
        'member_count'    => '注册会员数量',
    ];
}

/** 按 key 跑确定性统计，返回可读文本（数字真、不编造） */
function copilot_data_by_key(string $key): ?array {
    try {
        switch ($key) {
            case 'leads_count':
                require_once __DIR__ . '/CrmSystem.php';
                $leads = crm_get()['leads'] ?? [];
                $today = count(array_filter($leads, fn($l) => strpos(($l['created_at'] ?? ''), date('Y-m-d')) === 0));
                return ['type' => 'text', 'key' => $key, 'data' => "当前线索池共 " . count($leads) . " 条，今日新增 " . $today . " 条。"];
            case 'revenue':
                require_once __DIR__ . '/ShopSystem.php';
                $orders = shop_all_orders();
                $paid = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));
                $revenue = array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $paid));
                return ['type' => 'text', 'key' => $key, 'data' => "已支付订单 " . count($paid) . " 单，累计收入 ¥" . number_format($revenue, 0) . "。"];
            case 'active_users':
                require_once __DIR__ . '/CdpSystem.php';
                $profiles = CdpSystem::allProfiles();
                $active7 = count(array_filter($profiles, fn($p) => strtotime($p['last_seen'] ?? '2000-01-01') > time() - 7 * 86400));
                return ['type' => 'text', 'key' => $key, 'data' => "近 7 天活跃访客 " . $active7 . " 人（共 " . count($profiles) . " 个画像）。"];
            case 'conversion_rate':
                require_once __DIR__ . '/CdpSystem.php';
                $f = CdpSystem::getFunnel(['page_view', 'form_submit', 'purchase'], 30);
                $r = end($f);
                $last = $r['rate'] ?? 0;
                return ['type' => 'text', 'key' => $key, 'data' => "近 30 天转化漏斗：page_view → purchase 整体转化率 " . $last . "%。" . ($last < 5 ? "偏低，建议优化落地页与表单。😅" : "表现不错 👍")];
            case 'product_count':
                require_once __DIR__ . '/CommerceSystem.php';
                $n = count(CommerceSystem::allPublished());
                return ['type' => 'text', 'key' => $key, 'data' => "当前已上架商品 {$n} 个。"];
            case 'member_count':
                require_once __DIR__ . '/MemberSystem.php';
                $members = function_exists('member_get_all') ? member_get_all() : [];
                return ['type' => 'text', 'key' => $key, 'data' => "注册会员共 " . count($members) . " 人。"];
        }
    } catch (Throwable $e) {}
    return null;
}

/** LLM 路由：把任意提问归类到某个数据键（只做路由，不产出数字） */
function copilot_llm_route_data(string $question): ?string {
    if (!class_exists('AiCenter')) {
        $p = __DIR__ . '/AiCenter.php';
        if (!is_file($p)) return null;
        require_once $p;
    }
    if (!AiCenter::isConfigured()) return null;
    $keys = copilot_data_keys();
    $lines = [];
    foreach ($keys as $k => $label) $lines[] = "{$k}={$label}";
    $system = "把用户问题归类到一个数据查询键。可选键：\n" . implode("\n", $lines)
        . "\n只输出 JSON：{\"key\":\"键名\"}；无法归类输出 {\"key\":\"\"}。不要输出其他内容。";
    try {
        $r = AiCenter::json($system, '问题：' . $question, ['max_tokens' => 60, 'feature' => 'copilot_route', 'tier' => 'public']);
    } catch (Throwable $e) {
        return null;
    }
    if (empty($r['ok'])) return null;
    $key = (string)($r['data']['key'] ?? '');
    return isset($keys[$key]) ? $key : null;
}

/**
 * 快速数据问答（供小福回答运营问题）
 * 先关键词命中；未命中且有 AI → LLM 路由到数据键 → 再跑确定性统计。
 */
function copilot_quick_data(string $question): ?array {
    $m = mb_strtolower($question);
    // 关键词快速路径
    $key = null;
    if (mb_strpos($m, '线索') !== false && (mb_strpos($m, '多少') !== false || mb_strpos($m, '几个') !== false)) {
        $key = 'leads_count';
    } elseif ((mb_strpos($m, '收入') !== false || mb_strpos($m, '订单') !== false || mb_strpos($m, 'gmv') !== false) && (mb_strpos($m, '多少') !== false)) {
        $key = 'revenue';
    } elseif (mb_strpos($m, '活跃') !== false && mb_strpos($m, '多少') !== false) {
        $key = 'active_users';
    } elseif (mb_strpos($m, '转化') !== false && mb_strpos($m, '率') !== false) {
        $key = 'conversion_rate';
    } elseif (mb_strpos($m, '商品') !== false && (mb_strpos($m, '多少') !== false || mb_strpos($m, '几个') !== false || mb_strpos($m, '上架') !== false)) {
        $key = 'product_count';
    } elseif (mb_strpos($m, '会员') !== false && (mb_strpos($m, '多少') !== false || mb_strpos($m, '几个') !== false)) {
        $key = 'member_count';
    }
    // 未命中 → LLM 路由兜底（仅在像是数据提问时才花一次模型调用）
    if ($key === null && copilot_looks_like_data_question($question)) $key = copilot_llm_route_data($question);
    if ($key === null) return null;
    return copilot_data_by_key($key);
}

/** 低成本前置门：判断消息是否可能在问运营数据（省掉无意义的模型调用） */
function copilot_looks_like_data_question(string $message): bool {
    $m = mb_strtolower($message);
    $signals = [
        '多少', '几个', '几单', '几条', '几位', '几件', '数据', '统计', '报表', '看板',
        '情况', '表现', '怎么样', '如何', '趋势', '涨幅', '环比', '同比', '增长',
        '收入', '营业额', 'gmv', '订单', '活跃', '转化率', '转化', '线索', '会员', '商品', '上架',
    ];
    foreach ($signals as $s) {
        if (mb_strpos($m, $s) !== false) return true;
    }
    return false;
}
