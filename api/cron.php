<?php
/**
 * Scheduled Publisher — call via cron: * * * * * curl https://nownexts.com/api/cron.php
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

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'published' => $published, 'time' => date('Y-m-d H:i:s')]);
