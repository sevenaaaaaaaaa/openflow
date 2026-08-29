<?php
/**
 * FlowSystem — 全站统一事件总线（三条流动的联动中枢）
 *
 * 把散落的模块串成一条主线：
 *   A. 内容流动  知识库 → 创作 → 发布 → 分发 → 互动 → 回收洞察
 *   B. 数据流动  匿名埋点 → 识别 → 画像标签 → 线索 → CRM → 成交 → 会员
 *   C. 价值流动  免费 → 培育 → 付费 → 会员权益 → 复购/推荐
 *
 * flow_handle(event, context) 是唯一入口，任何行为事件上报都会触发：
 *   1. CDP 客户建档/合并 + 行为打标（数据流）
 *   2. 画像标签 / 价值评分（数据流）
 *   3. 积分激励（价值流）
 *   4. 营销自动化 / 画布触发器（价值流）
 *   5. 站内信 + 通知渠道（价值流）
 *   6. 线索联动（表单 → CRM）
 *
 * ── 流程编排三件套：本文件是「总事件总线」 ──
 * 关系：FlowSystem（本文件）= 总入口，接收所有行为事件并分发到各执行器；
 *       CanvasSystem = 可视化画布的编排执行器（nodes/edges）；
 *       AutomationSystem = 营销自动化执行器（触发器+动作）。
 * 加代码指引：全局事件联动（跨模块串联、事件分发规则）加这里；
 *             可视化流程加 CanvasSystem；触发器/动作自动化加 AutomationSystem。
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/CdpSystem.php';
require_once __DIR__ . '/../lib/ProfilingSystem.php';
require_once __DIR__ . '/../lib/Gamification.php';
require_once __DIR__ . '/../lib/MessageSystem.php';
require_once __DIR__ . '/../lib/MembershipSystem.php';
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_once __DIR__ . '/../lib/CanvasSystem.php';

// 行为 → 标签 + 积分 + 价值分映射
function flow_behavior_map(): array {
    return [
        'page_view'       => ['tag' => '浏览',            'points' => 0,  'score' => 1],
        'form_submit'     => ['tag' => '留资',            'points' => 10, 'score' => 15],
        'download'        => ['tag' => '资料下载',        'points' => 15, 'score' => 20],
        'course_view'     => ['tag' => '课程兴趣',        'points' => 5,  'score' => 10],
        'course_buy'      => ['tag' => '已购课程',        'points' => 50, 'score' => 50],
        'live_join'       => ['tag' => '直播互动',        'points' => 10, 'score' => 15],
        'consultation'    => ['tag' => '咨询意向',        'points' => 20, 'score' => 30],
        'consultation_done'=> ['tag' => '已购咨询',       'points' => 40, 'score' => 40],
        'community_post'  => ['tag' => '社区活跃',        'points' => 15, 'score' => 10],
        'comment'         => ['tag' => '互动评论',        'points' => 5,  'score' => 5],
        'subscribe'       => ['tag' => '订阅会员',        'points' => 100,'score' => 80],
        'login'           => ['tag' => '已登录',          'points' => 2,  'score' => 2],
        'register'        => ['tag' => '新注册',          'points' => 20, 'score' => 20],
        'video_play'      => ['tag' => '视频观看',        'points' => 3,  'score' => 5],
        'bookmark'        => ['tag' => '收藏',            'points' => 5,  'score' => 8],
        'share'           => ['tag' => '分享',            'points' => 10, 'score' => 15],
        'crm_stage_change'=> ['tag' => 'CRM 阶段变化',    'points' => 0,  'score' => 5],
    ];
}

// 唯一入口：处理一个行为事件
// $ctx: ['uid'=>, 'member_id'=>, 'email'=>, 'props'=>, 'page'=>, 'label'=>]
function flow_handle(string $event, array $ctx = []): array {
    $result = ['event' => $event, 'customer' => null, 'tagged' => [], 'points' => 0, 'triggers' => []];

    $uid = $ctx['uid'] ?? ($_COOKIE['fc_uid'] ?? '');
    $memberId = $ctx['member_id'] ?? ($_SESSION['member_id'] ?? '');
    $email = $ctx['email'] ?? ($_SESSION['member_email'] ?? '');

    // ── B. 数据流：CDP 建档/合并 ──
    $customer = null;
    try {
        if ($memberId || $email) {
            $customer = cdp_find($email, $memberId, $uid);
        }
        if (!$customer) {
            $customer = cdp_get_or_create($uid, $memberId, $email, $ctx['phone'] ?? '');
        }
        // 更新 last_seen
        Database::execute("UPDATE cdp_customers SET last_seen = ? WHERE id = ?", [date('Y-m-d H:i:s'), $customer['id']]);
    } catch (Exception $e) {}
    $result['customer'] = $customer;

    // ── 行为映射 ──
    $map = flow_behavior_map();
    $m = $map[$event] ?? null;
    if ($m && $customer) {
        // 打标
        try { cdp_add_tag($customer['id'], $m['tag']); $result['tagged'][] = $m['tag']; } catch (Exception $e) {}
        // 评分（累加到 CDP）
        try {
            $c = cdp_get_by_id($customer['id']);
            $score = (int)($c['score'] ?? 0) + $m['score'];
            cdp_set_score($customer['id'], $score);
        } catch (Exception $e) {}
    }

    // ── C. 价值流：积分激励（需登录）──
    if ($memberId && $m && $m['points'] > 0) {
        try {
            $member = null;
            foreach (json_read(DATA_DIR . '/members/index.json') as $mm) if ($mm['id'] === $memberId) { $member = $mm; break; }
            if ($member) {
                gamification_award($memberId, $m['points'], 'flow:' . $event);
                $result['points'] = $m['points'];
            }
        } catch (Exception $e) {}
    }

    // ── C. 价值流：营销自动化 / 画布 ──
    try {
        $triggerData = array_merge(
            ['email' => $email, 'uid' => $uid, 'member_id' => $memberId, 'label' => $ctx['label'] ?? '', 'page' => $ctx['page'] ?? ''],
            $ctx['props'] ?? []
        );
        if (function_exists('automation_trigger')) { automation_trigger('flow_' . $event, $triggerData); $result['triggers'][] = 'automation'; }
        if (function_exists('canvas_trigger')) { canvas_trigger('flow_' . $event, $triggerData); $result['triggers'][] = 'canvas'; }

        // ── Webhook 出站（flow 业务事件 → 订阅的 webhook 端点） ──
        $whMap = [
            'purchase' => 'order.paid',
            'form_submit' => 'form.submitted',
            'member_register' => 'member.registered',
            'lead_from_form' => 'lead.created',
            'course_complete' => 'course.completed',
            'course_enroll' => 'course.enrolled',
            'nps_submit' => 'form.submitted',
            'member_update' => 'member.updated',
            'crm_stage_change' => 'lead.stage_changed',
        ];
        if (isset($whMap[$event]) && class_exists('WebhookSystem')) {
            try { \WebhookSystem::trigger($whMap[$event], $triggerData); } catch (Exception $e) {}
        }

        // ── 转化回传（CAPI：线索/注册/订阅等转化 → 广告平台） ──
        $convMap = [
            'lead_from_form' => 'Lead',
            'member_register' => 'CompleteRegistration',
            'subscribe' => 'Subscribe',
            'consultation_done' => 'Lead',
        ];
        if (isset($convMap[$event]) && !empty($email)) {
            try {
                require_once __DIR__ . '/ConversionApi.php';
                $conv_track([
                    'event_name' => $convMap[$event],
                    'user_id' => $memberId,
                    'email' => $email,
                    'value' => (float)($ctx['amount'] ?? 0),
                ]);
            } catch (Throwable $e) {}
        }
    } catch (Exception $e) {}

    // ── C. 价值流：高价值行为站内信 ──
    $notifyEvents = ['subscribe', 'consultation_done', 'course_buy'];
    if ($memberId && in_array($event, $notifyEvents)) {
        try {
            if ($event === 'subscribe') inbox_notify_event('membership_upgraded', ['member_id' => $memberId, 'tier' => '会员']);
            if ($event === 'course_buy') inbox_notify_event('order_paid', ['member_id' => $memberId, 'title' => $ctx['label'] ?? '课程']);
        } catch (Exception $e) {}
    }

    return $result;
}

// ── 数据流：CRM 线索阶段变化联动（CRM → CDP 标签 + MA/画布触发器）──
//
// 由 crm_update_lead() 在检测到 stage 变化后调用。整条链路为旁路：
// 任何一步失败都不影响 CRM 本身的保存结果。
//
// @param string $email    线索邮箱（CDP 关联键）
// @param string $oldStage 变化前阶段（可能为空，如新建后首次赋值）
// @param string $newStage 变化后阶段，取值见 crm_stages()
// @param array  $lead     变化后的完整线索记录
function flow_crm_stage_change(string $email, string $oldStage, string $newStage, array $lead = []): array {
    $stages = function_exists('crm_stages') ? crm_stages() : [];
    $label  = $stages[$newStage] ?? $newStage;

    // 阶段专属 CDP 标签（如 "CRM:已成交"），与通用行为标签并存
    try {
        $customer = cdp_find($email, '', '');
        if ($customer) {
            cdp_add_tag($customer['id'], 'CRM:' . $label);
            // 成交/无效是终态，额外打一个结果标签便于分群
            if ($newStage === 'won')  cdp_add_tag($customer['id'], '已成交客户');
            if ($newStage === 'lost') cdp_add_tag($customer['id'], '流失线索');
        }
    } catch (Exception $e) {}

    // 走统一入口，复用 automation/canvas/webhook/评分全套管线
    return flow_handle('crm_stage_change', [
        'email' => $email,
        'label' => $label,
        'props' => [
            'old_stage'  => $oldStage,
            'new_stage'  => $newStage,
            'stage_label'=> $label,
            'lead_name'  => $lead['name'] ?? '',
            'lead_owner' => $lead['owner'] ?? '',
            'lead_value' => (float)($lead['value'] ?? 0),
        ],
    ]);
}

// ── 内容流：内容发布联动（发布后自动推进分发/收录）──
function flow_content_published(array $article): void {
    // 推送渠道（公众号/邮件等由各自模块触发）
    // IndexNow 收录
    if (!empty($article['slug']) && function_exists('indexnow_ping')) {
        try {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
            indexnow_ping($protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/article/' . $article['slug']);
        } catch (Exception $e) {}
    }
}

// ── 内容流：表单/线索联动 → CRM ──
function flow_lead_from_form(array $formData, string $formId = ''): ?array {
    $email = trim($formData['email'] ?? '');
    $contact = trim($formData['contact'] ?? '');
    $phone = trim($formData['phone'] ?? '');
    if (!$email && $contact) {
        if (strpos($contact, '@') !== false) { $email = $contact; }
        elseif (!$phone) { $phone = $contact; }
    }
    if (!$email && !$phone) return null;
    $key = $email !== '' ? $email : $phone;
    $lead = null;
    if (function_exists('crm_ensure_lead')) {
        try {
            $lead = crm_ensure_lead($email, $formData['name'] ?? '', $phone);
            // 附加来源
            if ($lead && function_exists('crm_update_lead')) {
                crm_update_lead($key, ['source' => 'form:' . $formId, 'company' => $formData['company'] ?? '']);
            }
        } catch (Exception $e) {}
    }
    // CDP 关联
    if ($lead) {
        flow_handle('form_submit', ['email' => $email, 'phone' => $phone, 'props' => $formData, 'label' => '表单:' . $formId]);
    }
    return $lead;
}

// ── 数据流：订单支付联动 ──
function flow_order_paid(array $order): void {
    $memberId = $order['member_id'] ?? '';
    $courseId = $order['course_id'] ?? '';
    $amount = (float)($order['amount'] ?? 0);
    if ($memberId) {
        // CDP 价值
        try {
            $c = cdp_find('', $memberId, '');
            if ($c) {
                cdp_add_tag($c['id'], '已购课程');
                cdp_add_ltv($c['id'], $amount);
            }
        } catch (Exception $e) {}
        // 积分
        try { gamification_award($memberId, 50, '购买课程'); } catch (Exception $e) {}
        // 站内信
        try { inbox_notify_event('order_paid', ['member_id' => $memberId, 'title' => $order['course_title'] ?? '课程']); } catch (Exception $e) {}
        // 转化回传（CAPI：purchase 事件 → 广告平台）
        try {
            require_once __DIR__ . '/ConversionApi.php';
            $m = member_get($memberId);
            $convData = [
                'event_name' => 'purchase',
                'user_id' => $memberId,
                'order_id' => $order['id'] ?? '',
                'email' => $m['email'] ?? '',
                'phone' => $m['phone'] ?? '',
                'value' => $amount,
                'currency' => 'CNY',
                'click_id' => $_COOKIE['fc_utm_click_id'] ?? '',
            ];
            conv_track($convData);
        } catch (Throwable $e) {}
    }
}
