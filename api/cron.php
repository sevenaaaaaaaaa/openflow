<?php
/**
 * Scheduled Publisher — call via cron: * * * * * curl https://example.com/api/cron.php
 * Publishes articles with publish_at <= now and status = draft
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../lib/AutomationSystem.php';
require_once __DIR__ . '/../lib/SubscriptionSystem.php';
require_once __DIR__ . '/../lib/AnalyticsSystem.php';
require_once __DIR__ . '/../lib/StorageSystem.php';
require_once __DIR__ . '/../lib/ModerationSystem.php';
require_once __DIR__ . '/../lib/WechatMp.php';
require_once __DIR__ . '/../lib/SocialPublisher.php';
require_once __DIR__ . '/../lib/Database.php';

// Require cron secret token for authentication
$cronSecret = json_read(DATA_DIR . '/cron_secret.json')['secret'] ?? '';
$requestSecret = $_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '';

if (empty($cronSecret)) {
    // Generate and save a new cron secret if none exists
    $cronSecret = bin2hex(random_bytes(32));
    json_write(DATA_DIR . '/cron_secret.json', ['secret' => $cronSecret]);
}

if (!hash_equals($cronSecret, $requestSecret)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '需要有效的 cron secret']);
    exit;
}

$published = 0;
foreach (get_articles() as $a) {
    $publishAt = $a['publish_at'] ?? '';
    if (($a['status'] ?? 'draft') === 'published') continue;
    if (empty($publishAt)) continue;
    if ($publishAt <= date('Y-m-d H:i:s')) {
        $a['status'] = 'published';
        $a['published_at'] = date('Y-m-d H:i:s');
        save_article($a['id'], $a);
        $published++;

        // IndexNow ping
        if (!empty($a['slug'])) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
            $url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/article/' . $a['slug'];
            indexnow_ping($url);
        }
    }
}

// 处理自动化延迟队列
automation_process_queue();
// ④ 定时 newsletter 发送（Revue 式定时）
try { require_once __DIR__ . '/../lib/MailCampaign.php'; require_once __DIR__ . '/../lib/BillionMail.php'; nl_process_schedule(); } catch (\Throwable $e) {}

// CRM 未跟进提醒（每天最多跑一次；线索超过 N 天没跟进就提醒负责人）
try {
    require_once __DIR__ . '/../lib/CrmSystem.php';
    require_once __DIR__ . '/../lib/NotifyChannels.php';
    $rmFile = DATA_DIR . '/crm_reminder_run.json';
    $lastRun = json_read($rmFile)['date'] ?? '';
    if ($lastRun !== date('Y-m-d')) {
        $crmCfg = json_read(DATA_DIR . '/crm_settings.json');
        $days = max(1, (int)($crmCfg['followup_days'] ?? 7));   // 阈值可配，默认 7 天
        $res = crm_send_followup_reminders($days);
        json_write($rmFile, ['date' => date('Y-m-d'), 'result' => $res]);
    }
} catch (Exception $e) {}

// 外部数据连接器定时同步（REST API / CSV 拉取）
try {
    require_once __DIR__ . '/../lib/DataSync.php';
    datasync_run_all();
} catch (Exception $e) {}

// 活动开始前提醒（报名者：开始前 2 小时提醒，每小时检查一次，防重复）
try {
    require_once __DIR__ . '/../lib/MessageSystem.php';    $events = json_read(DATA_DIR . '/events/index.json');
    $regs = json_read(DATA_DIR . '/event-registrations.json');
    $remindedFile = DATA_DIR . '/event-reminded.json';
    $reminded = json_read($remindedFile);
    $changed = false;
    foreach ($events as $ev) {
        if (($ev['status'] ?? '') !== 'published' || empty($ev['start_date'])) continue;
        $start = strtotime($ev['start_date']);
        if ($start < time() + 2 * 3600 && $start > time() - 1800) { // 开始前 2 小时内
            foreach (($regs[$ev['id']] ?? []) as $r) {
                $rid = ($r['id'] ?? '') ?: ($r['member_id'] ?? '');
                if ($rid === '' || isset($reminded[$rid])) continue;
                $reminded[$rid] = date('Y-m-d H:i:s');
                $changed = true;
                try { inbox_send($r['member_id'] ?? '', '🔔 活动即将开始：' . ($ev['title'] ?? ''), "你报名的活动「{$ev['title']}」将在 " . substr($ev['start_date'], 0, 16) . " 开始\n" . (($ev['event_type'] ?? '') === 'online' ? '线上参与' : '地点：' . ($ev['location'] ?? ''))); } catch (Throwable $e) {}
            }
        }
    }
    if ($changed) json_write($remindedFile, array_slice($reminded, -5000));
} catch (Exception $e) {}

// 转化漏斗级巡检（每 6 小时，对比 7 天转化率环比）
try {
    require_once __DIR__ . '/../lib/FunnelGuard.php';
    $fg = json_read(funnel_guard_file());
    if (empty($fg) || strtotime($fg['scanned_at'] ?? '2000-01-01') < time() - 6 * 3600) {
        $report = funnel_guard_scan();
        if (($report['alerts'] ?? 0) > 0) {
            require_once __DIR__ . '/../lib/MessageSystem.php';
            try { notify('funnel', '🚨 转化漏斗告警', $report['alerts'] . ' 个页面/渠道转化率骤降，点击查看详情', '/xmp/funnel-guard'); } catch (Throwable $e) {}
        }
    }
} catch (Exception $e) {}

// 报表邮件订阅推送（每日 9 点 / 每周一早）
try {
    $subs = json_read(DATA_DIR . '/report-subscribers.json');
    $today = date('Y-m-d');
    $isMonday = date('N') === '1';
    $sentFile = DATA_DIR . '/report-sent.json';
    $sent = json_read($sentFile);
    $needSend = [];
    foreach ($subs as $s) {
        if (empty($s['email'])) continue;
        $period = $s['period'] ?? 'daily';
        $key = ($period === 'weekly' ? 'w' : 'd') . ':' . $s['email'] . ':' . $today;
        if (($period === 'daily' && ($sent[$key] ?? 0) === 0) || ($period === 'weekly' && $isMonday && ($sent[$key] ?? 0) === 0)) {
            $needSend[] = $s['email'];
            $sent[$key] = 1;
        }
    }
    if (!empty($needSend)) {
        require_once __DIR__ . '/../lib/DashboardSystem.php';
        $html = report_build_html();
        $subject = '【经营报表】' . $today;
        foreach ($needSend as $em) report_send_mail($em, $subject, $html);
        json_write($sentFile, array_slice($sent, -2000));
    }
} catch (Exception $e) {}

// 多平台内容定时发布
try { SocialPublisher::processQueue(); } catch (Exception $e) {}

// 微信定时群发（到点执行）
$wxMassFile = DATA_DIR . '/wechat-mass.json';
$wxMass = json_read($wxMassFile);
$wxMassRemaining = [];
$wxSent = 0;
foreach ($wxMass as $task) {
    if (($task['status'] ?? '') === 'sent') continue;
    if (strtotime($task['send_at'] ?? '') > time()) { $wxMassRemaining[] = $task; continue; }
    // 到点执行
    try {
        $contentBody = $task['msg_type'] === 'text' ? ['content' => $task['content']] : ['media_id' => $task['media_id'] ?? ''];
        if (($task['target'] ?? 'all') === 'tag' && !empty($task['tag_id'])) {
            $r = WechatMp::massSendByTag($contentBody, $task['msg_type'], (int)$task['tag_id']);
        } else {
            $r = WechatMp::massSendByTag($contentBody, $task['msg_type'], 0);
        }
        if (($r['errcode'] ?? 1) === 0) {
            $task['status'] = 'sent';
            $task['sent_at'] = date('Y-m-d H:i:s');
            $wxSent++;
        } else {
            $task['status'] = 'failed';
            $task['error'] = $r['errmsg'] ?? '未知';
        }
        $wxMassRemaining[] = $task;
    } catch (Exception $e) {
        $task['status'] = 'failed';
        $task['error'] = $e->getMessage();
        $wxMassRemaining[] = $task;
    }
}
if (count($wxMass) > 0) json_write($wxMassFile, $wxMassRemaining);

// 订阅过期检查
sub_expire_check();

// 每日存储维护（每 6 小时一次的频率保护）
$lastMaintain = (int)(json_read(DATA_DIR . '/storage-maintain.json')['ts'] ?? 0);
if (time() - $lastMaintain > 6 * 3600) {
    storage_maintain();
    json_write(DATA_DIR . '/storage-maintain.json', ['ts' => time()]);
}

// 定期风控扫描（每 12 小时）
$lastMod = (int)(json_read(DATA_DIR . '/mod-scan.json')['ts'] ?? 0);
if (time() - $lastMod > 12 * 3600) {
    try { mod_scan_all(); } catch (Exception $e) {}
    json_write(DATA_DIR . '/mod-scan.json', ['ts' => time()]);
}

// 流失预警扫描（通知管理员，不自动发送）
$atRisk = analytics_at_risk();
if (count($atRisk) > 0) {
    $names = array_slice(array_column($atRisk, 'name'), 0, 5);
    notify('运营', '流失预警：' . count($atRisk) . ' 位用户可挽回', implode('、', array_filter($names)) . '…', 'admin/analytics.php');
}

// 自我进化扫描（每 6 小时体检一次，发现改进点）
$lastEvolve = (int)(json_read(DATA_DIR . '/evolution-scan.json')['ts'] ?? 0);
if (time() - $lastEvolve > 6 * 3600) {
    try {
        $evolveResult = SelfEvolve::runScan();
        json_write(DATA_DIR . '/evolution-scan.json', ['ts' => time()]);
        // 建议过期清理（每 24 小时）
        $lastExpire = (int)(json_read(DATA_DIR . '/evolution-expire.json')['ts'] ?? 0);
        if (time() - $lastExpire > 24 * 3600) {
            SelfEvolve::expireStale(30);
            json_write(DATA_DIR . '/evolution-expire.json', ['ts' => time()]);
        }
        // 发现严重问题则通知
        $critical = array_filter(SelfEvolve::state()['suggestions'] ?? [], fn($s) => ($s['status'] ?? 'open') === 'open' && ($s['severity'] ?? '') === 'critical');
        if (count($critical) > 0) {
            notify('进化', '自我体检发现 ' . count($critical) . ' 个严重问题', '进入自我进化中心查看建议', 'admin/evolution.php');
        }
    } catch (Throwable $e) {}
}

// 增长驱动引擎（每 6 小时自动推一轮，主动驱动网站前进）
$lastDriver = (int)(json_read(DATA_DIR . '/driver-scan.json')['ts'] ?? 0);
if (time() - $lastDriver > 6 * 3600) {
    try {
        GrowthFlywheel::runCycle();
        json_write(DATA_DIR . '/driver-scan.json', ['ts' => time()]);
    } catch (Throwable $e) {}
}

// 预热 AI 运营洞察缓存（工作台秒开，避免首次访问阻塞 9 秒）
try {
    require_once __DIR__ . '/../lib/CdpInsight.php';
    $insightCache = DATA_DIR . '/cache/cdp-insight-30.json';
    $stale = true;
    if (is_file($insightCache)) {
        $c = json_decode(file_get_contents($insightCache), true);
        if (is_array($c) && ($c['_t'] ?? 0) > time() - 3600) $stale = false;
    }
    if ($stale) { CdpInsight::generate(30); }
} catch (Throwable $e) {}

// 事件数据保留策略（每 24 小时清理 90 天前的埋点事件，防止数据库无限增长）
$lastEventsCleanup = (int)(json_read(DATA_DIR . '/events-cleanup.json')['ts'] ?? 0);
if (time() - $lastEventsCleanup > 24 * 3600) {
    try {
        $cutoff = date('Y-m-d H:i:s', strtotime('-90 days'));
        $removed = Database::execute("DELETE FROM events WHERE created_at < ?", [$cutoff]);
        // 顺手清理 heartbeat 噪音（保留最近 7 天）
        Database::execute("DELETE FROM events WHERE event = 'heartbeat' AND created_at < ?", [date('Y-m-d H:i:s', strtotime('-7 days'))]);
        json_write(DATA_DIR . '/events-cleanup.json', ['ts' => time()]);
        // 记录清理结果
        json_write(DATA_DIR . '/events-cleanup-log.json', ['ts' => time(), 'cutoff' => $cutoff]);
    } catch (Throwable $e) {}
}

// 数据保留策略（BACKLOG T1-5）：按后台配置的合规保留期清理过期事件与画像。
// 与上面 90 天兜底并存；retention_days=0 时跳过（默认不启用，行为不变）。
$consentPurge = ['skipped' => true];
try {
    require_once __DIR__ . '/../lib/ConsentSystem.php';
    $lastPurge = (int)(json_read(DATA_DIR . '/consent-purge.json')['ts'] ?? 0);
    if (time() - $lastPurge > 24 * 3600) {
        $consentPurge = consent_purge_expired();
        json_write(DATA_DIR . '/consent-purge.json', ['ts' => time(), 'result' => $consentPurge]);
    }
} catch (Throwable $e) {}

// ── Webhook 重投（P0-02）：首投失败的事件按指数退避在这里重发，耗尽转死信 ──
$webhookRetry = ['skipped' => true];
try {
    require_once __DIR__ . '/../lib/WebhookSystem.php';
    $webhookRetry = wh_process_queue(50);
} catch (Throwable $e) { $webhookRetry = ['error' => $e->getMessage()]; }

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'published' => $published, 'retention' => $consentPurge,
                  'webhook_retry' => $webhookRetry, 'time' => date('Y-m-d H:i:s')]);
