<?php
/**
 * Insight Flow 增长情报 · 设置页
 */
require_once __DIR__ . '/../../admin/config.php';
require_once __DIR__ . '/../../lib/PluginSDK.php';
require_login();
require_perm('settings');

$p = plugin('insight-flow');

$testResult = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__test'])) {
    csrf_verify();
    $base = rtrim((string)$p->get('insight_flow_url', ''), '/');
    if ($base === '') {
        $testResult = '还没填 Insight Flow 地址';
    } else {
        $r = $p->httpGet($base . '/health', [], 5);
        if ($r['ok']) {
            $v = json_decode($r['body'], true);
            $testResult = '连接成功，IF 版本：' . ($v['version'] ?? '?');
        } else {
            $testResult = '连接失败：status ' . $r['status'] . ' ' . $r['error'];
        }
    }
}

admin_header('Insight Flow 增长情报');
?>
<div class="admin-layout">
  <?php admin_sidebar('insight-flow'); ?>
  <div class="main">
    <h1>Insight Flow 增长情报</h1>
    <p class="sub">与 Insight Flow（insFlow）增长情报系统双向打通：本站行为事件出站给 IF 做旅程/漏斗分析；IF 洞察在此回读，供后台卡片与 GrowthBrain 消费。出站是旁路操作，失败只写日志。</p>

    <?php if ($testResult) echo msg(strpos($testResult, '连接成功') !== false ? 'success' : 'error', $testResult); ?>

    <?php echo $p->renderSettings([
        'insight_flow_url' => ['label' => 'Insight Flow 服务地址', 'type' => 'text',
            'placeholder' => 'http://127.0.0.1:8400',
            'hint' => 'IF 服务地址（本机默认端口 8400；线上经反向代理时填 https://…/inflow）'],
        'workspace_id' => ['label' => 'IF 工作区 ID', 'type' => 'text',
            'placeholder' => 'default', 'hint' => '对应 IF 侧的 Workspace id'],
        'ingest_secret' => ['label' => '出站 HMAC 密钥', 'type' => 'text',
            'hint' => '与 IF 侧 INSFLOW_INGEST_SECRET 一致；出站事件用它签名'],
        'api_key' => ['label' => 'IF API Key（可选）', 'type' => 'text',
            'hint' => 'IF 开启 API Key 认证后填写，用于回读洞察'],
        'push_enabled' => ['label' => '启用出站推送', 'type' => 'checkbox', 'hint' => '关闭后所有出站静默停止'],
        'push_cdp_events' => ['label' => '推送 CDP 行为事件', 'type' => 'checkbox', 'hint' => '旅程重建 / 转化漏斗数据'],
        'push_orders' => ['label' => '推送成交事件', 'type' => 'checkbox', 'hint' => 'LTV:CAC / RFM 分层第一方底座'],
    ]); ?>

    <h3>接入状态</h3>
    <p class="sub">
      回读 API（供 GrowthBrain / 自动化画布调用）：<br>
      <code>/api/plugin/insight-flow/insights?severity=high&limit=10</code><br>
      <code>/api/plugin/insight-flow/insights/{id}</code><br>
      动作反馈回传：<code>POST /api/plugin/insight-flow/feedback</code>
    </p>
  </div>
</div>
<?php admin_footer(); ?>
