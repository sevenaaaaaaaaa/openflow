<?php
/**
 * SEO 中心 — 把原先分散的 7 个 SEO 页面收进一个 tab 页
 *
 * 实现方式：各子页保留原文件与原逻辑，被本页 include 时通过 OF_EMBED
 * 常量跳过自己的 admin_header / sidebar / footer，只输出正文。
 * 好处是子页独立访问仍然可用（旧 URL 走 301 到这里对应 tab），
 * 业务逻辑一行没动，回滚成本极低。
 *
 * 注意：seo-functions.php 是共享函数库不是页面，不在合并范围内。
 */
require_once __DIR__ . '/config.php';
require_login();

/** tab 定义：key => [标题, 子页文件, 所需权限] */
function seo_center_tabs(): array {
    return [
        'pages'      => ['页面 SEO',   'seo.php',             'seo'],
        'tools'      => ['工具',       'seo-tools.php',       'seo-tools'],
        'batch'      => ['批量策略',   'seo-batch.php',       'seo'],
        'console'    => ['站长工具',   'seo-console.php',     'settings'],
        'structured' => ['结构化数据', 'structured-data.php', 'structured'],
        'images'     => ['图片 SEO',   'image-seo.php',       'media'],
        'redirects'  => ['301 重定向', 'redirects.php',       'redirects'],
    ];
}

$tabs = seo_center_tabs();
// 只保留当前管理员有权限的 tab
$allowed = [];
foreach ($tabs as $k => $t) {
    if (!function_exists('has_perm') || has_perm($t[2])) $allowed[$k] = $t;
}
if (!$allowed) { require_perm('seo'); }   // 一个都没有 → 走标准无权限提示

$tab = $_GET['tab'] ?? array_key_first($allowed);
if (!isset($allowed[$tab])) $tab = array_key_first($allowed);

define('OF_EMBED', 1);

admin_header('SEO 中心');
?>
<div class="admin-layout">
  <?php admin_sidebar('seo-center'); ?>
  <div class="main">
    <h1>SEO 中心</h1>
    <p class="sub">页面 SEO、工具、批量策略、站长工具、结构化数据、图片 SEO 与重定向，统一在这里管理</p>

    <div class="seo-tabs">
      <?php foreach ($allowed as $k => $t): ?>
        <a class="seo-tab<?= $k === $tab ? ' on' : '' ?>" href="?tab=<?=urlencode($k)?>"><?=htmlspecialchars($t[0])?></a>
      <?php endforeach; ?>
    </div>

    <div class="seo-tab-body">
      <?php
      $file = __DIR__ . '/' . $allowed[$tab][1];
      if (is_file($file)) {
          include $file;
      } else {
          echo msg('error', '子页面缺失：' . htmlspecialchars($allowed[$tab][1]));
      }
      ?>
    </div>
  </div>
</div>

<style>
.seo-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:18px 0 22px;border-bottom:1px solid var(--border);padding-bottom:0}
.seo-tab{padding:9px 15px;border-radius:8px 8px 0 0;font-size:13.5px;font-weight:600;color:var(--text-2);
  text-decoration:none;border:1px solid transparent;border-bottom:none;position:relative;top:1px;transition:.15s}
.seo-tab:hover{background:var(--bg-2);color:var(--text-1)}
.seo-tab.on{background:var(--bg-1);color:var(--primary);border-color:var(--border);border-bottom:1px solid var(--bg-1)}
/* 子页正文里第一个 h1 与中心标题重复，隐藏之 */
.seo-tab-body > h1:first-child{display:none}
</style>
<?php admin_footer(); ?>
