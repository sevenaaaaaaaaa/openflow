<?php
/**
 * 经营驾驶舱数据聚合
 */

// 核心 KPI（含环比：本期 vs 上期）
function dash_kpis(): array {
    $kpis = [];
    try {
        // 近30天访问（唯一访客 + PV）
        $v = Database::query("SELECT COUNT(DISTINCT uid) as uv, COUNT(*) as pv FROM events WHERE event='page_view' AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
        $kpis['uv'] = (int)($v[0]['uv'] ?? 0);
        $kpis['pv'] = (int)($v[0]['pv'] ?? 0);
        // 上期（30-60 天前）访问
        $prev = Database::query("SELECT COUNT(DISTINCT uid) as uv, COUNT(*) as pv FROM events WHERE event='page_view' AND created_at >= ? AND created_at < ?", [date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('-30 days'))]);
        $kpis['prev_uv'] = (int)($prev[0]['uv'] ?? 0);
        $kpis['prev_pv'] = (int)($prev[0]['pv'] ?? 0);
        // 今日访问
        $today = Database::query("SELECT COUNT(DISTINCT uid) as uv FROM events WHERE event='page_view' AND created_at >= ?", [date('Y-m-d')]);
        $kpis['today_uv'] = (int)($today[0]['uv'] ?? 0);
    } catch (Exception $e) { $kpis += ['uv'=>0,'pv'=>0,'today_uv'=>0,'prev_uv'=>0,'prev_pv'=>0]; }

    // 线索（本期 + 上期）
    $allSubs = json_read(DATA_DIR . '/submissions/index.json');
    $kpis['leads'] = count($allSubs);
    $kpis['prev_leads'] = count(array_filter($allSubs, fn($s) => ($s['created_at'] ?? '') < date('Y-m-d', strtotime('-30 days'))));

    // 订单与收入
    $orders = json_read(DATA_DIR . '/shop/orders.json');
    $paid = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));
    $kpis['orders'] = count($orders);
    $kpis['paid_orders'] = count($paid);
    $kpis['revenue'] = round(array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $paid)), 2);
    // 近30天收入 + 上期收入
    $recentPaid = array_values(array_filter($paid, fn($o) => ($o['paid_at'] ?? '') >= date('Y-m-d', strtotime('-30 days'))));
    $prevPaid = array_values(array_filter($paid, fn($o) => ($o['paid_at'] ?? '') >= date('Y-m-d', strtotime('-60 days')) && ($o['paid_at'] ?? '') < date('Y-m-d', strtotime('-30 days'))));
    $kpis['revenue_30d'] = round(array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $recentPaid)), 2);
    $kpis['prev_revenue_30d'] = round(array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $prevPaid)), 2);

    // 订阅活跃数
    $activeSub = count(array_filter(json_read(DATA_DIR . '/subscription/state.json'), fn($s) => ($s['status'] ?? '') === 'active'));
    $kpis['active_subscribers'] = $activeSub;

    // 会员总数
    $kpis['members'] = count(json_read(DATA_DIR . '/members/index.json'));

    // 分销佣金
    $kpis['commission_paid'] = round(array_sum(array_map(fn($o) => (float)($o['commission'] ?? 0), $paid)), 2);

    return $kpis;
}

// 近14天访问趋势
function dash_trend(): array {
    $days = [];
    try {
        $rows = Database::query("SELECT substr(created_at,1,10) as d, COUNT(DISTINCT uid) as uv FROM events WHERE event='page_view' GROUP BY d ORDER BY d DESC LIMIT 14");
        foreach (array_reverse($rows) as $r) $days[$r['d']] = (int)$r['uv'];
    } catch (Exception $e) {}
    // 补全缺失日期
    $result = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $result[$d] = $days[$d] ?? 0;
    }
    return $result;
}

// 渠道归因（按 utm_source 聚合已支付订单）
function dash_channel_attribution(): array {
    $orders = json_read(DATA_DIR . '/shop/orders.json');
    $paid = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));
    $byChannel = [];
    foreach ($paid as $o) {
        $source = ($o['utm']['utm_source'] ?? '') ?: ($o['utm']['utm_medium'] ?? '') ?: '直接访问';
        $byChannel[$source] = $byChannel[$source] ?? ['orders'=>0,'revenue'=>0];
        $byChannel[$source]['orders']++;
        $byChannel[$source]['revenue'] += (float)($o['amount'] ?? 0);
    }
    arsort($byChannel);
    foreach ($byChannel as &$c) $c['revenue'] = round($c['revenue'], 2);
    unset($c);
    return $byChannel;
}

// ─── UTM 全维度归因（SEO/SEM/信息流/社媒/自定义） ───
// 优先级：URL 的 UTM 参数 > referrer host。支持任意自定义 source。
function dash_utm_attribution(): array {
    $groups = [];
    $byMedium = [];
    $total = 0;
    try {
        // 1. UTM 来源落地（URL 带 utm 参数，track.php 记录的 utm_landing）
        $landings = Database::query("SELECT props FROM events WHERE event='utm_landing'");
        foreach ($landings as $r) {
            $p = json_decode($r['props'] ?? '', true);
            $source = $p['fc_utm_source'] ?? '';
            $medium = $p['fc_utm_medium'] ?? '';
            $campaign = $p['fc_utm_campaign'] ?? '';
            if ($source === '') { $medium = ''; } // 无 source 不计
            $cat = utm_medium_category($medium);
            $key = $cat . '|' . ($source ?: '直接访问');
            $groups[$key] = ($groups[$key] ?? ['visits'=>0, 'source'=>$source ?: '直接访问', 'medium'=>$cat]);
            $groups[$key]['visits']++;
            $byMedium[$cat] = ($byMedium[$cat] ?? 0) + 1;
            $total++;
        }
    } catch (Exception $e) {}
    // 2. 无 UTM 的访问按 referrer 归因（page_view 的 props.referrer）
    try {
        $pv = Database::query("SELECT props FROM events WHERE event='page_view' AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
        foreach ($pv as $r) {
            $p = json_decode($r['props'] ?? '', true);
            $ref = $p['referrer'] ?? '';
            if ($ref === '') continue;
            $host = parse_url($ref, PHP_URL_HOST) ?: '';
            $cat = referrer_category($host);
            $key = $cat . '|' . ($cat === '其他' ? $host : $cat);
            $groups[$key] = $groups[$key] ?? ['visits'=>0, 'source'=>$host ?: '其他', 'medium'=>$cat];
            $groups[$key]['visits']++;
            $byMedium[$cat] = ($byMedium[$cat] ?? 0) + 1;
            $total++;
        }
    } catch (Exception $e) {}
    uasort($groups, fn($a, $b) => $b['visits'] <=> $a['visits']);
    return ['groups'=>$groups, 'by_medium'=>$byMedium, 'total'=>$total];
}

// medium 分类：organic→SEO / paid,cpc,ppc→SEM / feed→信息流 / social→社媒 / email→邮件 / 其他→自定义
function utm_medium_category(string $medium): string {
    $m = strtolower(trim($medium));
    if ($m === '') return '直接';
    if (in_array($m, ['organic','natural'])) return 'SEO';
    if (in_array($m, ['cpc','paid','ppc','ads','sem'])) return 'SEM';
    if (in_array($m, ['feed','information_feed','pin'])) return '信息流';
    if (in_array($m, ['social','socialmedia'])) return '社媒';
    if (in_array($m, ['email','mail'])) return '邮件';
    return '自定义';
}

// referrer host 分类
function referrer_category(string $host): string {
    $host = strtolower($host);
    $searchEngines = ['google','baidu','bing','sogou','360','yahoo'];
    foreach ($searchEngines as $se) if (str_contains($host, $se)) return 'SEO';
    $social = ['weibo','weixin','wechat','zhihu','douyin','bilibili','xiaohongshu','twitter','facebook','linkedin','instagram'];
    foreach ($social as $s) if (str_contains($host, $s)) return '社媒';
    return '其他';
}

// 收入报表（按月 + 分销佣金明细）
function dash_revenue_report(): array {
    $orders = json_read(DATA_DIR . '/shop/orders.json');
    $paid = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));

    // 按月
    $monthly = [];
    foreach ($paid as $o) {
        $month = substr($o['paid_at'] ?? '', 0, 7);
        if (empty($month)) continue;
        $monthly[$month] = $monthly[$month] ?? ['orders'=>0,'revenue'=>0,'commission'=>0];
        $monthly[$month]['orders']++;
        $monthly[$month]['revenue'] += (float)($o['amount'] ?? 0);
        $monthly[$month]['commission'] += (float)($o['commission'] ?? 0);
    }
    krsort($monthly);

    // 分销佣金明细
    $members = json_read(DATA_DIR . '/members/index.json');
    $memberName = [];
    foreach ($members as $m) $memberName[$m['id']] = $m['name'] ?? '';
    $commByRef = [];
    foreach ($paid as $o) {
        if (!empty($o['referrer_id']) && $o['commission'] > 0) {
            $commByRef[$o['referrer_id']] = ($commByRef[$o['referrer_id']] ?? 0) + (float)$o['commission'];
        }
    }
    $commDetail = [];
    foreach ($commByRef as $mid => $amt) $commDetail[] = ['name'=>$memberName[$mid] ?? $mid, 'commission'=>round($amt,2)];
    usort($commDetail, fn($a,$b) => $b['commission'] <=> $a['commission']);

    return ['monthly'=>$monthly, 'commission'=>$commDetail];
}

// NPS 汇总
function dash_nps(): array {
    require_once __DIR__ . '/../admin/nps-lib.php';
    $projects = json_read(DATA_DIR . '/nps/projects.json');
    $total = 0; $npsSum = 0; $count = 0;
    foreach ($projects as $p) {
        $st = nps_compute(nps_get_responses($p['id']));
        $total += $st['total'];
        if ($st['nps'] !== null) { $npsSum += $st['nps']; $count++; }
    }
    return ['total_responses'=>$total, 'avg_nps'=>$count > 0 ? round($npsSum/$count) : null];
}

// ─── 活跃分析（DAU/WAU/MAU + 时段热力 + 新老访客） ───
function dash_activity(): array {
    $out = ['dau'=>0, 'wau'=>0, 'mau'=>0, 'hours'=>array_fill(0, 24, 0), 'new_visitors'=>0, 'returning'=>0];
    try {
        $today = date('Y-m-d');
        $dau = Database::query("SELECT COUNT(DISTINCT uid) c FROM events WHERE created_at >= ?", [$today]);
        $out['dau'] = (int)($dau[0]['c'] ?? 0);
        $wau = Database::query("SELECT COUNT(DISTINCT uid) c FROM events WHERE created_at >= ?", [date('Y-m-d', strtotime('-7 days'))]);
        $out['wau'] = (int)($wau[0]['c'] ?? 0);
        $mau = Database::query("SELECT COUNT(DISTINCT uid) c FROM events WHERE created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
        $out['mau'] = (int)($mau[0]['c'] ?? 0);
        // 时段热力（近7天，按小时）
        $hours = Database::query("SELECT substr(created_at,12,2) h, COUNT(*) c FROM events WHERE event='page_view' AND created_at >= ? GROUP BY h", [date('Y-m-d', strtotime('-7 days'))]);
        foreach ($hours as $r) $out['hours'][(int)$r['h']] = (int)$r['c'];
        // 新老访客：近30天，按 first_seen（uid 最早出现时间 < 30 天前 = 老访客）
        $visits = Database::query("SELECT uid, MIN(created_at) first_seen, COUNT(*) c FROM events WHERE created_at >= ? GROUP BY uid", [date('Y-m-d', strtotime('-30 days'))]);
        foreach ($visits as $v) {
            if (substr($v['first_seen'] ?? '', 0, 10) < date('Y-m-d', strtotime('-30 days'))) $out['returning']++;
            else $out['new_visitors']++;
        }
    } catch (Exception $e) {}
    return $out;
}

// ─── 行为路径（Top 落地页 + 来源 + 转化） ───
function dash_paths(): array {
    $out = ['pages'=>[], 'referrers'=>[], 'conversions'=>0];
    try {
        // Top 落地页（page_view 按 page）
        $pages = Database::query("SELECT page, COUNT(*) c FROM events WHERE event='page_view' GROUP BY page ORDER BY c DESC LIMIT 10");
        foreach ($pages as $r) {
            $label = trim($r['page'], '/') ?: '首页';
            $out['pages'][] = ['page'=>$label, 'views'=>(int)$r['c']];
        }
        // 来源（解析 props.referrer 的 host）
        $refs = Database::query("SELECT props FROM events WHERE props LIKE '%referrer%' AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
        $refCount = [];
        foreach ($refs as $r) {
            $p = json_decode($r['props'] ?? '', true);
            $ref = $p['referrer'] ?? '';
            if ($ref === '') continue;
            $host = parse_url($ref, PHP_URL_HOST) ?: $ref;
            $refCount[$host] = ($refCount[$host] ?? 0) + 1;
        }
        arsort($refCount);
        foreach (array_slice($refCount, 0, 6, true) as $k => $n) $out['referrers'][] = ['source'=>$k, 'count'=>$n];
        // 转化（表单提交 + 注册 + 下载）
        $conv = Database::query("SELECT COUNT(*) c FROM events WHERE event IN ('form_submit','register','download') AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
        $out['conversions'] = (int)($conv[0]['c'] ?? 0);
    } catch (Exception $e) {}
    return $out;
}

// ─── 偏好洞察（设备/语言/内容分类） ───
function dash_preferences(): array {
    $out = ['devices'=>[], 'languages'=>[], 'content'=>[]];
    try {
        // 设备/语言：从 CDP 画像 properties 聚合
        $profiles = json_read(DATA_DIR . '/cdp/profiles.json');
        $osCount = []; $langCount = []; $contentCount = [];
        $contentMap = ['academy'=>'学院', 'docs'=>'文档', 'courses'=>'课程', 'product'=>'产品', 'capability'=>'能力', 'blog'=>'博客', 'tools'=>'工具', 'community'=>'社区'];
        foreach ((array)$profiles as $p) {
            $prop = $p['properties'] ?? [];
            if (!empty($prop['os'])) $osCount[$prop['os']] = ($osCount[$prop['os']] ?? 0) + 1;
            if (!empty($prop['language'])) $langCount[$prop['language']] = ($langCount[$prop['language']] ?? 0) + 1;
        }
        // 内容偏好：从 page_view 的 page 分类聚合
        $pages = Database::query("SELECT page, COUNT(*) c FROM events WHERE event='page_view' GROUP BY page ORDER BY c DESC LIMIT 20");
        foreach ($pages as $r) {
            $path = trim($r['page'], '/');
            foreach ($contentMap as $key => $label) if (strpos($path, $key) === 0) { $contentCount[$label] = ($contentCount[$label] ?? 0) + (int)$r['c']; break; }
        }
        arsort($osCount); arsort($langCount); arsort($contentCount);
        foreach ($osCount as $k => $n) $out['devices'][] = ['name'=>$k, 'count'=>$n];
        foreach ($langCount as $k => $n) $out['languages'][] = ['name'=>$k, 'count'=>$n];
        foreach (array_slice($contentCount, 0, 8, true) as $k => $n) $out['content'][] = ['name'=>$k, 'count'=>$n];
    } catch (Exception $e) {}
    return $out;
}
