<?php
/**
 * 数据分析系统 — 转化漏斗 / RFM 分层 / 流失预警
 */

// ─── 转化漏斗 ───
// 环节：访客(page_view) → 线索(form_submit/lead) → 订阅/订单 → 付费
function analytics_funnel(): array {
    $steps = [];
    try {
        // 访客：唯一 uid 的 page_view 数（近30天）
        $visitors = Database::query("SELECT COUNT(DISTINCT uid) as c FROM events WHERE event='page_view' AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
        $steps[] = ['key'=>'visitor','name'=>'访客','count'=>(int)($visitors[0]['c'] ?? 0),'icon'=>'👀'];

        // 线索：form_submit 事件（近30天，去重 uid）
        $leads = Database::query("SELECT COUNT(DISTINCT uid) as c FROM events WHERE event='form_submit' AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
        $steps[] = ['key'=>'lead','name'=>'线索','count'=>(int)($leads[0]['c'] ?? 0),'icon'=>'📥'];

        // 订单：近30天订单数
        $orders = json_read(DATA_DIR . '/shop/orders.json');
        $orderCnt = count(array_filter($orders, fn($o) => ($o['created_at'] ?? '') >= date('Y-m-d', strtotime('-30 days'))));
        $steps[] = ['key'=>'order','name'=>'订单','count'=>$orderCnt,'icon'=>'🛒'];

        // 付费：近30天已支付
        $paidCnt = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid' && ($o['paid_at'] ?? '') >= date('Y-m-d', strtotime('-30 days'))));
        $steps[] = ['key'=>'paid','name'=>'已支付','count'=>$paidCnt,'icon'=>'💰'];
    } catch (Exception $e) {
        // 数据库未建时降级
        $steps = [
            ['key'=>'visitor','name'=>'访客','count'=>0,'icon'=>'👀'],
            ['key'=>'lead','name'=>'线索','count'=>count(json_read(DATA_DIR . '/submissions/index.json')),'icon'=>'📥'],
            ['key'=>'order','name'=>'订单','count'=>count(json_read(DATA_DIR . '/shop/orders.json')),'icon'=>'🛒'],
            ['key'=>'paid','name'=>'已支付','count'=>0,'icon'=>'💰'],
        ];
    }
    // 计算转化率
    foreach ($steps as $i => $s) {
        $steps[$i]['rate'] = ($i === 0) ? 100 : ($steps[$i-1]['count'] > 0 ? round($s['count'] / $steps[$i-1]['count'] * 100, 1) : 0);
        $steps[$i]['drop'] = ($i === 0) ? 0 : (($steps[$i-1]['count'] - $s['count']) > 0 ? $steps[$i-1]['count'] - $s['count'] : 0);
    }
    return $steps;
}

// ─── RFM 分层 ───
// R=最近购买/互动天数, F=购买频率, M=累计金额
// 输出每个会员的分层：高价值/潜力/新客/流失/沉睡
function analytics_rfm(): array {
    $members = json_read(DATA_DIR . '/members/index.json');
    $orders = json_read(DATA_DIR . '/shop/orders.json');
    $subs = json_read(DATA_DIR . '/submissions/index.json');

    $result = ['high_value'=>0,'potential'=>0,'new'=>0,'at_risk'=>0,'churned'=>0, 'members'=>[]];
    $today = time();

    foreach ($members as $m) {
        $mid = $m['id'];
        // M：累计付费金额
        $total = array_sum(array_map(fn($o) => ($o['member_id'] ?? '') === $mid && ($o['status'] ?? '') === 'paid' ? (float)($o['amount'] ?? 0) : 0, $orders));
        // F：购买次数
        $freq = count(array_filter($orders, fn($o) => ($o['member_id'] ?? '') === $mid && ($o['status'] ?? '') === 'paid'));
        // R：最近互动（取订单/提交时间最近）
        $lastActive = '';
        foreach ($orders as $o) if (($o['member_id'] ?? '') === $mid && ($o['paid_at'] ?? '') > $lastActive) $lastActive = $o['paid_at'];
        foreach ($subs as $s) if (($s['data']['email'] ?? '') === ($m['email'] ?? '') && ($s['created_at'] ?? '') > $lastActive) $lastActive = $s['created_at'];
        $daysSince = $lastActive ? (int)(($today - strtotime($lastActive)) / 86400) : 999;

        // 分层（先判活跃度，再判价值）
        $seg = 'new';
        if ($daysSince >= 30) $seg = 'churned';
        elseif ($daysSince >= 14) $seg = 'at_risk';
        elseif ($total >= 500 && $freq >= 2) $seg = 'high_value';
        elseif ($total > 0 && $freq >= 1) $seg = 'potential';
        else $seg = 'new';

        $result[$seg]++;
        $result['members'][] = ['id'=>$mid,'name'=>$m['name']??'','email'=>$m['email']??'','segment'=>$seg,'r'=>$daysSince,'f'=>$freq,'m'=>$total];
    }
    return $result;
}

// ─── 流失预警 ───
// 返回需要挽回的用户（7-30天未互动但曾有价值的）
function analytics_at_risk(int $daysMin = 7, int $daysMax = 30): array {
    $rfm = analytics_rfm();
    $atRisk = array_filter($rfm['members'], fn($m) =>
        $m['r'] >= $daysMin && $m['r'] <= $daysMax && ($m['m'] > 0 || $m['f'] > 0));
    usort($atRisk, fn($a,$b) => $a['r'] <=> $b['r']);
    return array_values($atRisk);
}

// 对指定会员发送挽回邮件（可被画布/定时调用）
function analytics_send_winback(string $memberId): array {
    $rfm = analytics_rfm();
    $member = null;
    foreach ($rfm['members'] as $m) if ($m['id'] === $memberId) { $member = $m; break; }
    if (!$member || empty($member['email'])) return ['ok'=>false,'error'=>'会员或邮箱不存在'];
    $bm = BillionMail::fromConfig();
    if (!$bm) return ['ok'=>false,'error'=>'邮件服务未配置'];
    $res = $bm->send($member['email'], '我们想念你了：专属回归福利', 
        '<h2>嗨，' . htmlspecialchars($member['name'] ?: '朋友') . '</h2><p>一段时间没见，我们为你准备了一份专属回归礼：<br>✅ 新课程 <strong>8 折</strong> 优惠券<br>✅ 最新《网站增长报告》免费下载</p><p><a href="/courses" style="background:#1e1e1e;color:#ddff0e;padding:12px 28px;border-radius:999px;text-decoration:none">立即回归 →</a></p>');
    return ['ok'=>(($res['code'] ?? 0) >= 200 && ($res['code'] ?? 0) < 300), 'response'=>$res];
}

// ─── 路径分析 ───
// 基于 events 表，按用户(uid)切会话(间隔>30min)，统计页面访问路径
// 输出：热门 2 步转移、热门完整路径、入口/出口页、跳出率、平均页数
function analytics_paths(int $days = 30, int $topN = 10, string $pageOnly = ''): array {
    $empty = ['sessions'=>0,'single_page'=>0,'bounce_rate'=>0,'avg_pages'=>0,'pairs'=>[],'paths'=>[],'entries'=>[],'exits'=>[]];
    try {
        $since = date('Y-m-d', strtotime("-$days days"));
        $rows = Database::query("SELECT uid, event, page, label, created_at FROM events WHERE created_at >= ? ORDER BY uid ASC, id ASC", [$since]);
    } catch (Exception $e) { return $empty; }

    // 按 uid 分组
    $byUid = [];
    $interactEvents = ['page_view','click','button_click','link_click','nav_click','footer_click','component_click','form_submit','form_click'];
    foreach ($rows as $r) {
        $ev = $r['event'] ?? '';
        if (!in_array($ev, $interactEvents)) continue;
        $uid = $r['uid'] ?: 'anonymous';
        $byUid[$uid][] = $r;
    }

    // 会话切分（>30 分钟间隔）
    $sessions = [];
    foreach ($byUid as $uid => $evts) {
        $cur = []; $prevT = 0;
        foreach ($evts as $e) {
            $t = strtotime($e['created_at'] ?? '') ?: 0;
            if ($cur && $prevT && ($t - $prevT) > 1800) { $sessions[] = $cur; $cur = []; }
            $cur[] = $e;
            $prevT = $t;
        }
        if ($cur) $sessions[] = $cur;
    }

    // 页面序列（相邻去重）
    $pageSeqs = [];
    foreach ($sessions as $s) {
        $seq = [];
        foreach ($s as $e) {
            if (($e['event'] ?? '') !== 'page_view') continue;
            $p = $e['page'] ?: '/';
            if (!$seq || end($seq) !== $p) $seq[] = $p;
        }
        if ($pageOnly) $seq = array_values(array_filter($seq, fn($p) => strpos($p, $pageOnly) !== false));
        if ($seq) $pageSeqs[] = $seq;
    }
    if (empty($pageSeqs)) return $empty;

    // 2 步转移
    $pairs = [];
    foreach ($pageSeqs as $seq) {
        for ($i = 0; $i < count($seq) - 1; $i++) {
            $k = $seq[$i] . '|' . $seq[$i + 1];
            $pairs[$k] = ($pairs[$k] ?? 0) + 1;
        }
    }
    arsort($pairs);

    // 完整路径
    $fullCounts = [];
    foreach ($pageSeqs as $seq) { $k = implode('|', $seq); $fullCounts[$k] = ($fullCounts[$k] ?? 0) + 1; }
    arsort($fullCounts);

    // 入口/出口
    $entries = []; $exits = [];
    foreach ($pageSeqs as $seq) {
        $entries[$seq[0]] = ($entries[$seq[0]] ?? 0) + 1;
        $exits[$seq[count($seq) - 1]] = ($exits[$seq[count($seq) - 1]] ?? 0) + 1;
    }
    arsort($entries); arsort($exits);

    $single = count(array_filter($pageSeqs, fn($s) => count($s) === 1));
    $total = count($pageSeqs);
    return [
        'sessions' => $total,
        'single_page' => $single,
        'bounce_rate' => $total ? round($single / $total * 100, 1) : 0,
        'avg_pages' => $total ? round(array_sum(array_map('count', $pageSeqs)) / $total, 2) : 0,
        'pairs' => array_slice($pairs, 0, $topN, true),
        'paths' => array_slice($fullCounts, 0, $topN, true),
        'entries' => array_slice($entries, 0, $topN, true),
        'exits' => array_slice($exits, 0, $topN, true),
    ];
}
