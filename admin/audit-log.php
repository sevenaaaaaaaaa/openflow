<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../lib/AuditLog.php';
require_login();
require_perm('settings');

// 操作
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    csrf_verify();
    AuditLog::clear();
    header('Location: audit-log.php?msg=日志已清空');
    exit;
}

if (!empty($_GET['msg'])) $message = $_GET['msg'];

// 筛选
$filterCategory = $_GET['category'] ?? '';
$filterUser = $_GET['user'] ?? '';
$filterSearch = $_GET['search'] ?? '';

if ($filterSearch) {
    $logs = AuditLog::search($filterSearch);
} elseif ($filterCategory) {
    $logs = AuditLog::byCategory($filterCategory);
} elseif ($filterUser) {
    $logs = AuditLog::byUser($filterUser);
} else {
    $logs = AuditLog::recent(200);
}

$stats = AuditLog::stats();

admin_header('审计日志');
?>
<div class="admin-layout">
  <?php admin_sidebar('settings'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-2">
      <h1 style="margin-bottom:0">审计日志</h1>
      <div style="margin-left:auto;display:flex;gap:8px">
        <a href="?clear=1&csrf_token=<?=csrf_token()?>" class="btn btn-danger btn-sm" onclick="return confirm('确定清空所有日志？')">清空日志</a>
      </div>
    </div>
    <p class="sub">记录所有管理后台操作，便于安全审计和问题追踪</p>
    <?php if (!empty($message)): ?><?=msg('success', $message)?><?php endif; ?>

    <!-- 统计 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
      <div class="cdp-stat"><div class="num" style="color:var(--accent)"><?=$stats['total']?></div><div class="lab">总日志数</div></div>
      <div class="cdp-stat"><div class="num" style="color:var(--ok)"><?=$stats['today']?></div><div class="lab">今日操作</div></div>
      <div class="cdp-stat"><div class="num" style="color:var(--accent)"><?=$stats['this_week']?></div><div class="lab">本周操作</div></div>
    </div>

    <!-- 筛选 -->
    <div class="card">
      <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="field" style="margin-bottom:0;flex:1;min-width:200px">
          <label>搜索</label>
          <input type="text" name="search" value="<?=htmlspecialchars($filterSearch)?>" placeholder="操作、用户、URL..." class="inp" style="height:36px">
        </div>
        <div class="field" style="margin-bottom:0;min-width:140px">
          <label>分类</label>
          <select name="category" class="inp" style="height:36px">
            <option value="">全部分类</option>
            <?php foreach (array_keys($stats['by_category']) as $cat): ?>
            <option value="<?=$cat?>" <?=$filterCategory===$cat?'selected':''?>><?=$cat?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin-bottom:0;min-width:140px">
          <label>用户</label>
          <select name="user" class="inp" style="height:36px">
            <option value="">全部用户</option>
            <?php foreach (array_keys($stats['by_user']) as $u): ?>
            <option value="<?=$u?>" <?=$filterUser===$u?'selected':''?>><?=$u?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="height:36px">筛选</button>
      </form>
    </div>

    <!-- 日志列表 -->
    <div class="card">
      <div style="max-height:700px;overflow-y:auto">
        <table>
          <thead><tr><th style="min-width:140px">时间</th><th>用户</th><th>分类</th><th>操作</th><th>URL</th><th>IP</th></tr></thead>
          <tbody>
          <?php foreach (array_reverse($logs) as $log): ?>
          <tr>
            <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?=$log['timestamp']?></td>
            <td style="font-weight:600;font-size:13px"><?=htmlspecialchars($log['user'])?></td>
            <td><span class="pill gray"><?=htmlspecialchars($log['category'])?></span></td>
            <td style="font-size:13px"><?=htmlspecialchars($log['action'])?></td>
            <td style="font-size:12px;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($log['url'] ?? '')?>"><?=htmlspecialchars($log['url'] ?? '')?></td>
            <td style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($log['ip'] ?? '')?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?>
          <tr><td colspan="6" class="empty">暂无日志</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
