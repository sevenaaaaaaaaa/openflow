<?php
/**
 * Notion 同步管理 — 全内容类型双向同步
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/NotionSync.php';
require_login();
require_perm('settings');

$cfg = NotionSync::config();
$message = '';
$error = '';

// 保存配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    csrf_verify();
    $cfg['token'] = trim($_POST['token'] ?? '');
    $cfg['parent_page_id'] = trim($_POST['parent_page_id'] ?? '');
    // 保留已有的数据库 ID
    NotionSync::saveConfig($cfg);
    $message = 'Notion 配置已保存';
}

// 推送
if (isset($_GET['push'])) {
    csrf_verify();
    $type = $_GET['push'];
    $r = NotionSync::pushAll($type);
    if ($r['ok']) $message = "✅ {$type} 推送完成：新增 {$r['created']}，更新 {$r['updated']}，共 {$r['total']} 条" . ($r['errors'] ? "（{$r['errors']} 错误）" : '');
    else $error = '推送失败：' . ($r['error'] ?? '未知');
}

// 拉取
if (isset($_GET['pull'])) {
    csrf_verify();
    $type = $_GET['pull'];
    $r = NotionSync::pullAll($type);
    if ($r['ok']) $message = "✅ {$type} 拉取完成：新增 {$r['created']}，更新 {$r['updated']}，共 {$r['total']} 条" . ($r['skipped'] ? "（{$r['skipped']} 跳过）" : '');
    else $error = '拉取失败：' . ($r['error'] ?? '未知');
}

$status = NotionSync::status();
$nc = NotionSync::client();

admin_header('Notion 同步');
?>
<div class="admin-layout">
  <?php admin_sidebar('notion-sync'); ?>
  <div class="main">
    <h1>🔄 Notion 同步</h1>
    <p class="sub">在 Notion Database 中管理内容，双向同步到 OpenFlow</p>
    <?php if ($message): ?><?=msg('success', $message)?><?php endif; ?>
    <?php if ($error): ?><?=msg('error', $error)?><?php endif; ?>

    <!-- 配置 -->
    <div class="card" style="margin-bottom:24px">
      <h2>⚙️ Notion 连接配置</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_config" value="1">
        <div class="field-row">
          <div class="field"><label>Integration Token <span class="hint">secret_...</span></label><input type="password" name="token" value="<?=htmlspecialchars($cfg['token'] ?? '')?>" placeholder="secret_xxxxxxxxxxxxxxx"></div>
          <div class="field"><label>Parent Page ID <span class="hint">存放数据库的页面</span></label><input type="text" name="parent_page_id" value="<?=htmlspecialchars($cfg['parent_page_id'] ?? '')?>" placeholder="32位 Notion Page ID"></div>
        </div>
        <div class="msg msg-info">在 <a href="https://www.notion.so/my-integrations" target="_blank">Notion Integrations</a> 创建 Integration，获取 Token。将 Integration 连接到一个 Notion 页面（Parent Page），数据库会自动创建在该页面下。</div>
        <button type="submit" class="btn btn-primary" style="margin-top:12px">保存配置</button>
      </form>
    </div>

    <!-- 同步状态 -->
    <div class="card" style="margin-bottom:24px">
      <h2>📊 同步状态</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
        <?php
        $icons = ['navigation' => '🧭', 'articles' => '📝', 'courses' => '📚', 'events' => '📅', 'pages' => '📄', 'skills' => '⚡'];
        $labels = ['navigation' => '导航站', 'articles' => '文章', 'courses' => '课程', 'events' => '活动', 'pages' => '落地页', 'skills' => '技能'];
        foreach ($status as $type => $s):
          $ready = $s['sync_ready'];
          $dbStatus = $s['notion_db'];
          $icon = $icons[$type] ?? '📦';
          $label = $labels[$type] ?? $type;
        ?>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:8px">
              <span style="font-size:22px"><?=$icon?></span>
              <strong><?=$label?></strong>
            </div>
            <span class="badge <?=$ready?'badge-green':'badge-gray'?>"><?=$dbStatus?></span>
          </div>
          <div style="display:flex;gap:16px;font-size:13px;color:var(--text-2);margin-bottom:12px">
            <span>本地 <b><?=$s['local_count']?></b> 条</span>
            <span>已映射 <b><?=$s['mapped_count']?></b> 条</span>
          </div>
          <div style="display:flex;gap:8px">
            <?php if ($ready): ?>
            <a href="?push=<?=$type?>&csrf_token=<?=csrf_token()?>" class="btn btn-primary btn-sm" onclick="return confirm('推送 <?=$s['local_count']?> 条 <?=$label?> 到 Notion？')">⬆ 推送到 Notion</a>
            <a href="?pull=<?=$type?>&csrf_token=<?=csrf_token()?>" class="btn btn-ghost btn-sm" onclick="return confirm('从 Notion 拉取 <?=$label?> 到本地？（覆盖同名数据）')">⬇ 从 Notion 拉取</a>
            <?php else: ?>
            <span style="font-size:12px;color:var(--faint)">请先配置 Notion Token + Parent Page ID</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 使用说明 -->
    <div class="card" style="padding:16px">
      <h2 style="margin-bottom:10px">💡 使用说明</h2>
      <table>
        <thead><tr><th>操作</th><th>说明</th></tr></thead>
        <tbody>
          <tr><td><strong>⬆ 推送</strong></td><td>把 OpenFlow 数据同步到 Notion 数据库（新增 + 更新已有）</td></tr>
          <tr><td><strong>⬇ 拉取</strong></td><td>把 Notion 数据同步回 OpenFlow（新增 + 更新已有）</td></tr>
          <tr><td><strong>双向</strong></td><td>推送和拉取都支持增量同步，通过 OpenFlow ID ↔ Notion Page ID 映射自动匹配</td></tr>
          <tr><td><strong>自动建库</strong></td><td>首次推送时自动在 Parent Page 下创建 Notion 数据库（含完整字段结构）</td></tr>
          <tr><td><strong>内容类型</strong></td><td>导航站、文章、课程、活动、落地页、技能 — 六类数据独立数据库</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
