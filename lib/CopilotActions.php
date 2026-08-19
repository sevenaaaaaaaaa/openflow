<?php
/**
 * CopilotActions — 小福增长 Copilot 可执行动作
 * 自然语言 → 创建自动化流程 / 查询数据 / 生成回复建议
 */

/**
 * 从自然语言解析出自动化流程（欢迎邮件 / 召回 / 线索跟进 / 购买发券 / 沉睡召回）
 * 返回 flow 数组或 null（未命中）
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
        $flows[] = ['name' => '线索孵化跟进', 'trigger' => 'lead_from_form', 'steps' => [
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
    return $flows[0] ?? null;
}

/**
 * 创建自动化流程（返回 ok + flow_id）
 */
function copilot_create_flow(array $flow): array {
    require_once __DIR__ . '/AutomationSystem.php';
    $flows = automation_get();
    $flow['id'] = 'flow_' . date('YmdHis') . substr(bin2hex(random_bytes(4)), 0, 6);
    $flow['enabled'] = true;
    $flow['created_at'] = date('Y-m-d H:i:s');
    $flow['updated_at'] = date('Y-m-d H:i:s');
    $flow['source'] = 'copilot';
    $flows[] = $flow;
    automation_save($flows);
    automation_log($flow['id'], '小福 Copilot 创建流程：' . ($flow['name'] ?? ''), 'info');
    return ['ok' => true, 'flow_id' => $flow['id'], 'flow' => $flow];
}

/**
 * 快速数据问答（供小福回答运营问题）
 */
function copilot_quick_data(string $question): ?array {
    $m = mb_strtolower($question);
    try {
        // 今日/近7天线索数
        if (mb_strpos($m, '线索') !== false && (mb_strpos($m, '多少') !== false || mb_strpos($m, '几个') !== false)) {
            require_once __DIR__ . '/CrmSystem.php';
            $leads = crm_get()['leads'] ?? [];
            $today = count(array_filter($leads, fn($l) => strpos(($l['created_at'] ?? ''), date('Y-m-d')) === 0));
            return ['type' => 'text', 'data' => "当前线索池共 " . count($leads) . " 条，今日新增 " . $today . " 条。"];
        }
        // 订单/收入
        if ((mb_strpos($m, '收入') !== false || mb_strpos($m, '订单') !== false || mb_strpos($m, 'gmv') !== false) && (mb_strpos($m, '多少') !== false || mb_strpos($m, '多少单') !== false)) {
            require_once __DIR__ . '/ShopSystem.php';
            $orders = shop_all_orders();
            $paid = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));
            $revenue = array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $paid));
            return ['type' => 'text', 'data' => "已支付订单 " . count($paid) . " 单，累计收入 ¥" . number_format($revenue, 0) . "。"];
        }
        // 活跃用户
        if (mb_strpos($m, '活跃') !== false && mb_strpos($m, '多少') !== false) {
            require_once __DIR__ . '/CdpSystem.php';
            $profiles = CdpSystem::allProfiles();
            $active7 = count(array_filter($profiles, fn($p) => strtotime($p['last_seen'] ?? '2000-01-01') > time() - 7 * 86400));
            return ['type' => 'text', 'data' => "近 7 天活跃访客 " . $active7 . " 人（共 " . count($profiles) . " 个画像）。"];
        }
        // 转化率
        if (mb_strpos($m, '转化') !== false && mb_strpos($m, '率') !== false) {
            require_once __DIR__ . '/CdpSystem.php';
            $f = CdpSystem::getFunnel(['page_view', 'form_submit', 'purchase'], 30);
            $r = end($f);
            $last = $r['rate'] ?? 0;
            return ['type' => 'text', 'data' => "近 30 天转化漏斗：page_view → purchase 整体转化率 " . $last . "%。" . ($last < 5 ? "偏低，建议优化落地页与表单。😅" : "表现不错 👍")];
        }
    } catch (Throwable $e) {}
    return null;
}
