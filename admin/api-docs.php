<?php
/**
 * API 文档 — Swagger UI 展示
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/ApiDocs.php';
require_login();
require_perm('settings');

admin_header('API 文档');
?>
<style>
.api-frame{width:100%;height:calc(100vh - 120px);border:none;border-radius:12px;background:#fff}
</style>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0">📖 API 文档</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <a href="/api/v1/docs.json" target="_blank" class="btn btn-ghost btn-sm">📥 下载 OpenAPI JSON</a>
        <a href="/api/v1/docs" target="_blank" class="btn btn-ghost btn-sm">🔗 独立页面</a>
      </div>
    </div>
    <p class="sub">基于 OpenAPI 3.0 规范 · Swagger UI 交互式文档</p>
    <iframe src="/api/v1/docs" class="api-frame"></iframe>
  </div>
</div>
<?php admin_footer(); ?>
