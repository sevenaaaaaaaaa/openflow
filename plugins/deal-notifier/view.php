<?php
/**
 * 成交播报 · 配置页
 */
require_once __DIR__ . '/../../admin/config.php';
require_once __DIR__ . '/../../lib/PluginSDK.php';
require_login();
require_perm('crm');

$p = plugin('deal-notifier');

// 发一条测试消息，验证 webhook 通不通
$testResult = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__test'])) {
    csrf_verify();
    $url = (string)$p->get('webhook_url', '');
    if ($url === '') {
        $testResult = '还没填 Webhook 地址';
    } else {
        $payload = $p->get('provider', 'wecom') === 'feishu'
            ? ['msg_type' => 'text', 'content' => ['text' => 'OpenFlow 成交播报测试消息']]
            : ['msgtype'  => 'text', 'text'    => ['content' => 'OpenFlow 成交播报测试消息']];
        $r = $p->httpPost($url, $payload);
        $testResult = $r['ok'] ? '测试消息已发出，去群里看看。'
                               : '发送失败：status ' . $r['status'] . ' ' . $r['error'];
    }
}

admin_header('成交播报');
?>
<div class="admin-layout">
  <?php admin_sidebar('deal-notifier'); ?>
  <div class="main">
    <h1>成交播报</h1>
    <p class="sub">CRM 成交、订单支付、退款、分群导入完成时，往群里发一条消息。发送是旁路操作，失败只写日志，不影响业务。</p>

    <?php if ($testResult) echo msg(strpos($testResult, '已发出') !== false ? 'success' : 'error', $testResult); ?>

    <?php
    ob_start();
    $notice = $p->renderSettings([
        'provider' => [
            'label' => '群机器人类型', 'type' => 'select',
            'options' => ['wecom' => '企业微信', 'feishu' => '飞书'],
            'default' => 'wecom',
        ],
        'webhook_url' => [
            'label' => 'Webhook 地址', 'type' => 'text',
            'placeholder' => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=...',
            'hint' => '留空则整个插件静默不发',
        ],
        'min_value' => [
            'label' => '成交播报金额门槛', 'type' => 'number', 'default' => 0,
            'hint'  => '低于此金额的成交不播报，0 表示都播',
        ],
        'notify_orders'  => ['label' => '播报订单支付', 'type' => 'checkbox', 'default' => true],
        'notify_refunds' => ['label' => '播报退款',     'type' => 'checkbox', 'default' => true],
    ]);
    $form = ob_get_clean();
    if ($notice) echo msg('success', $notice);
    echo $form;
    ?>

    <form method="post" style="margin-top:12px">
      <?= csrf_field() ?>
      <button name="__test" value="1" class="btn btn-ghost">发一条测试消息</button>
    </form>

    <div class="card" style="margin-top:20px">
      <h2 style="margin-top:0;font-size:15px">最近日志</h2>
      <?php $lines = $p->tailLog(30); ?>
      <?php if (!$lines): ?>
        <p class="sub" style="margin:0">暂无日志。</p>
      <?php else: ?>
        <pre style="margin:0;font-size:12px;line-height:1.7;white-space:pre-wrap;color:var(--text-2)"><?=htmlspecialchars(implode("\n", $lines))?></pre>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
