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
    if (empty($formData['email'])) return null;
    $lead = null;
    if (function_exists('crm_ensure_lead')) {
        try {
            $lead = crm_ensure_lead($formData['email'], $formData['name'] ?? '', $formData['phone'] ?? '');
            // 附加来源
            if ($lead && function_exists('crm_update_lead')) {
                crm_update_lead($formData['email'], ['source' => 'form:' . $formId, 'company' => $formData['company'] ?? '']);
            }
        } catch (Exception $e) {}
    }
    // CDP 关联
    if ($lead) {
        flow_handle('form_submit', ['email' => $formData['email'], 'props' => $formData, 'label' => '表单:' . $formId]);
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
    }
}
