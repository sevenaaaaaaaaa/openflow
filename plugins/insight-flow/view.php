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
    require_once __DIR__ . '/functions.php';
    $base = insight_flow_cfg($p, 'if_url');
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
        // 三方契约 v1.1 键名 if_url / if_secret / if_api_key；旧键 insight_flow_url / ingest_secret / api_key 作为默认值回显并继续兼容
        'if_url' => ['label' => 'Insight Flow 服务地址', 'type' => 'text',
            'placeholder' => 'http://127.0.0.1:8400', 'default' => (string)$p->get('insight_flow_url', ''),
            'hint' => 'IF 服务地址（本机默认端口 8400；线上经反向代理时填 https://…/inflow）。留空 = 插件不出站'],
        'workspace_id' => ['label' => 'IF 工作区 ID', 'type' => 'text',
            'placeholder' => 'default', 'hint' => '对应 IF 侧的 Workspace id'],
        'if_secret' => ['label' => '共享 HMAC 密钥', 'type' => 'text', 'default' => (string)$p->get('ingest_secret', ''),
            'hint' => '出站签名与入站 inbox 验签共用；与 IF 侧 INSFLOW_INGEST_SECRET / OPENFLOW_IF_SECRET 一致'],
        'if_api_key' => ['label' => 'API Key（可选）', 'type' => 'text', 'default' => (string)$p->get('api_key', ''),
            'hint' => '回源 IF 时作为 X-API-Key；IF 读本站 insights 接口时也可用它'],
        'push_enabled' => ['label' => '启用出站推送', 'type' => 'checkbox', 'hint' => '关闭后所有出站静默停止'],
        'push_cdp_events' => ['label' => '推送 CDP 行为事件', 'type' => 'checkbox', 'hint' => '旅程重建 / 转化漏斗数据'],
        'push_orders' => ['label' => '推送成交事件', 'type' => 'checkbox', 'hint' => 'LTV:CAC / RFM 分层第一方底座'],
    ]); ?>

    <h3>接入状态</h3>
    <p class="sub">
      回读 API（供 GrowthBrain / 自动化画布调用）：<br>
      <code>/api/plugin/insight-flow/insights?severity=high&limit=10</code>（需 X-API-Key；本地库优先，<code>&live=1</code> 回源 IF）<br>
      <code>/api/plugin/insight-flow/insights/{id}</code>（需 X-API-Key）<br>
      洞察入站（IF 推送 insight.upsert，HMAC 验签）：<code>POST /api/plugin/insight-flow/inbox</code><br>
      动作反馈回传：<code>POST /api/plugin/insight-flow/feedback</code>（采纳箱动作带 insight_id 时完成/忽略会自动回传）
    </p>
  </div>
</div>
<?php admin_footer(); ?>
