<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('export');

$format = $_GET['format'] ?? '';

if ($format === 'csv') {
    if (!file_exists(LEADS_CSV)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No leads data found']);
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Ymd') . '.csv"');
    readfile(LEADS_CSV);
    exit;
}

if ($format === 'json') {
    $leads = get_leads();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Ymd') . '.json"');
    echo json_encode($leads, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Webhook / API integration info
if ($format === 'webhook-test') {
    $webhookUrl = $_POST['webhook_url'] ?? '';
    if ($webhookUrl) {
        $leads = get_leads();
        $payload = json_encode(['leads' => array_slice($leads, 0, 3), 'test' => true, 'time' => date('Y-m-d H:i:s')]);
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) $message = '连接失败: ' . $error;
        elseif ($httpCode >= 200 && $httpCode < 300) $message = '测试成功 (HTTP ' . $httpCode . ')';
        else $message = '返回异常状态码: HTTP ' . $httpCode;
    }
}

$leads = get_leads();
$webhookFile = DATA_DIR . '/webhook.json';
$webhook = json_read($webhookFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_webhook'])) {
    csrf_verify();
    $webhook = ['url' => $_POST['webhook_url'] ?? '', 'secret' => $_POST['webhook_secret'] ?? ''];
    json_write($webhookFile, $webhook);
    $message = 'Webhook 配置已保存';
}

admin_header('数据导出');
?>
<div class="admin-layout">
  <?php admin_sidebar('export'); ?>
  <div class="main">
    <h1>数据导出与集成</h1>
    <p class="sub">导出线索数据、配置与其他工具的对接</p>

    <?php if (isset($message)): ?><?=msg('success', $message)?><?php endif; ?>

    <div class="stats">
      <div class="stat-card">
        <div class="num"><?=count($leads)?></div>
        <div class="label">总线索数</div>
      </div>
    </div>

    <div class="card">
      <h2>导出线索</h2>
      <div class="flex gap-4">
        <a href="export.php?format=csv" class="btn btn-primary">导出 CSV</a>
        <a href="export.php?format=json" class="btn btn-primary">导出 JSON</a>
        <a href="<?=SITE_URL?>/leads.csv" class="btn btn-ghost" target="_blank">查看原始 CSV</a>
      </div>
    </div>

    <div class="card">
      <h2>Webhook 对接</h2>
      <p class="text-sm text-muted mb-4">配置 Webhook 将线索数据实时推送到其他系统（如 CRM、飞书机器人、企业微信等）</p>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field">
          <label>Webhook URL</label>
          <input type="url" name="webhook_url" value="<?=htmlspecialchars($webhook['url'] ?? '')?>" placeholder="https://hooks.example.com/openflow-leads">
        </div>
        <div class="field">
          <label>Secret (可选)</label>
          <input type="text" name="webhook_secret" value="<?=htmlspecialchars($webhook['secret'] ?? '')?>" placeholder="用于签名验证">
        </div>
        <div class="flex gap-2">
          <button type="submit" name="save_webhook" class="btn btn-primary">保存配置</button>
          <button type="submit" name="test_webhook" class="btn btn-ghost" formaction="export.php?format=webhook-test">测试连接</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h2>API 接口</h2>
      <p class="text-sm text-muted">可通过以下接口供外部系统调用获取数据</p>
      <div class="mt-4" style="display:grid;gap:12px">
        <div>
          <code>GET /api/leads.php?key=YOUR_API_KEY</code>
          <span class="text-sm text-muted" style="margin-left:8px">获取线索列表 (JSON)</span>
        </div>
        <div>
          <code>GET /api/leads.php?key=YOUR_API_KEY&format=csv</code>
          <span class="text-sm text-muted" style="margin-left:8px">获取线索列表 (CSV)</span>
        </div>
        <div>
          <code>GET /api/pages.php?page=index</code>
          <span class="text-sm text-muted" style="margin-left:8px">获取页面内容 (JSON)</span>
        </div>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
