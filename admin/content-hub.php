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

    <div class="hub-tabs" id="hubTabs">
      <?php foreach ($allowed as $k => $t): ?>
        <a class="hub-tab<?= $k === $tab ? ' on' : '' ?>" href="?tab=<?=urlencode($k)?>"><?=htmlspecialchars($t[0])?></a>
      <?php endforeach; ?>
      <div class="hub-actions" id="hubActions"></div>
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
.hub-tabs{display:flex;align-items:flex-end;gap:2px;margin:6px 0 18px;border-bottom:1px solid var(--border)}
.hub-tab{padding:10px 16px;font-size:13.5px;font-weight:600;color:var(--muted);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px;transition:.15s;border-radius:8px 8px 0 0}
.hub-tab:hover{color:var(--fg);background:var(--hover)}
.hub-tab.on{color:var(--accent);border-bottom-color:var(--accent)}
.hub-actions{margin-left:auto;display:flex;gap:8px;padding-bottom:8px}
.hub-actions:empty{display:none}
@media(max-width:840px){.hub-tabs{flex-wrap:wrap}.hub-actions{margin-left:0;width:100%;flex-wrap:wrap;padding:8px 0}}
/* 子页正文的首个 h1 与中心标题重复；子页的操作按钮由 JS 提到 tab 行右侧 */
.hub-tab-body > h1:first-child{display:none}
.hub-tab-body > .flex > h1{display:none}
.hub-sub{font-size:13px;color:var(--muted);margin:-6px 0 14px}
.hub-tab-body .of-subtabs{margin-top:0}
</style>
<script>
// 子页自己的标题行（h1 + 操作按钮）：按钮提到 tab 行右侧，h1 隐藏（与「内容中心」重复）
(function(){var dst=document.getElementById('hubActions'),body=document.querySelector('.hub-tab-body');if(!dst||!body)return;
 var h1=body.querySelector('h1');if(!h1)return;var head=h1.closest('.flex, .v-head, .page-head')||h1.parentElement;
 var btns=head.querySelectorAll('a.btn, button.btn, .lst-actions > *');if(btns.length){var grp=head.querySelector('.lst-actions, .ml-auto, .flex.gap-2');
   var src=(grp&&grp.querySelectorAll('.btn').length===btns.length)?grp:null;
   if(src){while(src.firstChild)dst.appendChild(src.firstChild);} else btns.forEach(function(b){dst.appendChild(b);});}
 var sub=head.querySelector('p, .v-sub');
 if(sub&&sub.textContent.trim()){var d=document.createElement('p');d.className='hub-sub';d.textContent=sub.textContent.trim();body.insertBefore(d,body.firstChild);}
 head.style.display='none';})();
</script>
<?php admin_footer(); ?>
