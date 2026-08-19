<?php
/**
 * 用户画像 + 标签体系
 * 自动打标（行为/消费/调研）+ 手动标签 + 画像卡
 */

// 标签定义
function prof_tag_defs(): array {
    return [
        'lead' => ['name'=>'线索用户', 'color'=>'#7c3aed', 'icon'=>'📥'],
        'subscriber' => ['name'=>'订阅用户', 'color'=>'#0284c7', 'icon'=>'📬'],
        'buyer' => ['name'=>'付费用户', 'color'=>'#16a34a', 'icon'=>'💰'],
        'ambassador' => ['name'=>'推荐大使', 'color'=>'#d97706', 'icon'=>'🏅'],
        'teacher' => ['name'=>'讲师', 'color'=>'#dc2626', 'icon'=>'👨‍🏫'],
        'active' => ['name'=>'活跃用户', 'color'=>'#16a34a', 'icon'=>'🔥'],
        'at_risk' => ['name'=>'流失风险', 'color'=>'#d97706', 'icon'=>'⚠️'],
        'churned' => ['name'=>'已沉睡', 'color'=>'#9ca3af', 'icon'=>'😴'],
        'high_value' => ['name'=>'高价值', 'color'=>'#eab308', 'icon'=>'💎'],
        'content_creator' => ['name'=>'内容创作者', 'color'=>'#db2777', 'icon'=>'✍️'],
    ];
}

// 计算会员自动标签
function prof_auto_tags(array $member): array {
    $tags = [];
    $mid = $member['id'];
    $orders = shop_all_orders();
    $subs = json_read(DATA_DIR . '/submissions/index.json');

    // 付费
    $paid = array_filter($orders, fn($o) => ($o['member_id'] ?? '') === $mid && ($o['status'] ?? '') === 'paid');
    if ($paid) {
        $tags[] = 'buyer';
        $total = array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $paid));
        if ($total >= 500) $tags[] = 'high_value';
    }
    // 订阅
    require_once __DIR__ . '/SubscriptionSystem.php';
    if (sub_is_active($mid)) $tags[] = 'subscriber';
    // 大使/讲师
    if (!empty($member['ambassador'])) $tags[] = 'ambassador';
    if (($member['teacher_status'] ?? '') === 'approved') $tags[] = 'teacher';
    // 社区活跃（有发帖/评论）
    $posts = json_read(DATA_DIR . '/community-posts.json');
    $comments = json_read(DATA_DIR . '/community-comments.json');
    $hasPosts = count(array_filter($posts, fn($p) => ($p['author_id'] ?? '') === $mid));
    $hasComments = count(array_filter($comments, fn($c) => ($c['author_id'] ?? '') === $mid));
    if ($hasPosts || $hasComments) $tags[] = 'content_creator';

    // 最近互动（活跃度）
    $lastActive = $member['last_active'] ?? '';
    if ($lastActive) {
        $days = (int)((time() - strtotime($lastActive)) / 86400);
        if ($days <= 7) $tags[] = 'active';
        elseif ($days >= 30) $tags[] = 'churned';
        elseif ($days >= 14) $tags[] = 'at_risk';
    }

    return array_values(array_unique($tags));
}

// 合并自动+手动标签，返回详情
function prof_build_profile(array $member): array {
    $auto = prof_auto_tags($member);
    $manual = $member['manual_tags'] ?? [];
    $defs = prof_tag_defs();
    $all = array_unique(array_merge($auto, $manual));
    $detail = [];
    foreach ($all as $t) $detail[$t] = $defs[$t] ?? ['name'=>$t, 'color'=>'#6b6580', 'icon'=>'🏷️'];
    return ['auto'=>$auto, 'manual'=>$manual, 'all'=>$detail];
}

// 手动添加/移除标签
function prof_manual_tag(string $memberId, string $tag, bool $add): void {
    $members = json_read(DATA_DIR . '/members/index.json');
    foreach ($members as &$m) {
        if ($m['id'] === $memberId) {
            $manual = $m['manual_tags'] ?? [];
            if ($add && !in_array($tag, $manual)) $manual[] = $tag;
            if (!$add) $manual = array_values(array_filter($manual, fn($t) => $t !== $tag));
            $m['manual_tags'] = $manual;
            break;
        }
    }
    unset($m);
    json_write(DATA_DIR . '/members/index.json', $members);
}

// 更新会员活跃时间（各动作触发）
function prof_touch(string $memberId): void {
    $members = json_read(DATA_DIR . '/members/index.json');
    foreach ($members as &$m) if ($m['id'] === $memberId) { $m['last_active'] = date('Y-m-d H:i:s'); break; }
    unset($m);
    json_write(DATA_DIR . '/members/index.json', $members);
}

// ─── 整体用户画像（Audience）───
// 合并 CDP 全量客户 + 会员，提供筛选/分群/分组/分层数据
function prof_audience(array $filters = []): array {
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/CdpSystem.php';
    $customers = [];
    try { cdp_ensure_table(); $customers = Database::query("SELECT * FROM cdp_customers"); } catch (Exception $e) { $customers = []; }

    $members = member_get_all();
    $memberIndex = [];
    foreach ($members as $m) $memberIndex[$m['id']] = $m;

    $tagFilter = $filters['tag'] ?? '';
    $search = $filters['search'] ?? '';
    $identity = $filters['identity'] ?? 'all'; // all/member/anon
    $activity = $filters['activity'] ?? 'all'; // all/7/30/90
    $value = $filters['value'] ?? 'all';       // all/high/paid
    $segment = $filters['segment'] ?? '';      // RFM 分层

    $list = [];
    $byChannel = [];
    $active7 = $active30 = 0; $memberCnt = 0; $anonCnt = 0; $paidCnt = 0; $highCnt = 0;
    $segCounts = ['high_value'=>0,'potential'=>0,'new'=>0,'at_risk'=>0,'churned'=>0];

    foreach ($customers as $c) {
        $mid = $c['member_id'] ?? '';
        $m = $mid && isset($memberIndex[$mid]) ? $memberIndex[$mid] : null;
        $isMember = (bool)$m;
        $ltv = (float)($c['lifetime_value'] ?? 0);
        $lastSeen = $c['last_seen'] ?? '';
        $tags = array_filter(array_map('trim', explode(',', $c['tags'] ?? '')));

        if ($isMember) $memberCnt++; else $anonCnt++;
        if ($ltv > 0) $paidCnt++;
        if ($ltv >= 500) $highCnt++;
        if ($lastSeen) {
            $days = (int)((time() - strtotime($lastSeen)) / 86400);
            if ($days <= 7) $active7++;
            if ($days <= 30) $active30++;
        }

        // 筛选
        if ($identity === 'member' && !$isMember) continue;
        if ($identity === 'anon' && $isMember) continue;
        if ($activity === '7' && (!$lastSeen || (time() - strtotime($lastSeen)) / 86400 > 7)) continue;
        if ($activity === '30' && (!$lastSeen || (time() - strtotime($lastSeen)) / 86400 > 30)) continue;
        if ($activity === '90' && (!$lastSeen || (time() - strtotime($lastSeen)) / 86400 > 90)) continue;
        if ($value === 'high' && $ltv < 500) continue;
        if ($value === 'paid' && $ltv <= 0) continue;
        if ($tagFilter && !in_array($tagFilter, $tags)) continue;
        if ($search) {
            $hay = ($c['primary_email'] ?? '') . ' ' . ($m['name'] ?? '') . ' ' . $mid . ' ' . ($c['id'] ?? '');
            if (mb_strpos($hay, $search) === false) continue;
        }

        // 渠道（从事件来源推导）
        $channel = prof_channel_of($c, $lastSeen);

        // RFM 分层（会员用 analytics_rfm；匿名按 LTV/活跃）
        if ($isMember) {
            $seg = prof_member_segment($m);
        } else {
            $seg = $ltv >= 500 ? 'high_value' : ($ltv > 0 ? 'potential' : ($lastSeen && (time() - strtotime($lastSeen)) / 86400 >= 30 ? 'churned' : 'new'));
        }
        if ($segment && $seg !== $segment) continue;

        $segCounts[$seg] = ($segCounts[$seg] ?? 0) + 1;
        $byChannel[$channel] = ($byChannel[$channel] ?? 0) + 1;
        $list[] = [
            'cdp_id' => $c['id'], 'member_id' => $mid,
            'name' => $m['name'] ?? '', 'email' => $c['primary_email'] ?? ($m['email'] ?? ''),
            'is_member' => $isMember, 'channel' => $channel,
            'first_seen' => $c['first_seen'] ?? '', 'last_seen' => $lastSeen,
            'ltv' => $ltv, 'score' => (int)($c['score'] ?? 0),
            'tags' => $tags, 'segment' => $seg,
        ];
    }

    usort($list, fn($a, $b) => ($b['ltv'] <=> $a['ltv']) ?: strcmp($b['last_seen'], $a['last_seen']));
    arsort($byChannel);

    return [
        'total' => count($customers), 'members' => $memberCnt, 'anon' => $anonCnt,
        'active7' => $active7, 'active30' => $active30, 'paid' => $paidCnt, 'high' => $highCnt,
        'segments' => $segCounts, 'channels' => $byChannel, 'list' => $list,
    ];
}

// 单个客户的来源渠道：从 events 表 utm_landing 事件解析
function prof_channel_of(array $cdp, string $lastSeen = ''): string {
    try {
        $ids = json_decode($cdp['identities'] ?? '[]', true) ?: [];
        $uids = $ids;
        if (!empty($cdp['member_id'])) $uids[] = $cdp['member_id'];
        if (!$uids) $uids[] = $cdp['id'];
        $place = implode(',', array_fill(0, count($uids), '?'));
        $rows = Database::query("SELECT label, props, created_at FROM events WHERE event='utm_landing' AND uid IN ($place) ORDER BY id ASC LIMIT 1", $uids);
        if (!$rows) return 'direct';
        $label = (string)($rows[0]['label'] ?? '');
        $parts = array_filter(explode('|', $label));
        $source = $parts[0] ?? '';
        if (!$source) {
            $props = json_decode($rows[0]['props'] ?? '{}', true) ?: [];
            $source = $props['fc_utm_source'] ?? '';
        }
        if (!$source) return 'direct';
        $source = strtolower($source);
        if (strpos($source, 'google') !== false || strpos($source, 'baidu') !== false || strpos($source, 'bing') !== false) return 'search';
        if (strpos($source, 'wechat') !== false || strpos($source, 'weixin') !== false) return 'wechat';
        if (strpos($source, 'weibo') !== false) return 'weibo';
        if (strpos($source, 'zhihu') !== false) return 'zhihu';
        if (strpos($source, 'toutiao') !== false || strpos($source, 'xigua') !== false) return 'toutiao';
        if (strpos($source, 'facebook') !== false || strpos($source, 'twitter') !== false || strpos($source, 'linkedin') !== false) return 'social';
        return $source;
    } catch (Exception $e) { return 'direct'; }
}

// 会员 RFM 分层（复用 AnalyticsSystem，缓存避免重复读文件）
function prof_member_segment(array $member): string {
    static $rfmMap = null;
    if ($rfmMap === null) {
        require_once __DIR__ . '/AnalyticsSystem.php';
        $rfm = analytics_rfm();
        $rfmMap = [];
        foreach ($rfm['members'] as $rm) $rfmMap[$rm['id']] = $rm['segment'];
    }
    return $rfmMap[$member['id']] ?? 'new';
}

// 单个客户详细资料（Profile）：来源 / 首次末次访问 / 完整时间线
function prof_profile_detail(array $cdp): array {
    require_once __DIR__ . '/Database.php';
    $ids = json_decode($cdp['identities'] ?? '[]', true) ?: [];
    $uids = $ids;
    if (!empty($cdp['member_id'])) $uids[] = $cdp['member_id'];
    if (!$uids) $uids[] = $cdp['id'];
    $uids = array_values(array_unique($uids));

    $events = [];
    try {
        if ($uids) {
            $place = implode(',', array_fill(0, count($uids), '?'));
            $events = Database::query("SELECT event, label, page, props, created_at FROM events WHERE uid IN ($place) ORDER BY id DESC LIMIT 200", $uids);
        }
    } catch (Exception $e) { $events = []; }

    // 来源事件（最早的 utm_landing）
    $utm = null;
    foreach (array_reverse($events) as $e) {
        if (($e['event'] ?? '') === 'utm_landing') { $utm = $e; break; }
    }

    $orderCount = 0; $totalSpent = 0.0; $submitCount = 0;
    foreach ($events as $e) {
        $ev = $e['event'] ?? '';
        if ($ev === 'form_submit') $submitCount++;
    }
    $orders = shop_all_orders();
    foreach ($orders as $o) {
        if (!empty($cdp['member_id']) && ($o['member_id'] ?? '') === $cdp['member_id'] && ($o['status'] ?? '') === 'paid') {
            $orderCount++;
            $totalSpent += (float)($o['amount'] ?? 0);
        }
    }

    return [
        'first_seen' => $cdp['first_seen'] ?? '',
        'last_seen' => $cdp['last_seen'] ?? '',
        'utm' => $utm,
        'event_count' => count($events),
        'orders' => $orderCount,
        'total_spent' => $totalSpent,
        'submissions' => $submitCount,
        'timeline' => $events,
    ];
}

// 事件类型友好标签
function prof_event_label(string $event): string {
    $map = [
        'page_view' => '👀 浏览页面', 'button_click' => '🔘 按钮点击', 'link_click' => '🔗 链接点击',
        'nav_click' => '🧭 导航点击', 'footer_click' => '🦶 页脚点击',
        'component_view' => '👁 转化组件曝光', 'component_click' => '🎯 转化组件点击',
        'form_submit' => '📥 表单提交', 'utm_landing' => '📍 渠道落地',
        'abtest_impression' => '🧪 AB曝光', 'abtest_conversion' => '🧪 AB转化',
        'click' => '🔘 点击', 'video_play' => '▶️ 播放', 'download' => '⬇️ 下载',
    ];
    return $map[$event] ?? ('🏷 ' . $event);
}
