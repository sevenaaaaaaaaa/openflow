<?php
/**
 * 埋点防火墙 · 配置页
 */
require_once __DIR__ . '/../../admin/config.php';
require_once __DIR__ . '/../../lib/PluginSDK.php';
require_login();
require_perm('cdp');

$p = plugin('event-firewall');

admin_header('埋点防火墙');
?>
<div class="admin-layout">
  <?php admin_sidebar('event-firewall'); ?>
  <div class="main">
    <h1>埋点防火墙</h1>
    <p class="sub">
      在行为事件入库<b>之前</b>拦掉爬虫、内部流量和噪音事件。被丢弃的事件不会进任何统计，
      也<b>无法找回</b>——所有拦截项默认关闭，开之前先想清楚。每丢一条都会写进下方日志。
    </p>

    <?php
    ob_start();
    $notice = $p->renderSettings([
        'block_bots' => [
            'label' => '拦截爬虫', 'type' => 'checkbox',
            'hint'  => '按 User-Agent 匹配 bot/crawler/spider/curl 等',
        ],
        'internal_ips' => [
            'label' => '内部 IP 前缀', 'type' => 'text',
            'placeholder' => '192.168.,10.0.,203.0.113.7',
            'hint'  => '逗号分隔，前缀匹配。留空不拦',
        ],
        'blocked_events' => [
            'label' => '丢弃的事件名', 'type' => 'text',
            'placeholder' => 'heartbeat,ping,debug',
            'hint'  => '逗号分隔，精确匹配。留空不拦',
        ],
        'strip_query' => [
            'label' => '剥离 URL 上的敏感参数', 'type' => 'checkbox',
            'hint'  => '事件照常入库，只是把参数擦掉',
        ],
        'strip_params' => [
            'label' => '要剥离的参数名', 'type' => 'text',
            'default' => 'token,password,secret,code',
            'hint'  => '逗号分隔',
        ],
    ]);
    $form = ob_get_clean();
    if ($notice) echo msg('success', $notice);
    echo $form;
    ?>

    <div class="card" style="margin-top:20px">
      <h2 style="margin-top:0;font-size:15px">拦截日志（最近 50 条）</h2>
      <?php $lines = $p->tailLog(50); ?>
      <?php if (!$lines): ?>
        <p class="sub" style="margin:0">还没有拦截过任何事件。</p>
      <?php else: ?>
        <pre style="margin:0;font-size:12px;line-height:1.7;white-space:pre-wrap;color:var(--text-2)"><?=htmlspecialchars(implode("\n", $lines))?></pre>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
