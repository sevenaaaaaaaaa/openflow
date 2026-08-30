<?php
/**
 * SEO 增强 · 配置页
 *
 * 演示 SDK 的配置页脚手架：字段声明出来，表单渲染与保存都交给 SDK，
 * 插件这边只管页面外壳和想额外展示的东西（这里是最近日志）。
 */
require_once __DIR__ . '/../../admin/config.php';
require_once __DIR__ . '/../../lib/PluginSDK.php';
require_login();
require_perm('seo');

$p = plugin('seo-enhancer');

admin_header('SEO 增强');
?>
<div class="admin-layout">
  <?php admin_sidebar('seo-enhancer'); ?>
  <div class="main">
    <h1>SEO 增强</h1>
    <p class="sub">文章保存时自动补全留空的 SEO 字段；发布时推送搜索引擎收录。已经填过的字段不会被改写。</p>

    <?php
    // renderSettings 会处理 POST 保存并输出表单，返回提示文案
    ob_start();
    $notice = $p->renderSettings([
        'desc_length' => [
            'label' => 'SEO 描述长度', 'type' => 'number', 'default' => 120,
            'hint'  => '描述留空时从正文抽多少字',
        ],
        'title_warn_length' => [
            'label' => '标题超长提醒阈值', 'type' => 'number', 'default' => 60,
            'hint'  => '只写日志提醒，不会自动截断标题',
        ],
        'site_host' => [
            'label' => '站点域名', 'type' => 'text',
            'placeholder' => 'nownexts.com', 'hint' => '用于拼接收录推送的文章地址',
        ],
        'indexnow_key' => [
            'label' => 'IndexNow Key', 'type' => 'text',
            'hint'  => '留空则不推送收录',
        ],
    ]);
    $form = ob_get_clean();
    if ($notice) echo msg('success', $notice);
    echo $form;
    ?>

    <div class="card" style="margin-top:20px">
      <h2 style="margin-top:0;font-size:15px">最近日志</h2>
      <?php $lines = $p->tailLog(30); ?>
      <?php if (!$lines): ?>
        <p class="sub" style="margin:0">暂无日志。</p>
      <?php else: ?>
        <pre style="margin:0;font-size:12px;line-height:1.7;white-space:pre-wrap;color:var(--text-2)"><?php
          echo htmlspecialchars(implode("\n", $lines));
        ?></pre>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
