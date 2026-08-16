<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('settings');

// Extract the UTM tool's body content and inline styles
$utmHtml = file_get_contents(__DIR__ . '/../utm-tool.html');
if (!$utmHtml) {
    // Fallback: try the user's file
    $utmHtml = file_get_contents('/Users/sevenaaaaaaa/Downloads/utm-custom-tool.html');
}

// Extract styles and body content
preg_match('/<style>([\s\S]*?)<\/style>/i', $utmHtml, $styleMatch);
$utmStyles = $styleMatch[1] ?? '';

preg_match('/<body>([\s\S]*?)<\/body>/i', $utmHtml, $bodyMatch);
$utmBody = $bodyMatch[1] ?? '';

admin_header('UTM 链接生成器');
?>
<style>
.utm-tool-wrap{max-width:960px}
.utm-tool-wrap .container{max-width:100%!important;margin:0!important;padding:0!important}
<?=preg_replace('/body\s*\{[^}]*\}/', '', $utmStyles)?>
.utm-tool-wrap .tabs,.utm-tool-wrap .tab-panel{clear:both}
</style>
<div class="admin-layout">
  <?php admin_sidebar('utm-builder'); ?>
  <div class="main" style="max-width:none">
    <div class="utm-tool-wrap">
      <?=$utmBody ?: '<p class="text-sm text-muted">UTM 工具加载中...请确保 utm-tool.html 文件存在于网站根目录。</p>'?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
