<?php
/**
 * 内容中心 — 文章 / 页面 / 下载 / 播客 四类内容的统一入口
 *
 * 与 seo-center.php 同一套合并机制：子页保留原文件与原逻辑，
 * 被 include 时经 OF_EMBED 跳过自己的外壳，只输出正文。
 */
require_once __DIR__ . '/config.php';
require_login();

/** tab 定义：key => [标题, 子页文件, 所需权限] */
function content_hub_tabs(): array {
    return [
        'articles'  => ['文章',   'articles.php',   'articles'],
        'pages'     => ['页面',   'pages-list.php', 'settings'],
        'downloads' => ['下载',   'downloads.php',  'downloads'],
        'podcasts'  => ['播客视频', 'podcasts.php', 'settings'],
    ];
}

$tabs = content_hub_tabs();
$allowed = [];
foreach ($tabs as $k => $t) {
    if (!function_exists('has_perm') || has_perm($t[2])) $allowed[$k] = $t;
}
if (!$allowed) { require_perm('articles'); }

$tab = $_GET['tab'] ?? array_key_first($allowed);
if (!isset($allowed[$tab])) $tab = array_key_first($allowed);

define('OF_EMBED', 1);

admin_header('内容中心');
?>
<div class="admin-layout">
  <?php admin_sidebar('content-hub'); ?>
  <div class="main">
    <h1>内容中心</h1>
    <p class="sub">文章、页面、下载资料与播客视频，统一在这里管理</p>

    <div class="hub-tabs">
      <?php foreach ($allowed as $k => $t): ?>
        <a class="hub-tab<?= $k === $tab ? ' on' : '' ?>" href="?tab=<?=urlencode($k)?>"><?=htmlspecialchars($t[0])?></a>
      <?php endforeach; ?>
    </div>

    <div class="hub-tab-body">
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
.hub-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:18px 0 22px;border-bottom:1px solid var(--border)}
.hub-tab{padding:9px 15px;border-radius:8px 8px 0 0;font-size:13.5px;font-weight:600;color:var(--text-2);
  text-decoration:none;border:1px solid transparent;border-bottom:none;position:relative;top:1px;transition:.15s}
.hub-tab:hover{background:var(--bg-2);color:var(--text-1)}
.hub-tab.on{background:var(--bg-1);color:var(--primary);border-color:var(--border);border-bottom:1px solid var(--bg-1)}
/* 子页正文的首个 h1 与中心标题重复 */
.hub-tab-body > h1:first-child{display:none}
.hub-tab-body > .flex > h1{display:none}
</style>
<?php admin_footer(); ?>
